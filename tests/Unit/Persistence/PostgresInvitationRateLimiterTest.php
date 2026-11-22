<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence;

use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRatePolicy;
use App\Adapter\Persistence\Invitation\PostgresInvitationRateLimiter;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies invitation quota decisions independently of PostgreSQL locking semantics
 */
final class PostgresInvitationRateLimiterTest extends TestCase
{
    private Connection&MockObject $connection;
    private PostgresInvitationRateLimiter $limiter;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@1205'));
        $this->connection = $this->createMock(Connection::class);
        $this->limiter = new PostgresInvitationRateLimiter($this->connection, $clock, new InvitationRatePolicy(
            windowSeconds: 60,
            capacity: 2,
            resendLimit: 3
        ));
    }

    /**
     * Refuses to couple admission to the caller's business transaction
     */
    public function testRefusesAnEnclosingTransaction(): void
    {
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection->expects(self::never())->method('transactional');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invitation admission requires an independent transaction.');
        $this->limiter->consume(UserId::generate(), InvitationOperation::ISSUE);
    }

    /**
     * Charges the actor and target budgets even when the target already occupies a retained slot
     */
    public function testChargesBothDimensionsAtTheCapacityBoundary(): void
    {
        $this->runAdmission();
        $actor = UserId::generate();
        $target = UserId::generate();
        $scopes = [];
        $this->connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnCallback(
            static function (string $sql, array $parameters) use ($actor, $target, &$scopes): array|false {
                self::assertSame([$actor->toString(), 'resend'], array_slice($parameters, 0, 2));
                $scopes[] = $parameters[2];

                return match ($parameters[2]) {
                    '' => false,
                    $target->toString() => ['attempts' => '2', 'window_end' => '1260'],
                    default => self::fail('Unexpected target scope')
                };
            }
        );
        $this->connection->expects(self::once())->method('fetchOne')->willReturn('1');
        $writes = [];
        $this->connection->expects(self::exactly(3))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use (&$writes): int {
                if (str_starts_with($sql, 'DELETE')) {
                    self::assertSame([1205], $parameters);
                } else {
                    $writes[] = $parameters;
                }

                return 1;
            }
        );

        $this->limiter->consume($actor, InvitationOperation::RESEND, $target);

        self::assertSame(['', $target->toString()], $scopes);
        self::assertSame([
            [$actor->toString(), 'resend', '', 1260],
            [$actor->toString(), 'resend', $target->toString(), 1260]
        ], $writes);
    }

    /**
     * Denies a full budget or a new slot beyond capacity without inserting an attempt
     */
    #[DataProvider('denials')]
    public function testDeniedAdmissionDoesNotConsumeBudget(bool $exhausted, ?int $earliest, int $retry): void
    {
        $this->runAdmission();
        $actor = UserId::generate();
        $this->connection->expects(self::once())->method('fetchAssociative')
            ->with(self::anything(), [$actor->toString(), 'resend', ''])
            ->willReturn($exhausted ? ['attempts' => '3', 'window_end' => '1260'] : false);
        $this->connection->expects(self::exactly($exhausted ? 0 : 2))->method('fetchOne')
            ->willReturnCallback(static fn (string $sql): ?int => str_contains($sql, 'count(*)') ? 2 : $earliest);
        $this->connection->expects(self::once())->method('executeStatement')->with(
            self::stringStartsWith('DELETE'),
            [1205]
        );

        try {
            $this->limiter->consume($actor, InvitationOperation::RESEND);
            self::fail('Admission must fail closed');
        } catch (RateLimitExceeded $failure) {
            self::assertSame($retry, $failure->retryDelaySeconds());
        }
    }

    /**
     * Supplies independent window and capacity denial facts
     *
     * @return iterable<string, array{bool, ?int, int}>
     */
    public static function denials(): iterable
    {
        yield 'operation budget exhausted' => [true, null, 55];
        yield 'capacity released by an earlier expiry' => [false, 1220, 15];
        yield 'no retained minimum, use this window' => [false, null, 55];
    }

    /**
     * Runs admission in the supplied transaction callback without a database
     */
    private function runAdmission(): void
    {
        $this->connection->method('isTransactionActive')->willReturn(false);
        $this->connection->expects(self::once())->method('transactional')
            ->willReturnCallback(static fn (Closure $admit): mixed => $admit());
        $this->connection->expects(self::once())->method('executeQuery')->willReturn($this->createStub(Result::class));
    }
}
