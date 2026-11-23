<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Persistence\Authentication\PostgresAuthAttempts;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Application\Error\Exception\ServiceUnavailable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Protects bounded authentication admission against stale history and unavailable storage
 */
final class PostgresAuthAttemptsTest extends TestCase
{
    private const int NOW = 1792497600123456;
    private Connection&MockObject $connection;
    private Clock $clock;
    private PostgresAuthAttempts $attempts;
    private string $key;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->clock = $this->createStub(Clock::class);
        $this->clock->method('now')->willReturn(new DateTimeImmutable('2026-10-20T12:00:00.123456Z'));
        $this->attempts = new PostgresAuthAttempts($this->connection, $this->clock, 2);
        $this->key = hash('sha256', 'sample rate key');
    }

    /**
     * Rejects an invalid capacity without consulting storage
     */
    public function testRejectsInvalidCapacity(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid authentication rate capacity');
        new PostgresAuthAttempts($this->connection, $this->clock, 0);
    }

    /**
     * Checks readability without reserving a budget or exposing a storage failure
     */
    public function testAvailabilityProbeSanitizesStorageErrors(): void
    {
        $this->connection->expects(self::exactly(2))->method('executeQuery')->with(
            'SELECT key, hits, expires_at FROM auth_rate_limits LIMIT 0'
        )->willReturnOnConsecutiveCalls(
            $this->createStub(Result::class),
            $this->throwException(new RuntimeException('private storage detail'))
        );
        $this->connection->expects(self::never())->method('executeStatement');

        $this->attempts->check();
        try {
            $this->attempts->check();
        } catch (LogicException $failure) {
            self::assertSame('Authentication admission unavailable.', $failure->getMessage());
            self::assertNull($failure->getPrevious());

            return;
        }
        self::fail('Unavailable storage must deny admission');
    }

    /**
     * Does not consume an attempt with an invalid shape or inside the caller's transaction
     */
    #[DataProvider('invalidAdmission')]
    public function testRejectsInvalidAdmissionBeforeStartingTransaction(bool $active): void
    {
        $this->connection->method('isTransactionActive')->willReturn($active);
        $this->connection->expects(self::never())->method('transactional');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($active ? 'independent transaction' : 'Invalid authentication admission budget');
        $this->attempts->consume($active ? [$this->key => 1] : [], 10);
    }

    /**
     * Supplies outer admission failures
     *
     * @return iterable<string, array{bool}>
     */
    public static function invalidAdmission(): iterable
    {
        yield 'empty budget' => [false];
        yield 'enclosing transaction' => [true];
    }

    /**
     * Discards a hit exactly at the cutoff, keeps newer hits and stores every dimension under one lock
     */
    public function testConsumesRollingBudgetsTogether(): void
    {
        $this->executeInsideTransaction();
        $other = hash('sha256', 'another rate key');
        $this->connection->expects(self::exactly(3))->method('fetchOne')->willReturnCallback(
            function (string $sql, array $parameters) use ($other): string|int|false {
                if (str_contains($sql, 'count(*)')) {
                    return 1;
                }
                self::assertStringContainsString('WHERE key = ?', $sql);

                return match ($parameters) {
                    [$this->key] => json_encode([self::NOW - 10000000, self::NOW - 2000000], JSON_THROW_ON_ERROR),
                    [$other] => false,
                    default => self::fail('Unexpected rate key')
                };
            }
        );
        $stored = [];
        $this->connection->expects(self::exactly(5))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters = []) use (&$stored): int {
                if (str_starts_with($sql, 'DELETE')) {
                    self::assertSame([self::NOW], $parameters);
                } elseif (str_starts_with($sql, 'INSERT')) {
                    $stored[] = $parameters;
                } else {
                    self::assertStringStartsWith('SET LOCAL', $sql);
                }

                return 1;
            }
        );

        $this->attempts->consume([$this->key => 2, $other => 1], 10);

        self::assertSame([
            [$this->key, '['.(self::NOW - 2000000).','.self::NOW.']', self::NOW + 10000000],
            [$other, '['.self::NOW.']', self::NOW + 10000000]
        ], $stored);
    }

    /**
     * Fails closed without recording partial budgets on exhaustion, capacity or an invalid key
     *
     * @param string $reason
     * @param class-string<RateLimitExceeded|ServiceUnavailable> $failureClass
     */
    #[DataProvider('denials')]
    public function testDoesNotWriteDeniedBudgets(string $reason, string $failureClass): void
    {
        $this->executeInsideTransaction();
        $this->connection->expects(self::exactly(3))->method('executeStatement')->willReturnCallback(
            static function (string $sql): int {
                self::assertStringNotContainsString('INSERT', $sql);

                return 0;
            }
        );
        $this->connection->method('fetchOne')->willReturnCallback(
            static fn(string $sql): int|string|false => str_contains($sql, 'count(*)') ? 2 : (
                $reason === 'exhausted' ? '['.(self::NOW - 1500000).']' : false
            )
        );

        $this->expectException($failureClass);
        $this->attempts->consume([$reason === 'invalid' ? 'raw-key' : $this->key => 1], 10);
    }

    /**
     * Supplies denial reasons and their public failure classes
     *
     * @return iterable<string, array{string, class-string<RateLimitExceeded|ServiceUnavailable>}>
     */
    public static function denials(): iterable
    {
        yield 'rolling budget exhausted' => ['exhausted', RateLimitExceeded::class];
        yield 'storage capacity full' => ['capacity', RateLimitExceeded::class];
        yield 'unsanitized key' => ['invalid', ServiceUnavailable::class];
    }

    /**
     * Keeps diagnostic snapshots free of storage configuration
     */
    public function testDebugProjectionRedactsConnection(): void
    {
        self::assertSame(['connection' => '[redacted]'], $this->attempts->__debugInfo());
    }

    /**
     * Runs the repository's transactional callback and expects its shared admission lock
     */
    private function executeInsideTransaction(): void
    {
        $this->connection->expects(self::once())->method('transactional')->willReturnCallback(
            fn(Closure $operation): mixed => $operation($this->connection)
        );
        $this->connection->expects(self::once())->method('executeQuery')->with(
            'SELECT pg_advisory_xact_lock(24121, 45)'
        )->willReturn($this->createStub(Result::class));
    }
}
