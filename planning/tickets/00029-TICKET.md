---
id: TICKET-00029
epic: EPIC-00005
title: Deliver the registered Planning Dashboard
status: ready-for-agent
---

# Deliver the registered Planning Dashboard

## Problem statement

Authenticated users need one honest browser workspace for registered repository identity, resolved context, and
direct authoritative Planning operations. A UI that recreates Domain rules, masks stale state, provides generic
document replacement, or shows fabricated Agent/session capability would be unsafe and misleading.

## Solution and boundaries

Extend the EPIC-00004 authoritative application shell with a repository-scoped Planning workspace backed only by
TICKET-00024 through TICKET-00028 commands and queries. Let users select a registered repository, inspect checkout
and context state, browse complete Planning hierarchy/history, perform permission-filtered semantic edits and
lifecycle operations, view Roadmap and canonical queue, and inspect current or archived records.

Use command-oriented forms and complete guarded-operation previews against expected revisions. React owns
interaction and safe transport mapping, never Planning invariants. This EPIC provides direct human Planning only;
resumable browser Agent conversations and AI-authored proposals belong to the next Harness/browser-Agent EPIC.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Select a registered repository | N/A — repository selection/navigation is read-only | List visible repositories; get registration, designated checkout, availability, planning mode and context summary | N/A — navigation emits no Domain event | URL/shareable repository scope changes without crossing authorization or carrying stale authority implicitly |
| Inspect repository context | N/A — read-only interaction | Resolve current context summary, revision, instruction provenance, conflicts and availability | N/A — reading context emits no Domain event | User sees effective source facts, warnings, stale/unavailable reasons and permitted configuration actions |
| Browse Planning | N/A — read-only interaction | Hierarchy, artifact detail/history, criteria/evidence, dependency, progress, archive and rendered-view queries | N/A — reads emit no Domain events | Complete repository Planning is progressively available without loading every history or hiding absent detail |
| Edit Planning directly | Dispatch the exact TICKET-00026 semantic command represented by the form | Query current aggregate version, validation choices and impact preview | The owning Planning aggregate emits its semantic event | One reviewed field/section/child/relation changes or the stale command is rejected; no whole-document overwrite occurs |
| Perform guarded lifecycle or relationship work | Dispatch exact closeout, reopen, wontfix, reparent, dependency, waiver, archive or restore command | Preview eligibility, downstream impact, child/evidence state and retained links | Owning aggregate events only | Required confirmation is explicit and no cascade, inferred status or hidden secondary mutation occurs |
| View and reorder Roadmap/queue | Dispatch TICKET-00027 intent-specific reorder/override commands | Canonical Roadmap, queue, eligibility and explanation queries | TICKET-00027 semantic events | UI reflects server-confirmed ordering; rejected/stale drags return to authoritative state with reason |
| Inspect migration and authority | Dispatch only authorized rehearsal/approval/cutover operations exposed by TICKET-00028 | Query current mode, source/report revision, warnings and rendered Markdown | TICKET-00028 migration/authority events | Users can operate the guarded transition without treating a rehearsal or render as authority |
| Archive and restore visibility | Dispatch artifact-specific archive/restore commands | Independently paginated current or archived result queries | Owning aggregate archive/restore events | The complete result mode changes without implying completion, cancellation or deletion |

## Validation and permissions

Use the existing typed `/api/v1` JSend, validation, safe-error, OpenAPI, current-principal, route, API-client,
component, responsive, theme and security foundations. Every query and command revalidates server-side Permission,
repository/Workspace scope, active lifecycle, expected revision and relevant current context/authority. Client route
or control visibility never grants access.

Viewing repository registration, context, Planning, history/evidence and archive may have distinct scopes.
Creating/revising records, relationships/dependencies, Roadmap/queue, lifecycle, closeout, waiver, archive/restore,
configuration and migration/cutover use independently justified Permissions. Unknown/stale principal or repository
scope fails closed. Safe API responses exclude filesystem details not needed by the actor, secrets, raw events,
persistence models and unauthorized records.

Provide explicit loading, empty, denied, not-found, stale revision, partial query, conflict, unavailable checkout,
unavailable service, frozen Markdown, migration warning/failure and offline read-only states. V1 does not persist
manual form drafts or autosave keystrokes; warn before leaving dirty forms. Preserve durable authoritative state on
failure and never imply that a client-side optimistic move succeeded before server confirmation.

## Acceptance and evidence

- An authenticated authorized user can select Fight Agent OS, understand its registration/designated-checkout and
  current context/Planning authority, and navigate all migrated Planning record types.
- Direct forms dispatch shared semantic commands, show validation/impact, reject stale revisions without data loss
  to authority, and never expose a generic Markdown/document update route.
- Roadmap, queue, hierarchy progress and eligibility explanations match TICKET-00027 rather than client rules.
- Current/archive modes, lifecycle actions, closeout, dependencies and guarded operations preserve TICKET-00026
  invariants and exact server permissions.
- Migration rehearsal, approval, cutover state and on-demand Markdown rendering are understandable without
  presenting rehearsal/render output as authority.
- Responsive narrow/wide, keyboard/focus, loading/empty/error/denied/stale/partial/offline states receive focused
  component/behavior evidence under the accepted design system.
- Manually operate the real migrated Fight Agent OS Planning workspace. Automate owned client behavior and critical
  API contracts, but do not add broad browser suites or tests of React, routing, documentation or framework
  mechanics merely to prove that the browser works.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00105](../tasks/00105-TASK.md) | Explore the registered Planning workspace design | ready-for-agent |
| [TASK-00106](../tasks/00106-TASK.md) | Independently accept the Planning workspace handoff | ready-for-agent |
| [TASK-00107](../tasks/00107-TASK.md) | Deliver repository registration, selection, and context workspace | ready-for-agent |
| [TASK-00108](../tasks/00108-TASK.md) | Browse complete Planning hierarchy and history | ready-for-agent |
| [TASK-00109](../tasks/00109-TASK.md) | Edit Planning through semantic forms | ready-for-agent |
| [TASK-00110](../tasks/00110-TASK.md) | Operate guarded lifecycle and relationship work | ready-for-agent |
| [TASK-00111](../tasks/00111-TASK.md) | Operate the canonical Roadmap and work queue | ready-for-agent |
| [TASK-00112](../tasks/00112-TASK.md) | Operate migration and Planning authority | ready-for-agent |
| [TASK-00113](../tasks/00113-TASK.md) | Complete integrated Planning Dashboard states and accessibility | ready-for-agent |
| [TASK-00114](../tasks/00114-TASK.md) | Qualify the migrated Planning Dashboard | ready-for-human |
<!-- /planning:children -->

## Decisions and progress

Implements the direct human browser portions of
[WF-012 — Define browser planning and conversation ownership](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md)
while explicitly deferring browser Agent conversations. It depends on TICKET-00024 through TICKET-00028 and reuses
TICKET-00010, TICKET-00012, TICKET-00014, TICKET-00017, TICKET-00018 and TICKET-00022 rather than recreating API,
React, design, authentication, shell or integrated security foundations.
