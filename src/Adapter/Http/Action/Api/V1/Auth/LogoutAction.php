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
 * Class LogoutAction
 *
 * Ends only the package session selected by the presented current cookie
 */
#[PublicAccess]
final readonly class LogoutAction
{
    /**
     * Constructs LogoutAction
     */
    public function __construct(
        private AuthenticationService $authentication,
        private RefreshCookie $cookie,
        private SessionLifecycleResponder $responder
    ) {
    }

    /**
     * Revokes the current credential and clears browser cookies without existence disclosure
     */
    #[OA\Post(
        path: '/api/v1/auth/logout',
        operationId: 'logout',
        summary: 'Log out only the current browser session',
        description: <<<'TEXT'
            HTTPS browser POST with exact Origin, nonce-bound CSRF, same-origin Fetch Metadata when present,
            JSON exactly {} and independently committed network/endpoint admission before dispatch.
            Only the exact scoped HttpOnly refresh cookie selects a session; no Bearer/body/query/form authority.
            Never revokes other sessions. Missing, malformed, unknown, used or already-terminal credentials
            receive the same idempotent success with null data, not a claim that a session existed or was revoked.
            Refresh and CSRF cookies always expire with matching host-only Path=/api/v1/auth, Secure, HttpOnly,
            SameSite=Strict identities after admitted dispatch. A storage/event failure returns sanitized 500
            and the same deletion: server revocation may be unconfirmed. Client must clear memory immediately
            even on failed transport. No automatic retry. Request-control rejection does not mutate cookies.
            All responses are no-store; no session identifier, credential, digest or internal failure is exposed.
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
                description: 'Browser authority ended; no server existence assertion',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success']),
                        new OA\Property(property: 'data', nullable: true, enum: [null])
                    ],
                    type: 'object',
                    additionalProperties: false
                )
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/MethodNotAllowed', response: 405),
            new OA\Response(ref: '#/components/responses/BrowserAuthThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $credential = $this->cookie->extract($request);
        if ($credential !== null) {
            try {
                $this->authentication->logout($credential);
            } catch (RefreshSessionNotFoundException) {
                // Deliberately indistinguishable from an admitted current-session logout.
            } catch (Throwable) {
                return $this->responder->unavailable();
            }
        }

        return $this->responder->loggedOut();
    }
}
