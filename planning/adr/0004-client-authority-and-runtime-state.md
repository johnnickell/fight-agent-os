# ADR 0004: Define client authority and runtime-state ownership

- **Status:** Accepted
- **Date:** 2026-09-28
- **Decision owners:** Fight Agent OS maintainers
- **Acceptance:** Explicitly approved by the human maintainer under [TASK-00025](../tasks/00025-TASK.md) on 2026-09-28

## Context

The React foundation must not turn a token, a stale principal, or a second client store into permission truth.
It needs predictable loading and recovery without persisting credentials or unfinished business workflows.
This decision defines ownership and coordination contracts, not a state-library choice or working authentication.

It specializes [EPIC-00003](../epics/00003-EPIC.md), [EPIC-00004](../epics/00004-EPIC.md),
[TICKET-00012](../tickets/00012-TICKET.md), and the decisions in
[WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md),
[WF-005](../wayfinder/tickets/WF-005-design-authentication-and-authorization-journeys.md), and
[WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md).
[ADR 0003](0003-browser-authentication-security-profile.md) remains authoritative for credentials, CSRF,
refresh timing, cookie lifecycle, conflict recovery and browser coordination security. This ADR does not relax it.
[ADR 0001](0001-application-ownership-and-orchestration.md) preserves package and server-policy ownership.

## Decision

### One principal projection and one owner

`GET /api/v1/me` is the sole client source of current roles and permissions. The server resolves current
PostgreSQL authority, independently of client state. The endpoint requires authentication, not `VIEW_DASHBOARD`.
Its safe projection contains stable user identity, canonical email for current-user presentation, and unique
role and permission names. It excludes session identifiers, authentication versions, credentials and internal
lifecycle data. The implementing server/feature service owns the exact View and snake_case-to-camelCase codec.
Reject malformed or incomplete projections atomically; never salvage a partial permission list.

One volatile API-cache entry per tab's active authentication context owns the principal, load state, freshness,
request generation and invalidation. It is session-scoped in lifetime, **not** browser `sessionStorage` and not
keyed by a raw token or exposed server session identifier. Components, route libraries and contexts subscribe
to this owner; they do not copy the principal into another mutable store. The authentication coordinator owns
volatile credentials and refresh progress, not another authenticated user/permission snapshot. Token readiness
alone never makes the principal authenticated. Authentication UI derives its combined state from these owners.

Only a successfully decoded current-generation `/me` result may enter `authenticated`. The foundation may use
an explicitly injected deterministic loader in tests and development evidence; it must not silently supply a
fake authenticated principal in production while `/me` is absent. The actual server endpoint belongs to
[TASK-00117](../tasks/00117-TASK.md); [TASK-00049](../tasks/00049-TASK.md) connects its production feature service.

### Authority states and freshness

These are semantic states, not a mandate for an enum or library. Every non-authenticated state denies protected
route rendering and permission-dependent actions. Public authentication/recovery routes remain available.

| State | Meaning | Permitted exit |
|---|---|---|
| Unknown | Boot, reload or newly created authentication context; no established authority | Controlled restoration or login, then loading; confirmed absence becomes anonymous |
| Loading | A current-generation principal request is in flight; no trusted prior projection | Valid result becomes authenticated; authentication rejection becomes anonymous/terminal; network or protocol failure stays non-authoritative with explicit retry |
| Authenticated | Valid decoded principal for the current credential/context generation within freshness budget | Invalidation, expiry, refresh, logout, authentication failure or context change immediately removes authority |
| Anonymous | No established session, or completed local logout | Explicit login or a new permitted startup restoration; no background retry loop |
| Stale | Previous principal invalidated or its freshness budget elapsed | Clear usable projection, coalesce one bounded refetch when credentials remain usable; otherwise coordinate restoration |
| Refresh-failed | Refresh could not complete because of network, timeout or service failure, without proof of revocation | Clear local access and principal authority; show recoverable failure; explicit retry starts a new bounded restoration, not an automatic loop |
| Terminal | Revocation, terminal refresh rejection, exhausted conflict recovery or other terminal authentication failure | Clear credentials/private caches, reject late work; require explicit reauthentication, not a peer token or delayed result |

Network/protocol errors while loading are distinguishable safe error details, never an empty permission set
masquerading as a successful principal. A valid principal with no required permission is **forbidden presentation**,
not anonymous or terminal; a role or permission absent from a valid projection does not trigger refresh loops.

