# Safe API failures

TASK-00582 deliberately cuts the application and browser client over together. There is no old/new envelope
mode, diagnostic-code discriminator or permission to retry a mutation. Deploy PHP, its locked dependencies,
private exports and compiled client assets from the same revision. No database/schema change is required.
Independent Review and HTTP/browser/security QA remain separate from implementation and local checks.

## Qualified supplier and composition

The manifest selects Common `~1.4.1`; the lock selects **v1.4.1**, source/dist
`a482c220d312d2ef7762c3f0d85d9fb76a7021fa`. The remotely published non-prerelease tag, Composer distribution
and installed signatures are the authority, not the newer Common working checkout. Access Control remains
**v0.8.0**, source `319738255e682d587642b67856039f882b9588d6`; its Common `^1.3` requirement permits this
selection. No transitive package version changes or upstream edits/releases are part of this cutover.

Common's `SafeJSendErrorMiddleware`, `SafeHttpErrorPolicy`, `SafeHttpProjector`, `SafeHttpPresentation`,
`SafeHttpMetadata` and `SafeHttpDiagnostics` own the generic classification/render/fallback contracts.
The selected release exposes presentation/metadata properties; do not assume unreleased accessor methods.
`SafeHttpErrorPolicy(true)` deliberately permits validated Application retry hints. The response factory is
Common's `JSendResponseFactory` with the application's registered Slim PSR factories.

Slim invokes middleware last-in, first-out. `config/middleware.php` composes:

1. Sanitized, non-reporting Slim outer fallback.
2. `ApiResponseBoundary`: exact `/api` or `/api/` scope, validated/generated correlation, Common safe boundary,
   and final `Cache-Control: no-store` / `X-Correlation-ID` on successful and failed covered responses. A fresh
   per-invocation handler guard observes only the typed session-deletion obligation before Common catches it;
   any successful outer recovery then retains both deletion cookies. It neither classifies nor reports failures.
3. `NativeApiFailures`: only explicit supported Slim/package/owned Domain protocol translations.
4. Routing, then matched-Action security, declared-input validation, endpoint guards and Action/Responder.

Routing misses and method rejection are inside Common. A generic Slim `HttpException`, arbitrary numeric code,
message, cause, lookup exception or unknown PHP/storage fault does not acquire a public 4xx classification.
Domain exceptions remain Domain-owned. Known registration refusal and elevation-confirmation refusal have
explicit owner translations; malformed/unusable values are not automatically business-rule rejections.
Capability-specific query/result/admission corrections remain with TASK-00574–00581; shared imports do not
certify those unperformed corrections or supplier guarantees.

The maintained [thrown-failure flow](safe-http-errors/README.md) links the editable diagram, exact source revision,
source/arrow traces and explicit omissions. It illustrates this composition, not acceptance or browser readiness.

## Wire contract

| Outcome | Representation |
|---|---|
| Ordinary success | Existing `success` plus exact feature data |
| Expected 400/401/403/404/405/406/409/410/412/413/415/422/429 | `fail`, with `data.message` containing the fixed category message |
| Declared transport/public form validation (400) | `fail`, with bounded `data.fields` |
| Password policy or explicit elevation/registration rule refusal (422) | `fail`, with independently authored safe `data.fields` |
| Unknown/internal fault (500) | `error`, `message: "Internal server error."` |
| Declared temporary unavailability (503) | `error`, `message: "Service unavailable."` |
| Partial invitation (503) | `error`, fixed unconfirmed-completion message and safe inspected `data` |
| Uncertain invitation recovery (503) | `error`, fixed status-inspection guidance |
| Attempt to revoke current session (409) | `fail`, `data: {message: "Use Logout for the current session.", intent: "use_logout"}` |

