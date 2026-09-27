# Docker Sandboxes qualification

Candidate evaluation for [TASK-00138](../../planning/tasks/00138-TASK.md), following the
[terminal execution contract](TERMINAL_EXECUTION.md). This document guides the provisioner implementation and
its direct tooling qualification. It does not authorize live Agent execution or declare a supported profile.

## Candidate boundary

Evaluate one local microVM per execution trust boundary, with private Git storage, application containers and
test databases inside it. PHP retains TASK eligibility, grants, claims, attempt fencing, resource ownership and
acceptance. Pi remains the worker. No cloud execution is part of this evaluation.

Docker documents a separate kernel and Docker engine for each sandbox. That can let an Engineer use ordinary
container tooling without reaching the installation's Docker daemon. The VM is the outer boundary: the default
Agent has sudo inside it. Do not claim that in-VM users or containers independently protect credentials, review
evidence or another role from that Agent. Independent review must consume an immutable handoff in a separate
trust boundary. See [Docker's isolation model](https://docs.docker.com/ai/sandboxes/security/isolation/).

## Required local profile

| Concern | Qualification requirement |
|---|---|
| Workspace | Create without a workspace path; transfer a synthetic bundle first, later only the approved immutable Git snapshot. Do not use direct mounts of human checkouts. |
| Git | Private repository and linked worktree inside the VM; exact base and head recorded; controlled export/import outside canonical refs. |
| Host integrations | Shared skills off; SSH-agent forwarding off; no host MCP tools, clipboard reads or host lifecycle commands. Copy approved Harness/skills as versioned inputs. |
| Network | Deny all during filesystem qualification. Later enumerate exact model/dependency destinations and test denied paths, including direct connections, DNS and host services. |
| Credentials | No real model or source-host credentials for qualification. Later verify placeholder substitution and role-scoped authority without copying personal Pi authentication. |
| Resources | Explicit CPU/memory allocation and bounded writable disks; measure workspace, Docker data, temporary storage, logs, image caches and host-side artifacts separately. |
| Ownership | Persist create intent and observed runtime identity; reconcile ambiguous completion before reuse or retry. Names alone do not prove ownership. |
| Recovery | Preserve commits and database state through workload and VM restart; reconcile identities and verify readiness before relaunch. |
| Retirement | Stop owned workloads, preserve evidence, remove only identified probe VMs, and prove their resources are gone without restarting unrelated services. |

Use `sbx create` without a path for a mountless workspace; `sbx run` has different defaults and can mount the
current directory. Disable shared skills explicitly with `--skills=off`. See
[creation options](https://docs.docker.com/reference/cli/sbx/create/). Clone mode still exposes ignored/untracked
host files read-only, so it is not the selected source-transfer boundary.

The documented `sandbox.disk.dockerVolume` setting bounds Docker data, not the workspace. Do not infer a total
storage bound from it. Observe the actual filesystems, capacities and backing files; bound host-side growth as
well. SSH forwarding is enabled by default and must be disabled explicitly. See
[settings](https://docs.docker.com/ai/sandboxes/configuration/settings/).

## Pi integration qualification

The [Pi containerization guide](https://pi.dev/docs/latest/containerization) documents whole-process execution
in Docker Sandboxes with credentials substituted by its host proxy. That is the candidate integration: tools,
extensions and Pi itself stay inside the boundary. Do not authenticate Pi inside the sandbox with a personal
credential file.

Inspect and pin both kit content and the underlying image. The current
[contributed Pi kit](https://github.com/docker/sbx-kits-contrib/tree/main/pi) follows rolling images and its
[credential specification](https://github.com/docker/sbx-kits-contrib/blob/main/pi/spec.yaml) wires Anthropic.
That does not qualify John's selected OpenAI/Codex model or account. Test that provider path separately; never
substitute a provider/model silently. First launch a shell fixture without a model, then validate a pinned Pi
version and the approved Fight Harness package before any model request.

## Acceptance sequence

1. Verify the CLI version, downloaded artifact digest and signing identity. Record the exact template digest.
2. Confirm local account readiness and enumerate existing resources before any creation. A missing sign-in
   stops creation; the operator completes authentication through Docker's official flow.
3. Create two uniquely owned mountless fixtures with explicit limits. Observe runtime identities, mounts,
   filesystems, network policy and absence of host integrations before executing probe code.
4. Write distinct Git and database markers. Attempt sibling/host access. Exhaust only an explicitly bounded
   disposable filesystem, delete the filler, and prove space can be reused while the other fixture still works.
5. Stop and restart workload containers, then stop and restart their VMs. Verify exact Git heads and database
   markers. Do not equate graceful restart with forced-crash recovery or PHP process fencing.
6. Export evidence and retire each owned fixture normally. Preserve failed results and partial-resource identities.
7. Select or reject the measured profile. Keep missing storage, credential, platform or recovery requirements
   explicit; do not mark the parent TASK complete on documentation or a shell-only success.

## Local evidence

Evaluation started on 2026-09-26. Docker Desktop's old `docker sandbox` command reports removal and points to the
standalone `sbx` product. Docker Sandboxes 0.45.1 was downloaded under ignored task scratch and matched release
SHA-256 `0a7207d1736ef9d109b722cf3d21e2b9ed5840d7667fcbcb7596075265b22023`. Host-native signature verification
passed for Docker Inc, Team ID `9BNSXJN65R`; the initial restricted-shell signature result did not reproduce
outside that restriction. The CLI reports revision `9d79d90ee4c5d297fb3d36b75384e8cea7a4fbcb`.

The official shell template resolved to index digest
`sha256:1560168ac5fb9ce23d413c878349334c5845c07e264cd675d7867f0c78ad1761`, with ARM64 manifest
`sha256:b2c007bbb5dcdb60a29f3019c7160ebe0c50c968e08854fbba6093834f8f6a1b`. These identify inputs, not a passing
runtime qualification. Local commands and observations belong in `.runs/task-00138/sbx-study/`.

### Two-VM storage and persistence result

The synthetic macOS/ARM64 probe passed its storage and persistence assertions on 2026-09-27 UTC. Both VMs used
one CPU, 1 GiB RAM, shared skills off, SSH forwarding off, no host workspace path and deny-all network policy.
The database image was transferred offline; no model request or personal Pi credentials were used.

| Observation | Result and limit |
|---|---|
| Independent execution | Different VM identities, kernel boot identities and private Docker engine identities; each engine saw only its own database container. The two fill commands overlapped. |
| Source and Git | Each VM owned a bare repository and linked worktree seeded from a synthetic bundle. Distinct commits and source markers survived every completed check; human Git administration was absent. |
| Test databases | Each private engine owned its own PostgreSQL container and volume, with distinct markers. Both containers remained running during the successful fill checks, and markers survived container and VM restart. |
| Workspace capacity | Each writable root/workspace backing image was 20 GiB logical size; the guest filesystem reported 20,957,446,144 bytes. This is an observed template limit, not a qualified configurable per-TASK setting. |
| Docker capacity | Creation requested a separate 2 GiB disk; each backing image was 2,147,483,648 bytes and its guest filesystem reported 2,040,373,248 bytes. |
| Exhaustion and reuse | Both workspace filesystems and both Docker disks returned ENOSPC. Deleting the filler restored available guest space and a new, fsynced 1 MiB write succeeded in each. |
| Restart | Explicit container restart and VM stop/start preserved exact Git heads and database markers. New kernel boot identities confirmed VM restart. This does not qualify arbitrary crash recovery or PHP fencing. |
| Sampled exposure | Host home and SSH-agent socket were absent. Proxy HTTPS returned 403; direct external HTTPS and the attempted host application endpoint failed. Broader DNS, sibling and credential bypass probes remain required. |
| Retirement | After matching names to persisted VM IDs, ordinary stop/removal removed both fixtures, their runtime records and all four backing disk files. The sandbox inventory was empty and host free space increased by approximately 43 GiB. No unrelated Docker Desktop services were restarted. |

Use `qualification.json` for observations and assertion results, `commands.jsonl` for command exits/timestamps,
and `cleanup.json` for retirement evidence. Retain earlier failed reports beside them. These are local qualification
artifacts, not product tests or formal review receipts.

After verifying an empty inventory, the experiment's daemon was stopped. The CLI remains in ignored scratch;
Docker login, cached template content and the explicit SSH-off/default-deny settings remain available for the
next evaluation. This was not a global Pi installation or permanent Runner deployment.

Two readiness assumptions failed during probe development. File transfer preserved host ownership, leaving the
database archive unreadable to UID 1000; the provisioner must normalize and verify transferred ownership before
launch. A later probe tried to query a stopped database container after starting its VM. VM availability does not
establish application readiness: reconcile service state, start permitted services and wait for readiness before
resuming work. The successful repeat explicitly established database readiness before storage stress. Earlier
container exit observations alone do not establish disk exhaustion as their cause.

Guest deletion reclaimed guest capacity, but host sparse backing files remained allocated until VM retirement.
Budget concurrency against aggregate host allocation, including retained paused VMs, rather than guest free
space alone. Template cache, logs and exported artifacts also need enforceable retention/accounting.

### Pi transport and credential substitution result

On 2026-09-27 UTC, a separate mountless fixture ran pinned `@earendil-works/pi-coding-agent` and `pi-ai`
0.87.1 with the same ARM64 template digest, one CPU and 1 GiB RAM. The selected personal configuration was
inspected only for provider/model/credential-type metadata: `openai-codex`, `gpt-6-sol`, high reasoning, OAuth.
No personal authentication file or credential value was copied, refreshed or changed.

Both the SDK and full Pi CLI streamed `PROBE_OK` from a local synthetic provider through Docker's proxy.
The CLI disabled tools, extensions, skills, prompt templates, themes, context files and session persistence.
This was a transport fixture, not an autonomous Agent or an OpenAI response. No upstream model call occurred.

| Check | Observation and limit |
|---|---|
| Client identity | CLI reported 0.87.1. SDK, CLI and `undici` 8.10.2 were installed at exact versions with lifecycle scripts disabled; the resolved integrity lockfile is retained locally. A production image must bake and qualify these inputs. |
| Placeholder format | The native Codex provider rejects a plain placeholder before making a request because it extracts an account claim from the access token. A public, unsigned JWT-shaped sentinel with a synthetic account claim passed this client-side parsing. It is not an authentication credential. |
| Native credential resolution | An `api_key` entry did not satisfy this provider's OAuth-only resolver. The passing CLI fixture used an OAuth-shaped record containing synthetic access/refresh strings and a one-hour fixture expiry. Real refresh and long-running placeholder renewal remain unqualified. |
| Host substitution | A custom secret scoped to this VM replaced the public sentinel with a random synthetic canary outside the VM. The local provider observed the canary, never the sentinel, on both successful requests. |
| Request fidelity | Both requests used `gpt-6-sol`, high reasoning, zero tools and `store: false`. Explicit SSE worked with Pi's zstd request encoding. WebSocket/default automatic transport was not qualified. |
| Revocation | Removing the scoped custom secret stopped substitution. The next SDK request reached the fixture with the public sentinel, received 401 and exited 1. This proves loss of fixture credential authority, not a proxy-level request block or cancellation of an in-flight request. |
| Network denials | The attempted ChatGPT model endpoint, npm registry after installation, and host application endpoint each returned 403. These are sampled proxy denials, not a complete egress/escape proof. |
| Retirement | Scoped secret inventory was empty; persisted VM identity matched before ordinary removal. Sandbox inventory was empty afterward, the local fixture server closed, the synthetic canary file was removed and the idle daemon was stopped. |

The host fixture required both `host.docker.internal:<port>` and its proxy-translated `localhost:<port>` in the
network allowlist, plus both host aliases in the custom-secret matcher. Missing the translated network rule
produced a denial; missing the translated secret matcher forwarded the sentinel and produced 401. These are
fixture exceptions, not production permission to contact arbitrary host services. The production profile must
permit only its authenticated broker endpoint and explicitly qualified dependency destinations.

Raw evidence lives in `.runs/task-00138/provider-study/`: `result.json`, `provider-result.json`, `commands.jsonl`,
`package-lock.json` and `cleanup.json`. Earlier missing-package, compression, host-alias and credential-type
failures remain alongside the passing result. Response text and usage counts were generated by the fixture.

Docker's [credential documentation](https://docs.docker.com/ai/sandboxes/configuration/credentials/) describes
proxy substitution; its [Codex guide](https://docs.docker.com/ai/sandboxes/agents/codex/) describes host OAuth.
The inspected 0.45.1 CLI offers OpenAI OAuth registration globally, not as a demonstrated Workflow-scoped OAuth
grant. Do not infer role/model enforcement from secret substitution, use OAuth passthrough, or let removal of a
scoped credential silently restore broader global authority. Account-header mapping, authenticated upstream
transport, token refresh, entitlement and real-provider revocation still need separate qualification.

### Decision and outstanding requirements

Prefer Docker Sandboxes for the next backend qualification step. The measured local storage behavior addresses
the APFS file-sharing experiment's exhaustion/reclamation failure. It does not complete TASK-00138 or authorize
live Agent execution. In particular:

- Resolve the profile change explicitly: the existing TICKET describes restricted containers without a Docker
  socket; this candidate gives the worker sudo and its private in-VM engine. Never expose the installation engine.
  Put separate role credentials, review and authoritative evidence outside an Engineer-controlled VM.
- Complete selected OpenAI/Codex qualification through the broker: the pinned Pi synthetic SSE/substitution check
  passed, but real OAuth, refresh, account mapping and role/model/attempt enforcement remain unqualified. Qualify
  the Fight Harness and broader dependency/denied network paths. The contributed Anthropic kit is not evidence
  for the selected provider.
- Qualify controlled Git export/import, hostile input limits, sibling access, host storage accounting and the
  effective workspace limit across supported template versions. Do not treat independent markers as an escape test.
- Settle operator enrollment and implement durable ownership, readiness, attempt fencing and interrupted-effect
  reconciliation in PHP. Qualify other platforms separately; unsupported profiles remain unavailable.

Lima remains a fallback candidate. Its release archive was downloaded during preparation; no Lima VM was created.
Vagrant would add a provider-managed VM lifecycle but would still require the same policy and recovery evidence.
