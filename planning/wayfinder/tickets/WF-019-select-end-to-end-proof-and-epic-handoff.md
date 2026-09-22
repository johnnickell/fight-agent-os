# Select the end-to-end proof and EPIC handoff

**Labels:** `wayfinder:task`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-011](WF-011-prove-markdown-migration-and-authority-switch.md), [WF-014](WF-014-define-skill-trust-and-harness-distribution.md), [WF-015](WF-015-define-coordinated-task-authority-protocol.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md), [WF-017](WF-017-define-execution-history-and-event-authority.md), [WF-018](WF-018-prototype-sessions-first-browser-experience.md)

## Question

What is the smallest useful end-to-end proof, and what dependency-ordered EPIC grill sequence should carry the accepted umbrella decisions forward without competing with EPIC-00002 through EPIC-00004?

## Must decide

- A thin proof that crosses real boundaries—registered repository, resolved context, authoritative application operation, durable job/session, authorized local runner, one bounded agent phase, stored evidence/events, and browser observation—without prematurely automating the full lifecycle.
- Whether the proof uses existing Markdown authority or requires a disposable database planning slice; it must not disguise a projection as database authority or trigger cutover.
- Explicit proof exclusions, disposable versus production components, success/failure evidence, security controls, cleanup ownership, and human stop points.
- Which existing accepted EPIC/TICKET/TASK dependencies must land first and which can proceed independently.
- Proposed future EPIC boundaries, ordering, child-map handoffs, and grill briefs for repository/planning authority, harness/skills, coordination/runners, and browser observability.
- A first future decision or grill frontier after this map closes; no implementation TASK may be selected from this handoff itself.

## Resolution boundary

This decision produces a sequencing and EPIC-planning brief only. It may recommend one or more grill sessions after human approval. It must not write an EPIC, decompose TICKETs/TASKs, create branches, implement the proof, start agents, migrate planning, or publish work.

## Preferences required

John must choose the proof's user-visible value, acceptable disposable infrastructure, and which existing foundation work may be treated as a prerequisite rather than included. The recommendation should prove one repository and one bounded workflow path before multi-repository scheduling or complete automated work/review/land orchestration.

## Resolution

Write this only after all prerequisites are closed and the proof plus future grill sequence are approved. Link the future EPIC-planning handoff when it is created in a separate operation.
