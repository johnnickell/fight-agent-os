---
name: work
description: Use to implement one approved TASK as bounded, tested work on a feature branch, from checkout choice through commit and implementation handoff.
---

# Work

Turn one approved TASK into an implementation commit. Planning, independent review, publication, landing, merge, release, and deployment remain separate authority.

Before changing files, read the repository instructions and the shared [engineering](../../../docs/engineering/STANDARDS.md) and [execution](../../../docs/engineering/EXECUTION.md) standards.

## 1. Bound the work

1. Read the TASK, parent TICKET, relevant accepted decisions, and current Board.
2. Inspect repository identity, current branch/worktree, ancestry, and status. Record existing changes as unrelated unless the TASK clearly owns them.
3. Require executable TASK scope with resolved blockers. Ask for main-checkout or isolated-worktree placement before mutation unless the user already chose.
4. Write TASK-owned scratch only beneath `.runs/`; put an isolated worktree beneath `.runs/worktrees/`.

The intake is complete when approved scope and exclusions, placement, base, branch, and every pre-existing change are explicit. Stop for ambiguity, unexpected modifications, unsafe ownership, or missing authority.

## 2. Establish the branch

Use `feature/<description>` from `develop`. In the main checkout, create or continue the authorized feature branch without disturbing unrelated changes. For isolation, create the feature branch and worktree beneath `.runs/worktrees/`. Never hide, overwrite, clean, reset, or relocate work whose ownership is uncertain.

Continue only when branch ancestry and status are understood and the TASK owns the intended writes.

## 3. Implement the TASK

- Keep a dependency-ordered SUBTASK outline in TASK-owned scratch when the work needs coordination.
- Inspect and edit only what the accepted scope requires. Stop and ask before broadening product or planning scope.
- Make behavior testable, then add the smallest tests that prove acceptance. For a bug, first reproduce it and make a regression test fail when technically possible.
- Run focused checks as the change develops. Treat surprising generated files, secrets, unrelated failures, and destructive operations as stop conditions.

Implementation is complete only when every acceptance criterion is either demonstrated or explicitly unresolved.

## 4. Verify and record

1. Refresh generated planning views when authorized planning records changed.
2. Run focused checks, `git diff --check`, and the canonical `./bin/build`.
3. Inspect the final diff and status for scope, secrets, generated debris, and unrelated changes.
4. Record fresh commands, exit results, test counts, changed files, warnings, deprecations, skips, incomplete checks, and remaining uncertainty in the TASK completion notes. Keep detailed logs in TASK-owned scratch.

A failed or skipped required check remains incomplete; an inherited receipt is context, not fresh evidence.

## 5. Commit and hand off

Stage only TASK-owned paths, inspect the staged diff, and create the authorized implementation commit. Leave unrelated work untouched.

Hand off the TASK ID, branch and commit, approved scope, changed files, verification results and counts, warnings, unresolved risks, planning status, and preserved unrelated work. Request independent review; for a bootstrap exception, request the named human review required by the TASK.

Stop before review acceptance, push, PR publication, landing, merge, archive, release, or deployment unless a separate workflow and explicit authority grant that action.
