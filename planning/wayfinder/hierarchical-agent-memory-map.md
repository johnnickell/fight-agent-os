# Wayfinder Map: Hierarchical agent memory

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map summarizes the linked decisions and shows the next decision frontier.

## Destination

Define durable shared memory that sandboxed Agents can retrieve across their authorized work scope, curate
locally, and promote only through separately authorized broader-scope owners. Preserve useful learning across
sessions and Workflow attempts without allowing an implementing Agent to publish workspace-wide guidance.

**Done** = the linked decisions are closed; remaining fog is resolved or explicitly excluded; and an approved
handoff identifies the resulting EPIC work or amendments to existing planning. EPIC creation and TICKET/TASK
decomposition remain separate operations.

## Notes

- Charted on 2026-09-28 at John's request from the shared memory discussion. The ownership and authority
  boundary was approved in WF-024 on 2026-10-09, lifecycle/promotion in WF-025 on the same date, and
  retrieval/MCP access in WF-026 on 2026-10-10. John approved the bounded memory EPIC handoff and
  delegation of remaining fog on 2026-10-10; the EPIC was written in a separate approved grill.
- [Harness and browser Agents](../epics/00006-EPIC.md) already cover profile templates, trusted first-party MCP
  access and conversation continuity. Those capabilities do not define memory ownership or promotion.
- Preserve [repository identity](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md),
  [context precedence](tickets/WF-009-define-repository-context-and-policy-precedence.md),
  [Harness ownership](tickets/WF-014-define-skill-trust-and-harness-distribution.md), and
  [Workflow authority and independence](tickets/WF-015-define-coordinated-task-authority-protocol.md).
- [SDLC intake triage](sdlc-intake-triage-map.md) is a related, separately decidable capability. Neither map
  automatically blocks or changes the approved first Team Lead → Software Engineer execution slice.

The [incremental Harness amendment](../epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07)
now owns early repository-file relevance screening and subsequent session compaction support. WF-026 records the
connection to later memory retrieval. That independent milestone does not settle this map's ownership, lifecycle
or promotion decisions, change its frontier, or require a new map.

## Decisions so far

1. **[WF-024 — Define memory ownership and scope authority](tickets/WF-024-define-memory-ownership-and-scope-authority.md)
   is closed.** Project memory uses `RepositoryId`; a stable Agent has a small Workspace-private general bank and
   repository/assignment-scoped working memory. Team Lead owns shared Workflow memory and explicit role handoffs;
   TASK memory spans separately authorized Workflows. Shared entries preserve attributed history, while an Agent
   may correct its own private working memory in place. Private reads for audit/improvement use the exact
   `READ_PRIVATE_AGENT_MEMORIES` Permission and current target authority, with an explicit audit TASK additionally
   required for audit Agents. Memory is intended to be database-backed, not a copy of Pi conversations.
2. **[WF-025 — Define memory lifecycle and promotion](tickets/WF-025-define-memory-lifecycle-and-promotion.md)
   is closed.** Plain-language entries retain attribution, sources and verification state; shared corrections
   append and private owners may replace current text without retaining old private values by default. Memories
   do not expire, and completed Workflow/TASK associations remain available for authorized learning. Broader
   publication requires a separately attributed entry by an authorized target owner who checks underlying
   evidence; rejected proposals never become guidance. Routine archive/removal policy is deferred, while
   prohibited content requires exceptional removal. Source code establishes current behavior; published Planning
   establishes approved intent, and neither is displaced by memory.
3. **[WF-026 — Define sandbox memory retrieval and MCP access](tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md)
   is closed.** An Agent starts with instructions and an authoritative handoff, then explicitly uses
   scope-authorized MCP tools for exact reads, writes and Jev typed questions over memory or a permitted scope.
   Jev can narrow entries to IDs without loading source text into Agent context; it is not an Agent identity or
   a summarizer. Ordinary execution reads close with the relevant Workflow/TASK, while permitted Project
   Manager, audit and human historical reads remain. No memory-specific cache or offline database fallback is
   approved. File/SQL access and concrete Jev thresholds stay with their own qualified use cases.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-024](tickets/WF-024-define-memory-ownership-and-scope-authority.md) | Define memory ownership and scope authority | wayfinder:grill | HITL | Closed | — |
