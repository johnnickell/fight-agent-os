---
id: TICKET-00053
epic: EPIC-00012
title: Execute a confirmed direct hotfix
status: ready-for-agent
---

# Execute a confirmed direct hotfix

## Problem statement

An urgent production bug needs a short path from Triage to repair on the production base, without carrying
unfinished development changes into production or allowing the repairer to review its own work. A normal bugfix
already started on a development base may also become an emergency after new evidence arrives.

## Solution and boundaries

For an urgent `bugfix` proposal, show the incident scope and verified production base resolved from
[TICKET-00052](00052-TICKET.md)'s Repository strategy, then require **one quick human emergency confirmation**
before start/claim. Do not ask the human to confirm the branching strategy again. Start a separate bounded
hotfix Workflow directly with the existing senior-capability Hotfix Engineer; the initiating human or named
incident owner receives progress, blockers and scope decisions without a hidden Team Lead stage. PHP owns
transitions and safe handoffs. Use isolated work, smallest scoped repair, regression evidence, required checks,
non-contributing Senior Engineer technical review, applicable targeted QA and Release Manager PR handoff.
Required check exceptions need a separately explicit human grant, recorded risk and containment. Hotfix start
does not authorize merge, release or deployment.

If a normal bugfix is already active and an emergency is recognized, pause its Workflow. Reconcile its effects
and exclusive claim, preserve attributable history, obtain an emergency grant and create a linked, separately
authorized hotfix Workflow for the **same TASK** from the verified production base. Do not promote a Software
Engineer session into a Hotfix Engineer, retarget its development branch, automatically copy unreviewed work
or merge unrelated `develop` changes into `main`. Unknown ownership or external effect outcome prevents a
second concurrent executor. Post-PR merge-back is owned by [TICKET-00054](00054-TICKET.md), not an extra TASK.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Confirm and start a hotfix | Confirm emergency scope/base; authoritative start with separate bounded grant | Current TASK/category/priority, Repository production base, live grant and eligibility | Emergency confirmation and existing start/claim/dispatch facts | One hotfix Workflow with isolated repairer and direct human contact |
| Complete independent hotfix review and handoff | Existing phase/handoff and authorized publication operations | Exact changed subject, checks, findings, QA and authority | Repair/review/QA/publication facts on the hotfix Workflow | Reviewed, verified, published PR against configured production base; no approval, merge or deployment |
| Escalate a started normal bugfix | Pause, safely transfer ownership under new emergency grant, start linked Workflow | Existing claim, attempts, resources, process/effect state and approved incident scope | Pause, authorized reconciliation and linked-start facts | Same TASK, preserved old history and at most one active executor, without development-base contamination |

Names describe semantic interactions; re-use the existing Workflow/Review/QA/landing contracts rather than
creating a parallel evidence or authorization system.

## Validation and permissions

Only an authorized human confirms emergency treatment; urgency from Jev is a recommendation, not permission.
A hotfix Agent's senior capability does not grant independent review of its own contribution. Different
credentials/sessions and actual contributor checks protect the Senior Engineer verdict. Validate exact TASK,
context/configuration/base, grant, limits, claim, isolation and handoff snapshots at every authority-bearing
transition. No skipped sandboxing, required verification or QA solely due to urgency. Human exception approval
must be scoped to the exact check and record risk, rollback and follow-up without fabricating green evidence.
Publishing remains a PR handoff; effect grants for release or production deployment are unrelated.

## Acceptance and evidence

- One quick emergency confirmation names incident and verified production base, with no redundant strategy
  prompt; unconfirmed, missing/changed base or stale grant starts nothing.
- Repair is by Hotfix Engineer; a non-contributing Senior Engineer independently assesses exact evidence, QA
  exercises applicable behavior, and Release Manager can publish only after current acceptance and authority.
- The incident owner receives progress and escalations, with clear stop on limits, scope changes or required
  evidence; no concealed Team Lead phase, self-acceptance or implicit release/deployment.
- A normal-to-hotfix transfer preserves the same TASK and prior evidence but safely fences old processes,
  effects and claims before the new Workflow starts on its configured production base. Uncertain state pauses;
  unrelated development changes are not included.
- Verify owned emergency grants, contributor independence, claim-transfer races, stale evidence, exception
  handling, review/QA gates and isolated production-base behavior with focused application and integration
  evidence, not product tests of the sandbox/configuration tooling itself.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00206](../tasks/00206-TASK.md) | Confirm and repair a direct hotfix | needs-info |
| [TASK-00207](../tasks/00207-TASK.md) | Independently verify and hand off a hotfix PR | needs-info |
| [TASK-00208](../tasks/00208-TASK.md) | Transfer an active normal bugfix to a linked hotfix | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00012](../epics/00012-EPIC.md) under
[WF-028](../wayfinder/tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md). Consumes
[TICKET-00051](00051-TICKET.md)'s classification, [TICKET-00052](00052-TICKET.md)'s configured routes,
[TICKET-00032](00032-TICKET.md)'s authoritative execution and
[TICKET-00033](00033-TICKET.md)'s applicable QA boundary; managed independent review and publication
require the accepted [EPIC-00008](../epics/00008-EPIC.md) capabilities when available. This requirement did
not create an incident, Workflow, emergency grant or PR.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00206 — Confirm and repair a direct hotfix](../tasks/00206-TASK.md),
[TASK-00207 — Independently verify and hand off a hotfix PR](../tasks/00207-TASK.md), and
[TASK-00208 — Transfer an active normal bugfix to a linked hotfix](../tasks/00208-TASK.md). TASK-00206 depends on
TASK-00204 and TASK-00142, but remains `needs-info` until TICKET-00034's unplanned Hotfix Engineer profile
capability has a real qualified implementing TASK. TASK-00207 depends on TASK-00206 and remains `needs-info`:
EPIC-00008's `done` metadata and the completed local QA skill do **not** establish managed independent Review,
QA and Release Manager publication. Identify/approve their owning implementation and real TASK dependencies
before executing this integration; do not duplicate a generic pipeline in hotfix work. TASK-00208 depends on
TASK-00206, TASK-00204 and TASK-00142. All are unranked. TICKET-00054 owns post-merge follow-through; no
runtime effect was created by these planning records.
