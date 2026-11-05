---
id: TICKET-00041
epic: EPIC-00006
title: Schedule and observe safe session compaction
status: ready-for-agent
---

# Schedule and observe safe session compaction

## Problem statement

Long-running Fight sessions need compaction before context exhaustion and enough visibility to diagnose
failures and recover safely.

## Current direction

Use native Pi automatic and manual compaction with the verified recovery behavior in
[TICKET-00040](00040-TICKET.md). Use effective model and runtime settings and existing compaction events.
Keep native auto-compaction enabled and qualify both the compaction call and the first turn after it.
Compare context retention, summary latency, time to first response and available cache usage before deciding
whether any provider-specific cache optimization is warranted.

TASK-00168 owns operator-configurable **180k notice, 210k recommendation and 240k automatic compaction**
targets, including on a 500k context window. Prefer the native per-model reserve for the automatic trigger
where it meets the safe-boundary contract. Qualify smaller windows and current context accounting. Respect
pending questions, explicit stops and uncertain effects. A bounded snooze may defer a recommendation until
the automatic tier. Jev timing and expanded history remain deferred.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00168](../tasks/00168-TASK.md) | Enforce staged compaction at safe session boundaries | ready-for-agent |
| [TASK-00169](../tasks/00169-TASK.md) | Evaluate compaction timing with Jev after each eligible turn | needs-info |
| [TASK-00170](../tasks/00170-TASK.md) | Inspect compaction history and annotate recovery incidents | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this split on 2026-10-08. WF-029 timing and WF-030 observation requirements are accepted inputs to the
EPIC-00006 follow-on; exact qualification and implementation remain outstanding. No runtime policy, live session,
provider configuration, TASK priority or publication changed through this record.

[The 2026-11-05 amendment](../epics/00006-EPIC.md#approved-native-pi-compaction-and-jev-retirement--2026-11-05) retains the deterministic timing tiers, replaces the fixed runtime target and defers Jev timing.

### Original TASK decomposition — 2026-10-08

John approved three independent delivery outcomes targeting **Pi 1.1.0**:

| TASK | Outcome | Required blockers |
|---|---|---|
| [TASK-00168](../tasks/00168-TASK.md) | Deterministic stages, safe-point enforcement, early requests/snoozes, quiet notices and verified recovery/native fallback | [TASK-00164](../tasks/00164-TASK.md) |
| [TASK-00169](../tasks/00169-TASK.md) | Every-eligible-turn Jev evaluation with typed timing/reason choices, bounded budgets and degraded behavior | TASK-00168 and [TASK-00159](../tasks/00159-TASK.md) |
| [TASK-00170](../tasks/00170-TASK.md) | Bounded local lifecycle inspection, incident annotations and honest policy comparisons | TASK-00168 |

The deterministic baseline is useful without Jev. Semantic evaluation and history inspection can proceed
independently once their own prerequisites permit. Checkpoint creation/resume remains TICKET-00040's capability;
no duplicate storage, selection or repair implementation is introduced by the timing owner.

Use verified native/profile limits to scale the accepted 180k/210k/240k stages with ordered spacing and sufficient
reserve; unknown limits use the qualified fallback policy rather than guessed thresholds. Exact hook semantics,
scaling/rounding, reserves and protected resource ceilings must be versioned and qualified on Pi 1.1.0 before
activation. A completed turn permits evaluation, not automatic compaction. No cooldown skips eligible turns,
and no Jev outcome or outage may veto deterministic request-tier handling.

Reuse the checkpoint recovery limits and preserve request obligations across reload. Correlated observations are
sanitized protected local records under WF-017, not checkpoint authority or a second Planning database. Historical
inspection does not wait for future browser delivery; it distinguishes absent semantic/selection/repair producers
rather than inventing their events. Every TASK includes its own behavior evidence and actual integration checks.

All three TASKs remain unranked and preserve existing execution priorities. TASK decomposition for TICKET-00042
through TICKET-00045 remains outstanding within the selected follow-on scope; older EPIC/map work retains its
status. No code, live session, provider, runtime policy, cleanup or publication changed through this approval.

Next: `/skill:to-tasks TICKET-00042`
