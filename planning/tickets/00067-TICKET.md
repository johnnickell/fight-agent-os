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
| None | — | — |
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
TICKET's later exposure TASKs alongside the still-unallocated TICKET-00068 credential primitives. Current self/
admin authority, the protected Permission/callback, safe pending status and cancellation remain owned here;
notice implementation does not replace `UnavailableEmailChangeAdministration` or make arbitrary callers trusted.
Keep early authority primitives below final browser integration, without reverse-blocking notice capture or
operation on the finished user journey. Provider delay does not erase accepted intent, while lack of a supported
atomic intent seam blocks request exposure. This TICKET still needs its own approved TASK decomposition.

Next planning operation: `/skill:to-tasks TICKET-00067`; no implementation or live effects are authorized.
