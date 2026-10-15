# Wayfinder Map: Operational logs and failure diagnostics

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map only summarizes the linked resolutions and shows the next decision frontier.

## Destination

An authorized operator can open a failed operation or managed run in Agent OS, find its relevant logs, inspect
useful exception details and stack traces, and follow surrounding events to diagnose the problem.

**Done** = the initial user journeys and sources, diagnostic contract, backend, collection and browser integration,
search/archive lifecycle, operational failure behavior and observable acceptance criteria are agreed; every linked
decision is closed; remaining uncertainty is explicitly resolved, excluded or handed off; and approved EPIC
destinations and any required amendments are linked for separate grilling. This does not claim implemented
observability or authorize EPIC, TICKET or TASK creation automatically.

## Notes

### Approved planning scope and historical ownership

John approved this focused map and isolated worktree after inspecting existing ownership. The map adds a named
operator diagnosis outcome, not a prerequisite for the current terminal-first milestone. No backend or source
set was selected by that charting approval. WF-036–042 are now resolved; this map has no open decision frontier.
Loki and native Super Admin-only Logs are planning choices, not production qualification. The separately invoked,
confirmed grill has written [EPIC-00019](../epics/00019-EPIC.md), and its seven-TICKET requirement split is now accepted.
TASK decomposition is the current planning handoff below. Map closure alone created no implementation records.

During WF-036, John explicitly included all application commands, application queries and application events,
not just failures. All application Monolog records, channels and levels must reach the logging/collection pipeline;
no intentional discarding through null sinks, channel gaps, thresholds or sampling. SQL query logging is not
required. WF-036 initially assumed explicitly redacted useful payload/context rather than blanket exclusion.
During WF-037 John rejected log redaction and accepted preserving emitted logs while producers avoid emitting
secrets. WF-037 owns this explicit amendment; WF-036 retains its historical resolution and an amendment link.
A special Handler/Formatter is permitted for collection/encoding, not to reinstate rejected redaction. WF-037
resolves Common compatibility by keeping the current Common-backed public responses and using ordinary Monolog,
not adopting the opt-in safe middleware/reporter whose stricter redaction contract conflicts with this path.
WF-038 subsequently records John's explicit newest-context preference at capacity exhaustion: evict the oldest
eligible queued logs with visible loss accounting. This is an outage/capacity exception, not ordinary filtering,
sampling or permission to override preservation holds. WF-039 must reconcile preservation/archive consequences.
The source boundary now covers Agent OS's own services and managed-execution output. John excluded registered
project runtime-log collection (not deferred work) and browser JavaScript-error ingestion. Project identifiers
still relate Agent OS activity and run/check output; they do not enroll project services for collection. John
confirmed that managed-run/check output is Agent OS operation, whereas CMS runtime logs and observability belong
to the CMS. He approved installation-wide access through explicit log permissions, not ordinary project access
or Docker authority. WF-037 supplies the diagnostic design; exact Permission/enforcement integration remains
WF-041's responsibility.

- [WF-023 — Define local runtime and shared ingress topology](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md)
  retains its historical observability-service deferral. Its appended approved note delegates this evaluation,
  not installation or runtime changes. Structured stdout/stderr remains the preferred source contract.
- [WF-017 — Define execution history and event authority](tickets/WF-017-define-execution-history-and-event-authority.md)
  retains Workflow authority, observational limits, privacy and preservation categories. Its approved clarification
  separates the requested 14-day searchable window from preservation and the default 30-day post-terminal grace.
- [TICKET-00094 — Retain and protect immutable execution evidence](../tickets/00094-TICKET.md),
  [TICKET-00096 — Expose trustworthy Workflow history and live state](../tickets/00096-TICKET.md) and
  [TICKET-00097 — Inspect and control execution through the sessions Dashboard](../tickets/00097-TICKET.md)
  retain evidence, history and sessions-browser ownership. Reuse their links and permissions; do not introduce
  another Workflow store, transcript index or competing Artifact authority. Propose any necessary amendment
  explicitly before changing accepted requirements.

