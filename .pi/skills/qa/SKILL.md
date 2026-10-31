---
name: qa
description: Challenge a reviewed TASK, PR or release candidate with adversarial behavioral probes and applicable visual evidence; report defects without repairing source.
---

# QA

Resolve [project context](../../../docs/engineering/PROJECT_CONTEXT.md), target instructions and
[QA standards](../../../docs/engineering/QA.md). Follow the
[portable access rule](../../../harness/pi/FILE_SCREENING_RULE.md); provider policy never replaces target-owned gates.
For `release <version>` or an assigned release PR, first use
[release acceptance](../release/references/acceptance.md), substituting its subject, criteria and canonical paths.
Include required release scenarios and return to that coordinator; TASK routing below does not apply to releases.

1. **Frame independently.** Read TASK/parent/requirements, changed behavior and canonical technical review.
   Resolve PR identity from actual base/head/diff, not recent history. Verify cwd/origin/source/runtime against
   the reviewed subject and any proven bridge. Disclose contributions: implementers cannot independently pass
   their work. Publish initial INCOMPLETE at canonical base `.runs/qa/<TASK-ID>/qa.md` before runtime probes so
   interruption cannot leave an old PASS looking current. QA writes only its owned reports/evidence, not source/tests.
2. **Select proportionate scenarios.** Apply standards' applicability/adversarial guidance: intended use plus
   likely/high-impact failures and forbidden effects, with independently derived expectations. No UI does not mean
   no QA. Reuse Work's one runtime/record under the shared [resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md).
   Missing identity/prerequisites are INCOMPLETE, never another stack or shared/production fallback. Authorized
   disposable settings/credentials/terminals need no repeated permission. No global installation or shared stop.
3. **Exercise actual behavior.** Use the real browser/API/CLI/PTY/library/worker interface, not source-only claims
   or replayed logs. [Qualify gate reuse](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse) while still
   running independent focused probes. Disposable probes, aliases, endpoints and sessions have finite `finally`
   teardown; keep necessary evidence outside disposable source only while roles need it. For visual work follow
   the standards and [terminal capture procedure](../../../docs/engineering/TERMINAL_CAPTURE.md), preflight the
   bridge and inspect every comparable Before/After image. Missing required images remain a limitation/prerequisite,
   never fabricated evidence or a request for the user to manually take every screenshot. Logs stay human-only
   unless separately supplied for this authorized investigation.
4. **Judge without repairing.** Confirm defects; distinguish product failures from environment/tool prerequisites.
   Use PASS/FAIL/INCOMPLETE/criterion-specific N/A accurately. Give findings stable IDs, severity, criterion,
   exact steps, expected/actual effects and evidence for Work. Revalidate prior findings on repair, qualifying
   unchanged evidence rather than blindly rerunning suites. Recheck final source/runtime/PR head: drift prevents
   a current PASS. Missing technical acceptance leaves readiness INCOMPLETE, even if diagnostic probes pass.
5. **Publish and stop.** Use the [report template](../../../docs/engineering/QA_REPORT_TEMPLATE.md), preserve needed
   run evidence, atomically replace/read back the canonical report and separate local QA from PR artifact upload.
   Return disposition, exact subject/report path, fresh/reused evidence, warnings and limits. Existing TASK/PR
   receives necessary outcomes before final temporary disposal; do not create a permanent fixture/source archive.

Follow [handoff rules](../../../docs/engineering/HANDOFFS.md): confirmed defects to Work, missing acceptance to
Review, other INCOMPLETE to its exact prerequisite plus QA resume, and satisfied QA to authorized Land. Stop before
source/test repair, TASK edits, commits, publication, approval or merge. Technical ACCEPT is not QA PASS.
