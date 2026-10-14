<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class InviteOrdinaryUserHandler
 *
 * Coordinates installation policy, package behavior and required operator audit atomically
 */
final readonly class InviteOrdinaryUserHandler implements CommandHandler
{
    /**
     * Constructs InviteOrdinaryUserHandler
     */
    public function __construct(
        private InvitationTransaction $transaction,
        private QueryBus $queries,
        private CommandBus $commands,
        private AuditEvidenceRepository $audit
    ) {
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
        $userId = $command->userId;
        $this->transaction->commit(function () use ($input, $userId): void {
            InstallationManagedPolicy::requireReconciled($this->queries->fetch(
                new PreviewManagedPolicy(InstallationManagedPolicy::create())
            ));
            $this->commands->execute(new InvitePendingUser($input->actorId(), $userId, $input->email));
            $this->commands->execute(new AssignRoleToUser(
                $input->operatorId,
                $userId,
                RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)
            ));
            // A void bus may be vetoed or transformed: do not commit an incomplete/elevated workflow.
            $input->requireOrdinaryOutcome($this->queries->fetch(new GetUserById($userId)), $userId);
            $this->audit->add(AuditEvidence::record($input->actorId(), 'user.console_role_assigned', $userId));
        });
    }
}