### Required destination capabilities, not resolved technical choices

- A Papertrail-style browser journey: live tail, pause/resume, text search, time/service/severity filters,
  surrounding context and expandable structured events. Link and filter project, Workflow, TASK, run and
  correlation identifiers where actually available; missing relationships stay unknown.
- Fourteen days of searchable logs by default, configurable independently from archive retention. Durable
  compressed archives need verified delivery, explicit failure behavior and defined older-log retrieval.
  Evaluate continuous archival so preservation does not depend on an export at the retention deadline.
- Useful exception types, emitted messages, file/line locations, stack frames, chained causes and release identity,
  with generic public error responses. Preserve emitted logs without pipeline redaction; producers avoid emitting
  secrets. Incidental sensitive text remains an exposure risk, not a guarantee of secret-free storage. Stack traces
  do not imply distributed tracing.
- Prefer structured stdout/stderr; justify application-managed files if needed. Authorize live streams, search,
  detail, exports and archives separately as appropriate, with current scope checks and no implicit Docker power.
- Keep operational logs separate from authoritative Workflow events, audit evidence and agent transcripts.
  Decide explicitly whether metrics, distributed tracing, alerts and error grouping belong in this release.

### Verified baseline and evidence limits

Read-only inspection of Agent OS `develop` at `6263dc6e660f989ac425e4d07a90dc7bc990a92f` found:

- [composer.json](../../composer.json) requires `johnnickell/fight-common` `~1.3.0`;
  [composer.lock](../../composer.lock) and local installed metadata identify **v1.3.0**, source
  `7de6cad6e8a9752973ad9f8e27e285b0c1510582`. This is dependency evidence, not runtime qualification.
- [ApiFailureMiddleware](../../src/Adapter/Http/Middleware/Api/ApiFailureMiddleware.php) scopes handling to
  `/api` and its descendants, validates/generates correlation and logs unknown failures without messages/traces.
  [Logging composition](../../config/common/observability.php) supplies JSON stderr for those failures; the
  general logger is a null sink. Expected failures currently are not reported by this middleware.
- The locally inspected Fight Common **v1.4.0** tag is source
  `87b9580c072f3149d574e09865daa79620c92118`. Its opt-in `SafeJSendErrorMiddleware` passes the original Throwable
  once to `SafeHttpDiagnostics`, including expected failures. The consumer must redact messages, causes, traces
  and context before any logger/formatter, never passing a raw PSR-3 exception. Failed reporting yields generic
  safe 500 without recursive reporting or a delivery claim. The tag is an adoption candidate, not installed or
  selected. Recheck available releases and consumer compatibility in WF-037 and again before implementation.
