---
name: ci
description: Diagnose a failed build or CI job from its logs and revision, identify the cause or evidence gap, and recommend the next action.
---

# CI

Accept a job/run/PR URL, local build log or pasted failure. Resolve the target repository, source revision,
run attempt and failed jobs before comparing results. If only a fragment is available, bound the conclusion
to that fragment. Follow target-owned build instructions; do not import another project's known failures.

Read the first useful failure and enough surrounding output to explain it. Separate the root failure from
downstream cancellations. Classify the evidence as an application/test defect, build/dependency/configuration
problem, external service/runner problem, or unresolved. A failure that disappears on retry is not proven flaky
or fixed; passing locally does not establish that CI is wrong. Never classify an unknown failure as harmless.

Return a short explanation or one row per distinct failure: evidence, likely cause, confidence and next action.
Redact secrets and private payloads. Use [plain language](../../../docs/engineering/QUALITY.md#plain-language-output)
and [handoff rules](../../../docs/engineering/HANDOFFS.md). Reuse the existing issue/TASK when one owns the cause;
hand a confirmed bug to [fix](../fix/SKILL.md), which owns diagnosis/tracking, with repair through work.

Diagnosis is read-only. Do not retry jobs, reinstall dependencies, weaken checks, change secrets, edit code or
publish comments unless those effects are explicitly part of the user's request. Preserve authorization already
given and route authorized repairs through the target's work process. Respect the recently established rule:
one preparation per required build, qualifying evidence reuse, and no redundant release or validation lanes.
