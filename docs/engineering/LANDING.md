# Landing Standards

Land publishes an independently accepted TASK as a PR against `develop` and returns merge control to a human. Apply the shared [execution](EXECUTION.md) and [review](REVIEW.md) boundaries. Landing may reconcile a reviewed branch with an advancing base; a changed commit OID by itself is not a changed implementation.

## Intake and authority

Read the TASK, parent, decisions, Board, and canonical review report. Confirm independence, `accept`, requirement coverage, findings and limitations, reviewed branch/base/head and status. Inspect local and remote branches, current base, PR, worktrees and unrelated changes. Choose the user's authorized checkout; preserve unrelated work. Confirm hosting credentials and push path before modifying the target. An explicit `land TASK-NNNNN` authorizes branch reconciliation, TASK/PR metadata, non-force push, PR publication and ownership-proven cleanup. It does not authorize implementation fixes, force push, approval, merge, remote-branch deletion, archive, release or deployment.

## Reconcile an advancing base

The accepted report anchors **review provenance**, not a requirement that `develop` remain frozen. If the feature branch is still based on the reviewed base, reconcile it with current `develop` when needed:

1. Record reviewed base/head and current base/head. Require a clean target and a usable canonical `accept` report for the original snapshot. Check remote publication first: a local unpublished branch may be rebased; a published branch must be updated without rewriting published history (for example, merge `develop`) or left for the hosting platform. Explain the choice if the user asked specifically for a rebase that would need a force push.
2. Reconcile conflicts in generated planning views by regenerating them from authoritative records. For overlapping configuration or other files, inspect both sides and the final effective result; do not silently choose one side. Preserve both TASKs' behavior. Use `git range-diff` where applicable, compare the old reviewed change with the new-base effective diff, and inspect changes on the new base that interact with the TASK. An identical patch alone is not proof against changed dependencies.
3. Record a concise provenance bridge: old and new base/head OIDs, conflict resolutions, effective implementation differences, dependency interactions, and fresh focused and full-gate results. If only mechanical integration (including generated views and independent registrations) changed and the reviewed behavior remains intact, the independent `accept` remains applicable. A semantic implementation change, unproven interaction, unexpected scope change, or failing check requires a new independent review of the new target; preserve the branch and stop publication. Never rewrite the original review to pretend it reviewed the new OIDs.

Landing can resolve mechanical conflicts but not repair an implementation defect. If reconciliation cannot complete safely, stop with the exact conflict and hand off to `work` and then `review` as needed. Rebase/merge must never force-update a published branch.

## Verify and publish

Use the TASK completion notes to record the accepted report and any finding dispositions, reconciliation proof, fresh checks with counts/warnings, risks, and publication state. `done` means accepted implementation and required local verification, not PR approval or merge. Keep generated Board/indexes synchronized with authored planning changes.

Run focused checks, `./bin/planning-check`, `git diff --check`, and `./bin/build` on the reconciled implementation before initial publication. Stage only owned planning or reconciliation files; inspect the staged diff and commit them if needed. Push the feature branch without force; create or update its PR against `develop`. For a TASK PR, set and verify the title `TASK-NNNNN — <TASK title>` (the body template cannot set a title), and use the editable [PR body template](../../.github/pull_request_template.md) for scope, behavior, verification, warnings, evidence, and outstanding review/merge status. Remove placeholder text and avoid implying a check passed if it was not run. Preserve an existing PR's identity when updating it; do not rename an unrelated or historical branch merely to match the new convention. Record the actual PR URL in the TASK and refresh generated views. Run focused checks, planning validation, diff check and the full gate on that final tree before committing/pushing the PR metadata. If a result changes, correct the evidence and rerun the affected checks. Avoid repeating gates for an unchanged tree merely to accumulate receipts.

After final push, query the hosting service: PR open, base `develop`, intended head branch, remote head equal to local `HEAD`. Authentication, checks, publication, or remote-head mismatch blocks a successful landing; preserve the worktree. No approval or merge occurs here.

## Cleanup and handoff

Preview cleanup candidates before publication and recheck immediately before deletion. Remove only ownership-proven TASK-specific disposable resources: exact Compose project containers/volumes, inspected build output and a clean registered isolated worktree at the published commit. Preserve shared, durable, ambiguous and dirty resources, including review reports and unrelated worktrees. For isolated worktrees, remove without force from the base checkout as the final tool operation after all other work; retain the local feature branch for the human. If safe cleanup is unavailable, report landing incomplete rather than broadening deletion authority.

Return the PR link, review provenance and any reconciliation bridge, final checks and warnings, cleanup result and remaining human actions. Publication, approval, merge, branch deletion, archive, release and deployment are distinct states.
