---
id: TICKET-00110
epic: EPIC-00006
title: Work in a Pi terminal workspace with agent activity and live Docker logs
status: done
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

Qualify the currently installed Pi's supported extension APIs and behavior inside the first delivery TASK. If
persistent layout cannot be implemented cleanly through those APIs, use a dedicated terminal client over supported
Pi SDK/RPC. Reuse Pi's agent, provider and session machinery; do not fork Pi, nest another renderer in an extension
or create a session engine. Never pin Pi to a version number or require version equality. Record required
capabilities and actual tested versions as evidence only; documentation alone and a current version number do
not establish runtime behavior or future compatibility.

The first release has a read-only child pane using the existing local build monitor and a human-only live log
pane for the existing, explicitly selected main-checkout Agent OS Compose runtime. Show actual conversation,
child TASK/worktree/attempt and shared runtime identities separately. Selecting an observed child or changing
worktree context cannot change a running agent's directory, grants or session. The main-checkout runtime remains
the same across worktrees, visibly labelled as shared; do not claim its logs are worktree-isolated.

The local trusted adapter reads current Docker stdout/stderr using a fixed bounded operation. It is a display
consumer, not another collector. It does not need the planned logging backend, browser viewer, archives, database
Planning cutover or managed execution. Preserve available emitted content and source/service/timestamp detail;
missing task attribution stays unknown. No new instrumentation or log-coverage guarantee is included.

