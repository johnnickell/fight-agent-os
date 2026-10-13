---
id: TICKET-00067
epic: EPIC-00015
title: Request inspect and cancel a pending human email change
status: ready-for-agent
---

# Request inspect and cancel a pending human email change

## Problem statement

An active user needs to initiate and safely inspect/cancel a move to a different mailbox; an authorized Super
Admin needs the same limited actions for another active user. A direct edit or reuse of pending-invitation
correction would bypass the new mailbox, while a role label or client-provided target would enable cross-user
account takeover.

## Solution and boundaries

Provide authenticated self-service request, safe current pending/delivery status and cancellation in the account
journey. Resolve the self-service target from the *current active principal*, never an input user ID. On the
existing authorized user-detail surface, offer Super Admin request/cancel and safe target status with separate
current actor/target validation and explicit confirmation. Reconcile a distinct managed
`SUPER_ADMIN_ONLY` email-change administration Permission initially granted only to `ROLE_SUPER_ADMIN`, then
replace `UnavailableEmailChangeAdministration` only after the selected package callback and every entry path
are qualified. Use the package `RequestEmailChange` and `CancelEmailChange`, authoritative User/reservation/grant
state, safe package status where supported, and [TICKET-00066](00066-TICKET.md)'s durable old-mailbox request
notice. Show truthful issued vs delivered vs pending vs expired/cancelled states and recovery steps, without
exposing a grant, recipient credential, claim, ciphertext or audit internals. A request does not assert that the
new mailbox has received mail or that the active email has changed.

Do not alter [EPIC-00004](../epics/00004-EPIC.md)'s historical direct-edit deferral, the existing user
administration, invitation or password-reset TICKETs. No general profile editor, arbitrary active-email set,
pending-invitation correction for active users, self-service cross-user ID, automatic replacement of pending
changes, administrator confirmation bypass or role-name authorization belongs here.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Request own change | Package `RequestEmailChange` with current active actor/target and new canonical address | Current identity, reservation, latest grant and safe status | Package `EmailChangeRequested` after commit; old-mail request notice intent under TICKET-00066 | New address reserved, one-hour confirmation grant and package-owned encrypted delivery committed without promoting email |
| Request another user's change | Same package command after distinct authorized actor/target decision and confirmed intent | Safe target detail and fresh effective managed Permission | Package request and attributable admin audit; old-mail notice on accepted request | Same new-mailbox proof required; administrator does not receive credential |
| Inspect or cancel pending change | Package `CancelEmailChange` for cancellation; N/A for read | Current pending/grant/delivery safe status for owner or authorized administrator | Package `EmailChangeCancelled` only on successful cancellation | Grant and reservation become unusable; status reflects races or recovery without leaking tokens |

## Validation and permissions

Self-service requires active authenticated owner; cross-user request/cancel/status require a distinct current
`SUPER_ADMIN_ONLY` Permission, package `EmailChangeAdministrationAuthorization` callback, fresh actor/target
scope and action-specific server checks on HTTP, console and direct-bus entry paths. The managed tier restricts
assignment; `READ_USERS`, `UPDATE_USERS`, invitation rights, `ROLE_SUPER_ADMIN` alone or a browser projection do
not authorize change. Canonicalize and validate a *different* address; package reservations, revisions, latest-
grant and expiry fences prevent collision with existing Users, invitations and other changes, including races
with cancellation, confirmation, correction and delivery. Use bounded, redacted status and safe conflict/refetch
responses; no silent overwrite. Audit distinguishes self request/cancel and administrator action without secrets.

## Acceptance and evidence

- Real active owner and authorized Super Admin can request/cancel for permitted targets and inspect only their
  safe scope; anonymous, stale/disabled, missing Permission and cross-user self-service attempts fail closed
  through browser, HTTP and direct dispatch.
- Real PostgreSQL conflict/race tests cover distinct-address rule, competing reservations, repeated requests,
  cancellation versus confirmation and correct pending/active address and generation outcomes. Request and
  cancellation never directly promote the destination.
