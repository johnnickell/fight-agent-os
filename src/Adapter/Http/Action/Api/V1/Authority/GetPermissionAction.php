<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use App\Application\Authorization\ReadAuthorityCatalogs;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionId;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class GetPermissionAction
 */
#[RequiresPermission('READ_PERMISSIONS')]
final readonly class GetPermissionAction
{
    /**
     * Constructs GetPermissionAction
     */
    public function __construct(private ReadAuthorityCatalogs $catalogs, private CatalogResponder $responder)
    {
    }

    /**
     * Maps a path identifier into one authorized permission read
     */
    #[OA\Get(
        path: '/api/v1/permissions/{permissionId}',
        operationId: 'getPermission',
        summary: 'Read one safe permission definition',
        description: <<<'TEXT'
            Requires current protected READ_PERMISSIONS before lookup. Tier and managed markers describe policy,
            never grants or CRUD authority. Responses are no-store.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'permissionId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid')
        )],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe permission',
                content: new OA\JsonContent(ref: '#/components/schemas/CatalogPermissionSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'Authorized permission not found',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authentication = CatalogInput::authentication($request);
        try {
            $id = PermissionId::fromString(
                (string) RouteContext::fromRequest($request)->getRoute()?->getArgument('permissionId')
            );
        } catch (Throwable) {
            throw new ValidationException(['permission_id' => ['Invalid value.']]);
        }

        return $this->responder->respond($this->catalogs->permission($authentication, $id));
    }
}
