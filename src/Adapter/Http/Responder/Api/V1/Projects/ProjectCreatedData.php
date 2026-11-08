<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Domain\Workspace\Project\ProjectId;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class ProjectCreatedData
 */
#[OA\Schema(
    schema: 'ProjectCreatedSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            additionalProperties: false,
            required: ['project_id'],
            properties: [new OA\Property(property: 'project_id', type: 'string', format: 'uuid')]
        )
    ]
)]
final readonly class ProjectCreatedData implements Arrayable
{
    /**
     * Constructs ProjectCreatedData
     */
    public function __construct(private ProjectId $projectId)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['project_id' => $this->projectId->toString()];
    }
}
