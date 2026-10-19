<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Authorization\InstallationAuthority;
use App\Application\Failure\InvitationRecoveryUncertain;
use App\Application\Failure\ResourceNotFound;
use App\Application\Failure\StateConflict;
use App\Domain\Invitation\InvitationOperation;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\ResendInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Command\RetryInvitationDelivery;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotResendableException;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Exception\ActivationDeliveryNotRetryableException;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\Command\CorrectPendingInvitation;
use Fight\AccessControl\Domain\AccessControl\User\Exception\PendingInvitationCorrectionException;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use LogicException;

/**
 * Class RecoverInvitation
 *
 * Authorizes and bounds recovery while leaving atomic transitions and delivery with the package
 */
final readonly class RecoverInvitation
{
    /**
     * Constructs RecoverInvitation
     */
    public function __construct(
        private AuthenticatedPermissions $authority,
        private InstallationAuthority $targets,
        private InvitationRateLimiter $limiter,
        private ActivationGrantRepository $grants,
        private CommandBus $commands,
        private QueryBus $queries
    ) {
    }

    /**
     * Requests retry of the existing generation through the qualified package command
     */
    public function retry(AuthenticationContext $authentication, UserId $target): InvitationDeliveryStatusView
    {
        $actor = $this->authorize($authentication, $target, InvitationOperation::RETRY);

        return $this->dispatch(new RetryInvitationDelivery($actor->toString(), $target), $target);
    }

    /**
     * Requests an atomic replacement invitation through the package command
     */
    public function resend(AuthenticationContext $authentication, UserId $target): InvitationDeliveryStatusView
    {
        $actor = $this->authorize($authentication, $target, InvitationOperation::RESEND);

        return $this->dispatch(new ResendInvitationDelivery($actor->toString(), $target), $target);
    }

    /**
     * Requests atomic pending-address correction with a canonical destination
     */
    public function correct(
        AuthenticationContext $authentication,
        UserId $target,
        EmailAddress $email
    ): InvitationDeliveryStatusView {
        $actor = $this->authorize($authentication, $target, InvitationOperation::CORRECT);

        return $this->dispatch(new CorrectPendingInvitation(
            $actor,
            $target,
            EmailAddress::fromString($email->canonical())
        ), $target);
    }

    /**
     * Checks current invocation and installation authority before admission or target observation
     */
    private function authorize(
        AuthenticationContext $authentication,
        UserId $target,
        InvitationOperation $operation
    ): UserId {
        $actor = $this->authority->require($authentication, [PermissionName::fromString('MANAGE_USER_INVITATIONS')]);
        $this->targets->assertInvitationPermission($actor, $target);
        $this->limiter->consume($actor, $operation, $target);

        return $actor;
    }

    /**
     * Dispatches without an outer transaction and verifies a state effect before presenting safe status
     */
    private function dispatch(Command $command, UserId $target): InvitationDeliveryStatusView
    {
        $before = $this->grants->getLatestByUserId($target);
        if ($before === null) {
            throw new ResourceNotFound();
        }
        try {
            $this->commands->execute($command);
        } catch (EventDispatchFailed) {
            throw new InvitationRecoveryUncertain();
        } catch (ActivationDeliveryNotRetryableException) {
            throw new StateConflict();
        } catch (ActivationDeliveryNotResendableException) {
            throw new StateConflict();
        } catch (PendingInvitationCorrectionException) {
            throw new StateConflict();
        } catch (LogicException) {
            throw new StateConflict();
        }
        // Observation is not an admission precheck or a consumer replacement of package invariants.
        $after = $this->grants->getLatestByUserId($target);
        if (
            $after === null || ($after->getId()->equals($before->getId())
            && $after->getRevision() === $before->getRevision())
        ) {
            throw new LogicException('Invitation recovery did not establish an observable effect.');
        }
        $sameGeneration = $after->getId()->equals($before->getId());
        if (($command instanceof RetryInvitationDelivery) !== $sameGeneration) {
            throw new InvitationRecoveryUncertain();
        }
        if ($command instanceof CorrectPendingInvitation) {
            $user = $this->queries->fetch(new GetUserById($target));
            if (
                !$user instanceof UserView || !$user->getUserId()->equals($target)
                || $user->getEmail()->canonical() !== $command->getEmail()->canonical()
            ) {
                throw new InvitationRecoveryUncertain();
            }
        }
        $view = $this->queries->fetch(new FindInvitationDeliveryStatus($target));
        if (!$view instanceof InvitationDeliveryStatusView || !$view->getUserId()->equals($target)) {
            throw new LogicException('Invitation recovery status is unavailable.');
        }

        return $view;
    }
}
