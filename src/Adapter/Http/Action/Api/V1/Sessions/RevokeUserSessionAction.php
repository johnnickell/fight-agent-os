<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Sessions;

use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Sessions\SessionRevocationResponder;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\CurrentPrincipalProvider;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Command\RevokeSession;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\Exception\SessionRevocationReasonException;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionId;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\SessionRevocationReason;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Class RevokeUserSessionAction
 *
 * Admits one explicit user/session pair before package-owned atomic reasoned revocation
 */
#[RequiresPermission('DELETE_SESSIONS')]
final readonly class RevokeUserSessionAction
{
    /**
     * Constructs RevokeUserSessionAction
     */
    public function __construct(
        private RefreshSessionRepository $refreshSessionRepository,
        private CommandBus $commandBus,
        private SessionRevocationResponder $responder
    ) {
    }

    /**
     * Rejects mismatched targets before dispatch without wrapping the package transaction
     */
    #[OA\Delete(
        path: '/api/v1/users/{userId}/sessions/{sessionId}',
        operationId: 'revokeUserSession',
        summary: 'Revoke another installation user\'s active session with a safe reason',
        description: <<<'TEXT'
            Requires current DELETE_SESSIONS and package actor/target authorization. Self targets use
            /me/sessions or Logout. Only a bounded secret-free reason is accepted. Actor/current session
            are principal-derived. The session must belong to the explicit path user; ownership is immutable.
            Package handler commits revocation and required audit together before events. Unknown, wrong-owner,
            expired, revoked or competing sessions return generic 409. Target access/refresh fails after commit.
            All responses are no-store. Never enter credentials or other private information in the reason.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'sessionId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [new OA\Property(property: 'reason', type: 'string', minLength: 1, maxLength: 500)],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Revocation and safe audit committed; Cache-Control: no-store',
                content: new OA\JsonContent(ref: '#/components/schemas/SessionRevocationSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Generic session conflict',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'reason', 'label' => 'Reason', 'rules' => 'required|type[string]|max_length[500]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $request->getAttribute(CurrentPrincipalProvider::class);
        if (!$provider instanceof CurrentPrincipalProvider) {
            throw new AuthenticationRequired();
        }
        $route = RouteContext::fromRequest($request)->getRoute();
        $user = (string) $route?->getArgument('userId');
        $session = (string) $route?->getArgument('sessionId');
        foreach (['user_id' => $user, 'session_id' => $session] as $field => $value) {
            if (!preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D', $value)) {
                throw new ValidationException([$field => ['Invalid value.']]);
            }
        }
        /** @var array{reason: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        try {
            $reason = SessionRevocationReason::fromString($input['reason']);
        } catch (SessionRevocationReasonException) {
            throw new ValidationException(['reason' => ['Provide between 1 and 500 characters.']]);
        }
        if (
            preg_match('/[\x00-\x1f\x7f]/', $reason->toString()) !== 0
            || preg_match(
                '/(?:password|token|credential|secret|authorization|bearer|ciphertext|https?:\/\/)/i',
                $reason->toString()
            ) !== 0
        ) {
            throw new ValidationException(['reason' => ['Provide a nonempty reason without credentials or URLs.']]);
        }
        $principal = $provider->getCurrentPrincipal();
        $targetUser = UserId::fromString($user);
        if ($targetUser->equals($principal->getUserId())) {
            throw new PermissionDenied();
        }
        $targetId = RefreshSessionId::fromString($session);
        $target = $this->refreshSessionRepository->getById($targetId);
        if ($target === null || !$target->getUserId()->equals($targetUser)) {
            throw new StateConflict();
        }
        $command = new RevokeSession(
            $principal->getUserId(),
            $principal->getRefreshSessionId(),
            $targetId,
            $reason
        );

        return $this->responder->respond(fn() => $this->commandBus->execute($command));
    }
}
