---
name: qa
description: Challenge a reviewed TASK or PR with adversarial UI, API, CLI or implementation probes and visual evidence where applicable; report defects without repairing source.
---

# QA

Read [QA standards](../../../docs/engineering/QA.md). They own dispositions, report location, independence,
runtime identity and evidence requirements. Use `/skill:qa TASK-NNNNN` or `/skill:qa <PR URL>`; an optional
scenario narrows investigation but cannot silently waive the TASK's remaining required QA.

1. **Frame the subject.** Read the TASK, parent, accepted requirements, changed behavior and canonical technical
   review. For a PR, inspect its current base/head and resolve its TASK; ask only for missing target identity.
   Match the source (and runtime for executable scenarios) to the reviewed subject, accounting for any proven reconciliation bridge.
   Disclose contributions: the implementer cannot independently pass its own work. Resolve the canonical base
   worktree through Git metadata, allocate a new ignored QA run and publish an initial INCOMPLETE report before
   runtime work so interruption cannot leave an older PASS looking current.
2. **Choose scenarios and environment.** Apply [applicability](../../../docs/engineering/QA.md#applicability);
   no UI does not mean no QA. Choose a proportionate set of [adversarial scenarios](../../../docs/engineering/QA.md#adversarial-scenarios)
   against the changed contract: intended use plus likely or high-impact failures. Establish independent expected
   outcomes, including rejected operations and forbidden effects. Select browser, API, CLI/TUI, library/worker or
   executable-instruction exercises as relevant; do not expand into every mode. Inspect target-owned commands and
   available tools; use isolated services and disposable data. Retain owned environments needed for evidence or
   repair. Do not use production/shared user data, switch
   a dirty checkout, replace another TASK's running service, or install missing tools without authority. An
   unavailable prerequisite produces INCOMPLETE, with the exact recovery action.
3. **Exercise and capture.** Use the real implementation through the selected interface, including actual API
   requests, browser interaction or a PTY for interactive terminals. If needed, use bounded [disposable probes](../../../docs/engineering/QA.md#disposable-probes)
   in the ignored run directory; retain reproducible scripts and results, without patching source/tests.
   Record steps, inputs, expected/observed results and evidence per scenario. For instruction scenarios, label
   walkthroughs and their limits rather than claiming live execution. Follow [visual evidence guidance](../../../docs/engineering/QA.md#visual-evidence)
   for readable, comparable baseline/result captures of affected states; inspect every final image before citing it.
   For terminal image limitations, keep a sanitized real transcript/recording and state what it cannot prove.
   Screenshots, static source inspection, existing green tests and successful startup alone cannot establish
   executable behavioral PASS.
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
