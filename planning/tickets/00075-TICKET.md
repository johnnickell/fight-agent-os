---
id: TICKET-00075
epic: EPIC-00017
title: Establish database-authoritative Agent templates
status: ready-for-agent
---

# Establish database-authoritative Agent templates

## Problem statement

Repeated reconciliation from source definitions could overwrite human-approved browser revisions. Agent OS
needs one authoritative template history after initial setup, while preserving the reviewed starting catalog,
existing identities and exact resources already used by Agents and sessions.

## Solution and boundaries

Within the existing Harness bounded context, seed the eight reviewed managed SDLC templates from
[TICKET-00034](00034-TICKET.md) once with stable identities, complete reviewed content and provenance. Include
any separately approved Triage definition through its existing [EPIC-00012](../epics/00012-EPIC.md) owner;
this does not invent a ninth required role or redefine its behavior. After initial seeding, PostgreSQL owns
managed and custom template revisions, current selections, ancestry, scope and history. Subsequent deployment
or source changes must neither replace approved content nor select a different revision implicitly.

Give initial setup a bounded, attributable operation that recognizes a valid completed seed as a no-op, rejects
identity collisions and inconsistent/partial setup, and commits the complete intended initial set or rolls back.
Inspect actual existing Harness data before transition; do not treat a populated installation as empty, silently
adopt colliding resources or reset existing histories. Preserve historical revision bytes and references,
Agent-bound revisions and active sessions' immutable Harness snapshots. Current template selection uses
expected-state protection; revision history is not rewritten to manufacture rollback.

Safely change or retire the old recurring source reconciler **for Agent templates only** before enabling
[TICKET-00076](00076-TICKET.md)'s browser publication. Qualify subsequent deploy/reconcile behavior so it cannot
restore source authority. Reuse the same Harness resource identity and persistence boundary consumed by
[TICKET-00063](00063-TICKET.md), not a parallel registry. The explicit EPIC-00017 amendment supersedes only
WF-014/EPIC-00006's repeating template-source reconciliation assumption. No source/seed file is automatically
deleted, no Skill or Permission-definition authority changes, and Planning Markdown remains authoritative.
Exact schema, seed representation and transition mechanics belong to implementation planning.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Initialize reviewed templates | Protected Harness initial-seed operation | Stable managed identities, complete reviewed content and existing setup state | Owned seed/revision facts only after committed creation; none on a no-op | Complete initial catalog or rollback; no Agent or Permission grant |
| Inspect template authority | N/A — read-only inspection | Current selections, immutable revisions, scope, ancestry and managed-origin provenance | N/A — reading changes no business state | Safe authorized resource views distinguish installed history from source inputs |
| Preserve browser revisions through deploy | Explicit authorized transition of the old template reconciler | Existing references and current database authority | Attributable transition outcome where state changes; no fabricated template-change event | Later deployment cannot overwrite/select source revisions or alter active snapshots |

## Validation and permissions

Protected setup and template administration require current intent-specific managed `SUPER_ADMIN_ONLY`
management authority, granted only through the authoritative managed Super Admin Role, with target and scope
validation. A role name, source file or template key is not caller authority. Server reads must respect installation,
Workspace and registered-Repository scope; administrative access does not authorize disclosure to a provider.
Reject missing/invalid references, ambiguous identities and stale expected state. Preserve database uniqueness,
shared transaction behavior and immutable historical content; unknown storage/commit outcomes require authoritative
inspection before retry. Required state and audit evidence commit together, with external effects outside the
transaction under ADR 0002. Template requests are not AccessControl grants; seeding issues no credential.

## Acceptance and evidence

- Initial setup creates the complete approved catalog once; repeated setup preserves browser-authored current
  revisions, custom templates and historical bytes. Colliding/partial setup is a visible safe failure.
- Real PostgreSQL behavior covers competing setup, revision selection, rollback, references and uncertain
  outcomes; deployment/reconciler qualification directly demonstrates that database authority survives later deploys.
- Existing Agents and active sessions retain their exact referenced revisions; resource inspection is authorized
  and secret-free. Seeded resources alone do not establish a working Agent.
- Owned behavior tests and direct migration/setup/deployment checks plus `./bin/build` pass. Do not add product
  tests of migration files, wiring, generated artifacts or build/planning tools.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00017's five-TICKET split. Apply
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md)'s Harness identity and
snapshot rules with [EPIC-00017](../epics/00017-EPIC.md)'s explicit template-authority amendment, and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) /
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md). This supplies publication safety without
making the later editor a prerequisite for EPIC-00014's initial managed Agent work. No seed, schema, reconciler
transition or runtime authority was created by decomposition.

TICKET-00034's accepted [TASK-00248](../tasks/00248-TASK.md) now owns the reviewed starting catalog. This TICKET's
future primitive seed/storage TASKs consume those specifications; [TASK-00249](../tasks/00249-TASK.md) consumes
that actual database authority for profile resolution. Do not make seeding depend on TASK-00249, TASK-00250 or
whole TICKET-00034 completion. Keep one Harness template authority, no recurring source overwrites and no browser
editor prerequisite for the initial catalog. Precise TASK allocation here still requires separate decomposition
and approval; this coordination note creates no additional TASK or seed.
