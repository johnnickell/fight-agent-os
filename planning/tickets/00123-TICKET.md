---
id: TICKET-00123
epic: EPIC-00021
title: Deliver real account and recovery email
status: ready-for-agent
---

# Deliver real account and recovery email

## Problem statement

Null/in-memory delivery and local Mailpit cannot deliver real account or recovery email to John's inbox. Production
needs the selected provider and scheduled recovery of existing durable delivery work, without losing credentials,
leaking them or creating a competing queue/retry policy.

## Solution and boundaries

Deliver **Resend Free HTTPS** for the existing account/credential email use cases under
[EPIC-00021](../epics/00021-EPIC.md), reusing [TASK-00284](../tasks/00284-TASK.md)'s existing shared real transport/
enrollment primitive rather than implementing a second adapter. This TICKET owns invitation/reset environment
integration and scheduled operation, not email-change or notice lifecycle. Consume
[TICKET-00011](00011-TICKET.md)'s qualified package-owned grant/delivery state, direct handlers, bounded due-work
runner, stable delivery-generation identity, retry/lease/failure semantics and safe status. Do not reopen that
completed foundation merely to duplicate it; production provider enrollment and scheduling were explicitly excluded
there; the shared primitive is now mapped to TASK-00284, with this TICKET supplying account-mail integration and
scheduling. Reuse completed TASK-00040/00053 content/link mapping and TASK-00024's bounded runner.

Qualify current Resend plan eligibility, limits, request/idempotency semantics and actual inbox delivery under
bounded later authorization. The historical 3,000/month and 100/day Free figures are research, not entitlement or
a delivery guarantee. Gmail is John's inbox, not an SMTP fallback. No automatic paid upgrade or unapproved provider
substitution. Explain quota/rate/rejection/unavailability honestly and follow existing package retry/expiry policy.

Supply explicit sender/domain enrollment and DNS authentication procedures that preserve existing inbound mail.
Changes need their own target and effect approval; do not infer account access from planning consent. Keep provider
credentials protected and environment-specific. Staging mail goes only to explicitly authorized test recipients
or the selected safe simulation path; it cannot inherit production delivery authority.

Configure the existing bounded runner cadence, supervision and scheduling through
[TICKET-00122](00122-TICKET.md). Observe scheduler activity, due-work progress and provider outcomes distinctly;
provider acceptance is not proof of inbox receipt. Reconcile interruptions through the original package generation/
claim, not a new message identity or blind manual resend. Durable processing is at least once, not exactly once.
Integrate recovery suppression from TICKET-00124 and deployment interruption handling from TICKET-00125.

Out of scope: a consumer-owned outbox, invitation/reset/email-change policy duplication, additional account
journeys, a general job platform, Gmail/SMTP fallback, marketing mail, paid-plan enrollment and a monitoring vendor.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Enroll an approved sender | Explicit authorized provider/DNS setup; no new Domain command | Current account/plan, sender/DNS status and inbound-mail configuration | Provider receipts only | Bounded enrollment/authentication changes without disrupting inbound delivery |
| Deliver committed account work | Existing direct package delivery handler after committed claim | Exact generation, current claim/expiry and environment configuration | Existing package outcome/audit facts | Protected HTTPS send outside the business transaction; matching safe outcome persisted |
| Recover due or interrupted work | Existing bounded runner/package handlers | Due/leased/retryable/terminal state and original provider evidence | Existing package outcomes only | Retry/reconcile the original identity under package policy, not a parallel queue |
| Inspect delivery degradation | None; existing safe status plus bounded operator inspection | Scheduler health, due backlog, safe quota/failure and observed inbox result | None from reads | Distinguish not attempted, accepted, failed, uncertain and independently observed receipt |

## Validation and permissions

End-user permissions remain with the originating account use case; delivery uses a least-privilege internal
capability limited to valid discovered work and the approved environment. Account access, DNS mutations and live
messages need bounded authorization. Preserve stable delivery identity and current-claim fencing across provider
success/outcome-commit interruption. Do not infer safe replay from a timeout or promise provider deduplication
beyond the actual qualified window/contract.

Raw credentials, tokens, URLs, plaintext bodies, ciphertext and provider secrets never enter safe views, normal
logs, prompts, screenshots or ordinary audit evidence. Protect invocation material and sanitize provider errors.
Read-only health does not send messages, change configuration or initiate enrollment. Restored environments remain
suppressed until current authority and original operations reconcile with their owners.

## Acceptance and evidence

- The real adapter consumes the existing package contract without a new queue, retry engine or external call inside
  the business transaction. Current provider semantics and limits are recorded with evidence and limitations.
- An explicitly authorized real account/recovery message reaches the intended inbox through the enrolled sender;
  provider acceptance and inbox receipt are distinct observations. Existing inbound mail remains intact.
- Quota, rejection, outage, expired grants, duplicate processing and interrupted outcomes use original identity and
  package fencing; no blind resend, automatic upgrade, secret leak or exactly-once claim occurs.
- Existing scheduling discovers/reconciles durable work after restart and exposes stale/missing scheduling or
  delivery separately from application health. Staging and isolated restoration cannot send production mail.
- Test owned adapter/application behavior and important integration contracts; qualify provider/DNS/schedule
  mechanics directly through owning tools. No product-suite tests of config, cron wrappers or runbook prose.
- Full `./bin/build`, independent Review/QA and bounded live delivery evidence accompany implementation; simulated
  success alone does not qualify production mail. Record warnings, current limits and any missing target authority.

