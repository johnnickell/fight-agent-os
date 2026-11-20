<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/**
 * Class GetRoleAction
 */
#[RequiresPermission('READ_ROLES')]
final readonly class GetRoleAction
{
    /**
     * Constructs GetRoleAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private InstallationPermissionAdmission $installationAuthority,
        private CatalogResponder $responder
    ) {
    }

    /**
     * Maps a path identifier into one authorized role read
     */
    #[OA\Get(
        path: '/api/v1/roles/{roleId}',
        operationId: 'getRole',
        summary: 'Read one safe managed or custom role',
        description: <<<'TEXT'
            Requires current protected READ_ROLES before lookup. No membership or persistence internals.
            The revision is an exact state expectation, not authority. Managed roles are not mutation targets;
            custom definition mutations require their separate exact permission. Responses are no-store.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(
            name: 'roleId',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid')
        )],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe role',
                content: new OA\JsonContent(ref: '#/components/schemas/CatalogRoleSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'Authorized role not found',
                content: new OA\JsonContent(ref: '#/components/schemas/NotFoundError')
            ),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->installationAuthority->assertInstallationPermission(
            CatalogInput::authentication($request)->getUserId(),
            PermissionName::fromString('READ_ROLES')
        );
        try {
            $id = RoleId::fromString((string) RouteContext::fromRequest($request)->getRoute()?->getArgument('roleId'));
        } catch (Throwable) {
            throw new ValidationException(['role_id' => ['Invalid value.']]);
        }

        /** @var RoleView $view */
        $view = $this->queryBus->fetch(new GetRoleById($id));

        return $this->responder->respond($view);
    }
}
