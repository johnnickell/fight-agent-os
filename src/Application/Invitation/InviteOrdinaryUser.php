<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\ConsoleInvitationInput;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

/**
 * Class InviteOrdinaryUser
 *
 * Coordinates trusted ordinary invitations through public package behavior
 */
final readonly class InviteOrdinaryUser
{
    /**
     * Constructs InviteOrdinaryUser
     */
    public function __construct(
        private InvitationTransaction $transaction,
        private QueryBus $queries,
        private CommandHandler $invitePendingUser,
        private CommandHandler $assignRoleToUser,
        private AuditEvidenceRepository $audit
    ) {
    }

    /**
     * Creates a pending ordinary identity without selecting arbitrary authority
     */
    public function invite(ConsoleInvitationInput $input): UserId
    {
        $userId = UserId::generate();
        $this->transaction->commit(function () use ($input, $userId): void {
            InstallationManagedPolicy::requireReconciled($this->queries->fetch(
                new PreviewManagedPolicy(InstallationManagedPolicy::create())
            ));
            $this->invitePendingUser->handle(CommandMessage::create(new InvitePendingUser(
                $input->actorId(),
                $userId,
                $input->email
            )));
            $this->assignRoleToUser->handle(CommandMessage::create(new AssignRoleToUser(
                $input->operatorId,
                $userId,
                RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID)
            )));
            $this->audit->add(AuditEvidence::record($input->actorId(), 'user.console_role_assigned', $userId));
        });

        return $userId;
    }
}
