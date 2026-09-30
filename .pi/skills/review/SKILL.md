---
name: review
description: Independently challenge an implemented TASK or PR against its requirements, diff, tests and evidence; publish accept or revise without fixing it.
---

# Review

Read [review standards](../../../docs/engineering/REVIEW.md) before starting and [engineering standards](../../../docs/engineering/STANDARDS.md) for application changes. The standards own report format, verdict rules and publication; keep those definitions there.

1. **Frame the target.** Read the TASK, parent, decisions and completion claims. Identify the exact base/head, branch, status and all reviewed changes. Disclose contributions to implementation or its evidence; a contributor cannot independently accept. Resolve the base worktree through Git metadata and use only its canonical ignored `.runs/reviews/<TASK-ID>/review.md` for the report. Review is read-only over implementation and planning.
2. **Challenge acceptance in two passes.** Establish the [local gate and any explicit hosted requirement](../../../docs/engineering/REVIEW.md#local-gate-and-optional-hosted-checks); missing optional CI is not a blocker. Inspect the actual diff and map every TASK criterion to the stable Spec IDs in [review standards](../../../docs/engineering/REVIEW.md); check scope, outcomes, validation/authorization, effects, failures and evidence. Separately apply the Standards IDs to ownership, placement, transport scope, naming, meaningful tests and delivery. For each ID record Pass/Fail/Unverified/N/A, exact evidence and limitations; justify N/A and leave missing required proof Unverified. A single independent reviewer can perform both named passes, but builder self-checks cannot approve the work. Run fresh focused checks and the full gate when needed; distinguish new results from inherited receipts and disclose warnings, skips and limits. A rebase is judged on its effective new-base diff, not rejected solely for changed commit IDs.

   Review owns the detailed quality assessment; do not require the builder to duplicate it or treat builder
   self-checks as proof. Apply the shared [test, code and documentation checks](../../../docs/engineering/QUALITY.md) to the changed
   scope and directly affected contracts. Challenge each new/changed test's contract, defect sensitivity and
   expectation source under ST-04/SP-05; inspect removed coverage and legitimate interaction/snapshot
   counterexamples. Check code quality against ownership and behavior, and instruction/README/CHANGELOG accuracy
   under ST-03 and other applicable IDs. A rule the code violates is not automatically obsolete. Preserve
   existing verdict rules, separate taste from defects, and report corrections without editing implementation or
   instructions.

3. **Classify and route.** Apply the [hosted-evidence continuation](../../../docs/engineering/REVIEW.md#missing-hosted-evidence-and-focused-continuation) when explicitly required hosted CI is the only missing proof: retain `revise`/`Unverified`, identify the evidence action, and route to authorized draft publication rather than implementation repair. On return, review the new evidence and intervening changes without repeating unchanged checks. Give each actionable finding severity, affected pass/ID and TASK criterion, reproducible evidence, expected/observed behavior, and correction. Separate non-blocking findings and residual uncertainty. `accept` only when every applicable ID in both passes is Pass and all TASK criteria have sufficient evidence with no blockers; otherwise `revise`.
4. **Publish.** Write a complete version-3 report to the canonical path with the required identity, independence, findings, acceptance evidence, verification, limitations and verdict. Preserve any older numbered history and prior canonical report until the replacement is ready; atomically replace the canonical report and read it back. If publication fails, state the path and failure rather than giving a chat-only verdict.

Return the verdict and absolute canonical report path. Stop before fixes, planning finalization, push, approval or merge.

After technical acceptance, assess [QA applicability](../../../docs/engineering/QA.md#applicability) and record
affected behaviors, useful adversarial scenarios and any criterion-specific N/A reasons in the handoff.
Route changed behavior to the [qa skill](../qa/SKILL.md) before `land`, including APIs, libraries, CLI/background
work and executable instructions. Technical acceptance does not claim QA PASS. Do not claim screenshots were
verified unless actually inspected. Mechanical Board/docs reconciliation
uses the existing landing bridge; another review is not required merely because commit IDs changed.

Finish with an explicit, copyable `Next:` under the [handoff rules](../../../docs/engineering/HANDOFFS.md), using
the actual TASK ID: implementation findings to `work`, acceptance to applicable `qa`, or satisfied QA to authorized
`land`. Preserve evidence-only and human-prerequisite routes instead of always recommending implementation repair.
