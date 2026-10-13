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

Continue with `/skill:to-tasks TICKET-00085` to begin the remaining Harness TASK splits, then the remaining accepted
map TICKETs, including TICKET-00083 and TICKET-00086–00109. Each split still needs its own human approval. This is a
planning recommendation, not executable priority or full planning completion; preserve accepted TASKs, unmet
supplier holds and the Board's terminal-first order. Do not launch implementation or reopen closed decisions.

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
| [EPIC-00006](../epics/00006-EPIC.md) | Existing TICKET-00034/00035/00037–00045 retain team/branding/Jev; approved TICKET-00085–00092 complete generic Harness/browser requirements without replacing newer template/Agent owners |
| [EPIC-00007](../epics/00007-EPIC.md) | TICKET-00032 retains core execution; approved TICKET-00093–00098 complete Runner/evidence/research/history/browser/usage requirements |
| [EPIC-00008](../epics/00008-EPIC.md) | Accepted TICKET-00083 retains the entire managed review/QA/revision/acceptance/publication/reconciliation scope for TASK decomposition; completed local TICKET-00033/00084 do not satisfy it |
| [EPIC-00009](../epics/00009-EPIC.md) | Approved TICKET-00099–00104 cover deterministic creation, confirmed recovery, optional publication, registration and support |
| [EPIC-00010](../epics/00010-EPIC.md) | Approved TICKET-00105–00109 cover focused instruction inspection/editing, optional assistance, separate publication and safe synchronization |

All six original destinations now have accepted TICKET coverage. This does not complete their remaining TASK
splits, actual implementation or runtime qualification. Existing Jev/team/local TASKs keep their accepted outcomes;
other maps retain their own unfinished planning. Recommend TICKET-00085 first to continue the earlier Harness
primitives before final consumers, not because its ID confers execution priority. Continue through remaining
accepted map TICKETs, including TICKET-00083 and TICKET-00086–00109, stopping for each required split approval.
No new blocker, priority, credential, runtime or publication grant is created by this phase handoff.

Next: `/skill:to-tasks TICKET-00085`.

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
