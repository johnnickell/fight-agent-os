# Research MCP Skills and Pi support

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Open
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

Write this only after current primary-source verification is saved and linked. Distinguish stable protocol facts, implementation status, experiments, and architectural recommendations.
