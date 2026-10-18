<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Invitations;

use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Invitation\ReadInvitationStatus;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class ReadInvitationStatusAction
 *
 * Maps one target path identifier into an authorized safe status query
 */
#[RequiresPermission('MANAGE_USER_INVITATIONS')]
final readonly class ReadInvitationStatusAction
{
    /**
     * Constructs ReadInvitationStatusAction
     */
    public function __construct(private ReadInvitationStatus $status, private ReadInvitationStatusResponder $responder)
    {
    }

    /**
     * Reads package delivery status without body or query input
     */
    #[OA\Get(
        path: '/api/v1/invitations/{user_id}',
        operationId: 'readInvitationStatus',
        summary: 'Read safe invitation delivery lifecycle and expiry',
        description: <<<'TEXT'
            Requires exact MANAGE_USER_INVITATIONS. Package delivery lifecycle is stored state, not proof of inbox
            receipt or a computed expiry transition. Actor/action fixed-window limits default to 60 reads per
            60 seconds; configurable server-side. No query or body input.
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
                description: 'Credential-free package status',
                content: new OA\JsonContent(ref: '#/components/schemas/InvitationStatusSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'No invitation delivery generation for this target',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
            ),
            new OA\Response(ref: '#/components/responses/InvitationThrottled', response: 429),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = $request->getAttribute(AuthenticationContext::class);
        if (!$authentication instanceof AuthenticationContext) {
            throw new AuthenticationRequired();
        }
        try {
            $userId = UserId::fromString(
                (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('user_id')
            );
        } catch (Throwable) {
            throw new ValidationException(['user_id' => ['Invalid value.']]);
        }

        return $this->responder->respond($this->status->read($authentication, $userId));
    }
}
