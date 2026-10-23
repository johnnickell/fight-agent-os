# Refresh and current-session logout

TASK-00047 implements `POST /api/v1/auth/refresh` and `POST /api/v1/auth/logout`, plus the current-session
logout action on the real login success screen. TASK-00048 adds bounded reload/multi-tab continuity at
`/app/login`, pending independent Review/QA. TASK-00049 now adds the [sole production `/me` client/cache](CURRENT_PRINCIPAL.md), pending independent Review/QA.
Those slices do not add a session-management UI. TASK-00051 now lifts the sole graph into the
[responsive authenticated frame](APPLICATION_FRAME.md), pending independent Review/QA. Public activation and
future email-confirmation landings do not mount restoration.

## Protected browser contract

Both operations require the complete [browser request profile](BROWSER_AUTH_REQUESTS.md): configured direct
HTTPS/authority, exact Origin, same-origin Fetch Metadata when present, nonce-bound `X-CSRF-Proof`, JSON exactly
`{}`, no query/Authorization/form/override alternatives, and independently committed 20/network and
300/endpoint rolling 900-second admission. Validation and admission run before package dispatch. Rejected
request controls return safe correlated 400/403/405/429 and do not modify cookies or sessions. No permission
or Bearer token grants refresh/logout authority.

Only one exact raw `__Secure-agent_os_refresh` cookie is read. Parsed cookie parameters, lookalike names,
quoted/encoded/non-64-lowercase-hex values and duplicates cannot supply authority. A server cannot observe a
cookie's original Path attribute: the browser withholds wrong-path cookies, and missing authority is terminal.
Issuance/deletion use the fixed host-only `Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict` identity.
No credential, digest, session identifier, user existence detail or internal exception enters response data.
All responses are no-store and pass through the existing correlation boundary.

| Admitted operation/result | Public mapping | Cookies | Client memory |
|---|---|---|---|
| Refresh rotation | 200, existing `SessionAccessSuccess` view | Replace refresh only; preserve CSRF nonce | Replace access, invalidate superseded generation |
| Most-recent used credential within package five-second window | 409, exact generic `Conflict.` | Unchanged | No retained old access; typed recoverable conflict, no automatic retry |
| Refresh missing/malformed/unknown/revoked/expired/non-authoritative authority, or terminal replay | 401, exact generic `Unauthorized.` | Expire exact refresh and CSRF identities | Clear access and proof |
| Logout current/missing/malformed/unknown/used/already-revoked credential | 200, `{"status":"success","data":null}` | Expire both exact identities | Clear immediately; no claim about server existence |
| Package/storage/event failure | 500, exact generic `Internal server error.` | Expire both identities | Clear; logout UI explicitly reports unconfirmed server sign-out |

`AuthenticationService::refresh()` alone owns rotation, used-digest lookup, five-second conflict and replay
revocation; the Action never retries. Rotation preserves session/owner/authentication version/remembered flag
and original absolute deadline. Ordinary idle advances by one day and remembered idle by 30 days, capped at
absolute expiry. Access JSON timing matches the JWT's downward-rounded integer 900-second lifetime. Ordinary
cookies remain browser-session-only; remembered Expires remains at the original one-year absolute deadline,
rounded down, without Max-Age. The refresh credential is only in HttpOnly Set-Cookie, never JSON.

The PostgreSQL refresh repository shares the existing per-user authentication-authority fence with lifecycle
and password changes. At the rotation write boundary it rechecks active owner/current authentication version.
A mutation winning after package snapshot capture therefore rejects with the package terminal exception before
rotation or token issuance; it is not misclassified as a benign credential CAS conflict. Session compare-and-set
still atomically stores the replacement and historical digest. The package continues to own session revocation.
Concurrent refresh gives one 200 and one secretless 409. Logout winning before a captured rotation yields 401;
rotation winning before stale-cookie logout leaves the replacement session untouched (used cookies do not
select it). `CurrentSessionLoggedOut` is observed only after commit. No logout-everywhere is added.

The locked package's `RedactedCommandFailed` retains the original exception message, which may include SQL
and digest-bearing database detail. `AuthenticationEvents`, injected only into the shared authentication
service, replaces **refresh/logout** failure payloads before the normal shared dispatcher/subscribers see them:
fixed operation identity, empty data and fixed safe message. Other package outcome events retain their payloads,
identity/metadata and post-commit timing. The common event pipeline is not replaced. HTTP unexpected-failure
mapping contains no exception object/message. This is a consumer redaction control, not a vendor patch or a
change to package refresh/reuse policy.

## Single-tab client and UI

`SessionService` consumes only the fixed paths through `ApiClient`; it never reads/writes browser cookies.
Explicit refresh coalesces pending callers, clears superseded access immediately and adopts only strict decoded
access into `MemoryAccessToken`. A 409 remains the existing typed `conflict` category. There is no retry,
interceptor restoration, scheduler, channel, lock, principal fabrication or credential persistence.

