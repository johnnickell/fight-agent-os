<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Invitations;

use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ReadInvitationStatusResponder
 *
 * Presents the package credential-free status contract directly
 */
#[OA\Schema(
    schema: 'InvitationDelivery',
    required: ['user_id', 'status', 'expires_at', 'activation_delivery_id'],
    properties: [
        new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: ['pending', 'claimed', 'retry_pending', 'delivered', 'permanent_failure', 'expired', 'invalidated']
        ),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'activation_delivery_id', type: 'string', format: 'uuid')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'InvitationStatusSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/InvitationDelivery')
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class ReadInvitationStatusResponder
{
    /**
     * Constructs ReadInvitationStatusResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns no provider diagnostics, credential material or inferred receipt
     */
    public function respond(InvitationDeliveryStatusView $view): ResponseInterface
    {
        return $this->responses->fromEnvelope(JSendEnvelope::success($view), 200, ['Cache-Control' => 'no-store']);
    }
}
