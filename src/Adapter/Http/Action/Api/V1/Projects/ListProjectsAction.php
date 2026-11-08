<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Projects;

use App\Adapter\Http\Api\V1\Projects\ProjectInput;
use App\Adapter\Http\Api\V1\Projects\ProjectWorkspaceAdmission;
use App\Adapter\Http\Attribute\QueryString;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Projects\ListProjectsResponder;
use App\Domain\Workspace\Project\Query\ListProjects;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Query\QueryBus;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ListProjectsAction
 */
#[RequiresPermission('READ_PROJECTS')]
final readonly class ListProjectsAction
{
    /**
     * Constructs ListProjectsAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private ProjectWorkspaceAdmission $projectWorkspaceAdmission,
        private ListProjectsResponder $responder
    ) {
    }

    /**
     * Validates a bounded current-state query without a snapshot or bootstrap side effect
     */
    #[OA\Get(
        path: '/api/v1/workspaces/{workspace_id}/projects',
        operationId: 'listProjects',
        summary: 'List current Projects ordered by created_at then project_id',
        description: <<<'TEXT'
            Requires independent READ_PROJECTS and current initial Workspace administrator scope.
            Deterministic for a committed state, not a cross-request snapshot.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/WorkspaceScope'),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                schema: new OA\Schema(type: 'integer', minimum: 0, maximum: 100000, default: 0)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 25)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bounded safe current page',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectPageSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ProjectRejected', response: 422),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[QueryString]
    #[Validation(rules: [
        ['field' => 'offset', 'label' => 'Offset', 'rules' => 'digits|min_number[0]|max_number[100000]'],
        ['field' => 'limit', 'label' => 'Limit', 'rules' => 'digits|min_number[1]|max_number[100]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{offset?: string, limit?: string} $input */
        $input = $request->getAttribute(QueryString::class);
        $workspaceId = ProjectInput::workspace($request);
        $this->projectWorkspaceAdmission->require(ProjectInput::authentication($request), $workspaceId);

        return $this->responder->respond($this->queryBus->fetch(new ListProjects(
            $workspaceId,
            (int) ($input['offset'] ?? '0'),
            (int) ($input['limit'] ?? '25')
        )));
    }
}
