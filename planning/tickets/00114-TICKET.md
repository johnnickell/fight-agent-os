---
id: TICKET-00114
epic: EPIC-00019
title: Preserve and expire verified operational logs safely
status: ready-for-agent
---

# Preserve and expire verified operational logs safely

## Problem statement

Operators need older diagnostic records even if the live index fails or expires. An upload acknowledgement does not
prove recoverable archives, while autonomous expiry can destroy protected evidence or conceal an unverified copy.
Retention, capacity and privacy cleanup must remain explicit across live, queued, archived and restored material.

## Solution and boundaries

Deliver [EPIC-00019](../epics/00019-EPIC.md)'s independent continuous archival and lifecycle participation under
[WF-039](../wayfinder/tickets/WF-039-define-searchable-retention-and-verified-archives.md),
[WF-040](../wayfinder/tickets/WF-040-select-the-log-search-backend.md) and the existing
[TICKET-00094](00094-TICKET.md)/WF-017 preservation/privacy owner. Reuse private S3 infrastructure; do not create a
second Artifact or Workflow authority, a permanent Artifact per log, or a new storage stack. Archives are independent
compressed records, not Loki chunks or an export of whatever survives day 14.

Use immutable versioned gzip UTF-8 JSONL segments with reversible exact captured bytes, native fields, source/
fragment identity, original clocks, preservation references, sizes/counts and SHA-256. Stable segment identity
survives retries. Partition by compatible authorization/preservation categories; mixed segments take the strictest/
latest eligibility unless an owner-authorized verified split preserves every protected member.

Persist intent and expected digest/size before upload, reconcile uncertain writes under the same operation, then
read back and verify compressed size/digest, decompression, raw size/digest, framing and count before publishing
catalog availability. Existing identity with different bytes is a conflict, never overwrite permission. HEAD/ETag/
upload success alone is insufficient. Interrupted intent/upload/verification/publication remains recoverable pending
work. A verified archive is not proof that every originally emitted record was captured or that restore is ready.

