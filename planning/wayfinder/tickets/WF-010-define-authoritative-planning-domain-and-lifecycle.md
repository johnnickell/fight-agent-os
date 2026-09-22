# Define the authoritative planning domain and lifecycle

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-008](WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-009](WF-009-define-repository-context-and-policy-precedence.md)

## Question

What repository-scoped PostgreSQL domain model and application invariants can become the sole authority for the complete Wayfinder and EPIC → TICKET → TASK planning system?

## Must decide

- Aggregate and identity boundaries for maps, WF decisions, research references, EPICs, TICKETs, TASKs, acceptance criteria, evidence, dependencies, priorities, archive state, and links to execution artifacts.
- How parent/child and dependency relationships preserve repository terminology and keep WF decision tickets distinct from requirement TICKETs.
- Status and lifecycle transitions, including who may make them, validation errors, archive/restore, and the distinct meanings of TASK completion, review acceptance, PR publication, and merge.
- Revision, authorship, decision-history, optimistic-concurrency or merge behavior, and reconciliation of simultaneous browser and agent edits.
- Workspace/repository isolation and permission checks at every command and query.
- A single application query for “next executable TASK,” including priority, unresolved decisions, dependencies, claims, prerequisite availability, and an explanation of eligibility or exclusion.
- The application operations that both browser actions and agent/MCP tools must call, rather than duplicating rules or writing SQL.

## Resolution boundary

This decision establishes the conceptual model, command/query boundaries, and invariants. It must reference rather than replace current planning conventions. Exact Doctrine mappings, migrations, endpoint shapes, and import code remain downstream. Migration and authority switching belong to WF-011; browser conversation behavior belongs to WF-012.

## Preferences required

John must settle lifecycle choices where the current Markdown model is intentionally compact, especially edit conflict behavior and who may archive active or non-terminal records. The recommendation should preserve append-only revision evidence, use explicit commands for lifecycle transitions, and keep generated projections non-authoritative.

## Resolution

Write this only when the decision is closed. Link the accepted domain model or an ADR/EPIC-planning brief when one later exists.
