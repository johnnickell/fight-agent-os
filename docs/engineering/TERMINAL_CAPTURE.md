# Terminal screenshots for QA

Use this procedure for applicable macOS TUI visuals under the [QA capture scope](QA.md#terminal-capture-authority).
It captures the real terminal through installed Peekaboo; transcripts remain separate interaction evidence.
The helper manages iTerm sessions and checks their identity. It is trusted local tooling, not process isolation.
Use the provider's [terminal-window helper](../../.pi/skills/qa/scripts/terminal-window.applescript) even when the
target is another repository. Evidence and launchers belong to the **target's** canonical ignored QA run.

## Preflight

Run these through the same host execution route that will perform the capture:

```sh
peekaboo --version
peekaboo permissions status --all-sources
peekaboo bridge status --verbose
```

The qualified command surface is Peekaboo 4.9.5 on macOS with iTerm2. For another version, inspect local
`peekaboo see --help`, `peekaboo window list --help` and input help before using changed options.
For background agents, select a reachable, permitted Peekaboo Bridge. Screen Recording permits capture;
Accessibility supports window/UI interaction; keyboard delivery may also require Event Synthesizing. Local
permissions can be absent while the selected bridge has every required grant. Check the **selected source**.
No AI provider, API key, `--analyze`, standalone Peekaboo agent or MCP installation is needed for PNG capture.

Use `bridge status --verbose` to identify the operator-permitted host. Automatic selection can prefer an
ungranted daemon even while the permitted host is running. Set `qa_bridge_socket` to that host's observed socket
path, verify its permissions explicitly, and pin it on **every** subsequent Peekaboo operation:

```sh
peekaboo permissions status --all-sources --bridge-socket "$qa_bridge_socket"
peekaboo bridge status --bridge-socket "$qa_bridge_socket"
```

Do not guess a socket path or treat a reachable unapproved host as authorized. If the pinned host disappears,
recheck it and report the prerequisite; do not silently select another host or capture locally.

If a sandboxed call selects local fallback or cannot reach the bridge socket, use the execution host's supported
approval/escalation mechanism for the bounded check/capture, within the existing QA authority. Do not change
sandbox configuration, grant a broad command exemption, disable screening or use `--no-remote` to bypass a denial.
Respect actual rejections. If the authorized route is unavailable, record that exact blocker. Do not diagnose
missing macOS permission solely from a sandboxed local-runtime result or ask the operator to re-grant it blindly.

Missing installation or genuine OS grants need operator setup once, not consent for every image. See Peekaboo's
[installation](https://peekaboo.sh/install.html) and [permission guidance](https://peekaboo.sh/permissions.html).
QA does not install software, configure providers or change system permissions automatically.

## Create and bind an owned terminal

1. Resolve the target, reviewed source, canonical QA run and permitted scenario effects under [QA standards](QA.md).
   Use a private run directory (`umask 077`). Reject symlinked paths escaping it. Set `qa_run` to its absolute path
   and `qa_window_helper` to the absolute provider helper path resolved from this document's link.
2. Write and inspect `$qa_run/launch.sh`. It must launch the actual reviewed CLI with disposable configuration,
   explicit fixture/source paths and bounded effects/timeouts. The helper starts it with an empty environment,
   disposable HOME, system-only PATH and no shell startup files. Resolve required runtimes such as Node explicitly;
   do not inherit credentials or the user's interactive shell. Create `$qa_run/terminal-home` first. Keep an owned
   session alive until capture, using a bounded wait if its scenario command exits. Never replay logs as UI proof.
3. Create the session and retain the returned identity:

   ```sh
   osascript "$qa_window_helper" create "$qa_run" 100 30 > "$qa_run/terminal-identity.txt"
   ```

   The result is `window_id|session_id|tty|columns|rows`. Parse it into `qa_window_id` and `qa_session_id`, preserving
   the remaining fields as evidence. These are returned identities, never guessed IDs or the frontmost window.
   Dimensions are actual values: iTerm may constrain tiny requests. Verify the intended scenario size was reached.
4. Before every capture or input, verify the same session:

   ```sh
   osascript "$qa_window_helper" verify "$qa_window_id" "$qa_session_id"
   peekaboo window list --app com.googlecode.iterm2 --bridge-socket "$qa_bridge_socket" --json
   ```

   Match the returned window ID in iTerm's Peekaboo inventory. Retain only that window's metadata; other titles
   are unrelated private context. AppleScript identity alone is not proof that a live capture window exists.
   Pi can change the title; do not select by title, window index or whichever terminal is active. If the ID is
   absent, mismatched or ambiguous, stop that capture and diagnose the owned session. Never fall back to a desktop
   image. The helper refuses a changed current session, including a user selecting another tab in the owned window.

## Capture, interact and inspect

First capture a harmless disposable terminal state and inspect its actual image to prove that the route returns
terminal pixels. A successful exit or blank/wallpaper image is insufficient. Then exercise the required real
states, preserving input/state assertions alongside the PNGs. Use a fresh scenario filename for each capture:

```sh
peekaboo see --app com.googlecode.iterm2 --window-id "$qa_window_id" \
  --bridge-socket "$qa_bridge_socket" --no-elements \
  --path "$qa_run/captures/preview.png" --json > "$qa_run/captures/preview.json"
```

Create `captures/` first. Require `success: true`, the expected window ID and exact output path in the result,
then open the saved PNG with the agent's image-reading tool. Inspect legibility, clipping, expected state and
absence of unrelated/private content. Do not treat PNG existence as inspection or a behavioral PASS.
Preserve the screenshot receipt and timestamp; record source/runtime, scenario, dimensions, theme and final
sanitized image SHA-256 under the [evidence requirements](QA.md#visual-evidence). Do not reuse stale output files.

For input, use iTerm's bound session or Peekaboo's exact `--window-id` and fresh capture `snapshot_id`; follow the
installed command help. For example, a scenario's Return key can use:

```sh
peekaboo press Return --app com.googlecode.iterm2 --window-id "$qa_window_id" \
  --bridge-socket "$qa_bridge_socket" --snapshot "$qa_snapshot_id"
```

Observe the resulting state and refresh the receipt before further input. No targetless or global keystrokes,
foreground switching or broad desktop capture is implicit. A capture grant does not authorize a product save,
account change or provider request outside the selected disposable scenario. Refused input is an input-path
blocker, not permission to redirect keys to another window.

Resize through the owned session, verify the returned actual dimensions, wait for rendering and recapture:

```sh
osascript "$qa_window_helper" resize "$qa_window_id" "$qa_session_id" 80 24
```

Use comparable baseline/result scenarios and required narrow/scroll/confirmation states. A new feature can use
“Not present before”; neither a fabricated baseline nor a screenshot of transcript text replaces a real state.

## Cleanup and handoff

After collecting evidence, close only the matching owned session:

```sh
osascript "$qa_window_helper" close "$qa_window_id" "$qa_session_id"
```

Retain evidence and any useful failed fixtures. A timed-out create can leave an owned window: reconcile its
identity before retrying; never close unrelated windows or create an unbounded sequence. Report failed cleanup.
Carry the selected host/bridge route, version, owned identities, captured states and limitations into the QA report.
Missing tool, unreachable bridge, OS denial, stale window and invalid image are distinct prerequisites; name the
actual one with its recovery action. Required visual proof still missing means INCOMPLETE. Local capture does
not upload images, revise technical acceptance or authorize landing.
