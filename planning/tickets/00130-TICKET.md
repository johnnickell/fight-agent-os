---
id: TICKET-00130
epic: EPIC-00006
title: Run local Build through upstream agents with browsable activity and stack logs
status: ready-for-agent
---

# Run local Build through upstream agents with browsable activity and stack logs

## Problem statement

John expects local Build to use Claude-style upstream agents while preserving independent Work/Review/QA/Land
and useful Herdr panes. Existing Build explicitly launches Python-owned process stages. TICKET-00129 /
TASK-00554 delivered upstream companion qualification and passive SDK observation, not the Build cutover or
ordinary installed-package rollout. The operator also reports an empty lower pane and wants stack-wide logs,
automatic active-worktree following, an explicit main-stack override and service filters rather than a source form.

## Solution and boundaries

John approved these requirements and the four-TASK decomposition on 2026-11-02, following the isolated planning
draft and explicit pane-name/log-following amendments. This is local operator-authorized terminal integration
under EPIC-00006, not server-managed Execution, sandbox isolation or database-authoritative workflow delivery.

- Keep Build as the sole stage/evidence coordinator; upstream owns SDK execution; Herdr observes. Disable
  upstream workflow scheduling and automatic worktree management. Do not copy its execution engine.
- Bind each fresh stage session to trusted stage/TASK/checkout/attempt/effects before execution. Profiles, prompts,
  agent names and pane labels cannot grant independent stage authority. Do not mutate shared `process.env` to
  impersonate session-specific launcher identity. Qualify a supported initialization/parent-association mechanism;
  missing capability holds the affected route, with a small upstream hook preferred over monkey patches/forks.
- Preserve per-role model/thinking pins, governing context, selected checkout, one Compose runtime, repair
  accounting, canonical reports and fresh independent Review/QA without inherited Engineer conversation.
  SDK terminal state is not stage acceptance; unavailable display never permits retry or executor substitution.
- Use the existing verified native parent/custody and passive observation path. Rootless direct-spawn/RPC,
  foreign, nested and replaced records remain refused; do not infer ownership from transcript names or labels.
- Name the panes **AGENTS** and **LOGS**, including narrow presentations, without `human only` suffixes.
  Explain observation-only semantics in help/details. AGENTS provides a keyboard list/detail/back interface for
  verified live and retained completed top-level attempts, with existing pin/pause and bounded safe activity.
- LOGS defaults to all services of the verified main-checkout stack when no eligible active worktree is selected:
  the useful equivalent of `docker compose logs -f`, with bounded recent tail and service/container prefixes.
  Multiple services in one verified stack must not require a per-service picker. Genuine project ambiguity still
  requires an inspected choice. Do not evaluate arbitrary Compose files or offer a shell in the pane.
- In Follow mode, LOGS follows the verified active Build/selected-agent worktree and discovers late runtime
  startup. Mere creation of an unrelated Git worktree cannot steal the view. An active worktree without a stack
  is visibly unavailable, not silent main fallback. Idle returns to main unless an explicit override is held.
- Limit LOGS source/filter interaction to **Follow active**, **Main** (explicit main pin), and **Services** (All or
  selected services), plus ordinary display navigation/help. Returning to Follow releases Main. Stack changes
  reset filters to All, clear stale output and preserve explicit Main intent. No container lifecycle controls.
- Preserve exact source/generation verification, bounded readers/history, safe rendering, truthful quiet versus
  unavailable states and verified runtime-log context. Logs and transcripts never enter model context.
- Qualify installed-layout/native behavior privately; applying everyday package/profile changes needs explicit
  operator authority at a safe session boundary. Never reload the active TASK-00544 parent as a planning effect.
- Migrate Build's real callers/instructions and remove superseded launch code, adapters, checks and docs after
  replacement qualification. Temporary mixed execution in TASK-00560 is removed by TASK-00561's cutover;
  any retained non-Build monitor path needs a demonstrated surviving caller, not compatibility speculation.

Out of scope: managed Runner/server-side grants, production isolation, global rollout, a second scheduler/child
registry, MCP adoption, unbounded transcript collection, nested-agent trees, cross-parent dashboards, pane steering
or cancellation, automatic worktree/runtime provisioning and publication authority derived from display controls.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Execute an authorized stage | Existing explicit Build invocation through supported upstream execution | Current authorization, session/checkout/model identity and capabilities | Actual upstream lifecycle and existing stage outcomes | Fresh correctly bound worker, permitted role effects and ordinary handoff |
| Advance or repair | Existing evidence-based Build progression | Canonical reports, candidate identity, remaining repairs and actual liveness | Existing report/outcome facts | Correct next fresh stage or finite hold; no duplicate execution/publication |
| Browse agents | AGENTS list/select/back/pin/pause | Verified current-parent attempts and bounded history | Existing observation updates | Display selection and existing verified log context only |
| Follow stack output | LOGS Follow active/Main | Verified main/active checkout, project and current container set | Existing context/runtime lifecycle and Docker output | Bounded all-service tail/follow; no provisioning |
| Filter output | LOGS Services: All or inspected subset | Current stack services | Display filter change | Selected service output, prefixed sources; filters reset on stack change |
| Adopt installed integration | Explicit owning package/profile update procedure | Effective packages, agent definitions, collisions and supported APIs | N/A: local setup needs no new product event | Deliberate selected-installation update only |

