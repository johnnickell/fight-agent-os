---
id: TICKET-00011
epic: EPIC-00003
title: Establish recoverable external-effect delivery
status: ready-for-agent
---

# Establish recoverable external-effect delivery

## Problem statement

Invitation, recovery, and audit effects must not disappear if the process fails between committing authoritative state and contacting an external provider. Performing email or network effects inside the transaction is also unsafe, and exactly-once delivery cannot be promised.

## Solution and boundaries

Implement the recoverable durable-intent mechanism accepted by the PostgreSQL ADR. Commit authoritative state and required delivery intent on the same transactional connection, dispatch only after commit, and process pending work with explicit retry, idempotency, leasing/concurrency, and at-least-once semantics. Record safe delivery state and diagnostics sufficient for later recovery UI.

Establish mail and audit capability seams with deterministic null/in-memory test adapters. Prove the commit/dispatch crash window, duplicate processing, retries, competing workers, terminal outcomes, and credential redaction before EPIC-00004 relies on delivery.

Out of scope: exactly-once claims, provider-specific production enrollment, external effects inside business transactions, invitation UI/business policy, general event sourcing, and unrelated background-job infrastructure.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record a required effect | The originating package/application command changes authoritative state | Repositories inspect existing state and idempotency/delivery references | Post-commit events may request immediate coordination; durable intent remains authoritative for recovery | Business state and required delivery intent commit atomically |
| Dispatch pending intent | A delivery-processing operation claims and attempts eligible intent | Query pending/retryable delivery state with deterministic ordering and locking | Success/failure coordination may be emitted only after durable outcome recording | External mail/audit capability is invoked after commit, and attempt/outcome state is recorded |
| Retry a recoverable failure | A bounded retry operation reclaims eligible intent | Query attempts, schedule, terminal state, and idempotency key | No duplicate business event is inferred from a repeated attempt | At-least-once processing retries safely without losing or corrupting intent |
| Recover after commit/dispatch crash | A recovery runner processes committed unhandled intent | Query durable pending state after simulated process loss | Original in-memory event may be absent; durable intent still drives work | Required effect remains discoverable and is eventually attempted |

## Validation and permissions

Intent payloads must contain only the minimum safe provider input and must never expose raw activation/reset credentials through ordinary reads, logs, errors, or audit metadata. Retry policy must be bounded/configurable, times deterministic in tests, idempotency stable, and competing claims safe. Permanent versus recoverable failures require explicit state and operator-safe diagnostics.

The processor may execute only registered capability types from valid committed intent. End-user permission checks belong to the originating use case; processing authority is an internal operational capability with least-privilege credentials. Provider secrets remain external and are never persisted in intent.

## Acceptance and evidence

- The accepted PostgreSQL/durable-effects ADR names the implemented mechanism and consistency guarantees.
- State and required delivery intent commit atomically; external capabilities are not called inside that transaction.
- Pending, claimed, retryable, delivered, and terminal behavior is durable and observable without exposing secrets.
- Deterministic tests prove rollback, post-commit dispatch, simulated crash recovery, duplicate attempt safety, bounded retry, and competing-worker behavior against PostgreSQL where concurrency matters.
- Null/in-memory mail and audit adapters support fast behavior tests without network access.
- Logs and safe views redact credentials, tokens, secret provider data, and credential-bearing URLs.
- Focused delivery/recovery tests and `./bin/build` pass with fresh evidence and warnings.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00022](../tasks/00022-TASK.md) | Establish secret-safe outbound effect capabilities | ready-for-agent |
| [TASK-00023](../tasks/00023-TASK.md) | Process registered durable intents after commit | ready-for-agent |
| [TASK-00024](../tasks/00024-TASK.md) | Recover retry and observe durable effect delivery | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the durable external-effect direction approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md). It depends on the transaction and durable-intent contract in [TICKET-00009](00009-TICKET.md) and gates EPIC-00004 invitation/recovery delivery.
