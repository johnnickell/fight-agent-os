# Select the log search backend

**Labels:** `wayfinder:research`, `wayfinder:grill`
**Mode:** AFK + HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** [WF-039](WF-039-define-searchable-retention-and-verified-archives.md)

**Current resolution:** John approved Loki OSS as the conditional planning backend and the explicit
qualification-timing amendment. See [accepted resolution](#accepted-resolution--conditional-planning-selection).
Earlier Open/pending recommendations below are historical, not the current decision state.

## Question

Which qualified backend best supports the agreed sources, diagnostic queries, live tail and retention/archive
contract within an acceptable Agent OS installation footprint?

## Accepted archive and restore inputs

Closed [WF-039](WF-039-define-searchable-retention-and-verified-archives.md) selects verified continuous compressed
archives, 90-day ordinary retention, local-only initial protection and authorized browser search through temporary
restore. It supplies collection-time retention clocks, hold-aware cleanup, archive-full behavior and finite archive/
restore profiles. Off-machine backup is deferred; this does not weaken existing preservation owners. Qualify the
entire path, not just fast queries: native retention must not discard protected sole copies, old timestamps must
remain honest, and temporary scopes must be isolated, bounded, revocable and removable. No download-only substitute.

[Local archive research](../research/operational-log-local-archives-and-restore.md) identifies SeaweedFS object API,
security/default/quota and recovery caveats. It selects no deployed storage release or backend. Verify exact tagged
behavior rather than treating moving wiki claims, object checksums or S3 compatibility as composed recovery evidence.

## Must decide

- Compare VictoriaLogs and Loki first against actual debugging journeys, not general popularity. Consider
  OpenObserve and OpenSearch only where their capabilities justify added operational or product scope. No backend
  has been selected, including by WF-023's historical deferral or this research assignment.
- Verify current exact releases and primary-source licensing, community-edition feature availability and any
  paid-only dependencies, authentication/authorization/tenant capabilities, redistribution and deployment limits.
- Verify structured ingestion, query language, text/field/time/service/severity/identifier filters, context windows,
  pagination/order/limits and live-tail/search/detail/export APIs. Distinguish supported API contracts from browser
  screenshots, Enterprise features or unsupported UI embedding.
- Assess low/high-cardinality field treatment, exception/cause payloads, redaction/purge capabilities and safe
  indexing of sensitive details; avoid placing every run/correlation identity in expensive labels by assumption.
- Verify single-node deployment dependencies, collectors, persistent storage, recovery, upgrades, failure modes,
  searchable-retention semantics and archive/export/restore options against WF-038/039. Qualify the composed
  source/reader/collector path: checkpoint/persistence ordering, native oldest-eligible eviction and hold eligibility,
  bounded rejection/oversized-byte preservation, actual physical resource enforcement and independent loss status.
  Full-daemon-socket exposure requires explicit security acceptance; a source filter is not authorization. The
  collection profile excludes backend resources; measure/count those separately. SeaweedFS compatibility and
  availability need direct evidence; object storage is not assumed to be every backend's live index.
- Compare measured idle/ingest/query/tail resource needs: CPU, resident memory, disk growth/compression, log volume,
  retention, cardinality, concurrency, latency, hardware, versions and methodology. Distinguish independent tests,
  vendor benchmarks, minimum configuration and actual local measurement. Cite limits; do not invent a sizing result.
- Identify smallest useful installation, realistic growth and operator workload; state the chosen backend's
  tradeoffs and explicit conditions blocking qualification or requiring a future approved experiment.

## Resolution boundary

Research primary sources into `planning/wayfinder/research/`, then return evidence and an explicit recommendation
for human acceptance in this decision. Follow the research skill when invoked. Planning authority permits no
installation, service startup, benchmark deployment or runtime configuration. Missing comparable resource evidence
remains unresolved; ask for separately bounded experiment authority if necessary. Do not silently weaken settled
collection/storage requirements to make a preferred product win. Browser integration is WF-041.

## Primary-source comparison and recommendation — pre-experiment

The following recommendation and resource gap are historical. The completed experiment and explicit recommendation
amendment below now govern; comparison approval did not constitute backend selection.

[VictoriaLogs and Loki comparison](../research/log-backend-victorialogs-loki-comparison.md) records pinned
VictoriaLogs **v1.53.0** (`915d91904bf7f5be66ab00f88947ae7fc1431acc`) and Loki **v3.7.8**
(`09e6ce2ff1bdc19763a10265b870c86f51c98655`) documentation/source, community-feature/licensing boundaries, APIs,
limits, restore/retention conflicts and resource-evidence limitations. No installation/runtime probe occurred.

Recommend **VictoriaLogs OSS as the first qualification candidate**, not a selected backend. Direct indexed
field/full-text queries, native surrounding-stream context, local single-process storage and Apache-2.0 licensing
fit the diagnosis outcome. Loki remains the comparator: it supports high-cardinality structured metadata (not
stream labels), useful query/tail APIs and a WAL; do not dismiss it through a competitor's overbroad marketing claim.
No demonstrated need yet justifies widening to OpenObserve/OpenSearch.

Neither candidate is a drop-in match:

- Both require application-owned current permission enforcement. Tenant headers are routing/scope, not user
  authorization. No direct browser access to unrestricted backend/administrative APIs is accepted.
- Both have timestamp-age, retention and shape limits. A 14-day live index cannot simply absorb a 90-day temporary
  restore. Probe isolated restore storage with sufficient age bounds and unchanged original clocks. VictoriaLogs'
  filter deletion is exceptional/storage-rewriting, not assumed cheap per-job TTL; separate disposable storage is
  a proposed qualification path, not an approved runtime topology.
- Native expiry does not know Agent OS holds or verified archive state. Prove another protected copy exists before
  native expiry can remove the sole protected search copy, or qualify affected cleanup gating. This contract gap
  remains unresolved; neither product's default retention may silently override WF-039.
- VictoriaLogs can return normally for a partially invalid JSON-line batch and flushes memory periodically. Loki's
  WAL has explicit disk-full/corruption caveats and its merge iterator can collapse identical entries. Qualify
  accepted/durable/query-visible counts, exact raw payload recovery and distinct legitimate repeated observations.
- Collector replay/privileges, independent archive receipts, hold-aware eviction and SeaweedFS/catalog integration
  remain composed requirements. A supported HTTP output does not establish the whole pipeline.

## Resource evidence and qualification gate — pre-experiment

There is **no fresh comparable resource receipt** for these releases and the accepted workload. The pinned
VictoriaLogs benchmark suite uses VictoriaLogs v1.52.0 versus Loki/Promtail 2.9.0, with a different log corpus and
extra services including a privileged cAdvisor mount. Do not run it unchanged or repeat its marketing ratios as
current Agent OS sizing. Loki's WAL example and monolithic volume guidance are not a comparable local benchmark.
The research note separates actual published observations from unmeasured resource claims.

WF-040 remains **Open**. Required remaining evidence includes current-version bounded query/restore fidelity and
resource cost, a concrete protected-copy/expiry composition, source/collector/archive integration and exact image/
distribution qualification. A backend-only probe narrows these gaps; it cannot certify production authorization,
all recovery/hold races or 14-/90-day capacity. No human backend-selection approval has been given.

## Approved bounded disposable comparison

John approved this comparison on 2026-10-15: **“I approve of the comparison.”** This authorizes only the [scoped backend-only experiment](../research/log-backend-victorialogs-loki-comparison.md#proposed-next-evidence-step--requires-human-authority):
VictoriaLogs first, Loki second, exact pinned OSS images, synthetic data only, one backend at a time, at most
60 minutes execution, aggregate 2 vCPU/2 GiB running-container limits, 8 GiB owned scratch/runtime data and 2 GiB
additional image-download budget. Use the existing qualified Docker/wrapper path; stop for missing prerequisites
or unenforceable limits. No host package installs, daemon/runtime application changes, privileged containers,
container socket mounts, real project logs, general Docker grants or production data access. Only disposable
scratch configurations and owned cleanup, preserving bounded evidence and all unrelated resources.

Measure query/context/tail, raw/duplicate fidelity, old-log isolated import, process-restart receipt boundaries,
cleanup and idle/ingest/query/restore resources. This is direct runtime qualification, not an implementation TASK
or a product-suite test of infrastructure. Permission is granted; read-only preflight ran, but backend execution
has **not** begun. After evidence, return here to reconcile contract gaps and request explicit backend selection;
do not advance to WF-041 merely because one service starts or one query works.

## Historical preflight result — storage bound blocked execution

Read-only preflight found Docker Desktop 4.86.0, Engine 29.7.2/API 1.55, Compose v5.3.1, Linux/ARM64,
cgroup v2 and containerd-backed overlayfs. The engine advertises 10 CPUs and 8,321,515,520 bytes RAM; 48 running
container samples demonstrate a shared, concurrently active environment, not a quiet benchmark host. The host
filesystem reported 287 GiB available, which proves neither VM headroom nor a per-run storage quota.

No qualified persistent per-run disk ceiling was identified for the approved 8 GiB bound. A bind mount/volume name
is not a quota. The existing [storage experiment](../../../docs/engineering/TERMINAL_EXECUTION.md#bounded-storage-and-private-git-experiment)
records failed APFS mounted-image lifecycle qualification and forced-detach cleanup; that historical workaround
was not repeated. This is a prerequisite gap, not evidence that either backend fails or that every possible quota
mechanism is impossible. The approved instruction to stop for unenforceable limits governs.

`bin/exec` can select a scratch Compose file through Compose's environment, but its `run --rm web` path and the
comparison's isolated service/network configuration have not been exercised. `bin/up` starts/builds the application
stack and was not used. No images were pulled, containers/networks/volumes created, disk images mounted, corpus
generated or daemon/application configuration changed. There are no owned runtime resources to clean up.
Receipt: base-checkout `.runs/notes/observability-backends/preflight-20261015/receipt.json`; the
[comparison note](../research/log-backend-victorialogs-loki-comparison.md#approved-comparison-and-preflight-result)
records scope and remaining evidence. No backend performance result is available.

Recommend a **probe-only amendment**, pending John: use monitored owned scratch instead of claiming a hard
filesystem quota. Retain 8 GiB as the experiment's disk budget; reduce input to at most 64 MiB and stop ingestion/
owned backend processes at 2 GiB measured scratch use, polling every second and failing closed on monitor errors.
Use read-only container roots, bounded container logs and explicit ownership-checked cleanup; keep all approved
CPU/RAM/time/image/security limits. Monitoring can overshoot or fail and is **not** guaranteed 8 GiB enforcement.
This relaxation would qualify query/resource behavior only, not production hard-storage limits. Do not install a
new VM/storage facility, reuse the failed disk-image workaround or broaden authority without separate approval.

## Monitored-scratch amendment approved

John approved the preceding probe-only amendment on 2026-10-15: **“approved.”** The initial preflight stop above
remains historical evidence, not a current request to repeat approval. Proceed with monitored scratch, at most
64 MiB synthetic input, one-second monitoring and stopping owned ingestion/backend processes at 2 GiB measured
scratch use or monitor failure. Overshoot/supervisor failure remain disclosed; this is not a hard storage quota.
All other comparison limits and exclusions remain unchanged. No production backend selection is implied.

## Completed comparison and recommendation amendment

The [disposable comparison results](../research/log-backend-disposable-comparison-results.md) record the approved
experiment, exact Linux/ARM64 image digests, configuration, 12,000-record common corpus, measurements, failed
attempts/corrections and cleanup. Execution spanned 24 minutes 31 seconds, within the 60-minute authority.
Monitored scratch peaked at about 54 MiB allocated; no hard quota is claimed. Both images remain cached, but all
13 attempted owned container/network/data lifecycles were cleaned. No application/daemon configuration, real logs,
production service or source project changed. This authority is not an open-ended runtime-test allowance.

Findings that change the decision:

- Both returned all 12,000 records with matching full raw-export digests, delivered 20/20 fresh tail markers and
  reconstructed the same binary payload from ten bounded pieces. Both can expose five-event source context;
  Loki needs two correctly bounded range queries rather than a native context operator.
- Both retained twelve distinct same-time observation IDs, but time-only next-page advancement skipped remaining
  ties. Loki collapsed exactly identical transport duplicates; explicit observation identity remains necessary.
- Both eventually queried all 300 synthetic old records (30/89/120 days) with raw/event/collection clocks intact.
  Loki first returned zero despite HTTP 204 import acceptance; longer age settings and `query_ingesters_within: 0s`
  alone were insufficient. Isolated `POST /flush` plus count/content verification made the final import queryable
  at the first five-second poll. This is synthetic import, not verified SeaweedFS archive recovery.
- VictoriaLogs returned HTTP 200 while dropping oversized and out-of-retention inputs in these probes; Loki
  explicitly returned HTTP 400. Both default to a 256 KiB input-line bound. Bounded framing and independent loss/
  rejection dispositions are required, not truncation or treating HTTP success as per-record acceptance.
- An immediate owned process crash after 50 acknowledged fresh markers recovered **0/50 in VictoriaLogs versus
  50/50 in Loki**. After a 60-second delay both recovered 50/50. These are process-crash observations, not
  fsync/power-loss/exactly-once guarantees or a proposed fixed-sleep receipt protocol.
- VictoriaLogs used much less process memory: median idle RSS about **14 MiB versus 116 MiB**, sampled query/tail
  peaks **82 MiB versus 195 MiB**. Query medians were lower, but p95s were close in this small shared-host corpus.
  Offered rate was capped at 1,000 records/s; disk figures include different WAL/flush states and cannot establish
  a production compression or 14-/90-day sizing ratio. Detailed methodology/limits are in the results note.

**Amend the recommendation to Loki OSS for the next integration qualification**, not a selected or fully qualified
backend. For this diagnosis/recovery outcome, clearer permanent-rejection behavior and the demonstrated WAL crash
path outweigh the observed small-installation memory penalty. VictoriaLogs remains the lighter fallback candidate,
with Apache-2.0/native-context advantages, but needs an explicit solution to its receipt/loss gaps. Loki's AGPL-3.0,
production storage and other operational costs remain visible. No human backend-selection approval is recorded.

## Remaining gate before the approved timing amendment

WF-040 remains **Open**. The experiment narrows query/resource/old-import uncertainty; it does not qualify the
whole source → collector → search/archive → authorized temporary browser path. Next reconcile a concrete
Loki-first composition with WF-038/039, including:

1. Enrolled reader privileges/replay, independently bounded persistent destinations, permanent rejection accounting,
   newest-eligible eviction and protected-only capacity conflict. A WAL is not source checkpoint atomicity.
2. Supported production storage and SeaweedFS compatibility if proposed; the probe's filesystem store is not that
   decision, and Loki chunks are not the accepted verified gzip/JSONL archive/catalog.
3. A protected-copy-before-expiry or gated-cleanup design that cannot silently destroy sole preserved data; native
   search TTL has no Agent OS hold/verified-archive authority.
4. Tie-safe pagination and current authorization/revocation, temporary ready-scope resources/expiry/hold/purge races,
   representative volume sizing, image security/provenance and license/distribution qualification.

These are integration requirements of the accepted product outcome, not new speculative TASKs. Do not weaken
closed guarantees, authorize a runtime integration probe or advance to WF-041/decomposition through this result.
Final human backend acceptance follows evidence and explicit reconciliation, not one successful benchmark.

## Reconciled planning proposal — accepted by the resolution below

The [Loki-first integration reconciliation](../research/loki-integration-reconciliation.md) supplies the proposed
ownership/composition, deletion design, source-backed receipt caveats and explicit unresolved acceptance owners.
This was read-only work; no new runtime experiment or product implementation was performed.

The important engineering change from the probe configuration is **application-owned expiry/deletion, not native
14-day auto-deletion**. Loki cannot check Agent OS holds or independently verified archive copies. Its documented
explicit-deletion mode with automatic age retention disabled can support an owner-gated cleanup design. The
application still enforces the accepted 14-day live/90-day archive policy and bounded admission; backend zero-TTL
configuration is not unlimited user retention. An existing-owner fence must protect hold/late-ingest/purge races
through asynchronous deletion; merely checking before submission is insufficient. That owner integration and
physical capacity enforcement remain blockers, not proven features.

Prefer monolithic Loki/TSDB with a persistent WAL and qualified private S3 storage, using the planned SeaweedFS
capability only if its exact integration succeeds. Keep Loki chunks/indexes separate from verified gzip/JSONL
archives and authoritative Artifacts, with distinct credentials/accounting/lifecycles. The filesystem probe is not
production storage approval. Temporary imports use isolated scopes, honest original clocks and actual query-ready
verification after flush where needed; current permissions and immediate scope denial remain application-owned.

Further pinned-source inspection qualifies the earlier benchmark preference:

- Distributor validation can forward valid records and still return a batch error. Ingester error aggregation is
  not a per-record receipt. WAL disk-full handling can accept data without the promised persistence.
- Blocked ingestion defaults to custom **HTTP 260**, within the 2xx range; a configurable HTTP 200 blocking mode
  also exists. A stock sink accepting every 2xx response would misclassify loss. Qualify actual sink status handling,
  mixed/partial outcomes and independent dispositions; retain original observation identities on uncertainty.
- Metadata normalization is not raw-content preservation. Keep exact original content in the qualified envelope.

These are source-backed limitations, not newly reproduced runtime failures. Loki remains the preference for its
observed behavior and WAL, not a guarantee that all HTTP successes are lossless. No current collector/reader
configuration is proven to compose durable checkpoints, hold-aware oldest-eligible eviction, native framing,
independent destination receipts and physical limits. Conditional Loki selection would not select such a collector,
approve a custom queue/proxy/plugin or grant a Docker socket.

### Proposed explicit qualification-gate amendment

**Recommend:** accept Loki OSS as the **conditional planning backend direction**, with the reconciliation above;
move the still-unproven composed runtime/application checks from prerequisites to **WF-040 planning closure** into
mandatory integration/delivery gates carried by WF-042 and the existing owners listed in the reconciliation.
Before any work is presented as executable, WF-042 must retain each concrete blocker with its owner and verification
path. Unresolved design conflicts still return to WF-038/039 or their existing authority owner. No requirements,
source/privacy/preservation guarantees, physical-limit obligations or release evidence are waived.

This is an explicit proposed change to the qualification timing assigned to WF-040 by WF-038/039, not an assertion
that their current gate has passed. If John accepts, append the amendment to those historical handoffs, close
WF-040 **as a conditional planning selection only**, preserve the unresolved gates and route to WF-041. Do not
change those Closed decisions or close this one before acceptance. Selection would not authorize implementation,
new experiments, publication or production enrollment; Loki's AGPL distribution/image qualification remains required.

**Alternative:** keep the current preselection qualification gate, leave WF-040 Open and scope the missing
engineering/runtime evidence first. Further runtime work needs separately bounded authority; the completed
comparison grant does not extend to source, SeaweedFS or full-pipeline experiments.

## Accepted resolution — conditional planning selection

John approved the conditional Loki selection and qualification-timing amendment: **“approved.”** Close WF-040
as a **planning decision only**, not completed implementation, a fully qualified pipeline or production adoption.
The approved direction is Loki OSS with the reconciliation above: application-owned expiry/deletion under existing
preservation/privacy owners, separate verified archives, original-clock temporary restore and current-authorized
bounded access. The accepted 14-day live/90-day archive policy remains; autonomous backend TTL may not bypass
holds or verified-copy checks. No new hold authority is created.

The studied Loki v3.7.8 source/image identities are qualification evidence, not a floating dependency or permission
to install them. Production image/security/license review, supported storage and deployment sizing remain required.
The planned private SeaweedFS integration is conditional on qualification; neither it nor a collector/reader or
new Docker grant is selected by this resolution.

**Explicit gate amendment:** the composed runtime/application checks previously assigned to WF-040 as prerequisites
to planning selection remain mandatory but move to integration/delivery gates carried into
[WF-042](WF-042-bound-observability-delivery-and-acceptance.md). The corresponding amendments are appended to
[WF-038](WF-038-define-log-collection-and-failure-recovery.md#approved-qualification-timing-amendment) and
[WF-039](WF-039-define-searchable-retention-and-verified-archives.md#approved-qualification-timing-amendment).
Their source/access/loss/preservation/physical-limit contracts are unchanged. WF-042 must preserve each unresolved
blocker, its existing owner and verification path before presenting delivery work as executable. Design conflicts
still return to the owning decision; no unsupported mechanism becomes accepted through this timing change.

[WF-041](WF-041-select-the-authorized-log-browser-integration.md) may now choose browser integration using the
measured and proposed APIs with their disclosed limits. It must not treat tenant routing, import acceptance,
backend HTTP success or planning closure as current authorization, ready restoration or durable per-record receipt.
The collector, storage, fencing, partial-delivery, resource and application gates remain explicit in the
[integration reconciliation](../research/loki-integration-reconciliation.md#required-acceptance-ownership-and-proposed-gate-amendment).

No new runtime experiment, implementation, dependency update, deployment, publication or EPIC/TICKET/TASK creation
is authorized. The terminal-first milestone/Board priority remains unchanged. This acceptance records no fresh
runtime verification; only the earlier bounded comparison has runtime receipts within its stated scope.

Next: `/skill:wayfinder work WF-041`.
