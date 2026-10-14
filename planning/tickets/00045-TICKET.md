---
id: TICKET-00045
epic: EPIC-00006
title: Assess PHPUnit test usefulness
status: ready-for-agent
---

# Assess PHPUnit test usefulness

## Problem statement

A passing test can still prove little about the intended behavior. Engineers and architectural reviewers need
help distinguishing useful contract protection from suspected tautologies and incidental implementation checks,
with honest evidence and coverage rather than a model's claim that a test can never fail.

## Solution and boundaries

Implement WF-031's [PHPUnit usefulness contract](../wayfinder/tickets/WF-031-define-general-purpose-bounded-judgments.md#phpunit-test-usefulness-assessment)
through [TICKET-00042](00042-TICKET.md)'s typed judgment capability. Apply
[existing test-quality criteria](../../docs/engineering/QUALITY.md#tests-that-protect-contracts) and
[ST-04/SP-05 review rules](../../docs/engineering/REVIEW.md); do not create a new formal acceptance scale.

- Automatically assess added or materially changed PHPUnit cases before Engineer review handoff. During an
  architectural review explicitly scoped to the entire owned PHPUnit suite, inventory and assess all owned cases
  in bounded stages using the same criteria. Full-suite mode is part of that review scope, not a recurring audit
  or an automatic side effect of ordinary change review.
- Identify the observable contract and plausible defect each case should detect. Establish independent expected
  results from requirements, specifications, worked examples or reviewed fixtures instead of repeating the
  implementation, its algorithm or copied outputs/constants lacking a contract.
- Investigate self-comparisons, non-discriminating assertions, echo-only mocks, unreviewed snapshots and incidental
  internal/call-graph checks. A suspicious pattern is a candidate concern, not proof of a defect.
- Assess resilience to behavior-preserving refactors while preserving legitimate wire payloads, side effects or
  their absence, transaction/retry ordering and justified boundary interactions. Mocks and reviewed snapshots are
  not inherently invalid. Preserve uniquely useful coverage and require a contract-grounded replacement where
  necessary; no automatic deletion or rewriting of flagged tests.
- Gather permitted cases, related owned behavior, requirements and available execution/regression evidence. Return
  predefined per-criterion outcomes and actual candidate identifiers with unknown/missing-context exits. Jev cannot
  generate a free-form justification or prove absolute never-can-fail behavior. Distinguish suspected weak tests,
  reviewer-demonstrated tautologies and unknown usefulness; retain independent verification for findings.
- Bind changed-case assessment to exact review subject and use the same affected-unit reuse requirements as
  [TICKET-00044](00044-TICKET.md): complete relevant evidence/context/rubric/evaluator/policy identity and current
  authority, original provenance/time, fresh coverage and no removed cases counted. Text equality alone is insufficient.
- For whole-suite reviews, inventory all owned PHPUnit cases and report assessed/excluded/incomplete coverage,
  source revisions and bounded stage progress. Scope gaps and unknown coverage stay visible; no whole-suite claim
  from sampling or a partial run. Prefer shared assessment/provenance mechanics over a second evaluator.
- Report counts and proportions by inspected criterion/outcome with explicit denominators, exclusions, uncertainty
  and verified/unverified concerns. These are derived reporting measures, not pre-existing numerical good/bad
  thresholds or an overall correctness percentage. Keep model judgments, source findings and execution evidence distinct.
- Missing/invalid/budget-exhausted Jev assessments leave explicit advisory gaps and allow normal review handoff
  without a new waiver solely for this advisor. Existing required gates and verified findings still apply; a
  positive classification never replaces PHPUnit execution evidence required by the work.

Use relevant available failing-regression and execution receipts. Exclude new product tests of tests, deliberately
seeded failures, mutation tooling, automatic test repair/deletion and a changed coverage/acceptance gate. Future
fault-injection execution requires its own bounded design and authority. Safe local execution remains under its
existing owner; production inspection does not authorize PHPUnit against production or production data as fixtures.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Assess changed PHPUnit cases | Start assessment for current handoff subject | Changed cases, related contracts/behavior and predefined criterion judgments | Case-level assessment completed/partial/unavailable | Bounded authorized reads/provider calls and advisory evidence report |
| Assess the entire owned suite | Start/resume/cancel suite assessment within architectural-review scope | Owned-case inventory, staged outcomes and coverage | Progress/completion/partial/cancellation observations | Bounded investigation and report; no implicit test execution or source changes |
| Verify a suspected weak test | Existing reviewer finding operation only after evidence inspection | Exact case, expected-result basis and available regression/execution receipt | Model concern and independently verified finding remain distinct facts | Existing review report; no automatic deletion or acceptance |
| Compare usefulness coverage | N/A: reporting query does not mutate tests | Criterion counts/proportions, denominators and unknowns | N/A: inspection creates no acceptance transition | Authorized report view with provenance and honest limitations |

## Validation and permissions

Use actual caller/reviewer authority for tests, requirements, related code and receipt references, checking provider
redisclosure separately. Preserve independent reviewer sessions and no private Engineer-context transfer. Bound
cases/files, enumeration, payload/results, concurrency, duration and spend through the shared capability. Respect
cancellation/reload and reject stale results; never rerun effects or resample for a desired answer. Ordinary receipts
exclude source bodies/credentials. Any authorized local test execution obeys existing guarded test services and
ADR 0002; neither this inquiry nor SQL-read permission grants test-execution or database-mutation authority.

## Acceptance and evidence

- Qualify representative useful contract tests, weak/self-comparing or echo-only candidates, independently justified
  mocks/snapshots and legitimate ordering/absence-of-effect assertions. Measure false flags and missed concerns;
  reviewers verify claimed defects rather than relying on a classifier label or seeded product-suite failures.
- Demonstrate automatic changed-case assessment and bounded whole-suite architectural review with exact case/source
  mapping, all-owned-case inventory, explicit exclusions/unknowns and no overstated coverage after interruption.
- Verify affected-case reassessment when expectations/contracts/related behavior change, valid authorized reuse and
  removal of stale/deleted cases. A green execution receipt cannot substitute for an independent expectation basis.
- Demonstrate reporting denominators and verified/unverified distinctions, safe reviewer follow-up, advisory outage
  handling and preservation of unique useful coverage. No test is automatically deleted or considered infallible.
- Compare useful review direction, cost/latency and complete/incomplete coverage on representative workloads. Test
  owned assessment/reporting behavior only; qualify PHPUnit/runtime integrations through their owning tools.

## Sequencing and TASK readiness

Consume TICKET-00042 and existing review/architectural-review seams. Reuse compatible TICKET-00044 subject/coverage
mechanics where available, without imposing a whole-ticket implementation dependency; encode only real shared
capability blockers during TASK planning. Production SQL integration is not a prerequisite. Pin criterion enums,
case identity/inventory rules, instruction delivery, automatic-hook coverage and stage/aggregate limits at that step.
This TICKET defines automatic assessment, not a guaranteed numerical measure of test correctness.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00181](../tasks/00181-TASK.md) | Assess changed PHPUnit cases before Engineer handoff | ready-for-agent |
| [TASK-00182](../tasks/00182-TASK.md) | Assess the owned PHPUnit suite during architectural review | ready-for-agent |
| [TASK-00183](../tasks/00183-TASK.md) | Refresh test assessments when cases or contracts change | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this split on 2026-10-08 for the selected Jev follow-on. WF-031 and current ST-04/SP-05 remain
accepted authorities for usefulness and evidence. That TICKET decomposition allocated no TASK, ran no suite/audit/
provider, changed no tests or gates and granted no publication or production authority.


