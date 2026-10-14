<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\ConsoleRoleInput;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\GetPermissionById;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Application\Messaging\Query\QueryBus;
use LogicException;

/**
 * Class ConsoleAssignmentPolicy
 *
 * Qualifies actual current assignment references within the fixed installation catalog
 */
final readonly class ConsoleAssignmentPolicy
{
    /**
     * Constructs ConsoleAssignmentPolicy
     */
    public function __construct(private QueryBus $queries)
    {
    }

    /**
     * Requires the complete version-controlled authority before any console mutation
     */
    public function requireInstallation(): void
    {
        InstallationManagedPolicy::requireReconciled($this->queries->fetch(
            new PreviewManagedPolicy(InstallationManagedPolicy::create())
        ));
    }

    /**
     * Requires exact installation authority and safe existing target and role references
     */
    public function requireEligibleAssignment(ConsoleRoleInput $input): UserView
    {
        $this->requireInstallation();
        $user = $this->queries->fetch(new GetUserById($input->userId));
        if (!$user instanceof UserView || !$user->getUserId()->equals($input->userId)) {
            throw new LogicException('The target user is unavailable.');
        }
        $role = $this->queries->fetch(new GetRoleById($input->roleId));
        $input->requireEligibleRole($role);
        assert($role instanceof RoleView);
        foreach ($role->getPermissionIds() as $permissionId) {
            $permission = $this->queries->fetch(new GetPermissionById($permissionId));
            if (
                !$permission instanceof PermissionView || !$permission->getPermissionId()->equals($permissionId)
                || $permission->getTier() !== PermissionTier::ADMIN_SAFE
            ) {
                throw new LogicException('The requested role contains unavailable or protected authority.');
            }
        }

        return $user;
    }
}
