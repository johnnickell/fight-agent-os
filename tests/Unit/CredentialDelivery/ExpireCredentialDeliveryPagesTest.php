<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Application\CredentialDelivery\ExpireCredentialDeliveryPages;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ExpireInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\ExpiredCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindExpiredCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\Command\ExpireEmailChange;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeGrantId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\ExpirePasswordResetDelivery;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\Command;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves bounded cleanup orchestration independently of package lifecycle policy
 */
final class ExpireCredentialDeliveryPagesTest extends TestCase
{
    /**
     * Dispatches exact identities at one boundary and waits for writes before rediscovery
     */
    public function testDispatchesOrderedPagesAndChecksPersistedProgress(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00.123456Z');
        $activation = $this->item('activation', $at);
        $reset = $this->item('password_reset', $at);
        $email = $this->item('email_change', $at);
        $remaining = [$email, $reset, $activation];
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::exactly(3))->method('fetch')->willReturnCallback(
            static function (FindExpiredCredentialDeliveries $query) use ($at, &$remaining): array {
                self::assertSame($at, $query->getAt());
                self::assertSame(2, $query->getLimit());

                return array_slice($remaining, 0, 2);
            }
        );
        $commands = $this->createMock(SynchronousCommandBus::class);
        $seen = [];
        $commands->expects(self::exactly(3))->method('execute')->willReturnCallback(
            static function (Command $command) use (&$seen, &$remaining): void {
                $seen[] = $command;
                array_shift($remaining);
            }
        );
        $clock = $this->createMock(Clock::class);
        $clock->expects(self::once())->method('now')->willReturn($at);
        $report = (new ExpireCredentialDeliveryPages($queries, $commands, $clock))->run(2, 2);
        self::assertSame(
            ['pages' => 2, 'dispatched' => 3, 'remaining' => 0, 'stalled' => false, 'budget_exhausted' => false],
            $report
        );
        self::assertInstanceOf(ExpireEmailChange::class, $seen[0]);
        self::assertInstanceOf(ExpirePasswordResetDelivery::class, $seen[1]);
        self::assertInstanceOf(ExpireInvitationDelivery::class, $seen[2]);
        self::assertSame($email->getEmailChangeGrantId(), $seen[0]->getEmailChangeGrantId());
        self::assertSame($reset->getDeliveryId()->toString(), $seen[1]->getPasswordResetDeliveryId()->toString());
        self::assertSame($activation->getDeliveryId()->toString(), $seen[2]->getActivationDeliveryId()->toString());
        foreach ($seen as $index => $command) {
            self::assertTrue(
                $command instanceof ExpireEmailChange
                || $command instanceof ExpirePasswordResetDelivery
                || $command instanceof ExpireInvitationDelivery
            );
            self::assertSame('credential-recovery', $command->getActorId());
            self::assertSame($at, $command->getOccurredAt());
            self::assertSame([$email, $reset, $activation][$index]->getUserId(), $command->getUserId());
        }
    }

    /**
     * Refuses to treat a returned void command as successful cleanup
     */
    public function testUnchangedWorkStopsWithoutBusySpin(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::exactly(2))->method('fetch')->willReturn([$this->item('activation', $at)]);
        $commands = $this->createMock(SynchronousCommandBus::class);
        $commands->expects(self::once())->method('execute');
        self::assertSame(
            ['pages' => 1, 'dispatched' => 1, 'remaining' => 1, 'stalled' => true, 'budget_exhausted' => false],
            (new ExpireCredentialDeliveryPages($queries, $commands, $this->clock($at)))->run()
        );
    }

    /**
     * Stops a progressing queue at the finite cycle budget
     */
    public function testBudgetExhaustionIsNotAnEmptyQueue(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::exactly(2))->method('fetch')->willReturnOnConsecutiveCalls(
            [$this->item('activation', $at)],
            [$this->item('password_reset', $at)]
        );
        $commands = $this->createMock(SynchronousCommandBus::class);
        $commands->expects(self::once())->method('execute');
        self::assertSame(
            ['pages' => 1, 'dispatched' => 1, 'remaining' => 1, 'stalled' => false, 'budget_exhausted' => true],
            (new ExpireCredentialDeliveryPages($queries, $commands, $this->clock($at)))->run(1, 1)
        );
    }

    /**
     * Returns immediately for an empty queue
     */
    public function testEmptyDiscoveryDoesNotDispatch(): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->willReturn([]);
        $commands = $this->createMock(SynchronousCommandBus::class);
        $commands->expects(self::never())->method('execute');
        $runner = new ExpireCredentialDeliveryPages(
            $queries,
            $commands,
            $this->clock(new DateTimeImmutable('2026-10-01T12:00:00Z'))
        );
        self::assertSame(0, $runner->run()['dispatched']);
    }

    /**
     * Stops after failure without further dispatch or misleading cleanup success
     */
    public function testFailurePropagatesBeforeAnotherDispatch(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->willReturn([
            $this->item('activation', $at), $this->item('password_reset', $at)
        ]);
        $commands = $this->createMock(SynchronousCommandBus::class);
        $commands->expects(self::once())->method('execute')->willThrowException(new RuntimeException('private'));
        $this->expectException(RuntimeException::class);
        (new ExpireCredentialDeliveryPages($queries, $commands, $this->clock($at)))->run();
    }

    /**
     * Rejects unsafe bounds before querying or dispatching
     */
    #[DataProvider('invalidBounds')]
    public function testRejectsInvalidBounds(int $size, int $pages): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        $commands = $this->createMock(SynchronousCommandBus::class);
        $commands->expects(self::never())->method('execute');
        $this->expectException(InvalidArgumentException::class);
        $runner = new ExpireCredentialDeliveryPages(
            $queries,
            $commands,
            $this->clock(new DateTimeImmutable('2026-10-01T12:00:00Z'))
        );
        $runner->run($size, $pages);
    }

    /**
     * Supplies invalid operational bounds
     *
     * @return iterable<array{int, int}>
     */
    public static function invalidBounds(): iterable
    {
        yield [0, 1];
        yield [101, 1];
        yield [1, 0];
        yield [1, 11];
    }

    /**
     * Creates secret-free package work references
     */
    private function item(string $purpose, DateTimeImmutable $at): ExpiredCredentialDelivery
    {
        $id = match ($purpose) {
            'activation' => ActivationDeliveryId::generate(),
            'password_reset' => PasswordResetDeliveryId::generate(),
            'email_change' => EmailChangeDeliveryId::generate(),
            default => throw new InvalidArgumentException('Unsupported fixture purpose.')
        };

        return new ExpiredCredentialDelivery(
            $purpose,
            $id,
            UserId::generate(),
            $purpose === 'email_change' ? EmailChangeGrantId::generate() : null,
            $at,
            0,
            CredentialDeliveryStatus::PENDING
        );
    }

    /**
     * Supplies a fixed boundary
     */
    private function clock(DateTimeImmutable $at): Clock
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($at);

        return $clock;
    }
}
