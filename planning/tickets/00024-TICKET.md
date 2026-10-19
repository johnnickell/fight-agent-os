---
id: TICKET-00024
epic: EPIC-00005
title: Register repositories and designated checkouts
status: ready-for-agent
---

# Register repositories and designated checkouts

## Problem statement

The Workspace needs one stable identity for each intended repository collaboration and a conservative durable link
to the local checkout used by Dashboard operations. Paths, slugs, remotes, copied markers, and directory names are
mutable or ambiguous and cannot become identity or silent registration authority.

## Solution and boundaries

Deliver the one initial Workspace and Agent-OS-minted COMB-backed identities for Repository, Host, Checkout, and
Worktree. A Repository owns its mutable display name and Workspace-unique slug, lifecycle, optional primary source,
and equivalent source descriptors; Checkout and Worktree links remain distinct physical records.

Resolve local Git locations conservatively from existing links, Git common-directory evidence, an Agent-OS-owned
uncommitted Git configuration marker, and normalized source evidence. Require human confirmation before the first
durable link or any ambiguous relink. Let an authorized user select one linked Worktree as the designated local
checkout for ordinary Dashboard filesystem operations.

The approved [TASK-00498](../tasks/00498-TASK.md) adds explicit User/Agent Repository access grant/revoke as a
separate Repository-owned use case. Access Control retains identities, Roles and Permissions; registration,
configuration, Team membership and Start do not become access writers. This supplies the concrete access boundary
needed by configuration and execution without introducing multi-workspace assignment or a generic RBAC platform.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Register a repository | `RegisterRepository` | Preview Workspace slug/source conflicts and matching evidence | `RepositoryRegistered` | One stable Repository identity and explicit metadata are created; no checkout or Planning mutation is implied |
| Link the first checkout | `LinkCheckout` | Inspect Host, canonical path, Git common directory, marker, worktrees, and normalized remotes | `CheckoutLinked`, `WorktreeLinked` | Confirmed Checkout/Worktree identities and a non-committed Agent OS Git marker are recorded without changing branches or source files |
| Select the Dashboard checkout | `DesignateWorktree` | List linked available Worktrees with branch, path, Host, and availability | `WorktreeDesignated` | Exactly one linked Worktree becomes the repository's ordinary Dashboard filesystem target |
| Reconcile a moved, copied, or recloned checkout | `RelinkCheckout` or explicit conflict resolution | Explain existing marker, common-directory, source, and path evidence | `CheckoutRelinked` or conflict/refusal outcome | A confirmed link changes while Repository identity and history remain stable; copied/conflicting evidence never resolves silently |
| Change source descriptors | `ChangeRepositorySource` | Preview provider-native and normalized source conflicts | `RepositorySourceChanged` | Primary/equivalent source evidence changes explicitly without changing Repository identity |
| Archive or restore a repository | `ArchiveRepository`, `RestoreRepository` | Query lifecycle, active Planning/execution links, and retained checkout history | `RepositoryArchived`, `RepositoryRestored` | New Planning/Execution is disabled or restored while identity, links, and history remain durable |
| Inspect repository availability | N/A — read-only interaction | `GetRepositoryRegistration`, `ListRepositoryCheckouts`, `ExplainCheckoutMatch` | N/A — availability is an observation | Users see exact identity, source, designated checkout, matching evidence, ambiguity, and next action |
| Manage explicit Repository access | Expected-state grant/revoke for a typed User or Agent | Scoped relationship/effective-access/history and original-result inspection | Actual explicit access granted/revoked facts | Relationship, monotonic revision, audit and original result commit together; Roles, operation Permissions and execution authority remain unchanged |

## Validation and permissions

Generate identities internally; never accept caller-selected IDs during ordinary creation. Enforce one active
Repository per approved source identity within the Workspace, unique active slugs, valid lifecycle transitions,
and explicit source/fork/equivalence relationships. Directory name and slug similarity provide no matching
authority.

Before link or relink, canonicalize the path under a root approved by TICKET-00023; verify Git evidence, ownership,
regular directory shape, and containment; reject symlink escape, unavailable Hosts, ambiguous common directories,
unknown existing markers, and marker conflicts. A normal clone does not inherit a checkout identity. Linked Git
worktrees share common-directory evidence but keep separate Worktree identities.

Viewing registration, registering, changing metadata/source, linking/relinking, designating a Worktree, and
archive/restore require independently justified server-side Permissions. Registration alone never migrates
Planning, changes branches, rewrites instructions, grants Agent/Workflow authority, or authorizes commit, push,
PR, or merge.

Access management follows [TASK-00498's approved contract](../tasks/00498-TASK.md#approved-operation-permissions):
`READ_REPOSITORY_ACCESS` and `MANAGE_REPOSITORY_ACCESS` are separate `ADMIN_SAFE` Permissions, each combined with
current Repository access. Super Admin and the initial Workspace Admin Role supply their existing administrator
access paths; other human/Agent access is explicit. Role definitions, Permission memberships and assignments remain
independent. `ROLE_REPOSITORY_ADMIN` and `ROLE_DEVELOPER` are human Roles in the
[ubiquitous language](../../docs/UBIQUITOUS_LANGUAGE.md#human-roles), not Agent profiles or new access bypasses.

## Acceptance and evidence

- Repository, Host, Checkout, and Worktree identities remain stable across supported path moves and explicit
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
- Explicit access grant/revoke enforces current issuer authority and expected relationship state, preserves
  alternative administrator paths, and commits relationship/revision/audit/original result together. Stale requests,
  revoke/regrant, retries and concurrent protected operations produce the outcomes specified by TASK-00498.
- Focused Domain/Application and PostgreSQL behavior tests cover identity, access, uniqueness, concurrency, lifecycle,
  and matching decisions; real Git checkout/worktree cases are manually exercised without testing Git itself.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00077](../tasks/00077-TASK.md) | Accept the repository identity and link-consistency ADR | in-progress |
| [TASK-00078](../tasks/00078-TASK.md) | Register and inspect stable repositories | ready-for-agent |
| [TASK-00079](../tasks/00079-TASK.md) | Confirm and designate checkout and worktree links | ready-for-agent |
| [TASK-00080](../tasks/00080-TASK.md) | Complete repository reconciliation and lifecycle operations | ready-for-agent |
| [TASK-00498](../tasks/00498-TASK.md) | Grant and revoke Repository access for Users and Agents | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements [WF-008 — Establish repository identity and registration boundaries](../wayfinder/tickets/WF-008-establish-repository-identity-and-registration-boundaries.md).
It depends on TICKET-00023 approved-root availability and supplies Repository/Checkout/Worktree identities to
TICKET-00025, TICKET-00026, TICKET-00028, and TICKET-00029. New-project creation remains outside EPIC-00005.

John subsequently approved TASK-00498 and its two Permission definitions, with prerequisites TASK-00036/00078 and
new prerequisite edges from TASK-00081/00139. This resolves access-writer ownership; it changes no existing priority
or assignment. Correct transactions, revocation and retries are part of implementing the use case, not a separate
qualification phase. Role assignment/removal stays with TASK-00121; Workspace assignment remains deferred.
