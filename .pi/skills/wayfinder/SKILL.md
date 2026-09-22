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
3. Resolve it with the appropriate mode: grill, research, prototype, or manual task.
4. Update the decision ticket and map summary.
5. Graduate newly clear fog into decision tickets, or rule it out of scope.
6. Stop after one decision unless the user explicitly asks for another.

## Guardrails

- Decision tickets answer questions; they are not implementation TASKs.
- Refer to decisions by linked title and WF ID together when possible.
- Preserve concurrent work by checking current files before editing.
- Use `.runs/` only for scratch notes and handoffs.
- Run `./bin/planning-check --write` and `./bin/planning-check` after durable map edits.
