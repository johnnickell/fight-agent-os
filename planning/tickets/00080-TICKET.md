---
id: TICKET-00080
epic: EPIC-00018
title: Account for complete consumer coverage and qualification
status: ready-for-agent
---

# Account for complete consumer coverage and qualification

## Problem statement

The AccessControl capability matrix is a historical planning inventory, not proof of complete Agent OS adoption.
Starter maintainers need a traceable account of every supported public use-case family, with actual consumer
evidence and explicit dispositions rather than package fixtures, stored rows or planned screens labeled live.

## Solution and boundaries

Maintain an Agent OS-owned consumer capability/contract matrix against the actually selected and qualified
AccessControl tag, source and Composer lock. Re-evaluate the approved v0.5.0 coverage from the
[planning matrix](../wayfinder/research/WF-032-accesscontrol-consumer-capability-matrix.md) against current
public contracts and actual implementation; retain the original matrix as historical planning evidence, not a
relabeled conformance report. A changed package release requires impact assessment and an accurate supported-
contract label, not an assertion that an unchanged v0.5.0 run occurred.

For each relevant public use case record package contract, Agent OS owner and implementation/entry path, actor,
target, current Permission/authorization and effect boundary, appropriate human UI or qualified protected non-UI
surface, exact evidence provenance, limitations and any approved exclusion. Distinguish implemented, planned,
excluded, unsupported and unassigned facts while preparing internally. A family summary must not hide an
unaccounted public operation. Final readiness requires actual qualification of every in-scope capability and
approved explicit dispositions for exclusions/unsupported operations, with no unassigned or merely planned gap.

Trace fresh consumer evidence through each behavior's existing owner: human policy/authentication/grants,
email change, Agent operation/cohort/restore/credentials/use, Features and approved template administration,
including relevant PostgreSQL races, restart/recovery, revocation and browser authority. Identify which actual
source, configuration and tool versions each receipt qualifies. An existing receipt may support only its
unchanged, evidenced subject; never relabel historical, storage-only or package-modeled observations as a new
integrated consumer run. Missing evidence, incompatible composition or unresolved scope is a publication blocker.
Route it to the owning requirement or an explicit owner decision, without duplicating implementation or silently
expanding acceptance here.

This requirement owns coverage accounting and the complete-journey readiness assessment, not a second test
platform, generic certification system or all-EPIC status check. No endpoint-per-package-method rule, browser
secret console, dummy Feature or proof-only product is introduced. Accurately qualify the reusable Feature
infrastructure without requiring a specially selected product flag or a special absence-of-live-flag caveat.
Internal preparation may precede completed qualification; the final external handoff may not.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inventory actual coverage | Documentation maintenance, not an application command | Inspect actual locked public contracts, accepted owners and implemented entry paths | N/A — no Domain mutation or event | Attributable current matrix with explicit status and disposition per use case |
| Assess evidence | Owning qualification tools/operations only within their existing authority | Inspect exact scenario subjects, consumer receipts, versions, outcomes and limits | Only existing product facts if an authorized scenario has real effects; no synthetic certification event | Qualification accounted for or a specific gap returned to its owner |
| Decide handoff readiness | Explicit maintainer readiness assessment, not a new runtime command | Complete matrix, approved exclusions and remaining blockers | N/A — readiness documentation is not product authority | Final handoff held until every relevant family has a qualified or approved disposition |

## Validation and permissions

Access to source, receipts, private API documentation and operational observations remains independently
authorized. Matrix readers receive only reviewed safe references and redacted observations, never credentials,
private source bodies, hidden provider settings or unauthorized personal data. Reading a receipt does not authorize
replaying its operation; any fresh scenario preserves owning actor/target/delegation, confirmation, environment
and effect controls. Apply [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) when assessing package ownership and
transaction/recovery claims. This documentation adds no Permission, principal, transaction or event mechanism.

## Acceptance and evidence

- The actual locked public contract is accounted for by use case, including UI, protected operations and
  approved exclusions; no umbrella family label hides a missing owner, operation or unsupported guarantee.
- Every implemented claim has attributable Agent OS evidence bound to its actual source/environment/tool subject;
  package-only, storage-only, historical and incomplete evidence is distinguished and cannot clear the final gate.
- Gaps are explicitly owned and block final publication until resolved or excluded by the authorized owner.
  EPIC/TASK completion labels alone do not establish current qualification.
- Direct contract/source/receipt/link inspection and owning tools plus `./bin/build` validate the work. Do not
  add product-suite tests for the matrix, Markdown, quality tools or deliberately seeded tooling failures.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00018's three-TICKET split. Consume
[WF-034](../wayfinder/tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) and the
[adoption map](../wayfinder/accesscontrol-consumer-adoption-map.md). Existing EPIC-00004 and EPIC-00014 through
EPIC-00017 retain their implementations and qualification duties. This supplies the coverage gate for
[TICKET-00081](00081-TICKET.md)'s references and [TICKET-00082](00082-TICKET.md)'s final publication. No live
qualification, final matrix or starter handoff was produced by decomposition.
