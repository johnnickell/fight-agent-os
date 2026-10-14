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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

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
