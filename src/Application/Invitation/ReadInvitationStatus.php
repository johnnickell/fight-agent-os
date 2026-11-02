<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use App\Application\Authorization\AuthenticatedPermissions;
use App\Application\Failure\ResourceNotFound;
use App\Domain\Invitation\InvitationOperation;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use LogicException;

/**
 * Class ReadInvitationStatus
 *
 * Authorizes and limits safe package status reads without consumer delivery state
 */
final readonly class ReadInvitationStatus
{
    /**
     * Constructs ReadInvitationStatus
     */
    public function __construct(
        private AuthenticatedPermissions $authority,
        private InvitationRateLimiter $limiter,
        private QueryBus $queries
    ) {
    }

    /**
     * Composes bounded package target and delivery observations after exact read authorization
     */
    public function read(AuthenticationContext $authentication, UserId $userId): InvitationStatus
    {
        $actor = $this->authority->require($authentication, [PermissionName::fromString('READ_USERS')]);
        $this->limiter->consume($actor, InvitationOperation::STATUS);
        $user = $this->queries->fetch(new GetUserById($userId));
        if ($user === null) {
            throw new ResourceNotFound();
        }
        if (!$user instanceof UserView || !$user->getUserId()->equals($userId)) {
            throw new LogicException('Invitation target is unavailable.');
        }
        $view = $this->queries->fetch(new FindInvitationDeliveryStatus($userId));
        if (
            $view !== null
            && (!$view instanceof InvitationDeliveryStatusView || !$view->getUserId()->equals($userId))
        ) {
            throw new LogicException('Invitation status is unavailable.');
        }

        return new InvitationStatus($user, $view);
    }
}
