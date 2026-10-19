# Authorized invitation API

TASK-00041 issuance/status and TASK-00042 recovery expose five Bearer-protected operations, not public
registration or a browser invitation screen:

| Operation | Input | Exact current permission |
|---|---|---|
| `POST /api/v1/invitations` | JSON `email`, `mode`, optional boolean `confirm_super_admin` | `INVITE_USERS`; `super_admin` also requires `ASSIGN_SUPER_ADMIN` |
| `GET /api/v1/invitations/{user_id}` | User UUID path only; no query/body/Content-Type | `MANAGE_USER_INVITATIONS` |
| `POST /api/v1/invitations/{user_id}/retry` | User UUID path only; no query/body/Content-Type | `MANAGE_USER_INVITATIONS` |
| `POST /api/v1/invitations/{user_id}/resend` | User UUID path only; no query/body/Content-Type | `MANAGE_USER_INVITATIONS` |
| `PATCH /api/v1/invitations/{user_id}/email` | User UUID path and JSON `email` only | `MANAGE_USER_INVITATIONS` |

`mode` is exactly `ordinary` or `super_admin`. Ordinary maps only to the stable installation-managed `ROLE_USER`;
elevated maps only to managed `ROLE_SUPER_ADMIN` and requires literal JSON `true` confirmation. Missing or false
confirmation is 422, never implicit consent. String/numeric/null confirmation is invalid input (400).
No submitted actor, role, permission, password, target identity or provider option is accepted. The request actor
comes from verified authentication. Application freshly resolves that authentication and checks exact permissions
again, including before the independent assignment. Neither a role name nor a cached HTTP projection authorizes it.
Direct role administration's separate `MANAGE_USER_ROLES` requirement is not imposed on invitation issuance.

Install the current locked dependencies and apply the normal migration through the owning database wrapper before
serving these routes. The version-controlled managed policy must already be reconciled by a trusted operator;
issuance previews it and refuses drift rather than mutating installation authority. See
[managed policy](MANAGED_POLICY.md), [access tokens](ACCESS_TOKENS.md) and [invitation delivery](INVITATION_EMAIL.md).
No command here should be used with a real inbox until its separate provider/runtime prerequisites are satisfied.

## Input and safe HTTP outcomes

The actual Action declares source-only `JsonBody` plus Validation-owned snake_case fields/rules. JSON must be a
unique-key object, with no undeclared fields or nested values. Email is validated and bounded to 254 characters,
then canonicalized through the package email value before dispatch. Canonical/case-equivalent addresses conflict;
Unicode local parts unsupported by the current package validator are rejected, not transformed into new identities.
The existing Bearer extraction guard requires a seekable body and rejects bodies larger than **16,384 bytes** with
401 before JSON validation. This narrower authentication boundary remains intact; the input middleware's independent
65,536-byte ceiling does not promise acceptance of authenticated bodies that large.

All API outcomes use JSend, `Cache-Control: no-store`, and sanitized `X-Correlation-ID`; no CORS or cookie grant is
introduced. OpenAPI attributes on the actual Actions/Responders are authoritative. Responses contain no canonical
email because these operations need only stable user identity and delivery lifecycle, reducing unnecessary PII.

| HTTP | Meaning |
|---|---|
| 201 `success` | Origin, exact managed membership and separate confirmation audit were established; **not** email receipt |
| 200 `success` | Safe package delivery status read or completed recovery dispatch with an observed state effect |
| 400 `fail` | Invalid JSON/source/type/bound/allowlist or malformed path identifier; no dispatch |
| 401 `error` | Missing/invalid/stale authentication or rejected alternative/oversized credential transport |
| 403 `error` | Exact required permission absent, or Application authentication no longer authoritative |
| 404 `error` | Authorized target has no invitation delivery generation |
| 409 `error` | Canonical/reservation conflict, policy drift, ineligible recovery state or concurrent transition |
| 422 `fail` | Missing/false explicit elevation confirmation |
| 429 `error` | Atomic actor/action budget or key capacity exhausted; integer `Retry-After` seconds |
| 503 `fail` | Issuance completion partial/uncertain; inspect retained state, **never automatically retry issuance** |
| 503 `error` | Recovery coordination uncertain; read status before another operation, **never automatically retry** |
| 500 `error` | Unexpected dependency/configuration failure, safely correlated without private exception text |

Issuance `data` contains exactly `user_id` (nullable only for uncertainty), `assignment`
(`confirmed`, `unconfirmed`, `unknown`), `audit` (`confirmed`, `unconfirmed`), and nullable `delivery`.
These assignment/audit fields describe observed steps, not a parallel invitation-delivery lifecycle.
`delivery` and status-read `data` use the package `InvitationDeliveryStatusView` directly: `user_id`, `status`,
`expires_at`. The qualified package statuses are `pending`, `claimed`, `retry_pending`, `delivered`,
`permanent_failure`, `expired`, `invalidated`. `delivered` records the provider's accepted attempt outcome, not
inbox receipt. `expires_at` is the grant's expiry; the View reports stored lifecycle, not an invented clock-based
transition. A retryable/permanent provider failure can coexist with a 201 successfully recorded invitation;
recovery remains the package-owned [credential recovery](CREDENTIAL_RECOVERY.md) path.

