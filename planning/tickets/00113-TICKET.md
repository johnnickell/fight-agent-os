---
id: TICKET-00113
epic: EPIC-00019
title: Diagnose current failures through authorized native Logs
status: ready-for-agent
---

# Diagnose current failures through authorized native Logs

## Problem statement

A currently authorized operator needs to reach useful logs from a failed operation or run, search the entire
permitted window and follow surrounding activity without exposing raw diagnostics to ordinary users, stale sessions
or a public backend. A page-only search, ambiguous severity mapping or falsely continuous tail cannot diagnose reliably.

## Solution and boundaries

Deliver private conditional Loki integration and native Logs in the existing shell under
[WF-040](../wayfinder/tickets/WF-040-select-the-log-search-backend.md),
[WF-041](../wayfinder/tickets/WF-041-select-the-authorized-log-browser-integration.md) and
[EPIC-00019](../epics/00019-EPIC.md). Provide permission-gated Logs navigation and failure/correlation/run Summary
links, using available typed references without invented lineage. Keep raw Logs separate from TICKET-00096's sanitized
Activity and TICKET-00097's four independently loaded detail sections; do not create a second Dashboard.

Expose intent-specific application queries for whole-scope literal text, time/service/exact severity/available
relationship filters, source-generation context and exact raw detail. All includes DEBUG, with eight distinct Monolog
levels plus unknown. Default collection-time presentation is separate from emitted time; a 15-minute initial view
does not truncate the allowed 14-day live window. Cursors bind typed scope/filter/time/direction and current policy;
use stable identity and tie-safe traversal, never timestamp+1ns skipping or message-text deduplication. Missing complete
traversal capability is a delivery blocker, not an excuse to label truncated results complete.

Context may explicitly relax text/severity filters within the accepted bounded source/time window, never current
authority or retention. Render raw content inert and reversibly escaped, with labelled previews and bounded exact
continuation for large/binary/fragmented records. Expose only actually emitted exception/cause/stack/release content;
no synthesized locals, automatic external resources, HTML/ANSI execution or unrestricted dumps.

Use authenticated same-origin fetch streaming through a private Loki adapter. Qualify ingress buffering, cancellation,
leases, worker/socket occupancy and backpressure; no token URLs, arbitrary LogQL/admin proxy or browser/backend
connection. Tail, pause/resume and reconnect reconcile bounded coverage without falsely claiming caught-up state.
Protect frozen context within the finite buffer until explicit resume/navigation or required authority clearing.
Keep browser-buffer gaps distinct from collector loss and archive gaps; heartbeat is not collection health.

