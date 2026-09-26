# Roadmap

Fight Agent OS begins as a small Slim application and grows through approved planning sessions.

1. Plan the application foundation: development runtime, React client, API documentation, and identity
2. Prove a customized Pi terminal with a companion observatory
3. Build project-scoped planning in the browser, including research, grill sessions, and prototypes
4. Move planning authority into the database through a verified, explicit migration

This is direction, not an implementation commitment or an approved EPIC. See the [foundation brief](FOUNDATION.md).

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
| [EPIC-00006](epics/00006-EPIC.md) | Deliver browser Planning Agents and the trusted Harness | ready-for-agent |
| [EPIC-00007](epics/00007-EPIC.md) | Coordinate one TASK through implementation | ready-for-agent |
| [EPIC-00008](epics/00008-EPIC.md) | Complete independent review and PR publication | ready-for-agent |
| [EPIC-00009](epics/00009-EPIC.md) | Create and register new projects | ready-for-agent |
| [EPIC-00010](epics/00010-EPIC.md) | Edit repository instructions safely | ready-for-agent |

### TICKETs without TASKs

| TICKET ID | Title | Parent EPIC | Status |
|---|---|---|---|
| None | — | — | — |

### Parents ready for closeout review

| Type | ID | Title | Status | Children |
|---|---|---|---|---|
| TICKET | [TICKET-00007](tickets/00007-TICKET.md) | Stabilize application dependencies and smoke baseline | ready-for-agent | 2/2 terminal |
| TICKET | [TICKET-00008](tickets/00008-TICKET.md) | Establish application ownership and orchestration boundaries | ready-for-agent | 2/2 terminal |
| TICKET | [TICKET-00009](tickets/00009-TICKET.md) | Establish authoritative PostgreSQL persistence | ready-for-agent | 6/6 terminal |
<!-- /planning:frontier -->
