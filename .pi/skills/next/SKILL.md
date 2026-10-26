---
name: next
description: Recommend current TASK work, Wayfinder decisions and planning handoffs from bounded Board and index reads.
disable-model-invocation: true
---

# Next

Recommend and stop. This is read-only navigation, not execution, independent assessment or a planning audit.
Use the target's routing instructions before general project orientation.

## 1. Resolve routing context and freshness

Use [routing context](../../../docs/engineering/PROJECT_CONTEXT.md#routing-only-context), not delivery context.
Resolve the operator's named checkout, otherwise the invocation's Git root. Read its AGENTS routing section and
only routing substitutions in its conventions/capability binding. Provider skill paths never select the target.
Use the target's Board path, section names, status vocabulary, map inventory and completion semantics.
Do not read provider planning as a fallback or guess a private installation path.

Run the target's documented read-only planning validator once when available (here, `./bin/planning-check`).
On failure, report the actual error and smallest planning-owner handoff; stop selection without `--write`.
Name automatic parent synchronization as a repair only if target rules authorize it. When no validator exists,
disclose the limitation and cross-check selected metadata against the authored Board; do not claim freshness.
For a target without a Board, follow its documented planning entry or report the missing source/owner decision.

Use heading searches and bounded section reads, normally no more than about 24,000 characters of retrieved
routing evidence in total. Stop with a precise unresolved input before exceeding that scope unless the user
requests a deeper audit. Do not open whole long tasks, appended histories, source trees, graphs, general
README/architecture/context, or all receiving skills. An ambiguous route is a valid result, not a reason to
reconstruct the portfolio. These limits do not waive verification in the workflow eventually invoked.

## 2. Select TASK work and its next stage

Read the Board's current human decision and active section. Return **all active TASKs**; otherwise the first
executable Ready Frontier row in authoritative order, never the lowest ID. Do not start another TASK while
active work exists. If neither exists, say no TASK is currently executable; surface relevant Needs Info/Human
Action and the first waiting/triage reason under target rules, without claiming all work is finished.

Check selected TASK frontmatter/title and current progress/handoff only. Resolve blocker status only when
needed; completed dependency edges remain history. Preserve the Board choice when scope, approval or evidence
is unclear and name the missing input. `done` alone proves neither independent review, QA nor publication.

For selected work with delivery evidence, resolve the canonical base using Git common-directory/worktree
metadata. Check only its canonical review/QA identity, verdict, current findings and continuation fields;
check existence before opening. Do not scan private runs or load whole tasks from other worktrees. Match
repository/TASK and the claimed subject; an older report does not establish current-head acceptance. If the
current handoff cannot establish an applicable subject/bridge, report evidence reconciliation or independent
reassessment, not a guessed pass, repair or delivery. Do not perform that assessment inside `next`.

Before evaluating or repeating a relevant current delivery/merge handoff, follow
[live handoff evaluation](../../../docs/engineering/HANDOFFS.md#live-delivery-handoff-evaluation).
Require the target's qualified bounded live check for only the relevant explicit current subjects (in Agent OS,
`./bin/planning-check --check-delivery` with their TASK IDs). If the target has no qualified checker, use its
supported exact-PR hosting read within the same bounds or report the missing capability; do not assume command
compatibility. Exact MERGED evidence for an already-`done` subject resolves read-only only with published-head
→ merge → local-HEAD ancestry (replacement objects/lazy fetching disabled); report it and continue routing,
without a routine cleanup request. The next authorized mutation retires its marker. CLOSED-without-merge,
nonterminal or unintegrated MERGED evidence retains its precise decision/integration or explicit exceptional
Land reconciliation route; never repeat the ended merge action.
Unavailable/mismatched/partial evidence holds that delivery recommendation with the exact recovery action.
Do not invoke Land, refresh views or inspect unmarked historical PRs. Preserve unrelated available work,
Board ordering and planning routes; reconciled history alone creates neither requests nor selection holds.

Use this stage routing once evidence supports it:

| Current state | Recommendation |
| --- | --- |
| Unimplemented work, ongoing implementation or confirmed implementation/QA defects | `/skill:build TASK-NNNNN`; preserve the existing worktree, ledger and consumed revision allowance. QA failure does not reset it. |
| Explicit request for implementation alone | `/skill:work TASK-NNNNN` |
| Completed implementation awaiting independent technical acceptance | `/skill:review TASK-NNNNN` |
| Accepted work with missing/incomplete QA or applicability assessment | `/skill:qa TASK-NNNNN`, or the explicit missing prerequisite; include non-UI behavior |
| Accepted work with applicable QA satisfied or justified overall N/A | `/skill:land TASK-NNNNN`, subject to existing publication authority |
| Unknown/stale subject, uncertain findings or contradictory reports | Name the evidence owner and exact reconciliation/reassessment needed; do not infer a stage verdict |

Use supplied capability metadata/bindings to confirm availability before printing a command. Reading a second
skill's full instructions is not required merely to recommend it; load them when invoked. If command shape or
availability is unclear, inspect only its synopsis or name the missing capability. Do not make unsupported
claims about its behavior. Invoking `build` carries its defined authority; recommending it grants none.

For evidence-only exceptions, read only the relevant policy section if the selected handoff requires it:
[optional hosted checks](../../../docs/engineering/REVIEW.md#local-gate-and-optional-hosted-checks),
[QA applicability](../../../docs/engineering/QA.md#applicability), or
[landing intake](../../../docs/engineering/LANDING.md#intake-and-authority), with target substitutions.
An optional-CI-only erroneous verdict needs independent reassessment, not implementation. Before acceptance,
explicitly required hosted proof may need authorized draft publication then independent review. Accepted
candidate A with permitted administrative closeout B resumes through land, including delivery-only revise
that preserves A. A literal-final-head contradiction needs the requirement owner's amendment decision;
do not recommend another status commit. Missing authority is human input. Preserve findings and history.

## 3. Resolve Wayfinder from its index

Read only the target Wayfinder index's map-status/frontier inventory. After a successful freshness check,
an explicitly all-Closed inventory needs **no map reads**. Report no decision frontier, not that downstream
planning or implementation is complete. If there is no index, say so; do not infer closure.

For each Active/Open map, use only current status/frontier sections and decision/dependency metadata needed
to verify availability. The authored frontier wins over numeric order. Availability requires an Open decision,
all WF dependencies Closed, and any explicit external prerequisite met. Unknown state, absent/multiple/closed
frontiers or unmet prerequisites need named owner attention; an all-Closed decision register cannot close an
Open map. Use sourced inventory dispositions for intentionally absent frontiers. Do not read resolved decision
histories, research or closed maps merely to prove absence again.

Return every available map frontier when there are multiple, with no invented global priority. The supported
command is `/skill:wayfinder work WF-NNN`; include map, decision title and why it is available. If the inventory
conflicts with current evidence, report the conflict and stop that route rather than repairing it.

## 4. Route unfinished planning without reconstructing history

Read the Board/Roadmap's **current planning handoff** and only its named owner's current progress/approval
section. Closed maps can still have unfinished decomposition. Apply the target's map-wide phase order:
remaining decisions/approved unwritten EPICs, accepted TICKET decomposition, then TASK decomposition.
Honor an explicit narrower request. Use `/skill:to-tickets EPIC-NNNNN` or `/skill:to-tasks TICKET-NNNNN`
only when that phase and target are supported; unresolved scope/approval is human input.

Neither parent status nor a nonempty child table establishes full decomposition. Missing or contradictory
current handoffs need the planning owner's reconciliation; do not search every closed map, parent, child or
archive to manufacture a next phase. Planning choices never replace the executable TASK Board.

## 5. Stop at the recommendation

Return compact TASK, WAYFINDER and applicable PLANNING/HUMAN INPUT sections. Give each route's ID/title,
short evidence-backed reason and copyable `Next:` command, or precise missing input/owner. Identify uncertainty
without burying available routes. Do not invoke another skill, change status/reports/views, create worktrees,
refresh graphs or start implementation. Full context and gates belong to the selected workflow's invocation.
