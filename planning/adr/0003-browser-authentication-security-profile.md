# ADR 0003: Define the browser-authentication security profile

- **Status:** Accepted
- **Date:** 2026-09-25
- **Decision owners:** Fight Agent OS maintainers
- **Acceptance:** Explicitly approved by the human maintainer under [TASK-00017](../tasks/00017-TASK.md) on 2026-09-25

## Context and threats

The private same-origin browser client needs short-lived API authority that survives neither reload nor logout, and
bounded cookie-backed continuity across reloads and tabs. A stolen Bearer token, refresh replay, cookie-driven CSRF,
origin confusion, login guessing, tab races, stale identity, and leaked configuration must not silently create or
prolong authority. The token is not an authorization or permission snapshot.

This specializes [WF-005](../wayfinder/tickets/WF-005-design-authentication-and-authorization-journeys.md),
[WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md),
[TICKET-00010](../tickets/00010-TICKET.md), [TICKET-00017](../tickets/00017-TICKET.md),
[ADR 0001](0001-application-ownership-and-orchestration.md) and
[ADR 0002](0002-postgresql-consistency-and-durable-effects.md). The installed Fight Access Control
`AuthenticationService` supplies package-owned `login`, `refresh`, `logout`, `TokenSet`, refresh-session state and
`LoginThrottle` port. It supplies `sub`, `sid`, `type`, `auth_version`, `iat` to `TokenEncoder` and passes `exp`
separately. Its `starterDefaults` use 15-minute access, **15-day/30-day remembered refresh**, not the accepted
30-day idle/one-year absolute remembered policy; configure `AuthenticationTokenPolicy` explicitly instead.

The installed Fight Common `JwtDecoder` verifies only `SignedWith` using one HS key: its returned claim array is
**not authenticated application authority**. It does not validate issuer, audience, timestamps, token type,
subject, session, JTI or authentication version, select bounded `kid` overlap, or sanitize its exception chain.
`JwtEncoder` can populate registered claims, but does not itself impose our issuance profile. Neither a decoder
success nor the package `LoginThrottle` interface proves a production control. Do not patch `vendor/`.

## Decision

### Access issuance, consumption and authoritative resolution

Use one pinned **HS256** JWT profile in v1. An Agent OS `TokenEncoder` adapter enriches, but does not change, the
package-supplied `sub`, `sid`, `type`, `auth_version`, `iat` or `exp`. It supplies configured `iss`, a single
configured `aud`, and a cryptographically random unique `jti` (at least 128 bits); `nbf = iat`. Sign with the
active key and a nonempty `kid` protected header. Issue `exp = iat + 900 seconds`, with integer UTC-second times;
reject inconsistent package expiry rather than silently changing it. No roles, permissions, email, password,
refresh material, private state or key metadata beyond `kid` belong in claims. JTI is an identification/correlation
value, **not** a server-side token-revocation list; never log the raw token or a full claims dump.

The application consumer independently parses and validates the protected header, signature and the complete
claim profile before resolving any principal. Accept exactly one Bearer token from a single `Authorization` header;
reject repeated/folded headers, comma lists, wrong scheme, control characters, leading/trailing ambiguity, extra
credentials, oversized tokens and token material in URL/body/cookie. Set a small bounded token/header size in
TASK-00044; never take a key or algorithm from a token without checking configured allowlists. Reject `none`,
non-HS256, unknown/expired `kid`, unexpected critical/header fields, malformed encodings, duplicate JSON members,
non-canonical claim types and unknown claims. Use a parser capable of detecting duplicate JSON keys; if the chosen
library cannot, add a safe bounded parser or stop, rather than accepting ambiguous authority. Do not treat the
current Fight Common decoder's claim array as sufficient validation.

| Claim | Required issuer/consumer contract; reject absence, duplicates and wrong type |
|---|---|
| `iss`, `aud` | Exact nonempty configured issuer string; `aud` is exactly a one-element string array containing the configured audience, not substring or another service |
| `sub`, `sid` | Canonical nonempty package UserId and RefreshSessionId strings respectively; parse through owning ID factories; never accept arbitrary strings, arrays or another identifier kind |
| `jti` | Unique unpredictable 128-bit-or-more value in a canonical configured encoding; enforce length/format, without using it as permission or session authority |
| `type` | Exact string `access`, not a refresh or other token type |
| `auth_version` | Strict positive integer (not numeric string, float or boolean), equal to both persisted user and session versions |
| `iat`, `nbf`, `exp` | Strict integer UTC seconds; `nbf = iat`, `exp = iat + 900`; reject future `iat`/`nbf` beyond 30 seconds; do not accept at/after `exp`, even within clock skew; reject expired, zero, overflow and implausible past issuance |

