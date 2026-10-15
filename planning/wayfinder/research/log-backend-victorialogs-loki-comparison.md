# Log backend comparison: VictoriaLogs and Loki

## Question and recommendation

**Follow-up amendment:** John approved the comparison and then monitored scratch. The
[completed disposable comparison](log-backend-disposable-comparison-results.md) now supplies bounded local
measurements and recommends **Loki for next integration qualification**, superseding the initial VictoriaLogs-first
recommendation below. No production backend is selected. This note preserves the preceding read-only research
and preflight history; its no-runtime-evidence statements describe those earlier stages, not the current frontier.

For [WF-040 — Select the log search backend](../tickets/WF-040-select-the-log-search-backend.md), which existing
backend should be qualified first for Agent OS diagnosis, bounded collection, local verified archives and temporary
browser search?

**Initial read-only recommendation: VictoriaLogs OSS as the first qualification candidate, with Loki OSS as the comparator—not a selected
or fully qualified backend.** VictoriaLogs offers direct field/full-text search, native surrounding-stream context,
a single-process local-storage topology and Apache-2.0 licensing. Loki offers mature log APIs, native structured
metadata and a WAL, but adds stream/ordering constraints and more retention/storage configuration. These are
capability/operational observations, not proof that VictoriaLogs is faster or smaller for this application.

Neither backend supplies Agent OS permission checks, preservation-aware archival, a qualified Docker reader or the
complete temporary-restore lifecycle. Do not widen to OpenObserve/OpenSearch yet: these first candidates have
plausible query paths, and the remaining uncertainties should be measured/reconciled rather than avoided by adding
products. A demonstrated unresolvable capability gap can justify widening later.

## Evidence identity and limits

Read-only upstream research on 2026-10-15. GitHub's latest-release responses identified:

| Product | Advertised release | Published | Source used for docs/source |
|---|---|---|---|
| VictoriaLogs | v1.53.0 | 2026-10-01 | `915d91904bf7f5be66ab00f88947ae7fc1431acc` |
| Grafana Loki | v3.7.8 | 2026-09-17 | `09e6ce2ff1bdc19763a10265b870c86f51c98655` |

All product documentation cited below was read from those source snapshots, not an unversioned latest-docs page.
A guessed uppercase benchmark `README.md` returned 404; the actual file is lowercase `readme.md`.
At this read-only stage, no packages/images were installed/pulled, services started, backend queried or resource measurements taken.
Advertised tags are not selected container image digests, verified binaries or deployment receipts.

## Capability comparison

