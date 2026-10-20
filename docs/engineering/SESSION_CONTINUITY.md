# Refresh and current-session logout

TASK-00047 implements `POST /api/v1/auth/refresh` and `POST /api/v1/auth/logout`, plus the current-session
logout action on the real login success screen. Independent Review/QA remain separate. This is not a `/me`
client, authenticated product frame, session-management UI, reload restoration or multi-tab coordinator.

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
logout using the browser's **current** cookie. Aborting fetch cannot undo a committed mutation or Set-Cookie.
Failed bootstrap/transport/server calls retain no access and produce uncertainty, not a successful-revocation
claim. Disposal/pagehide fence late results and clear volatile proof/access. The shared memory seam also fences
late login success after local invalidation. It uses request-start monotonic expiry; empty reads never revive
or change a pending credential generation.

The real `/app/login` success panel exposes “Sign out of this session.” Busy state suppresses repeat activation;
completion removes the signed-in panel and focuses “Signed out of this page.” Failure uses the same terminal
local state with explicit “Server sign-out unconfirmed” guidance. The fixed return link is `/app/login`, not
caller-supplied navigation authority. No dashboard, principal, account or other-session controls are invented.
The public activation journey retains its existing document-scoped memory seam; this TASK does not mount a
shared authenticated shell around it.

TASK-00048 owns bounded conflict recovery, reload restoration, conservative scheduling/wake behavior,
Web Locks/BroadcastChannel and sibling-tab invalidation. Other tabs are not claimed to clear immediately here.
TICKET-00018 owns fresh principal/navigation integration; TICKET-00020 owns other-session management.

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