Use a maximum **60-second monotonic freshness budget** from the start of a successful principal request, not
from its delayed receipt. A response arriving after that budget cannot authorize. On budget exhaustion, tab
resume/focus after being hidden, or clock discontinuity, mark stale before another protected render/action and
refetch on demand. A foreground expiry invalidates mounted protected presentation; it need not start polling.
Coalesce concurrent demands and do not extend freshness on read, network failure or retry. This bounds normal
client presentation staleness, not server revocation latency or suspended-browser execution. Permission changes
without a delivered signal may remain visible inside that budget; every server operation still checks current
authority and can reject immediately. No push subscription or instant cross-tab consistency is claimed.

### One permission evaluator, server authority unchanged

One evaluator consumes the current principal state and exact required permission names. Complete-route metadata
explicitly distinguishes public routes, authentication-only routes, and protected routes requiring **all** listed
permissions. Nested routes accumulate requirements; missing or malformed protection metadata does not make a
route public. Authentication-only routes allow an empty permission requirement only when explicitly declared.
Layouts, Pages and Components use the same evaluator for visibility/disabled decisions. A disabled control must
also suppress its action handler; a route guard must gate protected loaders as well as the rendered outlet.

Unknown/loading/stale/error states deny. Role names are display data, never shortcuts, tier inference or an
administrator bypass. Permission names compare exactly without case folding or substring matching. A valid
principal denied a route sees a forbidden state rather than a login redirect cycle. A server `403` denies that
operation even if client presentation allowed it; invalidate/refetch authority once as appropriate, show the
forbidden outcome, and never replay the operation automatically. Some `403` responses reflect target policy,
not changed permissions; a refreshed projection cannot override that denial.

Client guards are usability, not security enforcement. They neither replace nor duplicate package Domain policy
or server Application actor/target checks. A successful client evaluation never authorizes a server mutation.

### Invalidation, race control and recovery

The principal cache and authentication coordinator expose narrow in-process notifications: credentials changed,
authority invalidated, logout and terminal failure. These are client coordination signals, not trusted Domain
events. An authority-changing operation invalidates on completion; if its outcome is uncertain, invalidate
conservatively rather than retaining a known-old projection. Clear protected server-data caches when credentials
change, authentication ends or authority is invalidated, so a late data response or retained cache cannot expose
another identity's data or retain a revoked view. Cancel/retire affected requests before refetching.

Advance a local context generation on logout, terminal failure or identity-context replacement; advance the
principal request generation on every invalidation, replacement load and credential change. Capture both when
starting a request and apply a result only if both still match. Abort is an optimization, not the safety check.
Old successes **and errors** must not restore authority, overwrite newer data or terminate a newer context.
All protected data responses need the corresponding context/invalidation fence, not just `/me`.

At refresh start, mark principal stale and remove protected presentation. On successful rotation or accepted
volatile peer credential, replace the memory token, invalidate the principal and protected data, and fetch `/me`
for that tab. Never accept a peer's principal projection. An identity change discovered by `/me` retires the old
context and all old requests/data before adopting the newly verified identity. A logout/terminal barrier rejects
unsolicited token broadcasts and in-flight login/refresh results until an explicit new authentication attempt.
A new attempt has its own generation; it cannot inherit an old attempt's success.

Web Locks leader election, validated versioned BroadcastChannel messages, bounded waits, conservative token
scheduling, secretless conflicts and at-most-one coordinated conflict recovery follow ADR 0003. Do not compare
independent tabs' local counters as if they were a shared clock: correlate volatile results to the current
coordination round and fence them locally. Invalidation messages carry only bounded protocol metadata and
invalidation intent; no identity, roles, permissions, form values or principal projection. The sole credential
message exception is ADR 0003's bounded **volatile access-token/expiry handoff**. CSRF proofs and refresh
credentials are never broadcast. Unsupported APIs use ADR 0003's bounded own-refresh fallback, never storage
polling; remote logout may only be learned on the next server interaction.

A current-generation `401` immediately removes local authority. A nonterminal expired-access condition may
enter the single bounded restoration flow permitted by ADR 0003; authority returns only after successful
refresh **and** a fresh `/me`. A terminal rejection or repeated failure requires re-login. No response interceptor
silently repeats a business mutation, and this ADR grants no general automatic request retry. Logout clears
memory immediately, broadcasts invalidation best-effort, and attempts the CSRF-protected server revocation even
if credentials are uncertain. Failed logout reports that server revocation is unconfirmed; local authority stays
cleared. A queued refresh cannot undo logout. Server cookie issuance/deletion and persistent session validity
remain ADR 0003's responsibility, not something JavaScript can assert or repair by reading a cookie.

