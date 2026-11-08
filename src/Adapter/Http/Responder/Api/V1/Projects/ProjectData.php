<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Application\Workspace\Project\View\ProjectView;
use App\Domain\Workspace\Project\SourceDescriptor;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class ProjectData
 *
 * Owns the HTTP projection of safe current Project facts
 */
#[OA\Schema(
    schema: 'ProjectSourceInput',
    type: 'object',
    required: ['type', 'locator'],
    additionalProperties: false,
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['git_network_v1', 'github_repository_v1']),
        new OA\Property(property: 'locator', type: 'string', minLength: 1, maxLength: 2048)
    ]
)]
#[OA\Schema(
    schema: 'ProjectSource',
    oneOf: [
        new OA\Schema(
            type: 'object',
            additionalProperties: false,
            required: ['type', 'authority', 'owner', 'repository'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['github_repository_v1']),
                new OA\Property(property: 'authority', type: 'string', enum: ['github.com']),
                new OA\Property(property: 'owner', type: 'string'),
                new OA\Property(property: 'repository', type: 'string')
            ]
        ),
        new OA\Schema(
            type: 'object',
            additionalProperties: false,
            required: ['type', 'transport', 'host', 'port', 'username', 'path_mode', 'path'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['git_network_v1']),
                new OA\Property(property: 'transport', type: 'string', enum: ['https', 'ssh']),
                new OA\Property(property: 'host', type: 'string'),
                new OA\Property(property: 'port', type: 'integer', minimum: 1, maximum: 65535),
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'path_mode', type: 'string', enum: ['absolute', 'home_relative']),
                new OA\Property(property: 'path', type: 'string')
            ]
        )
    ]
)]
#[OA\Schema(
    schema: 'ProjectInput',
    type: 'object',
    additionalProperties: false,
    required: ['display_name', 'slug'],
    properties: [
        new OA\Property(property: 'display_name', type: 'string', minLength: 1, maxLength: 120),
        new OA\Property(
            property: 'slug',
            type: 'string',
            minLength: 1,
            maxLength: 80,
            pattern: '^[a-z0-9]+(-[a-z0-9]+)*$'
        ),
        new OA\Property(
            property: 'primary_source',
            type: 'object',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/ProjectSourceInput')]
        ),
        new OA\Property(
            property: 'equivalent_sources',
            type: 'array',
            maxItems: 7,
            items: new OA\Items(ref: '#/components/schemas/ProjectSourceInput')
        )
    ]
)]
#[OA\Schema(
    schema: 'Project',
    type: 'object',
    additionalProperties: false,
    required: [
        'workspace_id', 'project_id', 'display_name', 'slug', 'lifecycle', 'primary_source', 'equivalent_sources',
        'metadata_revision', 'source_revision', 'lifecycle_revision', 'actor_type', 'created_by', 'updated_by',
        'created_at', 'updated_at'
    ],
    properties: [
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'display_name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'lifecycle', type: 'string', enum: ['active']),
        new OA\Property(
            property: 'primary_source',
            type: 'object',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/ProjectSource')]
        ),
        new OA\Property(
            property: 'equivalent_sources',
            type: 'array',
            maxItems: 7,
            items: new OA\Items(ref: '#/components/schemas/ProjectSource')
        ),
        new OA\Property(property: 'metadata_revision', type: 'integer', minimum: 1),
        new OA\Property(property: 'source_revision', type: 'integer', minimum: 1),
        new OA\Property(property: 'lifecycle_revision', type: 'integer', minimum: 1),
        new OA\Property(property: 'actor_type', type: 'string', enum: ['user']),
        new OA\Property(property: 'created_by', type: 'string', format: 'uuid'),
        new OA\Property(property: 'updated_by', type: 'string', format: 'uuid'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
    ]
)]
#[OA\Schema(
    schema: 'ProjectSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(property: 'data', ref: '#/components/schemas/Project')
    ]
)]
#[OA\Response(
    response: 'ProjectRejected',
    description: 'Invalid metadata, source evidence, pagination or identity',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationFail')
)]
#[OA\Response(
    response: 'ProjectConflict',
    description: 'Recognized active slug/source claim conflict without another Project identity',
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final readonly class ProjectData implements Arrayable
{
    /**
     * Constructs ProjectData
     */
    public function __construct(private ProjectView $projectView)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $view = $this->projectView;

        return [
            'workspace_id'       => $view->workspaceId->toString(),
            'project_id'         => $view->projectId->toString(),
            'display_name'       => $view->displayName,
            'slug'               => $view->slug,
            'lifecycle'          => 'active',
            'primary_source'     => $view->primarySource?->toArray(),
            'equivalent_sources' => array_map(
                static fn(SourceDescriptor $source): array => $source->toArray(),
                $view->equivalentSources
            ),
            'metadata_revision'  => $view->metadataRevision,
            'source_revision'    => $view->sourceRevision,
            'lifecycle_revision' => $view->lifecycleRevision,
            'actor_type'         => 'user',
            'created_by'         => $view->createdBy->toString(),
            'updated_by'         => $view->updatedBy->toString(),
            'created_at'         => $view->createdAt->format('Y-m-d\TH:i:s.uP'),
            'updated_at'         => $view->updatedAt->format('Y-m-d\TH:i:s.uP')
        ];
    }
}
