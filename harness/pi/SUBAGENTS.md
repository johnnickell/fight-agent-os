# Bundled subagents and Fight observation

## Fight-owned component — TASK-00560 packaging prerequisite

John approved maintaining the patched upstream engine inside this GitHub repository on 2026-11-04.
`vendor/pi-subagents/` is ordinary source, not a submodule, runtime checkout or second session engine. Its
[component README](vendor/pi-subagents/README.md) records upstream 0.23.1/base revision, MIT attribution and the
maintained delta. The Harness manifest loads its source entry once; the package ships runtime source/license/docs,
not development dependencies or tests. Fight owns policy and observation; the bundled engine owns SDK execution.
This packaging candidate is not independent acceptance, an everyday installation update or the actual Build route.

### Historical companion qualification

[TASK-00554](../../planning/tasks/00554-TASK.md) implements a passive bridge to
[`@tintinweb/pi-subagents`](https://github.com/tintinweb/pi-subagents). Private installed/native qualification
has run and the full local gate passed during delivered-base recovery. Independent Review ACCEPT and bounded
behavioral/visual QA PASS cover the accepted candidate; see the TASK's completion checkpoint. This is not global rollout or publication.

## Select the supported profile

For a new, explicitly authorized installation:

1. Review the shipped `profiles/subagents.json` and explicitly merge it into the project's `.pi/subagents.json`
   (or private agent-directory `subagents.json`) **before launching the updated package**. Never overwrite another
   operator's configuration. Project settings override agent-directory defaults; absent profile is not the supported
   setup. Package installation does not automatically apply settings or grant stage authority.
2. Install only the Fight package through Pi's package settings. The subagent engine is now bundled; remove an
   existing standalone `npm:@tintinweb/pi-subagents...` declaration in the intended settings scope, after affected
   sessions/Builds exit and before starting the updated copy. Inspect `pi list` and explicit extension paths/filters:
   there must be one Fight package identity and one engine, never standalone and bundled copies together.
   No second npm/Git package is needed. For source-local installs, explicitly prepare runtime dependencies with
   `npm --prefix harness/pi ci --ignore-scripts --legacy-peer-deps --no-audit --no-fund` from the repository root;
   Pi does not install a local directory's dependencies. Copied npm installs install the declared runtime dependencies.
   Pi itself is capability-qualified, never version-pinned. Earlier companion 0.22.1/source
   `fc089c4b7ab0f1185453b2e53027997c1f637e38`, Pi 1.4.0 and Herdr 0.9.5 are historical evidence, not this candidate's
   acceptance. Restart only the intended session; no active-parent reload or shared installation mutation.
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

## Opt-in Build Engineer

The bootstrap candidate adds **`--fight-build-engineer sdk`** and **`fight_build_engineer`** for one actual Engineer
assignment. It does not change the default executor or migrate Review/QA/Land; those process callers remain live
until TASK-00561. Packaging or scripted SDK qualification alone does not satisfy TASK-00560. Its real native Work
assignment, inspected Herdr captures and independent acceptance remain required.

### Prepare a new native host

At a safe session boundary, prepare one private copied installation using the profile above. Keep the active
Build/shared installation untouched. The package manifest loads the Build listener before the engine so it sees
the engine's actual settings-loaded event. Do not filter/reorder entries or load another engine. Prepare private
Pi `settings.json` with `packages` containing that copy, the actual provider skill directory in `skills`, and an
absolute `sessionDir` under the target canonical `.runs/tasks/<TASK-ID>/runs/` (or its Build run). Apply the shipped
`subagents.json` to that private agent directory before starting; do not change global settings/trust. Resolve
provider authentication through the existing supported operator/SDK credential path, never copied credentials.
Unavailable authentication holds the live qualification; a faux provider is only a controlled check.

Start ordinary **native Pi in an owned Herdr terminal**, in the already selected registered checkout:

```sh
PI_CODING_AGENT_DIR="$private_agent_dir" pi --fight-build-engineer sdk \
  --provider "$coordinator_provider" --model "$coordinator_model" --thinking "$coordinator_thinking"
```

This flag selects a route, **not Build authority**. A genuine operator `/skill:build TASK-NNNNN --checkout /absolute/checkout`
interaction or an existing trusted Build launcher must select Build/coordination for that exact TASK/checkout.
An Engineer bootstrap cannot promote itself, replay coordinator dialogue or synthesize interactive user input.
When the current host cannot load the candidate, commit the bootstrap and hand the exact private-host continuation
to the authorized Build owner. Do not call it complete or launch a subordinate demo in place of the remaining Work.

### Dispatch and inspect

The coordinator prepares/reads back a fresh canonical `.runs/build/<TASK-ID>/<run>/<attempt>/request.md` under
[Build's ordinary request rules](../../.pi/skills/build/SKILL.md). Keep governing skill/context paths, source/base/head,
permitted Work effects, the pinned Engineer provider/model/thinking, single Compose project, repair allowance and
canonical Work handoff. Then call `fight_build_engineer` with:

- `task`, `role: "engineer"`, exact canonical `checkout`, current full `head`, fresh absolute `attempt` directory;
- resolved target `base` (canonical evidence checkout), exact current `branch`, absolute target `record`, and
  absolute `context` from the coordinator's private `.runs/build/<TASK-ID>/`;
- exact pinned `provider`, `model`, `thinking`, and finite `timeout` seconds (at most 21600).

Resolve these target facts using [PROJECT_CONTEXT](../../docs/engineering/PROJECT_CONTEXT.md) before dispatch:
the coordinator verifies the actual record's approval/executable state, local branch/base rules, instructions,
gates and runtime. The runtime does not parse Agent OS YAML/status spellings or require `planning/tasks`, a
`feature/` prefix or root `AGENTS.md` in another project. It checks same-repository registered checkout/base,
exact branch/head, context in that target's task-owned Build scope and the selected record's TASK identity inside
the selected checkout. Matching TASK numbers in a provider/foreign repository are not permission to use it.
Context/record contents join the fresh assignment; they supply target facts, never trusted stage authority.
Native discovered instructions (`project_context`, including `CLAUDE.md` or ancestor instructions when used)
remain inherited and verified; the engine supplies the child's own skills/cwd rather than a parent transcript.
An unassigned/no-instruction target does not gain an artificial AGENTS prerequisite. Task-private session storage
may use the resolved base or selected checkout; no first-worktree heuristic selects target policy.

The tool requires actual session-local Build authority, same TASK/checkout, registered repository membership,
exact branch/head, resolved target context/record, supported profile/model and the engine's actual
`capabilities.beforeSessionStart`/`boundRootSession` flags. It claims `dispatch.lock` before calling the **one
upstream manager directly**. Existing execution evidence or an already claimed attempt refuses. No generic launch
facade, scheduler, second engine, parent-path inference or automatic worktree management is involved.

The awaited host-only setup runs **after factories**, before extension binding/session_start/prompting. Inspection
established that Fight chooses workflow authority in `session_start`, so this is early enough. Setup verifies the
actual fresh child, bound root, checkout, model/thinking, task-private session storage, loaded Fight extensions and
governing instructions, then binds Engineer/work/TASK/attempt and `source`, `checks`, `commit`, `task-handoff` effects
to the actual SessionManager. Fight verifies its initialized workflow again at input before a provider request.
No prompt, name, profile, persisted role entry or shared `process.env` impersonation grants the stage. The engine's
async-local child marker is shared across Pi module loaders; ordinary unbound SDK assistants do not inherit the
native parent's launcher role. Shutdown/reload revokes the binding; it cannot restore itself from transcript history.
These are trusted-local workflow responsibilities, not OS confinement. Native file tools and shell remain native.

The existing passive observer derives assigned identity from that exact live binding while retaining native-root
custody, registered-worktree verification, foreign/rootless/replaced refusal and bounded assistant/tool-name output.
Reader closure never cancels work. Missing display does not change the executor or result. Upstream parent shutdown
or reload aborts/disposes children; it does not provide durable survival.

The tool writes `sdk-outcome.json`, returns identity/state rather than Engineer dialogue, and consumes its own upstream
completion notification. Timeout/abort/settings change/shutdown request cancellation and allow ten seconds for the
original promise/live session to settle. A terminal label alone is not quiescence. Missing/replaced records, unsettled
execution or an exception after spawn was called remain **uncertain**; the coordinator/session checkpoint holds another
dispatch, including after reload. Reconcile the original native execution and any external effects before replacement;
never retry through Python to get output. A fresh host is not proof that old effects stopped. No automatic recovery or
acceptance follows from `completed`: inspect the committed candidate, normal gate and canonical Work handoff, then
use the existing **fresh independent Review** process request without Engineer conversation.

Canonical process stages are `engineer`, `reviewer`, `qa`, `land`; their skills remain `work`, `review`, `qa`,
`land`. Existing workflow aliases normalize at launch and after custody admission, from one shipped vocabulary
in `src/stage-roles.json`. This includes legacy Work/Review callers and existing QA Engineer/Release Manager workflow inputs,
not guessed aliases. Unknown roles refuse before starting a stage. SDK Engineer likewise accepts legitimate Work
aliases at its owning tool boundary, but always binds/emits Engineer. Build coordination is not a QA stage.
Non-Engineer process callers remain live until TASK-00561; no authority comes from AGENTS spelling alone.

Controlled owning qualification:

```sh
node --test harness/pi/tests/build-engineer.test.mjs harness/pi/tests/subagents.test.mjs
node harness/pi/tests/native/build-engineer.mjs "$PI_SDK_MODULES" "$sdk_evidence" "$installed_harness"
python3 -B harness/pi/tests/native/portable-stages.py "$process_evidence" "$installed_harness" "$read_only_consumer"
```

The installed probe uses a scripted provider and real SDK/engine/Fight initialization, including Work admission,
passive early/final text and tool names, duplicate refusal, unbound subordinate roles, provider error, timeout and
shutdown/reload uncertainty. Its private target has `work-items` records, a different state vocabulary,
`topic/` branch and `CLAUDE.md`, with wrong-role/target/context/head refusals. The separate native process probe
uses real installed Pi/monitor startup for each existing alias in a private main/linked target, plus read-only
known-consumer startup/provider resolution and custody negatives. It handles input without a provider turn;
its expected monitor exit 1 is an incomplete-response result, not successful Work. It creates no consumer TASK
or source changes. These probes are **not** a real permitted Work assignment, Sol-provider attestation,
inspected Herdr capture, independent acceptance or universal-project certification. Record sample limits and
actual consumer availability in the TASK and Work handoff.

## Build Engineer prerequisite — TASK-00560 held

This heading and the following original hold are retained as historical evidence. John's later supplier amendment
and the opt-in bootstrap above supersede the missing-hook/MAIN-only instruction, not the incomplete actual Engineer
acceptance. The bundled engine supplies the awaited setup and actual bound-root capabilities; see its
[supported local RPC contract](vendor/pi-subagents/docs/rpc.md). No upstream maintainer approval or separate
publication is a prerequisite to ordinary in-repository maintenance.

### Historical missing-capability qualification

TASK-00560's 2026-11-03 private qualification used Pi `1.5.0` and upstream `0.23.0`
(source `a60247c47f8b0a17bfe7208f9e248e464eda05f0`). These are tested identities, not Pi version gates
or adoption of a new everyday companion. The real Build Engineer route is **not implemented**. Keep the existing
Build process stages; do not select a subordinate Agent as an independent stage or silently replace an executor.

At that historical supplier revision, two capabilities were missing:

- **Trusted initialization before extension binding.** The supported RPC/manager `spawn` surface overwrites a
  caller's `onSessionCreated`. Internally `runAgent` calls it only after `session.bindExtensions()`, too late for
  Fight's `session_start` workflow admission. `onSpawned` supplies a record ID, not the actual SDK session.
- **Verified native-root association for programmatic spawn.** `spawnTopLevel` strips `rootSessionId` without
  deriving it from the bound parent context. A persisted session's `parentSession` header does not supply the
  missing manager-record association or grant Build stage authority. Existing rootless refusal stays intact.

The original prerequisite owner was `@tintinweb/pi-subagents`: its supported host spawn boundary needed to derive the root
from the actual bound parent and offer an awaited, session-specific trusted setup boundary before child extension
initialization/prompting. Setup rejection must settle without executing the assignment, including queued starts
and cancellation. Fight can then bind the actual session to the authorized stage/TASK/checkout/attempt/effects
and verify exact model/thinking before execution. No existing hook name is claimed, and no upstream contribution
or publication is authorized by this qualification. Pi's direct SDK supports host-controlled resource loading;
replacing upstream execution with another session engine is not this TASK's accepted route.

A real private installed/native TUI probe created **one pre-aborted qualification session**, not an Engineer
assignment. It retained the exact model/thinking and checkout, but had no root association, zero caller
`onSessionCreated` calls, zero messages/provider requests and no assigned TASK. Fight truthfully restored it as
Reviewer and its passive reader emitted zero frames with `root-association-unavailable`. This is bounded failure
evidence, not the required Engineer/Herdr/Review journey. Canonical TASK-00560 Work evidence records the exact
session identities and limits. Do not poll/rename records, infer authority from profiles/prompts or mutate shared
`process.env` to bypass this hold. The later authorized bundled candidate above addresses these API gaps locally, not the missing real Engineer
qualification. Resume the Build route only after its capabilities and Fight admission are deliberately qualified
in a private installation at a safe session boundary; never reload an active parent or change global settings/trust
as an implicit effect.

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

In the historically qualified standalone companion, RPC/direct `spawnTopLevel` strips root identity. That old
rootless route remains **not observable**, even if the old companion executes it successfully. The bundled
candidate instead derives actual bound-root identity; it does not waive any admission checks. They produce `root-association-unavailable`, not a guessed
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
