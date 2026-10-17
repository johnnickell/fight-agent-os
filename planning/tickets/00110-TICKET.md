---
id: TICKET-00110
epic: EPIC-00006
title: Work in a Pi terminal workspace with agent activity and live Docker logs
status: ready-for-agent
---

# Work in a Pi terminal workspace with agent activity and live Docker logs

## Problem statement

The human operator needs to keep a primary Pi conversation visible while observing a delegated agent and the
existing Agent OS runtime logs. Inline output obscures this context, and users should not need Herdr or tmux.

## Solution and boundaries

Deliver a custom Pi-based terminal workspace: primary conversation in the left half, selected child-agent
activity at top right and current Docker logs at bottom right. Each right pane occupies approximately one
quarter of a wide terminal; narrow terminals use keyboard-accessible selectable views. Reuse the accepted Fight
identity from [TICKET-00035](00035-TICKET.md). Herdr remains optional, with no required external multiplexer.

Qualify the installed Pi 1.2.0 extension route inside the first delivery TASK. If persistent layout cannot be
implemented cleanly through supported APIs, use a dedicated terminal client over Pi SDK/RPC. Reuse Pi's agent,
provider and session machinery; do not fork Pi, nest another renderer in an extension or create a session engine.
Current upstream documentation is not evidence of installed-version support. Record exact qualified versions.

The first release has a read-only child pane using the existing local build monitor and a human-only live log
pane for the existing, explicitly selected main-checkout Agent OS Compose runtime. Show actual conversation,
child TASK/worktree/attempt and shared runtime identities separately. Selecting an observed child or changing
worktree context cannot change a running agent's directory, grants or session. The main-checkout runtime remains
the same across worktrees, visibly labelled as shared; do not claim its logs are worktree-isolated.

The local trusted adapter reads current Docker stdout/stderr using a fixed bounded operation. It is a display
consumer, not another collector. It does not need the planned logging backend, browser viewer, archives, database
Planning cutover or managed execution. Preserve available emitted content and source/service/timestamp detail;
missing task attribution stays unknown. No new instrumentation or log-coverage guarantee is included.

Per-worktree containers, Compose overrides and automatic worktree-to-runtime switching are separate future work,
not prerequisites or implicitly created TASKs. Existing managed preparation ownership remains with
[TICKET-00032](00032-TICKET.md). Full log search, collection, retention and archive policy remain with the ongoing
Operational logs and failure diagnostics planning. Do not change that map or wait for its delivery here.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Work in the primary conversation | Existing explicit Pi input; select/focus/resize panes | Current local conversation and presentation identity | Existing Pi notifications; no new Workflow event for layout | Existing authorized Pi turn; local presentation changes |
| Observe a child | Select child; pause/resume display | Safe local stage progress and lifecycle state | Existing monitor observations, not execution authority | Bounded read subscription only |
| Follow existing runtime logs | Select explicit runtime/service; pause/resume display | Bounded Docker log tail and source availability | Runtime output, not authoritative Workflow state | Trusted local read process; no container lifecycle mutation |

## Validation and permissions

Preserve active Harness tool restrictions, file screening and protected provider handling through the chosen entry
point. SDK/RPC availability is not a tool grant. Managed identity/linked continuations remain with
[TASK-00325](../tasks/00325-TASK.md); managed durable attachment remains with
[TASK-00352](../tasks/00352-TASK.md). Their unfinished integrations do not block or count as completed local scope.

Docker access belongs to the trusted human-facing host adapter. Do not expose arbitrary commands, the Docker
socket, inherited Docker credentials or the log stream to agent tools/children/model context. Use explicit
operator-selected runtime identity and validated service choices with fixed argument invocation, not shell
interpolation or repository/model-directed configuration. The host operator's Docker access is powerful; a
read-only adapter operation is not a claim that Docker itself has granted read-only permissions.

Treat logs and child output as untrusted display content. Escape terminal controls without inventing a new
pipeline redaction policy. Bound lines, bytes, buffers, reconnects and subprocess lifecycle; disclose gaps,
truncation and unavailable sources. Closing a side pane stops only its reader. Preserve normal local Pi exit
semantics; do not claim durable survival or cancel an observed child as a display side effect. Runtime state
belongs under ignored `var/`; `.runs/` remains execution scratch/evidence, never application runtime storage.

## Acceptance and evidence

- Actual terminal use demonstrates primary input, side-pane focus, resize/narrow fallback, readable textual
  identities and restoration on exit/error; qualify policy hooks through the selected Pi integration.
- Safe progress from two distinguishable local child attempts can be observed without launching a second child,
  mixing stale events or changing execution. Preserve the stage monitor's truthful completion semantics.
- The existing main-checkout Compose log stream is visible with bounded tail, follow, pause/resume and service
  selection. Worktree context changes retain an explicit shared-runtime label and do not imply isolated output.
- Runtime missing, Docker unavailable/denied, stream loss, malformed/oversized lines and hostile terminal controls
  have honest bounded behavior. No container is created, restarted or reconfigured by observation.
- Meaningful behavior tests, direct Pi/Docker/PTY qualification, Before/After evidence and the full `./bin/build`
  belong to the implementing TASKs. Mock rendering alone is not live integration evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00447](../tasks/00447-TASK.md) | Work in the responsive Pi terminal workspace | needs-info |
| [TASK-00448](../tasks/00448-TASK.md) | Observe the selected local child agent in the terminal workspace | ready-for-agent |
| [TASK-00449](../tasks/00449-TASK.md) | Display the existing main-checkout Docker logs in the terminal workspace | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved decomposition — 2026-10-15

John approved one focused Harness TICKET and three TASKs in the isolated planning worktree. He then narrowed the
log pane to displaying current Docker logs, without the complete logging UI/backend. He clarified that containers
currently belong to the main checkout; per-worktree containers and Compose overrides are separate work. These
corrections supersede the draft's observability-supplier hold and automatic worktree-following scope.

- [TASK-00447](../tasks/00447-TASK.md) delivers the local workspace and has no external TASK blockers.
- [TASK-00448](../tasks/00448-TASK.md) delivers local child observation after TASK-00447.
- [TASK-00449](../tasks/00449-TASK.md) delivers the current shared-runtime log pane after TASK-00447, independently
  of TASK-00448 and the broader observability work.

All three are `ready-for-agent` when their explicit dependencies permit, with unranked order. Existing Board
priorities, completed branding, managed execution and the separate observability/grilling work are unchanged.
No new runtime, permission grant, global installation, paid call or implementation follows from planning approval.

Next: select TASK-00447 for local workspace implementation, or use `/skill:next` for the existing Board order.

### Planning verification

Prepared on `feature/pi-terminal-workspace-plan` in an isolated worktree, refreshed to `develop` at `4cdce9a`.
Live and archived inventories in the main checkout and active local worktrees were checked before allocation;
TASK-00446 was already owned, so this split uses TASK-00447–00449 and TICKET-00110. No existing TASK record or
priority was changed. The observability worktree was inspected read-only and not modified.

`./bin/planning-check --write` and `./bin/planning-check` passed with 577 records, 474 active and current generated
views. Local links, new-record whitespace and `git diff --check` passed. These are planning receipts only.
The full `./bin/build` was not run and remains required before commit/publication. No implementation, runtime
qualification, commit, push or PR was performed. The approved current-log scope supersedes the ignored draft.
