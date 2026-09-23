# Research MCP Skills and Pi support

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-008](WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](WF-009-define-repository-context-and-policy-precedence.md)

## Question

What does the current official MCP Skills extension require, which maintained servers, SDKs, and clients actually support it, and what Pi and Fight Common integration work is genuinely feasible now?

## Must decide

- Revalidate the stable `io.modelcontextprotocol/skills` specification, base-protocol revision, capability negotiation, methods, resource mapping, limits, caching, digest verification, approval, origin, collision, and code-execution requirements from primary sources.
- Verify current status—not roadmap claims—for official PHP, TypeScript, Go, and Python SDK support, the official client matrix, conformance/Inspector support, and at least one interoperable server.
- Verify Pi's installed and current upstream support for generic MCP and specifically the Skills extension; do not infer Skills support from tools/resources support.
- Identify the smallest Pi extension, SDK resource-loader, RPC, or package seam that could discover and load remote skills while preserving origin and approval semantics.
- Evaluate whether reusable MCP transport/resource primitives belong in Fight Common while repository planning, authorization, lifecycle, and workflow policy remain in Agent OS.
- Record version, commit/date, licensing, protocol compatibility, operational/security implications, and unknowns for every recommended dependency or reference.

## Resolution boundary

Produce a cited note under `planning/wayfinder/research/` and recommendations for WF-014. This ticket may inspect or run disposable interoperability examples under `.runs/`, but it must not install packages globally, add dependencies, implement an MCP server/client, modify Fight Common, or approve a remote skill.

## Primary starting sources

- [Official MCP Skills extension repository and stable specification](https://github.com/modelcontextprotocol/ext-skills)
- [SEP-2640](https://modelcontextprotocol.io/seps/2640-skills-extension)
- [Official extension client matrix](https://modelcontextprotocol.io/extensions/client-matrix)
- Official MCP SDK repositories, conformance suite, and Inspector
- Installed Pi documentation and checked examples under the installed Pi distribution

## Resolution

Current primary-source verification is recorded in [MCP Skills and Pi support research](../research/WF-013-mcp-skills-and-pi-support-research.md), verified 2026-09-23.

### Stable protocol facts

SEP-2640 is Final and `io.modelcontextprotocol/skills` has a released stable specification against MCP `2026-07-28`. A declaring server provides `skills/list`, `skills/get`, the base `resources` capability and `resources/read`; `resources/directory/read` is optional and gated by `directoryRead`. Static entries carry complete per-file `{uri, digest, size}` manifests with fixed 512-resource and 16 MiB support limits. Listings may be partial, `skill://` is conventional rather than authoritative, and identity is the pair of host-assigned originating-server identity and skill URI.

Hosts must retrieve lazily, verify size, digest, and exact frontmatter, preserve origin in every registry, approval, cache, path, and model-context surface, disambiguate collisions, and fail closed on manifest drift. Persisted approval binds to the complete content manifest and is revoked by any content-set change. Digests prove consistency, not trust. Dynamic skills cannot be content-bound. Remote skill text is untrusted prompt-injection input; remote `allowed-tools`, nested activation, cross-origin reads, and host-side code execution do not gain authority implicitly.

### Implementation status

No inspected official Go, TypeScript, Python, or PHP SDK default branch currently contains first-class Skills APIs. All four have open implementation PRs with green checks, while their current protocol extension and custom-method seams make application-owned adapters possible. Official MCP Inspector 2.7.0 partially supports Skills inspection and verification but is not an activating host. The official client matrix reports no full Skills host and does not list Pi. The official conformance default branch has server scenarios, but its released package lags and the scenarios cannot prove host-side approval, cache, origin, or execution behavior.

At least one server implementation is currently credible: the community GitHub Stars server uses the official Python SDK extension seam and has green pinned official server-conformance runs while explicitly declining to claim native host activation. The Hugging Face server's current source still omits stable-spec byte sizes despite its broader tracker label, demonstrating why labels alone are not evidence.

Installed Pi 0.87.1 is the current release. It and current upstream have no generic MCP client or Skills extension. Pi does provide extensions, packages, SDK resource-loader overrides, `resources_discover`, and pluggable read operations. Ordinary Pi filesystem-skill discovery is not sufficient for remote Skills: it eagerly reads `SKILL.md`, keeps the first name collision, and does not carry remote origin, manifest, approval, or acting-window state into model context.

### Architectural recommendation for WF-014

Use a host-owned TypeScript adapter distributed as a Pi package, backed by the official TypeScript MCP 2.0 transport and isolated custom Skills schemas until first-class released APIs can replace them. Keep remote registry summaries origin-tagged and collision-safe, and load through a dedicated approved operation with origin-bound manifest-restricted resource reads rather than presenting remote content as an ordinary filesystem skill. Initially reject dynamic skills, cross-origin reads, silent nested activation, implicit tool grants, and unapproved host execution. Use Inspector and a pinned conformance revision for diagnostics, then add host-side security tests that server conformance cannot supply.

Do not place MCP transport, JSON-RPC, Resources, or Skills protocol DTOs in Fight Common. Those are upstream SDK responsibilities. Keep registered origin, repository applicability, authorization, approval/revocation, cache policy, session linkage, acting-window enforcement, and workflow behavior in Agent OS. Reconsider a Fight Common wrapper only after a second real Fight consumer proves a stable policy-neutral capability.
