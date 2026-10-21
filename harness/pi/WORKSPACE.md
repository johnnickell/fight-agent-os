# Local terminal workspace (implementation candidate)

TASK-00449 adds the human-only shared-runtime reader below. Its frozen-candidate full gate passed; local
qualification is implementation evidence, not independent acceptance. See the TASK for visual limits and
`.runs/tasks/TASK-00449/runs/initial-finalization/handoff.md` for the committed-head gate result.

From a checkout with Node 22 or later and Pi already available on PATH:

```sh
./bin/terminal --provider openai-codex --model gpt-6.1-sol --thinking high
```

This is a dedicated, dependency-free RPC client, not a Pi extension screen or a new agent engine. It starts
`pi --offline --mode rpc` with normal configured resource discovery. It does not install anything or change global
configuration. `--offline` prevents automatic catalog requests; it does **not** disable explicitly submitted
provider prompts. Pi owns provider resolution, tools, context expansion, queued work and session persistence.
The configured model is displayed separately from any actual provider response identity.

**Current repair cycle 2; independent acceptance pending:** canonical Review of `735de3e…` resolved R1–R5,
found R6 (long standard dialog edits off-screen), and requested E1 (real persistent native-selection/resume proof).
The R6 repair follows the caret initially and after edits/backspace/newlines/resizes; manual scrolling remains
independent until editing resumes. Three new regressions failed before repair; all **45 workspace** and **404 owning
Harness** checks now pass. This is controlled evidence, not real-dialog PTY or independent QA. Coordinator's
frozen-candidate planning check passed **625 records / 515 active** and full gate passed **215.664 seconds**,
24 phases exit 0, source unchanged: `.runs/build/20261018T035504Z-11936/receipt.json`. Engineer verified equal
before/after snapshots and all 1,317 snapshot file hashes before evidence-Markdown-only updates. This is inherited
coordinator verification, not an Engineer rerun, literal post-commit receipt or independent acceptance. Canonical
Harness ran **374 tests**, including all **45 workspace** tests; Sass/Bootstrap/Storybook warnings remain.
**Recovered E1 now passes as coordinator-owned qualification, not independent QA:** real native interactive
selection and actual status match explicit workspace resume of the same owned session, including persisted harmless
shell conversation and review/reviewer/review-report/TASK-00447/exact checkout/status/null-launcher binding. Native
and resumed normal exit0, canonical/echo restoration and child reaping are recorded. Evidence under
`.runs/tasks/TASK-00447/runs/repair-cycle2/`: `e1-final-native{,-status}.json`, `e1-final-input.jsonl`, native stage in
`e1-final-summary.json`, and `e1-resume-{summary,resumed-before,resumed-after,resumed-status,resumed-settled}.json`.
The original helper API/observer failure, subsequent malformed checkout and final helper's incorrect all-history-in-
viewport assertion remain preserved; the latter summary is still incomplete, not relabelled a whole-script pass.
Resume-only recovery compared public session metadata and actual status instead; history above the viewport is not
lost. Its earlier guard-source hold preceded the coordinator's normally assessed successful execution after resume.
Configured Sol/high is separate from provider-event model identity; no launcher flags, policy mutation or RPC selector.
See [TASK-00447](../../planning/tasks/00447-TASK.md) for provenance. Literal post-commit gate and fresh independent
Review/affected QA remain pending at this tracked checkpoint; final gate/head/status are recorded in ignored
`.runs/tasks/TASK-00447/runs/repair-cycle2/final-handoff.md`. Historical frozen checkpoints/holds remain evidence,
not current prerequisites or reusable permits. No policy/input-scope amendment follows.

**Historical repair cycle 1 checkpoint:** canonical Review of `8e741aa…` requested R1–R5.
The repair explicitly refuses workflow selectors that RPC cannot honor, keeps dialog selection visible, bounds
input at grapheme boundaries with rejection notices, discloses entry/stream clipping with source identity, and
includes workspace behavior tests in the canonical gate. Fresh repair results and remaining limits are recorded
in [TASK-00447](../../planning/tasks/00447-TASK.md) and ignored
`.runs/tasks/TASK-00447/runs/repair-cycle1/final-handoff.md`; the predecessor checkpoint/red-green proof is
retained in `repair-handoff.md`. Recovered coordinator-owned repair gate passed **209.894 seconds** at
`.runs/build/20261018T011616Z-7136/receipt.json`, with equal source snapshots and **371 canonical Harness tests**
including all **42 workspace tests** (the broader focused Harness check passed 401). Engineer verified all eight
file hashes before evidence-Markdown-only updates; this is inherited verification, not a self-run or independent
acceptance receipt. The exact post-commit coordinator gate is pending before fresh Review. Sass/Bootstrap and
Storybook warnings remain; some ANSI logs were blocked as binary during Engineer inspection, so their counts
and warning inventory were not freshly rechecked. Review, affected QA and normal-input qualification remain
required. No producer, policy authority, runtime or native-input scope amendment is introduced.

