# Define log collection and failure recovery

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** [WF-037](WF-037-define-redacted-diagnostics-and-shared-http-adoption.md)

## Question

How are the selected sources collected with bounded resource use, honest delivery status and recoverable failure
behavior across application, process, collector and storage restarts?

## Accepted upstream constraints

WF-036/037 select Agent OS's own application activity, all its Monolog channels/levels, supporting services and
managed-run/check output—not registered-project runtime logs, JavaScript-error ingestion or transcripts. Preserve
emitted content without pipeline redaction; producers avoid deliberate secret emission. Use native Monolog records,
JSON/exception normalization and trusted available identifiers; existing API responses stay unchanged.

John's resilience intent is to keep as much working as possible and not deliberately fail unrelated operations
because logging fails. Model actual shared-resource and dependency failures; do not promise all operations survive.
Make logging degradation/loss uncertainty visible without relying solely on the failed logger. Mandatory audit,
authorization and execution-evidence behavior stays with its existing owners. Routine library settings need
engineering judgment; surface only concrete product/security/operational tradeoffs.

## Must decide

- Prefer structured stdout/stderr and explicitly justify any application-managed files. Define collection ownership
  and minimum privileged access without granting application/browser/Agent sessions general Docker authority.
- Define durable buffering/spooling ownership, acknowledgement boundaries, source rotation and cursor/checkpoint
  persistence. State which crashes and restarts can be recovered, and which loss windows remain possible.
- Set explicit finite disk/memory/time/concurrency limits and behavior at exhaustion: backpressure, bounded retry,
  rejection or dropping with visible gaps. Distinguish ordinary operation from preservation-critical evidence;
  no telemetry receipt may advance Workflow truth or satisfy an evidence hold by itself.
- Define identity/idempotency where sources support it, handling duplicates, delayed/out-of-order events, timestamps
  and surrounding-context ordering without claiming exactly-once delivery or inventing missing chronology.
- Define observable gaps, source enrollment/last-seen status, collector lag and failures. A disconnected tail or an
  empty search must not imply absence of failures or complete collection.
- Define behavior for malformed/oversized events, failed encoding/normalization, collector outage, search outage,
  storage failure and unavailable archives. Preserve independent diagnostic-failure signaling without recursion or
  failed raw payloads; do not reinstate redaction or silent message/trace truncation as a recovery mechanism.
- Identify source and buffer tamper/access boundaries and authorized replay, including retries after uncertain
  acknowledgements. Keep recovery evidence and implementation qualification requirements explicit.

## Resolution boundary

Choose delivery and failure semantics, required capabilities and measurable objectives. Do not install a collector,
change Docker logging/runtime configuration, select a backend or build a custom runner telemetry protocol without
concrete need. WF-039 owns verified archival and retention consequences; WF-040 must qualify product support against
this contract. Preserve WF-017 recovery from authoritative state rather than completeness of operational logs.

## Evidence and qualification limits

[Operational log collection and recovery boundaries](../research/operational-log-collection-recovery-boundaries.md)
records the initial primary-source research and repository baseline. Current Compose has no selected collector or
explicit logging-driver configuration; the live daemon was not inspected. Docker documentation distinguishes local
retention, blocking/non-blocking delivery and source loss. Vector's Docker source is documented as stateless/best
effort without acknowledgements, even when its downstream sink has a disk buffer. Alloy documents persistent Docker
source positions, but its inspected writer WAL/queue settings have experimental/default expiry/retry limitations.
No candidate is selected, installed, benchmarked or qualified. Read-only documentation/source evidence must not be
presented as a composed recovery receipt.

## Collection/recovery contract

- One installation-owned collection boundary enrolls only Agent OS services and managed-run/check sources. Prefer
  stdout/stderr and supported runtime log-reading interfaces, not application-managed files, arbitrary global
  container discovery or direct scraping of Docker's private log-file format. A collector-owned spool is not an
  application-managed log file. A privileged source reader, if needed, remains isolated from application/browser/
  Agent authority; a read-only Docker socket mount is not a read-only Docker API policy.