The former top-level current-session diagnostic code is not public API. The client rejects that retired shape.
401 includes `WWW-Authenticate: Bearer`; 405 preserves the exact Slim allowed-method list. Only validated
nonnegative 429/503 retry delays become `Retry-After`. Native arbitrary exception headers are never forwarded.
A retry hint describes admission/availability only: it neither schedules client work nor proves rollback,
exactly-once execution, a safe replay, mail receipt or an uncommitted prior command.

`ApiInputValidation` keeps its declared field/source allowlists and public-form message projection. It never
publishes a raw validator map. Common public projections accept only independently selected plain JSON values,
not entities, resources, arbitrary objects or `JsonSerializable` callbacks. Private diagnostic text is not
made safe by membership in a recognized exception category.

Role mutations and owned-session revocation translate explicit package refusals into Common intent before the
sole generic boundary presents/reports them. Refresh/logout keep their transport effects in
`SessionLifecycleResponder`: recoverable result conflict changes no cookie; terminal refresh clears both
refresh/nonce identities and challenges authentication; logout clears them idempotently. A failure after Action
entry clears browser cookies while leaving server outcome unconfirmed. It uses the same diagnostics sink and
Common policy; unknown faults stay 500, declared availability may be 503. Direct rendering/cookie application has
one local, non-reporting attempt to render Common's fixed fallback with both deletion cookies. If that also fails,
`SessionResponseFailure` carries only the fixed sanitized escape and the required deletion meaning, not a private
cause. The API boundary's invocation-local guard observes this narrow signal before rethrowing it to Common.
After any successful Common recovery, the boundary adds both exact deletions, regardless of which bounded
rendering attempt succeeds. No extra retry is added. Reporter failure does not consume local rendering recovery.
Terminal/logout rendering faults enter the same clearing path. The guard is created for each invocation and is
not stored in a shared service, static variable or request registry; return and escape cannot leak its flag into
a subsequent request. If Common cannot create a response within its existing bounds, escape remains sanitized,
without a response guarantee. Common may offer the distinct sanitized signal once, never the original failure again.
Admission-time 429/503 occurs before Action entry and does not silently clear cookies. A response cannot prove
a prior effect rolled back.

## Diagnostics and fallback limits

`RedactedHttpDiagnostics` is the single sink. Common offers each caught failure once. Expected 4xx filtering
happens inside this sink. Only a fixed log message, classified status and bounded validated exception type reach
logger processors/handlers/formatters; anonymous/path-bearing class names become `Throwable`. No raw exception,
request, URL, input, credential, cause, trace, validator map or diagnostic message is PSR-3 context. Correlation
is independently validated at the HTTP boundary; it is not an authorization or idempotency key.

A failed sink is not successful delivery. Common does not recursively report reporter/projector failures and
uses its fixed 500 fallback. The API wrapper still adds correlation/no-store to a successfully created fallback.
If PSR response/stream creation is broken, Common throws a sanitized exception without the private cause. A
valid/correlated response is then not guaranteed. Non-API paths, startup/container construction, failures before
this scope and the final broken-factory escape are not covered by the API response guarantee. Slim's outer
fallback is sanitized and non-reporting; it is not a second diagnostics owner.

## Client and operations

`decodeResponse` requires exact envelope/status/data agreement, rejects legacy 4xx `error` payloads and never
returns arbitrary server display messages. Feature copy remains client-owned. Validation details are bounded
and displayed only through the matching public form allowlist. Safe partial invitation facts are typed and
retained as uncertainty, not replay authority. Unavailable, precondition, rule and uncertain results retain their
own meanings; the principal loader treats unavailable as a non-authoritative network-style failure.
Existing session, form-generation and authority fences remain in force. No new endpoint or permission is added.

Export explicitly with `./bin/openapi export` and `./bin/validations export`; build the live client with
`./bin/client build`. Checks never install/export. Use focused owning checks, then normal `./bin/build` on
frozen inputs. The TASK and canonical ignored Work handoff own actual counts, warnings, runtime/ingress state
and verification gaps; this document does not certify browser trust, production ingress, MCP/OAuth/HMAC,
independent acceptance or deployment.
