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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 4 of EPIC-00014's seven-TICKET split. Reuses [EPIC-00005](../epics/00005-EPIC.md)
registered Repository identities and [WF-035](../wayfinder/tickets/WF-035-define-project-teams-and-agent-selection.md).
Supplies membership to [TICKET-00063](00063-TICKET.md) and the selector in [TICKET-00064](00064-TICKET.md);
no Repository grant, Team, Agent or association was created in planning.
