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
| [TASK-00283](../tasks/00283-TASK.md) | Protect email-change issuance material | ready-for-agent |
| [TASK-00284](../tasks/00284-TASK.md) | Enroll one shared real mail transport | needs-info |
| [TASK-00285](../tasks/00285-TASK.md) | Deliver one exact new-mailbox confirmation link | ready-for-agent |
| [TASK-00286](../tasks/00286-TASK.md) | Recover and inspect email-change credential work | ready-for-agent |
| [TASK-00287](../tasks/00287-TASK.md) | Operate scheduled new-mailbox delivery and recovery | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00015's four-TICKET split. Builds on
[TICKET-00011](00011-TICKET.md)'s bounded package human delivery, not a completed production mailer;
[TICKET-00067](00067-TICKET.md) and [TICKET-00069](00069-TICKET.md) depend on a truly deliverable
confirmation link. Package-owned durable state is protected by
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md). This record enrolled no provider or
scheduler and delivered no credential.

### Approved TASK decomposition

The maintainer approved five independently reviewable slices:

| TASK | Outcome | Blockers and holds |
|---|---|---|
| [TASK-00283](../tasks/00283-TASK.md) | Protect email-change issuance material | TASK-00024; secure generation and purpose-separated cipher, no unfinished request exposure |
| [TASK-00284](../tasks/00284-TASK.md) | Enroll one shared real mail transport | TASK-00040; `needs-info` for approved provider, sender/mailbox/environment scope and external-effect authority |
| [TASK-00285](../tasks/00285-TASK.md) | Deliver one exact new-mailbox confirmation link | TASK-00283 and TASK-00040; direct package claim/invoke/outcome and fixed safe content/link contract |
| [TASK-00286](../tasks/00286-TASK.md) | Recover and inspect email-change credential work | TASK-00285 and TASK-00024; bounded existing-runner extension, narrow audit provenance and independent expiry/status |
| [TASK-00287](../tasks/00287-TASK.md) | Operate scheduled new-mailbox delivery and recovery | TASK-00284, TASK-00286 and TASK-00274; `needs-info` for actual deployment/schedule/worker/key/target authority and qualification |

The inspected cipher allowlists invitation/reset only, email generator/delivery handler/subscriber are unregistered,
and the runner skips email delivery. TASK-00283 provides the small secure issuance prerequisite; TASK-00274 now
consumes it alongside TASK-00273's supported atomic notice seam. It does not depend on completed request capture.
TASK-00285 composes actual package delivery with the shared mail capability, not copied policy or another queue.

The provider invocation carries purpose, stable delivery ID, destination and secret, not User ID/expiry. The
confirmation API requires a User/credential pair. Resolve immutable link facts through the exact public delivery-ID
lookup and validate bindings, never by address/time/current User email or imagined invocation fields. Use configured
trusted HTTPS and fixed escaped text/HTML without tracking or credential leakage; keep payload semantics stable
across same-identity retries. TICKET-00069 consumes the producer contract and owns safe capture/history removal,
explicit confirmation and login return. A generated or received link alone is not its complete browser evidence.

TASK-00286 extends the existing worker and narrowly permits its actual email delivered/failed audit actions;
`credential-recovery` presently permits invitation/reset outcomes only. The actor string is provenance, not
authentication. Preserve package claim/lease/backoff/expiry policy and stop safely on contention/closed Doctrine
state. Status remains protected and nonmutating; dispatched counts are not successful deliveries. Existing expiry
already handles exact email authority/reservation cleanup, including terminal delivery and inactive accounts.

TASK-00287 must schedule expiry independently: current `run` preflight can fail before cleanup when a key/provider
is unavailable. Keys/providers are not required for standalone expiry/status. Preserve original key access across
all outstanding human-delivery purposes and do not invent rotation or physical-destruction guarantees. Qualify
actual process restart and provider acceptance before outcome loss; retain original generation identity and
at-least-once uncertainty. Cancellation/expiry cannot recall mail already in flight, but stale outcomes cannot
restore authority. No new request repairs an uncertain provider result.

**Shared ownership:** TASK-00284 now owns the real transport implementation/enrollment primitive previously
unmapped inside TASK-00277. TASK-00277 consumes it and retains notice-specific schedule, retention and both-stage
acceptance. TASK-00287 independently consumes it for credential operation, plus actual atomic request capture from
TASK-00274. Neither completed recovery journey is a prerequisite of the other. Reuse qualified provider/scheduler
facilities and attributable unchanged evidence; refresh affected integrations rather than adding a second platform
or a duplicate send ceremony. Shared transport never merges notice and credential lifecycle authority.

TASK-00280 now consumes TASK-00287 alongside its notice prerequisites; TASK-00282 consumes that request path.
The missing supplier mapping is resolved, not operational readiness or TASK-00273's contract hold. TASK-00278
retains current self/admin entry authority; TASK-00279 safe status and TASK-00281 cancellation remain independent
of successful sending. TASK-00281's exact-intent/self-audit hold is not resolved by credential delivery planning.
No final account/admin/confirmation browser journey reverse-blocks these suppliers.

Every slice records owned behavior/important package integration evidence and `./bin/build`; infrastructure,
configuration, migrations and generated artifacts are validated directly through owning tools. Real external
checks require explicit authorization and remain distinct from network-free tests/catcher receipts. Any discovered
consumer bug requires reproduction and a failing regression before repair. Source/lock changes from TASK-00273
must requalify affected credential integration; no upstream edit/release authority is implied.

TICKET-00069 now maps TASK-00288's inert landing, consuming TASK-00285's fixed producer contract, and
TASK-00289's explicit confirmation/login return, consuming TASK-00287's actual operation alongside notice/request
and security owners. No completed page reverse-blocks delivery or workers, and receipt alone is not confirmation.
After initial qualification, a held usable credential needs no online provider or delivery decryption to confirm
when atomic persistence is available. Shared-contract changes still require affected credential requalification.

All new orders are unranked; active TASK-00138/TASK-00226 and existing priorities remain unchanged. With
TICKET-00069–00081, twenty-three of the map's 24 TICKETs have written TASK splits; **only TICKET-00082 remains**.
Older Harness/managed review planning is still incomplete. Next planning operation: `/skill:to-tasks TICKET-00082`.
No credential, provider, schedule, request, route or live effect was created by planning. Implementation/worktree
choice, independent review, publication, upstream changes and actual enrollment remain separately authorized.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 387 records,
300 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T220134Z-55317`; summary log:
`.runs/build/ticket-00068-to-tasks.log`. These validate planning and the existing repository, not future cipher/
provider integration, link delivery, worker/schedule enrollment or real email-change operation. Independent review
and publication remain unperformed.
