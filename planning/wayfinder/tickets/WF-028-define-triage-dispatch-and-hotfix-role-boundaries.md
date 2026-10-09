# Define triage dispatch and hotfix role boundaries

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [SDLC intake triage](../sdlc-intake-triage-map.md)
**Depends on:** [WF-027](WF-027-define-task-triage-readiness-and-classification.md)

## Question

How does an accepted triage disposition dispatch a TASK into the correct authorized Workflow, including a direct
senior hotfix path, while preserving ownership, recovery, emergency boundaries and independent review?

## Existing contract and proposed change

[Team roles](../../../docs/engineering/TEAM.md) currently assigns implementation to Software Engineer, independent
technical review to Senior Engineer, and emergency diagnosis/repair to Hotfix Engineer with senior capability.
John's proposed direct Triage → Senior Engineer hotfix route is recorded here as a product direction requiring
an explicit reconciliation with those templates, not silently mapped to the current Hotfix Engineer profile.

Whichever template is selected, an Agent that implements or contributes acceptance evidence cannot independently
review that same change. A fresh session alone does not remove its prior contribution. Urgency never grants release
or deployment authority or permits self-approval.

## Proposed Jev use case — workflow and team recommendation — 2026-10-07

Consider Jev for semantic route selection only where the accepted WF-027 disposition and approved route catalog
leave a real ambiguity. If deterministic rules already map the disposition to one route, use those rules without
an extra model call. Reuse [TICKET-00037](../../tickets/00037-TICKET.md)'s bounded judgment capability; do not build
an independent router with its own eligibility or authority rules.

- **Candidate interaction:** provide the accepted disposition and exact TASK/context revisions with a bounded,
  permission-filtered catalog of eligible inner Workflows and responsible team templates. Ask which declared route
  fits, or return unknown/needs-human. Examples include a feature through Team Lead and a direct senior-capability
  hotfix route, whose exact template and reviewer independence remain unresolved here.
- **Authority:** Jev recommends a catalog identifier; it cannot invent a route, grant a role, select an unauthorized
  Agent, claim a TASK or start execution. PHP must revalidate current eligibility, grants, assignment scope, budgets,
  catalog/context revisions, availability and contributor/reviewer separation before dispatch.
- **Must decide here:** which choices are deterministic, when an additional judgment is justified, input/output
  contract, acceptance threshold, human resolution, unsupported/no-route behavior, outage policy and dispatch owner.
  Neither first-milestone threshold is a default for routing, and urgency is not permission to bypass checks.
- **Binding and recovery:** retain disposition and recommendation identity plus rubric/evaluator/catalog revisions;
  reject stale recommendations, consume an authorized dispatch once, and handle concurrent triggers, cancellation,
  crash/retry and rerouting through the owning Workflow contracts. A model retry cannot start another Workflow or
  silently replace an accepted route. Claim/grant timing is still a decision to resolve, not implemented here.
- **Qualification:** demonstrate a straightforward deterministic route, a genuinely ambiguous choice, no eligible
  route, unauthorized/stale catalog entries, unavailable evaluator, changed grants and duplicate triggers. Prove
  that a proposed implementer cannot become its own independent reviewer. Compare routing quality/cost/latency
  against deterministic classification-to-route mapping before making extra Jev calls mandatory.

These are proposed decision inputs. WF-028 stays Open and depends on WF-027; this amendment does not reconcile the
Senior Engineer/Hotfix Engineer templates, alter existing execution planning or authorize any actual dispatch.

## Must decide

- Versioned routes and exact handoff inputs/outputs: feature through Team Lead, direct senior hotfix path, and
  destinations for bugfix/chore/enhancement classifications from WF-027; unsupported routes stop explicitly.
- Whether senior hotfix implementation uses the existing Hotfix Engineer template, a new Senior Engineer
  specialization, or an explicit amendment to the current catalog; separate the implementer from its reviewer.
- Who owns communication, coordination and escalation when the direct hotfix route bypasses Team Lead, and which
  planning steps are shortened while retaining incident scope, regression evidence and independent verification.
- Start authorization, triage permission versus execution grants, claim timing and reservation ownership; record
  whether Triage is preflight or a Workflow phase, including identity before an execution Workflow exists.
- Deterministic PHP validation of the proposed route and fresh eligibility, live permissions, scope, budgets,
  context/TASK revisions and human decisions before dispatch. A model's readiness assertion is insufficient.
- Duplicate triggers, concurrent selection, crash/retry, stale triage decisions, cancellation and explicit rerouting;
  avoid duplicate Workflow starts and preserve the original disposition and handoff history.
- Whether rerouting after execution begins is ever allowed, what requires a new grant or Workflow, and how evidence
  and any future TASK memory carry over without importing prior permissions.
- Necessary amendments to [TICKET-00034](../../tickets/00034-TICKET.md),
  [EPIC-00007](../../epics/00007-EPIC.md), [EPIC-00008](../../epics/00008-EPIC.md) and existing Workflow decisions;
  explicitly select introduction timing rather than retroactively blocking the approved first execution slice.

## Resolution boundary

Set dispatch, role separation and recovery requirements and prepare a planning handoff. Do not implement a router,
change accepted template permissions, start execution, claim TASKs or authorize emergency/release/deployment effects.
