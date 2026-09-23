# Define repository context and policy precedence

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
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

### Resolution ownership and snapshot

One Agent OS application query resolves repository context before the first model request and returns a typed, versioned `RepositoryContextSnapshot`. The Pi session is bound to that snapshot, and every skill uses the same binding rather than discovering registered policy independently. WF-013 and WF-014 own the later Pi/MCP transport and injection mechanism.

Keep the full snapshot in application/harness session state outside the model context. Inject only a deterministic, size-capped prompt projection containing the repository name, planning mode, active branch/base guidance, required verification names, active restrictions, unresolved conflicts, and a short revision token. Repository and snapshot identifiers, provenance, service references, and detailed policy remain available on demand; the model does not need UUIDs, credentials, or service URLs in its prompt. Expose projection-size diagnostics and never duplicate instruction-file contents already loaded by Pi.

The snapshot contract records:

- contract schema, snapshot and configuration revisions, resolution time, and provenance;
- Workspace, repository, source, checkout, worktree, and execution-host bindings;
- planning-authority mode;
- base branch, allowed branch patterns, and current observed Git state;
- ordered named verification commands;
- required instruction-file locations plus discovered source paths and content digests;
- references to workflow limits and tracking capabilities once their owning decisions define them; and
- recognized conflicts, warnings, and availability without secrets or arbitrary plugin data.

Use a lean closed schema with optional versioned sections, not arbitrary JSON or a complete speculative workflow configuration.

### Session lifetime and drift

Resolve context exactly once for a session and retain that snapshot when the session resumes. Configuration changes never rewrite an active session. Live application authorization may always narrow or revoke access, while stale context can never grant or expand authority.

A configuration revision mismatch, effective instruction-file digest change, or meaningful Pi context reload marks the session visibly stale. Conversation and ordinary local Pi activity may continue, but new authority-bearing Agent OS operations stop. The user starts or forks a linked session to resolve current context; harmless resource reloads that leave effective context unchanged may continue. This preserves conversational lineage without mutating historical policy context.

### Planning-authority modes

Support an explicit one-way destination without automatic transitions:

```text
unregistered/local
    → registered/Markdown-authoritative
    → registered/database-authoritative
```

Unregistered/local mode uses discovered local instructions and files and has no Agent OS planning or execution authority. Registered/Markdown-authoritative mode adds registered context while repository Markdown remains the sole planning authority. Registered/database-authoritative mode uses Agent OS application commands and queries; Markdown may be a clearly labeled export or historical source but never a concurrent authority. Registration does not migrate planning, and WF-011 owns the validated, identity-preserving database cutover. Missing or unavailable authority fails visibly rather than silently switching modes.

### Instructions, conflicts, and revisions

Pi continues its native discovery of user, parent, repository, override, and nested instruction files. Treat those files as guidance, not an Agent OS policy language: record paths and digests, do not parse arbitrary prose into enforceable rules, and do not claim perfect conflict detection. Local instructions and skill procedures may guide work more strictly but cannot grant application authority or override structured restrictions.

Keep conflict handling small and explicit. Application safety and live authorization are non-overridable. Compare only recognized structured fields such as planning mode, base/branch rules, and required verification. If a recognized conflict, missing value, or ambiguity remains, interactive session creation pauses for a wizard that shows each source and a recommended choice. Non-interactive creation fails with actionable diagnostics instead of guessing. Compatible prose remains ordinary Pi context.

Wizard choices apply only to that session by default and may preserve or narrow, never widen, authority. An actor with repository-configuration management permission may explicitly save a choice for future sessions; saving does not rewrite an instruction file. Browser configuration editing starts from the current revision, validates and previews the change, and atomically appends an immutable revision with actor, timestamp, and reason. Reject stale expected revisions. Rollback appends a new revision copied from prior content. No multi-person approval is required, and planning-authority transitions are not ordinary configuration edits.

Workspace configuration uses current state plus immutable revisions. That requirement does not choose event sourcing. Evaluate event sourcing per bounded context in WF-010 and WF-017, while preserving the accepted non-event-sourced Access Control boundary.

### Availability and follow-up boundaries

A new registered session requires live context resolution and never inherits authority from a cached snapshot. When Agent OS is unavailable, the user may explicitly continue in unregistered/local mode. An existing session may continue clearly marked offline, but Agent OS planning mutations, workflow commands, claims, and other controlled operations are unavailable. Any cached database-planning projection is stale and read-only. Reconnection requires a new linked session rather than a silent refresh.

Project trust, model instructions, and operating-system access are not application authorization or sandbox boundaries. Agent OS must not claim control over ordinary local Pi tools running with the user's operating-system permissions.

Track newly exposed product journeys separately:

- [WF-021 — Define new project creation and registration](WF-021-define-new-project-creation-and-registration.md) owns starter-based project creation, optional GitHub publication, checkout setup, and registration.
- [WF-022 — Define browser instruction inspection and assisted editing](WF-022-define-browser-instruction-inspection-and-assisted-editing.md) owns authorized on-demand content viewing and revision-checked file proposals without turning the Dashboard into a general IDE.

This resolution creates no EPIC. The umbrella map intentionally defers EPIC selection and sequencing to WF-019. It also creates no schema, endpoint, extension, configuration file, database record, migration, runner operation, or planning-authority switch.
