# Local terminal workspaces

## Native Pi in Herdr (TASK-00510 candidate)

In native Pi inside Herdr, use `/fight-workspace` (or `/fight-workspace open`) to open a human-only child
activity companion and a human-only verified log companion (TASK-00512 implementation candidate). `/fight-workspace close` closes only proven-owned
companions. Repeated open reuses the same healthy panes. The existing Pi process, session/file, cwd, editor,
Tab completion, themes, dialogs, tools and workflow binding are unchanged. Herdr owns focus, resize and zoom;
Pi's Tab is not a pane-navigation shortcut here. The child companion supplies the validated context below;
TASK-00512 composes it with the existing bounded Docker reader. Fresh two-worktree live qualification and
independent acceptance of the complete replacement remain pending. The legacy `fight-terminal` client below
remains available until that acceptance; there is no automatic fallback to it.

Load the updated trusted package once through your existing Pi installation. A **full process restart** is needed
when adopting changed extension/module source; `/reload` did not replace cached imported `.mjs` code in the tested
installation. Do not add a second checkout's package beside the existing one or reinstall global Herdr integrations.
For a deliberate one-invocation development launch, use `pi --extension /absolute/trusted/harness/pi` with the
ordinary installed resources/policy; select only one resolved Harness package identity.

Prerequisites: native interactive Pi, `HERDR_ENV=1`, complete inherited pane/tab/workspace/socket context, the Pi
process in that pane's foreground, and the supported Herdr pane current/process-info/layout/split/rename/run/close
CLI operations. Initial/recovery open requires an unzoomed tab containing only this Pi and its owned companions;
other layouts are left unchanged. Missing APIs or ambiguous identity leave ordinary Pi usable. Tested Pi/Herdr
versions are evidence in TASK-00510, not version equality gates. The advertised official Herdr Pi integration was
qualified read-only and may coexist; this action neither installs it nor relies on an arbitrary focused pane.

Ownership is saved in the caller's ignored `var/herdr-workspace/`: actual primary/session/socket identity, returned
opaque pane and terminal IDs, and non-secret companion tokens. Before reuse/cleanup, Herdr topology/process facts
must match a live loopback companion handshake. A saved PID/label alone is insufficient. Loopback services are
bounded identity responders; the child handshake also reports its generation and permanent observation-invalidation
state. The log handshake also reports its tracked Docker subprocess PIDs, never output or source declarations.
A running reader is admitted only alongside its live companion in that companion's foreground process group,
with fresh direct-parent proof; unknown, untracked or additional foreground processes retain the ownership hold.
These health facts alone do not grant pane ownership. The child companion additionally serves only validated
selection metadata as described below, never progress or tool APIs. Same-user state/processes are trusted local inputs,
not OS-isolated or independently attested. CLI requests have a 2.5-second deadline and 1 MiB response bound;
startup probes and shutdown settling are finite. Closing a companion never sends input to or cancels Pi/children.

Companions use their own alternate screen and restore raw/cursor state on `q`, Ctrl+C and ordinary signals.
Native Pi shutdown/session replacement attempts companion-only cleanup; failed ownership checks retain resources
and report incomplete cleanup. A manually closed companion is rebuilt with its healthy sibling on explicit open.
Known partial effects are cleaned only with fresh ownership proof. A lost split reply is **uncertain**, not a
reason to split again: automatic retry is held even if all known companions were cleaned. Inspect the named
ownership file and the actual dedicated topology with Herdr. An operator must resolve any unknown effect before
clearing that file's uncertainty hold; never close a guessed ID or remove state merely to obtain a green retry.
Changed/replaced companion processes are retained, not killed. Unknown cleanup remains a disclosed limitation.

TASK-00510's accepted layout evidence remains historical context. TASK-00511 records fresh child integration
checks/captures separately; independent Review/QA remain pending for that change. No layout action changes tool
permission, workflow role, provider/model, enrollment or global settings.
Model-invoked commands do not become trusted operator workflow selections. Native shell is not OS isolation.

### Current-session activity in Herdr (TASK-00511 candidate)

