# Define memory lifecycle and promotion

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Hierarchical agent memory](../hierarchical-agent-memory-map.md)
**Depends on:** [WF-024](WF-024-define-memory-ownership-and-scope-authority.md)

## Question

What lifecycle and promotion contract makes locally recorded learning safe to reuse at broader scopes without
turning observations into unearned authority or losing their provenance?

## Agreed basis — 2026-09-28

Treat memory as a first-class domain capability, provisionally with an independently managed MemoryEntry aggregate.
Entries need scope, owner, provenance, revision and lifecycle. Promoting an entry creates a separately attributed
entry at the target scope, linked to its sources and rationale; it does not move or silently relabel the original.
A lower-scope Agent may propose promotion but cannot perform the broader publication without target-scope authority.

An Engineer's observation may inform a Team Lead's Workflow lesson, a Project Manager's project guidance and a
Director's workspace guidance. Each publication needs its own applicability judgment. This example does not yet
require every promotion to pass through every intermediate level.

## Must decide

- Aggregate invariants and lifecycle: record, revise, supersede, retract, expire, redact and, where authorized, purge;
  distinguish ordinary revision history from sensitive-content removal and retention obligations.
- Observation, inference, preference, reusable lesson and authoritative-decision reference; source links, originating
  Agent/session/phase/Workflow, applicability, freshness, verification state and contradiction handling.
- Promotion proposal, target-scope review, acceptance/rejection and attribution; whether any promotion requires a
  human or may be performed by an already authorized steward, including direct versus adjacent-level promotion.
- Independent assessment of source evidence; prevent an instruction embedded in a low-scope note from commanding
  its own promotion or expanding the steward's authority.
- Concurrent edits, expected revisions, retry idempotency, duplicate proposals and conflicting lessons.
- What happens to derived summaries and promoted entries when a source is corrected, retracted, restricted or
  deleted; preserve useful lineage without retaining content that must be removed.
- Relationship to Planning decisions, Workflow evidence and transcripts; memory references their authority rather
  than duplicating it. Search indexes, embeddings and generated summaries remain rebuildable derived views.

## Resolution boundary

Define business lifecycle and promotion behavior under WF-024's authority model. Leave storage technology,
wire DTOs, schema design and implementation slicing downstream. This decision imports or promotes no live memory.
