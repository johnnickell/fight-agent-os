# Shape the agent skill suite

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** [WF-001](WF-001-recover-source-conventions.md), [WF-006](WF-006-research-ui-design-skill-sources.md)

## Question

What project-local agent skills must exist before agents build authentication, authorization, and the early dashboard?

## Must decide

- Final skill names for engineering a task, code review, landing/cleanup, and UI/design work.
- Whether to use short names such as `work`, `review`, and `land` instead of any `fight-` prefix.
- The scope boundary between planning skills and execution skills.
- The minimum DDD, CQRS, ADR, test, and verification rules each execution skill must enforce.
- Whether worktree coordination is part of `work` now or a separate later skill.

## Resolution boundary

This ticket may settle the first execution and design skill suite and authorize TICKET/TASK planning for those skills. It must not implement authentication or dashboard features.

## Resolution

Write this only when the decision is closed. Link the created skill-planning EPIC/TICKETs or the implementation handoff.