| Contract | VictoriaLogs v1.53.0 OSS | Loki v3.7.8 OSS | Agent OS consequence |
|---|---|---|---|
| Initial topology | Single binary, local `storageDataPath`; no object store needed for the live index. | Monolithic `target=all` available. TSDB plus filesystem object store needs no separate service for a probe; upstream does not support filesystem store for production, including paid support. | Neither inherently needs Kubernetes. Loki with local S3/SeaweedFS adds an unqualified integration; neither live index is the verified archive. [V1][L1][L2] |
| Text/field filtering | LogsQL indexes fields; query API returns JSON Lines, accepts explicit time bounds, limit/offset and sorting. | LogQL stream selectors plus text/JSON/metadata filters; `query_range` supports bounds, direction and result limits. | Service/severity/operation/run fields can drive diagnosis. Do not expose arbitrary unbounded backend queries. [V2][V3][L3][L4] |
| Context/detail | Native `stream_context before/after/time_window`; all fields available through query projection. | Bounded before/after queries on the appropriate source stream/time can compose context from `query_range`. | Context is source-relative, not causal truth; explicit event/transport identity needed for reliable anchors and same-time ties. A separate immutable event-detail endpoint is not assumed. [V2][V4][L3] |
| Live tail | HTTP JSON-line tail, default five-second delay, per-stream ordering and configurable lookback; no global chronological guarantee. | WebSocket tail, dropped-entry reporting; initial historical lookback explicitly best effort. | Pause/reconnect/backfill and missing-coverage UI remain application work, not proof of completeness from a connected stream. [V2][L3] |
| High-cardinality IDs | Ordinary indexed fields are supported; keep run/correlation IDs out of stream fields. | Use structured metadata or the JSON line, not indexed stream labels; schema v13/TSDB required for structured metadata. | Both can represent high-cardinality IDs. VictoriaLogs' FAQ claim that Loki cannot support such fields is too broad when compared with current Loki metadata documentation. [V3][V5][L4] |
| Raw/native context fidelity | Nested dictionaries flatten, scalar types stringify, empty and missing values can collapse; `preserve_json_keys` helps selected nested values. | Original JSON can remain the line; structured metadata is flat string pairs, not an arbitrary nested Throwable dump. | Preserve exact raw payload separately in the record/envelope and archive. A search projection alone cannot establish byte/native-type round trips. [V3][V6][L3][L4] |
| Tenant/access boundary | AccountID/ProjectID headers select tenants; no per-tenant authorization. | X-Scope-OrgID selects tenants; `auth_enabled` requires that header but is not end-user authentication. | Private backend plus current Agent OS permission enforcement is mandatory. Backend tenant names are not Agent OS project grants; installation-wide log access remains the accepted boundary. [V1][V7][L5][L6] |
| Old data import | Ingestion rejects data outside global retention/backfill bounds; live 14-day settings cannot accept 90-day restore directly. | Default old-sample rejection is seven days; additional per-stream out-of-order checks apply even when age settings are changed. | Use isolated restore storage/tenant and explicit allowed-age/retention settings, retain original timestamps and never re-age records to now. Test older held material too. [V1][V8][L7][L8] |
| Expiry/purge | Native retention drops daily partitions, independent of application holds. Filter deletion API is opt-in, asynchronous, rewrites storage and is recommended for exceptional rather than frequent deletion. | Compactor supports tenant/stream retention and async delete requests; no native Agent OS hold authority. Retention disabled by default; delete cancellation defaults to 24h. | Native TTL must not destroy a protected sole copy. Immediate deny and eventual physical removal differ. Routine temporary-scope reclamation needs deliberate qualification. [V1][L9][L10] |
| Receipts/crash behavior | JSON-lines ingestion can accept a batch while skipping some bad lines. Storage has periodic in-memory flush, default five seconds; HTTP return is not a per-record durable receipt. | WAL improves acknowledged-write recovery across process crashes; docs disclose corruption/disk-full windows that weaken the guarantee. | Neither response can mark every input durable/searchable/archived without appropriate reconciliation. Keep archive acknowledgement independent. [V9][V10][L11] |

### Licensing and community features

VictoriaLogs' inspected source is Apache-2.0. Preserve the license and applicable notices on distribution and mark
modifications; this is not a trademark grant. The inspected query/ingest/tenant/delete/partition capabilities are
documented in OSS. Its native mTLS and automatic certificate issuance flags are Enterprise-only; do not silently
depend on them. Existing private ingress/proxy transport can be qualified instead, without selecting a paid feature.
No paid license is required by the proposed first probe. [V11][V12]

Loki's inspected source is AGPL-3.0. Redistribution/source obligations and section 13's modified-network-service
source-offer condition require deliberate compliance if shipping/modifying it. Do not misstate a separate unmodified
HTTP service as automatically relicensing all of Agent OS. Native transport mTLS is documented, but does not map
users to tenant permissions. Grafana Enterprise authorization/UI features are not evidence that OSS Loki implements
Agent OS permissions. No paid Loki feature is required for the compared ingest/query/retention primitives. [L5][L12]

Exact images, bundled libraries, notices, signature/provenance/security checks and any viewer license remain to be
qualified before adoption. This note is a technical license-scope comparison, not legal clearance or image approval.

## Important mismatches and proposed qualification paths

### Retention and temporary restore are not just longer settings on the live index

