<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Users\LifecycleInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\RemoveRoleFromUser;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class RemoveUserRoleAction
 *
 * Refuses protected removal before dispatching an ordinary role change
 */
#[RequiresPermission('MANAGE_USER_ROLES')]
final readonly class RemoveUserRoleAction
{
    /**
     * Constructs RemoveUserRoleAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
        private UserLifecycleResponder $responder
    ) {
    }

    /**
     * Removes an ordinary role from another active identity
     */
    #[OA\Delete(
        path: '/api/v1/users/{userId}/roles/{roleId}',
        operationId: 'removeUserRole',
        summary: 'Remove an ordinary role from another active user',
        description: <<<'TEXT'
            Requires MANAGE_USER_ROLES and confirm=true. Managed Super Admin removal is always forbidden,
            including with REMOVE_SUPER_ADMIN. No-store acknowledgement only; refetch current authority
            on the next request. A failure may follow commit; do not blindly retry.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', maxLength: 36, minLength: 36)
            ),
            new OA\Parameter(
                name: 'roleId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', maxLength: 36, minLength: 36)
            )
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['confirm'],
            properties: [new OA\Property(property: 'confirm', type: 'boolean', enum: [true])],
            type: 'object'
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
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(
                response: 409,
                description: 'Protected role, self, inactive, identity or conflicting assignment',
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
        $user = $this->queryBus->fetch(new GetUserById($targetId));
        if (
            !$user instanceof UserView
            || !$user->getUserId()->equals($targetId)
            || $user->getState() !== UserState::ACTIVE
        ) {
            throw new StateConflict();
        }
        $value = (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('roleId');
        try {
            if (strlen($value) !== 36) {
                throw new \InvalidArgumentException();
            }
            $roleId = RoleId::fromString($value);
        } catch (Throwable) {
            throw new ValidationException(['role_id' => ['Invalid value.']]);
        }
        $role = $this->queryBus->fetch(new GetRoleById($roleId));
        if (!$role instanceof RoleView || !$role->getRoleId()->equals($roleId)) {
            throw new StateConflict();
        }
        if (
            $roleId->toString() === InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID
            || ($role->isManaged() && $role->getName()->toString() === 'ROLE_SUPER_ADMIN')
        ) {
            throw new StateConflict();
        }
        $this->commandBus->execute(new RemoveRoleFromUser($authentication->getUserId(), $targetId, $roleId));

        return $this->responder->respond();
    }
}
