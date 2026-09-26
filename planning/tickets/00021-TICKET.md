---
id: TICKET-00021
epic: EPIC-00004
title: Deliver Super Admin user and invitation operations
status: ready-for-agent
---

# Deliver Super Admin user and invitation operations

## Problem statement

Authorized operators need a safe interface to inspect installation-wide user lifecycle state, invite users, and recover pending invitations. This TICKET owns the initial invitation/directory slice; the later full user/role/session administration TICKETs do not turn this invitation path into arbitrary user creation or permit role-name shortcuts.

## Solution and boundaries

Deliver permission-controlled Super Admin routes and APIs to list safe users, invite ordinary users, inspect invitation/delivery status, retry/resend, and atomically correct pending email. Permit elevated invitation/assignment only with `ASSIGN_SUPER_ADMIN`, explicit confirmation, and durable audit evidence. Use exact permission checks and existing lifecycle commands/queries; never expose raw grants.

Out of scope for this TICKET: custom roles/permissions (TICKET-00031), active email change, disable/enable/delete/restore and cross-user sessions (TICKET-00030), workspace membership, raw activation/reset credentials, and a role-name Super Admin bypass.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| List safe users | N/A — read-only interaction | `ListUsers` and safe lifecycle projection | N/A — no domain event | Authorized operator sees canonical email and approved lifecycle/role/invitation state only |
| Invite a regular user | `InvitePendingUser` with managed `ROLE_USER` assignment | Detect existing/pending identity and managed role | `UserInvited`, `RoleAssignedToUser` as applicable | Pending user and package-owned recoverable delivery state are created |
| Inspect and recover invitation | `RetryInvitationDelivery`, `ResendInvitationDelivery`, `CorrectPendingInvitation` | `FindInvitationDeliveryStatus`, safe user state | Retry/resend/correction events | Delivery retries or replacement/correction occur with predecessor grant invalidation where applicable |
| Invite/assign a Super Admin | `InvitePendingUser`, `AssignRoleToUser` through explicit elevated orchestrator | Resolve current `ASSIGN_SUPER_ADMIN` authority and target state | Invitation/role events plus durable safe audit evidence | Elevated pending identity/assignment occurs only after explicit confirmation |

## Validation and permissions

Canonical email, target lifecycle, managed role, confirmation, stale version, and delivery-state validations are server-owned. Conflicts and concurrent correction/resend/activation return safe recoverable results rather than overwriting authoritative state. Listings paginate/filter only through explicitly supported safe fields.

`READ_USERS`, `INVITE_USERS`, `MANAGE_USER_INVITATIONS`, and `ASSIGN_SUPER_ADMIN` gate their exact interactions. Possessing `ROLE_SUPER_ADMIN` is not itself an authorization bypass. Audit evidence records actor, target, action, time, result, and safe context without grants/passwords.

## Acceptance and evidence

- Each route/action has an exact server permission and matching fail-closed client presentation.
- User/list/status Views expose only approved lifecycle, managed-role, and delivery metadata.
- Ordinary invitation cannot assign elevated authority.
- Elevated invitation requires permission and explicit confirmation and creates durable audit evidence.
- Retry/resend/correction use lifecycle contracts, revoke predecessors as required, and handle stale/race outcomes safely.
- Raw credentials never appear in APIs, UI, logs, analytics, screenshots, or audit evidence.
- Responsive/accessibility states cover lists, empty/loading/errors, forms, confirmation, success, and conflict recovery.
- Focused permission, audit, PostgreSQL, HTTP, component tests and `./bin/build` pass.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00059](../tasks/00059-TASK.md) | Deliver the permission-controlled user directory | ready-for-agent |
| [TASK-00060](../tasks/00060-TASK.md) | Deliver pending-invitation status and recovery | ready-for-agent |
| [TASK-00061](../tasks/00061-TASK.md) | Deliver the ordinary web invitation journey | ready-for-agent |
| [TASK-00062](../tasks/00062-TASK.md) | Deliver the confirmed elevated invitation journey | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Depends on invitation lifecycle [TICKET-00016](00016-TICKET.md), current authority/shell [TICKET-00018](00018-TICKET.md), managed permissions [TICKET-00015](00015-TICKET.md), and accepted design [TICKET-00014](00014-TICKET.md).
