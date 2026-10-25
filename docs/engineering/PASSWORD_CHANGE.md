# Authenticated password change

TASK-00056 implements Account & Security → Change Password at `/app/account/password` and
`POST /api/v1/auth/password/change`. Independent Review/QA and delivery remain separate.

## Authority and server behavior

The versioned Action requires Bearer authentication and derives its sole target from the request-scoped
package `CurrentPrincipalProvider`. No role/managed permission is required for authenticated self-service.
Cookies do not authorize the operation; body actor/user/session/role/permission fields are rejected.
Stale, revoked and non-active principals fail before Action dispatch. Require direct configured HTTPS,
exact Origin, same-origin Fetch Metadata when supplied, unique-key bounded JSON and no forwarding,
method-override, encoded-body or query alternatives. This is the independent Bearer mutation profile,
not ambient-cookie authentication; no CSRF bootstrap is needed for this operation.

Source-only `JsonBody` and Validation own exactly `current_password`, `password` (replacement), and
`password_confirmation`. Current password is bounded to 1–128 Unicode codepoints, without replacement
composition checks. Replacement and confirmation are bounded to 8–128; confirmation is exact. Fields
are not trimmed, normalized or retained in messages. The shared `PasswordSetting` boundary separately
enforces Unicode composition and the pinned local common-password corpus before package dispatch.
See [password semantics](PASSWORDS.md). Client metadata never authorizes or replaces server checks.

Owned Application orchestration calls `AuthenticationService::changePassword()` directly. Only the
package reads/verifies current proof using its real/dummy path. The package owns Argon2id replacement,
authentication-version/authority-revision advancement, reset-grant retirement, all-active-session
revocation, atomic audit and the post-commit `PasswordChanged` event. No password history or requirement
to differ is added. Qualification uses the unchanged Access Control v0.8.0 lock/source
`319738255e682d587642b67856039f882b9588d6` and Common v1.3.0
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`, not version arithmetic.

Success is HTTP 200, no-store `{"status":"success","data":{"changed":true}}`, with fixed refresh/nonce
cookie deletion (Secure, HttpOnly, SameSite=Strict, Path=/api/v1/auth). No access/refresh token or identity
is returned. Every old access version and refresh session fails after commit. Wrong current proof and
safe package rejection share generic 409 `Conflict.`; the UI offers one safe message. Replacement policy
uses published 422 feedback. Transport errors are 400/403, lost authentication 401, admission 429 and
infrastructure failure sanitized 500. A post-commit event/network failure can leave a completed change;
there is no automatic mutation retry. Failure before commit leaves still-authoritative sessions intact.

Admission consumes independently committed peer/endpoint budgets: **20 peer / 300 endpoint attempts per
rolling 900 seconds**, configurable downward through the existing auth settings. Purpose `password-change`
is separate from login/refresh/reset/logout. Keys contain only purpose-separated HMACs of normalized peer
and endpoint, never identity/password material. Storage/capacity/lock failure denies admission; business
rollback cannot refund attempts. This is not production ingress/DDoS certification.

## Browser lifecycle

The protected form uses the existing sole application session/principal graph, public named schema
`password_change`, shared Formik generation adapter and production semantic controls. Metadata is anonymous
and contains only approved constraints/messages. Missing schema is explicit, fail-closed and retryable.
Current/new/confirmation autocomplete, paste, Unicode/spaces, show/hide, first-invalid focus, multiple
messages, confirmation dependencies, edit/reset/unmount fencing and busy duplicate suppression remain
supported. Nonvalidation rejection clears all password fields and focuses/announces the safe result;
policy feedback stays attached to fields for correction. No form values enter storage, history, intent,
analytics or diagnostics. Reference clearing cannot guarantee physical JavaScript/browser memory erasure.

Committed success reaches the coordinator even after edits, reset or navigation. Its terminal barrier clears
access/principal/intent and removes secret DOM, fences outstanding login/refresh results, and broadcasts one
bounded `password-change` signal containing only version/sender/sequence/kind. Recipients do not relay it or
perform cookie logout. Current and sibling tabs navigate to fresh Login and explain all sessions ended.
No replacement token is adopted. Missing BroadcastChannel cannot undo the server change; disconnected tabs
learn of revoked authority on their next authoritative request. Pagehide retires the existing full application
owner synchronously, including secret DOM. There is no second credential cache or reauthentication payload.

## Verification and safe reproduction

Use a separate Compose project, databases, writable mounts and unused loopback HTTP/HTTPS mapping. Preserve
existing keys privately. Follow [explicit build setup](BUILD.md) and [TLS setup](LOCAL_HTTPS.md); ordinary
`./bin/up` discovers the effective loopback HTTPS port and uses curl `--connect-to` while retaining canonical
`https://localhost:18443` authority. Do not stop/reset another project's resources or enroll global trust
without explicit human authority. Browser probes must reach the selected project, not the main listener.
The Engineer handoff records the actual mapping and task-owned check/browser access procedure.

```sh
./bin/database test-reset
./bin/phpunit --filter 'PasswordChangeApiTest|PasswordSettingTest|AuthAdmissionTest'
./bin/openapi export
./bin/validations export
./bin/client check
./bin/planning-check
./bin/build
```

Controlled separate-connection tests hold actual PostgreSQL authority/session-row locks rather than sleep.
They cover duplicate change, both change/refresh orders, current/other-session revocation and authority
replacement, plus user/session/audit rollback. Shared password tests own structural/checker boundaries;
HTTP integration proves their real invocation, ownership, cookie/token outcomes and redacted publication.
Client tests own pending A→B→A, reset/unmount, policy/schema/retry and terminal session transitions.
Production-backed stories cover light/dark ready, loading/unavailable, validation, proof/policy/throttle,
pending/system and cleared success. Real browser evidence is limited to blank forms, empty validation
and cleared results; never capture candidate values, request bodies, cookies, tokens, traces or videos.
Automated accessibility/keyboard/reflow/theme checks are bounded evidence, not universal WCAG, physical
assistive-technology/password-manager or production trust certification.
