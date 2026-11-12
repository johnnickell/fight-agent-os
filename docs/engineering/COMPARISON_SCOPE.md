# Comparison scope and host settings

This is the owning contract for comparison project selection, paths and host settings. TASK-00593 supplies
configuration, `bin/env` and the walkthrough below. The future `compare` skill consumes this contract;
there is no comparison parser, runner or skill entrypoint yet. Do not copy these rules into that skill.

## Select scope in the invoking project

Resolve the invoking Git working-tree root, including linked worktrees, from the invocation directory. Do not
use the installed skill's directory or another checkout. Inspect only authorized files and selected locations.
Configuration is data, not permission. If root discovery is unavailable or denied, stop dependent resolution.

1. A root `compare.local.yaml` that exists (including an unreadable file or dangling link) selects local mode.
   It replaces `compare.yaml` **in full**. Never merge them. Invalid or unreadable local input is an explicit
   configuration gap, never a reason to fall back to shared defaults.
2. Otherwise use root `compare.yaml` if present. Its directory is the path-resolution base. A symlink does not
   change that base or grant access to its target; require authorized access before following it.
3. Without explicit selection, select every project in the active file in declaration order. An empty project
   mapping means no scope: ask for project entries. If neither file exists, ask for scope. Never scan siblings.
4. The future skill's exact selection syntax is `/skill:compare --projects project-slim,project-yii`.
   One `--projects` followed by one comma-separated, nonempty list of distinct configured names replaces the
   selected membership for this run, preserving list order. Names use `[a-z][a-z0-9-]*`; no spaces, globbing,
   paths, aliases, repeated options or other options are supported by this contract. Unknown names are gaps,
   not paths to discover. Explicit selection still uses the active file for repository/path definitions and
   does not rescue an invalid local file. Without a file, ask for those definitions rather than invent them.
   This documents future invocation syntax; it is not an installed command today.

### Small YAML schema

Use a UTF-8 YAML block mapping with exactly one root key, `projects`. Its value is an ordered mapping of
unique project names. Each entry has exactly two required, nonempty string fields: `repository` and `path`.
Unknown or duplicate keys at any level invalidate the whole active file, including entries not selected.
`projects: {}` is the only permitted flow form and represents empty scope. Use spaces, not tabs; two spaces
per level. Blank lines and whole-line comments are allowed. Scalars may be plain or single/double quoted
YAML strings; an unquoted `#` begins a comment only after whitespace. YAML quote escaping follows YAML 1.2
(single quotes double an embedded quote; double quotes use YAML escapes). Read decoded strings as data.
Reject directives, multiple documents, tags, anchors, aliases, merge keys, sequences, other flow collections,
block/multiline scalars and non-string field values. Reject control characters in names and decoded values.
Do not use a loader that silently accepts duplicate keys or constructs objects.

`repository` uses exactly `github.com/OWNER/REPOSITORY`, with ASCII letters, digits, `_`, `-` or `.` in each
component; neither component may be `.` or `..`, and no `.git` suffix, URL credentials, port, query or fragment
is allowed. This is expected source evidence, **not** managed Project identity. Preserve case for display;
GitHub owner/repository comparisons are case-insensitive. Other source hosts/types require a future contract
change, not a guessed normalization.

Shared defaults live only in [compare.yaml](../../compare.yaml). A complete local replacement can be:

```yaml
projects:
  storefront:
    repository: github.com/example/storefront
    path: ../storefront
```

This selects only `storefront`; it retains none of the five shared entries. To use explicit selection, define
all names it references in the active file. Do not maintain a second paths file or registry.

## Resolve selected paths

For each selected entry, read `path` as a string, not shell text. Recognize only `$VARIABLE` and `${VARIABLE}`,
where VARIABLE is `[A-Z_][A-Z0-9_]*`. Scan left to right; every `$` must start one of these forms. Reject bare
`$`, unclosed braces, empty/invalid names and shell forms such as `$(...)`, `${NAME:-default}` or `$$`.
Backticks and a leading `~` are unsupported path syntax: report a gap, never execute or expand them.
There is no escaping for a literal dollar. Request each distinct referenced variable through the setting
boundary below; initially only PROJECT_BASE is supported. A syntactically valid but unsupported name fails.

Substitute returned values once as literal text, with no recursive expansion, shell evaluation, globbing,
word splitting or tilde expansion. Spaces stay inside one path. Resolve a relative result against the active
file's directory, not the process cwd or provider. Check authorized canonical location, directory readability
and Git working-tree root: a parent repository containing a non-checkout subdirectory is not that checkout.
Record configured and resolved paths separately, including symlink/canonical-path differences. Missing,
unreadable, non-directory or non-checkout locations are explicit gaps. Do not invent, create or substitute
locations. Settings containing shell-like text remain literal; they cannot cause execution.