The independent archive destination uses TICKET-00112's existing 2-GiB queue within its 5-GiB collection allocation,
not another spool. Follow the complete [WF-039 finite storage profile](../wayfinder/tickets/WF-039-define-searchable-retention-and-verified-archives.md#finite-local-storage-and-outage-behavior):
256-KiB/5-second segment closure, at most 16 open segments, bounded requests, one verifier, bounded reconciliation,
50-GiB admission target and 64-GiB physical archive allocation including catalog/versions/compaction overhead.
Qualify real headroom with the installation owner; separate directories/logical quotas do not prove isolation.
Archive-full behavior reclaims eligible data only, then blocks new archive admission and uses bounded queue policy;
newest-context queue eviction never authorizes early deletion of verified archives or held sole copies.

Use trusted original collection time for independently configurable ordinary search/archive durations, initially
14/90 days, with positive whole-day validation through the accepted 3,650-day engineering ceiling. Keep emitted time
separate and flagged when unknown; retry/import never re-ages. Preview affected eligibility and record an effective
policy revision. Saving a shorter duration cannot release holds or delete content as a configuration side effect.
Existing owner-controlled holds and configurable default 30-day post-terminal grace remain authoritative; active,
paused, recoverable and Needs Human material stays protected. Awaiting review is not terminal cleanup eligibility.

Coordinate logical query eligibility separately from physical deletion. Protected copies require current owner
references and a verified surviving alternative before releasing the last staged/searchable copy. Ordinary unheld
search expiry need not wait indefinitely for failed archives: expose the gap, retain surviving bounded pending
copies and do not falsely advertise older retrieval. Preserve verified archives through their own eligibility.

Application-owned expiry/deletion must fence hold/release/purge/policy/restore/late-ingest changes throughout the
actual asynchronous effect, not only at precheck or cancellation windows. TICKET-00113's Loki deletion mechanism
must qualify that participation; autonomous TTL is not a hold implementation. Unknown authority disables unsafe
cleanup/publication; finite admission still applies. Track requested, processed and physically reclaimed separately.

Persist intents/catalog/policy and owner links through ordinary restart. Surviving manifests can inventory objects
but cannot reconstruct lost grants, holds or purge authority. Existing authorized exposure-response purge denies
and removes affected copies across catalog, indexes, temporary scopes, segments/versions and caches, with secret-free
tombstones and verified replacement when mixed segments require it. Never mutate bytes beneath an accepted digest
or keep prohibited payloads in tombstones. External downloads and separately owned backups cannot be recalled by
assertion. This controlled privacy operation is not pipeline redaction.

Exclude off-machine backup, generic storage administration, index snapshots, transcript ingestion and browser restore/
extraction UI. Local-only protection accepts host/storage loss; it does not certify power-loss durability or recovery.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Preserve captured records | Admit/reconcile immutable archive intent and bounded verification | Expected digest/size/provenance and original transfer result | Committed archive lifecycle facts, not Workflow authority | Independently verified catalog entry or explicit pending/conflict/failure |
| Inspect coverage | No business mutation | Protected bounded segment coverage/status/size and known gaps | None from reads | Private catalogue facts for restore/download with honest verification state |
| Change ordinary retention | Existing authorized policy-change operation after preview | Current policy/revision, impacted eligibility and holds | Committed policy revision under owner audit | Future eligibility changes; no hidden immediate deletion or hold release |
| Expire or purge eligible copies | Existing owner-authorized fenced cleanup/purge and reconciliation | Current references, leases, holds, verified alternatives and exact scope | Secret-free disposition/tombstone facts | Safe logical denial and attributable physical cleanup, or explicit blocked/uncertain state |
| Recover local archive state | Bounded original-intent/catalog reconciliation | Surviving content and independently recovered current authority | Reconciled lifecycle facts | Verified recovered coverage/gaps, never invented grants or blanket success |

## Validation and permissions

Archive writers and cleanup participants operate only within their enrolled installation capability. Log read,
restore, export and download permissions do not confer retention-policy, hold, purge, storage or Docker powers.
Use existing preservation/installation authority; an unqualified actor/operation/fence binding is a delivery blocker,
not a new generic admin bypass. Metadata/manifest/coverage are private and currently gated: restore consumers need
`READ_LOGS` + `RESTORE_LOG_ARCHIVES`; download consumers may inspect only the needed bounded catalog under
`READ_LOGS` + `DOWNLOAD_LOG_ARCHIVES`, without restore power.

Validate actual bytes and current ownership, not caller paths, declared size/digest, object names or stale metadata.
External storage effects stay outside business transactions with durable intent and original-outcome reconciliation
under [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md). Do not infer atomic storage/database delivery.

## Acceptance and evidence

- Controlled exact content verifies independently of live search; every interrupted intent/upload/readback/catalog
  boundary reconciles without changed bytes, fabricated success or queue release before durable disposition.
- Original clocks, independent settings and boundary dates behave correctly under late arrival, restart, policy
  revision, holds/grace and mixed segments. Unheld index expiry and verified-archive retention remain distinct.
- Real owner-fenced asynchronous cleanup handles concurrent hold/purge/policy/restore/late ingestion; unavailable
  authority and protected sole copies prevent unsafe effects, with bounded admission and visible capacity conflict.
- Full/read-only/corrupt/missing storage, failed catalog and lost owner state produce truthful recovery limits;
  manifests do not recreate authority. Purge/tombstone evidence includes actual copy and backup limitations.
- Exact storage durability/private credentials and physical capacity, framing/expansion bounds and retained-volume
  fit are qualified directly. The 50-GiB target is not a proved 90-day guarantee; real compression/overhead and held
  growth must fit or return to the finite-profile owner without silently reducing coverage/retention.
- Owned lifecycle/authorization/concurrency/recovery tests and full `./bin/build` accompany direct infrastructure
  evidence; no product tests of migrations, configuration or object-server internals.

## Coordination

Supply original-clock eligibility, owner-fence participation, private catalog and verified archive receipt contracts
early to [TICKET-00112](00112-TICKET.md), [TICKET-00113](00113-TICKET.md), [TICKET-00115](00115-TICKET.md) and
[TICKET-00116](00116-TICKET.md). Consume their exact source identities and copy/disposition participants, not completed
browser journeys. TICKET-00117 supplies private-storage/physical constraints and integrated recovery qualification.
Existing TICKET-00094 remains authority for immutable evidence, holds and privacy; its source requirements are not
rewritten. Missing native storage/fencing/authority mechanisms remain concrete blockers before dependent execution.

Receiving [TASK-00455](../tasks/00455-TASK.md), [TASK-00457](../tasks/00457-TASK.md),
[TASK-00461](../tasks/00461-TASK.md) and [TASK-00463](../tasks/00463-TASK.md) now explicitly depend on
[TASK-00467](../tasks/00467-TASK.md)'s early preservation/disclosure participants. [TASK-00460](../tasks/00460-TASK.md)
now explicitly depends on [TASK-00468](../tasks/00468-TASK.md)'s intake/verified receipts. All retain their information/
qualification gates. The early owner does not wait for archive ingestion; the writer does not wait for its finished
collector binding. Existing evidence authority is not duplicated. Actual native asynchronous deletion/disclosure
races still require joint owner evidence; naming TASKs does not establish a working fence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00467](../tasks/00467-TASK.md) | Bind operational logs to preservation and disclosure authority | needs-info |
| [TASK-00468](../tasks/00468-TASK.md) | Write and verify immutable operational archives | needs-info |
| [TASK-00469](../tasks/00469-TASK.md) | Preview and apply independent log-retention policies | needs-info |
| [TASK-00470](../tasks/00470-TASK.md) | Inspect authorized archive coverage and availability | ready-for-agent |
| [TASK-00471](../tasks/00471-TASK.md) | Expire eligible live and archived copies safely | needs-info |
| [TASK-00472](../tasks/00472-TASK.md) | Apply authorized exposure-response purge across log copies | needs-info |
| [TASK-00473](../tasks/00473-TASK.md) | Recover archive coverage without reconstructing authority | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement group in EPIC-00019's seven-TICKET split. That requirement checkpoint allocated no
TASKs. Accepted requirements do not establish verified archives, deployed SeaweedFS, safe backend deletion or a
runtime grant. The qualification-timing amendment moves evidence to delivery, not out of scope.

