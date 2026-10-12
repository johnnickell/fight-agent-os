---
id: TICKET-00073
epic: EPIC-00016
title: Administer immutable Feature settings and safe rollout transitions
status: ready-for-agent
---

# Administer immutable Feature settings and safe rollout transitions

## Problem statement

A stored rollout setting is not a usable management journey. Administrators need safe creation, testing audience
rebinding and OFF/PREVIEW/ON transitions, with current authority and concurrency protection instead of a role
shortcut, stale checkbox or accidental removal of public availability.

## Solution and boundaries

Provide accessible Feature Flags list/detail/create/edit controls and bounded HTTP/direct operations using the
package `CreateFeature`, `GetFeatureById`, `SetFeaturePermission` and `SetFeatureStatus` contracts. Reuse
[TICKET-00072](00072-TICKET.md)'s complete catalog and authoritative package Permission choices for the admin
surface; permit separately protected detail/repair projection without creating a competing broad catalog.
Manual creation chooses a strict immutable name and an existing testing Permission ID and always begins `OFF`.
Show current binding, status, stable ID, positive revision and safe missing-binding repair marker; rebind only
to a current existing Permission. Every edit uses the current expected revision; a valid no-op still validates
expected full state and binding, advances no revision and emits no false change fact. A real edit advances once
under package policy and affects the next explicit availability check.

Reconcile a distinct managed `ADMIN_SAFE` Feature-management Permission, initially assigned **only** to
`ROLE_SUPER_ADMIN`, and enforce fresh actor/target/intent on all HTTP, CLI, worker and direct-bus paths. Tier
eligibility is not automatic custom-Role or Agent delegation. Before enabling PREVIEW/ON, qualify complete
preparation/current policy and registered runtime coverage through TICKET-00070/71; scanner success or a flag
alone cannot authorize deployment or the underlying action. Warn and require explicit confirmation before
leaving ON for PREVIEW/OFF; cancellation dispatches nothing. On stale conflict, refetch authoritative state
and obtain new confirmation if the still-intended transition remains dangerous, never replay blindly.

No rename, generic Permission CRUD or creation, testing-Permission-as-management right, direct SQL bypass,
role-name grant, automatic cleanup TASK or deletion engine is introduced. The guarded removal operation and
its confirmation/refusal integration belong to [TICKET-00074](00074-TICKET.md). The empty screen remains
useful; no arbitrary or dummy Feature is seeded for acceptance.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create manually | Authorized package `CreateFeature` | Strict name uniqueness and existing Permission ID | Package `FeatureCreated` after commit | One new immutable-name OFF record, retaining all unrelated settings/grants |
| Inspect or repair binding | `SetFeaturePermission` after confirmed authorized selection; N/A for read | Package `GetFeatureById`, existing Permission choices and complete catalog | Package `FeaturePermissionChanged` on actual committed change; none for read/no-op | Exact revision-checked rebind; original Permission reference released safely |
| Change rollout state | `SetFeatureStatus` with current expected revision and any required confirmation | Fresh Feature, preparation/current-policy and registered enforcement | Package `FeatureStatusChanged` after actual committed change | OFF/PREVIEW/ON setting changes, not action authority or cleanup |

## Validation and permissions

Current effective management Permission and target/intent scope must be checked by shared Application policy
on every entry, not only an Action attribute or browser guard. The testing Permission can have any eligible
package-defined tier as a reference; its binding grants neither management nor protected direct Agent grants.
Unknown/removed binding, malformed immutable name/status, stale/nonpositive revision, concurrent management
or reference mutation and readiness failure are safe denials/conflicts, never overwritten. Final full-state
comparison and binding validation, including no-ops, use TICKET-00070's shared connection/reference fences;
reused ORM state cannot hide changes. Package best-effort post-commit events do not guarantee UI delivery or
rollback; refetch after uncertain outcomes. Accessible confirmation and safe View/error/audit context exclude
provider/key/credential material and arbitrary infrastructure messages.

## Acceptance and evidence

- A currently authorized administrator can create OFF, inspect/repair binding and transition settings;
  ordinary Users, stale/revoked authority, testing-Permission holders without management rights and unauthorized
  direct calls are denied. No protected-tier or future scoped administrator privilege is inferred.
- PostgreSQL checks cover concurrent manual create/provision, status-versus-rebind stale conflicts, full-state
  compare/no-op, Permission-removal winner orders and rollback with authoritative next availability checks.
- UI gives truthful empty/loading/repair/denial/conflict states; leaving ON requires confirmation and a stale
  result requires refetch/reconfirmation. Enabling cannot claim rollout without complete runtime coverage.
- Owned authorization/HTTP/client/accessibility and package-consumer tests plus `./bin/build` pass; record
  exact qualified package/source/lock, warnings and operational limits, not fixture-only acceptance.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of EPIC-00016's five-TICKET split. Requires
[TICKET-00070](00070-TICKET.md)'s preparation/reference boundary and
[TICKET-00071](00071-TICKET.md)'s enforcement before enabling; consumes
[TICKET-00072](00072-TICKET.md)'s catalog and [EPIC-00004](../epics/00004-EPIC.md)'s managed authority,
current principal and authenticated UI without rewriting user/role owners. No management Permission or
Feature setting was created here.

TICKET-00070's approved TASK-00291 now supplies qualified shared reference/expected-state persistence and atomic
provisioning, with TASK-00290's exact inventory and TASK-00292's separate fresh preparation. Consume those
primitives; this TICKET retains management Permission/actor/intent checks, manual create/status/rebind entry
integration and its own affected races, including manual create versus provision. No finished UI or management
path reverse-blocks the reference primitive, and this management Permission is not privileged operator setup
authority. Prepared OFF still needs complete runtime enforcement and separate authorization before rollout.

TICKET-00071 now maps TASK-00293's matched HTTP enforcement and TASK-00294's registered non-Action/Agent
checks. Consume every applicable path before claiming rollout, without making a human-only capability wait
for unrelated Agent integration. Keep unknown/missing coverage a hold; ON cannot replace underlying authority.
These gates reread current settings and original Permission IDs while retaining request-principal snapshots;
setting changes affect subsequent explicit checks, not retroactive cancellation. No management journey
reverse-blocks either runtime primitive.
