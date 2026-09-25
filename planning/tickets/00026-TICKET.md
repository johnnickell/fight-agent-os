---
id: TICKET-00026
epic: EPIC-00005
title: Establish authoritative Planning artifacts and lifecycles
status: ready-for-agent
---

# Establish authoritative Planning artifacts and lifecycles

## Problem statement

Fight Agent OS needs one PostgreSQL-authoritative model for complete Wayfinder and EPIC → TICKET → TASK Planning.
Generic documents, mutable Markdown rows, last-write-wins updates, or repository-global locking cannot preserve the
accepted identities, semantic history, hierarchy, criteria, dependencies, evidence, and lifecycle invariants.

## Solution and boundaries

Deliver event-sourced aggregate roots for `WayfinderMap`, `WayfinderTicket`, `Epic`, `Ticket`, and `Task`, plus the
independently revisioned Planning evidence artifacts `ResearchNote` and `Prototype`. Use Fight Common event-store
contracts, application-owned metadata and idempotency, COMB-backed internal identities, immutable repository-local
human references, semantic commands/events, transactionally current constraint projections, and rebuildable read
projections.

Acceptance criteria, decision points, use cases, evidence references, prototype findings, and similar subordinate
concepts use stable child identities under their owning aggregate. Preserve the established lifecycle values,
archive as an orthogonal state, explicit closeout, standalone bug/chore TASKs, same-repository parentage, typed
Wayfinder and TASK dependencies, and cross-repository TASK dependencies within the Workspace.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create a Planning artifact | Intent-specific `CreateWayfinderMap`, `CreateWayfinderTicket`, `CreateEpic`, `CreateTicket`, `CreateTask`, `CreateResearchNote`, or `CreatePrototype` | Preview parent, reference allocation, uniqueness, lifecycle and dependency validity | Typed creation event such as `TaskCreated` | One aggregate stream, immutable internal identity and human reference/slug are created idempotently; parent and allocator constraints commit atomically |
| Revise structured Planning content | Section/child-specific commands against an expected aggregate version | Query current normalized sections, criteria, decisions and intervening revisions | Semantic events such as `EpicDestinationRevised` or `DecisionPointAccepted` | Complete normalized values or child changes append without keystroke, Markdown-diff, JSON-Patch, or generic-document events |
| Change hierarchy or dependencies | Explicit reparent/add/remove dependency commands with reason and expected revisions | Preview same-repository parent rules, cycles, terminal state, active links and downstream impact | `PlanningArtifactReparented`, `DependencyAdded`, `DependencyRemoved` or type-specific equivalents | Valid relationships and command-critical constraint projections change transactionally; cycles and cross-boundary relationships fail |
| Transition lifecycle | Type-specific ready, needs-info, start, complete, wontfix, reopen, close-map/ticket and closeout commands | Explain transition prerequisites, child outcomes, criteria, impact and unresolved decisions | Type-specific semantic lifecycle events | Only accepted transitions occur; child completion never closes a parent automatically |
| Record criteria and evidence | Add/revise/retire/satisfy/waive criterion and evidence commands | Query criteria, dispositions, provenance, related commits/artifacts/URLs and supersession | Criterion/evidence semantic events | Stable children retain actor, reason, time and immutable evidence links; waiver and correction remain explicit |
| Archive or restore | Type-specific archive/restore commands | Preview terminal, child, parent and retained-link requirements | `PlanningArtifactArchived`, `PlanningArtifactRestored` or type-specific equivalents | Visibility changes independently of lifecycle; restore preserves prior state and may require parent restoration |
| Read Planning history and hierarchy | N/A — read-only interactions | Repository hierarchy, aggregate detail/version/history, decisions, criteria, dependencies, evidence, archive and closeout readiness queries | N/A — reads emit no Domain events | Rebuildable views explain current state and provenance without treating projections as authority |
| Render authoritative Markdown | N/A — deterministic representation query | Query typed current Planning views and template revision | N/A — rendering is read-only | Copy-ready Markdown identifies authority, aggregate revision and template version and cannot be ingested as an ordinary update |

