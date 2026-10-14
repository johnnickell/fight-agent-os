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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

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
Exact TASK mappings, runtime compatibility and operational participants remain unqualified; existing needs-info
holds stay intact. The accepted terminal-first Board milestone and existing Jev TASKs are unchanged.
