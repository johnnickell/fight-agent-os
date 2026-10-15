---
id: TICKET-00115
epic: EPIC-00019
title: Investigate older incidents through temporary archive search
status: ready-for-agent
---

# Investigate older incidents through temporary archive search

## Problem statement

An incident beyond the live-search window must remain investigable in the browser without importing old records as
new activity, granting access through a guessed restore ID or retaining unbounded temporary copies. Upload/import
success alone does not establish that the selected archived content can actually be queried.

## Solution and boundaries

Deliver the older-log browser journey for [EPIC-00019](../epics/00019-EPIC.md), consuming
[WF-039's restore contract](../wayfinder/tickets/WF-039-define-searchable-retention-and-verified-archives.md#authorized-temporary-browser-restore)
and [WF-041](../wayfinder/tickets/WF-041-select-the-authorized-log-browser-integration.md). Reuse native Logs and
private bounded query operations rather than another viewer. Select sources/time from the currently authorized
verified catalog, show estimated size/coverage and known gaps, and label collection-time versus event-time basis.
Unknown estimates need bounded discovery; caller-supplied bucket keys, paths, tenants or URLs are never accepted.

An explicit idempotent restore request records requester, selected segment identities, current limits and expiry.
Resolve original-command uncertainty before retry; no automatic mutation replay after refresh/reconnect. Worker
admission/execution must recheck current authority and policy, verify archived segments again and stream bounded
validated decompression into an isolated temporary search scope. Preserve original observation/source identities,
collection/emitted clocks and provenance. Never merge with live tail, rewrite timestamps to satisfy backend limits
or replay business commands/events. Backend flush/import acknowledgement is not readiness: actual content/coverage
queries must establish the published ready or explicitly partial result.

Follow the complete finite profile: up to 7 days and 1 GiB decompressed input per request, one running restore and
at most four pending per installation, one outstanding/requester and at most four ready scopes. Temporary allocation
is 4 GiB physical including staging/indexes, with a 512-MiB/1-vCPU worker and separately accounted bounded backend
resources. Pending expiry is one hour, execution budget 15 minutes and storage/import requests 30 seconds. Ready
scopes expire 24 hours after readiness with no sliding renewal; healthy cleanup reconciles at least every five minutes.
Queries/context/detail use TICKET-00113's shared limits: two read/export operations installation-wide across live and
restored scopes, one/requester, 30-second operations and 500-event/1-MiB pages, not an additional restored-data budget.

Validate actual decompressed bytes and physical usage rather than trusting manifests; detect corrupt gzip, framing,
digest/count mismatch and expansion before unsafe publication. Failed/cancelled/expired scopes are inaccessible.
Any intentionally published verified partial subset carries exact coverage and incomplete status, never a silently
truncated complete result. Leftover imports count against capacity until confirmed cleaned; deny new admission rather
than hide them in another spool.

Use bounded source-segment lifecycle leases without extending primary archive retention indefinitely. Recheck
hold/purge/policy changes before publication and every private release. Logical expiry/revocation removes access
independently of delayed or failed physical cleanup. Restore is a disposable projection, not a preservation owner;
required evidence stays with its existing owner. Downloads/exports do not renew the scope.

Native coverage selection, submission/status/progress and search/context/detail retain keyboard/focus, reduced-motion,
light/dark/narrow-layout and volatile-state rules. Distinguish empty, busy, delayed, partial, corrupt, interrupted,
purged, expired and unavailable only where current authority permits knowledge of the target. Do not show another
requester's status because both are Super Admins. Export remains TICKET-00116; no sharing/scope-management feature,
off-machine recovery, unlimited fanout, download-only substitute or background business replay is included.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Select an older incident | No mutation | Authorized bounded archive coverage/size/gaps | None from reads | Explicit selection using verified catalog identities |
| Request temporary search | Idempotent restore admission under current authority | Current policy, request identity, limits and original outcome | Committed restore-intent/lifecycle facts | Bounded queued work or busy/denied outcome, not immediate readiness |
| Prepare and inspect restored data | Enrolled bounded verify/import worker; read-only status/search/context/detail | Actual segment/content verification, readiness and current scope authority | Restore lifecycle facts, not Workflow events | Isolated exact searchable bytes or explicit incomplete/corrupt failure |
| Expire or revoke a scope | Owning expiry/authority-loss/cleanup processing | Current expiry, policy, requester and residual physical usage | Secret-free lifecycle/disposal facts | Immediate access denial plus independently tracked physical reclamation |

## Validation and permissions

Require `READ_LOGS` + `RESTORE_LOG_ARCHIVES` for coverage, submission, status and every restored query/detail access.
All initial definitions remain protected Super Admin capabilities under TICKET-00113/existing managed authority;
restore IDs are not grants. Scope ownership, readiness, current policy and fixed expiry apply in addition to
permissions. Restored exports also require `EXPORT_LOGS`; archive downloads have their separate catalog path and
must not be forced to acquire restore power. Restore grants no hold/purge/retention/storage/Docker authority.

Use [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md): durable intent before external import, no external
storage operation inside the business transaction, current checks before fetch/release and each bounded batch,
same-origin authenticated transport/applicable CSRF, private no-store responses and generation-fenced volatile UI.
Unknown authority withholds output/publication, and already released bytes cannot be recalled.

## Acceptance and evidence

- Real native selection/request/status/search/context/detail works for verified older content, including original
  times beyond live lookback, without timestamp rewriting, live-tail mixing or replayed business operations.
- Idempotency, interrupted/uncertain submission/import and restart preserve the original request and actual result.
  Nominal valid supported selections succeed within the accepted budget; timeout alone is not successful restore.
- Corrupt/missing segments, expansion, oversize/unknown selection, capacity, partial import and backend lag produce
  bounded truthful states; content queries verify readiness, not merely import ACK or a fixed wait.
- Cross-requester access, guessed IDs, revoked sessions/capabilities, expiry and purge/hold/policy races deny unsafe
  disclosure/publication; late responses cannot revive old UI authority or extend scope lifetime.
- Actual full-boundary 1-GiB selections, shared read budgets, worker/backend/storage usage, cancelled work and delayed
  cleanup are accounted for. Accessible real browser flows and owned lifecycle/authority/recovery tests qualify the
  journey with direct provider/storage probes and full `./bin/build`, not tests of config or synthetic fixtures alone.

## Coordination

Consumes [TICKET-00114](00114-TICKET.md)'s catalog/verified segments/lifecycle leases and
[TICKET-00113](00113-TICKET.md)'s current protected authority and reusable private query/native presentation contracts.
Provides valid scope identity/readiness/expiry to [TICKET-00116](00116-TICKET.md). TICKET-00117 supplies physical/runtime
constraints and composed recovery. Missing old-data query support, fenced cleanup or aggregate capacity is a concrete
delivery blocker, not approval for a second backend or weaker retention. Early suppliers do not wait for this UI.

TICKET-00113 now allocates [TASK-00462](../tasks/00462-TASK.md) protected definitions,
[TASK-00463](../tasks/00463-TASK.md) current-authority/query/shared read admission and
[TASK-00464](../tasks/00464-TASK.md) bounded context/detail. Reuse these concrete capabilities under this TICKET's
additional requester/readiness/expiry/restore checks, not a whole native-Logs TICKET completion gate. No restore
TASK or operation is allocated by this coordination note.

TICKET-00114 now supplies [TASK-00467](../tasks/00467-TASK.md) early lifecycle/disclosure participants,
[TASK-00468](../tasks/00468-TASK.md) verified immutable archive receipts and
[TASK-00470](../tasks/00470-TASK.md) protected bounded catalog operations. Consume those precise capabilities rather
than completed archive cleanup/recovery. Receiving [TASK-00471](../tasks/00471-TASK.md) and
[TASK-00472](../tasks/00472-TASK.md) now explicitly name [TASK-00474](../tasks/00474-TASK.md)'s early lease/restriction
contracts, [TASK-00475](../tasks/00475-TASK.md)'s actual import/readiness participation and
[TASK-00477](../tasks/00477-TASK.md)'s retirement/removal mechanisms. Neither supplier waits for those completed
cleanup/purge consumers. TASK-00476's read/cache behavior joins early fences before exposure and owns its evidence.
All receiving qualification gates remain; IDs do not establish safe effects or prove absence of copies.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00474](../tasks/00474-TASK.md) | Admit requester-owned restores with bounded lifecycle authority | needs-info |
| [TASK-00475](../tasks/00475-TASK.md) | Prepare verified isolated archive-search scopes | needs-info |
| [TASK-00476](../tasks/00476-TASK.md) | Investigate restored incidents in native Logs | ready-for-agent |
| [TASK-00477](../tasks/00477-TASK.md) | Retire and reclaim temporary restore scopes safely | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement group in EPIC-00019's seven-TICKET split. That requirement checkpoint allocated no
TASKs or runtime grants. Actual restore/import/authority/expiry and browser qualification remain outstanding.

