<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class CatalogControls
 */
#[OA\Parameter(
    parameter: 'CatalogPage',
    name: 'page',
    in: 'query',
    schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000, default: 1)
)]
#[OA\Parameter(
    parameter: 'CatalogPerPage',
    name: 'per_page',
    in: 'query',
    schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 25)
)]
final class CatalogControls
{
}