WF-039 uses original trusted collection time for live/archive retention, with emitted event time retained separately.
A candidate mapping can use original collection time as VictoriaLogs `_time` or Loki's storage timestamp, keeping
emitted time in its own unchanged field. Browser labels/query basis must make this distinction visible. Missing
collection time cannot be repaired by silently using import time. Arbitrary event-time context versus storage-time
ordering needs actual query evidence, especially for delayed logs.

For VictoriaLogs, the first restore design to probe is a **separate disposable data directory/process**, loaded only
with the selected verified archive window. Its allowed retention/backfill age must cover the actual selected oldest
record, not simply assume all holds expire at day 90. It must be reachable only through the authorized restore route;
expire access at the application boundary and remove only that owned disposable data after shutdown. This avoids
using storage-wide asynchronous filter rewrites as routine per-job TTL. It introduces process/resource costs, and
multiple ready scopes under WF-039 must fit the aggregate budget—neither is measured or accepted as final topology.

For Loki, isolated restore tenants with explicit overrides or separate disposable instances are plausible. Keep
restore streams separate from current live streams; disable/adjust old-sample rejection only within the selected
scope, sort import batches where required and verify schema time coverage, query lookback, compactor retention and
out-of-order behavior. Increasing max age alone is not sufficient evidence of faithful 90-day or older-held import.

For **both**, normal index expiry can be safe only when the protected record has another confirmed owner-preserved
copy. Native retention does not consult Agent OS archive/hold state. The design must prove that an independent
preserved copy exists before native expiry can remove the search copy, or gate affected native cleanup. This is a
WF-038/039 integration requirement, not permission to silently reclassify search as disposable while it is the only
protected copy. No qualified hold-safe composition has yet been demonstrated. If achievable only by changing an
accepted guarantee, return for explicit reconciliation before selection.

### Ingestion errors, shape limits and distinct repeated records

VictoriaLogs' pinned source returns an error when every processed line failed, but can return normally for a mixed
valid/invalid batch. It also rejects out-of-retention timestamps. Field names have a documented 128-byte hard bound,
record size a 2 MB bound and field count a 2,000 hard bound (configurable insert default 1,000). Native Monolog
normalization is not a guarantee that every flattened record fits those limits. Use bounded transport envelopes,
raw payload/fragment preservation and selected safe-to-index fields without deleting producer content. Qualify
actual accepted counts and raw recovery; never treat a successful HTTP batch as lossless ingestion. Debug/ignore-field
examples in upstream collector docs are not authorized filtering under this map. [V5][V6][V8][V9][V13]

Loki defaults include a 256 KB line limit (truncation off), 64 KB/128-entry structured metadata limits, and finite
rate/stream limits. These must be reconciled with WF-038 framing; enabling truncation or unlimited memory is not
an acceptable fix. Preserve the emitted exception/raw body in the line/envelope, not only metadata that exceeds
its own smaller limit. Retryable throttling differs from permanent age/line/ordering rejection. [L4][L7][L8]

Loki's inspected merge iterator removes entries with identical stream/timestamp/line/metadata. Therefore two
legitimate identical emissions need distinct retained observation identity in a qualified envelope/metadata path,
without inventing business identity or changing timestamps. The exact comparison and end-to-end output must be
probed; enabling duplicate-timestamp increments would alter chronology and is not the chosen answer. Neither
backend's query pagination by time alone establishes exactly-once traversal over concurrent inserts/timestamp ties.
[V2][L3][L7][L13]

### Authentication, source capture and archive integration remain owned work

For either candidate, Agent OS must control current log permissions, scope mapping, query budgets, revocation,
exports and temporary restore access. Strip/replace client tenant headers; prevent direct ingest/admin/delete/
partition/system endpoint access. VictoriaLogs documents extra filters propagated into subqueries, but that is
not a substitute for validating the selected application query surface, including context expansion. Do not expose
unrestricted Grafana/VictoriaLogs UI simply because a backend can serve it. WF-041 owns the UI decision. [V2][V7][L5][L6]

