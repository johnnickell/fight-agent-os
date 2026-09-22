---
id: TICKET-00019
epic: EPIC-00004
title: Deliver password recovery and authenticated password change
status: ready-for-agent
---

# Deliver password recovery and authenticated password change

## Problem statement

Users need safe recovery from forgotten credentials and a secure way to replace a known password. Account discovery, weak/common passwords, reusable grants, or surviving sessions after reset would undermine authentication.

## Solution and boundaries

Deliver generic password-reset request, durable delivery, one-hour purpose-bound single-use reset grants, reset completion, and authenticated password change using Fight Access Control behavior. Reset revokes every active session and requires fresh login. Change requires proof of the current password and applies package session-invalidation policy.

Apply one policy to activation, reset, and change: 8–128 characters with uppercase, lowercase, number, and symbol; allow spaces, Unicode, paste, and password managers; Argon2id; no periodic expiration. Reject common passwords through a capability backed by a pinned, checksummed, license-reviewed local hashed denylist with deterministic offline tests.

Out of scope: external compromised-password APIs, hints/security questions, email change, account deletion/restoration, periodic expiry, and preserving password fields through reauthentication.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Request password reset | `RequestPasswordReset` | Resolve canonical eligible identity without public disclosure | `PasswordResetRequested` | If eligible, hashed one-time grant and durable delivery intent are recorded; response remains generic |
| Confirm/expire delivery state | `ConfirmPasswordResetDelivery`, `ExpirePasswordResetDelivery` | Safe internal delivery/grant state | `PasswordResetDeliveryConfirmed`, `PasswordResetDeliveryExpired` | Delivery lifecycle is durable and recoverable without exposing grant material |
| Complete password reset | `AuthenticationService::resetPassword()` | Resolve valid grant and authoritative user/session state | `PasswordResetCompleted`, session revocation outcomes | New Argon2id hash commits, grant is consumed, all sessions are revoked |
| Change known password | `AuthenticationService::changePassword()` | Resolve current principal and verify current hash | `PasswordChanged`, applicable session invalidation outcomes | Password changes atomically and configured authority invalidation occurs |

## Validation and permissions

Public request responses and timing must not disclose account existence/state and are rate-limited. Reset grants are one hour, single-use, purpose-bound, hashed at rest, and removed from visible URL history after capture. Candidate passwords are never trimmed, logged, or sent externally; denylist lookup material is not logged. Corpus provenance, license, version, checksum, update process, and third-party notice are required.

Reset requires grant possession; change requires authenticated ownership and current-password proof. Neither path permits administrator password assignment.

## Acceptance and evidence

- Existing/nonexistent/ineligible reset requests have indistinguishable public contracts while eligible requests create recoverable delivery.
- Reset links and diagnostics are redacted; invalid/used/expired states provide generic recovery guidance.
- Reset consumes once, updates hash, revokes all sessions, and requires fresh login; race tests prove duplicate completion safety.
- Change rejects incorrect current password and applies the same policy/denylist as activation/reset.
- Local denylist adapter has pinned provenance/checksum/license, no runtime network access, and deterministic fixtures.
- Accessible responsive request, completion, success, validation, and expiry states match accepted design.
- Focused security, PostgreSQL, HTTP, component tests and `./bin/build` pass.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00053](../tasks/00053-TASK.md) | Deliver recoverable secret-safe password-reset email | ready-for-agent |
| [TASK-00054](../tasks/00054-TASK.md) | Deliver the generic password-reset request journey | ready-for-agent |
| [TASK-00055](../tasks/00055-TASK.md) | Deliver one-time password-reset completion | ready-for-agent |
| [TASK-00056](../tasks/00056-TASK.md) | Deliver authenticated password change and terminal logout | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Depends on secure authentication [TICKET-00017](00017-TICKET.md), invitation password-policy integration [TICKET-00016](00016-TICKET.md), durable delivery [TICKET-00011](00011-TICKET.md), and accepted design [TICKET-00014](00014-TICKET.md).
