<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Invitations;

use App\Application\Invitation\InvitationIssuance;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class InvitationIssuanceData
 *
 * Projects only inspected step evidence and the package-safe delivery View
 */
final readonly class InvitationIssuanceData implements Arrayable
{
    /**
     * Constructs InvitationIssuanceData
     */
    public function __construct(private InvitationIssuance $outcome)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'user_id'    => $this->outcome->userId?->toString(),
            'assignment' => $this->outcome->assignment,
            'audit'      => $this->outcome->auditConfirmed ? 'confirmed' : 'unconfirmed',
            'delivery'   => $this->outcome->delivery?->toArray()
        ];
    }
}
