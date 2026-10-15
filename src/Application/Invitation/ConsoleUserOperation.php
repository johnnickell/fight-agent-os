<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Domain\Authorization\InstallationManagedPolicy;
use App\Domain\Invitation\Command\AddUserRole;
use App\Domain\Invitation\Command\InviteOrdinaryUser;
use App\Domain\Invitation\ConsoleRoleEvidence;
use App\Domain\Invitation\ConsoleRoleInput;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\Command;
use LogicException;
use Throwable;

/**
 * Class ConsoleUserOperation
 *
 * Scopes intended payloads, mandatory evidence and truthful partial outcomes per invocation
 */
final class ConsoleUserOperation
{
    private bool $active = false;
    private bool $auditConfirmed = false;

    /**
     * Constructs ConsoleUserOperation
     */
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly TransactionalUnitOfWork $transactions,
        private readonly AuditEvidenceRepository $audit,
        private readonly ConsoleCommandIntent $intent,
        private readonly ConsoleAssignmentPolicy $policy
    ) {
    }

    /**
     * Executes one intended operation and inspects authoritative state after any possible postcommit failure
     */
    public function run(InviteOrdinaryUser|AddUserRole $command): ConsoleUserOutcome
    {
        if ($this->active) {
            throw new LogicException('Console user operations cannot overlap.');
        }
        $this->active = true;
        $this->auditConfirmed = false;
        $success = false;
        try {
            $this->dispatch($command);
            $success = $this->auditConfirmed;
        } catch (Throwable) {
            // Package exceptions can follow a commit; inspect state without exposing private diagnostics.
        }
        try {
            $userId = $command instanceof InviteOrdinaryUser ? $command->userId : $command->input->userId;
            $roleId = InstallationManagedPolicy::USER_ROLE_ID;
            if ($command instanceof AddUserRole) {
                $roleId = $command->input->roleId->toString();
            }
            $view = $this->queries->fetch(new GetUserById($userId));
            if (
                !$view instanceof UserView || !$view->getUserId()->equals($userId)
                || ($command instanceof InviteOrdinaryUser && (
                    $view->getEmail()->toString() !== $command->input->email->toString()
                    || $view->getState() !== UserState::PENDING_ACTIVATION
                ))
            ) {
                return new ConsoleUserOutcome(
                    false,
                    null,
                    'unknown',
                    $this->auditConfirmed ? 'confirmed' : 'unconfirmed'
                );
            }
            $roles = array_map(static fn($role): string => $role->toString(), $view->getRoleIds());
            if ($command instanceof InviteOrdinaryUser && $success) {
                $command->input->requireOrdinaryOutcome($view, $userId);
            }
            $assigned = in_array($roleId, $roles, true);

            return new ConsoleUserOutcome(
                $success && $assigned,
                $userId,
                $assigned ? 'confirmed' : 'missing',
                $this->auditConfirmed ? 'confirmed' : 'unconfirmed'
            );
        } catch (Throwable) {
            return new ConsoleUserOutcome(false, null, 'unknown', $this->auditConfirmed ? 'confirmed' : 'unconfirmed');
        } finally {
            $this->active = false;
            $this->auditConfirmed = false;
        }
    }

    /**
     * Dispatches through configured filters while retaining the intended payload outside transformations
     */
    public function dispatch(Command $command): void
    {
        if (!$this->active) {
            throw new LogicException('A scoped console invocation is required.');
        }
        $this->intent->dispatch($this->commands, $command);
    }

    /**
     * Requires exact installation authority and safe existing target and role references
     */
    public function requireEligibleAssignment(ConsoleRoleInput $input): UserView
    {
        return $this->policy->requireEligibleAssignment($input);
    }

    /**
     * Validates additive membership and separately commits evidence for this invocation, including repeats
     */
    public function confirmAndAudit(ConsoleRoleInput $input, UserView $before): void
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
        $this->auditConfirmed = true;
    }
}
