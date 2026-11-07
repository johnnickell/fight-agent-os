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
            'status'    => 'Reusable TASK-00045 controls implemented; login/refresh/logout operations remain absent',
            'refresh'   => <<<'TEXT'
                Refresh credentials are opaque package-generated 256-bit values, not JWTs or Bearer
                tokens. Only a host-only __Secure- prefixed HttpOnly, Secure, SameSite=Strict cookie
                at Path=/api/v1/auth may transport them. The shared factory selects __Secure-agent_os_refresh.
                No Domain; ordinary cookie is session-only; remembered expiry cannot exceed the
                server absolute deadline. Never JSON, JavaScript, URL, header credential or example.
                No cookie apiKey scheme is offered for manual credential entry in documentation UI.
                TEXT,
            'mutations' => <<<'TEXT'
                Reusable login/refresh/logout guards require exact trusted HTTPS Origin, same-origin Fetch
                Metadata when present, an explicit CSRF header with the nonce-bound unexpired proof,
                and unique-key UTF-8 application/json objects bounded to 65536 bytes. Shared components
                describe input contracts, not registered operations. Direct TLS authority only; no
                forwarded-header promotion. Rolling persistent email/network/endpoint limits deny on outage.
                Future access credentials and CSRF proofs stay memory-only; responses are no-store.
                TEXT
        ],
        'safe-failures'           => [
            'description' => <<<'TEXT'
                Qualified Common safe HTTP policy owns generic classification and fallback.
                Expected 4xx categories use fail with fixed data.message; structured validation preserves data.fields.
                AuthenticationRequired -> 401 with WWW-Authenticate: Bearer; permission denial -> 403;
                required absence -> 404; explicit conflict -> 409; rule refusal -> 422; rate limiting -> 429.
                Native method rejection preserves exact Allow. Only validated 429/503 hints become Retry-After.
                Unknown faults remain 500 error. Declared availability is 503 error.
                Partial invitation retains safe inspected data.
                Current-session revocation uses explicit use_logout intent, never a diagnostic exception code.
                Correlation/no-store apply throughout the API scope. No retry hint implies replay or rollback.
                TEXT
        ]
    ]
)]
#[OA\Info(
    version: '0.1.0',
    title: 'Fight Agent OS representative API',
    description: <<<'TEXT'
        GET /api/v1/auth/csrf, GET /api/v1/validations/{form_name} and GET /api/v1/me
        are implemented here. The first two reads are public; /me requires current Bearer
        authority and projects safe identity and current role/permission names without credentials.
        The validation catalog is explicitly
        allowlisted and does not authorize any form operation. This document is repository-only;
        no OpenAPI, Swagger UI or diagnostic route is enabled in any environment.
        JSON responses use exactly application/json, JSend and snake_case data fields.
        Safe correlation is exposed only in X-Correlation-ID, not in the JSON envelope.
        No login, refresh, logout, invitation or other resource/persistence API
        is implied by the reusable schemas or the reusable Bearer scheme.
        CORS is absent: no approved cross-origin client or credentialed preflight exists.
        See planning/adr/0003-browser-authentication-security-profile.md in this repository.
        TEXT
)]
#[OA\Server(url: '/', description: 'Same-origin HTTPS only; deployment must configure its exact trusted origin')]
#[OA\SecurityScheme(
    securityScheme: 'AccessBearer',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: <<<'TEXT'
        Authentication boundary applied to GET /api/v1/me, not to approved public reads.
        Exactly one Authorization: Bearer compact JWT, at most 2048 token
        bytes; no repeated/comma-folded headers, extra whitespace or alternative query/body/cookie
        credentials. Access tokens stay in volatile memory, never persistent browser storage.
        Pinned HS256 with configured kid and finite prior-key overlap; exact issuer, single audience,
        subject/session/JTI/type/version and integer timestamps. Lifetime is 900 seconds with
        30 seconds future-only tolerance and no expiry grace. Each request resolves current
        active user, owned usable session and matching versions from PostgreSQL. JWTs contain
        no roles or permissions. Refusals are generic correlated no-store 401 responses.
        Protected JSON request bodies are bounded to 16 KiB and must be seekable.
        Opaque refresh cookies are not JWTs or Bearer credentials.
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
