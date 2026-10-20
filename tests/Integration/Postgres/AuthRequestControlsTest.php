<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\Failure\ApiFailureMapper;
use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Middleware\Api\ApiFailureMiddleware;
use App\Adapter\Http\Middleware\Api\Auth\AuthEndpointGuard;
use App\Adapter\Http\Middleware\Api\Auth\BrowserMutationGuard;
use App\Adapter\Http\Middleware\Api\Validation\ApiInputValidation;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Adapter\Security\HmacCsrfProofs;
use App\Application\Security\Csrf\Clock\CsrfClock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AccessClock;
use Tests\Support\EmptyAuthJsonAction;
use Tests\Support\LoginJsonAction;

/**
 * Exercises the shared JSON/browser/admission/failure pipeline without shipping future endpoint Actions
 */
final class AuthRequestControlsTest extends TestCase
{
    private Connection $connection;
    private AccessClock $clock;
    private HmacCsrfProofs $proofs;
    private string $nonce;
    private string $proof;
    private TestHandler $logs;
    /** @var App<null> */
    private App $app;

    /**
     * Composes real controls against only a guarded disposable PostgreSQL database
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
        $this->connection->executeStatement('TRUNCATE auth_rate_limits');
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-20T00:00:00Z'));
        $csrfClock = new class ($this->clock) implements CsrfClock {
            /**
             * Constructs a deterministic CSRF clock
             */
            public function __construct(private AccessClock $clock)
            {
            }

