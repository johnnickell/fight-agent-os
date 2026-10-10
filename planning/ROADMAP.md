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

John approved the [incremental Harness protection and retrieval milestone](epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07)
on 2026-10-07 for existing human-operated Pi sessions. Prioritize deterministic controls plus Jev screening and
required file relevance screening at an initial 0.70 positive-read threshold. This bounded Harness slice can
proceed before full managed sandbox/browser/database delivery. Accepted [Jev access](tickets/00037-TICKET.md),
[tool guard](tickets/00038-TICKET.md) and [file screening](tickets/00039-TICKET.md) requirements now own it;
access is decomposed into approved [protected setup](tasks/00184-TASK.md) and dependent
[typed judgments](tasks/00159-TASK.md). The guard is decomposed into independent
[deterministic restrictions](tasks/00160-TASK.md) and [semantic screening](tasks/00161-TASK.md), which depends on
those restrictions and the shared judgments. File screening is decomposed into
[single-file screening and instructions](tasks/00162-TASK.md), dependent on shared judgments, followed by
[bounded file sets and caching](tasks/00163-TASK.md). It proceeds independently of guard implementation.
The selected three-TICKET milestone now has approved TASK decompositions; broader EPIC/map planning remains open.
The accepted Jev follow-on now has requirements for [checkpoint/resume](tickets/00040-TICKET.md),
[compaction timing and observability](tickets/00041-TICKET.md), [general judgments](tickets/00042-TICKET.md),
[authorized analysis](tickets/00043-TICKET.md), [change quality](tickets/00044-TICKET.md) and
[PHPUnit usefulness](tickets/00045-TICKET.md), consuming closed WF-029 through WF-031. Checkpoint/resume now has
approved [required checkpoint](tasks/00164-TASK.md), [optional selection](tasks/00165-TASK.md),
[selective repair](tasks/00166-TASK.md) and [retention/holds](tasks/00167-TASK.md) TASKs targeting Pi 1.1.0.
Timing/observability now has approved [deterministic safe compaction](tasks/00168-TASK.md),
[every-turn Jev evaluation](tasks/00169-TASK.md) and [history/incident inspection](tasks/00170-TASK.md) TASKs on Pi 1.1.0.
General judgments now has approved [authenticated typed MCP judgments](tasks/00171-TASK.md),
[many-file resumable stages](tasks/00172-TASK.md) and [qualified answer reuse](tasks/00173-TASK.md) TASKs on Pi 1.1.0.
Authorized evidence now has approved [MCP composition](tasks/00174-TASK.md),
[repository analysis](tasks/00175-TASK.md), [application inspection](tasks/00176-TASK.md) and
[constrained SQL](tasks/00177-TASK.md) TASKs on Pi 1.1.0; analysis routes independently consume composition.
Change quality now has approved [automatic pre-handoff assessment](tasks/00178-TASK.md),
[qualified refresh/reuse](tasks/00179-TASK.md) and [independent reviewer follow-ups](tasks/00180-TASK.md) TASKs on
Pi 1.1.0. PHPUnit usefulness now has approved [changed-case assessment](tasks/00181-TASK.md),
[scoped whole-suite assessment](tasks/00182-TASK.md) and [context-sensitive refresh](tasks/00183-TASK.md) TASKs.
All 20 follow-on TASKs target Pi 1.1.0 and remain unranked with explicit capability blockers. TASK decomposition
is complete for the selected TICKET-00040 through TICKET-00045 scope; implementation and qualification remain pending.
Older browser/Harness planning is not implicitly complete. Broader routing retains its separate triage-map decisions. TASK-00138 retains its qualification requirements, and the Board's
current executable ordering is unchanged by this planning amendment.

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
| [EPIC-00008](epics/00008-EPIC.md) | Complete independent review and PR publication | review-publish-pr-handoff | done |
| [EPIC-00009](epics/00009-EPIC.md) | Create and register new projects | deterministic-project-creation | ready-for-agent |
| [EPIC-00010](epics/00010-EPIC.md) | Edit repository instructions safely | repository-instruction-editing | ready-for-agent |
| [EPIC-00011](epics/00011-EPIC.md) | Deliver durable scoped Agent memory | durable-agent-memory | ready-for-agent |
<!-- /planning:epics -->

## Planning Frontier

This generated view keeps requirement decomposition visible without changing executable TASK priority on the
[TASK Board](tasks/BOARD.md). Non-terminal EPICs and TICKETs remain listed until they receive their next-level
records. [Automatic parent completion](CONVENTIONS.md#automatic-parent-completion) closes eligible parents in
the same operation that completes their children.

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
| [TICKET-00049](tickets/00049-TICKET.md) | Retrieve scoped memory selectively with Jev | [EPIC-00011](epics/00011-EPIC.md) | ready-for-agent |
| [TICKET-00050](tickets/00050-TICKET.md) | Inspect memory and remove prohibited content safely | [EPIC-00011](epics/00011-EPIC.md) | ready-for-agent |
<!-- /planning:frontier -->
