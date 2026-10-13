---
id: TICKET-00062
epic: EPIC-00014
title: Administer Repository-linked Teams and Agent rosters
status: ready-for-agent
---

# Administer Repository-linked Teams and Agent rosters

## Problem statement

A working Agent cannot be attached to an inferred team or gain project authority from a display name. There
is no product Team/Repository/Agent roster today. The first Explorer and every later Agent must belong to
**exactly one** real Team, with stable Repository associations and safe transfer behavior.

## Solution and boundaries

Give a current authorized Super Admin safe browser and server operations to create/manage Teams, explicitly
associate each Team with one or more registered Repositories, select exactly one preferred default Team per
Repository where Teams are assigned, list alternatives, inspect rosters and manage membership. One Team may
serve multiple Repositories; a Repository may have several eligible Teams. Associate Agent identities with
exactly one Team and record attributable, revisioned roster/Repository/default changes. Transfer only a
truly idle Agent under an explicit confirmed administrative decision; an active session and its provenance
remain bound to their recorded Team, and a busy/waiting ambiguous identity does not silently move. Remove or
unlink references only when current assignments and active work remain valid, otherwise hold safely. Supply
current roster and associations to [TICKET-00064](00064-TICKET.md) without claiming a TASK or scheduling work.

Team membership is **not** an AccessControl Role, a grant of direct Permissions, a Repo permission, or a
Workflow authorization. Do not create Workspace/Project Administrator roles, cross-Team peer delegation or
an extra Repository identity/checkout registry. Team Leads may coordinate only under their assigned bounded
Workflow grants; they cannot administer membership.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Establish Team/Repository association | Agent OS-owned Team create/link/set-default operations | Registered Repository identity, current associations and revisions | Attributed Team/association facts after commit | Stable Team and exactly one explicit preferred default for a Repository |
| Inspect and edit roster | Assign/unassign or confirmed idle-transfer operation | Current Agent identity, Team membership, active session/work commitments | Membership and safe audit facts after commit | One-Team membership or safe refusal without altering in-flight sessions |
| View Team administration | N/A — read-only authorized UI query | Safe roster and Repository/default associations | N/A — no business event for viewing | Current bounded View with no credential, private tool data or secrets |

## Validation and permissions

Use distinct current managed Team/Agent-management Permissions initially restricted to the managed Super Admin
Role; per the later approved [EPIC-00017](../epics/00017-EPIC.md) browser administration is
`SUPER_ADMIN_ONLY` for now, with **no** implicit custom-Role/Agent delegation. Check actor, actual Repository,
Team, target, expected revision and active commitments on HTTP and direct entry paths. Enforce one-Team membership,
unique default and association consistency with PostgreSQL constraints/fences against competing edits, transfer
and Workflow start. Report stale/default conflicts, unknown Repository, lost authority or active ownership with
safe refetch/confirmation; never change another running session by changing the roster.

## Acceptance and evidence

- A Super Admin establishes a Team on a real registered Repository and a deterministic default/alternate
  roster; the first Explorer can be assigned to that Team without implied Repository access.
- Competing transfers/default edits and active-session/Workflow races preserve one-Team membership and never
  rewrite a running session's Agent, Team or grant. Revoked admin permission denies direct and UI writes.
- Accessible browser list/detail/edit/confirmation/denial states are truthful and secret-free. Owned
  Team/repository/roster/post-commit behavior tests, actual PostgreSQL races and `./bin/build` pass.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00251](../tasks/00251-TASK.md) | Establish authorized Teams on registered Repositories | ready-for-agent |
| [TASK-00252](../tasks/00252-TASK.md) | Manage Repository associations and preferred Teams | ready-for-agent |
| [TASK-00253](../tasks/00253-TASK.md) | Enforce one-Team Agent membership and shared admission fences | needs-info |
| [TASK-00254](../tasks/00254-TASK.md) | Transfer idle Agents and remove associations safely | needs-info |
| [TASK-00255](../tasks/00255-TASK.md) | Operate Team and roster administration in the browser | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 4 of EPIC-00014's seven-TICKET split. Reuses [EPIC-00005](../epics/00005-EPIC.md)
registered Repository identities and [WF-035](../wayfinder/tickets/WF-035-define-project-teams-and-agent-selection.md).
Supplies membership to [TICKET-00063](00063-TICKET.md) and the selector in [TICKET-00064](00064-TICKET.md);
no Repository grant, Team, Agent or association was created in planning.