Only 30 seconds of **future-clock tolerance** applies to `iat`/`nbf`; it never extends `exp` or the session's idle
or absolute deadline. Require `iat <= now + 30`, `nbf <= now + 30`, `iat > now - 900` where expiry is still in the
future, and a valid sequence `iat = nbf < exp`. Clocks must be UTC-synchronized; a grossly wrong server clock is
an operational failure, not grounds to broaden skew. Require an exact recognized claim set (not unknown
security-bearing fields). Parse errors and missing/wrong claims all produce the same sanitized correlated `401`.

The browser uses validated-format but **untrusted** `iat` and `exp` only to schedule an early refresh, not to
validate authority or synchronize its wall clock. This avoids assuming the browser's `Date.now()` matches the
issuer. Capture a monotonic timestamp at the start of the successful login/refresh request, then at response
receipt schedule no later than
`(exp - iat) - elapsed_request - 60 seconds` from receipt (clamp at zero and refresh immediately when no safe
budget remains). The initial request start predates token issuance, so subtracting the full request duration is
conservative even with network latency. Recompute the remaining budget from the monotonic clock rather than a
wall-clock offset; on tab wake/clock discontinuity, missing metadata, failed parsing, or `401`, fail closed and
coordinate one server refresh instead of extending authority. Broadcasted volatile tokens carry only a bounded
remaining-time hint from the leader, never an issuer-clock offset; recipients further bound that hint, reject
stale generations and must not treat it as server authority. The server alone validates expiry on every request.
TASK-00048 owns deterministic skew, delay, wake and cross-tab scheduling tests; TASK-00046/00047 supply safe
`iat`/`exp` response metadata or a validated-format access-token timing seam without persisting/authorizing from
client-parsed claims.

After cryptographic and claim checks, load the session by `sid` and user by `sub` from authoritative PostgreSQL
**for each authenticated request**. Require exact owner match, active user, unrevoked session, current idle and
absolute deadlines, matching user/session/token authentication versions, and a valid session at the current time.
Only then establish request-scoped principal context. Role/permission authorization remains a separate server-side
check against authoritative state; JWT claims never grant permissions. Fail closed on unavailable authority store.

Signing secrets, active/previous `kid`, accepted issuer/audience and rotation cutoff are externally configured,
never committed, embedded in HTML, returned to clients, dumped or logged. Reject malformed/non-hex secrets, weak
(<32 decoded random bytes) keys, duplicate/unknown IDs, unconfigured algorithms, missing active key, unsafe
issuer/audience or invalid overlap **at production boot**; do not auto-generate production keys. Use distinct
keyrings per environment. A key switch starts issuing only with the new key after all consumers can verify it.
Retain the prior key for verification only until its last possible issuance plus 900 seconds; never sign with it
after the switch or accept its tokens after the configured cutoff even if a token claims a later `exp`. No
indefinite fallback, remote `jku`/`jwk` resolution, or client-selected key. A key compromise requires emergency
key disablement, accepting immediate logout/re-authentication rather than waiting for overlap.

### Refresh credential and cookie lifecycle

Use package-generated opaque 256-bit refresh credentials only as cookie values; store only package one-way digests
at rest. A host-only `__Secure-`-prefixed cookie (no `Domain`) has `Path=/api/v1/auth`, `Secure`, `HttpOnly`,
`SameSite=Strict` and never appears in JSON, JavaScript, URLs, analytics, audit or logs. Do not use `__Host-`
with a non-root path; if routing changes, update the scope, issuance and deletion together. Require production
HTTPS with explicitly trusted proxy headers/hosts; do not enable generic credentialed CORS or cross-origin auth.
Ordinary refresh sessions expire after **one day idle / two days absolute**, with a browser-session cookie (no
`Expires`/`Max-Age`). Remembered sessions expire after **30 days idle / one year absolute**; their persistent
cookie expiry is no later than the server absolute deadline and is updated only on successful rotation. Server
idle/absolute deadlines, not browser cookie persistence, are authoritative. Rotate credential on each valid
refresh, advance idle no later than absolute expiry, and return a 15-minute access token only through the approved
access-token response field; hold it only in volatile client memory. Set `Cache-Control: no-store` on all
credential/CSRF responses. On logout/terminal failure expire the same cookie name, host-only domain and path with
matching security attributes; explicitly expire any known legacy variants during a separately planned transition,
not by guessing or broadly deleting other cookies.

### Browser mutation request controls and CSRF lifecycle