### Approved TASK decomposition and current handoff

John invoked `to-tasks TICKET-00114` in the selected worktree and answered **“approved”** to the seven-outcome proposal.
The following records are now written/accepted and cover this TICKET's complete agreed preservation/archive scope:

| Slice | TASK / outcome | Dependencies and current gate |
|---|---|---|
| A | [TASK-00467 — Bind operational logs to preservation and disclosure authority](../tasks/00467-TASK.md) | `needs-info`; TASK-00374/00481 plus actual early lifecycle/restriction participants from existing owners |
| B | [TASK-00468 — Write and verify immutable operational archives](../tasks/00468-TASK.md) | `needs-info`; A, TASK-00455/00457/00482/00483; actual private storage/resources/supervision remain unqualified |
| C | [TASK-00469 — Preview and apply independent log-retention policies](../tasks/00469-TASK.md) | `needs-info`; A, TASK-00379/00481; actual authorized operator binding remains unqualified |
| D | [TASK-00470 — Inspect authorized archive coverage and availability](../tasks/00470-TASK.md) | Decision-complete; A/B, TASK-00462/00463; separate restore/download permission combinations |
| E | [TASK-00471 — Expire eligible live and archived copies safely](../tasks/00471-TASK.md) | `needs-info`; A/B/C, TASK-00461/00474/00475/00477/00482/00483; actual asynchronous storage/restore-lease/supervision mechanisms remain unqualified |
| F | [TASK-00472 — Apply authorized exposure-response purge across log copies](../tasks/00472-TASK.md) | `needs-info`; A/B, TASK-00380/00457/00461/00463/00474/00475/00477/00478/00479/00482; actual extraction/output and storage-copy mechanisms remain gated |
| G | [TASK-00473 — Recover archive coverage without reconstructing authority](../tasks/00473-TASK.md) | `needs-info`; A/B/E, TASK-00481/00483; independently current restriction/tombstone recovery and native mechanisms remain gated |

