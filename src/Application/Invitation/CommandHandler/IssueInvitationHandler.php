<?php

declare(strict_types=1);

namespace App\Application\Invitation\CommandHandler;

use App\Application\Failure\IncompleteInvitation;
use App\Application\Invitation\InvitationIssuance;
use App\Domain\Authorization\MembershipEvidenceRepository;
use App\Domain\Invitation\Command\IssueInvitation;
use App\Domain\Invitation\InvitationRoleEvidence;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\CredentialDelivery\Query\CredentialDeliveryAbsent;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Command\InvitePendingUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\DuplicateEmailException;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class IssueInvitationHandler
 *
 * Coordinates independent invitation origin, role assignment and owned audit
 */
final readonly class IssueInvitationHandler implements CommandHandler
{
    /**
     * Constructs IssueInvitationHandler
     */
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private MembershipEvidenceRepository $membershipEvidenceRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return IssueInvitation::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $intent = $commandMessage->payload();
        if (!$intent instanceof IssueInvitation) {
            throw new InvalidArgumentException('Unexpected invitation command.');
        }
        try {
            $this->commandBus->execute(new InvitePendingUser($intent->actorId, $intent->userId, $intent->email));
        } catch (Throwable $failure) {
            if ($failure instanceof StateConflict && $failure->getPrevious() instanceof DuplicateEmailException) {
                throw new StateConflict();
            }
            throw new IncompleteInvitation($this->inspect($intent));
        }
        try {
            $this->commandBus->execute(new AssignRoleToUser(
                $intent->actorId,
                $intent->userId,
                $intent->mode->roleId()
            ));
            $user = $this->findPendingTarget($intent);
            if ($user === null || !$this->hasExpectedMembership($user, $intent)) {
                throw new LogicException('Invitation membership is unconfirmed.');
            }
            $evidence = InvitationRoleEvidence::confirmed($intent->actorId, $intent->userId, $intent->mode);
            $this->transactionalUnitOfWork->commitTransactional(fn() => $this->membershipEvidenceRepository->add(
                $evidence
            ));
        } catch (Throwable) {
            // Earlier package commits can survive. Never retry or compensate an uncertain mutation.
            throw new IncompleteInvitation($this->inspect($intent));
        }
    }

    /**
     * Reads retained origin after a failure without assuming rollback
     */
    private function inspect(IssueInvitation $intent): InvitationIssuance
    {
        try {
            $user = $this->findPendingTarget($intent);
        } catch (Throwable) {
            $user = null;
        }
        if ($user === null) {
            return new InvitationIssuance(null, 'unknown', false, null);
        }
        try {
            /** @var InvitationDeliveryStatusView|CredentialDeliveryAbsent $status */
            $status = $this->queryBus->fetch(new FindInvitationDeliveryStatus($intent->userId));
            $delivery = $status instanceof CredentialDeliveryAbsent ? null : $status;
        } catch (Throwable) {
            $delivery = null;
        }

        return new InvitationIssuance(
            $intent->userId,
            $this->hasExpectedMembership($user, $intent) ? 'confirmed' : 'unconfirmed',
            false,
            $delivery
        );
    }

    /**
     * Returns the pending target that still matches this issuance
     */
    private function findPendingTarget(IssueInvitation $intent): ?UserView
    {
        /** @var UserView $user */
        $user = $this->queryBus->fetch(new GetUserById($intent->userId));
        if (
            $user->getState() !== UserState::PENDING_ACTIVATION
            || $user->getEmail()->canonical() !== $intent->email->canonical()
        ) {
            return null;
        }

        return $user;
    }

    /**
     * Checks that the separate assignment established the intended exact membership
     */
    private function hasExpectedMembership(UserView $user, IssueInvitation $intent): bool
    {
        $roles = $user->getRoleIds();

        return count($roles) === 1 && $roles[0]->equals($intent->mode->roleId());
    }
}
