# Define searchable retention and verified archives

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** [WF-038](WF-038-define-log-collection-and-failure-recovery.md)

## Question

What independent searchable and archival lifecycle preserves emitted logs durably, verifies delivery and lets
an authorized operator retrieve older material without confusing retention, backup and restoration?

## Accepted collection inputs

Closed [WF-038](WF-038-define-log-collection-and-failure-recovery.md) defines the finite collection profile,
independent per-destination pending/receipt state and John's newest-context exhaustion preference: evict oldest
eligible queued operational data with visible loss. It does not permit deleting the only preservation-protected
copy, count a forwarder/search receipt as verified archival or promise infinite buffering. Reconcile protected
copies and bounded failure handling explicitly; any archive fanout must fit a declared finite budget. Do not
silently weaken WF-017 preservation or infer archive availability from the planned Artifact service.

WF-037's no-pipeline-redaction contract governs: preserve emitted content, producer-side secret avoidance, explicit
operator access and no automatic log sanitization. John accepted the archive duration, local-only failure boundary
and temporary browser-restore journey below. The engineering contract resolves their lifecycle; no collector,
backend, storage deployment, backup destination or runtime implementation is selected.

## Accepted partial direction — ordinary archive retention

John approved **90 days from trusted collection time** as the default retention of ordinary operational archive
bytes, configurable independently of the accepted 14-day searchable window. The recommendation's tradeoff was
roughly a quarter of older diagnosis history without indefinite default retention of emitted, potentially sensitive
content. Approval settles the retention policy, not measured storage capacity or a service/backup selection.

The 90-day age is cleanup eligibility for otherwise unheld operational logs, not an override of existing
preservation. Active, paused, recoverable and Needs Human material stays protected. Applicable bulky run logs keep
at least the existing configurable 30-day post-terminal grace; evidence/security/recovery and other explicit holds
can extend preservation beyond both defaults. Index expiry never authorizes destruction of the only required copy.
Unknown lifecycle/hold state is not proof of cleanup eligibility. Backup expiry and physical-erasure limits remain
separate questions; approval does not promise every copy disappears exactly on day 90.

## Accepted partial direction — local-only initial archives

John chose **local-only to start**, rather than the assistant's recommendation for independently verified
off-machine backup. Initial operational archives must persist locally across ordinary service/process restarts,
but **complete host/storage loss can destroy the archives and their recovery metadata**. Another local container,
bucket or volume is not an independent host-loss backup. Do not claim a host-disaster recovery point or zero loss.

Off-machine backup/protection is deferred beyond the initial scope, not an implicit prerequisite or an automatically
created future TASK. No cloud egress, backup destination/provider, extra service or data transfer is authorized.
This choice concerns operational observability; it neither removes other owners' backup obligations nor weakens
WF-017/TICKET-00094's holds, preservation/grace, privacy or mandatory evidence rules. Local byte verification,
consistent catalog/lifecycle recovery and honest unavailable/corrupt/missing outcomes remain required.

Existing WF-017/TICKET-00094 and WF-023 establish private Artifact storage ownership and a planned local SeaweedFS
backend, not a qualified operational archive or measured capacity. Local archives still need the primary-source
and eventual implementation qualification below; no SeaweedFS integration, live deployment or storage guarantee
is inferred from the local-only choice. Archive bytes, catalog and policy/hold/purge metadata must be accounted
for together rather than assuming that compressed payloads alone permit consistent recovery.

## Accepted partial direction — browser search through temporary restore

John selected **browser search through temporary restore**, not compressed-download-only retrieval. An authorized
operator selects a retained time/source window, waits for bounded verification/import, then investigates with log
search, filters, context and detail in an explicitly temporary archive scope. Restored material stays separate
from the live index, exposes pending/partial/missing/corrupt outcomes and expires automatically. This is not a
permanent 90-day search index, off-machine service or permission to change original record timestamps.

Current archive-restore and log-query permissions apply separately; links, job IDs and a successful restore grant
no new access or storage administration. Downloads/exports remain separately authorized, not the only older-log
journey. WF-041 owns the browser integration, and WF-040 must qualify the actual backend import/isolation APIs.
A qualified native equivalent can meet the bounded temporary-search journey; no unverified API is assumed.

## Must decide

- Fourteen days searchable by default with independent configuration; distinguish event-time/index-time handling,
  late data, current search availability, archive retention and preservation obligations.
