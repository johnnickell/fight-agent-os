<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Invitations;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class InvitationMutationResponder
 *
 * Acknowledges completed dispatch without disclosing user or delivery data
 */
#[OA\Schema(
    schema: 'InvitationMutationSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', nullable: true, enum: [null])
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class InvitationMutationResponder
{
    /**
     * Constructs InvitationMutationResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns an empty acknowledgement rather than inferring the current outcome
     */
    public function respond(): ResponseInterface
    {
        return $this->responses->fromEnvelope(JSendEnvelope::success(null), 200, ['Cache-Control' => 'no-store']);
    }
}
