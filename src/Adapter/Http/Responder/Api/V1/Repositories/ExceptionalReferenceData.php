<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class ExceptionalReferenceData
 */
#[OA\Schema(
    schema: 'ExceptionalReferenceSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            additionalProperties: false,
            required: ['reference', 'reserved'],
            properties: [
                new OA\Property(property: 'reference', type: 'string', format: 'uuid'),
                new OA\Property(property: 'reserved', type: 'boolean', enum: [false])
            ]
        )
    ]
)]
final readonly class ExceptionalReferenceData implements Arrayable
{
    /**
     * Constructs ExceptionalReferenceData
     */
    public function __construct(private string $reference)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['reference' => $this->reference, 'reserved' => false];
    }
}
