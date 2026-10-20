<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Auth;

use App\Adapter\Http\Api\V1\Auth\RefreshCookie;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\PublicAccess;
use App\Adapter\Http\Responder\Api\V1\Auth\SessionLifecycleResponder;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationService;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\RefreshSessionNotFoundException;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Class RefreshAction
 *
 * Rotates package-owned authority selected only by the guarded browser cookie
 */
#[PublicAccess]
final readonly class RefreshAction
{
    /**
     * Constructs RefreshAction
     */
    public function __construct(
        private AuthenticationService $authentication,
        private RefreshCookie $cookie,
        private SessionLifecycleResponder $responder
    ) {
    }

    /**
     * Dispatches one rotation without retrying a conflict or exposing refresh material
     */
    #[OA\Post(
        path: '/api/v1/auth/refresh',
        operationId: 'refresh',
        summary: 'Rotate the current browser refresh session',
        description: <<<'TEXT'
            HTTPS browser POST with exact Origin, nonce-bound CSRF, same-origin Fetch Metadata when present,
            JSON exactly {} and independently committed network/endpoint admission before package dispatch.
            Authority comes only from the host-only Secure HttpOnly SameSite=Strict __Secure-agent_os_refresh
            cookie at /api/v1/auth; body, query, form and Authorization alternatives are rejected.
            Rotation preserves owner, authentication version, remembered flag and original absolute deadline.
            Idle advances by one day (ordinary) or 30 days (remembered), capped at absolute expiry.
            Access lasts 900 seconds and belongs only in memory. Replacement refresh is only Set-Cookie:
            ordinary remains session-only; remembered Expires is no later than original one-year absolute expiry.
            CSRF nonce is unchanged. Most-recent used credential within five seconds produces secretless 409
            with no cookie changes and no server retry. Older/out-of-window replay revokes the package session
            family. Non-authoritative/unknown/expired/revoked credentials return generic 401 and expire refresh
            and CSRF cookies. Unexpected failure returns sanitized 500 with the same deletion; commit may be
            uncertain. No credential, digest or session existence detail is returned. All responses are no-store.
            TEXT,
        security: [['RefreshCookie' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/BrowserAuthOrigin'),
            new OA\Parameter(ref: '#/components/parameters/BrowserAuthCsrf')
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/BrowserAuthEmptyInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Rotated access and matching HttpOnly cookie',
                content: new OA\JsonContent(ref: '#/components/schemas/SessionAccessSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/MethodNotAllowed', response: 405),
            new OA\Response(
                response: 409,
                description: 'Bounded recoverable conflict; no retry or cookie changes',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/BrowserAuthThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $credential = $this->cookie->extract($request);
        if ($credential === null) {
            return $this->responder->terminal();
        }
        try {
            $result = $this->authentication->refresh($credential);
        } catch (RefreshSessionNotFoundException) {
            return $this->responder->terminal();
        } catch (Throwable) {
            return $this->responder->unavailable();
        }

        return $this->responder->respond($result);
    }
}
