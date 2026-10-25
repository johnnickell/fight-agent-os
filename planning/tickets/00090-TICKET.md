---
id: TICKET-00090
epic: EPIC-00006
title: Run and recover authorized durable Agent conversations
status: ready-for-agent
---

# Run and recover authorized durable Agent conversations

## Problem statement

An authorized bounded Agent assignment must survive client disconnect and process replacement without launching
a second Agent, losing accepted input or replaying uncertain effects. Browser and terminal must not create rival
conversation authorities or borrow unprotected local credentials when managed work is unavailable.

## Solution and boundaries

Provide the protected Harness conversation/execution capability for explicit bounded manual assignments and browser
Planning consumers. A separate human assignment identifies objective, acting named Agent, authorized Repository/
checkout/context, tool/effect bounds, allowed provider/model and finite limits. Agent creation or client attachment
never starts work automatically. Preserve initiating human, acting Agent, Team and real holder identity through
current authenticated delegation. Manual use needs no fabricated TASK Workflow or database Planning cutover.

Persist input before delegated work starts. Maintain stable conversation/submission/attempt identities, exact
Harness/Repository snapshot bindings, accepted answers, pending questions, proposal references, progress and
internal Pi-session lineage. Keep one bounded active Agent turn per conversation with durable admission/currentness
and single-owner fencing. Concurrent client input produces an explicit serialized outcome or conflict, not two
executions. Browser/terminal are clients of the same authorized conversation, not new Agents or credential holders.

