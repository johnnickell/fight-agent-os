# Loki-first integration reconciliation

**Acceptance update:** John approved the conditional Loki planning choice and explicit qualification-timing
amendment. [WF-040's accepted resolution](../tickets/WF-040-select-the-log-search-backend.md#accepted-resolution--conditional-planning-selection)
is authoritative; amendments are appended to WF-038/039 and mandatory gates are carried into WF-042. The proposal
history below does not imply approval is still pending or that production qualification has passed.

## Original question and recommendation

For [WF-040](../tickets/WF-040-select-the-log-search-backend.md), can Loki fit the accepted source, retention,
archive and preservation contracts without substituting backend defaults for Agent OS policy?

**Recommend Loki OSS as a conditional planning choice, with application-owned retention/deletion and explicit
unresolved integration gates.** This is a proposal for John's acceptance, not a qualified deployment or a selected
collector. The [disposable comparison](log-backend-disposable-comparison-results.md) supports core search, raw
fidelity, tail and old-import primitives at small scale. It does not support claiming the whole pipeline works.

There is a planning-gate conflict to resolve explicitly: WF-038/039 currently assign composed runtime qualification
to WF-040 before backend selection. That includes application-owned behavior not yet implemented. The proposed
amendment is to select the **backend design direction** now and carry the named unresolved checks as mandatory
integration/delivery blockers into WF-042 and the eventual owned implementation. This changes when the planning
choice is made, **not** the required evidence or accepted preservation/access/loss guarantees. Without John's
approval, WF-040 remains Open under its existing gate. Do not silently declare that gate satisfied by this note.

## Evidence scope

Read-only reconciliation on 2026-10-15, using Loki v3.7.8 source
`09e6ce2ff1bdc19763a10265b870c86f51c98655`, pinned upstream documents already collected and three additional pinned
source files. No new service, image pull, archive write, configuration change or runtime probe occurred in this
reconciliation. Earlier experiment receipts remain evidence only for their documented cases.

### Receipt caveats found in pinned source

1. `pkg/distributor/distributor.go` validates entries individually, retains valid entries and can return a validation
   error after forwarding surviving streams. A failing batch is **not necessarily wholly unaccepted**. Do not mark
   every record rejected or replay it with new identities. [S1]
2. `pkg/ingester/instance.go::Push` explicitly describes its returned error as reflecting the last processed stream;
   the loop overwrites `appendErr`. Earlier stream outcomes are not a per-record response manifest. That source
   path warrants a mixed-stream countercheck; it was not reproduced by the small homogeneous benchmark. [S2]
3. The same ingester source handles WAL `ENOSPC` by recording a failure/triggering flush-on-shutdown without
   necessarily returning the WAL error. The WAL document explains the periodic disk-pressure check and its race
   window. The 50/50 process-crash result therefore does **not** mean all successful pushes survive every fault. [S2][S3]
4. Blocked ingestion defaults to **HTTP 260**, a custom status in the 2xx range. Configuration can even request
   HTTP 200 while blocking. A generic “all 2xx means delivered” output is unsafe. Qualify the sink's exact accepted
   status/body behavior and use an explicit error response for blocking; ordinary Loki push success in the probe
   was 204. Authentication failure, blocked ingestion, partial acceptance and uncertainty need distinct handling.
   Disallow a success-on-blocking configuration. [S1][S4]
5. The distributor can normalize structured-metadata names and invalid UTF-8 values. Raw producer bytes belong in
   the retained raw envelope, reversibly encoded as needed, not exclusively in metadata that the backend may
   normalize. Keep trusted search metadata small/valid and do not let it overwrite original content/provenance. [S1]

These findings narrow—not erase—the preference for Loki: its explicit size/age rejection in the exercised cases
and WAL process-crash recovery are useful, but neither candidate has a universal transactional ingestion receipt.
The stock collector's error/retry behavior must be checked against these paths, not inferred from an HTTP logo.

## Proposed composition and ownership

```text
Selected Agent OS/managed sources
  → enrolled installation reader
  → bounded persistent collection/disposition state
      ├─ independent search delivery → private Loki monolith + local WAL + TSDB/object storage
      └─ independent archival → verified gzip/JSONL segments + private catalog/owner references

Authorized Agent OS browser requests
  → application log-access/query boundary → live Loki queries
  → bounded restore job → verified archive read → isolated temporary Loki scope → query readiness

Existing preservation/privacy owners
  → eligibility/fencing → application-owned cleanup → private backend/object deletion adapters
```

| Boundary | Proposed responsibility | What is not granted/proven |
|---|---|---|
| Source reader | Existing installation enrollment, source generation/cursor and retirement/replay rules | No general Docker socket for PHP/browser/Agents/ordinary delivery; no newly accepted privileged reader |
| Collector | Native qualified durable buffering/framing and independent destination/loss dispositions under WF-038 | No stock product yet proves hold-aware oldest-eligible eviction, checkpoint ordering and physical limits together |
| Loki live search | Monolithic OSS, TSDB, persistent WAL, low-cardinality source/service stream labels; observation/run/correlation fields outside indexed labels | Not the archive, hold authority, application permission engine or per-record durable receipt |
| Object storage | Prefer the already planned private SeaweedFS capability if its exact S3/runtime/security/resource contract qualifies | No second object product; no claim that filesystem-probe success qualifies SeaweedFS; no automatic selection of its deployment |
| Archive coordinator/catalog | Existing application orchestration owns intent, immutable segment identity, verification and links to preservation authority | No parallel Workflow/Artifact/hold authority, hidden extra spool or Artifact for every ordinary log |
| Browser/query boundary | Application rechecks current log permission, supplies private tenant/scope, bounds queries and denies expired/revoked scopes | Links/project membership/tenant headers do not grant access; no arbitrary backend/admin proxy |
| Restore execution | One bounded active job; isolated scope/directory, verified original content/clocks, explicit query-ready receipt | Import HTTP success/flush response alone is not ready; no live-tail mixing or business-event replay |

Live Loki chunks/indexes and ordinary gzip/JSONL archives require **separate namespaces, credentials, accounting
and lifecycle ownership** even if both use SeaweedFS. Loki must not get deletion rights over archives or Artifacts;
archive writers must not alter live indexes. Separate buckets are not hard physical isolation or independence from
a shared SeaweedFS/host outage. WAL storage must be accounted separately from chunk/index/compactor and archive
space; its disk-pressure threshold measures its filesystem, not merely WAL directory bytes. [S3]

The earlier filesystem backend remains disposable evidence, not an endorsed production topology: Grafana's pinned
document explicitly says it does not support that store for production. Use a qualified S3 deployment for the
proposed live topology rather than silently carrying the probe directory into production. S3 compatibility,
permissions, lifecycle, restart and resource evidence remain missing. [S5]

## Retention without silently defeating holds

**Do not configure autonomous 14-day Loki retention for this proposed composition.** The accepted fourteen days
remain the ordinary live-search policy, calculated from original trusted collection time, not an instruction that
Loki may delete sole protected copies at that instant.

Pinned deletion documentation supports enabling compactor deletion while setting `retention_period: 0s` to disable
automatic age-based retention. Configure no overriding stream/tenant age policy or storage lifecycle that can bypass
application eligibility. This internal zero is a mechanism choice, **not** an unlimited user retention setting or
permission to accumulate data without bounds. Ordinary policy durations remain positive/configurable as WF-039
requires. The change from the experiment's automatic TTL is intentional and must receive its own direct qualification.
Native filter-only deletion is not physical removal. [S6][S7]

Proposed cleanup behavior:

- The ordinary live query route enforces the accepted collection-time window. Hiding expired entries is not byte
  purge. A protected survivor awaiting a verified alternate copy remains preserved and explicitly accounted for;
  it is not silently offered as a new ordinary retention duration or declared archived.
- Scheduled application cleanup uses the existing owner's eligibility/hold/purge state and verified-copy references.
  Ordinary unheld entries may become eligible at live expiry even if archival failed, with a visible archive gap.
  Protected entries do not become eligible until the owner confirms the required alternate preserved copy or
  explicitly authorizes the relevant exceptional disposition. Do not wait for archive acknowledgement before
  delivering ordinary search records; the two delivery destinations remain independent.
- Persist an exact cleanup intent and obtain an **owner-supported fence** before submitting asynchronous deletion.
  The fence must cover races with new holds, privacy actions, policy revisions and late/replayed ingestion into the
  target scope. Rechecking once and then scheduling an unfenced future deletion is insufficient. A late hold must
  not be acknowledged as protecting bytes already irrevocably deleting; the existing owner must reconcile a
  preserved alternate copy or reject/conflict the claim. This is a required owner contract, **not an implemented
  new hold policy**. If that owner cannot provide the required fence, this composition is blocked.
- Delete selectors must identify only the authorized trusted coverage, including boundary precision and source
  generations. Do not build destructive filters from producer payload substrings or assume a project/run ID is
  authority. Source shows timestamp checks plus stream/pipeline filters; the exact supported selector path still
  needs counterchecks against same-time, mixed-hold and late-ingest cases. Hold back an ambiguous range rather
  than broaden a destructive selector. [S8]
- Track request acceptance, cancellation possibility, query denial, backend processing, object/version removal and
  physical reclamation separately. Async deletion's cancellation window is not a promise to undo completed deletion.
  Default 24-hour cancellation lag and rewrite/compaction overhead must fit the chosen storage envelope; do not
  promise exact physical removal at day fourteen. [S6]
- If cleanup cannot make safe progress, expose the reason and stop new backend admission before exhausting the
  qualified allocation; the bounded search queue then follows WF-038. No hidden unbounded retention, emergency
  deletion of held bytes, or intentional failure of unrelated operations. Source and archive work may continue
  only within their independently measured available resources.

This approach removes reliance on a nonexistent “Loki hold hook,” but adds real application lifecycle orchestration.
It is a design to qualify, not evidence that the current application implements those operations. Permanent loss
of authority/catalog state disables unsafe cleanup/publication as already required by WF-039.

## Delivery, replay and temporary retrieval conditions

- Use the original collector observation ID and collection timestamp on retries. Duplicate backend representation
  is tolerable where accurately identified; deduplicating legitimate identical emissions by content/time is not.
  Disable timestamp-increment workarounds and line truncation. Native/source framing and the tested bounded pieces
  are starting evidence, not an approved bespoke runner protocol or a guarantee that stock plugins preserve every
  byte. Preserve rejected/malformed bytes within the existing 256 MiB holding allocation.
- A homogeneous small push reduces diagnosis ambiguity but does not create atomic acceptance. For an uncertain or
  partly rejected batch, persist uncertainty, keep original IDs, isolate deterministically invalid entries through
  a qualified bounded path and prevent poison data monopolizing delivery. Search visibility is a separate receipt;
  the 204/WAL observation never releases a protected sole copy or marks archival verified.
- No reviewed collector currently meets every required property as a composed stack. Fluent Bit's native oldest-chunk
  output eviction does not prove hold-aware eligibility, safe shared-chunk reclamation or persistent replay
  disposition; Alloy positions and a sink WAL do not prove atomic handoff. Do not select them by assumption or
  approve a custom queue/proxy/plugin just to bridge the gap. Return an actual native-capability conflict to
  WF-038's owner; conditional Loki acceptance would not waive it. [S9]
- Retain the independent verified archive path and its original 90-day clock. Index expiry must not become an export
  deadline, and newest-queue eviction must not become early deletion of already-verified archives. [S10]
- For restore, prefer isolated temporary instances/storage over routine tenant deletion in the live index. The
  owning launcher—not PHP requests or the browser—gets only the separately qualified provisioning capability.
  A restored scope admits only selected verified coverage and allows its actual oldest timestamp, including holds
  beyond ordinary retention. No hardcoded 90-day or ten-year assumption substitutes for that check.
- The measured Loki route needed explicit chunk flush followed by record/content query verification. A flush affects
  the selected instance, which is why restore must not share that action with live ingestion. Scope readiness also
  requires current permissions, complete intended coverage or an explicitly partial result, and held/purged-state
  reconciliation. Process removal is not an application authorization/revocation implementation.
- Keep WF-039's 4 GiB aggregate temporary disk/one-worker/four-ready-scope caps. Backend memory/CPU and dormant-scope
  reactivation costs are additional measured resources; four copies of the small idle benchmark are not a sizing
  receipt. Refuse excess jobs rather than infer unlimited backend process grants.
- Same-time pagination still needs a bounded design in WF-041. A timestamp+1ns cursor silently loses tied records;
  sorting an already truncated backend page does not fix it. Preserve unserved boundary records within a bounded
  query scope or explicitly report a refinement/coverage limit. Do not claim complete traversal until qualified.

## Required acceptance ownership and proposed gate amendment

| Remaining evidence | Owning contract | Consequence if missing |
|---|---|---|
| Reader privilege acceptance, source replay/retirement, checkpoint/persistence ordering | WF-038 and existing runtime owner | No source enrollment/production collection |
| Native oldest-eligible queues, protected-only conflict, permanent/partial rejection and status | WF-038 | No claim of complete contract-compliant collection; no invented adapter approved by this note |
| Actual persistent physical limits and shared-service headroom | WF-038/039; deployment owner | No production enrollment on monitored scratch or logical bucket quota alone |
| Loki S3/SeaweedFS durability, permissions and capacity; exact image/license/security review | WF-040 backend integration | No production storage/backend adoption |
| Continuous verified archive intent/readback/catalog and partial-failure recovery | WF-039 | No archive-ready or older-log-coverage claim |
| Owner-supported cleanup fence, protected-copy checks, async delete completion/reclamation | WF-017/039 and existing preservation/privacy owners | Unsafe cleanup remains disabled; capacity incident/admission stop, not bypass |
| Current authorization, tie-safe context/pages, restore readiness/revocation/cleanup | WF-039/041 | No usable authorized browser release |
| Real-volume sizing, concurrency and composed fault/restore acceptance | WF-042 integrated delivery | No release acceptance from the small synthetic benchmark |

**Proposed human choice:** accept Loki OSS as the planning backend direction and this composition, and explicitly
move the outstanding composed runtime/application qualification from a prerequisite to **WF-040 planning closure**
to mandatory implementation/release gates owned by the contracts above and carried into WF-042. This is not a
completed TASK, unconditional backend approval, collector selection, Docker grant or permission to start services.
WF-042 must preserve each blocker with an executable owner/verification path before delivery work is presented as
ready; unresolved design conflicts still return to their owning decisions. No EPIC/TICKET/TASK is created here.

Alternative: retain the existing preselection gate, keep WF-040 Open and scope further engineering/experiments
before accepting a backend. The already-completed experiment's authority does not cover that work automatically.

This note recommends the first choice to avoid confusing planning with an already delivered system, while retaining
all delivery obligations. **Neither choice has been accepted yet.** Approval should explicitly amend the qualification
handoffs in WF-038/039 rather than silently rewriting their historical resolutions. All other closed choices remain.

## Accepted planning handoff

John subsequently approved the recommended conditional planning choice and gate amendment. This closes WF-040
as planning selection only. All unresolved checks remain mandatory; no collector, storage deployment, new Docker
grant, implementation or further experiment is authorized. See the accepted resolution above and
[WF-042's carried gates](../tickets/WF-042-bound-observability-delivery-and-acceptance.md#mandatory-qualification-gates-carried-from-wf-040).

Next: `/skill:wayfinder work WF-041`.

## Sources

- [S1 — Pinned distributor validation/partial acceptance/metadata normalization](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/distributor/distributor.go)
- [S2 — Pinned ingester instance Push and WAL ENOSPC handling](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/ingester/instance.go)
- [S3 — Pinned WAL guarantees, disk-pressure check and limitations](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/wal.md)
- [S4 — Pinned validation defaults, including blocked-ingestion 260](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/validation/limits.go)
- [S5 — Pinned filesystem object-store production limitation](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/filesystem.md)
- [S6 — Pinned deletion configuration, zero automatic retention and async cancellation](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/logs-deletion.md)
- [S7 — Pinned native retention/compactor behavior](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/docs/sources/operations/storage/retention.md)
- [S8 — Pinned delete-request filter implementation](https://github.com/grafana/loki/blob/09e6ce2ff1bdc19763a10265b870c86f51c98655/pkg/compactor/deletion/delete_request.go)
- [S9 — Collector qualification evidence](operational-log-collection-recovery-boundaries.md)
- [S10 — Archive storage/verification evidence](operational-log-local-archives-and-restore.md)

The new source snapshots are retained under base-checkout
`.runs/notes/observability-backends/integration-reconciliation/`. Their inspection is not a new runtime receipt.
