<?php

declare(strict_types=1);

namespace App\Application\Workspace;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Failure\PermissionDenied;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;

/**
 * Class RegistrationAccess
 *
 * Combines independent operation permissions with the currently implemented initial-Workspace access path
 */
final readonly class RegistrationAccess
{
    public const string VIEW = 'READ_REPOSITORY_REGISTRATIONS';
    public const string REGISTER = 'REGISTER_REPOSITORIES';
    public const string GENERATE = 'GENERATE_REFERENCE_IDS';

    /**
     * Constructs RegistrationAccess
     */
    public function __construct(
        private AuthenticatedPermissions $permissions,
        private RegistrationStore $store,
        private UserRepository $users,
        private RoleRepository $roles
    ) {
    }

    /**
     * Requires current initial scope and authoritative administrator access without granting a Permission
     *
     * TASK-00498 supplies Workspace Admin and explicit User/Agent Repository access later
     */
    public function require(
        AuthenticationContext $authentication,
        WorkspaceId $workspace,
        string $permission
    ): UserId {
        $actor = $this->permissions->require($authentication, [PermissionName::fromString($permission)]);
        $initial = $this->store->initialWorkspace();
        $roleId = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        $role = $this->roles->getById($roleId);
        $user = $this->users->getById($actor);
        if (
            $initial === null || !$initial->id->equals($workspace) || $user === null
            || $role === null || !$role->isManaged() || $role->getName()->toString() !== 'ROLE_SUPER_ADMIN'
            || !$user->hasRole($roleId)
        ) {
            throw new PermissionDenied();
        }

        return $actor;
    }
}