**Historical implementation qualification (not repair verification):** coordinator-observed owned RPC and real `./bin/terminal` PTY probes passed
with active Harness, wide/narrow views, side-input isolation, harmless native shell and terminal restoration.
A separate genuine provider PTY completed a turn and observed native status/read tools and its fixture marker;
the configured model is known, but the response model was not reported. Real wide/narrow terminal images were
captured and inspected using disposable HOME: they show truthful held/unavailable state, **not** an active
provider conversation. The new feature was **Not present before**. An exact-window GUI Tab attempt was refused
without dispatch; keyboard proof is from PTY, not that attempt. No light-theme or child-tab image was collected.
These are inherited implementation artifacts, not independent QA. After all 12 owned paths were frozen/staged,
the coordinator executed `./bin/planning-check && ./bin/build` through native interactive assessment on the dedicated
runtime: both passed, full build **206.636 seconds**, receipt `.runs/build/20261017T230157Z-2411/receipt.json`.
Engineer verified identical before/after snapshots and all 12 file hashes before this Markdown-only evidence
update. This supersedes the unexecuted predecessor gate/planning claims; generated views are current without
another `--write`. It is not a self-run Engineer or literal commit-head receipt. The coordinator's post-commit
gate is pending before Review, not claimed passed. The full gate ran 953 backend, 329 Harness, 409 frontend and
39 Storybook tests plus 78 browser captures; its Harness selection excludes workspace tests. Separate earlier
checks passed 391 Harness tests and 32 workspace checks. Missing runtime/provider/capture prerequisites are
resolved history, not current setup instructions; preserve the prepared runtime. Sass/Bootstrap deprecations and
Storybook directive/chunk-size/plugin-timing warnings remain.
See [TASK-00447](../../planning/tasks/00447-TASK.md)
for exact paths, image hashes, final gate result, warnings and remaining acceptance limitations.

## Conversation and observations

At 100 columns / 20 rows or larger, the conversation occupies the left half and each read-only observation
occupies a right quarter. Smaller terminals show one selectable view. The child pane can subscribe to an
explicitly selected local stage monitor (below); without a socket it says no child selected. The runtime pane is opt-in through explicit human launch options (below). Neither observation changes
conversation directories/grants or launches an observed agent. Managed execution remains outside this candidate.

| Key/input | Behavior |
|---|---|
| Tab / Shift+Tab | Focus next/previous pane; select that view on narrow terminals |
| PageUp / PageDown | Scroll only the focused pane; in dialogs scroll the exact request |
| Ctrl+W | Collapse/restore the split without discarding the conversation draft |
| Ctrl+T | Toggle local light/dark colors; `--theme system` uses terminal defaults |
| Enter | Submit the conversation draft, or explicitly answer a Pi dialog |
| Ctrl+J / bracketed paste | Insert newline / literal text; paste never submits or answers dialogs |
| Arrows, Home/End, Backspace/Delete | Edit by grapheme; Up/Down retrieve bounded input history |
| Escape / Ctrl+C | Clear queued inputs, abort current Pi work and shell; restore queued text to draft |
| Ctrl+Q; Ctrl+D with empty draft; `/quit`; `/exit` | Close the local workspace and its own Pi subprocess |
| `!command` / `!!command` | Normal Pi RPC bash, included in / excluded from subsequent model context |

