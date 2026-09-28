# Define memory ownership and scope authority

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Hierarchical agent memory](../hierarchical-agent-memory-map.md)
**Depends on:** —

## Question

What ownership and authority model lets an Agent read relevant shared memory while limiting writes and promotion
to its assigned scopes, including continuity across sessions and multiple Workflows for one TASK?

## Agreed basis — 2026-09-28

John agreed to a shared hierarchy of Workspace → Project → TASK → Workflow → Phase → Session, with Agent memory
alongside that hierarchy because one stable Agent may participate in several sessions or assignments. The hierarchy
describes applicability and ownership; it does not automatically grant access to every descendant or ancestor.

| Template | Intended reads within authorized assignments | Intended writes |
|---|---|---|
| Implementing Agent | Own working memory; relevant phase, Workflow, TASK and project memory; workspace access where permitted | Own session/Agent memory and assigned phase |
| Team Lead (Coordinator) | Relevant lower-scope working memory, Workflow, TASK, project and permitted workspace memory | Own session/Agent/phase memory and Workflow; TASK ownership is a candidate when work spans Workflows |
| Project Manager | Relevant authorized memory throughout its project, plus permitted workspace memory | Own working memory and project |
| Director / CTO | Authorized memory across the workspace | Own working memory and workspace |

These are semantic Agent profile templates, not the Access Control Role entity. Templates request capabilities;
actual direct Permissions, assignment scope and live server checks enforce access. Sharing a template does not
share an identity or memory automatically. An implementing Agent cannot publish a conclusion at workspace scope.

Read, write and promotion are separate operations. Same-level writes require ownership; one Agent cannot rewrite
another Agent's notes merely because both work in the same phase. Wider applicability does not imply greater truth.
Memory never grants execution authority, changes approved scope, supplies an approval, or substitutes for current
verification. Independent review requires an explicit context policy as well as separate identities and sessions.

## Must decide

- Whether Project memory is owned by the existing registered Repository identity or needs a distinct project concept;
  preserve the accepted Workspace/Repository model rather than silently introducing a new aggregate.
- Exact read/write/curation matrix, including whether higher-scope owners can revise lower-scope entries or only
  publish their own summaries, and who can inspect private working notes and conversations.
- Phase-shared versus Agent-private memory, ownership of phase summaries, and attribution when several Agents contribute.
- Stable Agent memory across session restart, process replacement, reassignment and identity retirement; prevent
  unrelated assignments from inheriting sensitive material through an Agent's personal history.
- Whether TASK memory is always available or conditional on multiple Workflows; who owns it across successive
  Team Leads, failed attempts, resumed work and concurrent assignments.
- Scope-limited workspace reads for implementers, project-wide reads for Project Managers, and Director access;
  no job title alone implies unrestricted access.
- Human visibility and control, revocation behavior, and reviewer visibility of prior findings versus private
  implementation reasoning. Define which historical evidence is deliberately supplied to a fresh review.

## Resolution boundary

Set ownership, applicability, continuity and authorization semantics. WF-025 owns lifecycle and promotion;
WF-026 owns retrieval and sandbox delivery. Do not provision templates, permissions or Agents, select schemas,
or amend accepted execution contracts merely by charting this decision.
