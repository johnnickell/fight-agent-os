---
id: TICKET-00010
epic: EPIC-00003
title: Establish safe versioned HTTP API delivery
status: ready-for-agent
---

# Establish safe versioned HTTP API delivery

## Problem statement

Feature Actions need a single versioned, validated, documented, and sanitized transport boundary. Ad hoc response mapping or exception handling would leak internals, blur command/query responsibilities, and force authentication work to redesign the HTTP stack.

## Solution and boundaries

Establish JSON APIs beneath `/api/v1` using JSend envelopes, explicit safe response/View models, snake_case transport fields, one final Action per interaction, and endpoint-specific successful Responders. Add post-routing/pre-Action `#[Validation]`, centralized known-exception mapping, generic correlated unknown-error handling, OpenAPI contracts, and restricted Swagger UI.

Record the browser-authentication security-profile ADR before authentication transport is implemented. It must cover access-JWT signature and registered/custom claim validation, external key/secret configuration and rotation expectations, refresh-cookie scope, CSRF/Origin/Fetch Metadata controls, refresh conflict/reuse outcomes, multi-tab behavior, and logout/invalidation semantics. This TICKET defines and proves the transport seams using the real ADR 0003 CSRF bootstrap. It does not implement login, user/session administration or invitation endpoints. Protected API-v1 Actions remain unavailable until the separately owned JWT-by-default route guard is in place.

Out of scope: authentication/invitation/account endpoints other than the ADR-approved CSRF bootstrap, entity serialization, arbitrary exception-message exposure, generic `LookupException` to `404` mapping, CORS without an approved client, and broad browser automation.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Dispatch a valid API interaction | An Action maps input and dispatches one existing command or explicit orchestrator | An Action dispatches one existing query for read interactions | Application events remain owned by the dispatched behavior | Endpoint-specific Responder emits a documented safe JSend representation |
| Reject invalid transport input | No command is dispatched | N/A — validation occurs before the Action | No domain event is emitted | Sanitized `400` JSend `fail` contains dotted field paths and message lists |
| Map known application failures | No additional command | N/A — exception type/context is inspected centrally | No new domain event is emitted | Validation, authentication, authorization, conflict, and not-found failures receive explicit safe status/envelope mapping |
| Handle an unknown failure | No additional command | N/A — correlation and exception context are inspected for logging only | No new domain event is emitted | Secret-safe correlated diagnostics are logged and a generic sanitized `500` is returned |
| Describe the API contract | N/A — documentation generation is tooling | Inspect route/request/response schemas | N/A — no application domain event is produced | OpenAPI stays aligned and Swagger UI and its OpenAPI document endpoint require `READ_SWAGGER` wherever explicitly enabled |

## Validation and permissions

Malformed JSON, undeclared shape, transport constraints, and domain validation must remain distinguishable without exposing internals. Unknown throwable messages, stack traces, credentials, grant material, SQL, and sensitive identifiers never enter responses. Correlation IDs must be validated/generated safely and propagated to logs and responses according to policy.

Authorization remains server-side and endpoint-specific; framework middleware may establish authentication context but cannot replace command/query permission checks. Swagger exposure, CORS, origins, headers, and production diagnostics are deny-by-default. The authentication ADR must explicitly close consumer gaps rather than treating signature-only JWT decoding or an interface-only throttle as a production control.

### Explicit request-input sources

The maintainer approved this follow-up on 2026-09-30; [TASK-00155](../tasks/00155-TASK.md) owns implementation.
`#[JsonBody]` and `#[QueryString]` are argument-free source indicators on the Action's `__invoke`, not DTO or
schema-class references. Fight Common's `#[Validation]` owns the expected field names/JSON keys and their
constraints. Actions read the checked values from the request and explicitly map them to the owning use case;
no input-only DTO construction, constructor reflection or parallel field/type registry is required. This does
not remove typed package commands, Domain value objects, safe response Views or field-type validation rules.

