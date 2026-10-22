<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Invitations;

use App\Application\Invitation\InvitationIssuance;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class IssueInvitationResponder
 *
 * Presents confirmed origin without promising delivery receipt or cross-step atomicity
 */
#[OA\Schema(
    schema: 'InvitationIssuanceData',
    required: ['user_id', 'assignment', 'audit', 'delivery'],
    properties: [
        new OA\Property(property: 'user_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'assignment', type: 'string', enum: ['confirmed', 'unconfirmed', 'unknown']),
        new OA\Property(property: 'audit', type: 'string', enum: ['confirmed', 'unconfirmed']),
        new OA\Property(
            property: 'delivery',
            required: ['user_id', 'status', 'expires_at', 'activation_delivery_id'],
            properties: [
                new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
                new OA\Property(
                    property: 'status',
                    type: 'string',
                    enum: [
                        'pending',
                        'claimed',
                        'retry_pending',
                        'delivered',
                        'permanent_failure',
                        'expired',
                        'invalidated'
                    ]
                ),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'activation_delivery_id', type: 'string', format: 'uuid')
            ],
            type: 'object',
            nullable: true,
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'InvitationIssued',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/InvitationIssuanceData')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Schema(
    schema: 'InvitationIncomplete',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['fail']),
        new OA\Property(property: 'data', ref: '#/components/schemas/InvitationIssuanceData')
    ],
    type: 'object',
    additionalProperties: false
)]
#[OA\Response(
    response: 'InvitationThrottled',
    description: 'Atomic actor/action budget or bounded key capacity exhausted; permission checks run first',
    headers: [
        new OA\Header(
            header: 'Retry-After',
            description: 'Whole seconds until the next admission window, bounded 1..86400',
            schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 86400)
        ),
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final readonly class IssueInvitationResponder
{
    /**
     * Constructs IssueInvitationResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns only safe origin evidence and package lifecycle
     */
    public function respond(InvitationIssuance $outcome): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new InvitationIssuanceData($outcome)),
            201,
            ['Cache-Control' => 'no-store']
        );
    }
}
