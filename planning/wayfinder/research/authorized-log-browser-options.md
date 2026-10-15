# Authorized log browser options

**Decision update:** John approved native Logs in the existing Agent OS shell and **Super Admin-only initially**.
WF-041 is Closed as a planning contract. The comparison and audience interview below preserve their historical
recommendations, not pending human choices. No viewer was implemented, permission granted or service installed.

## Question

For [WF-041 — Select the authorized log browser integration](../tickets/WF-041-select-the-authorized-log-browser-integration.md),
should the accepted operator diagnosis journey use native Agent OS UI or an existing Loki viewer?

## Short answer

**Recommend a native Logs workspace in the existing Agent OS application shell**, reached from failed operations,
managed runs and permission-gated navigation. Keep Loki private behind bounded application queries/streams.
This is a pending product/integration choice, not an accepted UI design or implementation grant.

Grafana Explore already supplies much of the interaction. Native is recommended because current Agent OS authority,
separate export/restore capabilities, temporary scope expiry and failure/run navigation remain application-owned
in either approach. Reusing Grafana reduces log-viewer implementation but introduces another authenticated browser
surface and an integration boundary; it does not remove that application work. No measured delivery-cost claim is made.

## Evidence scope

Read-only primary-source inspection on **2026-10-15**:

- Grafana **v13.2.4**, source `af4a33e3786594ad72f72d97e6744010330c2977` ([release][g-release]).
- Grafana Logs Drilldown **v3.0.0**, source `411f01bfd42f86bd373aa423dd7c24be84dc898f` ([release][d-release]).
- Agent OS's approved browser ADRs, current client foundation and sessions requirements, plus WF-036/039/040.

These are inspected candidate versions, not installation selections. Documentation at those exact source revisions
supports the comparison; no viewer was installed, started, embedded, benchmarked or tested with Agent OS. The
release/tree responses and downloaded source hashes are in base-checkout ignored scratch
`.runs/notes/observability-browser/`, including `source-receipts.json`. No real logs, credentials or private data
were sent to external services. No upstream installation/demo commands were executed.

## Findings

### Existing viewers cover useful interactions

[Grafana Explore][g-explore] documents live tail, pause/resume, time-based queries, surrounding context, expandable
fields/links, raw JSON export and log-line links. Its client-side string search filters **displayed results**;
whole-range text search still needs a backend query. Context can widen stream label filters, so application access
constraints cannot be merely prefilled UI filters. Its optional content-based deduplication is not the accepted
observation identity/replay contract. Its displayed severity groups combine notice/info and emergency/alert/critical;
qualification must preserve exact original Monolog levels, not mistake display categories for all eight levels.

[Logs Drilldown][d-overview] adds queryless service/label/field exploration and links to Explore. Its
[log views][d-view] include details/context, JSON/table views and downloads. Its [access documentation][d-access]
requires Grafana >=13.1 and Loki >=3.2 and lists structured metadata, volume, level detection and pattern ingestion.
However, the same release's [configuration documentation][d-config] explicitly allows disabling patterns and their
API calls. Therefore pattern ingestion is **not assumed unavoidable**. Volume/pattern conveniences do not silently
add metrics, error grouping or AI features to this map's initial scope. The inspected version pair meets the stated
version floors on paper, not a runtime compatibility receipt.

### Authentication is not the full permission contract

Grafana's [data source management][g-data] documents that organization users can query its data sources by default;
a Viewer may issue queries beyond the dashboards they can see. Its fine-grained data source permission feature is
listed as Enterprise/Cloud. [Label-based access control][g-lbac] is also Enterprise/Cloud, with Enterprise Logs
required for self-hosted Loki LBAC. These features must not be attributed to OSS or treated as Agent OS grants.

**This does not make Grafana OSS inherently unsuitable.** WF-036 already accepts installation-wide log operators,
not project-member row access. An isolated log-operator organization plus an application-authorized gateway could
be a legitimate candidate. It would still need current-access enforcement, revocation, separate export/archive
operations, original-clock live-window limits and isolated expiring restore scopes across every query/detail/tail/
label/download path. Neither organization membership nor a shared identity header alone proves those contracts.

[Auth proxy][g-auth] can accept a trusted user header and documents source-address restrictions against spoofing.
It also has synchronization caching and an optional login-token cookie that authenticates later requests without
the proxy header on every route. Agent OS would have to qualify the chosen mode, prevent direct bypass/header
spoofing, and handle existing streams and sessions after revocation. No auth-proxy deployment is selected here.

### Navigation versus embedding

