---
id: TICKET-00059
epic: EPIC-00014
title: Persist and fence current Agent credential operations
status: ready-for-agent
---

# Persist and fence current Agent credential operations

## Problem statement

`PostgresAgentRepository` currently provides storage and reference checks but deliberately returns
`UNAVAILABLE` for its operation contract. An Agent row, a package conformance fixture or a startup flag cannot
qualify Agent issuance, direct Permission changes, restoration or concurrent writers. A live Agent needs an
actual shared, persisted, current-only operation/cohort boundary before protected effects can be enabled.

## Solution and boundaries

Implement and qualify both package-declared Agent and Agent-operation repositories on the canonical PostgreSQL
transaction, including complete original-operation/request and successor correlation, exact revisions, reserved
slots, immutable destination binding, authority epochs, tombstones, ordered write and audit persistence. Resolve
one authoritative cohort with the actual locally installed compatible participants, positive monotonic
generation and an **independent** admission/restoration witness bound to the live storage incarnation. Fence
all Agent, operation, Permission/Role/User/managed-policy, destination/key and direct CLI/worker writers before
subordinate locks, for the transaction's duration. Prevent incompatible restarted binaries and other bypass
paths from writing, not just new-code callers. Reconcile controlled restoration with the real tools before
readmitting effects. Keep `getOperationContract()` sanitized `UNAVAILABLE` until the **complete** key/sink,
recovery, authentication/activation/use and writer composition supplied by the adjacent requirements is
qualified; this TICKET alone cannot declare operations live.

No alternative legacy mode, invented historical backfill, upstream contract change, raw-secret return or
in-memory proof product belongs here. [ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md)'s
historical Agent deferral is supplemented by [EPIC-00014](../epics/00014-EPIC.md) against the deliberately
selected, actually locked package; do not rewrite older receipts.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record an Agent operation | Package-owned provision/rotation/lifecycle and repository writes in their owning transactions | Read authoritative Agent, original request, operation and cohort | Package facts after confirmed commit; no duplicate owned event | Exact operation/authority/audit state commits or rolls back together |
| Refuse incompatible or bypassing writers | Rejected direct, bus, CLI and worker write attempts; protected cohort switch only under operator authority | Inspect both repositories, participating writer versions and independent witness | Safe operational refusal, not a false completion fact | No partial Agent, key, slot or audit mutation, and no materialization |
| Reconcile controlled restoration | Protected quiesce, switch and readmission intent, not ordinary user command | Compare live storage incarnation to independent history, receipts, orders and policy | Secret-free generation/admission observations | Prior admission is invalidated; only fully reconciled newer generation is admitted |

## Validation and permissions

Use package current-contract APIs and invariant checks, not duplicate Domain state policy. Shared cohort,
Permission/reference and target fences cover no-ops and every same-connection participant; missing storage,
invalid versions, mismatched generations/witness, outages or uncertain commit fail closed without repository
messages or guessed absence. Trusted operator authority guards cohort and restoration; ordinary Agent or Super
Admin UI access is not admission. Actor/target/delegation checks remain mandatory for every later entry path.

## Acceptance and evidence

- Real independently competing PostgreSQL writers, rollback, commit uncertainty, direct/no-op Permission changes,
  managed-policy and key/destination transitions obey one lock order and preserve original correlation.
- Missing/incompatible participants, old-binary restart, partial/outdated witness and real controlled restore
  cannot advertise readiness, mutate authority or disclose material; a newer reconciled cohort is observable.
- Exact tagged package/source/lock and real adapters, shared connection, source/tool observations and residual
  unsupported scenarios are recorded; package fixture or TASK-00126 storage evidence is labeled historical.
- Focused owned repository/behavior tests and directly run PostgreSQL/restoration tools plus `./bin/build` pass;
  do not add product tests for migrations, wiring, tooling, generated files or seeded tool failures.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00227](../tasks/00227-TASK.md) | Accept the Agent persistence and restoration design | ready-for-agent |
| [TASK-00228](../tasks/00228-TASK.md) | Enforce shared cohort admission | ready-for-agent |
| [TASK-00229](../tasks/00229-TASK.md) | Persist correlated issuance and credential retirement | ready-for-agent |
| [TASK-00230](../tasks/00230-TASK.md) | Persist recoverable delivery and maintenance state | ready-for-agent |
| [TASK-00231](../tasks/00231-TASK.md) | Fence every authority-changing writer | needs-info |
| [TASK-00232](../tasks/00232-TASK.md) | Operate controlled cohort changes and exclude old writers | needs-info |
| [TASK-00233](../tasks/00233-TASK.md) | Reconcile controlled restoration without resurrecting authority | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 1 of EPIC-00014's seven-TICKET split. Supply the operation/fence boundary to
[TICKET-00060](00060-TICKET.md) and [TICKET-00061](00061-TICKET.md); actual readiness is conjunctive, never
claimed merely by completing this persistence slice. Consume [WF-033](../wayfinder/tickets/WF-033-define-agent-operation-adoption-boundary.md)
and installed package operation/cohort/restoration contracts subject to exact release qualification. No
implementation or admission evidence was created during decomposition.

The maintainer subsequently approved seven dependency-ordered TASK slices:
[TASK-00227](../tasks/00227-TASK.md) accepts the concrete persistence/restoration design;
[TASK-00228](../tasks/00228-TASK.md) implements shared cohort admission;
[TASK-00229](../tasks/00229-TASK.md) persists original issuance and atomic retirement;
[TASK-00230](../tasks/00230-TASK.md) completes recoverable delivery/maintenance persistence and queries;
[TASK-00231](../tasks/00231-TASK.md) integrates every authority-changing writer;
[TASK-00232](../tasks/00232-TASK.md) operates controlled cohort changes and actual old-writer exclusion; and
[TASK-00233](../tasks/00233-TASK.md) reconciles controlled restoration against independent history and live effects.

TASK-00231–00233 remain `needs-info` until adjacent TICKET-00060/00061 and relevant policy-owner decompositions
identify their actual destination/key/delegation, sink/worker/recovery and activation/use TASK prerequisites.
Add precise blocker edges before readiness; do not invent IDs or make primitive implementations depend on this
entire TICKET's completion. They consume TASK-00228–00230, while later integrated writer/switch/restore acceptance
consumes their real participants. This preserves a non-circular qualification path without enabling a partial
composition. TASK order is unranked; the existing Board priorities and active work remain unchanged.

These records implement nothing and qualify no live operation. Continue map-wide TASK planning with
[TICKET-00060](00060-TICKET.md), not execution selection or cohort enablement.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 333 records,
246 active; `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero violations. Repetitive
deprecation warnings remain. Evidence: `.runs/build/20261012T153305Z-34306`; summary log:
`.runs/build/ticket-00059-to-tasks.log`. These checks validate the planning change and existing repository, not
future cohort, writer, credential or restoration behavior. Independent review, publication and merge are separate.