| [WF-025](tickets/WF-025-define-memory-lifecycle-and-promotion.md) | Define memory lifecycle and promotion | wayfinder:grill | HITL | Closed | [WF-024](tickets/WF-024-define-memory-ownership-and-scope-authority.md) |
| [WF-026](tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md) | Define sandbox memory retrieval and MCP access | wayfinder:grill | HITL | Closed | [WF-024](tickets/WF-024-define-memory-ownership-and-scope-authority.md), [WF-025](tickets/WF-025-define-memory-lifecycle-and-promotion.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-024 scope and ownership → WF-025 lifecycle and promotion → WF-026 retrieval and MCP → Planning handoff
```

## Frontier

No open Wayfinder decision remains. The approved
[Durable scoped Agent memory](#approved-epic-handoff--2026-10-10) destination produced
[EPIC-00011 — Deliver durable scoped Agent memory](../epics/00011-EPIC.md) through its separate grill. This map
is Closed. The next planning phase is that EPIC's separate requirement TICKET decomposition, not TASK work.

## Approved EPIC handoff — 2026-10-10

**Destination:** [EPIC-00011 — Deliver durable scoped Agent memory](../epics/00011-EPIC.md), written in a
separate approved grill on 2026-10-10. The handoff specified one EPIC for database-backed private and shared
Agent memory, including scope-aware MCP read/write operations, controlled promotion, retained learning from completed work,
and Jev typed questions over authorized memory. Consume WF-024, WF-025 and WF-026 without re-deciding their
ownership, lifecycle, execution-access or provider boundaries. Keep mandatory Workflow handoffs, authoritative
Planning and source code, Harness session continuity and repository-file screening under their existing owners.
The resulting EPIC states its full staged delivery and dependencies; no memory capability
or incremental Jev integration is claimed implemented by approving this handoff. Prefer a distinct memory EPIC
rather than silently expanding the approved first Harness or Team Lead → Engineer execution milestone.

**Delegated questions and exclusions:**

- Let the EPIC grill choose a bounded first useful delivery and sequencing against Harness, database authority
  and managed execution. Later TICKETs can refine human inspection, correction, sharing and promotion-review
  experience within the accepted access rules, and decide whether MemorySpace needs an aggregate alongside
  entries. Do not assume a new aggregate is already approved.
- Qualify retrieval usefulness, memory-specific Jev questions/thresholds and source coverage during downstream
  requirements and implementation. Semantic search and vector storage remain optional technology questions;
  WF-026 accepts typed judgments and exact reads, not an embedding provider.
- Deterministic routine archival and removal remain a **separate future lifecycle decision**, not a hidden
  delivery requirement. Workflow/TASK completion and age do not trigger deletion; retain authorized historical
  learning under WF-025. Exceptional prohibited-content removal remains required.
- Friendly generated names for multiple Agent instantiations, and choosing Director versus CTO as a template
  name or broader role, belong to future Harness/team-profile planning. Display names do not grant identity or
  authority; one stable Agent identity has at most one active session. Neither topic blocks this memory EPIC.

No EPIC, TICKET, TASK, schema, Agent, credential or live memory entry was created or changed by closing the map.

## Out of scope

- Runtime implementation, schemas, credential or Agent provisioning, package installation, and live memory ingestion.
- Editing personal assistant memory or importing private transcripts, host memory files or other projects' knowledge.
- Granting authority through remembered prose, changing Planning authority, or weakening independent review.
- Creating EPICs, requirement TICKETs or implementation TASKs during map creation; automatic archive or cutover.
