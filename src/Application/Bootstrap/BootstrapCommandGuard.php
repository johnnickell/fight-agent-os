<?php

declare(strict_types=1);

namespace App\Application\Bootstrap;

use App\Application\Invitation\ConsoleAssignmentPolicy;
use App\Application\Invitation\ConsoleCommandIntent;
use App\Domain\Bootstrap\Command\BootstrapSuperAdmin;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

/**
 * Class BootstrapCommandGuard
 *
 * Protects actual bootstrap payloads after configurable transformations and before package routing
 */
final readonly class BootstrapCommandGuard implements CommandFilter
{
    /**
     * Constructs BootstrapCommandGuard
     */
    public function __construct(
        private ConsoleCommandIntent $intent,
        private ConsoleAssignmentPolicy $policy,
        private BootstrapClaimScope $scope
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $command = $commandMessage->payload();
        if (
            $command instanceof BootstrapSuperAdmin || $command instanceof ReconcileManagedPolicy
            || $command instanceof InvitePendingUser || $command instanceof AssignRoleToUser
        ) {
            $this->intent->authorize($command);
            if ($command instanceof InvitePendingUser) {
                $this->scope->authorizeInvitation($command);
                $this->policy->requireInstallation();
            }
            if ($command instanceof AssignRoleToUser) {
                $this->scope->authorizeAssignment($command);
                $this->policy->requireInstallation();
            }
        }
        $next($commandMessage);
    }
}
