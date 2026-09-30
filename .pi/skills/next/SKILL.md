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

Before describing the command, read `.pi/skills/work/SKILL.md` and any canonical review for the selected TASK.
Apply the [local-first verification policy](../../../docs/engineering/REVIEW.md#local-gate-and-optional-hosted-checks); an optional missing hosted run is not a new blocker. If a report incorrectly requires optional CI, route to independent reassessment rather than overriding its verdict.
Before initial acceptance, a review whose only outstanding explicit requirement is hosted evidence routes to authorized draft publication under
[landing standards](../../../docs/engineering/LANDING.md#draft-publication-to-obtain-hosted-evidence), or to
independent `review` once that evidence is available. Read the selected skill before recommending it. State
missing publication authority as human input; do not recommend `work` for an evidence-only blocker. Read the selected TASK's canonical QA report when present under [QA standards](../../../docs/engineering/QA.md).
A current confirmed QA failure routes to `work` even when an older technical review says accept. Once technical acceptance is established, missing or
incomplete required/requested QA routes to `qa` or its named prerequisite; read that skill before recommending it.
Use [QA applicability](../../../docs/engineering/QA.md#applicability) for all behavior-affecting changes, including
non-UI work. An absent applicability assessment also routes accepted work to `qa`; a reasoned N/A in the technical
handoff or canonical QA report can satisfy that boundary for changes with no behavioral consequence.
Before acceptance, unimplemented TASKs and unresolved implementation findings route to `work`; completed
implementation awaiting technical review routes to `review`. A missing QA report must not send unimplemented
work directly into QA.
An accepted TASK with applicable QA satisfied routes to `land`; implementation findings route to `work`.
For accepted candidate A with administrative closeout B, resume final delivery verification through `land`,
including when required final-head proof becomes available. Apply the same route to an older delivery-only
`revise` report that explicitly preserves accepted A under [landing intake](../../../docs/engineering/LANDING.md#intake-and-authority).
Do not require another independent review, repeat initial draft intake or reopen implementation solely for delivery.
An unresolved literal-final-head completion contract needs the requirement owner's
amendment decision under [closeout rules](../../../docs/engineering/LANDING.md#acceptance-candidate-and-administrative-closeout),
not another status commit. Preserve the Board's TASK selection.

For a TASK requiring implementation, provide:

```text
TASK: TASK-NNNNN — Title
Why: Active work to continue | First executable Ready Frontier entry
Next: /skill:work TASK-NNNNN
```

If multiple TASKs are active, list all of them and state that the Board provides no instruction to start another
TASK. Do not invent a winner.

TASK selection is complete when every returned ID, title, status, blocker state, and reason agrees with the Board
and owning record.

## 3. Select Wayfinder decisions

Read `planning/wayfinder/README.md`, then every live map listed there. Inspect every **Active** map for decisions.
Closed maps have no decision frontier, but inspect their linked planning handoffs for explicitly unfinished EPIC
or decomposition work under [map-wide planning order](../../../planning/CONVENTIONS.md#map-wide-planning-order).
Do not invent unfinished destinations or reopen settled maps; verify resulting live/archived records.

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
Next: /skill:wayfinder work WF-NNN
```

When one active map has one available frontier, return it as the next Wayfinder decision. When multiple active maps
have available frontiers, return every map's available frontier and state that planning defines no global map
priority; let the human choose. When no map is active, report `WAYFINDER: None — all maps are closed`; this does
not by itself establish that their linked EPIC/TICKET/TASK planning handoffs are complete.

Wayfinder selection is complete when every active map has either one evidence-backed recommendation or one explicit
reason it has none.

## 4. Select the planning phase

For a selected map or each map with an explicit unfinished handoff, follow [map-wide planning order](../../../planning/CONVENTIONS.md#map-wide-planning-order)
and the [handoff command shapes](../../../docs/engineering/HANDOFFS.md#planning-handoffs). Prefer remaining
decisions/grills and approved unwritten EPICs, then accepted TICKET decomposition for all its EPICs, then TASK
decomposition for all its TICKETs. Read the receiving skill before recommending its command. Show the actual
target and evidence for its phase; a nonempty child table or parent status alone cannot prove full decomposition.
Honor an explicit narrower choice, and show unresolved scope/approval as human input rather than a runnable
next phase. Multiple maps remain independent choices without invented global priority. This recommendation does
not override the authoritative TASK Board or automatically start any planning or implementation.

## 5. Stop at the route

Return compact `TASK`, `WAYFINDER`, and applicable `PLANNING` / `HUMAN INPUT` sections. Finish each actionable
recommendation with a copyable `Next:` line under the [handoff rules](../../../docs/engineering/HANDOFFS.md), using
the actual target. Do not edit status, refresh generated
views, create branches or worktrees, or begin the recommended skill.
