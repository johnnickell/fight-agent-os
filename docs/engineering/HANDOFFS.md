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

## Readable handoff text

Apply this to user-facing handoffs, delegated stage requests and continuation summaries. Agents and humans must
be able to distinguish the requested action, current evidence, limits and next step without reconstructing words.
Use normal word spacing and punctuation, with short paragraphs or bullets and clearly separated labels and values.
Do not concatenate prose words or join labels to identifiers to shorten a prompt. Preserve literal paths, hashes,
commands, code and identifiers exactly; format or label them separately from the surrounding prose.

Reduce context by removing repetition and referencing canonical evidence with the relevant finding or section.
Keep the facts, authority boundaries and unresolved conditions needed for the receiving stage; a reference must
not conceal a blocker or imply acceptance. Before sending or saving, read back the text for joined words, ambiguous
labels and lost constraints, and rewrite affected prose. These checks also apply when adapting a compressed prior
handoff; do not carry its unreadable wording into the next request or silently repair quoted evidence.

## Engineering handoffs

Apply current [review](REVIEW.md), [QA](QA.md) and [landing](LANDING.md) standards; this table summarizes routing,
not new acceptance or publication authority.

The read-only [next skill](../../.pi/skills/next/SKILL.md) defaults new implementation and confirmed repair work
to `/skill:build TASK-NNNNN`. An explicit [build invocation](../../.pi/skills/build/SKILL.md) delegates coordination
through review, QA and land without another approval prompt; its Team Lead continues the stage routes below
within that authority. Individual stage skills retain their narrower boundaries. Evidence-only, QA, delivery,
planning and human-prerequisite handoffs remain specific to the actual state; `next` never launches the workflow.

| Completed state | Next recommendation |
|---|---|
| Work implemented or repaired the TASK, required checks passed and committed evidence is ready | `/skill:review TASK-NNNNN` in an independent session |
| Review says revise for implementation defects | `/skill:work TASK-NNNNN` with the canonical findings |
| Review accepts and behavioral QA is applicable or applicability remains unresolved | `/skill:qa TASK-NNNNN` in an independent session |
| Review accepts and applicable QA is satisfied, including justified overall N/A | `/skill:land TASK-NNNNN` when publication is authorized; otherwise name the missing authority |
| QA confirms a defect | `/skill:work TASK-NNNNN`; material repair then returns through review and affected QA |
| QA is incomplete | Name the actual missing prerequisite, then `/skill:qa TASK-NNNNN` to resume; missing technical acceptance first routes to independent review |
| QA passes or establishes justified N/A, with current technical acceptance | `/skill:land TASK-NNNNN` within publication authority |
| Initial independent acceptance lacks only explicitly required hosted evidence | Authorized `/skill:land TASK-NNNNN` draft-evidence path; then `/skill:review TASK-NNNNN` once that evidence is available |
| Accepted candidate A has administrative closeout B, with final delivery proof pending or newly available | `/skill:land TASK-NNNNN` verifies closeout and required checks; no additional independent review or canonical review update solely for delivery |
| TASK closes | Close eligible TICKET/EPIC ancestors and refresh views in the same operation under [automatic parent completion](../../planning/CONVENTIONS.md#automatic-parent-completion); no separate parent-closeout handoff |
| Explicit Land resume observes an ended original merge handoff | Use [ended-handoff recovery](LANDING.md#resume-an-ended-delivery-handoff) for that exact TASK/PR within authority; no automatic invocation or replacement delivery |
| Ended-handoff reconciliation finishes | Name the actual corrective PR's human action, the closed-unmerged maintainer decision, or no remaining subject action for a no-op; never repeat merge of the ended original PR |
| Land publishes successfully | Human review/merge of the actual PR link; do not imply automatic merge or start another TASK |

Failed required implementation checks keep work incomplete; recommend the same work skill only when continuing
it can resolve the issue, otherwise name the prerequisite. A draft PR or pending final-head delivery proof is
not successful landing. Distinguish missing initial acceptance from land-owned delivery verification after
acceptance using the routes above; retain required evidence gates without a completion or repair loop.
For an open-PR QA invocation, the observed PR URL can be the resume target; resolve its TASK for work/review/land.

In Agent OS, point current delivery claims to the subject's [explicit TASK marker and generated view](../../planning/CONVENTIONS.md#current-delivery-handoffs).
A fresh offline view establishes local identity consistency only, not live PR state, acceptance or a merge action.
Preserve distinct planning/decomposition handoffs and historical PR evidence; never infer a marker from them.
Other targets use their qualified local representation rather than inheriting Agent OS's script interface.

Work and Build carry the exact [resource inventory](LANDING.md#inventory-and-preserve) in their existing
handoffs to Land: Git checkout/ref/published OIDs, Compose context/container/network identities, live sessions,
evidence and data ownership/dependencies, including unknowns and explicit main/current/active holds. Land owns
[required safe cleanup and postcondition verification](LANDING.md#cleanup-and-handoff), not another registry or
an unconditional branch-retention handoff. Return published/delivery state separately from cleanup: a verified
PR with a held resource is publication with incomplete cleanup, naming the resource, reason, recovery owner/action
and durable receipt. Resume only missing effects; never recommend repeating publication to resolve a cleanup hold.

During authorized normal publication, Land applies [current subject handoff synchronization](LANDING.md#synchronize-current-subject-handoffs)
to applicable authored current Board/Roadmap/README handoffs as well as source records, parent rollups and derived
views. A historical acceptance/delivery-pending checkpoint is not today's instruction: preserve it and canonical
reports, and distinguish the current stage/action with an evidenced current section or durable result locator.
Missing proof or unresolved contradictory handoffs need a precise hold, not an inferred merge, unrelated repair
or another metadata commit merely to certify final-head success. Recommendations and read-only routing do not
perform this reconciliation or acquire Land authority. When explicitly resumed after an ended original PR,
Land uses the [bounded recovery path](LANDING.md#resume-an-ended-delivery-handoff), including safe checkout
selection after cleanup and inspection of existing correction/publication state before retry. A verified merge
and a closed-unmerged outcome require distinct instructions; unavailable or mismatched proof holds correction.
Already-consistent unchanged state needs no commit or publication retry. Historical acceptance and reports stay
intact, and observing closure never authorizes reopening or replacing the original PR.

## Release handoffs

[Release acceptance](../../.pi/skills/release/references/acceptance.md) owns version subjects and canonical reports.
Release review/QA returns to the coordinator for authorized preparation repair, evidence recovery or delivery,
not TASK `work`/`land`. Standalone assessors finish with `/skill:release <actual-version> resume`. Supported release
dispatch can continue automatically within existing authority; the current TASK-only Build launcher cannot.
When dispatch is unavailable, prepare the exact independent review/QA invocation and packet/context paths.
Signing, publication and other human boundaries remain concrete actions; `status` never dispatches or repairs.

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
