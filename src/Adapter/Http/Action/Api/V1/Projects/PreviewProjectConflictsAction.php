<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Projects;

use App\Adapter\Http\Api\V1\Projects\ProjectInput;
use App\Adapter\Http\Api\V1\Projects\ProjectWorkspaceAdmission;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Projects\PreviewProjectConflictsResponder;
use App\Domain\Workspace\Project\ProjectMetadata;
use App\Domain\Workspace\Project\Query\PreviewProjectConflicts;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Query\QueryBus;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class PreviewProjectConflictsAction
 */
#[RequiresPermission('READ_PROJECTS')]
final readonly class PreviewProjectConflictsAction
{
    /**
     * Constructs PreviewProjectConflictsAction
     */
    public function __construct(
        private QueryBus $queryBus,
        private ProjectWorkspaceAdmission $projectWorkspaceAdmission,
        private PreviewProjectConflictsResponder $responder
    ) {
    }

    /**
     * Validates advisory reads without reservation or another Project's identity
     */
    #[OA\Post(
        path: '/api/v1/workspaces/{workspace_id}/projects/conflict-preview',
        operationId: 'previewProjectConflicts',
        summary: 'Preview active slug/source conflicts without reservation',
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceScope')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProjectInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Advisory bounded conflict kinds only',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectConflictPreviewSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ProjectRejected', response: 422),
            new OA\Response(ref: '#/components/responses/InternalError', response: 500)
        ]
    )]
    #[JsonBody]
    #[Validation(rules: [
        [
            'field' => 'display_name',
            'label' => 'Display name',
            'rules' => 'required|type[string]|min_length[1]|max_length[120]'
        ],
        ['field' => 'slug', 'label' => 'Slug', 'rules' => 'required|type[string]|min_length[1]|max_length[80]'],
        ['field' => 'primary_source', 'label' => 'Primary source', 'rules' => 'type[?object]'],
        ['field' => 'equivalent_sources', 'label' => 'Equivalent sources', 'rules' => 'type[array]']
    ])]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{display_name: string, slug: string} $input */
        $input = $request->getAttribute(JsonBody::class);
        $workspaceId = ProjectInput::workspace($request);
        $this->projectWorkspaceAdmission->require(ProjectInput::authentication($request), $workspaceId);

        return $this->responder->respond($this->queryBus->fetch(new PreviewProjectConflicts(
            $workspaceId,
            new ProjectMetadata($input['display_name'], $input['slug']),
            ProjectInput::sources($input)
        )));
    }
}
