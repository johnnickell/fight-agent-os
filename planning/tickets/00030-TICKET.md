---
id: TICKET-00030
epic: EPIC-00004
title: Administer installation users and cross-user sessions
status: ready-for-agent
---

# Administer installation users and cross-user sessions

## Problem statement

The initial directory and invitation journeys cannot inspect one user's safe details, change lifecycle, assign roles to existing users, or investigate and revoke another user's sessions. Those are installation-wide security operations, not broadened self-service or generic user updates.

## Solution and boundaries

After the JWT-by-default API-v1 gate, managed policy and current-principal foundation exist, add separately authorized `GET /users/{userId}` (`READ_USERS`), `POST /users/{userId}/disable` (`DISABLE_USERS`), `/enable` (`ENABLE_USERS`), `DELETE /users/{userId}` (`DELETE_USERS`, package soft-delete), and `POST /users/{userId}/restore` (`RESTORE_USERS`). Only supported package commands are exposed. Global user-role association uses `PUT`/`DELETE /users/{userId}/roles/{roleId}` with `MANAGE_USER_ROLES`; assigning managed Super Admin additionally requires `ASSIGN_SUPER_ADMIN`; removing that managed Role additionally requires separate `REMOVE_SUPER_ADMIN`. Both need explicit confirmation, durable audit and last-active-admin/self-change safeguards. Administrative `GET /users/{userId}/sessions` (`READ_SESSIONS`) and `DELETE /users/{userId}/sessions/{sessionId}` (`DELETE_SESSIONS`) require package actor/target authorization; revocation requires a bounded reason and audit. An accessible user-detail/admin web journey follows the APIs; existing directory and invitation work remains owned by TICKET-00021.

Out of scope: public account creation, direct active email update, unrestricted `CREATE_USERS`/`UPDATE_USERS`, permission creation, password access, user-selected role on invitation, hard deletion, mass selection, global logout, multi-workspace/team administration or overriding package ownership checks.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect one user | N/A | Package `GetUserById` and exact safe role projection | None | No-store safe View, without grants/passwords/sessions |
| Change lifecycle | `DisableUser`, `EnableUser`, `DeleteUser`, `RestoreUser` | Resolve target state/revision/last-administrator constraints | Package events and durable safe audit | Only explicitly confirmed permitted transition commits; active sessions/authority fail closed as package policy dictates |
| Manage user roles | Package `AssignRoleToUser`, `RemoveRoleFromUser` through configured CommandBus after entry admission | Resolve actor, target, role managed status, last-admin constraints, authoritative permissions | Package role events and durable safe audit | Atomic permitted association change; principal projection invalidates |
| Inspect/revoke other user's session | `RevokeSession` for deletion | `ListActiveSessions` and package actor/target authorization | Package revocation event plus safe administrative audit | Scoped, reasoned operator action; target authority terminates without exposing credentials |
| Operate admin page | Existing bounded APIs only | Read current safe user/role/session Views | Client invalidation only | Accessible confirmation and server-refetched current state, no client authority |

## Validation and permissions