## Independent commits and retained state

This API inherits [TASK-00037](../../planning/tasks/00037-TASK.md)'s accepted sequencing, not an all-or-nothing
promise. `InvitePendingUser` and `AssignRoleToUser` traverse the configured shared buses and own independent
transactions/events. Pending identity, seven-day hashed grant, encrypted recoverable delivery and `user.invited`
audit commit in the first package step. Delivery can start **before assignment**. A second-step failure retains
that invitation and possibly sent email. Assignment confirmation uses a separate audit transaction, recording
`user.invitation_role_confirmed` with the authenticated actor UUID, target UUID, stable role ID and bounded
`membership_confirmed` result. It does not pretend the target authorized its own invitation.

A crash can leave membership without confirmation audit. An exception after a package commit does not prove
rollback. Safe package queries inspect current state, including through the DBAL read connection after a failed
Doctrine write manager. Failure to read delivery does not discard a known target; failure to validate identity
leaves it unknown. No uncertain mutation is retried, compensated, rolled back across steps or silently declared
successful. Audit acknowledgement loss remains unconfirmed, even if its row actually committed.

For a 503, retain the correlation and known user ID, stop automatic issuance/reinvitation, and have a trusted
operator inspect persisted identity, roles, audit and package delivery. Existing trusted `user:role:add` can correct
an eligible ordinary membership with the rules in [console invitations](CONSOLE_INVITATIONS.md); it cannot elevate
Super Admin. Elevated or unknown-state recovery needs operator investigation and separately scoped work.
The recovery operations below do not resume or repair issuance's independently committed role assignment.
Admin UI, activation transport/UI, real provider enrollment and recovery scheduling remain separate. A client must
not interpret any 503 as a safe idempotent retry instruction.

## Atomic invitation recovery

The deliberate current lock selects Access Control **v0.6.0**, source/lock
`dc4621d13851dd08cdf85ed9715ead24b1051e80`; Common remains **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. Version availability alone was not qualification. The v0.6.0
retry handler checks its injected Clock inside its transaction. Actual PostgreSQL probes reject at exact expiry
and afterward without changing revision or writing a success audit, including with normal post-commit coordination.
A separate injected audit failure proves rollback. Historical v0.5.0 qualification failures remain in TASK-00042.

Every recovery entry re-resolves invocation authentication and exact `MANAGE_USER_INVITATIONS`, then checks
current installation actor/target authority before admission or grant lookup. The package's
`InvitationAdministrationAuthorization` correction callback uses the same exact authoritative Role/Permission
references; a Role name never grants the permission, and missing/mismatched references fail closed. The callback
accepts trusted internal actor/target IDs, not HTTP authentication: nontrusted callers must enter through
`RecoverInvitation` with verified invocation authentication. Raw package bus access remains trusted composition,
not a public forwarding API. Unknown targets return generic 403; an authorized existing target without a grant
returns 404. No target email is disclosed.

- **Retry** dispatches `RetryInvitationDelivery`. Only live issued, retryable failed work returns to pending;
  generation, credential and provider idempotency identity remain unchanged. It is not an arbitrary reset.
- **Resend** dispatches `ResendInvitationDelivery`. An issued latest grant (including expired issued authority)
  is revoked and replaced atomically with a fresh purpose-bound seven-day grant and delivery generation.
- **Correction** dispatches `CorrectPendingInvitation`. Only a pending-activation user with a matching current
  issued invitation is eligible. Canonical email claims and live reservations conflict. User email, predecessor
  revocation, fresh seven-day successor, encrypted delivery and audit commit together, or all roll back.
  Active-user email change is not provided. Assignments are preserved.

The package transactions write `user.invitation_delivery.retry_requested`, `user.invitation_delivery.resent`,
or `user.pending_invitation_corrected` with safe actor/target/action evidence. No consumer delivery intent or queue
is introduced. Commands traverse the shared bus, with no outer consumer transaction, invariant replica, or
pre-cleanup transaction pretending to supply atomic expiry admission. The consumer observes an actual state effect
before returning success, refusing a silent bus veto. Retry must retain its generation; replacements must
change generation, and correction must also have matching canonical email in the safe package User View.
Incompatible concurrent observations return uncertainty, not a misleading success. This postcondition evidence
is not a new admission invariant or a claim to identify which concurrent operation won.

Recovery returns 200 JSend `success` with the same credential-free package status View as status reads; it is
neither credential disclosure nor proof of inbox receipt. Package delivery subscribers may process retry/resend
after commit. Correction's event has no immediate subscriber in this composition: its committed generation is
found by normal package discovery and the direct delivery handler through the internal recovery runner.
No enrolled scheduler is implied. Missing immediate dispatch never requires reconstructing a consumer intent.