- **Navigate to Grafana:** easiest reuse of its full viewer, but a second application surface/session boundary.
  Carry only reviewed safe navigation parameters; arbitrary query text or raw diagnostic messages must not become
  URLs. Recheck current authority at the destination. This remains a real alternative if John prefers reuse.
- **Embed Grafana:** does not remove those boundaries. [Configuration][g-config] disables embedding by default
  using `X-Frame-Options: deny`; enabling it needs deliberate frame/origin/CSP/cookie/CSRF and logout review.
  Same-origin reverse proxying is not automatic safety, and weakening Agent OS's browser ADRs is not approved.
- **Native UI:** uses the existing application frame and authority owner without an iframe, second login or
  new user-facing backend. It still requires real frontend/API work, bounded streaming and delivery qualification.

Both inspected repositories carry [AGPL-3.0 license text][g-license] ([Drilldown][d-license]). Distribution,
modification and source obligations require review for the exact adopted components; do not copy their UI code
into Agent OS under an assumption that separate-service licensing automatically covers combined frontend code.
A native interface using Loki's HTTP API does not require adopting Grafana frontend code. This is not legal advice
or a completed release/license review.

## Comparison and recommendation

| Option | Advantage | Remaining cost / qualification | Recommendation |
|---|---|---|---|
| Native Logs workspace | One application journey, authority owner and restore lifecycle | Build log interactions; qualify API/stream/resource/accessibility behavior | Prefer for the initial accepted journey |
| Navigate to Grafana Explore | Existing capable viewer and query language | Additional service/session/authorization bridge; restore and export integration | Viable alternative if viewer reuse outweighs integrated UX |
| Embed Explore | Existing viewer inside a surrounding frame | Same integration plus frame/origin/cookie/CSP issues; embedding is not authorization | Do not prefer over native or explicit navigation |
| Grafana Logs Drilldown | Queryless discovery and richer service exploration | Grafana boundary plus plugin/config/resource qualification; extra features need scope control | Not needed to satisfy this bounded initial journey |

This is a bounded comparison of the primary Loki-native Grafana surfaces, not an exhaustive viewer-market survey.
No vendor option is claimed fully qualified or impossible to secure.

## Proposed native integration boundary

- Reuse the existing application shell/theme and its current-authority owner. Provide a Logs workspace and
  links from failed operations/run Summary, not another sessions Dashboard. Keep raw diagnostics **separate from**
  TICKET-00097's sanitized Activity and authoritative history/evidence; do not silently broaden those responses.
- Preserve live tail, pause/resume, backend text search, time/service/exact severity filters, surrounding context,
  expandable escaped raw/structured detail and available project/Workflow/TASK/run/correlation references.
  Show installation-wide authority explicitly; project/run filters are navigation, not new grants.
- Agent OS server operations own current permissions, approved source/time/scope constraints and backend query
  construction. No public Loki endpoint, arbitrary LogQL/admin pass-through, browser-held backend credentials,
  Docker socket, storage credentials or arbitrary object-path access. A public SPA shell is not permission to
  disclose data; all private data operations need current server authority.
- Reuse [ADR 0003](../../adr/0003-browser-authentication-security-profile.md) and
  [ADR 0004](../../adr/0004-client-authority-and-runtime-state.md): approved same-origin authentication/CSRF
  boundaries, volatile private state, no-store, one authority owner, invalidation/late-response fences and safe
  intended navigation. Do not place tokens or sensitive search/log content in URLs, persisted browser state or
  telemetry. Live transport must fit that profile, not invent URL bearer tokens or another login cookie.
- Keep restore admission/status/query/detail, bulk export and archive download intent-specific and independently
  authorized as already required. Recheck permissions, scope expiry and current hold/purge state; a job ID is not
  authority. Viewing necessarily exposes visible bytes: separate export permission controls application-provided
  extraction, not manual copying/screenshots or guaranteed deletion of external copies.
- Honor WF-039's finite temporary-restore/query limits and WF-040's tie/receipt caveats. Finalize finite live/paused
  buffering, detail/context limits, reconnect coverage and stream revocation behavior within WF-041; do not infer
  those engineering contracts are proven by choosing native.
- Render logs as inert text, preserving original bytes through the storage/retrieval contract. No execution of
  emitted HTML/ANSI/link payloads or automatic external requests from diagnostic content. Bounded previews must
  disclose incompleteness and provide authorized bounded continuation, not silently redact or drop stored records.
- No prototype is needed **to choose interface ownership**. Final visual treatment, keyboard/focus, light/dark,
  responsive and failure-state evidence belong to delivery. Request a separately bounded prototype only if a
  specific unresolved interaction question actually needs one; do not create a demonstration TASK.

## Historical audience reconciliation

