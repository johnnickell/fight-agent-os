<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\V1\Projects;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Workspace\Workspace\Query\FindInitialWorkspace;
use App\Domain\Workspace\Workspace\WorkspaceId;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Messaging\Query\QueryBus;

/**
 * Class ProjectWorkspaceAdmission
 *
 * Admits the implemented HTTP Workspace scope after central operation permission admission
 */
final readonly class ProjectWorkspaceAdmission
{
    /**
     * Constructs ProjectWorkspaceAdmission
     */
    public function __construct(
        private QueryBus $queryBus,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository
    ) {
    }

    /**
     * Requires current initial scope and managed administrator membership without a second permission gate
     */
    public function require(AuthenticationContext $authentication, WorkspaceId $workspaceId): UserId
    {
        $initial = $this->queryBus->fetch(new FindInitialWorkspace());
        $roleId = RoleId::fromString(InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID);
        $role = $this->roleRepository->getById($roleId);
        $user = $this->userRepository->getById($authentication->getUserId());
        if (
            $initial === null || !$initial->id->equals($workspaceId) || $user === null
            || $role === null || !$role->isManaged() || $role->getName()->toString() !== 'ROLE_SUPER_ADMIN'
            || !$user->hasRole($roleId)
        ) {
            throw new PermissionDenied();
        }

        return $authentication->getUserId();
    }
}
