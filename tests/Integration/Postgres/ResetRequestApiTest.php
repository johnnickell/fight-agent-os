<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\CredentialDelivery\RandomPasswordResetCredentialGenerator;
use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Adapter\Http\Api\V1\Auth\ResetResponseTiming;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\HmacCsrfProofs;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Application\AccessControl\PasswordResetGrant\Service\PasswordResetCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryClaimToken;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryFailure;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Event\PasswordResetRequested;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetCredential;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\User\Event\RedactedCommandFailed;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
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
use Tooling\OpenApi\Document;

/**
 * Proves generic reset requests through the real HTTP, package and PostgreSQL boundaries
 */
final class ResetRequestApiTest extends TestCase
{
    private Connection $connection;
    private Container $container;
    /** @var App<Container> */
    private App $app;
    private AccessClock $clock;
    private UserId $target;
    private string $nonce;
    private string $proof;
    private string|false $oldUrl;
    private string|false $oldDeliveryKey;
    private TestHandler $logs;
    /** @var list<int> */
    private array $waits = [];

    /**
     * Prepares only the guarded test database and injected deterministic clocks
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
        $this->oldUrl = getenv('DATABASE_URL');
        $this->oldDeliveryKey = getenv('APP_CREDENTIAL_DELIVERY_KEY');
        putenv('DATABASE_URL='.$url);
        // Own the fixture cipher key; separate race workers inherit this same ephemeral value.
        putenv('APP_CREDENTIAL_DELIVERY_KEY='.bin2hex(random_bytes(32)));
        $container = require dirname(__DIR__, 3).'/config/services.php';
        $this->container = $container;
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-20T00:00:00.654321Z'));
        $container->set(Clock::class, fn(): Clock => $this->clock);
        \Slim\Factory\AppFactory::setContainer($container);
        /** @var App<Container> $app */
        $app = \Slim\Factory\AppFactory::create();
        $container->set(App::class, static fn(): App => $app);
        require dirname(__DIR__, 3).'/config/routes.php';
        require dirname(__DIR__, 3).'/config/middleware.php';
        $this->app = $app;
        $container->set(ResetResponseTiming::class, fn(): ResetResponseTiming => new ResetResponseTiming(
            static fn(): int => 100,
            function (int $remaining): void {
                $this->waits[] = $remaining;
            }
        ));
        $this->logs = new TestHandler();
        $container->get('http.failure.logger')->setHandlers([$this->logs]);
        $this->target = UserId::generate();
        $user = User::invite($this->target, EmailAddress::fromString('reset@example.test'), $this->clock->now());
        $hash = PasswordHash::fromString(password_hash('fixture', PASSWORD_BCRYPT, ['cost' => 4]));
        $user->activate($hash, $this->clock->now());
        $container->get(Connection::class)->transactional(fn() => $container->get(UserRepository::class)->add($user));
        $this->nonce = bin2hex(random_bytes(32));
        $this->proof = $container->get(HmacCsrfProofs::class)->sign($this->nonce, time() + 900);
    }

    /**
     * Restores only owned environment and failure injection
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement('ALTER TABLE audit_evidence DROP CONSTRAINT IF EXISTS reset_abort');
        putenv($this->oldUrl === false ? 'DATABASE_URL' : 'DATABASE_URL='.$this->oldUrl);
        if ($this->oldDeliveryKey === false) {
            putenv('APP_CREDENTIAL_DELIVERY_KEY');
        } else {
            putenv('APP_CREDENTIAL_DELIVERY_KEY='.$this->oldDeliveryKey);
        }
        $this->connection->close();
    }

    /**
     * Compares all public observables and deterministic timing paths without identity preflight
     */
    public function testGenericObservableMatrixAndDurableGeneration(): void
    {
        $generator = new class implements PasswordResetCredentialGenerator {
            public int $calls = 0;
            public string $last = '';
            /**
             * @inheritDoc
             */
            public function generate(): PasswordResetCredential
            {
                ++$this->calls;
                $credential = new RandomPasswordResetCredentialGenerator()->generate();
                $this->last = $credential->toString();

                return $credential;
            }
        };
        $this->container->set(
            PasswordResetCredentialGenerator::class,
            static fn(): PasswordResetCredentialGenerator => $generator
        );
        $events = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(PasswordResetRequested::class),
            function (EventMessage $message) use (&$events): void {
                $events[] = $message->payload()->toArray();
                self::assertFalse($this->container->get(Connection::class)->isTransactionActive());
            }
        );
        $baseline = $this->observable($this->request());
        $row = $this->connection->fetchAssociative('SELECT * FROM password_reset_grants');
        self::assertIsArray($row);
        self::assertSame('pending', $row['delivery_status']);
        self::assertSame(0, $row['delivery_attempt_count']);
        self::assertNotEmpty($row['delivery_ciphertext']);
        self::assertFalse(str_contains(json_encode($row, JSON_THROW_ON_ERROR), $generator->last));
        self::assertSame(1, $generator->calls);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $row['credential_digest']);
        self::assertEquals($this->clock->now()->modify('+1 hour'), new DateTimeImmutable($row['expires_at']));
        self::assertSame(['user_id', 'password_reset_delivery_id', 'issued_at'], array_keys($events[0]));
        self::assertSame($baseline, $this->observable($this->request()));
        self::assertSame($baseline, $this->observable($this->request('absent@example.test')));
        foreach (['pending_activation', 'disabled', 'deleted'] as $state) {
            $this->connection->executeStatement('TRUNCATE auth_rate_limits');
            $this->connection->update('users', [
                'state'         => $state,
                'password_hash' => $state === 'pending_activation' ? null : password_hash(
                    'fixture',
                    PASSWORD_BCRYPT,
                    ['cost' => 4]
                )
            ], ['id' => $this->target->toString()]);
            self::assertSame($baseline, $this->observable($this->request()));
        }
        self::assertCount(6, $this->waits);
        self::assertSame([250_000_000], array_values(array_unique($this->waits)));
        self::assertCount(2, $events);
        self::assertSame(2, $generator->calls);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM password_reset_grants'));
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM password_reset_grants WHERE revoked_at IS NULL AND consumed_at IS NULL'
        ));
        $old = $this->connection->fetchAssociative('SELECT * FROM password_reset_grants WHERE generation = 0');
        self::assertIsArray($old);
        self::assertNotNull($old['revoked_at']);
        self::assertNull($old['delivery_ciphertext']);
        self::assertSame('invalidated', $old['delivery_status']);
        $audit = json_encode(
            $this->connection->fetchAllAssociative('SELECT * FROM audit_evidence'),
            JSON_THROW_ON_ERROR
        );
        self::assertStringNotContainsString('reset@example.test', $audit);
        self::assertStringNotContainsString($row['credential_digest'], $audit);
        self::assertStringNotContainsString($row['delivery_ciphertext'], $audit);
    }

    /**
     * Keeps limiter outcomes independent of identity and admits exactly at the fractional expiry boundary
     */
    public function testAtomicLimitsExpiryAndRedaction(): void
    {
        foreach (['reset@example.test', 'unknown@example.test'] as $email) {
            $this->connection->executeStatement('TRUNCATE auth_rate_limits');
            for ($i = 0; $i < 3; ++$i) {
                $this->observable($this->request($email));
            }
            $before = $this->connection->fetchAllAssociative('SELECT * FROM auth_rate_limits ORDER BY key');
            $denied = $this->request($email);
            self::assertSame(429, $denied->getStatusCode());
            self::assertSame('900', $denied->getHeaderLine('Retry-After'));
            self::assertSame(
                $before,
                $this->connection->fetchAllAssociative('SELECT * FROM auth_rate_limits ORDER BY key')
            );
            $stored = json_encode($before, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString($email, $stored);
            self::assertStringNotContainsString('127.0.0.1', $stored);
            self::assertStringNotContainsString((string) getenv('APP_AUTH_RATE_KEY'), $stored);
            $this->clock->time = $this->clock->time->modify('+899999999 microseconds');
            self::assertSame(429, $this->request($email)->getStatusCode());
            $this->clock->time = $this->clock->time->modify('+1 microsecond');
            $this->observable($this->request($email));
        }
    }

    /**
     * Rolls predecessor, successor and audit back together and redacts failed command email before subscribers
     */
    #[DataProvider('rollbackPredecessors')]
    public function testRollbackAndFailureEventRedaction(bool $predecessor): void
    {
        if ($predecessor) {
            $this->observable($this->request());
        }
        $before = $this->connection->fetchAllAssociative('SELECT * FROM password_reset_grants');
        $events = [];
        $this->container->get(EventDispatcher::class)->addHandler(
            ClassName::underscore(RedactedCommandFailed::class),
            function (EventMessage $message) use (&$events): void {
                $events[] = $message->payload()->toArray();
            }
        );
        $this->connection->executeStatement(
            'ALTER TABLE audit_evidence ADD CONSTRAINT reset_abort CHECK (false) NOT VALID'
        );
        $response = $this->request();
        self::assertSame(500, $response->getStatusCode());
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM password_reset_grants'));
        self::assertCount(1, $events);
        self::assertSame([], $events[0]['command_data']);
        self::assertCount($predecessor ? 2 : 1, $this->waits);
        $diagnostics = (string) $response->getBody().json_encode(
            [$events, $this->logs->getRecords()],
            JSON_THROW_ON_ERROR
        );
        foreach (['reset@example.test', 'SQLSTATE', 'reset_abort', $this->nonce, $this->proof] as $marker) {
            self::assertStringNotContainsString($marker, $diagnostics);
        }
        // Attempt admission is independently committed, even though business state rolled back.
        self::assertCount(3, $this->connection->fetchAllAssociative('SELECT * FROM auth_rate_limits'));
    }

    /**
     * Supplies initial and successor transaction failure paths
     *
     * @return iterable<array{bool}>
     */
    public static function rollbackPredecessors(): iterable
    {
        yield [false];
        yield [true];
    }

    /**
     * Rejects hostile source and browser inputs before timing, grant, audit or package dispatch
     *
     * @param string $body
     * @param array<string, string> $headers
     * @param string $uri
     * @param string $method
     */
    #[DataProvider('hostileRequests')]
    public function testStrictPreDispatchControls(string $body, array $headers, string $uri, string $method): void
    {
        $response = $this->request(raw: $body, headers: $headers, uri: $uri, method: $method);
        self::assertContains($response->getStatusCode(), [400, 403, 405]);
        self::assertSame([], $this->waits);
        foreach (['password_reset_grants', 'audit_evidence', 'auth_rate_limits'] as $table) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM '.$table));
        }
        self::assertSame([], $response->getHeader('Set-Cookie'));
    }

    /**
     * Supplies malformed transports without real identity or credential material
     *
     * @return iterable<array{string, array<string, string>, string, string}>
     */
    public static function hostileRequests(): iterable
    {
        $uri = 'https://agent-os.test/api/v1/auth/password-reset';
        $body = '{"email":"reset@example.test"}';
        foreach (
            ['{}', '[]', '{"email":null}', '{"email":1}', '{"email":"not-email"}',
            '{"email":"RESET@example.test"}', '{"email":"reset@example.test","user_id":"ignored"}',
            '{"email":"a@example.test","email":"b@example.test"}', str_repeat(' ', 65537)] as $raw
        ) {
            yield [$raw, [], $uri, 'POST'];
        }
        foreach (
            [
            'Origin'                 => 'https://evil.test',
            'Sec-Fetch-Site'         => 'same-site',
            'Sec-Fetch-Mode'         => 'navigate',
            'X-CSRF-Proof'           => 'invalid',
            'Cookie'                 => '',
            'Content-Type'           => 'text/plain',
            'X-Forwarded-Proto'      => 'https',
            'Authorization'          => 'Bearer ignored',
            'X-HTTP-Method-Override' => 'POST'
            ] as $name => $value
        ) {
            yield [$body, [$name => $value], $uri, 'POST'];
        }
        yield [$body, [], str_replace('https:', 'http:', $uri), 'POST'];
        yield [$body, [], $uri.'?email=alternate', 'POST'];
        yield [$body, [], $uri, 'GET'];
    }

    /**
     * Serializes competing package requests across every supported predecessor lifecycle
     */
    #[DataProvider('predecessors')]
    public function testConcurrentSuccession(string $state): void
    {
        $repository = $this->container->get(PasswordResetGrantRepository::class);
        if ($state !== 'none') {
            $this->observable($this->request());
            $before = $repository->getLatestByUserId($this->target);
            self::assertNotNull($before);
            self::assertSame('password_reset', $before->purpose());
            self::assertTrue($before->isUsableAt($before->getExpiresAt()->modify('-1 microsecond')));
            self::assertFalse($before->isUsableAt($before->getExpiresAt()));
            $claim = CredentialDeliveryClaimToken::generate();
            $claimed = $before->claimDelivery($claim, $this->clock->now(), $this->clock->now()->modify('+30 seconds'));
            $next = match ($state) {
                'issued' => $before,
                'recoverable' => $claimed->failDelivery(
                    $claim,
                    $this->clock->now(),
                    CredentialDeliveryFailure::RETRYABLE_PROVIDER
                ),
                'confirmed' => $claimed->confirmDelivery($claim, $this->clock->now()),
                'consumed' => $before->consume($this->clock->now()),
                'revoked' => $before->revoke($this->clock->now()),
                'expired' => $before->expireDeliveryAt($before->getExpiresAt()),
                default => throw new \LogicException('Unknown fixture state.')
            };
            if ($state === 'confirmed' || $state === 'recoverable') {
                self::assertTrue($this->container->get(Connection::class)->transactional(
                    fn(): bool => $repository->replace($before, $claimed)
                ));
                $before = $claimed;
            }
            self::assertTrue($state === 'issued' || $this->container->get(Connection::class)->transactional(
                fn(): bool => $repository->replace($before, $next)
            ));
            if ($state === 'expired') {
                $this->clock->time = $this->clock->time->modify('+1 hour');
            }
        }
        $children = [];
        $failure = null;
        $this->connection->beginTransaction();
        $this->connection->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, ?))',
            [$this->target->toString(), 24122]
        );
        try {
            $pids = [];
            for ($index = 0; $index < 2; ++$index) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r',
                    'require "vendor/autoload.php"; Tests\\Support\\ResetRequestRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode(
                    ['now' => $this->clock->now()->format('Y-m-d\\TH:i:s.uP')],
                    JSON_THROW_ON_ERROR
                )."\n");
                fflush($pipes[0]);
            }
            foreach ($children as [$process, $pipes]) {
                $ready = (string) fgets($pipes[1]);
                self::assertTrue(preg_match('/\Aready ([1-9][0-9]*)\n\z/D', $ready, $match) === 1, 'Worker not ready.');
                $pids[] = (int) $match[1];
            }
            // Both buses are resolved before either request enters the real package transaction.
            $deadline = hrtime(true) + 4_000_000_000; // Below the workers' existing five-second lock timeout.
            foreach ($children as [$process, $pipes]) {
                self::assertSame(6, fwrite($pipes[0], "start\n"));
                fflush($pipes[0]);
            }
            $blocked = 0;
            while ($blocked !== 2 && hrtime(true) < $deadline) {
                $blocked = (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM pg_locks WHERE locktype = ? AND NOT granted AND pid IN (?, ?)',
                    ['advisory', ...$pids]
                );
            }
            self::assertSame(2, $blocked);
            $this->connection->commit();
        } catch (\Throwable $caught) {
            $failure = $caught;
        } finally {
            try {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();
                }
            } catch (\Throwable $caught) {
                $failure ??= $caught;
            }
            $results = $this->collectRaceWorkers($children);
        }
        if ($failure !== null) {
            foreach ($results as [$exit, $output, $errorBytes, $terminated]) {
                fwrite(STDERR, sprintf(
                    "Race cleanup: exit=%d stdout_bytes=%d stderr_bytes=%d terminated=%d\n",
                    $exit,
                    strlen($output),
                    $errorBytes,
                    (int) $terminated
                ));
            }
            throw $failure;
        }
        foreach ($results as [$exit, $output, $errorBytes, $terminated]) {
            self::assertFalse($terminated, 'Race worker exceeded bounded cleanup.');
            self::assertSame(0, $exit);
            self::assertSame(0, $errorBytes, 'Race worker emitted diagnostics (content withheld).');
            self::assertTrue($output === "committed\n", 'Race worker did not report a clean commit.');
        }
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM password_reset_grants ORDER BY generation');
        self::assertCount($state === 'none' ? 2 : 3, $rows);
        $current = array_pop($rows);
        self::assertIsArray($current);
        self::assertNull($current['revoked_at']);
        self::assertNull($current['consumed_at']);
        self::assertSame('pending', $current['delivery_status']);
        foreach ($rows as $row) {
            self::assertTrue($row['revoked_at'] !== null || $row['consumed_at'] !== null);
            self::assertNull($row['delivery_ciphertext']);
        }
    }

    /**
     * Releases both children and drains bounded diagnostics before closing their output pipes
     *
     * @param list<array{resource, array<int, resource>}> $children
     *
     * @return list<array{int, string, int, bool}>
     */
    private function collectRaceWorkers(array $children): array
    {
        /** @var list<array{int, string, int, bool}> $results */
        $results = [];
        foreach ($children as [$process, $pipes]) {
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $results[] = [-1, '', 0, false];
        }
        // Allow the existing ten-second statement bound after releasing the parent's lock.
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            $pending = false;
            foreach ($results as $index => [$exit, $output, $errorBytes, $terminated]) {
                [$process, $pipes] = $children[$index];
                $output = substr($output.(string) fread($pipes[1], 8192), 0, 4096);
                $errorBytes += strlen((string) fread($pipes[2], 8192));
                $status = proc_get_status($process);
                if (!$status['running'] && $status['exitcode'] >= 0) {
                    $exit = $status['exitcode'];
                }
                if ($status['running'] && hrtime(true) >= $deadline) {
                    $terminated = true;
                    // One second for graceful exit, then kill only this owned child.
                    proc_terminate($process, hrtime(true) < $deadline + 1_000_000_000 ? 15 : 9);
                }
                $results[$index] = [$exit, $output, $errorBytes, $terminated];
                $pending = $pending || $status['running'] || !feof($pipes[1]) || !feof($pipes[2]);
            }
            if ($pending) {
                usleep(1000);
            }
        } while ($pending && hrtime(true) < $deadline + 2_000_000_000);
        foreach ($results as $index => [$exit, $output, $errorBytes, $terminated]) {
            [$process, $pipes] = $children[$index];
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($process);
            $results[$index] = [$exit < 0 ? $closed : $exit, $output, $errorBytes, $terminated];
        }

        return $results;
    }

    /**
     * Supplies fresh, live, recoverable and terminal predecessors
     *
     * @return iterable<array{string}>
     */
    public static function predecessors(): iterable
    {
        foreach (['none', 'issued', 'recoverable', 'confirmed', 'consumed', 'revoked', 'expired'] as $state) {
            yield [$state];
        }
    }

    /**
     * Exercises the actual registered route with the existing nonce/proof profile
     *
     * @param string $email
     * @param string|null $raw
     * @param array<string, string> $headers
     * @param string $uri
     * @param string $method
     */
    private function request(
        string $email = 'reset@example.test',
        ?string $raw = null,
        array $headers = [],
        string $uri = 'https://agent-os.test/api/v1/auth/password-reset',
        string $method = 'POST'
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest($method, $uri, ['REMOTE_ADDR' => '127.0.0.1']);
        foreach (
            array_replace([
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://agent-os.test',
            'Sec-Fetch-Site' => 'same-origin',
            'X-CSRF-Proof'   => $this->proof,
            'Cookie'         => CsrfCookie::NAME.'='.$this->nonce
            ], $headers) as $name => $value
        ) {
            $request = $request->withHeader($name, $value);
        }
        $request->getBody()->write($raw ?? json_encode(['email' => $email], JSON_THROW_ON_ERROR));

        return $this->app->handle($request);
    }

    /**
     * Validates the exact generic contract and compares all headers except fresh correlation
     *
     * @return array<mixed>
     */
    private function observable(ResponseInterface $response): array
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['status' => 'success', 'data' => ['accepted' => true]],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        static $spec = null;
        $spec ??= Document::generate();
        (new ValidatorBuilder())->fromJson($spec)->getResponseValidator()->validate(
            new OperationAddress('/api/v1/auth/password-reset', 'post'),
            $response
        );
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/D', $response->getHeaderLine('X-Correlation-ID'));
        $headers = $response->withoutHeader('X-Correlation-ID')->getHeaders();
        foreach (['reset@example.test', 'absent@example.test', $this->nonce, $this->proof] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                (string) $response->getBody().json_encode([$headers, $this->logs->getRecords()], JSON_THROW_ON_ERROR)
            );
        }

        return [$response->getStatusCode(), (string) $response->getBody(), $headers];
    }
}
