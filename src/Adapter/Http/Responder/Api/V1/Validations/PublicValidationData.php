<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Validations;

use Fight\Common\Domain\Type\Arrayable;

/**
 * Class PublicValidationData
 *
 * Maps one validated catalog entry to its safe response fields
 */
final readonly class PublicValidationData implements Arrayable
{
    /**
     * Constructs PublicValidationData
     *
     * @param array{schema_version: int, revision: string, form_name: string, fields: array<string, mixed>} $schema
     */
    public function __construct(private array $schema)
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->schema;
    }
}
