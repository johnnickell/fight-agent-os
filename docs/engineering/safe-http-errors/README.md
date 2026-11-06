# Thrown API failure flow

**Existing implementation**, source commit **`1a35aff126aa0f848648c43cc5d43471f8452da5`** (image label
`1a35aff1`). [Editable Python source](api-failure-flow.py); [owning contract](../SAFE_HTTP_ERRORS.md).

![Existing thrown API failure flow](api-failure-flow.png)

Legend: dotted directed edges are numbered runtime interactions, not static dependency arrows or transaction
boundaries. Indirect hops are explicitly compressed. No color conveys authority or acceptance.

## Source and arrow traces

All Agent OS paths below refer to the full source commit above; `git show SOURCE_SHA:path` retrieves them.
Common paths refer to the locked v1.4.1 source `a482c220d312d2ef7762c3f0d85d9fb76a7021fa`.

| Steps / endpoints | Source / inspected meaning |
|---|---|
| 1–2 request → scope → Common | [`ApiResponseBoundary::process`](../../../src/Adapter/Http/Middleware/Api/ApiResponseBoundary.php) selects exact API scope, validates/generates correlation and calls the injected Common middleware |
| 3–4 downstream / translated throw | [`config/middleware.php`](../../../config/middleware.php), [`NativeApiFailures::process`](../../../src/Adapter/Http/Middleware/Api/NativeApiFailures.php) and [`API routes`](../../../config/routes/01-api-v1.php): Slim LIFO includes routing/security/validation/Actions inside the catch. Explicit native/package refusals translate; unknown faults remain unknown |
| 5 Common → diagnostics | Common `src/Adapter/Middleware/Psr15/SafeJSendErrorMiddleware.php::process` offers the caught failure once. [`RedactedHttpDiagnostics::report`](../../../src/Adapter/Http/Api/Failure/RedactedHttpDiagnostics.php) filters expected refusals and redacts before any logger formatter |
| 6 Common → policy/projector | Common `src/Adapter/Http/Error/SafeHttpErrorPolicy.php::{status,present,fallback}` owns generic categories/fallback. [`PublicFailureProjector::project`](../../../src/Adapter/Http/Api/Failure/PublicFailureProjector.php) supplies independently authored public fields, intent and protocol metadata |
| 7–8 Common → factory → adapter return | Common `src/Adapter/Http/Psr17/JSendResponseFactory.php::fromEnvelope`; scope middleware adds no-store/correlation after the successfully created response returns through Common |
| 9 response → client | [`ApiClient`](../../../client/src/api/ApiClient.ts) and [`decodeResponse`](../../../client/src/api/decodeResponse.ts) enforce the exact current envelope/status/data contract, retaining only declared typed data and no automatic mutation retry |

Composition is shared through [`http_failures.php`](../../../config/application/http_failures.php). The local
relative links navigate current source; the source commit above pins this diagram's actual inspected snapshot.
Behavioral evidence is in the TASK/Work handoff, not inferred from these arrows.

## Scope and omissions

This picture shows the **thrown** API failure path, not the complete class graph, HTTP deployment, bus internals,
Doctrine transaction, success path or independent acceptance. The owning contract documents the direct
refresh/logout cookie-effects branch: `SessionLifecycleResponder` uses the same sink and Common policy/fallback,
then `SessionResponder::clear` preserves exact cookie deletion. Direct safe validation/CSRF responses are not
invented Throwables. Role/session-revocation package failures are translated into Common intent before this
shown generic boundary. Startup/non-API/broken-factory escapes do not gain a valid response guarantee.

The source establishes calls/ownership and Slim registration order. Functional/integration checks separately
exercise covered behavior; the diagram does not prove security, exactly-once delivery, rollback, browser trust
or production readiness. Canonical local :443/browser qualification is currently held on privileged Herd
configuration test/reload; supplier-only verified TLS probes are not browser acceptance.
