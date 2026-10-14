<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CsrfBootstrapResponder
 *
 * Presents the bootstrap proof without exposing the nonce or application result
 */
#[OA\Schema(
    schema: 'CsrfSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            required: ['proof', 'expires_at'],
            properties: [
                new OA\Property(
                    property: 'proof',
                    description: 'Non-authoritative MAC proof; no credential example is published',
                    type: 'string',
                    pattern: '^[1-9][0-9]{0,9}\\.[a-f0-9]{64}$'
                ),
                new OA\Property(
                    property: 'expires_at',
                    description: 'UTC Unix seconds; proof is valid strictly before this deadline',
                    type: 'integer',
                    format: 'int64',
                    maximum: 9999999999,
                    minimum: 1
                )
            ],
            type: 'object',
            additionalProperties: false
        )
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class CsrfBootstrapResponder
{
    /**
     * Constructs CsrfBootstrapResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns a no-store JSend proof and a new session cookie only when needed
     */
    public function respond(CsrfProofView $result): ResponseInterface
    {
        $headers = ['Cache-Control' => 'no-store'];
        if ($result->newNonce) {
            $headers['Set-Cookie'] = sprintf(
                '%s=%s; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict',
                CsrfCookie::NAME,
                $result->nonce
            );
        }

        return $this->responses->fromEnvelope(JSendEnvelope::success(new CsrfBootstrapData($result)), 200, $headers);
    }
}
