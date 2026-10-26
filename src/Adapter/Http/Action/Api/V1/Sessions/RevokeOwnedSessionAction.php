<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Sessions;

use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Sessions\RevokeOwnedSession;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Class RevokeOwnedSessionAction
 *
 * Accepts only a canonical target and an empty JSON object through the guarded versioned route
 */
#[RequiresPermission('DELETE_OWN_SESSIONS')]
final readonly class RevokeOwnedSessionAction
{
    /**
     * Constructs RevokeOwnedSessionAction
     */
    public function __construct(private RevokeOwnedSession $sessions, private SessionRevocationResponder $responder)
    {
    }

    /**
     * Revokes another owned usable session through the configured package command bus
     */
    #[OA\Delete(
        path: '/api/v1/me/sessions/{sessionId}',
        operationId: 'revokeOwnedSession',
        summary: 'Revoke one other owned refresh session after explicit confirmation',
        description: <<<'TEXT'
            Requires Bearer and exact current DELETE_OWN_SESSIONS, never DELETE_SESSIONS or a Role bypass.
            Actor and current session derive exclusively from the request-scoped principal. Only the canonical
            path target and an empty JSON object are accepted. Package RevokeSession owns usability, ownership,
            current-session exclusion, optimistic replacement and RefreshSessionRevoked. Cross-user administration
            is always denied; no administrative reason or logout-everywhere capability exists here.
            Current target returns 409 code 5801 and directs to Logout. All other unknown, cross-user, expired,
            revoked or competing targets return identical 409 Conflict. without target details. Refetch the list
            after success or conflict. Only target is revoked; target access and refresh fail authoritatively.
            No credential is returned. All responses are no-store. Cross-device push is not provided.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'sessionId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[a-f0-9-]{36}$')
        )],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Target committed as revoked',
                content: new OA\JsonContent(ref: '#/components/schemas/SessionRevocationSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Generic target conflict or explicit current-session Logout instruction',
                content: new OA\JsonContent(oneOf: [
                    new OA\Schema(ref: '#/components/schemas/Error'),
                    new OA\Schema(ref: '#/components/schemas/CurrentSessionRevocationConflict')
                ])
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute(CurrentPrincipalProvider::class);
        if (!$provider instanceof CurrentPrincipalProvider) {
            throw new AuthenticationRequired();
        }
        $value = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('sessionId');
        if (!preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D', $value)) {
            throw new ValidationException(['session_id' => ['Invalid value.']]);
        }
        $target = RefreshSessionId::fromString($value);

        return $this->responder->respond(fn() => $this->sessions->revoke($provider, $target));
    }
}
