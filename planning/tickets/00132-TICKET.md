---
id: TICKET-00132
epic: EPIC-00023
title: Reconcile every unfinished product plan
status: ready-for-agent
---

# Reconcile every unfinished product plan

## Problem statement

Agent OS already has a substantial approved backlog. Correcting current code alone would leave future builders
following Repository terminology, mixed Stores, misplaced admission, broad services or unnecessary replay contracts.
The whole unfinished portfolio must direct work toward [EPIC-00023](../epics/00023-EPIC.md)'s approved architecture
without changing the intended products or pretending unimplemented behavior already exists.

## Solution and boundaries

- Account for every unfinished EPIC, TICKET and TASK, including relevant archived unfinished records if present at
  execution. Do not restrict review to titles containing Project, architecture or Repository. Inspect actual
  requirements and acceptance language, with traceable corrected or justified-unchanged dispositions.
- Correct business terminology, aggregate/handler/repository ownership, proposed messages/results, adapter
  authentication and permission/scope admission, creation IDs, transaction/audit ownership, and upstream supplier
  requirements. Use descriptive names in proposed interfaces/examples, not merely renamed file paths.
- Align Project vocabulary across registered Planning, Execution, access relationships, Teams, context, creation,
  instruction management and their downstream consumers. Retain qualified Git repository names where correct;
  do not rename source projects, package names or unrelated repository mechanisms by blind substitution.
- Replace ordinary-create immutable-response/replay and revocation/commit serialization requirements with the
  approved contracts. Preserve required audit, uniqueness, current-state consistency and separately justified
  multi-resource recovery, including Git linking and filesystem/publication workflows.
- Keep existing product owners and requirements. A correction to a planned use case belongs in its current record;
  do not duplicate it under this EPIC, silently cancel scope, mass-close work or reopen completed feature records.
- Make upstream capability needs and acceptance evidence explicit without guessed releases, development-snapshot
  imports, wrappers or blanket dependencies on unrelated upstream refactors. Preserve current external holds until
  their actual evidence exists. Missing suppliers block their consumers only.
- Update applicable current planning handoffs and generated views through the owning tools. Preserve historical
  approvals and completion evidence; visibly source-link architectural amendments where needed. Planned adapters
  need architecture contracts, not speculative MCP/HTTP/Console implementation.

Excludes application implementation, completed-history rewriting, automatic cancellation or archiving, new feature
scope, and a parallel permanent audit backlog or boilerplate scorecard in every record. Governing instructions
are owned by [TICKET-00131](00131-TICKET.md). Source corrections are owned by the remaining EPIC-00023 TICKETs.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Builder selects any unfinished approved record | Existing planned use-case intents, corrected in their owners | Existing planned reads/results | Existing business facts, not invented planning events | Requirements lead to the approved architecture instead of rejected patterns |
| Planner reconciles Project references across capabilities | Existing Project-related intents | Existing context/access/selection reads | Existing owned facts | Consistent identities, relationships, vocabulary and acceptance language |
| Supplier is planned but unavailable | N/A; prerequisite clarification | N/A | N/A | A precise affected-consumer hold, not a fake released guarantee or all-work freeze |

No runtime command/query/event or permission is added by this planning-reconciliation requirement.

## Validation and permissions

EPIC-00023 authorizes the listed architectural amendments, not removal of unrelated product/security requirements.
Retain actual per-entry permission and validation outcomes while correcting their placement and ordinary timing.
Do not infer destructive data authority, new grants, implementation acceptance or supplier availability from edited
planning metadata. Use native planning/link validation; do not add product tests of planning text.

## Dependencies and sequencing

Use [TICKET-00131](00131-TICKET.md)'s authoritative contract. Start the portfolio review and repair early so affected
feature work does not extend obsolete designs. Reconcile exact interface references with source owners as those
contracts settle; do not defer the entire review until all code refactors finish or create circular blockers.
TASK planning must assign bounded ownership and actual dependencies without inventing a universal implementation
freeze. The approved requirement covers the complete portfolio, not just a representative sample.

## Acceptance and evidence

- A refreshed inventory accounts for every unfinished EPIC/TICKET/TASK and additions during delivery. Each record
  has a traceable corrected or justified-unchanged disposition; source sampling alone cannot complete this outcome.
- Actual conflicting requirements, examples and acceptance criteria are repaired in their existing owners; the
  intended product outcome, non-conflicting security requirements and meaningful supplier edges remain intact.
- Project language is coherent across related plans, while Git/source/package terminology and separate Host,
  Checkout and Worktree identities remain accurate.
- Ordinary creation/admission contracts and genuine multi-resource recovery/audit requirements are distinguished.
  No mixed Store/UoW, handler-bypass or unnecessary compatibility mechanism is prescribed by surviving requirements.
- Current handoffs identify precise remaining phases and suppliers. Historical approvals are preserved, unfinished
  work is not falsely closed, and code/releases are not advertised as implemented by planning changes.
- Keep execution coverage evidence under existing task-owned runs and final outcomes in current records; do not
  introduce a permanent parallel queue. Planning generation/read-only and documentation checks pass, with
  independent Review and applicable documentation QA. No product build is claimed merely from these checks.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00565](../tasks/00565-TASK.md) | Reconcile identity, access and Feature plans | ready-for-agent |