### Approved TASK decomposition and current handoff

John invoked `to-tasks TICKET-00115` in the selected worktree and answered **“approved”** to the four-outcome proposal.
These records are now written/accepted and cover the complete agreed temporary archive-search scope:

| Slice | TASK / outcome | Dependencies and current gate |
|---|---|---|
| A | [TASK-00474 — Admit requester-owned restores with bounded lifecycle authority](../tasks/00474-TASK.md) | `needs-info`; TASK-00462/00463/00467/00470/00484; actual native admission/resource binding remains unqualified |
| B | [TASK-00475 — Prepare verified isolated archive-search scopes](../tasks/00475-TASK.md) | `needs-info`; A, TASK-00468/00461/00484; actual temporary launcher/storage/resource binding remains unqualified |
| C | [TASK-00476 — Investigate restored incidents in native Logs](../tasks/00476-TASK.md) | Decision-complete; A/B, TASK-00463/00464/00470 for the real native selection/status/search/context/detail journey |
| D | [TASK-00477 — Retire and reclaim temporary restore scopes safely](../tasks/00477-TASK.md) | `needs-info`; A/B, TASK-00467/00484; actual native cleanup/supervision/physical accounting remains unqualified |

A supplies real protected admission/status, original-request recovery, finite reservations and early current
lease/restriction participants. B owns actual archive re-verification, isolated old-data import, content-proven
readiness and ordinary interrupted-import recovery/disposal hooks. C delivers the real native older-incident journey
with shared query/detail/authority budgets. D owns scheduled retirement and residual reconciliation, not safety/tests
postponed from A/B. Each owns its relevant security, behavior tests, direct integration/resource qualification,
applicable browser accessibility and full build. There is no separate proof-only or parent-only acceptance gate.

