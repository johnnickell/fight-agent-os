---
name: review
description: Independently challenge an implemented TASK or PR against its requirements, diff, tests and evidence; publish accept or revise without fixing it.
---

# Review

Read [review standards](../../../docs/engineering/REVIEW.md) before starting and [engineering standards](../../../docs/engineering/STANDARDS.md) for application changes. The standards own report format, verdict rules and publication; keep those definitions there.

1. **Frame the target.** Read the TASK, parent, decisions and completion claims. Identify the exact base/head, branch, status and all reviewed changes. Disclose contributions to implementation or its evidence; a contributor cannot independently accept. Resolve the base worktree through Git metadata and use only its canonical ignored `.runs/reviews/<TASK-ID>/review.md` for the report. Review is read-only over implementation and planning.
2. **Challenge acceptance in two passes.** Inspect the actual diff and map every TASK criterion to the stable Spec IDs in [review standards](../../../docs/engineering/REVIEW.md); check scope, outcomes, validation/authorization, effects, failures and evidence. Separately apply the Standards IDs to ownership, placement, transport scope, naming, meaningful tests and delivery. For each ID record Pass/Fail/Unverified/N/A, exact evidence and limitations; justify N/A and leave missing required proof Unverified. A single independent reviewer can perform both named passes, but builder self-checks cannot approve the work. Run fresh focused checks and the full gate when needed; distinguish new results from inherited receipts and disclose warnings, skips and limits. A rebase is judged on its effective new-base diff, not rejected solely for changed commit IDs.
3. **Classify.** Give each actionable finding severity, affected pass/ID and TASK criterion, reproducible evidence, expected/observed behavior, and correction. Separate non-blocking findings and residual uncertainty. `accept` only when every applicable ID in both passes is Pass and all TASK criteria have sufficient evidence with no blockers; otherwise `revise`.
4. **Publish.** Write a complete version-3 report to the canonical path with the required identity, independence, findings, acceptance evidence, verification, limitations and verdict. Preserve any older numbered history and prior canonical report until the replacement is ready; atomically replace the canonical report and read it back. If publication fails, state the path and failure rather than giving a chat-only verdict.

Return the verdict and absolute canonical report path. Stop before fixes, planning finalization, push, approval or merge.
