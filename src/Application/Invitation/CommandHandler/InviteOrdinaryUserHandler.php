<?php

declare(strict_types=1);

namespace App\Application\Invitation\CommandHandler;

use App\Application\Invitation\ConsoleUserOperation;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleRoleInput;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class InviteOrdinaryUserHandler
 *
 * Sequences independent package commits and separately committed operator evidence
 */
final readonly class InviteOrdinaryUserHandler implements CommandHandler
{
    /**
     * Constructs InviteOrdinaryUserHandler
     */
    public function __construct(private ConsoleUserOperation $operation)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return InviteOrdinaryUser::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof InviteOrdinaryUser) {
            throw new InvalidArgumentException('Unexpected ordinary invitation.');
        }
        $input = $command->input;
        $this->operation->dispatch(new InvitePendingUser($input->actorId(), $command->userId, $input->email));
        $role = new ConsoleRoleInput(
            $input->operatorId->toString(),
            $command->userId->toString(),
            InstallationManagedPolicy::USER_ROLE_ID
        );
        $before = $this->operation->requireEligibleAssignment($role);
        $this->operation->dispatch(new AssignRoleToUser($role->operatorId, $role->userId, $role->roleId));
        $this->operation->confirmAndAudit($role, $before);
    }
}
