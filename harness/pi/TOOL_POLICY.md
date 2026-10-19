# Deterministic Pi tool policy

TASK-00498 removes native `read` interception from this profile. Reads remain visible and use Pi/OS permissions
even when this policy is missing, invalid, held or does not list `read`. The policy below governs writes, edits,
approved scripts and other tool routes. Historical read-denial qualification records describe earlier behavior.

This opt-in profile is **deterministic protection only**. It needs no Jev credential and performs no semantic
screening. It is not a managed Workflow grant, qualified sandbox, filesystem/process isolation or autonomous
launcher. Installing the package without selecting a policy leaves ordinary sessions unchanged.

## Operator setup

Supported runtime: **stable Pi 1.x from 1.2.0 onward, macOS APFS** (case-sensitive and case-insensitive APFS),
with the [required runtime capabilities](#runtime-compatibility). Minor/patch upgrades do not require an allowlist
edit or reenrollment. Earlier versions, prereleases, noncanonical version strings, other major versions/platforms
and unqualified filesystems refuse activation. The policy directory, working directory
and all allowed roots must share one filesystem device; targets crossing a nested mount to another device are
also refused. Keep a private operator directory on that volume, outside the repository and allowed roots. The package
uses Pi's exported tool-definition factories and execution wrappers, not a patched Pi installation. Review and
trust every loaded extension; they run as your OS user. Do not activate this profile in coordinator/delivery
sessions that require unsupported tools, unrestricted shell or child launch.

1. Outside the repository and all allowed roots, create an operator-owned directory (`0700`) and a JSON file
   (`0600`, owned by the launching user, regular file, no symlinks or hardlinks). A suitable location is
   `~/Library/Application Support/Fight Agent OS/tool-policy/`. Keep this directory outside Agent write roots.
   No repository instruction, model output, environment setting or session entry selects a profile.
2. Author the version-1 contract below with real **absolute** paths. Choose a short lowercase/hyphen profile ID,
   positive integer revision, explicit tools/effects/roots and protected resources. List every sensitive resource
   within allowed roots. The example is a shape, not runnable permission or an instruction to activate it.
3. Start a fresh trusted Pi process with an explicit operator flag:

   ```sh
   pi --fight-tool-policy "$HOME/Library/Application Support/Fight Agent OS/tool-policy/policy.json"
   ```

   Load this package first using the [installation instructions](README.md#install), or add
   `-e /absolute/path/to/harness/pi` for one invocation. No global installation or settings edit is automatic.
4. Inspect `/fight-policy`. Require `active`, the intended profile/revision/digest, tool/effect lists and coverage.
   The TUI status also says **deterministic protection only**. Print/JSON/RPC modes put redacted startup/inspection
   status on stderr, not protocol stdout. A missing or invalid selected policy leaves supported wrappers and the
   denial hook fail-closed; it never silently falls back to unrestricted tools. Without a selected flag, inspection
   says `inactive/not-selected` and tools are unchanged.
5. To revise policy, stop current work, edit the machine-local file as the operator, increment `revision`, and
   explicitly `/reload` or restart with the selected file. Detected drift invalidates the current profile and
   cancels pending operations. No operation-level approval changes policy. Restart after changing guard code or
   Pi itself; `/reload` is qualified for policy/session lifecycle, not proof of dependency/module-cache replacement.

```json
{
  "version": 1,
  "profile": "local-source",
  "revision": 1,
  "roots": ["/absolute/working/root"],
  "protectedPaths": ["/absolute/working/root/private-resource"],
  "tools": ["read", "write", "edit"],
  "effects": ["read", "write"],
  "scripts": []
}
```

Every top-level field is required; unknown fields, unsupported tools/effects and invalid bounds fail activation.
At most 16 roots, 64 protected paths, 4 tools, 3 effects and 32 script entries are accepted; the policy file is at
most 64 KiB. Roots must already be directories and cannot be `/`. Symlinked configured roots are pinned to their
initial canonical destination and rechecked. The legacy `read` tool entry is accepted but does not restrict native reads; `write` needs `write`; `edit` needs both `read` and `write`.
No delete, generic command, network, provider or child-launch permission exists in this schema. Effect classes
admit a declared operation; they do not constrain what trusted script code does internally.

## Runtime compatibility

The shared guard accepts stable `>=1.2.0 <2.0.0`; it does not pin each Pi minor/patch release. Both this policy
and enrolled file screening require successful registration of `session_start`, `tool_call`,
`session_before_switch`, `session_before_fork`, `session_before_tree` and `session_shutdown`. Each registration
must return Pi's unsubscribe function. This deterministic policy additionally requires `user_bash`; enrolled
file screening leaves shell native after TASK-00492 and does not register that hook. File screening requires `before_agent_start`, `input`, `getSettings` and `appendEntry`.
Both require callable `on`, `registerTool`, `getAllTools`, `getActiveTools`, `setActiveTools` and `getFlag` APIs.
Missing acknowledgements/capabilities keep activation unavailable with `unsupported-runtime-or-hooks`; presence
of `on` alone or a hard-coded `hooks: true` is not sufficient. Native tool factories remain required imports.

Historical compatibility probes exercised installed **Pi 1.2.0 and 1.3.0, Node 22.23.1, macOS APFS**: 42 deterministic
pipeline outcomes per release, plus 17 enrolled-screening outcomes per release, with no provider calls. They
covered real hooks, final-argument checks, alternate/nested denial, shell interception, lifecycle cancellation,
reload and missing-registration refusal. Disposable enrollment, enforcement files, monthly SQLite accounting,
legacy liabilities and exhausted finite counters remained byte-identical through screening probes and upgrade.
Future 1.x releases are admitted by the compatibility contract, not claimed individually verified. API/registration
checks cannot detect an upstream semantic change that preserves the API shape; they are not sandbox certification.

Restart Pi after updating this guard or Pi itself, then inspect the selected profile. No setup, conversion,
revocation, accounting migration/reset or paid qualification is required merely for a compatible runtime upgrade.
Never delete enrollment or accounting to clear a compatibility hold. Historical filesystem/CLI evidence below
retains its original runtime identity; this compatibility check does not rerun paid or visual qualification.

## Paths and protected resources

The policy directory, selected policy, Harness source directory, Pi installation, Pi agent directory, common SSH/
AWS/GnuPG/macOS Keychain locations, and every approved script are protected from Agent file mutations.
Configured protected paths deny mutations, including new descendants and symlink aliases. Components
named `.git`, `.pi`, `.ssh`, `.aws`, `.gnupg`, `auth.json`, or `.env`/`.env.*` are reserved anywhere in a path.
Other credentials/resources need explicit `protectedPaths`; this is not semantic credential detection. Choose
minimal roots rather than granting an entire home directory. Pi's own credential use is outside file-tool access.

Paths are canonicalized component by component using native filesystem spelling, including existing ancestors
of new write targets. Case and Unicode aliases are compared using the selected volume's own directory lookups,
not universal lowercasing or a guessed Unicode normalization table. At activation the guard creates a private
`0700` `.fight-policy-names-*` directory beside the policy, containing empty lookup directories for policy/reserved
name components. Admission only reads those lookup entries; it never creates a requested target to test equivalence.
The guard removes its lookup directory on cancellation, reload or shutdown. An abrupt process kill can leave an
inert directory there; the operator may remove that stopped session's directory. Missing/replaced lookup resources
fail closed. Existing distinct names on case-sensitive APFS remain distinct, while equivalent Unicode spellings and
nonexistent protected ancestors remain protected. Internal symlinks can reach allowed regular files; out-of-root and protected destinations, dangling symlinks, hardlinked
files and special files are denied. Tilde, leading `@`, percent encodings, backslashes and control characters are
rejected rather than interpreted ambiguously. Native reads/images retain Pi behavior. Edits and existing write targets are limited to 1 MiB; new write content and aggregate edit inputs
also have a 1 MiB ceiling. Edits accept 1–128 replacements.

Execution wrappers clone final arguments **after every `tool_call` hook**, validate types again, and construct
fresh tool definitions with checked filesystem operations. Every access/read/mkdir/write rechecks session,
cancellation, policy/guard content, canonical roots/resources and the final target immediately before its
synchronous filesystem effect. A queued edit/write therefore cannot use an earlier path check. There is no
cached/reusable allow decision. Guard source bytes and policy bytes are digest-bound; inspection and admission
recheck them. Replaced wrapped-tool ownership makes the profile unavailable.

These checks do **not** close OS-level TOCTOU races: another process can change filesystem components between
check/open or check/spawn. Hardlinks and symlinks are deliberately restricted, not an inode-capability sandbox.
Synchronous effect calls avoid JavaScript yields between final validation and access, not same-user races.

## Approved bash scripts

`bash` has no generic safe-executable allow entry and never evaluates command text with a shell. To enable it,
include `bash` in `tools`, `execute` and the script's declared effects in `effects`, and add an approved entry:

```json
{
  "path": "/absolute/operator-bin/check-source",
  "sha256": "<actual lowercase SHA-256 of the reviewed executable script>",
  "cwd": "/absolute/working/root",
  "args": [["check"], ["check", "brief"]],
  "effects": ["read", "execute"]
}
```

Compute the real digest with the platform SHA-256 tool (`shasum -a 256 /absolute/operator-bin/check-source` on
macOS). The executable must be a regular, single-link, at-most-64-KiB shebang script **outside all allowed roots**.
The policy must hold its current digest before activation and before each spawn. Scripts are implicitly protected.
A changed script requires renewed operator review and policy revision/selection, not Agent repair of its hash.

The only accepted command spelling is the canonical absolute script path followed by one exact approved argv
alternative, joined with single ASCII spaces. Path/argument tokens allow only `A–Z a–z 0–9 _ . / -`, at most
256 characters each. At most 16 arguments per alternative and 32 alternatives per script are supported. Empty
argv is `[[]]`. No quoting, escapes, substitution, operators, redirects, pipes, environment assignments, Unicode
lookalikes, `env`, interpreter prefixes, relative executable lookup or arbitrary `sh -c`/`eval` entry is accepted.
The current canonical cwd must exactly match the entry and lie within an allowed root. Timeout is positive and
at most 30 seconds (default 30); combined output is bounded at 64 KiB, marked truncated, and not spooled to a
bypass-readable temporary file. The child receives only fixed PATH `/usr/bin:/bin` and C locale, not provider/
application secrets or session identity from the parent environment.

**Operator review is essential.** Admit only narrowly purposed scripts with independently understood effects
and fixed trusted dependencies; do not approve scripts that evaluate arguments, load mutable repository code,
source writable shell files, launch Pi/children, or access protected resources. Pinning the top-level script does
not pin its interpreter, imported code, system utilities, dynamically read configuration or subprocess effects.
A script can access anything its OS user can access despite its declared effect class. The guard cannot verify
that declaration or enforce root/protected-path constraints inside a process. Qualification exercised only
self-contained disposable scripts using fixed OS utilities; it does not certify an operator's build/test scripts
or any arbitrary interpreter as safe. Use TASK-00138's separately qualified execution boundary when containment
is required; do not broaden this policy to emulate it.

## Route coverage and lifecycle

| Route | Treatment in the selected profile |
|---|---|
| Pi `read` calls, including nested reads | Native Pi behavior; no harness admission or denial |
| Pi `write`, `edit`, `bash` Agent calls | Execution-time wrappers and fresh per-effect admission |
| Later argument-changing `tool_call` hook | Final wrapper checks changed arguments, not the earlier proposal |
| `grep`, `find`, `ls`, `powershell`, custom/MCP tools | Inactive by default and denied by name before execution if enabled/called |
| `codemode`, tool search, `jev_read_file`, helpers, child/sub-agent launch | Agent tool execution denied; trusted registration/startup/hooks are outside this boundary |
| `ctx.executeTool` nested calls | Denied by `parentToolCallId`, except native `read`; mutations and approved scripts remain wrapped |
| TUI `!` and `!!`, built-in RPC `bash` dispatch | `user_bash` handled with a denial result; no local shell fallthrough |
| Trusted extensions, commands, SDK host calls, external editors, startup resources, `@file` prompt attachment | **Outside Agent tool coverage**, not isolated or intercepted by this policy |

Pi 1.2.0's CLI has no built-in Agent-callable child launcher. Custom child/helper tools are denied in their
entirety; children are not launched and inheritance is not claimed. MCP startup connections and trusted extension
code can run before any tool call. Prefer a dedicated launch with only reviewed extensions, `--no-mcp` and
`--no-approve` where project resources are unnecessary; these flags are not themselves a sandbox. Do not expose
SDK `session.executeBash`, `pi.exec`, raw Node APIs or tool implementations through an unreviewed host/helper.
They are trusted-host capabilities, **not covered Agent routes**. A controlled probe directly demonstrated that
SDK `executeBash` itself bypasses `user_bash`; the CLI TUI/RPC dispatch owns that event. The profile cannot make
an arbitrary SDK host or malicious trusted extension safe and must not be advertised for that purpose.

Reload/shutdown abort pending script processes and invalidate old wrapper closures. File operations waiting in
Pi's mutation queue recheck their original session/epoch and cannot start after invalidation. No semantic or
human admission decision is pending in this deterministic slice. Session switch/fork/tree navigation invalidates
the old context; even a cancelled navigation needs explicit reload/reselection before more work. An already
started synchronous write cannot be rolled back; process-group cancellation cannot contain escaped descendants
or undo prior script effects. A direct process is bounded, not a proof that all descendants stopped.

`/fight-policy` and normal block reasons contain bounded profile IDs, revisions/digests, counts and enumerated
reason codes, not source bodies, command strings, paths or credentials. Pi itself still renders/persists original
tool arguments and allowed results; the guard does not redact Pi's whole transcript. Do not put secrets into tool
arguments. Status is refreshed on admission/inspection, not an external liveness monitor. An unloaded/disabled
extension cannot reliably announce its own absence, block tools or detect a later unprotected process. Verify the
launch resources and current inspection before relying on protection; use external enforcement for stronger needs.

## Qualification and verification

Run `./bin/harness-check` for owned policy/effect behavior, then mandatory `./bin/build`; both include the policy
suite. The focused container deliberately uses `noexec` temporary storage: its process tests inject `/bin/sh`
only as a test launcher. Production uses direct script spawn, separately exercised in real Pi. These do not substitute
for real runtime qualification. TASK-00160's retained evidence uses real Pi 1.2.0 SDK tool dispatch, actual hooks,
wrapped filesystem/script effects, a late path-mutating hook, actual built-in codemode/tool-search denial,
controlled helper/MCP-named/child tool and nested denial, script/policy/source drift, actual reload and obsolete
closures. R1 repair qualification additionally exercises case/Unicode aliases, reserved names, guard resources
and nonexistent protected ancestors through real Pi calls on case-insensitive APFS; a disposable case-sensitive
APFS image verifies distinct-name admission alongside Unicode alias denials. Guard copies in disposable fixtures are SHA-256 matched to the submitted implementation, allowing
source-drift probes without modifying the checkout. No live MCP service or child execution is qualified. The real CLI RPC process also denies both context-included
and excluded `bash` commands and keeps startup/inspection diagnostics off protocol stdout. Inputs are controlled; no provider call, user credential
or live operator policy is used. Direct SDK calls to private pipeline entrypoints are qualification drivers,
not a supported external launch API. Separate real CLI PTYs exercise policy inspection, missing-policy refusal,
`!`/`!!` denial and reload in comparable dark/wide and light/narrow sessions. Baseline PTYs use the pinned
presentation-only package. Native terminal images are unavailable: ANSI transcripts demonstrate interaction and
file effects, **not visual clipping/font/layout correctness**. Independent Review and QA remain separate.

Required Jev tool-effect screening (TASK-00161) was cancelled by TASK-00492; no semantic command gate remains. TASK-00309's separate
[enrolled file-screening candidate](FILE_SCREENING_PROFILE.md) reuses these final-argument write/edit operations
with normal project reads, optional relevance discovery, role-scoped writes and native shell execution
without Jev assessment or confirmation. Its project-instruction, skill and `.env.example` access defaults
do not change the standalone deterministic policy described above.
Shell relevance bypass and same-user access are accepted interim limitations, not containment. This does not change
this standalone optional policy's exact-script shell restrictions. Current workflow proof/independent acceptance
remain pending.
Do not combine its enrollment with `--fight-tool-policy`: that conflict holds activation. This standalone
profile continues to deny Jev tools. Neither profile establishes TASK-00138 sandbox qualification.
