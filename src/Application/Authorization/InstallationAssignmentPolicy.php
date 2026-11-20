<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\ManagedPolicyCatalog;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\GetPermissionById;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use LogicException;
use UnexpectedValueException;

/**
 * Class InstallationAssignmentPolicy
 *
 * Qualifies current assignment references within the fixed installation catalog
 */
final readonly class InstallationAssignmentPolicy
{
    /**
     * Constructs InstallationAssignmentPolicy
     */
    public function __construct(private QueryBus $queryBus, private ManagedPolicyCatalog $managedPolicyCatalog)
    {
    }

    /**
     * Requires the complete version-controlled installation authority
     */
    public function requireInstallation(): void
    {
        $policy = $this->managedPolicyCatalog->load();
        $plan = $this->queryBus->fetch(new PreviewManagedPolicy($policy));
        if (!$plan instanceof ManagedPolicyPlan) {
            throw new UnexpectedValueException('Managed installation authority is unavailable.');
        }
        /** @var ManagedPolicyPlan $plan */
        InstallationManagedPolicy::requireReconciled($plan, $policy);
    }

    /**
     * Requires reconciled authority and safe existing target and role references
     */
    public function requireEligibleAssignment(UserId $userId, RoleId $roleId): UserView
    {
        $this->requireInstallation();
        $user = $this->requireUser($userId);
        $role = $this->queryBus->fetch(new GetRoleById($roleId));
        if (!$role instanceof RoleView) {
            throw new LogicException('The requested role is unavailable.');
        }
        /** @var RoleView $role */
        InstallationManagedPolicy::requireAdditiveRole($roleId, $role);
        foreach ($role->getPermissionIds() as $permissionId) {
            $permission = $this->queryBus->fetch(new GetPermissionById($permissionId));
            if (!$permission instanceof PermissionView) {
                throw new LogicException('The requested role contains unavailable or protected authority.');
            }
            /** @var PermissionView $permission */
            if (
                !$permission->getPermissionId()->equals($permissionId)
                || $permission->getTier() !== PermissionTier::ADMIN_SAFE
            ) {
                throw new LogicException('The requested role contains unavailable or protected authority.');
            }
        }

        return $user;
    }

    /**
     * Reads the required current target from the configured bus
     */
    public function requireUser(UserId $userId): UserView
    {
        $user = $this->queryBus->fetch(new GetUserById($userId));
        if (!$user instanceof UserView || !$user->getUserId()->equals($userId)) {
            throw new LogicException('The target user is unavailable.');
        }

        /** @var UserView $user */
        return $user;
    }

    /**
     * Requires the requested membership without dropping any previous membership
     */
    public function requireAdditiveOutcome(UserView $before, RoleId $roleId): void
    {
        $after = $this->requireUser($before->getUserId());
        $current = array_map(static fn(RoleId $id): string => $id->toString(), $after->getRoleIds());
        $required = array_map(static fn(RoleId $id): string => $id->toString(), [...$before->getRoleIds(), $roleId]);
        if (array_diff($required, $current) !== []) {
            throw new LogicException('Additive role membership was not established.');
        }
    }
}