- Separate bounded runtime retention from a persistent collector delivery queue. The application does not call the
  search/archive backend inline. Persist source identity/position and pending delivery where the adapter supports
  it, and qualify their actual ordering/atomicity. Require replay after collector restart within retained-source
  coverage, not just a stateless live stream that begins at the new process's startup time. Capture managed output
  before source retirement; define a bounded retirement/reconciliation path, not silent deletion of uncollected data.
- Track the boundaries honestly: application emission, runtime retention, collector receipt/persistence, destination
  acceptance, search availability and archive confirmation are different. Do not claim per-record confirmation
  where the source cannot provide it, or exactly-once delivery. Retry unconfirmed persisted deliveries; duplicates
  and delayed/out-of-order records are possible. Preserve original record/message identity; do not deduplicate by
  message text, timestamp or content hash and erase legitimate repeated operations.
- Keep raw emitted content and provenance. Malformed JSON must remain an authorized raw record with parsing-error
  metadata, not disappear. Use existing source framing/partial-message facilities for oversized output and report
  incomplete assembly; do not silently truncate stack traces or invent a custom runner telemetry protocol. Apply
  the finite framing/assembly and raw/rejected-data budgets below; these bound transport, not producer messages.
- Expose enrolled source/last-seen state, lag, queue usage, restart/replay state, parse failures, destination failures
  and known/potential gaps. An empty search, connected tail, healthy process or configuration-valid component is
  not proof of complete collection. Independent bounded supervisor/status signaling must remain possible when the
  main logger or destination is broken. Counts/intervals that cannot be known stay explicitly unknown. This does
  not implicitly add an alerting/metrics platform.
- Local persistence protects only the fault classes the storage/source support; it does not survive destruction of
  the host/volume. WF-039 owns continuous archive/off-machine recovery and independently confirmed destinations.
  The 14-day searchable default is not a promise that a local delivery queue holds 14 days or an unbounded outage.

No source adapter, collector product or new Docker grant is selected by this contract. The limits below are
engineering starting defaults, not a volume benchmark, promised outage duration or a separately approved SLA.
Derive actual outage coverage from measured ingress, delivery, source retention and physical overhead. WF-040 must
qualify the composed implementation; product-specific limitations return explicitly rather than silently weakening
this contract.

## Accepted partial direction — favor newer logs at exhaustion

John selected **option 2: overwrite the oldest queued logs to keep collecting newer context**. This supersedes the
assistant's backlog-preservation recommendation. Apply it to the bounded ordinary operational delivery queue during
capacity exhaustion, not as normal filtering, sampling, severity suppression or a change to the selected source set.

- Evict the oldest eligible queued entries/chunks and admit newer records. Qualify the actual collector's eviction
  granularity and ordering; do not substitute drop-newest or an unrelated age-expiry policy and call it equivalent.
- Make eviction/loss visible through the independent bounded status path: reason, affected source/destination,
  known time range, bytes/entries/chunks actually known and any uncertainty. A loss report only in the queue being
  overwritten is insufficient. Never mark an evicted, unconfirmed record as delivered, searchable or archived.
- Persist enough cursor/lifecycle information to distinguish intentional exhaustion eviction from confirmed
  delivery. Do not automatically rewind and refill a saturated queue with the same old records, defeating the
  chosen newest-first coverage. Later backfill is a separate authorized recovery action only where source/archive
  bytes actually remain available; do not promise that evicted content can be recovered.
- Keep as much application functionality working as possible; do not turn queue exhaustion into an intentional
  failure of unrelated operations. Shared disk/memory/runtime faults still require honest fault-specific handling.
- This selection does not override WF-017's active/recoverable-material preservation, evidence/security holds or
  post-terminal grace, nor authorize deleting archives/authoritative evidence. If a queue entry is the only copy
  subject to preservation, it is not eligible for routine eviction without independently confirmed preservation
  elsewhere or an explicitly approved policy amendment. WF-039 must reconcile this boundary; no archive copy or
  ability to evict protected bytes is assumed here.

