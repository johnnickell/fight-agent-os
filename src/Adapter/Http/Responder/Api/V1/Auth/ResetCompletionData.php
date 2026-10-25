<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class ResetCompletionData
 *
 * Exposes only completion, with no account, grant or session material
 */
#[OA\Schema(
    schema: 'ResetCompletionData',
    required: ['completed'],
    properties: [new OA\Property(property: 'completed', type: 'boolean', enum: [true])],
    type: 'object',
    additionalProperties: false
)]
final readonly class ResetCompletionData implements Arrayable
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['completed' => true];
    }
}
