<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\CsrfCookie;
use Fight\AccessControl\Application\AccessControl\User\Security\TokenSet;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use LogicException;
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
    public const string REFRESH_COOKIE = '__Secure-agent_os_refresh';

    /**
     * Constructs SessionResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns browser-session cookies without exposing refresh material to JSON
     */
    public function respond(#[SensitiveParameter] TokenSet $tokens): ResponseInterface
    {
        if ($tokens->isRemembered()) {
            throw new LogicException('Only ordinary activation sessions are supported.');
        }
        $refresh = $tokens->getRefreshCredential()->toString();
        if (preg_match('/\A[0-9a-f]{64}\z/D', $refresh) !== 1) {
            throw new LogicException('Invalid refresh credential encoding.');
        }

        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new SessionAccessData($tokens)),
            200,
            ['Cache-Control' => 'no-store']
        )->withAddedHeader(
            'Set-Cookie',
            self::REFRESH_COOKIE.'='.$refresh.'; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict'
        )->withAddedHeader(
            'Set-Cookie',
            CsrfCookie::NAME.'='.bin2hex(random_bytes(32)).'; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict'
        );
    }
}
