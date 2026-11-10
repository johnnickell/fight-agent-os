---
id: TICKET-00040
epic: EPIC-00006
title: Preserve context through verified compaction and resume
status: ready-for-agent
---

# Preserve context through verified compaction and resume

## Problem statement

A Fight agent must continue useful work after context compaction without losing required facts or treating a
summary as authority. Recovery must respect current permissions and the state of original operations.

## Solution and boundaries

Use native Pi compaction, session history and resume as the baseline. TASK-00164 qualifies the currently
installed supported capabilities on Fight's normal provider and session paths. Preserve objective, decisions,
constraints, pending questions, work state, evidence references and the next safe action. Verify current scope,
permissions and operation state before dependent actions. Reconcile uncertain effects through their owners.
Resume with minimal authorized source reads when repair is needed under TASK-00166.

Use protected, lineage-bound companion storage only for a demonstrated required gap. Preserve recoverable state
through interrupted writes and resolve retention and holds before activating new storage. Keep reviewer and
child context private and preserve independent approvals and operation authority. [TICKET-00041](00041-TICKET.md)
owns the deterministic timing tiers and any later observation gap.

## Acceptance and sequencing

Qualify automatic and manual compaction, repeated compaction, reload and interrupted compaction. Demonstrate safe
continuation with pending human input and unavailable or conflicting evidence. Measure context, latency and
available cache usage as separate facts. Defer Jev ranking and storage-specific retention pending the evidence
named in their TASKs. Native qualification and bounded repair require no Jev integration.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00164](../tasks/00164-TASK.md) | Preserve required context through compaction and validated resume | done |
| [TASK-00165](../tasks/00165-TASK.md) | Select optional retained context with Jev | needs-info |
| [TASK-00166](../tasks/00166-TASK.md) | Recover missing context through selective source reads | ready-for-agent |
| [TASK-00167](../tasks/00167-TASK.md) | Enforce checkpoint retention and recovery holds | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement split on 2026-10-08 for the selected Jev follow-on scope. This record is ready for
TASK decomposition, not implementation acceptance. WF-030's full accepted contract remains authoritative input;
WF-009/WF-017 retain instruction, conversation and retention ownership. No live compaction, cleanup, provider call,
TASK allocation or publication follows from this record. Existing six first-milestone TASKs and Board stay unchanged.

[The 2026-11-05 amendment](../epics/00006-EPIC.md#approved-native-pi-compaction-and-jev-retirement--2026-11-05) supersedes the older runtime target, Jev dependencies and assumed companion storage.

### Original TASK decomposition — 2026-10-08

John approved four independently reviewable outcomes and explicitly confirmed **Pi 1.1.0** as the target runtime:

| TASK | Outcome | Required blockers |
|---|---|---|
| [TASK-00164](../tasks/00164-TASK.md) | Required checkpoint facts, protected native/companion activation and validated baseline resume | [TASK-00160](../tasks/00160-TASK.md), protecting configured checkpoint resources through supported Agent routes |
| [TASK-00165](../tasks/00165-TASK.md) | Jev optional-context categories/ranking, budget fit and qualified native fallback | TASK-00164 and [TASK-00159](../tasks/00159-TASK.md) |
| [TASK-00166](../tasks/00166-TASK.md) | Question-first source recovery and changed-support reconciliation with validated continuation | TASK-00164 and [TASK-00162](../tasks/00162-TASK.md) |
| [TASK-00167](../tasks/00167-TASK.md) | Terminal checkpoint retention, recovery holds and guarded deletion with honest availability | TASK-00164 |

Pi remains the model-context owner; additional required checkpoint state uses protected session-linked companion
storage under operator policy outside the repository. Use a versioned format, predefined optional categories and
bounded rank values with deterministic ties. First establish the safe baseline and pause on repair-required facts;
selection, repair and retention then proceed independently when their own blockers permit.

The accepted repair limit is one bounded repair pass per resume attempt, followed by validation or preserved pause.
Attempt identity/budget survives reload; further attempts require an explicit authorized retry rather than a hidden
loop. Existing evaluator and file-read ceilings stay binding. Context allowances derive from the effective profile
and reserve instructions plus the next action; implementation pins concrete checkpoint/segment/aggregate limits
before activation and must qualify them on Pi 1.1.0. No historical Pi 0.87.1 presentation receipt establishes
compaction behavior. Unqualified required guarantees prevent acceptance or activation of the affected route.

Deliver local sessions first with their actual permissions and supported child paths; do not invent managed grants,
claims or leases. Required checkpoint privacy/protection and resume observations belong to the baseline; lifecycle
inspection and timing remain with TICKET-00041. Existing sandbox authority is not weakened by local protection.
All four TASKs are unranked and leave existing TASK priorities/dependencies unchanged. Implementation, checkout
choice, runtime qualification, review and publication remain separate from this planning approval.

The selected Jev follow-on still needs TASK decomposition for TICKET-00041 through TICKET-00045. Older EPIC/map
planning retains its existing status. No implementation, live provider call, compaction or cleanup ran here.

Next: `/skill:to-tasks TICKET-00041`
