---
id: TICKET-00112
epic: EPIC-00019
title: Collect operational logs with bounded recovery and visible loss
status: ready-for-agent
---

# Collect operational logs with bounded recovery and visible loss

## Problem statement

An operator needs attributable Agent OS logs despite collector restarts or destination outages, and needs to know
when capture or delivery is incomplete. A persistent sink alone does not recover a stateless reader, and unlimited
buffers or silent drop behavior would endanger the installation and conceal missing evidence.

## Solution and boundaries

Deliver installation-enrolled capture for [EPIC-00019](../epics/00019-EPIC.md) under
[WF-038](../wayfinder/tickets/WF-038-define-log-collection-and-failure-recovery.md), including its finite profile,
source/privilege, replay, eviction and independent-status contracts. Include Agent OS application activity/Monolog
from [TICKET-00111](00111-TICKET.md), supporting services where deployed and managed-run/check output. Maintain an
actual source inventory; absent producers, retired sources and unknown attribution are explicit, not silently omitted.
Exclude registered-project runtime logs, browser JavaScript errors and transcripts/prompts/hidden reasoning.

Prefer qualified structured stdout/stderr capture through the runtime's supported interfaces. Persist source
generation, stream/position and pending destination dispositions with crash-safe ordering or qualified overlap.
Advance checkpoints only after covered bytes are recoverable or their settled disposition is durable. A Docker
since timestamp is not a byte cursor. Rotation, TTY merge, partial framing and native driver limitations must be
reported honestly; identical text/timestamps do not establish identity or authorize deduplication.

Feed independently bounded persistent search and archive destinations. Preserve exact captured bytes/provenance
through reversible raw/fragment encoding for malformed, binary, oversized and incomplete records. Source retention,
collector persistence, sink acceptance, query visibility and archive verification are separate states. Retry uncertain
results with original identity; classify partial/rejected/poison responses without starving valid work or inventing
exactly-once delivery. Loki generic 2xx responses, blocked-ingestion HTTP 260 and WAL faults require the exact
[TICKET-00113](00113-TICKET.md) sink contract; only TICKET-00114's verified receipt confirms archival.

Under pressure, evict oldest eligible pending data by the qualified queue/chunk age and persist independent loss
dispositions, counts/ranges and granularity uncertainty. An evicted entry must not endlessly replay ahead of fresh
context. Never override holds, delete protected sole copies or apply queue eviction to verified archives. When only
protected material remains, keep bounded resources, expose the preservation/capacity conflict and defer to the
existing owner; no unlimited fallback or promise that all new output survives. Retirement's bounded handoff cannot
silently remove protected material or redefine Workflow completion.

