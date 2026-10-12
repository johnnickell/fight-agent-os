---
id: TICKET-00060
epic: EPIC-00014
title: Deliver protected Agent credential lifecycle and recovery
status: ready-for-agent
---

# Deliver protected Agent credential lifecycle and recovery

## Problem statement

Agent provisioning cannot be called usable because an Agent row or generated key exists. Credential generation,
fixed-sink delivery, activation, continuing use, uncertain-outcome recovery and revocation must remain
separate and securely composable; the existing human grant-delivery worker is not an Agent credential worker.

## Solution and boundaries

Use the selected package's public provisioning, rotation, revocation, status, delivery, discovery, recovery and
maintenance contracts with TICKET-00059's current persisted operation/cohort fence. Retain the original scoped
operation key and canonical request across retries. Enroll holder and destination through protected operator
paths; generate separate per-holder credentials and encrypt authentication envelopes separately from delivery
copies. Use **server-registered** destinations and immutable exact-tuple staging through a fixed protected sink
with globally deduplicated delivery IDs, stable-slot ordering and verified receipts. Persist claim and admission
before invoking the sink **outside** any database transaction; commit only a matching current outcome. Schedule
finite discovery/retry after restart regardless of event dispatch. Reconcile same-key uncertainty/receipt-first
recovery without issuing another secret. Support authorized rotation, revocation, expiry, rewrap, inert-entry
cleanup, key-reference counting and safe key-retirement holds; do not promise physical deletion from one count.

No browser raw key, arbitrary sink URL, lease/claim/receipt endpoint, consumer-owned parallel credential queue,
external effect inside a transaction, automatic new operation on ambiguous commit, legacy issuance mode or
exactly-once delivery. The historical WF-014 raw-secret-return language is superseded by this package's fixed
protected-sink contract; never send a key to the browser or a Pi child as a workaround.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Request provision or rotation | Package `AgentProvisioningService` / `AgentCredentialRotationService` with retained key and authorized scope | Current actor, target, destination, original operation and capacity | Package issuance or safe warning after confirmed commit | One bounded operation with durable protected material; issuance is not delivery or use |
| Deliver and reconcile | Package delivery and scheduled recovery with actual worker authorization | Due work, original receipt, live destination/cohort and pinned policy | Package receipt/failure outcomes only after relevant commit | Exact bytes reach fixed sink at least once; lost outcome resolves original ID and ordering |
| Revoke and maintain | Package lifecycle and maintenance services under protected grants | Safe operation status, key references, finite maintenance pages | Package terminal/maintenance facts after commit | Continuing use stops on revocation; inert material is rewrapped, expired or cleaned safely |

## Validation and permissions

Human UI may submit authorized **intent** and read only safe status through TICKET-00063; worker, key,
destination, claim, receipt and recovery operations use separate current trusted scope, actor/target and
maintenance grants on all paths. Neither a Super Admin label nor model-generated instructions authorize
privileged material access. Wrong holder, stale request/claim, incompatible cohort, revoked authority, key
outage or missing sink makes the operation unavailable/uncertain rather than activating a credential. A delivered
credential is not an activated or usable Agent until TICKET-00061 validates the actual caller and scope.

## Acceptance and evidence

- Actual cipher, key store, sink, receipt/order, deduplication and scheduler behavior meets selected package
  contract through competing workers, crash/restart, stale claims, slot races and changed authority.
- Same-key retries never generate a second credential; rotation atomically retires the predecessor, and revocation
  immediately blocks later use without relying on event delivery. Inspect logs/evidence for secret exposure.
- Maintenance/recovery uses original references and finite pages, accounts for in-flight envelopes/backups before
  physical key retirement, and reports confirmed versus indeterminate outcomes truthfully.
- Qualify actual consumer ports and exercised package-bound scenarios with real PostgreSQL/provider/sink tools,
  owned behavior tests and `./bin/build`, recording exact package/source and limitations. No proof-only product.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00234](../tasks/00234-TASK.md) | Enroll protected holders destinations and worker authority | ready-for-agent |
