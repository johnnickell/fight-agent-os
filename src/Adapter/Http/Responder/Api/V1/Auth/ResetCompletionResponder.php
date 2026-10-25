<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ResetCompletionResponder
 *
 * Requires fresh login and expires only a matching target browser session
 */
#[OA\Schema(
    schema: 'ResetCompletionSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/ResetCompletionData')
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class ResetCompletionResponder
{
    /**
     * Constructs ResetCompletionResponder
     */
    public function __construct(private JSendResponseFactory $responses, private BrowserSessionCookies $cookies)
    {
    }

    /**
     * Returns no tokens or identity and never changes an unrelated browser cookie
     */
    public function respond(bool $clearTargetSession): ResponseInterface
    {
        $response = $this->responses->fromEnvelope(
            JSendEnvelope::success(new ResetCompletionData()),
            200,
            ['Cache-Control' => 'no-store']
        );
        if ($clearTargetSession) {
            foreach ($this->cookies->delete() as $cookie) {
                $response = $response->withAddedHeader('Set-Cookie', $cookie);
            }
        }

        return $response;
    }
}
