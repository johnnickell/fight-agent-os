<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use App\Adapter\Persistence\RefreshSessionRecords;
use App\Application\Invitation\InvitationRateLimiter;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\InvitationRatePolicy;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use LogicException;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessClock;
use Tests\Support\AccessTokens;
use Tooling\OpenApi\Document;

/**
 * Proves real recovery routes against current PostgreSQL authority and package-owned transitions
 */
final class InvitationRecoveryApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private User $actor;
    private RefreshSession $session;
    private string $token;
    private UserId $target;
    private int $providerCalls = 0;
    private string $credential;
    private CredentialDeliveryOutcome $outcome = CredentialDeliveryOutcome::RETRYABLE_FAILURE;
    private bool $providerThrows = false;
    /** @var list<array{bool, int}> */
    private array $providerObservations = [];
    private TestHandler $logs;
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Boots real HTTP and seeds a failed invitation through the accepted issuance API
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
        $this->actor->activate(PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT)), $now);
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
        $provider = $this->createStub(CredentialDeliveryProvider::class);
        $provider->method('deliver')->willReturnCallback(function (
            CredentialDeliveryInvocation $invocation
        ): CredentialDeliveryOutcome {
            ++$this->providerCalls;
            $this->credential = $invocation->getCredential();
            $this->providerObservations[] = [
                $this->container->get(Connection::class)->isTransactionActive(),
                (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM activation_grants WHERE delivery_id = ?',
                    [$invocation->getIdempotencyId()]
                )
            ];
            if ($this->providerThrows) {
                throw new RuntimeException('private-provider-marker SQL password=secret https://secret.test/grant');
            }

            return $this->outcome;
        });
        $container->set(CredentialDeliveryProvider::class, static fn(): CredentialDeliveryProvider => $provider);
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $issued = $this->request('POST', '/api/v1/invitations', '{"email":"pending@example.test","mode":"ordinary"}');
        self::assertSame(201, $issued->getStatusCode(), (string) $issued->getBody());
        $data = json_decode((string) $issued->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        $this->target = UserId::fromString($data['user_id']);
        $this->connection->executeStatement('TRUNCATE invitation_rate_limits');
    }

    /**
     * Removes owned failure injection and restores process environment
     */
    protected function tearDown(): void
    {
        foreach (['users', 'activation_grants', 'audit_evidence'] as $table) {
            $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS recovery_abort');
        }
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Preserves retry identity and records safe actor/target audit after each failed provider attempt
     */
    public function testRetry(): void
    {
        $before = $this->grant();
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $response = $this->recover('retry');
            $data = $this->assertResponse($response, 200, 'retry')['data'];
            self::assertSame('retry_pending', $data['status']);
            $after = $this->grant();
            self::assertSame($before['id'], $after['id']);
            self::assertSame($before['delivery_id'], $after['delivery_id']);
            self::assertSame($before['credential_digest'], $after['credential_digest']);
            self::assertSame($before['expires_at'], $after['expires_at']);
            self::assertGreaterThan((int) $before['revision'], (int) $after['revision']);
            self::assertSame(2 + $attempt, $this->providerCalls);
        }
        self::assertSame(2, $this->auditCount('user.invitation_delivery.retry_requested'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
    }

    /**
     * Atomically replaces a grant and preserves assignments while correction canonicalizes the address
     */
    #[DataProvider('replacementOperations')]
    public function testReplacement(string $operation, string $audit): void
    {
        $before = $this->grant();
        $roles = $this->connection->fetchFirstColumn(
            'SELECT role_id FROM user_role_assignments WHERE user_id = ?',
            [$this->target->toString()]
        );
        $this->assertResponse($this->recover($operation), 200, $operation);
        $after = $this->grant();
        self::assertNotSame($before['id'], $after['id']);
        self::assertNotSame($before['delivery_id'], $after['delivery_id']);
        self::assertNotSame($before['credential_digest'], $after['credential_digest']);
        self::assertSame($before['id'], $after['predecessor_id']);
        self::assertSame(1, (int) $after['generation']);
        $old = $this->connection->fetchAssociative(
            'SELECT revoked_at, delivery_ciphertext FROM activation_grants WHERE id = ?',
            [$before['id']]
        );
        self::assertIsArray($old);
        self::assertNotNull($old['revoked_at']);
        self::assertNull($old['delivery_ciphertext']);
        $user = $this->container->get(UserRepository::class)->getById($this->target);
        self::assertNotNull($user);
        self::assertSame(
            $operation === 'email' ? 'corrected@example.test' : 'pending@example.test',
            $user->getEmail()->toString()
        );
        $expires = new DateTimeImmutable($after['expires_at']);
        $revoked = new DateTimeImmutable($old['revoked_at']);
        self::assertSame(7 * 86400, $expires->getTimestamp() - $revoked->getTimestamp());
        self::assertSame($roles, $this->connection->fetchFirstColumn(
            'SELECT role_id FROM user_role_assignments WHERE user_id = ?',
            [$this->target->toString()]
        ));
        self::assertSame(1, $this->auditCount($audit));
        $row = $this->connection->fetchAssociative(
            'SELECT actor_id, subject_id, context FROM audit_evidence WHERE action = ?',
            [$audit]
        );
        self::assertIsArray($row);
        self::assertSame($this->actor->getId()->toString(), $row['actor_id']);
        self::assertSame($this->target->toString(), $row['subject_id']);
        $due = $this->container->get(ActivationGrantRepository::class)->findDue(
            $this->container->get(Clock::class)->now(),
            100
        );
        if ($operation === 'email') {
            self::assertSame(1, $this->providerCalls);
            self::assertCount(1, $due);
            self::assertSame($after['delivery_id'], $due[0]->getDeliveryId()->toString());
            $this->container->get(CommandBus::class)->execute(new DeliverUserInvitation(
                $this->actor->getId()->toString(),
                $this->target,
                $due[0]->getDeliveryId()
            ));
        }
        self::assertSame(2, $this->providerCalls);
    }

    /**
     * Supplies distinct replacement outcomes
     *
     * @return iterable<array{string, string}>
     */
    public static function replacementOperations(): iterable
    {
        yield ['resend', 'user.invitation_delivery.resent'];
        yield ['email', 'user.pending_invitation_corrected'];
    }

    /**
     * Rejects missing permissions and stale authentication before any target state or limiter mutation
     */
    #[DataProvider('authorityCases')]
    public function testAuthority(string $operation, string $case, int $status): void
    {
        if ($case === 'permission') {
            $this->connection->executeStatement(
                'DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE name = ?)',
                ['MANAGE_USER_INVITATIONS']
            );
        } elseif ($case === 'stale') {
            $this->connection->executeStatement(
                'UPDATE users SET authentication_version = authentication_version + 1 WHERE id = ?',
                [$this->actor->getId()->toString()]
            );
        } elseif ($case === 'revoked') {
            $this->connection->executeStatement(
                'UPDATE refresh_sessions SET revoked = true WHERE id = ?',
                [$this->session->getId()->toString()]
            );
        }
        $before = $this->grant();
        $response = $this->request(
            $operation === 'email' ? 'PATCH' : 'POST',
            '/api/v1/invitations/'.$this->target.'/'.$operation,
            $operation === 'email' ? '{"email":"corrected@example.test"}' : null,
            $case !== 'anonymous'
        );
        $this->assertResponse($response, $status, $operation);
        self::assertSame($before, $this->grant());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Supplies each protected operation with all authentication failure classes
     *
     * @return iterable<array{string, string, int}>
     */
    public static function authorityCases(): iterable
    {
        foreach (['retry', 'resend', 'email'] as $operation) {
            foreach (['permission', 'stale', 'revoked', 'anonymous'] as $case) {
                yield [$operation, $case, $case === 'permission' ? 403 : 401];
            }
        }
    }

    /**
     * Rejects actor spoofing and malformed JSON, identifiers and destination sources without disclosure
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(string $operation, ?string $body, string $suffix = '', bool $badId = false): void
    {
        $before = $this->grant();
        $path = '/api/v1/invitations/'.($badId ? 'private-marker' : $this->target->toString()).'/'.$operation.$suffix;
        $this->assertResponse($this->request($operation === 'email' ? 'PATCH' : 'POST', $path, $body), 400, $operation);
        self::assertSame($before, $this->grant());
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Supplies real route input failures rather than synthetic test endpoints
     *
     * @return iterable<array{0: string, 1: ?string, 2?: string, 3?: bool}>
     */
    public static function invalidInputs(): iterable
    {
        foreach (['retry', 'resend', 'email'] as $operation) {
            $valid = $operation === 'email' ? '{"email":"corrected@example.test"}' : null;
            yield [$operation, $valid, '?actor_id=private-marker'];
            yield [$operation, $valid, '', true];
            yield [$operation, '{"actor_id":"private-marker","email":"corrected@example.test"}'];
        }
        foreach (
            ['{', '[]', 'null', '{}', '{"email":null}', '{"email":123}', '{"email":"private-marker"}',
            '{"email":"üser@example.test"}', '{"email":"a@example.test","email":"b@example.test"}'] as $body
        ) {
            yield ['email', $body];
        }
    }

    /**
     * Rolls back user, predecessor, successor, audit and staged delivery on injected persistence failure
     */
    #[DataProvider('rollbackCases')]
    public function testRollback(string $operation, string $table, string $condition): void
    {
        $before = $this->grant();
        $beforeUser = $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = ?',
            [$this->target->toString()]
        );
        $audits = (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence');
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT recovery_abort CHECK ('.$condition.') NOT VALID'
        );
        $this->assertResponse($this->recover($operation), 500, $operation);
        self::assertSame($before, $this->grant());
        self::assertSame($beforeUser, $this->connection->fetchAssociative(
            'SELECT * FROM users WHERE id = ?',
            [$this->target->toString()]
        ));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_grants'));
        self::assertSame($audits, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Supplies each atomic persistence boundary with a real failing database write
     *
     * @return iterable<array{string, string, string}>
     */
    public static function rollbackCases(): iterable
    {
        yield ['retry', 'audit_evidence', "action <> 'user.invitation_delivery.retry_requested'"];
        yield ['resend', 'activation_grants', 'generation = 0'];
        yield ['resend', 'audit_evidence', "action <> 'user.invitation_delivery.resent'"];
        yield ['email', 'users', "email <> 'corrected@example.test'"];
        yield ['email', 'activation_grants', 'generation = 0'];
        yield ['email', 'audit_evidence', "action <> 'user.pending_invitation_corrected'"];
    }

    /**
     * Retains old state when canonical or live reserved email is already claimed
     */
    #[DataProvider('claimCases')]
    public function testEmailClaim(bool $reserved): void
    {
        $now = new DateTimeImmutable('-1 minute');
        $other = User::invite(
            UserId::generate(),
            EmailAddress::fromString($reserved ? 'other@example.test' : 'corrected@example.test'),
            $now
        );
        if ($reserved) {
            $other->activate(PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT)), $now);
            $other->requestEmailChange(EmailAddress::fromString('corrected@example.test'), $now);
        }
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($other)
        );
        $before = $this->grant();
        $this->assertResponse($this->recover('email'), 409, 'email');
        self::assertSame($before, $this->grant());
        self::assertSame(0, $this->auditCount('user.pending_invitation_corrected'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Supplies canonical and reservation conflicts
     *
     * @return iterable<array{bool}>
     */
    public static function claimCases(): iterable
    {
        yield [false];
        yield [true];
    }

    /**
     * Keeps already delivered or permanently failed work non-retryable while resend creates new authority
     */
    #[DataProvider('terminalDeliveryOutcomes')]
    public function testTerminalDelivery(CredentialDeliveryOutcome $outcome, string $status): void
    {
        $this->outcome = $outcome;
        $data = $this->assertResponse($this->recover('retry'), 200, 'retry')['data'];
        self::assertSame($status, $data['status']);
        $before = $this->grant();
        $this->assertResponse($this->recover('resend'), 200, 'resend');
        self::assertNotSame($before['id'], $this->grant()['id']);
        $replacement = $this->grant();
        $this->assertResponse($this->recover('retry'), 409, 'retry');
        self::assertSame($replacement, $this->grant());
    }

    /**
     * Supplies distinct terminal provider outcomes without claiming inbox receipt
     *
     * @return iterable<array{CredentialDeliveryOutcome, string}>
     */
    public static function terminalDeliveryOutcomes(): iterable
    {
        yield [CredentialDeliveryOutcome::DELIVERED, 'delivered'];
        yield [CredentialDeliveryOutcome::PERMANENT_FAILURE, 'permanent_failure'];
    }

    /**
     * Preserves retryable state without leaking provider exceptions or credential-bearing links
     */
    public function testProviderFailureRedaction(): void
    {
        $this->providerThrows = true;
        $data = $this->assertResponse($this->recover('retry'), 200, 'retry')['data'];
        self::assertSame('retry_pending', $data['status']);
        self::assertSame(2, $this->providerCalls);
        self::assertSame([], $this->logs->getRecords());
    }

    /**
     * Reports failed post-commit coordination as uncertain rather than claiming rollback
     */
    public function testPostcommitUncertainty(): void
    {
        $events = $this->createStub(EventDispatcher::class);
        $events->method('trigger')->willThrowException(new EventDispatchFailed([]));
        $this->container->set(EventDispatcher::class, static fn(): EventDispatcher => $events);
        $before = $this->grant();
        $this->assertResponse($this->recover('retry'), 503, 'retry');
        self::assertSame($before['id'], $this->grant()['id']);
        self::assertSame('pending', $this->grant()['delivery_status']);
        self::assertSame(1, $this->auditCount('user.invitation_delivery.retry_requested'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Rejects consumed invitations and active targets without creating recovery work
     */
    public function testConsumedInvitation(): void
    {
        $this->activate($this->credential);
        $before = $this->grant();
        foreach (['retry', 'resend', 'email'] as $operation) {
            $this->assertResponse($this->recover($operation), 409, $operation);
            self::assertSame($before, $this->grant());
        }
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Returns controlled missing-target and missing-generation responses only after authority checks
     */
    public function testMissingWork(): void
    {
        foreach (['retry', 'resend', 'email'] as $operation) {
            $response = $this->request(
                $operation === 'email' ? 'PATCH' : 'POST',
                '/api/v1/invitations/'.UserId::generate().'/'.$operation,
                $operation === 'email' ? '{"email":"corrected@example.test"}' : null
            );
            $this->assertResponse($response, 403, $operation);
        }
        $this->connection->executeStatement(
            'DELETE FROM activation_grants WHERE user_id = ?',
            [$this->target->toString()]
        );
        $this->assertResponse($this->recover('retry'), 404, 'retry');
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Rejects pending, claimed, invalidated and delivery-expired work without a new retry audit
     */
    #[DataProvider('nonRetryableStates')]
    public function testNonRetryableState(string $state): void
    {
        $repository = $this->container->get(ActivationGrantRepository::class);
        $grant = $repository->getLatestByUserId($this->target);
        self::assertNotNull($grant);
        $now = $this->container->get(Clock::class)->now();
        $next = match ($state) {
            'pending', 'claimed' => $grant->requestDeliveryRetry(),
            'invalidated' => $grant->revoke($now),
            'expired' => $grant->expireDeliveryAt($grant->getExpiresAt()),
            default => throw new LogicException('Invalid fixture state.')
        };
        $connection = $this->container->get(Connection::class);
        $connection->transactional(fn() => self::assertTrue($repository->replace($grant, $next)));
        if ($state === 'claimed') {
            $claimed = $next->claimDelivery(CredentialDeliveryClaimToken::generate(), $now, $now->modify('+1 minute'));
            $connection->transactional(fn() => self::assertTrue($repository->replace($next, $claimed)));
        }
        $before = $this->grant();
        $this->assertResponse($this->recover('retry'), 409, 'retry');
        self::assertSame($before, $this->grant());
        self::assertSame(0, $this->auditCount('user.invitation_delivery.retry_requested'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Supplies every other durable non-retryable delivery state
     *
     * @return iterable<array{string}>
     */
    public static function nonRetryableStates(): iterable
    {
        foreach (['pending', 'claimed', 'invalidated', 'expired'] as $state) {
            yield [$state];
        }
    }

    /**
     * Rejects expired retry at the deadline without mutation or success audit
     */
    public function testExpiredRetry(): void
    {
        $before = $this->grant();
        $clock = new AccessClock(new DateTimeImmutable($before['expires_at']));
        $this->container->set(Clock::class, static fn(): Clock => $clock);
        $this->assertResponse($this->recover('retry'), 409, 'retry');
        self::assertSame($before, $this->grant());
        self::assertSame(0, $this->auditCount('user.invitation_delivery.retry_requested'));
        self::assertSame(1, $this->providerCalls);
    }

    /**
     * Denies exhausted operation budgets without changing grants or invoking the provider
     */
    #[DataProvider('replacementOperations')]
    public function testThrottle(string $operation, string $audit): void
    {
        $limiter = new PostgresInvitationRateLimiter(
            $this->container->get(Connection::class),
            $this->container->get(Clock::class),
            new InvitationRatePolicy(60, 10, 60, 100, 1, 1, 1)
        );
        $this->container->set(InvitationRateLimiter::class, static fn(): InvitationRateLimiter => $limiter);
        $this->assertResponse($this->recover($operation), 200, $operation);
        $before = $this->grant();
        $calls = $this->providerCalls;
        $response = $this->recover($operation);
        $this->assertResponse($response, 429, $operation);
        self::assertGreaterThanOrEqual(1, (int) $response->getHeaderLine('Retry-After'));
        self::assertLessThanOrEqual(60, (int) $response->getHeaderLine('Retry-After'));
        self::assertSame($before, $this->grant());
        self::assertSame($calls, $this->providerCalls);
        self::assertSame(1, $this->auditCount($audit));
        self::assertSame([1, 1], array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT attempts FROM invitation_rate_limits ORDER BY target_scope'
        )));
    }

    /**
     * Resolves controlled duplicate and mixed recovery snapshots in both winner orders without sleeps
     */
    #[DataProvider('recoveryRaces')]
    public function testRecoveryRace(string $first, string $second): void
    {
        $children = [];
        try {
            $children[] = $this->startRace($first, $this->target);
            $children[] = $this->startRace($second, $this->target);
            self::assertSame('committed', $this->releaseRace($children[0]));
            self::assertSame('conflict', $this->releaseRace($children[1]));
            $after = $this->grant();
            self::assertSame($first === 'retry' ? 0 : 1, (int) $after['generation']);
            self::assertSame(
                $first === 'email' ? 'race-corrected@example.test' : 'pending@example.test',
                $this->connection->fetchOne('SELECT email FROM users WHERE id = ?', [$this->target->toString()])
            );
            self::assertSame(1, $this->auditCount(match ($first) {
                'retry' => 'user.invitation_delivery.retry_requested',
                'resend' => 'user.invitation_delivery.resent',
                'email' => 'user.pending_invitation_corrected',
                default => throw new LogicException('Invalid test operation.')
            }));
            self::assertSame(1, (int) $this->connection->fetchOne(
                <<<'SQL'
SELECT count(*) FROM audit_evidence WHERE action IN (
    'user.invitation_delivery.retry_requested', 'user.invitation_delivery.resent', 'user.pending_invitation_corrected'
)
SQL
            ));
            self::assertSame(1, (int) $this->connection->fetchOne(
                <<<'SQL'
SELECT count(*) FROM activation_grants WHERE user_id = ? AND revoked_at IS NULL AND consumed_at IS NULL
SQL,
                [$this->target->toString()]
            ));
        } finally {
            $this->closeRaces($children);
        }
    }

    /**
     * Supplies duplicate and both mixed winner orders with overlapping package transaction snapshots
     *
     * @return iterable<array{string, string}>
     */
    public static function recoveryRaces(): iterable
    {
        yield ['retry', 'retry'];
        yield ['resend', 'resend'];
        yield ['email', 'email'];
        yield ['retry', 'resend'];
        yield ['resend', 'retry'];
        yield ['email', 'resend'];
        yield ['resend', 'email'];
    }

    /**
     * Allows only one corrected destination claim across independent users and transactions
     */
    public function testEmailClaimRace(): void
    {
        $issued = $this->request('POST', '/api/v1/invitations', '{"email":"second@example.test","mode":"ordinary"}');
        self::assertSame(201, $issued->getStatusCode());
        $data = json_decode((string) $issued->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        $other = UserId::fromString($data['user_id']);
        $children = [];
        try {
            $children[] = $this->startRace('email', $this->target);
            $children[] = $this->startRace('email', $other);
            self::assertSame('committed', $this->releaseRace($children[0]));
            self::assertSame('conflict', $this->releaseRace($children[1]));
            self::assertSame(1, (int) $this->connection->fetchOne(
                'SELECT count(*) FROM user_email_claims WHERE email = ?',
                ['race-corrected@example.test']
            ));
            self::assertSame('second@example.test', $this->connection->fetchOne(
                'SELECT email FROM users WHERE id = ?',
                [$other->toString()]
            ));
            self::assertSame(0, (int) $this->connection->fetchOne(
                'SELECT max(generation) FROM activation_grants WHERE user_id = ?',
                [$other->toString()]
            ));
        } finally {
            $this->closeRaces($children);
        }
    }

    /**
     * Preserves activation and replacement invariants in both controlled winner orders using the real package service
     */
    #[DataProvider('activationRaces')]
    public function testActivationRace(string $operation, bool $activateFirst): void
    {
        $credential = $this->credential;
        $children = [];
        try {
            $children[] = $this->startRace($operation, $this->target);
            if ($activateFirst) {
                $this->activate($credential);
                self::assertSame('conflict', $this->releaseRace($children[0]));
                self::assertSame('active', $this->connection->fetchOne(
                    'SELECT state FROM users WHERE id = ?',
                    [$this->target->toString()]
                ));
                self::assertNotNull($this->grant()['consumed_at']);
            } else {
                self::assertSame('committed', $this->releaseRace($children[0]));
                try {
                    $this->activate($credential);
                    self::fail('A replaced predecessor must never activate.');
                } catch (LogicException) {
                    self::assertSame('pending_activation', $this->connection->fetchOne(
                        'SELECT state FROM users WHERE id = ?',
                        [$this->target->toString()]
                    ));
                    self::assertNull($this->grant()['consumed_at']);
                }
            }
        } finally {
            $this->closeRaces($children);
        }
    }

    /**
     * Supplies resend/activation and correction/activation with both winners
     *
     * @return iterable<array{string, bool}>
     */
    public static function activationRaces(): iterable
    {
        foreach (['resend', 'email'] as $operation) {
            yield [$operation, true];
            yield [$operation, false];
        }
    }

    /**
     * Starts a bounded child and waits until its package transaction holds the original snapshot
     *
     * @return array{resource, array<int, resource>}
     */
    private function startRace(string $operation, UserId $target): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\InvitationRecoveryRaceWorker::run();'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3)
        );
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 15);
        fwrite($pipes[0], json_encode([
            'operation' => $operation,
            'actor'     => $this->actor->getId()->toString(),
            'session'   => $this->session->getId()->toString(),
            'version'   => $this->actor->getAuthenticationVersion(),
            'target'    => $target->toString()
        ], JSON_THROW_ON_ERROR)."\n");
        fflush($pipes[0]);
        self::assertSame('ready', trim((string) fgets($pipes[1])));

        return [$process, $pipes];
    }

    /**
     * Releases one actual competing transaction and returns only a sanitized outcome
     *
     * @param array{resource, array<int, resource>} $child
     */
    private function releaseRace(array $child): string
    {
        fwrite($child[1][0], "go\n");
        fflush($child[1][0]);

        return trim((string) fgets($child[1][1]));
    }

    /**
     * Closes owned pipes and terminates any child left blocked by an assertion failure
     *
     * @param list<array{resource, array<int, resource>}> $children
     */
    private function closeRaces(array $children): void
    {
        foreach ($children as [$process, $pipes]) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    /**
     * Exercises the package activation service with real persistence until its transport is implemented separately
     */
    private function activate(string $credential): void
    {
        $c = $this->container;
        $refresh = $c->get(RefreshSessionRepository::class);
        $generator = $this->createStub(RefreshCredentialGenerator::class);
        $generator->method('generate')->willReturn(RefreshCredential::fromString(bin2hex(random_bytes(32))));
        $service = new AuthenticationService(
            $c->get(UserRepository::class),
            $c->get(ActivationGrantRepository::class),
            $refresh,
            new SessionRevocationService($refresh),
            $c->get(TransactionalUnitOfWork::class),
            $c->get(Clock::class),
            $this->createStub(LoginThrottle::class),
            $generator,
            $c->get(PasswordHasher::class),
            $c->get(PasswordValidator::class),
            $c->get(TokenEncoder::class),
            new AuthenticationTokenPolicy(
                new DateInterval('PT15M'),
                new DateInterval('P1D'),
                new DateInterval('P2D'),
                new DateInterval('P30D'),
                new DateInterval('P1Y'),
                new DateInterval('PT5S')
            ),
            PasswordHash::fromString((string) password_hash('fixture', PASSWORD_BCRYPT)),
            $c->get(EventDispatcher::class),
            $c->get(PasswordResetGrantRepository::class),
            $c->get(AuditEvidenceRepository::class),
            $c->get(EmailChangeGrantRepository::class)
        );
        $service->activate($this->target, $credential, 'A!9z'.bin2hex(random_bytes(12)));
    }

    /**
     * Sends the real route without any actor JSON field
     */
    private function recover(string $operation): ResponseInterface
    {
        return $this->request(
            $operation === 'email' ? 'PATCH' : 'POST',
            '/api/v1/invitations/'.$this->target.'/'.$operation,
            $operation === 'email' ? '{"email":"Corrected@EXAMPLE.test"}' : null
        );
    }

    /**
     * Creates one actual authenticated request preserving input bytes
     */
    private function request(
        string $method,
        string $path,
        ?string $body = null,
        bool $authenticated = true
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://agent-os.test'.$path);
        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->token);
        }
        if ($body !== null) {
            $request->getBody()->write($body);
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        return $this->app->handle($request);
    }

    /**
     * Validates JSend against OpenAPI and scans public diagnostics for private material
     *
     * @return array<string, mixed>
     */
    private function assertResponse(ResponseInterface $response, int $status, string $operation): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $response->getHeaderLine('X-Correlation-ID'));
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(new OperationAddress(
            '/api/v1/invitations/{user_id}/'.$operation,
            $operation === 'email' ? 'patch' : 'post'
        ), $response);
        self::assertSame(array_fill(0, $this->providerCalls, [false, 1]), $this->providerObservations);
        $public = (string) $response->getBody().json_encode($response->getHeaders(), JSON_THROW_ON_ERROR);
        $public .= json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        $public .= json_encode($this->connection->fetchAllAssociative(
            'SELECT actor_id, subject_id, action, context FROM audit_evidence'
        ), JSON_THROW_ON_ERROR);
        $grant = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT credential_digest, delivery_ciphertext FROM activation_grants WHERE user_id = ?
ORDER BY generation DESC LIMIT 1
SQL,
            [$this->target->toString()]
        );
        $secrets = ['private-marker', 'private-provider-marker', 'SQL ', 'password=', 'stack', $this->token,
            $this->credential, $grant['credential_digest'] ?? '', $grant['delivery_ciphertext'] ?? ''];
        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                self::assertStringNotContainsString($secret, $public);
            }
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Reads only the owned fixture generation for independent state assertions
     *
     * @return array<string, mixed>
     */
    private function grant(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM activation_grants WHERE user_id = ? ORDER BY generation DESC LIMIT 1',
            [$this->target->toString()]
        );
        self::assertIsArray($row);

        return $row;
    }

    /**
     * Counts committed evidence without exposing private event payloads
     */
    private function auditCount(string $action): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT count(*) FROM audit_evidence WHERE subject_id = ? AND action = ?',
            [$this->target->toString(), $action]
        );
    }
}
