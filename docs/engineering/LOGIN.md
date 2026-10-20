# Canonical browser login

TASK-00046 supplies `POST /api/v1/auth/login` and `/app/login`, pending independent Review/QA.
This is existing-password authentication, not registration, refresh, logout, a `/me` client or a dashboard.

## Server contract

The exact JSON object is `{email: string, password: string, remember: boolean}`. All three fields are
required; booleans are never coerced. Email must be a valid canonical lowercase address, at most 254 bytes.
Password is 1–128 Unicode codepoints, with its original bytes preserved (including spaces); login does not
apply the new-password composition/denylist policy. Unknown fields, actor/role/permission input, wrong types,
missing fields, duplicate keys, alternate sources and bodies over 65,536 bytes fail before dispatch.

`LoginAction` consumes only `JsonBody`'s checked map and calls the shared package
`AuthenticationService::login()` directly. No command serializes a password and no consumer-owned login
policy or persistence is added. The package owns real/dummy hash verification, rehash, authority revision,
atomic session creation, token issuance and post-commit `UserLoggedIn`. Concurrent captured logins use the
existing conditional authority write: one wins, a stale snapshot is rejected; sequential logins can each
create a session. Lifecycle changes winning before commit reject a captured login. A later lifecycle/version
change still invalidates access through the existing authoritative request boundary.

The [browser request profile](BROWSER_AUTH_REQUESTS.md) is mandatory before mutation: direct configured HTTPS,
exact Origin/authority, same-origin Fetch Metadata where supplied, JSON, nonce-bound CSRF, independently
committed network/endpoint admission (20/300 per rolling 900 seconds), and package email `LoginThrottle`
(five attempts per rolling 900 seconds). The guard checks raw server `HTTP_HOST` independently when available:
Slim PSR-7 1.8.0 otherwise drops a nondefault port while constructing its Host header. Only a configured raw
authority plus the independently configured URI can qualify that stripped representation. Mismatched, absent
or ambiguous authority is not inferred from a port or forwarding header. Production ingress preservation and
physical duplicate-header rejection still require deployment qualification.

Nonexistent, pending, disabled, deleted, wrong-password and email-throttled attempts all return **401**, exact
JSend `{"status":"error","message":"Unauthorized."}`, no cookies and no identity-specific retry header.
They all traverse package password verification, using the configured Argon2id dummy hash for unavailable or
throttled identities. Network/endpoint admission and storage outage return generic **429** with bounded
1–900-second Retry-After independently of identity. Unexpected infrastructure/event failures remain sanitized
500 and must not be assumed rolled back after a post-commit event failure. There is no automatic replay.

Success reuses `SessionResponder`/`SessionAccessData`: only `access_token`, `issued_at`, `expires_at`, with
`expires_at - issued_at = 900` and integer timing no later than JWT expiry. Refresh is never in JSON.
Both session policies were already explicitly configured by activation and remain unchanged:

| Remember | Access | Idle / absolute | Refresh cookie |
|---|---|---|---|
| false | 15 minutes | one day / two days | Browser-session; no Expires/Max-Age |
| true | 15 minutes | 30 days / one calendar year | Expires rounded down to absolute deadline; no Max-Age |

Refresh cookie identity is fixed: `__Secure-agent_os_refresh`, host-only, `Path=/api/v1/auth`, Secure,
HttpOnly, SameSite=Strict. Success rotates the CSRF nonce; generic rejection changes neither cookie.
The package conflict window remains five seconds, not a new refresh endpoint.

## Form, memory and navigation

The named **`login`** registration exports only explicitly allowlisted email/password Required, Type[string]
and codepoint length constraints. Email format/canonicalization, byte bound, credential/lifecycle policy and
strict remember boolean remain server-owned. Anonymous schema reads must succeed before the form is enabled;
missing/malformed schemas show an explicit retry state. Run `./bin/validations export` and
`./bin/openapi export` after source changes; freshness checks never repair artifacts.