The maintainer approved five dependency-ordered TASKs:

- [TASK-00251](../tasks/00251-TASK.md): authorized Team creation/inspection/name management on a real registered
  Repository, with initial association/default and an explicit protected managed-policy extension.
- [TASK-00252](../tasks/00252-TASK.md): additional associations, complete default/alternative views and explicit
  preferred-Team changes, without changing existing assignments or grants.
- [TASK-00253](../tasks/00253-TASK.md): consistent one-Team initial Agent binding, roster/reference authority and
  shared transaction-duration admission fences.
- [TASK-00254](../tasks/00254-TASK.md): confirmed genuinely idle transfer and safe unlink/reference removal under
  current commitment/default/reference checks.
- [TASK-00255](../tasks/00255-TASK.md): accessible real browser Team/Repository/default/roster administration,
  consuming the actual named-Agent creation owner rather than duplicating provisioning.

TASK-00251/00252 are `ready-for-agent` behind their unfinished blockers. TASK-00253 retains `needs-info` for the
supported initial creation/membership integration and actual participant mapping: the inspected package service
generates the Agent ID and commits issuance internally, so a Team binding cannot be assumed from an outer
transaction or post-commit subscriber. A missing public seam blocks creation, not permission for an orphaned
Agent, hidden audit side effect, vendor patch or inferred upstream approval. TICKET-00063 consumes the supported
primitive and retains actual named-Agent creation.

TASK-00254 retains `needs-info` for actual manual-session/applicable Workflow commitment/admission primitives.
TICKET-00063's subsequent approved TASK-00258 now supplies TASK-00255's exact named creation blocker, resolving
its direct mapping to `ready-for-agent` behind unfinished dependencies. Supported creation/membership and actual
commitment-participant holds remain; this is neither implemented creation nor browser qualification. No unassign may leave a retained Agent with
zero Teams. Waiting for human review/PR merge alone does not imply busy, but active or uncertain commitments
block transfer. Transfers preserve historical Team/session/grant bindings and advance current authority epochs;
removal preserves valid defaults and assignments, with no force or cascade shortcut.

TASK-00244, TASK-00247 and TASK-00250 now explicitly consume TASK-00253. TASK-00231 consumes TASK-00254 and its
primitive dependency chain for actual writer qualification; downstream switch/restore/readiness inherit it.
These mappings do not clear remaining contract/host/Harness holds or qualify runtime behavior. Creation/current-use
consume early Team primitives, never TASK-00255's final browser journey; primitive session/commitment suppliers
cannot depend on final Explorer work or TASK-00241 readiness. Full Workflow automation and Planning database
cutover are not prerequisites for first manual Explorer use; unqualified admission paths remain disabled.

TICKET-00064 keeps selection, same-Team delegation and authoritative start integration with its existing owners;
this split creates no scheduling, commitment, claim or credential registry. All TASK orders remain unranked and
active TASK-00138/TASK-00226 are unchanged. Planning creates no Team, Agent, Permission, session or live effect.

With the subsequent TICKET-00063–00079 splits, twenty-one of the map's 24 TICKETs are decomposed; three remain,
TICKET-00080–00082. TASK-00258 consumes early Team binding. TASK-00261–00264 now consume roster/fence facts
for eligibility and owning start/delegation but do not resolve lower-level commitment-writer holds in TASK-00254.
Do not reverse-block that primitive on these consumers or require full Workflow selection for manual Explorer.
The separate TICKET-00034 detour is decomposed but does not finish older Harness planning.
TASK-00266–00272 now consume the existing role journeys without supplying these missing primitives or
becoming reverse prerequisites. Next planning operation: `/skill:to-tasks TICKET-00080`.
Implementation/worktree choice, fresh qualification, independent review, publication and operational authority
remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 355 records,
268 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T195256Z-42731`; summary log:
`.runs/build/ticket-00062-to-tasks.log`. These validate the planning change and existing repository, not future
Team/roster behavior, the creation/membership seam, commitment races or browser/Agent qualification. Independent
review and publication remain unperformed.