John approved the native interface direction. WF-041 now records proposed capability/navigation/stream/resource
contracts, but inspection exposed a genuine authority-policy decision rather than a routine setting:

- The [managed catalog](../../../src/Domain/Authorization/InstallationManagedPolicy.php) has no log permissions.
- The [managed-policy contract](../../../docs/engineering/MANAGED_POLICY.md#policy-ownership) and
  [TICKET-00031](../../tickets/00031-TICKET.md#validation-and-permissions) make installation-wide permissions
  `SUPER_ADMIN_ONLY` and forbid delegating protected definitions to custom roles or directly to Agents.
- Therefore protected log permissions would mean initial Super Admin-only access, not freely assignable log
  operators. Making global log access delegable requires an explicit owner-policy amendment; merely naming a
  log-operator Role or using `ADMIN_SAFE` cannot silently resolve the conflict.

Recommend Super Admin-only initially to preserve the accepted authority model. If John needs non-admin operators,
explicitly amend/reconcile that policy without granting broad administration solely for log access. Both choices
retain current intent-specific log permissions and no automatic project-member/Docker/storage authority.

WF-041 stays Open for this choice; WF-042 cannot start yet. No EPIC/TICKET/TASK, implementation, permission grant,
dependency installation or service startup is authorized. Terminal-first milestone and Board priority are unchanged.

## Approved resolution and current handoff

John subsequently selected **“super-admin only initially.”**
[WF-041's approved resolution](../tickets/WF-041-select-the-authorized-log-browser-integration.md#approved-audience-resolution)
retains protected intent-specific log permissions through the existing managed authority, with no Role-name bypass
or custom-role/direct-Agent delegation. Non-admin operator support is deferred and requires explicit future policy
reconciliation, not an automatic TASK. The catalog is unchanged; enrollment remains an implementation prerequisite.

The native interface, finite browser/stream/query/restore contract and named outstanding checks now hand off to
WF-042's delivery/acceptance decision. Source research and the earlier backend probe do not qualify the native UI,
current permission races, transport, accessibility or production resource fit. No implementation or runtime authority
is inferred from planning closure.

Next: `/skill:wayfinder work WF-042`.

## Sources

[g-release]: https://github.com/grafana/grafana/releases/tag/v13.2.4
[d-release]: https://github.com/grafana/logs-drilldown/releases/tag/v3.0.0
[g-explore]: https://github.com/grafana/grafana/blob/af4a33e3786594ad72f72d97e6744010330c2977/docs/sources/visualizations/explore/logs-integration.md
[g-data]: https://github.com/grafana/grafana/blob/af4a33e3786594ad72f72d97e6744010330c2977/docs/sources/administration/data-source-management/_index.md
[g-lbac]: https://github.com/grafana/grafana/blob/af4a33e3786594ad72f72d97e6744010330c2977/docs/sources/administration/data-source-management/teamlbac/_index.md
[g-auth]: https://github.com/grafana/grafana/blob/af4a33e3786594ad72f72d97e6744010330c2977/docs/sources/setup-grafana/configure-access/configure-authentication/auth-proxy/index.md
[g-config]: https://github.com/grafana/grafana/blob/af4a33e3786594ad72f72d97e6744010330c2977/docs/sources/setup-grafana/configure-grafana/_index.md
[g-license]: https://github.com/grafana/grafana/blob/af4a33e3786594ad72f72d97e6744010330c2977/LICENSE
[d-overview]: https://github.com/grafana/logs-drilldown/blob/411f01bfd42f86bd373aa423dd7c24be84dc898f/docs/sources/_index.md
[d-access]: https://github.com/grafana/logs-drilldown/blob/411f01bfd42f86bd373aa423dd7c24be84dc898f/docs/sources/access/_index.md
[d-config]: https://github.com/grafana/logs-drilldown/blob/411f01bfd42f86bd373aa423dd7c24be84dc898f/docs/sources/access/configure.md
[d-view]: https://github.com/grafana/logs-drilldown/blob/411f01bfd42f86bd373aa423dd7c24be84dc898f/docs/sources/view-logs/_index.md
[d-license]: https://github.com/grafana/logs-drilldown/blob/411f01bfd42f86bd373aa423dd7c24be84dc898f/LICENSE

Repository sources: [client foundation](../../../client/README.md),
[sessions Dashboard owner](../../tickets/00097-TICKET.md),
[WF-036 source/access choice](../tickets/WF-036-select-initial-log-sources-and-diagnosis-journeys.md),
[WF-039 restore contract](../tickets/WF-039-define-searchable-retention-and-verified-archives.md),
and [WF-040 accepted backend direction](../tickets/WF-040-select-the-log-search-backend.md#accepted-resolution--conditional-planning-selection).
