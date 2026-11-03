---
id: TICKET-00131
epic: EPIC-00023
title: Establish one authoritative architecture contract
status: done
---

# Establish one authoritative architecture contract

## Problem statement

Current instructions and accepted architecture records disagree with the maintainer's approved direction in
[EPIC-00023](../epics/00023-EPIC.md). Builders and reviewers must not choose between old Application admission,
Repository vocabulary, replay requirements and the new aggregate-first, adapter-admitted CQRS contract.

## Solution and boundaries

- Reconcile governing standards, ADRs, ARCHITECTURE, applicable AGENTS/skill instructions and current architecture
  references. Keep one normative owner for each rule and link it from entry guidance rather than copying a large
  policy into every document. Use the instruction-writing guidance for agent-facing edits.
- Specify aggregate roots, IDs, repository interfaces, values/nested entities and owned Command/Query/Event homes;
  matching Application handlers and semantic capabilities; capability-first persistence and transport adapters.
  Additional Domain building blocks and package exceptions require a demonstrated responsibility.
- Make Project the business concept while retaining qualified Git repository terminology. Repositories are
  collection-like aggregate boundaries; registered CommandHandlers own coordination, transactions and required
  audit, with successful events after commit. Reuse canonical package messages/contracts without aliases.
- State entry-adapter authentication and permission/scope admission, the trusted in-process bus, and ordinary
  admission-time authorization. Do not require a second Application gate or revocation/commit serialization.
  Preserve real Domain invariants and business consistency rather than treating all existing locks as obsolete.
- Specify adapter-generated creation IDs in void commands and uniqueness/fresh-query reconciliation for ordinary
  creation. Distinguish genuine multi-resource intent/recovery and required audit from original-response replay.
- Specify descriptive injected collaborators, typed safe reads, transport-owned representations, supported inward
  dependencies including PSR, and direct shared failure handling. Preserve the sensitive non-serializable upstream
  synchronous exception; do not put raw passwords/tokens into messages for architectural uniformity.
- Record application compatibility as a deliberate choice at any stage, not a library-style obligation. Needed
  durable data still requires preservation, migration or authorized disposal. Do not impose Agent OS policy on
  upstream packages or erase their compatibility obligations.
- Visibly reconcile ADR 0001/0002/0005, WF-008 and current standards where EPIC-00023 supersedes them. Keep original
  approvals and factual historical evidence distinguishable from current normative direction.

Excludes source refactoring, executable dependency-rule changes, rewriting individual product plans, new product
features, and broad frontend/Pi restructuring. Source owners update executable rules with their actual changes;
a documentation pass does not prove code conformity. [TICKET-00132](00132-TICKET.md) owns unfinished-plan repair.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Builder places or names an owned type | N/A; engineering guidance | N/A | N/A | One discoverable ownership, namespace and naming contract |
| Reviewer assesses a command, repository or entry boundary | N/A; review guidance | N/A | N/A | Apply approved responsibilities without restoring rejected layers or guarantees |
| Maintainer encounters an older ADR requirement | N/A; decision amendment | N/A | N/A | Find the current rule and preserved historical approval without conflicting advice |

## Validation and permissions

This requirement changes engineering authority, not runtime grants, authentication mechanisms or business state.
EPIC-00023 supplies the accepted policy changes; implementation must not infer further authorization or data-loss
permission. Follow existing writing-for-agents guidance and review linked callers of amended instructions. Verify
links, planning and documentation directly; do not add product tests of Markdown or static architecture rules.

## Dependencies and sequencing

This is the early rule authority for [TICKET-00132](00132-TICKET.md) and scoped source corrections in
[TICKET-00133](00133-TICKET.md) through [TICKET-00136](00136-TICKET.md). It does not depend on an upstream release
or completed refactor. Later TASK planning owns exact order and real blockers; no artificial all-application gate
is introduced by a documentation dependency. Current source must remain described as current source until changed.

## Acceptance and evidence

- Builders and reviewers can find consistent rules for all responsibility boundaries above through normal project
  entry instructions, without a private prompt or duplicated competing authority.
- Superseded admission, creation/recovery, transaction/audit and Repository-language requirements have visible
  amendments; unrelated invariants and historical approvals are preserved.
- Architecture documentation distinguishes implemented behavior, approved target and upstream availability.
  Necessary low-level/package exceptions are explicit, not a growing generic exemption list.
- Instruction/skill triggers, scope, completion and next permitted action agree with the new authority. No source,
  runtime security, dependency or implementation acceptance is claimed from prose alone.
- Owning documentation/link/planning checks pass; independent Review and applicable documentation QA evaluate
  the actual guidance. Runtime Commands, Queries, Events and permissions are N/A for this documentation outcome.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00564](../tasks/00564-TASK.md) | Publish the canonical architecture contract and reconcile its callers | done |
<!-- /planning:children -->

## Decisions and progress

TASK-00564's candidate `c160a31ba985978633c904e8493c091c8a35d785` has independent Review ACCEPT and nonvisual QA PASS.
Publication and final delivery checks were pending at the acceptance checkpoint; current delivery results are in
canonical ignored `.runs/tasks/TASK-00564/runs/land.md` and the TASK PR. The following is planning history.

John approved this requirement as the first of six cohesive TICKET areas under EPIC-00023, then approved the
single-TASK decomposition now written as [TASK-00564](../tasks/00564-TASK.md). It delivers the shared architecture
contract, targeted decision amendments and affected instruction callers together in one documentation PR. Its
approved metadata is `kind: chore`, no blockers and `order: 1`, explicitly ahead of currently ready PHP feature
work; other priorities are unchanged.

This TICKET's TASK decomposition is complete. Normative reconciliation is still required implementation work,
not an effect of writing planning records. Implementation, application verification and independent Review/QA
have not started. TICKET-00132's seven-area TASK plan is also approved and written as TASK-00565 through TASK-00571;
no source, instruction authority, dependency, commit/publication or upstream change is performed by these
planning updates. Remaining TICKET decomposition and the next target are owned by
[EPIC-00023's current handoff](../epics/00023-EPIC.md#progress), not a frozen child-record phase count.
