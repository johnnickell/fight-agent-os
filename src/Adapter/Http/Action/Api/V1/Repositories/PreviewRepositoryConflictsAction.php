<?php

declare(strict_types=1);

namespace App\Adapter\Http\Action\Api\V1\Repositories;

use App\Adapter\Http\Api\V1\Repositories\RegistrationInput;
use App\Adapter\Http\Attribute\JsonBody;
use App\Adapter\Http\Attribute\RequiresPermission;
use App\Adapter\Http\Responder\Api\V1\Repositories\PreviewRepositoryConflictsResponder;
use App\Application\Workspace\PreviewRepositoryConflicts;
use App\Domain\Workspace\RegistrationMetadata;
use Fight\Common\Application\Attribute\Validation;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class PreviewRepositoryConflictsAction
 */
#[RequiresPermission('READ_REPOSITORY_REGISTRATIONS')]
final readonly class PreviewRepositoryConflictsAction
{
    /**
     * Constructs PreviewRepositoryConflictsAction
     */
    public function __construct(
        private PreviewRepositoryConflicts $conflicts,
        private PreviewRepositoryConflictsResponder $responder
    ) {
    }

    /**
     * Maps a body-bearing read-only preview without reserving claims or emitting events
     */
    #[OA\Post(
        path: '/api/v1/workspaces/{workspace_id}/repositories/conflict-preview',
        operationId: 'previewRepositoryConflicts',
        summary: 'Preview active slug and explicit source conflicts without reserving anything',
        security: [['AccessBearer' => []]],
        parameters: [new OA\Parameter(ref: '#/components/parameters/RegistrationWorkspace')],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                additionalProperties: false,
                required: ['display_name', 'slug'],
                properties: [
                    new OA\Property(property: 'display_name', type: 'string', minLength: 1, maxLength: 120),
                    new OA\Property(property: 'slug', type: 'string', minLength: 1, maxLength: 80),
                    new OA\Property(
                        property: 'primary_source',
                        type: 'object',
                        nullable: true,
                        allOf: [new OA\Schema(ref: '#/components/schemas/RepositorySourceInput')]
                    ),
                    new OA\Property(
                        property: 'equivalent_sources',
                        type: 'array',
                        maxItems: 7,
                        items: new OA\Items(ref: '#/components/schemas/RepositorySourceInput')
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Advisory bounded conflict kinds, never hidden registrations',
                content: new OA\JsonContent(ref: '#/components/schemas/RepositoryConflictPreviewSuccess')
            ),
            new OA\Response(ref: '#/components/responses/PublicValidationBadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/RegistrationRejected', response: 422),
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

        return $this->responder->respond($this->conflicts->preview(
            RegistrationInput::authentication($request),
            RegistrationInput::workspace($request),
            new RegistrationMetadata($input['display_name'], $input['slug']),
            RegistrationInput::sources($input)
        ));
    }
}