- Set an archive default and configurable bounds explicitly. Preserve WF-017's active/paused/recoverable/Needs Human
  material, existing default 30-day post-terminal grace and evidence/security holds. Index expiry is not byte purge.
  Propose explicit amendments if any agreed journey requires changing an existing category.
- Evaluate continuous compressed archival before search expiry rather than relying on a deadline export. Define
  batch/segment identity and format, provenance, time/source/authorization partitions, manifest, digest/size and
  content verification, acknowledgements, idempotent retry and reconciliation of uncertain writes. Upload success
  alone is not verified delivery or demonstrated restorability.
- Specify archive failure, backlog, unavailable storage, quota exhaustion and retention-deadline behavior with
  finite disk limits. Decide when material is preserved, search expiry is delayed or a bounded loss policy needs
  human action; no silent deletion, success claim or unbounded fail-open disk growth.
- Evaluate planned private SeaweedFS for archives against actual required APIs, durability, deployment availability,
  compatibility and failure/recovery guarantees. Do not assume all S3-shaped products or log backends interoperate,
  or make this map another Artifact storage authority.
- Choose older-log retrieval: authorized compressed download, direct archival search or bounded temporary restore
  for querying. Define discoverability, requested window/source, current authorization, asynchronous completion,
  restore TTL/quotas, verification and unavailable/corrupt/missing outcomes.
- Separate operational archives from backup of search state, archive bytes/catalog and policy metadata. Define
  failure domains, restoration evidence, retention/deletion across replicas/backups and honest physical-erasure
  limits. Compression and same-volume duplication are not backup.
- Coordinate holds, authorized deletion, exposure-response/purge and safe tombstones across indexes, caches,
  exports, restored data and archives using existing evidence/privacy owners. Do not reinstate automatic pipeline
  redaction; surface any actual policy conflict explicitly. Log read/export rights do not authorize storage admin.

## Resolution boundary

Agree lifecycle and required storage/retrieval guarantees, not buckets, services, policy enrollment or live export.
Consume WF-017 and [TICKET-00094](../../tickets/00094-TICKET.md); do not shorten their preservation implicitly.
WF-040 qualifies backend-specific fit; conflicts return for explicit reconciliation. Installation/storage outages
and eventual implementation evidence remain honest constraints rather than assumptions of availability.

## Evidence and storage direction

[Local operational archives and temporary restore](../research/operational-log-local-archives-and-restore.md) records
primary-source research of SeaweedFS 4.48 and its upstream wiki. Basic object APIs are plausible archive primitives,
not composed qualification. Its API matrix does not support S3 RestoreObject or SelectObjectContent; temporary
browser search needs a qualified backend import/query path. Mini defaults and periodic bucket quotas do not prove
private access or hard physical storage limits. Native expiry uses PUT-time clocks, not our collection-time policy.

Prefer reusing the planned private SeaweedFS object capability if qualification succeeds, with dedicated archive
namespace/credentials/quotas isolated from authoritative Artifacts. No second general object-storage product is
introduced. WF-040 must verify actual release, deployment availability, API semantics, synchronization/durability,
security and resource fit. A material mismatch returns here rather than silently relaxing preservation or assuming
that an advertised S3-compatible service is already running. No installation or runtime grant follows.

## Lifecycle and verification contract

### Continuous archival and receipts

Archive captured records continuously alongside search delivery, not by exporting whatever remains on day 14.
Use WF-038's independently bounded archive delivery allocation and separate acknowledgement from search. A slow or
broken archive must not deliberately stop search delivery or unrelated business operations; shared faults remain
possible. Preserve emitted content without pipeline redaction, including malformed/raw/fragment records and actual
source provenance. Producer encoding failure or source loss before capture remains an explicit gap, not recoverable
archive content.

Use compressed UTF-8 JSON Lines segments with a versioned transport envelope, preserving native record fields and
exact captured bytes through reversible encoding where required. Gzip is the initial engineering format. Do not
claim parsed-and-reserialized JSON is the original byte stream. No new runner telemetry protocol follows. Segment
identity is stable across retry; use an owning ID factory/UUID library, not fabricated literals. Manifest fields
include format/compression version, source generation/stream, collection and available event-time ranges, record/
fragment count, compressed/uncompressed size, SHA-256 digests, original identities and preservation-owner references.
Missing provenance/chronology stays unknown. Metadata and manifests are private diagnostic data too.

Partition by installation/source and compatible authorization/preservation category, then bounded collection-time
windows. Where one segment contains multiple applicable lifecycles, the strictest/latest preservation applies to
the whole segment until an owner-authorized, verified split; never discard protected members to keep a batch small.
Do not blindly create a permanent Artifact for every log record or duplicate Workflow authority in the catalog.

