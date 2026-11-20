<?php

declare(strict_types=1);

namespace Tests\Unit\Application\AccessControl\User\QueryHandler;

use App\Application\AccessControl\User\QueryHandler\ListDirectoryUsersHandler;
use App\Domain\AccessControl\User\Query\ListDirectoryUsers;
use DateTimeImmutable;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\User\Query\ListUsers;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Protects the directory projection from producing a misleading page
 */
final class ListDirectoryUsersHandlerTest extends TestCase
{
    /**
     * Rejects an unrelated query before reading any User or Role data
     */
    public function testRejectsUnexpectedQueryWithoutFetching(): void
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->never())->method('fetch');
        $handler = new ListDirectoryUsersHandler($queryBus);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected directory query.');
        $handler->handle(QueryMessage::create(new ListUsers(new Pagination(1, 25))));
    }

    /**
     * Fails the projection when two different referenced Roles would appear under one name
     */
    public function testRejectsDistinctRolesWithTheSamePresentedName(): void
    {
        $now = new DateTimeImmutable('2026-11-02T12:00:00Z');
        $name = RoleName::fromString('ROLE_READER');
        $first = Role::define(RoleId::generate(), $name, [], $now);
        $second = Role::define(RoleId::generate(), $name, [], $now);
        $user = User::invite(UserId::generate(), EmailAddress::fromString('reader@example.test'), $now);
        $user->assignRole($first->getId(), $now);
        $user->assignRole($second->getId(), $now);
        $page = new ResultSet(1, 25, 1, ArrayList::of(UserView::class)->replace([UserView::fromUser($user)]));
        $roles = [
            $first->getId()->toString()  => RoleView::fromRole($first),
            $second->getId()->toString() => RoleView::fromRole($second)
        ];
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('fetch')->willReturnCallback(static fn(Query $query): ResultSet|RoleView => match (true) {
            $query instanceof ListUsers   => $page,
            $query instanceof GetRoleById => $roles[$query->getRoleId()->toString()],
            default                       => self::fail('Unexpected query: '.$query::class)
        });
        $handler = new ListDirectoryUsersHandler($queryBus);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('User role presentation is unavailable.');
        $handler->handle(QueryMessage::create(new ListDirectoryUsers(1, 25)));
    }
}
