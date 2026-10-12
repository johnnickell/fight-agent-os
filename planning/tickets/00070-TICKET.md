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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00016's five-TICKET split. Supplies the shared persistence/discovery boundary to
[TICKET-00071](00071-TICKET.md), [TICKET-00073](00073-TICKET.md) and
[TICKET-00074](00074-TICKET.md). Apply [WF-032](../wayfinder/tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md)
and [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) /
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) without displacing package Feature
policy. No actual scanner, migration, Feature or preparation proof was delivered by decomposition.
