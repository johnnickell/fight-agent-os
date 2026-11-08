---
name: sync
description: Integrate current origin/develop into one independently accepted TASK and automatically refresh its existing open PR through fresh Engineer and Land roles.
disable-model-invocation: true
---

# Sync

Act as local coordinator, never implementer or independent acceptor. This is accepted-work integration, not
Build's new-implementation sequence. Read [project context](../../../docs/engineering/PROJECT_CONTEXT.md), the
target instructions, complete TASK/accepted parents, canonical Review/QA and
[advancing-base policy](../../../docs/engineering/LANDING.md#reconcile-an-advancing-base).
Follow the [native workflow rule](../../../harness/pi/WORKFLOW_RULE.md).

## Admit one accepted subject

`/skill:sync TASK-NNNNN [--checkout /absolute/selected/checkout]` selects exactly one TASK. No TICKET expansion,
new PR, approval, PR merge, rebase, force-push, release, deployment or other-TASK sweep. Explicit operator invocation
authorizes bounded integration/commit/checks, automatic Land, non-force update of the same PR and ordinary
eligible cleanup, subject to narrower instructions. An explicit checkout must already be registered. Omit
`--checkout` to let Sync select or recreate an isolated worktree and continue the same run.

1. Confirm native selected workflow is `sync`, effect `coordination`, the same TASK and default mode.
   A concrete checkout binds the run to that path. A null checkout selects isolated-worktree resolution
   within the invoking session's repository. Keep the native selection intact and pass the resolved checkout
   to the process stages.
   Its workflow phase is `qa`, not a QA worker. Only operator selection supplies coordinator authority; neither
   a request, RPC/extension input, stage label nor an Engineer child can select Sync by prompt. If the active
   Harness lacks Sync admission, hold for the operator's reviewed-package adoption and explicit native invocation;
   do not fabricate Build authority or mutate settings/install/trust. Use the supported status surface
   (`fight_workflow_status.workflow` or `/fight-workflow`). Native file/shell availability is independent of
   this responsibility binding; no evaluator enrollment or credential is required.
2. Verify the invoking repository's origin, Git common directory and canonical base checkout.
   Read the existing task-owned run ledger and Work/Land handoffs there before selecting effects. Do not assume a
   completed TASK or a PR URL proves current acceptance or hosting state.
3. Bind the exact existing PR using Land's [target binder](../../../docs/engineering/LANDING.md#bind-the-publication-target)
   from the canonical checkout with actual origin, accepted base policy, PR URL and feature branch. Require
   OPEN, correct TASK/title, repository/base/head identity and exact remote OID. No silent retargeting or
   replacement of an ended PR. Fetch the exact bound head if its commit is missing locally and verify the
   fetched OID against the live PR before inspecting acceptance. Leave local feature branches untouched.
4. Require canonical independent Review ACCEPT and applicable QA PASS or criterion-specific justified N/A.
   Match TASK/branch/reviewed base/head, independence, coverage, findings and limitations. Inspect intervening
   commits and prior integration/administrative bridges against their actual subjects. A newer failure,
   incomplete required scenario, missing evidence or unreviewed intervening behavior blocks the fast path;
   never select an older pass. Preserve original report bytes and provenance, including reports in a different
   canonical base checkout. Record their original identities in the existing ledger; do not rewrite them.

## Resolve and prepare the checkout

Check the existing Build/Sync run for active consumers and acquire the shared subject lock before creating
or preparing a checkout. Validate the selected checkout against the bound Git common directory, PR head
branch, clean status and current OIDs. Preserve unrelated changes. Local history must contain the published
head. Unexplained local commits, divergence or an unsafe checkout hold integration.

- With an explicit path, use only that checkout after validation.
- Otherwise reuse the registered isolated checkout for the bound PR branch if it qualifies. Prefer the
  previous Build checkout when its ledger and current registration agree. If Land removed it, recreate
  an isolated checkout on the same PR branch, using the prior task-owned location if free and consistent
  with the target's current worktree convention. Use the target's native Git worktree workflow. If the local
  branch is missing, create the same feature branch at the verified published OID. If the local branch
  exists, verify it and preserve its ref. Hold if the branch is checked out in main, the intended path is
  occupied, or ownership is unclear. Never reset a ref
  or switch main to the feature branch to recover a checkout.

Verify the resulting Git registration, branch, clean status and head before dispatch. Record the actual
checkout and ownership in the existing ledger. Follow the target's runtime preparation and guarded cleanup
procedures for that checkout. Preserve the original Build ledger and independent reports.

## Delegate integration, then delivery

Reuse [Build's model pinning rules](../build/SKILL.md#pin-models-then-launch-fresh-roles) and the
[process protocol below](#process-stage-protocol) for both roles. The SDK stage tool is Build-only, not a Sync
route or fallback. Do not create another launcher or execution engine. Pin the same role model defaults and
preserve them on continuation. Observation remains human-only; visibility loss never authorizes a second worker.

Use the SAME canonical `.runs/build/<TASK-ID>/active` exclusive lock and private
`.runs/build/<TASK-ID>/<run>/` ledger/context convention as Build, recording workflow `sync`. Check current
ownership before acquiring/resuming. Keep task execution scratch under `.runs/tasks/<TASK-ID>/runs/`.

1. Launch one fresh `--role engineer` owning [Work](../work/SKILL.md), with the absolute
   [integration assignment](references/integration.md), resolved context and intake evidence. The assignment
   permits only accepted-work integration, not repairs/new behavior. Pass original acceptance/report locations,
   PR identity/published OID, branch/old base/head, gate/runtime facts and expected integration handoff.
   Do not copy private worker dialogue into another request.
2. Observe the original child through actual exit. Inspect monitor/Pi exits, actual model/thinking, clean
   committed candidate, original reports, integration decisions, focused results, normal gate's original receipt
   and warnings. Exit zero is not success. Stop on incompatible intent, discovered defect, new behavior or failed/
   unavailable required verification. Name the concrete decision/repair/evidence owner; do not launch Review/QA
   merely for code overlap or automatically repair a defect. New implementation retains normal independent acceptance.
3. On successful integration and verification, automatically launch a fresh `--role land` owning
   [Land](../land/SKILL.md), with the same context, original reports, exact existing PR and integration handoff.
   No second manual invocation/approval prompt and no routine Review/QA child. Land validates the integration-only
   scope/evidence under its normal policy, not another full semantic review. It updates only that existing OPEN PR
   by non-force push, verifies actual base/head/remote OID and required hosted/final-head evidence, applies relevant
   current-record closeout and ordinary eligible cleanup. Missing or changed identity holds the affected effect.
4. Inspect actual publication and cleanup postconditions. Reuse the target's
   [resource procedure](../../../docs/engineering/RESOURCE_LIFECYCLE.md), protecting main data, foreign/shared
   services, unique source and active consumers. If Land's own cwd holds source, observe its exit and continue
   qualified cleanup from the safe canonical checkout, using the existing fresh cleanup-only Land route if needed.
   Never confuse protected source with permission to suppress independent Docker teardown.

## Process stage protocol

This retained route serves explicit Sync's accepted-work Engineer/Land dispatch, including cleanup-only Land.
Resolve the absolute provider launcher [Build `scripts/run-stage.py`](../build/scripts/run-stage.py) from this
skill's location, never from an unrelated target. Its directory name does not make it a current Build caller.
The launcher uses the running Pi's exported native resource resolver offline to inspect configured packages and
direct/auto-discovered extensions. One complete Harness selects its canonical package directory; with none
configured, it selects the trusted provider checkout's `harness/pi`. Multiple owners, filtered/disabled/incomplete
resources, unavailable inventory or monitor support hold before dispatch. Project configuration requires native
project trust; inspection never grants it. Offline registration checks the selected package's actual injection;
diagnostics hold even with Pi exit zero. Inspect settings and name the prerequisite; never change installation,
settings/trust or discovery to bypass it. An explicit `--harness-package` must match any configured Harness.
These checks apply to every fresh/resumed/cleanup-only stage. No evaluator or screening preflight is required.

Create a fresh private attempt containing `request.md` with the exact role/TASK/checkout/base/head, absolute
owning skill/context/instruction/evidence paths, accepted integration/publication scope, target-owned gate/runtime
rules, original model/thinking pins and expected canonical handoff. Carry the same Compose project and native
state. Read the request back under the shared handoff readability rules. No Engineer dialogue, parent transcript,
`--continue`, `--resume`, `--session`, `--fork` or reused session directory. Use canonical `--role engineer` or
`land`; the shared Harness role vocabulary also normalizes existing `work` aliases without granting authority.

```sh
cd "$task_checkout"
python3 -u "$provider_stage_launcher" \
  --attempt "$attempt" --task "$task_id" --role "$role" \
  --provider "$stage_provider" --model "$stage_model" --thinking "$stage_thinking" \
  --timeout "$stage_timeout_seconds"
```

Set `provider_stage_launcher` to that verified absolute provider path, use actual pinned variables and a finite
stage timeout; shell timeout exceeds it plus termination grace. Keep stdout visible and relay meaningful progress
at least every 60 seconds; inspect bounded `progress.log` if buffered. Do not detach, pipeline away exits, infer
percentages or echo private source/thinking/credentials. Observe the original child through actual exit; inspect
monitor/Pi exits, provider errors, model/thinking and handoff/report evidence. Zero exit is not success or acceptance.
Timeout/cancel/uncertain liveness holds replacement until original execution and external effects are reconciled.
Never signal a reused/unknown PID or assume remote effects stopped with the CLI. Native session-file birthtime,
ancestry/custody and independent role admission remain required, separately from passive best-effort observation.

## Reconcile and finish

After interruption, timeout or partial publication inspect the original attempt's actual process/session,
Git/merge/commit, exact remote/PR and native resource state BEFORE another launch. Uncertain liveness holds
redispatch. Resume only missing effects: an already integrated verified candidate needs Land, not another merge;
an updated PR with a cleanup hold needs cleanup-only, not another publication. A failed merge may remain
uncommitted; record its exact state and owner, never discard source automatically. An ended PR routes to its
existing Land recovery/maintainer decision, never replacement. Retain needed evidence until its consumers finish.

Record a readable handoff under [handoff rules](../../../docs/engineering/HANDOFFS.md): actual branch, old/new
base/head, preserved report identities, role attempts, concise resolutions, fresh/reused gate receipt provenance,
warnings/incomplete checks, exact PR/publication state and removed/remaining resource counts with finite holds.
Retire eligible owned scratch only after durable TASK/PR outcomes and child exit; release only your own lock.
Success ends `Next:` with the actual human PR review/merge URL. Otherwise name the exact remaining owner/action.
Stop after this subject. Do not claim independent acceptance, publication or cleanup from walkthroughs or simulations.