Follow the accepted [Pi Durable direction](00032-TICKET.md#pi-durable-integration-direction--2026-10-13): evaluate
`@earendil-works/pi-durable` before equivalent checkpoint, queued-input or reconnect machinery. Qualification must
pin exact package/source/runtime/extensions and protected storage outside disposable worktrees, a single active
storage owner, brokered tools/providers and supported restart/recovery behavior. Record concrete gaps before
choosing an alternative. Internal Pi tasks are not Planning TASKs; storage durability does not execute work while
the owning machine is off or imply cross-machine migration.

Detach/reconnect resolves the original authorized Agent/conversation and committed state. Closing a client,
logging out or losing connectivity does not itself cancel an accepted turn, but live authority revocation still
denies subsequent protected actions. Preserve explicit Stop/pause/cancel state, owned background-work handling,
late results and uncertain liveness. Reconcile original application/provider effects before retry or dependent work;
only demonstrably replay-safe work may rerun. Missing current authority, safe storage, host protection or outcome
knowledge holds visibly. No duplicate launch, new holder, scope expansion or silent snapshot/provider refresh.

Keep owner-first protected transcript/input/checkpoint access; external observation exposes only authorized safe
status. Conversation-sharing decisions come from the owning journey, not a copied transcript. Integrate existing
[TICKET-00040](00040-TICKET.md)/[TICKET-00041](00041-TICKET.md) checkpoint/compaction contracts where applicable,
without reimplementing their selection/timing or invalidating local-session scope. Preserve required facts, question
identity and approval validity; a saved answer cannot revive expired or consumed authority.

Provide protected lifecycle/retention seams for completed/abandoned state and narrowly authorized redaction/holds.
Preserve active, paused, recoverable and needs-human state; follow WF-017's configurable 30-day terminal grace and
holds for applicable protected Pi/checkpoint content. Planning history and accepted artifacts retain their own
lifecycle. No blanket transcript deletion or automatic archive is implied.

Exclude Workflow claims/transitions, Runner provisioning, sandbox implementation, independent review acceptance,
coding execution, another credential or Team store, generic chat product and duplicate role outcomes. TASK-00250
owns role-session integration; TASK-00247 and TASK-00266 retain Explorer and Project Manager integration.

### Hosted Planning binding and recorded conversation usage

For the approved first browser journey, bind each admitted turn to its initiating user, selected environment,
nonsecret provider-connection identity and exact generation. Resolve credentials only through TICKET-00089's
protected owner. Conversation sharing, administrator access, reconnect and account/key replacement cannot borrow
another user's connection or reassign original attempts; current disclosure and budget authority still apply.

TASK-00352 owns the safe conversation/turn-to-original-usage query extension, consuming TASK-00345's normalized
facts and TASK-00344's authorized applicable budget view. Preserve attempt and linked-continuation attribution,
known stopped/failed/superseded charges, supported token categories, reported cost/currency versus explicit
estimates and missing/partial/stale/uncertain coverage. Do not double-count cumulative reports, parent/nested
activity or linked history, or aggregate incompatible currencies/units. No dummy Workflow, second ledger,
transcript writer or live channel is introduced. TASK-00396/00391 remain Workflow consumers, not these suppliers.

Ordinary conversation/history/usage reads cause no provider calls. Provider allowance stays separately authorized
under TICKET-00098; conversation visibility does not disclose another user's allowance or private environment totals.
TICKET-00092 renders these shared query results. A secondary usage/allowance failure must not erase otherwise
readable Planning; admission retains its authoritative limits independently of display availability.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Assign bounded manual work | Explicit human admission with original request identity | Current Agent/Team/scope, holder, snapshot, provider and host readiness | Committed assignment/submission facts | Protected durable work intent; no automatic grant or dummy Workflow |
| Submit input/run one turn | Persist input then claim/execute exact turn | Current conversation generation, pending input, authority and limits | Committed input/attempt/outcome facts; sanitized progress observations | One bounded active turn and durable result/history |
| Attach or reconnect | Authorized attachment; status/history reads stay read-only | Original conversation, current visibility, committed progress and pending question | Safe attachment observations, not a new Agent/session grant | Same conversation exposed through supported clients |
| Stop, pause, cancel or recover | Explicit lifecycle operation/original-attempt recovery | Current liveness, original effects, snapshots and authority | Confirmed lifecycle transitions only | Fenced continuation or truthful hold; no uncertain-effect replay |
| Retain or narrowly redact | Authorized retention/hold/redaction under owning policy | Exact content category, terminal state, references and holds | Sanitized retention/redaction facts, not content copies | Protected cleanup only when eligible; active recovery preserved |

## Validation and permissions

Require current human assignment/visibility authority independently of Agent-management rights. Each protected
operation rechecks live Agent direct Permissions, human delegation, Team/Repository scope, credential activation,
current host/session constraints and relevant claim/lease state when a real Workflow exists. Never infer Workflow
authority for manual work. Missing qualified host/origin/provider controls deny rather than use unrestricted Pi tools.

Bound submissions, histories, turns, recovery attempts and resource consumption. Enforce currentness against stale
clients/workers and duplicate acknowledgements; status GETs cannot run or retry work. Keep credentials outside model
context/child environments and private content out of ordinary logs/Workflow observation. Coordinate durable PHP
state and the selected transcript runtime explicitly; no dual writer or claim of distributed exactly-once effects.

## Acceptance and evidence

- Hosted per-user connection/generation binding survives reconnect, replacement, revocation and linked continuation
  without cross-user key use, liability reset or private usage disclosure. Conversation usage queries show original
  deduplicated facts without a Workflow, new ledger or provider call; unsupported values remain explicit.
- Real bounded authorized work survives detach/reattach and process replacement with the same Agent/conversation,
  accepted input, pending questions and exact snapshots; concurrent inputs preserve one active turn.
- Real supported runtime/host/storage/broker participants demonstrate stale-owner fencing, interrupted activation,
  stop/background-work behavior, revocation, uncertain liveness/effects and no duplicate paid/tool actions.
- Manual admission cannot borrow creation rights, bypass host protection or launch from attachment; unsupported
  attachment paths and powered-off execution remain visibly unavailable.
- Owner-first history and protected checkpoint/retention rules are enforced without exposing transcripts to ordinary
  observers or replaying approvals. Shared compaction behavior remains with its existing owner.
- Owned lifecycle/concurrency/recovery tests, direct pinned-runtime qualification, existing Explorer/Planning/custom
  consumer evidence and `./bin/build` pass. No disposable runtime proof product or tests of infrastructure text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00348](../tasks/00348-TASK.md) | Record bounded assignments and conversation identities | ready-for-agent |
| [TASK-00349](../tasks/00349-TASK.md) | Persist input and fence original attempts | ready-for-agent |
| [TASK-00350](../tasks/00350-TASK.md) | Open and fence protected runtime sessions | needs-info |
| [TASK-00351](../tasks/00351-TASK.md) | Execute admitted turns through one protected runtime | ready-for-agent |
| [TASK-00352](../tasks/00352-TASK.md) | Attach and inspect the same conversation | ready-for-agent |
| [TASK-00353](../tasks/00353-TASK.md) | Stop and recover original conversation work | ready-for-agent |
| [TASK-00354](../tasks/00354-TASK.md) | Retain and narrowly redact protected conversation content | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved private-production reconciliation — 2026-10-24

The rollout's per-user turn binding and conversation usage query are allocated to this existing conversation owner,
with TASK-00352's bounded query extension consuming TASK-00344/00345 through the existing invocation/attempt path.
TICKET-00089 retains connections; TICKET-00092 retains UI. TASK-00350's actual protected runtime/broker/storage
mapping remains `needs-info`; a hosted application and persisted conversation do not supply that runtime.
No new session/ledger/Workflow or TASK is created. Historical decomposition receipts remain historical.
See the [reconciliation receipt](../wayfinder/research/private-production-owner-reconciliation.md).

