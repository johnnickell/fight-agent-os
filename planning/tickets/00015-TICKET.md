---
id: TICKET-00015
epic: EPIC-00004
title: Establish managed authority and guarded bootstrap
status: ready-for-agent
---

# Establish managed authority and guarded bootstrap

## Problem statement

A new private installation needs deterministic managed authority and a safe first Super Admin path. A permanent bootstrap bypass, role-name authorization, or administrator-selected password would undermine the package authorization model.

## Solution and boundaries

Use `ReconcileManagedPolicy` to maintain `ROLE_USER`, `ROLE_SUPER_ADMIN`, and the exact catalog: `VIEW_DASHBOARD`, `READ_OWN_SESSIONS`, `DELETE_OWN_SESSIONS`, `READ_USERS`, `INVITE_USERS`, `MANAGE_USER_INVITATIONS`, `ASSIGN_SUPER_ADMIN`, `REMOVE_SUPER_ADMIN`, `DISABLE_USERS`, `ENABLE_USERS`, `DELETE_USERS`, `RESTORE_USERS`, `MANAGE_USER_ROLES`, `READ_SESSIONS`, `DELETE_SESSIONS`, `READ_ROLES`, `CREATE_ROLES`, `UPDATE_ROLES`, `DELETE_ROLES`, `READ_PERMISSIONS`, and `MANAGE_ROLE_PERMISSIONS`. The former planned `LIST_USERS` becomes `READ_USERS` before policy implementation; pending invitation remains `INVITE_USERS`, not unrestricted `CREATE_USERS`. `ROLE_USER` gets only dashboard and own-session permissions. `ROLE_SUPER_ADMIN` gets all approved permissions; installation-wide ones are marked `SUPER_ADMIN_ONLY`, with current authoritative checks, not role-name bypasses. Do not define unused generic create/update user or profile permissions until a concrete supported operation exists.

Provide a one-time guarded console bootstrap that reconciles policy, verifies no Super Admin authority exists, creates a pending identity through `InvitePendingUser`, assigns `ROLE_SUPER_ADMIN` through package behavior, records a distinct bootstrap actor and durable audit evidence, and requires normal activation. Provide a trusted console path for ordinary `ROLE_USER` invitations. Later elevated invitation/assignment requires authenticated `ASSIGN_SUPER_ADMIN`, explicit confirmation, and audit evidence.

Out of scope: public registration, `ROLE_ADMIN`, implementation of custom-role/permission administration (TICKET-00031), role-name bypasses, multi-workspace authority, administrator-chosen passwords, and enforcing tier grants merely by storing a tier label.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Reconcile managed authority | `ReconcileManagedPolicy` | `PreviewManagedPolicy`, role/permission repository reads | `ManagedPolicyReconciled` | Managed roles, permissions, and grants match version-controlled policy idempotently |
| Bootstrap the first Super Admin | Explicit Agent OS console orchestrator using `InvitePendingUser` and `AssignRoleToUser` | Query whether effective Super Admin authority already exists | `UserInvited`, `RoleAssignedToUser`, safe audited bootstrap evidence | One pending elevated identity and package-owned recoverable invitation state are committed; later bootstrap is refused |
| Invite an ordinary user from console | `InvitePendingUser` with managed `ROLE_USER` assignment | Existing-user/pending-state lookup | `UserInvited`, `RoleAssignedToUser` as applicable | Pending ordinary identity and package-owned delivery state are created without printing credentials |

## Validation and permissions

Canonical email, managed identifiers, exact policy membership, pending lifecycle, and one-time bootstrap guard are authoritative and race-safe in PostgreSQL. Reconciliation is idempotent and reports drift safely. Bootstrap must fail closed after any effective Super Admin exists, including competing attempts.

Bootstrap is a separately audited trusted-console capability, not anonymous web authority. Ordinary console invitation requires explicit trusted operator access. Web elevated invitation requires `INVITE_USERS` and `ASSIGN_SUPER_ADMIN`; direct managed-role assignment also requires `MANAGE_USER_ROLES`; removing managed Super Admin requires `MANAGE_USER_ROLES` plus separate `REMOVE_SUPER_ADMIN`, with last-admin/self-change safeguards in Agent OS Application, regardless of package handler permission checks. All other authorization is permission- or ownership-based. Package ownership authorization callbacks for invitation, session and email-change workflows remain distinct from global permission grants.

## Acceptance and evidence

- Reconciliation creates/updates the exact managed catalog and role grants without duplicate records.
- Repeat reconciliation is idempotent; drift preview and repair have deterministic tests.
- Fresh-install bootstrap succeeds once, creates a pending Super Admin, emits no raw grant, and uses normal delivery/activation.
- Existing-authority and competing-bootstrap tests prove later attempts fail safely.
- Ordinary console invitation assigns only `ROLE_USER` and uses recoverable delivery.
- Audit evidence distinguishes bootstrap, console operator, and later authenticated actor without secrets.
- Focused unit/PostgreSQL/console tests and `./bin/build` pass with warnings disclosed.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00036](../tasks/00036-TASK.md) | Reconcile the exact managed authority policy | ready-for-agent |
| [TASK-00037](../tasks/00037-TASK.md) | Deliver trusted ordinary-user console invitations | ready-for-agent |
| [TASK-00038](../tasks/00038-TASK.md) | Guard the one-time Super Admin bootstrap | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the authority/bootstrap decision from [WF-005](../wayfinder/tickets/WF-005-design-authentication-and-authorization-journeys.md). It depends on [TICKET-00008](00008-TICKET.md), [TICKET-00009](00009-TICKET.md), and the audit/delivery seams in [TICKET-00011](00011-TICKET.md).
