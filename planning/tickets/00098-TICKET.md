---
id: TICKET-00098
epic: EPIC-00007
title: Explain execution usage and completion estimates
status: ready-for-agent
---

# Explain execution usage and completion estimates

## Problem statement

Humans need to understand recorded cost, elapsed work and likely remaining time without mistaking missing usage
for zero, shared account capacity for a repository budget, or an Agent's guess for measured completion evidence.

## Solution and boundaries

Consume TICKET-00089's qualified protected provider facts and actual Workflow/phase/process boundaries to retain
attributable execution usage. Record provider, requested/reported model, Harness/profile/context and Pi versions,
available response/session identity, input/output/cache/reasoning/token quantities, currency and cost where actually
reported. Identify facts so cumulative streaming reports are not repeatedly summed. Attribute compaction, nested
tool-model calls and cache activity separately when exposed. Keep provenance and explicit unavailable/partial states;
never invent pricing or convert absence into zero. Hidden reasoning content is not usage metadata.

Separate wall elapsed, known active work, queue time, interruption and human wait using actual known boundaries.
Retain ambiguity and incomplete intervals rather than infer exact duration from missing heartbeats/observations.
Repeated/delayed events and restart must not double count, erase consumed work or fabricate a continuous run.
Observed account/billing uncertainty remains unknown, not proof of refunded or released budget.

Provide permission-filtered repository/Workflow usage summaries, and separately expose provider/account/credential-key
allowance or quota only through an actually qualified safe provider source. Label source, broader scope, report time,
reset/window and partial/unavailable state. A shared quota is not the selected Repository's balance. Do not infer
remaining capacity from local usage, scrape credentials/account pages or display key material. Absent qualified quota
support remains visibly unavailable and does not block useful execution/usage presentation.

Show an updating remaining-time range through Awaiting review with confidence, last update and a concise reason
for material changes. Use available phase/queue state, attempts/remaining limits, observed activity, failures/retries,
comparable historical phase durations and explicit broad defaults while data is sparse. Record basis/provenance and
qualify initial cohorts, defaults and confidence semantics during TASK implementation; no unsupported statistical
precision or fabricated percentage. The range may increase as facts change.

Show Waiting for human, Paused or Unable to estimate when appropriate. Separate estimated active work from queue
and human wait; no arbitrary duration assigned to an unresolved human decision. A range is advice, not a deadline,
permission to continue or reason to alter Workflow status. Model self-report is not measurement. Missing telemetry
cannot imply completion or manufacture a new estimate without sufficient evidence.

Retain actual-versus-predicted duration and historical prediction identity/basis for later calibration, distinguishing
changed scopes and incomplete/terminal outcomes. Expose enough safe detail to inspect why estimates changed without
creating a separate analytics platform or recurring certification ritual. Usage totals follow durable Workflow
history retention; bulky source observations follow their separate category/holds. Budget enforcement remains with
existing grant/provider/Workflow owners, which may consume exact measured facts but must treat unknowns safely.

Publish through TICKET-00096's rebuildable projections and TICKET-00097's independently loaded UI; no second live
channel or accounting store with competing authority. Basic usage facts must be available without completion of the
forecast or final Dashboard. EPIC-00008/TICKET-00083 later extends forecasting through PR handoff; it is not part
of this first slice and no Review outcome is predicted into authority.

Exclude billing/payment management, account enrollment, additional generative providers, quota claims without
qualified sources, cross-Repository private disclosure, automatic grant/budget extension, completion guarantees,
review/publication execution, a general metrics platform and a proof-only workload.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record known execution usage/time | Record original attributed fact or supported correction | Actual provider/attempt identity, cumulative semantics and known boundaries | Committed usage/time facts at their owner, not completion events | Durable honest accounting without double counting |
| Inspect scope-labelled usage/quota | N/A — read-only safe query | Authorized repository totals and independently qualified account/key allowance | Safe source observations when refreshed; no grant mutation | Measured/partial/unavailable facts with scope, freshness and reset window |
| Update a completion estimate | Bounded derived estimate update from current known facts | Phase/queue/attempt state, applicable history/defaults and current limits | Non-authoritative forecast observations | Inspectable remaining-time range or explicit waiting/unavailable state |
| Compare predictions with outcomes | N/A — read-only comparison | Retained prediction basis/time and actual known duration/outcome | N/A — comparison cannot alter execution or history | Evidence for later calibration with explicit missing/changed-scope limits |

