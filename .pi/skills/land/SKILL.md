---
name: land
description: Publish an accepted TASK and clean owned resources, or publish a draft to obtain its only missing hosted verification within user authority.
---

# Land

Read [landing standards](../../../docs/engineering/LANDING.md). The standards own reconciliation proof, publication and cleanup boundaries; this skill executes them for one TASK.

1. **Qualify.** Read the TASK, parent, Board and canonical `.runs/reviews/<TASK-ID>/review.md`. Check independence, verdict, criterion coverage, findings and limitations, reviewed base/head, branch and status. Inspect current `develop`, remote feature branch, PR, checkout and unrelated changes. Confirm credentials and cleanup ownership before mutation.
Only an explicit hosted acceptance requirement activates initial [draft publication](../../../docs/engineering/LANDING.md#draft-publication-to-obtain-hosted-evidence); a workflow file or missing optional run does not. If that proof is the only unresolved requirement, use the draft path and stop with an evidence handoff, incomplete TASK, preserved review and retained worktree. For an already-pushed administrative closeout with only final-head delivery proof pending, instead resume [final delivery verification](../../../docs/engineering/LANDING.md#verify-and-publish) under the canonical report's accepted-candidate provenance; do not repeat initial draft intake or toggle completion status. Ordinary steps below require independent `accept`.

2. **Reconcile if needed.** An advancing base does not invalidate acceptance by itself. On an unpublished clean branch, rebase onto `develop` when requested; on a published branch use a non-rewriting integration or stop if the user requires a rebase. Regenerate generated planning views from source records, resolve overlap explicitly, compare old reviewed change with new effective diff (`git range-diff` plus inspection), and check interacting base changes. Record old/new OIDs, resolutions and fresh verification as a provenance bridge. Continue under the existing independent review only if the integration is mechanical and behavior is unchanged; otherwise stop for `work` and fresh independent `review`. Never rewrite the review report to claim the new OIDs were reviewed.
3. **Publish.** Read `<base-worktree>/.runs/qa/<TASK-ID>/qa.md` when present or required/requested, and verify its subject, independence,
scenario coverage, artifact digests and freshness under [QA standards](../../../docs/engineering/QA.md).
FAIL routes to `work`; INCOMPLETE or missing required/requested QA routes to the [qa skill](../qa/SKILL.md)
or its named prerequisite, retaining resources. Never prefer an older PASS over the current report.
For UI/TUI changes require post-review QA evidence
and include the template's Before/After table; non-visual work can record N/A. For required hosted checks, establish the [acceptance-candidate and closeout contract](../../../docs/engineering/LANDING.md#acceptance-candidate-and-administrative-closeout) before recording completion; unresolved literal-final-head acceptance scope needs the requirement owner's decision. Record accepted review and finding dispositions in the TASK; run focused checks, planning validation, diff check and `./bin/build` on the reconciled implementation. Stage only owned files and inspect any commit. Push without force, create/update the PR against `develop` with a verified `TASK-NNNNN — <TASK title>` title and a truthful body based on the repository PR template; do not assume the body template controls the title. Record its URL and refresh planning views. Verify the final tree with focused checks, `./bin/planning-check`, `git diff --check` and `./bin/build` before the PR-metadata commit and final non-force push. Confirm the open PR's base, head and remote OID. Keep the PR draft and resources retained until any required final-head delivery checks and focused independent continuation pass; record that result outside tracked files so the certified head stays fixed.
Verify required captures are sanitized and accessible to the PR audience using an available authorized publication
mechanism. Local paths are not PR evidence. If upload/access is unavailable, report publication pending with an
exact handoff and retain resources; do not claim complete landing. Keep canonical reports/captures outside disposable
worktrees. QA of an already-open PR uses the same current-subject checks and does not imply merge approval.

4. **Clean and hand off.** Recheck exact TASK resource ownership. Remove only proven disposable TASK resources and, if isolated, its clean registered worktree as the final tool operation from the base checkout. Keep the feature branch and review evidence. Return the clickable PR URL, review/provenance bridge, checks, warnings, cleanup results and remaining human actions.

A conflict that changes implementation, failed required check, inaccessible remote, or ambiguous cleanup blocks successful landing. Landing never approves, merges, force-pushes, deletes the remote branch, archives or deploys.
