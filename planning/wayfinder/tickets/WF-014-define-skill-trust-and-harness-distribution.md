# Define skill trust and harness distribution

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-009](WF-009-define-repository-context-and-policy-precedence.md), [WF-013](WF-013-research-mcp-skills-and-pi-support.md)

## Question

How should the customized Pi harness discover, verify, approve, pin, load, record, update, and distribute portable skills and its own shared customization?

## Must decide

- Skill identity as origin plus URI and content/version information; collision handling among project-local, personal, packaged, and MCP-served skills without silent shadowing.
- Inspection and approval flow, per-file verification, allowed-tool treatment, code-execution gating, supporting-resource resolution, and behavior for dynamic or unverifiable skills.
- Caching, immutable session snapshots, updates, re-approval, rollback, server removal, offline behavior, and audit evidence for the exact skill revisions used.
- Boundary between portable skill instructions, separately retrieved repository context, and rules enforced by Agent OS application operations.
- Packaging of shared theme, extensions, commands/prompts, agent roles, and bootstrap behavior with reproducible versioning and project trust.
- Personal installation across repositories: compare versioned Pi packages, pinned Git/npm sources, local package references, and symlinks; copied divergent resources must not be the default.
- Separation of committed shared configuration from credentials, provider authentication, machine paths, and personal preferences.

## Resolution boundary

This decision selects the trust and distribution architecture informed by WF-013. It must not install a package, modify personal/global Pi configuration, connect an MCP server, approve remote content, or implement the harness. Repository business rules remain Agent OS concerns even if transport primitives are reusable.

## Preferences required

John must choose the acceptable approval frequency, offline expectations, and preferred personal-install experience. The recommendation should favor one versioned package/source of shared resources, explicit origin-aware skill approval, immutable per-session revision records, and local project instructions remaining visible rather than silently replaced.

## Resolution

Write this only when the skill trust model and harness distribution choice are approved, with WF-013 evidence linked.
