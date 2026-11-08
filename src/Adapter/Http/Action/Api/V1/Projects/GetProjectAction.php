<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Projects;

use App\Adapter\Http\Api\V1\Projects\ProjectInput;
use App\Adapter\Http\Api\V1\Projects\ProjectWorkspaceAdmission;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Projects\GetProjectResponder;
use App\Domain\Workspace\Project\Query\GetProjectById;
use Fight\Common\Application\Messaging\Query\QueryBus;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class GetProjectAction
 */
#[RequiresPermission('READ_PROJECTS')]
final readonly class GetProjectAction
{
    /**
     * Constructs GetProjectAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private ProjectWorkspaceAdmission $projectWorkspaceAdmission,
        private GetProjectResponder $responder
    ) {
    }

    /**
     * Validates a fresh scoped detail query independently of registration permission
     */
    #[OA\Get(
        path: '/api/v1/workspaces/{workspace_id}/projects/{project_id}',
        operationId: 'getProject',
        summary: 'Inspect current safe Project and Git-source facts',
        security: [['AccessBearer' => []]],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/WorkspaceScope'),
            new OA\Parameter(
                name: 'project_id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current Project, not checkout availability',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(
                response: 404,
                description: 'Required Project absent in admitted scope',
                content: new OA\JsonContent(ref: '#/components/schemas/Error')
            ),
            new OA\Response(ref: '#/components/responses/ProjectRejected', response: 422),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $workspaceId = ProjectInput::workspace($request);
        $this->projectWorkspaceAdmission->require(ProjectInput::authentication($request), $workspaceId);

        return $this->responder->respond($this->queryBus->fetch(new GetProjectById(
            $workspaceId,
            ProjectInput::project($request)
        )));
    }
}