- Accessible account and user-detail states show pending, unavailable, expired, delivery uncertainty,
  confirmation/cancellation and recovery truthfully without credentials. Request cannot go live before old-mail
  notice intent and new-mail delivery/recovery boundaries are qualified.
- Record selected tagged package/source/lock, owned authorization/browser/HTTP tests, integration evidence and
  `./bin/build`. Existing grant rows or package tests alone do not establish a usable product journey.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00278](../tasks/00278-TASK.md) | Enforce current email-change authority | ready-for-agent |
| [TASK-00279](../tasks/00279-TASK.md) | Inspect current pending changes safely | ready-for-agent |
| [TASK-00280](../tasks/00280-TASK.md) | Request an own-address change through the account journey | needs-info |
| [TASK-00281](../tasks/00281-TASK.md) | Cancel a pending change without affecting a successor | needs-info |
| [TASK-00282](../tasks/00282-TASK.md) | Administer another user's email change from user detail | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00015's four-TICKET split. Requires
[TICKET-00066](00066-TICKET.md)'s atomic notice boundary and
[TICKET-00068](00068-TICKET.md)'s credential provider/recovery before advertising usable request; relies on
[EPIC-00004](../epics/00004-EPIC.md)'s active browser authentication/current principal and
[TICKET-00030](00030-TICKET.md)'s user detail without changing their ownership. Extends the explicit WF-032
approval, not a retroactive EPIC-00004 rewrite. No permission grant or request endpoint was created here.

TICKET-00066's approved split supplies [TASK-00274](../tasks/00274-TASK.md)'s atomic request-notice capture
behind [TASK-00273](../tasks/00273-TASK.md)'s unresolved supported public contract, and
[TASK-00277](../tasks/00277-TASK.md)'s operational notice readiness. Map those concrete prerequisites to this
TICKET's exposure TASKs alongside TICKET-00068's now-allocated TASK-00283–00287 credential primitives. Current self/
admin authority, the protected Permission/callback, safe pending status and cancellation remain owned here;
notice implementation does not replace `UnavailableEmailChangeAdministration` or make arbitrary callers trusted.
Keep early authority primitives below final browser integration, without reverse-blocking notice capture or
operation on the finished user journey. Provider delay does not erase accepted intent, while lack of a supported
atomic intent seam blocks request exposure.

### Approved TASK decomposition

The maintainer approved five independently reviewable slices:

| TASK | Outcome | Blockers and holds |
|---|---|---|
| [TASK-00278](../tasks/00278-TASK.md) | Enforce current email-change authority | TASK-00036 and TASK-00117; complete managed policy extension and all supported caller checks, no mutation exposure |
| [TASK-00279](../tasks/00279-TASK.md) | Inspect current pending changes safely | TASK-00278 and TASK-00051; scoped query/API and real read-only account panel |
| [TASK-00280](../tasks/00280-TASK.md) | Request an own-address change through the account journey | TASK-00279, TASK-00274, TASK-00277 and TASK-00287; supplier mapping resolved, existing notice-contract and operational qualification holds remain |
| [TASK-00281](../tasks/00281-TASK.md) | Cancel a pending change without affecting a successor | TASK-00279; `needs-info` for supported exact-intent/generation fencing and atomic self-cancellation audit |
| [TASK-00282](../tasks/00282-TASK.md) | Administer another user's email change from user detail | TASK-00280, TASK-00281 and TASK-00123; complete protected cross-user API/UI integration behind their unresolved holds |

TASK-00278 extends the complete stable managed catalog rather than replacing it with a one-Permission policy.
The distinct administration Permission is `SUPER_ADMIN_ONLY`, initially assigned only to managed Super Admin;
self-service requires the current active owner, not that Permission. The package callback only checks actor !=
target, so it cannot authenticate a supplied actor or protect self calls by itself. Every supported HTTP and
non-HTTP entry needs trusted current actor/target policy; unsupported paths deny. Callback replacement alone
never opens unfinished request/cancel operations. Existing detail-read rights and email-action rights stay distinct.

