# Define sandbox memory retrieval and MCP access

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Hierarchical agent memory](../hierarchical-agent-memory-map.md)
**Depends on:** [WF-024](WF-024-define-memory-ownership-and-scope-authority.md), [WF-025](WF-025-define-memory-lifecycle-and-promotion.md)

## Question

How can a sandboxed Agent retrieve useful authorized memory and manage its own entries through the trusted
first-party MCP boundary while durable authority stays outside the sandbox?

## Working direction

Use MCP Resources for entry revisions and bounded summaries, with Tools for search and authorized mutations or
promotion proposals. The Harness supplies compact starting context and allows explicit deeper retrieval. This is
a design candidate requiring qualification against the selected MCP/Pi implementation, not a claim of support.

## Must decide

- Service ownership and application boundaries; durable memory outside the sandbox, with local scratch/cache
  explicitly disposable and no shared writable host-memory mount or direct database access.
- Discovery, search, resource reads and writes scoped to the authenticated Agent and current assignment; enforce
  permissions before exposing snippets, titles, counts or other metadata, and recheck authority on reads/writes.
- Initial context selection, explicit broader search, byte/token/result budgets, source visibility and exact entry
  revision recording. Distinguish later retrieval from silently changing pinned Harness or repository snapshots.
- Agent-requested remember/revise/retract operations and uncertain-write recovery under WF-025; actual server checks
  enforce WF-024 even when the model submits another scope or guessed resource URI.
- Cache isolation, retention and revocation; revocation prevents future access but cannot erase content already
  observed by a model. Restricted data must not be reintroduced through caches or derived summaries.
- Stale/conflicting memories, unavailable service, interruption and resume, independent review context, and clear
  behavior when a resource or operation is unsupported rather than silently widening access or falling back.
- First-party memory versus future external knowledge sources; any later federation preserves source permissions,
  provenance and trust instead of importing broad external access into every sandbox.
- Focused qualification evidence for useful retrieval, denied cross-scope access, promotion denial, revisions and
  recovery; reuse existing Harness/broker contracts without claiming current sandbox qualification is complete.

## Resolution boundary

Set the access and retrieval contract and identify any necessary bounded research. Preserve accepted first-party
MCP trust, live revocation and external authority boundaries. Do not implement transport, enroll credentials,
ingest host memory, or select an embedding provider merely to close this decision.