Side-pane typing/Enter is ignored. Global exit and abort remain available. A Pi dialog is modal even when an
observation was focused. Confirmations require `y` for **that request only**; Enter, `n` and Escape deny. Select
uses arrows/Enter, follows the selected option across wrapping/resizes, and always labels its selected ordinal.
PageUp/PageDown can inspect a label taller than the pane without losing that identifier. Input/editor dialogs
support literal text, Ctrl+J and Escape cancellation. Their viewport follows the caret initially, on editing
(including backspace/newline), and on resize while following. PageUp/PageDown deliberately suspends following
across redraw/resize; the next edit reveals the edit point again. Paste is ignored in dialogs. Focus returns to its previous pane after answering; the conversation draft stays separate.

## Local child observation (TASK-00448 candidate)

Observation is opt-in on the **existing** stage-monitor invocation. Before launching an authorized stage, prepare
an unused owner-only runtime directory under that stage checkout's `var/` and add
`--observe-socket /absolute/checkout/var/o/a.sock` to its existing `run-stage.py` command. The directory must already
exist with mode `0700`; the monitor exclusively binds a `0600` Unix socket and never replaces an existing path.
Keep the path short: Unix socket pathname limits are platform-specific (about 104 bytes on macOS). The option
does not change stage model, authority, request or settlement requirements. An already-running monitor without
the option cannot be retrofitted; do not relaunch its child or fall back to transcript/scratch tailing.

If the monitor lives in a candidate worktree while a different checkout's Harness is already installed,
inspect `pi list` first. Select that trusted installed package explicitly on the monitor with
`--harness-package /absolute/installed/harness/pi`. Pi deduplicates the same resolved package path; different
checkout paths are distinct resources and can conflict. This selects the bootstrap and complete protected
package, retaining normal resource discovery and `--fight-screening-required`; it does not disable extensions,
change settings/trust/enrollment, copy credentials or fall back after failure. Qualify startup/status before
provider work. An unknown installation or conflict remains a prerequisite, not permission to bypass protections.

In the workspace, supply only sockets you own and are permitted to observe:

```sh
./bin/terminal --provider openai-codex --model gpt-6.1-sol --thinking high \
  --observe-child /absolute/checkout/var/o/a.sock \
  --observe-child /absolute/checkout/var/o/b.sock
```

Up to eight distinct absolute paths are allowed. There is no directory scan, session attachment, durable replay
store or model-facing observation tool. Socket paths are not forwarded to Pi. Attempt/TASK/worktree/role and
configured provider/model/thinking arrive from the selected monitor; provider-reported identity is explicitly
unavailable in this safe contract. The attempt path is a label only, never opened. The monitor emits bounded
completed assistant progress, safe tool names/file targets/outcomes, lifecycle and heartbeats from its existing
safe boundary—not deltas, hidden reasoning, source/results, shell arguments or arbitrary stderr. Existing
`--screening-dispatch` privacy is preserved: pane text is a private-activity placeholder in that mode.

With the child pane focused: `[` / `]` select the previous/next supplied endpoint; `p` pauses/resumes progress
display; `c` closes/opens **only the reader**; `r` explicitly reconnects the same reader. Pause continues draining
and updating known lifecycle/heartbeat, counting undisplayed progress. Closing, switching and workspace exit
never send a command to an observed child. Ctrl+W is a layout-only collapse, not reader close; use `c` to stop
subscription. Global abort still targets only the workspace's primary conversation. Other side-pane typing and
Enter remain inert. PageUp/PageDown inspect the child identity/history without editing the primary draft.

The producer keeps at most four readers, each with a 64 KiB nonblocking send queue; excess/slow readers are
closed rather than holding up stage progress. Workspace rendering drops obsolete frames while terminal output
is backpressured and redraws the latest state on drain, rather than accumulating a frame queue. Each connection begins with the current snapshot; earlier progress
is not replayed. The pane retains at most 100 progress entries / 32 Ki UTF-16 units with omission counts, admits
records up to 32 Ki units, and rejects malformed records, stale sequences or subscription generations.
Progress/identity field limits use Unicode code points consistently with the Python monitor (600/1100 maximum
accepted points); the monitor truncates progress at 480 points plus an ellipsis and identity at its field-specific
limit plus an ellipsis. It encodes JSON as UTF-8 rather than expanding supplementary characters into surrogate
escapes. Framing and retained-memory caps still use UTF-16 units and are unchanged. Identity
is pinned per endpoint for this workspace lifetime; a replacement attempt at the same path is refused. Use a
new explicit socket path for a new attempt. Gaps and snapshot omissions are disclosed. No automatic reconnect
loop runs. A missing/closed stream or overdue heartbeat means unknown liveness, never inferred success/death.
Known settlement awaits process exit; only the monitor's effective zero exit plus normal non-aborted settlement
produces `completed`, and that is **not** review/acceptance. Final status is memory-only; the endpoint disappears
when the monitor exits. Owner-only filesystem checks are a local trust boundary, not same-user process isolation.

