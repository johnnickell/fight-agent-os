<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\AddUserRole;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleRoleEvidence;
use App\Domain\Invitation\ConsoleRoleInput;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use LogicException;
use Throwable;

/**
 * Class ConsoleUserOperation
 *
 * Coordinates trusted operator policy, independent package steps and truthful partial outcomes
 */
final readonly class ConsoleUserOperation
{
    /**
     * Constructs ConsoleUserOperation
     */
    public function __construct(
        private CommandBus $commands,
        private QueryBus $queries,
        private TransactionalUnitOfWork $transactions,
        private AuditEvidenceRepository $audit,
        private ConsoleAssignmentPolicy $policy
    ) {
    }

    /**
     * Executes an operator request and inspects retained state after any possible postcommit failure
     */
    public function run(InviteOrdinaryUser|AddUserRole $command): ConsoleUserOutcome
    {
        $auditConfirmed = false;
        $userId = $command instanceof InviteOrdinaryUser ? $command->userId : $command->input->userId;
        $input = $command instanceof AddUserRole ? $command->input : new ConsoleRoleInput(
            $command->input->operatorId->toString(),
            $userId->toString(),
            InstallationManagedPolicy::USER_ROLE_ID
        );
        try {
            $this->policy->requireInstallation();
            if ($command instanceof InviteOrdinaryUser) {
                $this->commands->execute(new InvitePendingUser(
                    $command->input->actorId(),
                    $userId,
                    $command->input->email
                ));
            }
            $before = $this->policy->requireEligibleAssignment($input);
            $this->commands->execute(new AssignRoleToUser($input->operatorId, $userId, $input->roleId));
            $this->confirmAndAudit($input, $before);
            $auditConfirmed = true;
        } catch (Throwable) {
            // Package exceptions can follow a commit; inspect state without exposing private diagnostics.
        }
        try {
            $view = $this->queries->fetch(new GetUserById($userId));
            if (
                !$view instanceof UserView || !$view->getUserId()->equals($userId)
                || ($command instanceof InviteOrdinaryUser && (
                    $view->getEmail()->toString() !== $command->input->email->toString()
                    || $view->getState() !== UserState::PENDING_ACTIVATION
                ))
            ) {
                return new ConsoleUserOutcome(false, null, 'unknown', $auditConfirmed ? 'confirmed' : 'unconfirmed');
            }
            if ($command instanceof InviteOrdinaryUser && $auditConfirmed) {
                $command->input->requireOrdinaryOutcome($view, $userId);
            }
            $assigned = in_array($input->roleId, $view->getRoleIds());

            return new ConsoleUserOutcome(
                $auditConfirmed && $assigned,
                $userId,
                $assigned ? 'confirmed' : 'missing',
                $auditConfirmed ? 'confirmed' : 'unconfirmed'
            );
        } catch (Throwable) {
            return new ConsoleUserOutcome(false, null, 'unknown', $auditConfirmed ? 'confirmed' : 'unconfirmed');
        }
    }

    /**
     * Validates additive membership and separately commits evidence for this invocation, including repeats
     */
    private function confirmAndAudit(ConsoleRoleInput $input, UserView $before): void
    {
        $after = $this->queries->fetch(new GetUserById($input->userId));
        if (!$after instanceof UserView || !$after->getUserId()->equals($input->userId)) {
            throw new LogicException('Role membership is uncertain.');
        }
        $roles = array_map(static fn($role): string => $role->toString(), $after->getRoleIds());
        $required = [...array_map(static fn($role): string => $role->toString(), $before->getRoleIds()),
            $input->roleId->toString()];
        if (array_diff($required, $roles) !== []) {
            throw new LogicException('Additive role membership was not established.');
        }
        // This is deliberately not the package assignment transaction: a crash can leave membership without evidence.
        $this->transactions->commitTransactional(
            fn() => $this->audit->add(ConsoleRoleEvidence::membershipConfirmed($input))
        );
    }
}
