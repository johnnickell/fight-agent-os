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
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RemoveRoleAction
 */
#[RequiresPermission('DELETE_ROLES')]
final readonly class RemoveRoleAction
{
    /**
     * Constructs RemoveRoleAction
     */
    public function __construct(private ManageCustomRoles $roles, private RoleMutationResponder $responder)
    {
    }

    /**
     * Removes an explicitly confirmed unreferenced custom role at its displayed revision
     */
    #[OA\Delete(
        path: '/api/v1/roles/{roleId}',
        operationId: 'removeCustomRole',
        summary: 'Remove a confirmed custom role definition',
        description: <<<'TEXT'
            Requires exact DELETE_ROLES. Managed or referenced roles reject atomically.
            JSON confirmation binds the path ID and exact displayed revision. Refresh and reconfirm after conflicts;
            uncertain failures never authorize automatic replay.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'roleId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid')
        )],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['expected_revision', 'confirm'],
            properties: [
                new OA\Property(property: 'expected_revision', ref: '#/components/schemas/RoleRevision'),
                new OA\Property(property: 'confirm', type: 'boolean', enum: [true])
            ],
            type: 'object',
            additionalProperties: false
        )),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role removed',
                content: new OA\JsonContent(ref: '#/components/schemas/RoleRemovalSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 409,
                description: 'Stale, managed, referenced, missing or concurrently changed target',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        ['field' => 'expected_revision', 'label' => 'Revision', 'rules' => 'required|type[string]|max_length[46]'],
        ['field' => 'confirm', 'label' => 'Confirmation', 'rules' => 'required|type[bool]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = CatalogInput::authentication($request);
        /** @var array{expected_revision: string, confirm: bool} $input */
        $input = $request->getAttribute(JsonBody::class);
        if (!$input['confirm']) {
            throw new ValidationException(['confirm' => ['Explicit confirmation is required.']]);
        }
        $id = RoleMutationInput::id($request);
        $revision = RoleMutationInput::revision($input['expected_revision']);

        return $this->responder->respond(function () use ($authentication, $id, $revision): null {
            $this->roles->remove($authentication, $id, $revision, true);

            return null;
        });
    }
}
