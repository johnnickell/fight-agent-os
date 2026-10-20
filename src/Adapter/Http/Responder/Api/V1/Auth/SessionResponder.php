<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\BrowserSessionCookies;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

/**
 * Class SessionResponder
 *
 * Maps an ordinary session to memory access data and a host-only refresh cookie
 */
#[OA\Schema(
    schema: 'SessionAccessSuccess',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', required: ['access_token', 'issued_at', 'expires_at'], properties: [
            new OA\Property(property: 'access_token', description: 'Keep only in volatile memory', type: 'string'),
            new OA\Property(property: 'issued_at', type: 'integer'),
            new OA\Property(property: 'expires_at', description: 'UTC seconds, issued_at + 900', type: 'integer')
        ], type: 'object', additionalProperties: false)
    ],
    type: 'object',
    additionalProperties: false
)]
final readonly class SessionResponder
{
    public const string REFRESH_COOKIE = BrowserSessionCookies::REFRESH_NAME;

    /**
     * Constructs SessionResponder
     */
    public function __construct(private JSendResponseFactory $responses, private BrowserSessionCookies $cookies)
    {
    }

    /**
     * Returns browser-session cookies without exposing refresh material to JSON
     */
    public function respond(#[SensitiveParameter] TokenSet $tokens): ResponseInterface
    {
        return $this->refreshed($tokens)->withAddedHeader('Set-Cookie', $this->cookies->rotateNonce());
    }

    /**
     * Replaces refresh material without invalidating concurrent tabs' CSRF proofs
     */
    public function refreshed(#[SensitiveParameter] TokenSet $tokens): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new SessionAccessData($tokens)),
            200,
            ['Cache-Control' => 'no-store']
        )->withAddedHeader(
            'Set-Cookie',
            $this->cookies->refresh($tokens)
        );
    }

    /**
     * Clears ambient credentials even when server revocation cannot be confirmed by the caller
     */
    public function clear(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->cookies->delete() as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
        }

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
