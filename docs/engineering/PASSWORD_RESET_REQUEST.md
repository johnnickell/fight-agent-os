# Generic password-reset requests

TASK-00054 implements `/app/reset-password` (with `/app/login/recovery` retained as an inert public alias)
and `POST /api/v1/auth/password-reset`. Independent Review/QA remain separate acceptance stages.
There is no completion form/API, public registration, delivery-management UI or production mail enrollment.

## Public contract

The sole input is `{"email":"person@example.test"}` with a canonical lowercase email, at most 254 bytes.
The typed client folds ASCII uppercase without trimming; malformed email stays a server validation concern.
The real named `password_reset_request` PHP Validation attribute publishes only required/string/1–254-codepoint
structural rules through the [private export and anonymous read](../api/VALIDATIONS.md). Email format and
canonicalization stay server-owned; neither client nor Action queries account existence. The form uses the
shared Formik adapter, multiple approved field messages, generation fences and first-invalid focus.
Schema failure prevents submission until explicit retry. Edits discard obsolete errors, not actual server success.

Every successfully processed eligible, absent, pending, disabled, deleted or repeated request returns exactly
HTTP **200**, `{"status":"success","data":{"accepted":true}}`, JSON and `Cache-Control: no-store`.
Only fresh random correlation varies. No cookie is set/cleared, no principal is restored, and no identity,
grant, delivery, expiry or destination metadata is returned. Every requester sees:

> If this address is eligible, a reset email will be sent. Check your inbox. Links expire after one hour.

Success removes the email form, focuses “Check your inbox” and offers Return to sign in. Validation, throttle,
secure-request refusal and system recovery use account-independent copy. An uncertain system/network result
can still have committed work; no client automatically retries a mutation.

The exact matched Action is explicitly public; that does not bypass source validation or browser guards.
The existing [browser mutation profile](BROWSER_AUTH_REQUESTS.md) enforces direct HTTPS/authority, exact Origin,
Fetch Metadata, JSON-only unique-key bounded objects, no alternate credential/override/forwarding sources and
nonce-bound CSRF. The new guard requires exactly one validated canonical email before admission/dispatch.

## Admission and timing

Three purpose-separated HMAC dimensions commit **together**, before business dispatch, on the existing dedicated
PostgreSQL admission connection: **3 per canonical email**, at most **20 per server peer** and **300 per reset
endpoint** per rolling **900 seconds**. The existing `APP_AUTH_RATE_KEY` and optional lower network/endpoint
limits apply. Reset purposes cannot collide with login. No raw email/network or HMAC key is stored in the
limiter. The existing store now admits at most three dimensions; capacity, bounded histories, lazy expiry and
2/3-second lock/statement deadlines remain unchanged. Denial does not grow histories or partially consume
another dimension. HTTP 429 and bounded 1–900-second Retry-After depend on attempts, never eligibility;
store failure denies with the existing safe 60-second retry. Business rollback cannot refund admission.

After admission, `ResetResponseTiming` measures dispatch using monotonic nanoseconds and makes **one wait of
at most 250ms**, only for the remaining portion of a fixed 250ms budget. Success, silent no-op and exception
paths use the same calculation; work already exceeding the budget receives no additional wait. There is no
random delay, identity-specific duration, retry loop, timing metric or per-identity diagnostic. Deterministic
clock/wait tests cover zero/short/exact/over-budget/exception paths, not flaky elapsed-wall-time assertions.
At most four otherwise idle admitted requests per second per single worker can occupy that worker; endpoint
admission averages at most 300/900 requests per second, with bounded bursts. Padding still consumes worker
capacity. Scheduling, interrupted waits, database contention/outage, CPU pressure, transport and operations
exceeding 250ms can distinguish durations. This is bounded normalization of obvious fast missing-user paths,
**not** constant-time execution, exact network equality or a load/performance certification.

## Package lifecycle and delivery

The unchanged lock qualifies Access Control **v0.8.0**, source/lock reference
`319738255e682d587642b67856039f882b9588d6`, and Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582` for this integration. Historical v0.6.0 email receipts are not
current request evidence. The Action dispatches `RequestPasswordReset` through the configured command bus;
it neither constructs a grant nor sends mail. The shared package handler owns active eligibility, one-hour
purpose-bound hashed authority, encrypted recoverable material, predecessor replacement, audit and the
secret-free post-commit `PasswordResetRequested` event. PostgreSQL authentication fences serialize the latest
generation decision across separate requests. Request failure events pass through `AuthenticationEvents`,
which replaces the raw email-bearing `CommandFailedEvent` with package `RedactedCommandFailed` containing
only command class, empty command data and fixed copy before any shared subscriber observes it.

**Reset mail is deliberately asynchronous.** Reset events are no longer registered for immediate synchronous
delivery, eliminating eligible-only SMTP latency from public requests. Invitation delivery remains unchanged.
Direct reset delivery commands and the existing durable discovery runner remain available. For every enabled
reset installation, an operator/scheduler must run the existing bounded recovery operation:

```sh
./bin/credential-delivery run --internal --page-size=100 --capacity=10
```

Follow [reset email](PASSWORD_RESET_EMAIL.md) and [recovery](CREDENTIAL_RECOVERY.md) for independent external
keys, catcher/private inspection, due retries, expiry and cleanup. No scheduler is enrolled by this TASK;
without an operator/scheduler, pending resets are not sent. Request acknowledgement does not promise provider
submission. The package's one-hour deadline begins at request, not at delivery. Configure the future canonical
completion path explicitly; `/app/reset-password/complete` remains unimplemented until TASK-00055. Do not
misconfigure reset email links to the request page or copy private mailbox material into evidence.

Controlled PostgreSQL evidence covers two simultaneous bus requests against no predecessor, live issued,
recoverable retry, confirmed delivery, consumed, revoked and expired predecessors, plus initial/successor
audit-failure rollback. Exactly one current generation remains; predecessor ciphertext is removed. Separate
three-connection races exhaust each email/peer/endpoint budget using database lock barriers, not sleeps.
The exact public response matrix separately uses the real HTTP pipeline, generated OpenAPI and shared schema
publication. Existing direct-delivery tests qualify downstream recovery and stale outcomes. These do not
certify production ingress, arbitrary event subscribers, WAL/backup erasure, SMTP exactly-once behavior or
universal browser/assistive-technology conformance. Email remains necessarily present in package identity and
delivery destination storage; it is not copied into admission, public diagnostics, audit context or analytics.

## Local verification and QA

Use only the selected task's Compose project and private `.env`; never reset another checkout's database.
Explicitly install dependencies and export metadata before the read-only [full gate](BUILD.md). Focused checks:

```sh
./bin/database test-reset
./bin/phpunit --filter 'ResetRequestApiTest|ResetResponseTimingTest|AuthAdmissionTest|DirectCredentialDeliveryTest'
./bin/client check
./bin/build
```

The production-backed `Authentication/ResetRequest` catalog covers ready light/dark, loading, unavailable,
validation focus, pending, throttle, outage and generic accepted states. Live production pages consume the
real exported PHP schema. Keep screenshot inputs synthetic; capture accepted/cleared states rather than email,
mailboxes or fragments. Task evidence records exact local resources, comparable Before/After captures,
keyboard/theme/reflow observations and trust/browser limits; independent QA must report its own observations.