| [TASK-00235](../tasks/00235-TASK.md) | Protect Agent credentials with independent encryption and key admission | ready-for-agent |
| [TASK-00236](../tasks/00236-TASK.md) | Stage immutable credentials in the fixed protected sink | ready-for-agent |
| [TASK-00237](../tasks/00237-TASK.md) | Deliver authorized lifecycle intent and safe original-operation status | ready-for-agent |
| [TASK-00238](../tasks/00238-TASK.md) | Deliver and reconcile one exact credential attempt | ready-for-agent |
| [TASK-00239](../tasks/00239-TASK.md) | Schedule finite recovery independently of events and callers | ready-for-agent |
| [TASK-00240](../tasks/00240-TASK.md) | Maintain original material and hold unsafe key retirement | ready-for-agent |
| [TASK-00241](../tasks/00241-TASK.md) | Integrate fail-closed lifecycle readiness and operator recovery | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 2 of EPIC-00014's seven-TICKET split. Depends on
[TICKET-00059](00059-TICKET.md) for operation/cohort persistence and supplies credentials to
[TICKET-00061](00061-TICKET.md) and authorized status/intent to [TICKET-00063](00063-TICKET.md).
No worker, sink, credential or provider enrollment is implemented by this record.

The maintainer approved eight dependency-ordered TASK slices:
[TASK-00234](../tasks/00234-TASK.md) enrolls protected holders/destinations and worker authority;
[TASK-00235](../tasks/00235-TASK.md) supplies independent encryption and key admission;
[TASK-00236](../tasks/00236-TASK.md) stages immutable credentials in the fixed sink with receipt lookup/cleanup;
[TASK-00237](../tasks/00237-TASK.md) delivers authorized lifecycle intent and safe original-operation status;
[TASK-00238](../tasks/00238-TASK.md) delivers/reconciles one exact attempt;
[TASK-00239](../tasks/00239-TASK.md) schedules finite recovery independently of events/callers;
[TASK-00240](../tasks/00240-TASK.md) maintains original material and holds unsafe key retirement; and
[TASK-00241](../tasks/00241-TASK.md) integrates fail-closed operational readiness and recovery.

TASK-00234–00240 consume persistence/admission primitives, not completion of TICKET-00059. Their concrete
participants are now linked into TASK-00231–00233's writer/switch/restore prerequisites. TASK-00241 follows
TASK-00233 and TICKET-00061's now-accepted [TASK-00242](../tasks/00242-TASK.md) through
[TASK-00245](../tasks/00245-TASK.md). TASK-00231–00233 and TASK-00241 are now `ready-for-agent` behind those
explicit unfinished blockers; TASK-00244/00245's real Team/Harness/host/origin prerequisites remain `needs-info`.
This resolves direct mapping, not qualification. No primitive may depend on TASK-00241 or the whole TICKET's
completion; integrated acceptance follows actual components, and TASK-00247's Explorer integration follows it.
A component pass cannot advertise positive readiness for an incomplete composition or substitute for real tools,
independent witness/history and current activation/use evidence.

This split introduces no browser secret route, parallel credential queue, physical key destruction or competing
Harness/Workflow owner. Human managed Permissions and Team-bound creation stay with TICKET-00063; actual Agent
HMAC, activation and broker-use controls stay with TICKET-00061. Safe operator enrollment, even when implemented,
is not a production grant or permission to launch. Orders remain unranked and Board active work is unchanged.

Next planning operation: `/skill:to-tasks TICKET-00062`, mapping its Team primitives without parent cycles,
then continue the remaining map TICKET decompositions. Implementation/worktree choice, independent review,
publication, merge and live operational effects remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 341 records,
254 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations. Repetitive deprecation warnings remain. Evidence: `.runs/build/20261012T160819Z-36426`; summary log:
`.runs/build/ticket-00060-to-tasks.log`. These are planning/repository checks, not fresh credential, sink, worker,
activation/use or restoration qualification. Independent review and publication have not been performed.