## Validation and permissions

Allocate human references atomically per repository and artifact kind and never reuse them. A WayfinderTicket has
one map, a requirement TICKET one EPIC, and a TASK zero or one TICKET only under the existing standalone rules.
Maps close only with resolved/delegated fog and approved handoff; WayfinderTickets require synthesized resolution;
TICKET and EPIC closeout revalidates children, criteria, outcomes and evidence. A human always confirms EPIC
completion and every judgment-bearing closeout or waiver.

Prevent dependency cycles across the Workspace. TASK `done` remains implementation acceptance, not independent
Review, PR publication, merge, release, deployment, or certification. Won't Fix requires an explicit downstream
impact plan; never cascade status or remove dependencies automatically. Active/non-terminal records cannot archive.
Cross-repository moves create a linked replacement rather than rewriting ownership/history.

Every command authenticates the actor, checks its intent-specific Permission and expected stream/constraint
versions, and is idempotent by command identity. Stale commands return current version/intervening facts without
silent merge. Viewing, creating, revising, relationships, lifecycle, criteria/evidence, waiver, closeout,
archive/restore, redaction, and cross-repository relationships receive independently justified authorization.
Emergency redaction is narrow, attributed and tombstoned; it never becomes ordinary editing.

## Acceptance and evidence

- Every accepted Planning root and stable child has the required typed identity, ownership, immutable reference,
  stream history, current projection and provenance.
- Semantic command tests cover accepted and rejected lifecycle, hierarchy, criteria, dependency, closeout,
  archive/restore, idempotency, stale version, and authorization behavior.
- PostgreSQL integration tests prove atomic reference allocation, command-critical constraints, parent validity,
  dependency-cycle prevention, concurrent revisions and exact event/projection recovery where database behavior is
  authoritative.
- Rebuilding projections from streams produces the same current Planning state; projections cannot mutate streams.
- Wayfinder, research, prototype, EPIC, TICKET, TASK, standalone record, evidence and rendered-Markdown behavior
  preserve the closed WF-010 semantics without creating one generic document aggregate.
- Test owned business behavior and important persistence contracts; do not test event-store libraries, templates as
  prose, migration tooling, framework mechanics, or the quality gate merely to manufacture coverage.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00085](../tasks/00085-TASK.md) | Establish event-sourced Wayfinder planning authority | ready-for-agent |
| [TASK-00086](../tasks/00086-TASK.md) | Complete Wayfinder decisions and handoff readiness | ready-for-agent |
| [TASK-00087](../tasks/00087-TASK.md) | Version research notes and prototype evidence | ready-for-agent |
| [TASK-00088](../tasks/00088-TASK.md) | Create and revise EPIC and TICKET artifacts | ready-for-agent |
| [TASK-00089](../tasks/00089-TASK.md) | Create and revise implementation TASK artifacts | ready-for-agent |
| [TASK-00090](../tasks/00090-TASK.md) | Enforce Planning hierarchy and dependency revisions | ready-for-agent |
| [TASK-00091](../tasks/00091-TASK.md) | Record stable Planning criteria and evidence | ready-for-agent |
| [TASK-00092](../tasks/00092-TASK.md) | Complete Planning lifecycle and archive operations | ready-for-agent |
| [TASK-00093](../tasks/00093-TASK.md) | Rebuild, redact and render Planning authority | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements [WF-010 — Define the authoritative Planning domain and lifecycle](../wayfinder/tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md)
and reuses [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) plus
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md). It depends on the EPIC-00003 persistence
foundation and the TICKET-00024 Repository identity contract; domain work may proceed once that contract is stable.
TICKET-00027 adds priority/eligibility, and TICKET-00028 owns Markdown import and authority switching.