Treat login, refresh and logout alike as ambient-cookie-sensitive `POST /api/v1/auth/*` mutations. Accept only
same-origin HTTPS requests with an **exact configured trusted `Origin`** (scheme, host, effective port); reject
missing, `null`, malformed, userinfo, suffix/wildcard, ambiguous or foreign origins, including from clients that
omit Origin. This is a deliberate browser-only contract; non-browser clients must use a separately approved
profile. `Sec-Fetch-Site` when present must be `same-origin` (reject `same-site`, `cross-site`, `none`, invalid or
contradictory values); absent Fetch Metadata is allowed only if Origin **and** CSRF checks succeed. Reject
cross-site preflights, and keep CORS deny-by-default. Never infer HTTPS or origin from untrusted forwarded headers.

Use a separate, non-authoritative double-submit CSRF protocol: an idempotent same-origin GET bootstrap endpoint
sets/reuses a high-entropy (at least 256-bit), host-only `__Secure-` CSRF nonce cookie, `Path=/api/v1/auth`,
`Secure`, `HttpOnly`, `SameSite=Strict`, browser-session only. It returns a short-lived (at most 15 minutes)
MAC-authenticated CSRF proof bound to that nonce, the configured origin, and expiry in a `no-store` JSON response.
Use an independent externally configured 256-bit-or-stronger CSRF MAC key, separate from signing keys; validate
encoding/strength at boot. The client holds the proof only in memory and sends it in one explicit CSRF header on
every mutation. Verify exact cookie/proof match with constant-time MAC comparison and deadline before dispatch;
reject duplicates, missing/malformed/expired proofs and alternative body/query tokens. The proof is intentionally
reusable within its short window; it is **not** a one-time authentication credential. Bootstrap may issue a new
proof for the same nonce without invalidating another tab's unexpired proof. On successful login or terminal
logout rotate/delete the nonce; other tabs must bootstrap anew. Do not rotate it on every refresh (which would
invalidate in-flight tabs); when the proof expires, re-bootstrap through the same-origin endpoint. Logout must
clear memory and attempt server revocation even if credential validity is uncertain; a missing/invalid CSRF proof
must never let an unauthenticated client revoke by cookie alone. A stolen CSRF proof without the corresponding
HttpOnly nonce and same-origin execution cannot authorize a mutation; XSS remains outside this defense.

Require `Content-Type: application/json` (optionally `charset=utf-8` only) on mutations, a bounded UTF-8 JSON
object and one explicit shape per endpoint: login requires canonical email, password, boolean remember; refresh
and logout require exactly `{}`. Reject empty bodies, arrays, unexpected fields, duplicate keys, form, multipart,
text, method overrides and oversized input **before** invoking package behavior. Cookie/CSRF credentials must
never be accepted from JSON, URL, authorization header or form. Responses use sanitized JSend error categories
and safe correlation IDs, not secrets or existence details. Bootstrap itself is read-only, never authenticates or
rotates refresh credentials, and must also require exact Origin-independent same-origin Fetch Metadata/HTTPS,
no-store and no credentialed CORS; mutation requests always require Origin and CSRF regardless of bootstrap.

A real, atomic, persistent production `LoginThrottle` must implement the package port: each `allows()` call
**consumes** a bounded attempt for canonical email, irrespective of identity or eventual result, using an HMAC
of canonical email with a separately configured secret rather than raw email as a storage key. The attempt must
commit independently of the login business transaction so rejected credentials cannot roll back the counter.
The initial policy is at most five calls per email key per rolling 15 minutes; keep retention bounded and test
concurrency with distinct PostgreSQL connections. Layer a bounded, atomic network/endpoint limiter for public
login plus refresh/logout to prevent changing guessed email keys or hammering sessions; use a trusted network
identity, not unchecked forwarding headers. Throttle/limiter storage outage denies attempts, never fails open.
The package interface alone, an in-memory counter or a counter in the rolled-back login transaction is not a
production control. Generic responses and bounded `Retry-After` avoid account enumeration; monitoring stores only
safe aggregates. TASK-00045 may refine concrete network thresholds but must not omit the control.

### Conflict, reuse, logout and tab coordination

Use package refresh behavior, not a second consumer-owned rotation policy. Exactly one competing refresh wins a
compare-and-set; its previously current credential is accepted as a **secretless** `RefreshResult::conflict()`
only if it is the most recently used credential inside a configured **5-second** conflict window. Never return
another tab's new credential or server-retry the same used cookie. Older/out-of-window used credentials trigger
package session-family revocation; unknown, malformed, expired, already revoked, wrong-user, inactive or
version-mismatched authority fails terminally. On conflict, the client waits for an already elected winner's
volatile result; if none arrives, it makes **at most one** coordinated retry using only the browser's current
cookie after a bounded wait, never by replaying the stale credential from JavaScript. If it conflicts again or
fails, clear memory, expire cookie where possible and transition to anonymous/terminal re-login. No unbounded
interceptor loop. Server never labels a suspected reuse as recoverable; avoid exposing whether a session existed.

