<?php

declare(strict_types=1);

namespace Tests\Unit\Sessions;

use App\Application\Security\Session\BrowserFamily;
use App\Application\Security\Session\DeviceDescriptor;
use App\Application\Security\Session\QueryHandler\ListOwnedSessionsHandler;
use App\Application\Security\Session\SessionPresentation;
use App\Domain\Security\Session\Query\ListOwnedSessions;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Repository\ResultSet;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Covers the owned projection and its optional metadata boundary
 */
final class ListOwnedSessionsTest extends TestCase
{
    /**
     * Adds device hints without replacing the authoritative page or requiring optional storage
     */
    #[DataProvider('availability')]
    public function testProjection(bool $available): void
    {
        $userId = UserId::generate();
        $id = RefreshSessionId::generate();
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $records = ArrayList::of(SessionView::class);
        $records->add(new SessionView(
            $id,
            $userId,
            $now,
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            false,
            true
        ));
        $sessions = ResultSet::strict(2, 1, 2, $records);
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::once())->method('fetch')->willReturnCallback(
            static function (ListActiveSessions $query) use ($sessions, $userId, $id): ResultSet {
                self::assertTrue($query->getActorId()->equals($userId));
                self::assertTrue($query->getUserId()->equals($userId));
                self::assertTrue($query->getCurrentSessionId()->equals($id));
                self::assertSame(2, $query->getPagination()->page());
                self::assertSame(1, $query->getPagination()->perPage());
                self::assertSame([], $query->getPagination()->orderings());

                return $sessions;
            }
        );
        $presentation = $this->createMock(SessionPresentation::class);
        $metadata = $presentation->expects(self::once())->method('descriptors')->with([$id->toString()]);
        if ($available) {
            $metadata->willReturn([$id->toString() => new DeviceDescriptor(BrowserFamily::FIREFOX)]);
        } else {
            $metadata->willThrowException(new RuntimeException('Optional storage unavailable.'));
        }
        $input = new ListOwnedSessions($userId, $id, 2, 1);
        $query = ListOwnedSessions::fromArray($input->toArray());
        $page = new ListOwnedSessionsHandler($queryBus, $presentation)->handle(QueryMessage::create($query));

        self::assertSame($sessions, $page->sessions);
        self::assertSame(
            $available ? BrowserFamily::FIREFOX : BrowserFamily::UNKNOWN,
            $page->deviceFor($id)->browserFamily
        );
    }

    /**
     * Provides available and unavailable optional storage
     *
     * @return iterable<array{bool}>
     */
    public static function availability(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * Rejects an internal request outside the supported page boundaries
     */
    public function testRejectsOutOfBoundsPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ListOwnedSessions(UserId::generate(), RefreshSessionId::generate(), 1001, 1);
    }

    /**
     * Rejects a malformed internal payload before dispatch
     */
    public function testRejectsMalformedPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ListOwnedSessions::fromArray(['userId' => UserId::generate()->toString()]);
    }
}
