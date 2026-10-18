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
     * Returns only the qualified package delivery View for the authorized target
     */
    public function read(AuthenticationContext $authentication, UserId $userId): InvitationDeliveryStatusView
    {
        $actor = $this->authority->require($authentication, [PermissionName::fromString('MANAGE_USER_INVITATIONS')]);
        $this->limiter->consume($actor, InvitationOperation::STATUS);
        $view = $this->queries->fetch(new FindInvitationDeliveryStatus($userId));
        if ($view === null) {
            throw new ResourceNotFound();
        }
        if (!$view instanceof InvitationDeliveryStatusView || !$view->getUserId()->equals($userId)) {
            throw new LogicException('Invitation status is unavailable.');
        }

        return $view;
    }
}
