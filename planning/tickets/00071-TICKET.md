---
id: TICKET-00071
epic: EPIC-00016
title: Enforce current Feature availability at every registered entry
status: ready-for-agent
---

# Enforce current Feature availability at every registered entry

## Problem statement

A Feature declaration, database status and client visibility are not a runtime guard. Without a real matched
Action middleware and equivalent registered non-HTTP checks, an `OFF` or `PREVIEW` capability can still execute
through direct HTTP, CLI, worker or Agent tool calls.

## Solution and boundaries

Integrate a package-backed `FeatureFlagMiddleware` **post-route and pre-Action** using the final matched FQCN
Action method and valid method-only `#[FeatureFlag]`, without eagerly constructing or dispatching a denied
Action. Resolve the already-authenticated current User/Agent principal, or `null` only on an explicitly
approved public route, and invoke package `FeatureAvailability` with the exact stored Feature and current
bound testing Permission **ID** on every check. `OFF` returns 404, `PREVIEW` returns 404 unless the User's
Role-derived or Agent's direct Permissions include that ID, and `ON` continues. In every allowed case ordinary
JWT/permission, actor/target, CSRF/origin and use-case authorization still apply; `ON` is not public access to
a protected Action. The existing `RequiresPermission`/JWT-by-default Action guard retains its separate owner.

Require every supported non-HTTP path to the same flagged capability to register and enforce its own server
availability check with the trusted caller and normal grants before work starts. The scanner in
[TICKET-00070](00070-TICKET.md) must cover those registrations. Do not claim a staged rollout if any path
remains ungated; unsupported dynamic, unregistered checks cannot be treated as protected. Recheck Feature
status/binding on each invocation with authoritative storage, including reused workers and successive checks;
a later OFF change need not retroactively cancel already admitted work. Do not duplicate OFF/PREVIEW/ON policy
in middleware, implement a second Feature status evaluator or create a dummy flagged product Action.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Enter a flagged HTTP Action | N/A — gating makes no mutation | Resolve matched method, current principal, `FeatureAvailability` and ordinary Action authority | N/A — a rejected check is not an operation event | 404 before Action on OFF/ineligible PREVIEW; eligible PREVIEW/ON continue to separately authorized Action |
| Enter the same capability outside HTTP | Existing owning CLI/worker/MCP command only after registered check | Fresh package availability, trusted Agent/User principal and underlying grant | Only the owning operation's event after actual success | No alternate bypass or grant from a flag alone |
| Observe setting/binding changes | N/A — another requirement owns edits | Fresh Feature and bound Permission ID on each invocation | N/A for a read/check | Next explicit check reflects current status; stale ORM/result caches do not preserve old availability |

## Validation and permissions

Malformed Attribute, unknown Feature, missing/mismatched binding, invalid state and storage outage never pass
through as ON or silently become OFF; map safe categories to responses/internal diagnostics without exposing
raw repository errors. OFF/ineligible PREVIEW denial is 404; a storage outage is not disguised as a valid OFF.
The package's ID-based binding decision is separate from active user/session resolution and Agent authentication;
current request authority must be qualified. Browser `/me` is not Agent authorization. Route-public status must
be explicit, not inferred from flag availability; direct command-bus and protected non-HTTP entry paths retain
their own actor/target/delegation checks.

## Acceptance and evidence

- Real matched Actions deny OFF/ineligible PREVIEW with 404 without Action construction/effect and allow ON/
  eligible PREVIEW **only** through the existing authorization chain; public and authenticated routes are distinct.
- Registered non-HTTP paths to the same capability cannot bypass the check; unknown/broken/outage cases fail
  safely, never silently allow or falsely report a policy 404.
- Actual PostgreSQL, HTTP and worker-reuse evidence covers same-instance status/binding changes, revocation,
  malformed metadata and ordinary authorization; record exact qualified package/source/lock and unsupported paths.
- Owned tests and direct runtime/composition checks plus `./bin/build` pass. Package controlled fixtures or
  a scanned Attribute alone are not evidence of a protected operation.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00293](../tasks/00293-TASK.md) | Guard matched HTTP Actions with current Feature availability | ready-for-agent |
| [TASK-00294](../tasks/00294-TASK.md) | Enforce registered Feature checks outside HTTP Actions | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00016's five-TICKET split. Depends on
[TICKET-00070](00070-TICKET.md)'s authoritative persistence/inventory and
[TICKET-00010](00010-TICKET.md)'s separately owned Action gate; supplies server truth to
[TICKET-00072](00072-TICKET.md) and safe enablement to [TICKET-00073](00073-TICKET.md).
The maintainer subsequently selected `ai-template-proposals` under TICKET-00077 as the first real flagged
capability. That owner consumes this enforcement; selection implements no middleware or flag.

TICKET-00070's approved supplier mapping is now TASK-00290 for exact discovery and the single explicit non-HTTP
registration source, TASK-00291 for qualified shared persistence/reference behavior, and TASK-00292 for fresh
candidate preparation before considering rollout. Reuse these primitives without another scanner/registry or
reverse dependency on finished middleware/non-HTTP enforcement. A preparation observation is not runtime access;
every actual invocation still performs the fresh package availability and ordinary authorization checks above.
These suppliers do not select or seed a real product Feature and do not qualify current User/Agent entry authority.