Current-session logout revokes the session selected by the current refresh cookie (not all user sessions), expires
that cookie and invalidates local memory even when revocation cannot be confirmed. Invalid/used-cookie logout
never revokes a different session. Broadcast a local invalidation; a failed server call leaves the client
non-authoritative and reports uncertainty rather than claiming server revocation. Password reset/change,
activation authority changes, email change, administrative revocation, inactivity and expiry invalidate through
server-side version/lifecycle/session checks, not just client notification. A 401 or terminal refresh ends local
authority; the client must not silently reauthenticate with stale results. No logout-everywhere is introduced.

On secure origins with Web Locks, one tab obtains an exclusive origin-scoped refresh lock, rechecks its local
memory/generation after acquisition and makes one refresh attempt. BroadcastChannel carries small versioned,
validated, origin-local, bounded **volatile** token/expiry or invalidation signals (never refresh cookie, CSRF
proof, passwords, form state, trusted roles/permissions or private user data); close channel/listeners on teardown.
Use monotonic local generations to prevent delayed refresh/login results restoring authority after logout or
terminal failure. Refresh scheduling follows the conservative `iat`/`exp` request-duration rule above, not the
browser wall clock. Followers wait a bounded interval and recheck; abort waiting/release on timeout, crash or tab
close, then one newly elected leader may try. Without Web Locks, tabs may race; only the package conflict window
and at-most-one coordinated recovery protect them. Without BroadcastChannel, tabs must obtain authority with a
bounded own refresh and may learn remote logout only on next server request; no claim of instant cross-tab sync.
Without either API, attempt one refresh and at most one conflict recovery, then fail closed. No timer/retry
loop or browser-persistent credential/cache/channel queue is authorized; `localStorage`, `sessionStorage`,
IndexedDB, Cache Storage, service workers and history never store access/refresh authority. These fallbacks
cannot guarantee a single rotation or immediate remote logout; they must never treat stale client state as
server authority.

## Threat and outcome matrix

| Threat / race | Required outcome and primary enforcement |
|---|---|
| Stolen Bearer token | Memory-only exposure limits lifetime; signature/claims + 15-minute hard expiry + per-request user/session/version checks reject stale authority; transport over HTTPS, no token logging |
| Stolen refresh cookie, replay or fixation | Opaque HttpOnly/Strict scoped cookie, digest-only storage, atomic package rotation, most-recent 5-second secretless conflict, older replay revokes family; login never accepts caller-supplied session/refresh credential |
| Cross-site POST, login CSRF, origin confusion | Exact HTTPS Origin + CSRF proof tied to HttpOnly nonce + Fetch Metadata + strict JSON and SameSite; reject before package dispatch |
| Refresh races or tab crash | One lock leader where available, bounded wait, secretless conflict and one recovery; terminal fail-closed on repeats; stale generation cannot restore logout |
| Logout and password/version invalidation | Revoke selected server session or all sessions when owning package operation mandates; clear local authority and cookie, notify tabs best-effort; re-resolve on every API call |
| Guessing, account enumeration, limiter outage | Independently committed atomic email HMAC throttle plus endpoint/network limits, dummy verification and generic errors; outage denies, never bypasses |
| Key compromise or invalid configuration | External strong keyring, production startup guard, bounded old-key overlap and emergency disable; no key in responses/logs |

## Alternatives considered

- **Use Fight Common `JwtDecoder` claims directly.** Rejected: `SignedWith` alone is insufficient for registered
  and package-specific claims, authoritative session checks and rotation; its exception may contain raw detail.
- **Store access tokens or refresh credentials in browser storage; put roles in JWT.** Rejected: persistence widens
  theft/replay and role snapshots become stale; `/api/v1/me` and server policy own permission truth.
- **Use only SameSite, Origin or CSRF alone.** Rejected: no one control covers all browser and proxy failures.
  Enforce the combined browser-only request profile; do not enable cross-origin credentialed calls to simplify it.
- **Rotate the CSRF cookie on every refresh or make proof single-use.** Rejected: concurrent tabs would invalidate
  each other; a bounded nonce-bound signed reusable proof plus rotation at login/logout is safer here.
