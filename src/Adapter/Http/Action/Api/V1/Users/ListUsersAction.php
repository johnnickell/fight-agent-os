<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Users;

use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Users\UserDirectoryResponder;
use Fight\AccessControl\Domain\AccessControl\Role\Query\GetRoleById;
use Fight\AccessControl\Domain\AccessControl\Role\Query\RoleView;
use Fight\AccessControl\Domain\AccessControl\User\Query\ListUsers;
use Fight\AccessControl\Domain\AccessControl\User\Query\UserView;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use LogicException;
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
    public function __construct(
        private QueryBus $queries,
        private UserDirectoryResponder $responder
    ) {
    }

    /**
     * Maps only checked pagination into the authoritative installation directory
     */
    #[OA\Get(
        path: '/api/v1/users',
        operationId: 'listUsers',
        summary: 'Read the safe installation-wide user directory',
        description: <<<'TEXT'
            Requires Bearer authentication and exact current READ_USERS, centrally enforced from this
            Action's permission metadata before execution and package ListUsers dispatch.
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

        $page = (int) ($input['page'] ?? '1');
        $perPage = (int) ($input['per_page'] ?? '25');
        if ($page < 1 || $page > 1000 || $perPage < 1 || $perPage > 100) {
            throw new ValidationException(['page' => ['Invalid value.']]);
        }
        $users = $this->queries->fetch(new ListUsers(new Pagination($page, $perPage)));
        if (
            !$users instanceof ResultSet
            || $users->itemType() !== UserView::class
            || $users->page() !== $page
            || $users->perPage() !== $perPage
            || $users->count() > $perPage
        ) {
            throw new LogicException('User directory is unavailable.');
        }
        $ids = [];
        foreach ($users->records() as $user) {
            foreach ($user->getRoleIds() as $id) {
                $ids[$id->toString()] = $id;
            }
        }
        $roles = [];
        foreach ($ids as $id) {
            $role = $this->queries->fetch(new GetRoleById($id));
            if (!$role instanceof RoleView || !$role->getRoleId()->equals($id)) {
                throw new LogicException('User role presentation is unavailable.');
            }
            $roles[] = $role;
        }

        return $this->responder->respond($users, $roles);
    }
}