Persist transfer intent and expected digest/size before upload. Write immutable content under the same segment
identity on retry; an existing object with different bytes is a conflict, not permission to overwrite it. On an
uncertain write, reconcile that operation by bounded readback. Verify compressed size/digest, decompression,
uncompressed digest/size, envelope framing and count before marking that segment archive-verified. ETag, HEAD,
metadata checksum or upload success alone is insufficient. Publish catalog availability only after verification;
a crash between storage and catalog publication remains reconcilable pending work. Verification does not prove
all originally emitted records were captured or that a browser import succeeds.

A transfer queue may release its copy only after the archive disposition is durably known. A protected entry needs
independently verified preservation in the correct owner/lifecycle, not merely a search acknowledgement. Local
readback confirms currently retrievable bytes; power-loss durability still requires the selected storage sync,
filesystem and restart contract to be qualified. No zero-loss guarantee follows from HTTP success.

### Time, retention and deletion eligibility

Use trusted **original collection time** for the ordinary 14-day live-search and 90-day archive clocks; keep emitted
event time separately for diagnosis. This prevents untrusted/future timestamps controlling expiry. Preserve both
clocks and the available event-time range in the catalog. Late capture receives its initial collection clock once;
retry, index lag, backend import and temporary restore never reset it. Future/malformed event time stays flagged,
not rewritten into invented chronology. Backend timestamp/retention limitations must be reconciled in WF-040.

Search and archive durations are independently configurable positive whole days, initially 14 and 90; use a finite
engineering validation ceiling of 3,650 days per setting, not unlimited retention. Holds are owner-controlled and
can outlast those ordinary limits. Increasing this engineering ceiling requires explicit capacity assessment, not
permission to shorten a hold. Changes have an effective policy revision and preview affected eligibility; reducing
a duration never silently releases holds or deletes bytes as a side effect of saving configuration.

- Ordinary archive cleanup starts no earlier than the original collection time plus the archive duration, and no
  earlier than any applicable terminal grace or hold release. Segments use the latest member eligibility. Missing
  terminal/hold state is not eligibility. Authorized exceptional early deletion remains with existing owners.
- Expiring an index entry is not byte purge. For protected material, confirm an available verified preserved copy
  with current owner references before releasing its last searchable/staged copy. If verification is unavailable,
  suspend that affected cleanup and report why; do not assume native backend TTL implements holds.
- For ordinary, unheld records, failed archival does not extend the live index indefinitely: at search expiry,
  expose the archive gap and retain any surviving bounded pending copy. Do not falsely advertise older retrieval.
  This does not authorize deleting already-verified archives before their accepted eligibility.
- Recheck and fence current holds/references/restore leases immediately before destructive effects; retry uncertain
  deletion as the same operation. Preserve secret-free tombstones and original outcome. Native bucket/volume TTL
  may not bypass this policy. Index expiry, logical denial, confirmed object/version deletion and physical storage
  reclamation are distinct states.

### Finite local storage and outage behavior

These are configurable engineering starting limits, not measured capacity or throughput. WF-040 must account for
real format/index/metadata/compaction overhead and explicit aggregate headroom alongside existing Artifact/DB/runtime
storage. A logical bucket quota or another directory on the same full filesystem is not physical isolation.

| Boundary | Initial engineering envelope |
|---|---|
| Collection-to-archive backlog | WF-038's 2 GiB destination queue within its 5 GiB collection allocation; no additional hidden spool. |
| Segment and staging | Close a segment at 256 KiB uncompressed framed bytes or 5 seconds, whichever occurs first. Compressed object plus metadata/request envelope must fit WF-038's 1 MiB delivery bound; split at record/fragment boundaries if needed. At most 16 open segments; staging counts against the collection allocation. |
| Local archive allocation | 50 GiB admission target for compressed objects, manifests and catalog accounting; 64 GiB physical archive allocation including metadata/versions/staging/compaction reserve. Enforce real headroom or revise the finite profile before deployment. This does not reserve 64 GiB inside an unqualified shared Artifact volume. |
| Archive verification work | One segment verification at a time; 10-second request deadline and WF-038's bounded backoff. Unconfirmed work remains pending, never successful merely because a retry budget elapsed. |
| Pressure visibility | Warn at 75% and high pressure at 90% of the applicable limit; expose used bytes, eligible cleanup and preservation-pinned bytes separately. No unbounded reserve for held logs. |
| Local recovery/reconciliation | Batches of at most 100 segments or 30 seconds per pass, one pass in flight. Persist progress; uncertain/missing owner metadata blocks unsafe deletion, not unrelated application work. |

