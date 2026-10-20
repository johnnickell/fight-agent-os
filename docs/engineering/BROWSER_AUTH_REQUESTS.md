# Browser authentication request controls

[ADR 0003](../../planning/adr/0003-browser-authentication-security-profile.md) governs these controls.
TASK-00045 provides reusable login/refresh/logout controls. [TASK-00046 login](LOGIN.md) now supplies its Action, route and UI; refresh/logout consumers remain planned.
Activation continues using the shared browser guard and session responder. Its separate admission limits
and invitation/password behavior are unchanged. Complete deployment qualification remains TASK-00063/64.

## Composition and request matrix

Login and future refresh/logout Actions declare `#[JsonBody]` and `#[Validation]` and consume only the validated source attribute.
The API pipeline is failure/correlation → routing/security → `ApiInputValidation` → the registered
`auth.guard.login`, `auth.guard.refresh` or `auth.guard.logout` → Action. These guards require the validated
JSON attribute; attaching them without source validation fails closed. They combine `BrowserMutationGuard`,
endpoint shape and independently committed admission. They never call an authentication service themselves.
No blanket public-route exemption, new bootstrap, credential store or permissive CORS policy is supplied. The login integration independently qualifies raw server `HTTP_HOST` when Slim's reconstructed Host drops a nondefault port; a matching configured URI is still mandatory.

| Boundary | Accepted | Rejected before Action dispatch |
|---|---|---|
| Method | POST | Other methods; preflight; method-override headers/fields |
| Transport | Direct trusted server HTTPS and configured authority | HTTP, userinfo/fragment, forwarded/original authority headers |
| Origin | One HTTPS origin with matching host/effective port | Missing, null, foreign, suffix, wildcard, path, userinfo, duplicate/list |
| Normalization | ASCII hostname case and default 443 normalize | Trailing dot, malformed port, non-HTTPS; no suffix matching |
| Fetch Metadata | Absent site, or exactly `same-origin`; optional mode `cors`/`same-origin`, destination `empty` | Same-site/cross-site/none, duplicates, navigations, user activation and malformed combinations |
| Content | One `application/json`, optional `charset=utf-8` only | Form, multipart, text, other parameters/encoding, duplicate Content-Type |
| JSON | Nonempty unique-key UTF-8 object, at most 65,536 bytes | Arrays, malformed/duplicate keys including escaped equivalents, nested values, unknown fields, oversized bodies |
| Login shape | Exactly canonical email (≤254 bytes), nonempty password (≤128 Unicode code points), boolean remember | Wrong types, noncanonical email, missing/extra fields and alternate credential sources |
| Refresh/logout shape | Exactly `{}` | Empty body, arrays, any fields |
| CSRF | One raw nonce cookie and one matching unexpired `X-CSRF-Proof` | Missing/duplicate/wrong/expired/rotated proof or nonce; query/body alternatives |

The existing transport-free MAC verifier uses constant-time verification and a 900-second maximum proof
window. Proofs are deliberately reusable until expiry, including across tabs. The bootstrap can issue another
proof for the same nonce. Successful activation/login rotates the nonce; refresh leaves it intact. Terminal
logout/failure deletes it. Sibling tabs bootstrap again; neither the nonce nor its proof carries authority.
Rejected input receives safe 400 source-validation or 403 browser/shape errors through the existing JSend
failure/correlation boundary, never submitted values. Routing rejects unregistered operations normally.

## Fixed cookies and session responses

`BrowserSessionCookies` fixes the cookie identity rather than accepting deployment-controlled cookie options:
`__Secure-agent_os_refresh`, host-only (no Domain), `Path=/api/v1/auth`, Secure, HttpOnly, SameSite=Strict.
The CSRF nonce uses the existing `__Secure-agent_os_csrf` with identical identity/security attributes.
There is no configurable insecure variant to validate or silently downgrade.

- Ordinary refresh cookie: no Expires or Max-Age; package absolute expiry must be future and within two days.
- Remembered: Expires equals the package absolute deadline rounded **down** to UTC seconds, never later;
  it must be future and within one calendar year. No Max-Age can extend it. The package server session remains
  authoritative (ordinary one-day idle/two-day absolute; remembered 30-day idle/one-year absolute).
- `SessionResponder::respond()` establishes a session and rotates the nonce. `refreshed()` replaces only the
  refresh cookie, preserving other tabs' proofs. `clear()` adds matching expired refresh/nonce identities to
  a caller-owned safe response and sets no-store, including an uncertain-revocation error response.
