---
id: TICKET-00020
epic: EPIC-00004
title: Deliver self-service active-session management
status: ready-for-agent
---

# Deliver self-service active-session management

## Problem statement

Users need visibility and control over their own long-lived refresh sessions. The interface must identify the current session and revoke another session safely without exposing credentials, fingerprinting users, or granting cross-user authority.

## Solution and boundaries

Use `ListActiveSessions` and `RevokeSession` to provide an authenticated Active Sessions page with coarse device/activity/creation/idle/absolute-expiry information. Identify the current session, require confirmation before revoking another owned session, and reserve current-session revocation for Logout. Propagate relevant invalidation across tabs and handle expiry/revocation races safely.

Out of scope: cross-user Super Admin access, logout-everywhere, raw refresh credentials, invasive fingerprinting/IP intelligence, editing lifetimes, and using revoke-current as an alternate logout path.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| List owned active sessions | N/A — read-only interaction | `ListActiveSessions` scoped to current principal | N/A — no domain event | Safe `SessionView` list identifies current session and coarse activity/expiry metadata |
| Revoke another owned session | `RevokeSession` | Resolve current principal and target ownership/state | `RefreshSessionRevoked` | Target session becomes unusable; current session remains authoritative |
| Handle concurrent expiry/revocation | `RevokeSession` may race with lifecycle expiry/revocation | Requery active sessions after safe conflict/not-found outcome | Existing package outcome only | UI converges to authoritative state without leaking whether another user owns an identifier |

## Validation and permissions

Session identifiers are validated and safe for transport but reveal no credential. Device/activity descriptions are coarse, escaped, and non-authoritative; do not add fingerprinting. Idle/absolute timestamps and current marker derive from authoritative session state. Revocation is idempotent or returns a safe generic conflict/not-found according to the API contract.

Every read/mutation requires both authoritative `READ_OWN_SESSIONS`/`DELETE_OWN_SESSIONS` as appropriate and package-enforced actor/target ownership. The target must belong to the current user and must not be the current session; failures do not reveal cross-user existence. `READ_SESSIONS`/`DELETE_SESSIONS` are separate installation-wide administrative permissions owned by TICKET-00030; they do not broaden this self-service endpoint. Client hiding never replaces server checks.

## Acceptance and evidence

- Active-session responses contain only approved coarse metadata and never refresh credentials or sensitive audit/provider data.
- Current session is identified reliably and cannot be revoked through the other-session operation.
- Another owned session can be confirmed/revoked and fails subsequent refresh immediately.
- Cross-user, malformed, already-revoked, expired, and competing revoke cases fail safely and converge after refresh.
- Relevant revocation/logout state propagates across tabs without token persistence.
- Responsive/accessible list, empty, confirmation, success, conflict, and error states match accepted design.
- Focused ownership, PostgreSQL race, HTTP, component tests and `./bin/build` pass.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00057](../tasks/00057-TASK.md) | Deliver authoritative owned-session visibility | ready-for-agent |
| [TASK-00058](../tasks/00058-TASK.md) | Deliver confirmed revocation of another owned session | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Depends on secure browser sessions [TICKET-00017](00017-TICKET.md), current-principal ownership [TICKET-00018](00018-TICKET.md), and accepted design [TICKET-00014](00014-TICKET.md).
