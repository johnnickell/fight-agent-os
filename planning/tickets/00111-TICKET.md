---
id: TICKET-00111
epic: EPIC-00019
title: Explain application activity and failures with useful diagnostics
status: ready-for-agent
---

# Explain application activity and failures with useful diagnostics

## Problem statement

An operator cannot explain a failed operation or managed run when expected failures, nested activity or ordinary
DEBUG records disappear, exceptions lack useful detail, or unrelated worker context is attached to an event.
Diagnostic reporting must not change existing public responses or become another execution authority.

## Solution and boundaries

Deliver the producer side of [EPIC-00019](../epics/00019-EPIC.md), consuming
[WF-036](../wayfinder/tickets/WF-036-select-initial-log-sources-and-diagnosis-journeys.md) and
[WF-037's diagnostic contract](../wayfinder/tickets/WF-037-define-redacted-diagnostics-and-shared-http-adoption.md#diagnostic-contract).
Use ordinary Monolog native records, all eight standard levels and all application channels, with useful context,
processor extra, emitted exception type/message/file/line/frames/chained causes and release identity where available.
No ordinary null sink, sampling, severity threshold or channel gap may discard included records. Native normalization
is not whole-record byte enforcement; cooperate with the collector's qualified reversible framing rather than
silently truncate stored originals. SQL statement logging is not required.

Observe all application command/query invocations and event dispatch, including nested/non-HTTP/worker paths and
events without subscribers. Record meaningful started/completed/failed/retry/replay distinctions and actual available
operation/correlation/Workflow/TASK/run/process identities. Handler return is not proof of transaction commit or
publication. Use the existing shared bus/service graph; do not build a parallel dispatcher or telemetry protocol.
Reset worker/request context, retain provenance, and never infer unavailable lineage from matching text or time.

Keep one error-reporting owner per actual HTTP/worker/process boundary without deleting distinct observations of
the same failure. Preserve current Common-backed public status, envelope, message, correlation and cache behavior
under [TICKET-00010](00010-TICKET.md) and TASK-00020. Do not adopt incompatible opt-in safe middleware/reporter solely
for logging. Producer-side secret avoidance is required: no deliberate credential emission, unrestricted payload
or object dumps, stack arguments or locals. Preserve already emitted content without pipeline redaction; this does
not weaken existing credential/audit/evidence owners or authorize transcripts/prompts/hidden reasoning.

On formatter/handler/sink failure, isolate damage, avoid recursion and retain as much independent operation as
possible. Report bounded stage/reason/uncertainty through the independent status path, never raw failed payloads.
Do not retry business operations to repair logs or weaken mandatory evidence/audit failure semantics. Runtime capture,
destination durability, browser access and retention belong to the other accepted TICKETs, not to logger success.

Exclude a new Workflow ledger, distributed tracing, error grouping, browser JavaScript-error capture and registered-
project runtime instrumentation. Managed check/run output is included through its actual runtime producers, not
conversation extraction; [TICKET-00096](00096-TICKET.md)'s sanitized history remains separate.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Explain ordinary application activity | Existing command/query dispatch; no new business command just for logging | Existing metadata needed for attributable context | Observations of actual invocation/dispatch, not new authoritative Workflow events | Native diagnostic observations for every accepted path/level |
| Explain expected or unexpected failure | Owning boundary reports the actual failure once | Actual exception/cause and permitted context, not object snapshots | Diagnostic failure observation; no invented commit/publication fact | Useful private diagnostics with unchanged public/business outcome |
| Reuse a worker safely | Existing worker lifecycle reset | Current request/attempt identity and known provenance | Actual lifecycle observations only | No context from a previous operation leaks into a later record |
| Survive diagnostic failure | Existing boundary isolation; never replay business work | Independent status and known stage/uncertainty | Bounded non-recursive diagnostic status | Honest degraded coverage without treating log delivery as business acceptance |

## Validation and permissions

This producer contract does not add a browser read or operator grant. Context comes from the actual trusted boundary;
producer-controlled metadata cannot authorize disclosure or replace runtime source identity. Existing execution and
credential operations retain their own current permissions and audit. Logs are installation-private, with access
owned by [TICKET-00113](00113-TICKET.md). Missing identity remains unknown; values use owning ID factories/contracts.

Respect [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md): observation of an invocation is not an owned
committed event, and logging must not introduce nested transactions or external effects inside business transactions.
Native formatting must preserve declared record/exception meaning and expose incomplete encoding honestly.

## Acceptance and evidence

- Actual HTTP, console/worker and nested bus use cases emit included ordinary activity and all eight actual Monolog
  levels/channels, including expected failures and events with no subscribers. No current general null sink remains
  on an included production path.
- Useful real exception/cause/stack/release details and available identities survive capture; missing data is explicit,
  and current public HTTP response contracts remain unchanged.
- Distinct boundary observations remain attributable without duplicate reporting by the same owner. Worker reset,
  retry/replay and postcommit failure cases cannot fabricate transaction success or stale lineage.
- Owned behavior tests exercise malformed/large data, producer secret avoidance, formatter/handler/sink failure and
  non-recursive status, without forcing unrelated business failures or claiming all shared faults are survivable.
- Qualify actual output encoding with TICKET-00112; use direct owning tools for configuration/infrastructure, not
  product tests of wiring or quality tools. Full `./bin/build` remains mandatory on implementation.

## Coordination

Supply native diagnostic content/context to [TICKET-00112](00112-TICKET.md) independently of completed Logs UI.
TICKET-00112 owns source generations, framing/checkpoints and independent status; TICKET-00113 consumes diagnostics
for diagnosis. Missing managed-runtime producers remain explicit dependencies under their existing execution owners,
not permission to claim complete source coverage. Do not reverse-block existing terminal/evidence work.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00451](../tasks/00451-TASK.md) | Emit useful native diagnostics without disrupting operations | needs-info |
| [TASK-00452](../tasks/00452-TASK.md) | Explain HTTP failures without changing public responses | ready-for-agent |
| [TASK-00453](../tasks/00453-TASK.md) | Observe every command query and event dispatch | ready-for-agent |
| [TASK-00454](../tasks/00454-TASK.md) | Attribute console and worker failures to the correct operation | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement group in EPIC-00019's seven-TICKET split. At that requirement checkpoint no TASKs
were allocated. All inherited exclusions and qualification requirements remain in force; accepted planning is not
implemented diagnostics or complete runtime qualification.

