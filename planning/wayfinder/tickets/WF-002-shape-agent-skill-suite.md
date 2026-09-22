# Shape the agent skill suite

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
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

[EPIC-00002](../../epics/00002-EPIC.md) establishes the project-local `work`, `review`, `land`, `design`, and `design-review` suite with short names and no imported Factory workflow machinery. The skills consume approved planning rather than creating or decomposing it. `work` owns implementation and worktree coordination, `review` owns independent signoff, and `land` finalizes the documented `done` state, publication readiness, and bounded TASK-owned cleanup before human merge control.

Design remains evidence-first and disposable under `.runs/prototypes/`, with independent rendered review and a project-owned Bootswatch-like visual-language specimen. Concise project-owned engineering and design standards support the skills. TICKET/TASK decomposition remains a separate operation.
