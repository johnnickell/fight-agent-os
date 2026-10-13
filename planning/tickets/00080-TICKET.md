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
infrastructure through its owners and account separately for the selected real `ai-template-proposals` journey
under EPIC-00017/TICKET-00077. Reuse that owner's real provider/browser/admission evidence rather than introducing
a special demonstration or second acceptance ceremony. Standalone manual-create UI/API under TICKET-00073 is now
deferred: provisioning does not qualify `CreateFeature`, and the deferral is not an automatic permanent exclusion.
Retain its explicit disposition and obtain any required final scope decision before handoff; do not invent a screen
or TASK merely to fill a coverage row. Internal preparation may precede completed qualification; the final external
handoff may not.

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
| [TASK-00305](../tasks/00305-TASK.md) | Establish qualified consumer coverage for the maintainer handoff | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00018's three-TICKET split. Consume
[WF-034](../wayfinder/tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) and the
[adoption map](../wayfinder/accesscontrol-consumer-adoption-map.md). Existing EPIC-00004 and EPIC-00014 through
EPIC-00017 retain their implementations and qualification duties. This supplies the coverage gate for
[TICKET-00081](00081-TICKET.md)'s references and [TICKET-00082](00082-TICKET.md)'s final publication. No live
qualification, final matrix or starter handoff was produced by decomposition.

### Approved TASK decomposition

The maintainer approved one unranked chore: [TASK-00305](../tasks/00305-TASK.md), **Establish qualified consumer
coverage for the maintainer handoff**. It removes the accepted EPIC-00018 coverage/publication blocker, not a
reason to add product features or another certification campaign. Internal inventory may be prepared earlier;
final acceptance requires actual qualified coverage or approved explicit dispositions, never inventory alone.

Direct blockers are TASK-00066, TASK-00128, TASK-00154, TASK-00246, TASK-00255, TASK-00266, TASK-00269,
TASK-00270, TASK-00271, TASK-00272, TASK-00289, TASK-00298, TASK-00301, TASK-00302, TASK-00303 and TASK-00304.
Their upstream dependencies supply the existing human/Agent/Team, email, Feature and template paths. Completion
labels are not receipts, and this later accounting does not reverse-block their delivery or expand their scope.

Status `needs-info` retains the final owner disposition of deferred manual `CreateFeature` and any other
unsupported/unmapped public operation, plus inherited runtime/composition holds. Provisioning does not qualify
manual creation; deferral is not automatic exclusion. No endpoint, demonstration or TASK is invented for a row.

Inspect the actual selected package/source/lock and consumer subject, preserve WF-032's historical matrix and
produce one maintained current consumer matrix. Account for individual operations within every relevant family,
real entry/authority/effect boundaries and applicable safe evidence. Reuse attributable unchanged receipts without
relabelling them fresh; changes require impact assessment and owning requalification, not a second test platform.
TASK-00302 supplies the real ai-template-proposals evidence without another demonstration or retirement requirement.

TICKET-00081 consumes the qualified matrix for actual references; TICKET-00082 retains immutable packaging,
authorized publication and recipient access. Their completed deliverables are not reverse prerequisites here.
One of three EPIC-00018 TICKETs and twenty-two of 24 map TICKETs are decomposed, not implemented. Two remain,
TICKET-00081/00082, alongside older incomplete Harness/managed review planning. Active work/priorities are unchanged.
Next planning target: `/skill:to-tasks TICKET-00081`.
No final assessment, exclusion approval, live scenario or publication occurred.

### Planning verification and resolved concurrency hold

`./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed initially with 405 records,
318 active. Concurrent unrelated planning then added TASK-00306 and TICKET-00083/00084 and reopened EPIC-00008;
the subsequent planning checks passed with 408 records, 322 active for that combined checkout. Those changes are
not part of this decomposition's authority and have not been restored, staged or included in a commit here.

Two full `./bin/build` attempts ran 459 tests/3499 assertions and 78 captures/zero violations, but **both failed
the final source-stability gate** while concurrent edits changed the checkout. These are failed gates, not fresh
acceptance receipts. Evidence: `.runs/build/20261013T170722Z-84920` and `.runs/build/20261013T171049Z-87037`;
logs: `.runs/build/ticket-00080-to-tasks-attempt-1.log` and
`.runs/build/ticket-00080-to-tasks-attempt-2.log`. Sass deprecations, React-Bootstrap `use client` and >500 kB bundle
warnings also remain. `.runs/planning/ticket-00080/concurrent.sha256` records observations of concurrent files,
not a claim that the other operation stopped editing them.

The maintainer confirmed concurrent edits were paused. Their separate work was committed as `460d57c` and
`20f5d18`; this decomposition preserves it and includes only its own record/handoff/generated-view changes.
Fresh `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with 408 records,
322 active. The rerun of `./bin/build` **passed**, including source stability: 459 tests, 3499 assertions,
78 captures, zero violations, 145.27 seconds. Evidence: `.runs/build/20261013T172649Z-93402`;
log: `.runs/build/ticket-00080-to-tasks.log`. The existing warnings above remain.

This is fresh verification of the combined checkout based on `20f5d18`, not an isolated build excluding concurrent
work or consumer runtime qualification. No product implementation, final coverage assessment, exclusion approval,
independent review or publication has occurred. TASK-00305 retains its approved needs-info holds.
