---
name: work
description: Implement or revise an approved TASK on a feature branch, verify it, and hand the committed work to independent review.
---

# Work

Read the target's instructions, TASK/parent, Board and [execution standards](../../../docs/engineering/EXECUTION.md).
Resolve [project context](../../../docs/engineering/PROJECT_CONTEXT.md); never substitute provider policy for the
target's own gates. Follow the [portable access rule](../../../harness/pi/FILE_SCREENING_RULE.md).

1. Verify cwd/origin/branch/base/status and accepted scope. Preserve unrelated work. Ask main checkout versus
   worktree only if not already selected; use `feature/task-NNNNN-<slug>` from the target base. Keep scratch in
   canonical `.runs/tasks/<TASK-ID>/runs/`, outside disposable checkouts.
2. On revision read canonical base `.runs/reviews/<TASK-ID>/review.md` and current QA report. Trace each current
   finding against the exact subject. Missing evidence routes to its owner, not speculative repairs. Never reset
   consumed repair allowance. An unchanged accepted implementation routes to QA/Land, not another Work attempt.
3. Follow the **explicit [Compose resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md)**.
   `bin/up`/`bin/down` select main; `--worktree SELECTOR` selects a registered disposable project. Reuse that
   project across roles/repairs. No resource receipt, branch/PID or worker-registration gate. Keep active-role
   evidence, not permanent source copies; source/Git holds cannot suppress explicit Docker teardown.
4. Implement only accepted behavior. Reproduce confirmed bugs with a failing owning regression before repair.
   Test owned product behavior; qualify infrastructure with its own tools, not application tests of tooling.
   Use [quality guidance](../../../docs/engineering/QUALITY.md) as needed and
   [Writing for Agents](../writing-for-agents/SKILL.md) for instruction changes. Keep affected current docs accurate.
5. Run focused checks during iteration, planning refresh/check if changed, `git diff --check` and **one final full
   target gate** on frozen implementation. Run `./bin/build` normally for automatic reuse; use `--force` for
   deliberate fresh execution. Record exact subjects, counts, warnings, failures and incomplete criteria. Later
   roles [report gate reuse](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse) accurately instead of
   repeating a passing suite for a role/commit/metadata change. Work cannot supply independent Review or QA.
6. Record implementation/results in the TASK and a readable ignored handoff: branch/head, changed paths,
   fresh/reused evidence, Compose project/config and minimal teardown locator, removed/remaining native
   resources, precise blockers and next action.
   Preserve evidence still needed by independent roles. Stage only owned paths, inspect the staged diff, commit
   and stop. No push, PR, approval, merge, release or deployment without separate authority.

Follow [handoff rules](../../../docs/engineering/HANDOFFS.md). Verified committed work ends with
`Next: /skill:review TASK-NNNNN — independent session required` using the actual ID. Incomplete work names its
real continuation/prerequisite, not a green handoff. Any later authorized closure includes eligible parent
rollup and generated views; Work does not close its own unaccepted TASK.
