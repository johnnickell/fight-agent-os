<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Invitations;

use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use App\Application\Invitation\InvitationRateLimiter;
use App\Domain\Invitation\InvitationOperation;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\FindInvitationDeliveryStatus;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\Query\InvitationDeliveryStatusView;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use LogicException;
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
#[RequiresPermission('READ_USERS')]
final readonly class ReadInvitationStatusAction
{
    /**
     * Constructs ReadInvitationStatusAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private UserRepository $userRepository,
        private InvitationRateLimiter $invitationRateLimiter,
        private ReadInvitationStatusResponder $responder
    ) {
    }

    /**
     * Reads package delivery status without body or query input
     */
    #[OA\Get(
        path: '/api/v1/invitations/{user_id}',
        operationId: 'readInvitationStatus',
        summary: 'Read safe invitation delivery lifecycle and expiry',
        description: <<<'TEXT'
            Requires exact READ_USERS; MANAGE_USER_INVITATIONS alone grants no read authority.
            Package delivery lifecycle is stored state, not proof of inbox
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
                description: 'Authorized target user not found',
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

        $this->invitationRateLimiter->consume($authentication->getUserId(), InvitationOperation::STATUS);
        if ($this->userRepository->getById($userId) === null) {
            throw new ResourceNotFound();
        }
        $delivery = $this->queryBus->fetch(new FindInvitationDeliveryStatus($userId));
        if (
            $delivery !== null && (!$delivery instanceof InvitationDeliveryStatusView
            || !$delivery->getUserId()->equals($userId))
        ) {
            throw new LogicException('Invitation status is unavailable.');
        }

        return $this->responder->respond($delivery);
    }
}
