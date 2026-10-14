<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Invitation\Command\AddUserRole;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleRoleInput;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

/**
 * Class ConsoleUserCommandGuard
 *
 * Enforces scoped console policy after configurable transformations and before routing
 */
final readonly class ConsoleUserCommandGuard implements CommandFilter
{
    /**
     * Constructs ConsoleUserCommandGuard
     */
    public function __construct(private ConsoleCommandIntent $intent, private ConsoleAssignmentPolicy $policy)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $command = $commandMessage->payload();
        if (
            $command instanceof InviteOrdinaryUser || $command instanceof AddUserRole
            || $command instanceof InvitePendingUser || $command instanceof AssignRoleToUser
        ) {
            $this->intent->authorize($command);
            if ($command instanceof InviteOrdinaryUser || $command instanceof InvitePendingUser) {
                $this->policy->requireInstallation();
            }
            if ($command instanceof AssignRoleToUser) {
                $this->policy->requireEligibleAssignment(new ConsoleRoleInput(
                    $command->getActorId()->toString(),
                    $command->getTargetUserId()->toString(),
                    $command->getRoleId()->toString()
                ));
            }
        }
        $next($commandMessage);
    }
}
