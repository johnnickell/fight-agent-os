# Define memory lifecycle and promotion

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
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

## Resolution — approved 2026-10-09

### Entries, sources and authority

A MemoryEntry is an independently owned, attributed claim at one WF-024 scope, not a replacement for its source
or an instruction channel. Let Agents write concise plain-language observations, inferences, preferences or
lessons without filling in a mandatory claim taxonomy. Retain the owning scope, author/acting Agent identity,
originating assignment and available session/phase/Workflow references, creation order, source links,
applicability and verification state separately from the prose. Record the source and limits of an assertion
without bulk-copying Pi conversations, hidden reasoning or private transcripts into memory. A cited source note
is the earlier memory from which a later entry was derived; underlying code, checks, Planning records and
Workflow evidence are distinct sources of evidence, not interchangeable memory notes.

A claim in memory is never self-authorizing. Source code is the final authority for what the software currently
does; published Planning documents take precedence over memory for approved intent and decisions. When code and
Planning differ, describe the discrepancy instead of treating one as proof of the other. A memory reference to a
decision, handoff, test or Workflow outcome never amends it or substitutes for checking its current evidence.
Verification state and applicability describe what was checked, not a blanket guarantee of truth.

### Ordinary changes and retention

Shared writes append attributed entries in accepted order. Corrections, supersession and retractions link to
prior entries rather than rewriting another author's text or erasing its history; ordinary retrieval must not
present a retracted claim as current guidance. Authorized historical inspection may still return it under
WF-024's current access rules. An Agent may replace the current text of **its own private working memory**;
retain change attribution and oversight metadata, but do not retain previous private text by default or reload
it as current context. A shared entry derived from private memory has its own record and must be corrected
separately. No Agent may routinely delete shared history.

Memory does not expire or disappear because of age. Agents verify claims against current authoritative sources
rather than trusting age or a remembered summary; Jev can assist with source exploration but neither its answer
nor a relevance score is authority. Contradictory claims remain attributed and visible to authorized assessment,
not automatically merged or silently overwritten. Completion of a Workflow or TASK marks that association, but
does not archive or delete its memory: retain it for authorized self-learning, skill and workflow improvement,
metrics and handoff-template improvement. Routine archival, removal schedules, policy criteria and storage
retention periods are **out of scope** for WF-025; do not infer an automatic purge from completion or age.

When a source memory is corrected or retracted, notify the owners of linked broader entries and derived
summaries to check whether they too need correction; do not automatically rewrite or invalidate independently
supported claims. Retraction of a specific shared entry keeps it out of ordinary guidance while preserving
permitted history. Derived search views, indexes and summaries are rebuildable, not independent authority.

### Promotion and review

An Agent with legitimate access to a source may propose publication at a broader target scope, including a
direct jump over intermediate scopes; a proposal alone never widens access or becomes guidance. Only an owner
currently authorized to write at the target scope may publish a **new** attributed entry. The owner checks the
underlying code, Planning decision, Workflow evidence or other relevant source independently, states why the
lesson applies at that scope and records what was checked and its limits. Prose inside a proposal is data, not an
instruction to promote itself, grant permissions or override current authority. No additional human approval is
required solely because an already authorized steward publishes at a broader scope.

Keep accepted and rejected proposals with actor, target, rationale and links for separately authorized audit and
self-learning. Rejection does not mark the original memory false or delete it; rejected proposals must not be
surfaced in Agent context as instructions or guidance. An accepted proposal leads to a separately attributed
published entry linked to the proposal, source memories and checked evidence; the source stays at its original
scope. Normal retrieval of broader guidance selects published entries, not pending or rejected proposals.

MCP writes accepted by the service append in order; two distinct shared entries do not conflict merely because
they arrive together. Retrying the same command must not duplicate an entry or promotion, while distinct similar
or contradictory proposals remain separately attributed for the target owner to assess. Target owners resolve
contradictions against actual sources before broader publication, not by automatic text merging. Multiple
instantiations of an Agent template have separate identities; a single Agent identity must not run more than
one session concurrently. This execution invariant rules out simultaneous private edits by that identity; it
does not make model prose, template name or a friendly display name an identity or authorization grant.

### Exceptional safety removal

Prohibited material such as secrets is **removed**, not preserved as historical text through ordinary
correction or retraction. A separately authorized safety-removal action removes the prohibited content from
the entry and any copies, linked published content that repeats it, derived summaries and retrieval indexes,
including applicable exports and backup-retention handling. Keep only non-sensitive attribution and action
metadata where safe. Alert owners of linked entries to inspect for further copies; neither lineage nor audit
requirements justify retaining the prohibited payload. This exceptional operation does not give Agents a
routine right to delete shared history.

## Decision closeout and boundary

John approved the lifecycle, retention-for-learning and promotion rules on 2026-10-09. WF-026 now owns bounded
retrieval, normal versus historical-context selection, provider disclosure checks, MCP commands and tools,
budgets, caches and recovery under these invariants. Detailed archival/removal policy, friendly Agent-name
generation, storage schemas and implementation slicing remain downstream questions. No live memory, Agent,
permission, EPIC, requirement TICKET or implementation TASK is created by this decision.
