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
