<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Api\V1\Authority\RoleMutationInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\Command\GrantPermissionToCustomRole;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class GrantRolePermissionAction
 */
#[RequiresPermission('MANAGE_ROLE_PERMISSIONS')]
final readonly class GrantRolePermissionAction
{
    /**
     * Constructs GrantRolePermissionAction
     */
    public function __construct(
        private CommandBus $commandBus,
        private InstallationPermissionAdmission $installationAuthority,
        private RoleMutationResponder $responder
    ) {
    }

    /**
     * Dispatches confirmed path intent after installation admission
     */
    #[OA\Put(
        path: '/api/v1/roles/{roleId}/permissions/{permissionId}',
        operationId: 'grantCustomRolePermission',
        summary: 'Grant an eligible permission to a custom role',
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'roleId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'permissionId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['confirm'],
            properties: [new OA\Property(property: 'confirm', type: 'boolean', enum: [true])],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Intent completed; refresh authorized catalogs',
                content: new OA\JsonContent(ref: '#/components/schemas/RoleRemovalSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(response: 404, description: 'Required target missing'),
            new OA\Response(response: 409, description: 'Ineligible or concurrently changed target'),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [['field' => 'confirm', 'label' => 'Confirmation', 'rules' => 'required|type[bool]']])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorId = CatalogInput::authentication($request)->getUserId();
        $this->installationAuthority->assertInstallationPermission(
            $actorId,
            PermissionName::fromString('MANAGE_ROLE_PERMISSIONS')
        );
        /** @var array{confirm: bool} $input */
        $input = $request->getAttribute(JsonBody::class);
        if (!$input['confirm']) {
            throw new ValidationException(['confirm' => ['Explicit confirmation is required.']]);
        }
        $roleId = RoleMutationInput::id($request);
        try {
            $permissionId = PermissionId::fromString(
                (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('permissionId')
            );
        } catch (Throwable) {
            throw new ValidationException(['permission_id' => ['Invalid value.']]);
        }

        return $this->responder->respond(function () use ($actorId, $roleId, $permissionId): null {
            $this->commandBus->execute(new GrantPermissionToCustomRole($actorId, $roleId, $permissionId));

            return null;
        });
    }
}
