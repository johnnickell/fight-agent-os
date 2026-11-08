# Authorized invitation API

TASK-00041 issuance/status and TASK-00042 recovery expose five Bearer-protected operations, not public registration.
TASK-00060's maintainer revision separates writes from reads and uses TASK-00118's safe user detail.

| Operation | Input | Exact current permission |
|---|---|---|
| `POST /api/v1/invitations` | JSON `email`, `mode`, optional boolean `confirm_super_admin` | `INVITE_USERS`; `super_admin` also needs `ASSIGN_SUPER_ADMIN` |
| `GET /api/v1/invitations/{user_id}` | User UUID path only; no query/body/Content-Type | `READ_USERS` |
| `POST /api/v1/invitations/{user_id}/retry` | User UUID path only; no query/body/Content-Type | `MANAGE_USER_INVITATIONS` |
| `POST /api/v1/invitations/{user_id}/resend` | User UUID path only; no query/body/Content-Type | `MANAGE_USER_INVITATIONS` |
| `PATCH /api/v1/invitations/{user_id}/email` | User UUID path and JSON `email` only | `MANAGE_USER_INVITATIONS` |

READ_USERS governs **every** user/status/page read. Manage-only actors may mutate but receive no identity or
status payload and cannot read users, detail or invitation status. READ-only actors cannot mutate. Page management
needs both permissions because the page reads. No role-name bypass, new permission, endpoint or package upgrade
is introduced. The [scoped ownership amendment](../../planning/adr/0001-application-ownership-and-orchestration.md#task-00060-invitation-admission-amendment--2026-11-03)
supersedes earlier requirements for data-returning mutations and redundant Application recovery admission.

## Admission and setup

HTTP Actions declare exact permissions once; `ApiActionSecurity` authenticates and enforces them against the
current request-scoped authoritative principal before input validation, Action execution or target lookup.
Mutation Actions take actor only from that verified authentication, admit the target to this installation,
consume the bounded operation budget and dispatch the existing package command through configured CommandBus.
Unknown mutation targets return generic 403. A request admitted before revocation may finish; the next request
is freshly checked. No second blanket Application gate or revocation-through-commit serialization is promised.

The package's mandatory correction callback binding trusts adapter-admitted in-process dispatch. It is not an
independent public entry point, ambient trust flag or grant from a service's presence. Every actual nontrusted
Console/MCP/other entry must establish its own actor, permission and scope before dispatch. Package business
invariants, generation fences and required transaction/audit atomicity remain package-owned.

Install locked dependencies, migrate the selected disposable/runtime database explicitly and reconcile managed
policy with the owning tools before serving these routes. No setup runs automatically in a gate. See
[managed policy](MANAGED_POLICY.md), [access tokens](ACCESS_TOKENS.md), [local invitation email](INVITATION_EMAIL.md)
and [resource selection](RESOURCE_LIFECYCLE.md). Real provider enrollment and recovery scheduling remain separate.

## Input and wire outcomes

JSON is a unique-key object containing only declared, bounded fields. Email is validated to at most 254 characters
and canonicalized with the package value. Canonical/case-equivalent or reserved addresses conflict; unsupported
Unicode local parts are rejected, not transformed. Actor, permission, role, generation, provider and arbitrary
lifecycle input is never accepted. Bearer extraction rejects non-seekable or over-16,384-byte bodies with 401;
the input middleware's separate 65,536-byte ceiling does not override that narrower boundary.

`mode` is `ordinary` or `super_admin`, mapped only to the corresponding stable managed Role. Elevated issuance
requires literal boolean `true` confirmation (missing/false: 422; wrong type: 400) and ASSIGN_SUPER_ADMIN.
Issuance retains its existing Application sequencing, including fresh authority before independent assignment;
this bounded revision does not refactor issuance, bootstrap or unrelated authentication/registration.

All outcomes use JSend, `Cache-Control: no-store` and sanitized correlation metadata. Actual Action/Responder
OpenAPI attributes are authoritative; exports remain private under `var/`.

- **Status GET:** `{"status":"success","data":<delivery-or-null>}`. Delivery contains exactly `user_id`,
  `status`, `expires_at`, `activation_delivery_id` from the package `InvitationDeliveryStatusView`. A known user
  without a generation returns null; an authorized unknown user returns 404. This endpoint no longer returns
  composite email/lifecycle fields. Read those through [safe user detail](USER_DIRECTORY.md#safe-user-detail-task-00118-implementation).
- **Retry/resend/correction:** `{"status":"success","data":null}` on completed configured command dispatch.
  No user/delivery/ID payload, repository postcondition query, inferred winner or provider receipt. The caller
  must deliberately read authoritative outcomes under READ_USERS. A manage-only integration needs a separately
  authorized reader/operator to inspect outcomes, never an automatic retry.
- **Issuance:** 201 data remains `user_id` (nullable for uncertainty), `assignment`
  (`confirmed`, `unconfirmed`, `unknown`), `audit` (`confirmed`, `unconfirmed`) and nullable package `delivery`.
  These fields describe separately observed steps, not a new delivery lifecycle.

This is a deliberate breaking application contract: update callers and regenerate OpenAPI/validation exports;
there is no old-response compatibility mode. Success does not prove inbox receipt or identify a concurrent winner.

| HTTP | Meaning |
|---|---|
| 201 `success` | Issuance and its recorded steps established; not recipient receipt |
| 200 `success` | Safe status read, or empty completed-command acknowledgement |
| 400 `fail` | Invalid JSON/source/type/bound/allowlist or malformed UUID; no dispatch |
| 401 `fail` | Missing/invalid/stale authentication or rejected credential transport |
| 403 `fail` | Required permission absent or mutation target outside installation admission |
| 404 `fail` | Authorized status target unknown |
| 409 `fail` | Canonical/reservation conflict, policy drift, missing/ineligible recovery work or concurrent transition |
| 422 `fail` | Missing/false elevation confirmation or invalid semantic domain input |
| 429 `fail` | Atomic operation budget/capacity exhausted; integer Retry-After |
| 503 `error` with safe `data` | Issuance partial/uncertain; inspect retained state, never automatically retry |
| 503 `error` | Recovery coordination uncertain; inspect state, never automatically retry |
| 500 `error` | Unexpected internal/dependency failure; safe correlation, no private diagnostics |

Package stored delivery statuses are `pending`, `claimed`, `retry_pending`, `delivered`, `permanent_failure`,
`expired`, `invalidated`. Delivered records provider acceptance, not recipient receipt. Expiry is the grant deadline;
a status read does not invent a clock-driven transition. A successfully recorded invitation may have failed delivery.

## Independent issuance commits

Issuance retains [TASK-00037](../../planning/tasks/00037-TASK.md)'s sequencing. InvitePendingUser and AssignRoleToUser
traverse configured buses and own independent transactions/events. Pending identity, seven-day hashed grant,
encrypted recoverable delivery and user.invited audit commit first; delivery can start before assignment.
A later failure retains the invitation and possibly sent mail. Separate confirmation audit records safe actor,
target, Role ID and membership_confirmed result. Crash/acknowledgement loss can leave committed membership without
confirmed audit. An exception is not proof of rollback; no compensation, transparent retry or all-step atomicity
is promised. Safe queries inspect current state, including after the write manager closes.

The [shared safe-error contract](SAFE_HTTP_ERRORS.md) owns exact category messages, headers, diagnostics and
strict client decoding. For 503 issuance, retain correlation/known ID and stop automatic issuance. A trusted operator inspects identity,
roles, audit and package delivery. Eligible ordinary membership can use the separately authorized console path;
elevated or unknown-state repair needs investigation. Recovery below does not resume issuance assignment.

## Atomic recovery and failure mapping

- RetryInvitationDelivery accepts only live issued, retryable failed work. It preserves generation, credential,
  expiry and provider idempotency identity; it is not an arbitrary reset.
- ResendInvitationDelivery atomically revokes the latest issued grant (including expired issued authority) and
  creates a fresh purpose-bound seven-day generation.
- CorrectPendingInvitation accepts only a pending user with a matching issued invitation. Canonical/reserved claims
  conflict. User email, predecessor revocation, successor, encrypted delivery and required safe audit commit together
  or roll back. Assignments remain; active-user email change is not provided.

The package handlers coordinate those transitions and user.invitation_delivery.retry_requested,
user.invitation_delivery.resent or user.pending_invitation_corrected audit on the same transaction connection.
Existing business locks, expected-state comparisons and uniqueness constraints remain. There is no outer consumer
transaction, consumer delivery queue, private handler invocation or post-command repository peek. Commands stay void;
completed dispatch is not independently observed proof of an effect. Configured bus/filter semantics are trusted
composition, not permission to infer state from unrelated repository changes.

Typed package not-retryable/not-resendable/pending-correction failures map to safe 409. The locked package still
uses plain LogicException for two specific compare-and-replace rejections (activation generation changed;
pending email changed/reserved). Only those exact plain-package rejection messages are deliberately mapped in
resend/correction; arbitrary LogicException and subclasses propagate to safe 500, never a blanket conflict.
The installed package has no more specific category for these two cases; this mapping adds no vendor patch.

EventDispatchFailed maps to 503 with fixed guidance:
`Recovery outcome uncertain. Read invitation status before another operation.` It can follow committed state
and audit. Never assume rollback or automatically replay. Unknown persistence/dependency errors remain safe 500;
those also cannot prove no prior commit. Stop a failed invocation and use a fresh request/container as appropriate.

Retry/resend subscribers may process delivery after commit. Correction's event has no immediate delivery subscriber;
normal package discovery and the internal runner find its durable generation. No scheduler is implied. At-least-once
attempts can duplicate effects after provider success/outcome-commit uncertainty. Neither retry nor resend is
request-idempotent or exactly once; another deliberate resend can create another successor.

Historical TASK-00042 qualification used Access Control v0.6.0 at dc4621d13851dd08cdf85ed9715ead24b1051e80;
v0.5.0 failures remain historical in that TASK. Current unchanged lock: Access Control **v0.8.0** at
319738255e682d587642b67856039f882b9588d6 and Common **v1.4.1** at a482c220d312d2ef7762c3f0d85d9fb76a7021fa.

## Browser convergence and privacy

The [ordinary issuance form](ORDINARY_INVITATIONS.md) at `/app/admin/invite` sends only canonical email and
fixed ordinary mode under INVITE_USERS. It retains only a strictly decoded safe target ID, distinguishes
accepted/rejected/unconfirmed work and offers status only with READ_USERS. It does not change the existing
issuance sequencing or implement TASK-00577's architecture cutover.

The status page uses the sole AuthorityCache, strict codecs and memory-only presentation. Each load deliberately reads
invitation delivery then safe user detail. They are separately authorized committed observations, not one atomic
cross-request snapshot. Neither uses a directory scan or route/form email as authority. Failed, refused or retired
reads yield no partial identity/status presentation. The existing detail decoder is shared, not copied.

Retry eligibility is presentation only; package admission is final. Resend/correction use named Cancel-first
confirmations explaining old-link invalidation, seven-day replacement and atomic email correction. Controls need
both permissions. Mutation transport itself needs management only and accepts only the empty acknowledgement.

Every mutation attempt retires outstanding status generations. On completion/conflict/throttle/uncertainty the
submitting page clears correction and performs one fresh status/detail load. Directory generations are invalidated;
mounted lists refetch, mounted detail clears and requires explicit Reload, and returning pages read fresh.
Relevant external work retracts already-adopted invitation state and enables deliberate Reload, not automatic
mutation replay. Late deadline completions invalidate again. Unmount, target and authority scopes fence obsolete
responses; unrelated targets remain usable. Duplicate activation is suppressed. Reload's busy semantics preserve
keyboard focus; confirmation cancel/escape/return and outcome heading focus remain intentional.

Eight-second client bounds do not cancel server commits. No correction or credential enters persistence, history,
analytics, diagnostics or screenshots. Only authorized canonical detail is rendered; no grants/digests/ciphertexts,
links, destination history, provider payload/key/message, audit internals, SQL or stack are presented.

## Bounded rate policy and verification

| Configuration | Default | Inclusive range |
|---|---:|---:|
| APP_INVITATION_RATE_WINDOW | 60 seconds | 1–86400 |
| APP_INVITATION_RATE_ISSUE_LIMIT | 10 | 1–1000 |
| APP_INVITATION_RATE_STATUS_LIMIT | 60 | 1–10000 |
| APP_INVITATION_RATE_RETRY_LIMIT | 5 | 1–1000 |
| APP_INVITATION_RATE_RESEND_LIMIT | 3 | 1–1000 |
| APP_INVITATION_RATE_CORRECT_LIMIT | 3 | 1–1000 |
| APP_INVITATION_RATE_CAPACITY | 10000 scope rows | 1–100000 |

Positive decimal integers only; invalid configuration fails closed. Status/issuance use their own actor/action
budgets; recovery atomically consumes actor/action and actor/action/target. Changing target cannot evade the
actor budget. Recovery needs two available rows; fixed-window boundaries may admit adjacent budgets in a short
interval. Keys contain only trusted UUIDs and closed operations, never email/credentials/provider input.

One short independent PostgreSQL advisory transaction serializes budget/capacity admission and expiry cleanup.
Denial rolls back tentative writes; the limiter refuses business-transaction nesting. Invalid/unauthorized input
consumes no budget; admitted valid conflicts do. Mutation target admission precedes its budget. No universal
throughput, sliding-window or request-idempotency guarantee is implied.

Owning regressions cover all read/manage combinations, empty mutation responses, separately forbidden reads,
no effects on denied writes, exact package command inputs, intentional versus internal failures, post-commit
uncertainty, rollback/audit/generation/claim invariants, controlled independent-connection races and authoritative
GET convergence. Client tests cover exact wire contracts, retirement, duplicate suppression, deliberate reload,
volatile correction, keyboard focus and no replay. OpenAPI/validation freshness, syntax/static/dependency tools
and the canonical build qualify their own boundaries. Independent Review and applicable QA remain required;
local tests/catalog captures are not real-inbox, scheduler, universal accessibility or privacy certification.
