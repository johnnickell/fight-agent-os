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
| [TASK-00273](../tasks/00273-TASK.md) | Qualify supported atomic notice coordination | needs-info |
| [TASK-00274](../tasks/00274-TASK.md) | Record an old-mailbox notice with each accepted request | ready-for-agent |
| [TASK-00275](../tasks/00275-TASK.md) | Record completion notices without losing the original recipient | ready-for-agent |
| [TASK-00276](../tasks/00276-TASK.md) | Dispatch one notice with protected recoverable outcomes | ready-for-agent |
| [TASK-00277](../tasks/00277-TASK.md) | Operate scheduled notice recovery and safe inspection | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00015's four-TICKET split after
[WF-032](../wayfinder/tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md). Supplies a hard
integration gate to [TICKET-00067](00067-TICKET.md) and [TICKET-00069](00069-TICKET.md). Package credential
state and provider ownership remain governed by [ADR 0001](../adr/0001-application-ownership-and-orchestration.md)
and [ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md); this notice intent is a distinct
Agent OS-owned informational effect, not a substitute credential queue. No notice, provider or seam was
implemented by this record.

### Approved TASK decomposition

The maintainer approved these five independently reviewable slices:

| TASK | Outcome | Blockers and holds |
|---|---|---|
| [TASK-00273](../tasks/00273-TASK.md) | Qualify supported atomic notice coordination | `needs-info`: actual supported package seam/owner resolution and any tagged release remain unmapped; no allocated TASK blocker |
| [TASK-00274](../tasks/00274-TASK.md) | Record an old-mailbox notice with each accepted request | TASK-00273 and TASK-00283; ready behind the unresolved notice contract, not exposed |
| [TASK-00275](../tasks/00275-TASK.md) | Record completion notices without losing the original recipient | TASK-00274, including the qualified seam transitively |
| [TASK-00276](../tasks/00276-TASK.md) | Dispatch one notice with protected recoverable outcomes | TASK-00274 and TASK-00040's shared mail capability; independent of completion capture |
| [TASK-00277](../tasks/00277-TASK.md) | Operate scheduled notice recovery and safe inspection | TASK-00275, TASK-00276, TASK-00024 and TASK-00284; `needs-info` for actual provider/schedule, protected target and retention/failure-policy enrollment |

TASK-00273 is a bounded contract/consumer-adoption chore, not authorization to modify or release upstream.
The inspected v0.5.0 source remains `46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`; implementation must qualify
its actual selected tagged/locked public contract. Request commits before an event carrying the new address;
confirmation commits before an event carrying only User ID/time. Neither supplies the required durable atomic
old-recipient/generation handoff. Merely documenting that gap does not complete qualification.

TASK-00274/00275 each own a complete capture use case across business coordination and PostgreSQL, using one
canonical transaction for required state/audit/notice intent. No notice-write failure may leave an accepted
unnotified transition. Post-commit publisher failure is not rollback. Capture exact original recipient and change/
stage identity; cancellation never erases the request notice, duplicate confirmation never creates another intent,
and a later same-address generation cannot inherit another change's notice. Preserve initiating actor, target and
what the confirmation proof actually establishes without inventing authenticated-person attribution.

TASK-00276 owns only informational notice attempts, not another credential queue. It reuses TASK-00040's shared
mail capability and stable immutable notice identity/content across retries, with committed claim, external send
and fenced outcome phases. Provider acceptance, actual mailbox receipt and certainty differ. Fixed content has
no confirmation credential/link, approval, cancel or undo control. No guaranteed arrival order or exactly-once
claim follows, and credential expiry does not define notice retention or discard accepted notices.

TASK-00277 reuses existing operational facilities without treating TASK-00024 as an enrolled scheduler/provider.
Its finite independent recovery, protected status, explicit retention and actual safe-target provider/schedule
qualification supply readiness. Retention must preserve unsettled attempts/deduplication and original-recipient
correlation until completion can no longer require it; successful request delivery alone is insufficient.
Missing enrollment is a hold, not success from null/in-memory/catcher results. Subsequent provider delays cannot
undo a committed account change, postpone session revocation or erase notice intent.

TICKET-00067 consumes TASK-00274 and TASK-00277 for request exposure and retains all self/admin entry authority,
current status and cancellation UI. TICKET-00069 consumes TASK-00275 and TASK-00277 for confirmation exposure,
with its explicit user action and login handoff. Both still require TICKET-00068's qualified new-mail credential
boundary. That TICKET may reuse qualified mail/operational facilities, never the notice queue or its lifecycle.
TICKET-00068 now supplies TASK-00283's early issuance protection to TASK-00274 and TASK-00284's shared real
transport to TASK-00277. TASK-00277 retains notice-specific schedule/retention and both-stage acceptance;
TASK-00287 independently consumes the same transport and accepted request capture for credential operation.
No complete notice/credential recovery journey blocks the other and no second provider is introduced. Capture and notice
operation do not depend on completed downstream browser journeys, and controlled provider qualification never
authorizes replacing the current administration denial or opening unfinished routes.

TICKET-00067's subsequent TASK-00278/00279 now own early authority/safe status; TASK-00280 consumes
TASK-00274/00277 for self request and TASK-00282 integrates administration. TASK-00281 independently qualifies
exact confirmed cancellation and required self audit; its inspected latest-target command has no expected-
generation input, and this notice seam does not implicitly resolve that separate hold. Status/cancellation do
not depend on sending readiness; final account/admin journeys do not reverse-block notice primitives.

TICKET-00069 now maps TASK-00288's inert landing and TASK-00289's explicit confirmation/login return.
TASK-00289 consumes TASK-00275/00277 and actual request/delivery owners without reverse dependencies. Its source
inspection adds exact reservation-revision qualification to TASK-00273/00275: current `confirmEmail()` checks
latest grant/credential/destination but does not explicitly compare the bound revision or call `matchesReservation()`.
Qualify supported-writer and same-address successor/ABA behavior; this is an unresolved guarantee, not a
reproduced exploit or authorization for a vendor patch. Supported atomic notice and operational holds persist.

All new TASKs are unranked; active TASK-00138/TASK-00226 and existing authored orders remain unchanged. With the
TICKET-00067–00076 splits, eighteen of the map's 24 TICKETs have written TASK splits; **six remain, TICKET-00077–00082**.
Older Harness/managed review planning is still incomplete. Next planning operation: `/skill:to-tasks TICKET-00077`.
No request, confirmation, notice, provider or schedule is live from this decomposition. Implementation/worktree
choice, upstream authorization, independent review, publication and operational enrollment remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 377 records,
290 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T212257Z-51118`; summary log:
`.runs/build/ticket-00066-to-tasks.log`. These validate planning and the existing repository, not the future
atomic notice seam, old-mailbox delivery, provider/schedule enrollment or complete email-change journey.
Independent review and publication remain unperformed.
