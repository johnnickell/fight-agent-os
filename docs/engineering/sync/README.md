# Local Sync role flow

## Current PHP process flow

**Existing implementation** at `909d9e7bb56663a3555d11f47ee594a3fe76b6d7`. This view covers Sync's process stage
only. Fresh Build dispatches through the SDK's `fight_build_stage` and does not use this launcher.

![Sync PHP monitor, child and discovery flow](php-stage-flow.png)

[Editable Python](php-stage-flow.py). Render with `./bin/diagrams render docs/engineering/sync/php-stage-flow.py`.
Use `git show 909d9e7bb56663a3555d11f47ee594a3fe76b6d7:PATH` for the traced source:

| Flow | Source and meaning |
|---|---|
| 1. Select package | [Provider launcher](../../../.pi/skills/build/scripts/run-stage.php) uses native inventory and registration, then execs the single selected monitor. A selection or admission failure does not choose a fallback. |
| 2–3. Start or cancel | [`StageChild` and `runStage`](../../../harness/pi/src/observation/run-stage.php) start a bootstrap that makes its own session and waits on a pipe. The monitor releases it only after checking group ownership; cancellation before release closes the pipe without authorizing Pi exec. Later group signals require a live leader and a matching group. |
| 4. Collect evidence | [`runStage`](../../../harness/pi/src/observation/run-stage.php) reads child events and stderr into private files, and records separate child and monitor exits. |
| Observe only | [`PassiveStageObservation`](../../../harness/pi/src/observation/stage-observation.php) forwards bounded progress to the [discovery helper](../../../harness/pi/src/observation/stage-discovery.php). The [native custodian](../../../harness/pi/src/observation/native-observation.mjs) owns admission; visibility loss does not control stage execution. |

The optional legacy observation socket and detailed custody checks are omitted. This is a source trace, not
proof that a live stage ran or that the candidate was accepted.

## Historical instruction and admission flow

**Existing instruction/admission flow at the historical revision** at reconciled source
`bc257339c132f1b5ee9735048eb82fe44dffe244`. This view traces the native workflow owner and reuse of existing
process dispatch. The following diagram/documentation changes do not alter those represented sources.

![Local Sync instruction and role flow](sync-flow.png)

[Editable Python](sync-flow.py). Render with `./bin/diagrams render docs/engineering/sync/sync-flow.py`.
Dotted arrows are specified ordered interactions, not static dependencies or runtime proof. Arrow 4–5 compresses
the return to the SAME Sync coordinator and its next dispatch; Engineer never launches Land. Engineer and Land
are separate fresh contexts. Sync retains coordination and never supplies independent acceptance or source repair.

## Source and arrow trace

All source rows refer to the full implementation revision above; retrieve with `git show REVISION:PATH`.
The current launcher and monitor links point to the PHP replacements from TASK-00617; the historical image
keeps the original filename.

| Arrow | Source at that revision | Meaning and evidence limit |
|---|---|---|
| 1 | [`WorkflowAccess.input`](../../../harness/pi/src/workflow/access.mjs) and [native extension](../../../harness/pi/src/workflow-extension.ts) | Explicit interactive `sync` selects one TASK, coordination/QA phase and checkout; RPC/extension text cannot select it. Native status is `fight_workflow_status` / `/fight-workflow`, not a file-tool permission gate. Direct SDK checks use simulated input, not operator authority. |
| 2 | [Sync intake](../../../.pi/skills/sync/SKILL.md#admit-one-accepted-subject) | Coordinator checks actual acceptance applicability and exact existing OPEN PR; missing/contradictory evidence holds effects. |
| 3 | [Sync delegation](../../../.pi/skills/sync/SKILL.md#delegate-integration-then-delivery), [provider dispatcher](../../../.pi/skills/build/scripts/run-stage.php) and [owning process monitor](../../../harness/pi/src/observation/run-stage.php) | Fresh Engineer uses existing native package/trust/role/custody machinery with `FIGHT_WORKFLOW_PHASE/TASK/CHECKOUT`. No evaluator preflight, parallel launcher or Build-only SDK substitution. |
| 4 | [Work assignment](../../../.pi/skills/sync/references/integration.md) | Preserve both intents and original reports; commit integration and run normal gate. Concrete incompatible intent, defects, new behavior or failed verification stop advancement. |
| 5 | [Sync progression](../../../.pi/skills/sync/SKILL.md#delegate-integration-then-delivery), [Land](../../../.pi/skills/land/SKILL.md) | Verified integration automatically delegates fresh Land through the SAME dispatcher; no routine Review/QA. This does not remove independent acceptance of Sync's new implementation. |
| 6 | [Advancing-base policy](../LANDING.md#reconcile-an-advancing-base), [delivery verification](../LANDING.md#verify-and-publish) and [resources](../RESOURCE_LIFECYCLE.md) | Land checks scope/evidence, updates only the same PR, verifies exact published head and required evidence, and retires eligible resources. Publication, cleanup holds and human merge remain distinct. |

## Evidence boundary and omissions

[TASK-00587's owning evidence checkpoint](../../../planning/tasks/00587-TASK.md#native-journey-evidence-and-upstream-reconciliation--2026-11-07)
records a genuine operator-selected journey on the original candidate: fresh Engineer, automatic fresh Land,
unchanged original reports, actual integration/gates/same-PR update, source-consumer hold beside successful Docker
teardown and later cleanup-only completion. That input PR has ended merged; it is not another runnable demonstration.
The historical journey predates the native-owner migration. Direct admission/restore/package checks qualify the
reconciled owner, but no second genuine journey or independent acceptance of this reconciled candidate is claimed.
Original source/diagram revision `ff86a39d83c094d6ec501eac0ebd6dddd41d640c` remains retrievable through Git history.

The view omits internal custody, timeout/observation internals, model selection, conflict hunks, application
services and detailed resource guards. The [owning skill](../../../.pi/skills/sync/SKILL.md) and shared standards
retain those contracts. This is trusted local guidance, not a server grant or OS sandbox. Sync itself still needs
normal independent Review and applicable QA; accepted inputs reuse original reports only within the approved
integration-only boundary. The picture is a source trace, not proof of runtime effects or acceptance.
