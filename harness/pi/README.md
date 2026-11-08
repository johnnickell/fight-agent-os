# Fight terminal for Pi

The package supplies the Fight welcome/compact header, session-local workflow binding, grouped usage footer,
passive child observation, a bundled upstream SDK-agent engine and optional Herdr companions. It is not the
managed Runner or an execution sandbox. Use native Pi file and shell tools; no evaluator enrollment, credentials,
screening prompts or provider preflight is required.

Explicit [`/skill:sync TASK-NNNNN`](../../.pi/skills/sync/SKILL.md) selects accepted-work coordination with
`skill: sync` / `effect: coordination`, reusing the existing process dispatcher for fresh Engineer then Land.
It requires current independent acceptance and an existing open PR; it is not Build authority or the SDK stage
route. The [all-role SDK default](SUBAGENTS.md#all-role-build-default) is the sole fresh Build execution route,
without a selector flag. Build requires TASK authority, a verified checkout and supported engine capabilities;
missing prerequisites hold rather than selecting process fallback. This source cutover does not migrate active
hosts, change Sync's process route or predeclare final independent acceptance/actual delivery. Skill discovery
does not update a separately installed Harness; adopt reviewed source explicitly at a safe boundary.

[Workflow and Jev retirement](WORKFLOW.md) describes surviving consumers, retained state and safe-boundary
adoption. The installed [workflow rule](WORKFLOW_RULE.md) applies in every participating project; targets need
no copy. Inspect `/fight-workflow` or `fight_workflow_status` for skill/effect/subject/checkout, not file permission.
The independent [deterministic tool policy](TOOL_POLICY.md) is activated only by explicit operator selection.
It restricts its documented shell routes, not native file access or SDK OS calls.

## Prerequisites

Install only what the selected path needs. Nothing here installs tools or changes global settings automatically.

| Path | Prerequisites |
|---|---|
| Native Pi + Fight | Supported installed Pi APIs and its required Node runtime; qualify capabilities, never an exact Pi version |
| Workflow worktree/ref removal | [Worktrunk](https://worktrunk.dev/) and the target's [resource lifecycle](../../docs/engineering/RESOURCE_LIFECYCLE.md) |
| Native workspace | Herdr interactive pane/CLI context and Node `process.execve`; see [workspace capabilities](WORKSPACE.md#native-pi-in-herdr) |
| Application checks/logs | Docker Compose and the target's prepared stack; logs inspect existing verified sources, never provision them |

## Install

Prepare runtime dependencies explicitly. Follow the [supported bundled-agent profile and standalone-companion
migration](SUBAGENTS.md#select-the-supported-profile) **before launching** the updated package. Never load two engines.
From the repository root:

```sh
npm --prefix harness/pi ci --ignore-scripts --legacy-peer-deps --no-audit --no-fund
pi install ./harness/pi
```

Pi stores a personal package declaration. Use `pi install --local ./harness/pi` for repository-only use.
A local install loads this directory without copying it; removing/moving the checkout requires explicit removal
of the stale declaration and selection of the new reviewed path. Running sessions remain unchanged. Installation
does not select a model/workflow, alter credentials/trust, install skills or authorize publication. Normal Pi
package/project trust still applies. The package is private; no npm publication is needed.

Remove only the intended declaration with `pi remove /absolute/path/to/harness/pi`. Preserve sessions, transcripts,
account settings, shared OpenRouter configuration and all historical evaluator receipts/accounting liabilities.
No evaluator-state migration or deletion runs on startup, reload, installation or removal.

## Native Herdr workspace

Ordinary `pi` remains usable without Herdr and never auto-opens panes. After the separate executable installation
below, run `fight-workspace` in the intended checkout inside Herdr. It starts **one native Pi** and opens owned
human-only **AGENTS** and **LOGS** companions. No model turn, blocking log form or automatic RPC fallback is used.
Pi retains editor/completion/Markdown/dialogs/session and workflow authority; Herdr owns layout/focus/resize only.

AGENTS supports Enter-detail/Esc-back inside its pane. Main stays with the coordinator; Ctrl+Shift+A or
`/fight-agents` opens an explicit bounded child-output viewer in main, with Esc restoring the same editor.
LOGS follows verified existing worktree sources: `f` Follow active, `m` Main pin, `s` Services All/subset, `r` retry.
Missing mappings/stack/custody stay unavailable, never silently fall back to main. No log selection enters
conversation context. `/fight-workspace [open|close]` provides manual open/recovery and companion-only close.
See [workspace ownership, controls and recovery](WORKSPACE.md).

### Install the global workspace command

A Pi package install does **not** put `fight-workspace` on PATH. Only an explicit operator action installs the
executable. Inspect the actual user-owned prefix, both command names and package directory. An unrelated file,
symlink, dangling link or package is a collision, not replacement authority. Never use Pi's own prefix, `sudo`,
`--force` or shell-profile edits.

```sh
source="/absolute/reviewed/fight-agent-os/harness/pi"
prefix="$HOME/.local" # inspect the actual user-owned prefix
ls -ld "$prefix/bin/fight-workspace" "$prefix/bin/fight-terminal" "$prefix/lib/node_modules/@fight/agent-os-pi"
# Continue only with absent destinations or a verified owned prior installation:
npm install --global --prefix "$prefix" --install-links --ignore-scripts --legacy-peer-deps --no-audit --no-fund "$source"
export PATH="$prefix/bin:$PATH" # current shell only
hash -r
fight-workspace --help
pi install "$prefix/lib/node_modules/@fight/agent-os-pi"
```

`--install-links` creates a copied package and owned executable link. Keep **one complete Harness identity** in
Pi settings, matching this executable; inspect `pi list` and explicitly remove only a replaced source declaration.
Do not load the copy beside a source checkout. Fully exit/restart only intended owned clients at a safe boundary.
Active sessions/shared installations are never updated by repository implementation or qualification.

The launcher forwards separate argv values, preserving spaces and literal punctuation. `--session`, `--continue`,
`--resume`, `--no-session` are mutually exclusive. Exact-file `--session` requires an absolute existing JSONL file
with the caller cwd in its header. It never guesses an ID or forks another project's history. Supported forwarding
includes provider/model/thinking/scope, session directory/name, theme/TUI mode, offline/verbose and header choices.
Prompts/@files, mode/print/export, API keys, trust/resource overrides and unknown extension flags are refused before
Pi starts. The one-use startup hook checks exact command owners and observer/opener capabilities; failures leave
native Pi running with a warning, not a second process. This is not malicious-local-process attestation.

### Update, remove and recover the launcher

Copied installations keep old code until explicitly updated or removed. Inspect all three destinations above,
fully exit only owned intended clients, then run the same reviewed npm installation command for an authorized
update. Verify the obsolete owned `fight-terminal` link is absent. Do not delete an unrelated command elsewhere
on PATH. To remove just that inspected npm copy:

```sh
npm uninstall --global --prefix "$prefix" --ignore-scripts --no-audit --no-fund @fight/agent-os-pi
hash -r
```

This preserves saved history, account/provider settings and the separate Pi declaration. Remove a stale declaration
explicitly only when appropriate. `pi update` without a target updates Pi, not this copied package.

| Failure | Recovery |
|---|---|
| Missing/filtered/duplicate Harness | Inspect `pi list`, exact resource paths and filters; select one reviewed complete package, then fully restart |
| Missing/incompatible Herdr | Use ordinary Pi; no automatic RPC fallback or installation |
| Unexpected prefix ownership or npm collision | Stop for the owner; never force-overwrite |
| Unknown actual session file | Inspect its prior selected store privately; do not guess newest/continue/fork |
| `fight-terminal` still resolves | It is an older/separate installation; inspect exact prefix and ownership |

## Module path migration

The manifest loads capability-named `*-extension.ts` entries exactly once, including `workflow-extension.ts`.
The retired `jev-extension.ts`, `file-screening-integration.ts`, `screening-bootstrap.mjs`, enrollment UI and
`src/jev/` have no compatibility shim. Replace any explicit retired entry/filter with the complete package or
`workflow-extension.ts` in the intended scope only. A broad `src/*.ts` glob is unsupported. Header-only loading
uses `src/header-extension.ts`, not the old `src/extension.ts`. See [safe-boundary adoption](WORKFLOW.md#safe-boundary-adoption).

## Appearance

Default `auto` shows the approved F welcome, then one compact identity at first Agent activity. Resumed sessions
start compact. `/fight-header auto|port|wordmark|compact|ascii|off` changes this session only; `off` restores Pi's
header. `--fight-header` wins over `FIGHT_PI_HEADER`. Unknown choices warn; invalid startup choices use `auto`.
Narrow/dumb/NO_COLOR terminals use ASCII. Active theme colors remain authoritative. No header renders in JSON,
print or RPC. Native image reads need only a path and preserve Pi's format/truncation behavior.

The workflow extension installs the TUI-only grouped footer through public Pi APIs: session usage/context,
model/thinking, directory/branch and extension statuses. It changes presentation, not accounting or Codex polling;
last installed footer wins and shutdown restores Pi's footer. API-unavailable native decorations are not recreated.

## Verification and packaging

Run owning Harness behavior checks with `npm --prefix harness/pi test` or the network-none
`./bin/harness-check`, which reuses this checkout's explicitly prepared tooling image (`./bin/up --build-only`).
Git and Python are required by the surviving shared-custody/Sync process checks; no tools are installed by acceptance. The canonical `./bin/build` additionally checks the bundled engine and application gates.
No product tests assert tooling/configuration behavior. Prepare the engine's locked development tools explicitly
on the OS where checks execute (native optional modules are platform-specific):

```sh
npm --prefix harness/pi/vendor/pi-subagents ci --ignore-scripts --legacy-peer-deps --no-audit --no-fund
```

For Docker, use the selected checkout's writable maintenance service and task-owned scratch/cache locations.
Never replace dependencies beneath a running gate. Pi supplies host peers; do not bundle another SDK.
`croner` and `nanoid` remain actual bundled-engine dependencies. `npm pack --ignore-scripts --pack-destination PATH`
creates a private distributable containing runtime source, bin, agents/profile, runbooks/licenses, not tests or
node_modules. Keep artifacts under ignored task scratch.

Use explicit isolated native/SDK probes in [qualification](WORKFLOW.md#direct-qualification). Tested Pi versions
are evidence, not equality gates or future compatibility promises. Controlled scripted model responses are not
live provider qualification, a genuine Build assignment, native visual evidence or independent acceptance.