- Cookie material is never added to session JSON. `SessionAccessData` retains only redacted access material
  and matching whole-second timing. Callers must never log TokenSet/cookie strings.
- No legacy cookie variant exists in the accepted implementation. No broad cookie deletion is attempted;
  a future known migration must explicitly enumerate and expire its old identities.

## Persistent attempt policy

Configure independent externally generated `APP_AUTH_RATE_KEY`: exactly 32 random bytes as lowercase hex.
Generate locally with a cryptographic source; never copy key material into evidence. Startup rejects missing,
malformed and obvious repeated-byte keys and reuse of a CSRF, activation-rate or active/previous signing key.
CSRF likewise rejects obvious weak keys and signing-key reuse. These checks cannot prove randomness.

The production package `LoginThrottle` now resolves to `AuthAdmission`. Each allowed `allows(email)` call
consumes one of **five** canonical-email attempts per rolling **900 seconds**, regardless of whether the user
exists or credentials later succeed. Denied calls return false and do not extend the window or grow storage.
Canonical email is keyed only by purpose-separated HMAC; raw email/password is never persisted or looked up by
this control. The package owns generic identity/password outcomes and dummy verification in TASK-00046.

Before each login/refresh/logout Action, separate rolling budgets admit at most **20** requests per server peer
and **300** per endpoint per 900 seconds. `APP_AUTH_NETWORK_LIMIT` and `APP_AUTH_ENDPOINT_LIMIT` may lower these
positive limits, not exceed them. Purposes are isolated; IPv6 addresses are keyed from normalized address bytes.
Network identity is only server `REMOTE_ADDR`, never client forwarding headers. Invalid identity denies.
There is no session/actor trust before authentication and no client-selected actor key.

`PostgresAuthAttempts` follows TASK-00041's independently committed, transaction-advisory-lock and bounded-capacity
admission pattern. The invitation store is actor/action/target-specific and fixed-window, so it cannot safely
implement the accepted rolling HMAC-email protocol. Its existing table/API remains unchanged; this narrow auth
store is not a new general-purpose limiter framework. One dedicated connection, separate from package business
transactions, commits all checked dimensions atomically. Nested admission transactions are refused. A global
advisory lock serializes capacity checks (10,000 rows); histories hold at most their configured attempt limit,
expired hits/rows are reclaimed lazily, and no denied attempt adds data. Maximum history is 1,000 entries at the
store boundary; this profile uses at most 300. Lock/statement timeouts are 2/3 seconds; contention/storage failure
denies with safe bounded retry, not a database exception chain. Retry-After is 1–900 seconds (outage: 60).
The table and payload are secret-free apart from one-way pseudonymous keys; backup/access policy still applies.

## HTTPS, startup and deployment limits

`APP_AUTH_TRANSPORT=direct` is the only supported mode. `APP_AUTH_TRUSTED_PROXIES=[]` is the only supported proxy
configuration. Both are supplied by Compose; production-like direct boot must explicitly select transport.
No forwarded-header promotion or CIDR enrollment is implemented. A TLS gateway must discard untrusted forwarding
metadata, supply trusted server HTTPS/authority and peer identity itself, and keep FPM private, as the local
[HTTPS profile](LOCAL_HTTPS.md) does. Generic reverse-proxy trust is **not** enabled by listing an address.
Actual ingress header preservation, repeated physical-header rejection and production network identity remain
deployment obligations; PSR-7 tests cannot qualify an arbitrary ingress.

HTTP boot validates the key/limits and transport settings, with a mandatory auth-store schema/availability check
in production-like environments. Apply migrations **before** HTTP boot. Invalid origin/CSRF/signing settings
already fail shared service boot. Cookie attributes are compile-time fixed. Console list/help does not resolve
HTTP admission keys or connect just to display help. No secrets are generated by production startup. Other
existing production prerequisites (especially enrolled credential delivery) remain required and are not bypassed
by these controls. A passing auth-component probe is not a successful complete production boot.

Tests exercise real shared JSON middleware, safe correlated rejection before dispatch, cookie serialization,
nonce replacement/expiry, rolling fractional expiry, known/unknown email equivalence, business rollback,
capacity/outage, and four three-connection contention scenarios (email and each endpoint), using database lock
barriers rather than sleeps. OpenAPI publishes reusable parameters/input/429 components without future paths.
Canonical login now exercises these controls through its real package/HTTP/browser boundary; package refresh/logout, cross-tab browsers, deployment trust and integrated redaction retain their downstream owners. No production or live-inbox operation is authorized here.