The initial candidate had only controlled monitor qualification. TASK-00448 repair cycle 1 adds genuine
sequential two-attempt observation through the candidate monitor and workspace, including assistant/tool/heartbeat
progress, pause/drain, primary shell input, close/reconnect, completion and inspected active-producer dark/light
wide/narrow images. These are implementer qualification, not independent acceptance. Stale-callback and hostile/
slow-reader stress remain controlled checks; the two live fixtures were deliberately not concurrent. Independent
Review/QA remain required. See [TASK-00448](../../planning/tasks/00448-TASK.md) for exact receipts and limitations.

## Human-only shared Docker logs (TASK-00449 candidate)

Select an **existing main-checkout** Compose runtime explicitly at launch:

```sh
./bin/terminal --provider openai-codex --model gpt-6.1-sol --thinking high \
  --logs-main /absolute/main/checkout --logs-context default \
  --logs-project fight-agent-os --logs-service web
```

All four options are required together; none is inferred from cwd, selected child, repository configuration,
model output, Docker environment overrides or a saved preference. With no selection, no Docker process starts.
The compact pane pins **SHARED** context/project/service and reader state above recent output; it never claims
worktree-isolated logs. Conversation and child identities stay separate.

With runtime focus: `[` / `]` select another verified single-container service; `p` freezes/resumes display;
`c` closes/opens only the reader; `r` explicitly reconnects; `i` shows verified main path, local endpoint and
full notices. PageUp/PageDown inspect bounded scrollback (or details); Tab returns to conversation. Pause
keeps a bounded frozen view while draining into the recent bounded buffer. Resume shows that recent window;
eviction counts disclose loss. Collapse (`Ctrl+W`) is layout-only, not close.
No log, source option or selection is sent to Pi RPC, tools, sessions or provider context. This is separation
in this client, **not** OS isolation: existing authorized native shell/file access is unchanged.

The trusted host uses fixed argument-vector Docker operations, no shell or Compose option passthrough. It checks
`docker --context NAME context inspect`, requiring a local Unix endpoint pinned for this workspace lifetime,
then lists containers with the selected Compose project label. Every returned container must have a full ID,
validated service and exact main-checkout working-directory label. Missing, ambiguous/replicated, stopped,
mismatched or inaccessible sources are refused; no source container is started or changed. To avoid evaluating
repository/worktree Compose configuration, the log operation is **`docker container logs` on that verified
Compose container ID**, not `docker compose logs` from cwd. Docker's emitted timestamps and diagnostic text are
retained; the selected service labels the stream. Separate stdout/stderr arrival order is not a total ordering
guarantee. Logs without TASK attribution remain unattributed.

Limits: initial/reconnect tail **100 lines**, **300 records / 32 Ki UTF-16 units** retained; a paused snapshot
adds at most one equal-sized buffer. Each raw record retains **4 Ki UTF-16 units**, with a clipping marker;
escaped display records cap at approximately **8 Ki units**. UTF-8 framing preserves fragmented characters,
replaces malformed bytes and visibly escapes C0/C1, escape/clipboard, CR and bidi controls. These are presentation
limits, not a new logging/redaction policy. Identity queries cap combined output at **64 KiB**, **32 containers**,
and **5 seconds**. One sequential metadata/read process is used; reader lifetime is **15 minutes**, followed by
explicit human reconnect only. Automatic retries: **zero**. Close sends TERM, then KILL after **1 second**,
with a **2-second** wait bound. An unreaped reader remains tracked; no replacement starts while tracked processes
remain. Workspace exit also disposes readers. No container lifecycle operation is implemented.

Interrupted/empty streams are not proof of source death or complete history. Reconnect/service switches have
unknown gaps and can replay up to 100 tail lines; there is no lossless cursor or deduplication guarantee.
Use `i` for the full notice if the compact pane clips details. Missing Docker/permission and stopped-source
states need operator recovery, never automatic startup or privilege escalation.

