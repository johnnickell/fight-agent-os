# Define repository context and policy precedence

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-008](WF-008-establish-repository-identity-and-registration-boundaries.md)

## Question

How does one Pi session resolve a registered repository context exactly once and combine database configuration with local AGENTS.md and repository instructions without silently overriding either source?

## Must decide

- The versioned repository-context contract returned to Pi and shared by every skill, including identity, planning mode, branch/base rules, verification commands, workflow limits, and tracking endpoints.
- Discovery and caching boundaries: when context is resolved, how a session records its revision, and what happens if configuration changes while the session is active.
- Explicit precedence and conflict behavior among application-enforced policy, registered configuration, committed project instructions, parent/user Pi context files, and skill instructions.
- Which conflicts are fatal, which require human choice, and which may compose; include security restrictions and narrower local verification requirements.
- Browser edit and revision rules for repository configuration, including validation, audit history, permissions, rollback, and propagation to later sessions.
- Three supported modes: unregistered/local conventions, registered with Markdown-authoritative planning, and registered with database-authoritative planning.
- Offline/unavailable-service behavior without allowing stale context to expand authority.

## Resolution boundary

This decision owns context composition and policy precedence, not stable repository identity, the planning data model, or Pi/MCP transport implementation. It may define an application query and immutable session snapshot contract but must not add configuration files, extensions, or database tables.

## Preferences required

John must choose how visibly the harness should stop on a conflict and whether a registered repository may deliberately pin a stricter local instruction set. The recommendation should make application-enforced safety rules non-overridable, fail closed on contradictory execution authority, and otherwise preserve stricter local instructions with a visible conflict record.

## Resolution

Write this only when the decision is closed. Record the accepted context contract and any newly sharp child-map questions.
