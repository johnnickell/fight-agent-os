<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Api\V1\Authority\RoleMutationInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\RoleMutationResponder;
use App\Application\Authorization\ManageCustomRoles;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RenameRoleAction
 */
#[RequiresPermission('UPDATE_ROLES')]
final readonly class RenameRoleAction
{
    /**
     * Constructs RenameRoleAction
     */
    public function __construct(private ManageCustomRoles $roles, private RoleMutationResponder $responder)
    {
    }

    /**
     * Renames the exact displayed state without replacing a stale caller revision
     */
    #[OA\Patch(
        path: '/api/v1/roles/{roleId}',
        operationId: 'renameCustomRole',
        summary: 'Rename a custom role definition',
        description: <<<'TEXT'
            Requires exact UPDATE_ROLES, not READ_ROLES. Managed roles reject. On conflict refresh and reconfirm;
            never substitute a fresh token or auto-retry. Same-name current intent is a fenced no-op.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'roleId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid')
        )],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'expected_revision'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    minLength: 6,
                    maxLength: 255,
                    pattern: '^ROLE_[A-Z_]+$'
                ),
                new OA\Property(property: 'expected_revision', ref: '#/components/schemas/RoleRevision')
            ],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current safe role',
                content: new OA\JsonContent(ref: '#/components/schemas/CatalogRoleSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Stale, managed, missing, conflicting or concurrently changed target',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'name', 'label' => 'Name', 'rules' => 'required|type[string]|min_length[6]|max_length[255]'],
        ['field' => 'expected_revision', 'label' => 'Revision', 'rules' => 'required|type[string]|max_length[46]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = CatalogInput::authentication($request);
        /** @var array{name: string, expected_revision: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        $id = RoleMutationInput::id($request);
        $name = RoleMutationInput::name($input['name']);
        $revision = RoleMutationInput::revision($input['expected_revision']);

        return $this->responder->respond(fn() => $this->roles->rename($authentication, $id, $name, $revision));
    }
}