The companion reuses the existing passive native-session custody reader; keep launching the ordinary authorized
provider monitor, without per-child socket flags. It never launches, cancels or retries a child, changes the
executor/tool/shell settings, reads transcripts, or sends progress to Pi, provider context or Herdr agent metadata.
Missing association leaves execution unchanged and visibly unavailable. The trusted-local-process limitation
and existing custody bounds under [automatic following](#automatic-session-following-and-worktree-test-bindings-task-00502-candidate)
apply unchanged; a signature is not independent native-origin attestation or OS isolation.

| Companion input | Display-only effect |
|---|---|
| `[` / `]` | Choose active siblings (or the pinned inactive child); no new output steals an active selection |
| `f` | Pin/unpin; a pin retains completion/disconnection labels; unpin recomputes activity |
| `p` | Freeze progress while draining bounded snapshots and counting subsequent records; lifecycle stays live |
| `c` / `r` | Close/reopen readers / explicitly rediscover; no child relaunch |
| `i`, PageUp/PageDown | Full identities/notices and bounded history; selection/lifecycle stay at the top |
| `q`, Ctrl+C | Exit only this display; explicit workspace open recovers owned companions |

No active child means idle. Initial/recomputed choice uses newest trusted start time, admission order within
the same custodian for timestamp ties, then stable child ID. Automatic or explicit selection changes discard a
frozen view. Completion means only the monitor's normal settled zero exit, never Review/QA acceptance. Expired
heartbeats say unknown/inactive; missing custody retains a pinned label but removes verified worktree authority.
Terminal-default text follows the companion terminal's foreground/background, independently of Pi's theme.
Narrow views keep a compact attempt/ordinal and known lifecycle header; `i` and paging reveal clipped identities.

Each open generation creates an owner-private canonical `var/herdr-workspace/*.context.json` binding to actual
native session ID/file/cwd/PID and a read-only loopback responder. A pending native file can become persistent
once; an inode/path/session replacement cannot rebind it. Supported shutdown invalidates the responder before
layout cleanup, even if cleanup fails. The companion checks this lifetime around every child refresh, at most
once per second with one refresh in flight. Lost/malformed/late lifetime data clears admissions and pins. From
the intended native Pi session, run `/fight-workspace open` to recover, including after a transient native-link
failure or same-path session-file replacement. Open checks both native lifetime and companion observation health;
when invalid, it replaces only proven-owned companions with a fresh generation and freshly discovers existing
work. It never clears the old invalidation or revives old metadata. Healthy repeated open (including intentionally
closed readers or unavailable child custody) remains idempotent. Ownership, unrelated-layout and uncertain-split
holds still apply. No provider turn is forced to persist a file.

#### Selection contract for the log companion

`src/herdr/activity.mjs` exports `readSelection(binding, companionEndpoint)` and `validateSelection`.
The caller must obtain the explicit owner-private binding and endpoint from a **live ownership-proven companion**
(the existing workspace ownership checks), never from progress, arbitrary labels, a directory scan or a saved
preference. `readBinding` refuses noncanonical/symlink/public/multilink bindings. `readSelection` freshly queries
native lifetime, then requests `selection` from the child endpoint with the expected companion generation/token.
Each local request has a **350 ms elapsed deadline**, **16 KiB** response cap; responders allow four connections,
**512 units** input and **400 ms** total lifetime. These are event-loop deadlines, not real-time guarantees.

The version-1 result contains generation, primary identity, actual session-file witness, timestamp, state,
verified worktree and optional child `{generation, id, worktree, lifecycle, state, pinned}` only. It contains no
progress, TASK/role label, attempt path, credentials or executable operation. Unknown fields are discarded.
Validate against the expected parent/session/file and companion generation; responses expire after **1.5 seconds**.
Idle permits the actual primary cwd; closed/unavailable has **no** source authority. A retained verified terminal
pin may retain its worktree; custodian loss removes it. Metadata is a worktree/lifetime context, not Docker-source
approval: the log companion still applies explicit source binding and exact container verification. Reopening issues
a fresh generation and freshly admits existing work. No previous response becomes authority for a later open.

Rendering drops obsolete frames under terminal backpressure; there is no frame queue or extra progress store.
Child retention remains eight children, 100 entries / 32 Ki units each, plus one bounded selected frozen view.
Owned binding cleanup checks the exact created inode; replacement files are retained. Abrupt crashes can leave
non-authoritative runtime files; no crash survivor is blindly reused as authority.

### Verified logs in Herdr (TASK-00512 candidate)

On a new `/fight-workspace open` generation, native Pi dialogs ask for **explicit human declarations**, never
source arguments embedded in a prompt, cwd-derived guesses, scans or saved preferences. Choose `Add worktree TEST
source` and enter each existing authorized source's absolute checkout/worktree, Docker context, Compose project
and eligible single-container service. Add up to eight distinct worktree/context/project bindings, then choose
`Open with …`. Supply all intended worktrees once, not on each child switch. Choosing zero bindings opens an
honest unavailable log display without starting Docker. Cancelling does not approve a source.

A separate `Add explicitly SHARED main source` choice makes shared mode available; the log pane's `m` key must
still select it deliberately. Shared output stays visibly **EXPLICIT SHARED**, not worktree-isolated. No missing
mapping or lost child custody silently selects shared or primary logs. Multiple test bindings for one worktree
remain unavailable until `{` / `}` chooses a labelled stack; `[` / `]` chooses only eligible verified services.
One-service declarations never widen to other project services.

Declarations remain in the native open generation's memory. A token-bound local query transfers them only to the
owned log process, after fresh child ownership/foreground proof. No log content is returned to native Pi, the
child companion, Herdr agent metadata, tools or provider context. The log process's identity responder exposes
only ownership/lifetime health, never log output or reader commands. This is a trusted-local display boundary,
not independently attested native origin, Docker-enforced read-only credentials or OS isolation.

The log companion polls validated selection at most twice a second, with one query in flight. Native/child
queries retain their 350 ms elapsed deadline and 16 KiB cap; selection authority expires after 1.5 seconds.
Initial child discovery may settle for at most ten seconds, without starting Docker or granting source authority.
Idle follows the actual primary checkout; selected/pinned context follows the verified child worktree. New child
identity/generation, source switch, closed/unavailable custody or expired/malformed lifetime immediately revoke
the old reader callbacks and clear live/paused output before reaping and any new verification. Even two children
in the same worktree get a fresh reader when their verified generation/identity changes. An unreaped process
blocks every replacement. Lost native/child transport permanently invalidates this display generation: recover
with `/fight-workspace open` in the intended native session, not by trusting an old response.

| Log companion input | Display-only effect |
|---|---|
| `{` / `}`, `[` / `]` | Explicit labelled stack / eligible service selection |
| `m` | Toggle follow / separately declared shared source |
| `p` | Freeze display while draining bounded recent output; resume shows the recent window |
| `c`, `r` | Close/reopen reader / explicitly reconnect, with unknown gaps and possible duplicate tails |
| `i`, PageUp/PageDown | Full conversation/child/source identities, endpoint, container generation, notices and bounded history |
| `q`, Ctrl+C | Reap only this display's reader; Pi, children and containers remain unchanged |

The [fixed Docker reader's limits](#human-only-shared-docker-logs-task-00449-candidate) remain unchanged: 100-line
tail, 300 records / 32 Ki units plus at most one equal paused snapshot, bounded escaped records, metadata and
process deadlines, 15-minute lifetime, zero automatic retries, TERM/KILL and finite reaping. Exact canonical
checkout labels, local Unix endpoint, service and full running-container identity are checked before reading.
In this companion, test **and shared** container/endpoint replacement require a fresh inspected human
declaration on a new open generation; the retained legacy shared reader's behavior is unchanged.
Use close then open to deliberately replace declarations; a healthy repeated open is idempotent and does not
re-prompt. A closed reader/companion never stops a container or child. No stack provisioning, restart, Compose
evaluation, database mutation, full logging service or automatic global update is included.

### Native entry and existing RPC sessions

The candidate workspace entry is ordinary `pi` inside Herdr, followed by `/fight-workspace open`. Outside Herdr,
keep using ordinary Pi; missing/incompatible Herdr APIs leave it usable and never silently launch the RPC editor.
A repository source change is not rollout: do not reinstall Herdr, mutate the live global package or load a second
Harness package identity automatically. Adopt reviewed source explicitly and fully restart Pi.

For an existing RPC conversation, record its **actual displayed session file**, stop that owned RPC client
normally, change to the same conversation checkout and run `pi --session /absolute/actual-session.jsonl`.
Do not use `--continue` to guess, fork/clone history, copy launcher-role flags or select authority through injected
input. In native Pi inspect `/session` and `/file-screening` for the actual session and workflow binding; select
a workflow only through the operator's explicit native action if needed. Then open the companions. Ephemeral
`--no-session` conversations cannot be resumed. Session files and historical evidence are never deleted.

**Publication transition still required:** this candidate does not retire or redirect the public `fight-terminal`
RPC executable. Independent Review/QA must first accept the complete native replacement with fresh real
Herdr/Pi/two-source evidence and the full gate. Its later authorized entry-point retirement/rollout must preserve
sessions and make non-Herdr prerequisites explicit; do not mistake this staging hold for accepted delivery or a
waived TASK criterion. Source-binding/host/gate prerequisites are recorded in TASK-00512.

## Legacy RPC workspace (retained)

TASK-00502 adds default current-session following and verified child worktree test-log context; see
[automatic following](#automatic-session-following-and-worktree-test-bindings-task-00502-candidate) for its native
launch contract, custody assumptions, limits and fresh qualification. Independent acceptance remains pending.

TASK-00449 adds the human-only shared-runtime reader below. Its frozen-candidate full gate passed; local
qualification is implementation evidence, not independent acceptance. See the TASK for visual limits and
`.runs/tasks/TASK-00449/runs/initial-finalization/handoff.md` for the committed-head gate result.

After the [explicit user-level launcher installation](README.md#install-the-global-workspace-command), run
from the project/worktree where the conversation belongs, with Node 22 or later and Pi on PATH:

```sh
fight-terminal --provider openai-codex --model gpt-6.1-sol --thinking high
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
occupies a right quarter. Smaller terminals show one selectable view. By default the child pane discovers eligible
current-session launches; `--observe-child` instead selects explicitly labelled legacy endpoints. Test logs follow
the verified selected/pinned worktree when an existing stack has an explicit human binding; shared logs remain a
separate choice. Neither observation changes
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

This is the explicit **legacy** path, not default authenticated discovery. Observation is opt-in on the **existing** stage-monitor invocation. Before launching an authorized stage, prepare
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
fight-terminal --provider openai-codex --model gpt-6.1-sol --thinking high \
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

TASK-00502's current **explicit legacy** selector subscribes to all supplied endpoints after obtaining the actual
Pi session ID. One active source is selected automatically; concurrent output/new siblings do not steal an active
selection. With child focus, `[` / `]` chooses among active sources (plus a pinned inactive source), and `f` toggles
a session-local pin. A pin retains completed/disconnected status; explicit choice may move it. Unpinning recomputes
the active choice. With none active and no pin, the pane says idle. The v1 monitor has no authenticated start
order: fallback uses host snapshot-admission order, **not** the TASK's required trusted launch order.

`p` pauses/resumes the selected source's progress display; `c` closes/opens **all observation readers**; `r`
explicitly reconnects all supplied sources. Pause continues draining and observing lifecycle/heartbeat, counting
undisplayed progress. Closing, switching and workspace exit never send a command to a child. Ctrl+W changes layout
only. Global abort still targets the workspace's primary conversation. Other side-pane typing and Enter remain
inert. PageUp/PageDown inspect identities/history without editing the primary draft. These controls never trust
the reported worktree as log authority, and never infer parent membership from an endpoint.

Actual session identity is checked with one read-only `get_state` request per second, at most one outstanding.
A changed/unavailable identity closes readers, clears the pin and holds conversation input; reopen explicitly in
the intended native session. Old endpoint admission is never inherited by another session. This limits legacy
presentation state; it is **not** authenticated registration or cross-parent discovery isolation.

The producer keeps at most four readers, each with a 64 KiB nonblocking send queue; excess/slow readers are
closed rather than holding up stage progress. Workspace rendering drops obsolete frames while terminal output
is backpressured and redraws the latest state on drain, rather than accumulating a frame queue. Each connection begins with the current snapshot; earlier progress
is not replayed. Each of at most eight simultaneous readers retains 100 progress entries / 32 Ki UTF-16 units with omission counts (at most 256 Ki retained progress units in total), admits
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
fight-terminal --provider openai-codex --model gpt-6.1-sol --thinking high \
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

<a id="existing-worktree-test-bindings-partial-task-00502-implementation"></a>

## Automatic session following and worktree test bindings (TASK-00502 candidate)

Automatic following is the default when no legacy socket flags are supplied. **Keep launching the ordinary
provider `.pi/skills/build/scripts/run-stage.py` monitor through Pi's independently selected executor.**
[Build](../../.pi/skills/build/SKILL.md) owns dispatch authority, stage identity and model pins. The unshipped
`fight-stage JSON` bash replacement and human `/fight-stage` command were rejected and removed. Fully restart
Pi to unload the old runtime; resume the same actual session and ledger, not another attempt. `native-launch.mjs`
was replaced by the observation-only `native-observation.mjs`; it has no execution API. Historical receipts still
name the rejected integration and remain history, not current instructions.

The passive extension registers no tools/commands, BashOperations, user_bash handlers, shell hooks or activation
changes. It reads supported actual session lifecycle/notification context without modifying events. Pi's selected
bash (including late custom registrations), disabled tools, custom shell/prefix and deterministic restrictions
remain untouched. RPC cannot select native workflow authority. The observer cannot launch/cancel/retry observed
children or route a refusal locally. Missing, malformed, slow, disconnected or broken observation changes only
visibility, never the command result/control. Closing panes or shutting down observation stops readers only.

### Custody and admission

Actual native TUI session lifecycle supplies session ID/file and canonical primary checkout. It asynchronously
starts a separate passive observation daemon, not an agent executor. Only native context IPC initializes that
daemon and promotes a pending real session-file witness. No discoverable descriptor exists until Pi actually
persists the file; no prompt, custom setup entry or fabricated file forces persistence. Session/path/inode
replacement cannot silently rebind a generation. RPC resume starts no native bootstrap.

Native registration closes at supported `session_shutdown` (new/resume/fork/reload/quit), not the
cancellable `session_before_switch`/`session_before_fork` proposals. Cancellation or target-validation failure
leaves the actual session, route generation and original context-start stamp intact. Successful teardown
invalidates queued observation callbacks; the replacement's `session_start` creates a fresh generation.
No timer recovery, extra restart, command wrapper or child replay is involved. The owning
[`native-lifecycle.py`](tests/native/native-lifecycle.py) check exercises genuine native routes; its explicit
`--with-provider` option adds one harmless parent turn for persisted fork/resume. The companion
[`session-lifecycle.mjs`](tests/native/session-lifecycle.mjs) qualifies public SDK validation/stale-context behavior
without provider calls. These are Engineer diagnostics, not independent QA or a Pi version guarantee.

The ordinary monitor starts its Pi child exactly as before. A best-effort background observer then discovers a
route for its **exact live native ancestor**, across at most 32 registered worktree roots and eight route entries
per root. It verifies native PID/start and the fixed live observation-daemon process/generation/endpoint inode.
Ambiguous nearest native routes are unavailable, never guessed. Its private `var/terminal/monitors/` witness
binds fixed provider monitor PID/start, native PID/start, identity and a fresh random token. Daemon admission
rechecks that witness, fixed monitor path, actual ancestry (at most 32 levels), canonical Git-registered worktree,
freshness and token replay. Qualified shared raw-clock monitor/context stamps plus public macOS `proc_pidinfo`
kernel lineage/birth evidence reject a delayed monitor or surviving old executor from a later context in the same
Pi process. The direct native child in that lineage must unambiguously postdate the context (20 ms safety band);
clock discontinuity over 10 ms, old/ambiguous executor birth or unavailable kernel evidence means unavailable
association, never a guessed session. Task/cwd/command text/process proximity/model-written IDs alone grant no membership.
This bootstrap still trusts local provider processes; it is not independent origin attestation (R1 below).

The monitor's separate nonblocking stream contains only existing safe progress. No command or cancellation
protocol exists. Discovery/acknowledgement is finite and optional; import/setup/connection failure leaves the
ordinary monitor and its child unchanged. A dropped terminal record means unknown/disconnected, not invented
success. The daemon retains random attempt IDs, immutable admitted identity, monotonic monitor start order and safe
bounded history. Progress cannot create membership or rewrite identity. Ordinary executors without qualifying
local fixed-monitor ancestry and unambiguous current-context executor birth, unsupported platforms, missing provider monitor installation or stale/ambiguous
association are explicitly unavailable. They are not relaunched or routed through another executor.

Runtime native routes, monitor bootstrap witnesses, short sockets and session descriptors/preferences live only
under ignored `var/terminal/{native,monitors,s,children}/`. Short socket names permit cross-worktree access without
changing any agent's cwd. The workspace obtains actual Pi `get_state` identity/file and inspects only that
session's bounded descriptor directory. Admission retains current owner-only endpoint inode, fixed observation
process/start/generation, fresh Ed25519 nonce reply, matching actual session-file inode and immutable attempt
identity/sequence. Signing keys stay in daemon memory. Stale/replaced/malformed/cross-session/key-substituted and
late replies are refused. Preferences cannot create membership. No `.runs/` ledger, transcript or request is
read for runtime discovery; attempt paths are display labels only.

**Owner-accepted native-origin limitation (independent Review R1, not repaired):** on 2026-10-21 John explicitly
accepted the [trusted-local-process assumption](../../planning/tasks/00502-TASK.md#owner-approved-trusted-local-process-amendment--2026-10-21).
Local Pi/provider processes and their bootstrap assertions are trusted. Malicious same-user process, agent or
bootstrap impersonation is outside current protection. An ordinary Node parent can fork the genuine fixed
custodian, assert an existing Pi session/file plus installed CLI path, and obtain a signer accepted by discovery.
The signature proves which custodian replied, not that its initializer originated in that Pi session. An existing
session file's inode is not proof of native origin. The earlier owner-decision hold is superseded by this explicit
requirement amendment, not a repaired R1 or independent acceptance.

The qualified supported context/session/RPC APIs provide IDs, paths and lifecycle callbacks, but no native-origin
attestation verifiable independently of that initializer. Another self-generated signer or caller field would not
supply it. [TASK-00507](../../planning/tasks/00507-TASK.md) owns a future bounded low-friction hardening feasibility
decision; it is unstarted and does not block TASK-00502 under the amended scope. Do not treat custody signatures
as independently attested native origin or claim OS/credential isolation. Normal actual-session/file binding,
disjoint honestly bootstrapped parents, stale/replaced/malformed/cross-session payload refusal, finite elapsed
deadlines/cancellation, safe observation-only UI, existing tool restrictions and exact Docker-source validation
remain required. R2's deadline/cancellation repair still needs independent re-review; Review/QA remain pending.

### Selection, lifetime and bounds

With one active member, follow it. With several, retain an active selection despite new siblings/output; when no
active selection remains, choose newest trusted start order, then stable attempt ID. `[` / `]` explicitly selects
an active child (or the pinned inactive child); `f` pins/unpins. Completion, disconnection and stale heartbeat do
not move a pin. Unpin recomputes activity; no active member means honest idle and primary worktree log context.
`p` freezes only the selected display, never lifecycle polling; selecting another child clears that frozen view.
`c` closes/opens observation readers and `r` rediscovers. A lost custodian retains a pinned last-known label but
removes its verified log authority until fresh admission succeeds. Closing panes never cancels execution.

Actual session/file replacement aborts in-flight challenges and invalidates old subscriptions and pins before
rediscovery. Closing observation readers also aborts pending discovery immediately; reopening or a new session
can start fresh discovery without waiting for old work. Late old finalizers cannot clear a new refresh lock.
Conversation input remains held for explicit native-session recovery under the existing policy boundary; RPC
never selects workflow/role authority. Native exit followed by `fight-terminal --session PATH` from the same
canonical parent checkout rediscovers valid still-live custodians without relaunching children. Editable saved
pin/selection preferences are restored only after fresh membership admission; they cannot create authority.

Each observation daemon holds at most **eight monitor associations**, 128 used bootstrap tokens over its lifetime,
and **100 entries / 32 Ki UTF-16 units per child**. Monitor-stream
sequence gaps are reported separately from retained-history eviction; an older custodian without that field
says unknown rather than inventing zero loss. Completed entries
can be pruned after ten minutes. Each workspace inspects at most **eight custodians / seventeen directory entries**
and retains at most **eight children**, preserving its selected/pinned entry under overflow. One refresh is in
flight per current session; ticks skip while it runs. Each custodian challenge has an absolute **one-second
elapsed transport deadline** and **2 MiB** response cap; trickling bytes do not extend it. Challenges are
sequential, not an unbounded subscription fanout. Discovery does not block conversation startup. Each custodian
admits at most sixteen total connections (at most eight monitor associations) with an absolute one-second
query/bootstrap deadline. Each monitor has a 64 KiB nonblocking queue, at most three seconds of background route
discovery/bootstrap (including bounded process inspection and acknowledgement), bounded nine-entry route scans
(to detect an eight-entry overflow), and no reconnect/relaunch on transport loss. A monitor stream has
an absolute 24-hour observation lifetime. Socket completion/error/close/abort
paths clear watchdogs and abort listeners. As with other event-loop timers, deadlines run when the loop can
service them; they are not hard real-time scheduling guarantees. Slow/absent consumers cannot hold stage progress. Unknown liveness is not inferred success.

Custodians expire ten minutes after all monitors exit and the original native connection closes, with an absolute
24-hour custody lifetime. Normal cleanup removes only its exact endpoint/descriptor inodes. A subsequent native
observation startup prunes bounded dead candidates for that session. Crash leftovers remain visibly rejected until this
cleanup; excessive/unrelated entries hold discovery instead of widening a scan. Preferences can remain as small
non-authoritative session-local state. No transcript collector or lossless replay guarantee is added.

### Existing test stacks

Bind already-existing, explicitly authorized **test** stacks once at workspace launch, not per child switch:

```sh
fight-terminal --session /absolute/native-session.jsonl \
  --logs-test /absolute/worktree-a desktop-linux existing-test-a postgres_test \
  --logs-test /absolute/worktree-b desktop-linux existing-test-b postgres_test
```

These are explicit human declarations of canonical worktree, test purpose, context, Compose project and eligible
service, never inferred project prefixes, cwd, model output or arbitrary configuration. Up to eight distinct
worktree/context/project bindings are held in host memory; only the explicitly bound service is eligible by
default, so choosing a test stack cannot expose another production/shared service. The repository's qualified
`bin/database` preparation uses selected Compose services but publishes no trusted association; this supported
human binding is therefore necessary. Saved source files cannot grant a binding. A later workspace invocation
requires explicit binding again, but selection changes within it need no socket/runtime flags. No provisioning,
Compose override, lifecycle action or database write is implemented.

Follow mode uses the selected/pinned child's **custody-verified worktree within the trusted-local-process
assumption**, otherwise the primary checkout. The conversation directory, grants and log source are distinct identities. Within one worktree, a valid reader can
remain; changing worktree/stack/generation stops/reaps it, clears live/paused buffers and rejects late callbacks.
At most one Docker reader runs. Missing, stopped, denied or mismatched sources are explicit, never shared fallback.
Several bound stacks require labelled `{` / `}` selection; service choice is retained only while eligible and its
container generation remains valid. `i` displays full source/endpoint/generation details. Shared mode remains a
separate explicit `--logs-main/context/project/service` choice; with test bindings, `m` toggles it deliberately.

Before every subscription, the fixed adapter checks the current local Unix endpoint, exact canonical checkout
label, eligible service and full running container ID. Test bindings pin the eligible service/container generation;
a recreated/removed/added eligible container or changed endpoint requires inspecting and supplying a fresh human
binding. No source is trusted because of an old receipt. One-service test bindings also narrow metadata queries
to that service. All existing Docker record/buffer/lifetime/reaping limits above remain intact. Pane data and
source choices never enter Pi/model context.

### TASK-00502 qualification

Observation-only redesign evidence is under `.runs/tasks/TASK-00502/runs/observe7/`; see TASK-00502's current
checkpoint and Engineer handoff for actual results, failures, warnings and missing visual proof. Installed Pi
1.3.0 is a tested receipt, never an equality gate. Independent Review/QA remain required. The prior replacement
integration's R3/QA-01 and E1 receipts are preserved; they are not relabelled passive-integration acceptance.

**Historical rejected-integration evidence:** `.runs/tasks/TASK-00502/runs/repair2-imwfDZ3L/` (private, not runtime input):
actual concurrent native parents, direct harmless monitor launches, same-session real workspace resume, disjoint
parent membership, actual supported RPC session replacement, stale/cross-parent/key-substituted registration
rejection, and automatic worktree log switching. The two user-authorized existing sources are main and TASK-00051
`postgres_test`, each checked against its exact live container ID, checkout label and local endpoint; no new stack
was used as observation proof. After observation closed, the TASK-00051 owner replaced its container. The
replacement was inspected only as metadata, and the fixed reader with the prior verified pin refused it before
issuing any log operation; observing that new ID requires fresh human binding/approval. Real 156×40 and 80×24 images show selector, focus, pin/completion, custody loss and
idle-primary states. Raw log-bearing images remain private; inspected header-only crops and metadata/hashes are
sanitized evidence. Disposable capture HOME has no provider credentials and submits no provider prompt on resume;
its Harness is truthfully labelled not-enrolled, not claimed active enrolled qualification.

Pi **1.3.0** and Node **22.23.1** are observed qualification versions, not equality pins. Earlier duplicate-resource,
builtin-metadata, CLI-layout/process-title and capture-helper failures remain preserved, not relabelled success.
Owning Node tests cover admission/selection, signed challenge attacks, source-generation/late-event isolation and
the former native execution operation's cancellation; Python monitor behavior was qualified directly. Those
execution-control cases are historical, not a current observation capability. The actual installed-SDK loading
probe qualifies package/explicit/deduplicated loading and preserves disabled bash (208 outcomes, no provider or
credential calls). Early native captures used the unpublished `native-observation.ts` filename; the final
package entry follows the repository convention as `native-observation-extension.ts`. The final frozen gate/head,
warning inventory, cleanup and remaining limits are recorded in the task's durable Engineer handoff. This is
implementation evidence, not independent Review/QA acceptance.

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
`fight-terminal --session PATH`. Use the displayed exact session path; do not fork/clone it or copy launcher
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

The wrapper accepts repeatable `--logs-test WORKTREE CONTEXT PROJECT SERVICE` for existing test bindings above,
plus `--theme system|light|dark`, `--provider`, `--model`, `--thinking`, `--session`,
`--session-dir`, `--name`, `--extension`, repeatable `--observe-child`, the four `--logs-*` source options above, `--continue`, `--no-session` and `--help`. It does not accept API keys,
initial prompts, arbitrary process modes or extension-disabling flags. The child gets only HOME, PATH, TMPDIR,
locale and terminal variables. Pi may resolve its configured credentials through HOME normally; this is not
credential isolation, nor a promise that ambient-environment-only providers work. No provider values are copied
into child environment variables, UI preferences or another credential store.

Conversation presentation state is memory-only; automatic child selection/pin preferences are scoped under
`var/terminal/children/` as described above. The child pane's separate bounds are described above. Conversation display retention is 500 entries / 256 Ki UTF-16 units, each entry 64 Ki units;
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
