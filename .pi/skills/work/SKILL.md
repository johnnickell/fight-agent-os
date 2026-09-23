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
4. Resolve the base worktree from Git metadata: canonicalize the absolute common directory, enumerate registered worktrees, and select the sole worktree whose absolute Git directory equals that common directory. Verify its top level; never infer the base from the current directory, parent paths, or list ordering.
5. Set the canonical review path to `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`, recognize immutable history only at sibling `review-<NNNNN>.md` paths, and state whether this invocation is a first implementation or an explicitly requested revision. `review.md` alone selects the latest handoff; never choose a numbered file, search linked worktrees, or use chat/session history for findings.
6. Write TASK-owned scratch only beneath `.runs/`; put an isolated worktree beneath `.runs/worktrees/`.

The intake is complete when approved scope and exclusions, placement, base, branch, review mode and path, and every pre-existing change are explicit. Stop for ambiguity, unexpected modifications, unsafe ownership, or missing authority.

## 2. Establish the branch

Use `feature/<description>` from `develop`. In the main checkout, create or continue the authorized feature branch without disturbing unrelated changes. For isolation, create the feature branch and worktree beneath `.runs/worktrees/`. Never hide, overwrite, clean, reset, or relocate work whose ownership is uncertain.

Continue only when branch ancestry and status are understood and the TASK owns the intended writes.

## 3. Qualify revision input

Check the canonical review path and its sequence state automatically after establishing the target branch or worktree.

- If neither canonical nor numbered reports exist and this is first implementation, record the absence and continue normally. If revision was requested, stop with the exact missing path. Any history without a canonical report is an incomplete handoff, not first implementation.
- Require one readable, non-symlink regular `review.md` within the ignored canonical review root. Enumerate only its sibling `review-<NNNNN>.md` history, require positive five-digit contiguous sequences, and reject malformed review names, gaps, redirects, duplicate candidates, or other fallbacks. The canonical bytes must equal the highest numbered file; never infer “latest” by timestamps, findings, chat, or branch state.
- Require `review_handoff_version: 2`, every exact identity key defined by the review standards, a positive `review_sequence` matching the highest `history_report`, and all complete ordered sections. Version 1 or unversioned reports are retained history but are not safe revision input; request a fresh review rather than guessing missing identity.
- Validate TASK, repository common directory, base worktree, both report paths, target kind/identifier, branch or detached state, full base/head OIDs, exact status, manifest, reviewed artifact count, content digest, and verdict against the requested TASK and current target snapshot. Rebuild the canonical JSON Lines manifest, including every selected reviewed ignored root and descendant, and recompute its digest. A clean Git status does not permit a null digest when reviewed ignored artifacts exist. A relocated checkout may have a different local path only when immutable repository, target, base/head, status, manifest, and content identity still match unambiguously.
- Reject an unreadable, malformed, partial, stale, superseded, mismatched, ambiguous, or interrupted report with the exact path and reason. A changed ignored artifact, addition, removal, type, mode, symlink target, or content digest is stale. Do not alter review findings or silently treat the invocation as first implementation.
- Treat a matching `revise` report and all of its blocking findings as the authoritative correction handoff. Build the revision SUBTASK outline from those findings and the approved TASK; do not broaden scope. A matching `accept` report supplies no revision authority—stop and hand it to the separate landing workflow unless separately approved work changed the target and requires a new review.

## 4. Implement the TASK

- Keep a dependency-ordered SUBTASK outline in TASK-owned scratch when the work needs coordination. For revision, trace every item to a persisted blocking finding.
- Inspect and edit only what the accepted scope requires. Stop and ask before broadening product or planning scope.
- Make behavior testable, then add the smallest tests that prove acceptance. For a bug, first reproduce it and make a regression test fail when technically possible.
- Run focused checks as the change develops. Treat surprising generated files, secrets, unrelated failures, and destructive operations as stop conditions.

Implementation is complete only when every acceptance criterion is either demonstrated or explicitly unresolved and, for revision, every persisted blocking finding has an explicit disposition.

## 5. Verify and record

1. Refresh generated planning views when authorized planning records changed.
2. Run focused checks, `git diff --check`, and the canonical `./bin/build`.
3. Inspect the final diff and status for scope, secrets, generated debris, and unrelated changes.
4. Record fresh commands, exit results, test counts, changed files, warnings, deprecations, skips, incomplete checks, and remaining uncertainty in the TASK completion notes. Keep detailed logs in TASK-owned scratch.

A failed or skipped required check remains incomplete; an inherited receipt is context, not fresh evidence.

## 6. Commit and hand off

Stage only TASK-owned paths, inspect the staged diff, and create the authorized implementation commit. Leave unrelated work untouched.

Hand off the TASK ID, branch and commit, approved scope, changed files, verification results and counts, warnings, unresolved risks, planning status, and preserved unrelated work. Request independent review; for a bootstrap exception, request the named human review required by the TASK.

Stop before review acceptance, push, PR publication, landing, merge, archive, release, or deployment unless a separate workflow and explicit authority grant that action.
