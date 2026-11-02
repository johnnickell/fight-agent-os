# Upstream subagents and Fight observation

[TASK-00554](../../planning/tasks/00554-TASK.md) implements a passive bridge to
[`@tintinweb/pi-subagents`](https://github.com/tintinweb/pi-subagents). Private installed/native qualification
has run and the full local gate passed during delivered-base recovery. Independent Review/QA remain pending;
see the TASK's current completion checkpoint. This is not global rollout or acceptance.

## Select the supported profile

For a new, explicitly authorized installation:

1. Install both the Fight package and the companion through Pi's package settings. The qualified companion is
   `npm:@tintinweb/pi-subagents@0.22.1`, source `fc089c4b7ab0f1185453b2e53027997c1f637e38`.
   Pi itself is capability-qualified, never version-pinned. Tested Pi `1.4.0` and Herdr `0.9.5` are evidence only.
2. Review the shipped `profiles/subagents.json` and explicitly apply it as the project's `.pi/subagents.json`
   (or private agent-directory `subagents.json`). Merge existing settings deliberately; never overwrite another
   operator's configuration. Project settings override agent-directory defaults. Restart only the intended session.
3. Confirm effective settings in upstream `/agents`: **worktreeIsolation false**, workflows/scheduling disabled,
   nesting disabled, strict agent files, no default-agent fallback and only Fight package agents. Local agent
   definitions override package definitions; inspect collisions rather than assuming package provenance.
4. Use the upstream **Agent tool**, explicitly selecting a shipped type. `pi -e` alone does not make packaged
   agents discoverable: upstream reads Pi's configured `packages[]`. Inspect `/fight-subagents` for capability,
   transport readiness and the last bounded refusal diagnostic. Open `/fight-workspace` in native Pi/Herdr as usual.

The shipped agents are `fight-engineer`, `fight-explorer`, `fight-review-assistant` and `fight-qa-assistant`.
Only the engineer requests editing and shell tools; the others request read-only built-ins. They load Fight's
extensions through upstream's **unscoped** selector `agent-os-pi`, not a scoped package name interpreted as a path.
They inherit the parent's selected model and thinking level, append governing context, and disable raw output-file
transcripts. The profile disables automatic upstream worktree creation/cleanup. Fight's selected checkout, native
parent/custody and single Compose project remain authoritative.

These are subordinate assignments, **not trusted independent Work/Review/QA/Land launches**. In particular, review
and QA assistants return advice to their parent, never canonical stage reports or acceptance. Profiles cannot
promote workflow roles, provision resources or authorize publication. Tool allowlists and fresh contexts are not
OS isolation. Fight extensions initialize their own session-local state; absent enrollment/Jev setup stays absent.
Do not claim all parent protections automatically propagate: the opt-in deterministic tool policy refuses Agent
at the parent tool boundary, so delegation under that selected policy is unavailable rather than a bypass.

## Supported observation boundary

The bridge uses upstream lifecycle events and its documented
[manager registry](https://github.com/tintinweb/pi-subagents/blob/master/docs/rpc.md#the-manager-registry).
An event/tool argument supplies only a lookup candidate. Admission requires the actual record's exact
`rootSessionId`, no nested/workflow owner, stable record/session object identity, canonical registered worktree,
and the existing native parent's process/file lifetime. SDK children use inherited native-parent IPC;
the separate monitor route's ancestry checks are unchanged.

Foreground and background Agent spawns are observed, as are same-session resumes. Upstream emits a started event
for background resume but not foreground resume: the latter is armed from Pi's Agent-tool execution notification
and waits for an actual record run transition. Input never supplies ownership. Resume creates a fresh display
attempt identity without recreating or executing the SDK session. Existing pins deliberately stay on the old
attempt until changed/unpinned; an active explicit selection is not stolen by concurrent output.

In the qualified upstream, RPC/direct `spawnTopLevel` strips root identity. Those rootless routes are **not
observable**, even if upstream executes them successfully. They produce `root-association-unavailable`, not a guessed
association from cwd, agent metadata or transcript filenames. Nested/workflow, missing, replaced and foreign
records are likewise refused. Missing registry/session APIs, capacity and the five-second session/resume window
have finite diagnostics. There is no requirement to make every upstream route supported, no monkey patch and no
second session engine.

## Output and lifecycle

At most eight agents are sampled once per second. Only bounded assistant text and tool **names** are forwarded;
no reasoning, arguments, tool-result bodies or transcript files are ingested. Attachment and completion recover
the latest bounded finalized assistant text from the **live SDK session**, including output produced before
attachment. This is an activity view, not lossless history. Same-sample tool activity preserves early prose;
transport coalescing preserves terminal frames through readiness/backpressure and bounded shutdown flushing.

The signed existing custody/Herdr reader retains selection/pin/pause, history bounds and verified log context.
Completed SDK attempts remain selectable so final output is readable. An unchanged refresh preserves an
eligible completed selection and its paused display. Unpinned completion still yields to live work; a pin keeps
its chosen attempt, and lost custody never retains log authority.

The daemon shares its eight child slots with existing monitors. Under admission pressure it retires the oldest
terminal SDK attempt, excluding the current selection and pin from the reader's existing preference. Live SDK
work and monitor entries are never retired by this policy; history remains bounded without a second registry.
If no eligible history can retire, inherited IPC returns a fixed refusal diagnostic to `/fight-subagents` and
detaches only that display reader. SDK execution is unchanged. SDK statuses (`completed`, `steered`,
`aborted`, `stopped`, `error`) are shown as upstream/SDK outcomes, **not fabricated process exits or acceptance**.
The native process-monitor route keeps its distinct real exit-code vocabulary.

Closing/reopening Fight readers or companions is passive: subscriptions/layout are disposed, not SDK agents.
However upstream itself aborts agents and disposes their sessions on Pi `session_shutdown`, including `/reload`,
new/resume/fork and quit. Do not advertise whole-runtime reload as passive or agent-preserving. Native reload
invalidates old companions/custody; reopen explicitly for the new context. Fight offers no launch, steer or
cancel API and never sends pane output into Pi/model context.

## Qualification and remaining gate

Engineer evidence is in canonical ignored `.runs/tasks/TASK-00554/runs/engineer-3/`, indexed by the Work handoff.
Real installed native Pi used read-only SDK access to configured operator authentication; no credentials were
copied into the private agent directory or evidence. The requested `openai-codex/gpt-6.1-sol` / `high` remained
selected. Actual iTerm/Peekaboo images show Herdr foreground/background/concurrent agents, early/final output,
selection/pin/pause, passive reader/companion closure and reopening, same-session foreground resume, rootless
refusal, missing package and native runtime reload/shutdown. Controlled Harness checks separately exercise
replacement/foreign/nested refusal and transport bounds; they are not live acceptance or independent QA.

The complete direct host Harness run passed **657/657**, zero skips, using a canonical temporary path (macOS
`/tmp` is a symlink and fails existing private-storage checks). Earlier failed host-environment attempts remain
in evidence. At that earlier checkpoint, `./bin/build` was held before phases by the legacy preparation wrapper
and full worktree startup needed the separately operated shared ingress. Delivered TASK-00543/00553 are now
integrated; the TASK's recovery checkpoint records explicit headless setup and the successful full gate. No
borrowed stack, fabricated receipt, shared-service startup or unfinished-branch merge was used.

### Repair cycle 1 — Engineer evidence, acceptance still held

Review R1/R2 were reproduced before repair. New direct owning regressions cover twelve sequential distinct
sessions and twelve same-session resumes, protected terminal history, real active capacity, downstream refusal
and repeated completed-selection/pause refresh. Fresh private native Pi/Herdr qualification on repaired executable
bytes shows ten sequential Agent attempts with eight retained entries, older completed selection/pause surviving
refresh, and foreground then background resume reusing the same actual SDK session. A pin survives history
pressure. This is Engineer evidence, not independent Review/QA; genuine capacity refusal is controlled-boundary
evidence, not a live eight-agent claim (the supported upstream profile retains maxConcurrent 4).

Canonical ignored `repair-1/` and the Work handoff own current counts, inspected captures, source identity,
cleanup and warnings. The gate remained held at that repair checkpoint; the TASK's later recovery checkpoint
supplies its first full success and the test-fixture-only adjustment. The older Engineer-3 evidence above remains
historical qualification with its original final-guard/capture limits; it is not fresh repaired-source QA.

## Caller/removal inventory

After actual upstream/native replacement qualification, source/caller search found no production callers of the
explicit-socket readers. Removed `src/workspace/{concurrent-children,child-observation}.mjs` and their two obsolete
test files; removed their gate/documentation entries and selected the bridge checks in the gate instead.

| Retained path | Actual caller and reason |
|---|---|
| `src/native-observation-extension.ts`, `src/observation/{native-observation,custodian,custody}.mjs` | Manifest loads native observation; upstream bridge and separate Build monitor reuse signed custody |
| `src/observation/subagents.mjs` | Native extension's passive SDK reader, covered by direct Harness checks |
| `src/observation/run-stage.py`, `automatic_observation.py`, `observation.py`, birth helpers | `.pi/skills/build/scripts/run-stage.py` delegates Work/Review/QA/Land monitoring here; separate TASK-00544 still owns that descendant-process route |
| `src/workspace/registered-children.mjs`, `src/herdr/activity.mjs` | Live verified Herdr activity renderer and selected/pinned worktree context, reused by both routes |

The Python monitor's `fight-child-observation-v1` string is still live protocol vocabulary, not a caller of the
deleted JavaScript readers. TASK-00544 was not repaired or retired. MCP evaluation remains separate
[TASK-00555](../../planning/tasks/00555-TASK.md).
