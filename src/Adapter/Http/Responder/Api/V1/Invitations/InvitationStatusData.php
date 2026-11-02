<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Invitations;

use App\Application\Invitation\InvitationStatus;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class InvitationStatusData
 *
 * Projects only canonical identity, lifecycle and safe package delivery metadata
 */
final readonly class InvitationStatusData implements Arrayable
{
    /**
     * Constructs InvitationStatusData
     */
    public function __construct(private InvitationStatus $status)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'user_id'  => $this->status->user->getUserId()->toString(),
            'email'    => $this->status->user->getEmail()->canonical(),
            'state'    => $this->status->user->getState()->value,
            'delivery' => $this->status->delivery?->toArray()
        ];
    }
}
