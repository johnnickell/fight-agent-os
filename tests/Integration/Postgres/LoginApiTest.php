<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\HmacCsrfProofs;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\Event\RedactedCommandFailed;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserLoggedIn;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Service\Container;
use Fight\Common\Domain\Messaging\Event\EventMessage;
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
 * Proves canonical browser login against the real package, HTTP pipeline and guarded PostgreSQL
 */
final class LoginApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private UserId $target;
    private string $password;
    private string $nonce;
    private string $proof;
    private string|false $oldUrl;
    private TestHandler $logs;
    private AccessClock $clock;

    /**
     * Seeds an existing password without imposing new-password composition on login
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
        $this->connection->executeStatement(
            'TRUNCATE users, roles, permissions, audit_evidence, auth_rate_limits CASCADE'
        );
        $this->oldUrl = getenv('DATABASE_URL');
        putenv('DATABASE_URL='.$url);
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-20T00:00:00.654321Z'));
        $container->set(Clock::class, fn(): Clock => $this->clock);
        $tokens = new AccessTokens();
        $tokens->clock->time = $this->clock->now();
        $container->set(TokenEncoder::class, static fn(): TokenEncoder => $tokens->jwt);
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $this->password = 'é'.bin2hex(random_bytes(8));
        $this->target = UserId::generate();
        $user = User::invite($this->target, EmailAddress::fromString('login@example.test'), $this->clock->now());
        $user->activate(
            PasswordHash::fromString($container->get(PasswordHasher::class)->hash($this->password)),
            $this->clock->now()
        );
        $container->get(Connection::class)->transactional(fn() => $container->get(UserRepository::class)->add($user));
        $this->nonce = bin2hex(random_bytes(32));
        $this->proof = $container->get(HmacCsrfProofs::class)->sign($this->nonce, time() + 900);
    }

    /**
     * Removes only owned injections and restores the caller environment
     */
    protected function tearDown(): void
    {
        foreach (['users', 'refresh_sessions', 'auth_rate_limits'] as $table) {
            $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS login_abort');
        }
        putenv($this->oldUrl === false ? 'DATABASE_URL' : 'DATABASE_URL='.$this->oldUrl);
        $this->connection->close();
    }

    /**
     * Checks exact configured precise deadlines, package event and refresh-only cookie mapping
     */
    #[DataProvider('rememberOptions')]
    public function testSuccessfulSession(bool $remember): void
    {
        $events = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(UserLoggedIn::class),
            function () use (&$events): void {
                $events[] = [$this->container->get(Connection::class)->isTransactionActive(),
                    (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions')];
            }
        );
        $response = $this->request(['remember' => $remember]);
        $data = $this->assertResponse($response, 200)['data'];
        self::assertSame([[false, 1]], $events);
        self::assertSame(['access_token', 'issued_at', 'expires_at'], array_keys($data));
        self::assertSame(900, $data['expires_at'] - $data['issued_at']);
        self::assertSame($this->clock->now()->getTimestamp(), $data['issued_at']);
        $claims = json_decode(
            base64_decode(strtr(explode('.', $data['access_token'])[1], '-_', '+/')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertSame($data['expires_at'], $claims['exp']);
        self::assertSame($data['issued_at'], $claims['iat']);
        self::assertSame($this->target->toString(), $claims['sub']);
        self::assertArrayNotHasKey('roles', $claims);
        self::assertArrayNotHasKey('email', $claims);
        $session = $this->connection->fetchAssociative('SELECT * FROM refresh_sessions');
        self::assertIsArray($session);
        self::assertSame($remember, $session['remembered']);
        self::assertSame(
            $this->clock->now()->format('U.u'),
            (new DateTimeImmutable($session['created_at']))->format('U.u')
        );
        self::assertEquals(
            $this->clock->now()->modify($remember ? '+30 days' : '+1 day'),
            new DateTimeImmutable($session['idle_expires_at'])
        );
        $absolute = $this->clock->now()->modify($remember ? '+1 year' : '+2 days');
        self::assertEquals($absolute, new DateTimeImmutable($session['absolute_expires_at']));
        self::assertSame((int) $session['authentication_version'], $claims['auth_version']);
        self::assertSame($session['id'], $claims['sid']);
        $cookies = $response->getHeader('Set-Cookie');
        self::assertCount(2, $cookies);
        self::assertMatchesRegularExpression(
            '/\A__Secure-agent_os_refresh=[a-f0-9]{64}; Path=\/api\/v1\/auth; Secure; HttpOnly; SameSite=Strict/',
            $cookies[0]
        );
        self::assertStringNotContainsString('Domain=', implode('', $cookies));
        self::assertStringNotContainsString('Max-Age=', implode('', $cookies));
        if ($remember) {
            self::assertStringEndsWith('; Expires='.$absolute->format('D, d M Y H:i:s').' GMT', $cookies[0]);
        } else {
            self::assertStringNotContainsString('Expires=', implode('', $cookies));
        }
        $refresh = explode(';', explode('=', $cookies[0], 2)[1], 2)[0];
        self::assertStringNotContainsString($refresh, (string) $response->getBody());
        self::assertStringNotContainsString($this->nonce, $cookies[1]);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM audit_evidence'));
    }

    /**
     * Supplies the two explicit boolean choices, never PHP truthiness
     *
     * @return iterable<string, array{bool}>
     */
    public static function rememberOptions(): iterable
    {
        yield 'ordinary' => [false];
        yield 'remembered' => [true];
    }

    /**
     * Keeps lifecycle, wrong-password, absent identity and email throttle publicly indistinguishable
     */
    public function testGenericFailureParityAndVerificationWork(): void
    {
        $validator = $this->container->get(PasswordValidator::class);
        $tracking = new class ($validator) implements PasswordValidator {
            /** @var list<string> */
            public array $hashes = [];
            /**
             * Constructs the password-work observer
             */
            public function __construct(private readonly PasswordValidator $inner)
            {
            }
            /**
             * @inheritDoc
             */
            public function validate(#[\SensitiveParameter] string $password, string $hash): bool
            {
                $this->hashes[] = $hash;

                return $this->inner->validate($password, $hash);
            }
            /**
             * @inheritDoc
             */
            public function needsRehash(string $hash): bool
            {
                return $this->inner->needsRehash($hash);
            }
        };
        $this->container->set(PasswordValidator::class, static fn(): PasswordValidator => $tracking);
        $failures = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(RedactedCommandFailed::class),
            function (EventMessage $event) use (&$failures): void {
                $failures[] = serialize($event);
            }
        );
        $baseline = $this->assertResponse($this->request(['password' => 'incorrect']), 401);
        self::assertSame($baseline, $this->assertResponse($this->request(['email' => 'absent@example.test']), 401));
        $originalHash = $this->connection->fetchOne('SELECT password_hash FROM users');
        foreach (['pending_activation', 'disabled', 'deleted'] as $state) {
            $this->connection->executeStatement(
                'UPDATE users SET state = ?, password_hash = ?',
                [$state, $state === 'pending_activation' ? null : $originalHash]
            );
            self::assertSame($baseline, $this->assertResponse($this->request(), 401));
        }
        $this->connection->executeStatement("UPDATE users SET state = 'active'");
        // The fifth existing-email call consumed the last slot, despite all business rollbacks.
        $this->assertResponse($this->request(['password' => 'incorrect']), 401);
        $throttled = $this->request();
        self::assertSame($baseline, $this->assertResponse($throttled, 401));
        self::assertFalse($throttled->hasHeader('Retry-After'));
        self::assertCount(7, $tracking->hashes);
        self::assertNotSame($tracking->hashes[0], $tracking->hashes[1]);
        self::assertSame($tracking->hashes[1], $tracking->hashes[2]);
        self::assertSame($tracking->hashes[1], $tracking->hashes[6]);
        foreach ($tracking->hashes as $hash) {
            self::assertStringStartsWith('$argon2id$v=19$m=19456,t=2,p=1$', $hash);
        }
        self::assertCount(7, $failures);
        $diagnostics = implode('', $failures).json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        $markers = [$this->password, 'login@example.test', 'incorrect', $this->nonce, $this->proof,
            ...$tracking->hashes];
        foreach ($markers as $marker) {
            self::assertStringNotContainsString($marker, $diagnostics);
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Verifies exact existing-password bytes without adding setting-time composition or trimming
     */
    #[DataProvider('existingPasswords')]
    public function testExistingPasswordBytes(string $password): void
    {
        $this->password = $password;
        $hash = $this->container->get(PasswordHasher::class)->hash($password);
        $this->connection->executeStatement('UPDATE users SET password_hash = ?', [$hash]);
        $this->assertResponse($this->request(), 200);
    }

    /**
     * Supplies accepted existing-password codepoint and whitespace boundaries
     *
     * @return iterable<string, array{string}>
     */
    public static function existingPasswords(): iterable
    {
        yield 'one codepoint' => ['é'];
        yield '128 codepoints' => [str_repeat('é', 128)];
        yield 'spaces and Unicode' => ['  é space  '];
        yield 'control bytes remain password bytes' => ["a\0b\n"];
    }

    /**
     * Publishes real safe structural messages anonymously and does not export password-setting policy
     */
    public function testRealSchemaAndMultipleServerErrors(): void
    {
        $response = $this->app->handle(new ServerRequestFactory()->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/validations/login'
        ));
        self::assertSame(200, $response->getStatusCode());
        $schema = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame(['email', 'password'], array_keys($schema['fields']));
        $bad = $this->assertResponse($this->request(['email' => '', 'password' => '']), 400);
        self::assertContains($schema['fields']['email']['rules'][2]['message'], $bad['data']['fields']['body.email']);
        self::assertContains(
            $schema['fields']['password']['rules'][2]['message'],
            $bad['data']['fields']['body.password']
        );
        self::assertStringNotContainsString('uppercase', (string) $response->getBody());
    }

    /**
     * Rejects every wrong field type and boundary before authentication can mutate state
     */
    #[DataProvider('invalidFields')]
    public function testInvalidFields(string $field, mixed $value): void
    {
        $this->assertResponse($this->request([$field => $value]), 400);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Supplies the complete declared body field matrix
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidFields(): iterable
    {
        foreach (['email', 'password'] as $field) {
            foreach ([null, false, 1, 1.5, [], ['nested' => 'x'], ''] as $i => $value) {
                yield $field.$i => [$field, $value];
            }
        }
        foreach ([null, 'true', 'false', 1, 0, [], ['nested' => true]] as $i => $value) {
            yield 'remember'.$i => ['remember', $value];
        }
        yield 'email length' => ['email', str_repeat('a', 255)];
        yield 'email malformed' => ['email', 'not-email'];
        yield 'password length' => ['password', str_repeat('é', 129)];
    }

    /**
     * Rejects duplicate, missing, extra, nested, malformed, oversized and alternate-source JSON
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodies(string $raw): void
    {
        $this->assertResponse($this->request(raw: $raw), 400);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Supplies structural JSON refusals with no credential fixtures
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        $bodies = ['', '[]', '{}', 'null', '{', '{"email":"a","email":"b"}',
            '{"email":"a","em\\u0061il":"b"}', "{\"email\":\"\xff\"}", str_repeat(' ', 65537)];
        foreach ($bodies as $i => $raw) {
            yield (string) $i => [$raw];
        }
        foreach (['email', 'password', 'remember'] as $field) {
            $body = ['email' => 'login@example.test', 'password' => 'sample', 'remember' => false];
            unset($body[$field]);
            yield 'missing '.$field => [json_encode($body, JSON_THROW_ON_ERROR)];
        }
        foreach (['actor', 'role', 'permissions', 'token', 'csrf', '_method'] as $field) {
            yield 'extra '.$field => [json_encode([
                'email' => 'login@example.test', 'password' => 'sample', 'remember' => false, $field => 'x'
            ], JSON_THROW_ON_ERROR)];
        }
    }

    /**
     * Denies hostile browser requests before consuming admission or creating a session
     */
    #[DataProvider('invalidHeaders')]
    public function testBrowserGuards(string $name, string $value): void
    {
        $this->assertResponse($this->request(headers: [$name => $value]), $name === 'Content-Type' ? 400 : 403);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Supplies real login browser-boundary failures
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield from ActivationApiTest::invalidHeaders();
        yield 'forwarded' => ['Forwarded', 'proto=https'];
        yield 'navigation' => ['Sec-Fetch-Mode', 'navigate'];
    }

    /**
     * Rejects noncanonical identity and alternate request sources without even consuming admission
     */
    public function testCanonicalAndTransportBoundaries(): void
    {
        foreach (['Login@example.test', "login@example.test\n", 'login@example.test '.str_repeat('x', 300)] as $email) {
            $response = $this->request(['email' => $email]);
            self::assertContains($response->getStatusCode(), [400, 403]);
            self::assertSame([], $response->getHeader('Set-Cookie'));
        }
        $this->assertResponse($this->request(uri: 'http://agent-os.test/api/v1/auth/login'), 403);
        $this->assertResponse($this->request(uri: 'https://agent-os.test/api/v1/auth/login?email=alternate'), 400);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Accepts a trusted nondefault HTTPS authority despite PSR Host port normalization
     */
    public function testNondefaultPortAndRawHostConflicts(): void
    {
        $previousOrigin = getenv('APP_BROWSER_ORIGIN');
        try {
            putenv('APP_BROWSER_ORIGIN=https://agent-os.test:8443');
            $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
            $container = $this->app->getContainer();
            self::assertInstanceOf(Container::class, $container);
            $container->get('http.failure.logger')->setHandlers([$this->logs]);
            $this->proof = $container->get(HmacCsrfProofs::class)->sign($this->nonce, time() + 900);
            $this->assertResponse($this->request(
                headers: ['Origin' => 'https://agent-os.test:8443'],
                uri: 'https://agent-os.test:8443/api/v1/auth/login',
                server: ['HTTP_HOST' => 'agent-os.test:8443']
            ), 200);
            foreach (['foreign.test:8443', 'agent-os.test', 'agent-os.test:8443,foreign.test', ''] as $rawHost) {
                $this->assertResponse($this->request(
                    headers: ['Origin' => 'https://agent-os.test:8443'],
                    uri: 'https://agent-os.test:8443/api/v1/auth/login',
                    server: ['HTTP_HOST' => $rawHost]
                ), 403);
            }
        } finally {
            putenv($previousOrigin === false ? 'APP_BROWSER_ORIGIN' : 'APP_BROWSER_ORIGIN='.$previousOrigin);
        }
    }

    /**
     * Rehashes an existing password through the package without changing its authentication version
     */
    public function testRehashAndRepeatedLogin(): void
    {
        $old = password_hash($this->password, PASSWORD_BCRYPT);
        $this->connection->executeStatement('UPDATE users SET password_hash = ?', [$old]);
        $before = $this->connection->fetchAssociative(
            'SELECT authentication_version, authentication_authority_revision FROM users'
        );
        $this->assertResponse($this->request(), 200);
        $this->assertResponse($this->request(), 200);
        $after = $this->connection->fetchAssociative(
            'SELECT password_hash, authentication_version, authentication_authority_revision FROM users'
        );
        self::assertIsArray($before);
        self::assertIsArray($after);
        self::assertSame($before['authentication_version'], $after['authentication_version']);
        self::assertSame(
            (int) $before['authentication_authority_revision'] + 2,
            (int) $after['authentication_authority_revision']
        );
        self::assertStringStartsWith('$argon2id$', $after['password_hash']);
        self::assertTrue(password_verify($this->password, $after['password_hash']));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Rolls back user and session writes together while preserving consumed admission
     */
    #[DataProvider('rollbackTables')]
    public function testRollback(string $table): void
    {
        $this->connection->executeStatement(
            'UPDATE users SET password_hash = ?',
            [password_hash($this->password, PASSWORD_BCRYPT)]
        );
        $before = $this->connection->fetchAssociative('SELECT * FROM users');
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT login_abort CHECK (false) NOT VALID'
        );
        $this->assertResponse($this->request(), 500);
        self::assertSame($before, $this->connection->fetchAssociative('SELECT * FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Supplies the login atomic write boundaries
     *
     * @return iterable<string, array{string}>
     */
    public static function rollbackTables(): iterable
    {
        yield 'user' => ['users'];
        yield 'session' => ['refresh_sessions'];
    }

    /**
     * Bounds endpoint retries independently of identity and denies storage outages
     */
    public function testEndpointLimitAndStorageOutage(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $this->assertResponse($this->request(['email' => 'absent'.$i.'@example.test']), 401);
        }
        $response = $this->request();
        $this->assertResponse($response, 429);
        self::assertGreaterThanOrEqual(1, (int) $response->getHeaderLine('Retry-After'));
        self::assertLessThanOrEqual(900, (int) $response->getHeaderLine('Retry-After'));
        $this->connection->executeStatement('TRUNCATE auth_rate_limits');
        $this->connection->executeStatement(
            'ALTER TABLE auth_rate_limits ADD CONSTRAINT login_abort CHECK (false) NOT VALID'
        );
        $this->assertResponse($this->request(), 429);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Rejects stale authority snapshots in duplicate-login and lifecycle-change winner orders
     */
    #[DataProvider('raceWinners')]
    public function testLoginAuthorityRaces(bool $disableWins): void
    {
        $children = [];
        try {
            for ($i = 0; $i < ($disableWins ? 1 : 2); ++$i) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\LoginRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode(['password' => $this->password], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                self::assertSame('ready', trim((string) fgets($pipes[1])));
            }
            if ($disableWins) {
                $users = $this->container->get(UserRepository::class);
                $this->container->get(Connection::class)->transactional(function () use ($users): void {
                    $expected = $users->getById($this->target);
                    self::assertInstanceOf(User::class, $expected);
                    $replacement = clone $expected;
                    $replacement->disable($this->clock->now());
                    self::assertTrue($users->replaceLifecycleState($expected, $replacement));
                });
            }
            foreach ($children as $i => [$process, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
                self::assertSame(!$disableWins && $i === 0 ? 'committed' : 'rejected', trim((string) fgets($pipes[1])));
            }
            self::assertSame(
                $disableWins ? 0 : 1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions')
            );
            self::assertSame(
                $disableWins ? 'disabled' : 'active',
                $this->connection->fetchOne('SELECT state FROM users')
            );
            self::assertSame(0, (int) $this->connection->fetchOne(
                <<<'SQL'
SELECT count(*) FROM refresh_sessions s JOIN users u ON s.user_id = u.id
WHERE s.authentication_version <> u.authentication_version
SQL
            ));
        } finally {
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
    }

    /**
     * Supplies two captured logins and a lifecycle winner before login commit
     *
     * @return iterable<string, array{bool}>
     */
    public static function raceWinners(): iterable
    {
        yield 'competing logins' => [false];
        yield 'disable before login commit' => [true];
    }

    /**
     * Builds only the actual registered public login operation
     *
     * @param array<string, mixed> $changes
     * @param array<string, string> $headers
     * @param string|null $raw
     * @param string $uri
     * @param array<string, string> $server
     */
    private function request(
        array $changes = [],
        array $headers = [],
        ?string $raw = null,
        string $uri = 'https://agent-os.test/api/v1/auth/login',
        array $server = []
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest(
            'POST',
            $uri,
            array_replace(['REMOTE_ADDR' => '127.0.0.1'], $server)
        );
        $effectiveHeaders = array_replace([
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://agent-os.test',
            'Sec-Fetch-Site' => 'same-origin',
            'X-CSRF-Proof'   => $this->proof,
            'Cookie'         => CsrfCookie::NAME.'='.$this->nonce
        ], $headers);
        foreach ($effectiveHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $input = array_replace([
            'email' => 'login@example.test', 'password' => $this->password, 'remember' => false
        ], $changes);
        $request->getBody()->write($raw ?? json_encode($input, JSON_THROW_ON_ERROR));

        return $this->app->handle($request);
    }

    /**
     * Validates the explicit OpenAPI wire response and checks output without dumping private markers
     *
     * @return array<string, mixed>
     */
    private function assertResponse(ResponseInterface $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        if ($status !== 200) {
            self::assertSame([], $response->getHeader('Set-Cookie'));
        }
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress('/api/v1/auth/login', 'post'),
            $response
        );
        $output = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        $markers = [$this->password, $this->nonce, $this->proof, '$argon2id$', 'SQLSTATE',
            'Stack trace', 'secret_hex'];
        foreach ($markers as $marker) {
            self::assertStringNotContainsString($marker, $output);
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