Per-worktree container provisioning and Compose overrides remain separate future work. Automatic observation
switching to existing worktree test stacks is now approved in the
[2026-10-20 follow-up](#approved-session-and-worktree-following--2026-10-20), after the first release.
Existing managed preparation ownership remains with
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
| [TASK-00447](../tasks/00447-TASK.md) | Work in the responsive Pi terminal workspace | done |
| [TASK-00448](../tasks/00448-TASK.md) | Observe the selected local child agent in the terminal workspace | done |
| [TASK-00449](../tasks/00449-TASK.md) | Display the existing main-checkout Docker logs in the terminal workspace | done |
| [TASK-00502](../tasks/00502-TASK.md) | Follow current-session children and their worktree test logs automatically | ready-for-agent |
| [TASK-00503](../tasks/00503-TASK.md) | Launch the Pi terminal workspace globally from any project | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved global workspace command — 2026-10-20

John prefers a global command because repository `bin/*` scripts are generally Docker execution wrappers.
[TASK-00503](../tasks/00503-TASK.md) delivers a package-owned `fight-terminal` command on PATH, preserving the
caller's project/worktree and existing Harness policy. It includes user-level installation/update/removal,
retirement of `bin/terminal` and migration of current launch/resume help. Historical receipts remain unchanged.
The initial local launcher was an implementation entry point, not the desired distribution interface.

This is independently deliverable after completed TASK-00447; neither TASK-00449 nor TASK-00502 blocks it. Normal
`pi` stays available and no global installation is performed by planning. The actual installation mechanism must
be qualified: a Pi package declaration alone does not establish a command on PATH. Existing Board priority/order
is unchanged. Planning validation for the amendment is recorded below.

### Approved session and worktree following — 2026-10-20

John approved [TASK-00502](../tasks/00502-TASK.md): automatically follow the current session's active child,
provide a selector for concurrent children and pin/unpin, and follow the observed worktree's existing test Docker
stack. With no selected child, logs follow the primary session's worktree. Trusted session-child registration and
verified worktree/test-stack associations are included because the initial monitor and shared-runtime reader do
not establish them. Missing or ambiguous associations remain visible; no silent main-checkout fallback is allowed.

This supersedes the original automatic-switching deferral for this follow-up only. TASK-00449 retains its original
shared-runtime delivery scope. Container provisioning/Compose overrides, managed execution and the full logging
backend remain separate. TASK-00502 depends on TASK-00448 and TASK-00449, stays unranked and does not alter existing
Board priorities. Pinning and switching affect observation only, never execution permissions or directories.

Prepared on `feature/terminal-context-follow-plan` from `develop` at `c4e4b7b`, refreshed to `f59f870` before
publication, in an isolated managed worktree.
Active-worktree task inventories were checked before allocating TASK-00502 and TASK-00503. `./bin/planning-check --write` and
`./bin/planning-check` passed with 639 records, 500 active and current generated views. Local planning links and
whitespace checks passed. TASK-00449 merged during publication preparation, so both follow-up TASKs now have
completed prerequisites.
The refreshed records preserve its completed state. Implementation and runtime qualification of the planned
features remain outstanding.

Publication gate: `./bin/build` passed in 493.32 seconds in a temporary, uniquely named Docker check stack mounted
to this worktree, with matching locked dependencies mounted read-only. Receipt:
`.runs/build/20261020T235535Z-508/receipt.json`; source remained unchanged during the gate. A first gate stopped at
OpenAPI freshness because this fresh worktree lacked generated artifacts; the owning export commands prepared
ignored OpenAPI/validation artifacts before the successful rerun. Initial stack startup also required preparing
empty dependency mount directories. No tracked implementation files changed. Bootstrap/Sass deprecation and
asset-size warnings remain disclosed in build output. The final receipt-note edit is verified by planning and
whitespace checks; it does not claim a second full build. These checks validate publication of the plan, not
implementation of either TASK or independent technical/behavioral acceptance.

### Local workspace candidate — 2026-10-17

TASK-00447 now has a dedicated Pi RPC client (`./bin/terminal`), owned focus/layout/lifecycle behavior tests and
honest side placeholders; see its current completion notes and [workspace runbook](../../harness/pi/WORKSPACE.md).
The coordinator recovered owned RPC transport and real PTY qualification (active Harness, wide/narrow,
side-input isolation, harmless shell and terminal restoration), then completed a separate genuine provider turn
with native status/read tools and fixture marker. Actual provider-reported model identity remains unavailable.
Real wide/narrow terminal images were captured and inspected, but show disposable-HOME held state, not active
provider conversation; the new feature was Not present before. Exact-window GUI Tab input was refused without
dispatch; PTY provides keyboard proof. No light-theme or child-tab image was collected. The dedicated runtime
is prepared. After freezing all 12 owned staged paths, the coordinator executed native
`./bin/planning-check && ./bin/build`: both passed, full build 206.636 seconds, receipt
`.runs/build/20261017T230157Z-2411/receipt.json`. Engineer verified the tested snapshot/file hashes before the
Markdown-only evidence update. This is fresh coordinator-observed pre-commit evidence, not a self-run Engineer
or independent acceptance receipt. The literal post-commit coordinator gate remains pending before Review.
Earlier resumed checks passed 391 Harness tests and 32 workspace checks; the full gate's narrower Harness
selection ran 329 tests. TASK-00447 records exact counts, warnings and limitations; prior runtime/provider/capture
holds are resolved history. Review/QA must assess remaining native-only/attachment/custom-component/host
limitations; none is silently accepted as an exclusion.
This is not acceptance; TASK-00448/00449 producers and managed integrations remain undelivered. No Pi version
pin was introduced.

### Superseding Pi qualification policy — 2026-10-17

The user's authoritative amendment is: “Pi moves quickly. we should NEVER pin to a version number. We are on
1.3.0 now, so do that”. Current requirements now qualify the installed runtime by supported APIs and actual
behavior, not a fixed Pi release. TASK-00447's obsolete exact-version access hold is cleared; historical receipts
remain history. Pi 1.3.0 is the installation observed during recovery, not a replacement pin. Root AGENTS.md owns
the lasting project convention. No unrelated historical records, dependency/model pins or global configuration
are changed, and the local presentation scope and required policy/terminal qualification are unchanged.

At this amendment checkpoint TASK-00447 was in prerequisite recovery/initial implementation and its RPC probe
still required a native one-command decision. The coordinator's later successful recovery and the candidate
above supersede that prerequisite; this historical amendment itself claimed no implementation or acceptance.

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