## Validation and permissions

Require actual authenticated producer identity and current Workflow/Repository visibility for facts/results. Broader
provider/account scope requires its own safe disclosure authority; Repository access alone is not account inspection.
Validate bounded numeric quantities/units, currency, identity and reporting semantics; reject malformed or ambiguous
reports rather than coerce them into measured success. Corrections retain provenance, not silent historical overwrite.

Keep raw keys/account credentials, source bodies, prompts and hidden reasoning out of telemetry and browser output.
Use canonical persistence/audit contracts for owned facts and qualified external adapters outside transactions.
Forecast computation and result reads cannot launch models, poll arbitrary account endpoints or trigger Workflow
transitions. Provider refresh is bounded under current access and its declared cost/side effects, not an unreported
paid generation. Source failure leaves explicit stale/unavailable state without resetting consumed limits.

## Acceptance and evidence

- Real execution receipts retain requested/reported identity, actual usage/currency/cost and unavailable distinctions;
  cumulative/duplicate/reordered reports, interruptions and restart produce honest non-duplicated totals.
- Known time categories remain separate; gaps never become invented active duration. Repository rollups and any
  qualified provider/account/key quota display correct scope/freshness; missing quota remains unavailable.
- A real Workflow shows a qualified updating range through Awaiting review, including increases, human wait,
  pause, sparse history and insufficient evidence. Predictions cannot advance state, extend limits or clear holds.
- Historical prediction/outcome comparisons preserve original basis and incomplete cases rather than selectively
  claiming accuracy. Runtime evidence comes from ordinary useful work, not synthetic claims of measured savings.
- Owned accounting/forecast/projection behavior tests, actual qualified provider receipt checks, honest browser
  presentation evidence and `./bin/build` pass. No product tests of provider SDKs, reports, wiring or monitoring tools.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00396](../tasks/00396-TASK.md) | Inspect attributable execution usage | ready-for-agent |
| [TASK-00397](../tasks/00397-TASK.md) | Inspect known execution time | ready-for-agent |
| [TASK-00398](../tasks/00398-TASK.md) | Explain usage and time in Workflow detail | ready-for-agent |
| [TASK-00399](../tasks/00399-TASK.md) | Inspect independently scoped provider allowance | needs-info |
| [TASK-00400](../tasks/00400-TASK.md) | Explain remaining time through Awaiting review | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved five bounded slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are feature records with blank order/pr; A/B/C/E are `ready-for-agent` behind
unfinished suppliers, while D retains its precise source/refresh mapping hold. Planning is not runtime readiness.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00396](../tasks/00396-TASK.md) | TASK-00345, TASK-00385, TASK-00386 | Actual Workflow-attributed usage, original correction/cumulative semantics, protected API/console and projections |
| B | [TASK-00397](../tasks/00397-TASK.md) | TASK-00385, TASK-00386 | Known timing categories/intervals, honest gaps/overlaps, protected API/console and projections |
| C | [TASK-00398](../tasks/00398-TASK.md) | TASK-00391 | Independent attribution/timing drill-down in the existing Dashboard, consuming A/B transitively |
| D | [TASK-00399](../tasks/00399-TASK.md) | TASK-00391; exact source/refresh supplier remains unallocated | Separately authorized actual account/key allowance, bounded protected refresh and honest browser presentation |
| E | [TASK-00400](../tasks/00400-TASK.md) | TASK-00391 | Explainable ranges through Awaiting review, original prediction/outcome comparisons and existing live/UI delivery |

