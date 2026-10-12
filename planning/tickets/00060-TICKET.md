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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 2 of EPIC-00014's seven-TICKET split. Depends on
[TICKET-00059](00059-TICKET.md) for operation/cohort persistence and supplies credentials to
[TICKET-00061](00061-TICKET.md) and authorized status/intent to [TICKET-00063](00063-TICKET.md).
No worker, sink, credential or provider enrollment is implemented by this record.