## Coordination

Reuse TICKET-00011 and the current locked package contracts without changing their policy. TICKET-00122 supplies
target secrets/supervision; this TICKET supplies mail configuration/cadence requirements early, not after final
production acceptance. TICKET-00124 suppresses/reconciles restored work; TICKET-00125 consumes qualified mail for
rollout. No completed downstream release is a prerequisite for adapter development or safe staging checks.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00527](../tasks/00527-TASK.md) | Deliver real invitation and password-reset email in staging | ready-for-agent |
| [TASK-00528](../tasks/00528-TASK.md) | Operate scheduled account-mail recovery across both environments | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved slice C of EPIC-00021's five-TICKET split, implementing WF-050's Resend selection through existing
owners. Follow [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) with their current qualified contracts,
not historical package-version prerequisites. Initial requirement acceptance allocated no TASKs. John subsequently
approved the two-TASK split and bounded existing-owner/dependency amendments below; neither approval grants account
enrollment, DNS changes, credential inspection, messages, spending or delivery acceptance.

### Approved TASK decomposition and current handoff

| Slice | Accepted TASK | Blockers | Delivered outcome |
|---|---|---|---|
| A | [TASK-00527 — Deliver real invitation and password-reset email in staging](../tasks/00527-TASK.md) | TASK-00524, inheriting TASK-00284 | Existing committed invitation/reset delivery reaches authorized staging inboxes through the shared Resend transport, with correct canonical links, protected configuration and distinct acceptance/receipt observations |
| B | [TASK-00528 — Operate scheduled account-mail recovery across both environments](../tasks/00528-TASK.md) | TASK-00527, TASK-00526 | Actual bounded runner/independent expiry enrollment, original-identity interruption recovery, separate health/progress/outcome status, production inbox evidence and suppression/reconciliation procedures |

Both are unranked `feature` TASKs, `ready-for-agent` when dependencies permit. Each owns meaningful application/
integration checks, direct provider/runtime evidence, full build and independent Review/QA. No new transport,
queue, retry policy, scheduler, certification TASK or product tests of cron/configuration/runbook prose are added.

#### Approved supplier reconciliation

TASK-00284/TICKET-00068 now record the accepted Resend Free HTTPS choice. The shared transport remains `needs-info`
for actual account/plan/sender/recipient/environment qualification and bounded external-effect authority; selecting
a vendor does not clear those holds. TASK-00277/00287 retain old-mailbox notice/email-change operation. No completed
email-change journey is an invitation/reset or transport prerequisite, and shared transport does not share lifecycle.

TASK-00524 adds TASK-00284 to its existing TASK-00523/00063 blockers. Inspection of
`config/application/credential_delivery.php` shows staging/production cannot resolve a required real credential
provider from current local/catcher composition, and TASK-00063 requires fail-closed provider startup capability.
The explicit supplier edge avoids treating development fallback as secure staging. TASK-00284 can qualify the
transport independently of a hosted app; final staged credential receipt/scheduling never reverse-blocks it.

No other existing TASK metadata changes. All statuses/orders, EPIC-00020 priority and actual qualification holds
remain. TICKET-00124/00125 consume suppression/reconciliation and qualified mail; their completed journeys do not
reverse-block this supplier. Production-only evidence needs do not block A's staging work. Both immediate and
scheduled send paths must remain suppressed during isolated recovery; stopping cron alone is insufficient.

Immediately before allocation, checked live/archive TASK filenames across seventeen local/cached refs and nine
registered worktrees, including untracked records; highest observed was TASK-00526. TASK-00527/00528 are local
allocations, not a global reservation. The ignored `.runs/notes/mail-decomposition/before.json` receipt captures
781 planning Markdown files before this step.

This TICKET's accepted requirements are fully decomposed, not implemented or operationally accepted. TICKET-00124's
TASK-00529/00530 and TICKET-00125's subsequent TASK-00532/00533 complete the selected map's TASK-planning phase.
Work remains uncommitted/unpublished, with no account/credential/DNS/mail or
runtime operation authorized by this decomposition.

#### Planning verification

`./bin/planning-check --write` and `./bin/planning-check` passed: **673 records, 517 active**, generated views current.
`git diff --check` and changed/untracked scope/whitespace checks passed across **66 planning paths** including earlier
uncommitted work; 4,082 relative link targets were checked.

Against the 781-file pre-edit baseline, exactly seventeen existing planning paths changed and TASK-00527/00528 were
added. No prior file was removed. All existing statuses/orders are preserved; the sole existing frontmatter change
is TASK-00524's approved TASK-00284 blocker. TASK-00284 retains `needs-info` and its existing order/dependencies.
All pre-existing TASK files other than TASK-00284/00524 remain byte-identical, including notice/email-change owners,
TASK-00510–00512 and all other qualification holds. EPIC-00020 remains unchanged. Verified the new records' exact
parent/kind/status/blank-order/blank-PR metadata and blocker lists; the complete TASK dependency graph is acyclic.

These are planning checks, not fresh application/provider/runtime evidence. No full build, independent Review/QA,
account/DNS enrollment, credential or mailbox access, message, schedule, deployment, recovery or spending operation
occurred. No commit or push occurred; earlier lifecycle/artifact/environment receipts remain historical.

Next: `/skill:next` for read-only Board-based selection after completed selected-map planning. No automatic implementation.
