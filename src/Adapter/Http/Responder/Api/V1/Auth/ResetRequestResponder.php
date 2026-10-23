<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ResetRequestResponder
 *
 * Returns one public outcome for every admitted identity state without setting cookies
 */
#[OA\Schema(
    schema: 'ResetRequestAccepted',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            required: ['accepted'],
            properties: [new OA\Property(property: 'accepted', type: 'boolean', enum: [true])],
            type: 'object',
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class ResetRequestResponder
{
    /**
     * Constructs ResetRequestResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns acknowledgement without confirming eligibility or delivery
     */
    public function respond(): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new ResetRequestData()),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
