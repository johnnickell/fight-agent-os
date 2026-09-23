# Landing Standards

Landing closes independently reviewed implementation and returns a published pull request and merge control to a human. It verifies review and evidence prerequisites, finalizes the authoritative TASK record, publishes the complete branch, and cleans only resources whose ownership is proved. It does not repeat review or repair defects. Apply the shared [execution](EXECUTION.md) and [review](REVIEW.md) boundaries throughout.

## Landing target and authority

Identify the TASK, accepted scope, repository, feature branch, `develop` ancestry, reviewed base and head commits, current status, authenticated remote/hosting access, remote branch, existing PR, and exact cleanup candidates before changing anything. The implementation must be committed, and the reviewed target must be exact. Record every post-review change; only mechanical landing records, publication metadata, generated planning views, and their verification evidence may follow the accepted head without another independent review.

Explicit invocation of `land` for a named TASK grants bounded authority to finalize that TASK's planning, create landing commits, push its named feature branch without force, create or update its PR against the accepted base, and remove its clean non-active isolated worktree plus exact ownership-proven TASK resources. It does not authorize approval, merge, local or remote branch deletion, archive, release, deployment, certification, broad Docker pruning, shared teardown, or ambiguous deletion.

Confirm before mutation that credentials, the required remote and hosting service, and safe cleanup prerequisites are available. Classify the target as the canonical base worktree or a registered isolated worktree. Finalization and publication may run from the target checkout; preserve the base worktree and defer any isolated-target removal until every other tool operation is complete. Stop for an inconsistent branch or diff, unexpected changes, uncertain ownership, dirty cleanup candidates, missing credentials, or unavailable publication operations. Successful landing may not silently degrade to a local-only handoff.

## Review qualification

Require an independent report that identifies its reviewer relationship, TASK, exact target and baseline, target status, verification, findings, evidence limits, and an `accept` verdict. Confirm that:

- the reviewer did not materially author, direct, repair, or provide acceptance evidence for the target;
- the report covers the current implementation commit and required acceptance criteria;
- every blocking finding is absent or corrected and covered by a later independent `accept` verdict;
- every non-blocking finding has an explicit disposition, such as accepted now or deferred with rationale and owner;
- post-review changes have not made the verdict stale.

This is provenance and disposition checking, not another implementation review. Missing, ambiguous, stale, or contradictory evidence requires refusal or a new review, not an inferred acceptance.

## Finalization and verification

Reconcile each acceptance criterion with accepted review evidence and fresh implementation receipts. Completion notes name the implemented scope, files, commits, review target and verdict, finding dispositions, fresh commands and counts, warnings, skips, residual risk, publication state, cleanup decisions, and retained human actions.

When acceptance is supported:

1. Run focused checks, `./bin/planning-check`, `git diff --check`, and `./bin/build` to obtain fresh results and counts on the accepted implementation before asserting completion.
2. Record those actual commands, results, and counts in the TASK completion notes, then set its status to `done`; do not alter requirement scope.
3. Run `./bin/planning-check --write` and inspect the generated changes.
4. Rerun the focused checks, `./bin/planning-check`, `git diff --check`, and final `./bin/build` after the authoritative TASK and generated views have changed.
5. Inspect the complete diff and status for scope, secrets, debris, and preserved unrelated work.
6. Stage only owned paths, inspect the staged diff, and create the landing commit.

A failed or skipped required check prevents publication. If a rerun changes a result recorded in the TASK, update the record and repeat the final checks before commit. Restore the TASK to a truthful non-`done` state using only landing-owned edits when acceptance is no longer supported, refresh generated views, and record the incomplete result; if safe restoration is uncertain, stop and ask. Never hide a failure or reuse an earlier build as final-tree evidence.

`done` means accepted implementation and required local verification. It does not mean committed, pushed, published as a PR, approved by a hosting platform, merged, archived, released, deployed, or certified.

## Publication and human handoff

Push only the named feature branch without force, then create or update only its intended PR against the accepted base. Capture the canonical PR URL, write it to the TASK metadata and completion evidence, refresh and inspect generated planning views, rerun focused checks plus `./bin/planning-check`, `git diff --check`, and `./bin/build`, commit those mechanical publication records, and push the final commit without force. Do not invent a changelog when the repository has none; update the authoritative TASK, generated Board/indexes, and any existing project completion surface.

After the final push, query the hosting service and verify that the PR is open, names the accepted base and feature head, and reports the same head commit as local `HEAD`. Authentication, initial push, PR creation/update, record update, verification, final checks, commit, final push, or remote-head mismatch blocks cleanup and successful landing. Preserve the worktree and report the exact failure instead of presenting local-only completion.

The handoff identifies TASK, branch, base/review/landing/publication commits, checks and warnings, review disposition, cleanup result, unrelated work, unresolved risks, and human actions still required. It always presents the canonical PR URL as a clickable link. Never approve or merge the PR.

## Resource ownership and cleanup

Treat resources as shared or ambiguous until ownership is demonstrated. A TASK ID in a path or name is useful but not sufficient when the resource can contain durable evidence, another checkout, shared state, or uncommitted work. Landing invocation authorizes cleanup only after the final PR head is verified and only within the ownership bounds below.

Accept ownership only from inspectable evidence appropriate to the resource:

- scratch/prototype paths: expected TASK-scoped root, ownership marker or creation record, and contents confirmed disposable;
- worktrees: registered path and branch, clean status at the verified published commit, and confirmation it is not the active checkout;
- ignored worktree output: an exact preview proving each path is ignored, inside the target worktree, disposable, and produced by TASK checks;
- processes: captured PID plus matching command and start identity, not a PID alone;
- databases: exact TASK-specific name, confirmed test endpoint, and no shared or production authority;
- containers and volumes: exact TASK-specific Compose project, labels, or IDs and no shared dependency.

Preview the cleanup set and operations at intake and recheck it immediately before deletion. Remove exact TASK containers and volumes before their worktree. When containerized checks leave root-owned ignored output, an ephemeral helper may mount only the exact target worktree and remove only previewed approved paths; record its image, mount, paths, and result. Preserve the canonical base worktree. For a registered clean isolated target, make removal the final tool operation: change to the base worktree, remove the target without force, and verify both registration and path removal in that same operation. Retain the local feature branch so the human can delete it after merge.

Never use broad globs, global pruning, shared Compose teardown, recursive deletion outside the owned root, forced worktree removal, or inferred PID ownership. Preserve dirty, active, or unregistered worktrees, source files, required evidence, shared services, durable records, and anything ambiguous. A cleanup refusal now makes landing incomplete: preserve the resource and report the exact condition rather than weakening the ownership rule or claiming successful landing.