A private informational preference is written to the selected main checkout's ignored
`var/terminal/runtime-source.json`. Owned nonsymlink directories and a private regular single-link file are
required. **It is never reloaded as authority**: each launch still requires explicit selection, so repository/model
edits cannot redirect this observer through saved state. No socket/credentials are forwarded to Pi's environment.
Docker's host authority remains powerful; these operations are not Docker read-only authorization.
No browser endpoint, collector, instrumentation, Compose override, per-worktree runtime product, archive or
application dependency on `.runs/` is added.

Qualification observed Node 22.23.1, Pi 1.3.0, Docker 29.7.2 and Compose v5.3.1; these are receipts, not pins.
No new Pi protocol command is added: the existing public RPC lifecycle and child-environment filter remain.
`node --test harness/pi/tests/runtime-logs.test.mjs` checks owned source, buffer, safe display and reader behavior.
Controlled peers are not live integration proof. TASK-00449 records the existing-source probe, separate real-Pi
PTY, sanitized terminal image, input denial, counts and limitations. Keep live pane contents out of model/tool
transcripts; sanitize explicitly authorized evidence.

## Policy and normal Pi paths

The client requires genuine healthy Harness access plus `get_state` and the `jev`/`file-screening` commands
before enabling conversation input. Jev is optional: an explicit `disabled`/`not-enrolled` status permits native
tools without credentials or enrollment. Enrolled sessions with unavailable optional screening can proceed when
the Harness independently reports `access.state: available`; authority or integrity failures report `held`.
Older providers without that field support only the explicit active or not-enrolled states. Missing, malformed
or held access and failed startup keep input disabled. Fix actual access failures through normal Pi; there is
no bypass flag or compatibility-by-version check. The current status adapter consumes the
Harness's existing `Fight screening status:` diagnostic. A changed/missing diagnostic is a qualification failure,
not permission to infer activation from command names alone. A green label is not OS isolation or a substitute
for executing policy-boundary probes.

Prompts, supported registered extension commands and prompt templates go through RPC `prompt`; streaming prompts
explicitly steer. `agent_settled`, not a successful command response, clears the running indicator. Shell input
uses RPC `bash`, preserving Pi's `user_bash` route. Under the [native-shell rule](FILE_SCREENING_RULE.md#native-shell-remains-independent),
there is no Jev command assessment, disclosure or confirmation, and no Jev setup is required. Existing task and
native runtime permissions still apply; RPC grants no shell authority. Explicit-file checks do not confine shell,
its descendants or trusted SDK calls, and do not provide OS isolation. Any independently selected
[deterministic tool policy](TOOL_POLICY.md) remains separate from Jev screening.

Supported extension select/confirm/input/editor requests are forwarded, request-bound, timeout-aware and never
automatically allowed. These standard dialogs are distinct from Jev command confirmation.
No policy source is modified to accommodate the UI. Raw stderr, provider configuration, signatures and image
payloads are not copied into display state. Pi retains images in the session; the display shows a text marker.

RPC input is **not** trusted interactive operator input. Every `/skill:…` invocation and `/file-screening`
command is explicitly refused locally, even if discovered in `get_commands`: these can select workflow, role,
subject, checkout or query/status mode, which RPC cannot establish. Nothing is submitted and the current binding
is unchanged. This is a compatibility refusal/routing boundary, not acceptance of reduced normal-input scope.
For a persistent session, exit with Ctrl+Q, open `pi --session PATH` in the same directory, select the intended
workflow there and inspect `/file-screening` to verify its binding. Exit native Pi and resume
`./bin/terminal --session PATH`. Use the displayed exact session path; do not fork/clone it or copy launcher
flags. Resume alone is not authority selection. An in-memory session cannot be resumed; its refusal says so.
The bounded real native-selection/resume qualification above establishes this owned session's continuation,
not universal native parity or independent acceptance; affected live QA remains required.

Enrollment, setup, role changes, login and native-only
commands such as `/settings`, `/model`, `/tree`, `/new` and `/reload` require the normal Pi TUI. Unknown slash
commands are rejected locally rather than accidentally sent to a provider. Native custom terminal components,
image rendering, file/image clipboard attachments, rich Markdown, mouse navigation and Pi custom palettes are
not implemented. This candidate is not full parity with the native editor. A supported RPC UI method is not proof
that every installed extension is usable outside the native TUI.

## Session and lifecycle boundaries

