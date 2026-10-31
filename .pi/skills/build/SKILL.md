---
name: build
description: Coordinate one approved TASK through fresh Engineer, independent Review, QA and authorized Land; accept an unambiguous TICKET input.
disable-model-invocation: true
---

# Build

Act as Team Lead, not implementer or independent acceptor. Resolve
[project context](../../../docs/engineering/PROJECT_CONTEXT.md), target instructions/TASK/parents/Board and
[execution rules](../../../docs/engineering/EXECUTION.md). Follow the
[portable access rule](../../../harness/pi/FILE_SCREENING_RULE.md). Fresh Pi contexts are not OS isolation.

## Bound one delivery

`/skill:build TASK-NNNNN` delegates Work, independent Review, applicable QA and Land, including owned commits,
non-force publication and suitable capture uploads. Do not ask again for these effects or ordinary task-owned
cleanup. Never approve/merge/force-push/release/deploy or continue to another TASK. Respect narrower invocation
authority. A TICKET selects only its sole unambiguous eligible TASK; otherwise ask, without decomposing it.

Verify cwd/origin/branch/base/status, blockers, existing PR and unrelated changes. Reuse the operator's selected
checkout; ask main versus worktree only when missing. Keep source in that selected checkout. Resolve canonical
base through Git; canonical Review/QA reports there remain authoritative. Read existing reports/ledger before
choosing a stage; resume current evidence, never reset repair usage or rebuild an accepted unchanged candidate.

Use a private `.runs/build/<TASK-ID>/<run>/` and exclusive `active` directory. Inspect existing ownership before
resume; release only your lock after children stop. One concise ledger holds authorization, project/subject,
checkout/base/head, pinned models, stage/attempt/report/PR locators, shared repairs and incomplete effects.
Keep active coordinator/session protected. No duplicate resource registry or permanent local source archive.
Use the **one shared [resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md)**: Work prepares one
runtime/record, all roles reuse it. Validate identity before dispatch; inspect actual handoffs, not per-stage
proof trees. Missing identity holds affected advancement, not independent eligible cleanup.

## Pin models, then launch fresh roles

Explicit invocation choices override target bindings, then local defaults: retain configured provider; for
`openai-codex`, Team Lead Sol/medium, Engineer and Review Sol/high, QA and Land Terra/high. Resolve the newest
available stable numeric model in that requested family from installed Pi metadata; explicit exact IDs stay
exact. Verify thinking support. No fallback/escalation/provider change or mid-delivery refresh. Pin the resolved
provider/model/thinking per role for repairs/resume; an explicit human change preserves prior history. Record
configured identity separately from unavailable provider attestation. Check installed `pi --version`, `--help`
and `--list-models`; versions are evidence, not equality gates. No global install/settings/trust changes.

Launch **one child at a time** using the existing provider-owned `scripts/run-stage.py`. Resolve its absolute path
beside this skill, never from an unrelated target. A separately trusted installed Harness may be selected with
`--harness-package`; missing support holds the affected route, never loads a second package or bypasses admission.
Read installed Pi [CLI/skills documentation](../../../harness/pi/README.md) as needed before integration changes.
Native session-file birthtime, ancestry/custody and independent role admission remain unchanged. Observation is
human-only/best-effort; missing visibility never authorizes relaunch or executor substitution.

Create a fresh private attempt with `request.md`. Include role, TASK, verified checkout/base/head, absolute owning
skill/context/instruction paths, permitted effects, relevant evidence, pinned model/thinking, remaining repair
allowance and expected canonical report/handoff. Pass the original attempt so each child explicitly registers
its live consumer through the shared resource procedure before using the runtime. Do not copy Engineer dialogue into independent requests or
suggest verdicts. Tell each child to verify target identity, read instructions, perform only its role and stop.
No `--continue`, `--resume`, `--session`, `--fork`, parent transcript or reused session directory.

```sh
cd "$task_checkout"
python3 -u "$build_skill_dir/scripts/run-stage.py" \
  --attempt "$attempt" --task "$task_id" --role "$role" \
  --provider "$stage_provider" --model "$stage_model" --thinking "$stage_thinking" \
  --timeout "$stage_timeout_seconds"
```

Use actual pinned variables and a finite stage timeout; shell timeout exceeds it plus termination grace. Keep
stdout visible, relay meaningful progress at least every 60 seconds, inspect bounded `progress.log` if buffered.
Do not detach, pipeline away exits, infer percentages or echo private source/thinking/credentials. Observe the
original child through exit. Inspect monitor/Pi exits, provider errors, session model/thinking and reports;
exit zero is not acceptance. Timeout/cancel/uncertain liveness needs reconciliation before another child.
Never arbitrarily signal a reused/unknown PID or assume remote effects stopped with the CLI.

## Advance by evidence

- Engineer: committed implementation and truthful gate/resource handoff → fresh independent Review.
- Review: current canonical ACCEPT with Standards/Spec passes → applicable independent QA. Findings → bounded
  Engineer repair; evidence-only gaps → their owner, not speculative source edits.
- QA: current canonical PASS or justified criterion-specific N/A → authorized Land. FAIL → Engineer;
  INCOMPLETE → exact prerequisite. No UI does not mean no QA. For UI/TUI carry the owning capture procedure,
  terminal authority and bridge prerequisites; inspect comparable real captures, never fabricate missing images.
- Land: verify actual exact ready PR, required checks/capture access/current closeout, then eligible cleanup.

Match TASK/branch/base/head/status, independence, findings and limitations before advancement. Never select an
older PASS over newer failure. [Qualify gate reuse](../../../docs/engineering/EXECUTION.md#gate-evidence-reuse):
one final full gate for changed implementation, independent focused probes, no repeat for role/commit/metadata.

Default **five shared repair cycles** across Review and QA; explicit positive allowance may be up to ten.
Consume before an Engineer repair; initial implementation is not a repair. New processes/QA failures/resumed
invocations never reset it. After the last cycle allow its Review/QA/Land if passing; remaining blockers need a
human decision. Evidence recovery consumes no repair, but unchanged holds are not unlimited retries.

## Finish the effects

Inspect native Git/Docker absence and protected/shared state, not a cleanup invocation or passing mechanism.
Record necessary outcomes in the existing TASK/PR, then retire eligible temporary runtimes, worktrees/refs,
fixtures/logs/sessions and cleanup's own files through the shared procedure. Do not retain them automatically.
If original Land itself was the consumer, observe both original exits/PID absence and continue from canonical
safe checkout using `scripts/cleanup-continuation.py --attempt ORIGINAL --receipt SAME --apply --finish`.
Use a fresh cleanup-only Land worker when required, same pinned model/ledger, with `--cleanup-receipt` at stage
entry. No publication, new runtime or tracked edits in that continuation. Preserve active coordinator and real
holds; never stop shared services/VMs or bypass protections. Independent effects proceed beside narrow holds.

Return actual PR/publication state, fresh/reused verification, warnings, removed/remaining counts, finite holds
and shared repair usage. Follow [handoff rules](../../../docs/engineering/HANDOFFS.md); success ends with the
actual human PR review/merge URL. Incomplete work names its real prerequisite. Stop after this TASK, not at a
child's next-skill recommendation when Build already authorized and evidenced the next stage.
