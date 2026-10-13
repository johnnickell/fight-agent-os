---
id: TICKET-00070
epic: EPIC-00016
title: Discover provision and prepare complete Feature references
status: ready-for-agent
---

# Discover provision and prepare complete Feature references

## Problem statement

Stored Feature rows and package fixtures do not establish a complete Agent OS code inventory, safe candidate
preparation or real Permission-reference integrity. An incomplete scan mislabeled empty, a partial creation pass
or a stale stored Permission binding could let code activate without a valid runtime check.

## Solution and boundaries

Compose the actually selected package `FeatureReferenceDiscovery`, `ProvisionFeatures` and
`ValidateFeaturePreparation` contracts with real Agent OS PostgreSQL Feature and Permission repositories.
Discover every supported **method-level** `#[FeatureFlag]` by instantiating its native Attribute and merge
all explicit registered non-HTTP checks; preserve validated names, completeness and exact **CANDIDATE** versus
**CURRENT** code identity. A genuinely complete empty inventory is valid, not a fallback for inaccessible
modules, malformed declarations or scan failure. Protected setup provisions *only missing* candidate names as
`OFF` in one package transaction, with the configured existing default Permission when required; preserve all
existing identities, settings, bindings and revisions. A separate fresh `ValidateFeaturePreparation` query
checks every discovered name and original bound Permission **ID** on the intended target before candidate code
may be considered for deployment. Prepared `OFF` is not an activated or authorized operation.

Complete the shared transaction-duration Permission-reference protocol across Feature create/rebind/remove,
Permission/Role/Agent writes and managed-policy preview/reconcile, including unpaginated `hasFeatureReference`
for every status, exact positive revisions, final expected-state comparisons, canonical uniqueness and fresh
committed reads after uncertainty. Read failures cannot mean absence; no runtime auto-provisioning, generic
Permission CRUD, inferred grant, source backfill, speculative Feature or deployment mechanism is introduced.
Code cleanup and actual record removal are owned by [TICKET-00074](00074-TICKET.md).

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inventory a code scope | N/A — read-only complete discovery | `FeatureReferenceDiscovery` for exact CANDIDATE or CURRENT code and explicit registrations | N/A — metadata has no business event | Complete references or unavailable/error; no creation or activation |
| Provision intended candidate | Protected package `ProvisionFeatures` with required configured default only for missing names | Fresh candidate discovery and authoritative Feature/Permission lookups | Package `FeatureCreated` only for committed new OFF records, best-effort after commit | All missing definitions commit OFF together or the pass rolls back; existing choices survive |
| Check preparation and references | N/A — package `ValidateFeaturePreparation` and managed-policy preview are read-only | Fresh target catalog, all candidate names and bound Permission IDs; unpaginated reference check | N/A — a read is not an activation event | Issues or prepared observation; live Permission removal refuses any remaining Feature reference |

## Validation and permissions

Only protected deployment/operator entry paths may invoke provisioning and preparation, with current operator,
target/code identity and explicit scope; the Feature's testing Permission does not grant setup. Scans reject
wrong scope, partial results and invalid attributes rather than ignore them. DB uniqueness and shared fences
protect concurrent provisioners and all Permission-removal paths through commit, including no-op rebinding;
managed reconciliation cannot drop a referenced Permission. Unknown or uncertain commits require a fresh read,
not a claimed rollback or idempotent event replay. Package post-commit publication is not durable preparation
proof. No scanner/status/Permission error leaks arbitrary repository diagnostics to browsers or logs.

## Acceptance and evidence

- An actual scanner covers every supported method declaration and registered entry point under each exact code
  scope, including invalid/inaccessible and genuinely empty inventory; candidate and running CURRENT are not
  silently interchanged.
- Real PostgreSQL tests/tool probes cover all-pass rollback, competing provisioners, new/old reference races,
  complete Permission scans beyond one page, policy removal conflicts, post-commit uncertainty and fresh reads.
- Fresh candidate preparation rejects missing definitions, broken/mismatched bindings, invalid records and
  failures without treating OFF as availability. Record selected tagged package/source/Composer lock and
  actual consumer port qualification, not package fixtures alone.
- Owned behavior tests and direct scanner/DB checks plus `./bin/build` pass; never add product-suite tests for
  migrations, wiring, generated files or tooling.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00290](../tasks/00290-TASK.md) | Discover complete Feature references for exact code scopes | ready-for-agent |
| [TASK-00291](../tasks/00291-TASK.md) | Provision missing Features atomically through protected operations | ready-for-agent |
| [TASK-00292](../tasks/00292-TASK.md) | Inspect fresh candidate preparation against the intended target | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00016's five-TICKET split. Supplies the shared persistence/discovery boundary to
[TICKET-00071](00071-TICKET.md), [TICKET-00073](00073-TICKET.md) and
[TICKET-00074](00074-TICKET.md). Apply [WF-032](../wayfinder/tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md)
and [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) /
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) without displacing package Feature
policy. No actual scanner, migration, Feature or preparation proof was delivered by decomposition.

### Approved TASK decomposition

The maintainer approved three independently reviewable use cases, reusing existing Feature storage and reference
fences rather than creating another persistence system or a proof-only qualification TASK:

