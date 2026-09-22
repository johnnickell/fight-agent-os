---
id: TICKET-00004
epic: EPIC-00002
title: Establish controlled landing and human handoff
status: ready-for-agent
---

# Establish controlled landing and human handoff

## Problem statement

Reviewed implementation needs a deterministic closeout path that records acceptance, refreshes planning, verifies the final tree, publishes authorized work, and returns control to a human without silently re-reviewing or claiming merge, release, or deployment completion.

## Solution and boundaries

Create a `land` skill that verifies independent review signoff and correction of blocking findings, finalizes authoritative planning and documentation, marks implementation acceptance `done`, refreshes generated views, runs final checks, commits and pushes authorized landing updates, opens or updates the PR, performs only ownership-proven cleanup, and hands off for human PR review and merge.

`land` trusts but verifies the presence and disposition of review evidence; it does not replace `review`. TASK `done` means implementation acceptance and required verification are complete, not that GitHub merge, release, deployment, certification, or archive has occurred.

Out of scope: implementation review, unresolved defect repair, autonomous merge, release/deployment operations, broad environment cleanup, and planning decomposition.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Qualify work for landing | N/A — the skill coordinates planning, Git, and verification tools | Read TASK/TICKET state, independent review, finding dispositions, diff/status, and existing PR data | N/A — no application domain event is produced | Landing stops unless acceptance and review prerequisites are satisfied |
| Finalize implementation records | N/A — direct authorized planning/documentation edits | Read acceptance evidence and generated-view state | N/A — no workflow domain event exists | TASK evidence is finalized, status becomes `done`, and generated views are current |
| Publish the human handoff | N/A — Git and hosting operations are explicit side effects | Inspect final checks, commits, remote branch, and PR state | N/A — no application domain event is produced | Final checks pass; authorized updates are committed/pushed; PR is opened or updated; human receives merge control |
| Clean up TASK-owned resources | N/A — bounded filesystem/process/container operations | Inspect ownership markers, paths, PIDs, worktrees, databases, and containers | N/A — no workflow domain event exists | Only resources demonstrably owned by the TASK are removed or stopped |

## Validation and permissions

Landing must stop for missing independent review, unresolved blocking findings, stale or incomplete acceptance evidence, failing required checks, unexpected unrelated changes, ambiguous cleanup ownership, or missing publication authorization. It must run `./bin/planning-check --write`, `./bin/planning-check`, required focused checks, and the final `./bin/build`; warnings and incomplete verification remain visible.

Cleanup is limited to TASK-owned `.runs/` material, worktrees, prototype folders, captured processes, test databases, and task-specific containers or volumes. Shared services, source files, durable evidence, and ambiguously owned resources require explicit human direction.

No application permission model applies. The skill may finalize authorized planning, commit, push, and create/update a PR when the TASK permits. It may not merge, deploy, release, certify, archive without explicit separate authority, or convert planning requirements.

## Acceptance and evidence

- `.pi/skills/land/SKILL.md` exists and uses shared landing/ownership standards.
- The skill verifies independent review and finding disposition without repeating implementation review.
- It preserves the distinction among TASK `done`, PR publication, human merge, release, deployment, and archive.
- It refreshes generated planning views and requires the canonical final build before publication.
- A bounded demonstration covers successful handoff and at least one refusal path such as unresolved findings or ambiguous cleanup ownership.
- Demonstration evidence identifies all final planning changes, checks, commits, push/PR effects, cleanup decisions, warnings, and retained human actions.
- No demonstration merges a PR or deletes shared/ambiguous resources.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00004](../tasks/00004-TASK.md) | Establish and prove controlled landing | in-progress |
<!-- /planning:children -->

## Decisions and progress

Implements the `land` boundary approved by [WF-002](../wayfinder/tickets/WF-002-shape-agent-skill-suite.md). Its executable work follows the safe execution requirements in [TICKET-00002](00002-TICKET.md) and the independent review contract in [TICKET-00003](00003-TICKET.md).
