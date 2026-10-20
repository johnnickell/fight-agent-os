<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\HmacCsrfProofs;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Event\CurrentSessionLoggedOut;
use Fight\AccessControl\Domain\AccessControl\User\Event\RedactedCommandFailed;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
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
 * Proves refresh and current logout through real package, HTTP and guarded PostgreSQL boundaries
 */
final class SessionLifecycleApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private AccessClock $clock;
    private string|false $oldUrl;
    private string $nonce;
    private string $proof;
    private string $password;
    private TestHandler $logs;
    /** @var list<string> */
    private array $failures = [];

    /**
     * Creates an isolated active identity and observes only safe boundary output
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
        $tokens = new AccessTokens();
        $this->clock = $tokens->clock;
        $this->clock->time = new DateTimeImmutable('2026-10-20T00:00:00.654321Z');
        $container->set(Clock::class, fn(): Clock => $this->clock);
        $container->set(TokenEncoder::class, static fn(): TokenEncoder => $tokens->jwt);
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $this->password = 'é'.bin2hex(random_bytes(8));
        $user = User::invite(UserId::generate(), EmailAddress::fromString('session@example.test'), $this->clock->now());
        $user->activate(
            PasswordHash::fromString($container->get(PasswordHasher::class)->hash($this->password)),
            $this->clock->now()
        );
        $container->get(Connection::class)->transactional(fn() => $container->get(UserRepository::class)->add($user));
        $this->nonce = bin2hex(random_bytes(32));
        $this->proof = $container->get(HmacCsrfProofs::class)->sign($this->nonce, time() + 900);
        $container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(RedactedCommandFailed::class),
            function (EventMessage $event): void {
                $this->failures[] = serialize($event);
            }
        );
    }

    /**
     * Restores only owned test state
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement('ALTER TABLE refresh_sessions DROP CONSTRAINT IF EXISTS session_abort');
        $this->connection->executeStatement('ALTER TABLE auth_rate_limits DROP CONSTRAINT IF EXISTS session_abort');
        putenv($this->oldUrl === false ? 'DATABASE_URL' : 'DATABASE_URL='.$this->oldUrl);
        $this->connection->close();
    }

    /**
     * Rotates both exact profiles near idle and absolute deadlines without extending absolute authority
     */
    #[DataProvider('profiles')]
    public function testRotation(bool $remember): void
    {
        $credential = $this->login($remember);
        $before = $this->connection->fetchAssociative('SELECT * FROM refresh_sessions');
        self::assertIsArray($before);
        $absolute = new DateTimeImmutable($before['absolute_expires_at']);
        $steps = $remember ? 13 : 2;
        for ($i = 0; $i < $steps; ++$i) {
            $next = $this->clock->now()->modify($remember ? '+29 days' : '+23 hours 30 minutes');
            $this->clock->time = $i === $steps - 1 ? $absolute->modify('-1 hour') : $next;
            $response = $this->request('refresh', $credential);
            $data = $this->check($response, 200, 'refresh')['data'];
            self::assertSame(900, $data['expires_at'] - $data['issued_at']);
            self::assertSame($this->clock->now()->getTimestamp(), $data['issued_at']);
            $claims = json_decode(
                base64_decode(strtr(explode('.', $data['access_token'])[1], '-_', '+/')),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame($data['expires_at'], $claims['exp']);
            self::assertSame($before['id'], $claims['sid']);
            self::assertSame($before['user_id'], $claims['sub']);
            self::assertSame((int) $before['authentication_version'], $claims['auth_version']);
            self::assertCount(1, $response->getHeader('Set-Cookie'));
            $cookie = $response->getHeaderLine('Set-Cookie');
            self::assertStringContainsString('; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict', $cookie);
            self::assertStringNotContainsString('Domain=', $cookie);
            self::assertStringNotContainsString('Max-Age=', $cookie);
            if ($remember) {
                self::assertStringEndsWith('; Expires='.$absolute->format('D, d M Y H:i:s').' GMT', $cookie);
            } else {
                self::assertStringNotContainsString('Expires=', $cookie);
            }
            self::assertStringNotContainsString($credential, (string) $response->getBody());
            $diagnostics = json_encode([
                $this->logs->getRecords(),
                $this->connection->fetchAllAssociative('SELECT * FROM audit_evidence'),
                $response->getHeaderLine('X-Correlation-ID'),
                $this->failures
            ], JSON_THROW_ON_ERROR);
            foreach ([$credential, $data['access_token'], $before['credential_digest']] as $marker) {
                self::assertFalse(str_contains($diagnostics, $marker), 'Sensitive material entered diagnostics.');
            }
            $credential = explode(';', explode('=', $cookie, 2)[1], 2)[0];
        }
        $after = $this->connection->fetchAssociative('SELECT * FROM refresh_sessions');
        self::assertIsArray($after);
        $preserved = ['id', 'user_id', 'created_at', 'absolute_expires_at', 'authentication_version', 'remembered'];
        foreach ($preserved as $key) {
            self::assertSame($before[$key], $after[$key]);
        }
        self::assertEquals($absolute, new DateTimeImmutable($after['idle_expires_at']));
        self::assertSame(
            $steps,
            (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_session_used_credentials')
        );
        self::assertNotSame($before['credential_digest'], $after['credential_digest']);
    }

    /**
     * Supplies both accepted cookie persistence profiles
     *
     * @return iterable<string, array{bool}>
     */
    public static function profiles(): iterable
    {
        yield 'ordinary' => [false];
        yield 'remembered' => [true];
    }

    /**
     * Keeps bounded conflict secretless and revokes a replay outside the precise five-second window
     */
    public function testConflictAndReplay(): void
    {
        $old = $this->login();
        $this->clock->time = $this->clock->now()->modify('+1 second');
        $winner = $this->request('refresh', $old);
        $this->check($winner, 200, 'refresh');
        $current = explode(';', explode('=', $winner->getHeaderLine('Set-Cookie'), 2)[1], 2)[0];
        $this->clock->time = $this->clock->now()->modify('+4 seconds');
        $conflict = $this->request('refresh', $old);
        self::assertSame(['status' => 'error', 'message' => 'Conflict.'], $this->check($conflict, 409, 'refresh'));
        self::assertSame([], $conflict->getHeader('Set-Cookie'));
        self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
        $this->clock->time = $this->clock->now()->modify('+2 seconds');
        $this->assertDeleted($this->request('refresh', $old), 401, 'refresh');
        self::assertTrue($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
        $this->assertDeleted($this->request('refresh', $current), 401, 'refresh');
    }

    /**
     * Rejects an older used credential even when the latest rotation remains in the conflict window
     */
    public function testOlderReplay(): void
    {
        $old = $this->login();
        $this->clock->time = $this->clock->now()->modify('+1 second');
        $first = $this->request('refresh', $old);
        $this->check($first, 200, 'refresh');
        $current = explode(';', explode('=', $first->getHeaderLine('Set-Cookie'), 2)[1], 2)[0];
        $this->clock->time = $this->clock->now()->modify('+1 second');
        $this->check($this->request('refresh', $current), 200, 'refresh');
        $this->assertDeleted($this->request('refresh', $old), 401, 'refresh');
        self::assertTrue($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
    }

    /**
     * Revokes only the current cookie and emits the logout event after commit
     */
    public function testCurrentLogoutAndUnknownParity(): void
    {
        $first = $this->login();
        $second = $this->login();
        $events = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(CurrentSessionLoggedOut::class),
            function () use (&$events): void {
                $events[] = [
                    $this->container->get(Connection::class)->isTransactionActive(),
                    (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions WHERE revoked')
                ];
            }
        );
        $expected = $this->assertDeleted($this->request('logout', $first), 200, 'logout');
        self::assertSame([[false, 1]], $events);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions WHERE NOT revoked')
        );
        $this->clock->time = $this->clock->now()->modify('+1 second');
        $this->check($this->request('refresh', $second), 200, 'refresh');
        foreach ([$first, bin2hex(random_bytes(32)), null, 'malformed'] as $credential) {
            self::assertSame($expected, $this->assertDeleted($this->request('logout', $credential), 200, 'logout'));
        }
    }

    /**
     * Maps all non-authoritative persisted states to the same terminal response without token issuance
     */
    #[DataProvider('terminalStates')]
    public function testTerminalStates(string $sql): void
    {
        $credential = $this->login();
        if (str_starts_with($sql, 'clock:')) {
            $this->clock->time = $this->clock->now()->modify(substr($sql, 6));
        } else {
            $this->connection->executeStatement($sql);
        }
        $this->assertDeleted($this->request('refresh', $credential), 401, 'refresh');
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_session_used_credentials'));
    }

    /**
     * Supplies real authoritative-state rejection boundaries
     *
     * @return iterable<string, array{string}>
     */
    public static function terminalStates(): iterable
    {
        yield 'revoked' => ['UPDATE refresh_sessions SET revoked = TRUE'];
        yield 'idle expired' => ['clock:+1 day'];
        yield 'absolute expired' => ['clock:+2 days'];
        yield 'pending' => ["UPDATE users SET state = 'pending_activation', password_hash = NULL"];
        yield 'disabled' => ["UPDATE users SET state = 'disabled'"];
        yield 'deleted' => ["UPDATE users SET state = 'deleted'"];
        yield 'stale version' => ['UPDATE users SET authentication_version = authentication_version + 1'];
    }

    /**
     * Rejects injected sources and hostile browser requests before any package mutation
     *
     * @param string $raw
     * @param array<string, string> $headers
     * @param integer $status
     */
    #[DataProvider('hostileRequests')]
    public function testGuards(string $raw, array $headers, int $status): void
    {
        $credential = $this->login();
        foreach (['refresh', 'logout'] as $operation) {
            $response = $this->request($operation, $credential, $raw, $headers);
            $this->check($response, $status, $operation);
            self::assertSame([], $response->getHeader('Set-Cookie'));
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT revision FROM refresh_sessions'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Supplies source, shape and browser-control violations for both registered operations
     *
     * @return iterable<string, array{string, array<string, string>, int}>
     */
    public static function hostileRequests(): iterable
    {
        $bodies = ['', '[]', '{', '{"credential":"private"}', '{"refresh_token":"private"}',
            '{"a":1,"a":2}', str_repeat(' ', 65537)];
        foreach ($bodies as $i => $raw) {
            yield 'body '.$i => [$raw, [], 400];
        }
        foreach (LoginApiTest::invalidHeaders() as $key => [$name, $value]) {
            yield 'header '.$key => ['{}', [$name => $value], $name === 'Content-Type' ? 400 : 403];
        }
        yield 'bearer alternative' => ['{}', ['Authorization' => 'Bearer private'], 403];
    }

    /**
     * Does not accept parsed cookie parameters, lookalike names, encoded or duplicated material
     */
    public function testCookieSources(): void
    {
        $credential = $this->login();
        $badCookies = [null, 'bad', strtoupper($credential), '"'.$credential.'"', '%61'.$credential,
            $credential.'; '.BrowserSessionCookies::REFRESH_NAME.'='.$credential];
        foreach ($badCookies as $bad) {
            $this->assertDeleted($this->request('refresh', $bad), 401, 'refresh');
        }
        $this->assertDeleted($this->request(
            'refresh',
            null,
            headers: ['Cookie' => CsrfCookie::NAME.'='.$this->nonce.'; refresh='.$credential]
        ), 401, 'refresh');
        self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
    }

    /**
     * Preserves business rollback, expires uncertain authority and prevents SQL leakage into events
     */
    public function testUncertainFailureRedaction(): void
    {
        $credential = $this->login();
        $this->clock->time = $this->clock->now()->modify('+1 second');
        $before = $this->connection->fetchAssociative('SELECT * FROM refresh_sessions');
        $this->connection->executeStatement(
            'ALTER TABLE refresh_sessions ADD CONSTRAINT session_abort CHECK (false) NOT VALID'
        );
        foreach (['refresh', 'logout'] as $operation) {
            $this->assertDeleted($this->request($operation, $credential), 500, $operation);
        }
        self::assertSame($before, $this->connection->fetchAssociative('SELECT * FROM refresh_sessions'));
        self::assertCount(2, $this->failures);
        foreach ($this->failures as $failure) {
            foreach (['SQLSTATE', 'session_abort', $credential] as $marker) {
                self::assertFalse(str_contains($failure, $marker), 'Sensitive detail entered an event.');
            }
        }
    }

    /**
     * Denies endpoint exhaustion and limiter outages before touching an otherwise valid session
     */
    public function testAdmissionLimitsAndOutage(): void
    {
        $credential = $this->login();
        foreach (['refresh', 'logout'] as $operation) {
            $this->connection->executeStatement('TRUNCATE auth_rate_limits');
            for ($i = 0; $i < 20; ++$i) {
                $status = $operation === 'refresh' ? 401 : 200;
                $this->assertDeleted($this->request($operation, null), $status, $operation);
            }
            $response = $this->request($operation, $credential);
            $this->check($response, 429, $operation);
            self::assertSame([], $response->getHeader('Set-Cookie'));
            self::assertGreaterThanOrEqual(1, (int) $response->getHeaderLine('Retry-After'));
            self::assertLessThanOrEqual(900, (int) $response->getHeaderLine('Retry-After'));
            self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
        }
        $this->connection->executeStatement('TRUNCATE auth_rate_limits');
        $this->connection->executeStatement(
            'ALTER TABLE auth_rate_limits ADD CONSTRAINT session_abort CHECK (false) NOT VALID'
        );
        foreach (['refresh', 'logout'] as $operation) {
            $this->check($this->request($operation, $credential), 429, $operation);
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT revision FROM refresh_sessions'));
    }

    /**
     * Rejects query, insecure transport, wrong methods and duplicate cookie headers before dispatch
     */
    public function testTransportBoundaries(): void
    {
        $credential = $this->login();
        foreach (['refresh', 'logout'] as $operation) {
            $this->check($this->request($operation, $credential, query: 'refresh=alternate'), 400, $operation);
            $this->check($this->request($operation, $credential, scheme: 'http'), 403, $operation);
            foreach (['GET', 'OPTIONS', 'PUT'] as $method) {
                $this->check($this->request($operation, $credential, method: $method), 405, $operation);
            }
            $request = new ServerRequestFactory()->createServerRequest(
                'POST',
                'https://agent-os.test/api/v1/auth/'.$operation,
                ['REMOTE_ADDR' => '127.0.0.1']
            )->withHeader('Content-Type', 'application/json')->withHeader('Origin', 'https://agent-os.test')
                ->withHeader('X-CSRF-Proof', $this->proof)->withHeader('Cookie', [
                    CsrfCookie::NAME.'='.$this->nonce,
                    BrowserSessionCookies::REFRESH_NAME.'='.$credential
                ])->withCookieParams([BrowserSessionCookies::REFRESH_NAME => $credential]);
            $request->getBody()->write('{}');
            $this->check($this->app->handle($request), 403, $operation);
        }
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT revision FROM refresh_sessions'));
        self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
    }

    /**
     * Coordinates independent HTTP contenders and terminal winner orders without sleeps
     */
    #[DataProvider('raceOrders')]
    public function testRefreshRaces(string $winner): void
    {
        $credential = $this->login();
        $this->clock->time = $this->clock->now()->modify('+1 second');
        $children = [];
        try {
            for ($i = 0; $i < ($winner === 'refresh' ? 2 : 1); ++$i) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\RefreshRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode([
                    'credential' => $credential,
                    'nonce'      => $this->nonce,
                    'proof'      => $this->proof,
                    'time'       => $this->clock->now()->format('Y-m-d\\TH:i:s.uP')
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                self::assertSame('ready', trim((string) fgets($pipes[1])));
            }
            if ($winner === 'logout') {
                $this->assertDeleted($this->request('logout', $credential), 200, 'logout');
            } elseif ($winner === 'disable' || $winner === 'version') {
                $users = $this->container->get(UserRepository::class);
                $id = UserId::fromString($this->connection->fetchOne('SELECT id FROM users'));
                $this->container->get(Connection::class)->transactional(function () use ($users, $id, $winner): void {
                    $expected = $users->getById($id);
                    self::assertInstanceOf(User::class, $expected);
                    $replacement = clone $expected;
                    if ($winner === 'disable') {
                        $replacement->disable($this->clock->now());
                        self::assertTrue($users->replaceLifecycleState($expected, $replacement));
                    } else {
                        $replacement->changePassword($expected->getPasswordHash(), $this->clock->now());
                        $replacement->advanceAuthenticationAuthorityRevision();
                        self::assertTrue($users->replaceAuthenticationAuthority($expected, $replacement));
                    }
                });
            }
            foreach ($children as $i => [$process, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
                $status = trim((string) fgets($pipes[1]));
                if ($status === '') {
                    stream_set_timeout($pipes[2], 2);
                    self::fail('Contender exited without a safe status.');
                }
                self::assertSame($winner === 'refresh' ? ($i === 0 ? '200' : '409') : '401', $status);
            }
            self::assertSame(
                $winner === 'refresh' ? 1 : 0,
                (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_session_used_credentials')
            );
            self::assertSame($winner === 'logout', $this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
            if ($winner === 'refresh') {
                // The superseded cookie cannot revoke the winner; it is not logout-everywhere.
                $this->assertDeleted($this->request('logout', $credential), 200, 'logout');
                self::assertFalse($this->connection->fetchOne('SELECT revoked FROM refresh_sessions'));
            }
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
     * Supplies compare-and-set, logout, lifecycle and authentication-version winner orders
     *
     * @return iterable<string, array{string}>
     */
    public static function raceOrders(): iterable
    {
        foreach (['refresh', 'logout', 'disable', 'version'] as $winner) {
            yield $winner => [$winner];
        }
    }

    /**
     * Seeds through the exact package login contract without browser-cookie introspection in application code
     */
    private function login(bool $remember = false): string
    {
        return $this->container->get(AuthenticationService::class)->login(
            'session@example.test',
            $this->password,
            $remember
        )->getRefreshCredential()->toString();
    }

    /**
     * Sends a real guarded mutation with only raw browser cookie authority
     *
     * @param string $operation
     * @param string|null $credential
     * @param string $raw
     * @param array<string, string> $headers
     * @param string $method
     * @param string $scheme
     * @param string $query
     */
    private function request(
        string $operation,
        ?string $credential,
        string $raw = '{}',
        array $headers = [],
        string $method = 'POST',
        string $scheme = 'https',
        string $query = ''
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest(
            $method,
            $scheme.'://agent-os.test/api/v1/auth/'.$operation.($query === '' ? '' : '?'.$query),
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $cookies = CsrfCookie::NAME.'='.$this->nonce;
        if ($credential !== null) {
            $cookies .= '; '.BrowserSessionCookies::REFRESH_NAME.'='.$credential;
        }
        $effectiveHeaders = array_replace([
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://agent-os.test',
            'Sec-Fetch-Site' => 'same-origin',
            'X-CSRF-Proof'   => $this->proof,
            'Cookie'         => $cookies
        ], $headers);
        foreach ($effectiveHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request->getBody()->write($raw);

        return $this->app->handle($request);
    }

    /**
     * Checks safe public mapping and real OpenAPI responses without dumping secret values
     *
     * @return array<string, mixed>
     */
    private function check(ResponseInterface $response, int $status, string $operation): array
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress('/api/v1/auth/'.$operation, 'post'),
            $response
        );
        $output = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        $markers = [$this->password, $this->nonce, $this->proof, 'SQLSTATE',
            'Stack trace', 'credential_digest', 'secret_hex'];
        foreach ($markers as $marker) {
            self::assertStringNotContainsString($marker, $output);
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Checks exact matching refresh and CSRF deletion on admitted terminal outcomes
     *
     * @return array<string, mixed>
     */
    private function assertDeleted(ResponseInterface $response, int $status, string $operation): array
    {
        $body = $this->check($response, $status, $operation);
        $cookies = $response->getHeader('Set-Cookie');
        self::assertCount(2, $cookies);
        $attributes = '; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict;';
        $expired = ' Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0';
        foreach ($cookies as $cookie) {
            self::assertStringContainsString(
                $attributes.$expired,
                $cookie
            );
        }
        self::assertStringStartsWith(BrowserSessionCookies::REFRESH_NAME.'=;', $cookies[0]);
        self::assertStringStartsWith(CsrfCookie::NAME.'=;', $cookies[1]);

        return $body;
    }
}
