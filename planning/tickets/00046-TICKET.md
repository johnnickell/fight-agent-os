---
id: TICKET-00046
epic: EPIC-00011
title: Keep scoped private Agent memory
status: ready-for-agent
---

# Keep scoped private Agent memory

## Problem statement

An Agent needs durable learning across its own sessions without carrying another repository's confidential
material into a new assignment, exposing private notes to peers or relying on a Pi transcript as the memory
store. Correcting a mistaken working belief should not reload an obsolete private value.

## Solution and boundaries

- Own database-backed MemoryEntries with stable identity, scope, attributed author, originating assignment and
  available session/phase/Workflow references, source links, applicability and verification state. Plain-language
  claims need no mandatory observation/inference taxonomy. Treat memory as context, not Planning, code, Workflow
  evidence or execution authority; do not create a speculative MemorySpace aggregate merely to store entries.
- Give each actual Agent identity a **small Workspace-private general bank** for broadly applicable lessons and
  repository/assignment-partitioned private working memory. The general bank cannot smuggle confidential
  repository/TASK content into another assignment. Identity/template/display name alone grants no repository
  access. An identity has at most one active session under the accepted execution contract.
- Let an Agent explicitly record, read, replace or clear its own current private working text under current
  assignment and scope. Keep safe change attribution without retaining prior private text by default. Do not
  ingest Pi checkpoints, raw transcripts or hidden reasoning, and do not turn a private change into a rewrite of
  an existing handoff or shared entry.
- Offer the own-private operations through first-party, HMAC-authenticated MCP tools bound to application
  commands/queries. Scope IDs in requests are targets, never authority. The service checks current Agent
  identity, Permission, assignment, ownership and scope lifecycle; accept exact same-command retries without
  a duplicate memory entry. No sandbox database connection, shared writable host-memory folder or global
  file-backed bank is authorized.
- Preserve separately authorized private oversight: the exact managed `READ_PRIVATE_AGENT_MEMORIES` Permission
  is **not** `SUPER_ADMIN_ONLY`. An authorized Super Admin checks effective Permission and target access and
  generates safe attributed access audit; an audit Agent additionally needs an explicit TASK binding its audit
  purpose and target. Role tier or a reviewer profile alone cannot grant access. Actual provisioning/grants
  follow the qualified Access Control contract, not a hardcoded title check.
- Normal working-memory access is constrained by current assignment, including after Workflow closure,
  reassignment, revocation or retirement; retained private material remains available only under its separate
  applicable oversight path. No automatic age expiry, routine Agent deletion of shared history or bulk feed to
  a self-learning Agent follows from private storage.

This requirement does not publish Workflow/TASK/Repository/Workspace guidance; TICKET-00047 and TICKET-00048
own shared entries and promotion. TICKET-00050 owns exceptional safety removal and basic human inspection;
its prohibited-content path must be available before live memory ingestion is claimed safe.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Remember private learning | Record private entry in currently assigned or permitted general scope | Resolve Agent, assignment, scope and source references | Private entry recorded | Durable attributed current text in the correct partition; retry does not duplicate |
| Correct or clear own belief | Replace or clear own current private entry | Read current entry and applicable ownership/lifecycle | Private entry corrected or cleared | Only the current text changes; safe metadata remains, prior private value is not reloaded |
| Recall own private context | N/A — read-only | Read exact authorized entry/own scoped entries | N/A — read does not change memory | Permitted current text and provenance only, not another Agent's working memory |
| Inspect for authorized oversight | Audited private-memory inspection | Read target private history under `READ_PRIVATE_AGENT_MEMORIES` and current target authority | Safe attributed access audit on successful disclosure | No publication or peer disclosure; missing audit TASK denies an audit Agent |

Semantic names are not final transport DTOs, Permission catalogs beyond the accepted exact oversight name,
Doctrine mappings or a requirement to event-source entries.

## Validation and permissions

Reject forged/unknown scope IDs, unavailable or closed assignments for normal access, wrong Agent identity,
missing direct/effective Permission, cross-repository data in a general bank, and reads of another Agent's
private content without the exact oversight authority. Apply current authorization to each MCP operation,
including after revocation; do not rely on pinned templates, cached conversation text or request-supplied actor
IDs. Bound entry text and source references without choosing schema sizes here. Duplicate uncertain writes
resolve their original result; errors and audit metadata must not leak private text or credentials. ADR 0001
owns Domain/Application/Adapter policy placement; ADR 0002 owns guarded PostgreSQL consistency and migrations.

## Acceptance and evidence

- Demonstrate an Agent's private entry surviving its session restart, its own correction becoming the only
  current text, and different identities/assignments not inheriting private content. Inspect safe attribution
  and no raw transcript/obsolete text in normal recall.
- Demonstrate allowed direct Super Admin oversight, denied peer/reviewer access, and audit-Agent denial without
  the scoped audit TASK even when it has `READ_PRIVATE_AGENT_MEMORIES`; preserve secret-safe access audit.
- Verify ordinary HMAC/MCP caller binding, current authorization, known-denial behavior and uncertain retry
  recovery with owned application behavior and the real integration boundary when available. Do not add
  product tests for migrations, wrapper commands, planning files or configuration text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00185](../tasks/00185-TASK.md) | Remember and curate an Agent's scoped private memory | ready-for-agent |
| [TASK-00186](../tasks/00186-TASK.md) | Inspect private memory under scoped oversight | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved under [EPIC-00011](../epics/00011-EPIC.md), [WF-024](../wayfinder/tickets/WF-024-define-memory-ownership-and-scope-authority.md),
[WF-025](../wayfinder/tickets/WF-025-define-memory-lifecycle-and-promotion.md) and
[WF-026](../wayfinder/tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md). This TICKET owns the
private-memory use case, not Agent provisioning, Harness package work, live data ingestion or implementation
acceptance. TASK decomposition is separate.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00185 — Remember and curate an Agent's scoped private memory](../tasks/00185-TASK.md)
and [TASK-00186 — Inspect private memory under scoped oversight](../tasks/00186-TASK.md) as two complete,
independently reviewable outcomes, not schema/MCP layers or a formal test for every note. TASK-00185 follows
the accepted Repository identity, assigned-Agent and authenticated MCP caller capabilities. TASK-00186
follows TASK-00185 and the managed-policy baseline, but remains `needs-info` until a real explicit audit-TASK
assignment and target authority path is identified and qualified; Super Admin-only evidence cannot complete it.
Both remain unranked. No live memory ingestion is authorized before TICKET-00050's safety-removal path is ready;
implementation, checks, independent review, PR publication and current Board priority are unchanged.
