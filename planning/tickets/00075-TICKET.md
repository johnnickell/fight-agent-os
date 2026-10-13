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
| [TASK-00299](../tasks/00299-TASK.md) | Initialize database-owned Agent templates without source overwrite | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00017's five-TICKET split. Apply
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md)'s Harness identity and
snapshot rules with [EPIC-00017](../epics/00017-EPIC.md)'s explicit template-authority amendment, and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) /
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md). This supplies publication safety without
making the later editor a prerequisite for EPIC-00014's initial managed Agent work. No seed, schema, reconciler
transition or runtime authority was created by decomposition.

TICKET-00034's accepted [TASK-00248](../tasks/00248-TASK.md) owns the reviewed starting catalog.
[TASK-00299](../tasks/00299-TASK.md) now owns the approved single initialization/inspection outcome, consuming
TASK-00248, TASK-00036's managed authority and TASK-00078's Workspace/Repository identities.
[TASK-00249](../tasks/00249-TASK.md) consumes this actual database authority for profile resolution, never the
reverse. Keep one Harness template authority, no recurring source overwrites and no browser editor prerequisite
for the initial catalog; whole TICKET-00034 or completed Agent/session journeys are not seed prerequisites.

TICKET-00063's accepted TASK-00257/00258 consume the exact database-backed profile for preview and named creation;
TASK-00260 integrates actual browser administration. The separately owned Triage definition must reach this
seed/resource boundary through a precise accepted primitive handoff, not a dependency on TASK-00200's completed
diagnosis (which now consumes TASK-00258). Map that input with the existing Triage owner during decomposition;
TASK-00260 retains the explicit hold. Neither seed/storage nor TASK-00249 resolution may depend on named
creation, session integration or final administration. There is no duplicate Triage catalog or editor gate.

### Approved TASK decomposition

The maintainer approved one unranked feature, TASK-00299, initially `needs-info`. It delivers protected atomic
initialization and bounded authorized inspection together with stable identities, immutable revisions/current
selections, scope/ancestry/provenance and preservation of existing bindings/history. Repetition recognizes complete
setup without comparing today's source to edited current rows or resetting custom templates. PostgreSQL competition,
selection/reference races, required audit/rollback, uncertainty and actual writer/deployment qualification remain
inside this outcome, not separate schema, inspection, transition or proof-only TASKs.

The approved `MANAGE_AGENT_TEMPLATES` Permission is managed `SUPER_ADMIN_ONLY`, assigned only through managed
Super Admin. Preserve the whole TASK-00036 policy and other accepted extensions; generate/version the stable ID
through the owning factory/library during implementation. Every supported path checks current trusted human
identity, intent and target/scope. This is neither a direct Agent grant nor publication/creation authority merely
because a template exists; later publication remains TICKET-00076's actual operation over the same store.

Source inspection found no implemented Agent-template store or recurring template reconciler in the inspected
`src/`, `config/` and migration sources. Actual installed state still needs inspection: do not assume every target
is empty or invent a legacy migration. Exclude actual old template writers before publication can become available,
without changing Skill/policy authority, deleting seed files or migrating Planning Markdown.

Required Harness resource/reference contracts and the independently deliverable Triage definition/registration
handoff remain unmapped readiness holds. EPIC-00012/TASK-00200 retains Triage ownership; no ninth required starting
role, duplicate catalog or completed-diagnosis dependency is introduced. Mapping TASK-00299 resolves only
TASK-00249's database-owner gap, not its remaining resource/model or current scoped-read guarantees.

This was EPIC-00017's first accepted TASK decomposition. With TICKET-00076/00077, three of this EPIC's five and nineteen
of the map's 24 TICKETs are decomposed; five remain, TICKET-00078–00082, alongside older incomplete Harness/managed
review planning. Active work and priorities are unchanged. Next: `/skill:to-tasks TICKET-00078`.
No implementation follows.

TICKET-00076 now maps [TASK-00300](../tasks/00300-TASK.md)'s actual human publication over this store/authority,
with [TASK-00301](../tasks/00301-TASK.md)'s retained-revision/ancestor review downstream. Candidate validation and
final publication/reference fencing remain their qualified composition, not a guarantee inferred from seeding.
Neither completed editor nor AI proposals reverse-blocks this initial database outcome.

Planning verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with
399 records and 312 active. `./bin/build` passed: 459 tests, 3499 assertions, 78 captures, zero violations.
Evidence: `.runs/build/20261013T152904Z-72221`; log: `.runs/build/ticket-00075-to-tasks.log`.
Sass deprecations, React-Bootstrap `use client` and >500 kB bundle warnings remain. These are fresh planning/
existing-repository checks, not template initialization, deployed writer exclusion, profile resolution or Agent
qualification. Independent review/publication remain unperformed; no seed, policy or runtime change ran.
