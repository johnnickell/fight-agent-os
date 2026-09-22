---
id: TICKET-00016
epic: EPIC-00004
title: Deliver the invitation and activation lifecycle
status: ready-for-agent
---

# Deliver the invitation and activation lifecycle

## Problem statement

Pending users need a complete, recoverable, and secret-safe route from invitation to active identity. Delivery failures, expired links, resend races, and pending-email mistakes must be recoverable without exposing one-time credentials.

## Solution and boundaries

Deliver invitation issuance, durable delivery, seven-day purpose-bound activation grants, public activation, delivery-status lookup, retry/resend, and atomic pending-email correction using Fight Access Control behavior. Apply the shared password policy and local common-password checker at activation. Resend replaces/revokes the predecessor grant. Capture credential-bearing link material only long enough to submit it, remove it from visible history, and prevent logs/referrers/analytics from retaining it.

This TICKET provides APIs and public activation UI plus the lifecycle used by console/admin clients. The complete Super Admin user/invitation interface belongs to TICKET-00021.

Out of scope: public registration, administrators choosing passwords, raw grants in normal reads, active-user email change, and full user lifecycle administration.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Issue and deliver an invitation | `InvitePendingUser`, `DeliverUserInvitation` | Existing user/pending state; `FindInvitationDeliveryStatus` | `UserInvited`, `UserInvitationDelivered` | Pending identity, hashed grant, durable delivery intent, and safe status are recorded |
| Activate a pending identity | `AuthenticationService::activate()` via an explicit Action/orchestrator | Resolve one-time grant and pending user | `UserActivated` | Password hash is set and identity becomes active atomically; grant becomes unusable |
| Retry or resend delivery | `RetryInvitationDelivery`, `ResendInvitationDelivery` | `FindInvitationDeliveryStatus` | `InvitationDeliveryRetryRequested`, `InvitationDeliveryResent` | Retry is requested or a replacement grant/delivery supersedes the predecessor |
| Correct a pending address | `CorrectPendingInvitation` | Pending identity and delivery state | `PendingInvitationCorrected` | Canonical email and associated invitation state change atomically without affecting active users |

## Validation and permissions

Emails are canonicalized; grants are purpose-bound, hashed at rest, single-use, and seven days; passwords use the accepted policy and offline denylist. Invalid/used/expired grants return generic recovery guidance. Concurrency tests must cover duplicate activation, resend/activate races, and correction/resend races.

Public activation needs possession of the valid grant but reveals no account state beyond safe completion/recovery. Invitation issuance requires trusted console authority or `INVITE_USERS`; retry/resend/correction requires `MANAGE_USER_INVITATIONS`; elevated assignment additionally requires `ASSIGN_SUPER_ADMIN` and confirmation.

## Acceptance and evidence

- Issuance commits pending identity and recoverable delivery intent; provider effects occur post-commit.
- Activation enforces password policy, consumes the grant once, and activates atomically.
- Status Views contain no raw credential; logs, responses, referrers, history, analytics, and screenshots are redacted.
- Retry, resend, correction, expiry, and invalid/used grant behavior are recoverable and generic.
- PostgreSQL race tests prove replacement and activation invariants.
- Public activation UI implements the accepted responsive/accessibility states.
- Focused Domain/Application, PostgreSQL, HTTP, component, and delivery tests plus `./bin/build` pass.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00039](../tasks/00039-TASK.md) | Enforce shared password acceptance and Argon2id hashing | ready-for-agent |
| [TASK-00040](../tasks/00040-TASK.md) | Deliver recoverable secret-safe invitation email | ready-for-agent |
| [TASK-00041](../tasks/00041-TASK.md) | Expose authorized invitation issuance and safe status | ready-for-agent |
| [TASK-00042](../tasks/00042-TASK.md) | Expose permission-controlled invitation recovery | ready-for-agent |
| [TASK-00043](../tasks/00043-TASK.md) | Deliver the public invitation activation journey | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Uses managed authority from [TICKET-00015](00015-TICKET.md), persistence [TICKET-00009](00009-TICKET.md), HTTP [TICKET-00010](00010-TICKET.md), delivery [TICKET-00011](00011-TICKET.md), and accepted production design [TICKET-00014](00014-TICKET.md).
