# Define compaction timing and notifications

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-030](WF-030-define-context-retention-and-resume-contract.md)

## Question

When should the Harness recommend or initiate context compaction for each Agent and sub-agent, and how should
Jev improve timing while deterministic context limits, safe checkpoints and operator control remain authoritative?

## Proposed basis — 2026-10-07

John requested custom "should I compact" notifications to preserve useful reasoning capacity. Use deterministic
context accounting to bound operation and Jev to assess a useful work boundary or the likely relevance of prior
context to the next step. Reuse [TICKET-00037](../../tickets/00037-TICKET.md)'s shared judgment capability.
[WF-030](WF-030-define-context-retention-and-resume-contract.md) first defines what a valid checkpoint retains and
how continuation is verified. This is follow-on scope under [EPIC-00006](../../epics/00006-EPIC.md), separate from
the six approved first-milestone TASKs; no runtime compaction behavior is activated by this record.

## Must decide

- Context accounting source, supported runtime/model context limits, reserved capacity for checkpoint creation
  and resume, soft notification thresholds and hard-limit behavior. Missing usage data must be explicit.
- Jev's bounded inputs and typed question: whether a safe work boundary has been reached, whether substantial
  prior context is still needed, and which uncertainty requires deferral. Select a specific rubric and evidence
  for thresholds rather than reusing file relevance or tool-guard thresholds.
- Notification-only versus authorized automatic compaction, who may initiate or defer it, and what happens at a
  hard limit if no safe checkpoint can be produced. A suggestion is not permission to discard required context.
- Safe points around in-flight tools, pending operator questions, uncertain external effects and review boundaries;
  coordinate with WF-030 so compaction cannot lose decisions or cause replay of an operation.
- Per-session/sub-agent budget ownership, independent triggers, parent visibility and instructions; no unsupported
  child session should be described as receiving notifications. Distinguish model switching from compaction.
- Notification presentation, cooldowns, duplicate suppression, snoozing/deferral, cost/latency ceilings and observability.
  Avoid evaluating every token or creating a notification loop that itself consumes excessive context.
- Jev outage/refusal/invalid response, stale context estimates, cancellation and reload behavior. Preserve a declared
  deterministic limit policy without inventing a model recommendation or silently proceeding past a hard limit.
- Initial supported Pi integration and qualification of actual compaction hooks, continuation semantics and timing.
  Implementation evidence must identify exact runtime versions rather than assuming provider-specific support.

## Evidence needed to resolve

Compare deterministic-only timing with bounded Jev recommendations across short/long parent and child sessions.
Measure completion quality, lost/repeated work, checkpoint cost, total context/cost/latency and notification burden.
Include in-flight effects, unanswered human decisions, evaluator outage, unknown token counts and reload. Do not
claim an optimal intelligence level merely because a notification or compaction completed.

## Resolution boundary

Set timing, notification/automation authority and failure behavior using WF-030's checkpoint contract, then identify
bounded EPIC-00006 amendments and qualification work. Do not compact live sessions, change active prompts, select
production thresholds without evidence, launch Agents or decompose implementation TASKs in this decision.
