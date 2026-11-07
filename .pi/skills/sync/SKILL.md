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
new PR, approval, merge, rebase, force-push, release, deployment or other-TASK sweep. Explicit operator invocation
authorizes bounded integration/commit/checks, automatic Land, non-force update of the same PR and ordinary
eligible cleanup, subject to narrower instructions. Reuse the user's checkout choice; ask main versus worktree
only when missing. A workflow selection is not installation, project trust or OS isolation.

1. Confirm native selected workflow is `sync`, effect `coordination`, same TASK/checkout and default mode.
   Its workflow phase is `qa`, not a QA worker. Only operator selection supplies coordinator authority; neither
   a request, RPC/extension input, stage label nor an Engineer child can select Sync by prompt. If the active
   Harness lacks Sync admission, hold for the operator's reviewed-package adoption and explicit native invocation;
   do not fabricate Build authority or mutate settings/install/trust. Use the supported status surface
   (`fight_workflow_status.workflow` or `/fight-workflow`). Native file/shell availability is independent of
   this responsibility binding; no evaluator enrollment or credential is required.
2. Verify cwd/origin, Git common-directory/canonical base, registered selected checkout, feature branch, clean
   status and current OIDs. Preserve unrelated changes; an unsafe/dirty selected subject holds integration.
   Read the existing task-owned run ledger and Work/Land handoffs before selecting effects. Do not assume a
   completed TASK or a PR URL proves current acceptance or hosting state.
3. Bind the exact existing PR using Land's [target binder](../../../docs/engineering/LANDING.md#bind-the-publication-target)
   with actual origin, accepted base policy, PR URL and feature branch. Require OPEN, correct TASK/title,
   repository/base/head identity and exact remote OID. Current local history must contain published history;
   unexplained remote/local divergence holds integration. No silent retargeting or replacement of an ended PR.
4. Require canonical independent Review ACCEPT and applicable QA PASS or criterion-specific justified N/A.
   Match TASK/branch/reviewed base/head, independence, coverage, findings and limitations. Inspect intervening
   commits and prior integration/administrative bridges against their actual subjects. A newer failure,
   incomplete required scenario, missing evidence or unreviewed intervening behavior blocks the fast path;
   never select an older pass. Preserve original report bytes and provenance, including reports in a different
   canonical base checkout. Record their original identities in the existing ledger; do not rewrite them.

## Delegate integration, then delivery

Reuse [Build's model pinning and process launch protocol](../build/SKILL.md#pin-models-then-launch-fresh-roles)
for capability checks, absolute provider launcher, fresh private attempts/requests, native package/trust/custody,
visible progress, finite timeouts, observation and exit reconciliation. Use the default process route for both
roles, not the opt-in Build-only SDK Engineer tool. Do not create another launcher or execution engine.
Pin the same role model defaults and preserve them on continuation. Observation remains human-only;
visibility loss never authorizes a second worker.

Use the SAME canonical `.runs/build/<TASK-ID>/active` exclusive lock and private
`.runs/build/<TASK-ID>/<run>/` ledger/context convention as Build, recording workflow `sync`. Check current
ownership before acquiring/resuming; no concurrent Build/Sync on the subject, resetting repair history or
new recovery framework. Keep task execution scratch under `.runs/tasks/<TASK-ID>/runs/`.

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
