<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class BrowserAuthControls
 *
 * Describes shared browser controls consumed by login and future refresh or logout operations
 */
#[OA\Parameter(
    parameter: 'BrowserAuthOrigin',
    name: 'Origin',
    in: 'header',
    required: true,
    description: 'Exact configured HTTPS scheme/host/effective port; absent/null/foreign origins are forbidden',
    schema: new OA\Schema(type: 'string')
)]
#[OA\Parameter(
    parameter: 'BrowserAuthCsrf',
    name: 'X-CSRF-Proof',
    in: 'header',
    required: true,
    description: <<<'TEXT'
        Exactly one unexpired nonce-bound proof from GET /api/v1/auth/csrf. Volatile memory only.
        Requires the matching browser-managed HttpOnly nonce cookie. Reusable until expiry;
        successful login rotates the nonce, refresh preserves it, terminal logout deletes it.
        Never send refresh credentials or CSRF alternatives in query, JSON or Authorization.
        TEXT,
    schema: new OA\Schema(type: 'string', maxLength: 75)
)]
#[OA\Schema(
    schema: 'BrowserAuthEmptyInput',
    description: 'Refresh/logout require exactly an empty JSON object, not an absent body or array',
    type: 'object',
    maxProperties: 0,
    additionalProperties: false
)]
#[OA\Response(
    response: 'BrowserAuthThrottled',
    description: <<<'TEXT'
        Generic refusal before authentication dispatch. Rolling 900-second network/endpoint admission;
        email LoginThrottle consumes at most five canonical-email attempts per rolling 900 seconds,
        independently committed irrespective of identity or login outcome. Outage denies.
        No email/network/credential data is exposed. This component does not register an operation.
        TEXT,
    headers: [new OA\Header(
        header: 'Retry-After',
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 900)
    )],
    content: new OA\JsonContent(ref: '#/components/schemas/Error')
)]
final class BrowserAuthControls
{
}