### Approved TASK decomposition and current handoff

The maintainer approved seven independently reviewable slices in the existing isolated planning worktree. Early
identity, attempt and host contracts stay independent of completed provider-driven turns and final continuation.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00348](../tasks/00348-TASK.md) | Explicit bounded assignment and stable conversation identity without launch |
| B | [TASK-00349](../tasks/00349-TASK.md) | Durable input/original attempts, single-owner fences and early cancellation/recovery state participants |
| C | [TASK-00350](../tasks/00350-TASK.md) | Actual protected runtime/storage opening and session/host acting-window adapter |
| D | [TASK-00351](../tasks/00351-TASK.md) | One production protected turn invocation path for independent provider integration |
| E | [TASK-00352](../tasks/00352-TASK.md) | Same-conversation terminal/API attachment and owner-first history resynchronization |
| F | [TASK-00353](../tasks/00353-TASK.md) | Actual stop/background-work/checkpoint/original-effect recovery and lineage primitives |
| G | [TASK-00354](../tasks/00354-TASK.md) | Protected retention/holds, eligible cleanup and narrowly authorized redaction |

TASK-00348 consumes TASK-00339/00253/00258/00234; TASK-00349 consumes TASK-00348/00340/00341. TASK-00350 consumes
TASK-00349/00321/00138 and remains `needs-info` for precise reusable protected host/broker and operational storage/
process participants under existing execution owners. TICKET-00093 now maps TASK-00368–00373's Runner operation,
not the missing protected host/broker implementation; enrollment/capacity or completed consumers do not clear it.
TASK-00138's qualification is not an
implemented runtime. Do not replace the missing narrow contract with completed Workflow/Runner/role journeys or
move sandbox/provisioner ownership here.

TASK-00351 consumes TASK-00349/00350/00244/00336/00344/00345, not completed TASK-00346/00347. TASK-00352 consumes
TASK-00351. TASK-00353 consumes TASK-00351/00164/00168, and TASK-00354 consumes TASK-00352/00353/00167. The six
non-host TASKs are `ready-for-agent` behind unfinished dependencies, not claims of actual sessions. Selected-provider
live acceptance for reconnect/recovery uses a qualified TASK-00346 or TASK-00347 path, never mandatory setup of both.
All new orders remain unranked; the terminal-first Board priority and active TASK-00138 stay unchanged.

TASK-00340 gains TASK-00348, and TASK-00344/00345 gain TASK-00349. Those three sole supplier-mapping holds are resolved
and their statuses become `ready-for-agent` behind unfinished dependencies. TASK-00244 gains early TASK-00348/00349/
00350, not completed TASK-00351. TASK-00336 gains TASK-00350, not its completed invocation consumer. TASK-00346/00347
gain TASK-00351. TASK-00342 gains TASK-00351/00352/00353 and retains explicit snapshot-change/linked-successor
integration. These consumers retain `needs-info` for TASK-00350's unresolved operational host/broker participants
and applicable Team/creation/current-authority composition. Actual provider/runtime qualification is still required.

TASK-00250 retains role integration and contribution holds; TASK-00247/00266 retain Explorer/Project Manager
outcomes. TASK-00325 consumes continuation for terminal switching; TASK-00302/00304 consume actual protected
proposal/custom-manual use through the existing owners. Their remaining scoped-read/Triage, proposal/browser,
Feature/publication and contribution holds are not cleared. No final consumer reverse-blocks the primitives.
TICKET-00091 now maps TASK-00355 early proposal admission/result/recovery, TASK-00356 human answers/operation
boundaries and TASK-00357–00360 typed candidates, human Apply, atomic decomposition and reviewed reconciliation.
TASK-00302 consumes TASK-00355 independently of Planning cutover; TASK-00266 consumes TASK-00360 while retaining
TICKET-00092's browser capabilities, now mapped to TASK-00361–00367. TASK-00266 gains TASK-00362–00367, resolving
browser allocation but retaining host/authority/role and TASK-00364 prototype-execution participant holds.
Shared conversation/provider suppliers never depend on those completed consumers.
TICKET-00094 now maps TASK-00374–00380's evidence/Artifact lifecycle. TASK-00379 consumes TASK-00354's protected
Pi retention seam and early Artifact/reference participants; TASK-00380 adds cross-copy redaction, not another
transcript store. These completed consumers never reverse-block early Pi lifecycle; actual participation and
storage/backup coverage are required before destructive operations. TICKET-00095 now maps TASK-00384's web-evidence
recovery consumer of TASK-00353: current exact-content screening is required at supported resumed-context ingress,
without a reverse dependency or blanket web requirement on local-only recovery. Host/broker and authority holds persist.