TASK-00471/00472 now explicitly depend on A/B/D for early contracts and actual provider participation; both retain
`needs-info`. No reverse edge to finished archive cleanup/purge or restore browser is added. Immediate logical
expiry/revocation denial belongs to A from inception, independent of D's delayed physical cleanup. C's read/cache
consumer honors those early fences before exposure; a later purge TASK does not excuse stale disclosure. Restored
export may consume exact scope/query capabilities with additional EXPORT_LOGS/current checks; live export and
archive download do not wait for this browser.

All four are unranked. A/B/D retain missing mechanism gates; C waits for named prerequisites. The full seven-day/
1-GiB, job/scope/worker/backend/4-GiB physical, fixed expiry and shared-read profile remains mandatory. The historical
300-record flush/import probe does not prove real archives, full-boundary readiness, isolation or physical capacity.
No new permission, launcher capability, backend stack, sharing feature or scope-renewal power is granted.

Before allocation, fetched `origin/develop` remained `58b087b` while the selected branch stayed `ac41c28`; no rebase
or upstream application adoption occurred. Live/archive paths across 17 refs and four registered worktrees ended at
TASK-00473 (committed refs TASK-00450), with no archived TASK records. Only TASK-00474–00477 were allocated. Recheck
IDs/upstream before future allocation/publication; this snapshot reserves nothing else. Existing local Board priority,
terminal-first work and TASK-00449 remain unchanged.

**Current phase:** all seven TICKET-00111–00117 TASK decompositions are complete/accepted; no map planning phase remains. Decisions
and EPIC/TICKET phases stay complete, not implementation/runtime acceptance. TASK-00480 now consumes TASK-00474/00475/
00476 and TASK-00478 for restored export; no reverse dependency on extraction or scope-lifetime extension is added.
TASK-00484 now supplies TASK-00474/00475/00477's exact early native launcher/lifetime/resource/removal contracts;
actual mechanisms remain gated, not qualified by naming the supplier.

### Temporary-restore planning verification — historical checkpoint

`./bin/planning-check --write` and `./bin/planning-check` passed with **613 records, 508 active**, generated views
current. Tracked and explicit untracked planning Markdown whitespace checks passed. Scope checks confirmed four
new TICKET-00115 feature TASKs with blank order/pr, three `needs-info` gates and one decision-complete browser slice;
TASK-00471/00472 retain `needs-info` with exact TASK-00474/00475/00477 supplier edges. No tracked prior TASK/TICKET
source changed, and no new IDs collided in other registered worktrees. Existing local Active/Ready rows and priority
remain unchanged. Relative to the archive checkpoint, the Board adds three Needs Info and one Waiting entry.

These are planning receipts only. No implementation, actual restore/cleanup qualification, full `./bin/build`,
independent review, commit, publication or rebase occurred. Changes remain uncommitted; full build and upstream/ID
reconciliation remain required before commit/publication. Historical backend/import evidence is not fresh application
acceptance; actual launcher, resource and owner mechanisms remain gated.

Next: `/skill:next` in `.runs/worktrees/observability-planning/`, following
[TICKET-00117's completed planning handoff](00117-TICKET.md#approved-task-decomposition-and-current-handoff).
Consult the Board read-only; no implementation or runtime authority follows.
