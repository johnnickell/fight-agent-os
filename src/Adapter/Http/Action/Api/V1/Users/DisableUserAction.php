<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Users\LifecycleInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Application\Authorization\PreventAdministratorLockout;
use Fight\AccessControl\Domain\AccessControl\User\Command\DisableUser;
use Fight\AccessControl\Domain\AccessControl\User\Exception\UserLifecycleException;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use LogicException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class DisableUserAction
 *
 * Admits confirmed disable intent and delegates lifecycle ownership to the package
 */
#[RequiresPermission('DISABLE_USERS')]
final readonly class DisableUserAction
{
    /**
     * Constructs DisableUserAction
     */
    public function __construct(
        private PreventAdministratorLockout $lockout,
        private CommandBus $commandBus,
        private UserLifecycleResponder $responder
    ) {
    }

    /**
     * Disables another eligible user after the explicitly non-atomic administrator precheck
     */
    #[OA\Post(
        path: '/api/v1/users/{userId}/disable',
        operationId: 'disableUser',
        summary: 'Disable a confirmed installation user',
        description: <<<'TEXT'
            Requires only exact current DISABLE_USERS. Self-targets reject. The authoritative last-admin
            query precedes command dispatch but is NOT atomic with it: concurrent requests may both pass.
            Requires explicit transition intent, not a displayed account revision. Concurrent unrelated email
            changes are preserved. Package lifecycle, session revocation and safe audit commit atomically.
            200 acknowledges void dispatch with no user data or READ_USERS prerequisite. Failures may follow
            commit; never automatically retry. Read fresh authorized detail to resolve an uncertain outcome.
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
                description: 'Dispatch completed; no-store acknowledgement',
                content: new OA\JsonContent(ref: '#/components/schemas/UserLifecycleSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Self, last administrator, missing or lifecycle conflict',
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
        $this->lockout->assertCanDisable($authentication->getUserId(), $targetId);
        try {
            $this->commandBus->execute(new DisableUser($authentication->getUserId(), $targetId));
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