The full [WF-041 finite envelope](../wayfinder/tickets/WF-041-select-the-authorized-log-browser-integration.md#initial-finite-browser-and-transport-envelope)
is normative: bounded requests/search strings, 30-second reads, 500-event/1-MiB pages, two installation-wide read/export
operations shared with restored data and one per requester; four tails/installation with shorter credential-bound
leases, bounded batches/backlog/retries, 2,000-observation/8-MiB view budgets including details/paused backlog and
200 rendered rows. Enforce aggregate counts through cancellation, not per process or UI instance. Serialized limits
are not heap/CPU fitness evidence. Preserve every more specific context/detail/pause/connection limit in WF-041.

The private Loki integration must qualify actual write/read/tail identity, all-record framing, partial batches,
HTTP-260 blocked ingestion, metadata normalization and WAL faults. Backend acknowledgement is not durable delivery
or verified archival; provide precise dispositions to TICKET-00112. Native asynchronous deletion participates in
TICKET-00114's owner fence, never an autonomous day-14 TTL. Historical pinned probes support only their measured
cases; exact image/storage/license/security adoption belongs to qualified delivery with TICKET-00117.

Exclude Grafana/iframe/frontend-code adoption, another credential store, raw logs in TASK-00389's safe Mercure topics,
non-admin delegation, arbitrary backend discovery/tenant selection/push/delete/flush APIs and public response changes.
Archive restore and bulk extraction remain TICKET-00115/00116; their shared read/authority contracts are supplied early.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Enter from failure/run or Logs | Navigation only | Current actor/capability and typed available references | None; viewing cannot advance a Workflow | Native authorized diagnosis surface, or honest denied/unavailable state |
| Search, filter and inspect | No business mutation | Whole-scope bounded search/context/raw continuation | None from reads | Useful results, exact severities and explicit coverage/preview limits |
| Tail, pause and resume | Read subscription/view controls only | Current authority, bounded reconnect coverage and source status | Transport observations, not authoritative Domain events | Bounded live view with stable focus and truthful gaps |
| Reconcile protected catalog | Existing managed-policy preview/apply under installation authority | Existing catalog/IDs and proposed exact additions | Existing committed policy facts | Four planned protected log permissions, without another authority model |
| Integrate private search delivery | Enrolled backend delivery under TICKET-00112's original identity | Exact backend acceptance/query visibility | Durable disposition only where qualified | Search data or explicit partial/uncertain result, not an archive receipt |

## Validation and permissions

Initial application audience is Super Admin-only through existing protected capabilities, never Role-name bypasses.
Extend [TICKET-00015](00015-TICKET.md)'s managed catalog explicitly with `READ_LOGS`, `EXPORT_LOGS`,
`RESTORE_LOG_ARCHIVES` and `DOWNLOAD_LOG_ARCHIVES`, all `SUPER_ADMIN_ONLY`, preserving every existing ID and
[TICKET-00031](00031-TICKET.md)'s prohibition on custom-role/direct-Agent delegation. Use owning ID factories when
implementation defines new identities. This accepted additive requirement does not alter the catalog or assign a
grant during planning. Preview/apply/enrollment retain existing trusted installation authority and audit.

`READ_LOGS` protects live search/tail/context/detail and source coverage. Provide shared current actor/session/
capability/policy checks before fetch, before private release and on each bounded stream/output batch. Unknown
authority withholds content. TICKET-00115/00116 enforce their additional operation/scope permissions; possession of
a URL, cursor, project link, tenant or restore ID grants nothing. Already released/in-flight bytes cannot be recalled.

Follow [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md): one `/me` owner, same-origin Bearer and applicable CSRF,
no-store private responses, volatile state, current-generation result fences and clearing on refresh/logout/expiry/
identity/scope changes. The client's 60-second freshness is not a server permission cache. Keep sensitive text in
bounded request bodies, out of URLs/history/referrers/telemetry/logged payloads. Validate typed IDs, times, literal
search and bound cursors; unauthorized/missing targets must not reveal hidden existence/counts.

## Acceptance and evidence

- Real failed HTTP/worker and managed-run entry paths expose included diagnostics and context without changing public
  responses, sanitized Activity, evidence permissions or Dashboard ownership. No fixture-only production UI.
- Whole-window literal search, all exact levels, same-time ties, late arrivals, binary/large detail and context work
  with honest collection-time/provenance and partial/unavailable states.
- Catalog preview/reconciliation and every private entry path enforce the four protected intent boundaries; non-admin,
  custom-role/direct-Agent, cross-scope and revocation/output races deny without leaking content.
- Actual streaming/ingress cancellation, lease rollover, slow clients, pause overflow and reconnect obey finite shared
  resources and do not falsely claim continuous coverage. A missing stream cannot silently become polling-only.
- Real keyboard/focus, accessible row/detail navigation, non-color severity, reduced motion, bounded announcements,
  light/dark/zoom/narrow layouts and authority clearing work. Do not announce each arriving line.
- Owned API/application/client tests plus direct backend/transport qualification meet WF-042's baseline objectives;
  full `./bin/build` and scoped real browser evidence remain mandatory. No tests of config/tooling/library internals.

## Coordination

Supply private destination, protected catalog, current-authority and bounded query contracts early, before final UI.
Consume TICKET-00112's identity/disposition/status, TICKET-00114's visibility/preservation fences and TICKET-00117's
runtime/storage constraints. TICKET-00115 reuses queries for isolated restored scopes; TICKET-00116 shares the read/
export admission budget. Existing shell/authentication and run-reference suppliers remain concrete dependencies;
unknown integration mechanisms stay blockers on dependent TASKs, not a blanket wait for completed sibling TICKETs.

Receiving [TASK-00459](../tasks/00459-TASK.md) now explicitly depends on [TASK-00461](../tasks/00461-TASK.md)'s early
private ingestion/disposition/visibility contract, not completed Logs UI or this entire TICKET. Its `needs-info`
gate remains until actual mechanisms/qualification are supplied. TASK-00461 does not depend on the finished collector
binding, avoiding a reverse cycle.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00461](../tasks/00461-TASK.md) | Provide private Loki ingestion with truthful receipt contracts | needs-info |
| [TASK-00462](../tasks/00462-TASK.md) | Reconcile the four protected log capabilities | ready-for-agent |
| [TASK-00463](../tasks/00463-TASK.md) | Search current logs in the native workspace | needs-info |
| [TASK-00464](../tasks/00464-TASK.md) | Inspect exact diagnostic content and surrounding context | ready-for-agent |
| [TASK-00465](../tasks/00465-TASK.md) | Follow live logs with bounded pause and recovery | needs-info |
| [TASK-00466](../tasks/00466-TASK.md) | Navigate from real failures and runs into diagnosis | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement group in EPIC-00019's seven-TICKET split. That requirement checkpoint allocated no
TASKs. Accepted planning does not select production images/storage, implement a protected catalog or establish
browser acceptance/deployment authority. Missing mechanisms require owner reconciliation, not weaker guarantees.

### Approved TASK decomposition and current handoff

