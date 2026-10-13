---
id: TICKET-00091
epic: EPIC-00006
title: Prepare and explicitly apply structured Planning proposals
status: ready-for-agent
---

# Prepare and explicitly apply structured Planning proposals

## Problem statement

Agent suggestions must survive interruption without becoming authoritative merely because they were generated.
Human review, semantic Planning validation, exact revisions and original-outcome recovery must agree across
single-artifact changes and atomic multi-record decomposition.

## Solution and boundaries

Provide protected durable proposal attempts/results through the existing Harness execution boundary, separate from
authoritative Planning revisions and unsaved browser edits. Bind admitted attempts to initiating human and acting
Agent, Repository/target or exact new-artifact intent, operation, base revisions, draft/selection generations,
conversation/submission, immutable snapshots and provider/model. Persist required intent/audit before external
work; retain original request/attempt identity through response loss, cancellation and recovery.

Keep Wayfinder, grill, to-tickets and to-tasks distinct operations. A Wayfinder interview resolves its decision,
not an EPIC; a separately invoked grill proposes one EPIC. Creating a parent never launches decomposition. Inspect
existing artifacts/children and propose additions or explicit revisions, not duplicates. Terminal/archived parents
require the owning reopen/restore action before decomposition. Agent preparation may gather facts while the human
is away, but cannot choose product/priority/boundary/risk preferences, invoke guarded lifecycle transitions, approve
its own proposal or begin another operation without the required human action.

Present editable structured candidates for Wayfinder maps/decisions, EPIC/TICKET/TASK changes, research and prototype
findings. Keep proposed, human-corrected, rejected, stale and applied versions distinguishable. Human answers are
durable revisioned decision points; corrections append semantic revisions. Validate bounded typed output and exact
references without salvaging malformed or partial output into apparently complete success. All model content is
untrusted inert data; it never becomes a general whole-document replacement or executable policy.

Require explicit human Create/Apply against current expected aggregate/queue revisions using the same semantic
Planning operations as direct editing. Make Tickets/Make Tasks proposals apply atomically: any stale member,
invalid relationship, missing permission or domain failure rejects the entire change. Persist required accepted
state, audit and effect intent atomically; record actual committed Planning facts, not model-authored success.
Use stable command identities; resolve uncertain apply before offering another. Proposal completion is not TASK
execution, review acceptance, PR publication or merge.

Retain conversation/proposal history after conflict. Offer optional AI-assisted reconciliation using exact base,
current and rejected proposal revisions, distinguish clean combinations from typed/relationship/narrative conflicts,
and produce a new candidate against current state for human review. Never last-write-wins, automatic merge/apply,
automatic new paid request or selection of a preferred product choice. Late results cannot overwrite newer human
edits, attach to another draft, revive stopped/discarded work or grant acceptance.

Expose the early protected proposal admission/result/status/cancellation/recovery contracts to the already-owned
[TICKET-00077](00077-TICKET.md)/TASK-00302 template-assistance consumer. That consumer retains its template schema,
Feature admission, editor and explicit publication; this TICKET adds no template publisher or second conversation
store. Its reusable attempt primitives must not wait for completed browser Planning or database Planning cutover.
Actual Planning Apply, unlike those primitives, requires the accepted database-authoritative Planning operations.

Exclude persistent direct-edit/browser-preview drafts, autosave, arbitrary document replacement, automatic proposal
acceptance, coding/Workflow execution, template publication and another provider queue. Unsaved manual preview
changes may be lost on refresh; durable server proposal recovery does not promise to recover them.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Request a bounded proposal | Admit exact operation/attempt under human intent | Current target/children, revisions, snapshots, disclosure and limits | Committed attempt facts, not accepted Planning facts | Recoverable generative work and protected candidate |
| Record an answer or inspect/edit preview | Record/correct accepted answer; preview edits remain volatile until explicit submission | Authorized proposal/history, unresolved questions and validation | Revisioned answer facts; no artifact event for an unsaved edit | Durable decision point or human-reviewable candidate |
| Create/apply approved content | Explicit semantic Planning command/atomic decomposition with expected revisions | Current authority, exact candidate and original command outcome | Owning committed Planning events and audit | Complete authoritative change or no partial hierarchy |
| Reconcile a stale proposal | Explicit bounded reconciliation request, then separate human Apply | Exact base/current/rejected revisions and conflicts | New proposal-attempt facts; no automatic accepted revision | Editable current-base candidate preserving original history |
| Stop or recover an attempt/apply | Explicit stop or original-operation reconciliation | Exact provider/Planning outcome and current visibility | Confirmed lifecycle/outcome facts only | Safe result/hold without duplicate generation or application |

## Validation and permissions

Authorize proposing, reading drafts, accepting revisions and guarded lifecycle operations independently against
current initiating-human authority and real acting-Agent delegation. Preserve Team/Repository/target scope and
owner-first visibility. Profiles, Skill text or a model's claimed approval cannot authorize Create/Apply. Every
final mutation revalidates domain invariants, references and expected revisions under the Planning owner; no client
validation, schema-valid model response or stale snapshot replaces server authorization.

Bound content/collections, reject unknown IDs/types and sanitize errors. Provider calls use
[TICKET-00089](00089-TICKET.md)'s separate disclosure/budget/currentness contract outside business transactions.
[TICKET-00090](00090-TICKET.md) owns shared durable execution; keep one attempt/result authority and exact mapping
rather than dual writers. Status/result reads never invoke models or replay mutations. Unknown billing/outcomes
retain bounded needs-human state when safe recovery is unsupported. Raw secrets/hidden reasoning stay excluded.

## Acceptance and evidence

- A real Planning operation yields an editable structured candidate; only explicit authorized human Create/Apply
  commits it. Separate Wayfinder/grill/decomposition actions remain observable through a continuous journey.
- Multi-record decomposition commits all intended records atomically or none; stale/malformed/denied proposals
  preserve answers and history. Reconciliation always returns a newly reviewed candidate, never silent overwrite.
- Double submit, lost apply response, cancellation, delayed model output and worker/browser replacement retain
  original identities and human edits without duplicate paid work, artifact creation or restored revoked access.
- Protected proposal primitives support the existing template-assistance consumer without relocating its Feature
  or publication authority; its actual integration evidence remains with TASK-00302.
- Owned proposal/Planning transaction, authorization and recovery tests, real provider-assisted Planning evidence
  and `./bin/build` pass. No framework, configuration, migration-file or planning-tool tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 7 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-012](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md),
[WF-010](../wayfinder/tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Planning Apply consumes TICKET-00026's semantic operations and the verified TICKET-00028 authority switch, with
TICKET-00027 queue revisions where applicable. Early protected attempts/results consume exact session/provider
contracts and supply TASK-00302 independently of final Explorer/Project Manager or complete browser Planning.
[TICKET-00092](00092-TICKET.md) owns the browser journey and consumes these operations; no reverse dependency on
its final UI or role acceptance is permitted. No TASKs, live proposal, Planning mutation or authority cutover is
created by this requirement approval; existing consumer needs-info holds require separate precise TASK mapping.