Not retryable/resendable, used, expired retry, replaced, same-email correction, claimed canonical/reservation
and concurrent state conflicts return correlated 409. Invalid JSON/email/IDs return 400 (semantic domain input
failures may be 422). Atomic throttle/capacity denial returns 429 and writes no package state. Post-commit event
coordination failure returns **503 JSend `error`**, distinct from issuance's 503 `fail`, with fixed guidance:
`Recovery outcome uncertain. Read invitation status before another operation.` It may follow a committed state
change and success audit; never assume rollback or automatically repeat. Unexpected persistence/dependency
failures remain safe 500, never private SQL/exception text. Doctrine may close its write manager after rollback;
stop that invocation and use a fresh request/container for further commands, as the internal runner already does.

At-least-once provider attempts can duplicate effects after provider success/outcome-commit uncertainty. Neither
retry nor resend is exactly-once or request-idempotent. Sequential deliberate resends can create another successor;
competing snapshots are fenced by package revision/reference checks. Responses never identify a winning raw
credential, digest, ciphertext, link, provider payload/message/key or exception trace.

## Atomic bounded rate policy

The production adapter is PostgreSQL-backed; no in-memory fallback exists. Successful authorization and semantic
confirmation precede admission; admission precedes policy/target lookup and mutations. Invalid body/unauthorized
requests consume no budget. Valid authorized conflicts and missing-status requests do consume it.

| External configuration | Default | Allowed inclusive range |
|---|---:|---:|
| `APP_INVITATION_RATE_WINDOW` | 60 seconds | 1–86,400 |
| `APP_INVITATION_RATE_ISSUE_LIMIT` | 10 | 1–1,000 |
| `APP_INVITATION_RATE_STATUS_LIMIT` | 60 | 1–10,000 |
| `APP_INVITATION_RATE_RETRY_LIMIT` | 5 | 1–1,000 |
| `APP_INVITATION_RATE_RESEND_LIMIT` | 3 | 1–1,000 |
| `APP_INVITATION_RATE_CORRECT_LIMIT` | 3 | 1–1,000 |
| `APP_INVITATION_RATE_CAPACITY` | 10,000 scope rows | 1–100,000 |

Values must be positive decimal integers, not floats/signs or coercible text. Invalid configuration fails closed.
Compose forwards these external values; refresh **only web** after changing its configuration, following the
[build setup](BUILD.md#explicit-setup). This is deployment configuration, not per-request input.

Keys contain authenticated user UUID, a closed operation enum (`issue`, `status`, `retry`, `resend`, `correct`)
and an empty aggregate scope or validated target UUID, never email, credentials, arbitrary target text or provider
fields. Ordinary/elevated issuance share one actor budget; status has its own. Each recovery admission atomically
consumes both actor/action and actor/action/target scopes under its operation-specific limit. Changing targets
cannot evade the actor budget; operations do not share budgets. Recovery needs room for both scope rows, so a
capacity of one fails closed for recovery. Windows align to Unix epoch multiples; a fixed-window boundary can
permit two adjacent budgets in a short interval (not a sliding-window guarantee).

One short PostgreSQL advisory transaction lock serializes admission, expiry cleanup, key-capacity check and
counter mutation across processes. At most the configured key cap is retained under that policy; expired rows
are deleted on successful admission; denial rolls back cleanup and all tentative scope writes together.
Saturation fails closed with bounded 1–86,400-second retry metadata. An operator lowering
capacity below an existing live row count must allow those windows to expire; no live counter is evicted to grant
extra budget. This correctness-first lock is an intentional contention point, not an unlimited-throughput claim.
The limiter refuses nesting in a business transaction. It supplements exact authorization and database uniqueness;
it does not provide request idempotency, cross-step atomicity or safe issuance retries.

## Verification boundaries

Owned unit tests prove every exact permission subset for both modes, fresh/revoked authority, confirmation,
canonical mapping, known conflicts, retained and uncertain steps, audit attribution and safe package-status reads.
Actual HTTP/PostgreSQL tests cover both managed destinations, no-dispatch JSON/spoofing/source/type matrix,
401/403 gates, canonical conflict/policy drift, independent assignment/audit failure, provider retry/permanent/
accepted outcomes, no-store/correlation/redaction and actual response-to-OpenAPI validation. Separate processes
synchronize after canonical preflight to prove one invitation winner; separate limiter connections block on the
real admission lock to prove one budget winner. Deterministic clock tests require no sleeps.

Recovery adds all permission subsets for each operation; stale/mismatched callback references; actual safe
HTTP/OpenAPI/source/spoofing/status/failure/redaction cases; same-generation retry, replacement and canonical
correction; user/grant/successor/audit rollback; package discovery and direct delivery. Controlled independent
PostgreSQL snapshots cover retry/retry, resend/resend, correction/correction, both retry/resend and correction/resend
winner orders, email-claim contention, and both resend/activation and correction/activation winner orders using
the package activation service (no activation transport). Recovery limiter races prove atomic multi-scope admission.

These are Engineer checks, not independent Review/QA or a production qualification. No real enrollment, real
inbox, deployed scheduler, crash-kill/network-partition simulation, hosted CI or admin/activation UI is implied.
