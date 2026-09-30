<?php

declare(strict_types=1);

namespace App\Adapter\Http\Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Class Contract
 *
 * Describes shared transport contracts without advertising additional operations
 */
#[OA\OpenApi(
    openapi: '3.0.3',
    security: [],
    x: [
        'future-browser-security' => [
            'status'    => 'Accepted ADR 0003 policy, not implemented authentication operations',
            'refresh'   => <<<'TEXT'
                Refresh credentials are opaque package-generated 256-bit values, not JWTs or Bearer
                tokens. Only a host-only __Secure- prefixed HttpOnly, Secure, SameSite=Strict cookie
                at Path=/api/v1/auth may transport them. Its exact name is not selected here.
                No Domain; ordinary cookie is session-only; remembered expiry cannot exceed the
                server absolute deadline. Never JSON, JavaScript, URL, header credential or example.
                No cookie apiKey scheme is offered for manual credential entry in documentation UI.
                TEXT,
            'mutations' => <<<'TEXT'
                Future login/refresh/logout require exact trusted HTTPS Origin, same-origin Fetch
                Metadata when present, an explicit CSRF header with the nonce-bound unexpired proof,
                and bounded application/json objects. Bootstrap does not enforce future mutations.
                Future access credentials and CSRF proofs stay memory-only; responses are no-store.
                TEXT
        ],
        'mapper-only-failures'    => [
            'description' => <<<'TEXT'
                Existing failure classification, NOT advertised bootstrap outcomes or new operations.
                Transport ValidationException -> 400 ValidationFail (body: Invalid input.);
                Domain ValidationException -> 422 ValidationFail (input: Invalid value.);
                AuthenticationRequired -> 401 Error (Unauthorized.);
                PermissionDenied -> 403 Error (Forbidden.);
                StateConflict/OptimisticConcurrencyException -> 409 Error (Conflict.);
                ResourceNotFound -> 404 Error (Not found.);
                framework equivalents plus 410 Gone. and 429 Too many requests. use Error.
                Unknown exceptions, including generic LookupException, become generic 500.
                TEXT
        ]
    ]
)]
#[OA\Info(
    version: '0.1.0',
    title: 'Fight Agent OS representative API',
    description: <<<'TEXT'
        Only GET /api/v1/auth/csrf is implemented here. This document is repository-only;
        no OpenAPI, Swagger UI or diagnostic route is enabled in any environment.
        JSON responses use exactly application/json, JSend and snake_case data fields.
        Safe correlation is exposed only in X-Correlation-ID, not in the JSON envelope.
        No login, refresh, logout, invitation, authenticated resource or persistence API
        is implied by the reusable schemas or the future Bearer scheme.
        CORS is absent: no approved cross-origin client or credentialed preflight exists.
        See planning/adr/0003-browser-authentication-security-profile.md in this repository.
        TEXT
)]
#[OA\Server(url: '/', description: 'Same-origin HTTPS only; deployment must configure its exact trusted origin')]
#[OA\SecurityScheme(
    securityScheme: 'FutureAccessBearer',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: <<<'TEXT'
        FUTURE ONLY, not applied to bootstrap. One Authorization Bearer access token,
        held only in volatile memory, never URL/body/cookie or persistent browser storage.
        ADR 0003 requires HS256, full header/claim/key/time validation and authoritative
        user/session/version resolution. Those consumers are not yet implemented.
        TEXT
)]
#[OA\Header(header: 'NoStore', required: true, schema: new OA\Schema(type: 'string', enum: ['no-store']))]
#[OA\Header(
    header: 'CorrelationId',
    required: true,
    description: 'Safe client-supplied or generated correlation, exposed in the header only',
    schema: new OA\Schema(type: 'string', pattern: '^[a-f0-9]{32}$')
)]
final class Contract
{
}
