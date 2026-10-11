# Define live Agent operation adoption and safe surfaces

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete AccessControl consumer adoption](../accesscontrol-consumer-adoption-map.md)
**Depends on:** [WF-032 — Reconcile AccessControl coverage with accepted Agent OS scope](WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md)

## Question

What smallest real Agent OS consumer journey qualifies the installed AccessControl v0.5.0 Agent credential,
operation and cohort contract, and which actions are human-facing versus protected worker/broker operations?
Which accepted owner may expand [EPIC-00006](../../epics/00006-EPIC.md)'s Agent/Harness destination, versus a
linked new EPIC? Use the [matrix](../research/WF-032-accesscontrol-consumer-capability-matrix.md) and installed
`vendor/johnnickell/fight-access-control/docs/agent-integration.md` and `agent-operation-cohorts.md`.

## Must decide

- First *real* activation/use proof: **recommend** one managed Explorer or Project Manager credential held by the
  actual protected holder, with controlled provision → sink delivery → authenticated MCP use → rotation → revoked
  denial and recoverable status across restart. Other managed roles then consume the same qualified boundary;
  none is bootstrapped by editing database rows or distributing a raw key in UI.
- Surface split: **recommend** authorized admin UI for safe Agent directory/detail/name, direct Permission preview/
  change, intent to provision/rotate/revoke, and operation status; self-service profile Tools only from an
  authenticated Agent. Use protected console/worker for keys, destination enrollment, cohorts, delivery,
  receipt reconciliation, maintenance, cleanup and restore; no browser endpoint for raw material, secret reads,
  lease admission or privileged worker mutation.
- Operation readiness and evidence gate: **recommend** the *full* v0.5.0 current-only operation repository,
  original-operation correlation, shared transaction/cohort fence and independent restoration witness before
  live enablement. Qualify actual PostgreSQL/writer/old-binary exclusions, sink slot ordering/receipts, keys,
  recovery and continuing use. Keep `getOperationContract()` unavailable until that evidence exists. No mock
  old-version backfill, upstream change, starter release or all-five-starter prerequisite.
- Destination ownership: **recommend** an approved EPIC-00006 amendment for the managed Harness identity/use
  contract *or* a separately linked Agent-credential EPIC with explicit EPIC-00006 dependency and owner decision;
  retain TICKET-00034 templates and TASK-00126 storage-only acceptance as-is. If expanding EPIC-00006 would
  displace its accepted Planning/terminal milestone, prefer the separate bounded EPIC.

## Resolution boundary

Decide product acceptance, ownership, access surfaces and qualification standard, not schemas, key services,
worker implementations, detailed endpoint count, operation handlers, implementation TASKs or deployment. Preserve
ADR 0001 package ownership and ADR 0002 shared transactions/durable recovery; a storage-only repository or
package fixture pass cannot be called live consumer qualification.

## Resolution

Pending maintainer decision after WF-032.