- Select one declared source, never a body/query merge or a fallback inferred solely from the HTTP method.
  Reject nonselected body/query input rather than silently ignoring it. `QueryString` covers declared GET query
  input; `JsonBody` covers declared JSON mutation input. Reject unsupported
  method/source combinations and conflicting or missing required metadata before Action execution. Configuration
  errors are safe server failures, not successful unvalidated dispatch or client field errors.
- A source declaration requires a valid `Validation` declaration. Its fields are an allowlist, not merely the
  subset to validate while silently passing extra values. Requiredness and field constraints come from the rules;
  an empty declared field set never permits arbitrary input. Path parameters and authenticated context remain
  separate; this contract does not merge headers, cookies, credentials or route values into the selected source.
- Preserve bounded JSON-object parsing, duplicate-key rejection, content-type policy and no-body behavior.
  Validate query input as deliberately as JSON: bounded bytes/field count, valid encoding, declared names, no
  duplicate decoded keys or silently normalized key collisions, and no unexpected scalar/array/nested shapes.
  Do not rely on PHP query parsing that has already dropped duplicates, renamed keys or truncated input.
- Query values remain strings unless an explicitly documented, qualified conversion is applied; JSON retains its
  decoded types. No blanket numeric/boolean casting, trimming or default insertion. In particular, `"false"`
  must never gain truth through PHP truthiness. Endpoint rules and mapping own allowed representations/defaults;
  malformed values fail rather than becoming defaults. Qualify the installed Common rules against both sources.
- List endpoints may declare `page`, `per_page`, `sort`, `order`, filters or `include_deleted` only when their
  own accepted use case supports them. Bound pagination; allowlist ordering/filter fields and operators. Never
  pass raw SQL/column expressions to persistence. Validating `include_deleted` does not authorize deleted-record
  visibility. This foundation grants no new query options or permissions to existing/planned endpoints.
- Invalid client input returns bounded sanitized `400` JSend `fail` field-message lists before dispatch; retain
  body paths and distinguish query paths without reflecting unsafe submitted keys/values or package prose.
  CSRF bootstrap's existing no-body/no-query, cookie, HTTPS/origin and failure contracts remain unchanged.
  A GET alone never opts an Action into query input. No new product endpoint is introduced just to prove this seam.

This supersedes the input-only DTO direction for future consumers, not TASK-00019's historical implementation
or acceptance evidence. Its installed `JsonBody(Dto::class)` behavior remains current until TASK-00155 is
implemented and verified. Shared public form-schema export remains separately allowlisted under TICKET-00036;
source markers and validation metadata grant neither publication nor operation authority.

### Canonical Action and Responder attribute ordering

For each applicable **class or method declaration**, order attribute groups top-to-bottom:

1. **OpenAPI** (`OA` operation/response/schema metadata).
2. **Input source** (`JsonBody` or `QueryString`, mutually exclusive for the selected input).
3. **Validation** (Fight Common `Validation`).
4. **Access requirements** (`RequiresPermission` or the applicable authenticated/public-access marker).
5. **Other applicable metadata**, with its placement documented when introduced rather than interleaved above.

Use one attribute per declaration block and keep each family's related metadata together. Preserve deliberate
order within repeatable attributes; this rule does not reorder nested OpenAPI arguments. Apply only groups that
belong at that site: the current OpenAPI operation and planned source/validation attributes belong on Action
`__invoke`; future security attributes retain their owning TASK's supported class/method target. Do not move an
attribute between targets to make one visual stack. Responders normally contain OpenAPI response/schema metadata
only; do not add input validation or permission policy to a Responder. Docblocks precede attributes.

This is a source presentation convention, **not middleware execution order** or permission precedence. Middleware
composition and authoritative server policy retain their own ordering requirements. TASK-00155 records the
convention in engineering/API guidance and applies it to affected declarations without speculative attributes;
TASK-00117 and later endpoint owners follow it when adding their actual metadata.

### Accepted public validation-schema read

