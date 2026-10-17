<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Failure\IncompleteInvitation;
use App\Application\Failure\StateConflict;
use App\Domain\Invitation\InvitationMode;
use App\Domain\Invitation\InvitationOperation;
use App\Domain\Invitation\InvitationRoleEvidence;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\Audit\AuditEvidenceRepository;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\DuplicateEmailException;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;
use Throwable;

/**
 * Class IssueInvitation
 *
 * Extends the accepted independent package-step invitation contract to authenticated callers
 */
final readonly class IssueInvitation
{
    /**
     * Constructs IssueInvitation
     */
    public function __construct(
        private AuthenticatedPermissions $authority,
        private InvitationRateLimiter $limiter,
        private ConsoleAssignmentPolicy $policy,
        private UserRepository $users,
        private CommandBus $commands,
        private QueryBus $queries,
        private TransactionalUnitOfWork $transactions,
        private AuditEvidenceRepository $audit
    ) {
    }

    /**
     * Issues one managed invitation with truthful retained-state failure reporting
     */
    public function issue(
        AuthenticationContext $authentication,
        EmailAddress $email,
        InvitationMode $mode,
        bool $confirmed
    ): InvitationIssuance {
        $permissions = [PermissionName::fromString('INVITE_USERS')];
        if ($mode === InvitationMode::SUPER_ADMIN) {
            $permissions[] = PermissionName::fromString('ASSIGN_SUPER_ADMIN');
        }
        $actor = $this->authority->require($authentication, $permissions);
        $mode->requireConfirmation($confirmed);
        $this->limiter->consume($actor, InvitationOperation::ISSUE);
        try {
            $this->policy->requireInstallation();
        } catch (LogicException) {
            throw new StateConflict();
        }
        $email = EmailAddress::fromString($email->canonical());
        if ($this->users->getByEmail($email) !== null) {
            throw new StateConflict();
        }
        $userId = UserId::generate();
        $auditConfirmed = false;
        try {
            $this->commands->execute(new InvitePendingUser($actor->toString(), $userId, $email));
            // Delivery may already have started. Recheck current authority before the independent assignment.
            $this->authority->require($authentication, $permissions);
            $this->policy->requireInstallation();
            $this->commands->execute(new AssignRoleToUser($actor, $userId, $mode->roleId()));
            $beforeAudit = $this->inspect($userId, $email, $mode, false);
            if ($beforeAudit->assignment !== 'confirmed') {
                throw new LogicException('Invitation membership is unconfirmed.');
            }
            // Separate commit, like TASK-00037: a crash can retain membership without this evidence.
            $this->transactions->commitTransactional(fn() => $this->audit->add(
                InvitationRoleEvidence::confirmed($actor, $userId, $mode)
            ));
            $auditConfirmed = true;
        } catch (DuplicateEmailException) {
            // The package origin transaction rejected a concurrent canonical-email claimant.
            throw new StateConflict();
        } catch (Throwable) {
            // An exception does not establish rollback. Never retry or compensate an uncertain mutation.
        }
        $outcome = $this->inspect($userId, $email, $mode, $auditConfirmed);
        if (!$outcome->auditConfirmed || $outcome->assignment !== 'confirmed' || $outcome->delivery === null) {
            throw new IncompleteInvitation($outcome);
        }

        return $outcome;
    }

    /**
     * Reads safe package Views on the still-usable read connection even after a failed write manager
     */
    private function inspect(
        UserId $userId,
        EmailAddress $email,
        InvitationMode $mode,
        bool $auditConfirmed
    ): InvitationIssuance {
        try {
            $user = $this->queries->fetch(new GetUserById($userId));
            if (
                !$user instanceof UserView || !$user->getUserId()->equals($userId)
                || $user->getState() !== UserState::PENDING_ACTIVATION
                || $user->getEmail()->canonical() !== $email->canonical()
            ) {
                return new InvitationIssuance(null, 'unknown', $auditConfirmed, null);
            }
            $roles = $user->getRoleIds();
            $assigned = count($roles) === 1 && $roles[0]->equals($mode->roleId());
            try {
                $delivery = $this->queries->fetch(new FindInvitationDeliveryStatus($userId));
            } catch (Throwable) {
                $delivery = null;
            }
            if (!$delivery instanceof InvitationDeliveryStatusView || !$delivery->getUserId()->equals($userId)) {
                $delivery = null;
            }

            return new InvitationIssuance($userId, $assigned ? 'confirmed' : 'unconfirmed', $auditConfirmed, $delivery);
        } catch (Throwable) {
            return new InvitationIssuance(null, 'unknown', $auditConfirmed, null);
        }
    }
}
