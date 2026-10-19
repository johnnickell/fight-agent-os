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
 * Class RetryInvitationAction
 *
 * Maps a verified actor and target to retry of the same invitation generation
 */
#[RequiresPermission('MANAGE_USER_INVITATIONS')]
final readonly class RetryInvitationAction
{
    /**
     * Constructs RetryInvitationAction
     */
    public function __construct(private RecoverInvitation $recovery, private ReadInvitationStatusResponder $responder)
    {
    }

    /**
     * Requests retry of package-defined failed work without body or query authority
     */
    #[OA\Post(
        path: '/api/v1/invitations/{user_id}/retry',
        operationId: 'retryInvitation',
        summary: 'Retry failed invitation delivery using the same generation',
        description: <<<'TEXT'
            Requires exact current MANAGE_USER_INVITATIONS before target disclosure. No query or body input.
            Only package-defined retryable failed, issued and unexpired work may transition; no replacement grant.
            Actor/action and actor/action/target atomic budgets default to 5 per 60 seconds, with bounded capacity.
            Throttle denial writes no package state. Delivery is at least once, not exactly once or inbox receipt.
            200 includes current credential-free status, which concurrent recovery may advance again.
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
                description: 'Not retryable, used, expired, replaced or concurrent state conflict',
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

        return $this->responder->respond($this->recovery->retry($authentication, $target));
    }
}