The real form uses `usePublicForm`, with multiple approved plain-text field messages, edit/dependency/form/
submission generations and first-current-invalid focus. A→B→A, delayed errors and unmount cannot restore stale
feedback. Unchanged neighboring errors survive. Generic credential outcomes remain form-level. A completed
server login is not suppressed by editing fields while it is pending. Remember is a separately controlled
unchecked boolean, disabled during submission, not a string validation field or persisted preference.

`LoginService` sends one protected mutation at a time, with no replay. It canonicalizes ASCII email case only,
never trims/transforms passwords, and adopts access directly into `MemoryAccessToken`, not React page state.
The shared strict session codec also serves activation. CSRF proof and schema caches are volatile. Pagehide
synchronously retires the form and clears memory before a document can freeze; disposal fences late success.
No credential, form, principal or intended route is persisted in storage, cookies readable by JavaScript,
URLs, analytics, diagnostics or history. The blocking head script discards all login query/fragment/history
state before theme storage or form boot. `returnTo` and arbitrary caller state are never navigation authority.

The existing `IntendedRoute` is the sole classified transient navigation owner: ten minutes, 2,048 characters,
registered protected static `/app/` paths and enumerated non-sensitive queries only. Its guard captures an
eligible anonymous target; the outer navigation/session owner retains that same instance through login.
Failures leave it untouched. `ShellApplication`/`LoginJourney` expose a credential-free `onAuthenticated`
completion signal, never an automatic redirect or `consume()` call: authority cached **before** login must
not select the new user's destination. The outer owner must load fresh post-login principal authority before
calling the existing `consume()` seam, which re-resolves current route requirements and consumes once. It never
receives email, password, form bodies or tokens. The current public shell has **no registered protected landing
or `/me` client**, so ordinary login truthfully stops at “You’re signed in”, removes the form, focuses the
success heading and keeps access only in the active page. No dashboard grant/principal is fabricated.
TICKET-00018 must supply the shared navigation owner, actual protected registrations and fresh `/me` before
protected entry; TASK-00047/48 own refresh/logout, reload restoration, scheduling and cross-tab continuity.

`/app/login/recovery` is a working help destination explaining that reset is not installed and directing the
user to their installation administrator without requesting a password. TICKET-00019 replaces it with the real
recovery journey. The C/round-4 typography/tokens, split wide/stacked narrow composition, associated labels,
current-password/username autocomplete, paste, remember explanation, focus, loading/failure/throttle/outage,
light/dark/system and reduced-motion behavior use production components. React-Bootstrap's remember checkbox
retains native accent-color rendering and an explicit 3px focus outline; no custom show-password control was
approved or added.

## Verification and local reproduction

Unchanged lock: Fight Access Control **0.6.0**, `dc4621d13851dd08cdf85ed9715ead24b1051e80`;
Common **1.3.0**, `7de6cad6e8a9752973ad9f8e27e285b0c1510582`. Actual installed public APIs were inspected
and exercised through real package/HTTP/PostgreSQL integration, not inferred from version labels.

Use [explicit setup](BUILD.md) and [local TLS](LOCAL_HTTPS.md) in an explicitly isolated Compose project,
with its own ports, database and keys. The ignored engineer handoff under
`.runs/tasks/TASK-00046/runs/initial/` names the selected checkout's private environment, runtime override,
fixture locator, startup, actual captures and sanitized receipts. It is evidence, not an application dependency.
Never run fixed-port TLS setup over another stack, enroll global trust implicitly, print credentials, or capture
filled live forms. Real blank/validation/cleared-success captures distinguish the absent login baseline from the
inherited not-found screen. Synthetic catalog failures are not live invalid-account evidence.

Focused tests cover actual structural rejection/schema read, both exact session policies, public failure parity,
real/dummy verifier work, rehash/rollback, persistent limits/outage, duplicate-login and lifecycle winner races,
redaction, strict client decoding, stale form results, safe intent and page retirement. Browser evidence checks
real ordinary/remembered HTTPS success, HttpOnly persistence differences, storage exclusion, keyboard focus and
responsive/theme states. These do not certify physical password managers, screen readers, all browsers, 400%
zoom, forced colors, production ingress or universal WCAG conformance. Independent Review/QA and deployment
qualification remain separate.