| TASK | Outcome | Blockers |
|---|---|---|
| [TASK-00290](../tasks/00290-TASK.md) | Discover complete Feature references for exact code scopes | TASK-00126 |
| [TASK-00291](../tasks/00291-TASK.md) | Provision missing Features atomically through protected operations | TASK-00290, TASK-00036 |
| [TASK-00292](../tasks/00292-TASK.md) | Inspect fresh candidate preparation against the intended target | TASK-00291 |

All three are `feature`, `ready-for-agent` behind their listed blockers and unranked. TASK-00126's implemented
`PostgresFeatureRepository`, Permission-reference checks and scoped PostgreSQL evidence remain their baseline,
not proof of scanner completeness or a composed Feature lifecycle. The inspected command/query configuration
contains no Feature provisioning/preparation registration. Each new slice owns actual implementation and affected
integration evidence, not a second receipt-only acceptance gate.

TASK-00290 implements exact CANDIDATE/CURRENT discovery, native Attribute instantiation and one explicit non-HTTP
registration source. Cover the declared application/module surface, including unloaded declarations and supported
method resolution, rather than only already-loaded classes or routes. Invalid placement/multiplicity/names,
partial/inaccessible/changed code, wrong identity/scope or bounds exhaustion cannot become complete empty success.
Inspect trusted selected candidate code without allowing arbitrary code execution or substituting current-process
classes. Qualify protected bounded inspection and code identity directly. Unknown running-version coverage holds;
TICKET-00074 retains actual deployment-overlap/current-observation qualification for retirement.

TASK-00291 supplies protected synchronous package `ProvisionFeatures` with explicit trusted operator, exact
candidate/target and the configured nullable default. Only a missing name makes that default required; empty or
all-existing inventories preserve package unused-default behavior. All new definitions commit OFF together with
owning-package IDs; existing identities/settings/bindings/revisions, including broken definitions, are untouched.
No Permission, membership, grant, automatic repair or runtime registration is created by provisioning.

This actual use case completes Feature-specific reference qualification across add/replace/remove, Permission/
Role/Agent writes and TASK-00036's managed-policy preview/reconcile. Reuse the shared canonical connection,
transaction-duration fence, full expected-state/no-op checks, positive revisions, canonical uniqueness and
unpaginated all-status reference lookup. Include actual whole-pass rollback, competing provisioners, rebind/
removal/no-op races and beyond-page removal conflicts. Preserve applicable cohort-first authority ordering without
a new fence owner, complete live-Agent prerequisite or bypass of unavailable Agent operation contracts. Later
management/retirement entry owners add their affected integration evidence without reverse-blocking this primitive.

The provisioning handler returns `void` and emits best-effort creation facts after commit; lost acknowledgement
or publication failure is not rollback. Fresh authorized inspection/repeat preserves committed winners and does
not replay their facts. Contain the package's original-command/error-bearing `CommandFailedEvent` within trusted
handling rather than exposing arbitrary diagnostics. No durable event outbox or preparation claim follows.

TASK-00292 composes the separate read-only package query and safe protected result. Its dependency reuses the
operational/persistence boundary; a caller need not provision first. Check every distinct name against current
Feature state and its original bound Permission ID, not a reused name or current default. Preserve safe missing/
broken/invalid issue categories; malformed Feature/Permission hydration needs explicit contract qualification,
while infrastructure/discovery failures remain errors. No stale identity-map/negative-cache result, automatic
repair or inferred readiness from a command/event. Exact code/target observations are not a distributed snapshot,
durable certificate or fence against later privileged changes.

TICKET-00071's TASK-00293 now consumes this chain for human/public HTTP enforcement; TASK-00294 adds actual
registered non-Action/Agent checks through TASK-00245. Reuse fresh package availability, native declarations and
one registration source without a reverse blocker or runtime scanner/provisioning call. Preparation stays a
protected pre-rollout observation, not an authorization decision at each invocation.

TICKET-00073 consumes safe reference writes and fresh preparation; TICKET-00074 consumes CURRENT discovery and
final expected-state/reference release with its independent deployment/cleanup authority. No final middleware,
UI, retirement, Agent/Harness or managed release journey reverse-blocks these primitives. Prepared OFF is neither
availability nor deployment approval, and a candidate result cannot authorize CURRENT-code retirement.

Each slice tests owned behavior and important contracts with actual scoped scanner/PostgreSQL evidence and
`./bin/build`. Validate configuration, migrations, generated artifacts and operational tools directly, not with
product tests of text/wiring/tooling. Reuse attributable valid earlier evidence, refresh affected integration and
require a failing regression before bug repair. No dummy product Feature or extra proof-only project is needed.
Missing supported guarantees hold the affected path for separately authorized owner resolution, not vendor patches.

Active TASK-00138/TASK-00226 and existing priorities remain unchanged. With TICKET-00071–00074, all five EPIC-00016
TICKETs now have approved written TASK splits. With TICKET-00075–00082, **all 24 map TICKETs are decomposed**;
older Harness/managed review planning remains incomplete.
Next: `/skill:next` for read-only Board routing.
Planning ran no scanner/provision/query, changed no catalog and enabled no Feature or deployment. Implementation/
worktree choice, independent review, publication and actual target effects remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 392 records,
305 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations. Repetitive Sass deprecations, React-Bootstrap `use client` bundling and >500 kB chunk warnings remain.
Evidence: `.runs/build/20261012T225927Z-59525`; summary log: `.runs/build/ticket-00070-to-tasks.log`. These validate planning and the existing repository, not the future
scanner, composed provisioning/preparation, full reference qualification or any actual deployment. Independent
review and publication remain unperformed.
