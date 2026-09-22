# Landing Standards

Landing closes independently reviewed implementation and returns merge control to a human. It verifies review and evidence prerequisites, finalizes the authoritative TASK record, publishes only authorized work, and cleans only resources whose ownership is proved. It does not repeat review or repair defects. Apply the shared [execution](EXECUTION.md) and [review](REVIEW.md) boundaries throughout.

## Landing target and authority

Identify the TASK, accepted scope, repository, feature branch, `develop` ancestry, reviewed base and head commits, current status, remote, and existing PR before changing anything. The implementation must be committed, and the reviewed target must be exact. Record every post-review change; only mechanical landing records and generated planning views may follow the accepted head without another independent review.

Derive separate authority for each side effect: planning finalization, landing commit, push, PR creation or update, and cleanup. An approved implementation TASK or a request to inspect landing readiness does not silently authorize publication or deletion. Stop for an inconsistent branch or diff, unexpected changes, uncertain ownership, missing credentials, or an unavailable required remote.

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
6. Stage only owned paths, inspect the staged diff, and create a landing commit only when authorized.

A failed or skipped required check prevents publication. If a rerun changes a result recorded in the TASK, update the record and repeat the final checks before commit. Restore the TASK to a truthful non-`done` state using only landing-owned edits when acceptance is no longer supported, refresh generated views, and record the incomplete result; if safe restoration is uncertain, stop and ask. Never hide a failure or reuse an earlier build as final-tree evidence.

`done` means accepted implementation and required local verification. It does not mean committed, pushed, published as a PR, approved by a hosting platform, merged, archived, released, deployed, or certified.

## Publication and human handoff

Before any network effect, restate the granted push and PR authority and inspect the remote branch and existing PR. Push only the authorized feature branch without force. Create or update only the intended PR, preserving the accepted base and target. Never approve or merge it.

If publication is not authorized or cannot complete, preserve the local commit and report the exact missing action. The handoff identifies TASK, branch, base/head and landing commits, PR state or simulated effect, checks and warnings, review disposition, cleanup result, unrelated work, unresolved risks, and the human actions still required.

## Resource ownership and cleanup

Treat resources as shared or ambiguous until ownership is demonstrated. A TASK ID in a path or name is useful but not sufficient when the resource can contain durable evidence, another checkout, shared state, or uncommitted work.

Accept ownership only from inspectable evidence appropriate to the resource:

- scratch/prototype paths: expected TASK-scoped root, ownership marker or creation record, and contents confirmed disposable;
- worktrees: registered path and branch, clean status, preserved commits, and confirmation it is not the active checkout;
- processes: captured PID plus matching command and start identity, not a PID alone;
- databases: exact TASK-specific name, confirmed test endpoint, and no shared or production authority;
- containers and volumes: exact TASK-specific Compose project, labels, or IDs and no shared dependency.

Preview the cleanup set and operations before applying them. Use exact identifiers, never broad globs, global pruning, shared Compose teardown, recursive deletion outside the owned root, forced worktree removal, or inferred PID ownership. Preserve source files, required evidence, shared services, durable records, and anything ambiguous. Refusal to clean an uncertain resource is a successful safety outcome, not incomplete housekeeping.

Cleanup authority is narrower than publication authority and must be explicit. Record each removed, stopped, retained, or refused resource and the evidence supporting that decision.