| [TASK-00566](../tasks/00566-TASK.md) | Reconcile Workspace and Planning plans | done |
| [TASK-00567](../tasks/00567-TASK.md) | Reconcile project creation and instruction-management plans | in-progress |
| [TASK-00568](../tasks/00568-TASK.md) | Reconcile managed Agent, Team and template plans | ready-for-agent |
| [TASK-00569](../tasks/00569-TASK.md) | Reconcile Harness, proposal and memory plans | ready-for-agent |
| [TASK-00570](../tasks/00570-TASK.md) | Reconcile execution and delivery-workflow plans | ready-for-agent |
| [TASK-00571](../tasks/00571-TASK.md) | Reconcile operations and architecture plans and close portfolio coverage | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Area C, [TASK-00567](../tasks/00567-TASK.md#r1-repair-checkpoint--2026-11-05), repairs confirmed Review R1 by
separating ordinary database designation from physical link/marker recovery while retaining attempt provenance
and all consistency/safety/supplier guarantees. Renewed independent Review/documentation QA remain required;
the initial candidate received REVISE, not acceptance. Its 57 unfinished EPIC-00009/00010 records
have explicit corrected or justified-unchanged dispositions in canonical ignored
`.runs/tasks/TASK-00567/runs/coverage.md`; final inventory accounts for additions/archives. Real physical/provider
recovery and all supplier holds remain. TASK-00571 receives that coverage, not deferred area-C repairs. Feature
execution, source cutovers and this TICKET's complete-portfolio acceptance remain separate.

Area B, [TASK-00566](../tasks/00566-TASK.md#accepted-implementation-checkpoint--2026-11-05), has independent
Review ACCEPT and documentation QA PASS on `7c9666af4dde06e05c8505c56cb00685c58ce7ab`: 50 unfinished
EPIC-00005 records corrected, three completed records preserved, no archived descendants. Its canonical coverage
is input to TASK-00571, not complete-portfolio acceptance. Publication and eligible cleanup are pending at that
checkpoint; current results are in canonical ignored `.runs/tasks/TASK-00566/runs/land.md` and the TASK PR.
This TICKET remains open for its other children. The decomposition narrative and its unstarted/verification
claims below are historical planning checkpoints, not current implementation status.

John approved whole-portfolio planning reconciliation and this second requirement area in the six-TICKET split,
then approved the seven-area TASK decomposition, now written as TASK-00565 through TASK-00571:

- [TASK-00565](../tasks/00565-TASK.md): identity/access/Feature/reference-consumer plans, EPICs 00004/00015/00016/00018
  and standalone TASK-00531; order `2`.
- [TASK-00566](../tasks/00566-TASK.md): Workspace and Planning, EPIC-00005; order `3`.
- [TASK-00567](../tasks/00567-TASK.md): project creation and instruction management, EPICs 00009/00010; order `4`.
- [TASK-00568](../tasks/00568-TASK.md): managed Agents/Teams/templates, EPICs 00014/00017; order `5`.
- [TASK-00569](../tasks/00569-TASK.md): Harness/proposals/memory, EPICs 00006/00011 and standalone TASK-00555; order `6`.
- [TASK-00570](../tasks/00570-TASK.md): execution/review/hotfix/releases, EPICs 00007/00008/00012/00013 and standalone
  TASK-00556; order `7`.
- [TASK-00571](../tasks/00571-TASK.md): operations/diagrams/architecture correction, EPICs 00019/00021/00022/00023,
  plus final refreshed combined portfolio coverage; order `8`.

All seven are `kind: chore`. The first six depend only on [TASK-00564](../tasks/00564-TASK.md), not on one another,
source refactoring or an upstream release. TASK-00571 depends on those six actual area corrections because its
complete outcome includes final cross-area reconciliation. It also repairs its own operations/architecture plans;
there is no audit-only substitute, permanent parallel queue or second parent-acceptance gate. Other TASK priorities
and existing feature blockers are not changed by writing these records.

The proposal's 525 unfinished records were a metadata snapshot, not an exhaustive requirements audit or fixed
completion count. Each area inspects actual requirements and records corrected or justified-unchanged dispositions
under its ignored task runs. Final coverage refreshes the baseline and current live/archive inventory, including
records added during delivery and legitimate status transitions, while preserving completed history. Each TASK owns
its actual area correction, affected current handoffs, direct checks, normal canonical gate with honest reuse
reporting, independent Review and applicable documentation QA; final accounting does not replace those outcomes.

TICKET-00132's TASK decomposition is complete. This update does not perform the portfolio review, amend other
product contracts by implication or start implementation, application verification, upstream work or publication.

Planning-only verification passes: `./bin/planning-check --write` and `./bin/planning-check` report 730 records,
532 active and current generated views; tracked/new-record whitespace checks pass. A metadata-only partition
check assigns all 532 currently unfinished records to exactly one of the seven named area owners. This checks
coverage assignment, not actual requirements or corrected conformity. No product build was run for this
planning-only update, and none of the seven correction TASKs has started.

Remaining TICKET decomposition and the next target are owned by
[EPIC-00023's current handoff](../epics/00023-EPIC.md#progress); this accepted seven-area plan starts no implementation.
