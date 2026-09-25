---
id: TICKET-00027
epic: EPIC-00005
title: Deliver the canonical Planning queue and Roadmap
status: ready-for-agent
---

# Deliver the canonical Planning queue and Roadmap

## Problem statement

Humans, the Dashboard, the terminal `next` surface, and later Coordinators need one authoritative answer for
priority and executable TASK eligibility. Separate browser ordering, stored Blocked statuses, or client-side
eligibility rules would produce contradictory work queues and unsafe execution choices.

## Solution and boundaries

Deliver workspace-scoped Roadmap order, TASK order and explicit queue overrides as semantic Planning behavior on
top of TICKET-00026. Provide one application query for the ordered executable TASK frontier and stable reasoned
inclusion/exclusion of candidate work. Priority applies only after eligibility; active claimed work takes
precedence; blocked work retains its priority for later eligibility.

Project the result into repository/workspace Roadmap, hierarchy-progress and Kanban-style browser views without
creating another lifecycle model. The existing Markdown `next` skill remains authoritative only until TICKET-00028
cutover; afterward every agent-facing and browser surface must use this application capability.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Order the Roadmap | `ReorderRoadmap` against expected ordering revision | Preview affected EPIC/TICKET order and authorization scope | `RoadmapReordered` | Workspace or repository-scoped priority changes atomically without changing artifact lifecycle |
| Override TASK queue position | `SetTaskQueueOverride`, `ClearTaskQueueOverride` | Preview inherited Roadmap priority, existing override and blockers | `TaskQueueOverrideSet`, `TaskQueueOverrideCleared` | An explicit attributed TASK priority persists until changed/cleared and does not make blocked work executable |
| Find executable work | N/A — read-only canonical query | `FindExecutableTasks` with actor, Workspace/repository and bounded page/filter | N/A — eligibility is derived | Ordered TASKs include stable reason codes and human explanations for inclusion and exclusion |
| Explain one TASK | N/A — read-only interaction | `ExplainTaskEligibility` | N/A — reads emit no Domain event | The user sees lifecycle, parent, dependency, claim, authority, context and later capability facts contributing to the result |
| Browse Roadmap and queue | N/A — read-only projections | Roadmap, hierarchy progress, queue lanes, archive and dependency-impact queries | N/A — projections do not mutate Planning | Dashboard and terminal consumers render the same ordering and eligibility semantics |

## Validation and permissions

An executable TASK is unarchived and Ready for Agent; its TICKET and EPIC are active, unarchived and
implementation-permitting; every blocker is Done; actor/Workspace/repository scope and Planning authority/context
are valid; and no conflicting active claim exists. Later Runner, checkout and capability requirements extend this
same query without replacing it. Won't Fix does not satisfy a blocker.

Use lower positive authored order first, then unranked records, with stable repository/reference tie-breakers.
Roadmap priority flows to descendants; explicit TASK override controls position among eligible work. Standalone
bugs/chores receive direct placement. Derived lanes such as Ready, Blocked, Human Action and Complete are
projections, not mutable statuses. Do not add manual Blocked or Hold in this EPIC and do not infer Planning status
from GitHub or merge observations.

Viewing the queue requires Planning view authority. Workspace-wide, repository, Roadmap and TASK override
operations require scope-appropriate server-side Permissions and expected ordering revisions. React drag/drop may
express a specific command intent but cannot apply or simulate a successful authoritative reorder client-side.

## Acceptance and evidence

- Dashboard and application/terminal consumers receive identical results from one canonical eligibility
  capability for the same actor and revision.
- Unit behavior covers every accepted inclusion/exclusion reason, priority inheritance, overrides, standalone
  work, Won't Fix blockers, archived/terminal parents and deterministic ties.
- PostgreSQL concurrency evidence proves stale/competing Roadmap and queue commands fail without partial reorder.
- Direct and transitive dependency explanations remain readable and blocked work retains its authored priority.
- Queue projections expose current/archived and derived lanes without persisting Blocked or conflating TASK Done,
  Review, PR, merge and delivery facts.
- Manually compare the migrated Fight Agent OS Board/next frontier with the canonical query after TICKET-00028;
  discrepancies block cutover or require explicit migration correction, not test-only normalization.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00094](../tasks/00094-TASK.md) | Version authoritative Roadmap ordering | ready-for-agent |
| [TASK-00095](../tasks/00095-TASK.md) | Version TASK sequencing and queue overrides | ready-for-agent |
| [TASK-00096](../tasks/00096-TASK.md) | Resolve canonical executable TASK eligibility | ready-for-agent |
| [TASK-00097](../tasks/00097-TASK.md) | Expose canonical Roadmap and work-queue views | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the priority and eligibility portions of
[WF-010](../wayfinder/tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) and the direct browser
projection boundary from [WF-012](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md).
It depends on TICKET-00026 and supplies canonical ordering/eligibility to TICKET-00028 and TICKET-00029. Workflow
start and claims remain later EPIC behavior that will extend, not fork, this query.
