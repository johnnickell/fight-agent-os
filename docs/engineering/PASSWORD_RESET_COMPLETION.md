# One-time password-reset completion

TASK-00055 implements `/app/reset-password/complete` and `POST /api/v1/auth/password-reset/complete`.
Independent Review/QA, production delivery enrollment and deployment remain separate.

## Link and browser ownership

Configure catcher delivery with `APP_PASSWORD_RESET_PATH=/app/reset-password/complete` and the exact trusted
HTTPS origin, following [reset email](PASSWORD_RESET_EMAIL.md). The fragment is exactly
`#user_id=<canonical UserId UUID>&credential=<RFC3986-encoded opaque credential>` in that order, with no other
fields. UserId is routing, not authority. The blocking head script accepts 1–4096 decoded UTF-8 bytes unchanged,
rejects malformed encoding/duplicates/unknown fields and bounds the complete input. It replaces the whole URL
and history state with the canonical path and `null` before theme storage, CSS, React or schema reads. Failed
history replacement denies intake and navigates to the fixed safe path. Query parameters never carry authority.

A single-use closure transfers material to `ResetCredential`, whose private volatile state is not serializable.
There is no browser persistence, API cache, intended route, telemetry or HTML hydration of grant material.
Later fragment navigation retires rather than replaces possession; the owned skip link still works. Unmount,
pagehide (including synchronous secret-form removal before possible bfcache freeze), terminal invalid-link and
success clear ownership. Reload/back/direct load cannot recover possession. JavaScript/PHP temporary copies
and browser internals cannot be reliably zeroized; reference clearing is not physical memory erasure.
The existing no-store HTML, no-referrer, self-only CSP and local fonts remain unchanged. CSS font subrequests
can name their non-sensitive stylesheet as referrer; no grant/password belongs in any referrer.

## Server and schema contract

The explicit public Action still traverses source validation and the [browser mutation profile](BROWSER_AUTH_REQUESTS.md).
Require direct HTTPS/configured authority, exact Origin, same-origin Fetch Metadata when present, nonce-bound
CSRF proof, unique-key bounded JSON, and exactly `user_id`, `credential`, `password`, `password_confirmation`.
No Bearer/query/forwarding/method-override alternative is allowed. Confirmation is exact; passwords remain
untrimmed, 8–128 Unicode codepoints. The opaque credential also has an independent 4096-byte transport bound.
Malformed transport is safe 400/403; a checked but unusable grant is generic 409.

`password_reset_completion` publishes only password/confirmation structural rules and approved shared guidance
through the PHP attributes → explicit export → anonymous schema service. It exposes no identity, grant, corpus
or policy reason. Shared Formik owns multiple errors, field/dependency generations, stale rejection fencing,
first-invalid focus and explicit unavailable-schema retry. A successful server mutation still reaches cleanup
and peer invalidation after local edits/reset/unmount. Server `PasswordSetting` independently checks exact Unicode
composition and the pinned local common-password corpus; no candidate is sent to an external service.

Reset completion consumes **20 peer / 300 endpoint attempts per rolling 900 seconds**, using the existing
`APP_AUTH_RATE_KEY`, optional lower limits and dedicated independently committed PostgreSQL admission connection.
Purpose `reset-completion` is distinct from request/login/refresh/logout; only purpose-separated HMAC endpoint
and normalized peer keys are stored. No target ID, grant or password is a limiter key. Both dimensions commit
atomically before policy/reset, survive business rollback and deny on capacity/lock/storage failure. Shared
capacity and bounded retry/history/deadline guarantees are unchanged. This is not production ingress or DDoS certification.

`CompletePasswordReset` applies shared candidate policy, then calls the shared package
`AuthenticationService::resetPassword()` directly. The package owns current active identity, latest usable
one-hour purpose-bound grant, credential verification, consume-once, Argon2id replacement, authentication
version/revision advancement, all-target-session revocation, atomic audit and post-commit event. There is no
consumer persistence shortcut or duplicate lifecycle policy. The unchanged lock qualifies Access Control
**v0.8.0**, source/lock `319738255e682d587642b67856039f882b9588d6`, and Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582` against the actual integration, not version arithmetic.

Success is exactly HTTP 200, no-store `{"status":"success","data":{"completed":true}}`. No TokenSet, access,
refresh, identity or grant metadata is returned; fresh sign-in is required. Invalid/unknown/used/expired/revoked/
replaced/mismatched/raced grants share `409 Conflict.`. Candidate rejection uses only the published password
message (422). Infrastructure failure is sanitized 500; post-commit event failure can leave a completed reset,
so neither server nor client automatically replays it. OpenAPI declares write-only sensitive fields and these limits.

## Target-scoped session retirement

Before reset, `ResetBrowserSession` uses the exact HttpOnly refresh credential (current or used) solely to
compare package session ownership for cookie cleanup, never to authorize reset. A successful matching target
expires the fixed refresh/nonce cookie identities. An unrelated browser session is untouched; reset possession
still independently selects its target. All old target server access versions and refresh sessions fail.

The inert public journey does not restore cookies or construct another principal/session graph. On success it
broadcasts one bounded `password-reset` message with transient target UserId, no credential or authority snapshot.
Existing `SessionCoordinator` recipients compare their sole cache's safe current/last-verified principal, clear
matching access/principal/intent through their terminal barrier and never relay an indiscriminate global logout.
Unrelated principals are unchanged. Unknown principal state is not silently assumed to match; server validation
remains authoritative. Missing/denied BroadcastChannel cannot undo reset; other tabs without messaging discover
revoked authority on their next authoritative request. No identity is persisted for coordination.

## Reproduction and evidence safety

Use a task-owned Compose project/database, unused loopback ports and private independent keys. Follow
[build](BUILD.md), [local HTTPS](LOCAL_HTTPS.md), [catcher](INVITATION_EMAIL.md) and
[reset request](PASSWORD_RESET_REQUEST.md) setup. Never enroll global trust automatically or reuse another
TASK's database. Freshly export OpenAPI/validation metadata, then build live assets before browser checks.

```sh
./bin/database test-reset
./bin/phpunit --filter 'ResetCompletionApiTest|PasswordSettingTest|AuthAdmissionTest'
./bin/client check
./bin/openapi check
./bin/validations check
./bin/build
```

Controlled PostgreSQL races use separate processes/connections and held production authority fences, no sleeps:
duplicate reset has one commit; successor-first invalidates the old completion; reset-first rejects a successor
request that already captured obsolete authority (a fresh explicit request can recover). Grant/user/session/audit
write failures roll back together while admission remains consumed. Tests also cover exact expiry, lifecycle/version
changes, unrelated-cookie preservation, old access/refresh denial, policy, request protection, safe publication
and redacted responses. Existing shared-form tests additionally own the full generation/reset/neighbor-error matrix.

Production-backed `Authentication/ResetCompletion` stories cover light/dark ready, schema loading/unavailable,
validation, generic invalid/used/expired, common-password, throttle, pending, system and cleared success states.
Live evidence captures only blank forms, empty validation or cleared outcomes after history removal. Never copy
mail bodies, fragments, cookies, candidates, traces, videos or DOM snapshots into evidence. The Engineer handoff
names actual baseline/result images, probe receipts, runtime identity and limits. Automated axe/keyboard/reflow
observations are bounded, not universal WCAG, physical AT/device, password-manager, bfcache-resume or production
trust certification. Independent QA must make its own observations.
