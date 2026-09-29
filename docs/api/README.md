# Representative API contract

[`openapi.yaml`](openapi.yaml) is the authoritative representative transport description, in OpenAPI 3.0.3
YAML. It documents **only `GET /api/v1/auth/csrf`** as an operation. Reusable routing responses and existing
failure-mapper schemas are not endpoints. The version labels this document, not an authentication release.

## Validate locally

After `./bin/composer install` and `./bin/up`:

```sh
./bin/openapi
./bin/build
```

`bin/openapi` runs the Composer-pinned `devizzent/cebe-php-openapi` **1.1.5** structural/reference/schema
validator, followed by focused application HTTP and failure-mapper tests. `league/openapi-psr7-validator`
**0.24** compares real PSR-7 responses with the operation and reusable schemas. All transitive versions are
locked in `composer.lock`. Both tools are development dependencies, never runtime middleware. YAML was chosen
for readable review; OpenAPI 3.0.3 matches this validator's supported 3.0 family rather than claiming 3.1 support.
References are document-local and validator meta-schemas ship with the locked package: no network is needed
after installation. No generated spec or remotely fetched examples are involved.

The drift tests exercise issuance, nonce reuse/replacement, body/content-type/cookie/query rejection,
HTTPS/origin/Fetch Metadata rejection, preflight denial, routing misses and a failed dependency through the
real bootstrap. They check exact content type, no-store, safe correlation and absent CORS separately from
schema validation. Mapper-only output is checked at its existing unit boundary. Negative evidence is actual
application rejection/failure, **not** deliberately corrupted specs or tests of the validator.

`./bin/build` already runs these behavioral tests through PHPUnit. Standalone document validation remains an
explicit `./bin/openapi` requirement until TICKET-00013's complete-gate integration. Do not substitute one for
the other. Tests of scripts, YAML prose, configuration text or the validator are intentionally absent.

## Exposure and diagnostics policy

The publication is the tracked repository document, **not an HTTP route**. No spec copy exists in `public/`;
there is no Swagger dependency, CDN asset, UI route, enabling flag, authorization bypass or diagnostic route.
Swagger UI is unavailable in **development, test and production**, including to authenticated callers. An
optional hosted viewer was not needed for this representative slice; enabling one later requires an explicit
environment allowlist and approved access control. Repository read access is the only documentation access
policy today. Do not submit real tokens or cookies to an external viewer.

Unknown documentation/diagnostic URLs receive safe routing failures. Non-API exceptions use Slim's
non-diagnostic fallback without raw exception logging; existing API failures keep their correlated sanitized
JSend boundary. The public entry point suppresses PHP error display before application boot. This fixes an
observed `/swagger` miss that previously emitted an uncaught stack and internal paths. It is not a new web
logging/observability system or proof of deployment enrollment. CORS remains absent, with no approved
cross-origin contract and no credentialed preflight.

## Current versus future security

Bootstrap accepts no body, Content-Type or query. It creates/reuses an HttpOnly nonce cookie and returns a
short-lived MAC proof, not authentication. Its Origin header is optional but exact when present; Fetch Metadata
is optional but must be `same-origin` when present. These are current GET semantics, not mutation exemptions.

The unused `FutureAccessBearer` scheme describes **memory-only access-token transport**. Opaque refresh
credentials are instead browser-managed HttpOnly cookies; their eventual exact cookie name and operations are
not invented here. `x-future-browser-security` records the accepted cookie and mutation semantics from
[ADR 0003](../../planning/adr/0003-browser-authentication-security-profile.md). There is intentionally no cookie
`apiKey` scheme inviting users to type refresh credentials. No credential examples are published.

Dotted snake_case validation paths are supported by the existing DTO boundary and described in the generic
`ValidationFail` dictionary; OpenAPI 3.0 cannot constrain dictionary key names. Bootstrap narrows this to `body`
or `cookie`. No real body-bearing operation exists yet, so dotted-field HTTP matrices and authenticated
401/409/422 journeys remain deferred to their owning tasks. Schema checks do not prove MAC correctness,
server authorization or a complete authentication journey; existing focused security tests cover CSRF policy.
