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

### Accepted Swagger access policy

When the Swagger frontend is implemented, protect both the UI route and its OpenAPI document endpoint with server-side checks of current authoritative `READ_SWAGGER`. This is a managed `SUPER_ADMIN_ONLY` permission, granted only to managed `ROLE_SUPER_ADMIN` by [TICKET-00015](00015-TICKET.md) / [TASK-00036](../tasks/00036-TASK.md), and unavailable for custom-role or direct-Agent delegation. A role name, JWT role claim, or client-side navigation guard is not authorization. Direct document requests must not bypass the UI's permission boundary.

Environment eligibility and permission are independent gates: disabled routes stay unavailable even to a permitted caller. Preserve deny-by-default exposure and [TASK-00063](../tasks/00063-TASK.md)'s narrow development/test exceptions and production-like exclusion. This decision does not enable Swagger in any environment.

## Acceptance and evidence

- An accepted browser-authentication security ADR covers the required token, cookie, CSRF/origin, refresh, multi-tab, and invalidation decisions before EPIC-00004 transport implementation.
- `/api/v1` composition supports FQCN final Actions, endpoint Responders, explicit Views, JSend, and snake_case mapping through `GET /api/v1/auth/csrf`; no unauthenticated access-control catalog is exposed.
- Post-routing/pre-Action validation rejects malformed/banned input without dispatch on implemented operations. Dotted-field `#[Validation]` DTO matrices wait for the first real request-body operation; no test-only route/DTO is invented.
- Central exception mapping handles each known category and keeps generic lookup failures out of automatic `404` treatment.
- Unknown failures have correlated secret-safe logs and generic client responses.
- OpenAPI describes the implemented CSRF bootstrap and actual failure schemas; it does not invent a protected resource or a request-body operation. Swagger UI and its OpenAPI document endpoint remain disabled until separately implemented under the accepted access policy.
- Future Swagger implementation proves both UI and direct OpenAPI document access: allowed only in an explicitly enabled environment with current authoritative `READ_SWAGGER`; anonymous, insufficient/revoked permission, unavailable authority, and disabled-environment requests disclose neither UI nor document. Client-only checks or a public static document are not acceptable substitutes.
- A separately owned, JWT-by-default API-v1 guard reads matched FQCN Action attributes without eager Action instantiation. Protected routes fail closed on missing/invalid metadata, authenticate from current authority, and enforce conjunctive permissions before dispatch while application/package target policies remain authoritative.
- Functional tests prove boot, unknown route, invalid bootstrap body/shape and reachable errors without a fabricated POST; first real body-bearing Action later proves malformed JSON and DTO shape, while the mapper is checked at its own boundary.
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
<!-- /planning:children -->

## Decisions and progress

The human approved `READ_SWAGGER` as a managed `SUPER_ADMIN_ONLY` permission for the future Swagger frontend and its OpenAPI document endpoint. TASK-00021's completed repository-only contract and disabled-route evidence remain unchanged; they do not prove this future permission gate. Swagger frontend/serving implementation still requires a separately decomposed TASK under this TICKET, with managed policy and authentication prerequisites; neither TASK-00021 nor TASK-00117 is implicitly expanded or marked as having delivered it.

Planning amendment verification on `feature/planning-swagger-permission`: `./bin/planning-check --write` and `./bin/planning-check` passed (192 records, 138 active; views current); `git diff --check` passed; `./bin/build` passed (7 terminal tests; Deptrac: 658 allowed, no violations/warnings/errors; PHPUnit: 182 tests, 1500 assertions). The HTTP failure test emitted a sanitized error log; no gate warnings or failures were reported. These checks verify planning consistency and the existing suite, not future Swagger authorization. Independent review is pending; publication and merge remain separate.

Implements the HTTP direction approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md) and records the browser-security gate required by [WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md). It depends on [TICKET-00008](00008-TICKET.md) and may proceed alongside PostgreSQL work once shared contracts are stable.
