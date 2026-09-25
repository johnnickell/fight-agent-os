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
- Focused Domain/Application and PostgreSQL behavior tests cover identity, uniqueness, concurrency, lifecycle, and
  matching decisions; real Git checkout/worktree cases are manually exercised without testing Git itself.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Implements [WF-008 — Establish repository identity and registration boundaries](../wayfinder/tickets/WF-008-establish-repository-identity-and-registration-boundaries.md).
It depends on TICKET-00023 approved-root availability and supplies Repository/Checkout/Worktree identities to
TICKET-00025, TICKET-00026, TICKET-00028, and TICKET-00029. New-project creation remains outside EPIC-00005.
