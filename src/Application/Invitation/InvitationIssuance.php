<?php

declare(strict_types=1);

namespace App\Application\Invitation;

use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;

/**
 * Class InvitationIssuance
 *
 * Reports inspected origin steps without claiming cross-step rollback or email receipt
 */
final readonly class InvitationIssuance
{
    /**
     * Constructs InvitationIssuance
     */
    public function __construct(
        public ?UserId $userId,
        public string $assignment,
        public bool $auditConfirmed,
        public ?InvitationDeliveryStatusView $delivery
    ) {
    }
}
