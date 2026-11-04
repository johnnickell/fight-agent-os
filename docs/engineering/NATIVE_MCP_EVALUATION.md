# Native Pi MCP evaluation — TASK-00555

## Decision and boundary

**Retain native Pi MCP as the baseline; do not require or install an adapter.** Native HTTP tools cover the
bounded client mechanics needed for finding/calling authorized workflow tools. No available Fight workflow
requires adapter-only prompts, elicitation, sampling, background MCP Tasks, Apps, semantic search or lazy
connections. This is an evaluation decision, not production enrollment or a claim that Fight MCP is delivered.
Independent Review/QA remain separate.

No integration was replaced. Repository search of `harness/pi/src`, local extension/agent locations, `config`
and `src` found no MCP registration/adapter caller to remove. Existing local skills, Jev tools, passive child
observation and the Build executor remain live, unrelated paths. No global settings, trust, keychain, package
installation/removal, fork, server implementation or rollout was performed.

## Required workflows and available contracts

| Named workflow | Required client behavior | Actual availability / owner |
|---|---|---|
| Find and call authorized Fight workflow tools | Streamable HTTP initialization, catalog, typed arguments/results, bounded failure; current server-side authorization | No Fight MCP endpoint or evaluation credential supplied. No MCP route/registration found in this checkout's application config/client integration census. Future authenticated tool entry belongs to TASK-00242–00245; fixture success does not satisfy it. |
| Delegate selected tools to a subordinate | Explicit child MCP loading and allowlist; fresh policy initialization; bounded connections and shutdown | TASK-00554 adopted `@tintinweb/pi-subagents`. Its current Fight agents select only `agent-os-pi`, **not native MCP**. Explicit opt-in was qualified in a disposable runner fixture, not adopted. |
| Stop/wait/recover a workflow operation | Client cancellation/deadline and truthful uncertain outcome | Native client sends cancellation notifications. Fight must authorize and durably reconcile operations; cancelling a request cannot prove business rollback. |
| Choose the smallest dependency | Native-first evidence and bounded supplier comparison | Native retained. Adapter options do not become prerequisites without a named unmet use case. |

The older [MCP assessment](MCP_CAPABILITY_ASSESSMENT.md) is historical supplier/planning context, not a current
live endpoint or permission contract. No external Common checkout, service, data or credential was borrowed.
Resources/Skills delivery and managed independent-stage execution remain their existing owners' work.

## Qualified identities and method

2026-11-04, macOS host Node **22.23.1**, installed Pi CLI/SDK **1.5.0** and native `pi-mcp` **1.5.0**.
The Pi npm distribution integrity observed was
`sha512-xNDZ5kqowO6Z03FS2gwAkPEGk1ssxVcYKQ6qZId84mFEvwACvvyc2l0vAMOS+c5zvxLRMLBE9r4y44IGYkMJaQ==`;
no Pi source Git revision was supplied by that registry observation. These are tested identities, not equality
gates or guarantees for future Pi releases. Qualify the exported capabilities again after changes.

The disposable supplier installation used adopted subagents **0.22.1**, npm Git revision
`fc089c4b7ab0f1185453b2e53027997c1f637e38`. Current registry subagents **0.23.1**, revision
`9bb6c822bae9f4fe8d33384b715a5e2e96d5df10`, was inspected as source only, not adopted or runtime-qualified.
Adapter **7.0.1**, revision `0beff98460567cd228ae08d99db5f169a1ad3034`, was inspected from its published
tarball/documentation; it was **not loaded or runtime-compared**, because no essential native gap warranted it.

The [probe](fixtures/native-mcp/probe.mjs) creates private HOME/agent/workspace directories, real native SDK
sessions and a loopback JSON streamable-HTTP fixture. It invokes the exported agent-core `runToolCall` with the
actual session hooks and synthetic in-memory issuing transcript entries; codemode uses real nested Pi calls.
The supplier's actual `runAgent` creates the child with a pre-aborted assignment, then the host exercises its
real tool scope. No provider inference occurs. The configured identity remains
`openai-codex/gpt-6.1-sol` / `high`; no alternate model or fabricated model response is used.
This proves runner/client mechanics, **not** a native Agent-tool/manager/Herdr journey, independent workflow
admission, root association, live Fight authorization or visual acceptance. TASK-00560's separate stage-launch
holds are not repaired or bypassed.

## Fresh observations

