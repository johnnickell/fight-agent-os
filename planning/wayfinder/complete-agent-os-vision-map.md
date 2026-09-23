# Wayfinder Map: Complete Fight Agent OS vision

**Label:** `wayfinder:map`
**Status:** Active

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map only summarizes the linked resolutions and shows the next decision frontier.

## Destination

Chart a decision-complete route from the current local Markdown planning and project-local Pi skills to a multi-repository Agent OS in which a browser and customized Pi harness share registered repository context, PostgreSQL is the eventual planning and execution authority, reusable skills can be delivered safely through MCP, and an authorized coordinator can carry an approved TASK through implementation, independent review, bounded revision, and PR publication before returning merge control to a human.

The intended journey remains:

```text
Register repository → Wayfinder → grill EPIC → TICKETs → TASKs → next executable TASK
→ coordinator claim → work/review revision loop → land/publish PR → human review and merge
```

**Done** = every linked decision is closed; newly exposed uncertainty is either resolved, excluded, or delegated without overlap to a named follow-on map; repository identity and planning authority are settled before dependent agent behavior; a smallest useful end-to-end proof and sequencing brief are approved; and the map has a clear handoff to one or more future grill sessions that may write EPICs. Done does not mean an EPIC, TICKET, TASK, database migration, or production implementation has been created by this map.

## Notes

### Implemented evidence

- The repository is still a Slim/PHP scaffold with inherited HTTP behavior. It has no React application, application authentication, planning database, coordinator, runner, MCP integration, or sessions dashboard. [README](../../README.md), [Architecture](../../ARCHITECTURE.md), and the [foundation brief](../FOUNDATION.md) remain the authoritative capability inventory.
- Markdown planning, generated views, and validation are implemented and authoritative. The project-local `grill`, `wayfinder`, `research`, `prototype`, `to-tickets`, and `to-tasks` skills were delivered by [EPIC-00001](../epics/00001-EPIC.md).
- Project-local `work`, `review`, and `land` skills have bounded implementations and completed TASK evidence; `next` is a read-only router. They are instructions for human-invoked work, not a durable coordinator. The design and design-review frontier in [EPIC-00002](../epics/00002-EPIC.md) remains unfinished.
- [EPIC-00003](../epics/00003-EPIC.md) and [EPIC-00004](../epics/00004-EPIC.md), their TICKETs, and their TASKs are accepted plans for the web architecture and invite-only shell, not implemented application capability. Their PostgreSQL work concerns the application/access-control foundation and does not by itself define the future planning authority.

### Accepted decisions that this map preserves

- Preserve EPIC → TICKET → TASK terminology; Wayfinder decisions remain distinct WF records. Grill creates an EPIC only, followed by separate TICKET and TASK decomposition.
- Keep business logic in Domain, orchestration in Application, and framework/provider concerns in Adapter. Preserve the accepted Slim, CQRS, Action–Domain–Responder, PostgreSQL, Doctrine, versioned API, React responsibility, and testing directions unless later evidence requires a separately approved change.
- Keep Markdown authoritative until an approved, validated, reversible database cutover. Registration alone must not migrate planning, remove files, override instructions, or authorize work.
- Keep TASK completion, independent review, PR publication, merge, release, and deployment distinct. Humans retain PR review and merge control.
- Treat the previous Factory repository as read-only reference material, never inherited authority. Authenticated `gh` access was confirmed while charting this map.

### Current factual observations, not architecture approval

- The official MCP Skills extension is now `io.modelcontextprotocol/skills`, with a stable specification written against MCP `2026-07-28`. It adds required `skills/list` and `skills/get`, optional `resources/directory/read`, per-file SHA-256 manifests, origin-scoped identities, content-bound approval, and explicit host security requirements. The source of truth is the [official extension repository](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/specification/stable/skills.mdx).
- Official MCP SDK support is not yet a safe assumption: the extension's [implementation list](https://github.com/modelcontextprotocol/ext-skills/blob/0e85d4db8860a305c857f26fdede64f416675b92/docs/implementations.md) records official Go, TypeScript, Python, and C# work as in progress, and the inspected official PHP SDK has no Skills extension surface. This must be revalidated when [WF-013](tickets/WF-013-research-mcp-skills-and-pi-support.md) is worked.
- Installed Pi `0.87.1` supports filesystem Agent Skills, packages, extensions, project trust, persistent sessions, SDK embedding, JSON/RPC subprocess integration, and resource-loader overrides. Its installed documentation and source expose no generic MCP client or Skills-extension support, and Pi is absent from the official MCP extension client matrix. Generic MCP support must not be presumed equivalent to Skills support.
- Pi packages provide a versionable distribution path for themes, extensions, skills, and prompts. Local package paths load in place rather than copying; project resources require trust. This is evidence to evaluate, not a settled packaging choice.
- The previous Factory reference demonstrates useful lessons—stream events while work runs, distinguish workflow sessions from agent sessions, record gates and evidence, preserve event ordering, provide bounded event/payload queries, and keep archive visibility separate from run status. It also shows liabilities this project must not inherit: SQLite as a queryable mirror rather than authority, per-card event polling, phase-heavy overview cards, hard caps, and file-only prompt/session artifacts.

