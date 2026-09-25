# Wayfinder Map: Complete Fight Agent OS vision

**Label:** `wayfinder:map`
**Status:** Closed

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
- [WF-013 primary-source research](research/WF-013-mcp-skills-and-pi-support-research.md) verified on 2026-09-23 that official Go, TypeScript, Python, and PHP SDK default branches still lack first-class Skills APIs; all have open green implementation PRs. MCP Inspector 2.7.0 and the official conformance default branch provide useful partial verification, but the official client matrix lists no fully supporting Skills host.
- Installed and current released Pi `0.87.1` supports filesystem Agent Skills, packages, extensions, project trust, persistent sessions, SDK embedding, JSON/RPC subprocess integration, resource-loader overrides, `resources_discover`, and pluggable read operations. It and inspected upstream source expose no generic MCP client or Skills-extension support, and Pi is absent from the official MCP extension client matrix. A safe adapter must preserve remote origin, lazy reads, content-bound approval, collisions, and execution gates rather than materialize remote content as ordinary Pi filesystem skills.
- Pi packages provide a versionable distribution path for themes, extensions, skills, and prompts. Local package paths load in place rather than copying; project resources require trust. This is evidence to evaluate, not a settled packaging choice.
- The previous Factory reference demonstrates useful lessons—stream events while work runs, distinguish workflow sessions from agent sessions, record gates and evidence, preserve event ordering, provide bounded event/payload queries, and keep archive visibility separate from run status. It also shows liabilities this project must not inherit: SQLite as a queryable mirror rather than authority, per-card event polling, phase-heavy overview cards, hard caps, and file-only prompt/session artifacts.

### Focused child maps

No focused child map is required at this handoff. The accepted repository/Planning, Harness, coordinated-execution, browser, onboarding, project-creation, and instruction-editing destinations are coherent enough for dependency-ordered EPIC grills. Open a later map only if implementation exposes genuinely new product uncertainty; do not create one to restate these tickets or select implementation details.

## Decisions so far

