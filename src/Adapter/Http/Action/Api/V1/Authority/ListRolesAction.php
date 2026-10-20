<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Authority;

use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Authority\CatalogResponder;
use App\Application\Authorization\ReadAuthorityCatalogs;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ListRolesAction
 */
#[RequiresPermission('READ_ROLES')]
final readonly class ListRolesAction
{
    /**
     * Constructs ListRolesAction
     */
    public function __construct(private ReadAuthorityCatalogs $catalogs, private CatalogResponder $responder)
    {
    }

    /**
     * Maps validated pagination into one authorized role catalog read
     */
    #[OA\Get(
        path: '/api/v1/roles',
        operationId: 'listRoles',
        summary: 'Read safe roles ordered by created_at then id ascending',
        description: <<<'TEXT'
            Requires current protected READ_ROLES. No grant membership or mutations.
            Offset pages are deterministic for a committed state, not a cross-request snapshot.
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
                description: 'Safe role page',
                content: new OA\JsonContent(ref: '#/components/schemas/CatalogRolesSuccess')
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

        return $this->responder->respond($this->catalogs->roles(
            CatalogInput::authentication($request),
            (int) ($input['page'] ?? '1'),
            (int) ($input['per_page'] ?? '25')
        ));
    }
}
