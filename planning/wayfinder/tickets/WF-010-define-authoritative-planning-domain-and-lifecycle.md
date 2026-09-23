# Define the authoritative planning domain and lifecycle

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-008](WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](WF-009-define-repository-context-and-policy-precedence.md)

## Question

What repository-scoped PostgreSQL domain model and application invariants can become the sole authority for the complete Wayfinder and EPIC → TICKET → TASK planning system?

## Must decide

- Aggregate and identity boundaries for maps, WF decisions, research references, EPICs, TICKETs, TASKs, acceptance criteria, evidence, dependencies, priorities, archive state, and links to execution artifacts.
- How parent/child and dependency relationships preserve repository terminology and keep WF decision tickets distinct from requirement TICKETs.
- Status and lifecycle transitions, including who may make them, validation errors, archive/restore, and the distinct meanings of TASK completion, review acceptance, PR publication, and merge.
- Revision, authorship, decision-history, optimistic-concurrency or merge behavior, and reconciliation of simultaneous browser and agent edits.
- Atomic, idempotent identity and human-readable sequence allocation under competing browser, agent, checkout, and worktree requests so independently created records cannot collide.
- Workspace/repository isolation and permission checks at every command and query.
- A single application query for “next executable TASK,” including priority, unresolved decisions, dependencies, claims, prerequisite availability, and an explanation of eligibility or exclusion.
- The application operations that both browser actions and agent/MCP tools must call, rather than duplicating rules or writing SQL.

## Resolution boundary

This decision establishes the conceptual model, command/query boundaries, and invariants. It must reference rather than replace current planning conventions. Exact Doctrine mappings, migrations, endpoint shapes, and import code remain downstream. Migration and authority switching belong to WF-011; browser conversation behavior belongs to WF-012.

## Preferences required

John must settle lifecycle choices where the current Markdown model is intentionally compact, especially edit conflict behavior and who may archive active or non-terminal records. The recommendation should preserve append-only revision evidence, use explicit commands for lifecycle transitions, and keep generated projections non-authoritative.

## Resolution

### Aggregates and identity

The Planning bounded context owns event-sourced aggregate roots for `WayfinderMap`, `WayfinderTicket`, `Epic`, `Ticket`, and `Task`. A `WayfinderTicket` owns ordered accepted `DecisionPoint` children and a synthesized resolution; browser conversation history remains separate under WF-012. Planning also owns event-sourced supporting aggregates for `ResearchNote` and `Prototype`. They are independently versioned artifacts, not work items and not members of the EPIC → TICKET → TASK hierarchy.

Acceptance criteria, evidence references, use cases, and similar subordinate concepts begin as stable-ID children of their owning aggregate. Research notes may be linked from multiple planning roots. Prototypes record their question or hypothesis, type, assumptions, disposable scope, run instructions, evidence, review findings, cleanup ownership, and accepted, rejected, or inconclusive verdict. Accepting a prototype promotes findings only, never prototype code.

Every aggregate root has a typed Fight Common `UniqueId` generated through `Uuid::comb()`. WF, EPIC, TICKET, and TASK records also have immutable human references allocated atomically per repository and artifact kind. References are never reused, including after archive or an abandoned create attempt; idempotent create commands return their original identity and reference on retry. Existing Markdown references survive migration while WF-011 mints internal IDs. Maps use internal IDs plus mutable repository-unique slugs rather than a new displayed MAP sequence.

Repository ownership and human references never change. A WF ticket has one owning map, a requirement TICKET one EPIC, and an implementation TASK zero or one TICKET according to the existing standalone bug/chore rules. Same-repository reparenting is an explicit, revision-checked event with reason, hierarchy validation, and impact preview. Terminal records must be reopened first, and active execution or delivery links require human confirmation. A cross-repository move creates a new record plus explicit supersession/link evidence rather than rewriting identity or history.

### Event authority and consistency

Each aggregate has its own authoritative event stream. Commands append against an expected stream version. Idempotent retry of the same command returns its original result; a different stale command is rejected with intervening events and current state so the human or agent can revise and resubmit. Do not use last-write-wins or silent automatic text merging. Snapshots may optimize aggregate loading but never replace stream authority.