## Engineering limits and recovery rules

### Source ownership and privileged boundary

The trusted installation runtime owns source enrollment and retirement, using actual installation/service and
managed-process identities. Reuse that inventory rather than treating a project ID or arbitrary container label
as permission to ingest everything in the project. Source enrollment covers all accepted records/channels/levels;
reader concurrency is a scheduling bound, not a reason to omit extra sources. Register short-lived managed sources
through their existing runtime lifecycle, with periodic discovery as reconciliation, not their only opportunity
for capture. Do not auto-enroll registered-project daemons or attach to Agent sessions/transcripts.

Keep privileged runtime log-reading at the trusted installation boundary. The buffering/delivery collector, PHP
request path, browser and Agents need no general Docker socket or credentials. Prefer an existing supported
constrained reader/adapter; authorize only enrolled source logs and necessary source metadata. Never expose exec,
attach/stdin, filesystem/archive reads, mutation, or credential/environment inspection through the log capability.
If a stock source requires the full daemon socket, isolate its reader as a trusted installation component and
return the root-equivalent exposure for explicit security acceptance before selecting/enrolling that configuration.
A source filter or read-only socket mount is not that acceptance. Do not invent a custom proxy or logging protocol
to hide an unqualified permission gap. No new runtime grant is enacted by this planning resolution.

Local runtime capture/rotation must not depend on a remote search/archive connection. Do not select a remote Docker
logging driver that prevents container startup when storage is unavailable. Supported driver/API behavior must be
verified together; this decision does not infer a tested `local`/Docker API combination from documentation alone.
Local logging I/O may still stall/fail under real disk/runtime faults; no non-blocking mode is declared lossless.

### Initial finite profile

Defaults are configurable positive finite installation values. A change in engineering size is not a new product
approval gate; a change in loss, access or preservation policy is. Invalid collection settings make coverage
unavailable/degraded, not an instruction to fail unrelated application startup. All components' memory, temporary
files and physical storage must be accounted for; vendor payload/chunk limits alone are insufficient.

| Boundary | Initial engineering limit and behavior |
|---|---|
| Runtime-retained logs | Rotation target 100 MiB per enrolled container/source; include driver overhead/overshoot when qualifying settings. Expose the aggregate for the actual runtime inventory and its existing provisioning quotas; this is not a separate 100 MiB whole-installation guarantee. No unlimited default `json-file` growth. |
| Ordinary persistent delivery | 2 GiB queued-data budget per enabled destination, initially search. Reserve a separately bounded destination queue if WF-039 enables archival; initial envelope allows two destinations, at most 4 GiB ordinary queued data. One destination's outage must not force the other to wait for its acknowledgement. |
| Raw/rejected-data holding | 256 MiB total across the collection unit, with the same oldest-eligible/newest-context exhaustion rule. This is not an unlimited DLQ, archive or Artifact authority. |
| Physical collection storage | 5 GiB total enforced collection allocation, covering both queues, holding area, checkpoints, format/temporary-file overhead and a 16 MiB reserved control/loss journal. Runtime driver storage and search/archive storage are separate, explicitly counted budgets. A documented logical queue size is not proof of a filesystem quota/reservation. |
| Collection process resources | 512 MiB memory and 1 vCPU aggregate for dedicated collection processes, including a separate reader if used. Supervision remains outside the collector process. Qualify actual peak/parser/plugin overhead; reaching the limit may restart the collector and create source lag. No unbounded in-memory fallback. |
| Concurrency | At most 16 active source reads and 2 destination delivery requests across the collection unit. Schedule enrolled sources fairly; rotate source follow leases within 30 seconds so quiet streams cannot occupy every slot indefinitely. Pending sources remain visible and recover from retained data where possible. |
| Framing and assembly | Prefer existing source partial-frame support. Raw transport pieces at most 64 KiB; parsed/assembled-event working budget 1 MiB and 5 seconds per event. At either assembly limit, preserve bounded raw continuation pieces and mark assembly incomplete rather than discard the suffix or silently truncate a trace. Budgets apply to memory/transport, not a producer message-size ban. |
| Delivery batches and time | At most 1 MiB encoded request/batch including envelope overhead; connection establishment 3 seconds, whole delivery attempt 10 seconds. Reduce batch size or use qualified native framing for a smaller destination limit. Long-lived source reads use the bounded leases above, not an infinite blocking attempt. |
| Persistence/checkpoints | Configure a 1-second flush/checkpoint cadence. Only a verified storage synchronization boundary warrants `collector-persisted`; the cadence is not a guaranteed one-second RPO under process/host/storage stalls. Checkpoint advancement must not skip data that is neither durably pending nor given a recorded confirmed-delivery/eviction disposition. |
| Retry and shutdown | Exponential backoff with jitter, 1–60 seconds between retryable attempts; no count-based silent discard of otherwise valid pending data. Attempts/concurrency remain bounded; queued data may age until delivery or approved capacity eviction. Graceful shutdown gets 30 seconds, then stops with persistent work left for restart rather than draining forever. |
| Discovery and independent health | Reconcile enrollment every 30 seconds, in addition to runtime lifecycle registration. An independent supervisor checks collector/destination progress every 5 seconds; three missed observations mark coverage unknown/unavailable. A quiet source's old last-record timestamp is not alone proof of failure. |

