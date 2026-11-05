---
id: TICKET-00024
epic: EPIC-00005
title: Register Projects and designated checkouts
status: ready-for-agent
---

# Register Projects and designated checkouts

## Problem statement

The Workspace needs one stable Project identity for each intended collaboration/publication target and a conservative durable link
to the local checkout used by Dashboard operations. Paths, slugs, remotes, copied markers, and directory names are
mutable or ambiguous and cannot become identity or silent registration authority.

## Solution and boundaries

Deliver the one initial Workspace and typed COMB-backed identities for Project, Host, Checkout and Worktree.
Creation entry adapters generate IDs with their owning factories and pass them in void commands. A Project owns
its mutable display name and Workspace-unique slug, lifecycle, optional primary Git source and equivalent source
descriptors; Checkout and Worktree links remain distinct physical records. Use `Project`, `ProjectId`,
`ProjectRepository`, `RegisterProject`, `RegisterProjectHandler` and `ProjectRegistered` under the
[aggregate-first contract](../../docs/engineering/STANDARDS.md#architecture). Registration is an operation, not
another aggregate or Store. Registered handlers coordinate collection-like repositories and required owned audit
through generic `TransactionalUnitOfWork` on the exact shared connection; success events follow commit.

Resolve local Git locations conservatively from existing links, Git common-directory evidence, an Agent-OS-owned
uncommitted Git configuration marker, and normalized source evidence. Require human confirmation before the first
durable link or any ambiguous relink. Let an authorized user select one linked Worktree as the designated local
checkout for ordinary Dashboard filesystem operations.

The approved [TASK-00498](../tasks/00498-TASK.md) adds explicit User/Agent Project access grant/revoke as a
separate Project-owned use case. Access Control retains identities, Roles and Permissions; registration,
configuration, Team membership and Start do not become access writers. This supplies the concrete access boundary
needed by configuration and execution without introducing multi-workspace assignment or a generic RBAC platform.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Register a Project | `RegisterProject` carrying adapter-generated `ProjectId` | Preview Workspace slug/source conflicts and matching evidence | `ProjectRegistered` | Void dispatch creates one Project and required audit; the adapter already knows its ID; no checkout, access or Planning mutation is implied |
| Link the first checkout | `LinkCheckout` | Inspect Host, canonical path, Git common directory, marker, worktrees, and normalized remotes | `CheckoutLinked`, `WorktreeLinked` | Confirmed Checkout/Worktree identities and a non-committed Agent OS Git marker are recorded without changing branches or source files |
| Select the Dashboard checkout | `DesignateWorktree` | List linked available Worktrees with branch, path, Host, and availability | `WorktreeDesignated` | Exactly one linked Worktree becomes the Project's ordinary Dashboard filesystem target |
| Reconcile a moved, copied, or recloned checkout | `RelinkCheckout` or explicit conflict resolution | Explain existing marker, common-directory, source, and path evidence | `CheckoutRelinked` or conflict/refusal outcome | A confirmed link changes while Project identity and history remain stable; copied/conflicting evidence never resolves silently |
| Change source descriptors | `ChangeProjectSource` | Preview provider-native and normalized source conflicts | `ProjectSourceChanged` | Primary/equivalent source evidence changes explicitly without changing Project identity |
| Archive or restore a Project | `ArchiveProject`, `RestoreProject` | Query lifecycle, active Planning/execution links, and retained checkout history | `ProjectArchived`, `ProjectRestored` | New Planning/Execution is disabled or restored while identity, links, and history remain durable |
| Inspect Project availability | N/A — read-only interaction | `GetProjectById`-style safe detail, `ListProjectCheckouts`, `ExplainCheckoutMatch` | N/A — availability is an observation | Users see exact identity, source, designated checkout, matching evidence, ambiguity, and next action |
| Manage explicit Project access | Expected-state grant/revoke for a typed User or Agent | Scoped relationship/effective-access/history Views | Actual explicit access granted/revoked facts | Relationship, monotonic revision and required audit commit together; Roles, operation Permissions and execution authority remain unchanged |

## Validation and permissions

Do not accept arbitrary untrusted client IDs. Ordinary creation has no original-response replay guarantee: after
commit/response loss a new attempt may conflict; a fresh authorized list/detail query reveals state and ID. Keep
required audit independently. Physical link/relink retains ADR 0005's exact-operation results and recovery.
Enforce one active Project per approved source identity within the Workspace, unique active slugs, valid lifecycle transitions,
and explicit source/fork/equivalence relationships. Directory name and slug similarity provide no matching
authority.

Before link or relink, canonicalize the path under a root approved by TICKET-00023; verify Git evidence, ownership,
regular directory shape, and containment; reject symlink escape, unavailable Hosts, ambiguous common directories,
unknown existing markers, and marker conflicts. A normal clone does not inherit a checkout identity. Linked Git
worktrees share common-directory evidence but keep separate Worktree identities.

Each entry adapter authenticates, validates and checks the independently justified operation Permission and
Workspace/Project scope before configured bus dispatch. Ordinary admitted requests may finish after revocation;
the next request checks fresh authority. Exact physical confirmation and current phase admission remain required. Registration alone never migrates
Planning, changes branches, rewrites instructions, grants Agent/Workflow authority, or authorizes commit, push,
PR, or merge.

Access management follows [TASK-00498's approved contract](../tasks/00498-TASK.md#approved-operation-permissions):
target `READ_PROJECT_ACCESS` and `MANAGE_PROJECT_ACCESS` are separate `ADMIN_SAFE` Permissions, each combined with
current Project access. Super Admin and the initial Workspace Admin Role supply their existing administrator
access paths; other human/Agent access is explicit. Role definitions, Permission memberships and assignments remain
independent. Existing human Role vocabulary `ROLE_REPOSITORY_ADMIN` and `ROLE_DEVELOPER` are human Roles in the
[ubiquitous language](../../docs/UBIQUITOUS_LANGUAGE.md#human-roles), not Agent profiles or new access bypasses.

## Acceptance and evidence

- Project, Host, Checkout, and Worktree identities remain stable across supported path moves and explicit
  relinking while mutable locators remain evidence only.
- Registration and source uniqueness are enforced transactionally under competing requests.
- Existing link, shared common directory, unique source proposal, fork/upstream ambiguity, missing source, moved
  checkout, copied marker, and conflicting marker cases produce the accepted automatic, confirmation, or refusal
  outcomes.
- The first durable link and every ambiguous relink require an authorized human confirmation with complete safe
  evidence.
- Exactly one available linked Worktree may be designated; changing it is explicit and does not mutate either
  working tree.
- Archive/restore retains every identity and historical link and does not delete local files or Planning records.
- Explicit access grant/revoke uses entry admission and expected relationship state, preserves alternative
  administrator paths, and commits relationship/revision/audit together. Stale requests and revoke/regrant retain
  monotonic history; lost responses use fresh authorized state/history reads, not original-result replay.
- Focused Domain/Application and PostgreSQL behavior tests cover identity, access, uniqueness, concurrency, lifecycle,
  and matching decisions; real Git checkout/worktree cases are manually exercised without testing Git itself.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00077](../tasks/00077-TASK.md) | Accept the repository identity and link-consistency ADR | done |
| [TASK-00078](../tasks/00078-TASK.md) | Register and inspect stable repositories | done |
| [TASK-00079](../tasks/00079-TASK.md) | Confirm and designate checkout and worktree links | ready-for-agent |
| [TASK-00080](../tasks/00080-TASK.md) | Complete Project reconciliation and lifecycle operations | ready-for-agent |
| [TASK-00498](../tasks/00498-TASK.md) | Grant and revoke Project access for Users and Agents | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

TASK-00566 applies the accepted standards and ADR amendments to unfinished requirements. Names here are targets,
not current executable APIs. TASK-00574/00575 own bootstrap/registration source and needed-data migration;
TASK-00498 owns access naming/consumers/catalog together. Existing Role names and exact `repository_id` marker
bytes remain until their explicitly owned cutovers. Completed TASK-00077/00078 and their approvals stay historical.

Implements [WF-008 — Establish repository identity and registration boundaries](../wayfinder/tickets/WF-008-establish-repository-identity-and-registration-boundaries.md).
It depends on TICKET-00023 approved-root availability and supplies Project/Checkout/Worktree identities to
TICKET-00025, TICKET-00026, TICKET-00028, and TICKET-00029. New-project creation remains outside EPIC-00005.

John subsequently approved TASK-00498 and its two Permission definitions, with prerequisites TASK-00036/00078 and
new prerequisite edges from TASK-00081/00139. This resolves access-writer ownership; it changes no existing priority
or assignment. Correct transactions and recovery are part of implementing the use case, not a separate qualification phase. Role assignment/removal stays with TASK-00121; Workspace assignment remains deferred.
