# Wayfinder Map: SDLC intake triage

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map summarizes the linked decisions and shows the next decision frontier.

## Destination

Define a future Triage Agent template for a human-selected TASK: classify its work and priority with Jev, surface
material routing conflicts or emergency decisions, and hand it to the appropriate authorized Workflow. Canonical
Planning eligibility and Workflow start retain readiness and execution authority.

**Done** = the linked decisions are closed; remaining fog is resolved or explicitly excluded; and an approved
handoff identifies amendments to team-template and execution planning. Creating or decomposing those amendments
is a separate operation; this map does not insert a new gate into current execution automatically.

## Notes

- Charted on 2026-09-28 from John's requested intake idea. WF-027 owns the proposed responsibility and classification
  questions; WF-028 owns dispatch and the hotfix role boundary.
- The executable item is a TASK under EPIC → TICKET → TASK. A requirements TICKET may contain several TASKs;
  "next ticket up" must not silently become execution of the whole parent TICKET.
- Preserve [canonical eligibility](../tasks/00096-TASK.md),
  [Team roles](../../docs/engineering/TEAM.md), [template planning](../tickets/00034-TICKET.md), and
  [Workflow authority](tickets/WF-015-define-coordinated-task-authority-protocol.md). Triage supplements the
  authoritative eligibility query with bounded judgment; it must not invent a competing queue or permission check.
- [Hierarchical memory](hierarchical-agent-memory-map.md) may support later triage learning. Triage semantics can
  be decided independently; this map does not depend on a memory implementation.

The Jev proposals added on 2026-10-07 use the shared capability planned in
[TICKET-00037](../tickets/00037-TICKET.md). WF-027's approved classification contract now resolves its scope;
WF-028 resolves dispatch and hotfix-role questions. Neither inserts a prerequisite into the approved first Harness
or Team Lead → Software Engineer milestone. See the
[follow-on coverage handoff](../epics/00006-EPIC.md#follow-on-jev-decision-coverage--2026-10-07) for uses outside this map.

## Decisions so far

1. **[WF-027 — Define TASK triage readiness and classification](tickets/WF-027-define-task-triage-readiness-and-classification.md)
   is closed.** Triage receives a human-selected TASK from terminal or browser intent, calls bounded Jev for every
   selection, classifies feature/bugfix/chore and low/medium/high/urgent priority, and routes on a current typed
   recommendation. Enhancement is feature; hotfix is a separate emergency route for an urgent bug only after human
   confirmation. Material declared-kind conflicts require human resolution; unavailable/unknown Jev classification
   uses a labeled declared-kind normal-route fallback with unknown priority. Triage never re-approves readiness,
   changes Planning metadata or claims execution; canonical eligibility and start authority retain those duties.
2. **[WF-028 — Define triage dispatch and hotfix role boundaries](tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md)
   is closed.** Initial feature/bugfix/chore routes share the standard Team Lead Workflow; direct hotfix uses Hotfix
   Engineer with independent Senior Engineer review, quick human emergency confirmation and Repository-configured
   production base. Future `release` TASKs use a separate Team Lead-coordinated project-specific Workflow, with
   human signing and effect grants. Triage preflights without claiming; the single authorized normal start intent
   automatically routes through authoritative atomic start, without another normal-work confirmation. GitFlow
   hotfix merge-back is offered as one authorized Yes/button action in the same hotfix history, not a new TASK.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-027](tickets/WF-027-define-task-triage-readiness-and-classification.md) | Define TASK triage readiness and classification | wayfinder:grill | HITL | Closed | — |