The launch directory stays the conversation directory. No selector changes it. By default Pi stores sessions
under that directory's ignored `var/terminal/sessions/`; `--continue` uses that same store. `--session PATH` opens
an existing Pi session and `--session-dir PATH` explicitly selects another store. Use `--no-session` for ephemeral
qualification. Resuming a session does not reset enrollment, budgets, accounting or role history. Operator role
changes still need the native TUI; inherited launcher-role environment flags are deliberately not forwarded.

The wrapper accepts only `--theme system|light|dark`, `--provider`, `--model`, `--thinking`, `--session`,
`--session-dir`, `--name`, `--extension`, repeatable `--observe-child`, the four `--logs-*` source options above, `--continue`, `--no-session` and `--help`. It does not accept API keys,
initial prompts, arbitrary process modes or extension-disabling flags. The child gets only HOME, PATH, TMPDIR,
locale and terminal variables. Pi may resolve its configured credentials through HOME normally; this is not
credential isolation, nor a promise that ambient-environment-only providers work. No provider values are copied
into child environment variables, UI preferences or another credential store.

Presentation state is memory-only. The child pane's separate bounds are described above. Conversation display retention is 500 entries / 256 Ki UTF-16 units, each entry 64 Ki units;
whole-entry eviction, per-entry clipping and streaming-tail clipping carry explicit notices, without editing Pi
history. Clipped entries preserve a bounded role/source header and a grapheme-safe tail; a trailing notice stays
visible while following the newest content. Input is capped at 32 Ki units across typed text, bracketed paste,
and input/editor dialogs, including prefill/newlines. A cluster crossing the boundary is rejected whole; the
status line discloses discarded input without echoing it. Paste truncation propagates that notice to the editor.
RPC records are capped at 8 MiB, pending requests at 32 and dialogs at eight. Terminal controls and bidi
embedding/override controls are removed from display output. Unicode uses grapheme boundaries, combining marks,
CJK-wide and common emoji widths; actual terminal/font width variations still need real qualification.
Long text wraps inside pane bounds. Very small terminals request resize rather than writing outside the screen.
`NO_COLOR` keeps terminal-default styling; the workspace never switches Pi's own theme via RPC.

Exit closes the child's stdin for orderly Pi disposal; after two seconds it signals that owned child, after four
seconds it kills it if necessary. It does not detach durable work or stop observed agents. Normal exit, handled
signals, startup/rendering/transport failures restore raw mode, cursor, bracketed paste and alternate screen.
SIGKILL, host/terminal loss and unsupported platforms cannot guarantee restoration. Use normal terminal recovery
if the host dies. No process, session, container or evidence belonging to another owner is cleaned up.

## Qualification

The installed Pi observed during this task is **1.3.0**, evidence only—not a pin, equality gate or future guarantee.
Its documented extension surface supplies widgets/header/footer/editor replacements and temporary custom screens,
not persistent reparenting of the primary transcript. The documented RPC wire protocol supplies the needed
subprocess/session and standard dialog boundaries without nesting a renderer inside an extension. This client
uses those public records directly, with LF framing, response IDs, backpressure and bounded cleanup; it does not
read private runtime code or fork Pi. Installed examples previously denied access were not shell-read.

Run `node --test harness/pi/tests/workspace.test.mjs` for owned layout, focus, editor, Unicode, paste, dialog,
transport and lifecycle checks; `./bin/harness-check` and the canonical `./bin/build` Harness phase run them
with the other selected Harness tests. `node --test harness/pi/tests/child-observation.test.mjs` covers the
owned child subscription/generation/buffer and lifecycle behavior; the full gate includes both files. Controlled peers
are explicitly **not** real-Pi integration proof. For acceptance, run the actual entry point in a PTY, inspect
active Harness identity, exercise a real authorized conversation and file/command boundaries, test narrow/resize,
scrolling, dialog cancellation, normal/error exit and capture real terminal images. Existing held-state images
and separate active-provider PTY have different evidentiary scope; QA must assess applicability and missing
scenarios rather than infer full native-editor parity. Native-only features listed above are not silently approved
exclusions from the accepted requirements. Never authorize a provider
request or accept a held command merely to obtain evidence. Keep execution evidence under the TASK's ignored
`.runs/tasks/TASK-00447/runs/` and run the canonical `./bin/build` from the selected checkout.
