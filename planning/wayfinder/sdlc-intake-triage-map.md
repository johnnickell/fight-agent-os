# Wayfinder Map: SDLC intake triage

**Label:** `wayfinder:map`
**Status:** Active

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map summarizes the linked decisions and shows the next decision frontier.

## Destination

Define a future Triage Agent template at the head of SDLC execution: inspect the next candidate TASK, confirm
readiness, identify required human decisions, and route it to the appropriate authorized Workflow before execution.

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

The Jev proposals added on 2026-10-07 are recorded in WF-027 and WF-028, using the shared capability planned in
[TICKET-00037](../tickets/00037-TICKET.md). This records candidate use cases without resolving either decision,
changing the frontier or making triage a prerequisite of the approved first Harness milestone. See the
[follow-on coverage handoff](../epics/00006-EPIC.md#follow-on-jev-decision-coverage--2026-10-07) for uses outside this map.

## Decisions so far

1. **[WF-027 — Define TASK triage readiness and classification](tickets/WF-027-define-task-triage-readiness-and-classification.md)
   is open.** Define intake, readiness evidence, work classification, human-decision outcomes and routing proposals.
   Proposed Jev questions now cover semantic readiness, work type and separate urgency/risk dimensions.
2. **[WF-028 — Define triage dispatch and hotfix role boundaries](tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md)
   is open.** Define enforceable handoffs and reconcile direct senior hotfix implementation with independent review.
   Proposed Jev route selection is limited to semantic ambiguity; deterministic dispatch validation stays authoritative.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-027](tickets/WF-027-define-task-triage-readiness-and-classification.md) | Define TASK triage readiness and classification | wayfinder:grill | HITL | Open | — |
| [WF-028](tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md) | Define triage dispatch and hotfix role boundaries | wayfinder:grill | HITL | Open | [WF-027](tickets/WF-027-define-task-triage-readiness-and-classification.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-027 readiness and classification → WF-028 dispatch and hotfix roles → Planning handoff
```

## Frontier

[WF-027 — Define TASK triage readiness and classification](tickets/WF-027-define-task-triage-readiness-and-classification.md)
is the next decision: what makes a candidate ready, which facts Triage may decide, and what must return to a human.

## Not yet specified (fog)

- Explicit invocation versus automatic intake service, trigger timing and bounded operating cost.
- First delivery slice and whether/when it becomes mandatory for existing execution paths.
- Human presentation of blocked readiness, classification conflicts and routing explanations.
- Template naming, skills and measured model suitability. Jev is proposed for bounded decisions; the Triage Agent
  main model and runtime remain unselected, and neither Jev policy nor integration is qualified by this map.

## Out of scope

- Launching Agents, claiming or reordering real TASKs, granting emergency authority, or changing current statuses.
- Replacing Planning eligibility, implementing a workflow engine, or silently rewriting accepted team responsibilities.
- Treating urgency as permission to skip sandboxing, independent review, required verification, or release/deployment grants.
- Creating EPICs, requirement TICKETs or implementation TASKs during map creation.
