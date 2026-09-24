---
id: TICKET-00011
epic: EPIC-00003
title: Establish recoverable external-effect delivery
status: needs-info
---

# Establish recoverable external-effect delivery

## Problem statement

Invitation, password-reset, and later email-change credential effects must not disappear if the process fails between committing package-owned delivery state and contacting an external provider. Performing email or network effects inside the transaction is unsafe, and exactly-once delivery cannot be promised. Durable database audit evidence is authoritative for the current foundation; external audit publication is not yet a demonstrated requirement.

## Solution and boundaries

After qualified Fight Access Control `v0.3.0` is tagged and locked, compose its package-owned credential-delivery queue. Persist exact package grant/delivery contracts on the shared connection, dispatch direct package handlers only after committed claims, and schedule package due-work discovery for restart recovery. Package policy owns leases, retry-until-expiry, terminal outcomes, stale-claim fencing, and credential materialization; Agent OS owns repository/provider adapters, scheduling capacity, and composition.

Establish secret-safe credential provider adapters with deterministic null/in-memory test implementations. Prove the commit/dispatch crash window, provider-success/outcome-commit crash, duplicate processing, package retry policy, competing workers, terminal ciphertext destruction, and credential redaction before EPIC-00004 relies on delivery.

Out of scope: a consumer credential outbox, external audit publication, exactly-once claims, provider-specific production enrollment, external effects inside business transactions, invitation/reset policy duplication, product email-change wiring, general event sourcing, and unrelated background-job infrastructure.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record required credential work | The originating package command changes authoritative state | Package repositories inspect current generation and delivery state | Post-commit events may request immediate coordination | Aggregate, encrypted delivery material, and audit evidence commit atomically; package state is the sole queue |
| Dispatch due package work | A direct package delivery handler claims and attempts one exact generation | Package due-work query returns deterministic secret-free references | Package outcome coordination follows durable state | Claim commits, provider runs with no transaction open, and matching outcome commits separately |
| Retry a recoverable failure | The same package operation reclaims work when package due time or lease expiry permits | Query attempts, next due time, lease, grant expiry, and stable delivery ID | No duplicate business event is inferred | At-least-once processing reuses delivery ID and retries under package policy until grant expiry |
| Recover after commit/dispatch crash | An Agent OS runner dispatches package-discovered work | Query pending, due-retry, and expired-lease generations | Original in-memory event may be absent | Committed package work remains discoverable and is eventually attempted |

## Validation and permissions

Package discovery and safe status expose only minimum references and must never expose raw credentials, hashes, ciphertext, tokens, provider secrets, URLs, or arbitrary errors. Package-owned bounded increasing backoff remains eligible until grant expiry; deterministic clocks prove timing, the immutable delivery-generation ID is stable idempotency identity, and competing claims are safe. Delivered and permanent outcomes destroy ciphertext and retain only operator-safe status/audit evidence.

The runner may dispatch only registered direct package handlers for valid discovered package work. End-user permission checks belong to the originating use case; processing authority is an internal operational capability with least-privilege credentials. Provider secrets remain external and never enter package delivery state.

## Acceptance and evidence

- The accepted PostgreSQL/durable-effects ADR and locked package identify package grant delivery state as the sole credential queue.
- Originating state, encrypted delivery material, and audit evidence commit atomically; no provider capability is called inside that transaction.
- Pending, claimed, retryable, delivered, permanent, expired, and abandoned-lease behavior follows package contracts and is observable without exposing secrets.
- Deterministic tests prove rollback, lost immediate dispatch, restart discovery, provider-success/outcome-commit recovery, stable duplicate identity, retry-until-expiry, terminal ciphertext destruction, and competing workers against PostgreSQL where concurrency matters.
- Null/in-memory credential provider adapters support fast behavior tests without network access.
- Logs and safe views redact credentials, hashes, ciphertext, tokens, provider data, URLs, and arbitrary errors.
- Focused delivery/recovery tests and `./bin/build` pass with fresh evidence and warnings.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00022](../tasks/00022-TASK.md) | Establish secret-safe credential provider adapters | ready-for-agent |
| [TASK-00023](../tasks/00023-TASK.md) | Compose direct package credential delivery | ready-for-agent |
| [TASK-00024](../tasks/00024-TASK.md) | Recover and observe package credential delivery | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

This TICKET is `needs-info`: Fight Access Control `v0.2.0` lacks due-work discovery, committed leases, and out-of-
transaction provider orchestration. Accepted upstream EPIC-00006/WF-009/WF-010 select package grant delivery state
as the sole queue for proposed `v0.3.0`. Implementation, qualification, a tagged stable release, and an Agent OS lock
update are required before composition may begin. Consumer outbox or retry-policy work must not bypass that gate.

Implements the durable external-effect direction approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md). It depends on the transaction and durable-intent contract in [TICKET-00009](00009-TICKET.md) and gates EPIC-00004 invitation/recovery delivery.