All installation-wide permissions above are `SUPER_ADMIN_ONLY` in the managed catalog, initially granted only to `ROLE_SUPER_ADMIN`; the actual request check is current authoritative permission, never a role-name/JWT claim. Matched FQCN Actions use TASK-00117's centrally enforced access metadata. For TASK-00118 safe detail/directory reads, the owner's 2026-11-03 correction makes `#[RequiresPermission('READ_USERS')]` on each HTTP Action the single declaration, enforced by existing `ApiActionSecurity` against authoritative current request state before execution or package user/role queries. Neither Action repeats permission/installation-policy checks; repeated lookups do not establish atomic authorization. Responders safely map the resulting Views without fetching. Do not introduce consumer read wrappers. For mutations, every actual entry adapter authenticates its caller and checks operation permission, target/scope and explicit confirmation before configured bus dispatch. Under the [canonical contract](../../docs/engineering/STANDARDS.md#entry-admission-and-authorization-timing), Application has no second blanket admission gate and the raw bus is trusted internal infrastructure, not an exposed entry. Reusable last-admin/self-lockout and target-lifecycle business policy stays with its Domain/Application owner; registered handlers coordinate package work, required audit and one generic transaction on the shared connection. Qualify supported package participation; no nested consumer transaction or audit decorator may simulate atomicity. Admission-time authority suffices for ordinary requests, while real last-admin/expected-state consistency remains enforced. Do not infer supplier signatures or behavior from a prospective version number. Package session authorization callbacks still ask Agent OS whether actor may manage the target; user/role membership checks do not substitute for those ownership decisions.

Only allowlisted route/body fields and validated IDs/revisions/reason enter use cases. Managed roles cannot be renamed/removed. Direct elevated assignment requires `MANAGE_USER_ROLES` **and** `ASSIGN_SUPER_ADMIN`; elevated removal instead requires `MANAGE_USER_ROLES` **and** `REMOVE_SUPER_ADMIN`. Both require confirmation and audit, with last-admin and self-lockout safeguards even when both permissions are present. Lifecycle and session failures avoid target enumeration; admin reasons/audit redact secrets. Future Workspace/Project admin must have scoped actor/target capabilities and separately approved permission catalog; global rights never inherit from a local title.

## Acceptance and evidence

- Safe detail and lifecycle operations reject anonymous, insufficient permission, wrong target state, self/last-admin loss, stale/conflicting mutation and injected fields before package dispatch where possible.
- Ordinary and Super Admin role-assignment/removal matrices use distinct current permissions, actor/target/role authority and last-admin guards on every entry path while preserving managed-role/bootstrap invariants.
- Cross-user sessions require separate permissions and package actor/target callbacks; self-service endpoints never become admin backdoors.
- Durable audit and request/response redaction cover actor, target, reason, outcome and timestamp without credentials or private session state.
- Browser UI has truthful permission-aware links, accessible confirmations, failure/recovery states, and authoritative refetch.
- Focused PostgreSQL/HTTP/application/client/security/accessibility checks and `./bin/build` pass; TASK-00126 verifies the required upstream authorization/tier/persistence contracts and records the exact tested release and source commit before dependent behavior is enabled.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00118](../tasks/00118-TASK.md) | Expose a safe authorized user detail | done |
| [TASK-00119](../tasks/00119-TASK.md) | Guard user disable and enable transitions | ready-for-agent |
| [TASK-00120](../tasks/00120-TASK.md) | Guard user soft deletion and restoration | ready-for-agent |
| [TASK-00121](../tasks/00121-TASK.md) | Authorize user role assignments and removals | ready-for-agent |
| [TASK-00122](../tasks/00122-TASK.md) | Authorize cross-user session inspection and revocation | ready-for-agent |
| [TASK-00123](../tasks/00123-TASK.md) | Operate full user administration in the browser | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

TASK-00118 pagination-only correction has independent Review ACCEPT and QA PASS on `55c55ad`;
publication and final checks are pending at this checkpoint. Current results are in the TASK and Land receipt.

TASK-00118 single-declaration correction (2026-11-03): **“Fix it in both actions”** removes duplicate
Action rechecks in both safe reads; centrally enforced Action metadata owns the HTTP permission boundary.
Independent Review and QA accept repair candidate `ba7f652`; publication and final-head checks were pending
at this checkpoint. Current delivery results are in [TASK-00118](../tasks/00118-TASK.md) and its Land receipt.
Earlier acceptance below covers only `c2227fa`. Mutation and non-HTTP admission now follow the canonical amendment above; the accepted read implementation evidence remains historical.

Historical TASK-00118 boundary correction (2026-11-02): **“the permission check is explicitly supposed to be in the HTTP action”**. This supersedes the original shared-Application requirement for the affected safe reads only; the [ADR amendment](../adr/0001-application-ownership-and-orchestration.md#task-00118-owner-clarification--2026-11-02) governs ownership. Independent Review/QA accepted repaired candidate `c2227fa`; prior acceptance is historical. Current delivery results are in [TASK-00118](../tasks/00118-TASK.md) and its Land receipt.

Historical decomposition decision: Human-approved TASK-00116 expansion of EPIC-00004. TICKET-00015 owns initial managed IDs, TICKET-00021 owns invitation/directory and TICKET-00020 owns **only** self-service sessions. TASK-00117 owns the common API-v1 security gate. The former blanket Application permission requirement is superseded by the canonical entry-admission amendment above; package-owned target invariants remain required. No TASK in this TICKET assumes a future version's API; dependent capabilities require inspection and consumer qualification of the actual tagged release.
