<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Authorization\InstallationPermissionAdmission;
use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use Fight\AccessControl\Domain\AccessControl\Permission\PermissionName;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\ListPermissions;
use Fight\AccessControl\Domain\AccessControl\Permission\Query\PermissionView;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ListPermissionsAction
 */
#[RequiresPermission('READ_PERMISSIONS')]
final readonly class ListPermissionsAction
{
    /**
     * Constructs ListPermissionsAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private InstallationPermissionAdmission $installationAuthority,
        private CatalogResponder $responder
    ) {
    }

    /**
     * Maps validated pagination into one authorized permission catalog read
     */
    #[OA\Get(
        path: '/api/v1/permissions',
        operationId: 'listPermissions',
        summary: 'Read safe permissions ordered by created_at then id ascending',
        description: <<<'TEXT'
            Requires current protected READ_PERMISSIONS. Tier and managed markers confer no mutation authority.
            No grants. Offset pages are deterministic for a committed state, not a cross-request snapshot.
            Responses are no-store.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CatalogPage'),
            new OA\Parameter(ref: '#/components/parameters/CatalogPerPage')
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Safe permission page',
                content: new OA\JsonContent(ref: '#/components/schemas/CatalogPermissionsSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[QueryString]
    #[Validation(rules: [
        ['field' => 'page', 'label' => 'Page', 'rules' => 'digits|min_number[1]|max_number[1000]'],
        ['field' => 'per_page', 'label' => 'Per page', 'rules' => 'digits|min_number[1]|max_number[100]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{page?: string, per_page?: string} $input */
        $input = $request->getAttribute(QueryString::class);

        $this->installationAuthority->assertInstallationPermission(
            CatalogInput::authentication($request)->getUserId(),
            PermissionName::fromString('READ_PERMISSIONS')
        );
        /** @var ResultSet<PermissionView> $page */
        $page = $this->queryBus->fetch(new ListPermissions(new Pagination(
            (int) ($input['page'] ?? '1'),
            (int) ($input['per_page'] ?? '25')
        )));

        return $this->responder->respond($page);
    }
}
