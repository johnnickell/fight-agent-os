<?php

declare(strict_types=1);

namespace Tests\Integration\Postgres;

use App\Adapter\Persistence\Guard\DatabaseTargetGuard;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use App\Application\Failure\RateLimited;
use App\Domain\Invitation\InvitationOperation;
use App\Domain\Invitation\InvitationRatePolicy;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Proves atomic bounded PostgreSQL admission, deterministic boundaries and independent-process contention
 */
final class InvitationRateLimiterTest extends TestCase
{
    private Connection $connection;
    private AccessClock $clock;

    /**
     * Guards and clears only invitation budgets in the dedicated test database
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
        $this->connection->executeStatement('TRUNCATE invitation_rate_limits');
        $this->clock = new AccessClock(new DateTimeImmutable('2026-10-17T12:00:00Z'));
    }

    /**
     * Releases only the test connection
     */
    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * Separates actors and operations and admits exactly at the deterministic fixed-window boundary
     */
    public function testWindowsAndIsolation(): void
    {
        $limiter = new PostgresInvitationRateLimiter(
            $this->connection,
            $this->clock,
            new InvitationRatePolicy(60, 1, 2, 5)
        );
        $actor = UserId::generate();
        $limiter->consume($actor, InvitationOperation::ISSUE);
        $this->assertLimited($limiter, $actor, 60);
        $limiter->consume($actor, InvitationOperation::STATUS);
        $limiter->consume($actor, InvitationOperation::STATUS);
        try {
            $limiter->consume($actor, InvitationOperation::STATUS);
            self::fail('Status budget was not enforced.');
        } catch (RateLimited $failure) {
            self::assertSame(60, $failure->retryAfter);
        }
        $limiter->consume(UserId::generate(), InvitationOperation::ISSUE);
        $this->clock->time = $this->clock->time->modify('+59 seconds');
        $this->assertLimited($limiter, $actor, 1);
        $this->clock->time = $this->clock->time->modify('+1 second');
        $limiter->consume($actor, InvitationOperation::ISSUE);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
        self::assertSame([''], $this->connection->fetchFirstColumn('SELECT target_scope FROM invitation_rate_limits'));
    }

    /**
     * Caps retained rows and commits bounded cleanup even when a new actor is refused
     */
    public function testCapacity(): void
    {
        $limiter = new PostgresInvitationRateLimiter(
            $this->connection,
            $this->clock,
            new InvitationRatePolicy(60, 1, 1, 1)
        );
        $limiter->consume(UserId::generate(), InvitationOperation::ISSUE);
        $other = UserId::generate();
        $this->assertLimited($limiter, $other, 60);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
        $this->clock->time = $this->clock->time->modify('+60 seconds');
        $limiter->consume($other, InvitationOperation::ISSUE);
        self::assertSame(
            [$other->toString()],
            $this->connection->fetchFirstColumn('SELECT actor_id FROM invitation_rate_limits')
        );
    }

    /**
     * Refuses accidental nesting instead of joining a business transaction
     */
    public function testNoBusinessTransaction(): void
    {
        $this->connection->beginTransaction();
        try {
            new PostgresInvitationRateLimiter($this->connection, $this->clock, new InvitationRatePolicy())
                ->consume(UserId::generate(), InvitationOperation::ISSUE);
            self::fail('Nested admission was accepted.');
        } catch (LogicException) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Admits one winner across separate processes already blocked on the shared atomic admission lock
     */
    #[DataProvider('operations')]
    public function testConcurrentAdmission(InvitationOperation $operation): void
    {
        $actor = UserId::generate();
        $target = $operation === InvitationOperation::ISSUE ? null : UserId::generate();
        $children = [];
        $this->connection->beginTransaction();
        $this->connection->executeQuery('SELECT pg_advisory_xact_lock(24121, 41)');
        try {
            $pids = [];
            for ($index = 0; $index < 3; ++$index) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r', 'require "vendor/autoload.php"; Tests\\Support\\InvitationRaceWorker::run();'],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3)
                );
                self::assertIsResource($process);
                $children[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode([
                    'operation'      => 'rate',
                    'rate_operation' => $operation->value,
                    'target'         => $target?->toString(),
                    'actor'          => $actor->toString(),
                    'now'            => $this->clock->time->format(DATE_ATOM)
                ], JSON_THROW_ON_ERROR)."\n");
                fflush($pipes[0]);
                $pids[] = (int) fgets($pipes[1]);
            }
            $blocked = 0;
            for ($attempt = 0; $attempt < 2000 && $blocked !== 3; ++$attempt) {
                $blocked = (int) $this->connection->fetchOne(
                    <<<'SQL'
                        SELECT count(*) FROM pg_locks WHERE locktype = ? AND classid = 24121 AND objid = 41
                        AND NOT granted AND pid IN (?, ?, ?)
                        SQL,
                    ['advisory', ...$pids]
                );
            }
            self::assertSame(3, $blocked, 'All contenders must reach the real PostgreSQL lock before release.');
            $this->connection->commit();
            $results = [];
            foreach ($children as [$process, $pipes]) {
                $results[] = trim((string) fgets($pipes[1]));
            }
            sort($results);
            self::assertSame(['admitted', 'throttled', 'throttled'], $results);
            self::assertSame($target === null ? [1] : [1, 1], array_map(
                'intval',
                $this->connection->fetchFirstColumn('SELECT attempts FROM invitation_rate_limits')
            ));
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
     * Supplies existing issuance and every new recovery admission class
     *
     * @return iterable<array{InvitationOperation}>
     */
    public static function operations(): iterable
    {
        foreach (
            [InvitationOperation::ISSUE, InvitationOperation::RETRY,
            InvitationOperation::RESEND, InvitationOperation::CORRECT] as $operation
        ) {
            yield [$operation];
        }
    }

    /**
     * Keeps target admission atomic with actor admission and enforces capacity before either write
     */
    public function testTargetCapacity(): void
    {
        $actor = UserId::generate();
        $target = UserId::generate();
        $limiter = new PostgresInvitationRateLimiter(
            $this->connection,
            $this->clock,
            new InvitationRatePolicy(capacity: 1)
        );
        try {
            $limiter->consume($actor, InvitationOperation::RETRY, $target);
            self::fail('Two scopes cannot fit a single-row capacity.');
        } catch (RateLimited $failure) {
            self::assertSame(60, $failure->retryAfter);
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM invitation_rate_limits'));
        }
        $limiter = new PostgresInvitationRateLimiter(
            $this->connection,
            $this->clock,
            new InvitationRatePolicy(capacity: 10, retryLimit: 1)
        );
        $limiter->consume($actor, InvitationOperation::RETRY, $target);
        try {
            $limiter->consume($actor, InvitationOperation::RETRY, UserId::generate());
            self::fail('Changing targets cannot evade the actor budget.');
        } catch (RateLimited) {
            self::assertSame([1, 1], array_map(
                'intval',
                $this->connection->fetchFirstColumn('SELECT attempts FROM invitation_rate_limits')
            ));
        }
    }

    /**
     * Checks safe exact retry delay without exposing an actor or target diagnostic
     */
    private function assertLimited(PostgresInvitationRateLimiter $limiter, UserId $actor, int $seconds): void
    {
        try {
            $limiter->consume($actor, InvitationOperation::ISSUE);
            self::fail('Issue budget was not enforced.');
        } catch (RateLimited $failure) {
            self::assertSame($seconds, $failure->retryAfter);
            self::assertSame('Too many requests.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }
}