The authoritative [initial finite profile](../wayfinder/tickets/WF-038-define-log-collection-and-failure-recovery.md#initial-finite-profile)
includes the 100-MiB/source runtime target, 2-GiB/destination queues, 256-MiB raw holding and 5-GiB physical collection
allocation with control reserve, plus CPU/RAM, framing, concurrency, lease, discovery, retry and shutdown limits.
These require actual enforcement and aggregate accounting, not just configured logical sizes. Do not add hidden
spools, source filters presented as authorization, a custom collector/queue/proxy or a Docker grant by implication.

Expose status through existing independent installation supervision, not solely collector stderr or Loki. Distinguish
not enrolled/captured, delayed, lost/evicted, unavailable, malformed/incomplete, not searchable, archive unconfirmed,
expired and purged. Preserve the 5-second supervision/three-miss unknown rule, bounded journal/incidents/status I/O,
and 75%/90% pressure signals. Exact missing counts are not promised when only uncertainty is known. Status contains
bounded safe facts, not another raw-content store, metrics platform or external alerting service.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Enroll and capture an accepted source | Explicit existing installation operation; enrolled reader work | Actual runtime/source identity, privilege and retained positions | Capture observations, not Workflow events | Private durable pending data with original collection clock/provenance |
| Deliver independently to search/archive | Bounded delivery/retry under enrolled authority | Per-destination pending state and exact receipt | Durable delivery dispositions with honest uncertainty | Search outage does not deliberately stop archival, or vice versa |
| Resume after restart or retirement | Existing bounded reconciliation/replay | Source generations, checkpoints, surviving bytes and settled losses | Recovery/retirement facts | Recoverable coverage resumes; irrecoverable intervals remain explicit |
| Handle exhausted or rejected data | Qualified oldest-eligible eviction and bounded rejection handling | Current preservation eligibility, pressure and original disposition | Independent loss/blocked dispositions | Newer ordinary context favored without held sole-copy deletion |
| Inspect coverage during failure | No mutation; protected read or trusted local supervision | Bounded stage/source/destination health and freshness | None from a read | A crashed reader/backend never appears healthy or complete-empty |

## Validation and permissions

Only the installation owner may enroll sources, alter credentials or repair/replay local spools. Browser log access
confers no storage/Docker administration. Isolate privileged reader/configuration/checkpoint surfaces from PHP,
Agents, managed projects and ordinary sinks; a read-only Docker socket mount is not authorization. Use private
transport and required authenticated/encrypted host-boundary communication without weakening TLS on failure.

Validate real source generations and supported driver/reader semantics, bounded framing and full accepted source/
level coverage. Producer labels cannot select arbitrary sources or widen access. Preservation participants consume
[TICKET-00114](00114-TICKET.md) and the existing evidence owner; unknown state blocks unsafe disposal. Public status
must not expose raw payloads/credentials or private metadata without current permission.

## Acceptance and evidence

- Exact selected reader/driver/collector versions, privileges and full-source/level coverage are qualified; a stateless
  Docker source plus disk sink is not accepted as durable capture evidence.
- Restart at checkpoint/persistence/partial-response boundaries, rotation and short-lived source retirement either
  recover original identities/bytes or expose attributable gaps; content/timestamp ties are not silently dropped.
- Independent sink outages, authentication/configuration failures, malformed/binary/large fragments, poison batches,
  HTTP 260 and WAL uncertainty retain truthful dispositions and bounded fair progress.
- Real physical exhaustion demonstrates oldest-eligible order, held-only refusal, persisted independent loss and
  no replay loop/hidden spool. Supervisor/status failure becomes unknown even when Loki is unavailable.
- Direct owning-tool qualification proves runtime/collector mechanisms and physical limits; owned behavior tests
  cover application contracts. Do not test configuration text or seed quality-tool failures. Integrate with
  TICKET-00117's declared workload and full `./bin/build`; tiny backend probes are not collection acceptance.

## Coordination

Supply source identity/framing/disposition/status contracts early. Consume early private-destination contracts from
[TICKET-00113](00113-TICKET.md), verified archive/preservation participation from [TICKET-00114](00114-TICKET.md),
and runtime/physical-limit constraints from [TICKET-00117](00117-TICKET.md). These are capability dependencies, not
requirements to finish their browsers or entire TICKETs before developing capture.

Receiving [TASK-00451](../tasks/00451-TASK.md) now names [TASK-00455](../tasks/00455-TASK.md)'s early bounded capture/
framing design and [TASK-00456](../tasks/00456-TASK.md)'s independent failure-status binding as explicit suppliers.
It remains `needs-info`: allocating suppliers does not qualify their mechanisms or waive its own output integration.
Neither supplier waits for completed producer/browser delivery.

No compliant collector/native queue/privilege mechanism is selected yet: dependent TASKs retain concrete blockers or return
an actual conflict to WF-038; this split authorizes no bespoke substitute or runtime experiment.

[TICKET-00110](00110-TICKET.md)'s trusted-human local Docker display remains independent; it is not this collector
and does not qualify coverage, retention or replay. Existing terminal/evidence work gains no reverse dependency.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00455](../tasks/00455-TASK.md) | Resolve native collection and privilege blockers | needs-info |
| [TASK-00456](../tasks/00456-TASK.md) | Expose collection degradation independently of logging | needs-info |
| [TASK-00457](../tasks/00457-TASK.md) | Preserve pending deliveries within bounded hold-aware queues | needs-info |
| [TASK-00458](../tasks/00458-TASK.md) | Capture and recover enrolled operational sources | needs-info |
| [TASK-00459](../tasks/00459-TASK.md) | Deliver captured logs to private search with truthful receipts | needs-info |
| [TASK-00460](../tasks/00460-TASK.md) | Deliver captured logs independently to verified archives | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement group in EPIC-00019's seven-TICKET split. That requirement checkpoint created no
TASK or runtime grant. Reader/native queue/physical enforcement and preservation integration remain unqualified.

### Approved TASK decomposition and current handoff

John separately invoked `to-tasks TICKET-00112` and answered **“approved”** to the six-outcome proposal. These records
are now written/accepted and cover this TICKET's complete collection scope:

| Slice | TASK / outcome | Dependencies and remaining input |
|---|---|---|
| A | [TASK-00455 — Resolve native collection and privilege blockers](../tasks/00455-TASK.md) | TASK-00467/00481/00482 early preservation/installation/physical inputs; explicit privileged-reader security acceptance remains required |
| B | [TASK-00456 — Expose collection degradation independently of logging](../tasks/00456-TASK.md) | TASK-00455/00483; actual independent supervision mechanisms remain unqualified |
| C | [TASK-00457 — Preserve pending deliveries within bounded hold-aware queues](../tasks/00457-TASK.md) | TASK-00455/00456/00467; concrete early eligibility/fence/verified-alternative mechanisms remain unqualified |
| D | [TASK-00458 — Capture and recover enrolled operational sources](../tasks/00458-TASK.md) | TASK-00455/00456/00457/00481/00483; actual native and managed-source registration/retirement bindings remain gated |
| E | [TASK-00459 — Deliver captured logs to private search with truthful receipts](../tasks/00459-TASK.md) | TASK-00457/00458/00461; mapped early private ingestion/disposition supplier, still unqualified |
| F | [TASK-00460 — Deliver captured logs independently to verified archives](../tasks/00460-TASK.md) | TASK-00457/00458/00468; mapped intake/verified-receipt supplier remains unqualified; actual search binding for paired outage acceptance |

All six are unranked and `needs-info` because concrete external inputs/mechanisms remain missing. This is accepted
TASK coverage, not executable supplier qualification. A removes a concrete design blocker rather than demonstrating
infrastructure for its own sake. B supplies real independent status; C owns finite persistence/loss/preservation;
D owns source identity/framing/checkpoints/replay/retirement; E/F own actual independent sink bindings and receipts.
Each owns relevant behavior/integration/direct-tool evidence and full build requirements.

TASK-00451 now has explicit `blocked_by: TASK-00455, TASK-00456`, retaining `needs-info` until its actual contracts and
qualification are satisfied. No whole collector/UI TICKET is substituted for those early suppliers. TASK-00454's
managed-runtime binding gate remains unchanged. Existing terminal-first and evidence owners gain no reverse edge.

E and F may implement independently. F owns paired live search/archive outage acceptance once both bindings exist;
E may close its own scoped work without claiming that unfinished paired result. This avoids an opposing acceptance
cycle and ensures the parent cannot close with paired independence unverified. TICKET-00117 retains final composed
installation/workload acceptance, not a replacement for any of these six TASKs' evidence.

Before allocation, fetched `origin/develop` and HEAD remained `ac41c28`. Live/archive paths across 18 available local/
remote refs and all four registered worktrees ended at TASK-00454 (committed refs at TASK-00450); no archived TASK
records were present. Only TASK-00455–00460 were allocated. Recheck future IDs; this snapshot reserves nothing else.
No runtime, privilege, policy, dependency, experiment or implementation is selected by writing these records.

**Current phase:** all seven TICKET-00111–00117 TASK decompositions are complete/accepted; no map planning phase remains. Later
splits map TASK-00461 to TASK-00459, TASK-00467 to TASK-00455/00457 and TASK-00468 to TASK-00460 as early suppliers;
all receiving qualification gates remain. These mappings do not introduce reverse dependencies on finished consumers.
TASK-00481/00482 now supply exact installation/physical inputs to TASK-00455, and TASK-00483 supplies actual runtime/
supervision to TASK-00456/00458; TASK-00458 also consumes TASK-00481 inventory. Existing managed-execution bindings
remain unresolved. All map planning phases are complete, not implementation or runtime acceptance.

### Collection-decomposition planning verification — historical checkpoint

`./bin/planning-check --write` and `./bin/planning-check` passed with **596 records, 491 active**, generated views
current. Tracked and explicit untracked planning Markdown whitespace checks passed. Scope checks confirmed six new
TICKET-00112 TASKs, unranked `needs-info` metadata and TASK-00451's explicit supplier edges; no tracked prior TASK/
TICKET source changed and no new IDs collided in other registered worktrees. The Board adds six Needs Info rows and
updates TASK-00451's blockers relative to the producer checkpoint; existing active/ready priority stays unchanged.

These are planning receipts only. No implementation, runtime qualification, full `./bin/build`, review, commit or
publication was performed. All changes remain uncommitted in the approved worktree; full build remains required
before commit/publication. Actual design/supervision/preservation/source/destination mechanisms remain gated.

Next: `/skill:next` in `.runs/worktrees/observability-planning/`, following
[TICKET-00117's completed planning handoff](00117-TICKET.md#approved-task-decomposition-and-current-handoff).
Consult the Board read-only without waiving qualification or starting implementation.
