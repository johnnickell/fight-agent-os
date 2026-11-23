<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Users\LifecycleInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserLifecycleResponder;
use App\Application\Authorization\PermissionGuard;
use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Application\AccessControl\Authorization\Service\SecurityContext;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\AccessControl\Domain\AccessControl\User\Command\AssignRoleToUser;
use Fight\AccessControl\Domain\AccessControl\User\Query\GetUserById;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\AccessControl\Domain\AccessControl\User\UserState;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
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
 * Class AssignUserRoleAction
 *
 * Admits confirmed role assignment before package dispatch
 */
#[RequiresPermission('MANAGE_USER_ROLES')]
final readonly class AssignUserRoleAction
{
    /**
     * Constructs AssignUserRoleAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
        private PermissionGuard $permissionGuard,
        private UserLifecycleResponder $responder
    ) {
    }

    /**
     * Assigns an authoritative role to another active identity
     */
    #[OA\Put(
        path: '/api/v1/users/{userId}/roles/{roleId}',
        operationId: 'assignUserRole',
        summary: 'Assign a role to another active user',
        description: <<<'TEXT'
            Requires MANAGE_USER_ROLES and confirm=true. Managed Super Admin additionally requires
            ASSIGN_SUPER_ADMIN and confirm_super_admin=true. No-store acknowledgement only; refetch
            current authority on the next request. A failure may follow commit; do not blindly retry.
            TEXT,
        security: [['AccessBearer' => []]],
        responses: [new OA\Response(response: 200, description: 'No-store dispatch acknowledgement')]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'confirm', 'label' => 'Confirmation', 'rules' => 'required|type[bool]'],
        ['field' => 'confirm_super_admin', 'label' => 'Elevation confirmation', 'rules' => 'type[bool]']
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
        $superAdmin = $roleId->toString() === InstallationManagedPolicy::SUPER_ADMIN_ROLE_ID;
        if ($superAdmin !== ($role->isManaged() && $role->getName()->toString() === 'ROLE_SUPER_ADMIN')) {
            throw new StateConflict();
        }
        if ($superAdmin) {
            $security = $request->getAttribute(SecurityContext::class);
            if (!$security instanceof SecurityContext) {
                throw new AuthenticationRequired();
            }
            $this->permissionGuard->assertAllowed($security, [PermissionName::fromString('ASSIGN_SUPER_ADMIN')]);
            $input = $request->getAttribute(JsonBody::class);
            if (!is_array($input) || ($input['confirm_super_admin'] ?? false) !== true) {
                throw new ValidationException(['confirm_super_admin' => ['Explicit confirmation is required.']]);
            }
        }
        $this->commandBus->execute(new AssignRoleToUser($authentication->getUserId(), $targetId, $roleId));

        return $this->responder->respond();
    }
}
