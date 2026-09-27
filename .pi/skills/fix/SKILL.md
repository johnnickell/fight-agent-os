---
name: fix
description: Diagnose a reported bug and prepare a bounded standalone bug TASK for human acceptance; hand regression-first repair to work.
---

# Fix

This skill owns diagnosis and tracking handoff. Resolve the selected repository/checkout, reported symptom,
expected contract, environment and impact. Inspect current source, applicable rules and relevant evidence before
naming a cause. Search existing live and archived TASKs and known issue/PR ownership; reuse a matching owner
rather than creating a second repair. Do not infer hosted access or publication authority from diagnosis.

Reproduce with a safe, scoped, non-destructive diagnostic when feasible. Record exact inputs, expected versus
observed results, revision and environment. Distinguish product behavior from environment failure and a suspected
cause from a demonstrated one. If evidence is insufficient or reproduction would need unsafe effects, state the
obstacle and request only the missing evidence/authority; do not manufacture a failed test or widen the scope.
Avoid exposing credentials or private request content.

Prepare a human-reviewable draft using the [TASK template](../../../planning/tasks/_TASK_TEMPLATE.md) and
[planning conventions](../../../planning/CONVENTIONS.md): standalone `kind: bug`, empty `ticket`, bounded outcome,
reproduction or obstacle, affected behavior/ownership, in/out scope, commands/queries/events/effects (or justified
N/A), validation/permissions, rejection and failure cases, observable acceptance and verification. Include known
dependencies/priority and the smallest failing regression plan for the eventual repair. Do not assign an ID until
record creation is authorized and both live/archive allocation have been checked. Do not create a parent TICKET
merely to track a small bug.

Present the draft in the response or an authorized ignored `.runs/` handoff. Drafting is not acceptance. If the
maintainer already authorized the concrete scope and record creation, preserve that decision; otherwise obtain
acceptance before writing a live record. Recheck ownership and ID availability immediately before creation,
then refresh and validate generated planning views. A rejected draft creates no record. An already-fixed report
returns the counterevidence and existing owner; unavailable reproduction remains an explicit gap, not a green check.

Finish with diagnosis confidence/limits, proposed or existing TASK, actual acceptance state and next action.
[Work](../work/SKILL.md) owns implementation after scope acceptance and the required checkout choice; its bug path
reproduces the failure and adds a failing regression before repair. Preserve choices already made. This skill does
not write product/test code, silently start repair, commit or publish. When the user also authorized repair, make
the explicit handoff to `work` rather than treating diagnosis as authority for those effects.

The [source comparison](../../../docs/engineering/SKILL_GAPS.md) records why the combined repair/publication workflow
was narrowed for Agent OS.
