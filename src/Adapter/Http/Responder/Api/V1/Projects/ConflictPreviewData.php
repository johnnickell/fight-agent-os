<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Projects;

use App\Application\Workspace\Project\View\ProjectConflicts;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class ConflictPreviewData
 */
#[OA\Schema(
    schema: 'ProjectConflictPreviewSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            additionalProperties: false,
            required: ['conflicts'],
            properties: [
                new OA\Property(
                    property: 'conflicts',
                    type: 'array',
                    maxItems: 2,
                    uniqueItems: true,
                    items: new OA\Items(type: 'string', enum: ['slug', 'source'])
                )
            ]
        )
    ]
)]
final readonly class ConflictPreviewData implements Arrayable
{
    /**
     * Constructs ConflictPreviewData
     */
    public function __construct(private ProjectConflicts $projectConflicts)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['conflicts' => [
            ...($this->projectConflicts->slugClaimed ? ['slug'] : []),
            ...($this->projectConflicts->sourceClaimed ? ['source'] : [])
        ]];
    }
}
