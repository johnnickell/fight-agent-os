---
id: TICKET-00052
epic: EPIC-00012
title: Configure routes and start normal work
status: ready-for-agent
---

# Configure routes and start normal work

## Problem statement

The accepted Workflow can claim a TASK from a configured base, but a Triage category needs an approved route
and the Repository needs an explicit branching strategy before normal and emergency bases can be resolved
without guessing. A terminal selection or browser drag must not create a second start/eligibility authority or
a needless normal-route confirmation.

## Solution and boundaries

Extend versioned Repository configuration and setup with GitFlow, trunk-based or custom branching strategy and
explicit per-route base/target mapping. Deliver an initial versioned catalog that maps `feature`, `bugfix` and
`chore` to the **same** Team Lead → Software Engineer Workflow, with category-specific guidance where useful.
Use [TICKET-00051](00051-TICKET.md)'s typed Jev result or labeled normal-route fallback. Do not make an
unavailable evaluator a new gate when a valid declared-kind fallback exists; a material conflict or unsupported
route still stops. The configured base is verified from current Repository policy, not inferred from prose or a
hard-coded `develop`/`main` name.

A single deliberate, authorized terminal start request or browser Ready → In Progress intent permits automatic
normal routing. Without a second normal confirmation, invoke the **same** server-authoritative Start coordinated
work operation: revalidate canonical eligibility, current Planning/context/configuration/catalog revisions,
live grants, actor/Agent scope, limits, resources and base; atomically create exactly one Workflow, dispatch
intent and exclusive claim. The UI must show actual accepted, pending or refused state instead of treating a
drag as completion. Configuration for hotfix is supplied here; emergency confirmation/repair belong to
[TICKET-00053](00053-TICKET.md). Neither this TICKET nor Jev starts release work.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Set Repository strategy and routes | Existing versioned Repository configuration revision/restore operation, scoped to authorized owner | Inspect/preview bases, targets, strategy and catalog revision | Existing configuration-revised fact; not a Workflow event | Validated revision for future route resolution; no implicit branch changes |
| Route a selected normal TASK | Invoke the existing authoritative start using the single granted intent | Triage result, canonical eligibility, current configuration/catalog, actor and grant | Existing Workflow-start/claim and durable-dispatch facts only upon successful atomic start | One claimed Workflow to Team Lead; no second normal prompt or queue reorder |
| Explain refusal or duplicate request | Same idempotent start/recovery operations | Current owner, safe exclusion, exact revisions and dispatch status | Existing refusal/recovery facts as applicable | Same request returns its Workflow; conflicting/stale requests do not claim or launch another |

Command/event names describe ownership and expected effects; do not duplicate TICKET-00032's start handler.

## Validation and permissions

Only authorized Repository managers revise branching configuration; Agent prose and Jev cannot change it.
Triage can propose a catalog category but does not inherit start, claim or grant authority. The server checks
the initiator's bounded start intent, live authority, current route, base and canonical eligibility **at the
atomic claim**, independently of UI or model. Missing/invalid strategy or base, catalog mismatch and drift
stop with safe reasons; existing sessions retain their historical configuration snapshots. Validate
idempotency and competing claims under the existing WF-015/TICKET-00032 contracts, not a new queue or lease.
Normal routing does not add a second eligibility algorithm or change the first approved execution slice.

## Acceptance and evidence

- GitFlow, trunk and custom configuration resolve explicit route bases/targets and retain attributed immutable
  revisions; missing/invalid configuration never silently selects `develop` or `main`.
- A terminal request and a browser drag, when their surfaces exist, reach the same authorized start operation;
  one normal-work intent can dispatch automatically and no client-side move claims success prematurely.
- A current feature, bugfix or chore (including supported declared-kind fallback) maps deterministically to the
  initial standard Workflow; unsupported or conflicting categories do not trigger an extra Jev route call or
  an unauthorized start.
- Changed TASK/configuration/catalog/grant/eligibility between preflight and claim refuses the stale intent;
  repeated and competing requests produce one owner and no duplicate workflow, claim, process or dispatch.
- Prove owned strategy validation, route selection, authorization, atomic/idempotent integration and safe API/CLI
  behavior against representative revisions and concurrency; directly qualify Git branch existence/provider
  observations without tests of configuration text or existing start infrastructure itself.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00203](../tasks/00203-TASK.md) | Configure and preview Repository branching and normal routes | ready-for-agent |
| [TASK-00204](../tasks/00204-TASK.md) | Start classified normal work from the terminal | ready-for-agent |
| [TASK-00205](../tasks/00205-TASK.md) | Start normal work from a Ready-card intent | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00012](../epics/00012-EPIC.md), consuming
[WF-028](../wayfinder/tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md),
[TICKET-00025](00025-TICKET.md)'s versioned context, [TICKET-00027](00027-TICKET.md)'s canonical eligibility,
[TICKET-00032](00032-TICKET.md)'s existing start, and [TICKET-00051](00051-TICKET.md)'s classification.
No Repository policy, branch, Workflow or claim was changed by approving the requirement.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00203 — Configure and preview Repository branching and normal routes](../tasks/00203-TASK.md),
[TASK-00204 — Start classified normal work from the terminal](../tasks/00204-TASK.md), and
[TASK-00205 — Start normal work from a Ready-card intent](../tasks/00205-TASK.md). TASK-00203 consumes versioned
configuration/context (TASK-00081/00082); TASK-00204 consumes classification/disposition, currentness and the
existing Team Lead execution path (TASK-00202, TASK-00084, TASK-00141); TASK-00205 consumes the terminal/server
start and canonical browser Ready view (TASK-00204, TASK-00111). All remain unranked. This split adds no
Workflow, claim, Jev call or UI gesture. Remaining map requirements retain separate TASK decompositions.

TICKET-00064's subsequent accepted TASK-00261/00262 supply current Team/Agent facts and fair whole-TASK intake;
TASK-00204 now explicitly consumes TASK-00263's Team-specific admission through the same TASK-00139 Start.
TASK-00141 supplies real same-Team execution using TASK-00264, not another selector or a cross-Team peer grant.
TASK-00265 consumes these terminal/browser paths and extends their existing Execution owner with durable
withdrawable Pending start and actual selected-Team integration. TASK-00205 stays the pointer/keyboard gesture
owner; no reverse dependency on that final integration or duplicate route/start/claim/queue is introduced.
Normal routing still needs no second routine confirmation, and unknown commit or withdrawal losing to start
must expose authoritative state rather than replay or cancel live work. Actual prerequisite holds remain.
