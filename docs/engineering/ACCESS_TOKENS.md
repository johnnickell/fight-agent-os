# Access-token boundary

[ADR 0003](../../planning/adr/0003-browser-authentication-security-profile.md) owns the accepted security policy.
`AccessJwt` implements Fight Common `TokenEncoder`/`TokenDecoder`; package `AuthenticationService` supplies
its five package-owned claims and expiration. Issuance preserves those claims and serializes only expiry
downward to integer UTC seconds, requiring `exp - iat = 900`; inconsistent supplied expiry still fails closed.
It adds issuer, single-element audience, `nbf = iat`, and random 128-bit lowercase hex JTI. Consumption independently
validates compact encoding, duplicate JSON members (including escaped names),
exact header/claim sets, types, signature, key, clock and identifiers. No roles, permissions or private claims.

## External configuration before boot

Every environment must provide `APP_ACCESS_TOKEN_CONFIG` as private JSON. Production never generates a key or
falls back to `APP_HMAC_SECRET`. Use a distinct keyring per environment and independent CSRF/delivery/request-HMAC
keys. Generate each signing secret with a cryptographic generator (`openssl rand -hex 32`); do not paste it into
logs, terminal transcripts or committed examples. Runtime configuration must remain external to public HTML,
source control, diagnostics and upload bundles.

The object has exactly these members **in this order**:

- `algorithm`: exact string `HS256`.
- `issuer`: exact HTTPS URL, at most 256 ASCII characters; host required, no userinfo/query/fragment.
- `audience`: exact 1–256 character ASCII identifier (`A–Z`, `a–z`, digits, `:._/-`); starts alphanumeric.
- `active_kid`: identifier of the sole active signing entry.
- `keys`: list with one active and optionally one verification-only previous entry.

Each active entry has exactly `kid`, then `secret_hex`. IDs are unique 1–64 character `[A-Za-z0-9_-]` strings.
Secrets contain 32–64 decoded bytes represented by lowercase hex. Startup rejects malformed/short/repeated-byte
keys (fewer than 16 distinct bytes), reused signing secrets, duplicate IDs and an absent active signer. These
checks cannot prove entropy: operators must generate random material, not encode human-chosen passwords.

The previous entry has exactly `kid`, `secret_hex`, `last_issued_at`, `verify_until`, in that order. The last two
are positive integer UTC seconds. `last_issued_at` cannot be in the server's future; `verify_until` must equal
`last_issued_at + 900`. Prior signatures require both `iat <= last_issued_at` and `now < verify_until`; there is
no indefinite fallback. Expired retained entries confer no authority. Remove compromised entries immediately.
All invalid configuration fails boot, even with only public routes; errors disclose only a fixed message.

## Rotation and integration

This is a bounded local keyring, not a key-management deployment. Arrange consumer readiness before switching
issuance (there is no multi-node rollout automation here). Select the new active entry and previous entry with
the last possible old issuance second. Synchronize clocks; retain the old entry only through its 900-second
window. New issuance always uses the active key. A grossly incorrect clock requires operational recovery, not
an expanded grace period. Production provisioning, deployment enrollment and emergency rotation are separate.

The shared package `Clock` port uses precise UTC `SystemDeliveryClock`; `AuthenticationService` must retain
precise time for validation, rotation and session deadlines. Delivery grant/lease/outcome deadlines are also
precise. Request validation and keyring checks use precise `SystemAccessClock`. Never floor a shared clock,
round persisted deadlines or add grace to obtain integer JWT times.

Under the explicitly approved [ADR precision clarification](../../planning/adr/0003-browser-authentication-security-profile.md#approved-access-expiry-precision-clarification),
`AccessJwt` uses only supplied expiry's `getTimestamp()` for serialized `exp`. Package integer `iat` and other
claims remain unchanged; serialized `exp` must equal `iat + 900`. Expiry that floors to a different second is
refused, not repaired by computing another deadline. Package DateTime/`TokenSet` expiry retains its original
fractional precision, while JWT expiry can be less than one second earlier, never later. Their whole-second
timing representations agree; they are not subsecond-identical. Future login/refresh responses must expose
whole-second timing no later than JWT expiry, not round package DateTime upward. [Activation](ACTIVATION.md) now supplies this response mapping through `SessionResponder`; [canonical login](LOGIN.md) now reuses that response mapping and refresh remains unfinished. Tests use representative fractional clocks and persisted hard deadlines, not only
whole-second fixtures.

`AuthenticateAccessToken` resolves session and user fresh on every request using package repository ports,
requires active lifecycle, owned usable session, matching token/user/session versions, and issuance no earlier
than session creation. It rechecks token expiry after store access. Store failure denies without preserving
SQL/exception chains. It returns package `AuthenticationContext` without permission projection.

TASK-00044's standalone `AccessTokenAuthentication` remains available for explicitly protected pipelines
**inside** `ApiFailureMiddleware`. TASK-00117's registered API-v1 group uses `ApiActionSecurity` with the same
extractor/authentication service, then constructs a fresh package `CurrentPrincipalProvider` per protected
request. It overwrites authentication/current-principal/security attributes only after verified resolution;
no shared caching provider or client-supplied authority is used. `/me` is the first protected operation.
See [matched-Action security](../api/README.md#matched-action-security-task-00117) for the exact public
allowlist, metadata placement, permission conjunction and safe projection. [Login](LOGIN.md) now supplies its real HTTP/browser matrix. Refresh/logout retain their owning TASKs.

The extractor accepts exact case-sensitive `Bearer ` plus one compact token, at most 2048 token bytes (2055
header bytes). It rejects duplicated/folded headers, whitespace/control ambiguity, alternative credential
fields (`access_token`, `token`, `authorization`) and recognizable compact tokens in query/body/cookie sources,
even alongside a valid header. Ordinary noncredential cookies and request fields remain allowed. Raw
`HTTP_AUTHORIZATION` is compared when available because PSR-7 implementations may normalize whitespace first;
a deployment must preserve raw headers and reject repeated physical headers at ingress. Body inspection is
bounded to 16 KiB, requires a seekable stream and preserves its cursor. Larger/streamed protected operations
need an explicitly planned transport policy rather than silently bypassing inspection.

Known refusals use generic JSend `401 Unauthorized.`, `Cache-Control: no-store`, and safe `X-Correlation-ID`.
No token, claim, key, SQL, decoder diagnostic or authentication audit event is logged by this boundary.
Existing approved public reads remain public. OpenAPI describes the real `/me` and login operations, not an unimplemented refresh
journey or fabricated permission-protected resource route.