At archive capacity, first reclaim only confirmed eligible data and expired temporary copies through their owning
cleanup paths. If this cannot free space, stop admitting archive writes, mark archival degraded and use only the
bounded pending queue. **Do not reinterpret newest-queue eviction as permission to shorten verified archives below
90 days or override holds.** The queue retains WF-038's oldest-eligible eviction for unpreserved ordinary entries,
with visible losses. If it contains only protected sole copies, no eviction is permitted: surface the preservation
incident and let the owning mandatory-evidence/recovery policy handle affected work. Do not deliberately fail
unrelated operations or claim unlimited protected/new output can survive finite storage.

Capacity estimates must include measured byte rates over the retention horizon, current pinned material and
physical overhead; reduce admission/report gaps rather than call an undersized installation fully covered. Changing
retention or deleting otherwise ineligible data to relieve pressure requires the existing authorized policy action,
not an automatic emergency cleanup. Storage absence, read-only/full disk, corruption, catalog failure and partial
verification remain distinct visible causes. No archive outage grants new Docker or storage-administration rights.

### Authorized temporary browser restore

The operator selects time range and sources from a private archive coverage catalog, with available event-time versus
collection-time basis explicit. Missing source timestamps cannot be inferred to match an event-time window. Resolve
only catalog-owned objects, not caller-supplied paths/URLs or arbitrary bucket keys. Show expected coverage, known
gaps and estimated size; unknown estimates require bounded discovery, not unlimited retrieval.

An application-owned asynchronous restore operation records requester, exact selected segments, current permissions,
limits and expiry. Recheck authority on execution and every status/query/detail/export access; revoke access promptly
when permission is lost. Verify selected segments again before streaming bounded decompression/import into an
isolated temporary search scope. Preserve original clocks/IDs/provenance and mark the restore observation separately.
Never merge imports into the live tail or replay commands/events/business operations. Backend import must accept
old record times without disguising them as new activity; this is a qualification requirement, not a claim of support.

| Boundary | Initial engineering envelope |
|---|---|
| Selection | Up to 7 days per request and 1 GiB decompressed input; larger retained periods require explicit smaller selections, not hidden unlimited job fanout. |
| Jobs | One running restore per installation, at most 4 pending; one outstanding job per requester. At most 4 ready temporary scopes. Return busy/limit status rather than silently accepting unbounded work. |
| Temporary resources | 4 GiB aggregate physical disk for temporary imports, indexes and staging, separately counted from archive/collection/live-search budgets. One restore worker capped at 512 MiB and 1 vCPU. Backend query/index resources must also be bounded and measured in WF-040. |
| Time | Pending jobs expire after 1 hour; execution gets 15 minutes, with 30-second bounded storage/import requests. A ready scope expires 24 hours after becoming ready; no sliding extension or silent renewal. |
| Query and cleanup | Queries at most 30 seconds, pages at most 500 events/1 MiB, at most 2 restore-scope queries in flight. A single oversized detail uses qualified bounded raw/fragment retrieval, not silent truncation. Cleanup reconciles at least every 5 minutes while healthy. |

Enforce actual decompressed byte and physical disk counters, not only manifest-declared size; detect invalid gzip,
framing, unexpected count/digest and decompression expansion. Stop with an explicit incomplete/too-large/corrupt
outcome without crashing or reading arbitrary filesystem paths. Failed/cancelled/expired scopes are inaccessible;
partial successful imports must never appear complete. Expose which verified subsets, if any, are deliberately
published as partial rather than silently serving a truncated result set.

Take bounded lifecycle leases while reading source segments; restore admission cannot indefinitely extend primary
retention. Recheck purge/hold changes before publishing and serving restored content. Temporary expiry revokes
query access immediately even if physical cleanup is delayed or the backend is unavailable. Count leftovers against
the allocation and reject new jobs if necessary. A restore is a disposable projection, not a new preservation owner;
required evidence must be retained by its existing owner, not by keeping temporary scopes forever.

### Local recovery, privacy and deletion

