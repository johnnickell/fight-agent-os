<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Action\Api\V1\Invitations\IssueInvitationAction;
use App\Adapter\Http\Action\Api\V1\Invitations\ReadInvitationStatusAction;
use App\Adapter\Http\Responder\Api\V1\Invitations\IssueInvitationResponder;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Application\Invitation\InvitationRateLimiter;
use App\Application\Invitation\IssueInvitation;
use App\Application\Invitation\ReadInvitationStatus;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\InvitationRatePolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessTokens;
use Tooling\OpenApi\Document;

/**
 * Exercises actual invitation routes, current PostgreSQL authority, package delivery and redaction
 */
final class InvitationApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $session;
    private string $token;
    /** @var array<string, string|false> */
    private array $environment = [];
    private int $constructions = 0;
    private int $providerCalls = 0;
    /** @var list<array{bool, int}> */
    private array $providerObservations = [];
    private CredentialDeliveryOutcome $providerOutcome = CredentialDeliveryOutcome::RETRYABLE_FAILURE;
    private bool $providerThrows = false;
    private TestHandler $logs;

    /**
     * Boots real routes on a separately guarded test database with an active package-created session
     */
    protected function setUp(): void
    {
        foreach (['DATABASE_URL', 'APP_ACCESS_TOKEN_CONFIG', 'APP_CREDENTIAL_DELIVERY_KEY'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement(
            'TRUNCATE users, roles, permissions, audit_evidence, invitation_rate_limits CASCADE'
        );
        $tokens = new AccessTokens();
        $tokens->clock->time = new DateTimeImmutable();
        $now = $tokens->clock->now()->modify('-1 minute');
        putenv('DATABASE_URL='.$url);
        putenv('APP_ACCESS_TOKEN_CONFIG='.json_encode($tokens->configuration, JSON_THROW_ON_ERROR));
        putenv('APP_CREDENTIAL_DELIVERY_KEY='.bin2hex(random_bytes(32)));
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $container->get(CommandBus::class)->execute(new ReconcileManagedPolicy(InstallationManagedPolicy::create()));
        $this->actor = User::invite(UserId::generate(), EmailAddress::fromString('actor@example.test'), $now);
        $this->actor->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT)),
            $now
        );
        $this->actor->assignRole(RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID), $now);
        $container->get(Connection::class)->transactional(
            fn() => $container->get(UserRepository::class)->add($this->actor)
        );
        $this->session = RefreshSession::start(
            RefreshSessionId::generate(),
            $this->actor->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            $this->actor->getAuthenticationVersion(),
            false
        );
        $this->connection->transactional(fn() => RefreshSessionRecords::insert($this->connection, $this->session));
        $claims = $tokens->claims();
        $claims['sub'] = $this->actor->getId()->toString();
        $claims['sid'] = $this->session->getId()->toString();
        $claims['auth_version'] = $this->actor->getAuthenticationVersion();
        $this->token = $tokens->signed(json_encode($claims, JSON_THROW_ON_ERROR));
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $provider = $this->createStub(CredentialDeliveryProvider::class);
        $provider->method('deliver')->willReturnCallback(function (
            CredentialDeliveryInvocation $invocation
        ): CredentialDeliveryOutcome {
            ++$this->providerCalls;
            $this->providerObservations[] = [
                $this->container->get(Connection::class)->isTransactionActive(),
                (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM user_role_assignments ur JOIN users u ON u.id = ur.user_id WHERE u.email = ?',
                    [$invocation->getEmail()->toString()]
                )
            ];
            if ($this->providerThrows) {
                throw new RuntimeException('SQL password=private-provider-marker https://secret.test/credential');
            }

            return $this->providerOutcome;
        });
        $container->set(CredentialDeliveryProvider::class, static fn(): CredentialDeliveryProvider => $provider);
        $container->set(IssueInvitationAction::class, function () use ($container): IssueInvitationAction {
            ++$this->constructions;

            return new IssueInvitationAction(
                $container->get(IssueInvitation::class),
                $container->get(IssueInvitationResponder::class)
            );
        });
        $container->set(ReadInvitationStatusAction::class, function () use ($container): ReadInvitationStatusAction {
            ++$this->constructions;

            return new ReadInvitationStatusAction(
                $container->get(ReadInvitationStatus::class),
                $container->get(ReadInvitationStatusResponder::class)
            );
        });
    }

    /**
     * Removes only fixture fault injection and restores boot environment
     */
    protected function tearDown(): void
    {
        foreach (['user_role_assignments', 'audit_evidence'] as $table) {
            $this->connection->executeStatement(
                'ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS invitation_api_abort'
            );
        }
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Confirms exact managed origin and independently committed delivery without credential exposure
     */
    #[DataProvider('successfulModes')]
    public function testIssuance(string $mode, string $roleId): void
    {
        $response = $this->issue([
            'email'               => 'NEW.User@EXAMPLE.test',
            'mode'                => $mode,
            'confirm_super_admin' => true
        ]);
        $data = $this->assertResponse($response, 201)['data'];
        self::assertSame('confirmed', $data['assignment']);
        self::assertSame('confirmed', $data['audit']);
        self::assertSame('retry_pending', $data['delivery']['status']);
        self::assertSame([$roleId], $this->connection->fetchFirstColumn(
            'SELECT role_id FROM user_role_assignments WHERE user_id = ?',
            [$data['user_id']]
        ));
        $user = $this->connection->fetchAssociative(
            'SELECT email, state, password_hash FROM users WHERE id = ?',
            [$data['user_id']]
        );
        self::assertSame([
            'email'         => 'new.user@example.test',
            'state'         => 'pending_activation',
            'password_hash' => null
        ], $user);
        $grant = $this->connection->fetchAssociative(
            'SELECT * FROM activation_grants WHERE user_id = ?',
            [$data['user_id']]
        );
        self::assertIsArray($grant);
        $created = new DateTimeImmutable((string) $this->connection->fetchOne(
            'SELECT created_at FROM users WHERE id = ?',
            [$data['user_id']]
        ));
        $duration = (new DateTimeImmutable($grant['expires_at']))->getTimestamp() - $created->getTimestamp();
        self::assertSame(7 * 86400, $duration);
        self::assertSame([[false, 0]], $this->providerObservations);
        $audit = $this->connection->fetchAllAssociative(
            'SELECT actor_id, subject_id, action, context FROM audit_evidence WHERE subject_id = ? ORDER BY action',
            [$data['user_id']]
        );
        self::assertContains('user.invited', array_column($audit, 'action'));
        self::assertContains('user.invitation_role_confirmed', array_column($audit, 'action'));
        foreach ($audit as $row) {
            self::assertSame($this->actor->getId()->toString(), $row['actor_id']);
        }
        $status = $this->request('GET', '/api/v1/invitations/'.$data['user_id']);
        self::assertSame($data['delivery'], $this->assertResponse($status, 200, false)['data']);
        $privateValues = [
            $grant['credential_digest'], $grant['delivery_ciphertext'],
            $this->token, $this->session->getId()->toString()
        ];
        foreach ($privateValues as $private) {
            self::assertStringNotContainsString(
                $private,
                (string) $response->getBody().(string) $status->getBody().json_encode($audit, JSON_THROW_ON_ERROR)
            );
        }
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies only the two approved managed destinations
     *
     * @return iterable<array{string, string}>
     */
    public static function successfulModes(): iterable
    {
        yield ['ordinary', InstallationManagedPolicy::USER_ROLE_ID];
        yield ['super_admin', InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID];
    }

    /**
     * Rejects malformed, ambiguous, undeclared and spoofed JSON before Action construction or side effects
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBody(
        string $body,
        string $contentType = 'application/json',
        string $query = '',
        int $expected = 400
    ): void {
        $response = $this->request('POST', '/api/v1/invitations'.$query, $body, $contentType);
        $this->assertResponse($response, $expected);
        self::assertSame(0, $this->constructions);
        $this->assertNoOrigin();
        self::assertSame('0', (string) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies the first real body-bearing route matrix without synthetic production routes
     *
     * @return iterable<string, array{0: string, 1?: string, 2?: string, 3?: int}>
     */
    public static function invalidBodies(): iterable
    {
        $bodies = [
            'empty'                => '',
            'malformed'            => '{',
            'list'                 => '[]',
            'scalar'               => 'true',
            'null'                 => 'null',
            'duplicate'            => '{"email":"a@example.test","email":"b@example.test","mode":"ordinary"}',
            'escaped-duplicate'    => '{"email":"a@example.test","mode":"ordinary","m\\u006fde":"super_admin"}',
            'unknown'              => '{"email":"a@example.test","mode":"ordinary","actor_id":"private-marker"}',
            'role'                 => '{"email":"a@example.test","mode":"ordinary","role":"ROLE_SUPER_ADMIN"}',
            'permissions'          => '{"email":"a@example.test","mode":"ordinary","permissions":"ASSIGN_SUPER_ADMIN"}',
            'missing-email'        => '{"mode":"ordinary"}',
            'wrong-email-type'     => '{"email":123,"mode":"ordinary"}',
            'bad-email'            => '{"email":"private-marker","mode":"ordinary"}',
            'unicode-email'        => '{"email":"üser@example.test","mode":"ordinary"}',
            'null-email'           => '{"email":null,"mode":"ordinary"}',
            'nested'               => '{"email":{"value":"a@example.test"},"mode":"ordinary"}',
            'bad-mode'             => '{"email":"a@example.test","mode":"ROLE_USER"}',
            'null-mode'            => '{"email":"a@example.test","mode":null}',
            'truthy'               => '{"email":"a@example.test","mode":"super_admin","confirm_super_admin":"true"}',
            'numeric-confirmation' => '{"email":"a@example.test","mode":"super_admin","confirm_super_admin":1}',
            'null-confirmation'    => '{"email":"a@example.test","mode":"super_admin","confirm_super_admin":null}',
            'oversized-email'      => json_encode(
                ['email' => str_repeat('a', 255).'@example.test', 'mode' => 'ordinary'],
                JSON_THROW_ON_ERROR
            )
        ];
        foreach ($bodies as $name => $body) {
            yield $name => [$body];
        }
        $valid = '{"email":"a@example.test","mode":"ordinary"}';
        // The existing Bearer transport gate rejects bodies above 16 KiB before input validation.
        yield 'oversized-authentication-body' => [str_repeat(' ', 16385), 'application/json', '', 401];
        yield 'oversized-json-body' => [str_repeat(' ', 65537), 'application/json', '', 401];
        yield 'wrong-content-type' => [$valid, 'text/plain'];
        yield 'query-input' => [$valid, 'application/json', '?actor_id=private-marker'];
    }

    /**
     * Requires literal elevation confirmation and returns safe semantic input failure
     *
     * @param array<string, mixed> $body
     */
    #[DataProvider('confirmationBodies')]
    public function testConfirmation(array $body): void
    {
        $this->assertResponse($this->issue($body), 422);
        $this->assertNoOrigin();
    }

    /**
     * Supplies absent and false confirmation
     *
     * @return iterable<array{array<string, mixed>}>
     */
    public static function confirmationBodies(): iterable
    {
        yield [['email' => 'new@example.test', 'mode' => 'super_admin']];
        yield [['email' => 'new@example.test', 'mode' => 'super_admin', 'confirm_super_admin' => false]];
    }

    /**
     * Enforces real-route permission gates before construction and refuses stale sessions
     */
    #[DataProvider('authorityFailures')]
    public function testAuthority(string $case, bool $status, int $expected): void
    {
        if ($case === 'permission') {
            $name = $status ? 'MANAGE_USER_INVITATIONS' : 'INVITE_USERS';
            $this->connection->executeStatement(
                'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = ?)',
                [$name]
            );
        } elseif ($case === 'revoked') {
            $this->connection->executeStatement(
                'UPDATE refresh_sessions SET revoked = true WHERE id = ?',
                [$this->session->getId()->toString()]
            );
        } elseif ($case === 'stale') {
            $this->connection->executeStatement(
                'UPDATE users SET authentication_version = authentication_version + 1 WHERE id = ?',
                [$this->actor->getId()->toString()]
            );
        }
        $response = $this->request(
            $status ? 'GET' : 'POST',
            $status ? '/api/v1/invitations/'.UserId::generate() : '/api/v1/invitations',
            $status ? null : '{"email":"new@example.test","mode":"ordinary"}',
            $status ? null : 'application/json',
            $case !== 'anonymous'
        );
        $this->assertResponse($response, $expected, !$status);
        self::assertSame(0, $this->constructions);
        $this->assertNoOrigin();
    }

    /**
     * Supplies authenticated permission, missing authentication and stale authority cases for both routes
     *
     * @return iterable<array{string, bool, int}>
     */
    public static function authorityFailures(): iterable
    {
        foreach ([false, true] as $status) {
            foreach (['permission', 'anonymous', 'revoked', 'stale'] as $case) {
                yield [$case, $status, $case === 'permission' ? 403 : 401];
            }
        }
    }

    /**
     * Rechecks elevated authority in Application before origin dispatch
     */
    public function testMissingElevationPermission(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = ?)',
            ['ASSIGN_SUPER_ADMIN']
        );
        $this->assertResponse($this->issue([
            'email'               => 'new@example.test',
            'mode'                => 'super_admin',
            'confirm_super_admin' => true
        ]), 403);
        $this->assertNoOrigin();
        self::assertSame('0', (string) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
    }

    /**
     * Preserves invitation after assignment rollback and preserves membership after separate audit rollback
     */
    #[DataProvider('partialFailures')]
    public function testPartialFailure(string $table, string $condition, string $assignment): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT invitation_api_abort CHECK ('.$condition.') NOT VALID'
        );
        $response = $this->issue(['email' => 'new@example.test', 'mode' => 'ordinary']);
        $data = $this->assertResponse($response, 503)['data'];
        self::assertIsString($data['user_id']);
        self::assertSame($assignment, $data['assignment']);
        self::assertSame('unconfirmed', $data['audit']);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'user.invitation_role_confirmed'"
        ));
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies independently failing assignment and mandatory confirmation audit transactions
     *
     * @return iterable<array{string, string, string}>
     */
    public static function partialFailures(): iterable
    {
        yield ['user_role_assignments', 'false', 'unconfirmed'];
        yield ['audit_evidence', "action <> 'user.invitation_role_confirmed'", 'confirmed'];
    }

    /**
     * Distinguishes provider failure and acceptance without claiming inbox receipt
     */
    #[DataProvider('deliveryOutcomes')]
    public function testDelivery(CredentialDeliveryOutcome $outcome, bool $throws, string $status): void
    {
        $this->providerOutcome = $outcome;
        $this->providerThrows = $throws;
        $data = $this->assertResponse($this->issue(['email' => 'new@example.test', 'mode' => 'ordinary']), 201)['data'];
        self::assertSame($status, $data['delivery']['status']);
        self::assertSame(1, $this->providerCalls);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Supplies qualified provider attempt outcomes
     *
     * @return iterable<array{CredentialDeliveryOutcome, bool, string}>
     */
    public static function deliveryOutcomes(): iterable
    {
        yield [CredentialDeliveryOutcome::DELIVERED, false, 'delivered'];
        yield [CredentialDeliveryOutcome::PERMANENT_FAILURE, false, 'permanent_failure'];
        yield [CredentialDeliveryOutcome::RETRYABLE_FAILURE, true, 'retry_pending'];
    }

    /**
     * Reports canonical duplicates and installation policy drift without a second origin
     */
    public function testConflictAndPolicy(): void
    {
        $this->assertResponse($this->issue(['email' => 'New@example.test', 'mode' => 'ordinary']), 201);
        $this->assertResponse($this->issue(['email' => 'NEW@EXAMPLE.test', 'mode' => 'ordinary']), 409);
        $this->connection->executeStatement(
            "UPDATE roles SET name = 'ROLE_DRIFTED' WHERE id = ?",
            [InstallationManagedPolicy::USER_ROLE_ID]
        );
        $this->assertResponse($this->issue(['email' => 'other@example.test', 'mode' => 'ordinary']), 409);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
    }

    /**
     * Returns controlled atomic retry metadata and keeps status query/body input undeclared
     */
    public function testThrottleAndStatusInput(): void
    {
        $container = $this->container;
        $container->set(InvitationRateLimiter::class, static fn(): InvitationRateLimiter =>
            new PostgresInvitationRateLimiter(
                $container->get(Connection::class),
                $container->get(Clock::class),
                new InvitationRatePolicy(60, 1, 1)
            ));
        $data = $this->assertResponse($this->issue(['email' => 'new@example.test', 'mode' => 'ordinary']), 201)['data'];
        $response = $this->issue(['email' => 'other@example.test', 'mode' => 'ordinary']);
        $this->assertResponse($response, 429);
        self::assertGreaterThanOrEqual(1, (int) $response->getHeaderLine('Retry-After'));
        self::assertLessThanOrEqual(60, (int) $response->getHeaderLine('Retry-After'));
        $path = '/api/v1/invitations/'.$data['user_id'];
        $this->assertResponse($this->request('GET', $path.'?user_id=private-marker'), 400, false);
        $this->assertResponse($this->request('GET', $path, '{}', 'application/json'), 400, false);
        $this->assertResponse($this->request('GET', $path), 200, false);
        $this->assertResponse($this->request('GET', $path), 429, false);
    }

    /**
     * Keeps missing generations and malformed target identifiers safe and explicit
     */
    public function testMissingStatus(): void
    {
        $this->assertResponse($this->request('GET', '/api/v1/invitations/'.UserId::generate()), 404, false);
        $this->assertResponse($this->request('GET', '/api/v1/invitations/private-marker'), 400, false);
        $this->assertNoOrigin();
    }

    /**
     * Resolves concurrent canonical requests through separate processes after both preflight checks
     */
    public function testConcurrentCanonicalInvitations(): void
    {
        $children = [];
        $released = false;
        try {
            for ($index = 0; $index < 2; ++$index) {
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\InvitationRaceWorker::run();'
                ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode([
                    'operation' => 'issue',
                    'actor'     => $this->actor->getId()->toString(),
                    'session'   => $this->session->getId()->toString(),
                    'version'   => $this->actor->getAuthenticationVersion()
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
            }
            foreach ($children as [$process, $pipes]) {
                self::assertSame('ready', trim((string) fgets($pipes[1])));
            }
            foreach ($children as [$process, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
            }
            $released = true;
            $results = [];
            foreach ($children as [$process, $pipes]) {
                $results[] = trim((string) fgets($pipes[1]));
            }
            sort($results);
            self::assertSame(['conflict', 'issued'], $results);
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM users WHERE email = 'race@example.test'"
            ));
            self::assertSame(1, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM audit_evidence WHERE action = 'user.invitation_role_confirmed'"
            ));
        } finally {
            foreach ($children as [$process, $pipes]) {
                if (!$released) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                self::assertSame(0, proc_close($process));
            }
        }
    }

    /**
     * Sends one checked body request through actual routing
     *
     * @param array<string, mixed> $body
     */
    private function issue(array $body): ResponseInterface
    {
        return $this->request(
            'POST',
            '/api/v1/invitations',
            json_encode($body, JSON_THROW_ON_ERROR),
            'application/json'
        );
    }

    /**
     * Preserves raw source bytes and headers for input and authentication boundary checks
     */
    private function request(
        string $method,
        string $path,
        ?string $body = null,
        ?string $contentType = null,
        bool $authenticated = true
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://agent-os.test'.$path);
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
        }
        if ($body !== null) {
            $request->getBody()->write($body);
        }
        if ($contentType !== null) {
            $request = $request->withHeader('Content-Type', $contentType);
        }

        return $this->app->handle($request);
    }

    /**
     * Validates actual responses against the owning OpenAPI contract and scans public diagnostics
     *
     * @return array<string, mixed>
     */
    private function assertResponse(ResponseInterface $response, int $status, bool $issue = true): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress(
                $issue ? '/api/v1/invitations' : '/api/v1/invitations/{user_id}',
                $issue ? 'post' : 'get'
            ),
            $response
        );
        $public = (string) $response->getBody();
        $public .= json_encode($response->getHeaders(), JSON_THROW_ON_ERROR);
        $public .= json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        $privateValues = [
            'private-marker', 'private-provider-marker', 'SQL ', 'password=', 'https://secret.test', $this->token
        ];
        foreach ($privateValues as $private) {
            self::assertStringNotContainsString($private, $public);
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Proves refusals leave no target, credential generation or provider effect
     */
    private function assertNoOrigin(): void
    {
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        self::assertSame(0, $this->providerCalls);
    }
}
