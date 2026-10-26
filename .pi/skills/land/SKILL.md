---
name: land
description: Publish an accepted TASK, obtain required hosted evidence, resume an ended handoff, or finish explicitly requested ownership-proven local cleanup without republishing.
---

# Land

In a participating harness session, follow the installed portable [project-access and discovery rule](../../../harness/pi/FILE_SCREENING_RULE.md).
Strongly prefer Jev before unfamiliar discovery; read known targets and core instructions directly. Respect the
operator-selected role, actual file-access denials and any independently selected deterministic tool policy.
Native shell requires no Jev command assessment or confirmation. Targets need no copy of this rule.

Read [landing standards](../../../docs/engineering/LANDING.md). The standards own reconciliation proof, publication and cleanup boundaries; this skill executes them for one TASK.
Before evaluating a current delivery/merge handoff, require
[live handoff evaluation](../../../docs/engineering/HANDOFFS.md#live-delivery-handoff-evaluation) using the target's
qualified bounded exact-subject read (Agent OS: `./bin/planning-check --check-delivery` with relevant marked TASK
IDs). This is separate from offline planning/build and never proves acceptance or candidate provenance.
Unverified evidence holds affected mutation/publication. At authorized normal intake use the target's qualified
`--reconcile-delivery` mode for exact MERGED/integrated `done` handoffs and inspect authored current claims;
no separate routine correction request is needed. Exceptional ended states enter recovery only on explicit
Land resume for that TASK/PR. Do not silently import another target's checker or mutation mode.

For `/skill:land TASK-NNNNN cleanup-only` or an explicit request to clean this repository's merged TASK resources,
enter [cleanup-only intake](../../../docs/engineering/LANDING.md#cleanup-only-intake) first, respecting named
exclusions. Inventory registered checkouts and orphan task Docker stacks independently, prove each subject's
live merge/publication identity and ownership, and execute only qualified remaining cleanup from a safe checkout.
Do not fall through to publication or corrective-PR creation; return actual per-subject results and holds.

For an explicit resume concerning a merged or closed-unmerged original PR, enter [ended-handoff recovery](../../../docs/engineering/LANDING.md#resume-an-ended-delivery-handoff) before the ordinary publication steps below. Verify the exact subject and live state, recover only an authorized owned correction checkout/branch, and inspect any existing correction before retry. Reuse the normal synchronization inventory; preserve historical acceptance and canonical reports. Unverified state, missing authority or ambiguous publication produces a precise hold. Already-consistent unchanged state produces a read-only receipt, not another commit. An ended original PR is never the correction's publication target. Return the correction's actual pending action or no-op result, then stop; do not fall through to normal publication of the ended branch.

1. **Qualify.** Read the TASK, parent, Board and canonical `.runs/reviews/<TASK-ID>/review.md`. Check independence, verdict, criterion coverage, findings and limitations, reviewed base/head, branch and status. [Bind the live target default](../../../docs/engineering/LANDING.md#bind-the-publication-target) against local policy and the existing subject PR before using any base. Inspect that bound base, remote feature branch, PR, checkout and unrelated changes. Confirm credentials and cleanup ownership before mutation.
Only an explicit hosted acceptance requirement activates initial [draft publication](../../../docs/engineering/LANDING.md#draft-publication-to-obtain-hosted-evidence); a workflow file or missing optional run does not. If that proof is the only unresolved requirement, use the draft path and stop with an evidence handoff, incomplete TASK, preserved review and retained worktree. For an already-pushed administrative closeout after acceptance, whether final-head delivery proof is pending or available, instead resume [final delivery verification](../../../docs/engineering/LANDING.md#verify-and-publish) under the canonical report's accepted-candidate provenance; do not repeat initial draft intake or toggle completion status. Ordinary steps below require independent `accept`.

2. **Reconcile if needed.** An advancing base does not invalidate acceptance by itself. On an unpublished clean branch, rebase onto the bound target base when requested; on a published branch use a non-rewriting integration or stop if the user requires a rebase. Regenerate generated planning views from source records, resolve overlap explicitly, compare old reviewed change with new effective diff (`git range-diff` plus inspection), and check interacting base changes. Record old/new OIDs, resolutions and fresh verification as a provenance bridge. Continue under the existing independent review only if the integration is mechanical and behavior is unchanged; otherwise stop for `work` and fresh independent `review`. Never rewrite the review report to claim the new OIDs were reviewed.
3. **Publish.** Assess [QA applicability](../../../docs/engineering/QA.md#applicability) against the reviewed scope;
missing assessment or required evidence routes to `qa`, including non-UI behavior. Verify any criterion-specific
N/A in the technical handoff or canonical QA report. Read `<base-worktree>/.runs/qa/<TASK-ID>/qa.md` when present or required/requested, and verify its subject, independence,
scenario coverage, artifact digests and freshness under [QA standards](../../../docs/engineering/QA.md).
FAIL routes to `work`; INCOMPLETE or missing required/requested QA routes to the [qa skill](../qa/SKILL.md)
or its named prerequisite, retaining resources. Never prefer an older PASS over the current report.
Before closeout/publication commits, execute [current subject handoff synchronization](../../../docs/engineering/LANDING.md#synchronize-current-subject-handoffs): inventory applicable authored current Board/Roadmap/README handoffs and their source records for this exact TASK, distinguish history, and resolve evidence/authority holds before factual corrections. Reconcile current actions alongside parent rollups and generated views; preserve canonical reports and historical checkpoints. Keep the inventory and stage-specific pending/result locators in the durable ignored receipt. The draft-evidence path must not move its fixed candidate with tracked handoff edits. Substantive instruction or acceptance changes are not administrative closeout.
For UI/TUI changes require post-review QA evidence
and include the template's Before/After table; non-visual work may omit the image table but retains behavioral
QA results and useful request/output/state evidence. For required hosted checks, establish the [acceptance-candidate and closeout contract](../../../docs/engineering/LANDING.md#acceptance-candidate-and-administrative-closeout) before recording completion; unresolved literal-final-head acceptance scope needs the requirement owner's decision. Record accepted review and finding dispositions in the TASK. When closing the TASK, run the [automatic parent completion](../../../planning/CONVENTIONS.md#automatic-parent-completion) rollup and include the resulting TICKET/EPIC statuses and generated views in the same commit; do not hand off a separate parent closeout. Then run focused checks, planning validation, diff check and `./bin/build` on the reconciled implementation. Stage only owned files and inspect any commit. Push without force, recheck the live default/policy/PR binding and create/update the PR against that bound base with a verified `TASK-NNNNN — <TASK title>` title and a truthful body based on the repository PR template; do not assume the body template controls the title. Record its URL, reconcile applicable current handoffs to the evidenced stage under the same procedure, and refresh planning views. Verify the final tree with focused checks, `./bin/planning-check`, `git diff --check` and `./bin/build` before the PR-metadata commit and final non-force push. Recheck live default/policy/PR binding and confirm the open PR's bound base, head and remote OID. Land verifies the administrative-only closeout diff and any required final-head delivery checks under the landing standards. Retain draft status and resources while required proof is missing; record successful delivery provenance in the ignored receipt and PR body, then mark the PR ready. Administrative closeout alone requires no additional independent review or canonical review update.
Publish sanitized image/video captures within existing publication authority using `gh pr create --attach` for a
new PR or `gh pr edit <PR-URL> --attach` for an existing PR; check both commands' `--help` for support first.
Repeat `--attach` for each file. Reference those same file paths in the Before/After table supplied via `--body-file`
so gh rewrites them to uploaded asset URLs rather than merely appending captures. Use descriptive image alt text
in the body, or `--attach './capture.png#Description'` for an appended image; videos do not accept alt text.
On partial upload failure, gh may create/update the PR and print its URL despite a nonzero exit. Inspect that PR
and its body, preserve successful uploads and retry only missing captures with `gh pr edit`; never blindly repeat
creation. Verify the resulting references and access for the intended PR audience. Local paths are not PR evidence.
If attachment support, upload or access is unavailable, report publication pending with an exact handoff and retain
resources; do not claim complete landing. Keep canonical reports/captures outside disposable worktrees. QA of an
already-open PR uses the same current-subject checks and does not imply merge approval.

4. **Clean and hand off.** Recheck the inventoried source records and authored current handoffs separately from generated views; record consistency or precise holds before cleanup. Apply the required [cleanup procedure](../../../docs/engineering/LANDING.md#cleanup-and-handoff) to the existing handoff's exact Git, Compose/container/network, process/session, evidence and data inventory. Preserve required artifacts outside disposable checkouts first. After delivery verification, remove eligible task containers, unused owned networks, clean inactive isolated worktrees from a separate safe checkout, and eligible local feature refs qualified against the exact published head or verified merge. Re-query each postcondition; do not retain every local branch unconditionally or stop at a successful removal command. Preserve main/current/active resources, shared runtime, durable data and canonical evidence with explicit dispositions. Qualify read-only virtualization handles under [live-consumer rules](../../../docs/engineering/LANDING.md#qualify-live-consumers), not a blanket VM-stop requirement. If this worker's cwd is the only hold, give the coordinator exact cleanup-only continuation from its safe checkout after worker exit. Partial failure or unsafe ownership leaves cleanup incomplete with an exact hold and finite resume action; never broaden deletion or repeat publication. Return the actual PR/delivery state, review/QA provenance, checks, warnings, inventory/results/receipt and remaining human actions without a final-head metadata loop.

A conflict that changes implementation, failed required check, inaccessible remote, or ambiguous cleanup blocks successful landing. Landing never approves, merges, force-pushes, deletes the remote branch, archives or deploys.

Finish with an explicit `Next:` under the [handoff rules](../../../docs/engineering/HANDOFFS.md). Ended-handoff recovery names the actual correction PR/human decision, missing authority/proof, or no remaining subject action; it never repeats merge of the ended original PR. Successful
publication points to human review/merge of the actual PR. Missing initial acceptance evidence routes to `review`
once available; final delivery verification after acceptance resumes through `land`. Name the required proof
and actual repair, QA or prerequisite route for blockers.