The maintainer approved [TICKET-00036](00036-TICKET.md)'s explicitly unauthenticated
`GET /api/v1/validations/{form_name}` on 2026-09-30. It serves only deliberately allowlisted browser-safe
constraint metadata, not user/role/permission catalogs or submitted values. No `VIEW_DASHBOARD` prerequisite
applies. [TASK-00151](../tasks/00151-TASK.md) owns export/serving; TASK-00117 must preserve this exact public
Action exception alongside the existing bootstrap/credential-flow exceptions. JWT-by-default for all other
APIs and every operation's server validation, authorization, CSRF/rate/grant controls remain unchanged.
This public metadata decision does not make Swagger or its OpenAPI document public.

### Accepted Swagger access policy

When the Swagger frontend is implemented, protect both the UI route and its OpenAPI document endpoint with server-side checks of current authoritative `READ_SWAGGER`. This is a managed `SUPER_ADMIN_ONLY` permission, granted only to managed `ROLE_SUPER_ADMIN` by [TICKET-00015](00015-TICKET.md) / [TASK-00036](../tasks/00036-TASK.md), and unavailable for custom-role or direct-Agent delegation. A role name, JWT role claim, or client-side navigation guard is not authorization. Direct document requests must not bypass the UI's permission boundary.

Environment eligibility and permission are independent gates: **only exact `APP_ENV=local` is eligible**.
Legacy `development`, `test`, staging, production, missing and unknown values do not enable Swagger, even for a
permitted caller or with APP_DEBUG=true. The maintainer narrowed the former development/test possibility to
local-only on 2026-09-30. [TASK-00063](../tasks/00063-TASK.md) must preserve this stricter rule. Automated tests
may boot an isolated local application to exercise allowed cases; APP_ENV=test itself grants no exposure.
This planning decision does not enable any route in the current implementation.

### Attribute-owned OpenAPI export and local viewer

The maintainer approved two implementation TASKs on 2026-09-30:
[TASK-00153](../tasks/00153-TASK.md) transitions the repository-only hand-authored YAML contract to one PHP
attribute-owned source and explicit deterministic JSON export;
[TASK-00154](../tasks/00154-TASK.md) serves the private artifact and protected viewer only under the environment
and permission gates above. Preserve actual response-contract evidence and document the single-source transition;
do not independently maintain YAML and attributes, infer unimplemented operations, or enable HTTP serving as an
export side effect. Generated JSON belongs outside public static assets; direct reads must be guarded too.

Bundle qualified Swagger UI assets locally. Reuse the application's system/light/dark preference and semantic
styles for inputs, code, responses and focus. Initialize `docExpansion: "none"`: tag groups **and operations**
start collapsed on load/remount, with deliberate expansion/search and no persisted state reopening the catalog.
No request executes merely because a section expands, the page loads or the theme changes.

### Local environment and safe debug configuration

Use `local` as the canonical developer environment name and wire it explicitly through local HTTP/TLS Compose
configuration rather than treating every non-production mode as local. Add/document APP_ENV and APP_DEBUG in
`.env.example`; the maintainer updates ignored `.env` personally. Example entries are prospective until
TASK-00154 removes the pending-wiring caveat after actual integration. Group example variables under stable
comment section identifiers with one definition per variable; this does not plan an environment-update script.
Preserve distinct APP_ENV=test guarded-database semantics and existing production protections.

Strictly parse APP_DEBUG as a boolean; absent means false and malformed configuration fails safely. Optional
browser diagnostics are enabled only when local and explicitly debug-enabled. Non-local debugging stays off;
APP_DEBUG never enables Swagger, authorization, PHP error display or sensitive diagnostics. Even local console
output must exclude credentials, cookies, CSRF/grants, passwords, form bodies, personal data and arbitrary raw
response/error objects. Provide a small shared safe logging seam/convention, not a new telemetry framework.

