# Operational log collection and recovery boundaries

## Question

For [WF-038 — Define log collection and failure recovery](../tickets/WF-038-define-log-collection-and-failure-recovery.md),
what can Docker/container collection and existing collector buffers actually recover, and where do durability,
resource and privilege claims require qualification?

## Short answer

Treat runtime capture, collector persistence and destination acceptance as separate boundaries. Prefer an
installation-owned collector with bounded persistent delivery queues and recoverable source positions. Neither
stdout success nor the words "disk buffer" establish end-to-end durability. At finite capacity, prolonged outages
force a real choice about data loss/backpressure; do not hide that choice behind promises of unlimited recovery.

No collector, backend, logging-driver change or Docker access is selected/authorized by this note.

## Evidence scope

Read-only inspection on 2026-10-15 of the planning worktree at `6263dc6e660f989ac425e4d07a90dc7bc990a92f` and the
primary sources below. Docker Markdown and Alloy documentation were read from their upstream default branches;
Vector documentation and a moving `master` source snapshot were inspected. These are current documentation/source
observations, not pinned-release qualification, installed behavior, a benchmark or a promise about a future tag.
The initial Vector single-file source URL returned 404; inspection used its actual `docker_logs/mod.rs` location.

Follow-up after John's newest-context choice inspected Fluent Bit documentation snapshot
`a67d56f2863246cd0c96cb02ea9c7823e4ed790a`, advertised release **v5.1.3** (published 2026-10-01), and that tag's
`flb_input_chunk.c` and Apache-2.0 LICENSE at source `d1be1ba389a481ea727462e9a4afeb4538f0365a`. No package was
installed or executed. Also inspected Moby's **v28.0.0** API 1.48 specification at source
`af898abe44662d9554fb15ee4d4a7307f1b8e315`; this is a versioned API example, not the observed host or a current
Docker selection. Guessed upstream buffering/API paths returned 404; the successful paths are cited below.

## Findings

### The application runtime is not a qualified collection system

[compose.yaml](../../../compose.yaml) declares the current web/PostgreSQL foundation and optional HTTPS services,
not the complete service graph planned in [WF-023](../tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md).
It has no explicit logging driver/options or collector. That means daemon settings govern unless overridden; the
actual live driver's settings were not inspected. Do not claim Docker's documented default is the observed host
configuration. The existing application's resource limits are not collector capacity measurements.

### Docker retention, forwarding and availability are separate concerns

- Docker documents `json-file` as its default, with no rotation by default. Docker recommends `local` for most
  non-Kubernetes uses; its default retained window is size-based, 20 MB × 5 files per container, with compression.
  These are documented defaults, not a duration guarantee or an approved Agent OS setting. [D1][D2][D3]
- Blocking delivery can propagate log backpressure to application stdout/stderr. Non-blocking delivery uses a
  finite in-memory buffer and drops new messages when full. Selecting non-blocking mode is therefore not a
  zero-loss availability switch; default buffer size is documented as `1m`. [D1]
- Docker explicitly warns against external tools accessing its internal `local`/`json-file` files. A host-path
  tailer is not automatically a supported or portable solution, especially across native Linux/Docker Desktop.
  Prefer a supported runtime source interface; qualify any exception rather than silently scraping internals. [D2][D3]
- The Fluentd driver can stop a container immediately when it cannot connect unless asynchronous mode is enabled.
  Its asynchronous buffer is in memory, finite, and can fail at capacity; writes have no timeout by default.
  This is not, by itself, the persistent source queue required for a restart-recovery claim. [D4]

### A durable sink buffer does not repair a stateless source

- Vector documents `docker_logs` as **best effort**, **stateless**, with **no acknowledgements**, requiring Docker
  permissions; by default it collects all containers. Explicit source enrollment is necessary to avoid collecting
  registered-project runtime logs. The source can merge Docker partial messages; that does not establish a
  persistent read cursor. [V1]
- The inspected `docker_logs/mod.rs` initializes capture time to now and maintains per-container last-log state in
  memory. This supports the documented restart-gap concern, not a guarantee that a restarted source replays all
  still-retained Docker history. The exact chosen release must be checked before relying on it. [V4]
- Vector disk buffers provide restart recovery for buffered events, with checksums and an approximately 256 MiB
  minimum per buffer. The documented default sync interval is 500 ms, not fsync per event. Storage errors can make
  Vector exit. A configured buffer-size maximum does not ensure other host processes leave enough space. [V2]
- Vector's end-to-end acknowledgements require participating sources and sinks. A non-acknowledging source cannot
  acquire that guarantee merely by enabling a downstream option. Its `block`/`drop_newest` policies make the
  finite-capacity tradeoff explicit; overflow buffering is documented as not production-ready. [V2][V3]

### Other existing components expose different tradeoffs, not an automatic winner