Use semantic past-tense lifecycle, relationship, and section-level events such as `TaskCreated`, `TaskMarkedReadyForAgent`, `TaskCompleted`, `DependencyAdded`, `EpicDestinationRevised`, and `DecisionPointAccepted`. Content revisions carry the complete normalized value of the changed section or child, not keystroke, Markdown diff, or JSON Patch events. Do not collapse behavior into a generic `PlanningDocumentUpdated` event. Generated Markdown, boards, indexes, search documents, and browser views are projections.

Aggregate-local versions guard one stream. Transactionally current, rebuildable constraint projections and allocators guard cross-aggregate invariants such as reference uniqueness, parent validity, active/archive constraints, and dependency cycles. Append the owning event and update command-critical constraints in the same PostgreSQL transaction. Optional search and display projections may update asynchronously. Do not add a repository-global planning version that makes unrelated edits conflict.

Event envelopes retain event and aggregate identity, stream version, timestamp, authenticated actor, command/causation identity, and planning-session/correlation identity when present. Content-bearing events reference versioned payloads and digests. Normal corrections append; a narrowly authorized emergency-redaction operation may irreversibly remove a prohibited payload while retaining event identity, actor, time, reason, digest, and an explicit redacted replay placeholder. Redaction scope includes projections, search indexes, exports, and backup-retention handling.

### Content and relationships

Use a hybrid typed content model. Titles, kinds, parents, statuses, priorities, dependencies, criteria, use cases, commands, queries, events, expected effects, permissions, evidence links, and delivery links are typed fields or stable-ID children. Destinations, problem statements, boundaries, rationale, implementation notes, progress, and completion summaries remain Markdown-capable narrative sections. Migration preserves unrecognized legacy sections as labeled supplemental content rather than discarding them.

TICKETs and TASKs own ordered acceptance criteria with stable child IDs; EPICs may own similarly structured outcome criteria. Semantic events add, revise, retire, satisfy, or waive criteria. Satisfaction records actor, time, rationale, and immutable commit, review, verification, artifact, URL, or execution evidence references. Waiver requires human authority and a reason. Corrections supersede evidence rather than rewriting it, and terminal records must be reopened before criteria change.

Dependencies are typed. Wayfinder tickets may depend on Wayfinder tickets. TASKs may depend on TASKs across registered repositories in the same workspace. Parent relationships remain same-repository; cross-workspace and arbitrary cross-type blocking relationships are prohibited. Constraint checks prevent cycles across the complete workspace dependency graph.

Each Git branch carries one lowercase primary planning reference. Implementation branches use forms such as `feature/task-00069-description`; planning branches use forms such as `planning/wf-010-description`, with EPIC or TICKET references where those records own the operation. Additional relationships live in Planning, Execution, or PR metadata rather than the branch name. Parsed names provide discovery evidence only and never establish identity, ownership, or authority.

### Lifecycle, closeout, and archive

