---
id: TICKET-00002
epic: EPIC-00002
title: Establish safe TASK execution
status: ready-for-agent
---

# Establish safe TASK execution

## Problem statement

Fight Agent OS needs a project-local implementation workflow that can turn one approved TASK into bounded, verified work without importing Factory machinery, damaging unrelated local work, or assuming planning and merge authority.

## Solution and boundaries

Create concise shared engineering standards and a `work` skill that reads the approved TASK and relevant decisions, confirms checkout/worktree placement with the user, implements only authorized scope on `feature/*` work from `develop`, and records honest verification and handoff evidence. Prove the workflow with one bounded disposable or low-risk demonstration before relying on it for production application work.

The skill must preserve Domain/Application/Adapter, CQRS, Action–Domain–Responder, testing, branch, scratch, and canonical-build conventions without duplicating the full repository instructions. It may commit authorized implementation work but must stop before independent review, push, PR publication, merge, deployment, or release claims.

Out of scope: EPIC/TICKET/TASK creation or decomposition, product-scope changes, independent review, landing, PR merge, deployment, multi-agent coordination, and imported Factory workflows.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Prepare an approved TASK | N/A — this repository skill coordinates tools rather than dispatching application commands | Read TASK, parent TICKET, accepted decisions, repository instructions, branch, worktree, and local status | N/A — no application domain event is produced | User confirms main checkout or isolated worktree; authorized branch and scope are explicit |
| Implement bounded work | N/A — direct authorized file/tool operations | Inspect only source, tests, and documentation needed by the TASK | N/A — application events belong to the implemented use case, not this workflow | Testable implementation and tests are created without overwriting unrelated work |
| Verify and hand off implementation | N/A — verification and Git operations are workflow effects | Read focused-check and canonical-build results plus final diff/status | N/A — no workflow domain event exists | Focused checks and `./bin/build` run; authorized work is committed; evidence, warnings, and remaining uncertainty are recorded |
| Prove the workflow | N/A — disposable demonstration | Read demonstration inputs and resulting evidence | N/A — no application domain event is produced | A bounded proof shows intake, checkout choice, scope control, checks, evidence, and handoff behavior |

## Validation and permissions

The skill must reject or stop for missing approved TASK scope, ambiguous repository ownership, unauthorized branch or checkout changes, unexpected unrelated modifications, destructive operations without ownership evidence, missing required verification, or requests to exceed planning/merge authority. It must ask for checkout/worktree choice before implementation unless already provided and keep task-owned scratch under ignored `.runs/` paths; worktrees belong under `.runs/worktrees/`.

For bugs, require reproduction and a failing regression test before repair where technically possible. Run focused checks during iteration and the inherited `./bin/build` as the full gate. Do not convert warnings or incomplete checks into success claims. Secrets, credentials, and sensitive evidence must not enter commits or logs.

No application permission model applies because this is a repository-local agent capability. Repository write and Git commit effects require explicit TASK authorization; push, PR, merge, deployment, planning decomposition, and independent acceptance are not granted.

## Acceptance and evidence

- `.pi/skills/work/SKILL.md` exists, is concise, and loads project-owned shared standards rather than copying long rules.
- Shared standards capture the required architecture, testing, branch/worktree, scratch, scope-preservation, commit, evidence, and warning behavior.
- The skill differentiates implementation completion from review, landing, merge, release, and deployment.
- A bounded dry run or disposable demonstration exercises TASK intake, checkout/worktree choice, branch and scope controls, focused checks, canonical build, evidence recording, and handoff.
- Evidence identifies the exact approved scope and changed files, includes fresh focused verification and `./bin/build` results with test counts, and surfaces every warning or incomplete check.
- Evidence confirms scratch stayed under `.runs/`, unrelated work was preserved, and the skill neither created planning records nor exercised push/merge authority.
- A human performs the bootstrap review, or the future independent `review` skill reviews this work before `work` is used for production application changes.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00002](../tasks/00002-TASK.md) | Establish and prove safe TASK execution | done |
<!-- /planning:children -->

## Decisions and progress

Implements the `work` boundary approved by [WF-002](../wayfinder/tickets/WF-002-shape-agent-skill-suite.md) and the first executable frontier selected by [WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md). This is the first EPIC-00002 requirement to decompose into TASKs and is an explicit bootstrap workflow because `work` does not yet exist.
