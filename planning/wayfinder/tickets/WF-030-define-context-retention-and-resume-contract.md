# Define context retention and resume contract

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** —

## Question

What must survive compaction, how may Jev help select additional useful context, and what must be revalidated before
an Agent or sub-agent resumes work from the resulting checkpoint?

## Proposed basis — 2026-10-07

John's context-efficiency direction calls for smaller continuation context without losing objectives, constraints
or evidence. Use Jev to rank optional supplied context segments under a bounded question, while deterministic
requirements preserve the mandatory checkpoint facts. Reuse
[TICKET-00037](../../tickets/00037-TICKET.md)'s shared judgment capability and the accepted context/authority
boundaries. This belongs to [EPIC-00006](../../epics/00006-EPIC.md), independently of durable memory implementation.

## Must decide

- Required checkpoint content: objective and current TASK/assignment, accepted decisions and constraints, remaining
  work, unanswered human questions, outstanding/uncertain effects, source revisions, evidence references and next
  action. Separate authoritative references from summaries and distinguish facts, proposals and uncertainty.
- Jev's permitted selection role, segment identifiers and typed relevance judgments; required facts cannot be
  dropped because of a low score, budget pressure or provider failure. Define optional-context ordering, token
  budgets and behavior when required content alone exceeds the available budget.
- Who creates and validates the checkpoint, what establishes completeness, where it resides, its revision/lineage,
  privacy/retention policy and how interrupted creation or failed resume is recovered without losing the prior state.
- Resume-time loading of governing instructions and selected skills, current permissions, assignment/claim state,
  policy/source revisions and relevant live evidence. Compacted prose cannot preserve a revoked permission or
  silently replace pinned context; handle drift and conflicting summaries explicitly.
- Treatment of in-flight tools, unconsumed operator approvals, ambiguous billed/external effects and pending human
  input. A continuation cannot infer success, inherit a stale one-use approval or replay an uncertain effect.
- Parent/sub-agent checkpoints and references, separation of private scopes and contributor/reviewer independence.
  A new session does not erase contribution history. Retained context does not authorize broader memory promotion.
- Provider disclosure and bounded use of Jev on supplied context, redacted receipts, exact source identities and
  unsupported input handling. Never import private host transcripts or hidden scopes as a convenience.
- Safe deterministic fallback when selection is unavailable, checks that identify incomplete/stale checkpoints, and
  whether intervention or a larger bounded continuation is required instead of a misleading successful resume.

## Evidence needed to resolve

Trace mandatory facts through representative compaction/resume scenarios, including conflicting source revisions,
revoked access, pending questions, uncertain effects and separated parent/child or reviewer contexts. Compare optional
Jev selection with deterministic retention for useful recall, context size, cost/latency and lost/repeated work.
Checkpoints must retain inspectable provenance; successful summarization is not proof of successful continuation.

## Resolution boundary

Set the checkpoint and resume contract that [WF-029](WF-029-define-compaction-timing-and-notifications.md) consumes.
Memory ownership/promotion stay with WF-024/WF-025 and memory retrieval with WF-026; this decision does not require
those implementations or grant memory writes. Do not compact live sessions, edit personal memory, change current
runtime behavior or create implementation records while resolving this decision.
