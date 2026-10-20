<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use App\Application\Failure\ResourceNotFound;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\GetPermissionById;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\ListRoles;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class ReadAuthorityCatalogs
 *
 * Enforces invocation-bound installation policy before trusted package catalog queries
 */
final readonly class ReadAuthorityCatalogs
{
    /**
     * Constructs ReadAuthorityCatalogs
     */
    public function __construct(
        private AuthenticatedPermissions $authentication,
        private InstallationAuthority $installation,
        private QueryBus $queries
    ) {
    }

    /**
     * Reads a bounded role page without accepting caller-selected ordering
     *
     * @return ResultSet<RoleView>
     */
    public function roles(AuthenticationContext $authentication, int $page = 1, int $perPage = 25): ResultSet
    {
        $this->authorize($authentication, 'READ_ROLES');
        $result = $this->queries->fetch(new ListRoles($this->pagination($page, $perPage)));
        if (!$result instanceof ResultSet || $result->itemType() !== RoleView::class) {
            throw new LogicException('Role catalog is unavailable.');
        }

        return $result;
    }

    /**
     * Reads one role only after current installation permission succeeds
     */
    public function role(AuthenticationContext $authentication, RoleId $id): RoleView
    {
        $this->authorize($authentication, 'READ_ROLES');
        $view = $this->queries->fetch(new GetRoleById($id));
        if ($view === null) {
            throw new ResourceNotFound();
        }
        if (!$view instanceof RoleView || !$view->getRoleId()->equals($id)) {
            throw new LogicException('Role catalog is unavailable.');
        }

        return $view;
    }

    /**
     * Reads a bounded permission page without accepting caller-selected ordering
     *
     * @return ResultSet<PermissionView>
     */
    public function permissions(AuthenticationContext $authentication, int $page = 1, int $perPage = 25): ResultSet
    {
        $this->authorize($authentication, 'READ_PERMISSIONS');
        $result = $this->queries->fetch(new ListPermissions($this->pagination($page, $perPage)));
        if (!$result instanceof ResultSet || $result->itemType() !== PermissionView::class) {
            throw new LogicException('Permission catalog is unavailable.');
        }

        return $result;
    }

    /**
     * Reads one permission without exposing grants or mutation capability
     */
    public function permission(AuthenticationContext $authentication, PermissionId $id): PermissionView
    {
        $this->authorize($authentication, 'READ_PERMISSIONS');
        $view = $this->queries->fetch(new GetPermissionById($id));
        if ($view === null) {
            throw new ResourceNotFound();
        }
        if (!$view instanceof PermissionView || !$view->getPermissionId()->equals($id)) {
            throw new LogicException('Permission catalog is unavailable.');
        }

        return $view;
    }

    /**
     * Checks all principal references and the exact protected managed permission before dispatch
     */
    private function authorize(AuthenticationContext $authentication, string $name): void
    {
        $permission = PermissionName::fromString($name);
        $actor = $this->authentication->require($authentication, [$permission]);
        try {
            $this->installation->assertInstallationPermission($actor, $permission);
        } catch (Throwable) {
            throw new PermissionDenied();
        }
    }

    /**
     * Bounds work for every caller and fixes the repository's committed-state ordering
     */
    private function pagination(int $page, int $perPage): Pagination
    {
        if ($page < 1 || $page > 1000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid catalog pagination.');
        }

        return new Pagination($page, $perPage);
    }
}