Use native existing collector/chunk facilities to implement these bounds. Exact-byte preservation can use reversible
encoding for non-UTF-8 bytes; it must not become replacement characters silently destroying source content. Store
trusted source/stream/fragment provenance separately from untrusted parsed fields. If native framing cannot preserve
an oversized event within the profile, the candidate is not qualified; do not approve a bespoke serializer by default.
An event larger than the entire retained allocation cannot be promised complete: remaining fragments and the gap
must be explicit. Neither malformed data nor a full rejected-data holding area may consume unbounded resources.

The 5 GiB collection envelope is a deployment requirement, not a claim that current Compose/Docker Desktop enforces
it. WF-040 must show actual supported enforcement/headroom and account for source-count/driver storage. Lack of a
portable quota or sufficient capacity requires a revised finite engineering profile with explicit evidence, not an
unbounded fallback. No hours/days of outage coverage, CPU sufficiency or throughput are asserted without measurement.

### Acknowledgements, restarts and source retirement

- Keep source read position, collector persistence and each destination's delivery disposition distinct. Advance a
  persistent source checkpoint only after the covered bytes are recoverable from the persistent spool or have a
  durably recorded settled disposition. Require crash-safe ordering or overlap replay that avoids checkpoint-ahead
  gaps. Existing native positions/WAL facilities must be qualified together, not assumed atomic across products.
- A sink's positive response means only what its pinned contract confirms. Record partial/rejected batches and
  uncertain responses; retry uncertain deliveries with existing idempotency identity where supported. Do not claim
  that HTTP success proves durable search, searchable indexing or independently verified archival.
- Restart from persistent source checkpoints and pending spool state while the source generation/retained bytes
  remain available. A deleted container, rotated-away interval, corrupt spool, lost volume or missing checkpoint
  yields explicit loss/uncertainty. Surviving on-disk files do not alone prove that all emitted logs were captured.
- A recorded queue eviction settles that destination's pending delivery as loss, not success. Do not repeatedly
  replay deliberately evicted old data into the full queue ahead of newer context. Replay of other unconfirmed
  surviving data remains automatic; optional older-data backfill requires separate authorized bounded recovery.
- Runtime retirement attempts collection handoff within the 30-second grace. If ordinary output cannot be recovered,
  record the source-retirement gap; do not falsely confirm collection or hang the owning business call indefinitely.
  Protected/mandatory evidence and sole preserved copies are not deleted on that timer: their existing owner must
  retain/reconcile them or explicitly handle the failure under its own rules. Logging receipts never satisfy that
  owner's release criteria by themselves. No new Workflow completion/failure rule follows from log delivery.

