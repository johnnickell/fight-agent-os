---
id: TICKET-00073
epic: EPIC-00016
title: Administer immutable Feature settings and safe rollout transitions
status: ready-for-agent
---

# Administer immutable Feature settings and safe rollout transitions

## Problem statement

A stored rollout setting is not a usable management journey. Administrators need safe provisioning, testing audience
rebinding and OFF/PREVIEW/ON transitions, with current authority and concurrency protection instead of a role
shortcut, stale checkbox or accidental removal of public availability.

## Solution and boundaries

Provide accessible Feature Flags list/detail/edit controls and bounded HTTP/direct operations using the
package `GetFeatureById`, `SetFeaturePermission` and `SetFeatureStatus` contracts for the first real rollout:
[EPIC-00017](../epics/00017-EPIC.md) / [TICKET-00077](00077-TICKET.md)'s `ai-template-proposals`. Reuse
[TICKET-00072](00072-TICKET.md)'s complete catalog and authoritative package Permission choices for the admin
surface; permit separately protected detail/repair projection without creating a competing broad catalog.
TASK-00291 provisions the declared real Feature OFF. Standalone manual-create browser/API work is deferred;
do not create it just to exercise package `CreateFeature` or demonstrate this management system.
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
role-name grant, standalone manual-create screen/API, automatic cleanup TASK or deletion engine is introduced.
The guarded removal operation and its confirmation/refusal integration belong to [TICKET-00074](00074-TICKET.md). The empty screen remains
useful; no arbitrary or dummy Feature is seeded for acceptance.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
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

- A currently authorized administrator can inspect/repair the provisioned Feature's binding and transition settings;
  ordinary Users, stale/revoked authority, testing-Permission holders without management rights and unauthorized
  direct calls are denied. No protected-tier or future scoped administrator privilege is inferred.
- PostgreSQL checks cover provisioning-versus-management interactions, status-versus-rebind stale conflicts, full-state
  compare/no-op, Permission-removal winner orders and rollback with authoritative next availability checks.
- UI gives truthful empty/loading/repair/denial/conflict states; leaving ON requires confirmation and a stale
  result requires refetch/reconfirmation. Enabling cannot claim rollout without complete runtime coverage.
- Owned authorization/HTTP/client/accessibility and package-consumer tests plus `./bin/build` pass; record
  exact qualified package/source/lock, warnings and operational limits, not fixture-only acceptance.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00297](../tasks/00297-TASK.md) | Manage the testing audience and rollout state for AI template proposals | needs-info |
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
primitives; this TICKET retains management Permission/actor/intent checks, status/rebind entry
integration and its own affected races. Manual creation is deferred by the later product-use amendment below. No finished UI or management
path reverse-blocks the reference primitive, and this management Permission is not privileged operator setup
authority. Prepared OFF still needs complete runtime enforcement and separate authorization before rollout.

TICKET-00071 now maps TASK-00293's matched HTTP enforcement and TASK-00294's registered non-Action/Agent
checks. Consume every applicable path before claiming rollout, without making a human-only capability wait
for unrelated Agent integration. Keep unknown/missing coverage a hold; ON cannot replace underlying authority.
These gates reread current settings and original Permission IDs while retaining request-principal snapshots;
setting changes affect subsequent explicit checks, not retroactive cancellation. No management journey
reverse-blocks either runtime primitive.

TICKET-00072 now maps TASK-00295's single complete authenticated `GET /api/v1/features` and TASK-00296's volatile
browser availability/invalidation. Reuse that broad safe catalog for listing; current edit revision, protected
repair/detail and Permission choices remain this management boundary's responsibility, not an expansion of the
ordinary-user catalog. Qualify actual rebind/status completion and uncertain-outcome invalidation through
TASK-00296, including late responses and Feature-only private-data fences. A command/event does not supply a new
availability snapshot; refresh and reauthorize without automatic replay. No management completion reverse-blocks
either catalog primitive, and no duplicate broad endpoint or authority store is introduced.

