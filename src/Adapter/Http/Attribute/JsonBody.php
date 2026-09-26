<?php

declare(strict_types=1);

namespace App\Adapter\Http\Attribute;

use Attribute;

/**
 * Class JsonBody
 *
 * Declares the transport DTO for a JSON-object request body
 *
 * Constructor parameters are mapped from camelCase to snake_case input fields. Only
 * explicitly declared primitive types are accepted; nested values require a
 * separately reviewed contract on the first real body-bearing interaction.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class JsonBody
{
    /**
     * Constructs JsonBody
     *
     * @param class-string $dto
     */
    public function __construct(public string $dto)
    {
    }
}
