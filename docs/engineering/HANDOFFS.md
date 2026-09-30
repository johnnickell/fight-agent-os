# Skill completion and next commands

At a useful stopping point, finish the user-facing result with `Next:` and one copyable command for the next
logical skill, using the actual target identity. Put the command in inline code, for example
Next: `/skill:review TASK-00058`; never output a placeholder ID as if it were runnable. Keep outcome, evidence,
limits and the durable handoff above it. A command recommendation does not invoke a skill or grant authority.

Read the target skill and current evidence before selecting it; use only supported invocation shapes. If the next
step needs a human decision, unavailable prerequisite or publication authority, state that action on the `Next:`
line instead of recommending a premature command. Where helpful, give the command to resume after that action.
Do not replace a known next target with generic `/skill:next`. When several targets are genuinely unordered,
present labeled choices without inventing priority; each command must identify its own target.

## Engineering handoffs

Apply current [review](REVIEW.md), [QA](QA.md) and [landing](LANDING.md) standards; this table summarizes routing,
not new acceptance or publication authority.

| Completed state | Next recommendation |
|---|---|
| Work implemented or repaired the TASK, required checks passed and committed evidence is ready | `/skill:review TASK-NNNNN` in an independent session |
| Review says revise for implementation defects | `/skill:work TASK-NNNNN` with the canonical findings |
| Review accepts and behavioral QA is applicable or applicability remains unresolved | `/skill:qa TASK-NNNNN` in an independent session |
| Review accepts and applicable QA is satisfied, including justified overall N/A | `/skill:land TASK-NNNNN` when publication is authorized; otherwise name the missing authority |
| QA confirms a defect | `/skill:work TASK-NNNNN`; material repair then returns through review and affected QA |
| QA is incomplete | Name the actual missing prerequisite, then `/skill:qa TASK-NNNNN` to resume; missing technical acceptance first routes to independent review |
| QA passes or establishes justified N/A, with current technical acceptance | `/skill:land TASK-NNNNN` within publication authority |
| Review's only missing proof is explicitly required hosted evidence | Authorized `/skill:land TASK-NNNNN` draft-evidence path; then `/skill:review TASK-NNNNN` once that evidence is available |
| Land publishes successfully | Human review/merge of the actual PR link; do not imply automatic merge or start another TASK |

Failed required implementation checks keep work incomplete; recommend the same work skill only when continuing
it can resolve the issue, otherwise name the prerequisite. A draft PR or pending final-head delivery proof is
not successful landing: use the existing evidence-continuation route, not a false completion or repair loop.
For an open-PR QA invocation, the observed PR URL can be the resume target; resolve its TASK for work/review/land.

## Planning handoffs

Follow [map-wide planning order](../../planning/CONVENTIONS.md#map-wide-planning-order). Recommend one next
operation within the current phase, not automatic execution of the entire map. Use real record IDs and repository
paths; include the selected map path when it disambiguates a destination. Read the receiving skill's input rules.

| Current state | Next recommendation |
|---|---|
| Wayfinder map has an unblocked authored decision frontier | `/skill:wayfinder work WF-NNN`; the selected mode handles its interview/research/prototype within the decision's scope |
| Map decisions are resolved and an approved EPIC destination remains unwritten | `/skill:grill <map-path> "<approved destination title>"` |
| Grill wrote an EPIC but another destination in the same map still needs grilling/EPIC creation | Continue that map's next `/skill:grill` destination; do not jump to this EPIC's TICKETs |
| All map EPIC destinations are resolved and written or explicitly excluded; an EPIC still needs accepted TICKET decomposition | `/skill:to-tickets EPIC-NNNNN` |
| One EPIC's TICKETs are written but another EPIC in the map still needs TICKET decomposition | `/skill:to-tickets EPIC-NNNNN` for that next EPIC |
| All map TICKET decompositions are accepted; a TICKET still needs TASK decomposition | `/skill:to-tasks TICKET-NNNNN` |
| One TICKET's TASKs are written but another TICKET in the map remains to decompose | `/skill:to-tasks TICKET-NNNNN` for that next TICKET |
| Planning phases are complete | `/skill:next` to consult the authoritative Board; do not select implementation from a map or start work |

Research or prototype evidence returns to its owning Wayfinder decision first, normally
`/skill:wayfinder work WF-NNN` to reconcile that result. Once the decision is closed, use the current authored
frontier. A map may be Closed while its linked EPIC handoff remains unfinished; continue that handoff without
reopening settled decisions. An unrelated standalone EPIC/TICKET can use its normal next decomposition step.
Pending human acceptance remains the next action; a prepared proposal is not a written/accepted planning record.