The inspected `UserView` has no pending-change fields and `CredentialDeliveryStatusView` describes one delivery,
not current change usability. TASK-00279 composes a coherent safe observation without returning a grant or
inventing a second authority. GET does not expire, repair, send or mutate. Issued, delivered, confirmed, expired,
cancelled and unknown remain distinct; absent or incoherent evidence is not a successful empty state. The account
panel uses the shared `/me` owner and volatile generation-fenced data, not a stored draft or private URL state.

The inspected `CancelEmailChange` takes only actor/target IDs and selects the latest eligible grant inside its
own transaction. Expected-state repository comparisons protect that loaded state, not the change the human
reviewed earlier; a browser revision/preflight cannot prevent delayed intent from cancelling a successor.
The handler audits only administrator cancellation. TASK-00281 must qualify supported exact-intent fencing,
original-outcome observation and atomic required self audit, obtaining separate owner/tagged-contract resolution
if absent. TASK-00273's request/confirmation seam does not automatically resolve cancellation. No nested
transaction, copied handler, hidden audit-repository policy or after-commit audit workaround is permitted.

Request and cancellation remain separate paths after safe status. TASK-00280 waits for qualified old-notice and
new-mail delivery; **TASK-00281 does not depend on sending readiness or TASK-00280**. Provider failure cannot
remove the ability to cancel supported current authority. Cancellation preserves accepted request notices,
does not promote an address and cannot recall already-sent mail. Required state/audit writes roll back together;
post-commit event failure/lost acknowledgement may follow success and never authorizes blind mutation replay.
The request command also supplies no client idempotency key; only supported original-result evidence can resolve
uncertainty, not repeated equal input or the latest address alone.

TASK-00282 extends the existing user detail under independent route/action checks, explicit target/intent
confirmation and current callback authority. Reuse the request/status/cancel primitives, not another pipeline.
Administrator action remains attributable and cannot receive the new-mail credential or bypass its confirmation.
TICKET-00069 retains actual explicit confirmation, client/session invalidation and login return. TICKET-00068
now supplies TASK-00283 protected issuance, TASK-00284 shared real transport, TASK-00285 exact link delivery,
TASK-00286 recovery and TASK-00287 actual operation. TASK-00280 consumes TASK-00287; TASK-00274 consumes only
early TASK-00283 and TASK-00277 consumes shared TASK-00284, not completed credential recovery. Missing supplier
mapping is resolved, not the supported notice/cancellation contracts or actual provider/schedule readiness.
No supplier is reverse-blocked on a completed account/admin journey; safe status/cancel stay independent of sending.

TICKET-00069 now maps TASK-00288's inert landing and TASK-00289's explicit same-secret confirmation/login
return. TASK-00289 consumes TASK-00282's actual self/admin initiation and exact cancellation chain, with no reverse
blocker. It owns full mailbox/browser confirmation, session/client invalidation and fresh-login acceptance;
request/status/cancel primitives retain their owners and separate holds. Exact confirmation-reservation binding
now needs explicit qualification through TASK-00273/00275; it does not resolve TASK-00281's intent/self-audit gate.

Each user-facing slice owns real safe HTTP/client behavior, PostgreSQL/package integration and applicable
accessible browser evidence; no proof-only final TASK is added. All new orders are unranked; active
TASK-00138/TASK-00226 and existing priorities remain unchanged. With TICKET-00068–00076, eighteen of the map's 24
TICKETs have approved written TASK splits; **six remain, TICKET-00077–00082**. Older Harness/managed review
planning is still incomplete. Next planning operation: `/skill:to-tasks TICKET-00077`. No permission, request, cancellation, UI or provider is live
from planning; implementation/worktree choice, upstream changes, review, publication and operational effects remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 382 records,
295 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T213728Z-53222`; summary log:
`.runs/build/ticket-00067-to-tasks.log`. These validate planning and the existing repository, not future email
administration authority, coherent status, request/cancellation contracts, browser journeys or real delivery.
Independent review and publication remain unperformed.
