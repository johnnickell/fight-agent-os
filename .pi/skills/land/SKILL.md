---
name: land
description: Publish an independently accepted TASK as a PR, reconciling a moving develop base when behavior is unchanged, then clean only proved TASK resources.
---

# Land

Read [landing standards](../../../docs/engineering/LANDING.md). The standards own reconciliation proof, publication and cleanup boundaries; this skill executes them for one TASK.

1. **Qualify.** Read the TASK, parent, Board and canonical `.runs/reviews/<TASK-ID>/review.md`. Check independent `accept`, criterion coverage, findings and limitations, reviewed base/head, branch and status. Inspect current `develop`, remote feature branch, PR, checkout and unrelated changes. Confirm credentials and cleanup ownership before mutation.
2. **Reconcile if needed.** An advancing base does not invalidate acceptance by itself. On an unpublished clean branch, rebase onto `develop` when requested; on a published branch use a non-rewriting integration or stop if the user requires a rebase. Regenerate generated planning views from source records, resolve overlap explicitly, compare old reviewed change with new effective diff (`git range-diff` plus inspection), and check interacting base changes. Record old/new OIDs, resolutions and fresh verification as a provenance bridge. Continue under the existing independent review only if the integration is mechanical and behavior is unchanged; otherwise stop for `work` and fresh independent `review`. Never rewrite the review report to claim the new OIDs were reviewed.
3. **Publish.** Record accepted review and finding dispositions in the TASK; run focused checks, planning validation, diff check and `./bin/build` on the reconciled implementation. Stage only owned files and inspect any commit. Push without force, create/update the PR against `develop` with a verified `TASK-NNNNN — <TASK title>` title and a truthful body based on the repository PR template; do not assume the body template controls the title. Record its URL and refresh planning views. Verify the final tree with focused checks, `./bin/planning-check`, `git diff --check` and `./bin/build` before the PR-metadata commit and final non-force push. Confirm the open PR's base, head and remote OID.
4. **Clean and hand off.** Recheck exact TASK resource ownership. Remove only proven disposable TASK resources and, if isolated, its clean registered worktree as the final tool operation from the base checkout. Keep the feature branch and review evidence. Return the clickable PR URL, review/provenance bridge, checks, warnings, cleanup results and remaining human actions.

A conflict that changes implementation, failing check, inaccessible remote, or ambiguous cleanup blocks successful landing. Landing never approves, merges, force-pushes, deletes the remote branch, archives or deploys.
