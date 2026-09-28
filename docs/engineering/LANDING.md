# Landing Standards

Land publishes an independently accepted TASK as a PR against `develop` and returns merge control to a human.
The bounded draft-publication path below can obtain hosted evidence before final acceptance. Apply the shared [execution](EXECUTION.md) and [review](REVIEW.md) boundaries. Landing may reconcile a reviewed branch with an advancing base; a changed commit OID by itself is not a changed implementation.

## Intake and authority

Read the TASK, parent, decisions, Board, and canonical review report. Confirm independence, verdict, requirement coverage, findings and limitations, reviewed branch/base/head and status. Inspect local and remote branches, current base, PR, worktrees and unrelated changes. Choose the user's authorized checkout; preserve unrelated work. Confirm hosting credentials and push path before modifying the target. An explicit `land TASK-NNNNN` authorizes branch reconciliation, TASK/PR metadata, non-force push, PR publication and ownership-proven cleanup. It does not authorize implementation fixes, force push, approval, merge, remote-branch deletion, archive, release or deployment.

Apply the [local-first verification policy](REVIEW.md#local-gate-and-optional-hosted-checks): require the local
gate, and require hosted acceptance evidence only when the target explicitly calls for it. When hosted CI is
optional, its absence or unavailability does not block technical acceptance or ordinary publication; disclose
its state and preserve actual merge restrictions separately.

Ordinary landing requires `accept`. A `revise` report with only unresolved hosted checks can enter initial
draft publication, or resume final delivery verification for an already-pushed administrative closeout B below.
For B, verify that the canonical report preserves accepted A, the permitted closeout policy and the A → B bridge;
resume at [final delivery verification](#verify-and-publish), without reopening A, repeating initial draft intake
or making another metadata commit. An explicit `land TASK-NNNNN` or request to publish for CI authorizes these
bounded evidence paths; a review report or `work` invocation alone does not.

## Acceptance candidate and administrative closeout

Before using required hosted evidence, identify its scope in the accepted TASK/repository policy. Distinguish:

- **Acceptance candidate A:** the implementation snapshot, with required local and hosted acceptance proof,
  independently accepted before tracked completion is recorded.
- **Administrative closeout B:** a later commit limited to truthful completion/review/PR metadata and regenerated
  planning views. `done` records acceptance of A, not a claim that B's hosted checks or publication succeeded.
  Requirements, instructions, code, dependencies and behavioral changes are not administrative closeout.
- **Final delivery checks:** any required checks on the literal final published head B remain blocking for
  successful landing and cleanup. They are not prerequisites for recording A's already-established acceptance.

This split must be permitted by the target's accepted policy; it is not a CI waiver or an automatic reinterpretation
of existing criteria. If a criterion makes literal-final-head hosted success a prerequisite for tracked `done`,
the completion commit would move that head. Stop before completion mutation and ask the authorized requirement
owner to amend the owning policy/TASK: accept a named implementation candidate, permit administrative closeout,
and retain literal-final-head proof as a delivery gate. Ambiguous scope needs the same clarification. Publication
permission alone does not authorize this amendment. If the owner declines, retain incomplete status and the
worktree, and report the incompatible requirements; do not loop through status commits or change consumer policy.
Independent review must use the recorded decision, not a builder's interpretation.

With that contract established, the finite sequence is A → accepted A → closeout B → verified B → cleanup.
Acceptance evidence identifies A; delivery evidence identifies B. No tracked commit is needed after B succeeds.
An additional reconciliation or non-administrative change creates a new acceptance candidate, not an exception.

## Draft publication to obtain hosted evidence

Use this path only when an independent canonical review establishes that all other applicable requirements pass,
local verification is sufficient for the current content, and the only unresolved items require hosted evidence.
Older reports need no schema conversion if they establish these facts. Missing unrelated proof, implementation
findings, an ambiguous report or a changed target without a valid provenance bridge cannot use this exception.

1. Verify the clean reviewed branch/base/head, existing PR, remote identity, actual CI triggers and applicable
   publication authority. Inspect intervening changes and any proven mechanical bridge; do not silently rebase
   or repair the candidate just to obtain a run. Preserve the candidate when fresh substantive review is needed.
2. Push the reviewed feature branch without force and create a draft PR against the intended base, or reuse the
   existing TASK PR. Mark final acceptance and hosted verification pending in the PR body. Preserve its identity;
   if an existing PR is ready, return it to draft within this publication authority. Use the ordinary TASK title
   and PR template without claiming completion. A draft can disclose pending post-review visual QA; the normal
   QA requirement still applies before accepted landing.
3. Capture the run URL, event, source head/base, actual tested commit and required job results. Determine whether
   CI tests the source head or a merge candidate, and establish its relationship to the reviewed change. Follow
   the target's accepted evidence requirement; do not quietly weaken a literal exact-head condition. Drafts or
   workflow filters may suppress runs: inspect the cause and report the next authorized action, rather than
   marking ready, changing CI policy, or manufacturing commits to trigger a run.
4. Keep the TASK incomplete, preserve `revise`/`Unverified`, and retain the worktree and review evidence. Store the
   publication/run receipt in ignored TASK scratch and link it in the handoff. Do not make a tracked PR-metadata
   commit during this phase merely to record the URL and thereby move the candidate awaiting CI.
5. Return the PR link and evidence for focused independent review of acceptance candidate A. Pending or
   infrastructure-failed checks remain evidence work; confirmed implementation failures route to `work`.
   Tracked completion, visual QA where applicable and closeout B follow the accepted landing path below.
   Required final-head delivery checks must cover B, including metadata commits; A's run cannot substitute.
   Do not waive required CI because a change was mechanical.

This is an incomplete landing handoff, not successful acceptance, approval, merge or cleanup. Do not poll
indefinitely; report queued/unavailable evidence and resume when new results or an explicit monitoring request arrive.

## Reconcile an advancing base

The accepted report anchors **review provenance**, not a requirement that `develop` remain frozen. If the feature branch is still based on the reviewed base, reconcile it with current `develop` when needed:

1. Record reviewed base/head and current base/head. Require a clean target and a usable canonical `accept` report for the original snapshot. Check remote publication first: a local unpublished branch may be rebased; a published branch must be updated without rewriting published history (for example, merge `develop`) or left for the hosting platform. Explain the choice if the user asked specifically for a rebase that would need a force push.
2. Reconcile conflicts in generated planning views by regenerating them from authoritative records. For overlapping configuration or other files, inspect both sides and the final effective result; do not silently choose one side. Preserve both TASKs' behavior. Use `git range-diff` where applicable, compare the old reviewed change with the new-base effective diff, and inspect changes on the new base that interact with the TASK. An identical patch alone is not proof against changed dependencies.
3. Record a concise provenance bridge: old and new base/head OIDs, conflict resolutions, effective implementation differences, dependency interactions, and fresh focused and full-gate results. If only mechanical integration (including generated views and independent registrations) changed and the reviewed behavior remains intact, the independent `accept` remains applicable. A semantic implementation change, unproven interaction, unexpected scope change, or failing check requires a new independent review of the new target; preserve the branch and stop publication. Never rewrite the original review to pretend it reviewed the new OIDs.

Example: two independent TASKs update the Board, an index or completion notes. Preserve both source records,
regenerate derived views, inspect the effective diff and run required checks. Existing acceptance remains usable;
no second model review, extra human approval step or revision-cycle charge is required solely for that conflict.
A Markdown requirement, authority rule or behavioral contract change is substantive and does not get this exception.

Landing can resolve mechanical conflicts but not repair an implementation defect. If reconciliation cannot complete safely, stop with the exact conflict and hand off to `work` and then `review` as needed. Rebase/merge must never force-update a published branch.

## Verify and publish

Use the TASK completion notes to record the accepted candidate/report and any finding dispositions, reconciliation
proof, fresh checks with counts/warnings, risks, and publication state. `done` means accepted implementation and
all required acceptance verification, including hosted proof when required, not successful delivery, PR approval
or merge. For required hosted checks, first establish the [closeout contract](#acceptance-candidate-and-administrative-closeout).
Record `done` in B only after A is independently accepted and required QA is complete; say explicitly that final
publication/checks are pending. Keep generated Board/indexes synchronized with authored planning changes.

Run focused checks, `./bin/planning-check`, `git diff --check`, and `./bin/build` on the reconciled implementation before initial publication. Stage only owned planning or reconciliation files; inspect the staged diff and commit them if needed. Push the feature branch without force; create or update its PR against `develop`. For a TASK PR, set and verify the title `TASK-NNNNN — <TASK title>` (the body template cannot set a title), and use the editable [PR body template](../../.github/pull_request_template.md) for scope, behavior, verification, warnings, evidence, and outstanding review/merge status. Remove placeholder text and avoid implying a check passed if it was not run. Preserve an existing PR's identity when updating it; do not rename an unrelated or historical branch merely to match the new convention. Record the actual PR URL in the TASK and refresh generated views. Run focused checks, planning validation, diff check and the full gate on that final tree before committing/pushing the PR metadata. If a result changes, correct the evidence and rerun the affected checks. Avoid repeating gates for an unchanged tree merely to accumulate receipts.

For required final-head delivery checks, retain the draft PR and worktree after pushing B. Obtain proof for B
and a focused independent continuation that verifies the A → B administrative-only diff and required run provenance.
Do not claim successful landing or perform cleanup while that proof is missing. `done` still describes accepted A;
it does not assert success of B's pending delivery checks. On success, record B's certification in the canonical
review, ignored receipt and PR body, then mark the PR ready and proceed to cleanup without another tracked commit.
The tracked note remains a truthful checkpoint (accepted A, delivery pending at closeout), not a live run ledger.

A queued, unavailable or infrastructure-failed final run leaves delivery incomplete: retain B, draft status and
resources, disclose the cause, and retry the same head when authorized. Do not toggle `done` just to record run
state. A run demonstrating an implementation defect invalidates acceptance: stop landing, route to `work` to
reopen the TASK and repair it, then require new verification and independent review. A non-administrative B or
unproven interaction likewise stops for fresh review; it cannot inherit A's completion claim. Any later commit
changes the delivery candidate and requires its own applicable final-head proof. Never use A's green run for B.

After final push, query the hosting service: PR open, base `develop`, intended head branch, remote head equal to local `HEAD`. Authentication failure, failed required checks, publication failure, or remote-head mismatch blocks a successful landing; preserve the worktree. No approval or merge occurs here.

## Cleanup and handoff

Preview cleanup candidates before publication and recheck immediately before deletion. Remove only ownership-proven TASK-specific disposable resources: exact Compose project containers/volumes, inspected build output and a clean registered isolated worktree at the published commit. Preserve shared, durable, ambiguous and dirty resources, including review reports and unrelated worktrees. For isolated worktrees, remove without force from the base checkout as the final tool operation after all other work; retain the local feature branch for the human. If safe cleanup is unavailable, report landing incomplete rather than broadening deletion authority.

Return the PR link, review provenance and any reconciliation bridge, final checks and warnings, cleanup result and remaining human actions. Publication, approval, merge, branch deletion, archive, release and deployment are distinct states.

## QA report intake and publication

Read the canonical `<base-worktree>/.runs/qa/<TASK-ID>/qa.md` for required or requested QA. Check current subject,
independence, scenario coverage, artifact digests and any reconciliation bridge under [QA standards](QA.md).
A current FAIL blocks accepted landing and routes to `work`; INCOMPLETE or missing required evidence routes to
`qa` or its stated prerequisite. Do not select a historical PASS or treat requested QA as optional after it fails.
Non-interactive work may have a justified N/A; do not force a browser run for documentation.

Publish sanitized captures through an available mechanism within the existing publication authority and verify
access for the intended PR audience. A local behavioral PASS may have publication pending. No working upload
path means an explicit human handoff and incomplete delivery, not invented URLs or product-source screenshot
commits. Keep reports/captures outside disposable worktrees; retain resources while required QA or artifact
publication is pending. Already-open PRs can receive QA before merge; this does not itself authorize PR updates.

## Visual evidence

For UI/TUI changes consume current post-review QA evidence under [QA standards](QA.md) and include the PR
Before/After table with accessible real captures. Non-visual work records N/A or omits that section. Do not run
unnecessary screenshot steps on documentation-only changes. Missing required evidence remains visible.
