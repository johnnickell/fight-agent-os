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

## Follow-on scope added — 2026-10-07

The original vision decisions and EPIC handoff were completed and remain accepted. John subsequently requested
three new Harness decision tickets for compaction timing, context retention and general-purpose Jev judgments.
This existing map is reopened only for that new scope; no new map is created and none of WF-008 through WF-023
is reopened. Earlier capability observations below are historical evidence, not a fresh runtime inventory.

The follow-on done condition is resolution or explicit exclusion of WF-029 through WF-031 with an approved
handoff into [EPIC-00006](../epics/00006-EPIC.md). The six approved first-milestone TASKs and current Board priority
are unchanged. Memory and triage maps retain their own frontiers and policy ownership.

The follow-on condition was met on 2026-10-08 through the approved, recorded EPIC-00006 amendment below.

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
8. **[Define the coordinated TASK authority protocol](tickets/WF-015-define-coordinated-task-authority-protocol.md) is closed.** Use distinct managed Explorer, Planner, Coordinator, Builder, Reviewer, and Publisher profiles; run one server-authoritative TASK workflow per explicit grant; preserve durable claims and commit-specific independent acceptance; allow five bounded revision cycles by default; publish one unstacked PR; permit proportionate mechanical repairs without ceremonial re-review; and return merge control to the human.
9. **[Define runner dispatch and recovery](tickets/WF-016-define-runner-dispatch-and-recovery.md) is closed.** Run one registered container-native PHP Agent runner with three one-shot consumers by default; dispatch one durable PostgreSQL-backed Command per explicitly granted TASK workflow; preserve leases, checkpoints, safe restart recovery, and distinct pause/cancel/disable/halt controls; sandbox each Pi peer to its isolated worktree; broker credentials and external tools through Agent OS; and grant no Docker socket.
10. **[Define execution history and event authority](tickets/WF-017-define-execution-history-and-event-authority.md) is closed.** Use one event-sourced Workflow per TASK grant with application-Command authority, separate Reviews and protected role-specific Pi conversation lineages, lightweight non-authoritative observations, immutable Git/code-review and S3-compatible Artifact evidence, truthful usage and duration facts, rebuildable browser projections, and tiered privacy-aware retention.
11. **[Prototype the sessions-first browser experience](tickets/WF-018-prototype-sessions-first-browser-experience.md) is closed.** Use a compact cursor-paginated Workflow table without embedded event history; switch the whole result set to an independent archive through one toggle; preserve running visibility across archive; load Summary, Changes, Activity, and Evidence independently; and place Sessions in a collapsible-sidebar/thin-bar/footer shell whose final themes and visual design remain separate.
12. **[Select the end-to-end proof and EPIC handoff](tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md) is closed.** Build and use a production Coordinator → Builder slice that stops at Awaiting review after database Planning and the existing application foundations; add an honest dynamic completion range; then sequence registered Planning, provider-configurable browser Agents/Harness, complete coordinated delivery, project creation, and instruction editing as non-overlapping EPIC handoffs.
13. **[Define developer onboarding and operator guidance](tickets/WF-020-define-developer-onboarding-and-operator-guidance.md) is closed.** Render one versioned task-based guide in the Dashboard; combine read-only preflight and post-bootstrap capability checks with explicit human-run mutations; support Linux and macOS honestly; preserve source, provider, keyring, container-secret, runner-bootstrap, and repository-registration boundaries; and validate documentation and setup commands directly without adding product tests for them.
14. **[Define new project creation and registration](tickets/WF-021-define-new-project-creation-and-registration.md) is closed.** Generate a fresh local Git repository from the newest immutable stable release of a managed or Workspace custom starter; allow admin-selected development profiles; preview and confirm deterministic preparation, optional private-by-default GitHub publication, and database-authoritative registration; preserve resumable partial effects; and limit playbook-guided browser Agents to advisory recovery using server-side OpenRouter support.
15. **[Define browser instruction inspection and assisted editing](tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) is closed.** Edit only Pi-native root context files through one designated checkout; keep Runner reads and expected-digest writes deterministic; let a browser Agent use a versioned writing Skill only to fill a human-submitted form; separate PR publication and safe default-branch synchronization; and provide no general repository editor.
16. **[Define local runtime and shared ingress topology](tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) is closed.** Use an installation-owned Compose stack with `nginx-proxy` shared ingress, explicit private shared-resource networks, canonical HTTPS with `.localhost` or operator-approved custom names, a complete bounded application/runner/Mailpit runtime, private SeaweedFS Artifact storage, container-native PHP commands, deliberate upgrades and host mutations, and no speculative observability or preview services.

### Follow-on decisions

- **[WF-030 — Define context retention and resume contract](tickets/WF-030-define-context-retention-and-resume-contract.md)**
  is closed as of 2026-10-08 with an accepted mandatory checkpoint, optional selection and safe-continuation contract.
  Automatic checkpointing/resume with intervention on exceptions, correlated observability, required-context
  overflow handling, native Pi compaction fallback when Jev is unavailable and bounded automatic recovery of
  missing required checkpoint facts are accepted. Optional context uses usefulness categories with ranking within
  each category. Required facts travel with source references; follow-up source reads use question-first Jev
  screening and the existing bounded exceptions. Checkpoint contents follow WF-017 retention, including the
  default 30-day terminal grace period and holds. Existing approvals survive compaction when their scope,
  current validity and applicable unconsumed status are verified. Supporting-source drift is reconciled within
  existing authority, with conflicts escalated. Protected per-conversation checkpoints and resume validation are
  accepted; runtime qualification remains future implementation evidence.
- **[WF-029 — Define compaction timing and notifications](tickets/WF-029-define-compaction-timing-and-notifications.md)**
  is closed as of 2026-10-08 with accepted timing, notification and recovery policies using WF-030's checkpoint
  contract. Automatic Harness initiation is accepted when timing and checkpoint checks permit.
  Initial current-profile targets are notice at 180k, recommendation at 210k and compaction request at 240k context
  tokens. From 180k the Agent may request safe compaction, for example before substantial work. From 210k actively
  recommend it at a safe point; a positive Jev recommendation can initiate compaction unless the Agent snoozes it
  while finishing work. Snoozes expire by 240k, when the Harness automatically compacts at the next safe point
  with WF-030 checkpoint validation. From notice, Jev checks run after every completed Agent turn, including during
  a snooze; duplicate evaluations and repetitive notices are suppressed.
  Shared evaluation budgets still apply and do not delay deterministic escalation. Native automatic compaction
  remains the final fallback. Other profiles scale automatically against their effective native trigger, with
  configurable overrides and reserved checkpoint capacity. Routine visibility uses Agent notices and quiet session
  status, with direct human alerts for intervention and all stages recorded in observability. At the request tier,
  finish and reconcile in-flight operations, then checkpoint and compact before another work step. Each Agent and
  sub-agent session compacts independently using its own usage/profile, with authorized parent status and no parent
  approval requirement. If Jev is unavailable or evaluation budgets are exhausted, skip assisted early compaction
  and continue with deterministic request-tier compaction and native fallback, retaining checkpoint checks and
  recording the degraded service. Unreliable usage or model limits suspend custom tiers in favor of verified native
  compaction; pause for intervention if safe native continuation cannot be established. Jev selects predefined
  compact/defer/uncertain and caller-authored reason choices; the Harness renders prewritten text, with no generated
  explanation expected from Jev. Compaction may proceed while awaiting a human answer, preserving the exact
  pending question and waiting state; replies arriving during the transition must be reconciled before dependent
  work. Interrupted compaction and harmless reload recover automatically only from verified state, discarding
  stale judgments and honoring explicit stops; unresolved recovery requires intervention. Question vocabulary,
  runtime enforcement, thresholds, child-session support, notification surfaces and scaling calculations remain
  implementation qualification work.
- **[WF-031 — Define general-purpose bounded judgments](tickets/WF-031-define-general-purpose-bounded-judgments.md)**
  is closed as of 2026-10-08 with an accepted general-purpose tool contract and semantic-investigation preference.
  Agent-authored bounded yes/no, choice and anchored-score questions plus reusable versioned presets are accepted;
  question authorship adds no authority. Evidence gathering may compose existing permitted MCP commands/queries,
  preserving the invoking principal's authenticated identity and current scope on every sub-call, without a
  privileged Jev identity. Safe repository static analysis and local/production application inspection are accepted
  within those permissions and separate disclosure controls. Any capability exposing Agent-controlled SQL,
  including MCP SQL tools, prefers a read replica; primary SQL requires explicit authorization, with enforced
  read-only permissions and query limits on both paths and no silent fallback. Normal API/MCP business operations
  remain primary-backed under their existing permissions and domain/effect contracts. Results use typed answers
  and choices/score anchors defined before evaluation, with compact evidence references and coverage/status metadata;
  source bodies, raw command output and generated explanations are excluded from the default response. Automatic
  advisory change-quality assessment before Engineer review handoff is accepted for simplicity, naming,
  understandability and semantic conventions. Independent reviewers may use focused Jev questions to narrow
  problem areas, then verify findings against source; scores do not become an acceptance gate. Assess all eligible
  changed code in bounded batches with revision-bound assessed/skipped/incomplete coverage; budget limits cannot
  silently turn a partial assessment into whole-change coverage. Reassess affected units and explicitly label
  reused results only after verifying unchanged code/context, rubric/evaluator and current access; retain original
  provenance while rebuilding coverage for current revisions. Score maintained code, including tests and applicable
  configuration; report generated-output and unmodified third-party exclusions, while assessing maintained
  generators and deliberate vendor modifications where applicable. Missing Jev/budget-limited assessment does
  not block review; handoff reports coverage gaps and retains normal independent review and required gates.
  Added/changed PHPUnit tests receive a usefulness assessment using existing contract, plausible-defect,
  independent-expectation and refactor-resilience criteria; the same check covers the entire owned suite during
  an architectural review with that scope. Model suspicions, verified findings and execution evidence remain
  distinct; report criterion outcomes and coverage without claiming proof that a test can never fail. Codebase-wide
  questions use Harness-owned discovery and staged evaluation across many authorized files, with broad cheap
  classification as a design goal and compact typed results for the main Agent. Batch limits do not impose a tiny
  total coverage cap; configurable aggregate budgets, containment, disclosure and honest coverage remain enforced.
  Reuse unchanged file/evidence judgments only after source/question/evaluator/policy and current-access checks,
  preserving original provenance; obtain fresh application/SQL observations by default. Code-quality assessments
  use shared versioned scoring anchors applied to each repository's requirements and conventions, with separate
  dimension results. Prefer Jev for semantic investigation and exact/structural/graph tools for precise symbols,
  dependencies and evidence; specialized consumer policies remain authoritative. First-party MCP delivery reuses
  shared evaluation and source resolution. Exact tool naming, schemas, limits and integration qualification remain
  implementation work.

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
| [WF-029](tickets/WF-029-define-compaction-timing-and-notifications.md) | Define compaction timing and notifications | wayfinder:grill | HITL | Closed | [WF-030](tickets/WF-030-define-context-retention-and-resume-contract.md) |
| [WF-030](tickets/WF-030-define-context-retention-and-resume-contract.md) | Define context retention and resume contract | wayfinder:grill | HITL | Closed | — |
| [WF-031](tickets/WF-031-define-general-purpose-bounded-judgments.md) | Define general-purpose bounded judgments | wayfinder:grill | HITL | Closed | — |
<!-- /planning:decisions -->