The existing [collector research](operational-log-collection-recovery-boundaries.md) remains applicable: Vector's
Docker source is stateless/best effort; Alloy positions are not proof of atomic delivery; Fluent Bit oldest-chunk
queues do not prove preservation-aware eviction or source replay. VictoriaLogs documents Fluent Bit HTTP ingestion,
but that does not qualify restart-safe capture, two destination acknowledgements or local archives. No general
Docker grant follows from selecting a log backend. [V13]

Local [archive research](operational-log-local-archives-and-restore.md) establishes only candidate object primitives.
Neither a Loki chunk bucket nor a VictoriaLogs partition snapshot is automatically WF-039's verified gzip/JSONL
archive and recoverable lifecycle catalog. No SeaweedFS-to-Loki storage compatibility or continuous archive adapter
has been demonstrated. Required application orchestration remains attached to the diagnosis outcome, not a new
speculative telemetry platform.

## Resource evidence: what is actually known

| Evidence | What it says | Why it cannot certify this installation |
|---|---|---|
| VictoriaLogs vendor benchmark suite at the inspected tag | Uses Loghub/generated logs and defaults to 10,000 records/s; dataset download instructions cite 49 GB space. Bundled Compose uses VictoriaLogs **v1.52.0** against Loki/Promtail **2.9.0**. | Not the compared release pair, no Agent OS exception/correlation/restore workload, no current local hardware receipt; includes extra metrics/Grafana services and a privileged cAdvisor host mount. Do not run it unchanged. [V14] |
| VictoriaLogs top-level claims | Claims up to 30× less RAM/15× less disk than alternatives and links benchmark suites. | Vendor claims, not comparable current measured evidence. No numerical superiority is accepted here. [V1] |
| VictoriaLogs capacity guidance | Recommends 50% spare RAM/CPU and at least 20% spare disk. Disk-retention thresholds are checked periodically and retain at least two days, so they can overshoot. Cache-memory flags are not process memory caps. | Headroom guidance is not idle/peak sizing or a hard physical quota. [V1][V8] |
| Loki monolithic guidance | Describes monolithic use up to approximately 20 GB/day. | Deployment guidance, not a measured resource guarantee for our query mix. [L1] |
| Loki WAL documentation's real-world example | 5,000 series, roughly 5 MB/s, five-minute checkpoints, approximately 10–15 GB WAL-only disk. | Missing matching hardware/version/query/restore methodology; not comparable to this local workload or a fixed WAL budget. [L11] |

**No comparable fresh idle/ingest/search/tail/restore CPU/RSS/disk/latency measurements are available from this
bounded evidence set.** WF-040 cannot honestly close as a qualified selection on these numbers. This is not a claim
that no comparable benchmark exists anywhere. Query and restore costs plus collector/archive/storage overhead must
be measured separately and then summed; WF-038's 512 MiB collection limit is not a backend budget.

## Proposed next evidence step — requires human authority