1. **[Establish repository identity and registration boundaries](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) is closed.** Use one explicit Workspace initially; mint COMB-backed repository, checkout, worktree, and host identities; treat source and Git facts as matching evidence; confirm new checkout links; preserve history through archive/restore; and grant no execution or migration authority through registration.
2. **[Define repository context and policy precedence](tickets/WF-009-define-repository-context-and-policy-precedence.md) is closed.** Resolve one compact, versioned context snapshot per Pi session; preserve native instructions without treating prose as policy; use a small conflict wizard; pin revisions; fail closed on drift or service loss; and make planning-authority upgrades explicit.
3. **[Define the authoritative planning domain and lifecycle](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) is closed.** Event-source typed Planning aggregates with COMB identities and readable references; enforce cross-aggregate invariants transactionally; preserve explicit lifecycles, closeout, archive, acceptance, and actor attribution; and expose one explainable workspace-wide executable-TASK query.
4. **[Prove the Markdown migration and authority switch](tickets/WF-011-prove-markdown-migration-and-authority-switch.md) is closed.** Import complete self-contained baseline events after one clean rehearsal and human-confirmed transactional switch; permit no dual writers; render Markdown on demand; and retire frozen files only through a later human-merged cutover PR.
5. **[Define browser planning and conversation ownership](tickets/WF-012-define-browser-planning-and-conversation-ownership.md) is closed.** Provide one seamless repository-scoped journey from durable intake through map, EPIC, TICKET, and TASK planning; keep conversations resumable and proposals revision-checked; and project canonical Roadmap, queue, execution, review, and delivery facts without conflating their authority.
6. **[Research MCP Skills and Pi support](tickets/WF-013-research-mcp-skills-and-pi-support.md) is closed.** Treat the stable `2026-07-28` Skills protocol as usable but its host ecosystem as immature; prefer an origin-aware host-owned TypeScript/Pi-package adapter, reject unsafe v1 cases, keep trust policy in Agent OS, and leave MCP wire primitives to official SDKs rather than Fight Common.
7. **[Define skill trust and harness distribution](tickets/WF-014-define-skill-trust-and-harness-distribution.md) is closed.** Establish Harness as the owner of versioned Skills, Themes, prompts, Agent personas and profile templates, trusted first-party MCP origins, package releases, user profiles, and session snapshots; distribute one npm package from `harness/`; keep MCP resources database-authoritative, static, verified, origin-bound, revisioned, and permission-filtered; and provision distinct direct-Permission Agents through secure client-held HMAC keys.
8. **[Define the coordinated TASK authority protocol](tickets/WF-015-define-coordinated-task-authority-protocol.md) is closed.** Use distinct managed Explorer, Planner, Coordinator, Builder, Reviewer, and Publisher profiles; run one server-authoritative TASK workflow per explicit grant; preserve durable claims and commit-specific independent acceptance; allow three bounded revision cycles by default; publish one unstacked PR; permit proportionate mechanical repairs without ceremonial re-review; and return merge control to the human.
9. **[Define runner dispatch and recovery](tickets/WF-016-define-runner-dispatch-and-recovery.md) is closed.** Run one registered container-native PHP Agent runner with three one-shot consumers by default; dispatch one durable PostgreSQL-backed Command per explicitly granted TASK workflow; preserve leases, checkpoints, safe restart recovery, and distinct pause/cancel/disable/halt controls; sandbox each Pi peer to its isolated worktree; broker credentials and external tools through Agent OS; and grant no Docker socket.
10. **[Define execution history and event authority](tickets/WF-017-define-execution-history-and-event-authority.md) is closed.** Use one event-sourced Workflow per TASK grant with application-Command authority, separate Reviews and protected role-specific Pi conversation lineages, lightweight non-authoritative observations, immutable Git/code-review and S3-compatible Artifact evidence, truthful usage and duration facts, rebuildable browser projections, and tiered privacy-aware retention.
11. **[Prototype the sessions-first browser experience](tickets/WF-018-prototype-sessions-first-browser-experience.md) is closed.** Use a compact cursor-paginated Workflow table without embedded event history; switch the whole result set to an independent archive through one toggle; preserve running visibility across archive; load Summary, Changes, Activity, and Evidence independently; and place Sessions in a collapsible-sidebar/thin-bar/footer shell whose final themes and visual design remain separate.
12. **[Select the end-to-end proof and EPIC handoff](tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md) is closed.** Build and use a production Coordinator → Builder slice that stops at Awaiting review after database Planning and the existing application foundations; add an honest dynamic completion range; then sequence registered Planning, provider-configurable browser Agents/Harness, complete coordinated delivery, project creation, and instruction editing as non-overlapping EPIC handoffs.
13. **[Define developer onboarding and operator guidance](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md) is closed.** Render one versioned task-based guide in the Dashboard; combine read-only preflight and post-bootstrap capability checks with explicit human-run mutations; support Linux and macOS honestly; preserve source, provider, keyring, container-secret, runner-bootstrap, and repository-registration boundaries; and validate documentation and setup commands directly without adding product tests for them.
14. **[Define new project creation and registration](tickets/WF-021-define-new-project-creation-and-registration.md) is closed.** Generate a fresh local Git repository from the newest immutable stable release of a managed or Workspace custom starter; allow admin-selected development profiles; preview and confirm deterministic preparation, optional private-by-default GitHub publication, and database-authoritative registration; preserve resumable partial effects; and limit playbook-guided browser Agents to advisory recovery using server-side OpenRouter support.
15. **[Define browser instruction inspection and assisted editing](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) is closed.** Edit only Pi-native root context files through one designated checkout; keep Runner reads and expected-digest writes deterministic; let a browser Agent use a versioned writing Skill only to fill a human-submitted form; separate PR publication and safe default-branch synchronization; and provide no general repository editor.
16. **[Define local runtime and shared ingress topology](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) is closed.** Use an installation-owned Compose stack with `nginx-proxy` shared ingress, explicit private shared-resource networks, canonical HTTPS with `.localhost` or operator-approved custom names, a complete bounded application/runner/Mailpit runtime, private SeaweedFS Artifact storage, container-native PHP commands, deliberate upgrades and host mutations, and no speculative observability or preview services.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) | Establish repository identity and registration boundaries | wayfinder:grill | HITL | Closed | — |
| [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md) | Define repository context and policy precedence | wayfinder:grill | HITL | Closed | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md) |
| [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) | Define the authoritative planning domain and lifecycle | wayfinder:grill | HITL | Closed | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md) |
| [WF-011](tickets/WF-011-prove-markdown-migration-and-authority-switch.md) | Prove the Markdown migration and authority switch | wayfinder:prototype | AFK + HITL | Closed | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) |
| [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md) | Define browser planning and conversation ownership | wayfinder:grill | HITL | Closed | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md) |
| [WF-013](tickets/WF-013-research-mcp-skills-and-pi-support.md) | Research MCP Skills and Pi support | wayfinder:research | AFK | Closed | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md) |
| [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md) | Define skill trust and harness distribution | wayfinder:grill | HITL | Closed | [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md), [WF-013](tickets/WF-013-research-mcp-skills-and-pi-support.md) |
| [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md) | Define the coordinated TASK authority protocol | wayfinder:grill | HITL | Closed | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md), [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md) |
| [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) | Define runner dispatch and recovery | wayfinder:grill | HITL | Closed | [WF-008](tickets/WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md) |
| [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md) | Define execution history and event authority | wayfinder:grill | HITL | Closed | [WF-010](tickets/WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) |
| [WF-018](tickets/WF-018-prototype-sessions-first-browser-experience.md) | Prototype the sessions-first browser experience | wayfinder:prototype | AFK + HITL | Closed | [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md), [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md) |
| [WF-019](tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md) | Select the end-to-end proof and EPIC handoff | wayfinder:task | HITL | Closed | [WF-011](tickets/WF-011-prove-markdown-migration-and-authority-switch.md), [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md), [WF-015](tickets/WF-015-define-coordinated-task-authority-protocol.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md), [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md), [WF-018](tickets/WF-018-prototype-sessions-first-browser-experience.md), [WF-020](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md), [WF-021](tickets/WF-021-define-new-project-creation-and-registration.md), [WF-022](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md), [WF-023](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) |
| [WF-020](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md) | Define developer onboarding and operator guidance | wayfinder:grill | HITL | Closed | [WF-014](tickets/WF-014-define-skill-trust-and-harness-distribution.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md), [WF-023](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) |
| [WF-021](tickets/WF-021-define-new-project-creation-and-registration.md) | Define new project creation and registration | wayfinder:grill | HITL | Closed | [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md), [WF-023](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) |
| [WF-022](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) | Define browser instruction inspection and assisted editing | wayfinder:grill | HITL | Closed | [WF-009](tickets/WF-009-define-repository-context-and-policy-precedence.md), [WF-012](tickets/WF-012-define-browser-planning-and-conversation-ownership.md), [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md) |
| [WF-023](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) | Define local runtime and shared ingress topology | wayfinder:grill | HITL | Closed | [WF-016](tickets/WF-016-define-runner-dispatch-and-recovery.md), [WF-017](tickets/WF-017-define-execution-history-and-event-authority.md) |
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
WF-016 + WF-017 ──→ WF-023 local runtime/shared ingress
WF-014 + WF-016 + WF-023 ──→ WF-020 onboarding and operator guidance
WF-009 + WF-016 + WF-023 ──→ WF-021 new project creation and registration
WF-009 + WF-012 + WF-016 ──→ WF-022 browser instruction inspection/editing
WF-011 + WF-014 + WF-015 + WF-016 + WF-017 + WF-018 + WF-020 + WF-021 + WF-022 + WF-023 ──→ WF-019 proof and EPIC handoff
```

## Frontier

This map is complete. Its first separately approved grill handoff is [EPIC-00005 — Deliver the registered Planning workspace](../epics/00005-EPIC.md): guided local operation, repository and checkout registration, context resolution, explicit Markdown-to-PostgreSQL Planning cutover, and a minimal authoritative browser Planning surface. The EPIC consumes rather than repeats settled Wayfinder decisions; no EPIC was created by this map itself.

## Delegated implementation detail and later scope

- Exact schemas, API routes, migration classes, UI component boundaries, table columns, indexes, event payloads, and retention durations.
- Authentication and authorization details beyond the accepted single-installation shell, including the eventual relationship among installation, workspace, repository, user, agent, and runner permissions.
- Exact content and packaging of the reusable Harness instruction-writing Skill adapted with attribution from Matt Pocock's MIT-licensed `writing-for-agents`; browser Skill creation remains outside WF-022.
- Exact model assignments, OpenRouter allowlists and cost-budget values for browser Agents, local Codex profile choices, later provider additions, coding-Agent provider choices, and per-repository resource-lock catalogs beyond the accepted provider-configurable browser Agent boundary, three-consumer default, and bounded attempt policy.
- Exact dynamic completion-forecast cohorts, defaults, confidence thresholds, calibration method, and copy beyond the accepted honest updating range and unavailable/human-wait states.
- Exact cursor encoding and expiry, Mercure topic and reconnect behavior, Dashboard component/query boundaries, stale thresholds, log indexing/search, diff rendering thresholds, theme-switcher placement, and safe artifact-preview implementation remain downstream choices under the accepted WF-017/WF-018 semantics; WF-023 owns their local runtime dependencies, not production UI.
- Exact Compose files, image tags, health and restart intervals, volume names, proxy labels, certificate and local-DNS utilities, secret formats, backup commands, SeaweedFS settings, and bounded worker process configuration within WF-023's accepted topology.
- Whether a second real Fight consumer eventually proves a stable policy-neutral MCP SDK wrapper for Fight Common; protocol transport and current application planning, trust, and workflow policy remain outside Fight Common.
- The project-local `grill` skill still assumes every grill session creates exactly one EPIC; a later standalone bugfix should distinguish EPIC-producing grill from `wayfinder:grill`, where one map may eventually hand off to zero, one, or several EPICs. Do not allocate that TASK while concurrent planning work may be creating TASK records.
- Deployment, remote runner fleets, release management, merge automation, and operation after human merge.

## Out of scope

- Implementing production code, installing packages, changing global or personal configuration, registering a live runner, starting agents, migrating data, or switching planning authority during this map session.
- Writing an EPIC, requirement TICKET, implementation TASK, ADR, or production schema before this map reaches its approved handoff.
- Replacing, reinterpreting, or bulk importing the accepted EPIC-00002 through EPIC-00004 plans.
- Importing Factory workflows, plans, approvals, runtime code, or SQLite authority wholesale.
- Removing or archiving current Markdown planning automatically, even after a future database cutover.
- Agent approval or publication implying defect-free software, human PR approval, merge, release, deployment, or certification.
