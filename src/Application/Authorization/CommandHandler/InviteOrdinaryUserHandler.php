<?php

declare(strict_types=1);

namespace App\Application\Authorization\CommandHandler;

use App\Application\Authorization\IncompleteMembership;
use App\Application\Authorization\InstallationAssignmentPolicy;
use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Authorization\MembershipProgress;
use App\Domain\Authorization\MembershipState;
use App\Domain\Authorization\OperatorMembershipEvidence;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use Fight\AccessControl\Domain\AccessControl\Audit\SystemId;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class InviteOrdinaryUserHandler
 *
 * Invites an ordinary user through separate origin, assignment and evidence commits
 */
final readonly class InviteOrdinaryUserHandler implements CommandHandler
{
    /**
     * Constructs InviteOrdinaryUserHandler
     */
    public function __construct(
        private CommandBus $commandBus,
        private InstallationAssignmentPolicy $assignmentPolicy,
        private MembershipProgress $membershipProgress,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private MembershipEvidenceRepository $membershipEvidenceRepository
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
            throw new InvalidArgumentException('Unexpected ordinary invitation command.');
        }
        $systemId = SystemId::generate();
        $roleId = RoleId::fromString(InstallationManagedPolicy::USER_ROLE_ID);
        $evidence = OperatorMembershipEvidence::membershipConfirmed(
            $command->operator,
            $command->userId,
            $roleId,
            $command->invocationId
        );
        try {
            $this->assignmentPolicy->requireInstallation();
            $this->commandBus->execute(new InvitePendingUser($systemId, $command->userId, $command->email));
            $this->assignmentPolicy->requireEligibleAssignment($command->userId, $roleId);
            $this->commandBus->execute(new AssignRoleToUser($systemId, $command->userId, $roleId));
            $user = $this->assignmentPolicy->requireUser($command->userId);
            if (
                $user->getEmail()->toString() !== $command->email->toString()
                || $user->getState() !== UserState::PENDING_ACTIVATION
                || array_map(static fn(RoleId $id): string => $id->toString(), $user->getRoleIds()) !== [
                    InstallationManagedPolicy::USER_ROLE_ID
                ]
            ) {
                throw new LogicException('The ordinary invitation outcome was not established.');
            }
            $this->transactionalUnitOfWork->commitTransactional(
                fn() => $this->membershipEvidenceRepository->add($evidence)
            );

            return;
        } catch (Throwable) {
            // Earlier package commits can survive any later failure.
        }
        try {
            $state = $this->membershipProgress->inspect($evidence, $command->email);
        } catch (Throwable) {
            $state = new MembershipState();
        }
        if (!$state->isComplete()) {
            throw new IncompleteMembership($state);
        }
    }
}