Logout clears access and advances local generation synchronously. If this same owner has a dispatched refresh,
logout waits for that response's browser cookie to land, fences out its access result, then sends one protected
logout using the browser's **current** cookie, only while its service context is still current. Explicit login
and validated peer invalidation retire the service context, so an older logout waiting on refresh/proof cannot
later select the replacement cookie. Coalescing and completion cleanup are context/operation-fenced: new demand does not reuse a
retired mutation, and its completion cannot detach newer pending work or clear its proof. Aborting fetch cannot undo a committed mutation or
Set-Cookie. Failed bootstrap/transport/server calls retain no access and produce uncertainty, not a
successful-revocation claim. Disposal/pagehide fence late results and clear volatile proof/access. The shared
memory seam also fences late login success after local invalidation. It uses request-start monotonic expiry; empty reads never revive
or change a pending credential generation.

The standalone login-success composition exposes “Sign out of this session.” The production TASK-00051 frame
now provides its current-session “Sign out” control using the same coordinator/service; the older standalone
composition remains a focused test/catalog seam. Busy state suppresses repeat activation;
completion removes the signed-in panel and focuses “Signed out of this page.” Failure uses the same terminal
local state with explicit “Server sign-out unconfirmed” guidance. The fixed return link is `/app/login`, not
caller-supplied navigation authority. No dashboard, principal, account or other-session controls are invented.
The public activation journey retains its existing document-scoped memory seam; this TASK does not mount a
shared authenticated shell around it.

### Bounded multi-tab coordinator (TASK-00048)

`SessionCoordinator` composes the existing `SessionService`, `LoginService` and `MemoryAccessToken`; it stores
credential progress only, never identity/roles/permissions. `SessionContinuity` subscribes directly. Its
`authenticated` state means credential-ready, not an authenticated principal or permission grant. `AuthorityCache` notifications use the existing sole-cache seam; TASK-00049's `LoginJourney` supplies its production
principal loader and terminal-result subscription, now lifted into `useApplicationSession` by TASK-00051.
TICKET-00018 owns real `/me` and authorized navigation; TICKET-00020 owns other-session management.

- States: unknown → restoring → credential-ready (`authenticated`), conflict, network-degraded or terminal;
  explicit login may cross a terminal/logout barrier. Logout clears memory immediately, then reports anonymous
  completion or unconfirmed server sign-out. No protected operation may use a missing/expired token.
- Cold `/app/login` performs one controlled refresh (or accepts a response to its fresh peer challenge).
  Network/protocol/service failure clears access and requires explicit retry. Exhausted conflict or established-
  session 401 ends the context and broadcasts terminal. TASK-00051's production graph opts into cold-anonymous
  401 presentation before any credentials have been established: fence work, keep the barrier, offer Login and
  retain only the classified intended path, without broadcasting a fabricated terminal principal. No interceptor
  or automatic business-mutation replay is introduced.
- Secure-origin Web Locks use fixed `fight-agent-os.session-mutation.v1`, exclusively for refresh/logout.
  Acquisition aborts after 2 seconds, with at most one successor election for refresh. A dispatched refresh has
  an 8-second observation bound. A conflict waits 250 ms for a winner, then permits exactly one 8-second retry;
  any failed recovery is terminal. `try/finally`/callback return release locks. A 50 ms discovery wait and,
  with Locks+Channel, another 50 ms acquired-lock delivery grace permit a winner's posted result to arrive.
  Maximum local refresh flow is 20.35 seconds (including two acquisition windows); held work is at most
  16.30 seconds. Logout acquisition is 2 seconds and observation is 8 seconds. At lock entry, logout
  rechecks its captured local generation before calling the service or clearing credentials; a retired queued
  logout cannot select a newer login's cookie. The service also rechecks its own context after waiting for an
  earlier refresh or CSRF proof, immediately before dispatch. A later explicit login retires that context even
  after logout observation has timed out; the old continuation cannot send an undispatched logout against it.
  Without a context replacement, same-context refresh-before-logout may still finish after an observation
  timeout. The displayed uncertainty remains honest; timeout alone is not revocation or cancellation of a
  dispatched server effect. No failure starts a timer loop.
- BroadcastChannel `fight-agent-os.session.v1` accepts exact version-1 envelopes: `version`, random 32-hex
  `sender`, positive safe-integer `sequence`, and `kind`. `request` adds random 32-hex `round`; `token` adds
  that round, a bounded compact access token (≤2,048 characters), and `remaining` (0 < ms ≤840,000).
  `invalidate`, `authority-changed`, `logout`, `terminal` add nothing. TASK-00049's `authority-changed` means
  refetch each tab's own `/me` with usable credentials, not replace credentials or trust a peer projection. Extra fields/versions/malformed values are rejected.
  At most 64 senders and 64 outstanding challenges are retained. Per-sender sequences reject duplicates;
  independent tab counters are never compared. At sender capacity, unknown senders cannot supply credentials
  or progress, but valid invalidate/logout/terminal signals still retire authority and pending peer challenges
  without adding bookkeeping. Such untracked safety signals may repeat conservatively; saturation never makes
  later tabs' authority invalidation or logout invisible. Repeated invalidations do not renew flow deadlines.
  Only the current local challenge can accept a token. An invalidation replaces any pending challenge and
  its elapsed-time origin, rejecting earlier replies even from a different sender. This does not restart the
  restoration flow, renew its mutation deadlines or grant another election/conflict recovery.