## Request one permitted setting

The consumer requests `{name: "PROJECT_BASE", context: invokingCheckoutRoot}` through one currently permitted
capability. This logical request is independent of shell or MCP transport. Initially PROJECT_BASE is the only
supported name. Reject every other name before consulting the environment or `.env`, even if it exists there.
A future permitted MCP transport can return the same result without changing configuration; none is supplied now.

A result is either one nonempty string value or a gap classified as unsupported, missing/empty, malformed,
unreadable, unavailable or denied. Never include unrelated values, raw dotenv lines or environment dumps.
A denied or unavailable capability stops dependent path resolution. Do not read `.env` directly, switch
providers, or fall back to Bash to bypass it. Other independently resolved entries may proceed only with
omissions explicit; do not claim complete scope. This is a native workflow boundary, not managed authorization
or an OS sandbox. Configuration cannot grant the capability.

### Current host transport

Run `./bin/env PROJECT_BASE` from the invoking checkout (or a subdirectory, using that checkout's command path).
The command uses the invoking Git root, **not its own installation path**, and requires only Python 3 and Git
on the host. It does not boot the application, use Composer, contact a service or require Docker. Success is
exit 0, stdout containing only the value and one terminating newline, and empty stderr. Failure has empty
stdout, a fixed safe diagnostic on stderr and exit 2. A missing Python/Git executable is an unavailable
transport; do not use an alternative shell resolver. No options, default-name request or multiple names exist.

Resolution order:

1. A nonempty process PROJECT_BASE wins; do not open `.env` at all. Whitespace-only or control-bearing process
   input is malformed, not permission to fall back. An unset or exactly empty process value permits step 2.
2. Parse only the invoking checkout's root `.env`. Missing or unreadable input fails. A missing or empty
   PROJECT_BASE fails. Do not search parents outside the checkout, read `.env.example`, or modify `.env`.

Values must be nonempty after checking for whitespace-only input and must contain no ASCII control characters
or DEL. No interpolation occurs. Prefer an absolute host path, such as `/srv/projects`, for portable intent;
relative values still resolve through the active configuration's directory. Host paths are not forwarded to
application containers. Never run `source .env`, `eval`, `env` without a supported name, or command substitution
on configuration text. The successful setting value is intended output; keep even supported values out of
public logs if they are private.

### Bounded dotenv grammar

This parser is deliberately smaller than shell or application dotenv. It reads UTF-8 without a BOM, accepts
LF or CRLF and a final line without a newline. Each physical line is blank, a comment whose first non-space/tab
character is `#`, or one assignment. Leading/trailing ASCII spaces and tabs are ignored around the key, `=` and
unquoted value. Keys use `[A-Za-z_][A-Za-z0-9_]*`; key names are case-sensitive. `export`, missing `=`, duplicate
keys (including unrelated ones), invalid UTF-8, control characters and multiline assignments fail the whole file.
A nonempty process override bypasses file validation entirely.

- An unquoted value extends to end of line or a `#` preceded by space/tab. Trim its outer space/tab; internal
  spaces are literal. A `#` without preceding whitespace is literal. Quotes inside an unquoted value are not
  supported. Empty assignments are allowed for unrelated keys but fail when PROJECT_BASE is selected.
- A single- or double-quoted value must close on the same physical line. Both styles are literal: there are
  **no escapes** and no interpolation. The first matching quote closes the value. After it, allow only
  space/tab and an optional `#` comment separated by at least one space/tab. Extra text fails. Empty quoted
  PROJECT_BASE fails. Quotes inside a value require the other quote style; literal matching quotes cannot be
  represented by this bounded format.
- `$VARIABLE`, `${VARIABLE}`, `$(...)`, backticks and backslashes in dotenv values are literal bytes, never
  evaluated. A quoted path may contain spaces or `#`. This dotenv grammar is distinct from YAML quoting.
- Validate the entire file's syntax and duplicate keys before returning its supported value. Diagnostics name
  only the supported setting and problem category, never a rejected argument, line, key or value. Unrelated
  assignments are parsed only for this validation; their values are never returned.

Safe examples (do not overwrite an existing `.env`):

```dotenv
PROJECT_BASE="/srv/team projects" # host checkout parent
```

```sh
PROJECT_BASE='/srv/projects' ./bin/env PROJECT_BASE
```

The process value above takes precedence even if the file is missing or malformed. See
[.env.example](../../.env.example) for the commented opt-in example.

## Observe source and revision without changing it

Follow [ADR 0005](../../planning/adr/0005-repository-identity-and-link-consistency.md): paths, names, Git remotes,
provider IDs and markers are evidence, not Project identity. Do not register/link Projects or write markers.
For each selected, authorized checkout:

1. Read its governing instructions before source inspection. Use authorized native Git reads to record its
   root/common directory, `HEAD` full OID, branch or detached state and working-tree status. For example,
   `git -C "$checkout" rev-parse --show-toplevel --git-common-dir HEAD`,
   `git -C "$checkout" symbolic-ref --quiet --short HEAD` (exit 1 means detached), and
   `git -c core.fsmonitor=false -c core.hooksPath=/dev/null --no-optional-locks -C "$checkout" status --porcelain=v1 --untracked-files=normal --ignore-submodules=all`.
   These command-scoped overrides disable executable filesystem monitoring and hooks without changing config;
   `--no-optional-locks` alone does not do so. Do not recurse into submodule status: the command deliberately
   omits submodule working-copy changes, so record that limit rather than infer those submodules are clean.
   If a submodule is independently selected and authorized, observe it separately with the same command.
   Use these overrides for **every** inspected checkout, including linked worktrees and submodules; local or
   worktree-specific config must not re-enable helpers. If the available Git/native capability cannot suppress
   executable helpers and avoid recursive inspection, stop status observation and record an unavailable gap.
   Do not run checkout code, hooks, network calls, fetch, install or repair commands for this observation.
2. Read authorized local remote/source evidence without publishing credential-bearing URLs. Use native Git
   configuration reads (for example `git -C "$checkout" config --local --get-regexp '^remote\..*\.url$'`)
   only into private authorized inspection, and record a sanitized host/owner/repository observation.
   Treat URL rewriting, unsupported source forms, no usable source or contradictory remotes as ambiguous.
   Do not print raw remote config to a report or invent an equivalence.
3. For this contract, plain credential-free `https://github.com/OWNER/REPO[.git]` and
   `git@github.com:OWNER/REPO[.git]` may support the expected GitHub source. A fork is a different source,
   not an alias. A matching upstream plus a different origin remains a fork/mismatch, not automatic alignment.
   Multiple contradictory candidates, local-only sources and unqualified aliases remain unresolved; ask the
   checkout owner which intended source is correct. An apparent match is only observed consistency.
4. Record clean/dirty state separately from committed revision facts. A dirty checkout can support explicitly
   qualified working-copy observations, never a claim that those bytes are committed at HEAD. Record denied
   or unavailable status/source reads as gaps; do not infer clean state, substitute a nearby repository,
   clone, certify or silently omit the target. Continue only independent observations with limits visible.

## Owning walkthrough

Use sanitized fixtures in task-owned ignored scratch; do not alter a user's `.env` or compared sources.
No product-suite tests should exercise this tooling. There is no resolution consumer to execute yet: walk the
selection/path rules explicitly using native reads and record that limitation.

- Read shared defaults: select exactly five entries; request PROJECT_BASE and join each path once. Walk
  `$PROJECT_BASE/project-slim` as well as `${PROJECT_BASE}/project-slim`; a relative `../storefront` resolves
  against the active file directory even when invocation is in a nested directory.
- Replace locally with only storefront: retain no shared entries. Walk explicit `--projects storefront` and
  unknown/duplicate/empty names. Malformed/duplicate local YAML stays a gap even with valid shared input.
  Empty mapping or absent files asks for scope; neither permits a directory scan.
- Invoke the actual host command on controlled quoted/unquoted/comment/whitespace values, empty/missing,
  malformed/duplicate assignments, process override and unsupported-name inputs. Check stdout/stderr/exit
  independently. Include harmless command-expression strings plus a sentinel that must not be created;
  unrelated synthetic secret values must not appear in output or diagnostics.
- Walk malformed placeholders, unsupported variables, missing/unreadable directories and a denied/unavailable
  setting or evidence capability. Stop the dependent observation, with no alternate resolver or direct file read.
- Inspect an authorized Git fixture's actual source, HEAD and status; walk matching source, fork/mismatch,
  contradictory/missing source and dirty state. Configure a harmless filesystem-monitor hook in the fixture
  and a submodule; verify the exact status command creates no hook sentinel, including when the submodule is
  inspected separately with worktree-specific monitor config. Record omitted submodule state and stop when
  helper suppression is unavailable. Record observations, not managed identity or certification.

Record actual outcomes, limits and unresolved owner actions in the TASK handoff. Independent Review and QA
remain separate. The later skill owns automated consumption and comparison/report behavior, not this slice.
