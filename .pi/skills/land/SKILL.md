---
name: land
description: Publish an accepted TASK, obtain required hosted evidence, resume an ended handoff, and automatically finish eligible owned cleanup; cleanup-only never republishes.
---

# Land

Resolve [project context](../../../docs/engineering/PROJECT_CONTEXT.md), target rules and
[landing standards](../../../docs/engineering/LANDING.md). Follow the
[native workflow rule](../../../harness/pi/WORKFLOW_RULE.md). This is one subject, not a fleet sweep.

1. **Choose the route.** Explicit `cleanup-only` enters
   [cleanup intake](../../../docs/engineering/LANDING.md#cleanup-only-intake), never publication. An ended PR
   enters [ended-handoff recovery](../../../docs/engineering/LANDING.md#resume-an-ended-delivery-handoff).
   Missing explicitly required hosted acceptance proof uses the authorized draft-evidence path, not invented
   acceptance or implementation edits. Otherwise require current independent Review ACCEPT and applicable QA
   PASS/criterion-specific N/A. A current FAIL/INCOMPLETE cannot be replaced by an older pass.
2. **Bind the subject.** Verify target origin/live default/policy/base, exact checkout/ref/OIDs, canonical reports,
   independence and actual live PR. An advancing base follows the standards' behavior-preserving integration
   boundary, including code conflicts without routine Review/QA. A Sync assignment also requires its original
   reports, integration handoff and same existing OPEN PR; no replacement PR. Stop for concrete incompatible
   intent, defects, new behavior or failed verification. Never rewrite acceptance or published history.
3. **Close out and publish only when authorized.** Reconcile applicable current TASK/parent/docs/handoffs;
   generated Board changes only here unless separate authored planning is approved. Apply the
   [authored routing boundary](../../../planning/CONVENTIONS.md#authored-routing-boundary): update/remove existing
   relevant actions, never append per-TASK assessment/acceptance/delivery checkpoints or move them to Roadmap/README.
   Preserve proof in the owning TASK/reports and delivery receipt/PR. No authored Board entry is required for acceptance
   or publication. Refresh/check planning,
   inspect exact administrative diff and whitespace. [Reuse qualified gate evidence](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse)
   rather than repeating it for metadata. Run `./bin/build` normally for automatic reuse; use `--force` for
   deliberate fresh execution and distinguish reused receipts from fresh evidence. Genuinely new implementation
   needs renewed verification/independence; integration-only changes follow advancing-base policy. Commit only
   owned files, non-force push, create/update the exact PR using its template and `TASK-NNNNN — <title>`. Verify
   current base/head/remote OID, required checks and audience-accessible applicable captures. Follow the standards'
   upload/partial-publication recovery and the [native attachment method](#github-native-artifact-attachments) below;
   missing required proof keeps delivery incomplete. No approval or merge.
4. **Retire and inspect.** Follow the one shared [resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md).
   Routine owned cleanup is already authorized. Invoke `bin/down --worktree SELECTOR` FIRST: task DB/storage
   is disposable; native Compose/Docker metadata governs teardown without worker/PID/receipt gates. Preserve
   main data and foreign/shared projects. Follow [scoped ingress retirement](../../../docs/engineering/LOCAL_INGRESS.md#scoped-task-preparation-and-retirement):
   verify the selected gateway/route is gone, then use supported tools/existing privilege to remove only its
   owned front-door blocks, full-test and gracefully reload before exact leaf removal. Preserve peer blocks,
   referenced material and shared supplier services/network/trust; no restart or blanket human-only cleanup.
   Explicit macOS authorization may use the runbook's native administrator dialog for an exact digest/master-
   bound test/HUP command; credentials stay in the OS dialog, with no persistent permissions or restart.
   `sudo -n` denial alone does not establish unavailability. Respect cancellation and reconcile partial effects.
   If full-test/reload privilege is genuinely unavailable, report exact names/include/digest and retain their leaves;
   that hold does not block independent Docker/source effects. Any required recovery setup uses the same
   runbook's explicit exact-name preparation, never silent infrastructure enrollment.
   Then separately qualify source/ref/private data, consumers and genuine
   filesystem protections before Worktrunk removal. Use the shared guarded native remover: it rechecks effective
   canonical/target hooks and uses per-invocation noninteractive consent only for absent hooks or the exact
   reviewed repository down hook. Do not ask the user for that routine cleanup; unknown/user/changed hooks
   refuse source removal without blocking Docker teardown. Never persist approvals, bypass hooks, force, prune,
   strip protections or stop a shared VM. An exact structured `retained_unmerged` outcome with absent source/
   registration/runtime, unchanged ref and no registered owner permits generated config retirement. Report
   the local ref intentionally retained pending integration separately; do not call eligible closeout failed,
   claim all refs deleted, retain secrets until merge or retry unchanged cleanup. Unknown/raced/checked-out/
   failed outcomes remain holds. After config retirement, repeats use only the original absolute path, never
   branch-based redirection or fabricated publication.
   No mandatory source copies or permanent local archive. Keep active-role evidence until outcomes reach TASK/PR.
   If this worker consumes source, hand the exact remaining effects to its owning coordinator, which follows
   [automatic cleanup continuation](../../../docs/engineering/LANDING.md#automatic-cleanup-continuation)
   after actual exit. A `stage-target-changed` refusal of a relocated worker is not itself a source hold:
   use the already-authorized coordinator cleanup path when available, without changing stage identity or
   requesting another cleanup prompt. That source hold cannot block explicit Docker teardown.
   No second cleanup helper or resource ledger.
5. **Report and stop.** Return actual publication state separately from removed/absent/held/failed resource counts,
   exact remaining identities/reasons, fresh/reused evidence and finite next action. Preserve scoped dependency
   warnings: distinguish temporary minimum-lane advisories from ordinary dependency results using actual evidence;
   neither a disclosure nor cleanup implies an advisory was fixed. Verify postconditions yourself;
   a helper exit, successful PR or technical acceptance is not complete cleanup. No tracked cleanup-result loop,
   persistent delivery queue, routine post-merge maintenance, remote-ref deletion, archive, release or deployment.

## GitHub-native artifact attachments

Use this only within explicit evidence-publication authority, after binding the exact repository/PR and checking
capture sanitization, captions and provenance. Prefer **`gh pr edit --attach`** for PR-body evidence; use
**`gh pr comment --attach`** only when a comment is the authorized destination. Inspect the installed
`gh pr edit --help` and `gh pr comment --help` for actual capabilities, not a version-number gate or an assumed
separate `gh upload` command. Do not request browser/manual upload, use gists or commit screenshots to product
source merely because no separate upload command exists. Keep credentials and private images out of logs/publication.

Preserve the current PR body and other contributors' work in a task-owned body file, adding/updating the readable
Before/After table with matching local Markdown image references. Recheck the live body before editing; reconcile
concurrent changes rather than overwrite them. For example, with `pr_url`/`repo` bound to the verified exact PR
and `evidence_dir` the owned sanitized capture directory, preserve the body in `pr-body.md` there and include:

```markdown
| Before | After |
|---|---|
| ![Before: baseline terminal](./before.png) | ![After: reviewed terminal](./after.png) |
```

Use actual filenames and scenario captions, baseline/result commits and any comparison limits. From that directory:

```sh
cd "$evidence_dir"
gh pr edit "$pr_url" --repo "$repo" --body-file ./pr-body.md \
  --attach './before.png#Before: baseline terminal' \
  --attach './after.png#After: reviewed terminal'
```

Repeat `--attach` for multiple files; supported image syntax is `'path#alt text'`. `gh` rewrites matching local
body references to uploaded assets, retaining the reference's alt text; unmatched attachments are appended.
For an authorized comment destination, use `gh pr comment` with the same body-file/reference/attachment method,
not an extra comment alongside a PR-body update.

A nonzero exit is not proof that nothing published: `gh pr edit` can upload some attachments and update the PR
with those successes before exiting nonzero. After any partial/uncertain result, inspect the actual exact PR or
comment body and uploaded URLs first. Retain successful asset URLs/references; retry only proven missing effects,
without duplicate uploads/comments or replacing successful URLs with local paths. If current state is unverified,
hold retries. Before claiming upload unavailable, inspect relevant native help/capabilities and actual authorized
errors; do not blindly update the global CLI or broaden authentication grants. Report the precise capability,
authority or access prerequisite when blocked. Verify the actual rendered body/table and each required asset's
access for the intended PR audience; command success, an uploader-only view or local paths alone is insufficient.
Missing required access keeps delivery incomplete.

Use [readable handoffs](../../../docs/engineering/HANDOFFS.md). Finish with the actual human PR review/merge URL,
cleanup prerequisite, or owning Work/Review/QA route. Never recommend merging an already-ended original PR.
