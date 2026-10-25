<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Auth;

use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class PasswordChangeData
 *
 * Exposes completion only, never replacement authority
 */
#[OA\Schema(
    schema: 'PasswordChangeData',
    required: ['changed'],
    properties: [new OA\Property(property: 'changed', type: 'boolean', enum: [true])],
    type: 'object',
    additionalProperties: false
)]
final readonly class PasswordChangeData implements Arrayable
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['changed' => true];
    }
}