- Access lifetime is conservatively request-start +840 seconds (validated `exp - iat =900`, minus60 seconds),
  not browser wall time. Peer hints subtract full local challenge elapsed time and cannot extend that maximum.
  Backwards/nonfinite monotonic reads retire access. Early expiry coordinates a new bounded refresh. Under
  TASK-00505's authorized ADR amendment, hidden time pauses routine timers; return checks the remaining budget,
  retains usable memory credentials and coalesces background `/me`, or restores once if expired/discontinuous.
  Visibility alone never extends the token budget. Errors never retain dispatch authority and require explicit
  recovery; last-verified same-context layout may remain with unavailable status. Terminal/logout/disposal
  remove private presentation and fence old login/refresh/network work.
- Unsupported or denied Channel uses own refresh under Locks; missing Locks permits independent-tab races.
  Missing both still permits only one conflict recovery. No storage polling, readable cookies, persistent queue,
  URL or HTML credential state is introduced. Without Channel, remote logout is learned on the next server
  interaction/resume, not immediately. Cross-tab messaging is best-effort, not distributed consensus.
- Mutation timeouts cannot undo a server commit or late browser Set-Cookie. Local generations reject the access
  result; logout waits for a same-owner dispatched refresh through `SessionService`. A timed-out/failed server
  observation is not proof of revocation. Reload may still find a server session after uncertain logout;
  cookies/server state remain server-owned. Do not claim atomic logout across crashed/suspended/unsupported tabs.
- Logout and terminal session end now clear optional navigation intent through the existing owner callback.
  Only the explicit production cold-anonymous rejection retains the bounded classified route for Login. No form/credential/private data enters that callback or
  channel. Every fresh principal must still come from `/me`; a peer token never supplies permissions.

The mounted journey disposes channels, timers, subscriptions and volatile services on teardown/pagehide. It
removes secret form DOM on context end. Cold loading, terminal expiry and signed-out states have atomic status
announcements and focused completion headings. Routine background checks keep the admitted outlet/frame mounted,
with unobtrusive status, paused protected actions and no unsolicited focus/scroll/navigation change. Failure
provides explicit bounded retry without implying logout or confirmed revocation. Reload/tab discard/process loss
still loses volatile page state; no browser persistence or automatic business-mutation replay is supplied. Existing semantic themes/reduced-motion
styles apply; actual assistive-technology and broad browser certification remain outside this local evidence.

## Verification and safe reproduction

The Composer lock remains Fight Access Control **0.6.0**, source
`dc4621d13851dd08cdf85ed9715ead24b1051e80`, and Common **1.3.0**, source
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. The installed public APIs, not version-label guesses, supplied
`RefreshResult`, `RefreshOutcome`, the refresh/logout services and exact cookie/session policy.

Use the [explicit isolated setup](BUILD.md), [guarded backend lifecycle](BACKEND_TESTS.md) and
[local TLS contract](LOCAL_HTTPS.md). Never reset another checkout's test database or replace its ports/keys.
The ignored `.runs/tasks/TASK-00047/runs/engineer-handoff.md` records this checkout's actual setup, private
fixture locator, fresh commands, counts, warnings, full-gate identity and safe browser capture receipts.
Runtime configuration, certificates, assets and private fixture state live under ignored `var/`/`.env`, not
under execution evidence. No global CA installation/change is required by this task-local setup.

`SessionLifecycleApiTest` exercises actual registered HTTP/package/PostgreSQL rotation, near-boundary ordinary
and remembered persistence, JWT timing, precise conflict/replay, terminal states, scoped cookies, admission
limits/outage, request security, rollback, redaction and four independently connected deterministic race orders.
`RefreshRaceWorker` uses bounded pipe barriers after authority capture, never scheduling sleeps; no secret is
written to its output. Existing repository/authentication/login suites remain required. Client service/component
checks exercise strict outcomes, no retry/persistence, superseded generations, refresh/logout ordering,
uncertainty, focus and busy suppression. Production-backed Session stories add light/dark, pending, ended and
uncertain states. Live browser probes separately check HttpOnly/persistence/path behavior and the real logout
journey; synthetic catalog outcomes are not real server-failure evidence. `./bin/build` remains the complete gate.

Browser captures never include filled passwords, refresh cookies, tokens, private identities or diagnostic
panels. Capture blank forms, cleared signed-in/current-session controls, and safe signed-out/uncertain states.
Screenshots and automated axe checks do not certify physical assistive technology, every browser, production
ingress/header preservation or universal WCAG conformance. Publication, independent Review/QA and merge are
separate responsibilities.
