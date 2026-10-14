---
id: TICKET-00096
epic: EPIC-00007
title: Expose trustworthy Workflow history and live state
status: ready-for-agent
---

# Expose trustworthy Workflow history and live state

## Problem statement

Operators need durable, attributable execution history without confusing incomplete tool telemetry or a live UI
message with Workflow authority. Recovery and user decisions cannot depend on an uninterrupted observation stream.

## Solution and boundaries

Consume the existing event-sourced Workflow's compact semantic events, exact phase/process/session identities and
verified evidence. TICKET-00032 retains authoritative Commands, expected stream versions, exact command-result
idempotency, grants/claims/leases, transactional dispatch and handoff decisions through Awaiting review. Use the
qualified Fight Common event-store/projector contracts and application metadata rather than another event protocol
or importing raw Pi JSONL. Preserve actual actor, Command, causation/correlation and delegated-user provenance.

Capture selected sanitized operational activity in a separately identified application-owned observation ledger.
Relate facts to actual Workflow, PhaseAttempt, ProcessAttempt, role/Agent/conversation, tool or external-operation
identities. Record available producer/receipt times, safe intent/target summary, start/end, duration, outcome/exit,
failure classification and permission-filtered evidence references. Redact before persistence and projection;
arguments, shell commands, paths, environment, provider payloads and output are not intrinsically safe. Omit unsafe
fields; bounded safe output lives in separately authorized Artifacts, not unbounded observation bodies.

Do not ingest, index, search or expose raw prompts, assistant/model responses, compiled instructions, thinking,
provider signatures, transcripts, message excerpts or native JSONL as execution history. Protected Harness/Pi
conversation reconstruction remains TICKET-00090 and its existing checkpoint owners. Execution observation may
show authorized lineage/lifecycle metadata only; Planning conversation content remains in its owner-first journey.

Observations may be duplicated, missing, delayed, reordered or purged. Deduplicate where a stable producer identity
exists, retain provenance and explicit incomplete/unknown state, and never invent a missing event or infer that no
tool ran. No custom global observation sequence, spool, telemetry service or complete-transcript reconstruction is
required. Recovery uses authoritative state and reconciled evidence even when every optional observation is absent.

Build idempotent rebuildable PostgreSQL Workflow list/detail, phase/activity, evidence-link and live-summary views
under at-least-once delivery. Keep authoritative decisions visually and structurally distinct from observations.
Integrate TICKET-00098's measured usage/time/forecast facts with their own freshness/provenance rather than summing
raw streaming deltas here. Projection progress/failure is not aggregate truth; expose stale/partial/unavailable
states and do not reconstruct authoritative transitions from telemetry.

Provide bounded server queries for safe repository-scoped Workflow metadata search, stable cursor history and
selected-phase drill-down. Search safe TASK/title, Workflow/Repository/status and actual PR-reference metadata
across the entire selected Current or Archived result, never just a rendered page and never private content.
Start a new stable result on changed search/archive/explicit refresh, ordered by last meaningful activity and
Workflow identity. Preserve every accessible history item through pagination rather than arbitrary silent caps.

Publish only committed projection updates through authorized Mercure delivery. Reconnecting clients reload durable
queries before following live changes; lost, repeated or stale notifications cannot change authority or overwrite
newer client context. Apply current read access to subscription and reload; a private topic or cached projection is
not a permanent grant. Retention/redaction uses TICKET-00094's categories and safe tombstones across derived views.

Exclude another Workflow event store/state machine, forecast authority, raw transcript search, generic observability
platform, browser component implementation and managed Review/publication behavior. Later phases extend this same
history through their owners; absence of a Review is not approval or a synthetic verdict.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record safe activity | Admit bounded sanitized observation with actual producer identity | Current provenance, scope, known identity and retention category | Non-authoritative operational observations | Safe ledger entry, never Workflow advancement |
| Project/rebuild committed state | Owning projection processing/rebuild operation | Authoritative events, prior projection position and exact evidence | Committed projection notifications, not new Domain decisions | Idempotent derived views; source authority unchanged |
| Inspect Workflow/phase history | N/A — read-only | Bounded safe list/search/detail/activity queries | N/A — reading cannot repair or transition work | Authorized exact state plus explicitly partial observations |
| Follow live updates | Authorized subscribe/reconnect; no business mutation | Durable current projection and freshness before following updates | Committed safe projection notifications | Best-effort timely visibility, never authority from transport |
| Apply required redaction/retention | Existing owned redaction/cleanup operation | Applicable holds, affected derived data and tombstones | Existing sanitized lifecycle facts | No prohibited copies left intentionally exposed through projections |

## Validation and permissions

