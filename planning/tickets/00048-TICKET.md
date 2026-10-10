---
id: TICKET-00048
epic: EPIC-00011
title: Review and publish broader memory lessons
status: ready-for-agent
---

# Review and publish broader memory lessons

## Problem statement

A locally useful observation cannot become Repository or Workspace guidance merely because an Agent wrote it.
Broader readers need attributed, independently assessed lessons whose source and limits remain visible, with
rejected proposals preserved for authorized learning rather than reused as instructions.

## Solution and boundaries

- Build on the scoped shared/private entry rules in TICKET-00046 and TICKET-00047. An Agent with current access
  may propose a broader target scope, including a direct jump over intermediate levels. A proposal is not
  publication, a Permission, an instruction to its reviewer or ordinary task guidance.
- Only a currently authorized target-scope owner may accept and publish its **own new entry** after checking
  underlying code, Planning decision, Workflow evidence or another relevant source, recording why the claim
  applies at that target and where it does not. Distinguish memory-source links from independently checked
  evidence. Do not move or relabel the original or require an extra human confirmation solely because a
  properly authorized steward publishes at a broader scope.
- Keep proposal target, actor, rationale, result and source/evidence links. Rejecting a proposal does not mark
  its source false, delete it or inject rejected text into ordinary Agent context. Accepting produces an
  attributed published entry linked to the proposal and sources. Only target-scope ownership, current
  assignment and direct/effective Permissions grant publication; a job title or source note cannot grant them.
- Link later corrections/retractions to published entries and alert the owners of broader derived entries and
  summaries when a source changes. Do not automatically rewrite or invalidate a broader conclusion supported
  by independent evidence. Allow stewards to assess contradictory lessons against actual sources rather than
  automatically merge or suppress them. Retry of the same proposal/review/publication operation does not
  duplicate its result; distinct proposals remain attributed.
- Keep project memory anchored to `RepositoryId` in its Workspace. A small private general bank is **not**
  Workspace-shared guidance; an Engineer cannot promote its own note by writing directly to a wider scope.
  Source code describes current behavior; published Planning describes approved intent. A memory must report
  their discrepancy rather than claim either is amended by publication.

This TICKET does not invent a new Project identity, require adjacent-level promotion, automatically approve
proposals, replace Planning approval or specify Jev selection thresholds. TICKET-00050 owns the human-visible
history and exceptional safety-removal path; it does not become another publication authority.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Propose broader guidance | Propose publication with source, target and applicability reason | Resolve permitted source and target | Promotion proposed | Attributed pending proposal only, no broader entry or access grant |
| Assess a proposal | Accept or reject with rationale under target ownership | Inspect source, independently checked evidence and conflicting guidance | Proposal accepted or rejected | Rejection remains history only; acceptance permits separately attributed publication |
| Publish an accepted lesson | Publish new target-scope entry from accepted proposal | Resolve current target authority and checked evidence | Broader entry published | Linked Repository/Workspace guidance without rewriting the source |
| Reassess changed source | Correct/retract own published entry after source-change notification | Inspect lineage and current source/evidence | Derived owner notified; correction or retraction recorded when chosen | No silent rewrite of independently supported entries |

## Validation and permissions

Check current source read and target write authority independently at the relevant operation; enforce target
Repository/Workspace ownership and live assignment/Permission rather than trusting a proposed scope or prose
embedded in an entry. A steward's model-readable source is untrusted data, never an instruction to execute,
approve or expand authority. Reject invalid/removed source links and missing applicability/evidence rationale
safely; record an explicit unknown or disputed basis rather than fabricating verification. Attribute decisions
and deduplicate exact retries. Rejected proposals are excluded from ordinary MCP guidance, though a separately
authorized audit/self-learning path may inspect them. TICKET-00050's safety removal takes precedence when a
source contains prohibited material.

## Acceptance and evidence

- Demonstrate one Engineer proposal accepted by a separately authorized owner and published as a new linked
  Repository or Workspace entry; deny direct broader publication by the Engineer.
- Demonstrate attributed rejection without deleting or surfacing the proposal as ordinary guidance, direct
  versus adjacent target choice, duplicate retry behavior and independent assessment of source evidence.
- Demonstrate a later source correction notifying derived owners without silently editing the published entry
  and a steward correction/retraction with preserved safe lineage. Test owned access and lifecycle behavior,
  not the existence of planning text or generated wiring.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00190](../tasks/00190-TASK.md) | Propose broader guidance from permitted memory | ready-for-agent |
| [TASK-00191](../tasks/00191-TASK.md) | Review and publish guidance at an authorized target | needs-info |
| [TASK-00192](../tasks/00192-TASK.md) | Reassess published guidance when a source changes | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved under [EPIC-00011](../epics/00011-EPIC.md), [WF-024](../wayfinder/tickets/WF-024-define-memory-ownership-and-scope-authority.md)
and [WF-025](../wayfinder/tickets/WF-025-define-memory-lifecycle-and-promotion.md). No promotion, Agent,
Permission, target entry or live human review was performed by accepting this TICKET. TASK decomposition is
separate.

### Approved TASK decomposition — 2026-10-10

John approved three independently reviewable outcomes:
[TASK-00190 — Propose broader guidance from permitted memory](../tasks/00190-TASK.md),
[TASK-00191 — Review and publish guidance at an authorized target](../tasks/00191-TASK.md), and
[TASK-00192 — Reassess published guidance when a source changes](../tasks/00192-TASK.md).
TASK-00191 remains `needs-info` until the real Repository and Workspace steward/target-write authority
paths are identified; a title or TASK-00189's historical-read path is not publication authority. The
records remain unranked. TICKET-00049 and TICKET-00050 still require separate TASK decomposition; this
approval does not activate live ingestion, publish memory or change the Board's execution priority.
