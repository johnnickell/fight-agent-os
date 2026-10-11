# Define TASK triage readiness and classification

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [SDLC intake triage](../sdlc-intake-triage-map.md)
**Depends on:** —

## Question

What must Triage establish about the next candidate TASK before it can recommend an execution route, and which
unresolved conditions require more information or a human decision?

## Proposed basis from John — 2026-09-28

Introduce Triage as a templated Agent responsibility, separate from the Access Control Role entity, at the head
of a future SDLC Workflow. It confirms TASK readiness, distinguishes hotfix, feature, bugfix, chore or other
enhancement work, detects human-decision requirements, and routes the TASK before implementation begins.
The intended examples are a feature TASK handed to Team Lead and a hotfix handed directly to a Senior Engineer;
WF-028 owns the exact hotfix template and independence contract.

## Proposed Jev use cases — readiness and classification — 2026-10-07

Use the shared bounded judgment capability from [TICKET-00037](../../tickets/00037-TICKET.md) as a proposed
semantic assistant to Triage. Preserve the canonical eligibility query and separate the Triage Agent's main model
from the decision evaluator. These historical proposals did not add an active intake gate or change TASK status,
kind, priority, claims or requirements; the resolution below supersedes them where they differ.

| Proposed use | Bounded question/input | Candidate output and boundary |
|---|---|---|
| Assess semantic readiness | Current TASK/context revisions, owning eligibility facts, scope, inputs and acceptance criteria: is material information or a human decision missing? | Ready-to-route, needs-information, needs-human or unknown recommendation with reasons/source identities; deterministic ineligibility remains authoritative |
| Classify work type | Current accepted TASK intent and evidence: which declared feature, bugfix, chore, hotfix-route or other category fits? | Typed classification with explicit ambiguity/unsupported exits; does not silently rewrite Planning kind or select a destination |
| Assess urgency and risk dimensions | Bounded incident/change facts under a declared rubric: what urgency/risk category is supported? | Separate dimensions for deterministic policy to combine; no queue reprioritization, emergency authority or lowered verification requirements |

Must decide the typed questions, allowed categories, relevant evidence requirements, confidence/uncertainty handling,
when a Triage Agent can act automatically and which outcomes require a human. Do not copy the 0.70 file relevance
or 0.95 tool-guard thresholds into triage. Distinguish missing facts from low-confidence classification, explicit
human scope choices from model recommendations, and provider failure from an ineligible TASK.

Bind judgments to TASK/context and eligibility revisions, rubric/policy and exact evaluator identity. Apply
permission/disclosure checks and bounded source/question/time/spend limits before evaluation; treat TASK prose as
untrusted data. Define stale-result invalidation, unavailable-service behavior and whether any deterministic-only
route is permitted explicitly; do not fabricate readiness or silently fall back. Preserve recommendation history
and human disposition without creating a competing queue.

Qualification should compare representative accepted and ambiguous TASKs against reviewed dispositions, including
missing acceptance criteria, unresolved human scope choices, deterministic blockers, uncertain hotfix requests,
stale inputs and evaluator outage. Measure classification/readiness errors and total cost/latency; report false
readiness separately from unnecessary escalation. WF-028 consumes accepted dispositions and owns dispatch.

These were proposals, not an additional readiness gate. The approved resolution below supersedes the proposed
per-TASK semantic readiness assessment and defines a declared-kind fallback when Jev cannot classify. It neither
qualifies Jev nor selects the Triage Agent's main model or dispatch mechanism.

## Decision questions considered

- Intake timing and ownership: consume the canonical next candidate/eligibility reasons; distinguish a triageable
  incomplete item from an executable TASK without treating `needs-triage` as implementation-ready or changing queue priority.
- Readiness evidence: bounded scope, clear acceptance criteria, adequate inputs, dependency availability, current
  context, conflicting claims, required authority, supported execution capability and unanswered human decisions.
  Consume deterministic facts from their owning services rather than asking a model to override them.
- Classification dimensions: existing TASK kind versus execution route and urgency. Decide whether hotfix is an
  emergency route for a bug, whether enhancement belongs to feature, and how ambiguous/unsupported cases are handled;
  do not add new Planning kind/status values merely by using these labels.
- Human judgment for scope, priority, risk acceptance, exceptions and emergency authority versus factual routing
  judgments that an already authorized Triage Agent may make automatically under accepted policy.
- Typed dispositions such as ready-to-route, needs-information, needs-human, ineligible and unsupported-route,
  including reasons, evidence and exact TASK/context revisions. Names are provisional and not new machine statuses.
- Whether Triage may propose TASK amendments or update any metadata; accepted requirements and human choices cannot
  be rewritten as a side effect of declaring an item ready.
