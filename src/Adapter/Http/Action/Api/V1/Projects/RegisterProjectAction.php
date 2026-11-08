<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Projects;

use App\Adapter\Http\Api\V1\Projects\ProjectInput;
use App\Adapter\Http\Api\V1\Projects\ProjectWorkspaceAdmission;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Projects\RegisterProjectResponder;
use App\Domain\Workspace\Project\Command\RegisterProject;
use App\Domain\Workspace\Project\ProjectId;
use App\Domain\Workspace\Project\ProjectMetadata;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Messaging\Command\CommandBus;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RegisterProjectAction
 */
#[RequiresPermission('REGISTER_PROJECTS')]
final readonly class RegisterProjectAction
{
    /**
     * Constructs RegisterProjectAction
     */
    public function __construct(
        private CommandBus $commandBus,
        private ProjectWorkspaceAdmission $projectWorkspaceAdmission,
        private RegisterProjectResponder $responder
    ) {
    }

    /**
     * Validates the request and generates the Project identity before void dispatch
     */
    #[OA\Post(
        path: '/api/v1/workspaces/{workspace_id}/projects',
        operationId: 'registerProject',
        summary: 'Register one Project without physical or execution authority',
        description: <<<'TEXT'
            Requires REGISTER_PROJECTS and current initial Workspace administrator scope. After response loss,
            a new create may conflict; reconcile through fresh independently authorized list/detail reads.
            No replay guarantee.
            TEXT,
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/WorkspaceScope')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProjectInput')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Committed Project identity known before dispatch',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectCreatedSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ProjectConflict', response: 409),
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
        $actorId = $this->projectWorkspaceAdmission->require(ProjectInput::authentication($request), $workspaceId);
        $projectId = ProjectId::generate();
        $this->commandBus->execute(new RegisterProject(
            $projectId,
            $workspaceId,
            $actorId,
            new ProjectMetadata($input['display_name'], $input['slug']),
            ProjectInput::sources($input)
        ));

        return $this->responder->respond($projectId);
    }
}
