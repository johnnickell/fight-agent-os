# Execution Standards

These rules govern implementation of one approved TASK.

## Scope and ownership

The TASK and its accepted parents authorize the change. Resolve ambiguous acceptance, blockers, repository identity, destructive operations, and file ownership before writing. Planning decomposition and product-scope expansion require a separate handoff.

Treat pre-existing modifications as unrelated until ownership is proved. Preserve them in place and exclude them from TASK diffs and commits. Put notes, logs, generated evidence, and other scratch beneath a TASK-owned `.runs/` directory; put isolated worktrees beneath `.runs/worktrees/`.

## Branches and commits

Use a `feature/*` branch from `develop` in the user-selected main checkout or isolated worktree. Establish branch ancestry and a status baseline before implementation. Never use cleanup, reset, checkout, or force operations against uncertain work.

One TASK normally produces one independently reviewable PR outcome. Stage explicit TASK-owned paths, inspect the staged diff, and keep the implementation commit bounded. A commit grants no push, publication, review, landing, merge, archive, release, or deployment authority.

## Verification and evidence

Use focused checks while iterating and run `./bin/build` as the complete local gate. Also inspect the final diff and status and run `git diff --check`. Refresh generated planning views before their read-only checks when the TASK authorizes planning edits.

Evidence names the approved scope, changed files, commands, fresh exit results, and test counts. Report all warnings, notices, deprecations, skips, unavailable checks, failures, and uncertainty. Historical or inherited receipts remain labeled as context. Keep secrets and credentials out of commands, logs, fixtures, evidence, and commits.

## Handoff

Before publication, update the TASK with completed implementation and verification while keeping review and merge state separate. The implementation handoff identifies branch and commit, acceptance covered, files changed, checks and counts, warnings, unresolved risks, unrelated work preserved, and the next authorized action.

Independent review must be performed by another reviewer. TASK `done` records accepted implementation and required local verification; it does not claim PR publication, merge, deployment, or release.