- **Return the winner's refresh credential on a conflict or retry server-side.** Rejected: leaks a new secret to a
  stale presenter and obscures suspected replay. The package conflict carries no TokenSet.
- **Trust in-memory throttling or the package port declaration.** Rejected: multi-process restarts and rollback
  bypass limits; production needs durable atomic committed attempt state.
- **Use asymmetric JWTs or generic remote key discovery now.** Deferred: one local trusted issuer/consumer
  deployment has no demonstrated distributed-key need; migration would require a new accepted profile.

## Consequences, enforcement and deferred work

This profile deliberately requires per-request PostgreSQL authority resolution, externally managed symmetric and
CSRF secrets, an explicit bootstrap round trip, and browser-only exact-Origin requests. It trades a small
short-lived CSRF proof in volatile memory and bounded cross-tab best-effort notifications for no persistent browser
credentials. A browser with malicious same-origin script remains compromised; this ADR is not an XSS defense.
No key-management infrastructure, global CORS, authentication endpoints, or production code is delivered by this
ADR. Exact network limiter thresholds, cookie deployment hostname, JSON byte limits and client timeout values
are bounded implementation choices for their named TASKs; they must be fixed and tested before production use.

| Owner | Required evidence before claiming the profile is enforced |
|---|---|
| [TASK-00018](../tasks/00018-TASK.md) | Real `GET /api/v1/auth/csrf` bootstrap, transport-free query/verifier, nonce cookie, short-lived MAC proof and same-origin/no-store evidence; no claim that issuance alone protects mutations |
| [TASK-00021](../tasks/00021-TASK.md) | Document the implemented bootstrap plus future Bearer vs scoped cookie/CSRF semantics in OpenAPI without claiming JWT/login already work |
| [TASK-00044](../tasks/00044-TASK.md) | Claim/header/key/time matrix, rotation cutoff and authoritative request-resolution security/HTTP/PostgreSQL tests; reject unsupported duplicate-key parsing rather than guessing |
| [TASK-00045](../tasks/00045-TASK.md) | Apply TASK-00018's CSRF verifier and cookie/Origin/Fetch/JSON/HTTPS guards before login/refresh/logout, with limiter/rollback/concurrency and redaction tests; production limiter must exist |
| [TASK-00117](../tasks/00117-TASK.md) | JWT-by-default FQCN Action/permission metadata guard and real authenticated `/me` query, without weakening credential-flow protections |
| [TASK-00046](../tasks/00046-TASK.md) | Both lifetime policies, generic login failure, token/cookie response and intended-route tests |
| [TASK-00047](../tasks/00047-TASK.md) | Atomic refresh/replay/conflict/current-logout race, cookie expiry and terminal outcome tests |
| [TASK-00048](../tasks/00048-TASK.md) | Client tab-generation, conservative `iat`/`exp` skew/delay/wake scheduling, lock/channel timeout/fallback, storage scans and real secure-origin browser smoke |
| [TICKET-00022](../tickets/00022-TICKET.md) | Integrated production-like HTTPS, authorization, redaction and critical-browser verification |

A later deployment task owns actual key provisioning, safe rotation operation, HTTPS/proxy enrollment and
incident response. Do not infer deployment readiness from this decision. Implementing TASKs must follow this
accepted profile without reopening settled choices merely for convenience. If a package, browser or provider
contract makes a required control infeasible, stop, show the concrete conflict and seek a separately approved
revision; do not silently weaken security or call a partial implementation complete. Authentication implementation may follow this accepted profile; independent implementation review, PR merge and
release are separate states.

## Evidence inspected

- The planning decisions, TICKETs and TASKs linked above; [ADR 0001](0001-application-ownership-and-orchestration.md)
  and [ADR 0002](0002-postgresql-consistency-and-durable-effects.md).
- Locked Fight Access Control `AuthenticationService`, `AuthenticationTokenPolicy`, `LoginThrottle`,
  `RefreshResult`, `RefreshSession` and `RefreshCredential` in
  `vendor/johnnickell/fight-access-control/src/`, with package-owned refresh conflict and replay behavior.
- Locked Fight Common `JwtEncoder`/`JwtDecoder` in `vendor/johnnickell/fight-common/src/Adapter/Auth/Security/`:
  `JwtDecoder` configures only `SignedWith`; its exception wraps parser/validator details.

## Acceptance

The human maintainer explicitly approved this ADR on 2026-09-25 after reviewing the full proposal, requesting
conservative `iat`/`exp` browser scheduling and removal of references to the outside example. Approval settles
this profile as a requirement for the implementing TASKs; it does not guarantee bug-free implementation, assert
that controls are already in production, or waive evidence, independent review or the stop-and-escalate rule.
