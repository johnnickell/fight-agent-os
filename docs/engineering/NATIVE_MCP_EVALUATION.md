# Native Pi MCP evaluation — TASK-00555

## Decision and boundary

**Conditionally retain native Pi MCP as the baseline; do not require or install an adapter.** Native covers
bounded protocol/tool mechanics and exposes a supported custom-transport seam. Ordinary native HTTP configuration
has not satisfied Fight's required HMAC/exact-request protected-holder contract; that integration remains unverified
and blocks live Fight use. Retain native only if the existing owners qualify that boundary and credential-free
children through the supported seam described below. No available Fight workflow requires adapter-only prompts,
elicitation, sampling, background MCP Tasks, Apps, semantic search or lazy connections. Those optional features
are not the missing signing requirement. This is evaluation, not production enrollment or Fight MCP delivery.
Independent Review/QA remain separate.

No integration was replaced. Repository search of `harness/pi/src`, local extension/agent locations, `config`
and `src` found no MCP registration/adapter caller to remove. Existing local skills, Jev tools, passive child
observation and the Build executor remain live, unrelated paths. No global settings, trust, keychain, package
installation/removal, fork, server implementation or rollout was performed.

## Required workflows and available contracts

| Named workflow | Required client behavior | Actual availability / owner |
|---|---|---|
| Find and call authorized Fight workflow tools | Streamable HTTP initialization, catalog, typed arguments/results, bounded failure; current server-side authorization | No Fight MCP endpoint or authorized holder supplied. No MCP route/registration found in this checkout's application config/client integration census. Authenticated entry belongs to TASK-00242–00245; fixture success does not satisfy it. |
| Authenticate every Fight request | Common HMAC over canonical method/authority/path/normalized query/signed headers/body digest, fresh nonce and bounded timestamp; no Bearer acceptance on the Agent route | [TASK-00242](../../planning/tasks/00242-TASK.md#validation-and-permissions) owns actual Common signer/verifier interoperability, key representation/derivation and atomic replay protection. Native bearer/OAuth probes do not qualify this contract. |
| Dispatch only an exactly authorized bounded use | Protected holder selects current credential/destination and signs/dispatches only the admitted request; no arbitrary signing oracle, redirect or unsigned fallback | [TASK-00244](../../planning/tasks/00244-TASK.md#validation-and-permissions) owns current-use/holder admission with existing Harness/Runner/host participants. Raw keys, provider credentials and signing capability must never enter Pi child env, files, models, transcripts or responses. Current owner remains `needs-info`, not live-qualified. |
| Discover pages, invoke and continue under current authority | Each later discovery page, invocation and supported continuation uses a fresh signed request/nonce and current bounded authority; old catalog/token is not authority | [TASK-00245](../../planning/tasks/00245-TASK.md#validation-and-permissions) owns the protected first-party transport/registry, trusted TLS/origin/session limits and supported retry/continuation contract. Unsupported interactions stay unavailable. |
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
tarball/documentation; it was **not loaded or runtime-compared**; the required signing boundary is unresolved, and source inspection
identifies seams in both suppliers rather than evidence that installing the adapter solves it.

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
| Errors/unavailable | MCP `isError`, JSON-RPC failure, aborted and timed-out fixture calls return errors without observed tool replay; 401/unavailable servers expose no tools | Other enabled servers continue working. Real remote retry, reconnect, catalog-change and SSE streaming combinations were not exercised. Native session-expiry recovery is a separate source-observed retry path, assessed below. |
| Headless auth | `/mcp login auth` on a no-UI SDK session opens no browser and creates no credential material (an empty auth JSON may exist) | Interactive OAuth enrollment/refresh/rotation was **not** exercised. This concerns OAuth servers, not Fight Agent HMAC. Fight headless use needs an authorized protected-holder dispatch path, never credentials provisioned into a Pi child or browser/Bearer fallback. |
| Credential isolation | Native OAuth store is 0600; different server names/URLs separate state; identical name/URL shares state | This is a synthetic store-keying probe, not encryption or a fresh OAuth flow. Children in the same agent directory share credential/config scope; they are not separate principals. Process environment and trusted SDK/shell access are not isolated. |
| Child compatibility | `extensions: ["builtin:mcp", "builtin:codemode"]` and `ext:builtin:mcp/mcp__fixture__echo`, `ext:builtin:codemode` admit the selected tool and deny direct/nested out-of-scope calls in 0.22.1 | Use exact builtin selectors on this supplier. `mcp`/`codemode` without `builtin:` did not select these resources. No Fight agent definition was changed. |
| Parent/child policy | Parent-only echo denial does **not** appear in the child; child's explicit scope gate still applies | Reinitialize required Fight policy in each authorized child. A prompt or parent allowlist cannot substitute for child initialization or server authorization. |
| Current Fight selector | A child with the shipped `agent-os-pi` selector shape starts no native MCP server | Selector-only fixture without loading the Fight package. Source inspection confirms all four shipped agents use this shape. This is unavailable MCP, not implicit inheritance. |
| Resources/shutdown | One parent plus one MCP-enabled child starts two stdio fixture processes despite child tool narrowing; both are reaped by awaited `session_shutdown` then disposal; parent remains usable after child closes | HTTP shares one fixture service but uses per-session client connections. Closing clients does not stop the remote service. Native eager connections have no qualified idle-stop capability. |

No application test suite was added for these documents or tooling. Fixtures are direct evaluation tools only.
Full application gate evidence and warnings are recorded in the TASK/Work handoff, not inferred from these checks.

## Required signing/holder fit — R1 correction

The required contract above existed before this evaluation. The original bearer fixture and OAuth store checks
missed it; they remain useful client-mechanics evidence, not Fight authentication or child credential exclusion.
The 2026-11-04 correction reproduces that omission with an owning `rg` search against the pre-repair head and
checks the available transport APIs without implementing a signer or server.

| Candidate | Supported / checked | Unverified requirement and operational cost |
|---|---|---|
| Native `mcp.json` / `registerMcpServer` HTTP configuration | Configured headers resolve when `createDefaultTransport` constructs the transport. Environment/`!command` resolution is not an exact-request signing callback. Native `AuthProvider.token()` supplies a Bearer token, not request method/URI/body signing input. | Configuration alone is insufficient evidence for HMAC/fresh nonce. A precomputed header or OAuth token cannot substitute for the protected holder, and supplying a key/signing command to children violates the contract. No config-only Fight enrollment recommended. |
| Native exported `createMcpExtension({ createTransport })` with `StreamableHttpTransport({ fetch })` | The factory returns a native `McpTransport`. Its per-request `McpFetch(input, init)` sees the URL, method, serialized POST body and assembled protocol/session headers. Fresh capture-only probe verifies initialization, notification, two discovery pages, tool invocation, GET and shutdown DELETE pass through it, without OAuth/Bearer or real network. | A supported insertion point, **not** a protected holder. A credential-free client shim could request bounded dispatch from an independently protected broker, but no such integration, authenticated channel, isolation or live signer/verifier compatibility is qualified here. Do not put signing code/keys or unrestricted signing access in a child closure. Owner must also qualify child loading; this explicit SDK factory is not inherited by existing supplier children. |
| Adapter 7.0.1 `pi-mcp-adapter/host-managed` | Published API/source exposes `createHostManagedMcpAdapter`, per-server `createTransport`, approval then `onToolCall`/single-use `dispatch()`, and externally owned lifetime. Its documented SDK HTTP example supplies a custom `fetch`; supplied transports must carry neither OAuth `authProvider` nor replay behavior. | Source-only alternative, not loaded. It still needs the same external holder/admission/credential-exclusion integration and exact-request transport checks. `onToolCall` alone sees logical arguments, not final serialized HTTP bytes, and is not a signing boundary for discovery or other traffic. It uses the supplier MCP SDK `Transport`, not native `McpTransport`. Added dependency, approval broker, frozen-catalog/restart behavior and caller/lifecycle qualification are costs, not proof of an essential native gap. Keychain/OAuth is unrelated to HMAC holder admission. |

Native source confirms `fetch` runs after JSON serialization and header construction, including GET resumptions
and best-effort DELETE (which bypasses `AuthProvider.token()` but still uses custom fetch). Source also shows
connection retries, read retries and one session-expired recovery path that can reinitialize and retry a call.
OAuth auth recovery can retry at the transport layer if supplied. These are not a blanket no-replay guarantee.
A future integration must qualify each permitted network attempt with fresh current-use admission and nonce,
forbid unapproved replay/continuation, and preserve uncertain in-flight outcomes; fresh signatures alone do not
make a business retry safe. No retry/streaming/continuation signing proof follows from this capture probe.

**Owner/prerequisites:** TASK-00242 must settle the actual selected Common source/key-derivation/canonicalization
contract with its real signer/verifier and nonce store. TASK-00244, with its existing protected session/host and
Harness/Runner broker participants, must supply exact-operation admission plus an authenticated credential-free
child-to-holder dispatch boundary that cannot become an arbitrary URL/signing oracle. Its current holds remain
unchanged. TASK-00245 must supply the authorized first-party endpoint, trusted connection/TLS/origin facts
(through its existing TASK-00333/TASK-00322 owners), signed catalog/pages/calls and supported continuation rules.
No unrelated planning owner or dependency was revised by this evaluation.

Operationally, this adds per-network-request broker admission/signing work, protected holder/key/nonce-store
availability, channel/session binding, rotation/revocation handling and bounded fail-closed shutdown/error paths.
Measure that overhead and parent/child connection cost when actual participants exist; no latency estimate is
claimed. The holder must validate the exact destination/method/path/query/headers/body and dispatch without
unexpected redirects or post-signing changes. Fetch-level visibility does not prove final wire canonicalization:
transport-generated headers, URI normalization, body bytes/digest and proxy authority need real interoperability
checks. Denied or unavailable holder means no protected dispatch and no Bearer, unsigned, local-tool or stale
signature fallback. Safe evidence must exclude keys, nonces, signed bodies and raw provider diagnostics.

**Conditional decision:** native-first remains the least additional supplier commitment because its supported
transport seam can be evaluated without an adapter. Live Fight compatibility remains **unverified**, not a
native pass or demonstrated incompatibility. Proceed with native only after the owners qualify the boundary
above. If the supported seam cannot meet an essential requirement, record that exact failure and compare the
host-managed supplier option/upstream support before deciding adoption; neither adapter installation nor a fork
is justified by the current missing implementation/proof.

Evidence: new [transport-seam probe](fixtures/native-mcp/transport-seam.mjs), **7/7** capture-only checks in
canonical `.runs/tasks/TASK-00555/runs/work-02/seam-02/results.json` and `seam-02.log`; `r1-before.txt`
records the original omission. The first seam attempt failed only on a nonexistent fixture cleanup method;
that was removed before the passing run. No socket, credential, signer, nonce or protected-holder implementation
exists in this probe. It uses an explicit native SDK session and synthetic issuing entry, not a child adoption
or provider-driven journey. The original 26-check fixture is unchanged and does not gain signing coverage.

Source qualification: installed Pi/native MCP 1.5.0 declarations and implementations at
`dist/extensions/mcp/{index.d.ts,runtime.d.ts,runtime.js}`, `dist/core/resolve-config-value.js`, and
`pi-mcp/dist/{auth-provider.d.ts,transports/streamable-http.d.ts,transports/streamable-http.js}`;
supplier revision above, [host-managed API](https://github.com/nicobailon/pi-mcp-adapter/blob/0beff98460567cd228ae08d99db5f169a1ad3034/docs/extension-api.md#host-managed-embedding),
`host-managed.ts` and package export map. Canonical `work-02/source-identities.txt` records inspected hashes.
These identities are observations, never Pi version gates.

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

The separate R1 seam probe needs only installed Pi and a new output directory (no supplier install):

```sh
env -i PATH="$PATH" node docs/engineering/fixtures/native-mcp/transport-seam.mjs \
  "$PI_PACKAGE" "$RUN/transport-seam"
```

Expected: exit 0 and **7** passing capture-only seam rows; no network/signing occurs. The successful native
factory/dispatch path does not authorize a child profile. If the real holder/channel is absent or denied, follow
TASK-00244's unavailable/no-fallback boundary, not a credential-bearing config workaround. This probe cannot
qualify that protected denial path because no holder is implemented.

The original fixture expects exit 0 and **26** passing rows. A failure stops the probe; inspect the exact row/log, never call an
unexecuted row passed. HTTP fixtures and sessions close in `finally`; successful shutdown also checks both
stdio PIDs are gone. An interrupted/externally killed process requires checking its exact fixture PIDs before
retiring scratch. Retain evidence/private supplier files only while Review/QA consume them; no global package
or trust cleanup is needed. Do not publish the private HOME/agent directories.

## Concrete follow-up, not new implementation authority

1. TASK-00242/00244/00245 must supply the real signer/verifier contract, current bounded-use protected holder,
   trusted endpoint/channel and supported request/continuation rules above. Then qualify native exact-request
   dispatch, fresh nonce/current authority on each page/call/permitted attempt, denial before wire effects,
   TLS/proxy/redirect behavior, revocation and uncertain outcomes end-to-end. Missing participants are
   **unverified** prerequisites, not passes or proven native defects. Do not implement them under this evaluation.
2. A separately authorized child-profile adoption must load the qualified credential-free transport and initialize
   required policy plus exact holder/session binding per child. Keep keys, provider credentials and signing
   capability inside the protected holder, never child env/files/models/transcripts/responses. Qualify the supported
   Agent entry and effective tools; a selector or parent handler does not establish isolation. Preserve existing
   trusted stage-launch/root-association boundaries. Do not broaden shipped profiles from this evaluation.
3. Revisit upstream adapter only for an evidenced essential gap, including a demonstrated native protected-transport
   limitation or an approved optional workflow. Qualify its relevant paths, credential-free holder boundary and
   removal effects privately before installation. No adapter prerequisite TASK, compatibility wrapper or fork
   is proposed.
