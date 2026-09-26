# Recommended Fight Access Control v0.4.0 addition: enforce permission grant tiers

**Status:** Proposal for a Fight Access Control Wayfinder session, not an accepted design, implementation TASK, or Agent OS dependency change
**Source:** Fight Agent OS canary integration; inspect the owning Fight Access Control repository and its current release before adopting this proposal

## Problem and intended outcome

Fight Agent OS wants `ROLE_SUPER_ADMIN` to be the only role that can ever carry installation-wide security-administration permissions. Future Workspace or Repository Admins may manage their own scoped users or custom roles, but must not gain or grant installation-wide permissions such as `ASSIGN_SUPER_ADMIN`. `MANAGE_ROLE_PERMISSIONS` should not become a route to grant itself or other protected permissions indirectly.

The package already models managed `PermissionTier::ADMIN_SAFE` and `PermissionTier::SUPER_ADMIN_ONLY`. In the installed package, `Permission::getManagedTier()` exposes that classification, but `GrantPermissionToCustomRoleHandler` checks only actor authorization, existence of the permission, and whether the role is custom; it does **not** inspect the tier before granting it. `Role::grantPermissionToCustom()` likewise checks only that the role is custom. Thus a `SUPER_ADMIN_ONLY` label alone does not enforce exclusive membership. The installed `UserRoleAssignmentAdministrationAuthorization` port receives only an actor ID, not the target role: it cannot by itself distinguish assigning `ROLE_SUPER_ADMIN` from assigning an ordinary role. `RoleAdministrationAuthorization` also receives only an actor ID, not the permission being granted.

A v0.4.0 outcome should make tier and elevation policy enforceable at the package-owned command boundary, not merely in Agent OS route middleware. An HTTP gate is useful early defense, but console, worker, and future MCP callers can also dispatch package commands.

## Proposed invariants to decide in Wayfinder

1. A managed `SUPER_ADMIN_ONLY` permission may be included **only** in the version-controlled managed `ROLE_SUPER_ADMIN`; no custom role, scoped admin role, ordinary managed role, or direct user grant may acquire it, even when the acting user is a Super Admin. Reject an attempt; do not silently skip the permission. Confirm whether the package can identify the designated managed role by a policy-owned stable ID rather than a role-name shortcut.
2. A managed `ADMIN_SAFE` permission can be granted to a custom role only after an authorized actor and target/scope have been checked. `ADMIN_SAFE` means eligible for controlled delegation, **not** authorization for every workspace/repository or every administrator. Fight Agent OS currently has installation-wide authority only; scoped delegation must wait for an explicit scoped model.
3. Decide how **custom permissions with a null tier** participate in delegation. Do not treat null as `ADMIN_SAFE` by default. Either introduce an explicit classification or fail closed until a documented safe policy is selected.
4. Reconciliation of managed policy must reject a definition that attaches `SUPER_ADMIN_ONLY` permissions to an ordinary managed role; runtime command paths must reject equivalent custom-role mutations. Review historical stored role memberships and provide a guarded detection/remediation path for pre-existing invalid grants, including in-memory/projection authority; changing the definition alone cannot make existing authority safe.
5. Assigning the managed `ROLE_SUPER_ADMIN` to a user must require both ordinary role-association authority (`MANAGE_USER_ROLES`) and explicit `ASSIGN_SUPER_ADMIN` authority, plus the consumer's confirmation/audit protocol. A generic actor-only `UserRoleAssignmentAdministrationAuthorization` check cannot enforce the target-specific part. Do not permit a role ID, a renamed custom role, or a different adapter to bypass it. Decide separately what authorization, confirmation and last-admin safeguards apply to **removing** that role; `ASSIGN_SUPER_ADMIN` must not silently imply removal authority.
6. Make granting/revoking custom-role permissions target-aware. A future holder of `MANAGE_ROLE_PERMISSIONS` may change only allowed `ADMIN_SAFE` associations, for roles and scopes they are authorized to manage; the role may never modify managed-role membership. In particular, an administrator must not be able to grant themselves `MANAGE_ROLE_PERMISSIONS` or another reserved permission via a custom role. If `MANAGE_ROLE_PERMISSIONS` remains `SUPER_ADMIN_ONLY`, it must not be delegable to scoped admins; a later narrower delegated capability would require separate policy.
7. Enforce policy for every supported entry point, including direct command-bus use; keep checks inside the same authoritative transaction/locking boundary as the change where racing policy or assignment revisions could invalidate the decision. Fail closed on missing or inconsistent role, permission, actor, or scope authority. Emit safe audit evidence without exposing credentials or internal state to HTTP clients.

