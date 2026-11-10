<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Users\LifecycleInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use Fight\AccessControl\Domain\AccessControl\User\Command\DeleteUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\UserLifecycleException;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use LogicException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class DeleteUserAction
 *
 * Admits confirmed current-state soft deletion without an administrator-count prerequisite
 */
#[RequiresPermission('DELETE_USERS')]
final readonly class DeleteUserAction
{
    /**
     * Constructs DeleteUserAction
     */
    public function __construct(private CommandBus $commandBus, private UserLifecycleResponder $responder)
    {
    }

    /**
     * Deletes another eligible identity through configured package dispatch
     */
    #[OA\Delete(
        path: '/api/v1/users/{userId}',
        operationId: 'deleteUser',
        summary: 'Soft-delete a confirmed installation user in their current state',
        description: <<<'TEXT'
            Requires exact DELETE_USERS and confirm=true. Self-deletion rejects. No displayed revision or
            administrator-count check: admitted administrators may delete each other. Only active or disabled
            targets are eligible. Identity and email claims remain reserved. Sessions and reset authority retire
            atomically with lifecycle and required audit. No hard deletion. Failures may follow commit; do not
            automatically retry. Resolve uncertainty with a fresh separately authorized READ_USERS query.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'userId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid', maxLength: 36, minLength: 36)
        )],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['confirm'],
            properties: [new OA\Property(property: 'confirm', type: 'boolean', enum: [true])],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'No-store void dispatch acknowledgement',
                content: new OA\JsonContent(ref: '#/components/schemas/UserLifecycleSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Self, missing or lifecycle conflict',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'confirm', 'label' => 'Confirmation', 'rules' => 'required|type[bool]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = LifecycleInput::authentication($request);
        $targetId = LifecycleInput::target($request);
        if ($authentication->getUserId()->equals($targetId)) {
            throw new StateConflict();
        }
        try {
            $this->commandBus->execute(new DeleteUser($authentication->getUserId(), $targetId));
        } catch (UserLifecycleException) {
            throw new StateConflict();
        } catch (LogicException $failure) {
            if (
                $failure::class !== LogicException::class
                || $failure->getMessage() !== 'The user lifecycle state changed concurrently.'
            ) {
                throw $failure;
            }
            throw new StateConflict();
        }

        return $this->responder->respond();
    }
}
