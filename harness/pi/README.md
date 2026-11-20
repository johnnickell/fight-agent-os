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

Native automatic/manual compaction and exact-session resume use Pi's session history, not Jev or a parallel
checkpoint store. Follow [verified continuation](COMPACTION.md) to retain required facts and revalidate current
workflow/operation authority; missing evidence and uncertain effects pause dependent work without replay.
The runbook records tested capabilities, separate measurements and unqualified paths, not guaranteed recall.

## Prerequisites

Install only what the selected path needs. Nothing here installs tools or changes global settings automatically.

| Path | Prerequisites |
|---|---|
| Native Pi + Fight | Supported installed Pi APIs and its required Node runtime; qualify capabilities, never an exact Pi version |
| Workflow worktree/ref removal | [Worktrunk](https://worktrunk.dev/) and the target's [resource lifecycle](../../docs/engineering/RESOURCE_LIFECYCLE.md) |
| Native workspace | Herdr interactive pane/CLI context and Node `process.execve`; see [workspace capabilities](WORKSPACE.md#native-pi-in-herdr) |
| Application checks/logs | Docker Compose and the target's prepared stack; logs inspect existing verified sources, never provision them |
| Compact shell output | Optional RTK on PATH with a working `rtk rewrite` command |

## RTK and graph navigation

The packaged RTK extension uses Pi's native `tool_call` hook and the installed RTK rewrite registry.
Supported bash commands are rewritten automatically; unsupported commands, native file tools and repository
wrappers retain their execution paths. No separate RTK Pi hook is needed alongside this package. Missing or
failing RTK warns once and leaves commands native. Rewrites that turn shell assignments into RTK commands
are rejected: the original script runs unchanged and RTK remains available for subsequent commands.
`rtk proxy COMMAND` requests raw output; use `rtk proxy bash -c 'script'` when a script must execute unchanged.
Keep RTK enabled by default. `RTK_DISABLED=1 pi` is an operator-requested session opt-out, not an agent recovery
step. Explicit `--fight-tool-policy` selection disables the optimizer so approved script arguments remain
exact. RTK changes output presentation, not permissions.
Use `rtk gain` to inspect actual recorded savings. No RTK installation, filter trust or global settings change
runs automatically. The integration follows the [upstream Pi hook](https://github.com/rtk-ai/rtk/tree/develop/hooks/pi)
and is qualified against installed capabilities rather than a pinned Pi version.

The installed workflow guidance directs agents to query existing Graphify graphs before broad source discovery,
verify findings against current files and notify you when relevant graph coverage is stale. The notice includes
the actual project's refresh command. Structural `graphify update` does not refresh document semantics;
semantic maintenance stays an explicit scoped Graphify skill operation. No graph rebuilding or hooks run on startup.

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

For Pi changes, run the owning checks from the repository root, separately from the required application gate
(`./bin/build`). Run Harness behavior with `npm --prefix harness/pi test` or the network-none
`./bin/harness-check`, which reuses this checkout's tooling image (`./bin/up --build-only` if missing).
Git and Python are required by the custody and Sync process checks. Use the locked Harness runtime dependencies
where needed (`npm --prefix harness/pi ci --ignore-scripts --legacy-peer-deps --no-audit --no-fund` if missing
or stale in that environment).

For changes affecting the bundled engine, run `npm --prefix harness/pi/vendor/pi-subagents run check` for
lint, type checking, and the full engine suite. Prepare its locked development tools only if missing or stale
on the OS running the checks; native optional modules are platform-specific:

```sh
npm --prefix harness/pi/vendor/pi-subagents ci --ignore-scripts --legacy-peer-deps --no-audit --no-fund
```

For Docker setup, use the selected checkout's writable maintenance service. Run engine tests with `TMPDIR`
and `GIT_CEILING_DIRECTORIES` set to the same private task-owned scratch directory outside source control.
Never replace dependencies beneath a running check. Pi supplies host peers; do not bundle another SDK.
No product tests assert tooling/configuration behavior.
`croner` and `nanoid` remain actual bundled-engine dependencies. `npm pack --ignore-scripts --pack-destination PATH`
creates a private distributable containing runtime source, bin, agents/profile, runbooks/licenses, not tests or
node_modules. Keep artifacts under ignored task scratch.

Use explicit isolated native/SDK probes in [qualification](WORKFLOW.md#direct-qualification). Tested Pi versions
are evidence, not equality gates or future compatibility promises. Controlled scripted model responses are not
live provider qualification, a genuine Build assignment, native visual evidence or independent acceptance.

## Local project names

The packaged `fight-local` command uses this machine's explicitly configured supplier
(`FIGHT_INGRESS_SUPPLIER`, otherwise the user config `fight/local-ingress.json` under
`XDG_CONFIG_HOME` or `~/.config`). Configure that pointer with the reviewed supplier's
`bin/local-domain install`. There are no author-specific paths or DockerProxy requirements.
The supplier owns canonical `var/local-ingress/hosts.json`; installed package copies
never carry a duplicate registry. `fight-local plan --project-root /absolute/app
--namespace local.example.com --primary` previews enrollment; `fight-local apply`
requires the printed plan path. Repository `bin/up --worktree PATH --task TASK-NNNNN`
maintains bindings only for already enrolled projects. Python 3 is required, as for
repository local tooling. Trust and scoped OS DNS enrollment remain explicit.

Future local browser Project creation should call this same supplier registration
operation. Hosted Project creation may offer a reviewed local command; it cannot
write the user's workstation registry directly. This package supplies the local
command, not hosted enrollment or a remote shell bridge.
