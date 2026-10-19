<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionTier;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;

/**
 * Class InstallationAuthority
 *
 * Checks current installation-wide human authority before package dispatch
 */
final readonly class InstallationAuthority
{
    /**
     * Constructs InstallationAuthority
     */
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private PermissionRepository $permissions
    ) {
    }

    /**
     * Requires exact current invitation permission without granting authority from a Role name
     */
    public function assertInvitationPermission(UserId $actorId, UserId $targetId): void
    {
        $actor = $this->users->getById($actorId);
        if ($actor === null || !$actor->getId()->equals($actorId) || $actor->getState() !== UserState::ACTIVE) {
            throw new PermissionDenied();
        }
        $expected = [];
        foreach ($actor->getRoleIds() as $id) {
            $expected[$id->toString()] = true;
        }
        $permissionIds = [];
        $designated = $this->roles->getByName(RoleName::fromString(Role::SUPER_ADMIN_NAME));
        foreach ($this->roles->getByIds($actor->getRoleIds()) as $role) {
            $key = $role->getId()->toString();
            if (!isset($expected[$key])) {
                throw new PermissionDenied();
            }
            unset($expected[$key]);
            $role->assertConsistentWithSuperAdmin($designated);
            foreach ($role->getPermissionIds() as $id) {
                $permissionIds[$id->toString()] = $id;
            }
        }
        if ($expected !== []) {
            throw new PermissionDenied();
        }
        $allowed = false;
        foreach ($this->permissions->getByIds(array_values($permissionIds)) as $permission) {
            $key = $permission->getId()->toString();
            if (!isset($permissionIds[$key])) {
                throw new PermissionDenied();
            }
            unset($permissionIds[$key]);
            $allowed = $allowed || $permission->getName()->toString() === 'MANAGE_USER_INVITATIONS';
        }
        if ($permissionIds !== [] || !$allowed) {
            throw new PermissionDenied();
        }
        $target = $this->users->getById($targetId);
        if ($target === null || !$target->getId()->equals($targetId)) {
            throw new PermissionDenied();
        }
    }

    /**
     * Requires an active actor with the current protected permission and an installed target
     *
     * A target may be pending or disabled for administrative operations. Package handlers own
     * target lifecycle and intrinsic Role/Permission mutation rules; this decision does not
     * replace their actor/target callbacks or an entry adapter's intent-specific check.
     */
    public function assertInstallationPermission(
        UserId $actorId,
        PermissionName $required,
        ?UserId $targetId = null
    ): void {
        $actor = $this->users->getById($actorId);
        if (
            $actor === null
            || !$actor->getId()->equals($actorId)
            || $actor->getState() !== UserState::ACTIVE
        ) {
            throw new PermissionDenied('The current actor cannot administer this installation.');
        }

        $permission = $this->permissions->getByName($required);
        if (
            $permission === null
            || !$permission->getName()->equals($required)
            || !$permission->isManaged()
            || $permission->getTier() !== PermissionTier::SUPER_ADMIN_ONLY
        ) {
            throw new PermissionDenied('The installation permission is unavailable.');
        }

        $managedRole = $this->roles->getByName(RoleName::fromString(Role::SUPER_ADMIN_NAME));
        if (
            $managedRole === null
            || !$managedRole->isManaged()
            || $managedRole->getName()->toString() !== Role::SUPER_ADMIN_NAME
            || !$actor->hasRole($managedRole->getId())
            || !$managedRole->hasPermission($permission->getId())
        ) {
            throw new PermissionDenied('The actor lacks the installation permission.');
        }

        if ($targetId !== null) {
            $target = $this->users->getById($targetId);
            if ($target === null || !$target->getId()->equals($targetId)) {
                throw new PermissionDenied('The target is outside the installation.');
            }
        }
    }
}
