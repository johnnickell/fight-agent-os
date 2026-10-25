<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\RequestPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\Event\PasswordResetCompleted;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AccessClock;
use Tests\Support\AccessTokens;
use Tooling\OpenApi\Document;

/**
 * Proves reset completion through the real policy, package, HTTP and PostgreSQL boundaries
 */
final class ResetCompletionApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private AccessClock $clock;
    private AccessTokens $tokens;
    private UserId $target;
    private string $credential;
    private string $password;
    private string $oldPassword;
    private string $nonce;
    private string $proof;
    private TestHandler $logs;
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Seeds only a guarded test identity and a package-issued reset at deterministic time
     */
    protected function setUp(): void
    {
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $this->connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($this->connection, $expected);
        $this->connection->executeStatement('TRUNCATE users, audit_evidence, auth_rate_limits CASCADE');
        foreach (['DATABASE_URL', 'APP_CREDENTIAL_DELIVERY_KEY'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        putenv('DATABASE_URL='.$url);
        putenv('APP_CREDENTIAL_DELIVERY_KEY='.bin2hex(random_bytes(32)));
        $this->container = require dirname(__DIR__, 3).'/config/services.php';
        $this->tokens = new AccessTokens();
        $this->clock = $this->tokens->clock;
        $this->clock->time = new DateTimeImmutable('2026-10-23T12:00:00.654321Z');
        $this->container->set(Clock::class, fn(): Clock => $this->clock);
        $this->container->set(TokenEncoder::class, fn(): TokenEncoder => $this->tokens->jwt);
        \Slim\Factory\AppFactory::setContainer($this->container);
        /** @var App<Container> $app */
        $app = \Slim\Factory\AppFactory::create();
        $container = $this->container;
        $container->set(App::class, static fn(): App => $app);
        require dirname(__DIR__, 3).'/config/routes.php';
        require dirname(__DIR__, 3).'/config/middleware.php';
        $this->app = $app;
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $this->target = UserId::generate();
        $this->password = ' A!9z-é'.bin2hex(random_bytes(12)).' ';
        $this->oldPassword = 'Old!9z-'.bin2hex(random_bytes(12));
        $user = User::invite($this->target, EmailAddress::fromString('reset@example.test'), $this->clock->now());
        $user->activate(
            PasswordHash::fromString($container->get(PasswordHasher::class)->hash($this->oldPassword)),
            $this->clock->now()
        );
        $container->get(Connection::class)->transactional(fn() => $container->get(UserRepository::class)->add($user));
        $this->issue();
        $this->nonce = bin2hex(random_bytes(32));
        $this->proof = $container->get(HmacCsrfProofs::class)->sign($this->nonce, time() + 900);
    }

    /**
     * Restores owned failure injection and process configuration
     */
    protected function tearDown(): void
    {
        $tables = ['users', 'password_reset_grants', 'refresh_sessions', 'audit_evidence', 'auth_rate_limits'];
        foreach ($tables as $table) {
            $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS completion_abort');
        }
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Completes atomically, expires matching cookies and makes all prior credentials unusable
     */
    public function testSuccessRevokesEverySessionAndAdvancesAuthority(): void
    {
        $auth = $this->container->get(AuthenticationService::class);
        $first = $auth->login('reset@example.test', $this->oldPassword, false);
        $second = $auth->login('reset@example.test', $this->oldPassword, true);
        $before = $this->connection->fetchAssociative('SELECT * FROM users');
        self::assertIsArray($before);
        $observed = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(PasswordResetCompleted::class),
            function () use (&$observed): void {
                $observed[] = [
                    $this->container->get(Connection::class)->isTransactionActive(),
                    (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions WHERE revoked = true'),
                    (int) $this->connection->fetchOne(
                        "SELECT count(*) FROM audit_evidence WHERE action = 'user.password_reset_completed'"
                    )
                ];
            }
        );
        $response = $this->request(cookie: $first->getRefreshCredential()->toString());
        $this->check($response, 200);
        self::assertSame([[false, 2, 1]], $observed);
        self::assertCount(2, $response->getHeader('Set-Cookie'));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            self::assertStringContainsString('; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict', $cookie);
            self::assertStringContainsString('Max-Age=0', $cookie);
        }
        $after = $this->connection->fetchAssociative('SELECT * FROM users');
        self::assertIsArray($after);
        self::assertSame((int) $before['authentication_version'] + 1, (int) $after['authentication_version']);
        self::assertGreaterThan(
            (int) $before['authentication_authority_revision'],
            (int) $after['authentication_authority_revision']
        );
        self::assertTrue(password_verify($this->password, $after['password_hash']));
        self::assertFalse(password_verify(trim($this->password), $after['password_hash']));
        self::assertStringStartsWith('$argon2id$v=19$m=19456,t=2,p=1$', $after['password_hash']);
        self::assertNotNull($this->connection->fetchOne('SELECT consumed_at FROM password_reset_grants'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        $verifier = new AuthenticateAccessToken(
            $this->tokens->jwt,
            $this->container->get(UserRepository::class),
            $this->container->get(RefreshSessionRepository::class),
            $this->clock
        );
        foreach ([$first, $second] as $prior) {
            try {
                $verifier->authenticate($prior->getAccessToken()->toString());
                self::fail('Old access authority survived reset.');
            } catch (\App\Application\Failure\AuthenticationRequired) {
                // Expected authoritative rejection; reaching fail() above would fail this test.
            }
            try {
                $auth->refresh($prior->getRefreshCredential()->toString());
                self::fail('Old refresh survived reset.');
            } catch (RefreshSessionNotFoundException) {
                // Expected package rejection, not a local expiry shortcut.
            }
        }
        $this->check($this->request(), 409);
    }

    /**
     * Keeps unrelated cookie authority unchanged while possession independently authorizes the target reset
     */
    public function testUnrelatedBrowserIdentityIsNotMutated(): void
    {
        $other = User::invite(UserId::generate(), EmailAddress::fromString('other@example.test'), $this->clock->now());
        $other->activate(
            PasswordHash::fromString($this->container->get(PasswordHasher::class)->hash($this->oldPassword)),
            $this->clock->now()
        );
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($other)
        );
        $session = $this->container->get(AuthenticationService::class)->login(
            'other@example.test',
            $this->oldPassword,
            false
        );
        $response = $this->request(cookie: $session->getRefreshCredential()->toString());
        $this->check($response, 200);
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE revoked = true'
        ));
        $this->clock->time = $this->clock->time->modify('+1 second');
        self::assertNotNull($this->container->get(AuthenticationService::class)->refresh(
            $session->getRefreshCredential()->toString()
        ));
    }

    /**
     * Gives invalid, unknown, expired, revoked, replaced and lifecycle-invalid grants one recovery class
     */
    #[DataProvider('invalidGrants')]
    public function testGenericRecovery(string $kind): void
    {
        $changes = [];
        if ($kind === 'wrong-user') {
            $changes['user_id'] = UserId::generate()->toString();
        } elseif ($kind === 'wrong-credential') {
            $changes['credential'] = bin2hex(random_bytes(32));
        } elseif ($kind === 'malformed') {
            $changes['credential'] = 'malformed';
        } elseif ($kind === 'boundary') {
            $this->clock->time = $this->clock->time->modify('+1 hour');
        } elseif ($kind === 'replaced') {
            $old = $this->credential;
            $this->issue();
            $changes['credential'] = $old;
        } elseif (in_array($kind, ['disabled', 'deleted', 'version'], true)) {
            if ($kind === 'version') {
                $this->connection->executeStatement(
                    'UPDATE users SET authentication_version = authentication_version + 1'
                );
            } else {
                $this->connection->executeStatement("UPDATE users SET state = '".$kind."'");
            }
        } else {
            $repo = $this->container->get(PasswordResetGrantRepository::class);
            $grant = $repo->getLatestByUserId($this->target);
            self::assertNotNull($grant);
            $now = $this->clock->now();
            $replacement = $kind === 'used' ? $grant->consume($now) : $grant->revoke($now);
            self::assertTrue($this->container->get(Connection::class)->transactional(
                fn(): bool => $repo->replace($grant, $replacement)
            ));
        }
        $body = $this->check($this->request($changes), 409);
        self::assertSame(['status' => 'error', 'message' => 'Conflict.'], $body);
        self::assertTrue(password_verify(
            $this->oldPassword,
            $this->connection->fetchOne('SELECT password_hash FROM users')
        ));
    }

    /**
     * Supplies independent unusable authority cases
     *
     * @return iterable<array{string}>
     */
    public static function invalidGrants(): iterable
    {
        $cases = [
            'wrong-user', 'wrong-credential', 'malformed', 'boundary', 'replaced',
            'disabled', 'deleted', 'version', 'used', 'revoked'
        ];
        foreach ($cases as $kind) {
            yield [$kind];
        }
    }

    /**
     * Reuses the actual safe PHP publication and independently enforces structural and common-password policy
     */
    public function testSchemaAndPasswordPolicy(): void
    {
        $response = $this->app->handle(new ServerRequestFactory()->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/validations/password_reset_completion'
        ));
        self::assertSame(200, $response->getStatusCode());
        $schema = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame(['password', 'password_confirmation'], array_keys($schema['fields']));
        self::assertSame(
            ['password'],
            $schema['fields']['password_confirmation']['rules'][4]['depends_on']
        );
        $rejected = ['lowercase!99', 'UPPERCASE!99', 'Upperlower!', 'Upperlower99', 'P@ssw0rd', 'ǅlower!99'];
        foreach ($rejected as $password) {
            $body = $this->check($this->request(['password' => $password, 'password_confirmation' => $password]), 422);
            self::assertSame(
                [$schema['fields']['password']['rules'][2]['message']],
                $body['data']['fields']['body.password']
            );
            self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM password_reset_grants'));
        }
        $body = $this->check($this->request(['password' => '', 'password_confirmation' => 'different']), 400);
        self::assertArrayHasKey('body.password', $body['data']['fields']);
        self::assertArrayHasKey('body.password_confirmation', $body['data']['fields']);
        self::assertStringNotContainsString($this->credential, (string) $response->getBody());
    }

    /**
     * Accepts the final microsecond before expiry without trimming Unicode or space bytes
     */
    public function testLastUsableInstant(): void
    {
        $this->clock->time = $this->clock->time->modify('+1 hour -1 microsecond');
        $this->check($this->request(), 200);
    }

    /**
     * Rejects source and browser-policy bypasses before package mutation
     */
    public function testRequestControls(): void
    {
        $refusedHeaders = [
            ['Origin' => ''], ['Origin' => 'https://foreign.test'], ['Sec-Fetch-Site' => 'cross-site'],
            ['X-CSRF-Proof' => 'invalid'], ['Authorization' => 'Bearer synthetic'],
            ['X-Forwarded-For' => '127.0.0.1'], ['X-HTTP-Method-Override' => 'POST']
        ];
        foreach ($refusedHeaders as $headers) {
            $this->check($this->request(headers: $headers), 403);
        }
        foreach (['[]', '{}', '{', '{"credential":"a","credential":"b"}', str_repeat('x', 65537)] as $raw) {
            $this->check($this->request(raw: $raw), 400);
        }
        $this->check($this->request(['unknown' => 'x']), 400);
        $this->check($this->request(['credential' => str_repeat('é', 2049)]), 403);
        $this->check($this->request(uri: 'http://agent-os.test/api/v1/auth/password-reset/complete'), 403);
        $this->check($this->request(
            uri: 'https://agent-os.test/api/v1/auth/password-reset/complete?credential=alternate'
        ), 400);
        self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM password_reset_grants'));
    }

    /**
     * Rolls back each authoritative write while independently committed admission remains consumed
     */
    #[DataProvider('writeTables')]
    public function testAtomicRollback(string $table): void
    {
        $this->container->get(AuthenticationService::class)->login('reset@example.test', $this->oldPassword, false);
        $before = $this->connection->fetchAssociative('SELECT * FROM users');
        $this->connection->executeStatement('TRUNCATE auth_rate_limits');
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT completion_abort CHECK (false) NOT VALID'
        );
        $this->check($this->request(), 500);
        self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM password_reset_grants'));
        self::assertSame($before, $this->connection->fetchAssociative('SELECT * FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE revoked = true'
        ));
        self::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM auth_rate_limits WHERE jsonb_array_length(hits) = 1'
        ));
    }

    /**
     * Supplies the grant, user, session and audit transaction boundaries
     *
     * @return iterable<array{string}>
     */
    public static function writeTables(): iterable
    {
        foreach (['password_reset_grants', 'users', 'refresh_sessions', 'audit_evidence'] as $table) {
            yield [$table];
        }
    }

    /**
     * Bounds admission without credential or identity keys and refuses storage outage
     */
    public function testAdmissionAndStorageFailure(): void
    {
        for ($index = 0; $index < 20; ++$index) {
            $this->check($this->request(['credential' => 'invalid']), 409);
        }
        $response = $this->request();
        $this->check($response, 429);
        self::assertSame('900', $response->getHeaderLine('Retry-After'));
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM auth_rate_limits');
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $row['key']);
        }
        $this->connection->executeStatement('TRUNCATE auth_rate_limits');
        $this->connection->executeStatement(
            'ALTER TABLE auth_rate_limits ADD CONSTRAINT completion_abort CHECK (false) NOT VALID'
        );
        $this->check($this->request(), 429);
        self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM password_reset_grants'));
    }

    /**
     * Retains uncertainty after a post-commit event failure rather than offering a second valid reset
     */
    public function testPostCommitFailureIsSanitized(): void
    {
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(PasswordResetCompleted::class),
            static function (): void {
                throw new \RuntimeException('private provider detail');
            }
        );
        $response = $this->request();
        $this->check($response, 500);
        self::assertStringNotContainsString('private provider detail', (string) $response->getBody());
        self::assertNotNull($this->connection->fetchOne('SELECT consumed_at FROM password_reset_grants'));
    }

    /**
     * Serializes duplicate completion and both successor winner orders without timing sleeps
     */
    #[DataProvider('competitions')]
    public function testControlledRaces(string $first, string $second): void
    {
        $children = [];
        $this->connection->beginTransaction();
        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, ?))',
            [$this->target->toString(), 24122]
        );
        try {
            foreach ([$first, $second] as $mode) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r',
                    'require "vendor/autoload.php"; Tests\\Support\\ResetCompletionRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 20);
                fwrite($pipes[0], json_encode([
                    'mode'       => $mode,
                    'now'        => $this->clock->now()->format('Y-m-d\\TH:i:s.uP'),
                    'user'       => $this->target->toString(),
                    'credential' => $this->credential,
                    'password'   => $this->password
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                $pid = (int) fgets($pipes[1]);
                self::assertGreaterThan(0, $pid);
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
                $blocked = false;
                for ($attempt = 0; $attempt < 5000 && !$blocked; ++$attempt) {
                    $blocked = (bool) $this->connection->fetchOne(
                        'SELECT EXISTS (SELECT 1 FROM pg_locks WHERE locktype = ? AND NOT granted AND pid = ?)',
                        ['advisory', $pid]
                    );
                }
                self::assertTrue($blocked, 'Worker did not reach the held authority fence.');
            }
            $this->connection->commit();
            $outcomes = [];
            foreach ($children as [$process, $pipes]) {
                $outcomes[] = trim((string) fgets($pipes[1]));
            }
            self::assertSame('committed', $outcomes[0]);
            self::assertSame($first === 'request' || $second === 'complete' ? 'conflict' : 'superseded', $outcomes[1]);
            $rows = $this->connection->fetchAllAssociative('SELECT * FROM password_reset_grants ORDER BY generation');
            if ($first === 'request') {
                self::assertCount(2, $rows);
                self::assertNotNull($rows[0]['revoked_at']);
                self::assertNull($rows[1]['consumed_at']);
                self::assertNull($rows[1]['revoked_at']);
                self::assertTrue(password_verify(
                    $this->oldPassword,
                    $this->connection->fetchOne('SELECT password_hash FROM users')
                ));
            } else {
                self::assertCount(1, $rows);
                self::assertNotNull($rows[0]['consumed_at']);
                self::assertTrue(password_verify(
                    $this->password,
                    $this->connection->fetchOne('SELECT password_hash FROM users')
                ));
            }
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            foreach ($children as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                self::assertSame(0, proc_close($process));
            }
        }
    }

    /**
     * Supplies exact controlled winner orders
     *
     * @return iterable<array{string, string}>
     */
    public static function competitions(): iterable
    {
        yield ['complete', 'complete'];
        yield ['complete', 'request'];
        yield ['request', 'complete'];
    }

    /**
     * Issues through the configured command bus while keeping plaintext fixture material only in memory
     */
    private function issue(): void
    {
        if (!isset($this->credential)) {
            $generator = $this->createStub(PasswordResetCredentialGenerator::class);
            $generator->method('generate')->willReturnCallback(function (): PasswordResetCredential {
                $value = PasswordResetCredential::fromString(bin2hex(random_bytes(32)));
                $this->credential = $value->toString();

                return $value;
            });
            $this->container->set(
                PasswordResetCredentialGenerator::class,
                static fn(): PasswordResetCredentialGenerator => $generator
            );
        }
        $this->container->get(CommandBus::class)->execute(new RequestPasswordReset(
            EmailAddress::fromString('reset@example.test')
        ));
    }

    /**
     * Builds the real route without alternate input owners
     *
     * @param array<string, mixed> $changes
     * @param array<string, string> $headers
     */
    private function request(
        array $changes = [],
        array $headers = [],
        ?string $raw = null,
        ?string $cookie = null,
        string $uri = 'https://agent-os.test/api/v1/auth/password-reset/complete'
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest('POST', $uri, ['REMOTE_ADDR' => '127.0.0.1']);
        $cookieHeader = CsrfCookie::NAME.'='.$this->nonce;
        if ($cookie !== null) {
            $cookieHeader .= '; '.BrowserSessionCookies::REFRESH_NAME.'='.$cookie;
        }
        $effectiveHeaders = array_replace([
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://agent-os.test',
            'Sec-Fetch-Site' => 'same-origin',
            'X-CSRF-Proof'   => $this->proof,
            'Cookie'         => $cookieHeader
        ], $headers);
        foreach ($effectiveHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request->getBody()->write($raw ?? json_encode(array_replace([
            'user_id'               => $this->target->toString(),
            'credential'            => $this->credential,
            'password'              => $this->password,
            'password_confirmation' => $this->password
        ], $changes), JSON_THROW_ON_ERROR));

        return $this->app->handle($request);
    }

    /**
     * Validates responses and redaction without printing sensitive fixture material
     *
     * @return array<string, mixed>
     */
    private function check(ResponseInterface $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress('/api/v1/auth/password-reset/complete', 'post'),
            $response
        );
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        if ($status === 200) {
            self::assertSame(['status' => 'success', 'data' => ['completed' => true]], $body);
        } else {
            self::assertSame([], $response->getHeader('Set-Cookie'));
        }
        $public = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        foreach ([$this->credential, $this->password, $this->nonce, $this->proof] as $marker) {
            self::assertFalse(str_contains($public, $marker), 'Secret reached public diagnostics.');
        }

        return $body;
    }
}