            /**
             * @inheritDoc
             */
            public function now(): int
            {
                return $this->clock->now()->getTimestamp();
            }
        };
        $this->proofs = new HmacCsrfProofs(bin2hex(random_bytes(32)), 'https://agent-os.test');
        $this->nonce = bin2hex(random_bytes(32));
        $this->proof = $this->proofs->sign($this->nonce, $csrfClock->now() + 900);
        $browser = new BrowserMutationGuard($this->proofs, $csrfClock);
        $admission = new AuthAdmission(
            new PostgresAuthAttempts($this->connection, $this->clock),
            bin2hex(random_bytes(32)),
            2,
            3
        );
        $responses = new JSendResponseFactory(new ResponseFactory(), new StreamFactory());
        $this->app = new App(new ResponseFactory());
        foreach (AuthOperation::cases() as $operation) {
            $action = $operation === AuthOperation::LOGIN ? LoginJsonAction::class : EmptyAuthJsonAction::class;
            $this->app->post('/api/v1/auth/'.$operation->value, $action.':handle')
                ->add(new AuthEndpointGuard($browser, $admission, $operation))
                ->add(new ApiInputValidation($responses));
        }
        $this->app->addRoutingMiddleware();
        $this->logs = new TestHandler();
        $this->app->add(new ApiFailureMiddleware(
            new ApiFailureMapper(),
            $responses,
            new Logger('test', [$this->logs])
        ));
        LoginJsonAction::$calls = EmptyAuthJsonAction::$calls = 0;
    }

    /**
     * Releases only this test's connection
     */
    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * Allows reusable proofs and absent Fetch Metadata while enforcing per-endpoint peer limits
     */
    public function testAllowedProfilesAndBudgets(): void
    {
        foreach (AuthOperation::cases() as $operation) {
            self::assertSame(204, $this->request($operation)->getStatusCode());
            self::assertSame(204, $this->request($operation, [
                'Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty'
            ])->getStatusCode());
            $response = $this->request($operation);
            self::assertSame(429, $response->getStatusCode());
            self::assertSame('900', $response->getHeaderLine('Retry-After'));
            foreach (['192.0.2.2' => 204, '192.0.2.3' => 429] as $network => $status) {
                self::assertSame($status, $this->request($operation, network: $network)->getStatusCode());
            }
        }
        self::assertSame(3, LoginJsonAction::$calls);
        self::assertSame(6, EmptyAuthJsonAction::$calls);
    }

    /**
     * Rejects malformed and foreign transport before dispatch or counter writes and without private diagnostics
     *
     * @param array<string, string|list<string>|null> $headers
     */
    #[DataProvider('rejections')]
    public function testRejections(
        array $headers,
        ?string $body = null,
        string $method = 'POST',
        string $origin = 'https://agent-os.test'
    ): void {
        $response = $this->request(AuthOperation::LOGIN, $headers, $body, $method, $origin);
        self::assertContains($response->getStatusCode(), [400, 403, 404, 405]);
        self::assertSame(0, LoginJsonAction::$calls);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $surfaces = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        $secrets = [
            $this->nonce, $this->proof, 'private-password-marker', 'private-email-marker', 'SQL-private-marker'
        ];
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $surfaces);
        }
    }

    /**
     * Supplies the accepted request-control matrix including source and ambiguity attacks
     *
     * @return iterable<string, array{
     *     0: array<string, string|list<string>|null>, 1?: ?string, 2?: string, 3?: string
     * }>
     */
    public static function rejections(): iterable
    {
        foreach (['Origin', 'Host', 'X-CSRF-Proof', 'Cookie', 'Content-Type'] as $name) {
            yield 'missing '.$name => [[$name => null]];
            yield 'duplicate '.$name => [[$name => ['bad', 'bad']]];
        }
        foreach (
            [
            'null', 'http://agent-os.test', 'https://agent-os.test.evil', 'https://user@agent-os.test',
            'https://agent-os.test/', 'https://agent-os.test:444', 'https://agent-os.test, https://evil.test'
            ] as $origin
        ) {
            yield 'origin '.$origin => [['Origin' => $origin]];
        }
        foreach (['same-site', 'cross-site', 'none', 'Same-Origin', 'invalid', 'same-origin, same-origin'] as $site) {
            yield 'fetch '.$site => [['Sec-Fetch-Site' => $site]];
        }
        foreach (
            [
            'Sec-Fetch-Mode'                => 'navigate',
            'Sec-Fetch-Dest'                => 'document',
            'Sec-Fetch-User'                => '?1',
            'Forwarded'                     => 'proto=https',
            'X-Forwarded-Proto'             => 'https',
            'X-HTTP-Method-Override'        => 'POST',
            'X-Method-Override'             => 'POST',
            'Content-Encoding'              => 'gzip',
            'Authorization'                 => 'private-password-marker',
            'Access-Control-Request-Method' => 'POST'
            ] as $name => $value
        ) {
            yield $name => [[$name => $value]];
        }
        foreach (
            [
            'text/plain', 'multipart/form-data', 'application/x-www-form-urlencoded',
            'application/json; charset=latin1', 'application/json; private=1'
            ] as $type
        ) {
            yield $type => [['Content-Type' => $type]];
        }
        foreach (
            [
            '', '[]', 'null', '{}', '{', '{"email":"a","email":"b"}',
            '{"email":"a","\\u0065mail":"b"}', '{"email":{}}', str_repeat(' ', 65537), "{\"email\":\"\xff\"}",
            '{"email":"private-email-marker","password":"private-password-marker","remember":"false"}',
            '{"email":"PRIVATE@example.test","password":"private-password-marker","remember":false}',
            '{"email":"private@example.test","password":"private-password-marker","remember":false,"csrf":"marker"}'
            ] as $index => $body
        ) {
            yield 'body '.$index => [[], $body];
        }
        yield 'invalid email' => [[], '{"email":"not-email","password":"marker","remember":false}'];
        yield 'password length' => [[], json_encode([
            'email'    => 'private@example.test',
            'password' => str_repeat('x', 129),
            'remember' => false
        ], JSON_THROW_ON_ERROR)];
        yield 'bad proof' => [['X-CSRF-Proof' => 'private-password-marker']];
        yield 'insecure transport' => [[], null, 'POST', 'http://agent-os.test'];
        yield 'query source' => [[], null, 'POST', 'https://agent-os.test?csrf=SQL-private-marker'];
        yield 'preflight' => [['Origin' => 'https://evil.test'], null, 'OPTIONS'];
        yield 'wrong method' => [[], null, 'GET'];
    }

    /**
     * Invalidates expired and replaced nonce proofs but permits re-bootstrap and refresh reuse
     */
    public function testNonceLifecycle(): void
    {
        $oldProof = $this->proof;
        $oldNonce = $this->nonce;
        self::assertSame(204, $this->request(AuthOperation::REFRESH)->getStatusCode());
        $replacement = new BrowserSessionCookies($this->clock)->rotateNonce();
        $this->nonce = explode(';', explode('=', $replacement, 2)[1], 2)[0];
        self::assertSame(403, $this->request(AuthOperation::REFRESH)->getStatusCode());
        $this->proof = $this->proofs->sign($this->nonce, $this->clock->time->getTimestamp() + 900);
        self::assertSame(204, $this->request(AuthOperation::REFRESH)->getStatusCode());
        $this->clock->time = $this->clock->time->modify('+900 seconds');
        self::assertSame(403, $this->request(AuthOperation::REFRESH)->getStatusCode());
        $this->proof = $oldProof;
        $this->nonce = $oldNonce;
        self::assertSame(403, $this->request(AuthOperation::LOGOUT)->getStatusCode());
        self::assertSame(403, $this->request(AuthOperation::LOGOUT, ['Cookie' => null])->getStatusCode());
    }

    /**
     * Requires exactly an empty object for refresh and logout
     */
    public function testEmptyShapesAndDuplicateNonce(): void
    {
        foreach ([AuthOperation::REFRESH, AuthOperation::LOGOUT] as $operation) {
            self::assertSame(400, $this->request($operation, [], '{"remember":false}')->getStatusCode());
            self::assertSame(400, $this->request($operation, [], '[]')->getStatusCode());
            $duplicate = CsrfCookie::NAME.'='.$this->nonce.'; '.CsrfCookie::NAME.'='.$this->nonce;
            self::assertSame(403, $this->request($operation, ['Cookie' => $duplicate])->getStatusCode());
        }
        self::assertSame(0, EmptyAuthJsonAction::$calls);
    }

    /**
     * Accepts equivalent effective HTTPS ports and the explicitly allowed UTF-8 media parameter
     */
    public function testNormalizedOriginAndCharset(): void
    {
        self::assertSame(204, $this->request(AuthOperation::LOGIN, [
            'Origin' => 'https://AGENT-OS.test:443', 'Content-Type' => 'application/json; charset=utf-8'
        ])->getStatusCode());
    }

    /**
     * Builds a real PSR request with no reliance on parsed-body defaults or forwarded identity
     *
     * @param AuthOperation $operation
     * @param array<string, string|list<string>|null> $headers
     */
    private function request(
        AuthOperation $operation,
        array $headers = [],
        ?string $body = null,
        string $method = 'POST',
        string $origin = 'https://agent-os.test',
        string $network = '192.0.2.1'
    ): ResponseInterface {
        $parts = explode('?', $origin, 2);
        $request = new ServerRequestFactory()->createServerRequest(
            $method,
            $parts[0].'/api/v1/auth/'.$operation->value.(isset($parts[1]) ? '?'.$parts[1] : ''),
            ['REMOTE_ADDR' => $network]
        );
        $defaults = [
            'Origin'       => 'https://agent-os.test',
            'X-CSRF-Proof' => $this->proof,
            'Cookie'       => CsrfCookie::NAME.'='.$this->nonce,
            'Content-Type' => 'application/json'
        ];
        foreach ([...$defaults, ...$headers] as $name => $value) {
            $request = $value === null ? $request->withoutHeader($name) : $request->withHeader($name, $value);
        }
        $defaultBody = '{"email":"private@example.test","password":"private-password-marker","remember":false}';
        $body ??= $operation === AuthOperation::LOGIN ? $defaultBody : '{}';

        return $this->app->handle($request->withBody(new StreamFactory()->createStream($body)));
    }
}
