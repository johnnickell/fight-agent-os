---
name: review
description: Independently challenge a TASK, PR or versioned release candidate against its requirements, diff, tests and evidence; publish accept or revise without fixing it.
---

# Review

In a participating harness session, follow the installed portable [project-access and discovery rule](../../../harness/pi/FILE_SCREENING_RULE.md).
Strongly prefer Jev before unfamiliar discovery; read known targets and core instructions directly. Respect the
operator-selected role, actual file-access denials and any independently selected deterministic tool policy.
Native shell requires no Jev command assessment or confirmation. Targets need no copy of this rule.

Resolve [target project context](../../../docs/engineering/PROJECT_CONTEXT.md) and read the target's review,
testing and delivery rules. In this repository, read [review standards](../../../docs/engineering/REVIEW.md)
and [engineering standards](../../../docs/engineering/STANDARDS.md) for application changes. Apply target bindings
to the shared workflow references below; provider application policy must not replace package-owned gates.

For `/skill:review release <version>`, a release PR or a release coordinator's assignment, first read
[release acceptance](../release/references/acceptance.md). Substitute its release subject, sourced criteria,
version-4 report and canonical release path for TASK identity/paths and the version-3 header below. No TASK ID is
required. Resolve ambiguous PR identity from its actual branch, diff and requirements; never adopt the last
completed TASK. Return release findings/acceptance to the release coordinator for repair, QA and delivery.
TASK-specific next-command routing below applies only to TASK subjects.

1. **Frame the target.** Read the TASK, parent, decisions and completion claims. Identify the exact base/head, branch, status and all reviewed changes. Disclose contributions to implementation or its evidence; a contributor cannot independently accept. Resolve the base worktree through Git metadata and use only its canonical ignored `.runs/reviews/<TASK-ID>/review.md` for the report. Review is read-only over implementation and planning.
For a target with `bin/runtime`, apply its [local resource lifecycle](../../../docs/engineering/RESOURCE_LIFECYCLE.md)
at actual Review entry and handoff: validate Work's SAME private receipt/locator and runtime, recording this stage.
Do not allocate a competing stack or reconstruct ownership. Use owning wrappers for any authorized additions;
pending/mismatched required creation evidence is Unverified, not an inferred pass. Lifecycle observations are
private task execution evidence, not source repair or permission to retire resources. Record original authority,
exact creators/mounts and generated-artifact/private classifications for authorized additions before losing creators.
Before returning, run `bin/runtime validate --stage review --attempt "$attempt" --handoff`; inspect the SAME
receipt's external preservation/capability proof and precise holds. Do not hand Land an inventory reconstructed
from prose or request another approval for disposal already authorized at creation.

2. **Challenge acceptance in two passes.** Establish the [local gate and any explicit hosted requirement](../../../docs/engineering/REVIEW.md#local-gate-and-optional-hosted-checks); missing optional CI is not a blocker. Inspect the actual diff and map every TASK criterion to the stable Spec IDs in [review standards](../../../docs/engineering/REVIEW.md); check scope, outcomes, validation/authorization, effects, failures and evidence. Separately apply the Standards IDs to ownership, placement, transport scope, naming, meaningful tests and delivery. For each ID record Pass/Fail/Unverified/N/A, exact evidence and limitations; justify N/A and leave missing required proof Unverified. A single independent reviewer can perform both named passes, but builder self-checks cannot approve the work. [Qualify the existing gate receipt](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse) against actual source, runtime and provenance; run fresh focused checks for evidence gaps, not a second complete suite merely for independence. A substantive change or invalid receipt needs a complete final gate. Distinguish new results from reused receipts and disclose warnings, skips and limits. A rebase is judged on its effective new-base diff, not rejected solely for changed commit IDs.

   Review owns the detailed quality assessment; do not require the builder to duplicate it or treat builder
   self-checks as proof. Apply the shared [test, code and documentation checks](../../../docs/engineering/QUALITY.md) to the changed
   scope and directly affected contracts. Challenge each new/changed test's contract, defect sensitivity and
   expectation source under ST-04/SP-05; inspect removed coverage and legitimate interaction/snapshot
   counterexamples. Check code quality against ownership and behavior, and instruction/README/CHANGELOG accuracy
   under ST-03 and other applicable IDs. A rule the code violates is not automatically obsolete. Preserve
   existing verdict rules, separate taste from defects, and report corrections without editing implementation or
   instructions.

3. **Classify and route.** Apply the [hosted-evidence continuation](../../../docs/engineering/REVIEW.md#missing-hosted-evidence-and-focused-continuation) when initial acceptance still lacks only explicitly required hosted CI: retain `revise`/`Unverified`, identify the evidence action, and route to authorized draft publication rather than implementation repair. On return, review the new evidence and intervening changes without repeating unchanged checks. Give each actionable finding severity, affected pass/ID and TASK criterion, reproducible evidence, expected/observed behavior, and correction. Separate non-blocking findings and residual uncertainty. `accept` only when every applicable ID in both passes is Pass and all TASK criteria have sufficient evidence with no blockers; otherwise `revise`.
4. **Publish.** Write a complete version-3 report to the canonical path with the required identity, independence, findings, acceptance evidence, verification, limitations and verdict. Preserve any older numbered history and prior canonical report until the replacement is ready; atomically replace the canonical report and read it back. If publication fails, state the path and failure rather than giving a chat-only verdict.

Return the verdict and absolute canonical report path. Stop before fixes, planning finalization, push, approval or merge.

After technical acceptance, assess [QA applicability](../../../docs/engineering/QA.md#applicability) and record
affected behaviors, useful adversarial scenarios and any criterion-specific N/A reasons in the handoff.
Route changed behavior to the [qa skill](../qa/SKILL.md) before `land`, including APIs, libraries, CLI/background
work and executable instructions. Technical acceptance does not claim QA PASS. Do not claim screenshots were
verified unless actually inspected. Mechanical Board/docs reconciliation
uses the existing landing bridge; another review is not required merely because commit IDs changed. After
acceptance, administrative closeout and newly available delivery CI results route to `land`, without another
independent review or canonical review update solely for delivery.

Finish with an explicit, copyable `Next:` under the [handoff rules](../../../docs/engineering/HANDOFFS.md), using
the actual TASK ID: implementation findings to `work`, acceptance to applicable `qa`, or satisfied QA to authorized
`land`. Preserve evidence-only and human-prerequisite routes instead of always recommending implementation repair.