### Focused child maps

Focused child maps are recommended once an umbrella decision exposes multiple independently orderable questions. Do not create them merely to restate the tickets below. Likely scopes are **registered repository and planning authority** after WF-008/WF-009, **portable harness and skill delivery** after WF-013, **coordinated execution and runners** after WF-015, and **planning/session browser experience** after WF-017. A child map may own only newly graduated fog; the existing WF ticket that gates it remains on this umbrella map and is linked rather than duplicated.

## Decisions so far

1. **[Establish repository identity and registration boundaries](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) is closed.** Use one explicit Workspace initially; mint COMB-backed repository, checkout, worktree, and host identities; treat source and Git facts as matching evidence; confirm new checkout links; preserve history through archive/restore; and grant no execution or migration authority through registration.
2. **[Define repository context and policy precedence](tickets/WF-009-define-repository-context-and-policy-precedence.md) is closed.** Resolve one compact, versioned context snapshot per Pi session; preserve native instructions without treating prose as policy; use a small conflict wizard; pin revisions; fail closed on drift or service loss; and make planning-authority upgrades explicit.
3. **[Define the authoritative planning domain and lifecycle](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) is open.** Set the canonical PostgreSQL planning model, lifecycle, concurrency, permissions, and executable-work query.
4. **[Prove the Markdown migration and authority switch](tickets/WF-011-prove-markdown-migration-and-authority-switch.md) is open.** Establish a reversible, identity-preserving migration and explicit authority cutover.
5. **[Define browser planning and conversation ownership](tickets/WF-012-define-browser-planning-and-conversation-ownership.md) is open.** Set the shared application operations and persistence boundary for browser planning conversations and artifacts.
6. **[Research MCP Skills and Pi support](tickets/WF-013-research-mcp-skills-and-pi-support.md) is open.** Revalidate the official extension and actual server, SDK, client, Pi, and Fight Common feasibility before selecting an integration.
7. **[Define skill trust and harness distribution](tickets/WF-014-define-skill-trust-and-harness-distribution.md) is open.** Decide skill identity, approval, loading, caching, revision evidence, conflicts, packaging, bootstrap, and personal installation.
8. **[Define the coordinated TASK authority protocol](tickets/WF-015-define-coordinated-task-authority-protocol.md) is open.** Specify coordinator, worker, reviewer, and publisher responsibilities with commit-specific acceptance and bounded continuation.
9. **[Define runner dispatch and recovery](tickets/WF-016-define-runner-dispatch-and-recovery.md) is open.** Decide durable jobs, runner registration, checkout ownership, leases, cancellation, and interruption recovery.
10. **[Define execution history and event authority](tickets/WF-017-define-execution-history-and-event-authority.md) is open.** Separate authoritative workflow state from observational events and resumable Pi conversations.
11. **[Prototype the sessions-first browser experience](tickets/WF-018-prototype-sessions-first-browser-experience.md) is open.** Use disposable evidence to validate the paginated overview and lazily loaded detail model without implementing production UI.
12. **[Select the end-to-end proof and EPIC handoff](tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md) is open.** Choose the smallest useful proof and a non-competing EPIC planning sequence after prerequisite decisions close.
13. **[Define developer onboarding and operator guidance](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md) is open.** Decide how the Dashboard safely teaches new developers to prepare source access, Pi, the shared harness, checkouts, and runners without silently performing setup mutations.
14. **[Define new project creation and registration](tickets/WF-021-define-new-project-creation-and-registration.md) is open.** Decide how a user safely creates a named project from an approved starter, optionally publishes it to GitHub, establishes its checkout and context, and registers it without hiding partial side effects.
15. **[Define browser instruction inspection and assisted editing](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) is open.** Decide how authorized users and browser AI conversations inspect and propose edits to recognized instruction files through an online checkout without creating a general-purpose IDE or hiding filesystem and Git effects.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) | Establish repository identity and registration boundaries | wayfinder:grill | HITL | Closed | — |
| [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md) | Define repository context and policy precedence | wayfinder:grill | HITL | Closed | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) |
| [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) | Define the authoritative planning domain and lifecycle | wayfinder:grill | HITL | Open | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md) |
| [WF-011](tickets/WF-011-prove-markdown-migration-and-authority-switch.md) | Prove the Markdown migration and authority switch | wayfinder:prototype | AFK + HITL | Open | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) |
| [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md) | Define browser planning and conversation ownership | wayfinder:grill | HITL | Open | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) |
| [WF-013](tickets/WF-013-research-mcp-skills-and-pi-support.md) | Research MCP Skills and Pi support | wayfinder:research | AFK | Open | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md) |
| [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md) | Define skill trust and harness distribution | wayfinder:grill | HITL | Open | [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md), [WF-013](tickets/WF-013-research-mcp-skills-and-pi-support.md) |
| [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md) | Define the coordinated TASK authority protocol | wayfinder:grill | HITL | Open | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md), [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md) |
| [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) | Define runner dispatch and recovery | wayfinder:grill | HITL | Open | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md) |
| [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md) | Define execution history and event authority | wayfinder:grill | HITL | Open | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) |
| [WF-018](tickets/WF-018-prototype-sessions-first-browser-experience.md) | Prototype the sessions-first browser experience | wayfinder:prototype | AFK + HITL | Open | [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md), [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md) |
| [WF-019](tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md) | Select the end-to-end proof and EPIC handoff | wayfinder:task | HITL | Open | [WF-011](tickets/WF-011-prove-markdown-migration-and-authority-switch.md), [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md), [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md), [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md), [WF-018](tickets/WF-018-prototype-sessions-first-browser-experience.md), [WF-020](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md), [WF-021](tickets/WF-021-define-new-project-creation-and-registration.md), [WF-022](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) |
| [WF-020](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md) | Define developer onboarding and operator guidance | wayfinder:grill | HITL | Open | [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) |
| [WF-021](tickets/WF-021-define-new-project-creation-and-registration.md) | Define new project creation and registration | wayfinder:grill | HITL | Open | [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) |
| [WF-022](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) | Define browser instruction inspection and assisted editing | wayfinder:grill | HITL | Open | [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md), [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-008 repository identity
  ├──→ WF-009 context precedence ──┬──→ WF-010 planning domain ──→ WF-011 migration/cutover ──┐
  │                                │                         └──→ WF-012 browser planning ──┐ │
  │                                └──→ WF-013 MCP/Pi research ──→ WF-014 skill/harness ──┤ │
  └───────────────────────────────────────────────────────────────────────────────────────┘ │
                                                                                            │
WF-010 + WF-012 + WF-014 ──→ WF-015 coordination ──→ WF-016 runner dispatch ──→ WF-017 history/events
WF-012 + WF-017 ──→ WF-018 sessions prototype
WF-014 + WF-016 ──→ WF-020 onboarding and operator guidance
WF-009 + WF-016 ──→ WF-021 new project creation and registration
WF-009 + WF-012 + WF-016 ──→ WF-022 browser instruction inspection/editing
WF-011 + WF-014 + WF-015 + WF-016 + WF-017 + WF-018 + WF-020 + WF-021 + WF-022 ──→ WF-019 proof and EPIC handoff
```

## Frontier

[Define the authoritative planning domain and lifecycle (WF-010)](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) is the one recommended next decision. WF-008 and WF-009 now provide stable repository identity, explicit planning-authority modes, immutable session context, and safe configuration revision semantics; the next dependency is deciding the authoritative PostgreSQL planning model and concurrency rules before migration or browser planning can be specified.

## Not yet specified (fog)

- Exact schemas, API routes, migration classes, UI component boundaries, table columns, indexes, event payloads, and retention durations.
- Whether newly exposed complexity warrants the candidate child maps above and which new decisions each would own; WF-020 will decide whether platform-specific onboarding needs its own focused child map, and WF-021 will make the same decision for starter qualification and project provisioning.
- Authentication and authorization details beyond the accepted single-installation shell, including the eventual relationship among installation, workspace, repository, user, agent, and runner permissions.
- Exact coordinator process topology, model/provider assignment, cost budgets, retry values, scheduling fairness, and concurrency limits.
- Whether deliberately supported stacked PRs are worthwhile; no dependent work may assume stacking until WF-015 decides it.
- Real-time browser transport, artifact storage backend, log indexing/search, and long-term execution-history retention.
- The exact reusable MCP component, if any, that belongs in Fight Common; application planning and workflow policy remain Agent OS concerns.
- The project-local `grill` skill still assumes every grill session creates exactly one EPIC; a later standalone bugfix should distinguish EPIC-producing grill from `wayfinder:grill`, where one map may eventually hand off to zero, one, or several EPICs. Do not allocate that TASK while concurrent planning work may be creating TASK records.
- Deployment, remote runner fleets, release management, merge automation, and operation after human merge.

## Out of scope

- Implementing production code, installing packages, changing global or personal configuration, registering a live runner, starting agents, migrating data, or switching planning authority during this map session.
- Writing an EPIC, requirement TICKET, implementation TASK, ADR, or production schema before this map reaches its approved handoff.
- Replacing, reinterpreting, or bulk importing the accepted EPIC-00002 through EPIC-00004 plans.
- Importing Factory workflows, plans, approvals, runtime code, or SQLite authority wholesale.
- Removing or archiving current Markdown planning automatically, even after a future database cutover.
- Agent approval or publication implying defect-free software, human PR approval, merge, release, deployment, or certification.
