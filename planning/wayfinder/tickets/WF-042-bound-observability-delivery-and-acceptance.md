# Bound observability delivery and acceptance

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** [WF-041](WF-041-select-the-authorized-log-browser-integration.md)

**Current resolution:** John approved the complete release boundary, adjacent-product deferrals and single EPIC
handoff. WF-042 is Closed as a planning decision. The original proposal below is retained as history; the
[accepted resolution](#accepted-resolution-and-epic-handoff) governs. The subsequently confirmed grill has written
[EPIC-00019](../../epics/00019-EPIC.md); see the [completed handoff](#epic-handoff-completed). Runtime qualification
remains outstanding.

## Question

What smallest useful release completes the agreed operator diagnosis journey, with observable acceptance and a
clear EPIC handoff that does not automatically block the existing terminal-first milestone?

## Mandatory qualification gates carried from WF-040

John approved [WF-040's conditional Loki planning selection](WF-040-select-the-log-search-backend.md#accepted-resolution--conditional-planning-selection)
and the explicit amendment appended to WF-038/039: composed runtime/application qualification is not a prerequisite
to closing the backend **planning** decision, but remains mandatory for integration/delivery. This transfer does
not resolve this decision or waive a requirement. Before presenting delivery work as executable, preserve each
concrete blocker, its existing owner and a bounded verification path. Unresolved design conflicts return to the
owning decision; missing runtime evidence requires separately authorized execution, not invented receipts.

| Mandatory gate | Existing owner | Required evidence / blocking effect |
|---|---|---|
| Reader enrollment/privilege, source replay/retirement and durable checkpoint ordering | WF-038 and runtime owner | Exact source/driver/reader contract, accepted privileges and recovery/retirement probes; no production enrollment without it |
| Native bounded queues/framing, oldest-eligible eviction, protected-only conflict and independent status | WF-038 | Full-source/level/raw coverage, physical bounds, fault/eviction/disposition evidence; no assumed collector, custom queue or hidden spool |
| Loki partial/uncertain acceptance, blocked-ingestion 260 and WAL fault handling | WF-038/040 | Exact sink response semantics, stable observation identity, bounded rejection/retry and independent receipts; generic 2xx success is insufficient |
| Supported private storage and actual resource enforcement | WF-038/039/040 and deployment owner | Exact S3/SeaweedFS compatibility if selected, namespace/credential isolation, WAL/index/archive accounting, durable restart and physical limits; probe filesystem/monitored scratch is not production approval |
| Continuous verified archives and recoverable catalog | WF-039 | Intent/upload/readback/verification/publication recovery, independent destinations, original-clock retention and corruption/capacity evidence; Loki chunks are not archives |
| Owner-supported cleanup fencing and sole-copy preservation | WF-017/039 and existing preservation/privacy owners | Hold/late-ingest/purge/policy races through asynchronous deletion, verified copies and reclamation; unsafe cleanup remains disabled when authority/fencing is unavailable |
| Current-authorized browser and temporary restore | WF-039/041 | Tie-safe bounded context/pages, readiness/content verification, revocation/expiry, partial/error states and scope cleanup/resources; tenant/restore IDs are not access grants |
| Representative sizing, distribution and complete delivery acceptance | WF-040/042 and engineering owners | Real-volume/concurrency/failure measurements, exact image provenance/security/license review, owned behavior tests and canonical full gate before release acceptance |

Use the [integration reconciliation](../research/loki-integration-reconciliation.md) for source-backed caveats and
ownership boundaries. Validate infrastructure directly with owning tools; test owned application behavior through
the eventual use cases. The existing small synthetic comparison supplies only its stated backend observations.
No reader/collector, production storage deployment or new Docker grant is selected, and no EPIC/TICKET/TASK or
runtime experiment is authorized by this gate transfer. These gates remain mandatory after this planning closure.

## Accepted browser input and outstanding qualification

[WF-041](WF-041-select-the-authorized-log-browser-integration.md#approved-audience-resolution) selects native Logs
in the existing Agent OS shell, with **Super Admin-only access initially**. Its four protected intent-specific
permissions follow the existing managed-policy owner; no custom-role/direct-Agent delegation or authority-policy
relaxation is selected. No catalog definitions or assignments have yet changed. Non-admin log operators are deferred,
not an automatic TASK. Raw diagnostics remain separate from sanitized Workflow Activity and authoritative evidence.

Carry [WF-041's engineering contract and qualification handoff](WF-041-select-the-authorized-log-browser-integration.md#qualification-handoff-and-closure)
into delivery acceptance, including:

- Protected catalog extension/enrollment, current operation/scope checks, denied non-admin/custom-role/direct-Agent
  access and authority-loss races; no Role-name bypass or grant inferred from a URL, project link or restore ID.
- Private Loki translation and actual authenticated fetch-stream/ingress cancellation, lease, authority and
  backpressure behavior without contaminating TASK-00389's safe Workflow notification transport.
- Whole-range text/exact-severity search, same-time traversal, bounded context/raw continuation, safe rendering and
  URLs, pause overflow/reconnect coverage, and honest distinction between display gaps and collection/archive loss.
- Actual requester-owned temporary restore readiness/expiry/revocation, bounded extraction and independent current
  authority during output; existing physical restore/cleanup limits remain mandatory.
- Measured aggregate browser/process/backend/worker resources under the finite profile, plus real failure/run entry,
  keyboard/focus, narrow-layout, light/dark and reduced-motion evidence. Serialized byte limits are not heap receipts.

These are inherited planning obligations, not fresh application evidence. WF-042 must bind them to concrete delivery
owners and verification paths; this handoff does not resolve release scope, create implementation records or invoke
an experiment. The terminal-first milestone and Board priority remain unchanged.

## First-release proposal — subsequently approved

**Recommend one complete operational-diagnosis release**, not a backend-only installation or a live-only MVP.
A currently authorized Super Admin can start from a failed Agent OS operation or managed run, find its records,
inspect useful emitted exceptions and surrounding activity, follow available relationships, and search older logs
through verified bounded temporary restore. They can also distinguish genuinely empty results from delayed,
missing, unavailable, expired or purged coverage. The existing terminal-first milestone/Board priority is unchanged.

The approved WF-036–041 contracts remain authoritative; this proposal adds delivery scope/acceptance, not another
copy of their detailed source, queue, archive, authorization or browser specifications.

### Included and deliberately not included

Include all accepted Agent OS command/query/event activity, all Monolog channels/levels including DEBUG,
server-request diagnostics, Agent OS supporting-service logs where deployed and managed-run/check output.
Enrollment follows the actual installation inventory; a missing required producer/runtime is a named dependency,
not a fabricated record or permanent source omission. Preserve emitted content without pipeline redaction while
producers avoid deliberately emitting secrets. Keep existing public HTTP behavior and evidence/privacy owners.

Include native Super Admin-only Logs, private conditional Loki integration, bounded persistent collection/replay,
independent continuously verified local archives, 14-day live search and independently configurable 90-day ordinary
archives, original clocks, hold-aware cleanup, temporary older-log search and the bounded export/download operations
already specified by WF-039/041. Current access, visible uncertainty and finite resource enforcement are part of the
release, not optional hardening afterward. Neither Grafana installation nor backend administration is a user journey.

Propose explicitly **deferring** these adjacent products, with no automatic TASKs:

- General metrics collection/dashboards and performance analytics. The minimal counters needed for backlog,
  capacity, latency qualification, lost/unknown records and source/destination health remain required.
- Distributed tracing and span instrumentation. Existing command/correlation/run relationships and exception
  stack traces remain required; they are not a promise of distributed traces.
- External notifications, paging, alert routing/rules and an alerting platform. Independent local degradation
  status, operator-visible warnings and an explicit recovery path remain required even when log search fails.
- Automatic error grouping, fingerprints, issue management and AI diagnosis. Exact text/severity/context search
  remains required; repeated messages are not ordinarily deduplicated away.

Keep already accepted deferrals: off-machine operational archives and non-admin log delegation. Keep explicit
**exclusions**, not future promises: registered-project runtime/service-log ingestion, browser JavaScript errors,
agent transcripts/prompts/hidden reasoning. No new telemetry SaaS, public backend or alternate installation topology.

### Complete release journeys

All log capabilities below are protected definitions under WF-041's initial managed Super Admin audience. Related
entity/evidence controls retain their own current permissions. Read queries emit no authoritative Workflow event.
Producer activity observations, archive/restore lifecycle facts and mandatory security audit remain distinguishable.

| Journey / entry | Commands, queries and effects | Observable acceptance |
|---|---|---|
| Failed HTTP or worker operation → correlation | `READ_LOGS` search/context/detail plus existing producer activity reporting; browse has no business mutation | Expected and unexpected failures show the applicable emitted exception/lifecycle records and neighboring service activity; public responses unchanged |
| Failed managed run → Summary/known run reference | `READ_LOGS` on Workflow/TASK/run/process relationships; related evidence follows its owner | Actual runner/check output and available attempt identities, never transcript reconstruction, invented lineage or another execution-state authority |
| Investigate active activity → Logs | `READ_LOGS` search/filter/tail/pause/resume/context/detail; transport is read-only | Whole-range literal search, all original severities, bounded rendering, tie-safe traversal, explicit reconnect/paused gaps and inert raw detail |
| Investigate missing or delayed records → coverage status | Protected bounded health/coverage reads; independent local operator diagnostics during application/search outage | Separate runtime capture, durable queue, backend acceptance/query visibility and archive verification; failure is never empty-success or a manufactured complete count |
| Diagnose an older incident → private archive coverage | `READ_LOGS` + `RESTORE_LOG_ARCHIVES`; explicit idempotent restore command then authorized status/search/detail | Verified content in an isolated requester-owned temporary scope; actual partial/corrupt/busy/expired states, no re-aging/live-tail mixing or business replay |
| Extract a bounded result or archive | `READ_LOGS` + `EXPORT_LOGS` or `DOWNLOAD_LOG_ARCHIVES`; restored exports also require restore scope authority | Intent-specific bounded output with current checks, no arbitrary object paths, false completion, new unlimited spool or guaranteed recall of downloaded bytes |
| Recover or reclaim capacity → existing operator/lifecycle owner | Existing qualified restart/reconciliation/hold/purge/cleanup operations, never powers derived from log read | Original delivery identity and clocks, protected-copy fencing, explicit unknown/reclaimed states; no silent sole-copy deletion or early verified-archive eviction |

### Proposed measurable acceptance profile

These are **engineering qualification objectives**, not measured capacity, runtime authority or a new retention
policy. Preserve WF-038/039/041's finite profiles. If resource fit fails, return measured evidence to the relevant
engineering owner and revise the finite profile before enrollment; never silently reduce coverage, weaken holds,
truncate records or suppress DEBUG to hit a target. A guarantee change needs human approval.

Use a declared reference installation with exact OS/architecture/runtime/service/image/driver/storage identities,
actual enrolled source inventory and separate budgets for runtime logs, collection, live indexes/WAL/compaction,
archives, temporary restore and browser/application transport. Physical hard limits/shared fault boundaries must
be qualified, not inferred from logical queue caps or the earlier monitored scratch.

Initial integrated workload:

- One hour at **10 records/second aggregate**, approximately **2 KiB mean original record size**, plus a separate
  **60-second 1,000-record/second burst**. This is a minimum reference profile, not a cap allowing source suppression
  or evidence that larger installations work. Include the real selected source families and short-lived producers.
- Exercise **4 live streams, 2 bounded read operations and 1 active restore** at their accepted aggregate limits;
  restore-scope reads share the read limit rather than silently adding extra capacity. Include paused and slow clients.
- Use a recorded corpus manifest with expected observation identities/digests and non-repetitive varied content:
  all eight actual Monolog level codes/names, native exception/cause shapes, legitimate identical messages,
  same-time ties, nested/non-HTTP activity, no-subscriber events, late/out-of-order records, malformed/non-UTF-8 and
  large/incomplete continuation cases. Use owning ID factories/libraries; never real secrets or raw transcripts.
- Populate representative retained history for query/capacity qualification, including boundary cases around
  day 14/day 90, permitted late arrival, holds/grace and older restored data. Controlled fixture clocks exercise
  eligibility semantics; they do not authorize production timestamp rewriting or prove long-duration durability.
  Qualify temporary restoration up to the accepted 1-GiB decompressed selection separately from tiny samples.

| Measurement | Initial acceptance objective |
|---|---|
| Healthy controlled coverage | Every expected captured identity/byte sequence is accounted for across searchable content and independently verified archives; no unexplained omission or ordinary filtering; multiplicity/replay uncertainty disclosed |
| Live visibility at baseline | p95 <=5 seconds and p99 <=15 seconds from trusted collection receipt to display on a healthy actively following browser; also report producer-to-collection lag separately |
| Search/context/detail at baseline | p95 <=3 seconds to a bounded first result over representative live history, >=100 varied requests per query class; report cold/warm results and actual scanned volume, not only a repeated hot query; existing 30-second hard deadline remains |
| Continuous verified archives at baseline | p95 <=60 seconds from trusted collection receipt to readback-verified catalog publication; upload/HEAD/ETag alone never counts |
| Burst recovery | All offered records have an explicit disposition; under the qualified non-exhausted profile, no loss and both destination backlogs settle within 5 minutes after burst ends |
| Independent failure status | WF-038's 5-second observations/three-miss unknown rule and visible progress/pressure states work without successful log search; no claim of surviving arbitrary shared host/storage failure |
| Temporary restore | Actual verified ready/partial/failure outcome within WF-039's 15-minute execution budget; nominal supported valid selections succeed, not merely time out; readiness is confirmed by content/coverage queries |
| Authorization and lifecycle | Zero unauthorized content releases in the scoped request/stream/export/restore/revocation probes; current checks at output boundaries and immediate logical scope expiry; already-released bytes are not recallable |
| Physical limits and pressure | Enforced bounded allocations, independent destination behavior, oldest-eligible eviction and persisted loss dispositions; no held sole-copy eviction, hidden spool or unbounded fallback |
| Browser interaction | Actual keyboard/focus, pause/resume, filter/context/detail, denied/partial/unavailable/expiry, narrow-layout and light/dark journeys pass on the owned UI; library/prototype screenshots do not qualify this |

Report percentile sample counts, timestamp/clock basis and uncertainty. Baseline objectives apply while required
services are healthy and within the qualified profile; faults/exhaustion must meet their explicit degraded behavior,
not those normal latency targets. A successful timeout or dropped-record counter cannot make a normal-load coverage
failure pass. A one-hour soak is not a long-term reliability claim.

At 10 records/second averaging 2 KiB, original bytes alone total roughly **23 GiB over 14 days / 148 GiB over 90 days**,
before indexes, metadata, versions, holds or replication. Thus the existing 50-GiB archive admission target is **not**
a proved 90-day capacity guarantee: measured compression/overhead and retention projection must fit, or engineering
must revise the finite allocation or supported installation profile explicitly. Highly repetitive probe compression
ratios are not adequate sizing evidence. Hold growth can exceed ordinary retention; capacity conflict follows the
accepted preservation/admission policy, not an invented promise of unlimited protected retention.

### Required failure and recovery evidence

Qualify with direct owning tools and owned use-case tests as appropriate, under separately bounded execution
authority. These are faults in the actual delivery/integration boundary, not tests that quality tools detect seeded
failures or product-suite tests of configuration files/wrappers.

- Diagnostic handler/formatter/sink failure, expected-failure reporting, nested command/query/event lifecycle and
  worker-context reset: unchanged public responses and no duplicate boundary report, recursion or unintended failure
  of otherwise independent operations. Preserve mandatory audit/evidence owner failure behavior.
- Collector restart at source/checkpoint/persistence/delivery uncertainty boundaries; short-lived source retirement,
  rotation, malformed/large data, partial/rejected batches, HTTP 260 and WAL/storage faults. Source gaps must remain
  attributable; a persistent sink queue alone does not prove recoverable source capture.
- Search unavailable while archives continue, archives unavailable while search continues, both unavailable,
  exhausted queues and protected-only capacity. Loss counts/granularity and any unknown count must be honest;
  intentionally evicted backlog must not replay endlessly back into the queue.
- Interrupted archive intent/upload/readback/catalog and local data/catalog/authority recovery; corrupt/missing
  segments and decompression limits. Losing authority disables unsafe publication/cleanup; host loss remains an
  accepted local-only failure boundary, not a recovery demonstration claim.
- Concurrent hold/release/purge/late-ingest/policy/restore/expiry across asynchronous deletion; exact destructive
  scope, verified alternatives for protected copies and separate requested/processed/physically reclaimed states.
- Current protected catalog/capability checks across every entry path, paused/ongoing streams, old cursors, downloads,
  cross-requester restore IDs and late browser responses. Unknown authority must withhold output without leaking
  hidden target existence. Ordinary project membership/custom roles/direct Agents never gain initial log access.
- Browser lease rollover, disconnect/reconnect, pause overflow, slow readers, tied timestamps and late arrivals;
  deadlines/concurrency/real heap/process/storage accounting; no false caught-up, complete-result or archive-ready state.

### Ownership, delivery order and unresolved blockers

Recommend **one new EPIC destination: “Diagnose Agent OS operations and managed runs with live and archived logs.”**
No existing EPIC found in the inspected active inventory owns this complete operational-log outcome. It extends
existing capabilities rather than reparenting their TICKETs or turning this feature into a terminal-first prerequisite.
The destination is proposed, not an allocated EPIC identity or an instruction to write it now.

| Delivery responsibility | Existing owner to consume / boundary to preserve |
|---|---|
| Producer diagnostics/activity and public HTTP compatibility | WF-037; current Common/Monolog and API contracts, including TASK-00020; do not adopt incompatible safe middleware merely for logging |
| Source privileges, native queues/replay/framing and deployment | WF-038, WF-023 and TICKET-00023's installation owner; no second stack, broad Docker grant or implicit custom collector/queue/proxy |
| Private Loki and storage integration | WF-040, existing installation owner and TICKET-00094's private S3 capability; isolate chunk/index/archive/Artifact authority and accounting |
| Verified archive/catalog and temporary restore | WF-039; observability-owned lifecycle consuming existing private storage, not another Artifact authority |
| Holds, purge, eligibility and disclosure fences | WF-017 and TICKET-00094's preservation/privacy owner; observability participates across its copies, does not weaken evidence rules |
| Protected log capability definitions/enrollment | TICKET-00015/00031 managed authority; deliberate additive catalog change, exact current checks, no non-admin delegation |
| Native Logs and failure/run entry links | WF-041 and existing shell/authentication/theme; TICKET-00097 owns sessions, TICKET-00096 owns sanitized history and TASK-00389 safe Workflow notifications |
| Integrated release evidence and operator recovery | Proposed observability EPIC consuming all above; owning engineering tools and full application gate, not a proof-only infrastructure demonstration |

Within the eventual approved EPIC, order work by the product dependencies: settle supplier/design blockers and exact
runtime authority; deliver compatible producer/collection/destination behavior; establish archive/hold/restore
correctness and private access operations; integrate native failure/run/older-log journeys; then qualify the composed
installation and recovery. Independently reviewable incremental delivery is allowed, but incomplete source coverage,
live-only browsing or unverified archives must be labelled partial rather than the completed observability release.
Do not stall independently useful existing producer/evidence/terminal work waiting for the complete Logs UI.

The mandatory-gate tables above remain unresolved: collector/reader and hold-aware native queue capability, physical
enforcement, supported S3/SeaweedFS integration, exact receipt/fencing and actual permissioned browser behavior are
not claimed delivered. During EPIC/TICKET planning assign each to a concrete product outcome/blocker and verification
path before its dependent implementation is presented as executable. Do not invent ready TASK IDs, waive a missing
capability, or silently authorize a bespoke substitute; conflicts return to WF-038/039/040 or the existing authority
owner. An implementation experiment needs its own target, budget, cleanup and human authority, not reuse of the
completed backend comparison grant.

No amendment to TICKET-00094/00096/00097's core authority, sanitization or terminal priority is proposed. The new EPIC
must name any precise additive integration requirements needed from those owners; if an actual contradiction is
found, request an explicit amendment instead of silently rewriting accepted scope. Exact diagnostics stay in Logs;
existing sanitized Activity/history remains sanitized. Controlled owner-authorized exposure-response purge is not
pipeline redaction and remains required.

### Acceptance, publication and planning handoff

Completion requires real owned behavior/integration evidence for the included journeys, qualified finite deployment
and recovery, and the canonical `./bin/build` on the delivering implementation. Run infrastructure checks directly
with owning tools; never add product tests of migrations, generated files, architecture/configuration/wrappers or
quality mechanisms. Independent review/QA where applicable, publication, merge and operational enrollment remain
separate receipts. Current planning checks and earlier experiments must not be presented as fresh release evidence.

If John accepts this scope/deferral/one-EPIC proposal, record the approval, close WF-042 and the decision map, and link
the approved but unwritten EPIC destination. Then recommend a **separate grill invocation** for that destination;
create no EPIC in this decision. Complete approved EPIC handoffs before TICKET decomposition, then complete the
TICKET phase before TASK decomposition. A Closed map with an unwritten approved EPIC retains that handoff, not an
invented new decision frontier or automatic implementation priority.

**Pending choice:** approve the complete first-release boundary, the explicit adjacent-product deferrals and the
single EPIC destination above, or identify a specific change. Engineering workload/objectives are proposed defaults,
not claims of human approval of every setting or completed verification. WF-042 and the map remain Open/Active.

Historical next: John accepts or revises WF-042's release/deferral/EPIC-handoff proposal.

## Must decide

- Synthesize the accepted sources, diagnostic policy/Common compatibility, collection, backend, UI and search/archive
  lifecycle into one coherent first release. Trace any exclusions or staged capability explicitly; do not quietly
  drop requested browser, preservation, exception or authorization behavior to call a thin slice complete.
- State each source journey, actor/permission, entry point, expected commands/queries/effects, available identities,
  safe detail and known gaps. Separate planning acceptance from runtime qualification and operational enrollment.
- Set measurable acceptance objectives: useful preserved message/frame/cause/release detail, filter/context fidelity,
  tail/search behavior, current authorization and revocation, delivery lag/recovery/loss visibility, finite resource
  limits, independent 14-day search retention and archive duration, verified continuous preservation and older-log
  retrieval. Define workload, thresholds and what unknown or unavailable looks like rather than inventing receipts.
- Require owned behavior and integration evidence for safe public responses, expected-failure reporting/severity,
  duplicate boundary-report prevention without dropping distinct observations, producer-side secret avoidance and
  no pipeline redaction under WF-037, diagnostic sink failure without recursion, collection
  restart/duplicates/gaps, archive verification/recovery and denied stream/search/detail/export/archive access.
- Qualify infrastructure directly with owning tools, storage restore/retrieval evidence and realistic resource
  measurements. Do not add product-suite tests of configuration/wrappers/tooling or tests proving tools detect
  deliberately seeded failures. Future owned application behavior still needs normal tests and the full gate.
- Explicitly include with a concrete journey or defer metrics, distributed tracing, alerting and error grouping.
  Keep health/delivery failure visibility required by this feature distinct from assuming a whole alerting platform.
- Identify existing owners to consume or explicitly amend, approved EPIC destination(s) and implementation ordering.
  The existing terminal-first milestone and Board priority remain unchanged unless John separately approves a change.

## Resolution boundary

Resolve delivery scope, acceptance and EPIC handoff only; create no EPIC, TICKET or TASK in this interview. Finish
all map decisions and then grill each approved EPIC destination separately before TICKET/TASK decomposition under
map-wide planning order. Preserve Workflow/audit/transcript boundaries, historical decisions and outstanding
supplier/runtime qualifications. Approval here does not authorize installing, implementing or deploying anything.

## Accepted resolution and EPIC handoff

John answered **“approved”** to the complete first-release boundary, explicit adjacent-product deferrals and single
EPIC destination. Close WF-042 and the [operational observability map](../operational-observability-map.md) as
**decision-complete planning**, not implemented observability, release acceptance or production enrollment.

The release retains all WF-036–041 sources, native Super Admin-only diagnosis journeys, private conditional Loki,
independent verified local archives and bounded older-log browser restoration. Backend-only/live-only increments
may be useful intermediate work but cannot represent completion of this release. Existing public HTTP, sanitized
Workflow history/Activity, evidence/privacy/preservation, managed authority and terminal-first priority remain intact.

Defer general metrics dashboards/analytics, distributed tracing, external alerting and automatic error grouping;
independent health, capacity, gaps and loss visibility remain required. Off-machine archives and non-admin log
operators retain their earlier deferrals. Registered-project runtime logs, browser JavaScript errors and agent
transcripts/prompts/hidden reasoning remain excluded, not implicit later features. No speculative follow-up TASKs
are authorized by any deferral.

The workload, measurable objectives and failure matrix above supply the initial engineering acceptance contract,
not measured fitness or approval of every library knob. Carry all named collector/reader, native queue, source
privilege, physical-limit, S3/SeaweedFS, receipt, preservation-fence, authority, browser and resource blockers into
concrete owned delivery paths. Missing guarantees block dependent execution/enrollment; contradictions return to
the owning contract. The earlier synthetic comparison is evidence only within its documented bounds, not a fresh
application receipt or reusable runtime authority. Capacity and compression remain to be measured.

**Approved EPIC destination:** “Diagnose Agent OS operations and managed runs with live and archived logs.”

- At decision closure: **approved destination, unwritten EPIC**. No EPIC identity was allocated and no TICKET/TASK
  was created. The separately invoked grill's later handoff is recorded below.
- Source requirements/owners: this decision's release journeys, acceptance profile and ownership table, plus the
  linked Closed WF-036–041 contracts and existing supplier owners. Reuse settled decisions; do not re-interview them.
- Receiving operation: a separately invoked grill writes one EPIC after its required confirmation. It must retain
  concrete integration blockers and existing authority rather than invent implementation readiness or a second stack.
- Subsequent phase: once that EPIC is written/approved and this map has no other approved EPIC destinations left,
  recommend TICKET decomposition; complete that phase before TASK decomposition. The Board still owns execution order.

The Closed map retains this unfinished EPIC handoff and does not reopen merely because the EPIC is unwritten.
No implementation, permission assignment, service/credential/daemon change, new experiment, commit, publication or
operational deployment follows from this approval. Full build, independent review and delivery remain separate.

Historical next: separately invoke grill for this map's approved destination in the planning worktree.

## EPIC handoff completed

John invoked grill for the exact approved destination and confirmed its summary with **“agreed”**.
[EPIC-00019](../../epics/00019-EPIC.md) is now written/approved, consuming WF-036–042 and the existing owner boundaries.
This is the map's only approved EPIC; its EPIC phase is complete. At the grill checkpoint no TICKET/TASK decomposition
had started. The subsequent accepted requirement split is recorded below; no implementation or runtime qualification
follows from either planning checkpoint.

At John's request the planning branch was rebased onto fetched `origin/develop` at `ac41c28` before allocation, with
all previous observability files preserved. Live/archive inventories in branch refs and registered worktrees were
checked. The upstream local terminal log-pane plan remains independent, explicitly reconciled in EPIC-00019;
no existing TICKET/TASK record was renumbered or made dependent on this feature.

## Accepted requirement handoff

John separately invoked `to-tickets` and approved EPIC-00019's seven-outcome proposal. The
[accepted split](../../epics/00019-EPIC.md#approved-ticket-decomposition-and-current-handoff) is written as
TICKET-00111–00117, covering diagnostics, collection, native live diagnosis, verified preservation/lifecycle,
temporary archive search, extraction and installation operation/recovery. Each gate retains its concrete requirement
owner and bounded verification path. This completes the map's TICKET phase, not its implementation or qualification.

The requirement operation created no TASKs and reassigned no owner/priority. The independent local terminal pane
remains independent; the four log permissions are a future additive catalog requirement, not a current grant.
Fetched `develop` remained at `ac41c28`, and current live/archive/concurrent IDs were rechecked before allocation.

## Accepted producer TASK handoff

John subsequently invoked `to-tasks TICKET-00111` and approved its four-outcome proposal. The
[accepted split](../../tickets/00111-TICKET.md#approved-task-decomposition-and-current-handoff) is now written as
TASK-00451–00454, all unranked. It covers native diagnostics/failure isolation, compatible HTTP diagnostics, all
command/query/event observations and actual console/worker/run attribution/reset. TASK-00451's early collection/
status supplier and TASK-00454's managed-runtime context bindings remain explicit information gates. Planning
acceptance supplies no missing runtime mechanism or permission to start services.

At the producer checkpoint TICKET-00111's decomposition was complete; TICKET-00112–00117 remained to decompose.
Live/archive/concurrent TASK inventories across 18 refs/four worktrees ended at TASK-00450 before those allocations;
no existing TASK or priority changed in that operation.

## Accepted collection TASK handoff

John subsequently invoked `to-tasks TICKET-00112` and approved its six-outcome proposal. The
[accepted split](../../tickets/00112-TICKET.md#approved-task-decomposition-and-current-handoff) is now written as
TASK-00455–00460: native design/privilege blockers, independent status, bounded hold-aware queues, enrolled capture/
recovery, truthful private-search delivery and independent verified archive delivery. All six remain `needs-info`
for concrete early inputs/mechanisms. TASK-00451 now explicitly depends on TASK-00455/00456 without clearing its
qualification gate. No custom collector, native capability, privileged access or runtime grant is inferred.

At the collection checkpoint, TICKET-00111/00112 decompositions were complete and TICKET-00113–00117 remained.
Fetched `develop`/HEAD remained `ac41c28`; 18-ref/four-worktree inventories ended at TASK-00454 before allocation.
Those ten TASKs were unranked; only TASK-00451's supplier metadata changed. Terminal-first work stayed independent.

## Accepted native Logs TASK handoff

John subsequently invoked `to-tasks TICKET-00113` and approved its six-outcome proposal. The
[accepted split](../../tickets/00113-TICKET.md#approved-task-decomposition-and-current-handoff) is written as
TASK-00461–00466: private ingestion/receipts, protected catalog reconciliation, real native search, exact context/
detail, bounded live follow/pause/recovery and actual failure/run diagnosis navigation. TASK-00459 now names
TASK-00461 as its early supplier, retaining its qualification gate. No browser prerequisite is added to ingestion.
Current-authority/query/shared-admission behavior feeds later restore/extraction without reassigning their scope.

At the native-Logs checkpoint, TICKET-00111–00113 decompositions were complete; TICKET-00114–00117 remained.
Fetched `develop`/HEAD remained `ac41c28`; 18-ref/four-worktree inventories ended at TASK-00460 before allocation.
Those sixteen TASKs were unranked; only TASK-00459's supplier metadata changed then. Runtime/lifecycle/transport gaps
remained named; no image/service/grant/implementation/experiment followed from that planning checkpoint.

## Accepted archive lifecycle TASK handoff

John subsequently invoked `to-tasks TICKET-00114` and approved its seven-outcome proposal. The
[accepted split](../../tickets/00114-TICKET.md#approved-task-decomposition-and-current-handoff) is written as
TASK-00467–00473: early preservation/disclosure participation, immutable verified archives, retention preview/apply,
private archive catalog, safe ordinary expiry, authorized cross-copy exposure purge and authority-aware local recovery.
TASK-00455/00457/00461/00463 now name TASK-00467; TASK-00460 names TASK-00468. All keep their qualification gates,
without a reverse dependency on completed collection, cleanup or browser consumers. Existing preservation/privacy
owners remain authoritative; six new slices retain information gates and catalog reads await named prerequisites.

At the archive-lifecycle checkpoint, TICKET-00111–00114 decompositions were complete; TICKET-00115–00117 remained.
Fetched `origin/develop` advanced to `58b087b`; the selected branch stayed `ac41c28` without rebase. The 17-ref/four-
worktree inventory ended at TASK-00466 before allocation. Those twenty-three TASKs were unranked; only the five
receivers gained earlier supplier metadata then. No runtime/grant/implementation authority followed.

## Accepted temporary restore TASK handoff

John subsequently invoked `to-tasks TICKET-00115` and approved its four-outcome proposal. The
[accepted split](../../tickets/00115-TICKET.md#approved-task-decomposition-and-current-handoff) is written as
TASK-00474–00477: requester-owned admission/status and early lifecycle fences, verified isolated import/query
readiness, the complete native older-incident journey and bounded retirement/reclamation. TASK-00471/00472 now name
TASK-00474/00475/00477 without clearing qualification gates or adding reverse finished-consumer dependencies.
Logical expiry/denial ships early; delayed physical cleanup cannot prolong access. Actual launcher/resource/native
isolation/readiness/cleanup evidence remains outstanding; the synthetic historical import is not qualification.

At the temporary-restore checkpoint TICKET-00111–00115 decompositions were complete; TICKET-00116–00117 remained.
Fetched `origin/develop` was `58b087b`; the selected branch stayed `ac41c28` without rebase. The 17-ref/four-worktree
inventory ended at TASK-00473 before allocating four new TASKs. Those twenty-seven were unranked; only TASK-00471/00472
gained earlier supplier metadata then. No runtime, permission, implementation, build or publication was claimed.

## Accepted extraction TASK handoff

John subsequently invoked `to-tasks TICKET-00116` and approved its three-outcome proposal, including shared download
admission and bounded output. The [accepted split](../../tickets/00116-TICKET.md#approved-task-decomposition-and-current-handoff)
is written as TASK-00478–00480: actual native live export, verified archive download and requester-owned restored
export. TASK-00472 now names TASK-00478/00479's early extraction/output participants without clearing qualification
gates or introducing reverse finished-purge prerequisites. Restored export joins those early fences and owns its races.

Downloads share the existing two-installation/one-requester read/export admission; transfer batches <=64 KiB and
queued output <=1 MiB. Existing selection/16-MiB/30-second limits remain. Actual fresh output authority, integrity/
completion, browser/proxy/worker/provider resources and cancellation are qualification gates, not inferred from caps.
Early exact installation suppliers are still needed; final composed acceptance must not reverse-block them.

At the extraction checkpoint TICKET-00111–00116 decompositions were complete; only TICKET-00117 remained. Fetched
`origin/develop` was `58b087b`; the selected branch stayed `ac41c28` without rebase. The 17-ref/four-worktree inventory
ended at TASK-00477 before allocating TASK-00478–00480. Those thirty TASKs were unranked; only TASK-00472 gained
supplier metadata then. TASK-00463 recorded shared download admission; no implementation/runtime/build was claimed.

## Accepted installation TASK handoff and completed map planning

John invoked `to-tasks TICKET-00117` and approved its seven-outcome proposal. The
[accepted split and exact early supplier mapping](../../tickets/00117-TICKET.md#approved-task-decomposition-and-current-handoff)
is written as TASK-00481–00487: narrow authority/preflight, actual isolated physical storage, capture/lifecycle
supervision, bounded temporary runtime launch/removal, authenticated streaming/extraction runtime, the existing
operator guide/recovery and complete supported live-and-archived operator qualification.

TASK-00481–00485 now supply sixteen receiving TASKs with true metadata edges; every receiver retains `needs-info`.
The early collection design consumes A/B and supplies C, avoiding a runtime/design cycle. D consumes the backend,
not completed restore consumers. E consumes TASK-00072's actual ingress; A–D do not inherit its complete browser chain.
F/G are later consumers, not early suppliers. Existing preservation/privacy/execution authority and terminal-first
work remain unchanged; native mechanisms and missing owner participants cannot be inferred from allocated IDs.

Each slice owns relevant tests/security/direct qualification/accessibility/full build. TASK-00487 retains this
WF-042's complete workload, retained-history, fault, physical-fit and real operator journey acceptance, transitively
covering all earlier observability TASKs. No test-everything-later or extra parent-only acceptance gate is introduced.
The full 1-GiB restore, current output authority, actual 90-day capacity fit and unsupported backup/local-loss limits
remain mandatory; historical experiments are not fresh acceptance or ongoing grants.

**Current phase:** all decisions Closed, sole EPIC approved/written, seven TICKETs approved/written and all seven TASK
decompositions accepted as TASK-00451–00487. **Map-wide planning is complete**, not implementation or release acceptance.
Fetched `origin/develop` remains `58b087b`; this branch stays `ac41c28` without rebase. The 17-ref/four-worktree inventory
ended at TASK-00480 before allocating seven new TASKs. All thirty-seven are unranked; only the sixteen named receiver
TASKs gained early supplier metadata here. TICKET-00117 records fresh planning verification; no runtime/build/publication
or operational enrollment is claimed.

Next: `/skill:next` for read-only Board-based work selection from the published planning branch, or from `develop`
after merge, following [the EPIC's current decomposition handoff](../../epics/00019-EPIC.md#approved-installation-task-decomposition-and-completed-map-planning). This
recommendation invokes no execution and waives no missing owner, qualification, review or publication requirement.