Persist the catalog, transfer/verification status, policy revision and links to authoritative hold/purge owners across
ordinary restarts. Object manifests can help inventory/reconcile surviving segments but cannot reconstruct missing
current grants, holds or purge authority. If that state is lost or inconsistent, keep content private, mark coverage
unknown and disable unsafe cleanup/restore publication until the owning state is recovered. Search indexes are
rebuildable projections only for bytes that actually survive and verify. Report the coverage recovered and gaps,
not a generic restoration-success claim. Do not start a second Workflow/Artifact authority.

No independent backup or full search-index snapshot service is added in this initial scope. Any pre-existing backup
retains its own owner and actual recovery evidence; do not count it as observability protection without qualification.
Local-only durability protects only its demonstrated restart/storage failure classes. Loss of host, volume, catalog
or required credentials/keys can make archives irrecoverable; neither compression nor same-host duplication fixes it.

No automatic pipeline redaction is introduced. When the existing privacy/evidence owner authorizes exposure-response
purge, coordinate denial/deletion across catalog, live and temporary indexes, archive segments/versions, caches and
owned export copies. Mixed segments may require a verified replacement excluding purged content plus an attributed
tombstone; never mutate content beneath an accepted digest. Retain no prohibited payload in tombstones. This is
controlled incident handling, not a reversal of preserving emitted records during ordinary collection. External
operator downloads and independently managed backups cannot be remotely erased by assertion; disclose actual limits.
Any conflict with another owner's accepted privacy contract requires explicit reconciliation, not a silent amendment.

## Resolution and qualification handoff

Closed as a lifecycle planning contract following John's explicit approvals of **90 days**, **local-only initially**
and **browser search through temporary restore**. The engineering design above supplies bounded mechanisms under
those choices; it does not assert human approval of individual library knobs or measured fitness of its defaults.
WF-017/TICKET-00094 preservation and authority remain unchanged. Off-machine protection stays deferred.

[WF-040](WF-040-select-the-log-search-backend.md) next qualifies exact collector/archive/backend versions and their
composed capability/resource fit, including dual-destination delivery, current-authorized temporary import/query,
old timestamps, finite physical resources, private storage and recoverable catalog/lifecycle handling. If a native
backend cannot implement hold-aware expiry or bounded old-log restore, return that conflict explicitly; do not
silently substitute download-only or promise an unsupported API. No backend or collector is selected here.

Future evidence must demonstrate continuous archival during search outage; independent receipt/verification and
uncertain-write recovery; restart across intent/upload/verification/catalog boundaries; malformed/raw/oversized
round trips; late/replayed timestamps; archive capacity and protected-only queues; concurrent hold/purge/restore/
expiry; corruption/decompression limits; current authorization and revoked/expired scope denial; local catalog/data
reconciliation; and actual temporary-search context/detail fidelity. Validate storage/runtime tooling directly with
its owning tools; application behavior tests belong to the eventual owned use cases, not configuration-text tests.
WF-041 owns browser integration and WF-042 integrated acceptance. Live experiments still need separately bounded
authority. No runtime installation, transfer, implementation, benchmark, full build or publication occurred here.

Historical next: `/skill:wayfinder work WF-040`.

## Approved qualification-timing amendment

John approved [WF-040's conditional Loki planning selection and explicit timing amendment](WF-040-select-the-log-search-backend.md#accepted-resolution--conditional-planning-selection).
The earlier assignment of complete composed runtime/application qualification before backend planning selection
is superseded **only in timing**. Archive/storage, preservation and restore checks remain mandatory integration/
delivery gates carried into [WF-042](WF-042-bound-observability-delivery-and-acceptance.md#mandatory-qualification-gates-carried-from-wf-040),
with concrete owners and verification paths before delivery work is executable.

The selected design direction uses application-owned expiry/deletion under existing preservation/privacy owners,
not autonomous Loki day-14 deletion. Its native explicit-deletion mode without age-based expiry is a mechanism to
qualify, not unlimited user retention. Owner-supported fencing and verified-copy checks must cover asynchronous
cleanup races; if that contract is unavailable, unsafe cleanup remains blocked. Fourteen-day live search, 90-day
ordinary verified archives, holds/grace, local-only initial protection and bounded authorized temporary browser
restore remain unchanged. Loki chunks/indexes are not the accepted gzip/JSONL archive or its lifecycle authority.

SeaweedFS/S3 compatibility, private permissions, durable intent/readback/catalog recovery, physical capacity and
restore readiness/revocation/cleanup are not proven by the small backend probe. No storage deployment, collector,
new authority grant or additional runtime experiment is approved. This decision remains Closed as its existing
lifecycle contract; final product qualification has not been claimed.

Next: `/skill:wayfinder work WF-041`.