Final owning-tool run: **26/26 checks passed**, zero skipped, exit 0. Canonical Work evidence:
`.runs/tasks/TASK-00555/runs/work-01/probe-10/{results.json,stdio.jsonl}` and `probe-10.log` in the canonical
checkout. `results.json` includes exact fixture SHA-256s, tested package versions, wire methods (never bearer
values), hook ancestry and detailed policy-denial outcomes. Earlier probe-01–07 failures were fixture defects:
incorrect codemode syntax/missing issuing transcript, cancellation expectations, empty auth-file creation and
wrong builtin selector spelling. They are not native-client regressions. Probe-08/09 were narrower passing
iterations; probe-10 is the final fixture subject.

| Boundary | Observed result | Limit / operational consequence |
|---|---|---|
| HTTP/auth/catalog/invocation | Real initialization, tool discovery and bearer-authorized direct/codemode calls; structured results preserved | Disposable HTTP, not TLS/proxy or Fight authentication. Fixture echoes requested protocol version; no server conformance/version-negotiation certification. |
| Tool policy | Direct and nested codemode denial reach Pi hooks; nested calls carry `parentToolCallId`; hidden tools unreachable | Native codemode is the indirect/proxy path, not the adapter's `mcp` proxy. Hooks protect this path, not arbitrary shell/SDK access. |
| Native allowlists | Explicit `mcp__fixture__*` plus exclusion removes other-server/denied tools from the registry; codemode cannot recover them | Plain built-in `--tools` lists are **not MCP allowlists**. Native docs require an `mcp__` entry or `--exclude-tools`/`--no-mcp`. Tool exposure alone is not authorization. |
| Errors/unavailable | MCP `isError`, JSON-RPC failure, aborted and timed-out calls return errors; failed tool calls not replayed; 401/unavailable servers expose no tools | Other enabled servers continue working. Real remote retry, reconnect, catalog-change and SSE streaming combinations were not exercised. |
| Headless auth | `/mcp login auth` on a no-UI SDK session opens no browser and creates no credential material (an empty auth JSON may exist) | Interactive OAuth enrollment/refresh/rotation was **not** exercised. Headless work needs preprovisioned authorized credentials or an operator sign-in; no automatic browser fallback. |
| Credential isolation | Native OAuth store is 0600; different server names/URLs separate state; identical name/URL shares state | This is a synthetic store-keying probe, not encryption or a fresh OAuth flow. Children in the same agent directory share credential/config scope; they are not separate principals. Process environment and trusted SDK/shell access are not isolated. |
| Child compatibility | `extensions: ["builtin:mcp", "builtin:codemode"]` and `ext:builtin:mcp/mcp__fixture__echo`, `ext:builtin:codemode` admit the selected tool and deny direct/nested out-of-scope calls in 0.22.1 | Use exact builtin selectors on this supplier. `mcp`/`codemode` without `builtin:` did not select these resources. No Fight agent definition was changed. |
| Parent/child policy | Parent-only echo denial does **not** appear in the child; child's explicit scope gate still applies | Reinitialize required Fight policy in each authorized child. A prompt or parent allowlist cannot substitute for child initialization or server authorization. |
| Current Fight selector | A child with the shipped `agent-os-pi` selector shape starts no native MCP server | Selector-only fixture without loading the Fight package. Source inspection confirms all four shipped agents use this shape. This is unavailable MCP, not implicit inheritance. |
| Resources/shutdown | One parent plus one MCP-enabled child starts two stdio fixture processes despite child tool narrowing; both are reaped by awaited `session_shutdown` then disposal; parent remains usable after child closes | HTTP shares one fixture service but uses per-session client connections. Closing clients does not stop the remote service. Native eager connections have no qualified idle-stop capability. |

No application test suite was added for these documents or tooling. Fixtures are direct evaluation tools only.
Full application gate evidence and warnings are recorded in the TASK/Work handoff, not inferred from these checks.

## Adapter comparison and installation/removal effects

