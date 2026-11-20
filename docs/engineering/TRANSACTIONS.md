# Shared DBAL transactions

## Current composition

TASK-00572 introduced Common's canonical DBAL transaction adapter at v1.5.0. TASK-00573's source adopts
Common **v1.5.1** with Access Control **v0.11.0**; see the [Role runbook](CUSTOM_ROLES.md#v0110-source-cutover)
for exact source references, migration constraints and the consumer verification record.

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

The released Role handlers write required audit explicitly on the shared connection, before commit. The consumer
transaction decorator and its event-based audit inference are removed. Read the Role runbook for history and
failure recovery across the adoption.

## Planned Agent and email-change boundaries

The [maintainer amendment](../../planning/adr/0001-application-ownership-and-orchestration.md#maintainer-accesscontrol-audit-amendment)
requires separate transactions for package Agent creation, owned Team assignment and package Permission application.
Agent OS owns truthful partial completion, use exclusion and same-intent recovery; do not manufacture an all-three
transaction. Template review/reconciliation/no-change evidence belongs to Agent OS, actual Permission-change audit
to AccessControl. Required state/audit atomicity remains within each owner, not a combined package review journal.
Supported atomic owned old-mailbox intent participation remains agreed but unimplemented/unreleased; required
request/confirmation notice atomicity is not removed by the Agent setup decision.

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

The TASK-00572 DBAL-only adoption did not change schema, data, permissions, endpoints or response contracts.
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
