---
name: wayfinder
description: Use for uncertain work that is too foggy or large to decompose directly. Maintain a Markdown wayfinder map and decision tickets until the path to an EPIC, TICKET, or implementation handoff is clear.
---

# Wayfinder

Wayfinder maps uncertainty. It does not implement the destination. It records decisions and research paths until the next planning handoff is obvious.

## Repository model

- Maps live under `planning/wayfinder/` according to `planning/CONVENTIONS.md`.
- Decision tickets use `WF-NNN-description.md` identities and are distinct from requirements TICKETs.
- Research belongs under `planning/wayfinder/research/` unless an existing map says otherwise.
- Markdown remains authoritative until an explicit database migration is approved and verified.

## Modes

### Chart a map

Use when the user brings a large, unclear idea.

1. Establish the destination and done condition.
2. Separate decided facts, open decisions, remaining fog, and explicit exclusions.
3. Create the map from `_MAP_TEMPLATE.md`.
4. Create only decision tickets that are sharp enough to state now.
5. Record blockers between decision tickets.
6. Leave vague downstream areas in remaining fog, not as fake tickets.

### Work a map

Use when a map already exists.

1. Load the map, generated decision table, and only the decision records needed for the current frontier.
2. Pick one unblocked open decision unless the user names another.
3. Resolve it with the appropriate mode: grill, research, prototype, or manual task. A grill-mode decision is an
   interview inside Wayfinder's resolution boundary; it does not automatically invoke the EPIC-producing
   [grill skill](../grill/SKILL.md). Recommend that skill when a bounded EPIC handoff is ready.
4. Update the decision ticket and map summary.
5. Graduate newly clear fog into decision tickets, or rule it out of scope.
6. Stop after one decision unless the user explicitly asks for another.

## Guardrails

- Decision tickets answer questions; they are not implementation TASKs.
- Refer to decisions by linked title and WF ID together when possible.
- Preserve concurrent work by checking current files before editing.
- Use `.runs/` only for scratch notes and handoffs.
- Run `./bin/planning-check --write` and `./bin/planning-check` after durable map edits.

## Handoff

Finish with a concrete `Next:` command under the [handoff rules](../../../docs/engineering/HANDOFFS.md).
While decisions remain, use the authored unblocked frontier. Follow [map-wide planning order](../../../planning/CONVENTIONS.md#map-wide-planning-order):
complete the selected map's decisions/grills and approved EPIC handoffs before recommending TICKET decomposition,
then complete its TICKET phase before TASK decomposition. Respect an explicit narrower human choice.
Link resulting EPICs and remaining approved destinations in the authorized map/handoff; do not infer completion
from one written EPIC or reopen a Closed map solely because its linked EPIC handoff is unfinished.
