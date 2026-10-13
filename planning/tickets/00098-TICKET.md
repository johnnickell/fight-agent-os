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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

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
