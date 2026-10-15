# Disposable backend comparison results

## Question and short answer

For [WF-040 — Select the log search backend](../tickets/WF-040-select-the-log-search-backend.md), can the pinned
VictoriaLogs/Loki candidates preserve and query the accepted log shape, restore old records and remain useful
within a small bounded local installation?

**Both demonstrated the core query/raw-content/old-import primitives. Prefer Loki for the next integration
qualification, superseding the initial VictoriaLogs-first recommendation—not selecting a production backend.**
VictoriaLogs was much lighter in this small workload, but its HTTP-success behavior concealed age/size rejection
and did not preserve a fresh acknowledged batch across an immediate process crash. Loki explicitly rejected those
age/size cases and recovered the fresh batch through its WAL. That reliability advantage is more important here
than the observed roughly 100 MiB idle-RSS difference. A single crash probe does not certify power-loss durability.

Loki is not a drop-in solution: temporary old-data import needed explicit flush/query-readiness reconciliation;
identical entries can collapse without observation identity; production storage, hold-aware expiry, source replay,
queue eviction, archive verification and Agent OS authorization remain unqualified. Its AGPL-3.0 obligations also
remain an adoption consideration; Apache-2.0 is still an advantage for VictoriaLogs. No reason yet to widen the
candidate list or waive the accepted WF-038/039 contracts.

## Authority, environment and containment

John approved the comparison, then explicitly approved **monitored scratch instead of a hard filesystem quota**.
The first read-only preflight stop and that amendment remain recorded in WF-040. Runtime execution on 2026-10-15
spanned **14:25:54–14:50:25 UTC, 24 minutes 31 seconds**, including diagnostics/retries and gaps between trials,
within the 60-minute limit. No further runtime experiment is implied by this result.

- Docker Desktop **4.86.0 (236216)**, Engine/client **29.7.2**, API **1.55**, Compose **v5.3.1**;
  Linux/ARM64 kernel **6.12.76-linuxkit**, containerd-backed overlayfs and cgroup v2.
- ARM64 host: 10 CPUs, 32 GiB RAM. Docker engine: 10 CPUs, 8,321,515,520 bytes RAM. This was a **shared, active
  development host**, with 48 container samples at preflight, not an isolated performance lab or reserved host.
- One backend instance at a time, with **1.75 CPU/1,792 MiB memory and no swap allowance beyond that memory**.
  The short-lived same-image `-version` launcher had 0.25 CPU/256 MiB, making the aggregate configured ceiling
  2 CPU/2 GiB. Host Python clients are outside those container limits. VictoriaLogs logged that it rounded its
  internal CPU quota interpretation down to one CPU; identical cgroup ceilings do not imply identical scheduler use.
- Existing `./bin/exec` launched only a scratch `COMPOSE_FILE`/unique project; its `web` service was a same-image
  version reporter with the isolated backend as a dependency. `./bin/down` cleaned that project. Normal application
  Compose was never launched or edited. Scoped Docker inspect/metrics/log/kill operations addressed owned resources.
- Read-only roots, UID/GID 10001, all capabilities dropped, no-new-privileges, PID limits, bounded `/tmp`,
  no privileged containers/socket mounts, and only owned scratch bind mounts. Backend HTTP ports bound only to
  `127.0.0.1` on a dedicated bridge; no shared application network. **This was not an egress-denial experiment**.
  Loki analytics reporting was disabled. The initial `internal: true` bridge attempts gave host connection refusal;
  their receipts are retained. Dedicated bridge plus loopback binding supplied the successful local client path.
- One-second scratch monitoring during active trials, stopping at 2 GiB or monitor errors. Peak recorded scratch
  usage across the observability-backends parent was **56,153,234 logical / 56,643,584 allocated bytes**, with
  869 samples and no abort. Docker logs were separately rotated at 4 MiB × 4 files/container. This is monitored
  experimental budgeting, **not** a proven hard physical disk quota or supervisor-failure guarantee.
- Only the two approved backend images were pulled. Selected manifests total **55,488,827 compressed layer/config
  bytes**, well below the 2 GiB image-download budget; actual wire retry traffic/extracted daemon allocation was
  not independently metered. No host packages, daemon settings, application dependencies or production data changed.

