<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Users;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class UserLifecycleResponder
 *
 * Acknowledges lifecycle dispatch without granting user-detail read authority
 */
#[OA\Schema(
    schema: 'UserLifecycleSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', nullable: true, enum: [null])
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class UserLifecycleResponder
{
    /**
     * Constructs UserLifecycleResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns a no-store acknowledgement without querying a void command outcome
     */
    public function respond(): ResponseInterface
    {
        return $this->responses->fromEnvelope(JSendEnvelope::success(null), 200, ['Cache-Control' => 'no-store']);
    }
}
