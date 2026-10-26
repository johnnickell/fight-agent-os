<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Api\V1\Authority\CatalogInput;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserDirectoryResponder;
use App\Application\Users\ReadUserDirectory;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ListUsersAction
 */
#[RequiresPermission('READ_USERS')]
final readonly class ListUsersAction
{
    /**
     * Constructs ListUsersAction
     */
    public function __construct(private ReadUserDirectory $directory, private UserDirectoryResponder $responder)
    {
    }

    /**
     * Maps only checked pagination into the authoritative installation directory
     */
    #[OA\Get(
        path: '/api/v1/users',
        operationId: 'listUsers',
        summary: 'Read the safe installation-wide user directory',
        description: <<<'TEXT'
            Requires Bearer authentication and exact current managed READ_USERS before package ListUsers.
            Role names and token claims grant nothing. Includes pending_activation, active, disabled and deleted
            identities, ordered by created_at ASC then stable user ID ASC. Offset pages describe committed state,
            not a cross-request snapshot. Defaults: page 1, per_page 25. Only these two query fields are accepted;
            no search, filtering, sorting, include, workspace, actor, role or mutation input is supported.
            Roles expose only authoritative name and managed ownership marker, never memberships or editability.
            Unresolved or duplicate role references fail closed. No credentials, sessions, revisions, grants,
            reservations, delivery, audit or permission internals are returned. Cache-Control: no-store.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/CatalogPage'),
            new OA\Parameter(ref: '#/components/parameters/CatalogPerPage')
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Explicit safe user page; Cache-Control: no-store',
                headers: [new OA\Header(header: 'Cache-Control', schema: new OA\Schema(
                    type: 'string',
                    enum: ['no-store']
                ))],
                content: new OA\JsonContent(ref: '#/components/schemas/UserDirectorySuccess')
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

        return $this->responder->respond($this->directory->read(
            CatalogInput::authentication($request),
            (int) ($input['page'] ?? '1'),
            (int) ($input['per_page'] ?? '25')
        ));
    }
}