### Exact image identity

| Candidate | Source commit | Linux/ARM64 image manifest |
|---|---|---|
| VictoriaLogs v1.53.0 | `915d91904bf7f5be66ab00f88947ae7fc1431acc` | `victoriametrics/victoria-logs@sha256:9beccb5e7d38e3db891578249f90ef16f572c37665062ca19cbcc753055431c9` |
| Loki v3.7.8 | `09e6ce2ff1bdc19763a10265b870c86f51c98655` | `grafana/loki@sha256:a2753977b07471e0bb271f421677036fb1c84a2fd409e14fa19dfc7cc3b789b5` |

Multiarchitecture index digests were respectively
`sha256:251121fa882af99b95ba0c230a4a2f412ea602d2698c64a96c58dc9842bb755d` and
`sha256:1107dd5274e0ada47e42472b7a7e71f3b2a2fe878878108f3e2f9e51528f0193`.
The version reporters/inspect records were captured. Digest pinning is not a signature, vulnerability scan,
complete third-party-notice review or production image approval. [R1]

## Workload and measurement method

The same **12,000-record, 21,073,071-byte** synthetic baseline corpus was reused for both products. SHA-256:
`01a48487205d6c4701199e5a1f50d0deca4000017e8fe97da4e6a8cefe233a2d`.
It contains four source streams, two service values, eight severity names including DEBUG, ten channels,
100 run IDs and high-cardinality correlation/observation IDs generated through Python's UUID library.

Each envelope keeps a raw JSON string with nested objects, arrays, empty/null/boolean values, exception/cause/trace
shape and useful payload, with selected flat search fields. A long raw context key and dotted/nested-key collision
exercise the distinction between a search projection and preserved content. Some emitted event times lag original
collection time by an hour. These are **Monolog-shaped synthetic fixtures**, not actual application emission or a
Monolog configuration test; their placeholder numeric level values do not qualify native Monolog's level-code map.
No real logs, credentials, business events, prompts or project data were used. Additional synthetic edge/restore
fixtures kept the unique corpus comfortably below the approved 64 MiB ceiling; retransmitting the same corpus
for a repeat is not a new unique corpus.

- Ingestion: 48 requests × 250 records, paced to at most 1,000 records/s, approximately 12 seconds, followed by
  seven seconds of settling. This measures the offered rate, **not maximum throughput**.
- Queries: 20 samples each of a service/severity/text failure search and run-ID search, two query clients alongside
  one tail connection, ≤500 returned events/request. Report nearest-rank p95 and median including host HTTP time.
- Process RSS/CPU were sampled through each product's `/metrics` roughly every second. CPU values below are sampled
  process CPU deltas divided by sampled elapsed time, in **CPU cores**, not percent of the entire host. RSS peaks
  are sampled, not exact high-water marks; neither includes every shared Docker/host/collector/storage cost.
- Raw export: a separate repeat exported all 12,000 baseline records in 500-record pages and compared a canonical
  sorted `(event_id, raw)` digest. Baseline timestamps are unique; same-time pagination was tested separately.
- Fresh tail: open a source-restricted connection, wait two seconds, emit 20 newly timestamped/identified markers,
  verify every identity. These replace the initial incomparable history-tail numbers: the common baseline aged
  outside Loki's requested ten-minute historical lookback by the time its trial ran.
- Crash: HTTP acknowledge 50 fresh markers, then SIGKILL only the owned backend about 0.30 seconds later; restart
  it with the same data. A second independent control waits 60 seconds before SIGKILL. Neither is host power loss,
  WAL corruption, disk-full behavior or verified archive recovery.
- Old import: separate disposable instance/data directory, 100 records each aged 30, 89 and 120 days, narrow
  per-source time queries. Retention allows those original timestamps; no re-aging to import time. Exact raw,
  emitted time and collection clock are checked. This is **synthetic import**, not SeaweedFS/gzip archive restore.

VictoriaLogs used a 14-day live retention, 512 MiB cache allowance and two concurrent searches; that cache flag is
not its process memory cap. Loki used monolithic `all`, schema v13/TSDB, filesystem store, WAL, two query workers,
14-day age/retention settings and structured metadata for observation/severity/run/correlation fields. Filesystem
storage here is a probe convenience, **not a production storage decision**. Raw context remains in the line/string,
not in high-cardinality stream labels. Configuration choices are recorded, not claims of optimal tuning. [R1][S1]

