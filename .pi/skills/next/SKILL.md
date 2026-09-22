---
name: next
description: Report the next executable TASK and available Wayfinder frontier, with the skill commands to run.
disable-model-invocation: true
---

# Next

Route the human to current work. This is a read-only recommendation: inspect, report, and stop before invoking
another skill or changing planning.

## 1. Validate the planning view

1. Read `planning/CONVENTIONS.md` as the authority for status, priority, blocking, and Wayfinder frontier semantics.
2. Run `./bin/planning-check` without `--write`.
3. If validation fails, report that planning is stale or invalid, include the failing command result, and stop without
   recommending work.

Validation is complete only when the generated Board and Wayfinder indexes are current.

## 2. Select TASK work

Read `planning/tasks/BOARD.md` and the complete record for every TASK considered here.

- When **Active Work** has entries, return the active TASK or TASKs. Continue them before recommending Ready
  Frontier work.
- Otherwise, return the first row of **Ready Frontier**. Its generated order is authoritative; never substitute a
  lower TASK ID.
- When neither section has an entry, report that no TASK is currently executable.
- Report relevant **Needs Info** or **Human Action** entries as human input, separate from the TASK recommendation.

Before describing the command, read `.pi/skills/work/SKILL.md`. For one selected TASK, provide:

```text
TASK: TASK-NNNNN — Title
Why: Active work to continue | First executable Ready Frontier entry
Run: /skill:work TASK-NNNNN
```

If multiple TASKs are active, list all of them and state that the Board provides no instruction to start another
TASK. Do not invent a winner.

TASK selection is complete when every returned ID, title, status, blocker state, and reason agrees with the Board
and owning record.

## 3. Select Wayfinder decisions

Read `planning/wayfinder/README.md`, then every live map listed there. Closed maps supply a no-work result only;
inspect every map marked **Active**.

For each active map:

1. Treat its authored `## Frontier` as authority. Do not replace it with the lowest WF ID.
2. Read every frontier decision record and each record named by `Depends on`.
3. A decision is available only when its status is **Open** and every dependency is **Closed**.
4. Report a missing frontier, multiple frontier choices, a closed frontier decision, or unfinished dependencies as
   map state requiring attention rather than fabricating available work.

Read `.pi/skills/wayfinder/SKILL.md` before recommending a decision. Read a mode-specific skill's current
`SKILL.md` before making any claim about it; omit claims that its actual scope does not support. The Wayfinder skill
owns map coordination, so the copyable command is:

```text
WAYFINDER: WF-NNN — Decision title
Map: Active map title
Why: Authored frontier; all dependencies closed
Mode: Ticket label and mode
Run: /skill:wayfinder work WF-NNN
```

When one active map has one available frontier, return it as the next Wayfinder decision. When multiple active maps
have available frontiers, return every map's available frontier and state that planning defines no global map
priority; let the human choose. When no map is active, report `WAYFINDER: None — all maps are closed`.

Wayfinder selection is complete when every active map has either one evidence-backed recommendation or one explicit
reason it has none.

## 4. Stop at the route

Return compact `TASK`, `WAYFINDER`, and optional `HUMAN INPUT` sections. Do not edit status, refresh generated
views, create branches or worktrees, or begin the recommended skill.