TASK-00396/00397 are the exact early measurement and projection-integration suppliers requested by TICKET-00096.
TASK-00387 gains both alongside existing TASK-00385/00386/00390 and becomes `ready-for-agent` behind unfinished
suppliers. Only its remaining allocation hold is resolved, not actual PostgreSQL, producer, provider, current-rights
or runtime qualification. These primitives consume original accounting/history/producer contracts, never completed
TASK-00387, final Dashboard, quota or forecasts. TASK-00345 retains request normalization/reservations and liabilities;
actual Workflow correlation belongs in TASK-00396 rather than a parallel accounting store.

TASK-00397 is independent of usage and provider accounts. Qualified boundaries, compatible clock domains and
per-attempt versus elapsed/overlap semantics establish known timing; heartbeat gaps, receipt order and missing pairs
do not prove continuous activity. Corrections remain attributable and replay cannot erase consumption. Both early
slices supply useful protected reads and actual projection integration rather than demonstration-only APIs.

TASK-00398 extends TASK-00391's basic metrics with independently loaded explanations; it does not replace the
Dashboard or duplicate summary policy. TASK-00400 independently delivers bounded forecast policy and inspectable
calibration evidence through that same production detail/live boundary. Initial cohorts/defaults/confidence and
update limits are qualified during implementation, not fabricated now. Waiting for human, Paused and Unable to
estimate remain explicit; no arbitrary human-decision duration, grant extension or authority-bearing prediction.

TASK-00320 gains TASK-00400 as its exact base measurement/forecast/query/UI supplier and becomes `ready-for-agent`
behind TASK-00316/00400. This resolves only its supplier-mapping hold. Actual later-phase measurement/composition
through PR handoff remains TASK-00320; base forecasting stops at Awaiting review and never reverse-depends on that
consumer. TASK-00319's independent Review-history mapping hold and all transitive host/source/authority holds remain.

**TASK-00399 remains `needs-info`.** Identify the supported provider/account/key source, precise reporting/reset/window
semantics and exact protected credential/refresh supplier before readiness. No future ID is invented and neither
OpenRouter nor Codex generation proves quota support. Only a mapped, actually qualified route may expose account
facts; no source means unavailable and continued hold, not completed integration. Repository reads do not grant
account disclosure. Ordinary reads never contact providers, inspect credentials or trigger generation. This optional
source blocks neither usage/time, history, forecasting nor terminal execution.

All actual measurement/prediction/allowance copies join existing retention/redaction/current-restriction boundaries;
durable totals and safe prediction basis remain history, separate from eligible bulky observations. No duplicate
projection/live channel, transcript ingestion, speculative analytics platform or proof-only TASK is introduced.
Every browser slice includes fresh keyboard/focus/announcement, responsive light/dark, accessibility and honest
stale/denied/unavailable/recovery evidence. Source qualification, owned tests, guidance and `./bin/build` stay inside
delivery; inherited receipts and fixtures are not fresh runtime or statistical-accuracy evidence.

This completes this TICKET's decomposition, not its delivery or the remaining map. Continue
`/skill:next` for Board-based selection after TICKET-00109's approved final split. All new orders stay
unranked; active TASK-00138 and terminal-first priorities remain unchanged. Preserve independent host/prototype/
provider/source/authority holds and Markdown Planning authority until separately verified cutover. Checkout and
finite operational/credential/provider authority, full build, independent review and publication remain separate.

### Planning verification

This historical measurement-split receipt predates TICKET-00099's catalog split; latest planning verification is
in TICKET-00109.

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
uncommitted planning. Before allocation, the highest live TASK was TASK-00395 and the archive contained no TASK
records. TASK-00396–00400 match the approved metadata/dependencies and all template sections; all are unranked
features with blank PRs, four ready behind unfinished suppliers and TASK-00399 `needs-info` for its exact safe
quota-source/protected-refresh mapping. No unknown future quota dependency is claimed verified.

