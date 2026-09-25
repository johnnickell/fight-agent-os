# MCP Skills and Pi support research

## Question

What does the released `io.modelcontextprotocol/skills` extension require, which maintained servers, SDKs, and clients support it today, and what is the smallest safe integration seam for Pi and Fight Agent OS?

This matters because generic MCP tools or Resources support is not evidence that a host can safely discover, approve, verify, load, and act on a remote Agent Skill. WF-014 needs current facts before selecting skill trust, packaging, cache, and harness behavior.

**Verification date:** 2026-09-23 UTC

## Short answer

`io.modelcontextprotocol/skills` is a released MCP extension. SEP-2640 became Final on 2026-09-13, and the official stable specification at `modelcontextprotocol/ext-skills@0e85d4d` targets base MCP revision `2026-07-28`. It requires `skills/list`, `skills/get`, standard `resources/read`, optional capability-gated `resources/directory/read`, complete per-file SHA-256 and byte-size manifests for static skills, strict origin preservation, lazy reads, content-bound approvals, collision handling, and additional approval around host-side execution.

The protocol is stable but the host ecosystem is not mature:

- The official Go, TypeScript, Python, and PHP SDK default branches do not yet contain first-class Skills APIs. Each has an open implementation PR with green checks. Their current base-protocol and extension/custom-method seams make an adapter technically possible, but an application would own the missing Skills validation and security behavior.
- The official client matrix records only **Partial** support for ChatGPT, fast-agent, and MCP Inspector and records no fully supporting client. Pi is absent.
- Official MCP Inspector 2.7.0 can inspect and verify Skills manifests, but it is not an activating agent host.
- The official conformance repository has server scenarios on its unreleased default branch. Those scenarios exercise wire-visible server behavior, not the host's approval, cache, origin, prompt-injection, or acting-window duties.
- `svg153/github-stars-contrib-mcp-server` supplies a current community server implementation whose pinned Skills conformance runs are green. It explicitly does not claim native host activation. Hugging Face's server is listed as `v1`, but its current `v0.4.23` source still omits stable-spec `size` fields and should not be used as the conformance reference.
- Installed Pi 0.87.1 is also the current released Pi version. Neither that release nor current upstream has a generic MCP client or a Skills-extension implementation. Pi does have viable extension, package, SDK `ResourceLoader`, `resources_discover`, and pluggable read-operation seams.

For WF-014, the smallest safe direction is a host-owned TypeScript adapter distributed as a Pi package, using the official TypeScript MCP 2.0 transport and custom-method/capability seams until first-class Skills support is released. The adapter should expose an explicit, origin-tagged **load remote skill** path and origin-bound supporting-resource reads rather than pretending remote skills are ordinary Pi filesystem skills. It should initially reject `"dynamic"` skills and require Agent OS approval policy before loading or host-side execution. MCP transport and wire codecs should remain in the official SDK rather than Fight Common; repository approval, authorization, lifecycle, and workflow policy belong in Agent OS.

## Findings

### 1. Released protocol contract