## Validation and permissions

Only an explicit authorized Build invocation or operator workflow selects role effects; a subordinate assistant
is not automatically an independent assessor. Trusted session initialization must not accidentally inherit the
parent coordinator's role or another SDK worker's process-global state. Reconcile timeout, shutdown/reload,
provider failure and uncertain execution before replacement. Reader closure is passive; upstream parent shutdown
may abort workers and must not be presented as durable survival.

Use exact canonical registered checkouts and verified existing local runtime identities. Never widen admission
merely to fill the pane, read arbitrary projects, start/stop containers, or use main as hidden worktree fallback.
Treat output as untrusted display content. Preserve credentials/private source outside reports and configuration.
New application HTTP/database authorization, Domain events and migrations are N/A to this local integration;
existing trusted-local limitations remain, without a claim of OS-enforced isolation.

## Acceptance and evidence

TASK-00560 delivers a usable upstream Engineer path and ordinary independent Review handoff. TASK-00561 delivers
the complete independent local Build replacement and caller retirement. TASK-00562 delivers AGENTS browsing and
simple pane names. TASK-00563 delivers stack-wide LOGS defaults, Main/Follow and service filtering. Each owns its
focused Harness checks, installed/native interaction and applicable inspected terminal captures, normal full
`./bin/build`, independent Review and applicable behavioral/visual QA. No product-suite tooling tests, separate
infrastructure demonstration, acceptance-only TASK or automatic global rollout is required.

Qualify supported Pi/upstream/Docker APIs by behavior and record tested versions/source identities; never pin Pi.
Mock/source-only evidence cannot establish actual installed terminal acceptance. Record incomplete capabilities
and distinguish fresh from qualified reused evidence, implementation acceptance, publication and human merge.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00560](../tasks/00560-TASK.md) | Execute a real Build Engineer stage through an upstream agent | needs-info |
| [TASK-00561](../tasks/00561-TASK.md) | Complete independent Build delivery through upstream agents and retire replaced launchers | ready-for-agent |
| [TASK-00562](../tasks/00562-TASK.md) | Browse live and completed agents in the Herdr AGENTS pane | ready-for-agent |
| [TASK-00563](../tasks/00563-TASK.md) | Follow the active Compose stack with stack-wide logs and simple service filters | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved the new requirements and TASK split on 2026-11-02 in response to the isolated planning proposal,
including AGENTS/LOGS naming and the additional stack-wide LOGS slice. Planning checkout is
`.runs/worktrees/build-subagents-planning`, branch `feature/plan-build-subagents`, from develop `db1681c`.

TASK-00544 is running independently and remains the owner of missing/broken sequential followers, late/replaced
runtime discovery and truthful no-stack/quiet states. Consume its accepted changes before shared-path work;
do not expand that active repair or treat the reported empty pane as an established root cause. Completed
TICKET-00129/TASK-00554 and TICKET-00110 remain historical observation owners, not reopened parents.

TASK-00560, TASK-00562 and TASK-00563 depend on TASK-00544 and TASK-00554. TASK-00561 depends on TASK-00560.
The UI/log slices do not block the full stage cutover. New TASKs are unranked; existing Board priority and unrelated
map phases remain unchanged. Planning approval authorizes record creation only, not implementation, installation,
provider calls, source repair, publication or disturbance of the running TASK-00544 session.

Planning verification on 2026-11-02: baseline `./bin/planning-check` passed (710 records / 514 active).
After record creation, `./bin/planning-check --write` and `./bin/planning-check` passed (715 records / 519 active),
and `git diff --check` passed. Only planning records/current handoffs and generated views changed. At the initial
planning checkpoint the files were uncommitted and no publication was claimed. John then explicitly authorized
committing, non-force push, a planning-only PR to develop and retirement of this planning worktree's owned
resources. Future implementation TASKs remain unstarted; their `pr` fields must not point to the planning PR.

Pre-publication `./bin/build` was attempted normally but stopped before application phases because this planning
worktree has no running Compose web service. No application stack was started or borrowed; this is not fresh
application-gate evidence. Focused planning validation is the completed verification for this planning-only change.
No installed-package changes or interruption of TASK-00544 were authorized. Publication and cleanup results belong
in the actual planning PR, not a tracked delivery queue or a claim of implementation acceptance.