### Approved TASK decomposition

The maintainer approved two enforcement paths, each owning its implementation and affected integration evidence:

| TASK | Outcome | Blockers |
|---|---|---|
| [TASK-00293](../tasks/00293-TASK.md) | Guard matched HTTP Actions with current Feature availability | TASK-00117, TASK-00292 |
| [TASK-00294](../tasks/00294-TASK.md) | Enforce registered Feature checks outside HTTP Actions | TASK-00293, TASK-00245 |

Both are unranked features, `ready-for-agent` behind listed blockers. TASK-00294 remains transitively held by
TASK-00245's unresolved first-party trust/current-use prerequisites; no alternate authentication/registry is
invented to bypass them. Missing actual supported entry owners/contracts hold the affected path explicitly.

TASK-00293 adds package-backed middleware post-route/authentication and pre-Action construction/dispatch.
Inspect the actual matched FQCN method without using Slim's callable resolver, which obtains/constructs the
Action. Instantiate the native Attribute; do not mistake malformed declarations for an unflagged route. Reuse
TASK-00117's authoritative User/public classification and preserve its ordinary security metadata, route/method
misses and request protections. Feature false gives fixed 404; Feature true only permits ordinary authorization
to continue. No public exception, Agent-on-JWT route or authentication fallback is introduced. Actual Agent
composition is qualified by TASK-00294 through its existing owning boundary.

The inspected package `FeatureAvailability` rereads Feature and original bound Permission ID on every call,
including OFF and ON. Unknown Feature, missing/mismatched binding, invalid metadata/state and operational errors
must remain safely classified, not silently OFF/ON or leaked as raw diagnostics. Fresh Feature reads do not
refresh the immutable principal snapshot; preserve its one-request semantics and resolve current authority for
later requests/jobs. No role-name/name-to-ID shortcut, stale identity-map/negative cache, second nonce consumption,
runtime scan/provision/repair or distributed check-and-effect/cancellation guarantee is added.

TASK-00294 extends the shared runtime composition to actual registered CLI/worker/direct-use and Agent/MCP
boundaries through TASK-00290's single registration source. Intersect package Feature restriction with the
existing Tool Permission/current-use decision using the same registry, request-bound provider and signed context.
Filter before pagination, mirrors or protected metadata disclosure; recheck invocation and supported continuations
before protected argument validation, restoration/confirmation consumption, progress and handling. Minimal bounded
protocol parsing needed to identify the Tool is not business execution. Unknown/denied Tool concealment remains
Common-owned; operational failure cannot masquerade as a successful empty/partial catalog.

Direct alternatives need the owning server-side check, not merely a method Attribute or HTTP middleware. Use
qualified current caller/target/grant context without borrowing a principal or treating unknown worker identity
as anonymous. New requests/pages/jobs reauthenticate through existing owners; within the request reuse one Agent
snapshot without a second nonce transaction. Work already admitted may finish after a later status change.
Unsupported modes/entries stay unavailable for rollout rather than becoming undocumented bypasses.

TICKET-00072's TASK-00295 now consumes TASK-00293's human availability for the complete catalog;
TASK-00296 adds browser integration without an unrelated Agent dependency. TICKET-00073 must
consume every applicable registered runtime boundary before rollout; TICKET-00074's eventual owner cleanup removes
all declarations, explicit registrations and checks before CURRENT absence can justify retirement. No completed
Feature gate reverse-blocks authentication, scanner, preparation or MCP primitives.

Each slice owns actual package/PostgreSQL and dispatch integration evidence, safe failures, fresh-read/principal
lifetime checks and `./bin/build`. Controlled native-metadata/operation fixtures prove owned reusable contracts,
not live product endpoints/Tools; directly inspect actual framework/runtime composition and existing unflagged
paths. No dummy product Feature, duplicate evaluator/registry, proof-only TASK or parent ceremony is needed.
TICKET-00077 owns `ai-template-proposals` admission and full product-path evidence; configuration, tooling,
migrations and generated files are validated directly, not product-tested as text/wiring. Confirmed bugs require
a reproducing failing regression before repair. Record exact package/source/lock and fresh versus reused receipts.

Active TASK-00138/TASK-00226 and existing priorities remain unchanged. With TICKET-00072/00073, four of EPIC-00016's
five TICKETs now have approved written TASK splits, not runtime acceptance. Fifteen of the map's 24 are decomposed;
**nine remain, TICKET-00074–00082**, alongside older incomplete Harness/managed review planning.
Next planning operation: `/skill:to-tasks TICKET-00074`.
No middleware, route, Tool gate, worker, nonce or Feature was enabled by planning. Implementation/worktree choice,
independent review, publication, enrollment and actual deployment remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 394 records,
307 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations. Repetitive Sass deprecations, React-Bootstrap `use client` bundling and >500 kB chunk warnings remain.
Evidence: `.runs/build/20261012T235119Z-61643`; summary log: `.runs/build/ticket-00071-to-tasks.log`.
These validate planning and the existing repository, not future middleware, non-Action/Agent enforcement,
current-authentication/runtime qualification or a live Feature rollout. Independent review and publication
remain unperformed.