John separately invoked `to-tasks TICKET-00113` and answered **“approved”** to the six-outcome proposal. These records
are now written/accepted and cover this TICKET's complete agreed native live-diagnosis scope:

| Slice | TASK / outcome | Dependencies and current gate |
|---|---|---|
| A | [TASK-00461 — Provide private Loki ingestion with truthful receipt contracts](../tasks/00461-TASK.md) | `needs-info`; TASK-00455/00467/00482/00483; actual storage/runtime/lifecycle/fence mechanisms remain unqualified |
| B | [TASK-00462 — Reconcile the four protected log capabilities](../tasks/00462-TASK.md) | Decision-complete; delivered TASK-00036 managed-policy foundation; no backend/browser prerequisite |
| C | [TASK-00463 — Search current logs in the native workspace](../tasks/00463-TASK.md) | `needs-info`; TASK-00461/00462/00459/00456/00467 and TASK-00051; mapped visibility/disclosure participants remain unqualified |
| D | [TASK-00464 — Inspect exact diagnostic content and surrounding context](../tasks/00464-TASK.md) | Decision-complete, blocked by TASK-00463 |
| E | [TASK-00465 — Follow live logs with bounded pause and recovery](../tasks/00465-TASK.md) | `needs-info`; TASK-00463/00464/00485; actual ingress/worker streaming mechanisms remain unqualified |
| F | [TASK-00466 — Navigate from real failures and runs into diagnosis](../tasks/00466-TASK.md) | Decision-complete; TASK-00463/00464/00465, real producer TASK-00452/00454 and Summary owner TASK-00391 |

A supplies actual private ingestion early; B supplies protected definitions through the existing operator use case;
C owns whole-window search, fresh fetch/release authority, typed query, shared admission, coverage and real native
presentation; D owns context/raw continuation; E owns real leased streaming/pause/reconnect; F adds actual failure/
run entry links and diagnosis integration. Each owns its security, bounded resources, tests, applicable browser
accessibility evidence and full gate. No separate proof-only TASK or parent-only acceptance gap is introduced.

TASK-00459 now names TASK-00461 explicitly while retaining its qualification gate. C's reusable current-authority/
query/shared-admission behavior supplies later restore/export use cases without requiring their completion; D can
supply bounded detail behavior under their additional scope checks. The existing cached request principal alone
cannot certify fresh release/batch authority; C/E must prove actual revalidation and owner-fenced disclosure.

All six are unranked. B is ready on its delivered foundation; D/F await named dependencies; A/C/E retain concrete
missing-capability gates. Existing catalog identities are preserved when B is implemented; this planning operation
assigns no permission and does not reopen the completed initial catalog delivery. No terminal-first/Workflow/
evidence owner gains a reverse prerequisite, and TASK-00449 remains independent.

Fetched `origin/develop`/HEAD remained `ac41c28`. Before allocation, live/archive paths across 18 local/remote refs
and four registered worktrees ended at TASK-00460 (committed refs TASK-00450), with no archived TASK records. Only
TASK-00461–00466 were allocated; recheck future IDs rather than treating this snapshot as a reservation.

**Current phase:** all seven TICKET-00111–00117 TASK decompositions are complete/accepted. No map planning phase remains.
TICKET-00114 now maps TASK-00467 to TASK-00461/00463 as the early lifecycle/disclosure supplier without clearing their
qualification gates. TICKET-00116 now explicitly shares TASK-00463's two-installation/one-requester admission with
archive downloads, without a separate pool or reverse extraction dependency. Decisions and EPIC/TICKET phases stay
complete; implementation/runtime evidence is outstanding. TASK-00482/00483 now supply TASK-00461's actual storage/
runtime binding and TASK-00485 supplies TASK-00465's transport; all native qualification gates remain.

### Native-Logs planning verification — historical checkpoint

`./bin/planning-check --write` and `./bin/planning-check` passed with **602 records, 497 active**, generated views
current. Tracked and explicit untracked planning Markdown whitespace checks passed. Scope checks confirmed exactly
six new TICKET-00113 TASKs, unranked metadata, three explicit information gates and TASK-00459's new supplier edge;
no tracked prior TASK/TICKET source changed and no new IDs collided in other registered worktrees.

Relative to the collection checkpoint, the Board adds one unranked Ready, two Waiting and three Needs Info entries,
and updates TASK-00459's blockers. Existing active work, first ready selection and execution priorities are unchanged.
These are planning receipts only: no implementation, runtime qualification, full `./bin/build`, review, commit or
publication was performed. Changes remain uncommitted; full build remains required before commit/publication.

Next: `/skill:next` in `.runs/worktrees/observability-planning/`, following
[TICKET-00117's completed planning handoff](00117-TICKET.md#approved-task-decomposition-and-current-handoff).
Consult the Board read-only; no implementation or runtime authority follows.
