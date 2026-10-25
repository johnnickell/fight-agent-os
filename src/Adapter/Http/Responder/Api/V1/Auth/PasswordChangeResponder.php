<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PasswordChangeResponder
 *
 * Expires browser cookies without issuing a replacement session
 */
#[OA\Schema(
    schema: 'PasswordChangeSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/PasswordChangeData')
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class PasswordChangeResponder
{
    /**
     * Constructs PasswordChangeResponder
     */
    public function __construct(private JSendResponseFactory $responses, private BrowserSessionCookies $cookies)
    {
    }

    /**
     * Returns the terminal fresh-login result
     */
    public function respond(): ResponseInterface
    {
        $response = $this->responses->fromEnvelope(
            JSendEnvelope::success(new PasswordChangeData()),
            200,
            ['Cache-Control' => 'no-store']
        );
        foreach ($this->cookies->delete() as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
        }

        return $response;
    }
}