## Blocking relationships

Follow-on planning: WF-030 retention/resume → WF-029 timing/notifications → EPIC-00006 handoff.
WF-031 general-purpose judgments → EPIC-00006 handoff independently. These are decision dependencies, not new
blockers on the accepted implementation TASKs. The original resolved relationships remain below for provenance.

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

The map remains Closed with no decision frontier. Its original six destinations are written as EPIC-00005 through
EPIC-00010, and the Jev follow-on decisions/amendment are accepted. John has approved the remaining EPIC-00006
Harness/browser, EPIC-00007 execution, EPIC-00009 project-creation and EPIC-00010 instruction-editing TICKET splits.
The original map's requirement phase is complete against the actual accepted coverage traced below, including
EPIC-00008's retained managed TICKET-00083 rather than its historical local-only completion.

The selected original EPIC-00005–00010 planning phases are complete with
[TICKET-00109's approved final split](../tickets/00109-TICKET.md#approved-task-decomposition-and-current-handoff).
Next: `/skill:next` for Board-based selection, not implementation. Unrelated maps retain their own handoffs.
Use the [Board](../tasks/BOARD.md) for current active work and ready ordering, and the
[Roadmap](../ROADMAP.md#approved-blended-delivery-strategy) for blended delivery strategy. TASK-00138's completed
qualification is not production orchestration. Upstream local screening TASK-00309 is distinct; managed Review
is TASK-00445. Earlier active-work/counts and terminal-first priority statements in this map's decomposition
checkpoints are historical; accepted scope and supplier constraints remain. The
[TICKET-00109 publication checkpoint](../tickets/00109-TICKET.md#authorized-planning-publication-checkpoint)
retains its original evidence, not a live Board snapshot.
TICKET-00083 has approved TASK-00445 and TASK-00310–00320 coverage, TICKET-00085 TASK-00321–00326,
TICKET-00086 TASK-00327–00332, TICKET-00087 TASK-00333–00338, TICKET-00088 TASK-00339–00342 and TICKET-00089
TASK-00343–00347. TICKET-00090 adds TASK-00348–00354, TICKET-00091 TASK-00355–00360 and TICKET-00092
TASK-00361–00367, TICKET-00093 TASK-00368–00373, TICKET-00094 TASK-00374–00380, TICKET-00095 TASK-00381–00384,
TICKET-00096 TASK-00385–00389, TICKET-00097 TASK-00390–00395, TICKET-00098 TASK-00396–00400 and TICKET-00099
TASK-00401–00405. TICKET-00100 now adds TASK-00406–00410; see the
[current local-stage handoff](../tickets/00100-TICKET.md#approved-task-decomposition-and-current-handoff).
TICKET-00101 adds TASK-00411–00417; see the [current attempt handoff](../tickets/00101-TICKET.md#approved-task-decomposition-and-current-handoff).
TASK-00404 gains TASK-00411 and becomes ready behind suppliers; only its allocation hold resolves. TASK-00407 gains
TASK-00412 but retains protected local-operation gaps, TASK-00409 actual process/egress support, and TASK-00410 gains
TASK-00413 while retaining actual reference composition and inherited host/process holds.
TICKET-00102 adds TASK-00418–00421; see the [current publication handoff](../tickets/00102-TICKET.md#approved-task-decomposition-and-current-handoff).
TASK-00411/00414/00417 gain TASK-00418/00420/00421 respectively, resolving only those allocation gaps.
TICKET-00103 adds TASK-00422–00426; see the [current registration handoff](../tickets/00103-TICKET.md#approved-task-decomposition-and-current-handoff).
TASK-00411/00413/00414 gain TASK-00422/00423/00425 respectively, resolving only allocation. TASK-00413 becomes ready
behind suppliers; TASK-00411/00414 retain initial-mode and actual first-link/stage holds. TASK-00422/00425 retain
new-Repository database-authority initialization gaps under TICKET-00028, not supplied by migration/post-cutover
TASK-00102/00103. TASK-00410/00417 retain actual reference/host/process/provider holds; TASK-00418/00420 retain
protected `gh` invocation/current-identity and Git/SSH invocation mapping gaps.
TASK-00402's real-release and TASK-00403's protected private-source holds remain. No early supplier depends on final
browser/receipt/integrated acceptance, and existing execution owners gain no reverse blocker.
TASK-00387 gains early usage/time suppliers TASK-00396/00397 and TASK-00320 gains TASK-00400's base forecast/query/UI,
resolving only their allocation holds. TASK-00399 retains its quota-source/protected-refresh mapping hold; independent
host/prototype/authority holds and unfinished runtime remain.
TICKET-00104 adds TASK-00427–00429; see the [current support handoff](../tickets/00104-TICKET.md#approved-task-decomposition-and-current-handoff).
TASK-00428 retains the exact pre-registration attempt/Workspace Agent/snapshot/assignment supplier gap; existing
Repository-bound contracts cannot fabricate context or silently widen authority. Model-free help and deterministic
creation gain no AI blocker. EPIC-00009's six TICKET splits are recorded, not implemented or runtime-qualified.
TICKET-00105 adds TASK-00430/00431; see the [current inspection handoff](../tickets/00105-TICKET.md#approved-task-decomposition-and-current-handoff).
TASK-00430 retains the protected human read/current-custody supplier gap under existing execution/Runner owners;
TASK-00431 extends the existing Dashboard behind it. No second resolver, fabricated Workflow or reverse blocker.
TICKET-00106 adds TASK-00432/00433; see the [current Save/Delete handoff](../tickets/00106-TICKET.md#approved-task-decomposition-and-current-handoff).
TASK-00432 retains its distinct protected human mutation/shared-custody/physical-guarantee hold; TASK-00433 extends
the existing viewer with the form and recovery. No reverse dependency or implicit publication/AI/sync authority.
TICKET-00107 adds TASK-00434/00435; see the [current assistance handoff](../tickets/00107-TICKET.md#approved-task-decomposition-and-current-handoff).
Bounded durable suggestions and explicit existing-form adoption retain supplier holds and never gate direct edits.
TICKET-00108 adds TASK-00436–00440; see the [current publication handoff](../tickets/00108-TICKET.md#approved-task-decomposition-and-current-handoff).
Exact admission, local commit, push/PR, safe return and browser recovery retain distinct protected-operation holds;
creation/managed publication are not whole-journey prerequisites and no prior owner gains a reverse dependency.
TICKET-00109 adds TASK-00441–00444 for explicit check/status, exact fast-forward/recovery, currently authorized
scheduling and the focused Dashboard/final real journey. Protected fetch/custody, conditional Git/physical-exclusion
and scheduled actor/delegation/runtime mappings remain held. Planning completion is not executable priority or
runtime qualification; preserve accepted TASKs, unmet suppliers and the Board's authored execution order. Do not launch
implementation or reopen closed decisions. Latest planning checks are in
[TICKET-00109](../tickets/00109-TICKET.md#authorized-planning-publication-checkpoint); the publication-stage full build
passed, with final documented-tree verification/publication pending at that checkpoint.

The dated selected-slice handoffs below preserve their historical approvals and routing. They do not override
this current handoff or the TASK Board's execution priority. All runtime implementation and qualification remain
with their owning records.

### Completed handoff: Extend EPIC-00006 with Jev compaction and judgment workflows

[EPIC-00006](../epics/00006-EPIC.md#jev-compaction-and-judgment-workflows--2026-10-08) now consumes the accepted
WF-029 timing/notification policy, WF-030 checkpoint/retention/resume contract and WF-031 judgment/analysis/review
contract. The approved amendment covers staged compaction, observability and recovery; authenticated MCP evidence
gathering and safe SQL access; economical file fan-out and typed results; automatic advisory change-quality and
PHPUnit usefulness checks, including full-suite architectural review. Its complete Jev inventory distinguishes
first-milestone delivery, accepted follow-on requirements and proposals still owned by other maps.

The original six first-milestone TASKs, current Board priority, separate memory/triage map ownership and runtime/
publication boundaries remain unchanged. The Grill handoff updated one existing EPIC without allocating records.
On 2026-10-08 John separately approved the six-TICKET decomposition for this selected Jev follow-on scope:

- [TICKET-00040 — Preserve context through verified compaction and resume](../tickets/00040-TICKET.md)
- [TICKET-00041 — Schedule and observe safe session compaction](../tickets/00041-TICKET.md)
- [TICKET-00042 — Answer typed questions across bounded evidence](../tickets/00042-TICKET.md)
- [TICKET-00043 — Gather judgment evidence through authorized tools and analysis](../tickets/00043-TICKET.md)
- [TICKET-00044 — Assess change quality before review handoff](../tickets/00044-TICKET.md)
- [TICKET-00045 — Assess PHPUnit test usefulness](../tickets/00045-TICKET.md)

All six requirements are accepted. On 2026-10-08 John separately approved TICKET-00040's
[TASK-00164](../tasks/00164-TASK.md), [TASK-00165](../tasks/00165-TASK.md),
[TASK-00166](../tasks/00166-TASK.md) and [TASK-00167](../tasks/00167-TASK.md) for required checkpoints, optional
selection, selective repair and retention/holds. Pi 1.1.0 is the explicitly confirmed target. These TASKs remain
unranked, with recorded capability blockers; the original six first-milestone TASKs retain their scope and priority.
John subsequently approved TICKET-00041's [TASK-00168](../tasks/00168-TASK.md),
[TASK-00169](../tasks/00169-TASK.md) and [TASK-00170](../tasks/00170-TASK.md) for deterministic safe compaction,
every-turn Jev evaluation and history/incident inspection, also targeting Pi 1.1.0 and remaining unranked.
On 2026-10-09 John approved TICKET-00042's [TASK-00171](../tasks/00171-TASK.md),
[TASK-00172](../tasks/00172-TASK.md) and [TASK-00173](../tasks/00173-TASK.md) for authenticated first-party typed
judgments, many-file resumable investigations and evidence/context/authority-bound reuse. They target Pi 1.1.0,
remain unranked and preserve the shared evaluator/source-policy dependencies. Required actual MCP authority is an
implementation deliverable; local tools alone do not establish it.
On 2026-10-09 John approved TICKET-00043's [TASK-00174](../tasks/00174-TASK.md),
[TASK-00175](../tasks/00175-TASK.md), [TASK-00176](../tasks/00176-TASK.md) and [TASK-00177](../tasks/00177-TASK.md)
for caller-preserving MCP composition, qualified repository analysis, fresh application inspection and constrained
SQL. The latter three independently consume composition; all target Pi 1.1.0 and remain unranked. Production/replica
qualification is route-specific and grants no access or provisioning authority through planning.
On 2026-10-09 John approved TICKET-00044's [TASK-00178](../tasks/00178-TASK.md),
[TASK-00179](../tasks/00179-TASK.md) and [TASK-00180](../tasks/00180-TASK.md) for automatic pre-handoff quality
assessment, qualified refresh/reuse and independent reviewer follow-ups. All target Pi 1.1.0 and remain unranked;
actual automatic hooks and all rubric anchors require qualification. Scores stay advisory, gaps remain explicit
and findings require independent source evidence.
On 2026-10-09 John approved TICKET-00045's [TASK-00181](../tasks/00181-TASK.md),
[TASK-00182](../tasks/00182-TASK.md) and [TASK-00183](../tasks/00183-TASK.md) for changed-case usefulness,
explicitly scoped whole-suite architectural assessment and context-sensitive refresh/reuse. All target Pi 1.1.0
and remain unranked. Typed criterion outcomes, case/dataset coverage and independent verification remain distinct
from execution evidence; no implicit test execution, mutation tooling or automatic test changes are introduced.
The selected six Jev follow-on TICKETs now have 20 approved TASKs, TASK-00164 through TASK-00183. Their TASK
decomposition is complete; the original six first-milestone TASKs and executable Board priority are unchanged.
Older browser/Harness requirements and other map EPIC/TICKET work retain their existing status; this selected
follow-on does not claim full map-wide decomposition or implemented behavior.

Next: `/skill:next` to consult the authoritative Board for the completed selected Jev planning slice. This does not
rank/start implementation or claim that older EPIC requirements and other map handoffs are fully decomposed.

### Approved selected SDLC template decomposition

After the Jev handoff, the maintainer explicitly selected [TICKET-00034](../tickets/00034-TICKET.md) and approved
[TASK-00248](../tasks/00248-TASK.md) for reviewed eight-role specifications,
[TASK-00249](../tasks/00249-TASK.md) for exact authorized profile resolution and
[TASK-00250](../tasks/00250-TASK.md) for immutable role-session integration. The last two retain explicit
`needs-info` for actual template database, resource/model, session/snapshot and named-Agent primitives. Triage
remains TASK-00200's separate extension. TASK-00200/00206/00247 now name the applicable concrete template/profile
prerequisites without clearing their real remaining runtime holds or changing role-journey ownership.

The newer EPIC-00017 amendment governs template authority: initial reviewed definitions feed TICKET-00075's
database seed/storage owner; profile resolution consumes it. Browser publication and one-time Permission
reconciliation remain separate, without recurring source overwrite or automatic grants. No second Harness
registry, launcher or Workflow is introduced. All new TASKs are unranked and active Board work is unchanged.

Only this selected TICKET's decomposition is complete. Older EPIC-00006 requirements remain incompletely
decomposed, and the [AccessControl map](accesscontrol-consumer-adoption-map.md) still has 21 accepted TICKETs
awaiting TASK splits. Planning qualifies no Agent or session and authorizes no implementation or live effects.
Next planning operation: `/skill:to-tasks TICKET-00062`, returning to that map's pending Team primitives.

### Approved remaining EPIC-00006 requirement decomposition

John explicitly selected [EPIC-00006](../epics/00006-EPIC.md) in an isolated worktree and approved eight additional
requirement TICKETs, completing that EPIC's requirement split without replacing its existing team, branding or Jev
records:

- [TICKET-00085 — Install and configure the versioned Harness](../tickets/00085-TICKET.md)
- [TICKET-00086 — Manage versioned Harness resources](../tickets/00086-TICKET.md)
- [TICKET-00087 — Discover and load trusted first-party Skills](../tickets/00087-TICKET.md)
- [TICKET-00088 — Resolve immutable Harness session snapshots](../tickets/00088-TICKET.md)
- [TICKET-00089 — Configure and qualify protected generative providers](../tickets/00089-TICKET.md)
- [TICKET-00090 — Run and recover authorized durable Agent conversations](../tickets/00090-TICKET.md)
- [TICKET-00091 — Prepare and explicitly apply structured Planning proposals](../tickets/00091-TICKET.md)
- [TICKET-00092 — Deliver the browser Planning Agent journey](../tickets/00092-TICKET.md)

These are accepted requirements, not implemented capabilities or accepted TASK splits. They retain the seed-once
Agent-template amendment, fixed protected-sink credential delivery and qualified Pi Durable preference. Existing
EPIC-00014/00017 consumers retain their precise missing supplier-TASK/runtime holds; naming a requirement owner
does not clear readiness. Generic resources/models/snapshots/sessions/proposals must not reverse-depend on final
browser Planning, Explorer, AI-template/custom-use integration or the complete Workflow. Actual Planning Apply
still requires its verified database authority; earlier manual/provider/proposal primitives do not.

No new TASK, grant, provider call, runtime activation, priority change or automatic Markdown cutover occurs here.
Remaining map work is the TICKET phase for EPIC-00007's undecomposed requirements and EPIC-00009/00010, before
map-wide TASK decomposition. The closed decision map needs no reopened interviews or new queue for that work.
Verification and the isolated worktree outcome are recorded in EPIC-00006; formal review and delivery are separate.

Next: `/skill:to-tickets EPIC-00007` for remaining requirements, preserving TICKET-00032 and its accepted TASKs.

### Approved remaining EPIC-00007 requirement decomposition

John continued on the same isolated planning branch and approved six additional requirement TICKETs:

- [TICKET-00093 — Operate the enrolled Runner and bounded dispatch](../tickets/00093-TICKET.md)
- [TICKET-00094 — Retain and protect immutable execution evidence](../tickets/00094-TICKET.md)
- [TICKET-00095 — Provide bounded first-party web research](../tickets/00095-TICKET.md)
- [TICKET-00096 — Expose trustworthy Workflow history and live state](../tickets/00096-TICKET.md)
- [TICKET-00097 — Inspect and control execution through the sessions Dashboard](../tickets/00097-TICKET.md)
- [TICKET-00098 — Explain execution usage and completion estimates](../tickets/00098-TICKET.md)

Together with [TICKET-00032](../tickets/00032-TICKET.md)'s retained core execution, these accepted records complete
EPIC-00007's requirement decomposition. TASK-00138–00142 remain unchanged. The existing Start/claim/grant/lease,
sandbox/preparation, Team Lead → Software Engineer handoff and recovery contracts are not replaced or split into
competing authorities. New Runner operation consumes committed dispatch/custody; evidence supplies exact subjects;
history, Dashboard and forecasts remain non-authoritative consumers. Accepted Team/Agent selection, browser
Start/Pending, protected Harness and later managed Review/QA/publication retain their owners and unmet supplier holds.

This is requirement approval, not TASK decomposition, implementation qualification, Agent launch or completed EPIC.
No new Board priority, automatic provider call, live enrollment, extra normal-start prompt or database Planning
cutover is introduced. The terminal milestone remains independent of final browser/forecast delivery; Markdown
continues supplying immutable eligibility until its separate verified switch. Exact early shared supplier TASKs
must not reverse-depend on finished consumer journeys. Verification is recorded in EPIC-00007; delivery is separate.

The remaining original map requirement phase is EPIC-00009 and EPIC-00010. Finish those approved destinations'
TICKET splits before descending to remaining TASK decompositions, unless the human explicitly selects otherwise.
Earlier selected-slice next lines above are historical and do not supersede this current handoff.

Next: `/skill:to-tickets EPIC-00009`.

### Approved EPIC-00009 creation requirement decomposition

John selected EPIC-00009 and approved six requirement TICKETs on the same isolated planning branch:

- [TICKET-00099 — Manage trusted immutable starter releases](../tickets/00099-TICKET.md)
- [TICKET-00100 — Generate and prepare a fresh local project](../tickets/00100-TICKET.md)
- [TICKET-00101 — Confirm and recover one durable creation attempt](../tickets/00101-TICKET.md)
- [TICKET-00102 — Publish the confirmed initial project to GitHub](../tickets/00102-TICKET.md)
- [TICKET-00103 — Register the created checkout and complete the handoff](../tickets/00103-TICKET.md)
- [TICKET-00104 — Explain creation failures with bounded support assistance](../tickets/00104-TICKET.md)

These accepted requirements complete EPIC-00009's TICKET split, not its TASK decomposition or runtime acceptance.
Catalog/manifest, deterministic local stages, one confirmed durable attempt, optional human-authorized GitHub
publication, existing registration/context integration and advisory support retain distinct ownership. No second
Repository/context/template/provider authority, coding Workflow, TASK claim or managed Review pipeline is added.
Local-only remains first-class; assistance outage never gates deterministic creation or recovery. Root/mount and
credential setup remain separate explicit operator actions.

TICKET-00103 carries the actual integrated create/register evidence, including at least one real eligible managed
stable release `>=1.0.0`, preferably `project-slim`, plus local-only/private-publication and recovery paths. That
source release remains an external authorized maintainer action; this planning session neither verified current
release availability nor created one. New generated repositories begin with verified database-authoritative context
without importing Planning; existing Markdown authority, source projects, readiness holds and Board priority remain
unchanged. No project, snapshot download, credential/provider access, cleanup or publication occurs through planning.

Exact early shared contracts must not reverse-depend on completed browser/integrated journeys. No TASK is allocated.
Verification is recorded in EPIC-00009; full build, review and delivery remain separate. EPIC-00010 still needs its
accepted requirement split before the remaining map TASK phase. Earlier selected-slice next lines are historical
and do not supersede the current Frontier.

Next: `/skill:to-tickets EPIC-00010`.

### Approved EPIC-00010 instruction-editing requirement decomposition

John selected EPIC-00010 and approved five requirement TICKETs on the same isolated planning branch:

- [TICKET-00105 — Inspect effective repository instructions](../tickets/00105-TICKET.md)
- [TICKET-00106 — Save and delete instruction files safely](../tickets/00106-TICKET.md)
- [TICKET-00107 — Prepare bounded AI instruction suggestions](../tickets/00107-TICKET.md)
- [TICKET-00108 — Publish an instruction change through a recoverable PR](../tickets/00108-TICKET.md)
- [TICKET-00109 — Synchronize the designated checkout safely](../tickets/00109-TICKET.md)

The accepted split completes this EPIC's requirements without another context/identity/Skill/session/Runner owner,
coding Workflow or managed Review dependency. Direct editing works without AI; assistance cannot submit. Save is
local only; publication requires its own exact consent and safe default-branch return; manual/scheduled sync remains
currently authorized and clean fast-forward-only. Shared checkout ownership and effective-change contracts precede
final integration so producers do not wait on finished consumers. Existing supplier holds and Board order remain.

TICKET-00109 carries the real Fight Agent OS instruction-change journey through separately human-controlled merge
and safe sync, with focused evidence from each owning TICKET, not a demonstration or certification product. This
planning session created no instruction change, Skill, Agent/provider call, branch, PR, merge or scheduled update.
No TASK was allocated. Verification is recorded in EPIC-00010; full build, review and delivery remain separate.

### Original map requirement-phase coverage and next phase

This coverage follows accepted requirement records and authored handoffs, not child-table non-emptiness or parent
implementation status. Historical next lines above do not supersede the current Frontier.

| Destination | Accepted requirement coverage |
|---|---|
| [EPIC-00005](../epics/00005-EPIC.md) | Approved TICKET-00023–00029 cover installation, identity, context, Planning, queue, migration and Dashboard; existing TASK splits remain |
| [EPIC-00006](../epics/00006-EPIC.md) | Existing TICKET-00034/00035/00037–00045 retain team/branding/Jev; approved TICKET-00085–00092 complete generic Harness/browser requirements; TICKET-00085–00092 now have TASK-00321–00367 with remaining supplier holds, without replacing template/Agent owners |
| [EPIC-00007](../epics/00007-EPIC.md) | TICKET-00032 retains core execution; approved TICKET-00093–00098 complete Runner/evidence/research/history/browser/usage requirements; TICKET-00093–00098 now have TASK-00368–00400 without clearing early host/prototype gaps; TASK-00387's exact early suppliers are mapped, while TASK-00399 retains its quota-source/refresh hold |
| [EPIC-00008](../epics/00008-EPIC.md) | Accepted TICKET-00083 retains the entire managed scope, now decomposed into approved TASK-00445 and TASK-00310–00320 with explicit supplier holds; completed local TICKET-00033/00084 do not satisfy managed runtime |
| [EPIC-00009](../epics/00009-EPIC.md) | Approved TICKET-00099–00104 cover deterministic creation, confirmed recovery, optional publication, registration and support; TICKET-00099–00104 now have TASK-00401–00429 with release, private-source, initial-mode, protected-operation and pre-registration support-scope holds retained |
| [EPIC-00010](../epics/00010-EPIC.md) | Approved TICKET-00105–00109 cover focused instruction inspection/editing, optional assistance, separate publication and safe synchronization; TICKET-00105 maps TASK-00430/00431 and TICKET-00106 maps TASK-00432/00433, with distinct protected read and mutation/custody holds retained; TICKET-00107 maps TASK-00434/00435 without clearing Skill/Agent/provider/host holds; TICKET-00108 maps TASK-00436–00440 with protected human/Git/source-host holds retained; TICKET-00109 maps TASK-00441–00444 with fetch/custody, conditional Git and scheduled-authority holds retained |

All six original destinations now have accepted TICKET and TASK-decomposition coverage. The final approved
TICKET-00109 split completes this original scope's planning phases, not actual implementation or runtime qualification.
Existing Jev/team/local TASKs keep their accepted outcomes; other maps retain their own unfinished planning.
TICKET-00083 and TICKET-00085–00109 now have approved TASK splits. Consult the authoritative Board for execution
selection; new IDs confer no priority and upstream TASK-00138 completion is preserved.
No new blocker, priority, credential, runtime or publication grant is created by this phase handoff.

Next: `/skill:next`.

### Approved selected managed review and publication decomposition

The maintainer explicitly selected TICKET-00083 before the default Harness sequence, requested an isolated
worktree and approved twelve TASKs. [Its coverage and current handoff](../tickets/00083-TICKET.md#approved-task-decomposition-and-current-handoff)
map TASK-00445 and TASK-00310–00320 to the full accepted managed Review/revision/acceptance/QA, mechanical reconciliation,
Release Manager closeout/publication, evidence/handoff/cleanup, trusted merge/local maintenance and Dashboard/
forecast outcomes. Recovery and current authority remain inside each owning delivery slice.

Existing named-role TASK-00267–00269 and hotfix/release TASK-00207/00215 now depend on the relevant generic
suppliers, not each other's completed journeys. Lower-level session/admission/contribution/evidence mappings
under existing owners and TICKET-00096–00098 presentation/forecast mappings were unresolved at that split.
TASK-00445/00319/00320 and existing held consumers retained `needs-info`; other new records kept explicit blocker
chains. Subsequent mappings are recorded below, including TASK-00400's resolution of TASK-00320's allocation hold;
TASK-00445/00319 and independent held consumers are not cleared by that forecast mapping.
No Board priority or first Awaiting review requirement changes. This completes only the selected TICKET's TASK
split, not implementation, supplier qualification, remaining map decomposition or publication authority.

That selected split handed planning to TICKET-00085, whose subsequently approved decomposition is recorded below.

### Approved Harness installation and configuration decomposition

The maintainer approved [TICKET-00085's six slices](../tickets/00085-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated planning worktree: TASK-00321–00326 own exact package installation/update/rollback,
trusted local connections, revisioned user preferences, protected interactive credential integration,
textual identity/linked switching and guided setup/read-only diagnostics. Reuse existing branding and operator
guidance; actual protected storage, credential/current-use and session authority remain with their owning suppliers.

At this split's approval, TASK-00323 retained TICKET-00086/00089 resource/model mapping holds; the subsequent
resource split below now maps the resource portion while preserving the model hold. TASK-00325 retains
TICKET-00088/00090 snapshot/continuation/session mapping holds. TASK-00324 consumes TASK-00241's actual credential readiness without
reverse-blocking its primitives. User Harness profiles do not replace role templates or clear TASK-00249's holds.
The earlier package/connection/profile facts supply later snapshot consumers, never depend on final switching,
onboarding, Explorer or browser Planning. Browser Planning and database Planning cutover are not installation gates.

This completes only TICKET-00085's TASK split, not supported-runtime qualification, remaining map decomposition,
publication, global configuration changes or credential access. Orders remain unranked and Board priority unchanged.
That split handed planning to TICKET-00086, whose subsequently approved decomposition is recorded below.

### Approved versioned Harness resource decomposition

The maintainer approved [TICKET-00086's six slices](../tickets/00086-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated worktree. TASK-00327–00332 own managed static Skills, scoped custom Skills, prompts/personas,
themes, explicit resource retirement/retained selection and attributed writing-Skill delivery to real Planning.
These are type-specific product paths, not a generic resource aggregate, template reconciler or proof-only platform.

TASK-00327/00329/00330 now supply exact resource/reference facts to TASK-00299 and transitively TASK-00249, plus
TASK-00323. TASK-00331 supplies TASK-00300's full lifecycle participants, without clearing complete publication-
safety qualification. Remaining model, Triage, scoped-read, host and consumer-composition holds persist.
At this split's approval TASK-00328/00332 retained TICKET-00087 collision/loader mapping holds. The subsequent
trusted-loader split below maps those suppliers while preserving lower-level session/host holds. Neither completed
custom publication nor final writing-Skill delivery reverse-blocks the primitives.

Preserve template seed-once database authority, live Permissions and immutable history; no automatic propagation,
purge, local-Skill removal or Planning cutover is introduced. Actual writing-Skill delivery needs exact trusted
selection and supporting resources despite the local alias collision, not merely an imported source bundle.
This completes only TICKET-00086's TASK split; runtime, remaining map decomposition and publication stay incomplete.
Orders remain unranked and Board priority unchanged. That split handed planning to TICKET-00087, whose approved
decomposition is recorded below.

### Approved first-party Skill trust and loading decomposition

The maintainer approved [TICKET-00087's six slices](../tickets/00087-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated worktree. TASK-00333–00338 own explicit first-party trust, early effective-alias observations/
choices, protected static MCP publication, verified Pi activation/supporting reads, safe verified cache and exact
origin removal through existing credential owners. TASK-00245 supplies protected transport, not a second server.

TASK-00245 gains TASK-00333 but retains its lower-level current-use/session/host holds. TASK-00328 gains TASK-00334,
resolving its sole mapping hold and becoming `ready-for-agent` behind unfinished suppliers. TASK-00332 gains
TASK-00336/00337 but retains `needs-info` through the loader's precise TICKET-00088/00090 admission/host/acting-window
holds. TASK-00324 gains TASK-00338's cleanup composition; that supplier uses lower-level credential owners, not
completed onboarding. Early trust/resource/choice facts supply snapshot bootstrap and cannot depend on completed
remote loading. No existing model, Triage, scoped-read or consumer publication-safety hold is cleared by this split.

Current installed Pi 1.2.0 documentation describes generic MCP tools/resources, unlike the historical 0.87.1
research. This changes the implementation starting point, not the accepted origin/Skills/authentication/host safety
requirements or qualification evidence. Actual supported SDK/Pi/protocol selection and all reachable path checks
remain inside implementation; docs or server conformance alone do not prove managed loading.

This completes only TICKET-00087's TASK split; no origin was trusted, Skill loaded, cache deleted or credential
changed. Orders remain unranked and execution priority unchanged. That split handed planning to TICKET-00088,
whose approved decomposition is recorded below. Runtime and remaining map work stay incomplete.

### Approved immutable Harness snapshot decomposition

The maintainer approved [TICKET-00088's four slices](../tickets/00088-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated worktree. TASK-00339–00342 own exact configuration resolution/inspection, immutable snapshot
bindings, drift/currentness guards and actual session reconnect/explicit linked continuation. Runtime-reported
model/response identity remains separately attributed evidence, never a rewrite of selected configuration.

TASK-00244/00336 gain TASK-00340/00341's early binding/currentness contracts, not the completed TASK-00342 consumer.
TASK-00250/00325 gain TASK-00342 while retaining their other role/provider/contribution or protected-session holds.
Generic snapshots do not duplicate TASK-00249's resolver or require completed role/switching/onboarding journeys.
At this split, TASK-00339/00340/00342 retained precise TICKET-00089 model/provider-outcome and TICKET-00090 early
identity/binding/runtime/recovery holds. The subsequent provider mappings are recorded below; snapshot mapping
does not qualify current grants, host/acting-window or live sessions.

This completed only TICKET-00088's TASK split, with no snapshot, session, grant or provider call created. All orders
remained unranked and terminal-first priority unchanged; planning continued to TICKET-00089.

### Approved protected generative provider decomposition

The maintainer approved [TICKET-00089's five slices](../tickets/00089-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated worktree. TASK-00343–00347 own early allowed provider/model choices, disclosure/budget admission,
original-outcome/accounting and independent actual OpenRouter/local Codex paths with real Planning consumption.
Configuration is not qualification; missing usage is not zero and uncertain work cannot silently regenerate.

TASK-00323/00339 gain TASK-00343, resolving their sole supplier-mapping holds and becoming ready behind unfinished
dependencies, not live support. TASK-00299/00249 receive model references while retaining Triage/scoped-read holds;
TASK-00300 retains full publication/current-authority composition. TASK-00342 gains TASK-00345 original outcomes,
retaining session/host/continuation holds. TASK-00302/00304 gain TASK-00344/00345, preserving proposal/manual/runtime/
Feature and other consumer holds. Each consumer qualifies its selected permitted provider, not mandatory setup of both.

TASK-00340/00342 and new provider owners retain precise TICKET-00090 identity/attempt/binding/invocation/host/recovery
contracts. Early participants cannot depend on completed provider-driven execution, final continuation, role/template
journeys or browser Planning. Actual qualification/guidance stays inside delivery, not proof-only TASKs. Jev,
credential delivery and existing session/proposal ownership stay distinct; no credentials or provider calls occurred.

This completed only TICKET-00089's TASK split. All orders remained unranked and Board priority unchanged;
planning continued to TICKET-00090. Its subsequent mappings below supersede the allocation holds above, not
actual runtime qualification.

### Approved durable conversation decomposition

The maintainer approved [TICKET-00090's seven slices](../tickets/00090-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated worktree. TASK-00348–00354 own bounded assignments/conversation identity, durable input/attempt
fences, protected runtime/storage, production invocation, attachment/history, actual stop/recovery and protected
retention/redaction. Pi Durable evaluation remains within delivery, with exact runtime/source/extensions/storage
qualification and concrete gaps before an alternative; existing local checkpoint owners keep their accepted scope.

TASK-00340 gains TASK-00348 and TASK-00344/00345 gain TASK-00349, resolving their three sole mapping holds and
becoming ready behind unfinished dependencies. TASK-00244 gains early TASK-00348/00349/00350, not final invocation;
TASK-00336 gains TASK-00350; provider adapters gain TASK-00351; TASK-00342 gains TASK-00351/00352/00353 and retains
linked snapshot-change integration. TASK-00250 and existing Explorer/Project Manager/template/custom consumers
retain their role outcomes and remaining contribution, Triage/scoped-read, proposal/browser and publication holds.

TASK-00350 remains `needs-info` for exact reusable operational host/broker participants under existing execution
owners. TASK-00138 qualification is not implementation; completed Workflow/Runner journeys are not a substitute
or reverse prerequisite. Consumer host/current-authority holds remain, and a selected actual provider must qualify
live reconnect/recovery without requiring both configured. No session, grant, provider call, transcript access or
cleanup occurred; planning is not live delivery or a new runtime authority.

This completed TICKET-00090's split with unranked orders and unchanged terminal-first Board priority; planning
continued to TICKET-00091. Its subsequent proposal mappings below do not supply operational qualification.

### Approved structured Planning proposal decomposition

The maintainer approved [TICKET-00091's six slices](../tickets/00091-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated planning worktree. TASK-00355–00360 own early protected proposals, revisioned human answers/
operation boundaries, typed Planning candidates, explicit human Create/Apply, all-or-none multi-record changes
and separately requested reconciliation producing a new candidate for human review.

TASK-00302 gains TASK-00355 admission/result/status/cancellation/recovery independently of database Planning
cutover, retaining template validation, Feature admission and explicit publication. TASK-00266 gains TASK-00360
and its proposal/answer/Apply chain while retaining TICKET-00092's precise browser gap and actual role integration.
TASK-00356 consumes TASK-00086/00104 alongside conversation input/attachment; semantic Planning and verified
cutover remain with existing owners. Neither completed browser/role work nor Planning Apply reverse-blocks the
early generic proposal, provider or conversation suppliers.

All six TASKs are ready behind unfinished dependencies, not qualified runtime. TASK-00350's operational host/broker
gap and independent Team/creation, Triage/scoped-read, contribution, authority/Feature/publication holds persist.
Selected-provider live evidence, actual transaction/race tests and operator guidance remain inside delivery, not
proof-only TASKs. No proposal, answer, model call, Planning mutation, cutover or runtime execution occurred.

This completed TICKET-00091's split with unranked orders and unchanged terminal-first Board priority. Its browser
handoff was subsequently decomposed below; proposal mapping is not runtime or managed-role qualification.

### Approved browser Planning journey decomposition

The maintainer approved [TICKET-00092's seven slices](../tickets/00092-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated planning worktree. TASK-00361–00367 deliver durable intake, interviews/map creation, distinct
artifact operations/Apply, bounded AFK preparation, same-conversation recovery/Stop/Abandon, private sharing/handoff/
retention and explicit context/provider continuation. Actual browser tests, accessibility, operational guidance and
real-use evidence stay within delivery outcomes, not a new proof-only TASK.

TASK-00266 gains TASK-00362–00367 and TASK-00361 transitively, preserving final named Project Manager integration and
TASK-00247's original Explorer outcome. This resolves browser allocation, not actual role/host/current-authority
composition; `needs-info` remains. Generic browser operations never depend on completed TASK-00266 and no provider,
conversation or proposal supplier reverse-depends on final UI. Database cutover remains with TASK-00104/00114.

TASK-00364 remains `needs-info` for exact protected prototype-execution participants under existing TICKET-00032 and
relevant TICKET-00093 operational owners. TASK-00087 evidence storage is not a runner. External web routes separately
require TICKET-00095's qualified search/retrieval, disclosure/SSRF and exact-content Jev screening; local-only work
need not wait for web research. TASK-00350's host/broker gap and independent Team/profile/current-authority holds
persist. The other six TASKs are ready behind unfinished suppliers, not qualified runtime.

This completed TICKET-00092's browser split with unranked orders and unchanged terminal-first Board priority.
No intake, model call, prototype, share/handoff, history read, live Planning mutation, cutover or cleanup occurred.
Its subsequent Runner handoff is decomposed below, not implemented.

### Approved Runner operation decomposition

The maintainer approved [TICKET-00093's six slices](../tickets/00093-TICKET.md#approved-task-decomposition-and-current-handoff)
in the same isolated worktree. TASK-00368–00373 cover protected enrollment/inspection, shared admission participants,
original dispatch, one-shot consumers, drain/Halt and Runner restart/recovery. Actual operational evidence and guide
updates stay within delivery, not a new proof-only TASK. That split preserved TASK-00139–00142 scope, blockers/order
and Start/preparation/role/recovery ownership; the subsequent evidence mapping below adds narrow suppliers. Runner-only installation adds no browser or Planning-cutover gate.

TASK-00254/00261/00263 gain TASK-00369's early applicable Workflow commitment/capacity/resource participants and
TASK-00264 consumes it transitively. Independent manual-session/Workflow composition, creation/current authority,
base-inclusion, contribution/fresh-session and host holds remain. No final role, selector or consumer reverse-blocks
the primitives it needs. All six new TASKs are ready behind unfinished suppliers, not operationally qualified.

TASK-00350/00364 remain `needs-info` for early protected host/broker/storage and prototype-execution participants.
Enrollment/capacity and completed Workflow delivery cannot implement them; this Runner split excludes a new sandbox/
provisioner or fabricated manual Workflow. TICKET-00094 owns exact early evidence/Artifact participants and
TICKET-00095 separately owns qualified screened research. Missing evidence/retention participation preserves content
and blocks unsafe recovery or cleanup.

This completed the Runner split, not its runtime. New orders remained unranked and terminal-first Board priority
unchanged. No enrollment, credential operation, queue, process, provider call, recovery, cleanup, implementation
or publication occurred. The subsequent evidence handoff is decomposed below.

### Approved immutable execution evidence decomposition

The maintainer approved [TICKET-00094's seven slices](../tickets/00094-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00374–00380 in the same isolated planning worktree. They deliver private Artifact transfer/recovery,
contribution provenance, immutable Git subjects, exact check receipts, bounded protected reads, held retention/
eligible purge and cross-copy redaction. Actual qualification and guidance remain within delivery, not proof-only TASKs.

TASK-00140/00087 gain TASK-00374; TASK-00250/00261/00264 gain TASK-00375; TASK-00141/00373 gain TASK-00377;
TASK-00445 gains TASK-00378. These are early supplier mappings, not complete role/Review or live storage qualification.
Independent source completeness, configured-base inclusion, authority, creation, phase/fresh-session and host holds
remain. TASK-00141 still owns complete handoff/Awaiting review and its real production TASK evidence. Existing
execution ownership/order remains; neither final consumers nor destructive retention reverse-block early primitives.

Private SeaweedFS integration extends one installation without the complete browser chain or another storage stack.
TASK-00087/00354 retain Planning/Pi lifecycle owners and supply actual reference/hold participants. Missing coverage
preserves content or keeps it denied; Awaiting review is not terminal cleanup. Future history/browser copies must
honor restriction/tombstone fences before exposure, not be assumed already qualified. TASK-00350/00364 host/prototype
holds remain; evidence storage is not execution and private reads do not bypass TICKET-00095's screened web gate.

This completed the evidence split, not its runtime. All seven new orders remained unranked; terminal-first priority
and active TASK-00138 stayed unchanged. No storage/credential operation, execution check, private-content read,
retention/redaction/purge, implementation or publication occurred. The subsequent web-research split is below.

### Approved bounded first-party web research decomposition

The maintainer approved [TICKET-00095's four slices](../tickets/00095-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00381–00384 in the same isolated planning worktree. They deliver exact-content Jev admission, protected
Brave search, independently authorized SSRF-safe retrieval and retained/cached/resumed web delivery. Operational
qualification and guidance remain inside delivery; no proof-only TASK, implementation or provider call is authorized.

TASK-00381 consumes TASK-00171's actual protected shared evaluator and current-use broker chain, not local Pi readiness
alone. Search and retrieval each depend only on that gate and remain independent. TASK-00384 consumes both plus
TASK-00374/00378/00353 storage/read/recovery owners. No generic supplier reverse-depends on completed web research,
no second evaluator/MCP/transcript store is introduced and the local Jev scope remains unchanged.

Fresh bounded delivery cannot silently certify truncated material or activate retained/cached/resumed routes before
qualification. TASK-00384 integrates current admission at supported web Artifact/context ingress; ordinary queries
stay provider-free and require a separate authorized operation when fresh screening is needed. Missing/redacted
content is not refetched automatically. Historical evidence and unknown usage remain honestly labelled.

TASK-00364 and TICKET-00043 now name conditional web suppliers without blanket blockers on local-only preparation,
generic judgments or every terminal TASK. Prototype/host and independent authority holds remain. Current caller
scope, separate retrieval/disclosure authority and exact-content screening are mandatory on each qualified route.

This completed the web-research split, not its runtime. All four new orders remained unranked; terminal-first
priority and active TASK-00138 stayed unchanged. The subsequent history/live split is below.

### Approved trustworthy Workflow history and live-state decomposition

The maintainer approved [TICKET-00096's five slices](../tickets/00096-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00385–00389 in the same isolated planning worktree. Independent semantic history and safe phase activity
consume TASK-00139/00117 and TASK-00141/00378 respectively. Whole-result stable search/summaries combine those
sources; actual history-copy lifecycle participation consumes TASK-00380, and committed live transport uses
TASK-00049's sole production principal/cache owner. Qualification and operator guidance belong inside delivery.

TASK-00387 remained `needs-info` for two allocation gaps at that split. TICKET-00097 subsequently mapped its early
revision-checked visibility/current-membership and independent-running safeguard to TASK-00390 below. TICKET-00098
subsequently mapped early attributable usage/time and projection integration to TASK-00396/00397. Those primitives
use early history/activity or source contracts but cannot depend on completed TASK-00387, final Dashboard or full forecast.
Allocation alone will not establish actual operational qualification.

Safe admission and current disclosure apply from inception, not after complete cross-copy cleanup. Observations,
projector progress and live notifications never change Workflow authority or reconstruct a missing event. Raw Pi
context, prompts, model/assistant responses, excerpts, thinking and compiled instructions remain excluded. Current
restriction/tombstone fences govern replay, restore and late publication; unknown copy coverage blocks affected
cleanup/full-removal claims and preserves or denies content.

TASK-00389 includes the narrow protected Mercure addition within the existing installation, not a parallel topology
or TASK-00072's complete browser prerequisite chain. Actual authenticated delivery/reload is required; MockHub is
not evidence. No existing terminal producer gains a history/live blocker. Independent host/prototype/authority
holds and Markdown Planning authority remain; no new web-call or cutover prerequisite is introduced.

This completed the history/live split, not runtime delivery. All five orders remained unranked; terminal-first
priority and active TASK-00138 stayed unchanged. No capture, rebuild, private read, subscription, credential/service
effect, cleanup, implementation or publication occurred. The subsequent sessions Dashboard split follows.

### Approved sessions Dashboard decomposition

The maintainer approved [TICKET-00097's six slices](../tickets/00097-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00390–00395 in the same isolated planning worktree. TASK-00390 supplies revision-checked visibility and
independent-running safeguards from TASK-00385, without depending on completed projected search or browser delivery.
TASK-00387 gained that exact blocker, resolving only its visibility allocation at that point. TICKET-00098's
subsequent early usage/time mapping is recorded below. Mapping does not qualify participants. Unknown
active-running safeguards reject active archive; visibility never changes execution, claims, grants or Planning.

TASK-00391 consumes TASK-00390/00052/00389 for compact searched/paginated Current/Archived views, overview metrics,
Summary and real live following. TASK-00392/00393 independently add immutable Changes/Evidence and safe Activity.
TASK-00394 consumes existing TASK-00373/00142 controls/recovery and TASK-00395 existing TASK-00265/00205 Start/Pending
interaction. No duplicate engine, new normal-work confirmation, synthetic data, transcript/excerpt viewer or client
eligibility is introduced. Ordinary real TASK evidence, per-slice browser accessibility/visual qualification, user
guidance and full gate remain inside delivery, not a separate proof-only TASK.

Qualified broader-scope quota and forecasts stay with TICKET-00098, extending these independently loaded surfaces;
absence is unavailable, never zero or a Repository balance. Its early facts must not depend on completed history
search, live consumer or final Dashboard. Existing host/prototype/authority/source/session holds remain; no supplier
or terminal primitive gains a browser blocker. Active TASK-00138, Board priorities and Markdown authority persist.

This completed the sessions split, not runtime delivery. All six orders stayed unranked. No implementation, private
read, visibility/control/Start mutation, credential/provider/runtime operation, cutover, cleanup or publication
occurred. The subsequent measurement/forecast split follows.

### Approved measurement and forecast decomposition

The maintainer approved [TICKET-00098's five slices](../tickets/00098-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00396–00400 in the same isolated planning worktree. TASK-00396 connects original TASK-00345 accounting to
actual Workflow/phase lineage and TASK-00385/00386 source/projection integration. Independent TASK-00397 supplies
known timing categories/intervals from those history/producer owners. Both supply TASK-00387 without depending on
its completed search, Dashboard or forecast. TASK-00387's remaining allocation hold is resolved and it becomes
ready behind unfinished suppliers; actual source/transaction/authority qualification is still required.

TASK-00398 extends TASK-00391 with independently loaded measurement explanations. TASK-00399 delivers separately
authorized account/key allowance and bounded explicit protected refresh but remains `needs-info`: its supported
source and exact credential/refresh supplier are unallocated. Generation support does not prove quota support;
missing quota stays unavailable and blocks neither history, usage/time nor forecasts. No source is invented and
ordinary queries never trigger provider work, authentication or generation.

TASK-00400 independently delivers explainable bounded ranges through Awaiting review and immutable prediction/basis
versus actual-outcome comparisons, including changed/incomplete cases. It uses the same query/live/UI owners and
qualifies sparse-history defaults/cohorts/confidence inside delivery, without forecasting human decisions or giving
ranges execution authority. TASK-00320 gains TASK-00400 as its base measurement/forecast/query/UI supplier, resolving
only its mapping hold; actual later-phase composition still requires TASK-00316 and inherited guarantees. TASK-00319
retains its independent hold. No foundational measurement or forecast depends on that later consumer.

Durable usage and safe prediction basis join existing history/copy protections; transient observations keep their
separate retention category. No duplicate accounting, live channel, transcript ingestion or analytics/certification
platform is introduced. Ordinary real TASK evidence, owned tests, source qualification, per-browser-slice visual/
accessibility/failure evidence, guidance and full gate stay within delivery. Fixtures and historical receipts never
become fresh runtime evidence or claimed statistical accuracy.

This completed the measurement split, not runtime delivery. All five orders stayed unranked. Active TASK-00138,
terminal-first priority, independent host/prototype/source/provider/authority holds and Markdown authority remained
unchanged. No implementation, private read, measurement capture, account/provider/credential operation, forecast
runtime, cleanup, cutover or publication occurred. The subsequent catalog split follows.

### Approved trusted starter catalog decomposition

The maintainer approved [TICKET-00099's five slices](../tickets/00099-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00401–00405 in the same isolated planning worktree. TASK-00401 supplies protected catalog administration
using TASK-00078's early Workspace identity and TASK-00117's API boundary. Entries are not automatically verified
or selectable releases. TASK-00402 installs public immutable snapshots, owns the shared validated generation
manifest and supplies real early caller-transaction eligibility/pin/reference/hold participants. Source preparation
commands remain inert during installation, and generation rechecks exact bytes/targets/values independently.

TASK-00402 reuses TASK-00374's narrow qualified private-storage capability, not Workflow-specific Artifact authority.
Workspace catalog authorization and release retention stay with this owner; no fabricated Repository/Workflow/Agent
or second storage stack is introduced. It remains `needs-info` for fresh availability of one real managed stable
release `>=1.0.0` with a valid manifest, preferably `project-slim`. Actual installation stays in its delivery;
source publication remains an explicit source-maintainer action, not a proof-only release/certification TASK.
Historical inspection proves neither current absence nor availability. TICKET-00103 retains real create/register proof.

TASK-00403 adds private GitHub retrieval through the same immutable pipeline and remains `needs-info` for the exact
protected Workspace credential/access supplier. Release Manager and future human project publication are different
authorities. Ordinary reads never contact providers or authenticate. Private-source failure cannot block public
installation or independently permitted installed local use. Credentials never enter snapshots, projects or Agents.

TASK-00404 owns withdrawal and explicit conflict resolution preserving old bytes and actual attempt references.
It now gains TASK-00411's early admission/reference participant and becomes ready behind suppliers, resolving only
its allocation hold. TASK-00411 consumes TASK-00402's early fences; it does not depend on completed TASK-00404 or
full browser/creation acceptance. Actual participant composition and supplier prerequisites remain.
Withdrawal closes new admission without deleting active/failed references, replacing trusted bytes, upgrading
projects or compensating automatically. Unknown ownership/participant coverage preserves content and holds.

TASK-00405 independently associates exact existing managed/custom template revisions through TASK-00249/00299.
Their scoped-read/Triage holds remain; actual Workspace-only pre-registration composition is required, not a
fabricated Repository, copied template authority or mandatory completed editor. Recommendations and selected
revisions stay distinct and create no Agent, Skill activation or Permission. TICKET-00101 keeps browser selection/
confirmation and TICKET-00103 context/receipt. Owned tests, real qualification, guidance and full gate stay in delivery.

Continue `/skill:next` after the approved final sync split. All five
orders remain unranked; TASK-00401/00404/00405 are ready behind unfinished suppliers, not qualified live use. No existing
supplier gains a reverse creation dependency. Active TASK-00138, terminal-first priorities, independent holds and
Markdown Planning authority remain unchanged. No source/private-content/credential read, download, catalog mutation,
source publication, project generation, cleanup, cutover or publication occurred. Remaining map TASK decomposition,
actual runtime, full build, independent review and publication are incomplete.

### Approved deterministic local-generation decomposition

The maintainer approved [TICKET-00100's five slices](../tickets/00100-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00406–00410 in the same isolated planning worktree. TASK-00406 supplies protected read-only local feasibility
using TASK-00402's immutable inputs, TASK-00073's root authority and TASK-00368's enrolled facts. It neither reserves
paths nor launches anything. TASK-00407 materializes exact declared content with real destination ownership/fencing;
TASK-00408 creates and reconciles only the selected branch and one original initial commit.

TASK-00407 gains TASK-00412's early stage participants, with TASK-00411 immutable admission transitively, but
remains `needs-info` for precise protected local-operation support under existing execution owners. TASK-00409 adds optional preparation but
retains `needs-info` for actual contained process supervision, dependency egress and credential isolation. Root
configuration, Runner enrollment and TASK-00138 qualification do not supply these implementations. Do not fabricate
a Workflow/Agent, build a second provisioner or require completed Team/role/browser journeys as substitutes.

TASK-00410 executes separately admitted local cleanup after real ownership, reference and quiescence checks. It
now gains TASK-00413's early cleanup-authority/reference participant but remains `needs-info` for actual reference
composition and inherited local host/process holds. Each ordinary stage already reconciles its own effects; final cleanup cannot reverse-block them.
TICKET-00101 retains complete preview, consent, attempt transitions and controls. Its early participants may consume
TASK-00406/00402 first but must not depend on completed stage adapters or the final browser recovery journey.

TICKET-00102 consumes TASK-00408's exact initial subject separately from preparation dirty-state observations.
TICKET-00103 still owns actual create/register proof and context/receipt using TASK-00402's required managed stable
release. Source publication remains a separate maintainer action; no release or runtime qualification occurred here.
Explicitly skipped preparation needs no preparation network capability; public installed/local-only paths do not
require private-source or GitHub publication credentials. Cleanup is neither automatic compensation nor required
before ordinary completion. Existing release/profile/source/attempt-reference and host/prototype holds remain.

All five are unranked features with blank PRs; TASK-00406/00408 are ready behind unfinished suppliers, not live-ready.
Owned behavior tests, real PostgreSQL/Git/Runner/source integration, guidance and the full gate remain in delivery,
with tooling/infrastructure qualified directly and no generated-file/configuration tests or proof-only TASKs.
Continue `/skill:next` after the approved final sync split. Preserve
active TASK-00138, terminal-first priority and Markdown Planning authority. No source/private-path/credential read,
project generation, Git commit, process, cleanup, live mutation, cutover or publication occurred. Implementation,
full build, review and publication remain outstanding; latest planning receipts are in TICKET-00109.

### Approved durable creation-attempt decomposition

The maintainer approved [TICKET-00101's seven slices](../tickets/00101-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00411–00417 in the same isolated planning worktree. TASK-00411 owns complete server preview and immutable
admission/retained inputs. TASK-00412 provides original stage delivery/claims/results and TASK-00413 separate
cleanup consent/reference exclusion before completed effect/browser consumers. TASK-00414 composes actual stages;
TASK-00415 supplies browser creation, TASK-00416 original-attempt recovery and TASK-00417 final explicit cleanup.

TASK-00411/00414 remain `needs-info` for initial-mode and actual first-link/stage composition after TASK-00422/00425
allocation. TASK-00413 gains TASK-00423 and becomes ready behind suppliers; only reference allocation resolves.
TASK-00417 retains actual reference/local-host/process/provider holds after remote-cleanup
allocation below. TASK-00418/00420 retain protected-operation mapping gaps. Early preflight contracts cannot wait
for admitted attempts; stage/reference producers may consume TASK-00412 but not completed TASK-00414/00416/00417
or final receipt acceptance. No guessed IDs or whole-TICKET dependency substitutes for exact allocation.
TASK-00412/00415/00416 are ready behind unfinished suppliers; all seven orders are unranked and PRs blank.

TASK-00404 gains TASK-00411, resolving its sole allocation hold and becoming ready behind suppliers. TASK-00407
gains TASK-00412 but retains its protected local-operation hold. TASK-00410 gains TASK-00413 but retains actual
reference composition and inherited host/process holds. TASK-00402's real managed-release prerequisite,
TASK-00403's private-source gap, TASK-00409's preparation boundary and all independent profile/runtime holds remain.

Current intent-specific authority, complete expected state and real PostgreSQL participants fence all admissions,
references and original outcomes. External effects follow committed intent; unknown outcomes preserve resources.
Explicit retry reconciles originals; revised continuation retains old consent/history and effects; cancellation
closes future admission without claiming rollback. Cleanup needs separate exact consent and real ownership/reference/
quiescence evidence. TASK-00413 is the early binding/exclusion owner; TASK-00417 composes actual impact/effects.

Public installed/local-only paths need no private-source/publication credentials. Explicit skipped preparation needs
no preparation-network support. Normal completion never requires cleanup or optional support, but still needs
actual Git/registration/database context. TICKET-00103 retains completion receipt/Dashboard and integrated real
create/register evidence. No fabricated Repository, Workflow or Agent supplies missing capability or grants.

Each delivery includes owned tests, real integration/direct infrastructure qualification, useful guidance and full
gate. Browser delivery includes fresh accessibility, keyboard/focus/announcements, responsive light/dark and honest
stale/denied/unavailable/recovery evidence, with volatile drafts and no reconnect/refresh mutation replay.
Continue `/skill:next` after the approved final sync split. Terminal-first priority,
active TASK-00138 and Markdown Planning authority remain unchanged. No runtime/source/account inspection,
confirmation, execution, cleanup, cutover or publication occurred; implementation, full build and review remain
outstanding. Latest planning verification is in TICKET-00109.

### Approved initial project-publication decomposition

The maintainer approved [TICKET-00102's four slices](../tickets/00102-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00418–00421 in the same isolated planning worktree. TASK-00418 supplies early read-only publication
feasibility; TASK-00419 creates/reconciles the exact confirmed remote; TASK-00420 configures the intended remote,
pushes only the initial commit and verifies the selected default; TASK-00421 inspects and separately cleans owned
remote resources. Remote setup/push/default remain one outcome with independently reconciled effects, not a new
attempt engine or standalone recovery/proof TASK.

TASK-00411 gains TASK-00418, TASK-00414 gains TASK-00420 and TASK-00417 gains TASK-00421. Only those allocation gaps
resolve. TASK-00418/00420 stay `needs-info` for protected `gh` invocation/current identity and Git/SSH invocation;
TASK-00419/00421 are ready behind unfinished/held suppliers, not runtime-qualified. All orders are unranked and PRs
blank. TICKET-00103 now maps TASK-00422–00426's early registration/context preflight/reference/stage and final receipt/
Dashboard/integrated proof, retaining the initial-mode supplier gap. All independent catalog-release/private-source/profile/local-host/process holds remain.

Preflight precedes completed admission. Actual effects consume TASK-00412 and TASK-00408's original subject; current
stage admission respects selected preparation disposition. Remote cleanup consumes early remote creation and
TASK-00413, never successful publication or final browser/receipt acceptance. Existing terminal/Runner/source-host
owners gain no reverse dependency; source-host publication here is not managed Release Manager or Agent authority.

Qualify current protected identities and actual provider-native attribution/enforcement rather than infer ownership
from names, CLI exit, ambiguous absence or old consent. State/audit/original intent commit before external effects;
unknown outcomes hold before retry. Initial publication leaves preparation changes untouched, never force-pushes
or adopts unrelated state. Cancellation keeps completed effects; cleanup needs separate exact consent and durable
reference/admission exclusion. Provider-native limits remain explicit; PostgreSQL alone cannot fence remote changes.

Local-only requires no publication identity/connectivity. TASK-00403's private-source connection gap is separate;
no enrollment, account repair, credential collection or generated-code/Agent secret access is introduced. Delivery
includes owned tests, actual applicable PostgreSQL/Git/GitHub evidence, versioned operator guidance and full gate;
tools/configuration are qualified directly, and fixtures do not prove live support. Read/create/push/delete effects
require separately scoped finite authority.

Continue `/skill:next` after the approved final sync split. Preserve active TASK-00138,
terminal-first order and Markdown Planning authority. No live provider/credential/source operation, confirmation,
cleanup, cutover, implementation or publication occurred. Latest planning verification is in TICKET-00109; full build,
independent review and delivery remain outstanding.

### Approved creation registration and receipt decomposition

The maintainer approved [TICKET-00103's five slices](../tickets/00103-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00422–00426 in the same isolated planning worktree. TASK-00422 supplies early read-only finalization
feasibility and first-link intent binding; TASK-00423 actual shared reference/exclusion participants; TASK-00424
exact registration/link/designation; TASK-00425 initial configuration/context; TASK-00426 durable receipt and
Dashboard handoff with real integrated acceptance. No standalone proof TASK or second resolver/attempt engine.

TASK-00411 gains TASK-00422, TASK-00413 gains TASK-00423 and TASK-00414 gains TASK-00425, with TASK-00424 transitively.
Only named allocation gaps resolve. TASK-00413 becomes ready behind unfinished suppliers; TASK-00411/00414 retain
initial-mode and actual first-link/stage holds, TASK-00410/00417 actual reference/host/process/provider holds.
TASK-00422/00425 remain `needs-info` for exact new-Repository database-authority initialization under TICKET-00028.
Migration/post-cutover TASK-00102/00103 do not implement it; no fake cutover, configuration mode bypass or fabricated
identity/snapshot can fill the gap. TASK-00423/00424/00426 are ready behind held suppliers, not runtime-qualified.
All new orders are unranked and PRs blank; terminal-first priority and existing owners remain unchanged.

Preflight precedes admission; original intent must bind later actual first-link evidence without expiry bypass or
repeated unchanged-stage prompts. Real reference producers share durable exclusion across the external cleanup
window without depending on completed deletion/browser consumers. Stage owners reconcile their original effects,
preserving partial resources/history and refusing duplicate/adopted outcomes. Receipt reads use retained facts,
not live provider/private-path inspection; later observations/corrections cannot rewrite original provenance.

Final receipt delivery includes actual installed managed stable-release >=1.0.0 local-only/private-publication
journeys, fresh Git/preparation facts, registration, initial database context and recovery. Source release remains
separate maintainer work; historical receipts, fixtures and enrollment reports do not qualify it. Local-only needs
no publication credentials, explicit preparation skip no preparation network, and optional support/cleanup never
gate normal completion. Browser acceptance includes fresh accessible responsive light/dark/reduced-motion evidence.
Dashboard navigation starts no Planning, conversation, Workflow, Agent or extra publication; source Markdown is
neither imported nor removed and this project's Planning authority remains unchanged.

Continue `/skill:next` after the approved final sync split. Latest planning verification
is in TICKET-00109. No source/private/account/credential inspection, runtime effect, marker write, provider call,
registration/context mutation, cleanup or cutover occurred. Implementation, full build, independent review and
publication remain outstanding; checkout and finite effect authority stay separate.

### Approved creation-support decomposition

The maintainer approved [TICKET-00104's three slices](../tickets/00104-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00427–00429 in the same planning worktree. TASK-00427 extends the single versioned guide/failure view with
model-free playbooks and safe export. TASK-00428 delivers actual protected OpenRouter assistance and original-result
recovery through existing Agent/session/retention owners. TASK-00429 adds explicit browser disclosure review,
advisory presentation and safe navigation to normal controls, never an Apply bypass.

TASK-00428 remains `needs-info`: failed creation may lack the Repository/checkout/context required by existing
TASK-00250/00339/00348 contracts. Map the exact attempt/Workspace-scoped actual Agent/snapshot/assignment supplier
under existing owners; no fabricated context, borrowed authority, unteamed/privileged support role or second engine.
TASK-00427/00429 are ready behind unfinished/held suppliers, not runtime-qualified. All orders are unranked and PRs
blank; existing scope/host/provider/current-authority holds and terminal-first priority remain unchanged.

Only expressly authorized bounded evidence and the exact playbook reach the provider, with independent disclosure,
finite per-turn/conversation limits, original provenance and honest usage/uncertainty. No automatic upload, fallback,
paid regeneration, arbitrary tools/files/network or advice-driven effect. Status reads are provider-free; browser
reconnect resolves the original result. Normal current-state controls retain all confirmation and effect authority.
Provider/configuration failure, denied disclosure and exhausted limits disable help only, not permitted deterministic
creation/recovery or model-free guide/export. No early supplier gains a reverse support dependency.

Owned tests, actual authorized provider/participant evidence, fresh accessible responsive browser proof and guidance
stay within delivery. Qualify tools/prose directly, not through product-suite tooling tests or a proof-only TASK.
EPIC-00009's six accepted TICKETs are now split; this is not delivery, runtime qualification or resolved supplier gaps.
Continue `/skill:next` after the approved final sync split. Latest planning verification
is in TICKET-00109; implementation, full build, independent review and publication remain outstanding. No Agent,
credential access, provider request, diagnostic upload, project effect or Planning authority change occurred.

### Approved effective-instruction inspection decomposition

The maintainer approved [TICKET-00105's two slices](../tickets/00105-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00430/00431 in the same planning worktree. TASK-00430 delivers bounded recognized-root query/API inspection
and qualified effective labels through the existing context/discovery owner. TASK-00431 adds the single-file viewer
in the existing repository Dashboard with truthful accessible states. Both are unranked features with blank PRs.

TASK-00430 remains `needs-info` for the precise protected human read/current-custody supplier under existing execution/
Runner owners. Context discovery, enrollment and Workflow resource contracts do not alone qualify this route. No
supplier ID, Workflow/Agent, alternate checkout, second custody registry or precedence engine is invented.
TASK-00431 is ready behind unfinished/held suppliers; existing holds and terminal-first priority remain unchanged.

Qualify actual Pi/Git/Runner behavior, coherent bounded bytes/digest, current scope and physical path-race protection.
Ignored/untracked files may be effective; unknown discovery/sync freshness stays unknown. No recursive or parent/user
body access, arbitrary files, effect reservation, relink, fetch, write, model disclosure or session reload follows
from inspection. Browser state is inert, no-store and volatile with current-authority/late-response fences.
Owned tests, direct runtime/tool qualification, guidance, full build and fresh accessible responsive browser evidence
belong inside delivery; no proof-only TASK or product tests of prose, generated lists or infrastructure.

TICKET-00106/00107 consume these observations/viewer; TICKET-00108/00109 retain publication/sync and final real
instruction-change acceptance. No completed consumer reverse-blocks early reads or terminal owners. Continue
`/skill:next` after the approved final sync split. Latest planning verification is in
TICKET-00109; implementation, full build, independent review and publication remain outstanding. No actual private
instruction read, runtime discovery, credential, checkout operation, provider call or Planning cutover occurred.

### Approved instruction Save/Delete decomposition

The maintainer approved [TICKET-00106's two slices](../tickets/00106-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00432/00433: exact Save/Delete/reconciliation with existing drift integration, then the Dashboard form,
complete diffs, dirty-leave/conflict and recovery. TASK-00432 retains the protected human mutation/shared-custody/
physical-guarantee hold; qualified reads and database fences alone do not qualify conditional filesystem effects.
TASK-00433 is ready behind dependencies, not runtime-qualified. Both are unranked features with blank PRs.

Reuse TASK-00430/00431 inspection and TASK-00341/00084 currentness ownership. No fabricated Workflow, second custody
registry, automatic merge/replay, Git publication/sync, Pi reload or Agent continuation. Direct edits do not wait
for optional assistance. Future publication/sync compose early contracts without reverse-blocking existing owners.
Latest planning verification is in [TICKET-00109](../tickets/00109-TICKET.md#planning-verification). Continue
`/skill:next` after the approved final sync split. Terminal priority, independent
holds and Markdown authority remain unchanged; implementation, full build, review and publication remain separate.

### Approved bounded instruction-assistance decomposition

The maintainer approved [TICKET-00107's two slices](../tickets/00107-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00434/00435: selected-file request/clarification/durable proposal/recovery, then the existing Dashboard form's
explicit adoption and separate human Save. Both are unranked features ready behind dependencies with blank PRs.
Existing protected read/custody, attributed Skill/loading, actual Agent and host/provider holds remain unresolved;
no pre-registration scope, second conversation engine or implicit instruction-writer authority is introduced.

Initial qualification uses TASK-00346 OpenRouter, not a Codex dependency or fallback. Exact bounded disclosure,
current authority, original identity, finite budgets and typed complete diff/provenance are required. Late/stale
proposals cannot overwrite newer drafts or trigger automatic merge, paid replay or Agent submission. Advice without
Update and direct editing without AI remain independent. No supplier or terminal owner gains a reverse blocker.
Latest planning verification is in [TICKET-00109](../tickets/00109-TICKET.md#planning-verification). Continue
`/skill:next` after the approved final sync split. Full build, implementation, actual
provider/browser qualification, independent review and publication remain separate and outstanding.

### Approved instruction-publication decomposition

The maintainer approved [TICKET-00108's five slices](../tickets/00108-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00436–00440: exact preview/admission, selected-file commit, protected push/one PR, safe default return and
the existing Dashboard confirmation/recovery/handoff. All are unranked features with blank PRs. TASK-00436–00438
retain protected human admission/identity, local Git/physical exclusion and authenticated Git/PR mapping holds.
TASK-00439/00440 are ready behind suppliers, not qualified. No invented Workflow/Agent, second custody registry or
borrowed Release Manager grant; related creation/managed publication journeys are not complete-feature blockers.

Consume early Save/result/currentness/form owners without reverse dependencies. Separate intent precedes each
external effect and actual original reconciliation follows; no unrelated staging, force-add, destructive repair,
duplicate commit/PR, blind push replay or automatic compensation. Verified PR and safe return are separate facts;
blocked return retains ownership and unrelated work. Actual effective changes use existing drift guards without
history rewrite or Pi reload. Browser publication acts on saved state, not unsent drafts, and needs no AI.

Latest planning verification is in [TICKET-00109](../tickets/00109-TICKET.md#planning-verification). Continue
`/skill:next` after its approved split; actual sync and final instruction-change acceptance with separately
human-controlled merge remain delivery obligations, not effects authorized by decomposition. Terminal priorities, independent holds and Markdown authority remain unchanged.
Implementation, full build, actual Git/Runner/GitHub/browser qualification, independent review and publication remain
outstanding; no private instruction, credential, provider, Git, PR, merge, cleanup or sync effect occurred.

### Approved designated-checkout synchronization decomposition

The maintainer approved [TICKET-00109's four slices](../tickets/00109-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00441–00444: retained status/explicit bounded checks; exact clean default fast-forward and original recovery;
revisioned currently authorized scheduling; and existing Dashboard controls with final real instruction-change
acceptance. All are unranked features with blank PRs. TASK-00441–00443 retain protected source-read/fetch/custody,
conditional Git/physical-exclusion and actual scheduled actor/delegation/revocation/runtime mappings. TASK-00444 is
ready behind suppliers, not qualified. No missing supplier ID, new scheduler platform/registry, implicit administrator
or fabricated Workflow/Agent is introduced.

Reuse early identity/configuration, inspection/Save/result/currentness and actual publication/Workflow resource
participants without reverse-blocking completed consumers or terminal delivery. Ordinary reads never fetch; a check
never implicitly updates the worktree. Exact manual consent and current scheduled authority remain distinct, with
default-enabled checks granting no permanent access. All predicates repeat at the real effect boundary; unsupported
physical guarantees deny. Retain original partial/unknown effects and custody; no blind retry, false release,
destructive repair, branch deletion, automatic merge, draft replacement, Pi reload or Planning cutover.

TASK-00444 delivers real browser controls and the actual Fight Agent OS instruction journey through inspection,
qualified assistance, expected-digest Save, exact PR/human handoff, separately authorized normal human merge and
clean default sync. Missing real evidence remains incomplete; tests, accessible browser evidence, direct scheduler
qualification, guidance and full build stay inside delivery rather than a proof-only TASK.

This completes this original map scope's planning phases and EPIC-00010's five approved splits, not implementation,
qualification, acceptance or unrelated-map planning. Fresh planning checks are recorded in
[TICKET-00109](../tickets/00109-TICKET.md#planning-verification). Next: `/skill:next` for Board-based selection;
upstream TASK-00138 completion, terminal-first priorities, independent supplier holds and Markdown authority remain.
Full build, actual runtime/browser journey, independent review and publication remain outstanding. No private read,
credential/provider operation, Save, Git/fetch/update, schedule, PR, merge, cleanup or cutover occurred.

### Original completed handoff

The original vision scope was complete. Its first separately approved grill handoff is [EPIC-00005 — Deliver the registered Planning workspace](../epics/00005-EPIC.md): guided local operation, repository and checkout registration, context resolution, explicit Markdown-to-PostgreSQL Planning cutover, and a minimal authoritative browser Planning surface. The EPIC consumes rather than repeats settled Wayfinder decisions; no EPIC was created by this map itself.

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
- Writing new follow-on EPIC amendments, requirement TICKETs, implementation TASKs, ADRs or production schemas
  before their approved handoff; existing accepted implementation work remains independently authorized.
- Replacing, reinterpreting, or bulk importing the accepted EPIC-00002 through EPIC-00004 plans.
- Importing Factory workflows, plans, approvals, runtime code, or SQLite authority wholesale.
- Removing or archiving current Markdown planning automatically, even after a future database cutover.
- Agent approval or publication implying defect-free software, human PR approval, merge, release, deployment, or certification.
