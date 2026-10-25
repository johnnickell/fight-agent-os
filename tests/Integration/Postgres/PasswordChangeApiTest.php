<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Application\Security\AccessToken\AuthenticateAccessToken;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\AccessControl\Domain\AccessControl\User\Event\PasswordChanged;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
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
use Tooling\OpenApi\Document;

/**
 * Proves authenticated password ownership and terminal invalidation through real PostgreSQL and HTTP
 */
final class PasswordChangeApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private UserId $target;
    private string $password;
    private string $oldPassword;
    private string $access;
    private string $refresh;
    private TestHandler $logs;
    private string|false $databaseUrl;

    /**
     * Seeds only guarded test identities and independently authenticated sessions
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
        $this->databaseUrl = getenv('DATABASE_URL');
        putenv('DATABASE_URL='.$url);
        $this->container = require dirname(__DIR__, 3).'/config/services.php';
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
        // Existing credentials need not satisfy replacement composition.
        $this->oldPassword = ' old '.bin2hex(random_bytes(12)).' ';
        $this->seed($this->target, 'change@example.test');
        $session = $container->get(AuthenticationService::class)->login(
            'change@example.test',
            $this->oldPassword,
            false
        );
        $this->access = $session->getAccessToken()->toString();
        $this->refresh = $session->getRefreshCredential()->toString();
    }

    /**
     * Restores owned failure injection and environment
     */
    protected function tearDown(): void
    {
        foreach (['users', 'refresh_sessions', 'audit_evidence'] as $table) {
            $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS change_abort');
        }
        putenv($this->databaseUrl === false ? 'DATABASE_URL' : 'DATABASE_URL='.$this->databaseUrl);
        $this->connection->close();
    }

    /**
     * Changes only the principal and atomically invalidates all prior authority without replacement tokens
     */
    public function testSuccessAndAllSessionInvalidation(): void
    {
        $auth = $this->container->get(AuthenticationService::class);
        $second = $auth->login('change@example.test', $this->oldPassword, true);
        $other = UserId::generate();
        $this->seed($other, 'other@example.test');
        $unrelated = $auth->login('other@example.test', $this->oldPassword, false);
        $before = $this->user();
        $observed = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(PasswordChanged::class),
            function () use (&$observed): void {
                $observed[] = [
                    $this->container->get(Connection::class)->isTransactionActive(),
                    (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions WHERE revoked = true'),
                    (int) $this->connection->fetchOne(
                        "SELECT count(*) FROM audit_evidence WHERE action = 'user.password_changed'"
                    )
                ];
            }
        );
        $response = $this->request();
        $this->check($response, 200);
        self::assertSame([[false, 2, 1]], $observed);
        self::assertCount(2, $response->getHeader('Set-Cookie'));
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            self::assertStringContainsString('; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict', $cookie);
            self::assertStringContainsString('Max-Age=0', $cookie);
        }
        $after = $this->user();
        self::assertSame((int) $before['authentication_version'] + 1, (int) $after['authentication_version']);
        self::assertGreaterThan(
            (int) $before['authentication_authority_revision'],
            (int) $after['authentication_authority_revision']
        );
        self::assertTrue(password_verify($this->password, $after['password_hash']));
        self::assertFalse(password_verify(trim($this->password), $after['password_hash']));
        self::assertStringStartsWith('$argon2id$v=19$m=19456,t=2,p=1$', $after['password_hash']);
        self::assertNotNull($auth->login('change@example.test', $this->password, false));
        $priorSessions = [
            [$this->access, $this->refresh],
            [$second->getAccessToken()->toString(), $second->getRefreshCredential()->toString()]
        ];
        foreach ($priorSessions as [$access, $refresh]) {
            try {
                $this->container->get(AuthenticateAccessToken::class)->authenticate($access);
                self::fail('Old access survived password change.');
            } catch (\App\Application\Failure\AuthenticationRequired) {
                // Expected authoritative rejection.
            }
            try {
                $auth->refresh($refresh);
                self::fail('Old refresh survived password change.');
            } catch (RefreshSessionNotFoundException) {
                // Expected package rejection.
            }
        }
        self::assertNotNull($this->container->get(AuthenticateAccessToken::class)->authenticate(
            $unrelated->getAccessToken()->toString()
        ));
        $this->check($this->request(), 401);
    }

    /**
     * Rejects current proof without changing password, cookies or session authority
     */
    public function testWrongCurrentProofAndExactBytes(): void
    {
        $before = $this->user();
        $body = $this->check($this->request(['current_password' => trim($this->oldPassword)]), 409);
        self::assertSame(['status' => 'error', 'message' => 'Conflict.'], $body);
        self::assertSame($before, $this->user());
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE revoked = true'
        ));
        self::assertNotNull($this->container->get(AuthenticateAccessToken::class)->authenticate($this->access));
    }

    /**
     * Enforces replacement policy, closed source validation and confirmation without mutations
     *
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(array $input, int $status): void
    {
        $before = $this->user();
        $this->check($this->request($input), $status);
        self::assertSame($before, $this->user());
        self::assertNotNull($this->container->get(AuthenticateAccessToken::class)->authenticate($this->access));
    }

    /**
     * Supplies independently selected transport and policy boundaries
     *
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'common' => [['password' => 'P@ssw0rd', 'password_confirmation' => 'P@ssw0rd'], 422];
        yield 'composition' => [['password' => 'lowercase-only', 'password_confirmation' => 'lowercase-only'], 422];
        yield 'confirmation' => [['password_confirmation' => 'Mismatch!9'], 400];
        yield 'target' => [['user_id' => UserId::generate()->toString()], 400];
        yield 'actor' => [['actor' => 'not-an-authority'], 400];
        yield 'session' => [['session_id' => 'not-an-authority'], 400];
        yield 'role' => [['role' => 'ROLE_SUPER_ADMIN'], 400];
        yield 'permission' => [['permission' => 'CHANGE_PASSWORD'], 400];
        yield 'empty-current' => [['current_password' => ''], 400];
        yield 'oversized-current' => [['current_password' => str_repeat('x', 129)], 400];
        yield 'array-current' => [['current_password' => []], 400];
        yield 'oversized-new' => [['password' => str_repeat('é', 129)], 400];
    }

    /**
     * Requires independent Bearer authority and direct same-origin mutation controls
     */
    public function testRequestControls(): void
    {
        foreach ([['Authorization' => ''], ['Authorization' => 'Bearer invalid']] as $headers) {
            $this->check($this->request(headers: $headers), 401);
        }
        foreach (
            [['Origin' => 'https://foreign.test'], ['Origin' => ''], ['Sec-Fetch-Site' => 'cross-site'],
            ['Sec-Fetch-Mode' => 'navigate'], ['Sec-Fetch-Dest' => 'document'], ['Forwarded' => 'proto=https'],
            ['X-Forwarded-Host' => 'agent-os.test'], ['X-HTTP-Method-Override' => 'POST'],
            ['Content-Encoding' => 'gzip']] as $headers
        ) {
            $this->check($this->request(headers: $headers), 403);
        }
        $this->check($this->request(raw: '{"current_password":"a","current_password":"b"}'), 400);
        $this->check($this->request(uri: 'http://agent-os.test/api/v1/auth/password/change'), 403);
        self::assertNotNull($this->container->get(AuthenticateAccessToken::class)->authenticate($this->access));
    }

    /**
     * Fails revoked and inactive principals before password mutation
     */
    public function testLostAuthority(): void
    {
        $before = $this->user();
        $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = true');
        $this->check($this->request(), 401);
        self::assertSame($before, $this->user());
        $this->connection->executeStatement('UPDATE refresh_sessions SET revoked = false');
        $this->connection->executeStatement("UPDATE users SET state = 'disabled'");
        $this->check($this->request(), 401);
        self::assertSame($before['password_hash'], $this->user()['password_hash']);
    }

    /**
     * Applies endpoint budgets before package proof without mutating authoritative sessions
     */
    public function testAdmission(): void
    {
        $before = $this->user();
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $this->check($this->request(['password' => 'P@ssw0rd', 'password_confirmation' => 'P@ssw0rd']), 422);
        }
        $response = $this->request();
        $this->check($response, 429);
        self::assertNotSame('', $response->getHeaderLine('Retry-After'));
        self::assertSame($before, $this->user());
        self::assertNotNull($this->container->get(AuthenticateAccessToken::class)->authenticate($this->access));
    }

    /**
     * Preserves package acceptance of an unchanged policy-compliant password without history rules
     */
    public function testNoInventedHistoryRule(): void
    {
        $this->oldPassword = $this->password;
        $this->connection->executeStatement('UPDATE users SET password_hash = ? WHERE id = ?', [
            $this->container->get(PasswordHasher::class)->hash($this->oldPassword), $this->target->toString()
        ]);
        $this->check($this->request(), 200);
        self::assertTrue(password_verify($this->password, $this->user()['password_hash']));
    }

    /**
     * Rolls password, revision, session and audit writes back together while admission remains consumed
     */
    #[DataProvider('writeTables')]
    public function testAtomicRollback(string $table): void
    {
        $before = $this->user();
        $predicate = match ($table) {
            'users' => 'authentication_version = '.(int) $before['authentication_version'],
            'refresh_sessions' => 'revoked = false',
            default => "action <> 'user.password_changed'"
        };
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT change_abort CHECK ('.$predicate.')'
        );
        $this->check($this->request(), 500);
        self::assertSame($before, $this->user());
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM refresh_sessions WHERE revoked = true'
        ));
        self::assertSame(0, (int) $this->connection->fetchOne(
            "SELECT count(*) FROM audit_evidence WHERE action = 'user.password_changed'"
        ));
        self::assertGreaterThan(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Supplies atomic mutation participants
     *
     * @return iterable<string, array{string}>
     */
    public static function writeTables(): iterable
    {
        foreach (['users', 'refresh_sessions', 'audit_evidence'] as $table) {
            yield $table => [$table];
        }
    }

    /**
     * Preserves a committed change when event delivery fails without leaking downstream material
     */
    public function testPostCommitUncertainty(): void
    {
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(PasswordChanged::class),
            function (): void {
                throw new \RuntimeException($this->password);
            }
        );
        $this->check($this->request(), 500);
        self::assertTrue(password_verify($this->password, $this->user()['password_hash']));
        $this->check($this->request(), 401);
    }

    /**
     * Serializes competing package operations through controlled separate PostgreSQL connections
     */
    #[DataProvider('competitions')]
    public function testControlledRaces(string $first, string $second): void
    {
        $children = [];
        $otherSession = $this->container->get(AuthenticationService::class)->login(
            'change@example.test',
            $this->oldPassword,
            true
        );
        $before = $this->user();
        $raceTime = $this->container->get(Clock::class)->now()->format('Y-m-d\\TH:i:s.uP');
        $this->connection->beginTransaction();
        $revocationRace = array_intersect(['revoke', 'other-revoke'], [$first, $second]) !== [];
        if ($revocationRace) {
            $this->connection->executeQuery('SELECT id FROM refresh_sessions FOR UPDATE')->fetchAllAssociative();
        } else {
            $this->connection->executeQuery(
                'SELECT pg_advisory_xact_lock(hashtextextended(?, ?))',
                [$this->target->toString(), 24122]
            );
        }
        try {
            foreach ([$first, $second] as $mode) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r',
                        'require "vendor/autoload.php"; Tests\\Support\\PasswordChangeRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 20);
                $refresh = $this->refresh;
                if ($mode === 'other-revoke') {
                    $refresh = $otherSession->getRefreshCredential()->toString();
                }
                fwrite($pipes[0], json_encode([
                    'mode'     => $mode,
                    'now'      => $raceTime,
                    'user'     => $this->target->toString(),
                    'current'  => $this->oldPassword,
                    'password' => $this->password,
                    'refresh'  => $refresh
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                $pid = (int) fgets($pipes[1]);
                self::assertGreaterThan(0, $pid);
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
                $blocked = false;
                for ($attempt = 0; $attempt < 10000 && !$blocked; ++$attempt) {
                    $blocked = (bool) $this->connection->fetchOne(
                        'SELECT EXISTS (SELECT 1 FROM pg_locks WHERE NOT granted AND pid = ?)',
                        [$pid]
                    );
                }
                self::assertTrue($blocked, 'Worker did not reach held authority fence.');
            }
            $this->connection->commit();
            $outcomes = [];
            foreach ($children as [$process, $pipes]) {
                $outcomes[] = trim((string) fgets($pipes[1]));
            }
            self::assertSame('committed', $outcomes[0]);
            $secondConflicts = ($first === 'change' && in_array($second, ['change', 'refresh', 'authority'], true))
                || ($first === 'authority' && $second === 'change');
            self::assertSame($secondConflicts ? 'conflict' : 'committed', $outcomes[1]);
            $changed = $first === 'change' || ($second === 'change' && !$secondConflicts);
            $after = $this->user();
            self::assertTrue(password_verify($changed ? $this->password : $this->oldPassword, $after['password_hash']));
            self::assertSame(
                (int) $before['authentication_version'] + (int) $changed,
                (int) $after['authentication_version']
            );
            self::assertSame((int) $changed, (int) $this->connection->fetchOne(
                "SELECT count(*) FROM audit_evidence WHERE action = 'user.password_changed'"
            ));
            if ($changed) {
                self::assertSame(0, (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM refresh_sessions WHERE revoked = false'
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
     * Supplies exact competing winner orders without sleeps or probabilistic scheduling
     *
     * @return iterable<array{string, string}>
     */
    public static function competitions(): iterable
    {
        yield ['change', 'change'];
        yield ['change', 'refresh'];
        yield ['refresh', 'change'];
        yield ['change', 'revoke'];
        yield ['revoke', 'change'];
        yield ['change', 'other-revoke'];
        yield ['other-revoke', 'change'];
        yield ['authority', 'change'];
        yield ['change', 'authority'];
    }

    /**
     * Creates an active identity through package persistence
     */
    private function seed(UserId $id, string $email): void
    {
        $now = $this->container->get(Clock::class)->now();
        $user = User::invite($id, EmailAddress::fromString($email), $now);
        $user->activate(
            PasswordHash::fromString($this->container->get(PasswordHasher::class)->hash($this->oldPassword)),
            $now
        );
        $this->container->get(Connection::class)->transactional(
            fn() => $this->container->get(UserRepository::class)->add($user)
        );
    }

    /**
     * Returns persisted state only for private assertions
     *
     * @return array<string, mixed>
     */
    private function user(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [$this->target->toString()]);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * Builds the real protected route with no cookie-derived authority
     *
     * @param array<string, mixed> $changes
     * @param array<string, string> $headers
     */
    private function request(
        array $changes = [],
        array $headers = [],
        ?string $raw = null,
        string $uri = 'https://agent-os.test/api/v1/auth/password/change'
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest('POST', $uri, ['REMOTE_ADDR' => '127.0.0.1']);
        $defaults = [
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://agent-os.test',
            'Sec-Fetch-Site' => 'same-origin',
            'Authorization'  => 'Bearer '.$this->access,
            'Cookie'         => BrowserSessionCookies::REFRESH_NAME.'='.$this->refresh
        ];
        foreach (array_replace($defaults, $headers) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $input = [
            'current_password'      => $this->oldPassword,
            'password'              => $this->password,
            'password_confirmation' => $this->password
        ];
        $request->getBody()->write($raw ?? json_encode(array_replace($input, $changes), JSON_THROW_ON_ERROR));

        return $this->app->handle($request);
    }

    /**
     * Validates public contracts and diagnostics without exposing fixture secrets
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
            new OperationAddress('/api/v1/auth/password/change', 'post'),
            $response
        );
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        if ($status === 200) {
            self::assertSame(['status' => 'success', 'data' => ['changed' => true]], $body);
        } else {
            self::assertSame([], $response->getHeader('Set-Cookie'));
        }
        $public = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        foreach ([$this->oldPassword, $this->password, $this->access, $this->refresh] as $marker) {
            self::assertFalse(str_contains($public, $marker), 'Secret reached diagnostics.');
        }

        return $body;
    }
}