## Results

### Content, query and recovery behavior

| Probe | VictoriaLogs | Loki |
|---|---|---|
| Baseline acknowledged / query-visible | 12,000 / 12,000 | 12,000 / 12,000 |
| Full baseline raw export | 12,000 unique IDs; digest equal | 12,000 unique IDs; digest equal |
| Five-event source context around sequence 1004 | Native `stream_context`: 996, 1000, 1004, 1008, 1012 | Two bounded range queries: same five records after correcting exclusive-end handling |
| Other synthetic tenant lookup | Empty | Empty |
| Negative result limit | HTTP 400 | HTTP 400 |
| Twelve distinct same-time observations | 12 retained; 5 returned on a 5-row page | 12 retained; 5 returned on a 5-row page |
| Time-only next page at timestamp +1 ns | 0: skips remaining tied records | 0: skips remaining tied records |
| Two exactly identical transport submissions | 2 returned | 1 returned |
| Fresh tail | 20/20 identities, approximately 5.99 s from emission to first result | 20/20 identities, approximately 0.50 s from emission to first result |
| Reversible 307,200-byte binary payload in ten ≤32 KiB pieces | All ten pieces; reconstructed SHA-256 equal | All ten pieces; reconstructed SHA-256 equal |
| Single 337,920-byte raw field / approximately 338 KiB encoded line at defaults | HTTP **200**, **zero** records; backend warned line exceeds 262,144 bytes and skipped it | HTTP **400**, **zero** records; explicit 262,144-byte entry-limit error |
| 89-day-old record into live 14-day store | HTTP **200**, **zero** queried records | HTTP **400**, explicit timestamp-too-old rejection |
| Mixed valid + malformed native JSON-line batch | HTTP **200**, valid record visible; malformed record absent | Not exercised with an equivalent native request shape |
| Immediate crash after 50 fresh acknowledgements | **0/50** recovered | **50/50** recovered |
| Delayed crash control after 60 s and 50 visible markers | **50/50** recovered | **50/50** recovered |
| Isolated old-data import/query | **300/300**, raw/event/collection clocks equal | **300/300 after flush/readiness polling**, raw/event/collection clocks equal |
| Owned temporary process/network/data cleanup | Removed and checked | Removed and checked |

The full-export raw digest on both sides is
`84285dcefec3f413dc7a048d834694bc32ddbc1477d1fc49e511f15bed1d6cef`.
Fragment reconstruction digest on both sides is
`a7d8881521cbb1e4a5ca960198c7907b45625b39d3a7a86368fc8b4ecad01014`. [R1]

**Interpretation:** raw envelopes/framing and observation identity are necessary, not optional embellishments.
Successful HTTP return does not make VictoriaLogs' input loss detectable per record, and waiting a fixed interval
is not a durable per-record acknowledgement protocol. Loki's explicit rejections are easier to reconcile, but
partial batch semantics beyond these cases remain to be qualified. Neither tenant result proves Agent OS user
authorization; a caller able to choose another tenant header is not thereby forbidden from that tenant.
Neither backend supports a completeness claim from timestamp-only pagination in the presence of ties.

### Resource and latency observations

| Metric | VictoriaLogs | Loki |
|---|---:|---:|
| Idle median process RSS, 12 samples | 14.1 MiB | 116.2 MiB |
| Ingest sampled peak process RSS | 48.5 MiB | 162.8 MiB |
| Ingest sampled mean process CPU | 0.024 cores | 0.066 cores |
| Query/tail sampled peak process RSS | 81.9 MiB | 195.4 MiB |
| Query/tail sampled mean process CPU | 0.027 cores | 0.136 cores |
| Paced baseline ingestion elapsed | 12.17 s | 12.19 s |
| Failure-search median / p95, 20 samples | 14.8 / 74.0 ms | 58.9 / 75.1 ms |
| Run-ID search median / p95, 20 samples | 19.3 / 52.8 ms | 44.1 / 60.4 ms |
| Live data allocation after crash/restart and settling | 1,548,288 bytes | 24,236,032 bytes |
| Old import-to-query evidence elapsed | 7.12 s including fixed 7 s settle | 12.14 s including fixed 7 s settle and 5 s post-flush poll |
| Restore sampled peak process RSS | 19.9 MiB | 138.9 MiB |

