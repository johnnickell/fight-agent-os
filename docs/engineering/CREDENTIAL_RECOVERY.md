# Internal credential recovery

TASK-00024 supplies bounded internal cleanup and delivery recovery using Fight Access Control v0.5.0. These are
one-shot console operations, not a scheduler or product endpoint. No production provider or recovery schedule is enrolled.

## Adoption and data safety

The committed Composer lock selects Access Control v0.5.0 (`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`) and
Common v1.3.0 (`7de6cad6e8a9752973ad9f8e27e285b0c1510582`). Install that lock, then apply migrations through the
ordinary authorized `./bin/database migrate` operation before using recovery. TASK verification applies migrations
only to the guarded test database; it does not migrate development or production.

`Version20261006220706` adopts non-null Permission tiers, mandatory email-grant reservation bindings and expiry-order
indexes. It locks affected tables while checking data. Valid legacy custom-null tiers become `ADMIN_SAFE`; malformed
managed tiers, reserved custom Super Admin roles and forbidden protected membership abort without rewriting authority.
**Any existing email-grant history aborts adoption**: a matching current email cannot establish a historical reservation
revision. Do not delete rows, infer/backfill that binding or reset a non-test database to bypass the guard. Obtain a
separate reconciliation decision, preserve the database and old application/lock together, and retry only with an
approved compatible dataset. Downgrade is deliberately refused because it would discard reservation bindings; recovery
requires an approved database/application restore or forward correction, not automatic rollback to old binaries.

The inspected local development database had zero Permissions, Roles, memberships and email grants. This is not an
inventory of another installation. Future targets require their own inventory, backup, migration and deployment authority.
Permission-reference writes now require a transaction at **READ COMMITTED** isolation, so a snapshot taken before the
shared tier/reference fence cannot bypass current membership. Custom/ordinary Role grants and protected promotion share
that fence. Agent/Feature persistence and entry paths are absent; adding them must extend reference fencing. Broader
entry-point authorization, Agent contracts and protected managed reconciliation remain gated by TASK-00126.

## Commands and authority

```sh
./bin/credential-delivery run --internal --page-size=100 --capacity=10
./bin/credential-delivery expire --internal --expiry-page-size=50 --expiry-pages=10
./bin/credential-delivery status activation DELIVERY_ID --internal
./bin/credential-delivery status password_reset DELIVERY_ID --internal
```

Replace `DELIVERY_ID` with a package-generated delivery-generation ID known to the operator. Status also supports
`email_change`. Cleanup handles invitation/reset delivery expiry and email authority/reservation expiry; **email-change
provider delivery remains unregistered**. No new public routes, product email-change journey or generic job types exist.

`--internal` acknowledges an internal operation; it is **not authentication or end-user authorization**. Restrict CLI/OS
access and supply least-privilege recovery credentials. Use a separate migration identity in deployment. Local Compose
identities are development/test identities, not deployed privilege certification. Do not repoint the wrapper at another
database without separate authority. Tests use only the explicitly guarded test database.

`run` validates the provider and both delivery ciphers before any mutation, performs bounded cleanup, then offers one
bounded live-delivery page. It requires the original external `APP_CREDENTIAL_DELIVERY_KEY`. Local/test defaults to the
retryable null adapter; `APP_CREDENTIAL_DELIVERY_ADAPTER=memory` selects deterministic simulation. Neither sends credentials.
Production-like composition fails closed without a real provider. **`expire` and `status` do not resolve keys, ciphers or
providers**; cleanup can remove expired bytes even when the delivery key is unavailable.

## Bounds and results

Live delivery accepts `--page-size` and `--capacity` from 1–1000 (defaults 100), using their minimum. Capacity bounds
sequential offers, not parallelism. `--lease-seconds=300` only states the package's fixed five-minute lease expectation,
capped at grant expiry; all other values are rejected. Retry/backoff, attempts and lifecycle remain package-owned.

Both `run` and `expire` accept `--expiry-page-size=1..100` (default 50) and `--expiry-pages=1..10` (default 10).
One captured clock boundary applies to the cleanup cycle. Every page is synchronously dispatched before rediscovery;
there are at most 1000 cleanup dispatches and one final bounded observation. The query selects eligible latest issued
generations before ordering by expiry instant, delivery ID and purpose. Invitation/reset cleanup requires recoverable
material. Email authority cleanup includes already-delivered/permanent/delivery-expired work, clearing only its exact
bound User reservation in the same transaction.

Cleanup reports `pages`, `dispatched`, `remaining`, `stalled`, and `budget_exhausted` under `expiry`.
`remaining` is the size of the last bounded observation, **not a total backlog count**. `dispatched` means the handler
returned, not that cleanup succeeded. Unchanged identities/revisions/status on rediscovery set `stalled=true` and stop
this invocation; investigate unresolved persisted work without a busy loop or synthetic success. A progressing queue
that reaches its page budget sets `budget_exhausted=true`; a fresh invocation continues it. An empty last observation
says nothing about work arriving or expiring after that observation.

`run` additionally reports `discovered`, `offered`, `dispatched`, `contended`, `unsupported`, and `stopped` under
`delivery`. Cleanup stall or failure prevents live dispatch. Budget exhaustion alone does not prevent still-live work.
Delivery handler return counts do not mean provider success; inspect safe status. Unsupported email delivery is counted
but never dispatched. A stale/non-retryable package delivery failure stops that page because Doctrine may close its
manager on rollback; restart with a fresh invocation. Unexpected failures stop immediately with a constant diagnostic.

| Exit | Meaning |
|---|---|
| 0 | Bounded work completed, cleanup budget reached, delivery contention stopped the page, or status found |
| 1 | Runtime/preflight failure, unchanged cleanup work, or unsupported live work |
| 2 | Missing internal acknowledgement, invalid command, ID or operational bound |
| 3 | Exact generation status not found |

Status returns the exact package-approved safe View: purpose, delivery/User IDs, revision, persisted delivery status,
due time, expiry, attempts and classified failure/outcome timing. For claimed work, `due_at` is lease end. It excludes
destinations, credentials, hashes, ciphertext, claim tokens, URLs and arbitrary provider diagnostics. Email delivery
status alone is not email authority status. Cleanup facts are package post-commit events, not a new durable audit/outbox.

## Guarantees and evidence limits

Claim commit precedes provider invocation; outcome and audit commit afterward. Originating rollback creates no work;
lost immediate dispatch recovers without the original event. Expired leases can be reclaimed, and downtime-expired
claims lose their material and claim state while preserving prior attempts/outcomes. Retry failures use package backoff
until expiry. Cancellation, replacement, permanent failure and direct expiry destroy live-row material under package policy.

Expected-state repository writes fence stale provider outcomes and cleanup against successors. Cleanup rolls back on
write failure; restart discovers persisted work again. Duplicate cleanup commands are no-ops after a committed expiry.
Email expiry fails closed on absent/mismatched or newer same-email reservations. Reclaimed-claim regressions and actual
PostgreSQL rollback, competing snapshots, stale-provider and grant/promotion winner-order probes accompany the TASK.

At-least-once delivery and the provider-success/outcome-commit crash window still permit duplicate effects with one
stable delivery identity. No exactly-once guarantee, durable event replay, physical erasure of backups/WAL, scheduler
operation, real-provider idempotency or production privilege certification follows. Tests use fixed clocks, separate
connections and controlled failure boundaries, not OS-process kills or a deployed scheduler. Independent review and
behavioral QA remain separate from implementation evidence.