Pi Durable evaluation belongs inside TASK-00350 and actual integration/recovery, not a proof-only TASK. Pin exact
source/runtime/extensions/storage and record concrete gaps before an alternative. Preserve the separately approved
Pi 1.1.0 local checkpoint/compaction/retention scope of TASK-00164/00168/00167; managed compatibility needs fresh
qualification, not a silent upgrade or duplicated policy. Runtime durability does not execute while powered off,
renew approval, prove external exactly-once effects or grant cross-machine migration.

This decomposition is complete, not runtime delivery. Following TICKET-00091–00099's approved proposal/browser/Runner/evidence/web-research/history/sessions/measurement/catalog splits,
continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve Markdown Planning authority and all unresolved owner
holds. No live assignment, process, credential, provider call, transcript read or cleanup is authorized by planning.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving all prior approved
uncommitted planning changes. Live and archived TASK identities were rechecked before allocating TASK-00348–00354;
the archive contained no TASK records. `./bin/planning-check --write` and `./bin/planning-check` passed with
481 records, 389 active and current generated views. Tracked `git diff --check` and all 46 new/untracked TASK
whitespace checks passed, including these seven records. Validation reported no dependency cycles or invalid
planning links. Existing Board priorities and active TASK-00138 remain unchanged; all new orders are unranked.

These are planning receipts, not actual runtime, provider, host, checkpoint or retention qualification. The full
`./bin/build` was not run and remains required before commit/publication. No implementation, package adoption,
assignment/session launch, credential inspection, provider call, transcript read, cleanup, commit, push, PR or
merge was performed. Changes remain uncommitted in the isolated worktree; independent review and delivery are
separate. Concurrent Jev/Harness/build/planning changes observed in the main checkout were left untouched, not
claimed clean or included in this planning scope.

### Original requirement approval and prerequisite requests

The requests below describe the earlier handoff; the approved mappings above supersede its allocation gaps,
without claiming operational qualification.

Approved as area 6 of the remaining eight-TICKET EPIC-00006 decomposition. Applies
[WF-012](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md),
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md),
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md)/
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consumes early TICKET-00085–89 contracts, TICKET-00061's per-use authority and actual qualified host owners;
manual assignments do not wait for complete browser Planning. Supplies session/manual-admission/recovery primitives
to TASK-00250 and TASK-00304, and delegated execution to TICKET-00091/TASK-00302. Do not reverse-block these
primitives on final role/custom-template journeys, complete Workflow delivery or database Planning cutover.
TICKET-00088 now maps TASK-00339–00342: early exact resolution, retained binding, currentness guard and final
runtime/continuation integration. TASK-00340 remains `needs-info` for this TICKET's precise early authorized
session-identity/intent and binding transaction contract. Map that independently of completed protected execution,
TASK-00244's broker, TASK-00336's loader and TASK-00342's final continuation. Early assignment/identity facts may
consume TASK-00339; final admission/activation consumes TASK-00340/00341 with live authority. A persisted binding
is not a grant or a running session, and an arbitrary supplied identity cannot authorize it.

TASK-00342 separately needs this TICKET's actual protected runtime/continuation/recovery participants. Those
primitives may consume the early snapshot contracts but cannot depend on the completed consumer integration.
TASK-00244/00336 retain their non-snapshot grant/host/acting-window holds.

TICKET-00089 now maps TASK-00343's early provider/model catalog, TASK-00344's disclosure/budget admission,
TASK-00345's original-outcome/accounting and independent TASK-00346/00347 OpenRouter/Codex paths. This TICKET must
supply exact early authorized assignment/submission/attempt and shared-transaction participants to TASK-00344;
original-attempt/cancellation/current-owner/recovery participants to TASK-00345; and protected invocation/host plus
early real Planning consumption seams to TASK-00346/00347. Separate those primitives from completed provider-driven
execution. They cannot depend on completed live adapters or final TASK-00342/00250/00302/00304 integration. Final
execution uses the selected qualified provider with current admission; no fixture proves a live route and no
provider is mandatory when the other is configured. Neither snapshot/catalog facts nor persisted intent is a grant.

The approved split above now maps these session/attempt/invocation owners. TASK-00350's exact operational host/
broker participant gap and actual runtime compatibility remain unqualified, with applicable consumer holds intact.
The accepted terminal-first Board milestone and existing Jev TASKs are unchanged.