SEP-2640 was merged as Final on 2026-09-13. The extension repository identifies `specification/stable/skills.mdx` as the source of truth and pins it to base protocol revision `2026-07-28`. The repository and specification are Apache-2.0. The Agent Skills format it delegates to is also Apache-2.0 at the inspected source revision. [Extension README](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/README.md) · [stable specification](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx) · [SEP-2640 merge](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2640) · [Agent Skills specification](https://github.com/agentskills/agentskills/blob/69ef37e9424c0a7ea9dd2293b559e43ec8176379/docs/specification.mdx)

#### Negotiation and methods

A server declares `io.modelcontextprotocol/skills` under `capabilities.extensions` in `server/discover` and must also declare the base `resources` capability. An empty settings object means required support only; `directoryRead: true` additionally commits the server to `resources/directory/read`. A client must observe the declaration before invoking extension methods and must include base-revision request metadata. [Capability negotiation](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#capability-negotiation)

Required operations are:

- `skills/list`: paginated entries; a listing may be empty or partial and therefore is not proof that no skill exists.
- `skills/get`: obtains one complete entry by its `SKILL.md` URI, including skills absent from a partial listing; unknown skills return JSON-RPC `-32602`.
- `resources/read`: reads `SKILL.md` and supporting files as ordinary MCP resources.
- `resources/directory/read`: optional, paginated, direct-child metadata only, and callable only when `directoryRead` is declared.

Both Skills results use base cache fields. `skills/list` and `skills/get` require `resultType: "complete"`, `ttlMs`, and `cacheScope`; freshness hints are not integrity evidence. There is no archive or bundle retrieval in v1. [Listing](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#listing-skills) · [single-skill lookup](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#getting-a-skill) · [directory reading](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#reading-directories)

#### Entry, identity, and limits

A `Skill` entry contains:

- the resource URI of `SKILL.md`;
- the complete YAML frontmatter rendered verbatim as JSON;
- either a complete array of `{uri, digest, size}` for every file or the literal `"dynamic"`.

Static digests are `sha256:` plus 64 lowercase hexadecimal characters over raw bytes. Sizes cover those same bytes. A conforming host must support at least 512 resources and 16 MiB total per static skill; servers should not exceed those limits. The manifest includes nested skill files when they are supporting files of an enclosing skill. [Skill entries and resources](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#skill-entries) · [limits](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#limits)

`skill://` is conventional, not privileged. A host recognizes a skill from `skills/list`, `skills/get`, or an explicit reference confirmed by `skills/get`, not from the URI scheme. Identity is the pair **(host-assigned originating-server identity, skill URI)**. Names are labels and can collide within or across servers. A host must preserve and expose origin, namespace collisions per origin, and prevent remote skills from silently shadowing local or other remote skills. The self-reported MCP `serverInfo.name` is not a trustworthy origin identifier. [Resource mapping](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#resource-mapping) · [Skill URIs and names](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#skill-uris)

#### Verification, caching, and approval

Static files are fetched lazily: no `SKILL.md` on connection, listing, or approval, and no supporting file before it is needed. Every read must match both manifest size and digest. `SKILL.md` frontmatter must then be parsed and compared field-for-field to the advertised frontmatter. Unlisted files, mismatches, and stale manifests fail closed; refreshing an entry that changes its complete resource set revokes any persisted content-bound approval. Digests prove consistency with the same server's manifest, not authorship or trust. [Integrity and verification](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#integrity-and-verification)

If content is materialized or cached, the path must encode origin and URI. Disk cache content must either be immutable in a host-only-writable location or be rehashed on every read. It must be outside every filesystem-skill discovery path and remain classified as MCP-origin after restart or server disconnect. Removing a server should remove its cache. [Cache requirements](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#security-considerations)

A `"dynamic"` entry has no stable integrity or content-bound approval. A host may refuse it and must never treat an earlier approval as approval of its current bytes. Declining dynamic skills is the simpler safe v1 policy.

#### Security and execution

The extension classifies remote skill text as untrusted prompt-injection input and as higher risk than a remote tool invocation. Content must enter model context tagged with the host-assigned originating server and must not be presented as equivalent to a local filesystem skill. A skill from server A cannot direct a generic reader to server B without explicit per-call approval naming both origins. [Security considerations](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx#security-considerations)

Remote frontmatter grants no host authority. In particular, `allowed-tools` must be ignored unless the user explicitly approves that grant for that skill. Host-side script or arbitrary command execution requires explicit per-skill approval, and that gate applies to execution calls throughout the interval in which the host is acting on the skill. Approving an enclosing skill does not activate a nested skill; nested activation requires fresh consent.

The transport supports individual script files because Agent Skills can contain `scripts/`, but the Skills working group deliberately defines MCP-served skills as instructors, not an automatic code-distribution mechanism. [Decision log](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/docs/decisions.md#2026-02-14-skills-served-over-mcp-use-the-instructor-format)

### 2. SDK status as of 2026-09-23

All four inspected official SDK default branches understand the `2026-07-28` era and/or extension negotiation sufficiently to make a custom adapter possible. None has merged its first-class Skills implementation. Green checks on an open PR are encouraging evidence, not released API support.

| SDK | Current inspected source and released version | Skills status | License observation |
| --- | --- | --- | --- |
| TypeScript | [`6032170`](https://github.com/modelcontextprotocol/typescript-sdk/tree/60321700871029401a2e3bed8fdf4f02c9ec3331), packages 2.0.0; npm 2.0.0 | No Skills symbols on `main`. [PR #2818](https://github.com/modelcontextprotocol/typescript-sdk/pull/2818) is open at `b009106` with green checks. Released 2.0 exposes typed custom requests and extension capability maps, enough for a bounded adapter. | Published packages say MIT; repository license records the MCP MIT-to-Apache transition. |
| Go | [`8075fb3`](https://github.com/modelcontextprotocol/go-sdk/tree/8075fb3cf313a11816340dcbdd7090d04a5a4b71), release 1.8.0 | No Skills API on `main`. [PR #1238](https://github.com/modelcontextprotocol/go-sdk/pull/1238) and filesystem-provider [PR #1240](https://github.com/modelcontextprotocol/go-sdk/pull/1240) remain open; the protocol PR has green checks. | Repository transition license (MIT and Apache-2.0 by contribution provenance). |
| Python | [`6affe5c`](https://github.com/modelcontextprotocol/python-sdk/tree/6affe5c0d3588fd1705713b3703dc68015cfe3eb), release 2.2.0 | No first-class Skills client/API on `main`. [PR #3485](https://github.com/modelcontextprotocol/python-sdk/pull/3485) remains open with green checks. The merged generic `Extension`, `MethodBinding`, `ResourceBinding`, and raw-request seams are sufficient for a server adapter and are used by the interoperable server below. | MIT. |
| PHP | [`16836d4`](https://github.com/modelcontextprotocol/php-sdk/tree/16836d4e9a0f96831789ac6e64d5ec5238d2c833), release 0.8.1 | No Skills surface on `main`. [PR #372](https://github.com/modelcontextprotocol/php-sdk/pull/372) remains open with green checks. Current source has `2026-07-28`, generic extension registration, custom handlers, and low-level client requests, but Fight Agent OS would own Skills DTO/validation behavior if it used those seams now. | Current package declares Apache-2.0; repository license records the MCP transition. |

The official extension implementation list accurately labels these SDK efforts **in progress**, but it is community-maintained and must not substitute for checking default branches and PR states. [Implementation list](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/docs/implementations.md)

### 3. Client, Inspector, conformance, and server reality

#### Official client matrix

The official matrix fetched on 2026-09-23 records Skills support as **Partial** for ChatGPT, fast-agent, and MCP Inspector. Every other listed client has a blank Skills cell. There is no fully supporting client, and Pi is not listed. A blank cell is not proof of every internal capability, but it is no basis for selecting a host. [Official extension client matrix](https://modelcontextprotocol.io/extensions/client-matrix)

#### MCP Inspector 2.7.0

Inspector 2.7.0 is a useful verifier. Its CLI accepts `skills/list`, `skills/get`, and `resources/directory/read`; `--verify` checks entries, reads static resources, verifies size/digest and frontmatter, and reports nonconformance. Its UI and test server cover pagination, collisions, malformed entries, and directory behavior. It is intentionally **Partial** because verification is not model-context activation, persisted approval, acting-window enforcement, or safe host execution. [Inspector 2.7.0](https://github.com/modelcontextprotocol/inspector/tree/2.7.0) · [CLI verification documentation](https://github.com/modelcontextprotocol/inspector/blob/2.7.0/clients/cli/README.md#skill-verification---verify)

Inspector is suitable for development/CI diagnostics. It should not become the production skill loader or an Agent OS subprocess dependency merely to avoid designing host trust behavior.

#### Official conformance suite

The official conformance default branch at [`7169291`](https://github.com/modelcontextprotocol/conformance/tree/7169291ec0b68eb370fddcd9947313ab0d5e4156) adds three server scenarios: enumeration, manifest, and optional directory behavior. The traceability source says they exercise 40 of 89 extracted requirements; the remaining 49 are predominantly host-side obligations such as lazy retrieval, approval binding, cache isolation, origin, and context handling. The latest published conformance release observed was 0.1.16 from 2026-03-27, so Skills users must pin the later source commit rather than assuming the release contains SEP-2640 scenarios. [SEP-2640 traceability](https://github.com/modelcontextprotocol/conformance/blob/7169291ec0b68eb370fddcd9947313ab0d5e4156/src/seps/sep-2640.yaml)

A green server conformance report proves server wire behavior only. It cannot prove a host safely activates or executes a skill.

#### Interoperable server

`svg153/github-stars-contrib-mcp-server` current source at [`10e2400`](https://github.com/svg153/github-stars-contrib-mcp-server/tree/10e240092b498db38f202fd1980f5d0a01301d07) implements static Skills through the official Python SDK extension seams, including byte sizes, SHA-256 manifests, pagination, cache fields, exact URI reads, frontmatter, and no optional directory surface. Its workflow pins official conformance commit `7169291`; the most recent inspected Skills run at source commit `2f2f983` completed successfully on 2026-09-20. The current-head divergence only adds unrelated audit-coverage files. The project is MIT and versioned 0.3.1. [Implementation](https://github.com/svg153/github-stars-contrib-mcp-server/blob/10e240092b498db38f202fd1980f5d0a01301d07/src/github_stars_contrib_mcp/skills/extension.py) · [evidence boundary](https://github.com/svg153/github-stars-contrib-mcp-server/blob/10e240092b498db38f202fd1980f5d0a01301d07/docs/mcp-skills-evidence.md) · [successful workflow](https://github.com/svg153/github-stars-contrib-mcp-server/actions/runs/35479104624)

This is valid evidence for at least one current server and SDK extension seam. Its own evidence correctly leaves native host activation unproven.

Hugging Face `hf-mcp-server` 0.4.23 is operationally interesting—HTTP-only Skills, validated in-memory snapshots, background refresh, and failure retention—but its current manifest type at [`d91868c`](https://github.com/huggingface/hf-mcp-server/blob/d91868c970f146f5be2f0d611ffd78ea4f4c4a73/packages/app/src/server/skills/skill-types.ts) has `{uri,digest}` without required `size`. It also does not enforce the stable per-skill 512/16 MiB floor as written. Therefore the broader implementation list's `v1` label is not sufficient evidence of stable-spec conformance. [HF Skills setup](https://github.com/huggingface/hf-mcp-server/blob/d91868c970f146f5be2f0d611ffd78ea4f4c4a73/README.md#environment-variables)

### 4. Pi status and integration seams

#### Installed and upstream capability

The installed Pi package is `@earendil-works/pi-coding-agent` 0.87.1, MIT, from `earendil-works/pi`. It matches the current released tag [`v0.87.1`](https://github.com/earendil-works/pi/tree/f07218c4d4bbc12bef056a7058c3dd49dfe41abe). Upstream `main` was inspected at [`898ab80`](https://github.com/earendil-works/pi/tree/898ab804050730e9dcefb4443875d5a932aa6a32); the relevant resource-loader, skill, and read-tool files are unchanged from the release.

Neither installed source/docs nor current upstream contains `skills/list`, `skills/get`, `resources/directory/read`, an MCP dependency, or a generic MCP client. A prior generic MCP extension [PR #3774](https://github.com/earendil-works/pi/pull/3774) was auto-closed unmerged and is not product capability. Generic MCP tools/Resources support must therefore not be attributed to Pi, and Skills support cannot be inferred.

Pi does implement local Agent Skills with filesystem paths. It advertises name, description, and an absolute `SKILL.md` location, then instructs the model to use `read` or `bash`. Name collisions keep the first discovered skill. This conflicts with the MCP requirements to disambiguate collisions, preserve origin in model context, and keep a remote cache outside filesystem discovery. [Pi skill loading](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/src/core/skills.ts) · installed Pi 0.87.1 `docs/skills.md` documentation

#### Available seams

Pi nevertheless exposes useful building blocks:

- Extensions can register tools, commands, lifecycle hooks, and `resources_discover`; asynchronous factories and `session_start` can own connections, and `session_shutdown` can close them. [Extension API](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/docs/extensions.md)
- Pi packages can distribute extensions and local skills through npm, Git, or local paths. A package is a distribution mechanism for the adapter; loading remote content into an ordinary package directory would not preserve MCP origin or approval. [Pi packages](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/docs/packages.md)
- The embedding SDK accepts a host-owned `ResourceLoader`, custom tools, and resource overrides. [SDK resources](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/docs/sdk.md#configuring-a-session)
- The read tool has pluggable async `access` and `readFile` operations, making a virtual origin-encoded path technically possible. [Read operations](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/src/core/tools/read.ts)
- RPC exposes Pi control and events but has no MCP registry or remote-resource abstraction. It is a browser/process integration seam, not a Skills transport.

`resources_discover` alone is not a conforming remote-skill seam. It returns filesystem paths and Pi then reads `SKILL.md` immediately to build metadata, violating MCP's no-prefetch rule; normal filesystem discovery also loses the mandatory remote-origin distinction. A custom `ResourceLoader` plus virtual read operations can avoid prefetch, but Pi's current `Skill` and prompt formatter do not expose origin, approval, manifest, or acting-window state. Using this route safely would require additional host-owned behavior rather than a thin configuration change.

#### Smallest safe Pi shape

The smallest credible v1 is an Agent-OS-owned Pi extension/package or SDK adapter with these behaviors:

1. Use official TypeScript MCP 2.0 transport, `server/discover`, extension capability maps, and typed custom `client.request()` calls. Keep all Skills wire schemas behind one adapter so first-class SDK APIs can replace them after PR #2818 is released.
2. Build a registry only from entries. Do not fetch skill files while listing or approving.
3. Advertise origin-tagged, collision-safe remote skill summaries separately from Pi's local filesystem registry. A model loads one through a dedicated host operation such as `load_mcp_skill(origin, uri)`, not by reading an ordinary `.pi/skills` path.
4. On load, re-fetch or validate the held entry according to cache policy, obtain required Agent OS approval, read `SKILL.md` from the same origin, verify size, digest, URI, and exact frontmatter, and inject content with explicit MCP-origin metadata.
5. Expose supporting reads through an origin-bound operation restricted to the held manifest. Use directory reads only when declared, and never let their live result extend the held manifest.
6. Track the conservative acting window and gate any host-side execution requested while remote skill instructions remain active. Ignore `allowed-tools` unless the user separately approves that grant.
7. Reject dynamic skills, cross-origin reads, unapproved nested activation, and silent local/remote name substitution in the first version.
8. Let Agent OS own server registration, credentials, host-assigned origin labels, repository applicability, user/agent authorization, approval records, revocation, audit evidence, and lifecycle. The adapter is transport and harness glue, not the policy authority.

A deeper virtual `ResourceLoader` integration could later make remote and local skill selection feel native. It should wait until the above security state can be represented and tested, or until Pi gains a first-class origin-aware remote-skill abstraction. Shelling out to Inspector or materializing into standard Pi discovery is not the production seam.

### 5. Fight Common boundary

Do not implement MCP JSON-RPC, HTTP/stdio transports, Resources, cache semantics, or Skills DTOs in Fight Common. Those primitives are protocol-owned and are already maintained in official SDKs. Reimplementing them would create version and conformance liability exactly while the extension APIs are changing.

Keep in Fight Agent OS:

- registered MCP server/origin identity and credentials;
- repository/workspace applicability;
- permission checks and delegated actor attribution;
- skill approval, revocation, collision, cache, and retention policy;
- Pi-session/context linkage and acting-window enforcement;
- browser inspection and audit UX;
- planning and execution workflow decisions.

Use the official TypeScript SDK in the Pi adapter and, if Agent OS later serves Skills from PHP, prefer a released official PHP Skills extension after PR #372 merges. Fight Common should receive a reusable capability only after a second real Fight consumer proves a stable, policy-neutral abstraction. That future capability should wrap an official SDK rather than own the wire protocol.

### 6. Recommendations for WF-014

1. Treat MCP server connection, transport authentication, digest success, skill approval, and host-execution approval as separate facts.
2. Define remote identity as the registered host-assigned server identity plus URI; display both and persist both everywhere.
3. Start with static manifests only. Reject `"dynamic"` and over-limit skills with an explicit reason.
4. Bind persisted approval to the ordered/canonicalized complete `{uri,digest}` resource set and relevant explicit tool grants. Any addition, removal, or digest change revokes it.
5. Preserve local Pi skills as a separate origin class. Require visible disambiguation for same-name skills; never use Pi's first-wins behavior for remote collisions.
6. Inspect before load and require explicit approval before any host-side code execution. Nested activation gets a new approval.
7. Use lazy origin-bound resource reads and a cache excluded from Pi discovery. Prefer host-only-writable immutable cache entries; otherwise rehash on every access.
8. Pin the stable extension commit/base revision and the official SDK version. Revalidate open SDK PRs immediately before implementation planning.
9. Use Inspector 2.7.0 for diagnostics and pin conformance `7169291` or a later released version containing SEP-2640. Add host-side tests because server conformance cannot cover approval or execution.
10. Package the Pi adapter for distribution, but do not package approved remote bytes as ordinary filesystem skills.
11. Keep MCP primitives out of Fight Common until repeated use proves a neutral boundary.

## Sources

### Protocol and format

- [MCP Skills extension stable specification at `0e85d4d`](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx)
- [MCP Skills extension README and source-of-truth statement](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/README.md)
- [SEP-2640 merged Final](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2640)
- [MCP Skills design decisions](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/docs/decisions.md)
- [Agent Skills specification at `69ef37e`](https://github.com/agentskills/agentskills/blob/69ef37e9424c0a7ea9dd2293b559e43ec8176379/docs/specification.mdx)
- [Official extension client matrix](https://modelcontextprotocol.io/extensions/client-matrix)

### SDKs and test tooling

- [Official Skills implementation tracker](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/docs/implementations.md)
- [TypeScript SDK `6032170`](https://github.com/modelcontextprotocol/typescript-sdk/tree/60321700871029401a2e3bed8fdf4f02c9ec3331) and [Skills PR #2818](https://github.com/modelcontextprotocol/typescript-sdk/pull/2818)
- [Go SDK `8075fb3`](https://github.com/modelcontextprotocol/go-sdk/tree/8075fb3cf313a11816340dcbdd7090d04a5a4b71) and [Skills PR #1238](https://github.com/modelcontextprotocol/go-sdk/pull/1238)
- [Python SDK `6affe5c`](https://github.com/modelcontextprotocol/python-sdk/tree/6affe5c0d3588fd1705713b3703dc68015cfe3eb) and [Skills PR #3485](https://github.com/modelcontextprotocol/python-sdk/pull/3485)
- [PHP SDK `16836d4`](https://github.com/modelcontextprotocol/php-sdk/tree/16836d4e9a0f96831789ac6e64d5ec5238d2c833) and [Skills PR #372](https://github.com/modelcontextprotocol/php-sdk/pull/372)
- [Conformance SEP-2640 requirements at `7169291`](https://github.com/modelcontextprotocol/conformance/blob/7169291ec0b68eb370fddcd9947313ab0d5e4156/src/seps/sep-2640.yaml)
- [MCP Inspector 2.7.0](https://github.com/modelcontextprotocol/inspector/tree/2.7.0)

### Servers

- [GitHub Stars server implementation at `10e2400`](https://github.com/svg153/github-stars-contrib-mcp-server/blob/10e240092b498db38f202fd1980f5d0a01301d07/src/github_stars_contrib_mcp/skills/extension.py)
- [GitHub Stars server evidence boundary](https://github.com/svg153/github-stars-contrib-mcp-server/blob/10e240092b498db38f202fd1980f5d0a01301d07/docs/mcp-skills-evidence.md)
- [Successful pinned conformance run](https://github.com/svg153/github-stars-contrib-mcp-server/actions/runs/35479104624)
- [Hugging Face server 0.4.23 Skills types](https://github.com/huggingface/hf-mcp-server/blob/d91868c970f146f5be2f0d611ffd78ea4f4c4a73/packages/app/src/server/skills/skill-types.ts)

### Pi

- [Pi 0.87.1](https://github.com/earendil-works/pi/tree/f07218c4d4bbc12bef056a7058c3dd49dfe41abe)
- [Pi skills implementation](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/src/core/skills.ts)
- [Pi resource-loader interface](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/src/core/resource-loader.ts)
- [Pi pluggable read operations](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/src/core/tools/read.ts)
- [Pi extension documentation](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/docs/extensions.md)
- [Pi package documentation](https://github.com/earendil-works/pi/blob/f07218c4d4bbc12bef056a7058c3dd49dfe41abe/packages/coding-agent/docs/packages.md)
- [Unmerged generic MCP extension PR #3774](https://github.com/earendil-works/pi/pull/3774)
- Installed Pi 0.87.1 `docs/` documentation

## Open questions

- Will the official Skills PRs merge without material API or semantics changes, and which released versions will contain them?
- Will official conformance publish host-side scenarios for lazy loading, origin, content-bound approval, cache isolation, and acting-window execution gates?
- Should the first Agent OS proof use a temporary typed adapter over TypeScript 2.0 custom methods or wait for a released first-class TypeScript Skills API?
- How should Agent OS represent the acting window across Pi context compaction, linked sessions, and model switches? A conservative session-long execution gate is safer but may be intrusive.
- Which registered-server authentication and certificate facts constitute the host-assigned origin identity? MCP's self-reported server name cannot.
- Which UI and permission govern inspection, skill approval, tool grants, revocation, and cross-origin reads?
- What retention period and removal behavior should apply to verified cache bytes and approval tombstones?
- How should skill-level license terms and compatibility claims be reviewed before organization-wide approval?
- Does a future Pi release add a native origin-aware remote-skill registry that makes the dedicated load tool unnecessary?