Preserve current machine status values. `WayfinderMap` uses `Active` and `Closed`; `WayfinderTicket` uses `Open` and `Closed`; EPIC, TICKET, and TASK use `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `in-progress`, `done`, and `wontfix`. UI labels are centralized presentation values such as **Needs Triage**, **Ready for Agent**, **In Progress**, **Done**, and **Won't Fix**. Blocking, closeout readiness, and other eligibility facts are derived, not stored statuses. Archive state is orthogonal to lifecycle status.

Clients invoke intent-specific commands such as marking ready, starting work, requesting information or human action, completing, declining, reopening, archiving, and restoring. There is no generic status setter. Aggregates validate allowed transitions and require relevant reasons, evidence, closeout material, or impact decisions before emitting events.

Completing children never completes a parent automatically. Planning derives **Ready for Closeout** when all children are terminal and keeps eligible parents visible through Dashboard and agent-facing queries. A dedicated closeout operation rereads parent intent, criteria, child outcomes, review evidence, exclusions, and unresolved work, then appends documentation revisions and completion atomically against the expected parent version. An authorized planning agent may close a fully evidenced TICKET whose children are all Done. A human confirms TICKET closeout when any child is Won't Fix, scope changed, evidence is incomplete, or judgment remains. A human always confirms EPIC completion. TASK landing may report closeout eligibility but never silently closes a parent.

Transitioning a TASK to Won't Fix requires a downstream-impact preflight. Show direct and transitive active dependents before mutation. A human classifies each direct dependent as safe to continue with the dependency explicitly removed and a rationale, requiring planning with the edge retained and the dependent moved to Needs Info, separately Won't Fix through its own impact review, or cancelled with no change. Never cascade statuses or remove dependencies automatically. Persist a recoverable impact plan if concurrent changes interrupt completion.

Archive is an explicit authorized operation, never a completion side effect. TASKs must be Done or Won't Fix. TICKETs and EPICs must be terminal with all children terminal. Wayfinder maps and tickets satisfy their existing closure invariants. Active and non-terminal records cannot be archived. Restore is explicit, preserves prior status and history, and may require restoring an archived parent first; reopening afterward is a separate reasoned command.

A Wayfinder map owns its destination, done condition, linked ticket references, dependency graph, notes, exclusions, and one recommended frontier. The system calculates eligible decisions, but frontier selection remains authored. A WF ticket cannot close without a synthesized resolution and no unresolved required decision. A map cannot close until all owned tickets close, remaining fog is resolved, excluded, or delegated, no frontier remains, and an approved handoff is linked.

### Actors, priority, and executable work

Every protected command and query uses an authenticated Fight Access Control user, agent, or explicit system actor. Agents never impersonate users. A browser delegated-agent planning session carries both the fixed initiating `UserId` and acting `AgentId` for its lifetime; resulting event envelopes preserve both. Direct terminal sessions may legitimately carry only `AgentId`, and Agent OS never infers `UserId` from an operating-system account. Concrete command IDs record immediate causation, while planning-session or workflow IDs correlate the wider operation. Cross-repository dependency changes require authority over both repositories. No operation requires multi-user approval, although one human confirmation remains mandatory where stated above.

TASK order is workspace-scoped, event-sourced, and optionally filtered by repository. Lower positive values come first and unranked TASKs follow; deterministic ties use repository and TASK references. Reordering is explicit. Eligibility is evaluated before priority, and active owned work takes precedence over unrelated ready work.

One application query owns next-executable-TASK behavior for the Dashboard and every agent surface. An executable TASK is unarchived and Ready for Agent; its TICKET and EPIC are active, unarchived, and implementation-permitting; every blocker is Done; actor/workspace/repository scope is valid; no conflicting active claim exists; current planning authority and context are valid; and later runner, checkout, and capability requirements are available. A Won't Fix blocker does not satisfy a prerequisite. Every exclusion returns a stable reason code and human-readable explanation. WF-015 and WF-016 add claim, coordinator, and runner facts without creating another eligibility algorithm.

### Delivery, rendering, and durable artifacts

TASK Done means implementation acceptance and required verification are complete. Independent review acceptance, branch and commit evidence, PR publication, human approval, merge, release, and deployment remain distinct linked records or observations. Planning may project provider and Execution links but never infers TASK status from GitHub. A TASK may retain multiple attempts and superseded branches or PRs without rewriting history.

React owns interactive Dashboard planning. Forms and agent tools map explicitly to shared semantic application commands and queries. Twig renders deterministic read-only Markdown representations for API, MCP, terminal, and export use from typed Planning views, with templates such as `epic.md.twig`, `ticket.md.twig`, and `task.md.twig` plus Wayfinder or board templates when required. Rendered output identifies reference, aggregate revision, authority mode, and template version. It is not accepted as aggregate replacement; full Markdown ingestion belongs only to explicit migration/import. Current copy-ready Markdown templates remain until WF-011 verifies authority cutover.

Accepted prototype files must be copied from disposable scratch into managed durable artifact storage before becoming authoritative evidence. Store stable storage references, digest, media type, size, and provenance rather than blindly embedding generated files in events. Retention holds last while linked planning remains active and at least until every related EPIC is archived. Map-only evidence remains through map closure, handoff, and resulting EPIC linkage or an explicit retention decision. Archive releases the mandatory hold but never deletes automatically. Later purge is explicit or policy-driven, leaves a tombstone and digest, and remains separate from cleanup of verified disposable `.runs/` copies.

This decision defines the Planning model and invariants, not event-store tables, Doctrine mappings, migration code, endpoint schemas, React components, Twig locations, projection workers, artifact backend, or retention durations. WF-011 owns Markdown migration and authority switching; WF-012 owns browser conversation/proposal behavior; WF-015 and WF-016 own coordinated claims and runner behavior; WF-017 independently decides Execution event authority. The umbrella map defers EPIC selection and sequencing to WF-019, so this resolution creates no EPIC or implementation record.
