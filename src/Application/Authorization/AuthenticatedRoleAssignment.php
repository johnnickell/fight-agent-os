<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Failure\PermissionDenied;
use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContextProvider;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthoritativePrincipalResolver;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\Role;
use Fight\AccessControl\Domain\AccessControl\Role\RoleRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Throwable;

/**
 * Class AuthenticatedRoleAssignment
 *
 * Authorizes each authenticated assignment against fresh authority before trusted package dispatch
 */
final readonly class AuthenticatedRoleAssignment
{
    /**
     * Constructs AuthenticatedRoleAssignment
     */
    public function __construct(
        private AuthenticationContextProvider $authentication,
        private AuthoritativePrincipalResolver $principals,
        private RoleRepository $roles,
        private CommandBus $commands
    ) {
    }

    /**
     * Requires exact current actor and permissions without treating raw package dispatch as authentication
     */
    public function assign(AssignRoleToUser $command): void
    {
        try {
            $principal = $this->principals->resolve($this->authentication->getAuthenticationContext());
            $role = $this->roles->getById($command->getRoleId());
            if (
                !$principal->getUserId()->equals($command->getActorId())
                || $role === null || !$role->getId()->equals($command->getRoleId())
                || !$principal->hasPermission(PermissionName::fromString('MANAGE_USER_ROLES'))
                || (($command->getRoleId()->toString() === InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID
                    || $role->getName()->toString() === Role::SUPER_ADMIN_NAME)
                    && !$principal->hasPermission(PermissionName::fromString('ASSIGN_SUPER_ADMIN')))
            ) {
                throw new PermissionDenied();
            }
        } catch (Throwable) {
            throw new PermissionDenied();
        }
        $this->commands->execute($command);
    }
}