TASK-00387 gains TASK-00396/00397 and TASK-00320 gains TASK-00400, resolving only their specified allocation holds.
Both become `ready-for-agent` behind unfinished suppliers, not operationally qualified. Explicit checks preserved
independent TASK-00445/00319, consumer/current-authority, host/prototype and provider `needs-info` holds; actual
runtime/source participation is still required. TASK-00138/00139/00142/00159/00184/00226 remain byte-for-byte unchanged
against this worktree's HEAD. Existing accounting, history, live, presentation and terminal/entry-owner dependency
metadata and priority were checked, including TASK-00345/00385/00386/00388/00389/00390/00391 and previously approved
TASK-00140/00141/00373/00378 blockers. No existing producer gains a metric, quota, forecast or browser dependency.

`./bin/planning-check --write` and `./bin/planning-check` passed with 527 records, 435 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 92 new/untracked TASK
whitespace checks passed, including these five. Active TASK-00138 remains the only active Board item, order 2.
That split advanced handoffs to TICKET-00099, with TICKET-00100–00109 still to decompose at that point; no stale
current TICKET-00098 command handoff remained. Earlier measurement/visibility/forecast allocation prose and receipts are labelled historical.

These are planning receipts, not fresh provider/producer, PostgreSQL accounting/timing, quota-source, forecast/
calibration, browser accessibility/visual or runtime qualification. Full `./bin/build` was not run and remains
required before commit/publication. All changes remain uncommitted; independent review/publication are separate.
No implementation, private read, measurement capture, accounting mutation, account/provider/credential/service
operation, forecast runtime, cleanup, live Planning mutation, cutover, commit, push, PR or merge occurred. Unrelated
main-checkout work was untouched, not claimed clean. Inherited runtime receipts and controlled fixtures are not fresh
application evidence, measured savings or statistical-accuracy claims.

### Earlier measurement allocation request and original requirement approval

The request below predates approved TASK-00396/00397 and TASK-00400. Its measurement/forecast allocations are now
mapped above; only TASK-00399's source/refresh allocation and independent qualification holds remain unresolved.

TICKET-00096 now maps TASK-00385 semantic projections, TASK-00386 actual safe activity, TASK-00387 stable search/
summaries, TASK-00388 history-copy lifecycle participation and TASK-00389 committed live transport. This TICKET keeps
measurement/cumulative semantics, time categories, forecast basis/calibration and qualified account/quota ownership.
No streaming observation sum or projection freshness becomes a measured accounting fact.

TICKET-00097 now allocates TASK-00390's early visibility/current-membership and independent-running safeguard;
TASK-00387 gains that blocker but remains `needs-info` pending this TICKET's precise early attributable usage/time
facts and projection integration supplier. TASK-00391 supplies the production sessions overview/Summary and shared
independent section/live composition; TASK-00392/00393 add exact evidence and sanitized Activity, TASK-00394 existing
controls and TASK-00395 Start/Pending continuity. This TICKET retains qualified usage/quota/forecast integration into
those surfaces, not another browser shell or live/accounting owner. During decomposition, map early measured facts
separately from completed forecasting, quota and final Dashboard. They may consume TASK-00385/00386 or actual
provider/attempt owners, but cannot depend on completed TASK-00387 or its downstream live consumer. Later forecasts
extend the same qualified projection path; absent quota/forecast remains unavailable, not a new basic-history gate.
Add exact TASK blockers only after approval, preserving source/host/authority holds and original usage uncertainty.
This handoff authorizes no provider/account inspection, measurement capture, new TASK or runtime effect.

Approved as area 6 of EPIC-00007's remaining six-TICKET split. Applies
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md),
[WF-019](../wayfinder/tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md), EPIC-00007's accepted scope-labelled
usage/quota amendment, [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume exact TICKET-00089 provider contracts and TICKET-00032/00093 authoritative phase/queue/attempt boundaries;
TICKET-00096 supplies safe observation/projection mechanics without owning another usage policy. Avoid whole-TICKET
cycles by keeping raw producer facts, accounting and projection adapters independently scoped. TICKET-00097 consumes
safe presentation facts; completed forecasts/browser journeys do not reverse-block the first terminal execution.
Exact TASK slices, numeric forecast policies, qualified quota sources and runtime evidence remain future work.
No provider inspection, forecast runtime, TASK, priority change or operational authority is created by this approval.
