---
id: TICKET-00066
epic: EPIC-00015
title: Durably notify the old mailbox of email-change request and completion
status: ready-for-agent
---

# Durably notify the old mailbox of email-change request and completion

## Problem statement

A human must learn at their *old* mailbox when an email change is accepted and again when it completes, whether
initiated by the owner or an administrator. Neither a synchronous send nor an in-memory event subscriber ensures
that a notification survives a crash after the package commits; after confirmation the canonical email is already
the *new* address. Silent lost notices or a completion notice to the wrong recipient defeat the approved journey.

## Solution and boundaries

Establish an Agent OS-owned, durable, attributable **informational** notice intent and protected delivery/recovery
boundary for each accepted request and each successful confirmation. Bind a stable separate notice identity,
original/old canonical recipient, stage and exact owning change generation/transition at commit; preserve that
recipient across promotion. Dispatch only after the accepted state commits, use provider idempotency where
qualified, retry finite due work after restart and report delivered/retryable/uncertain outcomes honestly. A
cancellation does not retroactively erase the request notice; a failed, duplicate or stale confirmation never
creates another completion notice. Record the initiating actor distinctly from the target and the later
confirming credential holder where package evidence permits, without putting the secret in notice state or audit.

**Contract gate:** the installed package's `RequestEmailChangeHandler` and `AuthenticationService::confirmEmail()`
each own and commit their transaction, then emit an event. A consumer after-commit subscriber, outer nested
transaction, audit-repository side effect or unverified provider call inside that transaction is **not** an
acceptable atomic intent seam. Inspect the actually chosen package/source and qualify a supported way to
coordinate each accepted transition and required notice intent without duplicating package authority. If none
exists, stop and obtain explicit package/owner contract resolution and a tagged, locked integration before
exposing the journey; do not quietly drop the notice or manufacture a post-commit guarantee. This requirement
owns the integration boundary, not new package policy or a second credential-delivery queue.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record request notice | Package `RequestEmailChange` via the qualified atomic notice coordination boundary | Authoritative original address, actor/target and accepted generation | Package request fact plus Agent OS notice intent only when durably committed | One recoverable secret-free intent addressed to the *old* mailbox, not the new one |
| Record completion notice | Package `AuthenticationService::confirmEmail()` via the qualified boundary | Original recipient captured before promotion and actual consumed generation | Package confirmation fact plus Agent OS completion intent only after success | One recoverable intent still addressed to the *old* mailbox; no second effect on replay |
| Send and recover notices | Protected Agent OS notice dispatcher/worker | Due notices and safe operational outcomes, not package credential claims | Owned notice outcome facts after provider result; no duplicate package business event | Provider called outside business transaction; stable ID reused on retry; uncertain outcomes remain visible |

## Validation and permissions

No public command, browser body, email address or caller-supplied target can enqueue a notice independent of the
accepted owning operation. Protected dispatch needs least-privilege operational authorization, bounded discovery,
rate/capacity policy, reliable scheduling and secret-free diagnostics. Recipient is personal data, restricted to
the owner/qualified operator where necessary and retained only under an explicit policy; neither notice includes
the new-mailbox credential, its link, grant material or an old-mailbox approval/cancel action. No provider call
occurs inside a business transaction. An ambiguous provider outcome may produce a duplicate message, never an
exactly-once assertion or a false delivered status. The notification provider and its schedule must be really
enrolled before advertising the notices as operational.

## Acceptance and evidence

- Qualify and record the exact selected package release, source/lock, supported atomic integration seam and any
  necessary owner amendment. If the seam is absent, dependent request/confirmation exposure remains blocked.
- With real PostgreSQL, rollback, process failure immediately after accepted commit, concurrent cancel/confirm,
  competing dispatch and provider-acceptance/outcome-commit uncertainty do not silently lose an accepted notice
  or misaddress its completion; retries retain stable stage-specific identities and safe status.
- Both owner- and administrator-initiated requests result in old-mailbox notices on accepted request and
  confirmation, without credential or private provider data in messages, audit, URLs, APIs or logs.
- Owned notice behavior/provider integration tests, direct scheduler/provider enrollment checks and
  `./bin/build` pass; an in-memory event or null provider is not production-delivery evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00015's four-TICKET split after
[WF-032](../wayfinder/tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md). Supplies a hard
integration gate to [TICKET-00067](00067-TICKET.md) and [TICKET-00069](00069-TICKET.md). Package credential
state and provider ownership remain governed by [ADR 0001](../adr/0001-application-ownership-and-orchestration.md)
and [ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md); this notice intent is a distinct
Agent OS-owned informational effect, not a substitute credential queue. No notice, provider or seam was
implemented by this record.
