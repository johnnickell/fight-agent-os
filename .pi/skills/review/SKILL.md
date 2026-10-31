---
name: review
description: Independently challenge a TASK, PR or versioned release candidate against requirements, diff, tests and evidence; publish accept or revise without fixing it.
---

# Review

Resolve [project context](../../../docs/engineering/PROJECT_CONTEXT.md), target instructions and
[review standards](../../../docs/engineering/REVIEW.md). Follow the
[portable access rule](../../../harness/pi/FILE_SCREENING_RULE.md); provider policy never replaces target-owned gates.
For `release <version>` or an assigned release PR, first apply
[release acceptance](../release/references/acceptance.md), substituting its subject, criteria, version-4 report
and canonical version path. Never guess a TASK from recent history or use TASK routing for a release subject.

1. **Bind and establish independence.** Read TASK/parent/decisions, requirements and completion claims. Verify
   cwd/origin/base/head/branch/status and full effective diff, including any reconciliation bridge. Disclose
   implementation/evidence contributions: a contributor cannot independently accept. Review is read-only over
   source/planning; only the independent owner publishes canonical base `.runs/reviews/<TASK-ID>/review.md`.
2. **Reuse, don't multiply.** Follow the one shared [resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md).
   Validate/reuse Work's runtime/record; missing required identity is Unverified, not another setup stack. Own
   probes have finite `finally` teardown. Keep evidence while roles need it, not permanent source/fixture archives.
3. **Challenge both passes.** Map every criterion to Spec IDs and separately apply Standards IDs. Use
   [quality guidance](../../../docs/engineering/QUALITY.md) and relevant engineering standards for changed code,
   tests, instructions and docs. Record Pass/Fail/Unverified/justified N/A per applicable ID, exact evidence and
   limits. Challenge removed coverage and new tests' independently derived expectations; qualify infrastructure
   with owning tools, not product tests of tooling. Independent review cannot be replaced by builder self-checks.
4. **Verify and judge.** [Qualify existing gate evidence](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse)
   against actual source/inputs/runtime and warnings. Run fresh focused checks for gaps, not a second full suite
   for independence or changed OIDs alone. Missing optional CI is not a blocker. Required hosted-only gaps route
   to their authorized evidence owner, not manufactured code edits. Give reproducible findings stable IDs,
   severity, criterion, expected/actual result and correction. `accept` requires sufficient evidence for all
   applicable criteria/IDs and no blockers; otherwise `revise`. Never fix findings yourself.
5. **Publish and stop.** Atomically publish/read back the canonical version-3 TASK report with exact identity,
   independence, both passes, findings, fresh/reused checks and limitations. Preserve prior history until the
   replacement is ready; failed publication is not a chat-only verdict. Assess QA applicability: changed behavior
   needs affected independent QA even without a UI; justify criterion-specific N/A. Acceptance is not QA or merge.

Return verdict, absolute report path and [readable next action](../../../docs/engineering/HANDOFFS.md): findings
to Work, technical acceptance to applicable QA, satisfied QA to authorized Land, or the exact evidence prerequisite.
Administrative closeout/delivery evidence alone routes to Land, not another independent acceptance or report rewrite.
Stop before source/planning repair, commit, push, PR approval or merge.