- Current [ApiFailureMapper](../../src/Adapter/Http/Api/Failure/ApiFailureMapper.php) uses Common's standard
  `fail` envelope for validation and `error` envelope for other known 4xx responses.
  [TASK-00020](../tasks/00020-TASK.md#completion-notes) records that application mapping;
  [the browser decoder](../../client/src/api/decodeResponse.ts) expects it. HTTP status and JSend envelope are
  separate choices: Common's general response factory accepts a controller-selected status without forcing
  all 4xx to `fail`. This is not a different JSend structure or an established conformance defect. John corrected
  the earlier conflation of Common's opt-in safe-middleware defaults with a universal JSend/package requirement.
  That v1.4.0 policy does use `fail` for its expected client-failure categories, differs in validation
  classification and requires valid 401/405 metadata. Its projector cannot change status or freely switch
  envelope kinds. WF-037 must preserve current application behavior or obtain explicit migration approval;
  observability supplies no independent reason to change envelopes.
- WF-023 selects private SeaweedFS for planned Artifact storage. That does not prove present availability,
  archive suitability or compatibility with any log backend. Existing retention/storage plans are not fresh
  delivery, recovery or performance evidence.

The initial baseline had no backend licensing/API research. WF-040 now records a pinned primary-source comparison
and an explicitly approved, completed disposable runtime comparison. Its bounded observations are not production
observability deployment or composed pipeline qualification. Closed decision summaries below are planning
resolutions; open decisions remain unsettled. Neither capability requirements nor diagnostic design alone
constitute backend approval.

## Decisions so far

1. **[WF-036 — Select initial log sources and diagnosis journeys](tickets/WF-036-select-initial-log-sources-and-diagnosis-journeys.md)
   is closed.** John accepted Agent OS commands/queries/events, all its Monolog output, supporting-service logs
   and managed-run/check output, with useful context. Its original redaction premise is explicitly amended in
   WF-037, not silently rewritten. Registered-project runtime logs and JavaScript-error ingestion are excluded,
   not deferred. Installation-wide access requires explicit log
   permissions; ordinary project access, links and correlation do not grant log or Docker authority.
2. **[WF-037 — Define redacted diagnostics and shared HTTP adoption](tickets/WF-037-define-redacted-diagnostics-and-shared-http-adoption.md)
   is closed.** Preserve emitted logs without pipeline redaction; producers avoid emitting secrets. Use native
   Monolog records/exception normalization and standard levels, with full selected-record coverage and useful context.
   Keep current Common-backed API responses; do not adopt its incompatible opt-in safe middleware/reporter solely
   for logging. John's resilience intent is to keep as much working as possible, isolate diagnostic failures and
   expose degraded coverage—not guarantee which operations survive any outage.
3. **[WF-038 — Define log collection and failure recovery](tickets/WF-038-define-log-collection-and-failure-recovery.md)
   is closed.** Installation-owned enrolled capture feeds persistent, independently bounded destination queues;
   runtime retention, collector persistence, destination receipts and archives remain distinct. Favor newer context
   through oldest-eligible eviction with independent loss reporting, without overriding holds. The ticket sets
   finite engineering disk/memory/CPU/framing/retry/concurrency defaults and source/replay/privilege boundaries.
   No collector or Docker grant is selected. The approved WF-040 timing amendment carries composed qualification
   into mandatory WF-042 integration/delivery gates; none is claimed complete here.
4. **[WF-039 — Define searchable retention and verified archives](tickets/WF-039-define-searchable-retention-and-verified-archives.md)
   is closed.** John approved 90-day ordinary archives, local-only initially and browser search through temporary
   restore. Fourteen-day search and 90-day archival clocks remain independent; holds/grace can extend preservation.
   Continuous compressed archival requires readback/content verification, owner-aware cleanup and finite capacity.
   Temporary scopes are isolated, currently authorized and expire; defaults and failure states are in the ticket.
   Host/storage loss can destroy local archives; off-machine protection is deferred. No storage/backend is qualified.
5. **[WF-040 — Select the log search backend](tickets/WF-040-select-the-log-search-backend.md)
   is closed.** John approved **Loki OSS as the conditional planning backend** and the explicit qualification-timing
   amendment. Application-owned expiry/deletion under existing preservation/privacy owners replaces autonomous
   day-14 backend deletion; separate verified archives and authorized original-clock temporary restore remain
   required. Outstanding composed checks are mandatory delivery gates in WF-042, not waived or claimed complete.
   The amendments are appended to WF-038/039. The small comparison supports only its measured API/resource cases;
   partial-batch/HTTP-260/WAL caveats remain. No collector, production storage, Docker grant or hold-safe
   implementation is selected/qualified. Owned experiment resources/data were removed; two pinned images remain cached.
6. **[WF-041 — Select the authorized log browser integration](tickets/WF-041-select-the-authorized-log-browser-integration.md)
   is closed.** John approved native Logs in the existing shell and **Super Admin-only initially**, linked from
   failed operations/runs and separate from sanitized Activity. Protected intent-specific permissions retain the
   existing managed-authority invariants; non-admin delegation is deferred, not an automatic TASK. Finite browser/
   stream/query/restore contracts and mandatory verification gates hand off to WF-042. No UI, permission grant or
   runtime integration has been implemented.
7. **[WF-042 — Bound observability delivery and acceptance](tickets/WF-042-bound-observability-delivery-and-acceptance.md)
   is closed.** John approved one complete live-and-archived diagnosis release and one new EPIC destination,
   reusing existing runtime/authority/evidence/history/UI owners. General metrics, tracing, external alerting and
   error grouping are deferred while independent health/loss visibility remains required. Acceptance workloads,
   measurable objectives and unresolved qualification gates carry into delivery. The separately confirmed grill
   has written approved [EPIC-00019](../epics/00019-EPIC.md); its separately approved TICKET-00111–00117 requirements
   are written. All TICKET-00111–00117 TASK splits are accepted/written (thirty-seven TASKs);
   no map planning phase remains. TASK-00487 contains mandatory composed operator/workload acceptance.
   No implementation has started for this map.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-036](tickets/WF-036-select-initial-log-sources-and-diagnosis-journeys.md) | Select initial log sources and diagnosis journeys | wayfinder:grill | HITL | Closed | — |
