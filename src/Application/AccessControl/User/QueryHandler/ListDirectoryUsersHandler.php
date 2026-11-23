<?php

declare(strict_types=1);

namespace App\Application\AccessControl\User\QueryHandler;

use App\Application\AccessControl\User\View\DirectoryUser;
use App\Application\AccessControl\User\View\UserDirectoryPage;
use App\Domain\AccessControl\User\Query\ListDirectoryUsers;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\User\Query\ListUsers;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use InvalidArgumentException;
use LogicException;

/**
 * Class ListDirectoryUsersHandler
 *
 * Joins a canonical User page with referenced Roles without granting authority
 */
final readonly class ListDirectoryUsersHandler implements QueryHandler
{
    /**
     * Constructs ListDirectoryUsersHandler
     */
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListDirectoryUsers::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): UserDirectoryPage
    {
        $query = $queryMessage->payload();
        if (!$query instanceof ListDirectoryUsers) {
            throw new InvalidArgumentException('Unexpected directory query.');
        }

        /** @var ResultSet<UserView> $page */
        $page = $this->queryBus->fetch(new ListUsers(Pagination::strict(
            $query->page,
            $query->perPage,
            $query->orderings
        )));
        $roles = [];
        $names = [];
        $users = [];
        foreach ($page->records() as $user) {
            $userKey = $user->getUserId()->toString();
            if (isset($users[$userKey])) {
                throw new LogicException('User presentation is unavailable.');
            }
            $assigned = [];
            foreach ($user->getRoleIds() as $id) {
                $key = $id->toString();
                if (!isset($roles[$key])) {
                    try {
                        /** @var RoleView $role */
                        $role = $this->queryBus->fetch(new GetRoleById($id));
                    } catch (ResourceNotFound $error) {
                        throw new LogicException('A directory Role reference is unavailable.', previous: $error);
                    }
                    $name = $role->getName()->toString();
                    if (isset($names[$name])) {
                        throw new LogicException('User role presentation is unavailable.');
                    }
                    $names[$name] = true;
                    $roles[$key] = $role;
                }
                $assigned[] = $roles[$key];
            }
            usort($assigned, static fn(RoleView $a, RoleView $b): int => strcmp(
                $a->getName()->toString(),
                $b->getName()->toString()
            ));
            $users[$userKey] = new DirectoryUser($user, $assigned);
        }

        return new UserDirectoryPage(
            $page->page(),
            $page->perPage(),
            $page->totalPages(),
            $page->totalRecords(),
            array_values($users)
        );
    }
}