These observations support **small local feasibility**, not a universal speed/RAM/compression ratio. The live disk
figures are not equivalent long-term storage inventories: Loki includes an active WAL, VictoriaLogs lost the fresh
crash markers, flushing/compaction stages differ and the highly repetitive synthetic payload compresses unusually
well. VictoriaLogs' tiny restore disk count immediately after querying did not prove durable storage and is not
presented as archive compression. The query/tail resource phases also had different historical-tail traffic;
use the dedicated fresh-tail probe for delivery behavior, not those phases as matched sustained tail-load tests.
No 14-/90-day capacity, real corpus diversity, saturation, competing four-ready-scope restore workload, steady-state
compaction, object-store cost or independent repeat-run variance was measured. [R1]

### Loki old-import reconciliation

Disabling `reject_old_samples` and lengthening retention admitted all 300 old records with HTTP 204, but initial
queries returned **zero**. Adding `querier.query_ingesters_within: 0s` alone still returned zero. A focused diagnostic
confirmed a current-time control was queryable while old queries reached no ingesters; after `POST /flush`, the
old records became queryable from storage. Pinned source distinguishes store/ingester lookback precedence, and the
effective configuration exposed a 2h41m store lookback. Do not infer from one age knob that every query-routing
window has changed. [R1][S2][S3]

The final 300-record import used an isolated instance with old samples allowed, sufficiently long retention and
that explicit flush. All 300 became queryable on the first five-second poll after flush. Raw payload and both
clocks matched. **Flush response alone is still not a ready/complete receipt**; verify intended counts/identities
and content before publishing a restored scope. This route affects the whole selected ingester and was safe here
only because it contained solely disposable restore data, not the live search workload. The fixed ten-year probe
retention is not a product cap on older held material; actual selected coverage must govern final restore settings.

## Failed attempts, corrections and evidence honesty

- Three initial VictoriaLogs starts on an `internal: true` bridge could not be reached from the host. Owned resources
  were cleaned after each. The first attempt's driver captured stdout but missed backend stderr; later captures
  include both. The final dedicated-bridge path is loopback-bound but does not claim egress denial.
- The first VictoriaLogs restore result collector rejected a valid nine-fractional-digit RFC3339 timestamp using
  the host Python parser. The driver was corrected to compare exact epoch nanoseconds and rerun. This was a probe
  parser error, not evidence of backend timestamp corruption.
- The first Loki context assembly returned six records because the before-query's end was exclusive. Using two
  preceding records and three forward records returned the intended five. Backend behavior, not causal ordering,
  controls the composed context API.
- Initial tail numbers measured unequal historical lookback coverage. Fresh restricted-source markers replaced
  them; no claim that Loki cannot tail follows from its initial zero historical rows. The small WebSocket probe
  did not qualify long-lived heartbeat/reconnect/backfill behavior.
- Loki's initial and window-only old-import failures are preserved; they were not deleted after the flush-path
  result. The restored-count result is explicitly conditional on the final isolated flush/query-readiness procedure.
- VictoriaLogs' CPU-rounding warning and oversized-line drop warning are retained. The latter is operationally
  important: warning-only rejection must not be mistaken for an acknowledged lossless batch. The 262,144-byte
  default exists in **both** candidates, despite VictoriaLogs' larger documented hard ceiling. [R1][S4]

## Cleanup and receipts

All **13** attempted instance lifecycles have ownership-scoped cleanup receipts. Final checks found no owned
containers or `wf040-*` networks, and all owned backend data-directory paths were removed. This is ordinary owned
resource removal, not proof of secure physical-media erasure or an application hold-aware purge. The two exact
images remain in the Docker cache; no shared images, volumes or unrelated services were pruned/stopped.

Raw evidence is in the base checkout at:

`.runs/notes/observability-backends/comparison-20261015T141833Z/`

[R1] receipt inventory:

