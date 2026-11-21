<?php

declare(strict_types=1);

namespace App\Application\Authorization\CommandHandler;

use App\Application\Authorization\IncompleteMembership;
use App\Application\Authorization\InstallationAssignmentPolicy;
use App\Domain\Authorization\Command\AddUserRole;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Authorization\MembershipProgress;
use App\Domain\Authorization\MembershipState;
use App\Domain\Authorization\OperatorMembershipEvidence;
use Fight\AccessControl\Domain\AccessControl\Audit\SystemId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use Throwable;

/**
 * Class AddUserRoleHandler
 *
 * Adds eligible membership and separately records what this invocation confirmed
 */
final readonly class AddUserRoleHandler implements CommandHandler
{
    /**
     * Constructs AddUserRoleHandler
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
        return AddUserRole::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof AddUserRole) {
            throw new InvalidArgumentException('Unexpected role assignment command.');
        }
        $evidence = OperatorMembershipEvidence::membershipConfirmed(
            $command->operator,
            $command->userId,
            $command->roleId,
            $command->invocationId
        );
        try {
            $before = $this->assignmentPolicy->requireEligibleAssignment($command->userId, $command->roleId);
            $this->commandBus->execute(new AssignRoleToUser(SystemId::generate(), $command->userId, $command->roleId));
            $this->assignmentPolicy->requireAdditiveOutcome($before, $command->roleId);
            $this->transactionalUnitOfWork->commitTransactional(
                fn() => $this->membershipEvidenceRepository->add($evidence)
            );

            return;
        } catch (Throwable) {
            // A failed acknowledgement does not establish whether the transaction committed.
        }
        try {
            $state = $this->membershipProgress->inspect($evidence);
        } catch (Throwable) {
            $state = new MembershipState();
        }
        if (!$state->isComplete()) {
            throw new IncompleteMembership($state);
        }
    }
}
