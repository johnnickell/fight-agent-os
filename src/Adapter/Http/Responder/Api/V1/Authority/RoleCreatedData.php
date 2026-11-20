<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Authority;

use Fight\AccessControl\Domain\AccessControl\Role\RoleId;
use Fight\Common\Domain\Type\Arrayable;
use OpenApi\Attributes as OA;

/**
 * Class RoleCreatedData
 *
 * Presents the identity supplied to Role creation
 */
#[OA\Schema(
    schema: 'RoleCreatedSuccess',
    type: 'object',
    additionalProperties: false,
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['success']),
        new OA\Property(
            property: 'data',
            type: 'object',
            additionalProperties: false,
            required: ['role_id'],
            properties: [new OA\Property(property: 'role_id', type: 'string', format: 'uuid')]
        )
    ]
)]
final readonly class RoleCreatedData implements Arrayable
{
    /**
     * Constructs RoleCreatedData
     */
    public function __construct(private RoleId $roleId)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['role_id' => $this->roleId->toString()];
    }
}
