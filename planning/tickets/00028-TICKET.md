---
id: TICKET-00028
epic: EPIC-00005
title: Migrate and cut over Markdown Planning authority
status: ready-for-agent
---

# Migrate and cut over Markdown Planning authority

## Problem statement

Fight Agent OS cannot adopt PostgreSQL Planning through a best-effort import, partial switch, inferred meaning, or
dual writers. The current Markdown contains real maps, decisions, research, prototypes, EPICs, TICKETs, TASKs,
standalone records, dependencies, ordering, evidence and history whose identity and semantics must remain
traceable through an explicitly approved authority change.

## Solution and boundaries

Deliver a deterministic complete-baseline importer and guarded authority-switch operation under the explicit modes
from TICKET-00025. Inventory and digest one immutable source snapshot, rehearse import into a clean target, report
every mapping/warning/rejection, and require a human-approved clean rehearsal. The final operation revalidates the
same source, imports atomically, verifies the complete result, and changes repository Planning authority in one
transaction or leaves it unchanged.

After cutover, database streams are sole write authority. Freeze source Markdown and render current Markdown on
demand from typed TICKET-00026/00027 views with revision, authority and template provenance. Retire frozen source
only through a later separately approved, verified and human-merged change.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inventory migration source | N/A — read-only migration preparation | `InspectMarkdownPlanningSource` | N/A — inspection creates no Planning event | A bounded immutable manifest records every authoritative file, digest, record/reference, relationship and generated/source classification |
| Rehearse complete import | `RehearseMarkdownPlanningImport` against source manifest and empty target | Query mapping report, imported projections, invariant failures and canonical queue comparison | Rehearsal records are operational evidence, not authoritative Planning events | A disposable clean target contains the proposed streams/projections and a complete deterministic report without changing repository authority |
| Approve a rehearsal | `ApprovePlanningMigrationRehearsal` | Revalidate report completeness, source digest, target cleanliness and unresolved warnings | `PlanningMigrationRehearsalApproved` in migration authority, not imported aggregate history | Human approval binds one exact source/report revision and expires on any material change |
| Cut over authority | `CutOverPlanningAuthority` | Revalidate source, approval, target, imported identities, invariants, projections and queue | `PlanningAuthorityChanged` plus imported baseline stream events carrying source provenance | All baseline streams, constraints and mode change commit atomically, or no authoritative effect remains |
| Render current Markdown | N/A — deterministic representation query | Render typed Planning views, boards and indexes with authority/revision/template metadata | N/A — rendering is read-only | Users can inspect/export current Markdown without creating another writer or overwriting frozen source |
| Diagnose or recover failure | N/A unless a new rehearsal is explicitly requested | Query failed stage, safe mismatch, retained evidence and unchanged authority | N/A — failed attempts do not imply success | Operator receives exact safe recovery steps; retry begins from a newly validated clean attempt when required |

## Validation and permissions

Preserve existing human references while minting internal COMB identities. Import complete self-contained baseline
events with source path/digest and migration actor/time provenance rather than pretending historical Markdown
edits were native Domain events. Preserve map ownership/frontier/handoff, decision resolution, research/prototype
links and verdicts, hierarchy, standalone kind, lifecycle, archive, criteria/evidence, dependencies, Roadmap/TASK
order, PR links and durable narrative needed for current meaning.

Reject unknown/duplicate references, broken parents/links, dependency cycles, unsupported status, conflicting
slugs/order, missing required resolution/handoff, generated-view disagreement, mutable source, non-empty target,
partial import, projection mismatch and canonical-queue mismatch. Do not infer missing statuses, repair content,
drop unsupported records, allocate replacements silently, or update source during import.

Inspection/rehearsal requires migration-preview authority. Approval and final cutover require distinct guarded
repository Planning-authority Permission and explicit confirmation of source manifest, report, repository and
current mode. A migration process cannot grant itself authority. Rendered Markdown is permission-filtered and
contains no secrets. Rollback before cutover discards only proven rehearsal-owned data; after cutover there is no
automatic fallback to Markdown writing.

## Acceptance and evidence

- Two clean rehearsals from the same immutable source produce equivalent identity/reference mappings, aggregate
  streams, projections, hierarchy, dependencies, ordering and canonical queue results.
- The report accounts for every source and generated record as imported, intentionally represented, warned or
  rejected; no omission is implicit.
- Deliberately stale approval, changed source, non-empty target, malformed record, broken relationship, cycle and
  projection/queue mismatch cases fail before authority changes and retain useful diagnostics.
- One human-confirmed final run imports the real Fight Agent OS Planning baseline and changes authority atomically;
  direct inspection proves no partial stream/constraint/mode result on induced transactional failure.
- Markdown writes are refused after cutover while deterministic on-demand renders reflect database state and label
  revision, authority and template version.
- Original files remain frozen and available; this TICKET neither deletes nor archives them.
- Test importer/cutover behavior and PostgreSQL atomicity where owned; review migration reports and perform the real
  rehearsal/cutover manually rather than creating ceremony browser or tooling tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00098](../tasks/00098-TASK.md) | Inventory immutable Markdown Planning source | ready-for-agent |
| [TASK-00099](../tasks/00099-TASK.md) | Map complete Markdown baseline events | ready-for-agent |
| [TASK-00100](../tasks/00100-TASK.md) | Rehearse and compare complete PostgreSQL import | ready-for-agent |
| [TASK-00101](../tasks/00101-TASK.md) | Approve an exact Planning migration rehearsal | ready-for-agent |
| [TASK-00102](../tasks/00102-TASK.md) | Cut over Planning authority atomically | ready-for-agent |
| [TASK-00103](../tasks/00103-TASK.md) | Enforce database-only Planning consumers | ready-for-agent |
| [TASK-00104](../tasks/00104-TASK.md) | Migrate Fight Agent OS Planning authority | ready-for-human |
<!-- /planning:children -->

## Decisions and progress

Implements [WF-011 — Prove the Markdown migration and authority switch](../wayfinder/tickets/WF-011-prove-markdown-migration-and-authority-switch.md).
It depends on TICKET-00024 Repository identity, TICKET-00025 authority mode, TICKET-00026 complete Planning
semantics and TICKET-00027 canonical ordering/eligibility. The accepted cutover is required for final
TICKET-00029 operation and every later coordinated Workflow.