| [WF-028](tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md) | Define triage dispatch and hotfix role boundaries | wayfinder:grill | HITL | Closed | [WF-027](tickets/WF-027-define-task-triage-readiness-and-classification.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-027 readiness and classification → WF-028 dispatch and hotfix roles → Planning handoff
```

## Frontier

No open decision remains on this map. Both approved EPIC destinations have accepted requirement TICKETs.
All eight requirement TICKETs across EPIC-00012/00013 now have approved TASK decompositions. Map-wide
planning phases are complete. Consult the authoritative Board through `/skill:next` for execution selection;
this map does not authorize a Workflow or set Board priority.

## Planning handoff

- First destination: **[EPIC-00012 — Introduce TASK Triage and the direct hotfix route](../epics/00012-EPIC.md)**
  was approved and written as a later execution slice without retroactively blocking the approved first Team Lead →
  Software Engineer milestone. Amend team-template planning for distinct Triage identity and hotfix coordination; extend execution planning for preflight, one authorized
  normal start, versioned route catalog, Repository branching-strategy configuration, emergency confirmation,
  independent review, safe normal-to-hotfix transfer and same-hotfix post-merge Yes/button merge-back. Reuse canonical
  eligibility and the WF-015 authoritative start/claim and recovery contracts. Its approved requirements are
  [TICKET-00051](../tickets/00051-TICKET.md) (classification), [TICKET-00052](../tickets/00052-TICKET.md)
  (routes/normal start), [TICKET-00053](../tickets/00053-TICKET.md) (direct hotfix), and
  [TICKET-00054](../tickets/00054-TICKET.md) (same-hotfix merge-back). See
  [WF-027](tickets/WF-027-define-task-triage-readiness-and-classification.md) and
  [WF-028](tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md).
- Second destination: **[EPIC-00013 — Support release TASKs and project-specific release Workflows](../epics/00013-EPIC.md)**
  was approved and written for a standalone `release` TASK kind and project-specific runbook/effect authority.
  Coordinate through Team Lead; distinguish library tagging from application deployment and preserve human
  signing and effect-specific grants. Its approved requirements are
  [TICKET-00055](../tickets/00055-TICKET.md) (release kind/policy),
  [TICKET-00056](../tickets/00056-TICKET.md) (coordination/approval),
  [TICKET-00057](../tickets/00057-TICKET.md) (library publication), and
  [TICKET-00058](../tickets/00058-TICKET.md) (application deployment). Do not treat TASK PR landing as release
  or silently reinterpret a chore. See [WF-028](tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md).

Both destinations have approved EPICs and requirement TICKETs. TICKET-00051 now has approved implementation
[TASK-00200](../tasks/00200-TASK.md) (authorized diagnosis and profile),
[TASK-00201](../tasks/00201-TASK.md) (bounded Jev and fallback) and
[TASK-00202](../tasks/00202-TASK.md) (human conflict disposition). TICKET-00052 now has approved
[TASK-00203](../tasks/00203-TASK.md) (versioned branch/route policy),
[TASK-00204](../tasks/00204-TASK.md) (single-intent terminal normal start) and
[TASK-00205](../tasks/00205-TASK.md) (browser Ready intent through the same server operation). TICKET-00053
now has approved [TASK-00206](../tasks/00206-TASK.md) (confirmed direct repair),
[TASK-00207](../tasks/00207-TASK.md) (independent review/QA/PR handoff), and
[TASK-00208](../tasks/00208-TASK.md) (linked same-TASK transfer). TICKET-00054 now has approved
[TASK-00209](../tasks/00209-TASK.md) (verified merge-back offer) and
[TASK-00210](../tasks/00210-TASK.md) (scoped same-hotfix effect/recovery). TICKET-00055 now has approved
[TASK-00211](../tasks/00211-TASK.md) (standalone release kind/guarded completion),
[TASK-00212](../tasks/00212-TASK.md) (project release policy) and
[TASK-00213](../tasks/00213-TASK.md) (qualified eligibility/route). TICKET-00056 now has approved
[TASK-00214](../tasks/00214-TASK.md) (one durable start/claim),
[TASK-00215](../tasks/00215-TASK.md) (exact preparation/certification),
[TASK-00216](../tasks/00216-TASK.md) (bounded human grant) and
[TASK-00217](../tasks/00217-TASK.md) (conditional human signing handoff/local verification). TICKET-00057
now has approved [TASK-00218](../tasks/00218-TASK.md) (verified remote tag),
[TASK-00219](../tasks/00219-TASK.md) (hosted publication or qualified N/A),
[TASK-00220](../tasks/00220-TASK.md) (registry/consumer evidence or qualified N/A) and
[TASK-00221](../tasks/00221-TASK.md) (branch follow-through/guarded closeout). TICKET-00058 now has
approved [TASK-00222](../tasks/00222-TASK.md) (qualified application preflight),
[TASK-00223](../tasks/00223-TASK.md) (bounded rollout/reconciliation),
[TASK-00224](../tasks/00224-TASK.md) (observed health/recovery), and
[TASK-00225](../tasks/00225-TASK.md) (branch follow-through/guarded closeout). All map TICKETs have
approved implementation TASK splits. Before publication, the 26 approved splits' draft IDs
00199–00224 were renumbered to TASK-00200–00225 because `develop` had independently published the
unrelated [TASK-00199](../tasks/00199-TASK.md). All links to these splits use their final IDs.
TASK-00200 and TASK-00206 have known unallocated TICKET-00034 profile prerequisites, not fabricated
TASK blockers. TASK-00207 needs actual managed Review/QA/publication;
TASK-00215 needs actual managed independent Review/QA/acceptance; TASK-00222 needs a concrete qualified
application deployment target; and TASK-00209, TASK-00221 and TASK-00225 need trusted managed merge
observation. EPIC-00008's `done` status and the local QA skill do not prove those exist. No implementation
was created by this map or its planning handoffs. Exact UI copy, template names, model qualification,
Jev rubric and budgets, runbook details, phase schemas and SUBTASK/PR coordination within approved TASKs
remain bounded implementation questions, not new intake decisions.

## Out of scope

- Launching Agents, claiming or reordering real TASKs, granting emergency authority, or changing current statuses.
- Replacing Planning eligibility, implementing a workflow engine, or silently rewriting accepted team responsibilities.
- Treating urgency as permission to skip sandboxing, independent review, required verification, or release/deployment grants.
- Creating EPICs, requirement TICKETs or implementation TASKs during map creation.
