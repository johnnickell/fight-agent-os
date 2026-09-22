---
name: grill
description: Use for a live decision interview that turns an idea into one approved EPIC only. Ask dependency-ordered questions with recommendations, inspect facts yourself, and stop before TICKET or TASK decomposition.
---

# Grill

Grill is the human-in-the-loop interview that settles the smallest useful destination before planning proceeds. In this repository, grill writes an EPIC and nothing below it.

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

When no frontier remains:

1. Summarize the destination, boundaries, exclusions, and follow-up decisions.
2. Ask the user to confirm shared understanding.
3. After confirmation, create or update exactly one `planning/epics/NNNNN-EPIC.md` record.
4. Run `./bin/planning-check --write` and `./bin/planning-check`.

Do not create TICKETs, TASKs, implementation branches, prototypes, or database records from grill alone.
