<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Users\LifecycleInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use Fight\AccessControl\Domain\AccessControl\User\Command\RestoreUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\UserLifecycleException;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use LogicException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RestoreUserAction
 *
 * Admits confirmed restoration with an explicit package-supported destination
 */
#[RequiresPermission('RESTORE_USERS')]
final readonly class RestoreUserAction
{
    /**
     * Constructs RestoreUserAction
     */
    public function __construct(private CommandBus $commandBus, private UserLifecycleResponder $responder)
    {
    }

    /**
     * Restores a deleted identity without reviving sessions or disclosing activation authority
     */
    #[OA\Post(
        path: '/api/v1/users/{userId}/restore',
        operationId: 'restoreUser',
        summary: 'Restore a confirmed deleted installation user',
        description: <<<'TEXT'
            Requires exact RESTORE_USERS, confirm=true and an explicit restoration_state of active or
            pending_activation. Confirmation applies to current state, not a displayed revision. Only deleted
            targets are eligible. Active restoration retains the password; pending restoration clears it and
            atomically issues fresh package activation authority internally. Neither issues sessions, revives old
            sessions nor discloses credentials. Email claims remain reserved. Required audit is atomic. Failures
            may follow commit; do not automatically retry. Resolve uncertainty with a fresh authorized READ_USERS query.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'userId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid', maxLength: 36, minLength: 36)
        )],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['confirm', 'restoration_state'],
            properties: [
                new OA\Property(property: 'confirm', type: 'boolean', enum: [true]),
                new OA\Property(property: 'restoration_state', type: 'string', enum: ['active', 'pending_activation'])
            ],
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
                description: 'Missing or lifecycle conflict',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'confirm', 'label' => 'Confirmation', 'rules' => 'required|type[bool]'],
        [
            'field' => 'restoration_state',
            'label' => 'Restoration state',
            'rules' => 'required|type[string]|in_list[active,pending_activation]'
        ]
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = LifecycleInput::authentication($request);
        $targetId = LifecycleInput::target($request);
        /** @var array{restoration_state: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        try {
            $this->commandBus->execute(new RestoreUser(
                $authentication->getUserId(),
                $targetId,
                UserState::from($input['restoration_state'])
            ));
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
