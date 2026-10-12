---
id: TICKET-00068
epic: EPIC-00015
title: Deliver and recover new-mailbox confirmation credentials
status: ready-for-agent
---

# Deliver and recover new-mailbox confirmation credentials

## Problem statement

The persisted email-change grant and key-free expiry worker do not deliver its confirmation link. The human
credential runner currently skips this purpose; the local/test null provider, no enrolled production provider
and no deployed schedule cannot prove that the user can receive a usable new-mailbox link.

## Solution and boundaries

Qualify and compose the package's current `DeliverEmailChange`, `ExpireEmailChange`, due-work/status contracts
and email-change delivery subscriber with the shared human credential-delivery infrastructure. Enroll a real
configured, secret-safe mail provider and bounded scheduled discovery/retry/expiry operation. The package-owned
grant/delivery state remains the **only** credential queue: issuance, encrypted material and audit commit in the
originating operation; claim commits before provider invocation; the provider receives the purpose-bound
credential for the *new* address outside the transaction; a matching outcome commits separately. Continue due
work after restart independent of post-commit events; reuse the exact delivery-generation identity for provider
idempotency where available. Maintain key access and safe expiry for pending work; clearly distinguish issued,
claimed, delivered, retryable, permanently failed, expired and indeterminate status. This boundary supplies
secret-safe status to [TICKET-00067](00067-TICKET.md) and the credential to the two-step confirmation journey
in [TICKET-00069](00069-TICKET.md), not access to raw material through the UI.

Do not introduce a consumer credential outbox, a second mailer queue claiming package delivery guarantees,
public worker/claim/lease/ciphertext endpoints, a general raw-token console or an exactly-once claim. This
TICKET does not own [TICKET-00066](00066-TICKET.md)'s **separate, secret-free** old-mailbox notices or change
the invitation/reset owners beyond justified shared operational composition.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Deliver new-mailbox link | Direct package `DeliverEmailChange` for an exact discovered generation | Qualified due-work and current grant/delivery state | Package delivery outcome after matching commit | Provider sends purpose-bound confirmation secret only to the reserved *new* mailbox |
| Retry after crash or outage | Scheduled package due-work and matching delivery command | Pending, retryable and expired-lease references plus safe operational status | Existing package outcome facts, not a duplicate request | Same generation/ID is retried until package expiry; uncertainty stays explicit |
| Expire unusable work | Protected `ExpireEmailChange` under package policy | Due expiry and safe grant status | Package expiry fact on confirmed transition | Expired/cancelled/consumed material cannot be reused; package-authorized cleanup proceeds |

## Validation and permissions

Only the qualified internal worker with narrow operational authority may discover, claim, decrypt/invoke,
record and expire work; owner/admin UI sees only safe, scoped statuses. Preserve package deadlines, revisions,
claim fencing and delivery failure categories; do not log or return credentials, URLs, ciphertext, provider
secrets or arbitrary provider messages. The confirmation URL uses the configured exact trusted HTTPS origin
with no credential reflection in analytics or diagnostics. Lost provider results may lead to at-least-once
attempts, not an automatic new request. Provider/schedule/key failure is unavailable or uncertain, never
reported as successful delivery. Credential to the old mailbox is forbidden.

## Acceptance and evidence

- A real configured provider delivers the exact package credential to the intended *new* mailbox; the local
  null/in-memory provider and existing storage evidence remain labeled partial.
- Real PostgreSQL/worker/provider checks exercise rollback, competing claims, cancelled/replaced/expired
  generations, loss of immediate dispatch, process restart, lease expiry, provider acceptance before outcome
  commit and secret-free status/redaction.
- Qualify a scheduled production-like discovery/expiry and recovery path, provider idempotency limits,
  encryption/key retention and distinct credential/old-notice recipient behavior with source/version/lock evidence.
- Focused owned delivery/HTTP integration checks and `./bin/build` pass. No product tests of configuration,
  migrations or seeded infrastructure failures stand in for direct provider/worker qualification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00015's four-TICKET split. Builds on
[TICKET-00011](00011-TICKET.md)'s bounded package human delivery, not a completed production mailer;
[TICKET-00067](00067-TICKET.md) and [TICKET-00069](00069-TICKET.md) depend on a truly deliverable
confirmation link. Package-owned durable state is protected by
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md). This record enrolled no provider or
scheduler and delivered no credential.

TICKET-00066's approved [TASK-00276](../tasks/00276-TASK.md)/[TASK-00277](../tasks/00277-TASK.md) now own
informational-notice dispatch and actual operational qualification, reusing TASK-00040's mail capability and
TASK-00024's existing facilities where appropriate. This TICKET may consume qualified shared transport/provider/
schedule primitives with attributable evidence, but neither their complete notice journey nor a separate notice
queue becomes package credential authority. Identify exact shared primitive mappings during this decomposition;
do not introduce reciprocal whole-TICKET dependencies or require final request/confirmation UI as a supplier.
Existing provider/schedule holds remain and changed integrations need fresh affected evidence. TASK-00273's notice
contract amendment must preserve package credential behavior and be requalified here where relevant.