Extend the existing allowlisted, versioned inert JSON boot configuration under
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md#local-debug-and-documentation-availability-amendment)
with safe `app_debug` and `swagger_available` booleans. The latter is environment availability, never a user
permission. Prefer this existing channel over a new configuration API or hidden input; base64 is encoding only
and does not make secrets safe. No environment-object dump or credential/authority hydration. The server checks
local eligibility and current permissions independently of any client-modifiable boot values.

### Swagger request credentials and effects

Use the existing in-memory access-token provider and ordinary bounded session refresh; retain ADR 0003's
fifteen-minute access lifetime. No long-lived/static Swagger credential, token hidden/base64 input, token form
prefill, persisted Swagger authorization or independent refresh loop. Attach the latest credential only at the
qualified same-origin transport boundary, never to arbitrary server URLs, redirects, remote refs or third-party
validators. Protected viewer/bootstrap and spec fetches use this same authority; direct unauthenticated reads
remain denied, and the generic application shell contains no protected spec/viewer payload.

Every in-page Execute is an explicit real operation under that endpoint's own server permissions, validation,
CSRF/Origin/rate/confirmation controls; READ_SWAGGER does not grant mutation authority. Apply shared restoration,
expiry, logout/context-generation fences and safe credential/display redaction. Do not replay mutations after
refresh/401/403/cancellation. Keep tokens out of Swagger authorization state, visible/copyable curl/examples,
logs and downloads. Qualify actual hook/transport behavior and clearly report unsupported execution cases
rather than bypassing auth or leaking credentials to make the viewer appear complete.

## Acceptance and evidence

- An accepted browser-authentication security ADR covers the required token, cookie, CSRF/origin, refresh, multi-tab, and invalidation decisions before EPIC-00004 transport implementation.
- `/api/v1` composition supports FQCN final Actions, endpoint Responders, explicit Views, JSend, and snake_case mapping through `GET /api/v1/auth/csrf`; no unauthenticated access-control catalog is exposed.
- Post-routing/pre-Action validation rejects malformed/banned input without dispatch on implemented operations. Declared query/JSON input uses source-only markers and `#[Validation]` without input-only DTOs; real endpoint owners prove their full input matrices without inventing test-only product routes.
- Explicit source selection and Validation-owned fields reject malformed, undeclared, ambiguous or oversized input before dispatch, preserve checked request values without implicit coercion/merging, and leave authorization with the owning use case.
- Action and Responder class/method declarations follow the canonical attribute grouping above without moving targets or treating source order as execution order.
- Central exception mapping handles each known category and keeps generic lookup failures out of automatic `404` treatment.
- Unknown failures have correlated secret-safe logs and generic client responses.
- OpenAPI describes the implemented CSRF bootstrap and actual failure schemas; it does not invent a protected resource or a request-body operation. Swagger UI and its OpenAPI document endpoint remain disabled until separately implemented under the accepted access policy.
- Future Swagger implementation proves both viewer/bootstrap and direct OpenAPI document access: allowed only
  in exact APP_ENV=local with current authoritative READ_SWAGGER; anonymous, insufficient/revoked permission,
  unavailable authority and every non-local environment disclose neither viewer payload nor document.
  Client-only checks or a public static document are not acceptable substitutes.
- Attribute export preserves actual operation/response contracts, supplies deterministic safe private JSON
  and one authored source, and never exposes a document merely by running generation.
- The viewer follows shared system/light/dark themes, initially collapses all tags/operations and uses only
  current memory credentials through shared request/refresh/security boundaries, with no long-lived credential
  exception, secret-bearing snippets or automatic mutation replay.
- Strict local/debug configuration and versioned safe booleans support guarded sanitized console output without
  exposing environment objects, credentials, authority or debug privileges in non-local environments.