### Ordering, attribution and replay access

Retain emitted timestamps with known precision/timezone, observed collection time, trusted source generation and
stream identity, and native offsets/sequences/partial identities where available. Collector-assigned receipt/fragment
IDs identify transport observations, not invented business identities. Docker timestamp cursors are not byte offsets;
use qualified overlap handling and expose remaining duplicate/skip uncertainty. Never collapse identical messages
or same-time records merely because their content matches.

Order within an actually ordered source stream where supported; deterministic browser ties are presentation, not
proof of chronology across processes. Missing/invalid timestamps remain unknown with receive-time provenance, not
silently rewritten event times. TTY-merged stdout/stderr cannot be relabeled into invented separate streams. Late
and duplicate records remain visible. WF-039 determines late-data retention; WF-041 determines surrounding-context
presentation under these limitations.

Spool/raw holding/checkpoints and collector configuration are installation-private, not mounts writable by managed
projects/Agents or publicly served directories. Only installation operators can alter enrollment/credentials or
repair/replay local spools; browser read/export access is not storage administration. Automatic retries operate
within their enrolled capability. User-requested archive restore/backfill must recheck current applicable permission
and limits under WF-039/041. Use authenticated private transport and encryption in transit across a host boundary.
Checksums detect corruption, not malicious rewriting by a privileged operator; no cryptographic/nonrepudiation claim
is made. Producer-controlled context can be false and must not replace trusted source attribution or authorization.

### Failure classification and independent coverage state

- Producer normalization/encoding/handler failure: preserve the business outcome per WF-037 and issue a bounded
  independent diagnostic status with stage/reason/uncertainty. Do not retry the business operation, recurse through
  the broken logger, or dump the failed raw payload into the health path. Successfully captured malformed bytes
  remain raw operational records; absent producer bytes cannot be reconstructed by the collector.
- Transient network, destination overload/rate limit or server failure: retry within the profile while continuing
  local capture. A search outage must not imply archive failure or vice versa. An unconfigured archive is explicitly
  unconfigured, never "delivered". Search/index lag is separate from acceptance and from connected live-tail status.
- Authentication, TLS identity or endpoint configuration failure: mark that destination blocked, keep bounded
  pending data and retry/probe at the bounded cadence; do not waive verification or leak credentials to status.
- Payload-size rejection: reduce/split using qualified framing; permanent malformed-record/schema/timestamp rejection:
  preserve raw bytes in the bounded rejected-data path and identify the affected destination/reason. Do not retry a
  poison batch forever ahead of all valid records or treat every 4xx as a retryable storage outage. Preserve
  original fields/times; an operator repair is not permission to silently rewrite the original record.
- Queue/holding pressure: warn at 75% and mark high pressure at 90%; at capacity apply oldest-eligible eviction with
  independent loss accounting. Eviction order is queue/chunk age at the pressured destination, not claimed global
  event-time order. Chunk-granularity eviction may remove more than the immediately required bytes and must report
  actual known counts/granularity. Do not use a DLQ's default drop-newest behavior as an equivalent implementation.
- If no eligible bytes can be freed, never bypass preservation holds or grow without bound. Retain/replay available
  source bytes where possible, mark incoming coverage at risk/lost/unknown, and surface the preservation/capacity
  conflict to its owner. WF-039 must define preservation storage/failure handling. The newest-context preference is
  not an impossible promise that arbitrary held data and unlimited new data both fit finite storage.
- Disk full/read-only/I/O error, corrupt checkpoint/chunk, reader outage or collector OOM/restart: isolate/restart the
  collection component where possible, mark affected stages and source intervals uncertain, and resume from surviving
  qualified state. Do not mask actual shared-resource failures or claim application survival is guaranteed.

