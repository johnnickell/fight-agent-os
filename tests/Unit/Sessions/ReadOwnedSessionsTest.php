<?php

declare(strict_types=1);

namespace Tests\Unit\Sessions;

use App\Adapter\Http\Api\Auth\RequestAuthenticationContext;
use App\Application\Authorization\PermissionGuard;
use App\Application\Failure\PermissionDenied;
use App\Application\Sessions\ReadOwnedSessions;
use App\Application\Sessions\SessionPresentation;
use DateTimeImmutable;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\Permission\Permission;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\ListActiveSessions;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Query\SessionView;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshCredential;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\AccessClock;

/**
 * Protects Application ownership and fail-closed package result boundaries independently of HTTP
 */
final class ReadOwnedSessionsTest extends TestCase
{
    /**
     * Requires principal-owned inputs, safe result types and bounded work on every entry
     */
    #[DataProvider('cases')]
    public function testRead(string $fault): void
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $permission = Permission::define(
            PermissionId::generate(),
            PermissionName::fromString('READ_OWN_SESSIONS'),
            $now
        );
        $role = Role::define(
            RoleId::generate(),
            RoleName::fromString('ROLE_READER'),
            $fault === 'denied' ? [] : [$permission->getId()],
            $now
        );
        $user = User::invite(UserId::generate(), EmailAddress::fromString('sessions@example.test'), $now);
        $user->activate(
            PasswordHash::fromString((string) password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4])),
            $now
        );
        $user->assignRole($role->getId(), $now);
        $session = RefreshSession::start(
            RefreshSessionId::generate(),
            $user->getId(),
            RefreshCredential::fromString(bin2hex(random_bytes(32))),
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            1,
            false
        );
        $users = $this->createStub(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $sessions = $this->createStub(RefreshSessionRepository::class);
        $sessions->method('getById')->willReturn($session);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('getByIds')->willReturn([$role]);
        $permissions = $this->createStub(PermissionRepository::class);
        $permissions->method('getByIds')->willReturn($fault === 'denied' ? [] : [$permission]);
        $provider = new CurrentPrincipalProvider(
            new RequestAuthenticationContext(new AuthenticationContext($user->getId(), $session->getId(), 1)),
            new AuthoritativePrincipalResolver($users, $sessions, $roles, $permissions, new AccessClock($now))
        );
        $view = $fault === 'foreign' ? new SessionView(
            $session->getId(),
            UserId::generate(),
            $now,
            $now,
            $now->modify('+1 day'),
            $now->modify('+2 days'),
            false,
            true
        ) : SessionView::fromSession($session, $session->getId());
        $records = ArrayList::of(SessionView::class);
        if ($fault !== 'empty') {
            $records->add($view);
        }
        $result = new ResultSet(1, 25, count($records), $records);
        $bus = $this->createMock(QueryBus::class);
        $bus->expects(in_array($fault, ['denied', 'bounds'], true) ? self::never() : self::once())
            ->method('fetch')->willReturnCallback(function ($query) use ($user, $session, $result, $fault) {
                self::assertInstanceOf(ListActiveSessions::class, $query);
                self::assertTrue($query->getActorId()->equals($user->getId()));
                self::assertTrue($query->getUserId()->equals($user->getId()));
                self::assertTrue($query->getCurrentSessionId()->equals($session->getId()));
                self::assertSame([], $query->getPagination()->orderings());

                return $fault === 'type' ? null : $result;
            });
        $metadata = $this->createMock(SessionPresentation::class);
        $skipMetadata = in_array($fault, ['denied', 'bounds', 'type', 'foreign'], true);
        $metadata->expects($skipMetadata ? self::never() : self::once())
            ->method('descriptors')->willReturnCallback(function ($ids) use ($fault, $session) {
                self::assertSame($fault === 'empty' ? [] : [$session->getId()->toString()], $ids);
                if ($fault === 'metadata') {
                    throw new RuntimeException('private-storage-marker');
                }

                return [];
            });
        if ($fault === 'denied') {
            $this->expectException(PermissionDenied::class);
        } elseif ($fault === 'bounds') {
            $this->expectException(InvalidArgumentException::class);
        } elseif (in_array($fault, ['foreign', 'type'], true)) {
            $this->expectException(LogicException::class);
        }
        $page = new ReadOwnedSessions(new PermissionGuard(), $bus, $metadata)->read(
            $provider,
            $fault === 'bounds' ? 1001 : 1
        );
        self::assertSame($result, $page['sessions']);
        self::assertSame([], $page['devices']);
    }

    /**
     * Supplies meaningful authorization, bounds, mapping and optional-data failures
     *
     * @return iterable<array{string}>
     */
    public static function cases(): iterable
    {
        foreach (['success', 'empty', 'metadata', 'type', 'foreign', 'denied', 'bounds'] as $fault) {
            yield [$fault];
        }
    }
}
