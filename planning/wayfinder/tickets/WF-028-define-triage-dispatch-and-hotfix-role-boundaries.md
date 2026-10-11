# Define triage dispatch and hotfix role boundaries

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [SDLC intake triage](../sdlc-intake-triage-map.md)
**Depends on:** [WF-027](WF-027-define-task-triage-readiness-and-classification.md)

## Question

How does an accepted triage disposition dispatch a TASK into the correct authorized Workflow, including a direct
senior hotfix path, while preserving ownership, recovery, emergency boundaries and independent review?

## Existing contract and proposed change

[Team roles](../../../docs/engineering/TEAM.md) currently assigns implementation to Software Engineer, independent
technical review to Senior Engineer, and emergency diagnosis/repair to Hotfix Engineer with senior capability.
John's original direct Triage → Senior Engineer hotfix proposal is superseded by the approved use of the existing
senior-capability Hotfix Engineer for repair and a separate Senior Engineer for independent review below.

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

These were proposed decision inputs. The resolution below supersedes extra semantic route selection by Jev when
the classification-to-catalog mapping is deterministic. It does not authorize any actual dispatch.

## Decision questions considered

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

## Resolution — 2026-10-10

John approved the following initial **versioned route catalog**; later catalog changes require their own accepted
configuration/requirements, not a rewrite of previous handoffs:

| Classified work | Initial route | Coordination and limit |
|---|---|---|
| `feature`, `bugfix`, `chore` | Standard single-TASK Workflow | Team Lead → Software Engineer, with category-specific guidance where needed; existing review, QA and PR landing contracts remain in force |
| Human-confirmed urgent `bugfix` | Separate hotfix Workflow | Direct Hotfix Engineer repair → independent non-contributing Senior Engineer review → applicable targeted QA → Release Manager PR handoff; initiating human or named incident owner is the direct contact, without a hidden Team Lead stage |
| Future `release` TASK kind | Separate release Workflow | Team Lead coordinates preparation and all authorized non-human steps; project-specific runbook determines library version tag/publication versus application deployment; Release Manager executes only separately granted effects; human signs releases where currently required |

Enhancement remains `feature`. Release is a **future Planning TASK kind and classification extension**, not a
synonym for today's `chore` or authority to release. Until the kind, project release policy, runbook and route
are supported and approved, return unsupported rather than silently dispatch it. This extends the initial
three-category vocabulary of [WF-027](WF-027-define-task-triage-readiness-and-classification.md) prospectively;
that decision's no-new-kind and no-extra-readiness-gate constraints still hold for existing TASKs. A release
Workflow can start from the initiating authorized request without another routing prompt; signing, tagging,
publication and deployment each retain their required specific human intervention/grants under the project's
approved runbook. Ordinary TASK PR landing never supplies release or deployment authority.

### One preflight, one authoritative start

- Triage is a separately authorized, permission-filtered **preflight** before any execution Workflow or TASK
  claim exists. A deliberate authorized terminal start request or browser Ready → In Progress drag supplies the
  single bounded normal-work start intent. Call Jev for each selected TASK under WF-027, then map a usable
  classification (or labeled declared-kind fallback) to the versioned catalog **deterministically**. Do not add
  another Jev route-selection call or a second normal-route confirmation. The Triage identity has classification
  authority, not implementation, emergency, release or claim authority. Preflight records subject revisions,
  catalog/configuration revision, classification provenance and proposed route; it never changes TASK state.
- For a supported normal or release route within that initial grant, Triage requests the same server-authoritative
  start operation from terminal and browser. PHP revalidates the selected TASK against canonical eligibility,
  live actor/Agent permissions and bounded grant, Planning/context/configuration/catalog revisions, target base,
  budgets, available roles and independence before atomically creating the Workflow, durable dispatch intent and
  exclusive claim. Successful start yields the Workflow identity; the UI gesture is only an intent until then.
  Stale or conflicting facts stop or require fresh classification; neither Jev nor a UI/client may grant execution.
