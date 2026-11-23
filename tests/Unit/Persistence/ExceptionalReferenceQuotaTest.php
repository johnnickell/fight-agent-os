<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Reference\ExceptionalReferences;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Protects the bounded storage and per-actor admission for exceptional references
 */
final class ExceptionalReferenceQuotaTest extends TestCase
{
    /**
     * Does not allocate a fresh actor slot when the current window is at capacity
     */
    public function testCapacityDoesNotInsertANewActor(): void
    {
        $actor = UserId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $parameters = []) use ($actor): int|false {
                if (str_contains($sql, 'actor_id = ?')) {
                    self::assertSame([$actor->toString()], $parameters);

                    return false;
                }
                self::assertStringContainsString('count(*)', $sql);

                return 10000;
            }
        );

        $this->assertDenied($connection, $actor);
    }

    /**
     * Rejects a request if the guarded attempt update returns no row for the actor
     */
    public function testFullActorBudgetDoesNotGenerateReference(): void
    {
        $actor = UserId::generate();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $parameters) use ($actor): int|false {
                if (str_starts_with($sql, 'INSERT')) {
                    self::assertSame([$actor->toString(), 1200], $parameters);

                    return false;
                }
                self::assertSame([$actor->toString()], $parameters);

                return 1;
            }
        );

        $this->assertDenied($connection, $actor);
    }

    /**
     * Checks the denial retry delay against a fixed instant late in the minute
     */
    private function assertDenied(Connection&MockObject $connection, UserId $actor): void
    {
        $connection->method('transactional')->willReturnCallback(static fn (Closure $consume): mixed => $consume());
        $connection->method('executeStatement')->with(self::stringStartsWith('DELETE'), [1200]);
        $references = new ExceptionalReferences($connection, new AccessClock(new DateTimeImmutable('@1257')));

        try {
            $references->generate($actor);
            self::fail('No reference may be issued after denial');
        } catch (RateLimitExceeded $failure) {
            self::assertSame(3, $failure->retryDelaySeconds());
        }
    }
}
