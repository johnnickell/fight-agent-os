---
id: TICKET-00047
epic: EPIC-00011
title: Share attributed Workflow and TASK learning
status: ready-for-agent
---

# Share attributed Workflow and TASK learning

## Problem statement

A later authorized Workflow needs useful lessons from prior attempts without resuming a predecessor's private
session or treating a memory note as the mandatory handoff, a current check result or an approval. Attributed
shared history must remain available for permitted learning after work completes while ordinary execution
access respects the Workflow and TASK lifecycle.

## Solution and boundaries

- Build on TICKET-00046's entry identity and application authority. A Team Lead owns shared Workflow entries;
  an assigned Team Lead can append attributed TASK summaries starting with the first Workflow. Separate failed,
  resumed or successive Workflows keep their own memory. Agents keep their private phase/working notes; required
  attributed phase handoffs/outcomes remain under the Workflow contract, not memory.
- Accept explicitly scoped, idempotent shared append, correction, supersession and retraction intents through
  the first-party HMAC/MCP operations. Preserve order and authorship; do not edit another Agent's original text
  or erase attributed shared history. Ordinary guidance excludes retracted claims; permitted historical
  inspection can still find them. Similar or contradictory notes remain separately attributed for assessment.
- An Engineer can read shared Phase/Workflow/TASK entries relevant to its **current** assignment but loses normal
  access to its completed Workflow's memory after closure. An assigned Team Lead may read completed Workflow
  memory while its TASK remains active to prepare another attempt or a TASK summary; its normal Workflow/TASK
  reads end when the TASK completes. A later Workflow receives TASK summaries and mandatory handoffs, not
  predecessor Workflow memory or private notes automatically.
- Authorized Project Manager access to completed Workflows within its repository remains available for future
  estimation. Permitted humans and scoped audit Agents have separately authorized historical access. Completion
  labels the Workflow/TASK association but does not expire, archive or delete entries. Retained history may
  support skill and workflow improvement, metrics and handoff templates through a properly scoped principal;
  it is not an unpermissioned feed.
- Source code remains the final authority for current behavior and published Planning for approved intent;
  memory citations never change those authorities or override independent review. Shared retrieval and
  authorship cannot expand a receiving Agent's permissions or replace the exact review subject, criteria,
  checks and prior findings required by the handoff contract.

This TICKET does not publish Repository/Workspace guidance, decide promotion proposals (TICKET-00048), enable
Jev question lookup (TICKET-00049), or implement a separate formal handoff system. Safety removal and the basic
human oversight path belong to TICKET-00050 and must be available before unsafe live ingestion is claimed safe.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Share a Workflow lesson | Append entry at owned Workflow scope | Resolve Agent, Workflow, assignment and source references | Workflow memory appended | New attributed shared entry; original private notes and mandatory handoffs unchanged |
| Carry learning across attempts | Append TASK summary in assigned active TASK | Read permitted prior Workflow lessons and TASK context | TASK summary published | Later authorized Team Lead sees attributed TASK guidance without inherited private or predecessor Workflow context |
| Correct or retract a shared claim | Append linked correction, supersession or retraction | Read source identity and current scope | Shared correction or retraction recorded | Original remains historical; normal guidance no longer presents a retracted claim |
| Reuse completed-work history | N/A — read-only | Resolve currently authorized project, audit or human historical view | N/A — read does not change entries | Permitted historical lessons remain after TASK completion; ordinary execution access closes |

## Validation and permissions

Validate current Agent identity, direct/effective Permission, owned target scope, Workflow/TASK state, assignment
and safe source references at each operation. A guessed Workflow/TASK ID, Team Lead title, earlier access or a
prior handoff cannot grant shared write/read rights. Same-command retry returns its original append; a distinct
append is ordered rather than silently merged. Deny Engineer cross-Workflow reads after closure and normal Team
Lead reads after TASK completion without hiding Project Manager or authorized audit/history access. Bound
returned content and safe audit metadata; never expose another Agent's private thoughts through shared results.
Protect prohibited content through TICKET-00050, not an ordinary retraction that retains the payload.

## Acceptance and evidence

- Show an assigned Team Lead publishing distinct Workflow and TASK lessons and a later authorized Workflow
  receiving the TASK summary via its own context while mandatory handoff evidence remains separate.
- Verify lifecycle-sensitive permitted/denied reads for Engineer, Team Lead, Project Manager and authorized
  historical principals after Workflow and TASK completion; completion never deletes the lesson.
- Demonstrate attributed append order, retry deduplication, linked correction/retraction and ordinary versus
  historical guidance without testing generated planning, schema or workflow-tool configuration text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00187](../tasks/00187-TASK.md) | Curate attributed lessons within a Workflow | ready-for-agent |
| [TASK-00188](../tasks/00188-TASK.md) | Carry TASK summaries across Workflow attempts | ready-for-agent |
| [TASK-00189](../tasks/00189-TASK.md) | Inspect retained lessons under historical authority | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved under [EPIC-00011](../epics/00011-EPIC.md), [WF-024](../wayfinder/tickets/WF-024-define-memory-ownership-and-scope-authority.md),
[WF-025](../wayfinder/tickets/WF-025-define-memory-lifecycle-and-promotion.md), and
[WF-026](../wayfinder/tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md). Reuse the actual
coordinated Workflow/phase contracts from EPIC-00007 and EPIC-00008 when available; this TICKET does not
advance their Board priority, create a Workflow or publish live memory. TASK decomposition is separate.

### Approved TASK decomposition — 2026-10-10

John approved three vertical, independently reviewable outcomes:
[TASK-00187 — Curate attributed lessons within a Workflow](../tasks/00187-TASK.md),
[TASK-00188 — Carry TASK summaries across Workflow attempts](../tasks/00188-TASK.md), and
[TASK-00189 — Inspect retained lessons under historical authority](../tasks/00189-TASK.md). The last remains
`needs-info` until actual Project Manager/Repository and scoped audit-TASK authority paths can be identified;
templates, title-only roles, private oversight or test fixtures do not supply those paths. All three remain
unranked, and live memory ingestion remains gated on TICKET-00050's safety-removal capability. TICKET-00048
through TICKET-00050 still need separate TASK decomposition; this approval does not start implementation.
