<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\Command\CreateCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Command\RemoveCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Command\RenameCustomRole;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRevision;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class ManageCustomRoles
 *
 * Establishes current installation authority for every entry path and leaves intrinsic invariants with the package
 */
final readonly class ManageCustomRoles
{
    /**
     * Constructs ManageCustomRoles
     */
    public function __construct(
        private AuthenticatedPermissions $authentication,
        private InstallationAuthority $installation,
        private CommandBus $commands,
        private QueryBus $queries
    ) {
    }

    /**
     * Creates a custom definition without assigning any authority
     */
    public function create(AuthenticationContext $authentication, RoleName $name): RoleView
    {
        $actor = $this->authorize($authentication, 'CREATE_ROLES');
        $id = RoleId::generate();
        $this->commands->execute(new CreateCustomRole($actor, $id, $name));

        return $this->current($id);
    }

    /**
     * Renames only the exact displayed revision without silently retrying stale intent
     */
    public function rename(
        AuthenticationContext $authentication,
        RoleId $id,
        RoleName $name,
        RoleRevision $expectedRevision
    ): RoleView {
        $actor = $this->authorize($authentication, 'UPDATE_ROLES');
        $this->commands->execute(new RenameCustomRole($actor, $id, $name, $expectedRevision));

        return $this->current($id);
    }

    /**
     * Removes only an explicitly confirmed exact revision
     */
    public function remove(
        AuthenticationContext $authentication,
        RoleId $id,
        RoleRevision $expectedRevision,
        bool $confirmed
    ): void {
        $actor = $this->authorize($authentication, 'DELETE_ROLES');
        if (!$confirmed) {
            throw new InvalidArgumentException('Explicit removal confirmation is required.');
        }
        $this->commands->execute(new RemoveCustomRole($actor, $id, $expectedRevision));
    }

    /**
     * Resolves actor provenance and the exact protected operation permission
     */
    private function authorize(AuthenticationContext $authentication, string $name): UserId
    {
        $permission = PermissionName::fromString($name);
        $actor = $this->authentication->require($authentication, [$permission]);
        try {
            $this->installation->assertInstallationPermission($actor, $permission);
        } catch (Throwable) {
            throw new PermissionDenied();
        }

        return $actor;
    }

    /**
     * Reads actual state after confirmed dispatch without imposing an unrelated READ_ROLES permission
     */
    private function current(RoleId $id): RoleView
    {
        $view = $this->queries->fetch(new GetRoleById($id));
        if (!$view instanceof RoleView || !$view->getRoleId()->equals($id)) {
            throw new LogicException('The mutation outcome requires authoritative reconciliation.');
        }

        return $view;
    }
}