- Alloy's `loki.source.docker` documents a persistent positions file and resumption from that position after
  restart. This is a candidate for source replay, not proof of atomic checkpoint-to-destination durability. It
  reads through the Docker daemon; source/target filtering is not daemon API authorization. [A1][A3]
- Alloy's inspected `loki.write` documentation marks its queue configuration and WAL as experimental. WAL is
  disabled by default; documented default segment maximum age is one hour, after which old segments are eventually
  deleted. Endpoint retries default to ten; zero means indefinite retries. Ordinary HTTP 4xx other than configured
  429 are not retried. Multi-endpoint fanout can couple slow destinations. These settings require explicit
  reconciliation with retention/recovery requirements; "enable WAL" is not a completed design. [A2]
- Alloy's source and writer component-health indicators document invalid-configuration health, not a complete
  delivery-outage indicator. Readiness/last-read time, queue/dropped-entry/error observations and an independent
  supervisor/status path are needed to avoid reporting healthy collection solely from process/component state.
  This does not approve a separate metrics platform or alerting product. [A1][A2]

### Native oldest-chunk eviction exists, but is not a complete qualified pipeline

- Fluent Bit documents filesystem-backed queues with a per-output `storage.total_limit_size`. At capacity, its
  oldest queued chunk is discarded to admit newer data. This is a native route toward John's WF-038 preference,
  not a reason to build a custom spool or select Fluent Bit in advance of source/backend qualification. Chunks
  average about 2 MB; the documentation does not define an exact universal chunk-byte maximum. [F1]
- The inspected v5.1.3 source includes queue-space reclamation, per-output route removal, warning records and
  dropped-record/byte counters where counts can be obtained. Safe deletion and other output routes affect physical
  reclamation. This is not proof of global event-time order, preservation-aware eligibility, independent durable
  loss reporting or bounded total process/filesystem consumption. Verify those separately. [F2]
- The documented retry default is one retry; exhausted retries normally discard data. Retries can instead continue
  without a count limit while each attempt/backoff/resource use remains bounded. A destination's permanent-error
  return is distinct from retryable failure. Do not allow stock retry defaults to create an extra unapproved loss
  policy. [F3]
- Fluent Bit's documented DLQ retains rejected internal chunks, but is unlimited unless explicitly capped. At its
  cap it skips **new** rejected chunks, not oldest-first eviction; files remain until manually removed. Thus simply
  enabling a bounded DLQ does not implement the chosen newest-context policy for rejected data. [F4]
- Storage documentation offers `full` synchronization, checksum validation and restart-backlog memory controls.
  Checksum validation is off by default, and a backlog-memory limit is not a process-memory bound. These are useful
  capabilities, not proof of atomic source-checkpoint/persistence ordering or power-loss durability on the selected
  host/filesystem. Apache-2.0 source licensing was inspected; exact distribution/plugin/dependency licensing and
  security/host compatibility remain qualification requirements. [F5][F6]

### Docker source positions and driver compatibility need direct checks

Moby v28.0.0's versioned log API describes stdout/stderr, follow, timestamps, since/until and tail, but does not
provide a durable byte-offset cursor or exactly-once receipt. Timestamp restart boundaries can replay multiple
records; timestamps are not unique identities. This specification names `json-file`/`journald`, whereas Docker's
`local` driver documentation describes reading through `docker logs`. Do not turn that documentation difference
into a tested compatibility claim or declare either source path qualified. Verify the actual supported
Engine/API/driver/collector combination, including TTY stream merging and source retirement. [D2][D6]

### Collection privileges must not flow to viewers

Docker documents daemon control as privileged and recommends only trusted users control it. A read-only filesystem
mount of its Unix socket is not a method-authorization policy. Source include-label settings constrain intended
collection, not what a compromised daemon client could request. Applications, viewers and Agent sessions must not
receive Docker credentials/socket access through the log capability. A privileged installation reader or constrained
API boundary needs an explicit, qualified trust design; this note does not approve a new general Docker client. [D5]

## Engineering direction returned to WF-038

- Keep selected services on structured stdout/stderr. Use an installation-owned, explicitly enrolled source reader
  and an existing collector rather than a custom runner logging protocol or an application-to-search dependency.
- Require persisted source identity/position and pending-delivery state where replay is supported. Distinguish
  emitted, runtime-retained, collector-received, collector-persisted, destination-accepted, searchable and archived.
  Record what is actually observable; do not manufacture per-record receipts for non-acknowledging sources.
- Keep source-retention bytes and collector-queue bytes independently finite. Size outage coverage from measured
  byte rates and actual resource overhead, not searchable-retention days or unmeasured hours of recovery.
