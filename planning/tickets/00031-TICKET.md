---
id: TICKET-00031
epic: EPIC-00004
title: Read authority catalogs and administer custom roles
status: ready-for-agent
---

# Read authority catalogs and administer custom roles

## Problem statement

An operator needs safe role/permission visibility and custom-role administration without making installation-wide authority delegable, mutating managed roles, or treating a stored permission tier as enforcement. The currently locked Fight Access Control v0.3.0 package is baseline evidence, not proof of the required protected-tier and handler/authorization contracts. TASK-00126 qualifies capabilities against an available tagged release and records its exact version and commit at implementation.

## Solution and boundaries

Provide Bearer-protected `GET /roles`, `/roles/{roleId}` (`READ_ROLES`) and `GET /permissions`, `/permissions/{permissionId}` (`READ_PERMISSIONS`) through package read queries with bounded pagination and explicit no-store Views. `POST /roles` (`CREATE_ROLES`), `PATCH /roles/{roleId}` (`UPDATE_ROLES`) and `DELETE /roles/{roleId}` (`DELETE_ROLES`) manage **custom roles only** through package commands; no general permission CRUD exists. Add `PUT`/`DELETE /roles/{roleId}/permissions/{permissionId}` (`MANAGE_ROLE_PERMISSIONS`) only after the tagged upstream release is inspected and the Agent OS actor/scope checks and actual package-owned custom-role/tier invariants are verified. Expose an accessible custom-role management interface after the safe APIs.

Out of scope: managed-role mutation, assigning `SUPER_ADMIN_ONLY` permissions to custom roles, permission creation/update/delete, ad hoc NULL-tier delegation, arbitrary admin bypass, scoped role/team membership (future work), raw package entities, and presenting upstream planning or a prospective version number as a qualified consumer contract.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Browse authority catalog | None | `ListRoles`, `GetRoleById`, `ListPermissions`, `GetPermissionById` | None | Safe paginated managed/custom role and read-only permission views |
| Maintain custom roles | `CreateCustomRole`, `RenameCustomRole`, `RemoveCustomRole` | Resolve actor and managed/custom target state | Package role events and durable safe audit | Only custom role definition changes, with assignment/reference conflicts failing safely |
| Delegate custom-role permissions | `GrantPermissionToCustomRole`, `RevokePermissionFromCustomRole` through Agent OS authorized Application service | Resolve current actor, target role, grant tier and permission state | Package events and durable safe audit | Only eligible non-global permission associations commit |
| Operate role UI | Existing bounded APIs | Current catalog/current-principal projection | Client invalidation only | Accessible previews, confirmation and refetch without client-only authority |

## Validation and permissions

All read/CRUD/delegation permissions are installation-wide `SUPER_ADMIN_ONLY` managed permissions, initially granted only to `ROLE_SUPER_ADMIN` and checked by current authoritative permission. TASK-00117 FQCN Action attributes block unauthorized HTTP dispatch; a shared Agent OS Application authorization boundary checks current actor permission and installation/workspace/repository scope for every HTTP and non-HTTP entry adapter. The qualified package must enforce its intrinsic custom-role, managed-reference and tier eligibility invariants; do not duplicate those checks in the application if the tagged contract proves them. A package handler's planned removal of consumer permission checks is not a waiver for console/Agent entry paths. In v0.3.0, custom-role grant handlers do **not** enforce the desired tier policy. The required contract has non-null tiers for every Permission, with new custom Permissions starting `ADMIN_SAFE`, not null, as traced in upstream WF-012; TASK-00126 must qualify it and deliberately adapt the consumer schema/repository. Until a tagged release is inspected and accepted with those safe invariants, deny all custom-role permission grants; do not substitute an HTTP-only check. `SUPER_ADMIN_ONLY` and malformed/null-tier delegation must remain forbidden across every entry path. If the tagged package does not guarantee this, stop for an explicit owner/policy decision before enabling grants. Reconcile any future `ADMIN_SAFE` scoped roles with independently bounded target authorization before allowing Workspace/Repository Admin grants.

Read Views must not expose member lists or internal tokens, grant/hash/audit/persistence internals, or imply roles are mutable when managed. Mutations require validated names/IDs/revisions/confirmations, safe target/permission existence behavior and audit; do not publish a route until its underlying guard exists. Client permission checks are display hints only.

## Acceptance and evidence

- Catalog reads are pagination-bounded, safely shaped and gated by `READ_ROLES`/`READ_PERMISSIONS` before package dispatch.
- Managed roles cannot be renamed, removed or granted through custom-role operations; CRUD changes only permitted custom roles with safe conflicts and audit.
- The required upstream authorization/tier/persistence capabilities are verified in a tagged release and intentional locked consumer/schema update, not inferred from Wayfinder decisions or a prospective version; exact tested version/commit, contract differences and tier/target ownership are recorded.
- Shared Agent OS Application services check actor permission and scope for every entry adapter; the qualified package enforces custom-role/tier invariants, including races and unknown-tier failure. A missing upstream guarantee blocks enablement pending an explicit owner decision.
- Accessible role views/controls use exact permission names and authoritative refetch, no fabricated permission CRUD.
- Focused package-boundary/PostgreSQL/HTTP/client/security/accessibility checks and `./bin/build` pass with warnings disclosed.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00124](../tasks/00124-TASK.md) | Expose guarded role and permission catalogs | ready-for-agent |
| [TASK-00125](../tasks/00125-TASK.md) | Manage custom role definitions without changing managed roles | ready-for-agent |
| [TASK-00126](../tasks/00126-TASK.md) | Qualify the upstream authorization contract and consumer update | needs-info |
| [TASK-00127](../tasks/00127-TASK.md) | Guard custom-role permission delegation at every entry path | ready-for-agent |
| [TASK-00128](../tasks/00128-TASK.md) | Operate custom roles and the read-only permission catalog | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Human-approved TASK-00116 expansion of EPIC-00004. TASK-00126 qualifies the required upstream contract and consumer adapters before TASK-00036 reconciles stable protected managed authority. The selected upstream release may remove duplicate handler permission checks; cross-user session, email-change administration and pending-invitation correction actor/target authorization callbacks are separate and retained per upstream WF-013, subject to tagged contract verification. Treat tier eligibility as a required package invariant subject to consumer release qualification; Agent OS keeps delegation unavailable until that guarantee is verified and protects actor/scope at its own entry boundaries. No role/permission endpoints exist in the current application.
