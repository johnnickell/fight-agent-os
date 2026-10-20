<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Http\Api\V1\Auth\AuthOperation;
use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Security\Authentication\AuthAdmission;
use App\Application\Failure\RateLimited;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Proves independently committed rolling email and endpoint budgets, bounded retention and real contention
 */
final class AuthAdmissionTest extends TestCase
{
    private Connection $connection;
    private AccessClock $clock;
    private string $key;

    /**
     * Guards and clears only this capability's disposable test state
     */
    protected function setUp(): void
    {
        $this->connection = $this->connect();
        $this->connection->executeStatement('TRUNCATE auth_rate_limits');
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-20T00:14:59.500000Z'));
        $this->key = bin2hex(random_bytes(32));
    }

    /**
     * Releases only this test connection
     */
    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * Consumes five canonical-email calls without a user lookup and expires them precisely on a rolling boundary
     */
    public function testRollingEmailAndRedactedKeys(): void
    {
        $throttle = $this->admission();
        $email = EmailAddress::fromString('Known@Example.test');
        for ($index = 0; $index < 5; ++$index) {
            self::assertTrue($throttle->allows($email));
        }
        self::assertFalse($throttle->allows(EmailAddress::fromString('known@example.test')));
        $this->clock->time = $this->clock->time->modify('+1 second');
        self::assertFalse($throttle->allows($email), 'A fixed-window boundary must not reset rolling email admission.');
        self::assertTrue($throttle->allows(EmailAddress::fromString('unknown@example.test')));
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM auth_rate_limits');
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $row['key']);
            self::assertStringNotContainsString('@', json_encode($row, JSON_THROW_ON_ERROR));
        }
        $this->clock->time = $this->clock->time->modify('+898 seconds +999999 microseconds');
        self::assertFalse($throttle->allows($email));
        $this->clock->time = $this->clock->time->modify('+1 microsecond');
        self::assertTrue($throttle->allows($email));
        self::assertCount(1, json_decode((string) $this->connection->fetchOne(
            'SELECT hits FROM auth_rate_limits WHERE key = ?',
            [hash_hmac('sha256', 'email:known@example.test', (string) hex2bin($this->key))]
        ), true, 16, JSON_THROW_ON_ERROR));
    }

    /**
     * Retains committed attempts despite a separate business transaction rollback
     */
    public function testBusinessRollbackCannotUndoAdmission(): void
    {
        $business = $this->connect();
        $business->beginTransaction();
        try {
            self::assertTrue($this->admission()->allows(EmailAddress::fromString('rollback@example.test')));
            $business->rollBack();
            $separate = $this->connect();
            self::assertSame(1, (int) $separate->fetchOne('SELECT count(*) FROM auth_rate_limits'));
            $separate->close();
        } finally {
            $business->close();
        }
    }

    /**
     * Refuses accidental transaction nesting and unavailable storage without leaking SQL details
     */
    public function testNestingAndOutage(): void
    {
        $this->connection->beginTransaction();
        try {
            $this->admission()->allows(EmailAddress::fromString('nested@example.test'));
            self::fail('Nested admission accepted.');
        } catch (LogicException $failure) {
            self::assertNull($failure->getPrevious());
        } finally {
            $this->connection->rollBack();
        }
        $this->connection->executeStatement('ALTER TABLE auth_rate_limits RENAME TO auth_rate_limits_hidden');
        try {
            self::assertFalse($this->admission()->allows(EmailAddress::fromString('outage@example.test')));
            try {
                $this->admission()->endpoint(AuthOperation::REFRESH, '192.0.2.1');
                self::fail('Unavailable storage accepted.');
            } catch (RateLimited $failure) {
                self::assertSame(60, $failure->retryAfter);
                self::assertNull($failure->getPrevious());
            }
        } finally {
            $this->connection->executeStatement('ALTER TABLE auth_rate_limits_hidden RENAME TO auth_rate_limits');
        }
    }

    /**
     * Enforces capacity atomically, then reclaims expired histories
     */
    public function testCapacityAndEndpointIsolation(): void
    {
        $admission = new AuthAdmission(new PostgresAuthAttempts($this->connection, $this->clock, 2), $this->key, 1, 2);
        $admission->endpoint(AuthOperation::LOGIN, '2001:db8::1');
        foreach (['2001:0db8:0:0:0:0:0:1', '192.0.2.1'] as $network) {
            try {
                $admission->endpoint(AuthOperation::LOGIN, $network);
                self::fail('Exceeded network or capacity accepted.');
            } catch (RateLimited $failure) {
                self::assertSame(900, $failure->retryAfter);
            }
        }
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
        $this->clock->time = $this->clock->time->modify('+900 seconds');
        $admission->endpoint(AuthOperation::LOGOUT, '192.0.2.1');
        $stored = $this->connection->fetchAllAssociative('SELECT hits FROM auth_rate_limits');
        self::assertCount(2, $stored);
        foreach ($stored as $row) {
            self::assertCount(1, json_decode((string) $row['hits'], true, 16, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Admits exactly one contender after all separate connections reach the real database lock
     */
    #[DataProvider('raceOperations')]
    public function testConcurrency(string $operation): void
    {
        if ($operation === 'email') {
            for ($index = 0; $index < 4; ++$index) {
                self::assertTrue($this->admission()->allows(EmailAddress::fromString('race@example.test')));
            }
        }
        $children = [];
        $this->connection->beginTransaction();
        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 45)');
        try {
            $pids = [];
            for ($index = 0; $index < 3; ++$index) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r',
                        'require "vendor/autoload.php"; Tests\\Support\\AuthAdmissionRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 10);
                fwrite($pipes[0], json_encode([
                    'operation' => $operation,
                    'now'       => $this->clock->time->format('Y-m-d\TH:i:s.uP'),
                    'key'       => $this->key
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                $pids[] = (int) fgets($pipes[1]);
            }
            $blocked = 0;
            for ($attempt = 0; $attempt < 2000 && $blocked !== 3; ++$attempt) {
                $blocked = (int) $this->connection->fetchOne(
                    <<<'SQL'
SELECT count(*) FROM pg_locks WHERE locktype = ? AND classid = 24121 AND objid = 45
AND NOT granted AND pid IN (?, ?, ?)
SQL,
                    ['advisory', ...$pids]
                );
            }
            self::assertSame(3, $blocked);
            $this->connection->commit();
            $results = [];
            foreach ($children as [$process, $pipes]) {
                $results[] = trim((string) fgets($pipes[1]));
            }
            sort($results);
            self::assertSame(['admitted', 'denied', 'denied'], $results);
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
     * Supplies each independent endpoint budget and the canonical-email throttle
     *
     * @return iterable<array{string}>
     */
    public static function raceOperations(): iterable
    {
        yield ['email'];
        foreach (AuthOperation::cases() as $operation) {
            yield [$operation->value];
        }
    }

    /**
     * Refuses unsafe runtime policy inputs before consuming attempts
     */
    public function testInvalidPeerAndConfiguration(): void
    {
        foreach (['', 'unknown', '192.0.2.1, 192.0.2.2'] as $network) {
            try {
                $this->admission()->endpoint(AuthOperation::LOGIN, $network);
                self::fail('Invalid peer accepted.');
            } catch (RateLimited $failure) {
                self::assertSame(60, $failure->retryAfter);
            }
        }
        $invalid = [['', 20, 300], [str_repeat('a', 64), 20, 300], [$this->key, 21, 300], [$this->key, 20, 301]];
        foreach ($invalid as $case) {
            try {
                new AuthAdmission(new PostgresAuthAttempts($this->connection, $this->clock), ...$case);
                self::fail('Invalid admission configuration accepted.');
            } catch (\InvalidArgumentException $failure) {
                self::assertSame('Invalid authentication admission configuration.', $failure->getMessage());
            }
        }
        $snapshot = print_r($this->admission(), true);
        self::assertStringNotContainsString($this->key, $snapshot);
        self::assertStringNotContainsString((string) hex2bin($this->key), $snapshot);
        self::assertStringNotContainsString('Connection', $snapshot);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM auth_rate_limits'));
    }

    /**
     * Constructs the production adapter with a deterministic clock
     */
    private function admission(): AuthAdmission
    {
        return new AuthAdmission(new PostgresAuthAttempts($this->connection, $this->clock), $this->key);
    }

    /**
     * Opens and verifies a separate dedicated-test connection
     */
    private function connect(): Connection
    {
        $url = (string) getenv('TEST_DATABASE_URL');
        $guard = new DatabaseTargetGuard(explode(',', (string) getenv('TEST_DATABASE_ALLOWED_HOST')));
        $expected = $guard->assertConfigured((string) getenv('APP_ENV'), $url);
        $connection = DriverManager::getConnection(new DsnParser([
            'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'
        ])->parse($url));
        $guard->assertConnected($connection, $expected);

        return $connection;
    }
}