### Authority-transition matrix

| Trigger / race | Required local outcome | Recovery / observation |
|---|---|---|
| Cold load or reload | Unknown, no token or principal restored from storage/HTML | One coordinated cookie-backed restoration; fresh `/me` before protected UI |
| Login succeeds | New credential/context generation; previous private caches retired; loading | Decode current `/me`; login token alone grants no UI permission |
| `/me` succeeds / is malformed / unavailable | Authenticated only for valid current result inside freshness budget; otherwise no projection | Safe protocol/network error and explicit retry; no stale-while-revalidate authority |
| Freshness expiry or hidden-tab resume | Stale, protected presentation/data removed | Coalesced current-generation refetch on demand, no indefinite timer loop |
| Refresh begins / succeeds | Stale at start; credential replacement invalidates again before refetch | Every participating tab independently loads `/me`; token handoff never supplies permissions |
| Refresh network failure | Refresh-failed, memory access and principal cleared | Explicit bounded retry; distinguish outage from confirmed revocation |
| Refresh conflict / leader loss | Remain non-authoritative during bounded wait | ADR 0003's one recovery, then fail closed; repeated conflict cannot loop |
| Logout during refresh or load | Advance context barrier, clear memory/private data and intended route | Best-effort notification and server revocation; late success cannot reauthenticate |
| Revocation, terminal refresh or terminal `401` | Terminal, credentials/principal/private data cleared | Explicit re-login; server independently rejects stale requests |
| Permission change, operation uncertainty or authority signal | Invalidate principal and protected data; old requests fenced | Fresh `/me`, then re-evaluate current route and controls; denied route becomes forbidden |
| Multiple tabs, missing channel or suspended peer | Each tab has one cache and local fences; no trusted shared snapshot | Best-effort notification, freshness/resume rules and server checks; no instant-sync claim |
| Old `/me` success/error after new load, logout or identity change | Ignore it regardless of AbortController outcome | It cannot overwrite or terminate the newer context |
| Protected navigation while anonymous / forbidden | Preserve only an eligible intended route for login / show forbidden | Restore only after fresh authority and current route requirements allow it |
| Malformed runtime configuration | Safe boot error; no authentication calls or protected mount | Fix deployment configuration; do not infer permissive defaults or dump raw values |

### Narrow state ownership and persistence

| State | Owner / lifetime | Persistence and sharing |
|---|---|---|
| Shareable route, approved filter/sort/page values | URL, decoded by the owning route | Only allowlisted non-sensitive navigation values; never business drafts, arbitrary objects or secrets |
| Form fields, dialog state, pending interaction | Nearest Page/Component; discard on teardown/context end | Volatile only; passwords/credentials never copied into global context, diagnostics or restoration state |
| Server data and current principal | One API cache; authentication-context scoped with invalidation fences | Memory only; no dehydrated HTML snapshot, persisted query cache or cross-tab principal sharing |
| Access token, CSRF proof, refresh coordination | Shared client's authentication coordinator, through narrow capabilities | Memory only; ADR 0003's limited volatile token handoff; HttpOnly cookies inaccessible to JavaScript |
| Application-wide dependencies and derived presentation | Narrow application context exposing owner subscriptions/capabilities | No second principal/token/workflow store or generic mutable global bag |
| Intended protected route | One bounded in-memory navigation intent | No URL `returnTo`, history-state payload, cookie, storage or cross-tab transfer |
| Typed public runtime configuration | Immutable validated boot input | Non-secret deployment configuration, separate from identity and authority |
| Theme choice | Presentation-preference owner; derived effective theme | Only explicit `system`/`light`/`dark` preference may initially use `localStorage` |

Prohibit access tokens, JavaScript-held CSRF proofs, trusted authorization snapshots and business workflow state
in `localStorage`, `sessionStorage`, IndexedDB, Cache Storage, service workers, URL/query/fragment/history state,
base HTML and runtime configuration. Do not opt private responses into browser HTTP caching; `/me` and
credential/CSRF responses require `Cache-Control: no-store`. No offline cache, worker queue, autosaved browser
business draft or persisted development cache is authorized. Sensitive objects must not enter logs, analytics,
error reports or retained devtools snapshots. Ordinary static asset caching is not a private response cache.