Independent status lives with existing installation supervision, not solely in collector stderr or the search
backend. It records current stage/source/destination state, freshness, backlog/oldest pending age and known loss
counters plus at most 64 recent incident summaries in the reserved control budget. Coalesce overflow into aggregate
counts/ranges with explicit detail uncertainty; this is not an unlimited parallel event/history store. Status writes
and reads have a 1-second deadline and one in-flight operation. A stale/unreadable status is unknown, never healthy.
The collector's crash must be observable from its supervisor even if its own process cannot report it. A host/shared
storage failure can destroy both local data and local status; off-machine protection is WF-039, not claimed here.

Coverage must distinguish no matching records from pending enrollment, not captured, delayed, evicted/lost, source
unavailable, parse/assembly incomplete, not yet searchable, archive unconfirmed, index expired and authorized purge.
Do not promise exact missing counts or boundaries when only an interval of uncertainty is known. WF-041 consumes
these distinctions for browser presentation; no separate alerting/metrics product is implicitly authorized.

## Qualification handoff and resolution

Closed as a planning contract on John's accepted source/access/no-redaction/resilience constraints and his explicit
newest-context exhaustion choice. The remaining mechanisms above are engineering judgments, not assertions that
John approved individual library settings. The research note preserves the initial recommendation and subsequent
human choice. No new human policy approval, runtime authority, dependency update or implementation is inferred.

[WF-039](WF-039-define-searchable-retention-and-verified-archives.md) next resolves archive/preservation, confirmed
copies, retention and bounded retrieval. It must reconcile sole protected copies with eviction explicitly.
[WF-040](WF-040-select-the-log-search-backend.md) must qualify exact source/collector/driver/backend versions, licenses,
privileged reader isolation, disk/resource enforcement, native framing, rejection handling and receipt semantics.
A stateless source plus a disk sink, native drop-oldest without preservation eligibility, or a product screenshot is
insufficient. A demonstrated conflict returns to the owning contract; it does not authorize a silent relaxation.

Required future qualification evidence covers full selected-source/level coverage and exclusions; short-lived source
capture/retirement; restart at checkpoint/persistence/uncertain-response boundaries; duplicate/late/same-time records;
malformed/non-UTF-8/oversized/incomplete output; each destination unavailable; full queues and actual eviction order;
protected-only capacity; source rotation/deletion; corrupt/lost storage; independent status during collector failure;
and denied cross-source/unauthorized access. Validate collector/runtime configuration and infrastructure behavior
directly with their owning tools, not product-suite tests of wiring/configuration or deliberately seeded quality-tool
failures. WF-042 owns the integrated delivery/acceptance boundary. Any live experiment needs separately authorized
scope; no runtime probe or benchmark has occurred here.

Historical next: `/skill:wayfinder work WF-039`.

## Approved qualification-timing amendment

John approved [WF-040's conditional Loki planning selection and explicit timing amendment](WF-040-select-the-log-search-backend.md#accepted-resolution--conditional-planning-selection).
The earlier requirement to complete composed runtime/application qualification before WF-040 planning selection
is superseded **only in timing**: the collection checks above remain mandatory integration/delivery gates carried
into [WF-042](WF-042-bound-observability-delivery-and-acceptance.md#mandatory-qualification-gates-carried-from-wf-040).
WF-042 must preserve each concrete blocker, owner and verification path before delivery work is executable.

All source/privilege, checkpoint/persistence, native framing, partial/rejected receipt, oldest-eligible eviction,
protected-only capacity, independent status and physical-limit obligations remain unchanged. No reader/collector,
Docker grant, custom queue/proxy/plugin or production configuration is selected or qualified by Loki's planning
selection. Missing native capabilities and design conflicts return to their owning contract rather than becoming
silent implementation assumptions. Monitored experimental scratch is not production quota enforcement.

This decision remains Closed as its existing planning contract; the amendment does not reopen the accepted loss
policy, claim additional runtime evidence or authorize implementation/experiments. The map's next decision is
[WF-041](WF-041-select-the-authorized-log-browser-integration.md).

Next: `/skill:wayfinder work WF-041`.