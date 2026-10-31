---
name: qa
description: Challenge a reviewed TASK, PR or release candidate with adversarial behavioral probes and visual evidence where applicable; report defects without repairing source.
---

# QA

In a participating harness session, follow the installed portable [project-access and discovery rule](../../../harness/pi/FILE_SCREENING_RULE.md).
Strongly prefer Jev before unfamiliar discovery; read known targets and core instructions directly. Respect the
operator-selected role, actual file-access denials and any independently selected deterministic tool policy.
Native shell requires no Jev command assessment or confirmation. Targets need no copy of this rule.

Resolve [target project context](../../../docs/engineering/PROJECT_CONTEXT.md) and read the target's QA,
testing and release rules alongside [QA standards](../../../docs/engineering/QA.md) for dispositions, independence,
runtime identity and evidence. Apply target bindings throughout; provider application policy must not replace
package-owned gates. Use `/skill:qa TASK-NNNNN` or `/skill:qa <PR URL>`; an optional scenario narrows investigation
but cannot silently waive the TASK's remaining required QA.

For `/skill:qa release <version>`, a release PR or a release coordinator's assignment, first read
[release acceptance](../release/references/acceptance.md). Substitute its release subject, sourced criteria and
canonical release paths for TASK identity/paths throughout this procedure and template. No TASK ID is required.
Include required release-endpoint scenarios even for prose-only preparation. Return the durable result to the
release coordinator; the TASK `work`/`review`/`land` routes below do not apply to release subjects. Standalone
release QA ends with the actual version's release resume command.

1. **Frame the subject.** Read the TASK, parent, accepted requirements, changed behavior and canonical technical
   review. For a PR, inspect its current base/head and resolve its TASK; ask only for missing target identity.
   Match the source (and runtime for executable scenarios) to the reviewed subject, accounting for any proven reconciliation bridge.
   Disclose contributions: the implementer cannot independently pass its own work. Resolve the canonical base
   worktree through Git metadata, allocate a new ignored QA run and publish an initial INCOMPLETE report before
   runtime work so interruption cannot leave an older PASS looking current.
For a target with `bin/runtime`, apply its [local resource lifecycle](../../../docs/engineering/RESOURCE_LIFECYCLE.md)
at actual QA entry and handoff. Validate and reuse Work's SAME private receipt/locator and runtime; use owning
wrappers to capture QA's authorized additions/replacements there. Missing/pending identity evidence is a concrete
INCOMPLETE prerequisite, never permission for another setup stack or retrospective ownership. Retain original
attempts and exact resource-specific holds for repair/Land; recording private runtime evidence is not source repair.
Capture original disposal authority, exact generations/creating mounts, private/evidence classification and finite
observer lifetimes before losing creators. Before returning, run `bin/runtime validate --stage qa --handoff`
with the original `--attempt` binding; inspect external preservation/capability proof in the SAME receipt. Prefer external probe/
report locations; no handwritten Land reconstruction or recurring approval for already-authorized disposable data.

2. **Choose scenarios and environment.** Apply [applicability](../../../docs/engineering/QA.md#applicability);
   no UI does not mean no QA. Choose a proportionate set of [adversarial scenarios](../../../docs/engineering/QA.md#adversarial-scenarios)
   against the changed contract: intended use plus likely or high-impact failures. Establish independent expected
   outcomes, including rejected operations and forbidden effects. Select browser, API, CLI/TUI, library/worker or
   executable-instruction exercises as relevant; do not expand into every mode. Inspect target-owned commands and
   available tools; use isolated services and disposable data. Create missing disposable local settings, test
   credentials and TLS material under [QA authority](../../../docs/engineering/QA.md#sequence-and-authority)
   without asking again. Retain owned environments needed for evidence or
   repair. Do not use production/shared user data, switch
   a dirty checkout, replace another TASK's running service, or install missing tools without authority. An
   unavailable prerequisite produces INCOMPLETE, with the exact recovery action. For macOS terminal visuals,
   use the [Peekaboo capture procedure](../../../docs/engineering/TERMINAL_CAPTURE.md) and preflight its bridge
   before the scenario run. Routine task-owned window creation, interaction, capture and cleanup are included
   in the operator's QA invocation; do not request confirmation for each screenshot or default to manual capture.
3. **Exercise and capture.** Use the real implementation through the selected interface, including actual API
   requests, browser interaction or a PTY for interactive terminals. If needed, use bounded [disposable probes](../../../docs/engineering/QA.md#disposable-probes)
   in the ignored run directory; retain reproducible scripts and results, without patching source/tests.
   [Qualify gate evidence reuse](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse) rather than rerunning
   the complete suite; independent adversarial scenarios remain required. Record fresh versus reused checks,
   steps, inputs, expected/observed results and evidence per scenario. For instruction scenarios, label
   walkthroughs and their limits rather than claiming live execution. Follow [visual evidence guidance](../../../docs/engineering/QA.md#visual-evidence)
   for readable, comparable baseline/result captures of affected states; inspect every final image before citing it.
   Recheck the owned terminal session before each capture and inspect the saved PNG. Diagnose a missing bridge,
   sandbox access, OS permission or stale window separately. For unresolved terminal image limitations, keep a
   sanitized real transcript/recording and state what it cannot prove.
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

End with `Next:` using the actual target and [handoff rules](../../../docs/engineering/HANDOFFS.md): confirmed
defect to `work`, missing technical acceptance to `review`, other INCOMPLETE to its named prerequisite and QA
resume command, and satisfied QA to authorized `land`. Keep the report and evidence links above that line.