### Approved TASK decomposition — 2026-10-09

John approved three independently reviewable outcomes targeting **Pi 1.1.0**:

- [TASK-00181](../tasks/00181-TASK.md): automatic changed-case usefulness assessment, blocked by TASK-00178.
  Reuse its actual handoff/subject/report capabilities with PHPUnit case identities and the four established
  contract, defect-sensitivity, independent-expectation and refactor-resilience criteria.
- [TASK-00182](../tasks/00182-TASK.md): explicitly scoped whole-owned-suite architectural assessment, blocked by
  TASK-00181. Reuse bounded staged investigation with truthful inventory, progress and criterion denominators;
  do not turn ordinary review into an automatic full-suite audit.
- [TASK-00183](../tasks/00183-TASK.md): case/contract/context-sensitive refresh and reuse, blocked by
  TASK-00181/TASK-00179. Revalidate providers, fixtures, expectations, related behavior and current authority,
  remove deleted cases from current coverage and retain original provenance on reused outcomes.

Full-suite assessment and refresh can proceed independently after their actual blockers. All three remain unranked
and preserve prior TASK scope and executable Board priority. Their dependencies reuse concrete capabilities rather
than all of TICKET-00044; no production SQL or compaction prerequisite is introduced.

Criterion outcomes are supported/concern/unknown/not-applicable; invalid/unavailable operation states and attributed
reviewer verification are separate. Declaration/dataset/execution populations stay distinct. Existing execution
receipts are evidence, not permission to run tests or proof of usefulness. No seeded failures, mutation tooling,
product tests of tests, automatic deletion/repair or new acceptance gate is introduced. Advisory outages leave
explicit gaps while ordinary review and mandatory gates continue.

This completes TASK decomposition for the explicitly selected TICKET-00040 through TICKET-00045 follow-on:
20 TASKs, TASK-00164 through TASK-00183. Older EPIC scope and other maps retain their status. Implementation,
actual hook/inventory/criterion qualification, independent review, publication and runtime activation remain separate.

Next: `/skill:next` to consult the authoritative Board for the completed selected planning slice; this does not
rank or start these TASKs, or claim older EPIC/map planning is complete.
