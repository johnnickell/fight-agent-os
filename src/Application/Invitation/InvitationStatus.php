<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;

/**
 * Class InvitationStatus
 *
 * Composes package-owned target and delivery observations for authorized status presentation
 */
final readonly class InvitationStatus
{
    /**
     * Constructs InvitationStatus
     */
    public function __construct(public UserView $user, public ?InvitationDeliveryStatusView $delivery)
    {
    }
}