- Route recommendation contract, ambiguity/conflict handling, escalation owner, and bounded investigation; Triage
  should not silently become Project Manager, Team Lead or the implementing Engineer.

## Resolution boundary

Set readiness/classification semantics and distinguish model judgment from deterministic enforcement. Preserve
the single canonical eligibility contract in [TASK-00096](../../tasks/00096-TASK.md). WF-028 decides dispatch,
grant/claim timing and hotfix roles. This record performs no real triage or Planning lifecycle mutation.

## Resolution — 2026-10-10

John approved classification and routing preparation, **not a second readiness review** of each `ready-for-agent`
TASK. An authorized human selects a TASK through a future terminal workflow invocation or a browser request such
as moving a Ready card toward In Progress. Triage consumes that selected TASK and its current declared kind and
Planning/context/eligibility facts; it does not scan, rank or claim the queue. A UI gesture expresses an intent,
not a completed status transition or execution grant. On explicit request, Triage may diagnose an incomplete TASK,
but cannot make it executable. The canonical eligibility capability remains the sole deterministic owner of
readiness, blockers, current authority and safe reasons; start must revalidate it under the applicable Workflow
contract. Do not add a separate model-based readiness gate, mutate Planning status or replace the work skill's
ordinary scope checks.

### Typed classification and priority

- Call the shared bounded Jev classifier for **every selected TASK**, including one whose declared kind appears
  clear. Provide permission-filtered TASK intent, declared kind and relevant bounded evidence; treat TASK prose as
  untrusted input, not policy. The initial routing categories are `feature`, `bugfix` and `chore`; enhancement is
  `feature`, not a fourth workflow or a new Planning `kind`. Map the existing declared `bug` kind to `bugfix`
  only in this routing vocabulary. Return `unknown`/ambiguous explicitly rather than inventing a category.
- Jev also recommends one configurable initial priority band: `low`, `medium`, `high` or `urgent`. These are
  advisory relative-priority labels, **not** authored Board order, a queue override or a new Planning priority
  status. Do not require comparison to every Ready TASK to assign a band. Only an authorized human can decide a
  Planning priority change. The exact rubric, evaluator, budgets and any later vocabulary revisions are qualified
  and versioned during implementation; neither the file-screening nor tool-guard confidence threshold applies.
- Classify work category and urgency/priority separately. A `bugfix` with recommended `urgent` priority can be
  proposed for a distinct hotfix workflow, but an authorized human must confirm emergency treatment before it
  starts. `urgent` on another category does not silently make it a bug or grant a hotfix route. Urgency never
  authorizes skipping isolation, verification, independent review or release/deployment grants.

### Conflict, fallback and handoff

- When a usable Jev category materially conflicts with the TASK's declared kind such that the workflow route
  changes, pause for human resolution and show both classifications and their evidence. No TASK kind, scope or
  priority is silently rewritten. A non-material refinement such as calling a feature an enhancement does not
  pause routing.
- If Jev is unavailable, times out, returns `unknown`/ambiguous or otherwise supplies no usable category,
  fall back to the current, supported TASK-declared kind for the **normal** workflow only. Mark the provenance as
  declared-kind fallback, the priority as `unknown`, and the Jev failure/uncertainty separately from Planning
  ineligibility. Do not claim a Jev result or infer emergency urgency. If the kind is missing, unsupported or
  inconsistent with current authoritative facts, stop for human resolution rather than guessing.
- Produce a bounded recommendation with TASK and context/eligibility revisions, category and evidence/provenance,
  priority band or `unknown`, any emergency confirmation, reason for fallback or escalation, evaluator and rubric
  identity when Jev ran, and the proposed category for WF-028's route selection. An authorized human resolves a material
  conflict or approves changed scope, priority or emergency treatment. Triage may recommend a correction but
  neither applies Planning amendments nor gains permissions through its classification. Fresh eligibility,
  current grants and supported routes are validated by the receiving application operation, not Jev.
- Bind judgments and any human disposition to their exact subject and rubric revisions; reject stale results.
  Apply permission/disclosure and bounded source, time and spend constraints even if Jev is inexpensive. Retain
  traceable classification/fallback/history without creating a competing queue. Qualification should compare
  declared kinds with representative accepted and disputed TASKs, priority bands, urgent bugs, conflicts,
  missing/unsupported kinds, stale facts and Jev outage; report errors, fallback and escalation rates, latency
  and actual cost rather than assuming classification is free or infallible.

[WF-028](WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md) owns the predefined workflow catalog,
route-to-role mapping, final invocation and authorization, claim timing, duplicate/retry recovery, and the exact
senior-capability hotfix handoff. This decision neither chooses the hotfix implementer nor inserts a mandatory
Triage gate into the already approved first Team Lead → Software Engineer execution slice. No runtime capability,
Workflow, claim, Agent session or Jev invocation was created by closing this decision.