Sources: upstream [comparison](https://github.com/nicobailon/pi-mcp-adapter/blob/0beff98460567cd228ae08d99db5f169a1ad3034/docs/pi-builtin-comparison.md),
[configuration](https://github.com/nicobailon/pi-mcp-adapter/blob/0beff98460567cd228ae08d99db5f169a1ad3034/docs/configuration.md),
[extension API](https://github.com/nicobailon/pi-mcp-adapter/blob/0beff98460567cd228ae08d99db5f169a1ad3034/docs/extension-api.md)
and `pi-builtin-mcp.ts` in the published 7.0.1 tarball. Upstream benchmark numbers are not fresh Fight measurements.

- Native already offers deferred discovery/codemode, streamable HTTP, OAuth and permission-hook interception.
  Adapter laziness could reduce duplicated **local** server processes, but adds caching/lifecycle/configuration
  ownership. Prefer one authorized remote HTTP service for future Fight tools; no measured need for many local
  MCP processes exists here.
- Adapter OS-keychain storage, prompts, elicitation, Tasks and Apps are supplier claims, not tested Fight needs.
  Native's plaintext 0600 OAuth store is an explicit cost; neither package supplies Fight principal isolation.
- Loading the adapter's default file-based extension registers `/mcp`, suppressing native MCP **for sessions**.
  On first start per adapter version it may persist `-builtin:mcp` to the **user** settings and an onboarding
  version marker. This is a startup effect, not an npm postinstall script. An explicit config path skips that
  settings write; programmatic `createMcpAdapter({config})` keeps native `/mcp` and uses an isolated supplied config.
- Removing the extension alone may leave the persisted disable entry. A future authorized removal must inspect
  effective settings and deliberately restore native Built-in MCP, preserving unrelated entries. Shell `pi mcp`
  still uses native support even while a session uses the adapter, so a green shell list would not prove the
  session's adapter route. No such installation/removal was performed here.
- Adapter runtime tool-call events and proxy-only modes are not equivalent to native per-tool Pi interception;
  its documented approval-broker boundary needs separate qualification if selected. Use the upstream package/API,
  never copy transports/auth or fork to solve an unevidenced gap.

## Reproduce the bounded fixture

Run from the selected registered checkout. Requires existing supported Pi SDK/agent-core/codemode/MCP exports,
Node and npm; no Docker or provider credentials. This example resolves the current managed Pi installation;
other installation layouts must supply their actual `PI_PACKAGE`. Missing exports block the probe, not justify
installing/replacing Pi or changing the configured model.

```sh
BASE=$(git rev-parse --path-format=absolute --git-common-dir)
BASE=${BASE%/.git}
RUN="$BASE/.runs/tasks/TASK-00555/runs/native-$(date -u +%Y%m%dT%H%M%SZ)"
PI_PACKAGE="$HOME/.pi/agent/install/releases/$(pi --version)/node_modules/@earendil-works/pi-coding-agent"
mkdir -p "$RUN"
npm install --prefix "$RUN/supplier" --ignore-scripts --omit=dev --legacy-peer-deps \
  --no-audit --no-fund @tintinweb/pi-subagents@0.22.1
mkdir -p "$RUN/supplier/node_modules/@earendil-works"
for package in pi-coding-agent pi-agent-core pi-ai pi-tui; do
  ln -s "$(dirname "$PI_PACKAGE")/$package" "$RUN/supplier/node_modules/@earendil-works/$package"
done
ln -s "$(dirname "$(dirname "$PI_PACKAGE")")/typebox" "$RUN/supplier/node_modules/typebox"
env -i PATH="$PATH" node docs/engineering/fixtures/native-mcp/probe.mjs \
  "$PI_PACKAGE" "$RUN/supplier/node_modules/@tintinweb/pi-subagents" "$RUN/probe"
```

Expected: exit 0 and 26 passing rows. A failure stops the probe; inspect the exact row/log, never call an
unexecuted row passed. HTTP fixtures and sessions close in `finally`; successful shutdown also checks both
stdio PIDs are gone. An interrupted/externally killed process requires checking its exact fixture PIDs before
retiring scratch. Retain evidence/private supplier files only while Review/QA consume them; no global package
or trust cleanup is needed. Do not publish the private HOME/agent directories.

## Concrete follow-up, not new implementation authority

1. When Fight's owning server work supplies an authorized HTTP endpoint, principal credential, catalog and
   cancellation/outcome contract, qualify TLS, actual authorization (including direct lookup), errors and
   uncertain outcomes end-to-end. The current missing endpoint is **unverified**, not a pass or native defect.
2. A separately authorized child-profile adoption must explicitly load native MCP and initialize required
   policy/credentials per child, qualify the supported Agent entry and effective tools, and preserve existing
   trusted stage-launch/root-association boundaries. Do not broaden shipped profiles from this evaluation.
3. Revisit upstream adapter only for an evidenced essential gap (for example an approved keychain requirement
   or actual elicitation workflow). Qualify its relevant paths, credential scope and removal effects privately
   before any installation decision. No adapter prerequisite TASK, compatibility wrapper or fork is proposed.