Require current intent-specific Workflow/Repository/evidence visibility and actual authenticated producer scope.
Validate typed IDs, parentage, bounded safe fields, schema/version and expected currentness without accepting a
model-supplied identity as provenance. State/telemetry timestamps do not authorize a transition. Keep shared
transaction and required audit boundaries under ADR 0002; notifications occur only after projection commit.

Bound query/filter/cursor and event payload sizes, enforce stable pagination and access for each result/evidence
link, and reject cross-scope or stale cursor use safely. UI/query caches cannot leak previously authorized private
data after revocation. Live updates must be safe for the authenticated audience; arbitrary payloads and secrets do
not cross Mercure. Status queries cannot launch replay or external work as a read side effect.

## Acceptance and evidence

- Real execution exposes durable semantic state and separately labelled sanitized observations, including selected
  phase/Agent parentage and tool metadata, without transcripts or excerpts in any supported history/search route.
- Duplicate/reordered/missing observations, projector interruption and rebuild preserve correct source authority
  and honest partial/freshness states; safe history remains accessible beyond a single page.
- Whole-result metadata search and cursor changes behave consistently; cross-Repository/read revocation and
  redaction remain enforced in queries, cached views, evidence links and live delivery.
- Mercure loss/reconnect reloads committed durable state; old notifications cannot manufacture a transition or
  revive retired client authority. Existing execution recovery still works without optional telemetry.
- Owned projector/authorization/query/recovery tests, actual producer/live-delivery evidence and `./bin/build` pass;
  no tests of Messenger/Mercure internals, generated views, wiring or a seeded-failure monitoring demo.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00385](../tasks/00385-TASK.md) | Inspect rebuildable authoritative Workflow history | ready-for-agent |
| [TASK-00386](../tasks/00386-TASK.md) | Capture and inspect sanitized phase activity | ready-for-agent |
| [TASK-00387](../tasks/00387-TASK.md) | Search stable history and scope-labelled summaries | ready-for-agent |
| [TASK-00388](../tasks/00388-TASK.md) | Retain and redact history across derived copies | ready-for-agent |
| [TASK-00389](../tasks/00389-TASK.md) | Follow committed Workflow updates through Mercure | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved five bounded implementation slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. Each delivers a real inspection, activity, search, privacy or live-following outcome
with qualification and guidance inside delivery, not a proof-only TASK.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00385](../tasks/00385-TASK.md) | Rebuildable committed semantic Workflow projections and protected API/console history |
| B | [TASK-00386](../tasks/00386-TASK.md) | Actual sanitized phase/tool observations and protected evidence references |
| C | [TASK-00387](../tasks/00387-TASK.md) | Whole-result stable Current/Archived search, phase detail and scope-labelled summaries |
| D | [TASK-00388](../tasks/00388-TASK.md) | Actual history-copy participation in existing retention/redaction and fenced rebuild |
| E | [TASK-00389](../tasks/00389-TASK.md) | Committed Mercure delivery, authorized reload-first client transport and narrow hub qualification |

TASK-00385 consumes TASK-00139 and TASK-00117. Independent TASK-00386 consumes TASK-00141 and TASK-00378.
TASK-00387 consumes both early history/activity slices, TASK-00388 consumes TASK-00387 and TASK-00380, and
TASK-00389 consumes TASK-00388 and TASK-00049. All five are feature records with blank order/pr, now
`ready-for-agent` behind unfinished suppliers, not operationally ready.

TICKET-00097 maps TASK-00390's early revision-checked visibility/current-membership and independent-running safeguard.
TICKET-00098 now adds TASK-00396 attributable usage and TASK-00397 known timing, both with actual source/projection
integration. TASK-00387 gains these two blockers and its remaining allocation hold is resolved. Early suppliers
consume original accounting/history/producer contracts, never completed TASK-00387, final Dashboard, quota or
forecast. Actual participant qualification remains required; TASK-00399's separate quota-source/refresh hold blocks
none of this history. Core history does not wait on completed presentation or estimates.

TASK-00139–00142 retain Start, execution, handoff and recovery ownership. Only semantic Commands change Workflow
state; observations, projection positions and Mercure messages cannot infer success or approval. A/B remain independent,
and actual producer evidence belongs within the ordinary real TASK path rather than a second certification run.
Use qualified Common store/projector contracts, actual actor/Command/causation provenance, canonical PostgreSQL
transactions and honest incomplete/freshness states. No raw Pi JSONL, prompts, assistant/model responses, thinking,
compiled instructions, provider signatures or message excerpts enter history/search.

TASK-00378 retains evidence access, TASK-00379/00380 retain lifecycle policy and original operations, and TASK-00388
adds actual history-copy/reference/hold/restriction participation. Safe admission and current disclosure apply in
A/B/C from inception, not after D. Unknown coverage preserves content or keeps it denied and blocks affected cleanup/
complete-removal claims. Replay, restore and late publication cannot resurrect restricted payloads; archive and
Awaiting review never mean terminal cleanup. Query/status reads never rebuild or invoke external providers.

