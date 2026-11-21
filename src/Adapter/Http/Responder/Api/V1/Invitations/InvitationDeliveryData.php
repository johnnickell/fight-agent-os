<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Invitations;

use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class InvitationDeliveryData
 *
 * Projects safe package status and its activation origin
 */
final readonly class InvitationDeliveryData implements Arrayable
{
    /**
     * Constructs InvitationDeliveryData
     */
    public function __construct(private InvitationDeliveryStatusView $delivery)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'user_id'                => $this->delivery->getUserId()->toString(),
            'status'                 => $this->delivery->getStatus()->value,
            'expires_at'             => $this->delivery->getExpiresAt()->format(DATE_ATOM),
            'activation_delivery_id' => $this->delivery->getActivationDeliveryId()->toString(),
            'origin'                 => $this->delivery->getOrigin()->value
        ];
    }
}
