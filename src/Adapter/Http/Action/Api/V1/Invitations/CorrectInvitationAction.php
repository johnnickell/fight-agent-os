<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Invitations;

use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Invitations\ReadInvitationStatusResponder;
use App\Application\Failure\AuthenticationRequired;
use App\Application\Invitation\RecoverInvitation;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\AuthenticationContext;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class CorrectInvitationAction
 *
 * Maps checked destination input to atomic pending-invitation correction
 */
#[RequiresPermission('MANAGE_USER_INVITATIONS')]
final readonly class CorrectInvitationAction
{
    /**
     * Constructs CorrectInvitationAction
     */
    public function __construct(private RecoverInvitation $recovery, private ReadInvitationStatusResponder $responder)
    {
    }

    /**
     * Updates a pending address without accepting actor or arbitrary lifecycle fields
     */
    #[OA\Patch(
        path: '/api/v1/invitations/{user_id}/email',
        operationId: 'correctInvitation',
        summary: 'Atomically correct a pending invitation address and replace its grant',
        description: <<<'TEXT'
            Requires exact current MANAGE_USER_INVITATIONS before target disclosure. No query input.
            Requires a pending-activation user and current issued invitation. Canonicalizes email and rejects
            canonical or reserved email claims. User email, predecessor revocation, fresh purpose-bound seven-day
            grant/delivery and safe actor/target/action audit commit atomically through the package command.
            Actor/action and actor/action/target atomic budgets default to 3 per 60 seconds, with bounded capacity.
            Throttle denial writes no package state. Package discovery recovers delivery without a consumer queue;
            immediate delivery is optional. At least once is not exactly once or proof of inbox receipt.
            On 503 the transition may have committed: read status before any further operation; never auto-retry.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'user_id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid')
        )],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                minLength: 3,
                maxLength: 254
            )],
            type: 'object',
            additionalProperties: false
        )),
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
                description: 'Not pending, used, replaced, email claimed or concurrent conflict',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid canonical destination',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
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
    #[JsonBody]
    #[Validation(rules: [[
        'field' => 'email',
        'label' => 'Email',
        'rules' => 'required|type[string]|min_length[3]|max_length[254]|email'
    ]])]
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
        /** @var array{email: string} $input */
        $input = $request->getAttribute(JsonBody::class);

        return $this->responder->respond($this->recovery->correct(
            $authentication,
            $target,
            EmailAddress::fromString($input['email'])
        ));
    }
}