### Approved TASK decomposition and current handoff

John separately invoked `to-tasks TICKET-00111` in the observability worktree and answered **“approved”** to the
four-outcome proposal. These four TASKs are written/accepted and cover this TICKET's complete agreed producer scope:

| Slice | TASK / delivered outcome | Dependencies and current gate |
|---|---|---|
| A | [TASK-00451 — Emit useful native diagnostics without disrupting operations](../tasks/00451-TASK.md) | `needs-info`: explicit TASK-00455/00456 suppliers now map early capture/framing and independent failure-status; actual contracts/qualification still required, not whole-pipeline completion |
| B | [TASK-00452 — Explain HTTP failures without changing public responses](../tasks/00452-TASK.md) | Decision-complete, blocked by TASK-00451; completed HTTP foundations remain in force |
| C | [TASK-00453 — Observe every command query and event dispatch](../tasks/00453-TASK.md) | Decision-complete, blocked by TASK-00451; uses existing Common seams/shared graph |
| D | [TASK-00454 — Attribute console and worker failures to the correct operation](../tasks/00454-TASK.md) | `needs-info`, blocked by TASK-00451/00453 plus unresolved concrete managed-runtime context/lifetime supplier under TICKET-00032 |

A supplies native record/context/failure handling and producer-to-capture compatibility; B owns public HTTP parity
and expected/direct/unexpected failure diagnostics; C owns complete bus/event observations and nested restoration;
D owns actual console/worker/run attribution, job reset and recovery compatibility. Each owns relevant security,
failure tests and full build evidence. There is no separate proof-only TASK or deferred parent-only acceptance gap.
Native output does not certify collection/indexing/archives; a fake worker cannot discharge D's actual runtime gate.

Both information gates retain concrete capability owners. TICKET-00112's later accepted split maps TASK-00455/00456
as TASK-00451's early suppliers without waiting for completed producer/browser work; its actual qualification gate
remains. Managed execution owners keep terminal-first priority and gain no observability prerequisite. All four
producer TASKs remain unranked; the sole dependency update is TASK-00451's explicit supplier edges, not a status,
scope or execution-priority change.

Fetched `origin/develop` and this worktree HEAD remained `ac41c28`. Before allocation, live/archive paths across 18
available local/remote refs and all four registered worktrees had maximum TASK-00450; no archived TASK records were
present. Only TASK-00451–00454 were allocated here. Recheck before subsequent allocation/publication; this inventory
is not a reservation. No source, service, permission, dependency, experiment or implementation changed.

**Phase:** all seven TICKET-00111–00117 TASK decompositions are complete/accepted. No map planning phase remains;
implementation/review/qualification are unclaimed. Existing execution-owner inputs in TASK-00454 remain gated;
allocating installation suppliers does not manufacture managed-run context or lifetime participation.

### Producer-decomposition planning verification — historical checkpoint

`./bin/planning-check --write` and `./bin/planning-check` passed with **590 records, 485 active**, generated views
current. Tracked and explicit untracked planning Markdown whitespace checks passed. The Board diff adds only two
Waiting and two Needs Info rows; existing active/ready work and execution priority are unchanged. Scope checks
confirmed exactly four new TASKs, the correct parent/unranked metadata, no tracked prior TASK/TICKET source edits
and no matching new TASK IDs in other registered worktrees. These are planning checks, not application evidence.

Full `./bin/build`, runtime qualification, implementation, review, commit and publication were not performed.
Changes remain uncommitted in the approved worktree; full build remains required before commit/publication.

Next: `/skill:next` in `.runs/worktrees/observability-planning/`, following
[TICKET-00117's completed planning handoff](00117-TICKET.md#approved-task-decomposition-and-current-handoff).
Consult the Board read-only; this recommendation starts no implementation and waives no producer gates.