A implements early actual owner participation, not a document-only promise or another preservation store. B owns
ordinary intent/upload/readback/catalog crash recovery and durable verified receipts. C delivers a real existing-owner
policy operation; D delivers intent-specific private catalog operations, not a demonstration UI. E owns actual ordinary
expiry/reclamation, F the separately authorized privacy operation, and G damaged/lost catalog and surviving-object
reconciliation. Each owns relevant security, bounded resources, tests, direct provider qualification and full build.
No later proof-only TASK defers an earlier slice's acceptance or leaves a parent-only qualification gap.

A supplies TASK-00455/00457/00461/00463; B supplies TASK-00460. Their metadata now names those suppliers while keeping
`needs-info`. Neither early supplier waits for its completed consumers. D consumes reusable current-authority/shared
read behavior from TASK-00463 and supplies TICKET-00115/00116 without requiring their completion. E/F need actual
restore/extraction participants, not their entire browser journeys. Early restrictions apply before exposure; F's
later all-copy operation cannot defer initial denial or reverse-block capture. G consumes early tombstones, not
completed all-copy purge as a blanket prerequisite.

All seven TASKs are unranked; six retain missing-capability gates and D waits for named prerequisites. Existing
Artifact, lifecycle, privacy, installation and Workflow owners remain authoritative. No old owner TASK metadata,
terminal-first priority or TASK-00449 scope changes. Physical storage, current authority and native asynchronous
mechanisms remain qualification gates; 50 GiB is not a proved 90-day capacity guarantee.

Before allocation, fetched `origin/develop` and the main checkout had advanced to `58b087b`; this selected branch
remained at `ac41c28`. The upstream diff is invitation-email delivery/documentation/planning, with no observability
ID collision. No rebase or upstream application adoption was performed. Live/archive paths across 17 available
local/remote refs and all four registered worktrees ended at TASK-00466 (committed refs TASK-00450), with no archived
TASK records. Only TASK-00467–00473 were allocated; recheck IDs and upstream state before future allocation/publication.

**Current phase:** all seven TICKET-00111–00117 TASK decompositions are complete/accepted. No map planning phase remains.
TICKET-00115 now maps TASK-00474/00475/00477 to TASK-00471/00472 as early lease/restriction and actual import/removal
participants without clearing qualification gates or adding reverse consumer dependencies. TICKET-00116 now maps
TASK-00478/00479 as TASK-00472's extraction/output suppliers; actual mechanisms remain unqualified. Decisions and
EPIC/TICKET phases remain complete; implementation/runtime qualification are outstanding. TASK-00481/00482/00483
now supply exact operator/storage/supervision dependencies in the table above; no gate is cleared by allocation.

### Archive-lifecycle planning verification — historical checkpoint

`./bin/planning-check --write` and `./bin/planning-check` passed with **609 records, 504 active**, generated views
current. Tracked and explicit untracked planning Markdown whitespace checks passed. Scope checks confirmed seven
new TICKET-00114 feature TASKs with blank order/pr, six `needs-info` gates and one decision-complete catalog slice;
five exact receiver supplier mappings retain their statuses. No tracked prior TASK/TICKET source changed, and no
new IDs collided in other registered worktrees. Existing local Active/Ready rows and priority remain unchanged.

Relative to the native-Logs checkpoint, the Board adds six Needs Info entries and one Waiting entry, and updates
the five receiver blocker lists. These are planning receipts only: no implementation, runtime qualification, full
`./bin/build`, independent review, commit or publication occurred. All changes remain uncommitted; full build and
upstream/ID reconciliation remain required before commit/publication. Fetched invitation-email delivery is not
claimed as application evidence in this older checkout.

Next: `/skill:next` in `.runs/worktrees/observability-planning/`, following
[TICKET-00117's completed planning handoff](00117-TICKET.md#approved-task-decomposition-and-current-handoff).
Consult the Board read-only; no implementation or runtime authority follows.
