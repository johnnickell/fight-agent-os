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
| [TASK-00355](../tasks/00355-TASK.md) | Request inspect and recover protected proposals | ready-for-agent |
| [TASK-00356](../tasks/00356-TASK.md) | Record human Planning answers and operation boundaries | ready-for-agent |
| [TASK-00357](../tasks/00357-TASK.md) | Prepare and inspect typed Planning candidates | ready-for-agent |
| [TASK-00358](../tasks/00358-TASK.md) | Explicitly create or apply reviewed Planning changes | ready-for-agent |
| [TASK-00359](../tasks/00359-TASK.md) | Apply multi-record Planning proposals atomically | ready-for-agent |
| [TASK-00360](../tasks/00360-TASK.md) | Reconcile rejected proposals without silent application | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved six independently reviewable implementation slices in the existing isolated planning
worktree. They separate reusable proposal contracts from database-authoritative Planning decisions/Apply and
final browser/role integration.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00355](../tasks/00355-TASK.md) | Protected proposal admission, typed result/status, cancellation and original-attempt recovery |
| B | [TASK-00356](../tasks/00356-TASK.md) | Revisioned human answers/corrections and explicit distinct Planning operation boundaries |
| C | [TASK-00357](../tasks/00357-TASK.md) | Real typed Planning candidates with exact references, human corrections and retained versions |
| D | [TASK-00358](../tasks/00358-TASK.md) | Explicit human semantic Create/Apply and original committed-outcome recovery |
| E | [TASK-00359](../tasks/00359-TASK.md) | All-or-none multi-record decomposition and supported initial map/decision creation |
| F | [TASK-00360](../tasks/00360-TASK.md) | Explicit bounded reconciliation yielding a new current-base candidate for separate human review |

TASK-00355 consumes TASK-00349/00351/00353, including existing provider admission/accounting transitively, without
Planning cutover or final browser dependencies. TASK-00356 consumes TASK-00349/00352/00086/00104: conversation input
remains distinct from existing Planning DecisionPoints, and actual Planning mutation requires verified database
authority. TASK-00357 consumes TASK-00355/00356; TASK-00358 consumes TASK-00357; TASK-00359 consumes TASK-00358;
TASK-00360 consumes TASK-00359. TASK-00085–00095 retain semantic artifacts, evidence, lifecycle, relationships and
ordering; TASK-00103/00104 retain authority enforcement and verified cutover. No nested transaction or second
allocator/attempt/transcript authority substitutes for their actual participants.

All six records are `ready-for-agent` behind unfinished dependencies, not runtime qualification. TASK-00350's
precise operational host/broker gap and inherited Team/creation/current-authority holds persist. Real provider-
assisted acceptance uses one selected qualified TASK-00346 or TASK-00347 path, never mandatory simultaneous setup
or a proof-only product. Owned tests, actual integration and operator guidance belong within these outcomes.
Orders remain unranked; Board priority and the active terminal-first milestone remain unchanged.

TASK-00302 gains TASK-00355, not completed TASK-00356–00360. It retains template payload/Permission validation,
Feature admission, editor and explicit publication plus its independent host/authority/rollout/publication holds.
The sole proposal-allocation gap is mapped, but its `needs-info` remains. TASK-00266 gains TASK-00360's complete
proposal/answer/Apply/reconciliation chain while retaining actual Project Manager integration. The subsequent
TICKET-00092 split now adds TASK-00362–00367, with TASK-00361 transitively, resolving its browser allocation gap
while retaining TASK-00364's exact protected prototype-execution hold. Existing profile, contribution, Triage/scoped-read and host/authority holds remain.
No final consumer reverse-blocks shared primitives; generic proposal availability does not imply live template
or managed-role qualification.

TICKET-00092 now maps TASK-00361–00367's browser intake/questions/previews, visibility/handoff and continuous
journey against these operations. Durable server candidates are not autosaved manual forms. Reconciliation is an explicit
new bounded request, not automatic retry; Apply remains a separate exact human command. Unknown original outcomes
hold another Apply, and neither accepted Planning content nor a final proposal starts execution/publication.

This decomposition is complete, not implementation. Following TICKET-00092–00099's approved browser/Runner/evidence/web-research/history/sessions/measurement/catalog splits,
continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve Markdown authority until separately approved verified
cutover and preserve all unresolved suppliers. Planning authorizes no model call, account/credential operation,
proposal, accepted answer, Planning mutation, cutover or runtime execution.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving all prior approved
uncommitted planning changes. Live and archived TASK identities were rechecked before allocating TASK-00355–00360;
the live ceiling was TASK-00354 and the archive contained no TASK records. `./bin/planning-check --write` and
`./bin/planning-check` passed with 487 records, 395 active and current generated views. Tracked `git diff --check`
and all 52 new/untracked TASK whitespace checks passed, including these six records. Validation reported no
invalid planning links or dependency cycles; that historical handoff advanced to TICKET-00092. Board
priority and active TASK-00138 remain unchanged, and all new orders are unranked.

These are planning checks, not provider/runtime, database Apply or browser qualification. The full `./bin/build`
was not run and remains required before commit/publication. No implementation, provider call, credential access,
proposal/answer, live Planning mutation, cutover, session launch, commit, push, PR or merge was performed. Changes
remain uncommitted in the isolated worktree; independent review and publication are separate. Unrelated concurrent
main-checkout work was left untouched, not claimed clean or included in this scope. Earlier receipts remain
historical and are not fresh evidence for these unimplemented capabilities.

### Original requirement approval and supplier handoff

The allocation requests below are superseded by the approved mappings above, not by operational qualification.

Approved as area 7 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-012](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md),
[WF-010](../wayfinder/tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

TICKET-00089 now maps TASK-00344/00345's protected disclosure/budget admission and original-outcome/accounting
contracts. TASK-00346/00347 independently supply actual OpenRouter/Codex paths; proposal consumers use the selected
qualified path without requiring both configured. TICKET-00090 now maps TASK-00348/00349 early assignment/attempt
facts and TASK-00351/00352/00353 invocation/attachment/recovery. Those suppliers retain TASK-00350's precise
operational host/broker gap; this TICKET now maps structured proposals/results to TASK-00355/00357, semantic
answer acceptance to TASK-00356 and Apply/reconciliation to TASK-00358–00360. TASK-00302 consumes only TASK-00355.
Do not make provider primitives depend on completed proposal/application or browser journeys.

Planning Apply consumes TICKET-00026's semantic operations and the verified TICKET-00028 authority switch, with
TICKET-00027 queue revisions where applicable. Early protected attempts/results consume exact session/provider
contracts and supply TASK-00302 independently of final Explorer/Project Manager or complete browser Planning.
[TICKET-00092](00092-TICKET.md) owns the browser journey and consumes these operations; no reverse dependency on
its final UI or role acceptance is permitted. The original requirement approval created no TASKs; the subsequent
approved split above creates planning records only. Actual supported contracts, host/authority composition and
consumer qualification remain incomplete; no live proposal, Planning mutation or authority cutover occurred.
