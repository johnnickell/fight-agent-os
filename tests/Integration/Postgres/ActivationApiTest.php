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
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryInvocation;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryOutcome;
use Fight\AccessControl\Application\AccessControl\CredentialDelivery\Service\CredentialDeliveryProvider;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Event\UserActivated;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Service\Container;
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
 * Proves actual anonymous activation, request protections, password policy and atomic session mapping
 */
final class ActivationApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private UserId $target;
    private string $credential = '';
    private string $password;
    private string $nonce;
    private string $proof;
    private TestHandler $logs;
    /** @var array<string, string|false> */
    private array $environment = [];

    /**
     * Seeds only a guarded disposable database through the real package invitation path
     */
    protected function setUp(): void
    {
        foreach (['DATABASE_URL', 'APP_CREDENTIAL_DELIVERY_KEY'] as $name) {
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
            'TRUNCATE users, roles, permissions, audit_evidence, activation_rate_limits CASCADE'
        );
        putenv('DATABASE_URL='.$url);
        putenv('APP_CREDENTIAL_DELIVERY_KEY='.bin2hex(random_bytes(32)));
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;
        $provider = $this->createStub(CredentialDeliveryProvider::class);
        $provider->method('deliver')->willReturnCallback(function (
            CredentialDeliveryInvocation $invocation
        ): CredentialDeliveryOutcome {
            $this->credential = $invocation->getCredential();

            return CredentialDeliveryOutcome::DELIVERED;
        });
        $container->set(CredentialDeliveryProvider::class, static fn(): CredentialDeliveryProvider => $provider);
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $this->target = UserId::generate();
        $container->get(CommandBus::class)->execute(new InvitePendingUser(
            UserId::generate()->toString(),
            $this->target,
            EmailAddress::fromString('activation@example.test')
        ));
        $this->password = 'A!9z '.bin2hex(random_bytes(12));
        $this->nonce = bin2hex(random_bytes(32));
        $this->proof = $container->get(HmacCsrfProofs::class)->sign($this->nonce, time() + 900);
    }

    /**
     * Removes only owned failure injections and restores environment
     */
    protected function tearDown(): void
    {
        foreach (['users', 'refresh_sessions', 'activation_grants', 'activation_rate_limits'] as $table) {
            $this->connection->executeStatement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS activation_abort');
        }
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        $this->connection->close();
    }

    /**
     * Activates exactly once and publishes only after the entire state is independently visible
     */
    public function testActivatesAndMapsOrdinaryCookieWithoutRefreshInJson(): void
    {
        $observed = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            \Fight\Common\Domain\Utility\ClassName::underscore(UserActivated::class),
            function () use (&$observed): void {
                $observed[] = [
                    $this->container->get(Connection::class)->isTransactionActive(),
                    $this->connection->fetchOne('SELECT state FROM users WHERE id = ?', [$this->target->toString()]),
                    (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions')
                ];
            }
        );
        $started = new DateTimeImmutable();
        $response = $this->request();
        $data = $this->assertResponse($response, 200)['data'];
        self::assertSame([[false, 'active', 1]], $observed);
        self::assertSame(['access_token', 'issued_at', 'expires_at'], array_keys($data));
        self::assertSame(900, $data['expires_at'] - $data['issued_at']);
        $cookies = $response->getHeader('Set-Cookie');
        self::assertCount(2, $cookies);
        self::assertMatchesRegularExpression(
            '/\A__Secure-agent_os_refresh=[a-f0-9]{64}; Path=\/api\/v1\/auth; Secure; HttpOnly; SameSite=Strict\z/',
            $cookies[0]
        );
        self::assertStringNotContainsString('Domain=', implode('', $cookies));
        self::assertStringNotContainsString('Expires=', implode('', $cookies));
        self::assertStringNotContainsString('Max-Age=', implode('', $cookies));
        $refresh = explode(';', explode('=', $cookies[0], 2)[1], 2)[0];
        self::assertStringNotContainsString($refresh, (string) $response->getBody());
        self::assertStringNotContainsString($this->nonce, $cookies[1]);
        $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE id = ?', [$this->target->toString()]);
        self::assertIsArray($user);
        self::assertSame('active', $user['state']);
        self::assertTrue(password_verify($this->password, $user['password_hash']));
        self::assertStringStartsWith('$argon2id$v=19$m=19456,t=2,p=1$', $user['password_hash']);
        $session = $this->connection->fetchAssociative('SELECT * FROM refresh_sessions');
        self::assertIsArray($session);
        self::assertFalse($session['remembered']);
        $created = (new DateTimeImmutable($session['created_at']))->getTimestamp();
        self::assertSame(86400, (new DateTimeImmutable($session['idle_expires_at']))->getTimestamp() - $created);
        self::assertSame(172800, (new DateTimeImmutable($session['absolute_expires_at']))->getTimestamp() - $created);
        self::assertGreaterThanOrEqual($started->getTimestamp(), $data['issued_at']);
        self::assertNotEmpty($this->connection->fetchOne('SELECT consumed_at FROM activation_grants'));
        $this->assertResponse($this->request(), 409);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        self::assertCount(1, $observed);
    }

    /**
     * Rejects policy bypasses before grant consumption, including common and Unicode composition cases
     */
    #[DataProvider('rejectedPasswords')]
    public function testRejectsPasswordSettingPolicy(string $password, string $reason): void
    {
        $response = $this->request(['password' => $password, 'password_confirmation' => $password]);
        $body = $this->assertResponse($response, 422);
        self::assertSame(
            ['Use 8–128 characters: uppercase, lowercase, number and symbol. Known-common passwords cannot be used.'],
            $body['data']['fields']['body.password']
        );
        self::assertStringNotContainsString($reason, (string) $response->getBody());
        self::assertSame('pending_activation', $this->connection->fetchOne('SELECT state FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM activation_grants'));
    }

    /**
     * Supplies independently derived policy violations that pass transport length validation
     *
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedPasswords(): iterable
    {
        yield 'uppercase' => ['lowercase!99', 'uppercase_required'];
        yield 'lowercase' => ['UPPERCASE!99', 'lowercase_required'];
        yield 'number' => ['Upperlower!', 'number_required'];
        yield 'symbol' => ['Upperlower99', 'symbol_required'];
        yield 'common' => ['P@ssw0rd', 'common_password'];
        yield 'titlecase is not uppercase' => ['ǅlower!99', 'uppercase_required'];
    }

    /**
     * Exposes the real anonymous safe schema and proves multiple server errors from its PHP declarations
     */
    public function testPublishedSchemaAndRealValidation(): void
    {
        $request = new ServerRequestFactory()->createServerRequest(
            'GET',
            'https://agent-os.test/api/v1/validations/invitation_activation'
        );
        $response = $this->app->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $schema = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame(['password', 'password_confirmation'], array_keys($schema['fields']));
        self::assertSame('passwordConfirmation', $schema['fields']['password_confirmation']['client_field']);
        self::assertSame(['password'], $schema['fields']['password_confirmation']['rules'][4]['depends_on']);
        $bad = $this->assertResponse($this->request(['password' => '', 'password_confirmation' => 'different']), 400);
        self::assertContains(
            $schema['fields']['password']['rules'][2]['message'],
            $bad['data']['fields']['body.password']
        );
        self::assertContains(
            $schema['fields']['password_confirmation']['rules'][4]['message'],
            $bad['data']['fields']['body.password_confirmation']
        );
        self::assertStringNotContainsString($this->credential, (string) $response->getBody());
    }

    /**
     * Preserves accepted Unicode and boundary-length bytes through JSON and the real password hash
     */
    #[DataProvider('acceptedPasswordBytes')]
    public function testAcceptedPasswordBytes(string $password): void
    {
        $this->assertResponse($this->request([
            'password'              => $password,
            'password_confirmation' => $password
        ]), 200);
        $hash = $this->connection->fetchOne('SELECT password_hash FROM users');
        self::assertTrue(password_verify($password, $hash));
    }

    /**
     * Supplies exact code-point boundaries and unmodified Unicode, whitespace and NUL contracts
     *
     * @return iterable<string, array{string}>
     */
    public static function acceptedPasswordBytes(): iterable
    {
        yield 'eight codepoints' => ['Aa1! z9Q'];
        yield '128 codepoints, not bytes' => ['Aa1!'.str_repeat('é', 124)];
        yield 'Unicode categories and spaces' => ['Éclair! ٩ Test'];
        yield 'combining characters and newline' => ["A!9ze\u{0301}\n space"];
        yield 'embedded NUL' => ["A!9z\0 space"];
    }

    /**
     * Rejects each field independently while all neighboring fields remain valid
     */
    #[DataProvider('invalidFieldValues')]
    public function testFieldRules(string $field, mixed $value): void
    {
        $this->assertResponse($this->request([$field => $value]), 400);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM activation_grants'));
    }

    /**
     * Supplies the complete declared field/type/length/comparison matrix
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidFieldValues(): iterable
    {
        foreach (['user_id', 'credential', 'password', 'password_confirmation'] as $field) {
            foreach ([null, true, 12] as $index => $value) {
                yield $field.' type '.$index => [$field, $value];
            }
        }
        yield 'user format' => ['user_id', 'not-an-id'];
        yield 'empty credential' => ['credential', ''];
        yield 'credential bound' => ['credential', str_repeat('a', 513)];
        yield 'password minimum' => ['password', 'Aa!9999'];
        yield 'password maximum' => ['password', 'Aa!9'.str_repeat('é', 125)];
        yield 'confirmation minimum' => ['password_confirmation', 'Aa!9999'];
        yield 'confirmation maximum' => ['password_confirmation', 'Aa!9'.str_repeat('é', 125)];
        yield 'confirmation comparison' => ['password_confirmation', 'Different!9'];
        yield 'unknown field' => ['remember', true];
    }

    /**
     * Refuses every missing declared field before admission or activation
     */
    #[DataProvider('requiredFields')]
    public function testMissingField(string $field): void
    {
        $input = [
            'user_id'               => $this->target->toString(),
            'credential'            => $this->credential,
            'password'              => $this->password,
            'password_confirmation' => $this->password
        ];
        unset($input[$field]);
        $this->assertResponse($this->request(raw: json_encode($input, JSON_THROW_ON_ERROR)), 400);
    }

    /**
     * Supplies each mandatory field rather than testing only an entirely empty body
     *
     * @return iterable<string, array{string}>
     */
    public static function requiredFields(): iterable
    {
        foreach (['user_id', 'credential', 'password', 'password_confirmation'] as $field) {
            yield $field => [$field];
        }
    }

    /**
     * Rejects malformed, unknown, oversized and disallowed body input without session or grant mutation
     */
    #[DataProvider('invalidBodies')]
    public function testBodyMatrix(string $body): void
    {
        $this->assertResponse($this->request(raw: $body), 400);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Supplies transport violations without including real credential bytes in data-set names or output
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        $bodies = [
            '', '{}', '[]', '{', '{"password":"x","password":"y"}',
            '{"remember":true}', '{"user_id":null}', str_repeat('x', 65537)
        ];
        foreach ($bodies as $index => $body) {
            yield 'body '.$index => [$body];
        }
    }

    /**
     * Applies exact Origin, Fetch Metadata and nonce-bound proof checks before activation
     */
    #[DataProvider('invalidHeaders')]
    public function testRequestProtection(string $name, string $value): void
    {
        $this->assertResponse($this->request(headers: [$name => $value]), $name === 'Content-Type' ? 400 : 403);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Supplies request protection failures including malformed and foreign metadata
     *
     * @return iterable<string, array{string, string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'foreign origin' => ['Origin', 'https://foreign.test'];
        yield 'null origin' => ['Origin', 'null'];
        yield 'missing origin' => ['Origin', ''];
        yield 'missing proof' => ['X-CSRF-Proof', ''];
        yield 'duplicate nonce' => ['Cookie', CsrfCookie::NAME.'=a; '.CsrfCookie::NAME.'=b'];
        yield 'bearer alternate source' => ['Authorization', 'Bearer synthetic'];
        yield 'cross site' => ['Sec-Fetch-Site', 'cross-site'];
        yield 'same site' => ['Sec-Fetch-Site', 'same-site'];
        yield 'wrong proof' => ['X-CSRF-Proof', 'invalid'];
        yield 'wrong cookie' => ['Cookie', CsrfCookie::NAME.'=invalid'];
        yield 'text' => ['Content-Type', 'text/plain'];
        yield 'override' => ['X-HTTP-Method-Override', 'POST'];
    }

    /**
     * Makes mismatched, unknown and expired identities indistinguishable
     */
    public function testGenericGrantRecovery(): void
    {
        $first = $this->assertResponse($this->request(['credential' => bin2hex(random_bytes(32))]), 409);
        $second = $this->assertResponse($this->request(['user_id' => UserId::generate()->toString()]), 409);
        $this->connection->executeStatement("UPDATE activation_grants SET expires_at = now() - interval '1 second'");
        $third = $this->assertResponse($this->request(), 409);
        self::assertSame($first, $second);
        self::assertSame($first, $third);
    }

    /**
     * Rolls back grant, user and session together while retaining independently committed admission
     */
    #[DataProvider('rollbackTables')]
    public function testRollbackAtEachAuthoritativeWrite(string $table): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE '.$table.' ADD CONSTRAINT activation_abort CHECK (false) NOT VALID'
        );
        $this->assertResponse($this->request(), 500);
        self::assertNull($this->connection->fetchOne('SELECT consumed_at FROM activation_grants'));
        self::assertSame('pending_activation', $this->connection->fetchOne('SELECT state FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
        self::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM activation_rate_limits WHERE attempts = 1'
        ));
    }

    /**
     * Supplies actual write boundaries for rollback injection
     *
     * @return iterable<string, array{string}>
     */
    public static function rollbackTables(): iterable
    {
        yield 'grant' => ['activation_grants'];
        yield 'user' => ['users'];
        yield 'session' => ['refresh_sessions'];
    }

    /**
     * Commits only one duplicate activation after both real transactions captured the same grant
     */
    #[DataProvider('atomicCompetitions')]
    public function testControlledAtomicCompetition(bool $admission): void
    {
        if ($admission) {
            for ($i = 0; $i < 9; ++$i) {
                $this->assertResponse($this->request(['credential' => 'invalid']), 409);
            }
        }
        $children = [];
        try {
            for ($i = 0; $i < 2; ++$i) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\ActivationRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode([
                    'mode'       => $admission ? 'admission' : 'activation',
                    'user'       => $this->target->toString(),
                    'credential' => $this->credential,
                    'password'   => $this->password
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                self::assertSame('ready', trim((string) fgets($pipes[1])));
            }
            foreach ($children as $i => [$process, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
                if (!$admission) {
                    self::assertSame($i === 0 ? 'committed' : 'conflict', trim((string) fgets($pipes[1])));
                }
            }
            if ($admission) {
                $outcomes = [];
                foreach ($children as [$process, $pipes]) {
                    $outcomes[] = trim((string) fgets($pipes[1]));
                }
                sort($outcomes);
                self::assertSame(['409', '429'], $outcomes);
                self::assertSame([10, 10], $this->connection->fetchFirstColumn(
                    'SELECT attempts FROM activation_rate_limits ORDER BY key'
                ));
                self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
            } else {
                self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
                self::assertSame('active', $this->connection->fetchOne('SELECT state FROM users'));
                self::assertNotNull($this->connection->fetchOne('SELECT consumed_at FROM activation_grants'));
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
     * Supplies duplicate activation and the last admission slot as separate competing contracts
     *
     * @return iterable<string, array{bool}>
     */
    public static function atomicCompetitions(): iterable
    {
        yield 'duplicate activation' => [false];
        yield 'last network admission slot' => [true];
    }

    /**
     * Denies admission on unavailable persistence before any activation mutation
     */
    public function testAdmissionStorageFailure(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE activation_rate_limits ADD CONSTRAINT activation_abort CHECK (false) NOT VALID'
        );
        $this->assertResponse($this->request(), 500);
        self::assertSame('pending_activation', $this->connection->fetchOne('SELECT state FROM users'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM refresh_sessions'));
    }

    /**
     * Bounds retained dimensions and recovers capacity only after expiry
     */
    public function testAdmissionCapacityAndExpiry(): void
    {
        $this->connection->executeStatement(<<<'SQL'
INSERT INTO activation_rate_limits (key, window_end, attempts)
SELECT lpad(to_hex(i), 64, '0'), extract(epoch from now())::bigint + 900, 1
FROM generate_series(1, 10000) AS i
SQL
        );
        $this->assertResponse($this->request(), 429);
        $this->connection->executeStatement('UPDATE activation_rate_limits SET window_end = 1');
        $this->assertResponse($this->request(['credential' => 'invalid']), 409);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM activation_rate_limits'));
    }

    /**
     * Consumes attempts despite rejection and bounds persistent non-reversible dimensions
     */
    public function testRateBoundary(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->assertResponse($this->request(['credential' => 'invalid']), 409);
        }
        $response = $this->request();
        $this->assertResponse($response, 429);
        self::assertGreaterThanOrEqual(1, (int) $response->getHeaderLine('Retry-After'));
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM activation_rate_limits');
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $row['key']);
            self::assertSame(10, $row['attempts']);
        }
    }

    /**
     * Builds the real public route request, never a test-only endpoint
     *
     * @param array<string, mixed> $changes
     * @param array<string, string> $headers
     */
    private function request(array $changes = [], array $headers = [], ?string $raw = null): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest(
            'POST',
            'https://agent-os.test/api/v1/auth/activate',
            ['REMOTE_ADDR' => '127.0.0.1']
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
            'user_id'               => $this->target->toString(),
            'credential'            => $this->credential,
            'password'              => $this->password,
            'password_confirmation' => $this->password
        ], $changes);
        $request->getBody()->write($raw ?? json_encode($input, JSON_THROW_ON_ERROR));

        return $this->app->handle($request);
    }

    /**
     * Checks real responses against OpenAPI and scans body/log output without dumping any credential
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
            new OperationAddress('/api/v1/auth/activate', 'post'),
            $response
        );
        $public = (string) $response->getBody().json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        self::assertFalse(str_contains($public, $this->credential));
        self::assertFalse(str_contains($public, $this->password));
        self::assertFalse(str_contains($public, $this->nonce));
        self::assertFalse(str_contains($public, $this->proof));

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
