# Local Sync role flow

**Existing instruction/admission flow** at implementation source
`ff86a39d83c094d6ec501eac0ebd6dddd41d640c`. This view explains the new coordinator's ownership and reuse of
existing dispatch, not a successful native Sync/role/PR observation. The later diagram/documentation commit does
not change the represented admission, role-assignment or dispatch sources.

![Local Sync instruction and role flow](sync-flow.png)

[Editable Python](sync-flow.py). Render with `./bin/diagrams render docs/engineering/sync/sync-flow.py`.
Dotted arrows are specified ordered interactions, not static dependencies or runtime proof. Arrow 4–5 compresses
the return to the SAME Sync coordinator and its next dispatch; Engineer never launches Land. Engineer and Land
are separate fresh contexts. Sync retains coordination and never supplies independent acceptance or source repair.

## Source and arrow trace

All source rows refer to the full implementation revision above; retrieve with `git show REVISION:PATH`.

| Arrow | Source at that revision | Meaning and evidence limit |
|---|---|---|
| 1 | [`WorkflowAccess.input`](../../../harness/pi/src/jev/workflow-access.mjs) | Explicit interactive `sync` selects one TASK, coordination/QA phase and checkout; RPC/extension text cannot select it. Parser qualification is not native operator execution. |
| 2 | [Sync intake](../../../.pi/skills/sync/SKILL.md#admit-one-accepted-subject) | Coordinator checks actual acceptance applicability and exact existing OPEN PR; missing/contradictory evidence holds effects. No actual input PR was used in Work qualification. |
| 3 | [Sync delegation](../../../.pi/skills/sync/SKILL.md#delegate-integration-then-delivery), [provider dispatcher](../../../.pi/skills/build/scripts/run-stage.py) and [owning process monitor](../../../harness/pi/src/observation/run-stage.py) | Fresh Engineer uses existing qualified native package/trust/role process path. No parallel launcher and no Build-only SDK substitution. Real Sync child custody remains unqualified in this Work run. |
| 4 | [Work assignment](../../../.pi/skills/sync/references/integration.md) | Preserve both intents and original reports; commit integration and run normal gate. Concrete incompatible intent, defects, new behavior or failed verification stop advancement. Local Git/planning effects were exercised separately, not through a real Sync child. |
| 5 | [Sync progression](../../../.pi/skills/sync/SKILL.md#delegate-integration-then-delivery), [Land](../../../.pi/skills/land/SKILL.md) | Verified integration automatically delegates fresh Land through the SAME dispatcher; no routine Review/QA. Actual automatic progression is unverified. |
| 6 | [Advancing-base policy](../LANDING.md#reconcile-an-advancing-base), [delivery verification](../LANDING.md#verify-and-publish) and [resources](../RESOURCE_LIFECYCLE.md) | Land validates scope/evidence, updates only the same PR, verifies exact published head and required evidence, and retires eligible resources. Publication, cleanup holds and human merge remain distinct; no actual PR effect was performed by Work. |

## Scope and limitations

The diagram omits internal custody protocol, timeout/observation internals, model selection, Git conflict hunks,
application services and detailed resource guards. The [owning skill](../../../.pi/skills/sync/SKILL.md) and shared
standards retain those contracts. This is local trusted workflow guidance, not a server grant or an OS sandbox.
The new Sync implementation itself requires normal independent Review and applicable QA. Accepted input TASKs
can reuse their original independent reports only within the approved integration-only boundary.
