# Select the authorized log browser integration

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** [WF-040](WF-040-select-the-log-search-backend.md)

**Current resolution:** John approved native Logs in the existing Agent OS shell and **Super Admin-only initially**.
WF-041 is Closed as a planning contract, with current intent-specific protected permissions and the bounded
engineering design below. Historical pending choices are resolved; implementation and runtime qualification remain
outstanding. No authority-policy relaxation or operational grant is approved.

## Question

Should Agent OS provide a native log interface or integrate an existing viewer, and how does either choice preserve
current authorization and a seamless failed-operation/run diagnosis journey?

## Accepted older-log journey

[WF-039](WF-039-define-searchable-retention-and-verified-archives.md) selects browser search through bounded temporary
restore, not downloads only. Include private coverage discovery, time/source selection, asynchronous progress and
explicit partial/missing/corrupt/busy/expired states. Query the isolated temporary scope with current permissions;
do not mix old restored records into live tail, reset their timestamps or treat a job ID as authority. Consume the
finite TTL/byte/job/query limits and current-authorization/cleanup contract there. WF-040 now selects Loki OSS
conditionally for planning, not a production-qualified integration. This input does not select a UI or grant
implementation authority.

## Accepted backend planning input

[WF-040](WF-040-select-the-log-search-backend.md#accepted-resolution--conditional-planning-selection) is Closed
following John's approval of Loki OSS and the explicit qualification-timing amendment. Consume its measured
query/tail/raw/old-import primitives and disclosed limits; unresolved integration/application checks remain
mandatory delivery gates in [WF-042](WF-042-bound-observability-delivery-and-acceptance.md#mandatory-qualification-gates-carried-from-wf-040).

- Agent OS controls current log permissions and private tenant/scope mapping. Neither tenant headers nor project,
  run or restore-job identifiers authorize access. No unrestricted browser-to-Loki/admin connection is approved.
- Application-owned retention/cleanup preserves the accepted 14-day/90-day clocks and existing hold authority;
  native automatic expiry may not delete sole protected copies. Query denial and physical deletion are distinct.
- Loki surrounding context needs bounded source/time queries. Same-time pages must not skip unseen observations
  through timestamp+1ns advancement or sorting an already truncated result page. Preserve identity and honest gaps.
- Isolated restore publication needs verified intended coverage/content and current permission/hold/purge state;
  the experiment required flush plus query verification. HTTP import success or a job ID is not ready restoration.
- Browser buffering, detail limits, revocation, scope expiry and actual aggregate resources remain design/acceptance
  work here and in WF-042, not proven application behavior. Raw content remains preserved under WF-037; rendering
  must not execute it or leak it through public errors/URLs.

No browser choice is made by carrying these inputs.

## Historical interface recommendation — subsequently approved

The [primary-source viewer comparison](../research/authorized-log-browser-options.md) inspects Grafana v13.2.4 and
Logs Drilldown v3.0.0 against this decision's accepted contracts. Explore already supplies tail/pause/resume,
context and expandable fields; an isolated log-operator Grafana organization plus a qualified application gateway
is a viable alternative. Do not claim OSS is inherently unsafe or needs project-level LBAC for the accepted
installation-wide operator policy. Fine-grained Grafana data source permissions/LBAC are not assumed OSS features.

**Recommend native Logs in the existing Agent OS shell**, linked from failed operations/run Summary and
permission-gated navigation. This is more owned viewer work, but retains one application authority/restore journey
without introducing Grafana's separate browser/session boundary. Do not embed raw logs in sanitized Activity or
replace the sessions Dashboard. Loki remains private behind intent-specific bounded application operations;
backend query credentials, storage administration and Docker access never reach the browser.

The linked note proposes the navigation/security boundary and compares native, explicit Grafana navigation,
embedding and Drilldown. It preserves all accepted tail/search/context/detail/relationship/older-restore features,
separate export/restore authority and the existing browser ADRs. Native UI is not a claim that those operations
already exist or that a read grant can prevent manual copying of visible bytes.

**Pending human choice:** prefer this integrated native experience, or choose reuse of Grafana through explicit
navigation with its additional authorization/session/restore integration. No iframe, viewer installation or
prototype is needed merely to make that ownership choice. A separate bounded prototype would require a concrete
unresolved interaction question and its own authority.

WF-041 stays Open. After the direction is accepted, complete its engineering permission, navigation, finite
buffering/detail/query, stream-revocation and reconnect contracts; routine settings are engineering judgments,
not further policy interviews. Surface genuine tradeoffs or contradictions rather than assuming approval.
No runtime experiment, service startup, implementation or EPIC/TICKET/TASK creation occurred.

Historical next: John accepts the native Logs direction or chooses the Grafana navigation alternative.

## Must decide

- Compare native Agent OS UI and qualified existing viewers, including licensing/community feature limits,
  embedding versus navigation, public/private routes, origin/CSP/CSRF constraints, authentication handoff and
  authority enforcement. A shared login or hidden navigation does not establish project-level authorization.
- Cover live tail, pause/resume, bounded buffering while paused, reconnect/cursor/gap behavior, text search,
  time/service/severity filters, surrounding context and expandable safe structured events/exception causes.
- Enter from failed operations and managed runs with available project, Workflow, TASK, run and correlation
  identifiers. Preserve navigable scope/time filters without leaking secrets in URLs or treating an ID as access.
  Extend [TICKET-00097](../../tickets/00097-TICKET.md)'s sessions surface, not another execution Dashboard.
- Enforce current access for stream establishment/reconnection and during long-lived access as appropriate,
  search, event detail, exports, archive catalog/download/restore and query results. Define revocation, stale
  links, denied/missing/purged/unavailable states and sensitivity distinctions without leaking hidden existence.
- Define query/backend credential custody, server-side scope enforcement, deployment topology and capability
  boundaries. The browser must not gain unrestricted backend APIs, arbitrary object access or Docker authority.
- Define useful bounded rendering, keyboard/accessibility behavior, source/coverage provenance and visible
  delayed/duplicate/out-of-order/incomplete data. Separate operational events from authoritative history/evidence.
- Identify whether one disposable prototype/design review is needed to settle interaction uncertainty; ask for
  bounded evidence authority rather than implementing production UI under this decision.

## Resolution boundary

Choose interface integration and security/navigation contracts only. Do not implement UI, enable routes, expose a
backend, provision credentials or register streams. Consume WF-040's conditionally selected backend and scoped
API evidence, not an invented production qualification, together with WF-037/039 privacy and retrieval contracts;
any contradiction needs explicit reconciliation. Final release acceptance is WF-042.

## Accepted direction and engineering contract

John answered **“approved”** to native Logs rather than Grafana navigation. Select a **native Logs workspace in the
existing Agent OS shell**, using private Loki through application-owned operations. Do not install Grafana, embed
an iframe, add another Dashboard or adopt Grafana frontend code. The tradeoff is more owned UI work in exchange for
one application authority/navigation/restore journey. The comparison remains evidence, not a claim that Grafana
could not have been secured.

The following mechanisms/defaults are engineering judgments under John's approved native-interface and initial
Super Admin-only choices and the existing WF-036–040 contracts, not claims that he individually approved library
settings or measured their fitness. Actual integration/resource/accessibility evidence remains mandatory in WF-042
under WF-040's approved timing amendment; no runtime qualification is claimed. Conflicts return to the owning
contract rather than silently weakening it.

### Surface and navigation ownership

- Use the existing protected application frame, theme, typed API boundary and sole `/me` authority owner. A Logs
  workspace is an operational diagnosis view, not another Workflow engine, sessions Dashboard or history store.
  Enter from a failed operation's safe correlation reference, run Summary and permission-gated Logs navigation.
- Preserve [TICKET-00097](../../tickets/00097-TICKET.md)'s four independently loaded sections. Add navigation to
  the Logs feature rather than placing raw diagnostic output into sanitized Activity, Summary or authoritative
  history/evidence responses. The eventual observability handoff owns those entry links; no existing TICKET/TASK
  acceptance criteria or terminal-first dependency is silently rewritten here.
- Show the selected scope/time and an installation-wide log-access indication. Available project, Workflow, TASK,
  run and correlation relationships are filters/references, not grants. Missing relationships remain unknown.
  Related entity navigation separately checks that entity's current authority; log access does not reveal extra
  protected entity metadata or authorize Workflow/evidence operations.
- Allow only reviewed typed, non-secret navigation identifiers, time bounds, service keys and severity choices in
  routes. Keep free text, raw messages, exception text, credentials and backend expressions out of URLs/history,
  referrers, analytics and producer diagnostics. Submit sensitive search text in a bounded request body; log the
  invocation's safe metadata, not that text. No generic `returnTo` or arbitrary backend/object URL is accepted.
- Reuse ADR 0004's bounded volatile intended route; restored navigation rechecks current permissions and target
  state. Reload may lose volatile search text by design. No persisted private query/result cache, saved search
  service or new link-token authority is introduced.

### Capability and enforcement contract

Use exact capability checks through the existing access-control owner, not Role-name shortcuts. Initial planned
Permission names follow the current catalog's naming conventions:

| Operation | Required planned permissions and target checks |
|---|---|
| Open Logs; live search/tail/context/detail; source/coverage metadata | `READ_LOGS`; current installation/source/time/disclosure constraints |
| List archive coverage/status; submit bounded restore | `READ_LOGS` + `RESTORE_LOG_ARCHIVES`; requester, selected catalog segments and current policy |
| Query/detail in an existing ready temporary scope | `READ_LOGS` + `RESTORE_LOG_ARCHIVES`; requester-owned scope, readiness, current policy and fixed expiry |
| Application-provided live/restored bulk export | `READ_LOGS` + `EXPORT_LOGS`; restored data also requires `RESTORE_LOG_ARCHIVES` and valid scope |
| Download verified archive content | `READ_LOGS` + `DOWNLOAD_LOG_ARCHIVES`; catalog-owned selection and current disclosure policy |

Archive download holders may inspect the bounded catalog/status needed for that operation without restore power.
Restore status and results initially remain requester-owned; an installation-wide read grant alone does not expose
another requester's temporary scope. No sharing or scope-management feature is implied. Related Artifacts, holds,
purge, policy changes and storage/Docker administration retain their existing separate authorities.

The inspected [managed catalog](../../../src/Domain/Authorization/InstallationManagedPolicy.php) has none of these
log permissions. Its [policy contract](../../../docs/engineering/MANAGED_POLICY.md#policy-ownership) and
[TICKET-00031](../../tickets/00031-TICKET.md#validation-and-permissions) reserve installation-wide permissions as
`SUPER_ADMIN_ONLY`; protected definitions cannot be delegated to custom roles or directly to Agents. That tier is
**not merely a rule about who administers grants**. Choosing it would restrict the initial audience to holders of
the managed Super Admin authority. Conversely, using `ADMIN_SAFE` to make global raw-log access delegable would
require an explicit policy amendment, not a routine naming/configuration choice or a new Role name alone.

### Historical audience choice — resolved by the approval below

WF-036 approved explicit installation-wide log permissions, not ordinary project-member access; it did not resolve
this concrete tier/delegation conflict. John's native-interface approval does not implicitly amend authority owners.

1. **Recommend Super Admin-only initially.** Add the intent-specific log permissions as protected definitions
   through the existing managed-policy owner, preserving its role invariants. Enforce current capabilities rather
   than a Role-name bypass. This fits the present installation-wide authority model, but non-admin operators cannot
   be granted log access independently. Separate query/export/restore/download checks still define operation intent;
   they do not claim separately grantable audiences under the current managed role.
2. **Alternatively, support non-admin log operators.** Explicitly amend the installation-wide delegation policy,
   then reconcile the exact capability/tier/grant and actor/scope rules with TICKET-00015/00031 and the qualified
   package. Do not grant full Super Admin merely to view logs, label a globally sensitive capability `ADMIN_SAFE`
   without approval, or introduce an application-only bypass around protected package invariants.

No choice or grant is recorded yet. Both options retain no automatic `ROLE_USER`/project-member access, no Docker/
storage-administration rights and current server checks. Preserve existing IDs and generate new definitions through
the owning factory during authorized delivery, not planning. Unknown/missing authority denies.

### Approved audience resolution

John selected **“super-admin only initially.”** Use the protected `SUPER_ADMIN_ONLY` definitions `READ_LOGS`,
`EXPORT_LOGS`, `RESTORE_LOG_ARCHIVES` and `DOWNLOAD_LOG_ARCHIVES` through the existing managed-policy owner, granted
to managed `ROLE_SUPER_ADMIN` under its existing invariants. Keep the operation-specific current capability and
scope checks above; never substitute a Role-name, client visibility or stale token bypass.

This resolves WF-036's initially unspecified log-operator audience without relaxing TICKET-00015/00031's protected
permission policy. Non-admin operators, ordinary project members, custom roles and direct Agent grants receive
no log capability through this decision. Non-admin delegation is deferred, not an automatic future TASK; any later
proposal needs explicit authority-owner reconciliation and human approval. Do not promote a user merely to make a
log link work. Separate protected operation checks remain useful even though the initial managed role receives
all four permissions; they do not imply independently grantable initial audiences.

The permission catalog and assignments have **not** been changed. Adding stable definitions and qualifying/enrolling
the protected catalog remain deliberate delivery prerequisites. Preserve all existing identities/invariants and
use the owning ID factory. Log access itself grants no Docker, storage, preservation, purge or execution power;
those operations still require their independent existing authorities. No new runtime or implementation grant follows.

### Disclosure and current authorization

`READ_LOGS` includes authorized raw detail, not just a supposedly secret-free subset: WF-037 rejected pipeline
redaction. Separate extraction capabilities constrain application-provided bulk/download operations, not manual
copying, screenshots or remote deletion of already disclosed bytes. Producer-side secret avoidance remains required.

Every server request checks current actor/session/capability and applicable scope/purge policy before fetching and
again before releasing private output. Long transfers recheck at each bounded output batch. Use current authoritative
state, not only JWT claims, stale `/me`, a tenant header, a restore ID or an earlier successful query. Unknown policy
withholds output. Revocation denies subsequent authorized releases; bytes already released/in flight cannot be recalled.
Concurrent disclosure/purge behavior must participate in the existing owner fence, not invent a second hold authority.

Keep private responses `no-store`; apply the existing authentication and applicable same-origin/CSRF profile, deny
credentialed cross-origin access and do not weaken CSP for logs. Client guards gate loaders as well as rendering.
Retire streams/requests and clear private results on logout, refresh, invalidation, expiry or identity/scope change;
context, query and authority generations reject late successes and errors. ADR 0004's 60-second client freshness
budget is not a server permission cache or revocation guarantee.

### Private query and stream boundary

The browser calls intent-specific Agent OS operations, never Loki directly. Application owns authorization,
retention visibility, typed filters, restore identity and bounded query construction; Adapter owns Loki credentials,
HTTP/WebSocket translation and provider errors. No arbitrary LogQL/admin proxy, caller-supplied tenant, push/delete/
flush endpoint, unrestricted label discovery, object path or storage credential is exposed. Keep existing public
status/envelope/message/correlation/cache behavior unchanged; private diagnostics never enter public errors.

Prefer bounded **same-origin authenticated fetch streaming** for tail, compatible with the existing Bearer header
profile; translate private Loki tail through the server adapter. Do not put tokens in WebSocket/EventSource URLs or
introduce another persistent browser credential. Qualify framing, ingress buffering, cancellation and worker
occupancy with the owning runtime before enrollment. This selects an integration contract, not a new deployed
service or a requirement to hold an unqualified shared PHP worker indefinitely.

[TASK-00389](../../tasks/00389-TASK.md)'s Mercure transport owns safe committed Workflow notifications, not raw logs.
Do not route diagnostic bytes into its topics/backlog or reinterpret that task's authorization as a raw-log grant.
If implementation proposes another transport, it must retain these boundaries and explicitly reconcile the owner;
missing streaming qualification is a delivery blocker, not permission to substitute silent polling or drop live tail.

### Search, context and exact detail

- Search executes over the selected server-side scope, not just rendered rows. Start with literal text search and
  typed time/service/exact severity/available relationship filters; escape provider syntax. Preserve all eight
  Monolog levels distinctly plus unknown/unparsed service severity; default All includes DEBUG. UI filters do not
  suppress collection or archival and never change original content.
- Default time presentation/filtering uses trusted collection time with separately labelled emitted time. Optional
  emitted-time filtering still obeys collection-clock retention and reports unknown source timestamps. An initial
  15-minute live view is a convenience; the entire allowed 14-day window remains queryable through bounded pages.
- Context is a separate bounded read around the selected observation/source generation. Explicitly mark when it
  relaxes text/severity filters to reveal neighboring events; never relax authorization, restore isolation or
  time-policy constraints. Cross-source correlation is an explicit relationship filter, not invented adjacency.
- Keep stable observation/source identity and original clocks. Exact same-time distinct records must remain
  discoverable. Do not advance timestamp+1ns past unseen ties, content-deduplicate repeated messages or sort only
  a truncated page and claim complete order. Query cursors bind scope/filter/time/direction and current policy;
  late arrivals and unstable coverage are visible. Qualify a tie-safe traversal or report an explicit incomplete
  boundary; required complete bounded traversal remains a delivery blocker, not an optional omission.
- Expand event context/extra, emitted exception type/message/file/line/frames/causes and release identity where
  present. Never synthesize locals/arguments/object snapshots or new request/response dumps. Preserve raw bytes
  and fragment provenance; malformed/binary data has an explicit escaped/reversible representation. Rendering
  uses inert text, not HTML, terminal controls, executable links or automatic external resource loads.
- Large records use a labelled preview plus bounded authorized continuation. A preview is not a complete record;
  copying/exporting it must identify that limitation. No silent truncation/redaction of stored originals, and no
  automatic loading of every cause, fragment or related event.

### Initial finite browser and transport envelope

These are configurable engineering defaults to qualify, not benchmark receipts or a claim that serialized bytes
bound JavaScript/PHP heap. WF-042 must qualify total process/backend memory, CPU, worker and socket use in addition
to these counts. Admission counts include abandoned work until cancellation/completion is confirmed.

| Boundary | Initial limit and behavior |
|---|---|
| Typed request | At most 16 KiB encoded request, including at most 4 KiB UTF-8 literal search text; reject excess before provider work |
| Search/context/detail | At most 30 seconds; each page at most 500 events and 1 MiB encoded output; at most 2 active read/export operations per installation across live and restored scopes, with one per requester; no unbounded waiting queue |
| Context | Initially 50 observations on each side within a 5-minute window on each side; page/window changes are explicit bounded reads, not unlimited fanout |
| Raw detail | At most 64 KiB raw input per fragment and 1 MiB encoded response; explicit continuation bound to the same observation; one expanded detail per view |
| Tail connections | At most 4 per installation, 2 per requester, 1 per view; lease at most 30 seconds or remaining credential validity, whichever is shorter; current authorization on every lease and output batch |
| Tail output/backpressure | At most 64 KiB encoded per batch and 1 MiB queued output per stream; flush eligible data within 1 second while healthy; slow readers receive an explicit interruption/gap and disconnect rather than unbounded buffering |
| Browser retained data | At most 2,000 observations or 8 MiB encoded data per view, whichever first; include paused backlog and loaded detail in that budget; render at most 200 rows at once with accessible bounded navigation |
| Pause | Freeze the visible reading position; pending arrivals use at most 500 observations/2 MiB within the same total budget, not an additional allocation; on exhaustion stop following and show the coverage gap without evicting the frozen view |
| Reconnect | Bounded jittered 1–30-second backoff, at most 5 failed attempts before explicit Retry; reauthorize and reconcile bounded search coverage before claiming caught up; never replay restore/export/business mutations automatically |
| Bulk extraction | Live/restored export at most 7 days and 16 MiB encoded output per explicit request; archive download at most 16 MiB compressed selected objects per explicit request, each transfer at most 30 seconds; reject known excess or mark partial transfer precisely, no hidden fanout or server spool |

Tail batches must recheck current access at the output boundary even when the lease is still valid. No payload is
released on a failed/unknown authority check. Healthy idle streams send a bounded non-sensitive heartbeat at most
every 5 seconds, without treating that heartbeat as collection health. Lease rollover/reconnect is read-only;
re-establishing transport cannot renew authorization, temporary scope expiry or retention.

Pause/resume never promises an unlimited browser queue. Resume reconciles the stopped interval through bounded
search and shows exactly which coverage is known, missing or unavailable. Do not jump to current time and label
it continuous. Retain the user's frozen context until an explicit resume/navigation action, except when current
authority requires clearing it. Browser-buffer gaps differ from collector loss and archive gaps. Backend tail
has no durable exactly-once cursor; stable observation IDs and coverage receipts, not message text, govern retries.
If evidence cannot establish coverage, say unknown; retained original source pieces remain discoverable by search.

For archive download, expose catalog objects individually when a selection exceeds the transfer limit; no newly
created giant aggregate object is required. Size/time limits do not shorten archive retention or replace older-log
browser search. Known oversize selections return an actionable narrower-selection response; interruption never
claims a completed export. No new export-copy store or persistent read job is introduced.

During [TICKET-00116's approved TASK decomposition](../../tickets/00116-TICKET.md#approved-task-decomposition-and-current-handoff),
John approved explicit shared download admission: downloads use the same two installation-wide read/export slots,
one/requester, not a separate pool. Extraction output batches are <=64 KiB and queued output <=1 MiB per transfer.
These are accepted delivery bounds, not runtime/resource evidence; actual browser/proxy/worker/provider fit and
cancellation remain mandatory qualification. The existing selection/16-MiB/30-second limits and no-spool contract
remain unchanged.

### Restore, state and accessible interaction

Use WF-039's existing 7-day/1-GiB restore admission, job/worker/physical limits, verification and fixed 24-hour ready
scope expiry unchanged. The native workspace provides private coverage selection, explicit submit, status/progress,
then search/context/detail in the isolated ready scope. Submission is a mutation with stable request identity;
resolve uncertain outcomes before retrying. Restore never mixes into live tail, rewrites clocks or starts business
execution. Expiry/revocation denies access independently of delayed physical cleanup; downloads/exports do not extend
scope lifetime. Requester-owned status uses current permissions, not possession of a guessed job ID.

Keep loading, empty, denied/not available, delayed, partial, interrupted, busy, corrupt, purged, expired and backend-
unavailable states distinct **only where the actor may know the target/state exists**. Unauthorized/missing IDs must
not disclose hidden existence or counts. A backend failure is not an empty success; stale links do not prove purge.
Show source/generation, time basis, gaps/duplicate uncertainty and independent delivery/archival status without
invented completeness. A healthy browser connection does not prove healthy collection or verified archives.

Use labelled keyboard-operable search/filters, explicit Follow/Pause/Resume, stable focus during arrivals, accessible
row/detail navigation, non-color severity text, reduced-motion behavior and bounded status announcements. Never
announce every log line through an aria-live region or move focus on tail arrival. Preserve readable light/dark,
wrapping, zoom and narrow layouts without forcing an entire raw record into the DOM. Final visual treatment belongs
to the existing shell/theme owner. No disposable prototype is required for this resolved ownership contract; actual
production keyboard/focus/responsive/browser evidence remains mandatory.

### Qualification handoff and closure

Required evidence includes: current-capability/catalog enrollment and denied cross-intent/scope access; revocation,
expiry and late-response races during streams/restores/downloads; unsafe raw rendering and URL isolation; exact
severity and whole-range search; same-time traversal and bounded context; binary/large-record continuation;
pause overflow, reconnect/late arrivals and honest gaps; cancelled/slow clients and actual aggregate resource use;
archive readiness/partial/corrupt states; and real accessible navigation from failures and managed runs.

Test owned application/client behavior, and qualify provider/ingress/stream/runtime mechanisms directly with owning
tools. Existing library/source documentation, the synthetic backend probe and this approval do not prove native
browser behavior. Carry these gates into [WF-042](WF-042-bound-observability-delivery-and-acceptance.md); do not
add product-suite tests of configuration, wrappers or deliberately seeded tooling failures. Full `./bin/build`
and independent review remain required on the eventual implementation path.

WF-041 is Closed on John's native-interface and initial Super Admin-only approvals, with the engineering contract
above. Carry the named unverified browser/authority/resource checks into WF-042; closure is not a successful UI
or security test. No UI, permission assignment, route, stream, service, prototype, runtime experiment, dependency
update, EPIC, TICKET or TASK is created by this planning work. The existing terminal-first milestone and Board
priority remain unchanged.

Next: `/skill:wayfinder work WF-042`.
