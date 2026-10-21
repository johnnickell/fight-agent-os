<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Api\V1\Authority\RoleMutationInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use App\Application\Authorization\ManageCustomRoles;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class CreateRoleAction
 */
#[RequiresPermission('CREATE_ROLES')]
final readonly class CreateRoleAction
{
    /**
     * Constructs CreateRoleAction
     */
    public function __construct(private ManageCustomRoles $roles, private RoleMutationResponder $responder)
    {
    }

    /**
     * Creates one custom definition using the verified actor rather than caller-supplied authority
     */
    #[OA\Post(
        path: '/api/v1/roles',
        operationId: 'createCustomRole',
        summary: 'Create a custom role definition',
        description: <<<'TEXT'
            Requires exact CREATE_ROLES. No assignment or Permission grant. Conflict requires refresh;
            never automatically retry an uncertain failure.
            TEXT,
        security: [['AccessBearer' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['role_id', 'name'],
            properties: [
                new OA\Property(property: 'role_id', type: 'string', format: 'uuid'),
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    minLength: 6,
                    maxLength: 255,
                    pattern: '^ROLE_[A-Z_]+$'
                )
            ],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Current safe role',
                content: new OA\JsonContent(ref: '#/components/schemas/CatalogRoleSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Identity, name or invariant conflict',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'role_id', 'label' => 'Role', 'rules' => 'required|type[string]|uuid'],
        ['field' => 'name', 'label' => 'Name', 'rules' => 'required|type[string]|min_length[6]|max_length[255]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = CatalogInput::authentication($request);
        /** @var array{role_id: string, name: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        $id = RoleId::fromString($input['role_id']);
        $name = RoleMutationInput::name($input['name']);

        return $this->responder->respond(fn() => $this->roles->create($authentication, $id, $name), 201);
    }
}