- Hotfix alone requires one **quick human confirmation before start/claim** of the emergency route, incident scope
  and resolved base. No additional branching-strategy confirmation. Its separate bounded emergency grant does
  not authorize merge, release or deployment. A material declared-kind route conflict, unsupported route, or
  inability to establish current authority stops for human resolution, never an automatic route switch.
- Configure GitFlow, trunk-based or an explicit custom strategy at Repository setup with versioned per-route
  base/target mappings. Resolve and verify the base from current configuration (usually `develop` for ordinary
  GitFlow work and `main` for its hotfixes), not from Jev or hard-coded branch names. A missing/invalid base
  blocks start; never fall back from a hotfix to `develop`. A branch-strategy configuration change invalidates a
  stale preflight. Use isolated worktrees and bind handoffs to exact TASK/context/configuration/catalog/grant and
  source/base/head identities; preserve distinct role sessions and permissions.

### Emergency continuation and recovery

- Hotfix shortens discovery and coordination, not isolation, required regression evidence, independent review,
  applicable targeted QA or required verification. A human must explicitly authorize any specific check exception
  with recorded risk, containment and rollback; urgency never waives it or permits self-review. An Agent that
  contributes to repair or acceptance evidence cannot be that repair's independent reviewer even in a fresh
  session. The direct human/incident owner handles blockers, changed scope, exceptions and progress, while the
  application owns Workflow transitions, escalation and audit history.
- If a normal bugfix already started and new evidence demands an emergency production fix, pause the normal
  Workflow. After a quick human emergency confirmation, safely reconcile its claim and effects and create a
  **linked, newly authorized hotfix Workflow for the same TASK**; do not elevate an existing Agent or session or
  simply retarget its `develop` branch. Resolve the production base from Repository policy and carry forward only
  reviewed, explicitly scoped evidence or changes; never merge unrelated unfinished `develop` work into
  production. Preserve the prior Workflow and evidence; uncertain effects/claim ownership pause rather than
  launching a second concurrent executor. This does not create another TASK.
- On GitFlow, after trusted verification of the hotfix merge into the production branch, the **same hotfix
  history** offers one explicit Yes/button authorization to merge that fix back into `develop`. No separate TASK,
  new Workflow, automatic merge or repeat branching-strategy decision. Revalidate the exact source/target and
  effect authority, run applicable checks, record result and reconcile an uncertain outcome before retry. If
  conflicts or incompatible history prevent a safe merge, pause with a concrete resolution path; never claim a
  successful merge-back from a click or a queued command. Trunk-based repositories have no separate `develop`
  merge-back. This post-PR action needs a narrowly approved extension to the current WF-015 PR-handoff stop
  contract, not an implicit merge grant in the ordinary Workflow.
- Use the WF-015 single-owner/idempotent start and recovery contracts: retrying the same authorized request
  resolves to the same Workflow, competing starts report the owner, and crash/retry/duplicate Jev or transport
  delivery cannot duplicate claims, sessions, PRs, tags, merges or deployments. Reconcile uncertain external
  results before attempting another effect. Cancellation quiesces owned activity before releasing a claim;
  rerouting before start refreshes preflight, while rerouting after start requires an explicit new bounded grant
  and safe owner transfer, never a model-initiated permission change.

Planning handoff: extend [TICKET-00034](../../tickets/00034-TICKET.md)'s catalog with a distinct Triage profile
and clarify direct Hotfix Engineer coordination without changing Senior Engineer review independence; extend
[EPIC-00007](../../epics/00007-EPIC.md)/[EPIC-00008](../../epics/00008-EPIC.md)'s later Workflow planning for
preflight routing, hotfix and post-PR merge-back without retroactively gating their approved first execution
slice. Separately plan the `release` TASK kind, repository branching/release policy, project-specific runbooks,
Team Lead preparation and independently granted signing/publication/deployment effects. This decision creates no
TASK, Workflow, claim, Agent, changed permission or real release.
