<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Invitations;

use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Invitation\RecoverInvitation;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class ResendInvitationAction
 *
 * Maps a verified actor and target to atomic invitation replacement
 */
#[RequiresPermission('MANAGE_USER_INVITATIONS')]
final readonly class ResendInvitationAction
{
    /**
     * Constructs ResendInvitationAction
     */
    public function __construct(private RecoverInvitation $recovery, private ReadInvitationStatusResponder $responder)
    {
    }

    /**
     * Replaces an issued invitation without accepting body or query authority
     */
    #[OA\Post(
        path: '/api/v1/invitations/{user_id}/resend',
        operationId: 'resendInvitation',
        summary: 'Atomically replace an issued invitation and request delivery',
        description: <<<'TEXT'
            Requires exact current MANAGE_USER_INVITATIONS before target disclosure. No query or body input.
            The latest grant must be issued, including expired issued grants. Revocation and a new purpose-bound
            seven-day grant and delivery generation commit with durable audit in one package transaction.
            Actor/action and actor/action/target atomic budgets default to 3 per 60 seconds, with bounded capacity.
            Throttle denial writes no package state. Delivery is at least once, not exactly once or inbox receipt.
            200 includes current credential-free status; it does not identify which concurrent credential won.
            On 503 the transition may have committed: read status before any further operation; never auto-retry.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'user_id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid')
        )],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Recovery dispatched and a state effect observed; safe current status',
                content: new OA\JsonContent(ref: '#/components/schemas/InvitationStatusSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'No safe invitation generation',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
            ),
            new OA\Response(
                response: 409,
                description: 'Not resendable, used, replaced or concurrent state conflict',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InvitationThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500),
            new OA\Response(
                response: 503,
                description: 'Uncertain outcome; read status, do not automatically retry',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            )
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = $request->getAttribute(AuthenticationContext::class);
        if (!$authentication instanceof AuthenticationContext) {
            throw new AuthenticationRequired();
        }
        try {
            $target = UserId::fromString(
                (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('user_id')
            );
        } catch (Throwable) {
            throw new ValidationException(['user_id' => ['Invalid value.']]);
        }

        return $this->responder->respond($this->recovery->resend($authentication, $target));
    }
}
