# Fight terminal for Pi

Version 0.1.0 supplies the approved Fight Agent OS welcome mark and compact working header (qualified with Pi
0.87.1), plus operator-only Jev setup/synthetic readiness and a trusted-consumer typed-judgment capability (Pi 1.2.0 on
macOS). Mixed typed forms have controlled Pi integration evidence, not live qualification; see
[bounded typed judgments](JUDGMENTS.md). One authorized live Decisions
check succeeded on October 14, 2026; independent acceptance remains pending. A synthetic result does not establish
model accuracy or another installation's account access. This is not the
managed Runner or an execution sandbox. TASK-00160 adds an explicitly selected
[deterministic tool policy](TOOL_POLICY.md) for compatible stable Pi 1.x (>=1.2.0) macOS APFS sessions: digest-bound script/argv admission and denial of unsupported helper/nested/child tools. It says **deterministic
protection only**, not Jev screening or process isolation; installation alone never activates it.
TASK-00162 adds opt-in, instruction-based single-file
screening; TASK-00163 extends it with bounded lists/globs and session-local metadata caching; see [operator policy, session activation and qualification limits](FILE_SCREENING.md). No global policy
is activated by installing/updating this source. TASK-00309's [enrolled-profile implementation candidate](FILE_SCREENING_PROFILE.md)
adds a stable `/file-screening` diagnostic, explicit enrollment preview, shared monthly accounting and local
workflow metadata, optional discovery and conservative legacy reconciliation. Project enrollment stands until revocation/
authority change, with explicit conversion of historical timeboxed profiles. Use ordinary Pi and the owning provider's
`/skill:build TASK-NNNNN`: shell/Git/checks use ordinary Pi execution without Jev command screening or confirmations.
Jev is optional: no setup, credentials or enrollment is required for normal native tools. Shell can bypass relevance screening and same-user state is not confined.
See the profile's [launcher contract](FILE_SCREENING_PROFILE.md#coverage-and-normal-build). Limited live synthetic
boundary evidence exists; representative investigations, visual proof and independent acceptance remain pending.
Local checks do not establish production readiness. Both protected profiles share a
[runtime compatibility contract](TOOL_POLICY.md#runtime-compatibility): compatible 1.x minor/patch upgrades
need no allowlist update or reenrollment; missing required hooks/APIs still hold activation. Restart Pi after
updating guard code or the runtime. Enrollment, monthly/session accounting, pending liabilities and finite test
counters are retained; do not rerun setup or delete state merely to adopt a compatible Pi release.

Routine interactive screening is quiet: startup/turn status and success events do not dump JSON or write around
the input editor. Use `/file-screening inspect` for details; actionable file-screening warnings remain.
The installed [shared rule](FILE_SCREENING_RULE.md) supplies portable guidance in every participating project;
targets do not need a copy. Strongly prefer Jev before unfamiliar discovery; read known targets and core project
instructions directly with `read` and `path`. Large Boards support ordinary bounded reads and continuation.
Negative/unavailable relevance results need a concrete reason for direct reading, not an access override.
Native `read` is not intercepted by either harness profile. Logs, images, links and cross-project
instructions use Pi and OS permissions without harness receipts, exclusions or enrollment. Optional discovery
refusals do not prohibit native reads. Writes and edits also use native Pi behavior; evaluator disclosure keeps its separate controls.

Interactive `/skill:work`, `/skill:review`, `/skill:qa` and `/skill:land` select workflow responsibilities;
`/file-screening role engineer|reviewer|qa|land` is the explicit operator alternative. Engineer/Land edit normal
project files, including `.env.example`, instructions and `.pi/skills`, in their selected checkout. Repository
enrollment includes verified linked worktrees without another setup; select an existing checkout choice with
`/skill:work TASK-NNNNN --checkout /absolute/path`. Reviewer/QA and Build write only their task-owned reports and
scratch, including canonical base paths from an external worktree. The [skill/effect inventory](FILE_SCREENING_PROFILE.md#installed-skill-effects)
defines every installed skill and mode. Explicit hidden provider Build/Next instructions and their exact linked
references work in enrolled consumers without enrolling the provider root or allowing automatic invocation.
Native file tools are independent of workflow roles, exclusions and integrity holds. Native shell receives no Jev assessment; discovery checks do not provide shell/OS containment. Restart Pi after upgrades
and reselect the workflow for older sessions. See the [profile](FILE_SCREENING_PROFILE.md) for limits/recovery.

In the native TUI, Codex weekly usage has its own footer row; `File screening: active · Jev: configured` appears
on the next row. Narrow terminals wrap rather than hide the statuses. `configured` means valid local Jev setup,
not a successful provider probe. `/jev inspect` shows `state: configured` with a separate optional
`selfCheck.state: no-retained-result` when the current runtime has no result. That does not prove a check was
never run; its actual result, if retained, remains in `outcome`. No self-check,
credential resolution or polling is triggered by drawing the footer or inspecting configuration.
Ask the Agent to inspect screenshots normally: image `read` calls need only a path, including external Desktop
files. No special command, path-only message, input selection or approval popup is required. Images go directly
to the main model, not Jev; optional text relevance screening remains available.
See [images and screenshots](FILE_SCREENING_PROFILE.md#images-and-screenshots) for supported forms and retained
native format and output handling.

## Native Herdr workspace candidate

`/fight-workspace [open|close]` preserves native Pi inside Herdr. TASK-00511 connects the owned child companion
to current-session observation with selection/pinning, pause/drain and validated lifetime metadata; the log
companion remains a truthful TASK-00512 placeholder. Herdr owns layout,
focus, resize and zoom; Pi retains Tab completion. Missing or ambiguous prerequisites leave ordinary Pi usable,
with no automatic RPC fallback. See [native setup, ownership and recovery](WORKSPACE.md#native-pi-in-herdr-task-00510-candidate).
The action performs no global installation or workflow selection. Independent acceptance remains pending.

## Responsive local workspace candidate (retained RPC client)

The package-owned global `fight-terminal` command starts a dedicated Pi RPC terminal client with a persistent half/quarter/
quarter split and narrow selectable views. TASK-00502 adds current-session native-child custody and automatic
selected/pinned worktree following; explicit human bindings observe existing test services through the fixed
Docker reader. Its owner-approved trusted-local-process assumption does not prevent malicious same-user bootstrap
impersonation or independently attest native origin; see the [custody boundary](WORKSPACE.md#custody-and-admission).
No managed integration or container lifecycle operation is added. The explicit user-level launcher installation below does not install Pi or select Harness policy. Read the [workspace runbook](WORKSPACE.md) for input, policy,
session and cleanup boundaries. Coordinator RPC/PTY/provider/file-hook checks, real held-state images and a
recovered passing pre-commit full gate are recorded there with limitations; the coordinator's post-commit gate
and independent Review/QA remain pending.
Normal Pi remains the operator path.

## Install

From the repository root, install for every Pi session:

```sh
pi install ./harness/pi
```

Pi stores its package declaration in personal settings. Restart Pi to load the package; an already running
session is unchanged. A local install resolves to this directory, so moving/removing the checkout requires
removing the old declaration and installing from the new location. Source updates take effect in later sessions.
No npm registry publication is required. The manifest is private to prevent accidental publication.

For repository-only use, use `pi install --local ./harness/pi` instead. To remove the personal installation:

```sh
pi remove /absolute/path/to/fight-agent-os/harness/pi
```

A **Pi** install changes only Pi's package declaration; it does not put `fight-terminal` on PATH. It does not select
models, alter credentials, install skills, authorize tools, or overwrite another theme. Pi's normal package/project
trust rules still apply.

### Install the global workspace command

Prerequisites: a trusted local Harness source, Node 22 or later, npm and an independently installed `pi` on PATH.
Qualify the supported installed Pi CLI/RPC and healthy Harness status in normal Pi, not by version equality.
The commands below were qualified with Node 22.23.1, npm 10.9.8 and Pi 1.4.0; these are receipts, not pins.
Use a user-owned prefix; never `sudo`, `--force`, or the user's Pi installation prefix. From the repository root:

```sh
source="$PWD/harness/pi"
prefix="$HOME/.local"  # choose your own user-owned prefix
command -v fight-terminal  # if present, inspect it BEFORE installing
ls -ld "$prefix/bin/fight-terminal" "$prefix/lib/node_modules/@fight/agent-os-pi" 2>/dev/null
```

Stop if either destination or an earlier PATH command belongs to something else. Do not remove it or overwrite
it; choose a different prefix/resolve PATH with its owner. For a fresh installation, both destinations must be
absent. npm also refuses an unrelated executable at the destination with `EEXIST`; do not retry with `--force`.
After inspecting the trusted source and destinations, install explicitly:

```sh
npm install --global --prefix "$prefix" --install-links --ignore-scripts --legacy-peer-deps --no-audit --no-fund "$source"
export PATH="$prefix/bin:$PATH"  # current shell only; no profile is edited
command -v fight-terminal
fight-terminal --help
cd "/path/to/your project"
fight-terminal
```

`--install-links` is intentional: npm **copies** the local package into
`$prefix/lib/node_modules/@fight/agent-os-pi` and symlinks `$prefix/bin/fight-terminal` to its package-owned
executable. `--legacy-peer-deps` prevents npm from installing the host-provided peers (plain `--omit=peer`
did not do so in the qualified global install). It does not link the source checkout or install Pi/host peers. Assets resolve relative to that
executable, not the target project's files; the caller's directory is preserved, including spaces and registered
worktrees. `private: true` remains set. No registry publication is needed. Help proves only command resolution;
real startup must show the actual conversation directory/session and healthy Harness access before input works.
Do not automatically enroll a project to make that check green: explicit not-enrolled access is supported.

The separate `pi install "$source"` declaration still loads Harness extensions **from the source path**, not the
npm copy. Preserve that trusted source and use `pi list` to inspect the actual installation. Do not add the copied
package as another Pi resource alongside the existing source: different package paths can duplicate commands and
protected resources. The launcher keeps normal Pi discovery; it never forces its own copy as an extension.

### Update, remove and recover the launcher

For an update, fully exit owned workspace/Pi processes, review the new trusted source and confirm both installed
destinations still belong to this package. Rerun the exact npm install command above (including `--install-links`);
then restart and requalify actual Harness startup/session identity. Source edits alone do **not** update the copied
launcher. Pi's local-source extensions use the source's new contents on the next full process startup. Do not use
`pi update` without a target as a launcher update: that updates Pi itself.

Remove only this npm installation:

```sh
npm uninstall --global --prefix "$prefix" --ignore-scripts --no-audit --no-fund @fight/agent-os-pi
hash -r
```

This removes its copied package and bin link, not Pi settings/credentials, enrollment/accounting or any saved
project `var/terminal/` sessions. Unrelated prefix packages/commands remain. It does **not** remove the separate
Pi source declaration. Only if you also intend to stop loading the Harness, explicitly use `pi remove` as above;
that is a separate operator action, never a launcher-removal side effect.

| Failure | Recovery |
|---|---|
| `fight-terminal` not found | Check the chosen prefix's `bin` on this shell's PATH and `command -v`; reinstall in that prefix if absent. No shell profile is changed automatically. |
| Missing Node or Pi | Install the missing prerequisite separately, make it available on PATH and qualify normal Pi; never replace the user's `pi`. |
| npm `EEXIST`, unexpected command or package destination | Stop and inspect ownership; choose another prefix or ask the command's owner. Never force replacement. |
| Source moved/deleted | The copied launcher survives. The separate Pi declaration does not: in normal Pi remove the exact stale source declaration and install the reviewed new source path. Reinstall the launcher from that new source for future updates. Do not delete settings/enrollment/sessions. |
| Older manual `npm link`/default linked install breaks after source move | Inspect the dangling command/package links; remove only that owned npm installation using the explicit prefix, then reinstall with `--install-links` from the current source. |
| Missing/corrupt installed assets | Reinstall the trusted package in the same inspected prefix; never search the target project for an executable. |
| Missing, filtered, duplicate or unavailable Harness | Input stays held or startup fails. Inspect `pi list`, resource filters and explicit `--extension` paths in normal Pi; retain one trusted package identity, fully restart and verify status. Never disable protections or reenroll automatically. |
| Unwritable project runtime | Restore authorized write access to the caller's `var/terminal/` (or explicitly choose permitted `--session-dir`); do not silently run in the provider checkout. |

Repository `bin/terminal` was retired by TASK-00503; replace current launch/resume invocations with
`fight-terminal`, retaining their existing supported arguments. Historical receipts still name the old entry point.

## Module path migration

Module names follow the repository's [Pi/TypeScript convention](../../docs/engineering/STANDARDS.md#pi-and-typescript-module-naming).
The manifest loads `header-extension.ts`, `jev-extension.ts`, `tool-policy-extension.ts` and
`native-observation-extension.ts` and `herdr-workspace-extension.ts` exactly once.
`file-screening-integration.ts` is internal to the Jev adapter; do not load it as an extension.

| Old path under `harness/pi/src/` | Current path | Compatibility |
|---|---|---|
| `extension.ts` | `header-extension.ts` | Old file removed; update explicit paths |
| `file-screening-extension.ts` | `file-screening-integration.ts` | Internal import moved; `installFileScreening` export unchanged |
| `jev-extension.ts` | Unchanged | Default registration and named `installJev` export retained |
| `tool-policy-extension.ts` | Unchanged | Independent policy selection unchanged |
| — | `native-observation-extension.ts` | New native fixed-monitor association; ordinary tools and selected executors retain their restrictions |
| — | `herdr-workspace-extension.ts` | Native Herdr layout and human-only child companion; log placeholder, no RPC fallback |

The consumer inventory found no supported caller requiring an old-path shim. Package-root installations
(`pi install ./harness/pi` or `pi --extension ./harness/pi`) follow the manifest and need no path change.
For header-only loading, use `pi --extension ./harness/pi/src/header-extension.ts` instead of the removed
`src/extension.ts`. Update any operator-owned explicit extension path or package resource filter in the
appropriate settings scope yourself; this migration does not edit settings or global installation paths.
Do not combine package loading with a second copy of its entry points or use a broad `src/*.ts` glob.

If Pi reports a missing old file, stop that process, replace the old path with the corresponding current path
(or select the package root), and start Pi again. An absent header after a package update can also mean an old
resource filter no longer matches; select the new entry in `pi config` in the intended scope. After authorized
adoption, **fully exit and restart the Pi process**; `/reload` alone is not the adoption qualification for
renamed guard modules. Do not rerun Jev setup, reenroll projects, reset accounting/ledgers, change models or
restart sessions automatically. Existing commands, tool IDs, permissions and deterministic-policy decisions
are unchanged.

## Appearance

The default `auto` mode shows the refined F welcome and switches to a one-line identity at the first Agent run.
Resumed conversations start compact. Choose another mode for the current session with `/fight-header`, or use
`/fight-header port`, `wordmark`, `compact`, `ascii`, `auto`, or `off`. `off` restores Pi's own header.

Use `pi --fight-header compact` for a quiet startup, or set `FIGHT_PI_HEADER=compact` in the launching environment.
The CLI flag takes precedence over the environment. Preferences are not silently written to personal files.
Unknown options produce a warning; an invalid startup option falls back to `auto`.

Narrow terminals use a short ASCII identity. `TERM=dumb` or `NO_COLOR` also uses unstyled ASCII. Pi's built-in
light/dark themes use the approved orange accent; custom themes keep their own accent. Other colors follow the
active theme. JSON/print/RPC output receives no header. The header extension leaves Pi's editor and error messages
alone. The separate Jev extension uses Pi's supported custom-footer API for grouped status rows, with current
session usage, context, selected model, directory/branch and other extension statuses from read-only public APIs.
This changes presentation only, not accounting or Codex polling. Its footer is TUI-only and is removed at shutdown;
like other custom footers, the last installed footer wins. Native-only decorations not exposed by these APIs,
such as virtual-route annotations, are not reproduced.

The mark is an approved terminal adaptation of this repository's `docs/assets/fight-agent-os-dark.svg`, with
symmetric light squares above/below the orange entry point. It uses terminal block characters, not an image
protocol. The separate prototype is not a runtime dependency.

## Verification and packaging

`./bin/harness-check` (from the repository root) runs owned presentation, Jev and deterministic-policy behavior tests in the pinned Node container. The
full `./bin/build` includes this check. If the check image is missing, explicitly fetch it first:

```sh
docker pull node@sha256:dd9d21971ec4395903fa6143c2b9267d048ae01ca6d3ea96f16cb30df6187d94
```

For direct development with Node 22 or later, `npm test` in this directory runs the same tests. Pi supplies the
declared peer dependencies when loading extensions. There are no third-party runtime dependencies to install.
Use `npm pack --ignore-scripts --pack-destination <output-directory>` to create a distributable tarball; package
contents include the manifest, package-owned bin, source and declared runbooks/license. Keep local build artifacts under `.runs/`.

Controlled behavior tests are separate from Pi integration qualification. For installed-SDK loading and
public behavior checks, set `PI_SDK_MODULES` to the reviewed Pi installation's `node_modules` directory and run
from the repository root, using a fresh task-owned evidence directory:

```sh
node harness/pi/tests/native/module-loading.mjs "$PI_SDK_MODULES" .runs/tasks/TASK-00495/runs/loading
node harness/pi/tests/native/optional-jev.mjs "$PI_SDK_MODULES" .runs/tasks/TASK-00495/runs/optional-jev
node harness/pi/tests/native/read-policy.mjs "$PI_SDK_MODULES" .runs/tasks/TASK-00495/runs/read-policy
node harness/pi/tests/native/worktree-access.mjs "$PI_SDK_MODULES" .runs/tasks/TASK-00495/runs/worktree-access
```

These probes use disposable operator/session fixtures and controlled provider boundaries, not live Jev calls.
The loading probe exercises the manifest, explicit entries, deduplication, header-only loading, JSON mode,
header commands and obsolete-path recovery. Its header callbacks are not terminal screenshots. Record the
actual SDK version and required API/hook availability; a tested version is evidence, not an equality gate or
a promise about untested runtimes. Real terminal/font rendering still varies; a browser design mockup must not
be presented as a native terminal screenshot.

## Jev setup and readiness (operator only)

Load the trusted package through the installation above, or explicitly for one invocation with
`pi --extension ./harness/pi`. Nothing resolves Jev credentials or sends a Decisions request on load/reload.
Setup commands are TUI-only; no generic Agent-callable judgment or credential tool is registered.
`jev_read_file` is exposed only with its shared rule and a separately configured operator/session file policy. Never pass a key in a command,
prompt, environment, argv, transcript or receipt. Keep the main Agent provider/model unchanged.

1. If OpenRouter is already configured in Pi, reuse it; no separate Jev key or Keychain helper is needed.
   Otherwise the human operator can use Pi's `/login openrouter` privately. Account enrollment, key creation,
   billing capacity and source access are the operator's responsibility, not automatic Harness setup.
2. Review the [Decisions contract](https://openrouter.ai/docs/api/api-reference/alphadecisions/submit-a-decisions-request)
   and [Jev endpoint/prices](https://openrouter.ai/api/v1/models/typesafe/jev-1.13/endpoints).
   The qualified public contract is `POST https://openrouter.ai/api/alpha/decisions`, model
   `typesafe/jev-1.13`, provider TypeSafe, expected response build `typesafe/jev-1.13-20260917`.
   A different returned build is rejected, never silently adopted. The fixed synthetic path returned that build
   in the October 14 live check; other question types and live refusal behavior are not qualified here.
3. Use `/jev setup`. Confirm that exact connection, enter explicit positive integer request/session ceilings in
   micro-USD (1 USD = 1,000,000), choose `fixed-synthetic-only` for readiness alone (or explicitly enable
   `authorized-supplied-state` for [trusted consumers](JUDGMENTS.md)), then confirm disclosure and current price.
   No credential is read and no provider request is sent by setup. No monetary ceiling is selected for you.
   Setup is one-time for the pinned connection and approved limits: there is no daily or periodic renewal.
   Existing settings, including older confirmations, remain valid without migration or timestamp refresh.
   Use setup again only to change your choices or approve changed connection/price pins; missing, corrupt or
   removed settings need repair. Setup is not project permission: [TASK-00309](../../planning/tasks/00309-TASK.md)
   separately owns standing project enrollment until revocation or authority changes, not periodic renewal.
4. Use `/jev inspect` for redacted effective settings and configuration-bound readiness. Inspection does not
   resolve authentication. Use `/jev check` only when you authorize one billed synthetic request and Pi's
   credential resolution, whose configured source may itself prompt for access. Cancel the confirmation to send nothing.

The October 14 public endpoint lists $0.042 per million input tokens, free output, 64,000 context tokens and a
32,000 prompt-token maximum. We conservatively reserve the entire 64,000 context: **$0.002688 (2,688 micro-USD)**
per attempt, not an estimated kettle-token count. Both explicit ceilings must accommodate the reservation;
request must not exceed session. Confirm pricing at setup, not before each use. Elapsed time never increases
approved prices or budgets; changed pins or a reported price breach need explicit action. Provider routing preferences request no fallback,
TypeSafe only and the published token-price maxima; they are not proof of server-side billing enforcement.
An unknown or uncertain billed outcome retains the reservation; reported actual cost settles it once, rounded
up to micro-USD. Missing cost is unknown, never zero. There are no automatic retries. A price-bound breach is a
failure that halts further admission in the persisted session ledger: reconcile and requalify pricing, rather than retrying. Do not delete a ledger to regain budget.

Settings and session charges live outside the repository under
`~/Library/Application Support/Fight Agent OS/jev/` (directory `0700`, files `0600`). Settings contain **no key**
or duplicate credential-store reference. Session-ID-bound reservations survive reload/process resume; a stale
lock, corrupted ledger or unresolved charge fails closed and needs human reconciliation, not automatic reset.
Changing settings invalidates readiness and cancels pending work. Shutdown/reload rejects stale completions.
Two concurrent operations, a 15-second deadline, 64 KiB input and response limits, eight questions and sixteen
choices are shared ceilings across readiness and typed consumers. `/jev check` still submits exactly one fixed
yes/no question and no choices or private source. Trusted consumers separately require current disclosure
permission and explicit request/session allocations; see [their contract and qualification limits](JUDGMENTS.md).
A complete finite Yes probability of at least 0.70 passes this synthetic check, not a measured accuracy claim.

### Authentication boundary and failures

The injected adapter calls only `ctx.modelRegistry.getApiKeyForProvider('openrouter')`. Pi chooses its configured
source (runtime override, stored authentication, provider configuration or its own environment/ambient rules);
Jev does not inspect/copy auth files, choose an independent fallback or resolve another provider. A key reusable
by Pi is **not** proof of Decisions access. Regional/proxy-specific keys may be rejected at the fixed official
Decisions endpoint; there is no automatic redirect or endpoint substitution.

Pi 1.2.0 returns undefined both for absent authentication and resolution errors. Jev reports the honest opaque
`credential-unavailable`, not fabricated missing/locked/denied categories. `provider-denied` (HTTP 401/403),
`billing` (402), `provider-rejected` (400/413/422), `timeout`, `provider-unavailable`, `refusal`, `invalid-result`,
`synthetic-negative`, budget and setup failures remain distinct. HTTP 422 alone is not proof of refusal.
The controlled explicit refusal fixture is not a qualified live refusal schema. Raw upstream bodies/errors
are discarded; never use their content to construct operator diagnostics.

Credential resolution has no supported abort parameter. A timed-out resolution cannot dispatch later, and holds
its concurrency slot until it settles; an unresolved Pi source may require human intervention/restart.
The transient buffer is cleared after transfer, but Pi's immutable/cached key string and fetch's header string
cannot be zeroized here. The key exists in the trusted Pi process; same-user tools/extensions can access that
process's resources. Nonsecret settings permissions do not isolate them from same-user Agents. Only macOS local
settings/runtime are directly qualified in this slice; other hosts report unsupported. There is no Keychain-only
isolation claim. Managed infrastructure-owned credentials and TASK-00138 isolation remain separate future work.

### Qualify your installation

The [TASK-00184 qualification record](../../planning/tasks/00184-TASK.md#authorized-live-evidence-recovery--2026-10-14)
reports one real Pi-managed OpenRouter check: Yes probability 0.99, 438 ms, 290 input/23 output tokens and
provider-reported cost $0.00001218. The conservative ledger settled 13 micro-USD, with no pending reservation.
Its $0.002688 request / $0.005376 session ceilings authorized **one request only**, not a repeat. Reload returned
to unchecked without another dispatch; the main Agent remained openai-codex/gpt-6.1-sol/high. Exact-key scans of
actual captured presentation/evidence found no occurrences. Native terminal images, a persisted JSONL transcript
(no model messages were sent), real pending-network cancellation and independent invoice verification were not
obtained; pending cancellation/stale completion use separately labeled controlled tests. Independent Review/QA
remain separate. This receipt neither enrolls another account nor authorizes another billed check.

For your own qualification, explicitly authorize ceilings, credential resolution and one paid fixed synthetic
check. With the trusted package loaded, use `/jev setup`, `/jev inspect`, then `/jev check` and confirm once.
Retain only redacted configuration/evaluator/schema, elapsed time, outcome and available measured usage/cost.
Inspect actual terminal/session/log presentation for disclosure without printing credentials; record absent files
rather than claiming they were scanned. Exercise reload/shutdown and verify an unchanged main model. Do not invoke
Pi credential-print commands or inspect private auth files. Keep controlled injected credentials/transport separate
from live evidence. Preserve uncertain charges and obtain new permission before any additional live request.
