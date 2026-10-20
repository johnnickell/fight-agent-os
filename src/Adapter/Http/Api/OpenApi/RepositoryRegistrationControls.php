<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class RepositoryRegistrationControls
 *
 * Declares shared registration scope and bounded exceptional-reference admission
 */
#[OA\Parameter(
    parameter: 'RegistrationWorkspace',
    name: 'workspace_id',
    in: 'path',
    description: 'Exact explicitly bootstrapped initial Workspace, never a client authority claim',
    required: true,
    schema: new OA\Schema(type: 'string', format: 'uuid')
)]
#[OA\Response(
    response: 'ReferenceThrottled',
    description: 'Exceptional-reference actor budget or bounded admission capacity exhausted',
    headers: [
        new OA\Header(header: 'Retry-After', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 60)),
        new OA\Header(ref: '#/components/headers/NoStore', header: 'Cache-Control'),
        new OA\Header(ref: '#/components/headers/CorrelationId', header: 'X-Correlation-ID')
    ],
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final readonly class RepositoryRegistrationControls
{
}
