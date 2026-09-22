# Define the coordinated TASK authority protocol

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-012](WF-012-define-browser-planning-and-conversation-ownership.md), [WF-014](WF-014-define-skill-trust-and-harness-distribution.md)

## Question

What authoritative state machine and handoff contract lets a coordinator carry one approved TASK through work, independent review, bounded revision, and PR publication without expanding human-granted authority?

## Must decide

- Coordinator, worker, independent reviewer, and publisher responsibilities, identities, tool access, session separation, and independence requirements.
- Atomic eligibility check and claim using the canonical next-TASK query; lease/claim ownership, duplicate prevention, cancellation, retry, stale-claim recovery, and release.
- Commit-specific handoffs: approved TASK revision, base/head, changed tree, checks, artifacts, findings, verdict, limits, and preserved unrelated work.
- Revision loop policy: blocking versus non-blocking findings, dispositions, conflicting findings, unavailable evidence, implementation changes invalidating acceptance, retry/time/cost limits, and human escalation.
- Exact acceptance rule: reviewed implementation commit, every criterion evidenced, required checks passing, no unresolved blocker; acceptance is not a defect-free guarantee.
- Separate treatment of mechanical landing-record commits without allowing substantive changes to bypass re-review.
- Checkout/worktree ownership, feature branch/base policy, cleanup ownership, and protection of unrelated local work.
- Workflow-start grants for commit, push, and PR create/update; `land` remains publisher, never merger, and merge remains human-controlled.
- TASK done versus unmerged PR and prerequisite availability; compare waiting for merge with explicitly supported stacked PRs rather than assuming stacking.
- Scope for continuing to additional eligible TASKs, including count/time/cost limits and a visible stop/resume boundary.

## Resolution boundary

This decision defines application workflow policy and phase contracts. It must not start a coordinator, claim a TASK, create a branch/worktree, run agents, push, publish, or merge. Runner transport belongs to WF-016; event persistence belongs to WF-017.

## Preferences required

John must choose publication authority at workflow start, acceptable automated revision limits, whether any stacked-PR mode is worth supporting, and how far one coordinator may continue. The recommendation should start with one TASK per explicit grant, no stacking, commit-specific acceptance, and a hard return to human control after PR handoff.

## Resolution

Write this only when the state machine, authority grants, acceptance invalidation, escalation, and dependency policy are approved.
