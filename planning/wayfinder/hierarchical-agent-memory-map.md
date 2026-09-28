# Wayfinder Map: Hierarchical agent memory

**Label:** `wayfinder:map`
**Status:** Active

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

- Charted on 2026-09-28 at John's request from the shared memory discussion. The agreed direction is recorded
  in WF-024; unresolved product choices remain open rather than being inferred from agreement with the direction.
- [Harness and browser Agents](../epics/00006-EPIC.md) already cover profile templates, trusted first-party MCP
  access and conversation continuity. Those capabilities do not define memory ownership or promotion.
- Preserve [repository identity](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md),
  [context precedence](tickets/WF-009-define-repository-context-and-policy-precedence.md),
  [Harness ownership](tickets/WF-014-define-skill-trust-and-harness-distribution.md), and
  [Workflow authority and independence](tickets/WF-015-define-coordinated-task-authority-protocol.md).
- [SDLC intake triage](sdlc-intake-triage-map.md) is a related, separately decidable capability. Neither map
  automatically blocks or changes the approved first Team Lead → Software Engineer execution slice.

## Decisions so far

1. **[WF-024 — Define memory ownership and scope authority](tickets/WF-024-define-memory-ownership-and-scope-authority.md)
   is open.** Broad reads and narrow writes, with separate promotion authority, are the agreed basis; precise
   ownership, visibility and continuity rules remain to be settled.
2. **[WF-025 — Define memory lifecycle and promotion](tickets/WF-025-define-memory-lifecycle-and-promotion.md)
   is open.** Decide entry invariants, revision and retention behavior, and the evidence required to publish at
   a broader scope after WF-024.
3. **[WF-026 — Define sandbox memory retrieval and MCP access](tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md)
   is open.** Decide bounded retrieval, external service authority and the Resource/Tool boundary after ownership
   and lifecycle are settled.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-024](tickets/WF-024-define-memory-ownership-and-scope-authority.md) | Define memory ownership and scope authority | wayfinder:grill | HITL | Open | — |
| [WF-025](tickets/WF-025-define-memory-lifecycle-and-promotion.md) | Define memory lifecycle and promotion | wayfinder:grill | HITL | Open | [WF-024](tickets/WF-024-define-memory-ownership-and-scope-authority.md) |
| [WF-026](tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md) | Define sandbox memory retrieval and MCP access | wayfinder:grill | HITL | Open | [WF-024](tickets/WF-024-define-memory-ownership-and-scope-authority.md), [WF-025](tickets/WF-025-define-memory-lifecycle-and-promotion.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-024 scope and ownership → WF-025 lifecycle and promotion → WF-026 retrieval and MCP → Planning handoff
```

## Frontier

[WF-024 — Define memory ownership and scope authority](tickets/WF-024-define-memory-ownership-and-scope-authority.md)
is the next decision: establish the exact scope/ownership matrix, especially agent continuity, shared phase
visibility, and TASK memory spanning multiple Workflows.

## Not yet specified (fog)

- First useful delivery slice and sequencing relative to Harness and managed execution.
- Human inspection, correction, sharing and promotion-review experience.
- Whether a MemorySpace needs its own lifecycle and aggregate boundary alongside individual memory entries.
- Retrieval ranking, semantic search, summarization strategy and measurable usefulness; no vector database is selected.
- Whether Director or CTO is the eventual template name, and its responsibilities beyond memory stewardship.

## Out of scope

- Runtime implementation, schemas, credential or Agent provisioning, package installation, and live memory ingestion.
- Editing personal assistant memory or importing private transcripts, host memory files or other projects' knowledge.
- Granting authority through remembered prose, changing Planning authority, or weakening independent review.
- Creating EPICs, requirement TICKETs or implementation TASKs during map creation; automatic archive or cutover.