TASK-00389 uses TASK-00049's sole production principal/cache owner and a bounded nonvisual client transport;
TICKET-00097 retains components and actual browser visual/accessibility delivery. Live notifications follow projection
commit and reconnect reloads durable current queries. The inherited MockHub fallback is not live qualification.
Deliver the narrow protected Mercure addition within TASK-00072/TICKET-00023's single installation boundary, not a
second stack or its complete browser prerequisite chain. Existing installation work reuses that addition.

No existing TASK gains a new history/live blocker. Preserve independent host/prototype, current-authority,
role/source/session/base-inclusion and provider qualification holds; observation history resolves none of them.
This split requires no web call and no Planning cutover. All orders remain unranked; active TASK-00138 and the
terminal-first milestone retain priority.

TICKET-00097 now maps TASK-00391's actual Dashboard/live consumer, TASK-00392/00393 independent evidence/activity,
TASK-00394 existing intervention and TASK-00395 Start/Pending continuity. These consume existing owners, never
reverse-block early history or grant execution authority through browser state.

TASK-00398/00399/00400 now map measurement explanations, independently qualified allowance and forecasts/comparisons
through the same protected query/copy/live owners. New copies must participate before exposure; none creates a
second accounting/projection channel or read-triggered provider effect.

This decomposition is complete, not runtime implementation. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Implementation checkout, runtime/credential/destructive authority,
full build, independent review and publication remain separate. Markdown stays authoritative until verified cutover.

### Historical planning verification for the history/live split

The receipt below predates visibility/measurement allocation; latest planning verification is in TICKET-00109.

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
uncommitted planning. Before allocation, the highest live TASK was TASK-00384 and the archive contained no TASK
records. TASK-00385–00389 match the approved metadata/dependencies and template sections; all are unranked features
with blank PRs, four ready behind unfinished suppliers and TASK-00387 `needs-info` for its two exact allocation gaps.

`./bin/planning-check --write` and `./bin/planning-check` passed with 516 records, 424 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 81 new/untracked TASK
whitespace checks passed, including these five. Explicit checks preserved independent consumer/host/prototype
`needs-info` holds, unchanged TASK-00138/00139/00142/00159/00184/00226 records against this worktree's HEAD and
existing producer priority/status. TASK-00049/00072/00171 keep their existing dependency metadata; TASK-00140/00141/
00378/00380 retain their previously approved blockers. No terminal producer gains a history/live dependency.
Active TASK-00138 remained the only active Board item, order 2. That split advanced handoffs to TICKET-00097, with
TICKET-00098–00109 still to decompose at that point; no stale current TICKET-00096 command handoff remained.

These are planning receipts, not fresh source/projector/observation, PostgreSQL privacy/rebuild, Mercure, browser,
provider or runtime qualification. Full `./bin/build` was not run and remains required before commit/publication.
All changes remain uncommitted; independent review and publication are separate. No implementation, observation
capture, private history/session read, rebuild, visibility mutation, provider call, hub/subscription/credential
operation, cleanup, live Planning mutation, cutover, commit, push, PR or merge occurred. Unrelated main-checkout work
was left untouched, not claimed clean. Earlier verification receipts remain historical.

### Original requirement approval and supplier handoff

The earlier mapping requests below are resolved only to the extent explicitly recorded above; independent holds persist.

Approved as area 4 of EPIC-00007's remaining six-TICKET split. Applies
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md),
[WF-018](../wayfinder/tickets/WF-018-prototype-sessions-first-browser-experience.md), the EPIC's accepted phase-detail
amendment, [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume early TICKET-00032/00093 producers and TICKET-00094 evidence, not their completed end-to-end acceptance.
[TICKET-00097](00097-TICKET.md) owns browser presentation and archive/restore visibility operations;
[TICKET-00098](00098-TICKET.md) owns measured accounting/forecast semantics. Define projection/visibility contracts
without whole-TICKET cycles; forecasts and final browser integration cannot reverse-block core history. Existing
TASKs and supplier holds remain unchanged; no observation capture, subscription or runtime is enabled by planning.

TICKET-00094 now allocates TASK-00374's private Artifact/reference/hold participants, TASK-00375 contribution facts,
TASK-00376/00377 exact subjects/receipts, TASK-00378 authorized reads and TASK-00379/00380 lifecycle/redaction.
Consume the precise early contracts needed here; do not reverse-block them on completed history. Actual observation,
projection/index/export writers must participate in reference/hold and restriction/tombstone fencing before exposure
or cleanup, including replay/rebuild and late publication. Missing coverage is unknown, not proof of deletion.
This maps evidence owners only; current-authority, host and other independent operational holds remain.