| [WF-037](tickets/WF-037-define-redacted-diagnostics-and-shared-http-adoption.md) | Define redacted diagnostics and shared HTTP adoption | wayfinder:grill | HITL | Closed | [WF-036](tickets/WF-036-select-initial-log-sources-and-diagnosis-journeys.md) |
| [WF-038](tickets/WF-038-define-log-collection-and-failure-recovery.md) | Define log collection and failure recovery | wayfinder:grill | HITL | Closed | [WF-037](tickets/WF-037-define-redacted-diagnostics-and-shared-http-adoption.md) |
| [WF-039](tickets/WF-039-define-searchable-retention-and-verified-archives.md) | Define searchable retention and verified archives | wayfinder:grill | HITL | Closed | [WF-038](tickets/WF-038-define-log-collection-and-failure-recovery.md) |
| [WF-040](tickets/WF-040-select-the-log-search-backend.md) | Select the log search backend | wayfinder:research, wayfinder:grill | AFK + HITL | Closed | [WF-039](tickets/WF-039-define-searchable-retention-and-verified-archives.md) |
| [WF-041](tickets/WF-041-select-the-authorized-log-browser-integration.md) | Select the authorized log browser integration | wayfinder:grill | HITL | Closed | [WF-040](tickets/WF-040-select-the-log-search-backend.md) |
| [WF-042](tickets/WF-042-bound-observability-delivery-and-acceptance.md) | Bound observability delivery and acceptance | wayfinder:grill | HITL | Closed | [WF-041](tickets/WF-041-select-the-authorized-log-browser-integration.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-036 sources/journeys → WF-037 diagnostics/adoption → WF-038 collection/recovery
  → WF-039 retention/archives → WF-040 backend → WF-041 browser → WF-042 delivery/handoff
```

Requirements lead product selection. WF-040 must reconcile candidate-specific storage/collection limitations with
WF-038/039 explicitly, not silently weaken them. A demonstrated conflict returns to the named human decision;
it does not authorize a backend selection, new service or automatic policy amendment.

## Frontier

None: WF-036–042 are Closed. John approved the complete first-release scope, explicit deferrals and single EPIC
handoff in [WF-042](tickets/WF-042-bound-observability-delivery-and-acceptance.md#accepted-resolution-and-epic-handoff).
This map is decision-complete, not implementation-complete. Its sole approved destination is now written as
[EPIC-00019](../epics/00019-EPIC.md), with accepted TICKET-00111–00117. No new decision frontier is opened for TASK
planning; unresolved runtime mechanisms remain named qualification blockers.

## Approved EPIC handoff

| Destination | Approval / source | Planning state |
|---|---|---|
| [EPIC-00019 — Diagnose Agent OS operations and managed runs with live and archived logs](../epics/00019-EPIC.md) | [WF-042 accepted resolution](tickets/WF-042-bound-observability-delivery-and-acceptance.md#accepted-resolution-and-epic-handoff), consuming WF-036–041; separately confirmed grill | EPIC and seven TICKETs accepted; [TICKET-00111–00117 TASKs accepted; all map planning complete](../epics/00019-EPIC.md#approved-installation-task-decomposition-and-completed-map-planning); implementation/qualification pending |

John confirmed the separately invoked grill summary; [EPIC-00019](../epics/00019-EPIC.md#approved-epic-handoff--2026-10-15)
now consumes the complete live/archived diagnosis journey, native Super Admin-only access, existing owners and all
mandatory integration gates. No other approved destination remains unwritten. Do not repeat settled interviews or
infer readiness from the small backend probe. Exact additive integrations and blockers remain required in decomposition.

The branch was rebased onto fetched `origin/develop` at `ac41c28` before allocation. Live/archive inventories across
available branches and all registered worktrees were checked. New upstream [TICKET-00110](../tickets/00110-TICKET.md)
and [TASK-00449](../tasks/00449-TASK.md) retain their independent trusted-human local log-display scope; they neither
wait for nor qualify this EPIC. No existing TICKET/TASK IDs, priorities or ownership were changed.

The EPIC and TICKET phases are complete for this map. John approved the seven-outcome split, now written as
[TICKET-00111](../tickets/00111-TICKET.md) diagnostics, [TICKET-00112](../tickets/00112-TICKET.md) collection,
[TICKET-00113](../tickets/00113-TICKET.md) native live diagnosis, [TICKET-00114](../tickets/00114-TICKET.md) verified
preservation/lifecycle, [TICKET-00115](../tickets/00115-TICKET.md) temporary archive search,
[TICKET-00116](../tickets/00116-TICKET.md) bounded extraction and [TICKET-00117](../tickets/00117-TICKET.md) operation/
recovery. The [EPIC coverage/coordination record](../epics/00019-EPIC.md#approved-ticket-decomposition-and-current-handoff)
traces every mandatory gate and early supplier without whole-TICKET dependency cycles.

John separately approved all seven splits, most recently
[TICKET-00117's seven-TASK split and exact supplier mapping](../tickets/00117-TICKET.md#approved-task-decomposition-and-current-handoff).
All are written as TASK-00451–00487. **Map-wide planning is complete**: all decisions Closed, sole EPIC approved,
seven requirement TICKETs approved and all seven TASK decompositions accepted. Parent statuses remain open because
implementation is unfinished, not because another decomposition/parent-only acceptance phase is needed.

TASK-00481–00485 supply sixteen receivers with exact early authority/storage/supervision/temporary-runtime/transport
edges. All receiving qualification gates remain. The collection design does not depend on its later runtime consumer;
finished operator guide TASK-00486 and composed qualification TASK-00487 never reverse-block early suppliers. G
transitively consumes every earlier observability TASK and owns the mandatory workload/fault/operator acceptance.
Actual preservation/privacy and managed-execution inputs remain gated where missing. Naming suppliers grants no
privilege and qualifies no resource fit, fence, current output authority or storage/restore mechanism.

Fetched `origin/develop` remains `58b087b`; this branch remains `ac41c28` without rebase. Live/archive/concurrent
inventories across 17 refs/four worktrees ended at TASK-00480 before allocating TASK-00481–00487. All thirty-seven
are unranked; only the sixteen named receivers gained early supplier metadata in this operation. Existing local
execution priorities/unrelated work are unchanged. No implementation, deployment, grant or runtime experiment follows.

Next: `/skill:next` in `.runs/worktrees/observability-planning/` for read-only Board-based work selection.
No execution is invoked. Recheck IDs/upstream before future allocation/publication; full build, review/QA,
publication and operational qualification/enrollment remain distinct outstanding work.

## Outstanding delivery qualification — handed off

- Measured sizing and physical enforcement of WF-038/039's finite collection/archive/restore profiles, source/backend
  format compatibility and recovery/query/tail/delivery performance; graduate only concrete additional decisions.
- Qualified local archive/catalog/lifecycle integration and backend temporary-import/isolation APIs; the accepted
  contract does not prove a SeaweedFS/backend integration, protect against host loss or weaken holds.
- Disposable UI evidence and additional integration/volume experiments remain separately scoped. WF-040 now has
  bounded backend measurements, but no full pipeline, real-volume sizing, hard disk quota or browser acceptance.
- WF-042 carries each unresolved collector/storage/authority/browser/resource obligation with its existing owner
  and verification path into the approved EPIC handoff. Exact additive integrations must be traced before dependent
  implementation is executable; any actual authority/guarantee conflict needs an explicit amendment.

## Out of scope

- Product implementation, dependency installation/update, runtime configuration, Docker authority changes,
  real-log ingestion, archive writes, production access and publication. The sole runtime exception is John's
  approved WF-040 disposable synthetic comparison within its recorded bounds and stop conditions; it is not
  deployment authority.
- Reopening the vision map, redefining Workflow/audit authority, importing agent transcripts/prompts/thinking,
  replacing existing evidence ownership or shortening existing preservation without explicit approval.
- Automatically making observability a terminal-first prerequisite or changing Board execution priority.
- Collecting runtime/service logs from registered applications/projects managed by Agent OS and browser
  JavaScript-error ingestion are explicitly excluded by John, not deferred delivery promises. This does not
  exclude Agent OS's own project-linked activity, server HTTP diagnostics or managed check/run output.
- General metrics dashboards/analytics, distributed tracing, external alerting and automatic error grouping are
  explicitly deferred by WF-042. Independent local health, capacity, gap/loss visibility and recovery remain required.
- Off-machine operational-archive backup/recovery is deferred beyond the initial local-only scope by John. No
  cloud egress or host-loss protection is implicitly promised; other evidence/backup owners remain unchanged.

## Planning verification and delivery

The isolated planning branch is `feature/observability-planning`, now rebased onto `ac41c28`, at
`.runs/worktrees/observability-planning/` in the base checkout. Before rebase/EPIC creation, `./bin/planning-check --write`
and `./bin/planning-check` passed with 572 records, 473 active, and `git diff --check` passed. Those counts are historical;
the [EPIC progress](../epics/00019-EPIC.md#progress) records the new planning handoff and reconciliation.
WF-036–042 and this map are Closed: source/access, native Monolog/no-pipeline-redaction diagnostics, existing Common
response path, finite collection/recovery, local archive/temporary-browser-restore lifecycle, conditional Loki,
native Super Admin-only Logs and the complete release/acceptance/EPIC-handoff contract. No decision remains Open;
the single EPIC, its seven-TICKET split and all TICKET-00111–00117 TASK decompositions are written/approved as
TASK-00451–00487. All map planning phases are complete; implementation and qualification remain pending. John approved the
qualification-timing amendment, appended
to WF-038/039 and carried into WF-042 as mandatory delivery gates. Backend observations remain fresh only within
the earlier synthetic probe's documented scope; no additional runtime evidence is claimed by this approval.
Collector/SeaweedFS/archive/hold integration is not qualified. No production deployment/collector, ongoing Docker
grant or public API amendment is accepted. Planning decision closure is not application acceptance.
Two exact backend images were pulled and remain cached. All experiment containers/networks/data were removed;
application/dependency/daemon configuration and unrelated services were unchanged. Planning checks are separate
from runtime evidence. The full `./bin/build` has not been run for this uncommitted planning edit; it remains
required before any commit/publication. Independent review, commit, PR, merge, release and deployment are unclaimed.
