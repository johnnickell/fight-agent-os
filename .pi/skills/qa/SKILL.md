---
name: qa
description: Exercise a reviewed TASK or open PR in its actual browser or terminal and write an independent QA report with captures and repair handoffs, without changing implementation.
---

# QA

Read [QA standards](../../../docs/engineering/QA.md). They own dispositions, report location, independence,
runtime identity and evidence requirements. Use `/skill:qa TASK-NNNNN` or `/skill:qa <PR URL>`; an optional
scenario narrows investigation but cannot silently waive the TASK's remaining required QA.

1. **Frame the subject.** Read the TASK, parent, accepted requirements, changed behavior and canonical technical
   review. For a PR, inspect its current base/head and resolve its TASK; ask only for missing target identity.
   Match the source and runtime to the reviewed subject, accounting for any proven reconciliation bridge.
   Disclose contributions: the implementer cannot independently pass its own work. Resolve the canonical base
   worktree through Git metadata, allocate a new ignored QA run and publish an initial INCOMPLETE report before
   runtime work so interruption cannot leave an older PASS looking current.
2. **Choose scenarios and environment.** Map affected criteria to observable actions and assertions, including
   relevant rejection/error/permission states. Identify browser, interactive terminal or non-interactive CLI
   behavior. Inspect target-owned startup/test commands and available tools; use isolated services and disposable
   data. Retain owned environments needed for evidence or repair. Do not use production/shared user data, switch
   a dirty checkout, replace another TASK's running service, or install missing tools without authority. An
   unavailable prerequisite produces INCOMPLETE, with the exact recovery action.
3. **Exercise and capture.** Use the real application with browser automation or visible interaction; use an
   actual PTY for interactive terminal behavior. Record steps, inputs, expected/observed results and evidence per
   scenario. Capture comparable baseline/result images where applicable and inspect every image before citing it.
   For terminal image limitations, keep a sanitized real transcript/recording and state what it cannot prove.
   Screenshots, static source inspection and successful startup alone cannot establish a behavioral PASS.
4. **Judge and hand off.** Apply the standards' PASS/FAIL/INCOMPLETE/N/A rules. Confirm reproducible defects and
   distinguish environment/tool failures. Assign stable finding IDs and provide criterion, severity, exact
   reproduction, expected/actual outcome and artifact references for `work`; do not repair source or tests.
   Reruns trace prior findings and repeat affected scenarios; reuse unaffected evidence only with provenance.
   Recheck source/runtime and, for PR intake, remote head before finishing. A changed or unverifiable subject
   prevents a current PASS.
5. **Persist and stop.** Use the [report template](../../../docs/engineering/QA_REPORT_TEMPLATE.md), preserve the
   run report/captures and atomically replace the canonical report; read it back. Record local behavioral QA and
   PR artifact publication readiness separately. Return disposition, exact subject, absolute report path,
   evidence links and next action. `land` publishes evidence within its authority; invoking `qa` alone does not
   upload artifacts, post comments, edit the PR/TASK, commit, push, approve or merge.

Default flow: `work → review → qa → land → human merge`. An already-open PR may receive the same QA before
merge. If technical acceptance is missing, record findings as diagnostic evidence but leave acceptance readiness
INCOMPLETE; do not invent an independent review. A confirmed defect is still reported as FAIL.
