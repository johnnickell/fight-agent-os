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

After the JWT-by-default API-v1 gate, managed policy and current-principal foundation exist, add separately authorized `GET /users/{userId}` (`READ_USERS`), `POST /users/{userId}/disable` (`DISABLE_USERS`), `/enable` (`ENABLE_USERS`), `DELETE /users/{userId}` (`DELETE_USERS`, package soft-delete), and `POST /users/{userId}/restore` (`RESTORE_USERS`). Only supported package commands are exposed. Global user-role association uses `PUT`/`DELETE /users/{userId}/roles/{roleId}` with `MANAGE_USER_ROLES`; assigning managed Super Admin additionally requires `ASSIGN_SUPER_ADMIN` and explicit confirmation. Both role Actions retain `#[RequiresPermission('MANAGE_USER_ROLES')]` and refuse assignment and removal for self-targets and pending, disabled or deleted targets before dispatch. These checks are admission-time only, with no commit-time recheck; every other exposed role entry enforces the same admission rules. Allowed changes retain package conflicting-write checks and atomic required audit. No application entry may remove managed Super Admin. The app must also reject deletion of the caller's own account or any managed Super Admin account. Administrative `GET /users/{userId}/sessions` (`READ_SESSIONS`) and `DELETE /users/{userId}/sessions/{sessionId}` (`DELETE_SESSIONS`) require package actor/target authorization; revocation requires a bounded reason and audit. An accessible user-detail/admin web journey follows the APIs; existing directory and invitation work remains owned by TICKET-00021.

Out of scope: public account creation, direct active email update, unrestricted `CREATE_USERS`/`UPDATE_USERS`, permission creation, password access, user-selected role on invitation, hard deletion, mass selection, global logout, multi-workspace/team administration or overriding package ownership checks.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect one user | N/A | Package `GetUserById` and exact safe role projection | None | No-store safe View, without grants/passwords/sessions |
| Change lifecycle | `DisableUser`, `EnableUser`, `DeleteUser`, `RestoreUser` | Resolve target lifecycle eligibility; TASK-00119 retains its non-atomic last-admin precheck; deletion uses current-state confirmation and excludes self and Super Admin targets | Package events and durable safe audit | Only explicitly confirmed permitted transition commits; active sessions/authority fail closed as package policy dictates |
| Manage user roles | Package `AssignRoleToUser`, `RemoveRoleFromUser` through configured CommandBus after entry admission | Resolve actor, target, authoritative Role identity and current permissions; refuse managed Super Admin removal | Package role events and durable safe audit | Atomic permitted association change; principal projection invalidates |
| Inspect/revoke other user's session | `RevokeSession` for deletion | `ListActiveSessions` and package actor/target authorization | Package revocation event plus safe administrative audit | Scoped, reasoned operator action; target authority terminates without exposing credentials |
| Operate admin page | Existing bounded APIs only | Read current safe user/role/session Views | Client invalidation only | Accessible confirmation and server-refetched current state, no client authority |

## Validation and permissions

