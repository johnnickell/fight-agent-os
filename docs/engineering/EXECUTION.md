# Execution Standards

These rules govern implementation of one approved TASK.

## Scope and ownership

The TASK and its accepted parents authorize the change. Resolve ambiguous acceptance, blockers, repository identity, destructive operations, and file ownership before writing. Planning decomposition and product-scope expansion require a separate handoff.

Treat pre-existing modifications as unrelated until ownership is proved. Preserve them in place and exclude them from TASK diffs and commits. Put notes, logs, generated evidence, and other scratch beneath a TASK-owned `.runs/` directory; put isolated worktrees beneath `.runs/worktrees/`.

## Branches and commits

Use a `feature/*` branch from `develop` in the user-selected main checkout or isolated worktree. Establish branch ancestry and a status baseline before implementation. Never use cleanup, reset, checkout, or force operations against uncertain work.

One TASK normally produces one independently reviewable PR outcome. Stage explicit TASK-owned paths, inspect the staged diff, and keep the implementation commit bounded. A commit grants no push, publication, review, landing, merge, archive, release, or deployment authority.

## Review handoff intake

Resolve the base worktree through canonical Git common-directory and registered-worktree metadata, selecting and verifying the sole worktree whose Git directory equals the common directory. Do not assume the current checkout is the base, trust list order without validation, derive it from parent paths, or search arbitrary directories. The only implementation-review handoff is `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`, as defined by the [review standards](REVIEW.md). Immutable siblings named `review-<NNNNN>.md` retain sequence history but are never alternative handoffs.

Every `work` intake states whether it is first implementation or requested revision and checks the canonical path and sequence directory automatically. Absence is normal only when both canonical and history are absent during first implementation. Require regular non-symlink files, contiguous five-digit positive history, and canonical bytes identical to the highest sequence; reject malformed names, gaps, divergence, or history without canonical state rather than guessing which review is latest.

A usable handoff has review version 2, every defined identity key, and the full ordered report. Its positive sequence and history path match the highest numbered file. Validate its TASK, repository/base identity, canonical and history paths, target identifier and branch state, full reviewed base/head OIDs, exact status, manifest, reviewed artifact count, content digest, and verdict against the selected current target. Rebuild the canonical JSON Lines snapshot manifest, including each selected reviewed ignored root and descendant. Re-enumeration must detect path, addition, removal, type, mode, symlink-target, and content changes even when Git status and `HEAD` remain unchanged. A changed local path alone is permissible only when all immutable target and content identity still matches unambiguously. Version 1 and unversioned reports require a fresh review; their missing artifact or sequence identity must not be guessed.

Reject stale, superseded, mismatched, partial, duplicate, redirected, unreadable, interrupted, or ambiguous review material rather than using chat history, searching linked worktrees, editing findings, choosing a numbered report, or silently falling back to first-implementation behavior. A matching `revise` report is authoritative correction input: preserve its findings and trace revision work to each blocker. A matching `accept` report does not authorize more implementation; route it to landing unless separate approved work has invalidated it and requires a new review.

## Verification and evidence

Use focused checks while iterating and run `./bin/build` as the complete local gate. Also inspect the final diff and status and run `git diff --check`. Refresh generated planning views before their read-only checks when the TASK authorizes planning edits.

Evidence names the approved scope, changed files, commands, fresh exit results, and test counts. Report all warnings, notices, deprecations, skips, unavailable checks, failures, and uncertainty. Historical or inherited receipts remain labeled as context. Keep secrets and credentials out of commands, logs, fixtures, evidence, and commits.

## Handoff

Before publication, update the TASK with completed implementation and verification while keeping review and merge state separate. The implementation handoff identifies branch and commit, acceptance covered, files changed, checks and counts, warnings, unresolved risks, unrelated work preserved, and the next authorized action.

Independent review must be performed by another reviewer. TASK `done` records accepted implementation and required local verification; it does not claim PR publication, merge, deployment, or release.
