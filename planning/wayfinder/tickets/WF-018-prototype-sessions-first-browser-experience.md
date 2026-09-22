# Prototype the sessions-first browser experience

**Labels:** `wayfinder:prototype`
**Mode:** AFK + HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-012](WF-012-define-browser-planning-and-conversation-ownership.md), [WF-017](WF-017-define-execution-history-and-event-authority.md)

## Question

Which sessions-first information architecture makes live and historical coordinated work understandable at repository scale without overloading the overview or truncating detail?

## Must decide

- Repository selector and server-paginated session table columns: TASK, workflow status, latest meaningful outcome, branch/PR, start, last activity, duration, and usage, with no embedded phase/event lists.
- Active, Archived, and All views; reversible archive/unarchive; whether an active session may be archived and how active execution remains visible.
- Session detail hierarchy for summary, agents/conversations, phase attempts, events, review findings/verdicts, verification evidence, branches/commits/PR, artifacts, usage, interruptions, and failures.
- Incremental loading, stable cursor semantics, server-side pagination, lazy sections, virtualized/render-bounded views, and separate fetches for large payloads/logs/artifacts.
- Empty, loading, partial, unavailable-repository, interrupted, cancelled, stale, and permission-denied states.
- Relationship to browser planning conversations without conflating them with workflow execution sessions.
- What reference-Factory ideas to retain or reject, explicitly avoiding per-card event histories and arbitrary silent display caps.

## Resolution boundary

Create a disposable prototype under `.runs/prototypes/` using fake data that exercises large histories, multiple repositories, active/archive behavior, mobile/desktop layouts, and failure states. Capture screenshots and a verdict, then update this decision. Do not create production React components, API endpoints, database queries, packages, or global configuration.

## Preferences required

John must select the preferred table/detail density, archive behavior for active sessions, and which summary outcomes deserve top-level prominence. The recommendation should use a compact paginated table, explicit Active/Archived/All tabs, lazy detail sections, and archive as visibility only—never pause/resume/cancel.

## Resolution

Write this only after the disposable alternatives are reviewed. Record the selected interaction model, rejected alternatives, accessibility limits, and remaining implementation questions.