The server-controlled HttpOnly refresh/CSRF cookies specified in ADR 0003 are the explicit credential-persistence
exception, not permission for JavaScript persistence. The future invitation/reset email-link journeys in
EPIC-00004 own their narrowly required inbound one-time grant handling and prompt URL/history removal; this ADR
creates no new grant-link transport and never permits those URLs or grant values in navigation restoration.

### Safe intended-route restoration

Keep at most one in-memory intent for **10 minutes**, bounded to **2,048 serialized characters**, containing a
recognized application's canonical pathname and only that route's explicitly allowlisted non-sensitive query
values. Discard fragments initially. Start the deadline when captured; repeated redirects do not renew it.
Reload/tab close loses intent by design. Do not serialize it into the login URL or browser history state.

Parse against the fixed current origin, require a root-relative path matching a registered protected route,
reject external origins/schemes, protocol-relative forms, userinfo, backslashes, controls, malformed encoding
and ambiguous encoded separators. Build the result from the matched route and decoded, validated parameters;
never concatenate or repeatedly decode caller-supplied redirect text. Credentials, invitation/reset paths,
passwords, email/form fields, request bodies and arbitrary state are ineligible. Each future route must classify
its path/query parameters before opting into restoration; default is not eligible.

After login/restoration, wait for fresh `/me`, resolve the route again and evaluate its complete current
requirements. Consume the intent once. Expired, malformed, unknown or no-longer-permitted targets fall back to
the authorized landing route, or an authenticated forbidden state if none is allowed; they never force a
Dashboard grant. Explicit logout discards intent. Involuntary session expiry may retain only an eligible intent
for the bounded login attempt, never form state. Theme changes and peer notifications cannot alter navigation
intent.

### Typed non-secret runtime configuration

Use a small explicit versioned schema, runtime-decoded before authentication transport or protected mounting.
The initial transport contract is `schema_version: 1` and `api_base_path: "/api/v1"`, mapped to an immutable
camelCase client model. These fields describe the existing same-origin topology, not environment-derived
permission policy. Reject unknown fields, wrong types, unsupported versions and a different/absolute API base;
never redirect Bearer/CSRF material to a runtime-selected origin. Future public fields require explicit schema
and use-case changes rather than passing through a server environment object.

Delivery may use a dedicated inert configuration JSON block in base HTML with correct escaping/CSP handling;
that block contains only this allowlisted public configuration. No principal, role, permission, secret, token,
cookie value, signing metadata or private provider configuration belongs in HTML/config/bundle. Route permission
requirements are static presentation metadata in code, not injected user grants. Invalid or missing config
renders a generic accessible boot failure without attempting auth, echoing raw values or falling back to a
different API host. Theme fallback remains safe independently; no dynamic code evaluation is needed.

### Theme preference bootstrap

The only initial application-managed persisted JavaScript state is the explicit theme preference, under one
versioned application-owned `localStorage` key (`fight-agent-os.theme.v1`). Accept exactly `system`, `light` or
`dark`; missing, invalid or inaccessible storage means `system`. Do not persist a default automatically or store
the resolved OS mode. An explicit selection may persist its enum; selecting `system` may store that enum.
Storage denial/quota errors must not prevent boot or an in-memory selection.

A small CSP-compatible pre-render bootstrap reads only that preference, resolves system with
`prefers-color-scheme`, and applies a presentation marker/color-scheme before the first application paint where
possible. Reuse the same validation/resolution rules during React mounting rather than a second theme policy.
In system mode subscribe to OS changes; explicit light/dark ignores them. If the media API is unavailable use
light as the deterministic effective fallback while preserving the `system` preference. Clean up listeners.
Theme-only storage notifications may update sibling tabs after enum validation; they never coordinate auth.
No secret storage scan, hydration of business state or authority request is part of theme bootstrap.

This selects preference semantics and bootstrap responsibility, not colors, tokens, final CSS, selector design
or an accessibility certification. [TASK-00050](../tasks/00050-TASK.md) owns the production accessible selector,
OS/storage behavior and integration with the accepted visual language; the foundation must preserve this seam.

## Alternatives and consequences

