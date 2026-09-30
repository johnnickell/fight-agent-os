---
name: to-tasks
description: Use after a TICKET is accepted to decompose it into executable TASK records, normally one independently reviewable PR each.
---

# To Tasks

This skill turns a TICKET into dependency-ordered implementation TASKs. A TASK is bounded executable work, usually one PR. SUBTASK coordination stays under ignored `.runs/` folders during implementation.

## Process

1. Read the TICKET, parent EPIC, relevant ADRs, and current Board.
   If part of a Wayfinder handoff, inspect the map's remaining phases under [map-wide planning order](../../../planning/CONVENTIONS.md#map-wide-planning-order).
   Favor accepted TICKET decompositions for all its EPICs before TASK decomposition. A direct request for this
   TICKET is an explicit narrower choice; preserve it and record any remaining map work without reconfirming it.
2. Inspect live and archived TASKs before allocating IDs.
3. Draft vertical implementation slices:
   - Each TASK delivers an independently reviewable outcome.
   - Prefer complete paths through Domain, Application, Adapter, and UI over layer-only slices.
   - Use explicit blockers for true dependencies only.
   - Keep prefactors small and first when they make the change safe.
4. Present the draft to the user with title, blocker list, delivered outcome, and acceptance evidence.
5. After approval, create TASK records from `_TASK_TEMPLATE.md` with correct `ticket: TICKET-NNNNN`, `kind`, `status`, `order`, and `blocked_by` metadata.
6. Run `./bin/planning-check --write` and `./bin/planning-check`.

## Guardrails

- Do not start implementation from this skill unless the user separately authorizes execution.
- Keep worktree choice explicit before implementation.
- TASK completion requires recorded verification and evidence, not just code changes.
- Bugs need a reproducing regression test before repair unless the TASK justifies why that is impossible.

Finish with an explicit `Next:` under the [handoff rules](../../../docs/engineering/HANDOFFS.md). Continue with
`to-tasks` for remaining accepted TICKETs in the selected map once earlier phases are complete; an explicit slice
does not silently mark the rest of the map complete. When all planning phases are complete, recommend
`/skill:next` for Board-based execution selection. Never start implementation as a decomposition side effect.
