<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class LoginInput
 *
 * Describes the exact public login JSON shape independently of future refresh inputs
 */
#[OA\Schema(
    schema: 'BrowserAuthLoginInput',
    description: 'Canonical login input contract; bounded unique-key UTF-8 JSON only',
    required: ['email', 'password', 'remember'],
    properties: [
        new OA\Property(
            property: 'email',
            description: 'Canonical email',
            type: 'string',
            format: 'email',
            minLength: 1,
            maxLength: 254,
            writeOnly: true
        ),
        new OA\Property(property: 'password', type: 'string', minLength: 1, maxLength: 128, writeOnly: true),
        new OA\Property(property: 'remember', type: 'boolean')
    ],
    type: 'object',
    additionalProperties: false
)]
final class LoginInput
{
}
