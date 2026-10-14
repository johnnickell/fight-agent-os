---
name: grill
description: Use for a live decision interview that turns an idea into one approved EPIC only. Ask dependency-ordered questions with recommendations, inspect facts yourself, and stop before TICKET or TASK decomposition.
---

# Grill

Grill is the human-in-the-loop interview that settles the smallest useful destination before planning proceeds.
In this repository, this skill writes one approved EPIC and nothing below it.

## Input

- `/skill:grill <idea>` interviews one bounded EPIC destination.
- `/skill:grill <map-path> "<destination title>"` consumes that map's named EPIC handoff. Reuse settled decisions;
  ask only unresolved product choices. Resolve the real path and approved destination before proceeding.
- If the supplied target is only an unresolved Wayfinder decision whose boundary excludes EPIC creation, route
  it to `/skill:wayfinder work WF-NNN` using its real ID. Wayfinder owns that decision interview; do not create an
  EPIC or repeat settled decisions just because the record's mode says grill.

## Operating rules

- Read `README.md`, `ARCHITECTURE.md`, `planning/CONVENTIONS.md`, `planning/FOUNDATION.md`, and `planning/tasks/BOARD.md` before writing durable planning.
- Treat factual discovery as agent work. Inspect files, commands, and references yourself instead of asking the user for facts you can verify.
- Treat product, priority, risk, and boundary choices as human decisions. Recommend an answer, but wait for the user.
- Ask questions in rounds. Each round contains only the current decision frontier: questions whose prerequisites are already settled.
- Do not ask a question whose answer depends on another unsettled question in the same round.
- Keep the interview focused on the smallest useful EPIC destination. Push implementation slicing to `to-tickets` and `to-tasks`.

## Round format

Use numbered questions. Include a short recommendation for each.

```markdown
❓ **Q1 - Decision title**: Decision question and options.

➡️ Recommended answer: Your recommendation and why.

---
```

After the user answers, summarize settled decisions, recompute the frontier, and ask the next round.

## Completion

When no interview frontier remains:

1. Summarize the destination, boundaries, exclusions, and follow-up decisions.
2. Ask the user to confirm shared understanding.
3. After confirmation, create or update exactly one `planning/epics/NNNNN-EPIC.md` record.
4. Run `./bin/planning-check --write` and `./bin/planning-check`.

Do not create TICKETs, TASKs, implementation branches, prototypes, or database records from grill alone.

Finish using the [handoff rules](../../../docs/engineering/HANDOFFS.md) with an explicit `Next:` command or pending
human action. Follow [map-wide planning order](../../../planning/CONVENTIONS.md#map-wide-planning-order): after
one EPIC, favor the map's remaining grills/EPICs before `to-tickets`, and all TICKET decompositions before TASKs.
One invocation still handles one approved EPIC; a recommendation does not start the next one.
