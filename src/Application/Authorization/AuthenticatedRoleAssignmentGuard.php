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
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Class AuthenticatedRoleAssignmentGuard
 *
 * Requires fresh authoritative permission for actual routed assignment payloads, never cached role names
 */
final readonly class AuthenticatedRoleAssignmentGuard implements CommandFilter
{
    /**
     * Constructs AuthenticatedRoleAssignmentGuard
     */
    public function __construct(
        private AuthenticationContextProvider $authentication,
        private AuthoritativePrincipalResolver $principals,
        private RoleRepository $roles
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $command = $commandMessage->payload();
        if ($command instanceof AssignRoleToUser) {
            try {
                // Resolve afresh for every mutation, not CurrentPrincipalProvider's earlier cached projection.
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
        }
        $next($commandMessage);
    }
}
