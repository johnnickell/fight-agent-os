<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class BrowserAuthEmptyInput
 *
 * Describes the exact empty JSON object required by refresh and logout
 */
#[OA\Schema(
    schema: 'BrowserAuthEmptyInput',
    description: 'Exactly an empty JSON object, never an absent body or array',
    type: 'object',
    maxProperties: 0,
    additionalProperties: false
)]
final class BrowserAuthEmptyInput
{
}
