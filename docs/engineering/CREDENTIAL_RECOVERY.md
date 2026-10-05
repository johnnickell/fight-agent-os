# Internal credential recovery

TASK-00024 adds a one-shot internal runner, not a scheduler or product endpoint. Its implementation is incomplete:
**downtime-expired delivery cleanup remains an upstream qualification/scope decision**. Do not enroll it as a complete
production recovery system.

## Run one page

Use an authorized local OS session with access to the repository's Docker configuration. Apply recovery-order
indexes through the ordinary authorized `./bin/database migrate` operation before operational enrollment; TASK
verification applied and reversed/reapplied that migration only on the guarded test database:

```sh
./bin/credential-delivery run --internal --page-size=100 --capacity=10
./bin/credential-delivery status activation DELIVERY_ID --internal
./bin/credential-delivery status password_reset DELIVERY_ID --internal
```

Replace `DELIVERY_ID` with a package-generated delivery-generation ID already known to the operator. Status also
supports package `email_change` views; the runner deliberately does not dispatch email-change work.

`--internal` is an explicit operational acknowledgement, **not authentication or end-user authorization**. Restrict
CLI/OS access and supply database credentials scoped to grant recovery and audit writes; use a separate migration
identity in a deployment. The local Compose identities remain development/test identities, not proof of a deployed
least-privilege role. No HTTP route exposes these commands. `./bin/exec` runs a fresh Compose process with its configured
`DATABASE_URL`; do not point it at another database without separate operational authority. PostgreSQL tests use only
the guarded test database through `./bin/database`.

Run requires the external `APP_CREDENTIAL_DELIVERY_KEY` used when the work was issued. Changing or losing it prevents
materialization. Provider composition must be explicit for the environment: local/test defaults to the retryable null
adapter, and `APP_CREDENTIAL_DELIVERY_ADAPTER=memory` opts into a deterministic in-memory simulation. Neither sends
credentials. The command validates provider and both ciphers even for an empty queue. Production-like environments
fail closed because no real provider is enrolled. Status does not decrypt or require provider configuration.

## Bounds and results

Page size and capacity are integers from 1 through 1000; both default to 100. The package query receives their minimum.
The runner processes one deterministic package page sequentially, without polling or creating another queue. Capacity
means maximum offers per invocation, not parallel processes. Start another invocation for the next eligible page;
scheduling cadence and worker enrollment are outside this TASK.

The locked Fight Access Control `v0.3.0` handlers fix claim leases at five minutes, capped at grant expiry.
`--lease-seconds=300` can state that expectation; every other value is rejected. There is no consumer lease override,
retry budget or backoff flag. The package uses increasing retry delays (60 seconds initially, capped at 3600 seconds)
and destroys recoverable material when a retry would reach grant expiry. The runner uses the injected package clock;
production uses system UTC time, while integration tests advance clocks without sleeping.

Run emits only counts: `discovered`, `offered`, `dispatched`, `contended`, `unsupported`, and `stopped`.
**Dispatched means the package handler returned, not that the provider delivered.** Inspect status to distinguish
retryable, permanent and delivered outcomes. Discovery does not reserve work; two workers can discover the same
reference. Package claims and expected-state repository writes fence competing/stale outcomes.

A stale/non-retryable package failure stops the current page (`contended=1`, `stopped=true`) because Doctrine closes
the manager on transaction rollback. Resume with a fresh invocation; do not continue dispatch through the old manager.
Other failures stop with exit 1 and a fixed diagnostic, never arbitrary exception/provider data. Unsupported families
are counted but never dispatched, and require an operator decision rather than adding an arbitrary job handler.

| Exit | Meaning |
|---|---|
| 0 | Page completed or stopped on package contention; or status found |
| 1 | Runtime/preflight failure, or unsupported work detected |
| 2 | Missing internal acknowledgement, invalid command, ID or operational bound |
| 3 | Exact generation status not found |

Status prints the package-approved `CredentialDeliveryStatusView::toArray()` without expanding it: purpose, delivery
and user IDs, revision, status, due time, expiry, attempts and classified failure/outcome timing. For claimed work,
`due_at` is the lease end. It excludes destination, credentials, hashes, ciphertext, claim tokens, URLs and arbitrary
provider messages. Run errors provide no exception details; use safe package state/audit evidence for diagnosis.

## Guarantees and unresolved boundary

Claim commit precedes provider invocation; outcome and audit evidence commit afterward. Originating rollback creates
no discoverable work. A lost post-commit event is recovered by discovery; a lost worker claim is reclaimed after lease
expiry. The provider-success/outcome-commit crash window permits **duplicate effects** with the same immutable delivery
ID. At-least-once attempts and stable provider idempotency identity do not promise exactly-once delivery.

Delivered, permanent and invalidated generations have no recoverable ciphertext. Retry backoff can also terminalize
a generation as `expired`. However, package due discovery excludes generations already past expiry. If the process
stays down through expiry, persisted `pending`, `retry_pending` or `claimed` state can retain ciphertext; the status
query reports persisted state rather than synthesizing terminal cleanup. The runner must not claim this gap is solved
or implement a consumer-owned transition policy. TASK-00024 retains its terminalization criterion pending the owner's
upstream-capability/scope decision.

Deterministic integration probes use distinct PostgreSQL connections and controlled boundaries after originating
commit, after claim commit, and after provider acceptance/before outcome commit. They overlap discovered snapshots
and stale claim owners with no sleeps, external provider or network-dependent delivery test. OS-process crash,
scheduler enrollment, real provider idempotency and deployment privilege hardening remain separate from those probes.
