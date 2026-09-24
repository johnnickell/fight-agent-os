# Prototype the sessions-first browser experience

**Labels:** `wayfinder:prototype`
**Mode:** AFK + HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-012](WF-012-define-browser-planning-and-conversation-ownership.md), [WF-017](WF-017-define-execution-history-and-event-authority.md)

## Question

Which sessions-first information architecture makes live and historical coordinated work understandable at repository scale without overloading the overview or truncating detail?

## Must decide

- Repository selector and server-paginated session table columns: TASK, workflow status, latest meaningful outcome, branch/PR, start, last activity, duration, and usage, with no embedded phase/event lists.
- Current versus Archived result switching; reversible archive/unarchive; whether an active session may be archived and how active execution remains visible.
- Session detail hierarchy for summary, agents/conversations, phase attempts, events, review findings/verdicts, verification evidence, branches/commits/PR, artifacts, usage, interruptions, and failures.
- Incremental loading, stable cursor semantics, server-side pagination, lazy sections, virtualized/render-bounded views, and separate fetches for large payloads/logs/artifacts.
- Empty, loading, partial, unavailable-repository, interrupted, cancelled, stale, and permission-denied states.
- Relationship to browser planning conversations without conflating them with workflow execution sessions.
- What reference-Factory ideas to retain or reject, explicitly avoiding per-card event histories and arbitrary silent display caps.

## Resolution boundary

Create a disposable prototype under `.runs/prototypes/` using fake data that exercises large histories, multiple repositories, active/archive behavior, mobile/desktop layouts, and failure states. Capture screenshots and a verdict, then update this decision. Do not create production React components, API endpoints, database queries, packages, or global configuration.

## Preferences required

John must select the preferred table/detail density, archive behavior for active sessions, which summary outcomes deserve top-level prominence, and whether the structural shell needs evidence now or belongs wholly to later visual design. The recommendation should use a compact paginated table, a whole-result Archived toggle, lazy detail sections, and archive as visibility only—never pause/resume/cancel.

## Resolution

The [durable prototype evidence](../research/WF-018-sessions-browser-prototype-evidence.md) records the disposable fake-data alternatives, accepted revision, eleven captures, 43 passing browser assertions, static checks, process cleanup, and evidence limits. The runnable source and bulky captures remain under ignored `.runs/prototypes/wf-018/`; they are disposable and must not be promoted into production.

### Overview and pagination

Select the compact repository-scoped table rather than the operational card grid. One row represents one Workflow and shows, in priority order:

1. TASK title/reference and Workflow identity;
2. authoritative Workflow status with compact Review state;
3. latest meaningful outcome and consequence or next action;
4. branch and PR/merge reference;
5. start and last meaningful activity/freshness;
6. known active duration and provider-reported usage;
7. visibility action.

Do not embed phase lists, event streams, tool calls, agent lanes, transcript excerpts, charts, or mini-timelines in rows. At narrow widths, present the same priorities as labelled records without document-level horizontal scrolling; production must verify whether responsive native-table CSS preserves assistive-technology semantics or use a separate semantic list.

Use a default 25-record server page and opaque cursor over a stable query snapshot ordered by last meaningful activity with Workflow identity as a deterministic tie-breaker. Newer/Older traverses that snapshot; explicit refresh begins another. Counts may be projected separately but must disclose staleness. Do not expose database offsets, fetch event history per row, render thousands of records, or silently cap older history.

### Archive model

Reject Active/Archived/All tabs. Use one labelled **Archived** toggle that replaces the complete current result set—heading, count, state, table, and cursor chain—with an independently server-paginated archived result set. Switching resets to that side's first page. Do not provide a combined All result initially.

Archive and Restore change visibility only. They never pause, resume, stop, cancel, approve, publish, or merge a Workflow. A running Workflow may be archived after explicit confirmation, but it remains visible in a repository-level Running now summary with a direct detail link and remains in archived results. Restore returns it to current results without changing execution. If production cannot guarantee that independent running indicator, disallow archiving non-terminal Workflows rather than hiding active execution.

### Detail hierarchy

Keep four separately loadable detail sections:

- **Summary:** status, meaningful outcome, next action, freshness, Review state, immutable Git subject, PR, phase/attempt/recovery summary, interruption/failure, duration, usage, and role-specific AgentConversation lifecycle metadata.
- **Changes:** complete bounded syntax-highlighted `base…head` diff for the exact submitted or accepted subject. Keep every file discoverable; load file bodies lazily and route oversized or binary content to explicit Artifacts rather than silently omitting it.
- **Activity:** visibly distinguish authoritative decisions from sanitized observations; use filterable bounded cursor windows and Artifact-backed full safe output. Expose no Pi transcript, raw prompt, hidden reasoning, credential, provider signature, or unsanitized payload route.
- **Evidence:** independent Review verdict/findings and criterion links, verification summaries, Git state, Artifacts, sensitivity, retention, and hold information.

Keep unresolved blockers, Review state, exact Git subject, and unavailable/stale warnings visible in Summary even when full detail lives elsewhere. Each section owns its loading, empty, denied, partial, stale, retry, and purged/tombstoned states so one failed query does not erase the dossier. Planning conversations remain under Planning; Workflow details may link their TASK and grant but never present those conversations as execution history.

### Structural shell and theme boundary

Use a collapsible primary sidebar, thin contextual utility bar, and modest footer as the structural shell direction. Narrow layouts start with navigation closed; labelled keyboard controls open/close it and return focus deliberately. Sidebar visibility grants no authority.

This approves structure, not the prototype's dimensions, colors, typography, spacing, icons, responsive treatment, footer copy, or final component design. The deeper visual-language and independent-review work under [TICKET-00014](../../tickets/00014-TICKET.md), [TASK-00034](../../tasks/00034-TASK.md), and [TASK-00035](../../tasks/00035-TASK.md) owns the application-wide `system`/`light`/`dark` behavior and theme-switcher placement. Sessions inherit that one theme model rather than introducing another.

### Reference interpretation and limits

Retain from the reference Factory useful live status, distinct Workflow/phase/Agent identities, evidence-backed checks, cursor windows, and visible partial repository failure. Reject SQLite/file mirror authority, per-card polling, fixed-height truncation, phase-heavy overview cards, archive-as-reviewed semantics, and arbitrary visible-item caps.

The prototype exercised 2,381 fake Workflows through 25-row pages, 12,840 observations through 50-row windows, a 37-file immutable diff, multiple repositories, current/archive behavior, desktop/mobile reflow, and loading, empty, partial, stale, unavailable, denied, interrupted, failed, cancelled, Review, PR, and active-archive states. It did not test assistive technology, forced colors, zoom matrices, final themes, browser diversity, real query performance, cursor expiry, Mercure recovery, storage, permission races, archive concurrency, redaction, or production diff parsing. Screenshots and automation establish no WCAG conformance.

Exact routes, component and query boundaries, permission names, cursor encoding/expiry, stale thresholds, diff renderer and thresholds, virtualization, cache policy, final copy, shell styling, and theme-switcher placement remain downstream implementation/design choices. This decision creates no production component, API, database query, package, authority change, or visual-design acceptance.
