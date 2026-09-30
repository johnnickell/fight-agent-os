# Roadmap

The accepted sequence is governed by EPICs and their requirements, with current implementation facts in the
[capability inventory](FOUNDATION.md). Markdown remains authoritative until the explicit verified cutover.

1. Package the approved Fight terminal identity and qualify the local execution boundary (EPIC-00006–00007).
2. Deliver the terminal path first: PHP-owned operator authorization, immutable Markdown TASK snapshots, exclusive
   claims and durable Workflow state; recoverable worktree/container/test-database preparation; then distinct
   Team Lead → Software Engineer Pi sessions to durable Awaiting review. Prove two independent TASKs can run safely.
3. Extend the same Workflow through Senior Engineer review → QA → Release Manager landing → human PR handoff
   (EPIC-00008). Proven mechanical documentation reconciliation preserves acceptance.
4. Complete authenticated browser journeys, registered database Planning and browser Planning Agents
   (EPIC-00003–00006), reusing the same application operations and explicit verified Markdown cutover.
5. Deliver project creation and safe instruction editing along their own dependencies (EPIC-00009–00010).

John approved terminal-first sequencing on 2026-09-26. Browser pages, React and database Planning cutover are no
longer blanket prerequisites for terminal execution. Required identity, permission, repository enrollment,
immutable context/Harness versions, PostgreSQL durability and credential isolation remain prerequisites of the
operations that consume them. Markdown remains authoritative before cutover; a revision-bound read adapter feeds
the same eligibility/claim operations, not a second Workflow engine. The console must not infer an operator's
business authority merely from access to a terminal. Exact enrollment and grant contracts are settled before launch.

The next execution TASKs are [sandbox boundary](tasks/00138-TASK.md), [authorized console start](tasks/00139-TASK.md),
[recoverable preparation](tasks/00140-TASK.md), [Pi delegation](tasks/00141-TASK.md), and
[parallel/recovery qualification](tasks/00142-TASK.md). They are incremental delivery slices, not a promise that
existing foundation dependencies disappear or that a container alone is ready for autonomous work.

Current amendments define [Team roles](../docs/engineering/TEAM.md), [sandbox startup](tickets/00032-TICKET.md),
[post-review QA](tickets/00033-TICKET.md), and [team templates](tickets/00034-TICKET.md). Those TICKETs cover selected
cross-cutting requirements, not full decomposition of their parent EPICs; remaining EPIC requirements still need
TICKET/TASK coverage before execution. Prioritize Graphify and Writing for Agents locally, then security audit and
the existing skill backlog. Signed releases, application deployment and operational hotfix automation require
separate accepted implementation scope; self-learning awaits trustworthy metrics. Personal daily workflows are excluded.

The [external architecture study](research/atomic-lessons.md) informs contracts without importing Atomic's runtime.

<!-- planning:epics -->
| EPIC ID | Title | Target | Status |
|---|---|---|---|
| [EPIC-00001](epics/00001-EPIC.md) | Establish project-local planning skills | foundation | done |
| [EPIC-00002](epics/00002-EPIC.md) | Establish project-local execution and design skills | foundation | done |
| [EPIC-00003](epics/00003-EPIC.md) | Establish the web application architecture foundation | foundation | ready-for-agent |
| [EPIC-00004](epics/00004-EPIC.md) | Deliver the invite-only authenticated application shell | foundation | ready-for-agent |
| [EPIC-00005](epics/00005-EPIC.md) | Deliver the registered Planning workspace | registered-planning-workspace | ready-for-agent |
| [EPIC-00006](epics/00006-EPIC.md) | Deliver browser Planning Agents and the trusted Harness | browser-planning-agents-harness | ready-for-agent |
| [EPIC-00007](epics/00007-EPIC.md) | Coordinate one TASK through implementation | coordinator-builder-awaiting-review | ready-for-agent |
| [EPIC-00008](epics/00008-EPIC.md) | Complete independent review and PR publication | review-publish-pr-handoff | ready-for-agent |
| [EPIC-00009](epics/00009-EPIC.md) | Create and register new projects | deterministic-project-creation | ready-for-agent |
| [EPIC-00010](epics/00010-EPIC.md) | Edit repository instructions safely | repository-instruction-editing | ready-for-agent |
<!-- /planning:epics -->

## Planning Frontier

This generated view keeps requirement decomposition and parent closeout visible without changing executable TASK
priority on the [TASK Board](tasks/BOARD.md). Non-terminal EPICs and TICKETs remain listed until they receive their
next-level records; parents with only terminal children remain listed until an explicit closeout.

<!-- planning:frontier -->
### EPICs without TICKETs

| EPIC ID | Title | Status |
|---|---|---|
| [EPIC-00009](epics/00009-EPIC.md) | Create and register new projects | ready-for-agent |
| [EPIC-00010](epics/00010-EPIC.md) | Edit repository instructions safely | ready-for-agent |

### TICKETs without TASKs

| TICKET ID | Title | Parent EPIC | Status |
|---|---|---|---|
| [TICKET-00034](tickets/00034-TICKET.md) | Define the SDLC team templates and role-specific capabilities | [EPIC-00006](epics/00006-EPIC.md) | ready-for-agent |

### Parents ready for closeout review

| Type | ID | Title | Status | Children |
|---|---|---|---|---|
| TICKET | [TICKET-00007](tickets/00007-TICKET.md) | Stabilize application dependencies and smoke baseline | ready-for-agent | 2/2 terminal |
| TICKET | [TICKET-00008](tickets/00008-TICKET.md) | Establish application ownership and orchestration boundaries | ready-for-agent | 2/2 terminal |
| TICKET | [TICKET-00009](tickets/00009-TICKET.md) | Establish authoritative PostgreSQL persistence | ready-for-agent | 6/6 terminal |
| TICKET | [TICKET-00012](tickets/00012-TICKET.md) | Establish the React and component-catalog foundation | ready-for-agent | 5/5 terminal |
| TICKET | [TICKET-00033](tickets/00033-TICKET.md) | Verify reviewed behavior with independent QA before landing | ready-for-agent | 1/1 terminal |
<!-- /planning:frontier -->
