<?php

declare(strict_types=1);

namespace App\Application\Users;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\Query\ListUsers;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class ReadUserDirectory
 *
 * Authorizes installation reads and resolves exact authoritative role presentation
 */
final readonly class ReadUserDirectory
{
    /**
     * Constructs ReadUserDirectory
     */
    public function __construct(
        private AuthenticatedPermissions $authentication,
        private InstallationAuthority $installation,
        private QueryBus $queries,
        private UserRolePresentation $roles
    ) {
    }

    /**
     * Reads a fixed-order package page without caller-selected identity, visibility or authority
     *
     * @return array{users: ResultSet<UserView>, roles: array<string, list<array{name: string, managed: bool}>>}
     */
    public function read(AuthenticationContext $authentication, int $page = 1, int $perPage = 25): array
    {
        $permission = PermissionName::fromString('READ_USERS');
        $actor = $this->authentication->require($authentication, [$permission]);
        try {
            $this->installation->assertInstallationPermission($actor, $permission);
        } catch (Throwable) {
            throw new PermissionDenied();
        }
        if ($page < 1 || $page > 1000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid directory pagination.');
        }
        $result = $this->queries->fetch(new ListUsers(new Pagination($page, $perPage)));
        if (
            !$result instanceof ResultSet
            || $result->itemType() !== UserView::class
            || $result->page() !== $page
            || $result->perPage() !== $perPage
            || $result->count() > $perPage
        ) {
            throw new LogicException('User directory is unavailable.');
        }

        return ['users' => $result, 'roles' => $this->roles->resolve($result->records())];
    }
}
