# Shared DBAL transactions

## Current composition

TASK-00572 adopts released Common **v1.5.0**, source/Composer lock reference
`06fb5cd3720af9cf8295198f74040d95b64c5155`, with manifest constraint `~1.5.0`.
Access Control remains v0.8.0; its Common `^1.3` requirement admits this release.
This is consumer adoption, not a supplier release or qualification of TASK-00573's Role-audit replacement.

[Shared persistence composition](../../config/common/persistence.php) binds the canonical
`Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork` directly to the registered DBAL
`Connection`, behind the unchanged `TransactionalUnitOfWork`. Identity, authority, grant/session, required audit,
credential delivery, Workspace and Project participants keep that exact connection. Owning handlers/coordinators
complete their operation; repositories do not independently commit or flush. Existing conflict-recovery savepoints
remain subordinate to the enclosing transaction, not nested consumer units of work.

The former EntityManager had empty metadata and existed only to support the ORM transaction adapter. No surviving
owned or Access Control consumer requires ORM. The direct `doctrine/orm` dependency and unused transitive ORM
packages are removed; this does not replace genuine ORM persistence or prohibit its justified future use.
The [historical Project ownership picture](project-registration/README.md) retains unchanged generic-port and
handler/event ownership; only its concrete transaction composition has changed.

**Required Role audit remains:** `RoleMutationUnitOfWork` still delegates to this same generic boundary and records
atomic Role audit. Its inferred audit design is pending replacement under TASK-00573, not approved as the final
architecture and not removed merely because DBAL is now available.

## Completion and failure recovery

- `commitTransactional(callable): mixed` preserves the callback result. Aggregate, required audit and justified
  durable-intent writes commit together. The generic boundary has no business-aware event/audit interception.
- A caller-owned/nested transaction or auto-commit-off connection is refused before the callback. Do not wrap
  package-handler dispatch in an outer transaction or use savepoints to evade this restriction.
- A callback failure with confirmed rollback propagates its original exception; the DBAL boundary remains usable.
  There is no EntityManager to rebuild. Required audit failure rolls back business changes and durable intent.
- Begin, commit or rollback uncertainty raises canonical `DbalTransactionOutcomeUncertain` and closes the boundary.
  A lost commit acknowledgement may follow a real commit. Cleanup is not proof of rollback. End that invocation,
  obtain fresh composition/connection and inspect authoritative state under the operation's normal admission.
  Do not blindly retry or translate an unknown driver failure into absence or a safe uniqueness conflict.
- Success events follow acknowledged commit. Their publication can fail after state and required audit commit;
  the original state remains authoritative. Ordinary Project creation recovers through fresh authorized reads,
  not an original-response journal. Credential delivery retains its separately justified committed intent and
  bounded recovery; external effects are not rollback-safe or exactly once.

There is no schema migration, data disposal, permission, endpoint or response contract change in this adoption.
Installation uses the committed Composer lock through `./bin/composer install`; existing development databases
remain intact. Test resets use only the guarded isolated test database.

## Consumer verification

Existing PostgreSQL suites exercise expected-state/reference conflicts, identity and session atomicity,
required audit failures, durable credential intent and recovery without immediate publication, and post-commit
publication failures. The Project integration also loses an acknowledgement *after a real PostgreSQL commit*,
verifying retained Project/audit state, no invented success event, safe failure and fresh-query/conflict recovery.
It does not retest the upstream generic adapter in isolation.

Use focused `./bin/phpunit --testsuite Postgres` after `./bin/database test-reset`, followed by normal `./bin/build`.
Composer/dependency/composition checks use their owning tools, not product wiring tests. Exact results, warnings,
release/caller inventory and native runtime locators belong to TASK-00572's canonical Work handoff. Independent
Review and applicable concurrency/failure QA remain separate from implementation verification; no browser behavior
or HTTPS routing changes are introduced by this substrate adoption.
