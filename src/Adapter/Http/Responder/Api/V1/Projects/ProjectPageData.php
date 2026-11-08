<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Application\Workspace\Project\View\ProjectPage;
use App\Application\Workspace\Project\View\ProjectView;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class ProjectPageData
 */
#[OA\Schema(
    schema: 'ProjectPageSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            additionalProperties: false,
            required: ['items', 'has_more', 'offset', 'limit'],
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    maxItems: 100,
                    items: new OA\Items(ref: '#/components/schemas/Project')
                ),
                new OA\Property(property: 'has_more', type: 'boolean'),
                new OA\Property(property: 'offset', type: 'integer', minimum: 0, maximum: 100000),
                new OA\Property(property: 'limit', type: 'integer', minimum: 1, maximum: 100)
            ]
        )
    ]
)]
final readonly class ProjectPageData implements Arrayable
{
    /**
     * Constructs ProjectPageData
     */
    public function __construct(private ProjectPage $projectPage)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'items'    => array_map(
                static fn(ProjectView $view): array => (new ProjectData($view))->toArray(),
                $this->projectPage->items
            ),
            'has_more' => $this->projectPage->hasMore,
            'offset'   => $this->projectPage->offset,
            'limit'    => $this->projectPage->limit
        ];
    }
}
