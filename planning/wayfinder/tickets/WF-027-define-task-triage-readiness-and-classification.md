# Define TASK triage readiness and classification

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
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
from the decision evaluator. These proposals inform this open decision; they do not add an active intake gate or
change TASK status, kind, priority, claims or requirements.

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

This record remains Open. The Jev proposal neither selects the Triage Agent's main model nor qualifies automatic
classification, creates an execution grant or resolves the separate role/dispatch decision.

## Must decide

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