## Candidate package seams to evaluate, not prescribed implementation

- Add target-aware authorization contracts/policy checks around `GrantPermissionToCustomRole`, `RevokePermissionFromCustomRole`, `AssignRoleToUser`, and `RemoveRoleFromUser`, rather than relying exclusively on the existing actor-only ports. Keep package-owned invariants (eligible tier and managed-role safety) in the owning package; let the consumer supply its current-principal and eventual scope authorization through explicit ports.
- Check `ManagedPolicy` reconciliation and role reconstruction as well as mutation commands. Decide whether to centralize tier eligibility in a Domain policy shared by managed-role and custom-role paths, or an equivalent fail-closed package boundary.
- Avoid giving a caller a universal `canManageRoles()` boolean that authorizes any target permission or role. Make target permission tier, target role, actor authority, and any scope explicit to the policy under review.
- Do not modify `vendor/` or rely on an unreleased package change in Agent OS by default. Qualify/package/tag upstream changes, then deliberately update the consumer lockfile and verify the full integration matrix.

## Acceptance evidence to request in the owning package

- A custom-role grant of `SUPER_ADMIN_ONLY` is rejected for both ordinary administrators and a Super Admin, through the real package command handler; there is no partial role change or success event.
- An authorized grant of `ADMIN_SAFE` to an eligible custom role succeeds; unauthorized actors, unavailable authority, invalid scopes, managed-role targets, and null-tier decisions fail safely.
- Malformed managed policy attempting to grant `SUPER_ADMIN_ONLY` outside the designated managed role fails before reconciliation changes authority. Pre-existing forbidden memberships are detected and handled according to an approved migration/remediation policy.
- Ordinary user-role assignments work; assigning `ROLE_SUPER_ADMIN` without **both** required authorities is rejected through every supported command entry point. Removal and last-admin cases follow an explicit separately tested decision.
- Cross-adapter and concurrent attempts cannot slip through an HTTP-only check, stale actor/permission/role state, or a race between validation and mutation. Failed operations leave consistent persistence and safe audit/event outcomes.
- All package tests and the consumer's focused authorization/PostgreSQL/HTTP tests pass; the Fight Agent OS canonical `./bin/build` passes after adopting a tagged release. Record warnings and limits honestly.

## Source facts to verify against the owning repository

These paths describe the installed Fight Access Control dependency in Fight Agent OS at proposal time; verify current upstream code and version before planning v0.4.0:

- `vendor/johnnickell/fight-access-control/src/Domain/AccessControl/Permission/{PermissionTier,Permission}.php`
- `vendor/johnnickell/fight-access-control/src/Domain/AccessControl/Role/Role.php`
- `vendor/johnnickell/fight-access-control/src/Application/AccessControl/Role/CommandHandler/GrantPermissionToCustomRoleHandler.php`
- `vendor/johnnickell/fight-access-control/src/Application/AccessControl/Role/Service/RoleAdministrationAuthorization.php`
- `vendor/johnnickell/fight-access-control/src/Application/AccessControl/User/CommandHandler/{AssignRoleToUserHandler,RemoveRoleFromUserHandler}.php`
- `vendor/johnnickell/fight-access-control/src/Application/AccessControl/User/Service/UserRoleAssignmentAdministrationAuthorization.php`
- `vendor/johnnickell/fight-access-control/src/Domain/AccessControl/ManagedPolicy/ManagedPermissionDefinition.php`

**Handoff:** Begin a Wayfinder decision in Fight Access Control to settle tier semantics, target-aware authorization, existing-data handling, delegated scoped administration, and role-elevation/removal guarantees before decomposing v0.4.0 implementation work. This note is a recommendation, not authorization to change the source package or to expand Agent OS planning automatically.