All installation-wide permissions above are `SUPER_ADMIN_ONLY` in the managed catalog, initially granted only to `ROLE_SUPER_ADMIN`; the actual request check is current authoritative permission, never a role-name/JWT claim. Matched FQCN Actions use TASK-00117's centrally enforced access metadata. For TASK-00118 safe detail/directory reads, the owner's 2026-11-03 correction makes `#[RequiresPermission('READ_USERS')]` on each HTTP Action the single declaration, enforced by existing `ApiActionSecurity` against authoritative current request state before execution or package user/role queries. Neither Action repeats permission/installation-policy checks; repeated lookups do not establish atomic authorization. Responders safely map the resulting Views without fetching. Do not introduce consumer read wrappers. For mutations, every actual entry adapter authenticates its caller and checks operation permission, target/scope and explicit confirmation before configured bus dispatch. Under the [canonical contract](../../docs/engineering/STANDARDS.md#entry-admission-and-authorization-timing), Application has no second blanket admission gate and the raw bus is trusted internal infrastructure, not an exposed entry. Except for TASK-00121's explicit role admission amendment below, reusable last-admin/self-lockout and target-lifecycle business policy stays with its Domain/Application owner; registered handlers coordinate package work, required audit and one generic transaction on the shared connection. TASK-00119 uses the operator-approved non-atomic query-before-command last-admin precheck described below, not handler participation for that precheck. Delete/restore retain TASK-00120's current-state confirmation without a displayed revision or administrator-count prerequisite. The later owner decision below supersedes permission to delete another Super Admin. Qualify supported package participation; no nested consumer transaction or audit decorator may simulate atomicity. Admission-time authority suffices for ordinary requests, while real last-admin/expected-state consistency remains enforced. Do not infer supplier signatures or behavior from a prospective version number. Package session authorization callbacks still ask Agent OS whether actor may manage the target; user/role membership checks do not substitute for those ownership decisions.

Only allowlisted route/body fields and validated IDs, operation-applicable revisions and reason enter use cases. For TASK-00119 disable/enable, the operator's 2026-11-09 clarification removes any displayed whole-account revision prerequisite: disabling must be able to complete despite an unrelated concurrent email change, without losing that change. Explicit transition confirmation, lifecycle eligibility and atomic package state/session/required audit remain required. The subsequent explicit operator decision, “We can do the check with a query before issuing the command to remove the admin. No need to be more atomic than that,” authorizes TASK-00119's last-effective-admin query before package dispatch without atomic count/check/mutation or cross-request serialization. Concurrent prechecks can both pass; that final-admin race is accepted for this TASK only. Self-target transitions still reject; package lifecycle/session/required audit remain atomic. That TASK-00119 amendment does not change siblings. Managed roles cannot be renamed or deleted. Direct Super Admin assignment requires `MANAGE_USER_ROLES` **and** `ASSIGN_SUPER_ADMIN`, explicit confirmation, durable audit and self-change safeguards. The app never removes that Role from a User, regardless of permission. Lifecycle and session failures avoid target enumeration; admin reasons/audit redact secrets. Future Workspace/Project admin must have scoped actor/target capabilities and separately approved permission catalog; global rights never inherit from a local title.

### Owner-approved Super Admin policy amendment

Removing managed Super Admin membership or deleting an account that holds that Role is database maintenance
only. Self-deletion remains prohibited for all users. This supersedes the earlier elevated-removal contract
and the allowance for Super Admins to delete each other. TASK-00121 implements the role-removal prohibition.
The completed TASK-00120 records a separate deletion follow-up; its historical verification does not prove
the new guard. Disable, restore, Super Admin assignment and session administration keep their existing requirements.

### Owner-approved role admission amendment — TASK-00121 and TASK-00123

`#[RequiresPermission('MANAGE_USER_ROLES')]` continues to gate both HTTP role Actions. Before dispatch, each Action refuses both assignment and removal for pending, disabled or deleted targets and for the signed-in user targeting themselves. These are admission-time checks only, with no commit-time recheck requirement. Any other exposed role entry must authenticate its caller and enforce the same admission rules. Preserve package conflicting-write checks and atomic required audit. Managed Super Admin assignment retains `ASSIGN_SUPER_ADMIN` and explicit confirmation; removal remains forbidden.

This supersedes TASK-00121's older business-policy participation hold specifically for target-state and self-target checks, without claiming supplier qualification, implementation or completed acceptance. TASK-00123 hides role assignment and removal controls for self and inactive (pending, disabled or deleted) targets; otherwise it shows only allowed controls and never offers managed Super Admin removal. Direct-request denial remains authoritative on the server. Account-deletion, disable, restore and session policies are unchanged.

### TASK-00120 operator amendment — 2026-11-10 (historical)

The operator accepted confirmation as “delete this user in their current state” and explicitly accepted the concurrent-two-admin deletion race: “If confirmation means ‘delete this user in their current state,’ yes, do that. I'm fine with the edge case possibility in the second case. In practice, our installation will only ever have a single SUPER_ADMIN, so that removes that possibility for us at least.” This scoped amendment superseded displayed-revision and last-admin requirements for TASK-00120 only. The later policy above supersedes its cross-admin deletion allowance. It requires self-deletion prohibition and exact operation-specific SUPER_ADMIN_ONLY permissions, not an admin count/check or a hard-coded one-admin limit. Restore preserves package identity reservations and may issue fresh activation authority for pending activation internally, without session issuance, old-session revival or credential disclosure. TASK-00119 and role/session siblings retain their own safeguards.

## Acceptance and evidence

- Safe detail and lifecycle operations reject anonymous, insufficient permission, wrong target state, self/last-admin loss, lifecycle-relevant conflicting mutation and injected fields before package dispatch where possible. TASK-00119 disable/enable require no displayed whole-account revision; an unrelated concurrent email change alone must not cause disable to fail. TASK-00119's last-admin rejection is based on the precheck observation, not a cross-request final-admin guarantee. TASK-00120 delete/restore use explicit current-state confirmation, with no displayed whole-account revision, new entity revision, administrator-count prerequisite or atomic cross-target last-admin decision. Package lifecycle CAS, reservations, session retirement and required audit atomicity remain required; self-deletion and deletion of any managed Super Admin account must be refused.
- Every role entry checks current permissions and authoritative target and Role identity, refusing assignment and removal for self and pending/disabled/deleted targets before dispatch, without a commit-time recheck of those admission decisions. HTTP Actions retain `#[RequiresPermission('MANAGE_USER_ROLES')]`. Package conflicting-write checks and atomic required audit remain required. Managed Super Admin assignment requires separate permission, confirmation and audit. Every entry refuses managed Super Admin removal even if the caller holds `REMOVE_SUPER_ADMIN`.
- Cross-user sessions require separate permissions and package actor/target callbacks; self-service endpoints never become admin backdoors.
- Durable audit and request/response redaction cover actor, target, reason, outcome and timestamp without credentials or private session state. For cross-user session revocation only, the [TASK-00122 amendment](../tasks/00122-TASK.md#current-amendment--task-00573) requires a validated reason at entry and stores it durably only in protected `facts.reason` in the revocation transaction. Never return or log the reason, and preserve earlier audit records as stored.
- Browser UI has truthful permission-aware links, accessible confirmations, failure/recovery states, and authoritative refetch. Role assignment and removal controls are hidden for self and inactive targets; otherwise only allowed controls appear, never managed Super Admin removal. The server remains authoritative for direct-request denial.
- Focused PostgreSQL/HTTP/application/client/security/accessibility checks and `./bin/build` pass; TASK-00126 verifies the required upstream authorization/tier/persistence contracts and records the exact tested release and source commit before dependent behavior is enabled.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00118](../tasks/00118-TASK.md) | Expose a safe authorized user detail | done |
| [TASK-00119](../tasks/00119-TASK.md) | Guard user disable and enable transitions | done |
| [TASK-00120](../tasks/00120-TASK.md) | Guard user soft deletion and restoration | done |
| [TASK-00121](../tasks/00121-TASK.md) | Authorize user role assignments and removals | ready-for-agent |
| [TASK-00122](../tasks/00122-TASK.md) | Authorize cross-user session inspection and revocation | done |
| [TASK-00123](../tasks/00123-TASK.md) | Operate full user administration in the browser | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-11-17: [EPIC-00027](../epics/00027-EPIC.md) prioritizes the remaining backend assignment and browser
administration TASKs for the earlier shared frontend release. TASK-00121 consumes the corrected audit/catalog/
authentication suppliers. TASK-00123 implements its screens in the shared package, consuming TASK-00597's
stateful table and URL protocol. Existing server policy and the separate email-change exclusion remain intact.

TASK-00118 pagination-only correction has independent Review ACCEPT and QA PASS on `55c55ad`;
publication and final checks are pending at this checkpoint. Current results are in the TASK and Land receipt.

TASK-00118 single-declaration correction (2026-11-03): **“Fix it in both actions”** removes duplicate
Action rechecks in both safe reads; centrally enforced Action metadata owns the HTTP permission boundary.
Independent Review and QA accept repair candidate `ba7f652`; publication and final-head checks were pending
at this checkpoint. Current delivery results are in [TASK-00118](../tasks/00118-TASK.md) and its Land receipt.
Earlier acceptance below covers only `c2227fa`. Mutation and non-HTTP admission now follow the canonical amendment above; the accepted read implementation evidence remains historical.

Historical TASK-00118 boundary correction (2026-11-02): **“the permission check is explicitly supposed to be in the HTTP action”**. This supersedes the original shared-Application requirement for the affected safe reads only; the [ADR amendment](../adr/0001-application-ownership-and-orchestration.md#task-00118-owner-clarification--2026-11-02) governs ownership. Independent Review/QA accepted repaired candidate `c2227fa`; prior acceptance is historical. Current delivery results are in [TASK-00118](../tasks/00118-TASK.md) and its Land receipt.

Historical decomposition decision: Human-approved TASK-00116 expansion of EPIC-00004. TICKET-00015 owns initial managed IDs, TICKET-00021 owns invitation/directory and TICKET-00020 owns **only** self-service sessions. TASK-00117 owns the common API-v1 security gate. The former blanket Application permission requirement is superseded by the canonical entry-admission amendment above; package-owned target invariants remain required. No TASK in this TICKET assumes a future version's API; dependent capabilities require inspection and consumer qualification of the actual tagged release.
