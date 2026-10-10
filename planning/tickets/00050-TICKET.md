---
id: TICKET-00050
epic: EPIC-00011
title: Inspect memory and remove prohibited content safely
status: ready-for-agent
---

# Inspect memory and remove prohibited content safely

## Problem statement

Durable private and shared memory needs a basic human oversight path and an exceptional way to remove secrets
or other prohibited text. An ordinary correction or retraction that leaves the offending bytes in history,
provider inputs or derived views is not safety removal; unrestricted human or Agent browsing of private notes
would be another privacy failure.

## Solution and boundaries

- Offer an authorized human a basic inspection path for shared and permitted private memory, its current versus
  historical state, ownership/scope, provenance and source links, and promotion acceptance/rejection history.
  Keep it proportionate: no polished memory dashboard, general repository-file editor, human access by job title
  alone or browser-side permission authority. Reuse the authenticated Dashboard and current-principal boundaries
  from ADR 0003/0004 when available.
- Support owned-scope human corrections, supersession or retractions under the same memory lifecycle as Agent
  operations. A human oversight read is not a right to rewrite another author's attributed history. Private
  Agent-content inspection requires `READ_PRIVATE_AGENT_MEMORIES` plus current target access; an audit Agent
  additionally requires the explicit scoped audit TASK. Record safe attributed audit for private inspection.
- Provide a **separately authorized** exceptional safety-removal intent for prohibited content. Remove offending
  payload from the entry, copies in linked published content, rebuildable search/summary views and applicable
  exports, and address backup retention; preserve only non-sensitive audit/tombstone metadata where safe.
  Alert owners of linked entries to inspect for remaining copies without reproducing the prohibited bytes in
  alerts. Do not ask an Agent to delete routine shared history or mislabel retraction as redaction.
- Make safety removal and secret-safe provider handling available before claiming live memory ingestion and
  Jev disclosure safe. Treat links, citations, previews, logs and provider receipts as potential leak paths;
  source memory must not remain available through a stale derived result after removal. Exact operational
  backup/export remediation follows the qualified installation's capabilities; do not claim impossible
  instantaneous erasure from third-party systems or retain a forbidden payload merely for history.
- Keep human oversight subject to Workflow/TASK lifecycle and separate project/historical authority: closure
  removes ordinary execution access, not the Project Manager's currently authorized estimation read or
  permitted human/audit history. Source code and published Planning still outrank memory; an interface cannot
  present an unverified memory as an approved instruction.

Routine scheduler-based archival/removal, generated Agent names, detailed UX design and external oversight
publication are separate concerns. This TICKET does not grant an audit Agent private access just because it
can read shared history, nor does it authorize hidden Pi reasoning export.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect authorized memory | N/A — read-only | Query shared or separately permitted private current/history and safe lineage | Safe private-access audit observation where required | No new instruction, access grant or memory mutation |
| Correct within owned scope | Append linked correction/retraction or change own current private text through owning operation | Inspect current entry, sources and ownership | Correction or retraction recorded under owning lifecycle | Attributed history remains; obsolete guidance stops being current |
| Inspect promotion outcomes | N/A — read-only | Query proposal rationale and linked accepted/rejected publication | N/A — inspection does not publish | Rejected text stays out of ordinary Agent context |
| Remove prohibited content | Authorize exceptional safety removal with reason and target | Resolve linked copies, derived views and export/backup reach | Safety removal and safe audit fact recorded | Forbidden bytes are removed or access blocked pending verified remediation; safe metadata remains |

## Validation and permissions

Validate current authenticated user/Agent identity, direct/effective Permission, target Repository/Workspace and
ownership before exposing titles, snippets, history, private content or mutation controls. Server checks are
binding; browser controls and role labels are only presentation. Apply separate authorization to safety removal,
record bounded secret-free reasons and results, and reject stale target/derived copies rather than return removed
content. Do not allow ordinary Agent roles to invoke permanent shared deletion or bypass mandatory Workflow
handoffs. Safe failure when part of a required removal is not yet verified must block further disclosure of
that payload; transaction and recoverable external effects follow ADR 0001/0002 rather than assuming one
atomic database transaction erases provider-held bytes.

## Acceptance and evidence

- Demonstrate allowed human inspection of shared memory, permitted private oversight with attributed access
  audit, and denied peer/reviewer or out-of-scope human access. Show accepted/rejected proposal history without
  turning rejected content into ordinary guidance.
- Demonstrate ordinary correction versus exceptional safety removal on a representative prohibited payload,
  including linked published entries and derived views/exports where applicable; verify no source body or
  credential is copied into notifications/receipts. Inspect actual backup/export handling before claiming
  complete removal; surface any unverified external retention honestly.
- Verify the usable basic human journey and authoritative server denials with focused application and browser
  behavior evidence. Do not add product-suite tests for migration files, generated indexes, build wrappers,
  planning Markdown or deliberately seeded failures.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved under [EPIC-00011](../epics/00011-EPIC.md), [WF-024](../wayfinder/tickets/WF-024-define-memory-ownership-and-scope-authority.md)
and [WF-025](../wayfinder/tickets/WF-025-define-memory-lifecycle-and-promotion.md). Basic human oversight
was explicitly included during the EPIC grill; no live human operation, Agent access grant, deletion, backup
change or provider call occurred through this planning TICKET. TASK decomposition is separate.