### Approved product-use amendment

After challenging the proposed manual-creation slice, the maintainer selected `ai-template-proposals` in
TICKET-00077 as the first real Feature. This narrows the earlier manual-create requirement: use real declarations
and protected OFF provisioning; defer the standalone manual-create UI/API rather than adding a demonstration
TASK. Package `CreateFeature` still exists; this deferral does not claim its consumer qualification or silently
exclude it from the final coverage accounting owned by TICKET-00080.

Management supports that real rollout: OFF stops new AI proposal admission, PREVIEW restricts it to otherwise-
authorized administrators with the bound testing Permission, and ON retains ordinary management/provider checks.
Manual template editing/publication and safe lifecycle handling of admitted requests remain independent of the
flag. TICKET-00077 owns actual provider/browser/alternative-entry evidence; reusable Feature work stays upstream.
Select the real testing Permission/audience explicitly, without automatic grants or changing the terminal-first
execution priority. Setting ON is not deployment approval or permission to make a provider call.

The earlier three-TASK draft was not approved. The amendment allocated no TASK IDs; the maintainer subsequently
approved the single vertical management outcome below. No separate demo, manual-create or proof-only TASK.

### Approved TASK decomposition

[TASK-00297](../tasks/00297-TASK.md) owns the complete audience/status management browser/API path after
TASK-00036 and TASK-00296, which transitively supply provisioning, preparation, HTTP enforcement and the catalog/
frame. It includes inspection, the approved `MANAGE_FEATURES` `ADMIN_SAFE` extension initially granted only to
managed Super Admin, current actor/target/intent checks, both package setters, confirmation and browser invalidation.
This is one independently reviewable product prerequisite, not separate read-only or policy-infrastructure TASKs.

It is an unranked feature with status `needs-info`: trusted code/target/preparation/applicable-entry rollout
composition and safe active broken-binding repair remain unqualified. The preparation query is not deployment
authority; a repair must not require broken pre-state to pass preparation or restore live availability before
safety checks. Existing protected installation authorization cannot simply accept this ADMIN_SAFE Permission;
preserve its SUPER_ADMIN_ONLY boundary and implement the distinct management policy without role shortcuts.
Unknown support holds; no vendor patches, outer transactions or guessed upstream seam.

TICKET-00077 retains real AI admission/provider/browser evidence and the deliberate testing audience. Applicable
non-HTTP paths consume TASK-00294 without imposing unrelated Agent integration on human-only management. Neither
completed AI authoring nor this management journey reverse-blocks upstream primitives. TICKET-00074 consumes this
authority/detail/confirmation for its separate retirement operation. The terminal-first milestone and active work
remain unchanged. With TICKET-00074–00076, eighteen of 24 map TICKETs are decomposed; six remain, TICKET-00077–00082.
Next planning operation: `/skill:to-tasks TICKET-00077`; no implementation follows from this approval.

TICKET-00074 now maps [TASK-00298](../tasks/00298-TASK.md)'s complete retirement path after TASK-00297. It reuses
this management authority/detail/confirmation and invalidation without a new Permission or reverse dependency.
Its actual CURRENT/deployment-activation safety hold is distinct from rollout/active-repair qualification;
no premature cleanup chore or live retirement of `ai-template-proposals` is authorized.

Planning verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with
397 records and 310 active. `./bin/build` passed: 459 tests, 3499 assertions, 78 captures, zero violations.
Evidence: `.runs/build/20261013T024016Z-67998`; log: `.runs/build/ticket-00073-to-tasks.log`.
Sass deprecations, React-Bootstrap `use client` and >500 kB bundle warnings remain. These are fresh checks of
planning and the existing repository, not implementation, readiness, browser-management or live AI evidence.
Independent review and publication remain unperformed; no Feature, Permission or provider operation was enabled.