- A separately owned, JWT-by-default API-v1 guard reads matched FQCN Action attributes without eager Action instantiation. Protected routes fail closed on missing/invalid metadata, authenticate from current authority, and enforce conjunctive permissions before dispatch while application/package target policies remain authoritative.
- Functional tests prove boot, unknown route, invalid bootstrap body/shape and reachable errors without a fabricated POST; first real body/query consumers prove their declared fields, normalization and constraints without input-only DTOs, while the mapper is checked at its own boundary.
- Focused HTTP/OpenAPI tests and `./bin/build` pass with fresh evidence and warnings.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00017](../tasks/00017-TASK.md) | Accept the browser-authentication security-profile ADR | done |
| [TASK-00018](../tasks/00018-TASK.md) | Establish the versioned JSend API interaction boundary | done |
| [TASK-00019](../tasks/00019-TASK.md) | Reject invalid API input before dispatch | done |
| [TASK-00020](../tasks/00020-TASK.md) | Centralize correlated and sanitized API failures | done |
| [TASK-00021](../tasks/00021-TASK.md) | Publish and restrict the representative OpenAPI contract | done |
| [TASK-00117](../tasks/00117-TASK.md) | Guard API-v1 Actions with JWT and permission attributes | ready-for-agent |
| [TASK-00153](../tasks/00153-TASK.md) | Export the attribute-owned OpenAPI contract as deterministic JSON | done |
| [TASK-00154](../tasks/00154-TASK.md) | Serve local-only permission-gated Swagger with shared credentials and themes | ready-for-agent |
| [TASK-00155](../tasks/00155-TASK.md) | Validate explicit JSON and query input without transport DTOs | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

On 2026-09-30, the maintainer approved argument-free JSON/query source markers, Validation-owned input fields,
checked request-value consumption without input DTOs, and canonical Action/Responder attribute ordering.
TASK-00155 is the bounded follow-up under this TICKET. This planning amendment does not implement or verify
that behavior; completed TASK-00019/00153 evidence remains historical and unchanged. Publication of these plans
is separately authorized on `feature/planning-explicit-input-sources`; implementation requires its own handoff.
Planning checks passed with 201 records / 139 active and current generated views; whitespace checks passed.
The unchanged application baseline passed OpenAPI checks (64 tests / 523 assertions) and `./bin/build`
(seven terminal tests, 195 PHP tests / 1,643 assertions, zero Deptrac violations/warnings). The expected synthetic
HTTP failure log was sanitized. Author instruction walkthroughs covered source confusion, duplicate/query input,
attribute targets, Responder scope and publication boundaries; these are not independent QA or execution proof
of TASK-00155. Independent review and human merge remain separate.

The human approved `READ_SWAGGER` as a managed `SUPER_ADMIN_ONLY` permission for the future Swagger frontend and its OpenAPI document endpoint. TASK-00021's completed repository-only contract and disabled-route evidence remain unchanged; they do not prove this future permission gate. The maintainer subsequently approved the local-only export/viewer decomposition as TASK-00153 and TASK-00154,
with explicit managed-policy/authentication/theme prerequisites, safe APP_ENV/APP_DEBUG projection, shared
memory credentials and initially collapsed tag groups/operations. Neither TASK-00021 nor TASK-00117 is marked
as having delivered these new capabilities. TASK-00153 now implements the private attribute-owned export with
local verification, independent technical acceptance and behavioral QA PASS for candidate `78a2c56`.
TASK-00153 records the acceptance and separate landing checkpoint; PR publication does not imply merge.
TASK-00154's viewer/serving boundary is still unimplemented, and no HTTP documentation exposure is enabled.

Planning amendment verification on `feature/planning-swagger-permission`: `./bin/planning-check --write` and `./bin/planning-check` passed (192 records, 138 active; views current); `git diff --check` passed; `./bin/build` passed (7 terminal tests; Deptrac: 658 allowed, no violations/warnings/errors; PHPUnit: 182 tests, 1500 assertions). The HTTP failure test emitted a sanitized error log; no gate warnings or failures were reported. These checks verify planning consistency and the existing suite, not future Swagger authorization. Independent review is pending; publication and merge remain separate.

Implements the HTTP direction approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md) and records the browser-security gate required by [WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md). It depends on [TICKET-00008](00008-TICKET.md) and may proceed alongside PostgreSQL work once shared contracts are stable.
