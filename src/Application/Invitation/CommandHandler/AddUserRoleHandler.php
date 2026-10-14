<?php

declare(strict_types=1);

namespace App\Application\Invitation\CommandHandler;

use App\Application\Invitation\ConsoleUserOperation;
use App\Domain\Invitation\Command\AddUserRole;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class AddUserRoleHandler
 *
 * Adds eligible existing membership and records an explicitly separate console confirmation
 */
final readonly class AddUserRoleHandler implements CommandHandler
{
    /**
     * Constructs AddUserRoleHandler
     */
    public function __construct(private ConsoleUserOperation $operation)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return AddUserRole::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof AddUserRole) {
            throw new InvalidArgumentException('Unexpected console role addition.');
        }
        $input = $command->input;
        $before = $this->operation->requireEligibleAssignment($input);
        $this->operation->dispatch(new AssignRoleToUser($input->operatorId, $input->userId, $input->roleId));
        $this->operation->confirmAndAudit($input, $before);
    }
}
