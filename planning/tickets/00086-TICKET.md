---
id: TICKET-00086
epic: EPIC-00006
title: Manage versioned Harness resources
status: ready-for-agent
---

# Manage versioned Harness resources

## Problem statement

Planning Agents and role/template consumers need exact inspectable resources, not mutable copied instructions or
source files masquerading as runtime authority. Managed updates must preserve custom work and historical evidence.

## Solution and boundaries

Within Harness, provide type-specific Skills, themes, prompts and personas with stable logical identities,
immutable revision bytes, current selections, provenance and Workspace or registered-Repository scope. Do not
force different resource invariants into a generic aggregate. PostgreSQL owns runtime selections and revisions;
use current state plus immutable history, not event sourcing by default. Reserve packaged `agent-os-*` names for
bootstrap resources and keep general development Skills database-authoritative for trusted MCP delivery.

Reconcile reviewed managed definitions idempotently: normalize a complete bundle/manifest, resolve its stable
managed key, no-op on unchanged digest, otherwise append and atomically select a revision. Preserve custom
resources; absence from source does not delete or retire anything. Authorized custom creation/revision validates
scope, type, references and effective alias collisions. Retirement and rollback are explicit expected-state
operations, retain referenced historical bytes and never rewrite history. Retain bytes until separately approved
purge policy; no purge implementation or automatic source deletion is included.

Include the reusable Writing for Agents Skill adapted with attribution from Matt Pocock's MIT-licensed source,
using the existing local skill as an input to reviewed managed distribution. Preserve complete static supporting
resources and licensing. This is trusted distribution for real Planning and later consumers, not a general browser
Skill-authoring or repository-instruction-editing product.

Supply exact resource/reference inspection for [TICKET-00034](00034-TICKET.md)'s role resolver and existing
[TICKET-00075](00075-TICKET.md)/[TICKET-00076](00076-TICKET.md) template consumers. Templates remain their distinct
type-specific owner: seed once and then retain database authority under EPIC-00017. Do not apply repeating managed
Skill reconciliation to Agent templates or automatically propagate requested Permissions to actual Agents.

Exclude template seeding/publication/ancestor review, Agent provisioning, browser instruction editing, execution
policy, a second registry and general-purpose resource-editor UI. MCP transport/loading belongs to
[TICKET-00087](00087-TICKET.md); user preferences and snapshots belong to their respective owners.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Reconcile reviewed managed resources | Reconcile complete managed bundle with exact source identity | Current managed key, digest, references and scope | Committed resource revision/selection facts; none on a no-op | Atomic immutable revision/current selection, without custom overwrite |
| Create or revise a custom resource | Authorized type-specific create/revise with expected state | Effective catalog, collision choices, valid references and history | Owned committed creation/revision facts | New scoped resource or revision, never a tool/Permission grant |
| Retire or roll back | Explicit retirement or selection of retained revision | Current state and retained referenced bytes | Committed retirement/selection facts | Future selection changes; pinned sessions/history remain intact |
| Inspect exact resources | N/A — read-only | Authorized type/revision/content/manifest/provenance/reference views | N/A — reads do not mutate resource authority | Exact data for approved consumers; no provider disclosure implied |

## Validation and permissions

Require current intent-specific management authority and scope for writes and current visibility for every read.
Reject malformed type content/frontmatter, incomplete manifests, traversal/escaped paths, oversized bundles,
reserved-name misuse, missing or cross-scope references, unresolved collisions and stale expected selections.
Skills are static; no dynamic expansion or executable policy may grant authority. Skill `allowed-tools` is a
non-authoritative request, not a Permission source.

Use real PostgreSQL uniqueness/reference/current-selection protection, atomic required audit and rollback under
ADR 0002. Model resource-reference contracts precisely enough for later publication owners to hold their required
fences; read-only resolution alone is not proof of atomic template publication. Source reconciliation, custom
editing, retirement and rollback must preserve immutable bytes already referenced by snapshots or Agents.
Keep secrets and unauthorized resource bodies out of ordinary logs/events. Provider disclosure remains separate.

## Acceptance and evidence

- Managed replay is a no-op; changed managed content appends/selects exactly one revision without overwriting custom
  work. Competing edits and partial failure preserve a complete authoritative state.
- Scope, type, reference, manifest and collision violations fail visibly; exact historical content remains readable
  to authorized consumers after retirement/rollback. Active sessions do not silently move.
- Real Planning can select the attributed managed writing Skill through the trusted loader; source copies alone
  do not establish managed delivery. Consumer-owned template behavior is not reimplemented here.
- Owned resource/PostgreSQL behavior tests and direct import/licensing/migration checks plus `./bin/build` pass;
  do not add product tests of bundle file layout, generated output, migration text or planning tools.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of the remaining eight-TICKET EPIC-00006 decomposition. Applies
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md), the template-only amendment in
[EPIC-00017](../epics/00017-EPIC.md), [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Uses registered identity/scope from [TICKET-00024](00024-TICKET.md). Resource/reference primitives supply
TASK-00249, TASK-00299 and the template editor without depending on their completed integration, final Agent
journeys or database Planning cutover. TICKET-00087 consumes publication manifests and provides host collision
observations; define that contract without reversing the resource-storage dependency. Exact TASK mappings and
qualification remain future work; existing `needs-info` holds are not cleared by requirement allocation.
