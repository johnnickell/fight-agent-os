---
id: TICKET-00041
epic: EPIC-00006
title: Schedule and observe safe session compaction
status: ready-for-agent
---

# Schedule and observe safe session compaction

## Problem statement

Waiting for native context exhaustion leaves little room for useful continuity. Agents need earlier opportunities
to compact, bounded deferral while finishing work, dependable fallback and observations that reveal where context
or effort was lost without copying private conversations into telemetry.

## Solution and boundaries

Implement [WF-029](../wayfinder/tickets/WF-029-define-compaction-timing-and-notifications.md) and the observability
contract in [WF-030](../wayfinder/tickets/WF-030-define-context-retention-and-resume-contract.md), using
[TICKET-00040](00040-TICKET.md) for checkpoint/resume and [TICKET-00037](00037-TICKET.md) for timing judgments.

- Target notice at 180k, recommendation at 210k and request at 240k tokens initially. Qualify and scale ordered
  tiers against each profile's effective native trigger/context limit and required reserve, with explicit
  overrides. The reported roughly 272k trigger is a reference, not a universal verified limit. Code owns accounting.
- From notice, let the Agent request safe compaction, including before large work, without a positive Jev answer.
  From recommendation, actively recommend compaction; a positive judgment may initiate it unless the Agent defers.
  Record snooze and reason, ending no later than request; snooze never stops accounting or eligible evaluations.
- At request, finish/reconcile already in-flight operations and compact at the next safe point before another
  work step. No new calls to extend that work unit, no Jev veto and no routine human approval. If the checkpoint
  contract cannot be met within limits, preserve and pause for intervention.
- Evaluate after every completed turn from notice onward. Work boundaries inform the answer but do not gate the
  hook. Deduplicate duplicate hooks and notices, with bounded concurrency/cost; no cooldown skips eligible turns.
  Jev selects predefined compact/defer/uncertain and caller-authored reason options. Neither 0.70 file screening
  nor the tool-guard threshold is a timing confidence rule; Jev cannot generate a new explanation.
- Track independent Agent/sub-agent usage, profile and lifecycle. Share only authorized status/handoff facts;
  routine compaction needs no parent approval. Respect aggregate budgets and reviewer independence. Quiet Agent/
  session notices are normal; direct human alerts identify actual intervention needs.
- Jev outage/budget exhaustion leaves visible degraded operation with Agent-requested compaction, deterministic
  request-tier action and qualified native fallback. Unknown/stale usage or limits suspend custom tiers; use
  verified native fallback only if TICKET-00040 remains satisfied, otherwise preserve/pause. Native summarization
  may require its own authorized model/cost capacity.
- Preserve exact pending human question identity/content/context and waiting state through compaction; reconcile
  concurrent replies. On reload/interruption recover verified state within bounds, discard stale/duplicate work,
  retain explicit stops/cancellations and outstanding request-tier obligations, and never replay uncertain effects.

Own bounded observation inspection and aggregate comparisons, consuming TICKET-00040's checkpoint/resume facts:

- Correlate session/lineage, Agent/role, authorized parent-child links, attempt/checkpoint revisions and actual
  TASK/Workflow/phase/process identifiers when present. Local sessions do not invent managed identifiers.
- Record tier crossings, requests/recommendations, snoozes/cancellations, candidate/validation/activation results,
  compaction, resume, fallback/repair and failures. Preserve initiating actor, automatic/manual mode, bounded reason
  categories, policy/rubric/Harness/runtime/evaluator versions, digests and protected references.
- Capture required-field validation, bounded optional inclusion/exclusion identifiers and available judgments;
  before/after counts with accounting provenance; separate checkpoint/Jev usage, cost and latency. Label measured,
  estimated and unavailable; correlate stable usage identities to avoid double counting.
- Allow attributed incident annotations and corrections for lost constraints, missed questions, repeated work,
  recovered context and uncertain/replayed operations. Compare policies, fallback/blocked-resume rates, context
  reduction and available total cost/latency with explicit incomplete history. Correlation is not causal proof.
- Preserve WF-017 privacy/retention and access-check protected references. Ordinary telemetry/Dashboards exclude
  raw transcripts, checkpoint prose, source/full-tool bodies, credentials and hidden reasoning. Missing/purged
  evidence is explicit. Delayed/missing/duplicate observations neither authorize continuation nor prove recovery.

Exclude checkpoint storage ownership, memory promotion, automatic provider switching, forecasting optimal intelligence,
a new general observability platform and managed execution launch. Reuse existing observation ownership and expose
qualified local inspection without making it depend on an entire future browser Dashboard.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Apply profile timing | Select permitted threshold overrides | Effective limits, reserve and usage | Profile/tier observations | Versioned effective timing state, no new authority |
| Evaluate or request compaction | Request, snooze or cancel; initiate at safe point | Typed timing judgment and checkpoint readiness | Recommendation/defer/request/compaction outcome | Bounded Jev call and TICKET-00040 lifecycle when safe |
| Recover degraded sessions | Reconcile timing state and invoke qualified fallback | Current accounting, in-flight work, native capability | Fallback/recovery/intervention facts | Verified continuation or preserved pause |
| Inspect and improve policy | Record attributed incident annotation/correction | Authorized session history and bounded aggregate comparisons | Annotation/correction recorded | Sanitized observation metadata; no automatic policy rewrite |

## Validation and permissions

Operator-controlled profiles and current authority constrain overrides; Agent context cannot enlarge limits or
change protected policies. Apply source/provider-disclosure and spend limits to judgments. Observability access
cannot expose private child/reviewer context or become permission for effect replay. Validate stale evaluations,
duplicate events and unknown accounting explicitly; stop new work when safe continuation cannot be established.

## Acceptance and evidence

- Qualify actual Pi completed-turn and native compaction hooks, accounting source, ordered scaling/overrides/reserves,
  safe points and child-session support. Demonstrate early request, recommendation, bounded snooze and request-tier
  compaction without new work slipping past the boundary or eligible turns being skipped by cooldown.
- Exercise outages/exhausted budgets, unknown counts, interrupted/reloaded state, pending questions and in-flight
  uncertain operations; preserve explicit stop and show honest fallback/intervention states.
- Trace correlated checkpoint/compaction/resume observations and annotations through missing/duplicate delivery;
  verify privacy, access controls, retention categories and honest unavailable counts/costs.
- Compare representative continuation usefulness, loss/repetition incidents, total cost/latency and recall. Smaller
  context alone is not success. Qualify runtime mechanisms directly and test owned lifecycle/inspection behavior.

## Sequencing and TASK readiness

Consume TICKET-00040's verified checkpoint contract and required TICKET-00037 evaluator slices. Observation capture
belongs with each producing lifecycle; this TICKET supplies correlated inspection/comparisons without making
checkpoint durability depend on telemetry. Pin supported runtime, hook semantics, scaling/rounding and budgets at
TASK decomposition; exact dependencies follow capabilities, not whole-EPIC completion. No browser or memory gate.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00168](../tasks/00168-TASK.md) | Enforce staged compaction at safe session boundaries | ready-for-agent |
| [TASK-00169](../tasks/00169-TASK.md) | Evaluate compaction timing with Jev after each eligible turn | ready-for-agent |
| [TASK-00170](../tasks/00170-TASK.md) | Inspect compaction history and annotate recovery incidents | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this split on 2026-10-08. WF-029 timing and WF-030 observation requirements are accepted inputs to the
EPIC-00006 follow-on; exact qualification and implementation remain outstanding. No runtime policy, live session,
provider configuration, TASK priority or publication changed through this record.

### Approved TASK decomposition — 2026-10-08

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