**Authority update, 2026-10-15:** John approved the following comparison. The heading and proposal below retain
the original requested scope; see [preflight result](#approved-comparison-and-preflight-result) for its current
storage prerequisite. Approval is not backend selection or authority to bypass the stated stop conditions.

Run a **disposable backend-only comparison**, VictoriaLogs first and Loki second, to answer the concrete blocker:
can each serve the accepted search/context/raw-detail/old-restore query shape within a modest local envelope without
losing or silently rewriting input? This is a runtime qualification experiment, not product implementation or tests
of configuration/quality tools. No experiment is invoked by this note.

Proposed authorization boundary:

- Use the existing Docker runtime and repository wrapper conventions; inspect the available qualified wrapper path
  before execution. No host package installs, daemon changes or changes to production Compose/application files.
  Only exact OSS release images above, resolved to recorded immutable image digests before use; no `latest`,
  privileged containers, Docker socket mounts inside containers or host filesystem mounts outside owned scratch.
- One backend at a time, disposable private network; only loopback access if a host client is needed. No real
  application/project logs, credentials or user data. Synthetic native-Monolog-shaped records, generated library
  IDs and fixed source/control metadata; at most 256 MiB uncompressed corpus. No upstream 49 GB corpus download.
- At most 60 minutes execution total, 2 vCPU/2 GiB aggregate running-container limits, 8 GiB owned runtime/scratch
  data and at most 2 GiB additional image-download budget. Check available headroom before pulls; stop if enforcing
  the bound is unavailable or limits are reached. These are probe ceilings, not approved production sizing.
- Scratch configuration/data/receipts only under `.runs/notes/observability-backends/` subfolders. Track every owned
  container/network/storage path. Stop/remove owned runtime resources after results, preserve bounded evidence;
  do not prune shared images/volumes or touch concurrent work. Any broader runtime prerequisite returns for approval.

Use the identical recorded corpus/query set with current, delayed, same-time repeated, nested/empty/long-key,
malformed/raw/fragment and 30-/89-day-old records plus a retained older-than-90-day case. Include high-cardinality
correlation IDs as ordinary fields/metadata, not stream labels. Record input/accepted/query/restore counts and raw
payload digests separately; legitimate repeated emissions must remain distinguishable.

Measure idle, bounded steady ingestion/burst (up to 1,000 records/s within the corpus cap), two diagnosis queries plus
one tail, temporary old-data import/query and cleanup. Record elapsed time, CPU, RSS/peak, physical index/WAL growth,
query p50/p95 from a stated sample count, time to first tail result, restore duration and failures. Probe a backend
process restart after acknowledged writes using **only disposable data**; distinguish acknowledged/query-visible/
recovered counts. Verify tenant isolation primitives, query limits, pagination ties, late-data and purge/temporary
scope cleanup semantics. Do not change producer timestamps to make an old-data test pass.

This small probe cannot prove 14-/90-day capacity, current application authorization, hold-owner races, production
Docker source replay, SeaweedFS archival or four simultaneous ready restore scopes. Return those as named remaining
qualification items. Do not close the decision solely because one process starts or one query succeeds. Backend
selection still needs John's explicit acceptance after evidence and any contract conflicts are reconciled.

## Approved comparison and preflight result

**Historical preflight:** the monitored-scratch amendment was subsequently approved and the comparison completed;
see the [runtime results](log-backend-disposable-comparison-results.md). Preserve the initial stop below rather
than treating its prerequisite as a current approval request.

On 2026-10-15 John said **“I approve of the comparison.”** Read-only preflight then observed Docker Desktop
4.86.0 (236216), Docker Engine/client 29.7.2, API 1.55, Compose v5.3.1, Linux/ARM64 kernel 6.12.76-linuxkit,
cgroup v2 and containerd-backed overlayfs. The Docker engine reports 10 CPUs and 8,321,515,520 bytes memory; the
ARM64 host reports 10 CPUs/32 GiB memory. Forty-eight concurrent container statistics samples and a summed
125.9% CPU single sample are workload context, not a benchmark or guaranteed spare capacity. Host `df -h` reported
287 GiB free; Docker VM space and reserved runtime headroom have not been established.

The selected release images were absent from the local image inventory. No images were pulled or digests resolved.
`bin/exec` is `docker compose run --rm web`; a scratch-only `COMPOSE_FILE` is a potential wrapper path, not yet a
qualified launch. `bin/up` starts/builds the normal application stack and creates application scratch, so was not
used. No backend/corpus/container/network/volume/disk mount was created. No services or unrelated workloads were
changed, and no runtime cleanup is needed.

**Execution stopped before startup:** a qualified persistent per-run storage quota was not identified for the
approved 8 GiB bound. Ordinary bind mounts/volume names and free capacity do not enforce it. The repository's
[bounded-storage experiment](../../../docs/engineering/TERMINAL_EXECUTION.md#bounded-storage-and-private-git-experiment)
records a fixed-size APFS mounted-image lifecycle failure (continued ENOSPC/retained virtualization handles and
forced-detach cleanup). This is historical evidence against assuming that workaround is qualified, not a fresh
failure of VictoriaLogs/Loki. No new storage method was installed or tested. Neither absence of an existing wrapper
quota nor this historical failure proves that every possible Docker Desktop quota mechanism is impossible.

The approved plan explicitly requires stopping for unenforceable limits. A polling watchdog is not silently
substituted for a hard quota. A narrow proposed amendment is **monitored owned scratch for this synthetic probe
only**: retain the 8 GiB budget, cap input at 64 MiB, measure scratch every second and stop ingestion/owned processes
at 2 GiB or on monitor failure, with read-only container roots and bounded Docker logs. This leaves headroom but
cannot guarantee no overshoot or survival of supervisor failure. All other approved limits remain; this is not
production storage enforcement or authority for a new VM, disk-image mount or daemon configuration. John has not
yet approved this amendment. Alternatively, a qualified quota-backed scratch path can satisfy the original scope.

Preflight receipt is retained in the base checkout at
`.runs/notes/observability-backends/preflight-20261015/receipt.json`. It distinguishes read-only observations,
unexecuted wrapper/resource checks and zero runtime effects. WF-040 remains Open; the existing primary-source
candidate recommendation is unchanged, and **no fresh backend resource/recovery/query comparison is claimed**.

## Sources

Pinned VictoriaLogs v1.53.0:

- [V1 — Overview, storage, retention, deletion, multitenancy and capacity](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/README.md)
- [V2 — Query/tail APIs and extra filters](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/querying/README.md)
- [V3 — Data model and stream cardinality](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/keyConcepts.md)
- [V4 — LogsQL and stream_context](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/logsql.md)
- [V5 — FAQ and hard field/record limits](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/FAQ.md)
- [V6 — Ingestion APIs](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/data-ingestion/README.md)
- [V7 — Security and proxying](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/security-and-lb.md)
- [V8 — OSS flags](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/victoria_logs_common_flags.md)
- [V9 — JSON-line handler](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/app/vlinsert/jsonline/jsonline.go)
- [V10 — Storage flush/retention configuration](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/app/vlstorage/main.go)
- [V11 — LICENSE](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/LICENSE)
- [V12 — Enterprise-only flags](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/victoria_logs_enterprise_flags.md)
- [V13 — Fluent Bit HTTP integration](https://github.com/VictoriaMetrics/VictoriaLogs/blob/915d91904bf7f5be66ab00f88947ae7fc1431acc/docs/victorialogs/data-ingestion/Fluentbit.md)
- [V14 — Vendor benchmark suite](https://github.com/VictoriaMetrics/VictoriaLogs/tree/915d91904bf7f5be66ab00f88947ae7fc1431acc/deployment/logs-benchmark)

Pinned Loki v3.7.8:

- [L1 — Deployment modes](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/get-started/deployment-modes.md)
- [L2 — Filesystem object store limits](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/filesystem.md)
- [L3 — HTTP API](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/reference/loki-http-api.md)
- [L4 — Structured metadata](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/get-started/labels/structured-metadata.md)
- [L5 — Authentication and mTLS](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/authentication.md)
- [L6 — Tenancy](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/multi-tenancy.md)
- [L7 — Source limits/defaults](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/validation/limits.go)
- [L8 — Validation and ordering rejection](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/request-validation-rate-limits.md)
- [L9 — Retention](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/retention.md)
- [L10 — Deletion](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/logs-deletion.md)
- [L11 — WAL and failure caveats](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/wal.md)
- [L12 — LICENSE](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/LICENSE)
- [L13 — Merge iterator duplicate handling](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/iter/entry_iterator.go)

Release identities: [VictoriaLogs v1.53.0](https://github.com/VictoriaMetrics/VictoriaLogs/releases/tag/v1.53.0),
[Loki v3.7.8](https://github.com/grafana/loki/releases/tag/v3.7.8).