- The initial research recommendation favored preserving queued records and pausing source ingestion. John
  subsequently selected newer context/oldest-eligible eviction in WF-038. Its
  [accepted exhaustion direction](../tickets/WF-038-define-log-collection-and-failure-recovery.md#accepted-partial-direction--favor-newer-logs-at-exhaustion)
  owns the decision; the initial recommendation is not current policy. Qualify actual eviction granularity,
  independent loss reporting and preservation eligibility rather than silently substituting drop-newest.
- Local disk queues do not survive destruction of that storage. Continuous archival/off-machine recovery remains
  WF-039's decision; a queue is neither an archive nor authoritative execution evidence.
- WF-038 now supplies the engineering capacity/time/concurrency/framing envelope and required loss/receipt semantics.
  Its closure resolves a capability contract, not these candidates' qualification or a measured recovery horizon.
  WF-040 must qualify a composed source/collector/backend path; WF-039 resolves preservation/archive consequences.

## Sources

- [D1 — Docker: Configure logging drivers](https://docs.docker.com/engine/logging/configure/)
- [D2 — Docker: Local logging driver](https://docs.docker.com/engine/logging/drivers/local/)
- [D3 — Docker: JSON-file logging driver](https://docs.docker.com/engine/logging/drivers/json-file/)
- [D4 — Docker: Fluentd logging driver](https://docs.docker.com/engine/logging/drivers/fluentd/)
- [D5 — Docker daemon attack surface](https://docs.docker.com/engine/security/#docker-daemon-attack-surface)
- [D6 — Moby v28.0.0 API 1.48: ContainerLogs](https://github.com/moby/moby/blob/af898abe44662d9554fb15ee4d4a7307f1b8e315/docs/api/v1.48.yaml#L8162)
- [V1 — Vector: Docker logs source](https://vector.dev/docs/reference/configuration/sources/docker_logs/)
- [V2 — Vector: Buffering model](https://vector.dev/docs/architecture/buffering-model/)
- [V3 — Vector: End-to-end acknowledgements](https://vector.dev/docs/architecture/end-to-end-acknowledgements/)
- [V4 — Vector source: Docker logs](https://github.com/vectordotdev/vector/blob/master/src/sources/docker_logs/mod.rs)
- [A1 — Alloy: Docker log source](https://grafana.com/docs/alloy/latest/reference/components/loki/loki.source.docker/)
- [A2 — Alloy: Loki writer](https://grafana.com/docs/alloy/latest/reference/components/loki/loki.write/)
- [A3 — Alloy: Docker discovery](https://grafana.com/docs/alloy/latest/reference/components/discovery/discovery.docker/)
- [F1 — Fluent Bit buffering, pinned documentation](https://github.com/fluent/fluent-bit-docs/blob/a67d56f2863246cd0c96cb02ea9c7823e4ed790a/pipeline/buffering.md)
- [F2 — Fluent Bit v5.1.3 chunk/queue implementation](https://github.com/fluent/fluent-bit/blob/d1be1ba389a481ea727462e9a4afeb4538f0365a/src/flb_input_chunk.c)
- [F3 — Fluent Bit scheduling/retries, pinned documentation](https://github.com/fluent/fluent-bit-docs/blob/a67d56f2863246cd0c96cb02ea9c7823e4ed790a/administration/scheduling-and-retries.md)
- [F4 — Fluent Bit DLQ, pinned documentation](https://github.com/fluent/fluent-bit-docs/blob/a67d56f2863246cd0c96cb02ea9c7823e4ed790a/administration/dead-letter-queue.md)
- [F5 — Fluent Bit storage settings, pinned documentation](https://github.com/fluent/fluent-bit-docs/blob/a67d56f2863246cd0c96cb02ea9c7823e4ed790a/administration/configuring-fluent-bit/yaml/service-section.md#storage-configuration)
- [F6 — Fluent Bit v5.1.3 LICENSE](https://github.com/fluent/fluent-bit/blob/d1be1ba389a481ea727462e9a4afeb4538f0365a/LICENSE)
- [F7 — Fluent Bit v5.1.3 release](https://github.com/fluent/fluent-bit/releases/tag/v5.1.3)

## Open questions

- The exhaustion preference is now settled in WF-038: favor newer context. Exact oldest-eligible eviction support,
  independent loss accounting and preservation-boundary reconciliation remain qualification inputs. No lossless
  unbounded continuation is claimed.
- Exact supported source/replay adapter and its privileged boundary across the supported host profiles; source
  retirement for short-lived managed containers must not silently destroy the only uncollected copy.
- WF-038 sets finite engineering limits; actual process/filesystem overhead, throughput and outage coverage still
  require qualification. No representative source-volume or resource experiment has been authorized/performed.
- Pinned collector release, license/security review, backend connector/acceptance semantics and atomicity/loss
  windows across source positions, collector persistence and destination acknowledgement. No product is qualified.
- Archive fanout/isolation, off-machine storage loss and replay into the chosen searchable backend remain
  WF-039/040 qualification inputs; no SeaweedFS compatibility is assumed.
