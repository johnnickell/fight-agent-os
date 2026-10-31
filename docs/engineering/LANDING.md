# Landing Standards

Land publishes an independently accepted TASK against its verified target default branch and returns merge control to a human.
An explicit resume can also reconcile an ended original delivery handoff through the bounded recovery path below.
The bounded draft-publication path below can obtain hosted evidence before final acceptance. Apply the shared [execution](EXECUTION.md) and [review](REVIEW.md) boundaries. Landing may reconcile a reviewed branch with an advancing base; a changed commit OID by itself is not a changed implementation.

## Intake and authority

Read the TASK, parent, decisions, Board, and canonical review report. Confirm independence, verdict, requirement coverage, findings and limitations, reviewed branch/base/head and status. Inspect local and remote branches, current base, PR, worktrees and unrelated changes. Choose the user's authorized checkout; preserve unrelated work. Confirm hosting credentials and push path before modifying the target. An explicit `land TASK-NNNNN` authorizes branch reconciliation, TASK/PR metadata, non-force push, PR publication and ownership-proven cleanup. It does not authorize implementation fixes, force push, approval, merge, remote-branch deletion, archive, release or deployment.

Apply the [local-first verification policy](REVIEW.md#local-gate-and-optional-hosted-checks): require the local
gate, and require hosted acceptance evidence only when the target explicitly calls for it. When hosted CI is
optional, its absence or unavailability does not block technical acceptance or ordinary publication; disclose
its state and preserve actual merge restrictions separately.

Ordinary landing requires independent `accept`. Before that acceptance, a `revise` report with only unresolved
required hosted checks can enter initial draft publication. For already-pushed administrative closeout B,
resume [final delivery verification](#verify-and-publish) whether required proof is pending or available.
Verify accepted A in the canonical report and the permitted closeout policy; land establishes the A → B bridge
in its ignored receipt. An older delivery-only `revise` report can use this route only if it explicitly preserves
independent acceptance of A and has no substantive or unresolved acceptance findings. Preserve that report;
no delivery-only canonical update is needed. Missing or ambiguous acceptance still requires independent review.
Do not reopen A, repeat initial draft intake or make another metadata commit solely to resume delivery.
An explicit `land TASK-NNNNN` or request to publish for CI authorizes these bounded evidence paths; a review
report or `work` invocation alone does not.

## Bind the publication target

Before reconciliation or publication, resolve the target's exact origin and **live** GitHub default branch.
Use `python3` with the owning provider's `.pi/skills/land/scripts/bind-target.py`, supplying `--root` as the
selected target checkout and `--policy` as that target's actual accepted integration branch. When a subject PR
exists also supply its exact `--pr` URL and intended `--head` branch. The helper performs one repository read
and at most one PR read, each bounded to 15 seconds and 16 KiB; it writes nothing and never fetches. Record its
JSON observation in the ignored delivery receipt. Missing tooling/authentication, malformed identity, a policy/
default conflict or an existing PR targeting another base holds publication for an explicit owner disposition;
never retarget an existing PR silently. Stale local `origin/HEAD` is not authority.

Bind all following base/ref/fetch, ancestry, push/PR creation and final verification operations to that observed
branch. References to `develop` below are Agent OS's current policy/examples, not a provider-wide literal.
Recheck the binding immediately before publication and at final handoff; a changed live default holds the old
binding until its policy/PR conflict is resolved. The helper does not establish subject acceptance or remote
head equality; retain the exact PR/title/candidate/remote checks below. Ordinary planning/builds stay offline.

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
   and PR template without claiming completion. A draft can disclose pending post-review behavioral QA; the normal
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
   Tracked completion, applicable behavioral QA and closeout B follow the accepted landing path below.
   Required final-head delivery checks must cover B, including metadata commits; A's run cannot substitute.
   Do not waive required CI because a change was mechanical.

This is an incomplete landing handoff, not successful acceptance, approval, merge or cleanup. Do not poll
indefinitely; report queued/unavailable evidence and resume when new results or an explicit monitoring request arrive.

## Reconcile an advancing base

The accepted report anchors **review provenance**, not a requirement that `develop` remain frozen. If the feature branch is still based on the reviewed base, reconcile it with current `develop` when needed:

1. Record reviewed base/head and current base/head. Require a clean target and a usable canonical `accept` report for the original snapshot. Check remote publication first: a local unpublished branch may be rebased; a published branch must be updated without rewriting published history (for example, merge `develop`) or left for the hosting platform. Explain the choice if the user asked specifically for a rebase that would need a force push.
2. Reconcile conflicts in generated planning views by regenerating them from authoritative records. For overlapping configuration or other files, inspect both sides and the final effective result; do not silently choose one side. Preserve both TASKs' behavior. Use `git range-diff` where applicable, compare the old reviewed change with the new-base effective diff, and inspect changes on the new base that interact with the TASK. An identical patch alone is not proof against changed dependencies.
3. Record a concise provenance bridge: old and new base/head OIDs, conflict resolutions, effective implementation differences, dependency interactions, and fresh focused results and the full-gate receipt assessment under [gate evidence reuse](EXECUTION.md#gate-evidence-reuse). If only mechanical integration (including generated views and independent registrations) changed and the reviewed behavior remains intact, the independent `accept` remains applicable. A semantic implementation change, unproven interaction, unexpected scope change, or failing check requires a new independent review of the new target; preserve the branch and stop publication. Never rewrite the original review to pretend it reviewed the new OIDs.

Example: two independent TASKs update the Board, an index or completion notes. Preserve both source records,
regenerate derived views, inspect the effective diff and run required checks. Existing acceptance remains usable;
no second model review, extra human approval step or revision-cycle charge is required solely for that conflict.
A Markdown requirement, authority rule or behavioral contract change is substantive and does not get this exception.

Landing can resolve mechanical conflicts but not repair an implementation defect. If reconciliation cannot complete safely, stop with the exact conflict and hand off to `work` and then `review` as needed. Rebase/merge must never force-update a published branch.

## Synchronize current subject handoffs

During normal publication, Land owns the exact subject's applicable authored **current** Board, Roadmap and
README handoffs, not just parent statuses and generated views. Apply this procedure in the authorized target
checkout before closeout/publication commits, and recheck it before returning control to the human. Follow the
target's paths and bindings; shared provider links do not authorize edits in a different checkout or project.

Land closes the independently accepted TASK, eligible parents and applicable authored current documentation
in this delivery. There is no tracked delivery marker/table, persistent awaiting-merge section, routine
reconciliation mode or post-merge maintenance obligation. A TASK's `pr` records publication history, not a
current merge action. Freeze truthful acceptance/publication-pending checkpoints and point delivery results
to the durable ignored receipt and actual PR; do not maintain a competing tracked live ledger.

Require [exact live PR evidence](HANDOFFS.md#live-delivery-handoff-evaluation) during publication and explicit
resume. Preserve exact repository/default/base/head/OID, independent Review/QA and accepted-candidate bridge.
A missing or contradictory observation holds the affected effect, never a guessed merge. Human merge remains
separate; do not delete its remote head. Planning checks/build stay offline. Preserve all historical
acceptance/publication and canonical reports. These provider instructions grant no secondary edit authority.

1. **Inventory and classify.** Resolve the authoritative TASK/parent records, Board, Roadmap and README from
   the target's instructions, planning tools and existing bindings, not provider path defaults. Inventory the
   source TASK/review/PR facts and eligible ancestor rollups alongside those surfaces, including standalone/no
   parent and absent/unrelated surfaces. Follow subject-specific current-section/source links only as needed.
   For each applicable authored handoff record the path/section, exact TASK and PR identity (when known), claimed stage/action, authoritative
   source and disposition. Include current subject-related capability/acceptance claims, even without a `Next:`
   label. Distinguish generated blocks, authored current instructions and historical checkpoints. Record absent,
   unrelated, history-only and already-consistent surfaces as such; do not create a README, scan every historical
   PR or change unrelated routing merely to fill the inventory. Generated freshness alone cannot prove authored
   handoffs are current.
2. **Resolve evidence before mutation.** Compare current claims with source-record status, canonical independent
   Review/QA, accepted-candidate/reconciliation provenance and the relevant hosting observation. For a PR handoff,
   verify repository, PR URL, base/head branch, live state and remote head against the intended candidate; record
   the observation and time in the ignored receipt. Neither `done`, a PR URL nor a historical pending note proves
   publication, delivery success or merge. Correct an unambiguously stale factual stage/pointer only when its
   source evidence and edit authority are established. Unresolved identity, conflicting authoritative sources,
   inaccessible required evidence or an unsafe checkout holds the affected mutation/publication: name the exact
   sections/claims, missing proof, blocked operation and recovery owner/action. Do not pick an arbitrary winner,
   infer success or repair unrelated requirements. An ended PR needs [ended-handoff recovery](#resume-an-ended-delivery-handoff);
   stop normal publication rather than inventing a replacement or treating closed-unmerged as delivered.
3. **Reconcile current facts, preserve history.** Update authoritative TASK/PR metadata and applicable authored
   current handoffs to the evidenced stage, then roll up eligible parents and refresh derived views with the
   target's owning planning command. Preserve requirements, priorities, dependencies, Won't Fix decisions and
   unrelated work. Never edit canonical Review/QA reports, accepted-candidate provenance or historical completion/
   delivery-pending checkpoint text to manufacture later evidence. If a checkpoint can be mistaken for today's
   instruction, add a clearly separate current-section pointer or historical-context qualifier outside that
   checkpoint, leaving its original evidence intact. Factual stage/PR-locator maintenance may accompany permitted
   administrative closeout; changing executable workflow rules, requirements, implementation or acceptance is
   not metadata. Apply the [closeout contract](#acceptance-candidate-and-administrative-closeout): substantive
   edits require a new acceptance candidate and independent review, not a broadened Land repair exception.
4. **Freeze truthful wording, verify delivery.** Before the final metadata commit, make current handoffs explicit
   about what is established and what remains pending. For example: “A accepted; publication/final-head checks
   pending at this checkpoint; current delivery result and next action: <durable receipt/actual PR>.” Use real
   locators, not that placeholder. While proof is pending, the current action is evidence recovery through Land,
   not an unconditional human-merge instruction. Put later delivery results in the ignored receipt and PR body;
   do not change a historical pending checkpoint or create another tracked commit solely to certify B's success.
   If current tracked wording already points to that live result and remains truthful, leave it unchanged.
   A failed/pending required check retains the draft/resources and precise hold under Verify and publish.
5. **Check and report consistency.** Inspect the effective diff and every inventoried current handoff after
   refresh, checking authored sections separately from generated blocks. After the final push verify the actual
   open PR and head, and reconcile the receipt/PR body to that observation before declaring success. Return the
   inventory/dispositions, changed current sections, preserved history/report identities, verification and
   unresolved holds in the durable ignored receipt. Successful publication hands the actual open PR to human
   review/merge; it never anticipates merge, starts another TASK or overrides a hold. Already-consistent state
   needs no correction or empty commit. Missing required delivery or cleanup proof still blocks successful Land.

The [draft-evidence path](#draft-publication-to-obtain-hosted-evidence) retains its fixed candidate and incomplete
TASK. Inventory applicable handoffs there, but record pending state/contradictions in the ignored receipt and PR
body without tracked reconciliation commits that move the candidate awaiting proof. Never claim synchronized
tracked handoffs or successful landing while a required correction remains deferred. The separate read-only
live check introduces no hosted-CI requirement, automatic repair/dispatch or cross-project rollout. Ended-handoff
recovery below consumes this inventory and preservation contract rather than replacing it.

## Resume an ended delivery handoff

Use this path only on an **explicit Land resume for the exact TASK and ended original PR**, when an exceptional
factual correction is actually necessary. No routine post-merge maintenance or new correction is required. A routing
recommendation or observed merge is not an invocation. This is factual current-handoff reconciliation, not
implementation repair or a replacement delivery attempt. Read the TASK, parent, Board and original canonical
Review/QA and accepted-candidate receipts; keep their identities and bytes intact. No new independent acceptance
is needed merely to interpret historical delivery evidence. Missing proof needed to support a claim holds that
claim's correction; it never permits fabricating acceptance or rewriting a report.

1. **Prove subject and current state before edits.** Resolve the intended repository/remote, original PR URL and
   number, TASK, original base/head branches and recorded accepted/published OIDs from authoritative sources.
   Query that exact PR with existing hosting capabilities, for example `gh pr view "$original_pr" --repo "$repo"
   --json url,number,state,mergedAt,mergeCommit,baseRefName,headRefName,headRefOid,headRepository,headRepositoryOwner`.
   These are shell variables bound to the verified identities, not literal placeholders to paste unresolved.
   Compare returned repository/URL, base/head and head OID with the recorded candidate and any established
   reconciliation/closeout bridge. Deleted branches do not require recreating them: use PR evidence and retained
   commit provenance. Record observation time, source and OIDs in ignored TASK scratch. A changed OID without a
   bridge, wrong repository/PR/branch, contradictory evidence or missing candidate provenance is a hold, not a
   best guess. `done`, old prose and a PR link are not live proof.

   | Verified observation | Permitted current instruction |
   |---|---|
   | MERGED, with merge time/OID and matching subject provenance | Retire the original merge action; state the observed merge and point to current routing or the correction's remaining action. This proves merge, not deployment/release or unobserved checks. |
   | CLOSED with no merge, matching subject provenance | Retire the false merge action; state **closed without merge / not delivered through this PR**. Preserve acceptance/status and name the maintainer decision about future delivery. Do not reopen implementation, reopen the PR or invent a replacement implementation PR. |
   | OPEN | This ended-handoff path does not apply. Leave files unchanged and return the exact ordinary Land/delivery or human action justified by current evidence. |
   | Unavailable CLI/authentication/hosting, inconsistent state or identity | Leave affected files unchanged; name the blocked correction/publication and the owner/action to restore exact PR evidence. Never assume merged, closed or still open. |

2. **Select a safe correction location and effects.** Inspect `git worktree list --porcelain`, status, branch,
   origin, local history and current remote base. Confirm the base OID with `git ls-remote --heads origin
   refs/heads/develop` (using the bound base), fetching it within authority before using a stale local tracking
   ref. Read-only inspection/no-op needs no new correction worktree or publication authority; select those only
   for a necessary correction/effect. The original worktree may be absent; neither its absence nor an
   ended original PR authorizes switching, resetting or repurposing a human checkout. Honor an already-selected
   safe checkout; otherwise ask for the correction checkout/worktree choice. After inspecting existing corrections
   in step 3, a user-authorized fresh isolated registered worktree can be created from the verified current
   `origin/develop` (or target's bound base) using an unused owned `feature/task-NNNNN-ended-handoff-correction`
   branch, with the actual TASK ID. Do not recreate/reuse the ended original feature branch to publish correction.
   Resolve branch/path collisions by ownership evidence, not deletion or an automatically incremented suffix.
   Unrelated dirty changes, ambiguous ownership or stale checkout state block mutation until its owner resolves
   it. An existing proven owned correction diff may continue only after the interruption checks below.
   Keep scratch under `.runs/tasks/<TASK-ID>/runs/` and retain the durable receipt at the canonical base before
   any authorized worktree cleanup. Never mutate another checkout's tracked files through provider paths.

   Explicit Land authority covers bounded factual correction/publication under the intake rules, but still
   verify that it applies to the selected correction checkout, branch and new/existing correction PR. A narrower
   instruction (read-only, local-only or no new PR) wins. If an effect or checkout choice is missing, return the
   exact requested authority; do not repeat an approval already supplied. Observing closure alone grants none.

3. **Inspect interruption and existing correction before retry.** Bind a correction identity to repository,
   original TASK/PR, observed ended state/OID, target base and current-handoff-only scope. Inspect retained
   receipts, local branches/log/diffs, the exact remote correction ref (`git ls-remote --heads origin
   "$correction_ref"`) and hosting results. Query the recorded corrective PR directly; if absent, use a bounded
   exact-head search including all states, for example `gh pr list --repo "$repo" --state all --head
   "$correction_branch" --json url,state,baseRefName,headRefName,headRefOid,body`. Establish subject ownership from
   its body/receipt and actual diff, not title or branch name alone. Check fork/repository identity on a match.
   Do not scan every historical PR or create anything merely to discover whether an earlier create succeeded.

   - Matching local uncommitted correction: inspect and continue only its proven owned diff, without resetting
     it. A local committed/unpushed correction is reused after revalidation, not committed again.
   - Push succeeded but PR creation did not: verify remote OID and, after proving no matching corrective PR
     exists, create only the missing PR within authority. Never blindly repeat the push/create sequence.
   - Matching open corrective PR: reuse its branch/PR and reconcile its actual remote/local head and diff before
     any update. If it already contains the desired candidate, record that result without another commit/push.
   - Corrective PR already merged: inspect the current base's sources/views. If consistent, finish read-only;
     if not, identify the remaining discrepancy and request the needed new correction scope/authority rather
     than assuming another PR is allowed. A closed-unmerged corrective PR needs a maintainer disposition, not
     automatic reopening or replacement.
   - Nonzero/timeout after push or create: inspect actual remote ref, exact-head PR results and PR body before
     retrying. Confirm success, prove the missing effect, or **hold** with the unresolved publication operation
     and recovery action. Conflicting matches, inaccessible hosting or unknown remote OID never justify another
     PR/commit or declaring success. One bounded inspection pass suffices; do not poll/retry indefinitely.

4. **Reconcile only necessary current facts.** Apply [current subject handoff synchronization](#synchronize-current-subject-handoffs)
   to the correction's current-base sources: inventory authored Board/Roadmap/README actions and source records,
   distinguish history, reconcile factual stage/pointers and refresh derived views with the target's owning tool.
   Keep original PR metadata/provenance distinguishable from the corrective PR; do not replace the original
   TASK PR locator with the corrective PR and thereby disguise non-delivery. Record the correction link and its
   original-subject relationship in the current section/ignored receipt and PR body where appropriate. Preserve
   canonical reports, historical acceptance/completion checkpoints, priorities, dependencies and unrelated work.
   In the closed-unmerged case, implementation acceptance remains historical acceptance, not merge or automatic
   `wontfix`/reopen. If correcting the handoff would alter requirements, workflow rules, implementation or
   acceptance, stop for the authorized work/review route; this recovery exception cannot accept substantive edits.

5. **Finish finitely, verify any actual candidate.** Compare the intended current facts and derived views with
   the existing tree/correction. Unchanged, already-consistent sources need only a read-only receipt, even on
   repeated resume: no empty commit, acceptance toggle, report rewrite, duplicate PR or publication retry.
   For an actual factual correction, inspect its complete diff and run focused checks, planning refresh/read-only
   validation, `git diff --check` and qualified gate-receipt reuse under [Verify and publish](#verify-and-publish).
   Preserve original accepted A and any closeout B/bridge; a factual correction C is not retroactively reviewed
   A and supplies no new independent acceptance. Apply the administrative-only assessment and all applicable
   candidate/publication checks; a substantive change needs its own independent review and applicable QA.
   Before commit/publication, recheck the original PR's live identity/state and current base/source snapshot;
   a reopened original or changed/contradictory source holds the stale correction for bounded reassessment.
   Stage only owned files, reuse the proven correction branch, commit only necessary changes, and non-force
   publish/create or update **the corrective PR**, never the ended original PR. Verify corrective PR repository,
   open state, base/head, actual remote OID and any required final-head proof before successful delivery/cleanup.
   Missing/failed required proof retains resources and a precise hold. Administrative factual metadata can reuse
   qualified evidence; executable instructions and requirements remain substantive even in Markdown.

   Freeze truthful tracked wording before the last necessary commit: observed original merge or non-delivery,
   correction publication/checks pending at that checkpoint, and an actual durable result locator. Put subsequent
   results in ignored evidence/PR body, not another metadata commit certifying C. Return original and correction
   identities, inventory/dispositions, preserved report hashes/provenance, commands/results, publication outcome,
   resources and exact remaining human action. Normal ownership-proven cleanup applies only with its existing
   authority; no new approval/merge, original-PR reopening/replacement, deployment, release, archive or remote
   branch deletion is authorized. A no-op does not trigger unrelated cleanup or start another TASK.

For example, after a verified merge the current section can say “Original PR merged at the recorded merge OID;
current correction result is in the durable TASK receipt; no original-PR merge action remains.” After verified
closure it must instead say “Original PR closed without merge; implementation acceptance is preserved, delivery
through that PR did not occur; maintainer must decide future delivery.” Use actual PR/receipt locators and observed
OIDs in execution, not these example descriptions. Historical delivery-pending text stays untouched beneath a
clearly separate current pointer. The correction's human review/merge action, if any, names only its own open PR.

## Verify and publish

Use the TASK completion notes to record the accepted candidate/report and any finding dispositions, reconciliation
proof, fresh checks with counts/warnings, risks, and publication state. `done` means accepted implementation and
all required acceptance verification, including hosted proof when required, not successful delivery, PR approval
or merge. For required hosted checks, first establish the [closeout contract](#acceptance-candidate-and-administrative-closeout).
Record `done` in B only after A is independently accepted and required QA is complete; say explicitly that final
publication/checks are pending. [Synchronize current subject handoffs](#synchronize-current-subject-handoffs)
alongside these source records; a generated refresh does not reconcile authored Board/Roadmap/README actions.
Close eligible TICKET and EPIC ancestors in this same administrative closeout
under [automatic parent completion](../../planning/CONVENTIONS.md#automatic-parent-completion). Run
`./bin/planning-check --write` and include the parent statuses and generated views in the same commit. No separate
parent review, approval or closeout handoff is required.

Apply [gate evidence reuse](EXECUTION.md#gate-evidence-reuse) before initial publication: inspect actual source,
runtime inputs and provenance, reuse the qualifying complete receipt and run focused checks for affected contracts.
A substantive implementation/input change needs one complete final gate, not another run for each publication step.
Run `./bin/planning-check` and `git diff --check` for owned metadata changes. Stage only owned planning or reconciliation files; inspect the staged diff and commit them if needed. Push the feature branch without force; create or update its PR against `develop`. For a TASK PR, set and verify the title `TASK-NNNNN — <TASK title>` (the body template cannot set a title), and use the editable [PR body template](../../.github/pull_request_template.md) for scope, behavior, verification, warnings, evidence, and outstanding review/merge status. Remove placeholder text and avoid implying a check passed if it was not run. Preserve an existing PR's identity when updating it; do not rename an unrelated or historical branch merely to match the new convention. Record the actual PR URL in the TASK and refresh generated views. Inspect the exact administrative diff, validate planning, whitespace and applicable live delivery provenance before committing/pushing PR metadata; reuse the qualified implementation receipt without relabeling it final-head execution. If a result changes, correct the evidence and rerun the affected checks. Avoid repeating gates for an unchanged tree merely to accumulate receipts.

Land owns final delivery verification after independent acceptance. Verify the A → B diff contains only
permitted administrative closeout and preserve any mechanical-reconciliation bridge. For required final-head
delivery checks, retain the draft PR and worktree after pushing B until proof covers B. Record the run URL,
event, source head/base, actual tested commit, required job conclusions and warnings. Distinguish source-head
runs from merge-candidate runs and verify the tested head/base relationship against the accepted requirement;
a stale run, wrong target or skipped required job is not proof.

Record the accepted A checkpoint, B, the administrative diff assessment, any reconciliation bridge and delivery
proof in the ignored receipt and PR body. Once applicable QA, publication and required delivery checks are
satisfied, mark the PR ready and proceed to ownership-proven cleanup without another tracked commit. No additional
independent review or canonical review update is required solely for administrative closeout, changed commit IDs
or newly available delivery CI results. Preserve the canonical acceptance report; do not rewrite it to claim B
was independently reviewed. The tracked note remains a truthful checkpoint (accepted A, delivery pending at
closeout), not a live run ledger. Missing required delivery proof blocks successful landing and cleanup without
rescinding A's acceptance or requiring another review.

A queued, unavailable or infrastructure-failed final run leaves delivery incomplete: retain B, draft status and
resources, disclose the cause, and retry the same head when authorized. Do not toggle `done` just to record run
state. A run demonstrating an implementation defect invalidates acceptance: stop landing, route to `work` to
reopen the TASK and repair it, then require new verification and independent review. A non-administrative B or
unproven interaction likewise stops for fresh review; it cannot inherit A's completion claim. Any later commit
changes the delivery candidate and requires its own applicable final-head proof. Never use A's green run for B.

After final push, query the hosting service: PR open, base `develop`, intended head branch, remote head equal to local `HEAD`. Authentication failure, failed required checks, publication failure, or remote-head mismatch blocks a successful landing; preserve the worktree. No approval or merge occurs here.

## Cleanup and handoff

### Cleanup-only intake

An explicit `/skill:land TASK-NNNNN cleanup-only`, or a request to clean this repository's merged TASK
resources, enters here **before** publication or ended-handoff correction. Ordinary one-TASK Land does not
silently sweep unrelated tasks. Bind a repository-scoped request to its canonical checkout and explicit exclusions;
preview a bounded candidate inventory from registered worktrees **and** Docker project/configuration/mount
metadata beneath that repository. Resolve each candidate to its exact TASK and PR using authoritative records
or retained handoffs, then query that PR directly. Do not scan every historical PR or other repositories.

For merged candidates, prove repository/base/head identity, final PR head, merge OID/time and inclusion in current
`develop` (use hosting provenance for squash/rebase). Requalify any remaining local tip against that head and
preserve unpublished work. An absent worktree/ref does not prevent independently qualified Docker cleanup.
For a published-open subject, retain the ordinary delivery/QA/provenance gates below. Closed-unmerged, unknown
identity or inaccessible proof holds the affected candidate; a merged status alone does not prove resource ownership.
Preserve existing Review/QA and acceptance receipts; do not rerun implementation acceptance merely to remove
an already-merged test stack. Missing historical reports limit acceptance claims, not independently proven
ownership/merge cleanup under the explicit request.

Reconcile the existing receipt with **current** Git/Docker/process state, including resources recreated since a
recorded removal. Apply the inventory, preservation, consumer and postcondition rules below to remaining effects
only. Historical publication wording does not require a corrective PR or tracked changes before independently
qualified ownership-proven cleanup. Do not publish, create a correction checkout,
repeat gates, rewrite reports or commit metadata just to retire resources. Ended-handoff correction remains a
separate explicitly requested operation. Return per-subject removed/retained/held results and actual next actions.

### Executable local operation

Local Land and Build's post-worker cleanup-only continuation execute the
[exact-subject cleanup operation](../../.pi/skills/land/references/cleanup.md), using the existing private handoff/
receipt. This section remains policy authority; the operation's schema, conservative capability holds and finite
resume procedure live beside its callers. Normal callers have no ad hoc destructive fallback. Qualification,
external preservation, effect intent and fresh postconditions are recorded separately from publication. A hold
never authorizes protection changes or generic runtime setup. This helper is not universal shell enforcement or
OS isolation; unrestricted agents can bypass it, and arbitrary external races cannot be atomically fenced.

### Standing authority and upstream readiness

Original recorded authority to dispose of positively disposable local test/dev resources carries through Work,
Review and QA to Land; no recurring human or per-effect prompt is required. Recoverable unused images/cache
use the separately approved recoverability policy without historical task ownership. Unknown/durable/shared
data, unique work, conflicting consumers and protected host/private/report state remain outside that authority.

For lifecycle-enabled targets, require the [upstream readiness operation](RESOURCE_LIFECYCLE.md#standing-cleanup-readiness),
not a prose-only checklist. Each creating stage records exact identities/generations/config/env/creating mounts,
dependencies and private/generated-artifact classifications before creators disappear. Its handoff runs actual
preservation/capability checks and carries the SAME receipt, immutable external proof and original worker records.
Land refreshes readiness on the current closeout source before publication, binds actual acceptance/QA/publication
and original Land monitor/Pi exits at the proper stage, generates the plan, executes eligible effects and verifies
outcomes. Build finishes the supported SAME-receipt post-worker continuation without another routine approval.
A missing proof/capability is a precise affected hold, not permission to strip metadata, guess exclusions or skip
independent eligible effects. Controlled qualification is not independent Review/QA or actual delivery evidence.

### Completion ownership

Safe cleanup is a required effect of authorized Land, not an optional follow-up after publication. Remove the
eligible isolated worktree, local feature branch, disposable test containers, unused task-owned networks and
qualified task-created named/anonymous disposable volumes. Account separately for created versus reused/shared
images and caches; unsupported exact retirement is a finite hold, never an implicit leak exemption.
TASK-00546 additionally permits bounded unused positively downloadable/recoverable image/cache reclamation
without historical TASK ownership through the SAME operation's documented storage policy. Qualify actual engine,
worker, retained backing/content, protected inputs, retention and consumers first. This exception never extends
to unique work, volumes, host/source/private data, unfiltered prune or a protection-changing fallback.
Publication does not require merge first. A resource that fails qualification stays in place with an explicit
hold; successful publication with cleanup held is **not complete Land**. Do not request another cleanup prompt
for effects already within Land authority. Never delete the main checkout or a current/active branch, terminate
an unrequested session, delete a remote branch, prune globally, stop a shared stack/VM or erase durable data.

### Inventory and preserve

Preview candidates before publication; carry the inventory in the existing Work → Build → Land handoff and
durable TASK receipt, not a new registry. Re-observe it immediately before each destructive effect. Bind each
entry to the target repository/TASK and record exact identity, ownership evidence, dependencies, intended
remove/retain/hold disposition and reason, command/result and postcondition:

- Selected and canonical safe checkouts, Git common directory, registered worktree paths, local full branch ref,
  local OID, accepted/published candidate and bridge, remote URL/ref/OID, exact PR identity/state/merge evidence.
- Runtime kind: qualified Compose project, absolute configuration/override/env-file **locators** and working
  directory, or the explicit standalone creation capability with immutable original allocation/source proof.
  Carry wrapper invocation, container IDs/configuration fingerprints/labels, mounts and dependencies;
  network IDs/configuration fingerprints/labels and every attached endpoint's ownership. Never invent Compose
  labels/configuration for standalone subjects; valid historical adoption uses a linked immutable plan or a
  precise evidence hold under the executable contract, without requiring a retired source worktree.
  Capture exact creating-container mounts and storage identity/provenance before removing the creating
  container, and retain that observation outside removal candidates. For named/image-declared anonymous
  volumes, distinguish task-created, merely used and shared storage, size observation, explicit disposability
  and discard authority. Inventory images/caches with creation versus reuse, sizes/dependencies and retention
  reasons; shared layers and unknown caches are not disposable by association.
  Names or Compose labels alone are not sufficient: compare them with the handoff, resolved configuration,
  actual checkout/mount paths and current consumers. Include task stacks whose working/configuration directory
  is no longer a registered worktree. If original configuration is gone, corroborate exact project/config paths
  and mounts against retained handoffs or the verified PR's source configuration, service/process topology and
  data disposition; record what was reconstructed. Uncorroborated ownership still holds. Never recreate a
  missing checkout merely to run its old teardown wrapper. Never print environment values or credentials.
- Task processes/sessions (identity, liveness and cwd), shared consumers, volumes/bind data, ignored/untracked
  artifacts, canonical reports, captures, drafts and private runtime/credential paths. Explicitly record none,
  unknown or unavailable; missing inventory is not proof of absence. Active coordinators and child sessions
  retain their checkout/branch/mount dependencies; ask their owner for an authorized later retirement, not kill.

Before retiring a checkout, inspect tracked status plus untracked **and ignored** files; clean Git status alone
misses evidence and keys. Preserve required artifacts outside that checkout at the canonical base's ignored
TASK run/report locations or the target's bound equivalents. Follow [scratch retention](SCRATCH.md): record
old/new recoverable locators, ownership, exclusions and digests, verify the copy and consumers, preserve report
bytes and private file permissions. A locator inside the deleted checkout is not retention. Unknown ownership,
unrecoverable evidence, private state without an authorized destination, or a live mount holds the checkout.
Durable volumes/databases/credentials remain retained by default; volume deletion needs separate explicit
proven-disposable authorization. Never use `down -v` to make the inventory empty.

### Qualify live consumers

A task session/coordinator cwd, writer, active Docker bind mount or other actual dependency holds its checkout.
Recheck exited worker PIDs by identity/liveness, not a historical receipt. Do not kill an unrequested session or
stop a shared VM. A generic virtualization process's read-only descriptors are **not alone** an active-task proof,
nor is their read-only mode alone permission to delete. Qualify them from a separate safe checkout:

1. Identify the live process, owner, executable and descriptor access modes. Establish it is the local Docker
   file-sharing/virtualization service using local runtime/process evidence, not a similar name or old PID.
2. Successfully inspect **all** containers, including stopped ones, for source mounts into the candidate and
   parent mounts exposing it, plus host sessions/process cwd, write-capable descriptors and known non-Docker
   consumers. Retire qualified task containers first. An explicit candidate mount/session/writer, incomplete
   visibility or unknown consumer holds the affected checkout. A parent-only shared repository mount is not by
   itself a task dependency: inspect that container's configured source, processes/cwd and actual candidate use;
   positively establish unrelated use or hold it. Never stop a shared stack to clear general path exposure,
   infer absence from an inspection error, or kill an unknown consumer to proceed.
3. Preserve required ignored/private state outside the checkout with modes and manifests. Compare source
   digests/status before and after copying and immediately before removal; changes or unverifiable files hold.
4. When only positively qualified read-only file-sharing handles remain, record that evidence and proceed with
   normal non-force Git cleanup. Handles need not reach zero; do not demand VM shutdown to clear file-sharing
   cache descriptors. Any new actual dependency or removal failure stops the affected effect without force.

This exception is bounded to qualified read-only runtime file sharing, not arbitrary readers or an exclusion for
all VM processes. Preserve the observation and postconditions in the existing receipt.

### Qualify local Git cleanup

Ordinary publication cleanup requires completed applicable QA, publication and final delivery verification under
[Verify and publish](#verify-and-publish), and reconciled current handoffs under
[subject synchronization](#synchronize-current-subject-handoffs). Preserve accepted-candidate/report provenance.
An explicitly resumed ended-handoff **correction** also requires its live-state and correction-publication proof;
closure alone does not authorize disposal. An explicit cleanup-only request instead uses
[its intake](#cleanup-only-intake): merged-resource retirement needs no new acceptance, handoff correction or
corrective publication. Then establish one proof path:

| State | Required proof for the local feature ref |
|---|---|
| Published, still open/unmerged | Exact repository/PR/base/head identity and verified delivery; fresh `git ls-remote --heads` for the exact full remote ref equals the recorded published candidate **and** local tip. The PR remains open for human review; leave its remote branch intact. |
| Merged, including a deleted remote branch | Fresh exact PR evidence establishes MERGED, merge time/OID and verified final published PR head; the local tip equals that proven head through its recorded acceptance/closeout bridge. Verify the merge in the intended current base (including hosting evidence for squash/rebase merges). An absent remote ref is not itself merge proof. No remote recreation is needed. |
| Missing/mismatched/inaccessible proof, closed without merge, or local-only commits | Hold the local branch and affected checkout. Name the missing observation, unpublished OIDs or maintainer delivery decision; never infer safety from `done`, a gone tracking ref or a similar name. |

Resolve commits locally and inspect `git log`/`git rev-list` between the proven published head and local ref:
there must be zero local-only commits and the tips must match, not merely share an ancestor. A later local
metadata commit is still unpublished work. If the remote advanced, reconcile provenance/delivery first; do not
quietly delete the older branch. Keep publication proof separate from merge proof and timestamp both observations.

From a separate verified safe checkout, recheck registered worktrees, status, branch/OID, sessions and mounts
under [consumer qualification](#qualify-live-consumers). If the Land worker itself uses the candidate, return its
exact remaining effects to the owning coordinator. After observing worker exit, that coordinator must continue
cleanup from the canonical safe checkout within existing authority, using a cleanup-only Land worker there when
its role requires delegation. Do not dispatch that continuation from the removal candidate, terminate the active
coordinator, or return an already-ended worker-cwd hold as a human prerequisite.
Use `git worktree remove` without force only for a clean, inactive, isolated owned checkout at the qualified
candidate, after preservation and container retirement. Re-query worktree registration and filesystem absence;
removal is not the final tool operation because postconditions and branch cleanup still follow. Never switch or
remove the selected main checkout merely to make its branch eligible. Before branch deletion, prove the exact
ref is not checked out in **any** worktree and no active session/consumer needs it. Prefer `git branch -d` when
Git's ancestry/upstream check permits it. If Git refuses only because a proven published-unmerged or squash-merged
tip is not an ancestor of the safe checkout, the above proof permits exact compare-and-delete with
`git update-ref -d "$local_ref" "$proven_oid"` (bound full `refs/heads/...` and verified old OID), not blanket
`branch -D`. Recheck the ref/worktree/ownership immediately beforehand; an OID change or competing activity
holds deletion. Remove only that owned branch's obsolete configuration if present, never other refs/config.
Record which proof/tool was used and verify ref absence. Do not delete any remote ref.

### Retire task Docker resources

Use the target's owning Docker wrappers with the inventoried exact Compose context where they safely support
bounded retirement. Inspect resolved configuration, labels, mounts and consumers first; do not dump secrets or
run a generic wrapper that selects the shared default stack. If no scoped wrapper exists, use explicit Compose
project/config arguments or exact Docker IDs with the same ownership proof. Use `docker inspect` for exact
container IDs and `docker network inspect` for network IDs/labels/endpoints. Stop/remove only the enumerated
disposable task containers without force; preserve creating-container volume/mount provenance durably first.
Then qualify each explicitly authorized task-created disposable volume separately: reobserve its exact identity
and all current consumers, including stopped containers, immediately before normal removal and verify actual
absence. Missing provenance, recreated identity, unknown/durable/shared data or inspection/permission failure
holds it. Never use blanket `rm -v`, `down -v` or unqualified/unfiltered prune. The bounded recoverable-storage
capability is separately qualified by the [owning operation](../../.pi/skills/land/references/cleanup.md#recoverable-storage-and-allocation-admission-task-00546),
not a fallback after a volume/service hold. A shared container/mount or active task session
holds the affected container and dependent worktree. Inspect network endpoints again **after** container removal;
remove an inventoried network by exact ID only when it is task-owned, non-external/non-shared and has zero
endpoints. A foreign/shared endpoint, unknown dependency, label/config mismatch or inspection failure holds the
network; never disconnect others to free it. Do not infer network retirement from a container or Compose exit.
Retain shared runtime and durable data explicitly, with their recovery locators and reasons.

### Verify results and resume

Keep the durable receipt outside every removal candidate and update it after each effect. Record removed,
retained-by-policy, held and failed entries separately, including exact exits/errors and remaining dependencies.
Permission failures are holds, not permission to use force, sudo, broad pruning or an alternate owner. Re-query
`git worktree list --porcelain`, the exact ref with `git for-each-ref`, `docker ps -a --no-trunc` and
`docker network ls --no-trunc`/`docker network inspect`, exact volume inventories/inspections and content-addressed
image observations to prove absence or retained state. Report Docker logical size separately from unknown
physical host reclaim; do not sum shared layer sizes into a reclaim claim. Distinguish a
successful empty inventory query from a daemon/auth/permission failure. Verify retained data/evidence digests,
canonical reports and shared runtime, and recheck the published remote ref/PR remains intact (or the already
verified merged/deleted-remote state is unchanged). Failed postconditions leave cleanup incomplete.

On interruption/resume, inspect the existing receipt and current Git/Docker/PR state once, reconcile uncertain
operations, and attempt only remaining authorized effects whose prerequisites now pass. Already-absent owned
resources are recorded as such, not deleted again. Never repeat publication, create a duplicate PR, recreate a
removed worktree, rewrite independent reports or make another metadata commit solely to record cleanup success.
If a hold is unchanged, return its exact resource, reason, recovery owner/action and receipt locator; do not
poll or retry indefinitely. Later disposition belongs in ignored evidence/PR body, not a final-head commit loop.

Return the actual PR/delivery state, review/QA provenance and bridge, target-specific current-handoff inventory,
checks/warnings, before/after cleanup results and precise holds. The next human action must distinguish PR
review/merge from remaining cleanup recovery. Publication, local cleanup, human approval/merge, remote-branch
deletion, archive, release and deployment remain separate facts.

## QA report intake and publication

Apply [QA applicability](QA.md#applicability) to the reviewed scope. Changed behavior requires QA even without a
UI; verify criterion-specific N/A reasons in the technical handoff or canonical QA report for changes without
behavioral consequences. Missing assessment routes to `qa`. Read the canonical
`<base-worktree>/.runs/qa/<TASK-ID>/qa.md` for required or requested QA. Check current subject,
independence, scenario coverage, artifact digests and any reconciliation bridge under [QA standards](QA.md).
A current FAIL blocks accepted landing and routes to `work`; INCOMPLETE or missing required evidence routes to
`qa` or its stated prerequisite. Do not select a historical PASS or treat requested QA as optional after it fails.
Non-visual evidence can use real requests, command/driver outputs, state checks or instruction walkthroughs as
appropriate. No screenshot requirement does not imply overall QA N/A.

Publish sanitized captures through an available mechanism within the existing publication authority and verify
access for the intended PR audience. A local behavioral PASS may have publication pending. No working upload
path means an explicit human handoff and incomplete delivery, not invented URLs or product-source screenshot
commits. Keep reports/captures outside disposable worktrees; retain resources while required QA or artifact
publication is pending. Already-open PRs can receive QA before merge; this does not itself authorize PR updates.

## Visual evidence

For UI/TUI changes consume current post-review QA evidence under [QA standards](QA.md) and include the PR
Before/After table with accessible real captures. Non-visual work records visual N/A or omits that section while
retaining its behavioral QA results and relevant evidence in the PR verification summary. Do not run
unnecessary screenshot steps on documentation-only changes. Missing required evidence remains visible.