- **JWT/HTML/config grants or persisted principal cache:** rejected; stale snapshots and credential exposure
  bypass the sole current-principal source and make logout/revocation misleading.
- **A global user store plus API cache, or component-local authorization:** rejected; duplicate ownership and
  role shortcuts diverge across routes/components. Subscription context is sufficient without another store.
- **Stale-while-revalidate principal rendering:** rejected; ordinary server-data caching convenience is not
  permission authority. Fail-closed invalidation costs a loading transition and occasional extra `/me` request.
- **A broad state framework chosen now:** deferred; implementation may select an API-cache/router library only
  if it can meet this ownership, generation and no-persistence contract without duplicate stores.
- **Persistent return URLs, browser form recovery or storage-based tab election:** rejected; their convenience
  does not justify secret/open-redirect exposure or weakening ADR 0003. Reload can lose navigation intent.
- **Cross-tab principal broadcasts or automatic mutation replay after refresh:** rejected; each tab refetches
  its own authority, and uncertain side effects must not be silently repeated.
- **Persist all presentation preferences immediately:** rejected; only the required theme enum is authorized.
  Future preferences need explicit requirements, not a generic persistence bag.

One cache/evaluator reduces inconsistent UI checks but cannot make client state tamperproof or prevent XSS.
Bounded freshness and immediate invalidation prefer safe interruption over availability during outages.
Cross-tab APIs are best-effort, not a distributed transaction; server authorization remains decisive. Additional
round trips and loss of browser drafts/intended routes on reload are deliberate consequences.

## Enforcement and deferred integration

| Owner | Required implementation evidence |
|---|---|
| [TASK-00026](../tasks/00026-TASK.md) | Runtime schema success/rejection and safe boot failure; no authority in HTML/bundle/config; narrow shell/context boundaries and theme-bootstrap seam |
| [TASK-00027](../tasks/00027-TASK.md) | Typed transport/codec, same-origin credential policy, memory-only CSRF/access seams, safe diagnostics and cancellation; no refresh implementation yet |
| [TASK-00028](../tasks/00028-TASK.md) | Injected-loader transition matrix, freshness/controlled promises, one evaluator for nested routes/components, malformed authority and safe intended-route rejection; no claim of live `/me` |
| [TASK-00046](../tasks/00046-TASK.md), [TASK-00047](../tasks/00047-TASK.md) | Real login/refresh/logout outcomes and safe intent consumption; server cookie/revocation evidence under ADR 0003 |
| [TASK-00048](../tasks/00048-TASK.md) | Generation/round fences, bounded Locks/Channel/fallback, logout races and unsupported-browser behavior; deterministic tests and narrow secure-origin browser smoke |
| [TASK-00117](../tasks/00117-TASK.md), [TASK-00049](../tasks/00049-TASK.md) | Single production `/me` endpoint/service, strict safe no-store View, real cache integration, permission change and stale-response tests; no second authority source |
| [TASK-00050](../tasks/00050-TASK.md), [TASK-00051](../tasks/00051-TASK.md) | Theme invalid/missing/denied storage and OS tracking, production accessible control, permission-aware frame and forbidden states |
| [TICKET-00022](../tickets/00022-TICKET.md) | Integrated HTTPS/security, storage and secret-redaction evidence, critical browser/accessibility journeys; not a substitute for lower-level tests |

Implementers must test owned runtime behavior at its narrowest useful boundary: success, denial, malformed
input, outage, out-of-order completion and cleanup. Verify configuration/tooling/artifact concerns directly,
not with product-suite tests of Markdown or wrappers. This documentation TASK adds no runtime tests or code.
A conflicting browser/library/package contract requires concrete evidence and an approved ADR revision, not a
silent fallback that persists authority or weakens fail-closed behavior. Production UI, `/me`, authentication
journeys, cache library selection, exact tab timeouts and final visual design remain with their named TASKs.

## Acceptance and feedback

The human maintainer explicitly approved the full draft on 2026-09-28, including the 60-second freshness
budget, 10-minute/2,048-character in-memory route intent, and strict initial runtime schema. The maintainer
requested the complete text before approving; no decision revisions were requested. Published under
`planning/adr/` from the human-selected current checkout on `feature/task-00025-client-authority-adr`.
Acceptance binds downstream implementation; it does not assert implemented controls, independent implementation
review, PR publication, merge or deployment. TASK-00025 records verification and subsequent review separately.