- `final-run-summary.json`: execution span, monitored peaks, image-manifest budget, final cleanup checks and evidence hashes.
- `probe.py`, `followup.py`, `restore-diagnostic.py`, `*.compose.json`, `loki*.yaml`: disposable driver/configuration.
  Corrections are described above; the final driver includes them and must not be confused with every earlier attempt.
- `corpus-identity.json`, `corpus.jsonl`, `*-results.json`: baseline/follow-up/restore counts, payload digests and timings.
- `*-container-identity.json`, `*-image.json`, `*-network.json`, `*-metrics.json`, `storage-monitor.jsonl`:
  exact identities, configured isolation/limits and sampled evidence.
- `*-cleanup.json`, `*-backend.log`, `*-launch.log`: owned cleanup, backend warnings and version reporters.
- `attempt-*`, `loki-initial-restore/`, `loki-window-only-restore/`, `loki-restore-diagnostic-*`:
  unsuccessful attempts and counterchecks, not fresh successful product acceptance.

Scratch receipts are local evidence, not published/committed application tests. This durable note preserves the
material outcomes even if ordinary scratch cleanup later removes the large corpus.

## Remaining qualification and next decision work

**Read-only follow-up:** the [integration reconciliation](loki-integration-reconciliation.md) now proposes a
conditional planning choice and explicit qualification-gate amendment for John. It also finds source-level
partial-batch/error-aggregation, blocked-ingestion HTTP 260 and WAL ENOSPC caveats. The measured successful cases
above remain valid but are not a universal all-record receipt. No new runtime experiment accompanied that work.

Keep WF-040 **Open**. The next read-only engineering step is to reconcile a Loki-first composition with the
already accepted source/collector/archive/hold contracts, before requesting final human backend acceptance:

1. **Source and destination receipts:** qualify enrolled capture/replay, bounded independent queues, explicit
   permanent rejection disposition, newest-eligible eviction and protected-only capacity conflict. Native WAL
   helps one backend hop; it does not establish atomic source checkpoints, exactly-once ingestion or archive receipt.
2. **Production storage:** the probe used Loki's filesystem store. Qualify the selected supported storage topology,
   including SeaweedFS S3 compatibility if proposed, independent archive format/catalog, disk accounting and recovery.
   Loki chunks are not WF-039's verified gzip/JSONL archives. No SeaweedFS instance was started here.
3. **Protection before expiry:** native search TTL has no Agent OS hold authority. Specify/prove when a protected
   copy is secured outside the expiring index or how affected cleanup is gated. No silent sole-copy deletion, no
   assumption that a successful Loki push means an archive exists, and no shortening accepted archive retention.
4. **Browser-facing contracts:** current authorization and revocation, bounded raw/detail/context and tie-safe
   pagination, four ready temporary scopes within aggregate resources, cancel/expire/hold/purge races and cleanup
   need qualification. Tenant headers and process deletion are not the missing application implementation.
5. **Acceptance limits:** reconcile AGPL distribution obligations and exact image security/provenance; measure a
   representative real-volume sizing profile only under separately bounded authority. The small probe supplies
   evidence, not production concurrency, host-loss recovery, application acceptance or deployment permission.

These are concrete requirements of the accepted diagnosis outcome, not new speculative infrastructure TASKs.
A missing guarantee returns to its owner; this experiment does not reopen closed choices automatically, authorize
another runtime integration test, or advance to WF-041/decomposition. The original experiment permission is not an
open-ended benchmark allowance.

Next: `/skill:wayfinder work WF-040` — reconcile the Loki-first composition and remaining acceptance conditions;
request any additional bounded runtime authority only if that reconciliation identifies a necessary experiment.

## Primary sources

The earlier [pinned comparison](log-backend-victorialogs-loki-comparison.md) contains the complete release/license/API
source inventory; this note adds measured local evidence [R1], not a substitute upstream citation.

- [S1 — Pinned Loki local configuration](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/cmd/loki/loki-local-config.yaml)
- [S2 — Pinned Loki querier lookback and store/ingester precedence](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/querier/querier.go)
- [S3 — Pinned Loki HTTP API, including flush](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/reference/loki-http-api.md)
- [S4 — Pinned VictoriaLogs flags, including default insert line limit](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/victoria_logs_common_flags.md)
