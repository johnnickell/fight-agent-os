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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00016's five-TICKET split. Depends on
[TICKET-00070](00070-TICKET.md)'s authoritative persistence/inventory and
[TICKET-00010](00010-TICKET.md)'s separately owned Action gate; supplies server truth to
[TICKET-00072](00072-TICKET.md) and safe enablement to [TICKET-00073](00073-TICKET.md).
The first real flagged capability will be selected by its actual owner later. No middleware or flag was
implemented here.

TICKET-00070's approved supplier mapping is now TASK-00290 for exact discovery and the single explicit non-HTTP
registration source, TASK-00291 for qualified shared persistence/reference behavior, and TASK-00292 for fresh
candidate preparation before considering rollout. Reuse these primitives without another scanner/registry or
reverse dependency on finished middleware/non-HTTP enforcement. A preparation observation is not runtime access;
every actual invocation still performs the fresh package availability and ordinary authorization checks above.
These suppliers do not select or seed a real product Feature and do not qualify current User/Agent entry authority.
