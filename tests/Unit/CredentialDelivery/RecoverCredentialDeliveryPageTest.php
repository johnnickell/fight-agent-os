<?php

declare(strict_types=1);

namespace Tests\Unit\CredentialDelivery;

use App\Application\CredentialDelivery\RecoverCredentialDeliveryPage;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Timing\Service\Clock;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationDeliveryId;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\DeliverUserInvitation;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotRetryableException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\CredentialDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\DueCredentialDelivery;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Exception\CredentialDeliveryTransitionException;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\FindDueCredentialDeliveries;
use Fight\AccessControl\Domain\AccessControl\EmailChangeGrant\EmailChangeDeliveryId;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Command\DeliverPasswordReset;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\Exception\PasswordResetDeliveryException;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetDeliveryId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\Command;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class RecoverCredentialDeliveryPageTest
 *
 * Proves bounded operational dispatch without consumer lifecycle policy
 */
final class RecoverCredentialDeliveryPageTest extends TestCase
{
    /**
     * Proves package page order and exact generation dispatch under the smaller operational bound
     */
    #[DataProvider('bounds')]
    public function testBoundedDispatch(int $pageSize, int $capacity, int $limit): void
    {
        $at = new DateTimeImmutable('2026-10-02T12:00:00Z');
        $activation = $this->work('activation', $at);
        $reset = $this->work('password_reset', $at);
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('fetch')->willReturnCallback(
            static function (FindDueCredentialDeliveries $query) use ($at, $limit, $activation, $reset): array {
                self::assertSame($at, $query->getAt());
                self::assertSame($limit, $query->getLimit());

                return [$reset, $activation];
            }
        );
        $commands = $this->createMock(CommandBus::class);
        $seen = [];
        $commands->expects(self::exactly(2))->method('execute')->willReturnCallback(
            static function (Command $command) use (&$seen): void {
                $seen[] = $command;
            }
        );
        $runner = new RecoverCredentialDeliveryPage($queries, $commands, $this->clock($at));
        self::assertSame([
            'discovered'  => 2,
            'offered'     => 2,
            'dispatched'  => 2,
            'contended'   => 0,
            'unsupported' => 0,
            'stopped'     => false
        ], $runner->run($pageSize, $capacity));
        self::assertInstanceOf(DeliverPasswordReset::class, $seen[0]);
        self::assertInstanceOf(DeliverUserInvitation::class, $seen[1]);
        self::assertSame($reset->getDeliveryId()->toString(), $seen[0]->getPasswordResetDeliveryId()->toString());
        self::assertSame($activation->getDeliveryId()->toString(), $seen[1]->getActivationDeliveryId()->toString());
        self::assertSame($reset->getUserId(), $seen[0]->getUserId());
        self::assertSame($activation->getUserId(), $seen[1]->getUserId());
        self::assertSame('credential-recovery', $seen[0]->getActorId());
        self::assertSame('credential-recovery', $seen[1]->getActorId());
    }

    /**
     * Supplies independently selected capacity bounds
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function bounds(): iterable
    {
        yield 'capacity' => [10, 2, 2];
        yield 'page' => [2, 10, 2];
        yield 'maximum' => [1000, 1000, 1000];
    }

    /**
     * Proves invalid operational bounds cannot discover or dispatch work
     */
    #[DataProvider('invalidBounds')]
    public function testInvalidBounds(int $pageSize, int $capacity): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('fetch');
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $runner = new RecoverCredentialDeliveryPage(
            $queries,
            $commands,
            $this->clock(new DateTimeImmutable('2026-10-01T12:00:00Z'))
        );
        $this->expectException(InvalidArgumentException::class);
        $runner->run($pageSize, $capacity);
    }

    /**
     * Supplies invalid page and capacity bounds
     *
     * @return iterable<array{int, int}>
     */
    public static function invalidBounds(): iterable
    {
        yield [0, 1];
        yield [1001, 1];
        yield [1, 0];
        yield [1, 1001];
    }

    /**
     * Proves an empty page has no effect and email-change work never reaches an unregistered handler
     */
    public function testEmptyAndUnsupportedPages(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturnOnConsecutiveCalls([], [$this->work('email_change', $at)]);
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('execute');
        $runner = new RecoverCredentialDeliveryPage($queries, $commands, $this->clock($at));
        self::assertSame(0, $runner->run(1, 1)['discovered']);
        self::assertSame([
            'discovered'  => 1,
            'offered'     => 0,
            'dispatched'  => 0,
            'contended'   => 0,
            'unsupported' => 1,
            'stopped'     => false
        ], $runner->run(1, 1));
    }

    /**
     * Proves stale package work stops the current worker before using a closed transaction manager
     *
     * @phpstan-param class-string<\Exception> $exception
     */
    #[DataProvider('contentions')]
    public function testContentionStopsPage(string $exception): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturn([$this->work('activation', $at), $this->work('password_reset', $at)]);
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('execute')->willThrowException(new $exception('private material'));
        $runner = new RecoverCredentialDeliveryPage($queries, $commands, $this->clock($at));
        self::assertSame([
            'discovered'  => 2,
            'offered'     => 1,
            'dispatched'  => 0,
            'contended'   => 1,
            'unsupported' => 0,
            'stopped'     => true
        ], $runner->run(2, 2));
    }

    /**
     * Supplies only the package stale-generation and claim failures
     *
     * @return iterable<array{class-string<\Exception>}>
     */
    public static function contentions(): iterable
    {
        yield [ActivationDeliveryNotRetryableException::class];
        yield [PasswordResetDeliveryException::class];
        yield [CredentialDeliveryTransitionException::class];
    }

    /**
     * Proves infrastructure failures propagate rather than masquerading as healthy contention
     */
    public function testUnexpectedFailureStopsDispatch(): void
    {
        $at = new DateTimeImmutable('2026-10-01T12:00:00Z');
        $queries = $this->createMock(QueryBus::class);
        $queries->method('fetch')->willReturn([$this->work('activation', $at), $this->work('password_reset', $at)]);
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('execute')->willThrowException(new RuntimeException('private'));
        $runner = new RecoverCredentialDeliveryPage($queries, $commands, $this->clock($at));
        $this->expectException(RuntimeException::class);
        $runner->run(2, 2);
    }

    /**
     * Creates safe discovered references using package ID factories
     */
    private function work(string $purpose, DateTimeImmutable $at): DueCredentialDelivery
    {
        $id = match ($purpose) {
            'activation' => ActivationDeliveryId::generate(),
            'password_reset' => PasswordResetDeliveryId::generate(),
            default => EmailChangeDeliveryId::generate()
        };

        return new DueCredentialDelivery($purpose, $id, UserId::generate(), $at, 0, CredentialDeliveryStatus::PENDING);
    }

    /**
     * Creates a fixed package clock
     */
    private function clock(DateTimeImmutable $at): Clock
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($at);

        return $clock;
    }
}
