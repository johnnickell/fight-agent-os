# ADR 0002: Define PostgreSQL consistency and durable effects

- **Status:** Accepted
- **Date:** 2026-09-23
- **Decision owners:** Fight Agent OS maintainers
- **Acceptance:** Explicitly accepted by the human maintainer under TASK-00011 on 2026-09-23

## Context

Fight Agent OS must persist the foundation identity, authorization, session, grant, audit, and required-delivery
state owned by Fight Access Control without weakening that package's repository contracts. The locked dependency
baseline is Fight Access Control `v0.2.0` at `c986b488ef8490c16c7a69e4675b5348f8d6dbf5` and Fight Common `v1.2.0`
at `a2cd615d9b5064c9c30e994655536176249cd73b`.

The package contracts require more than ordinary create/read/update behavior. They include complete expected-state
comparisons, independently advancing revisions, transaction-duration reference fences, coupled user-authority and
session insertion, one-time grant succession, deterministic latest-generation resolution, historical credential
digest detection, and rollback with the caller's Unit of Work. Required email or other provider effects must also
survive a process failure after business commit but before immediate dispatch.

This decision specializes [EPIC-00003](../epics/00003-EPIC.md), the closed
[WF-004 decision](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md),
[TICKET-00009](../tickets/00009-TICKET.md), and the ownership boundary in
[ADR 0001](0001-application-ownership-and-orchestration.md). It gates schema and adapter work beginning with
[TASK-00012](../tasks/00012-TASK.md).

## Decision

### PostgreSQL and schema authority

PostgreSQL is the authoritative durable store for local development, integration tests, and production. Production
persistence, constraint, transaction, locking, and competing-write claims require PostgreSQL evidence. SQLite may
be used only by genuinely database-independent unit tests and never as evidence for these contracts.

Doctrine Migrations is the only schema-evolution path. Every table, column, index, constraint, trigger if one is
unavoidably selected, and data migration is versioned. Identifiers are `snake_case`. Runtime requests, repository
constructors, application boot, and tests must not create or update schema opportunistically. Deployment or an
explicit guarded development/test command applies migrations before application traffic.

Database constraints are authoritative for stable identifiers, canonical names, unique credential digests,
idempotency identities, membership uniqueness, and references. Repository checks improve errors but never replace
constraints. Migrations must give important constraints stable names so adapters can translate only known
violations into contract-safe conflicts; raw SQL, values, credentials, connection details, and internal identifiers
must not cross the Adapter boundary.

### One connection and one transaction boundary

One container-scoped Doctrine `EntityManagerInterface` owns one DBAL `Connection`. Every ORM mapping, targeted
DBAL repository operation, audit write, and durable-intent write participating in a use case receives that exact
connection. Opening an independent write connection inside a business operation is prohibited. Separate
connections are allowed only for explicit read-only operational work or controlled concurrency tests and never to
pretend that their writes belong to the caller's transaction.

`DoctrineTransactionalUnitOfWork::commitTransactional()` is the canonical transaction boundary. The Application
handler or justified orchestrator invokes it once around all required state, audit, and durable-intent writes. Its
`EntityManager::wrapInTransaction()` flushes and commits on success and rolls back on failure. Nested transactional
execution remains an error; repositories neither begin nor commit transactions and must not call `flush()` as an
independent completion boundary.

Fight Access Control `v0.2.0` handlers type the deprecated Fight Common `UnitOfWork`, although inspected handlers
call only `commitTransactional()`. Until a tagged package release accepts `TransactionalUnitOfWork`, composition
must bind that exact package capability to a narrow Adapter compatibility implementation that delegates
`commitTransactional()` and `isClosed()` to the singleton `DoctrineTransactionalUnitOfWork`. Its deprecated
`commit()` operation must fail closed rather than flush outside the canonical boundary. This is required
compatibility, not an owned alias for the package contract, and is removed when the stable package signature is
upgraded. The deprecated `DoctrineUnitOfWork` must not become a second transaction authority.

Events preserve their package or application ownership. Immediate in-process dispatch and any provider attempt
occur only after a successful commit. A post-commit callback is an optimization, not recovery authority.

### Mapping policy

Repositories implement the installed package interfaces directly and return exact package entities or declared
`ResultSet` values, never persistence records. ORM is preferred only where external mapping of the package entity
faithfully represents all state and transitions. Package source is not modified and persistence attributes are not
added to it. Adapter-private row models are allowed only behind an explicit mapper that reconstructs the public
entity through supported package APIs.

Reflection writes into package private state, PHP serialization, duplicated aggregate policy, and lossy shadow
state are prohibited. If the locked package cannot be reconstructed exactly through supported APIs or qualified
external ORM mapping, implementation stops for a separately authorized upstream package change and tagged release;
it does not guess. Join tables, claim tables, and durable-intent rows are Adapter persistence models and must not
leak into Domain, Application, HTTP, logs, or events.

Use ORM for faithful aggregate scalar hydration and ordinary identity lookups where it remains transparent. Use
DBAL on the shared connection for compare-and-swap statements, explicit lock acquisition, multi-row succession,
`FOR UPDATE SKIP LOCKED` claiming, constraint-sensitive inserts, and queries whose ordering or locking contract is
clearer in SQL. Choosing DBAL does not move Domain transition rules into SQL: adapters verify the package-produced
predecessor/replacement transition, then enforce authority and atomicity against stored state.

All paginated queries use an explicit deterministic order; no repository relies on physical row order. Unless a
contract later specifies a stronger user-visible order, permission, role, and user pages order by `created_at,
id`, active sessions by `created_at, id`, and managed subsets by the same keys. `getByIds()` removes repeated input
identifiers and preserves first-requested order while omitting missing identities. Latest grant lookup uses an
explicit per-user generation number, then stable ID as a defensive tie-breaker; uniqueness prevents two latest
generations.

### Contract inventory and persistence strategy

| Contract | Mapping and authoritative constraints | Comparison, locking, and ordering |
|---|---|---|
| `PermissionRepository` | Permission identity, canonical name, managed flag/tier, and timestamps are externally mapped; IDs and canonical names are unique, and managed tier/flag shape is checked | Adds and lookups may use ORM. Replacement is a DBAL conditional update comparing the complete expected tuple. Remove takes the shared permission-reference fence, compares the complete row, and succeeds only when no role membership references it |
| `RoleRepository` | Role scalar state is mapped separately from a unique role-permission join table with foreign keys to both authorities; role IDs and canonical names are unique | Add/replace/validate take the permission-reference fence and lock referenced permissions in stable ID order. Replacement compares the complete expected role and exact membership set. Remove takes the role-reference fence and rejects assigned roles. Containing/managed/page queries use explicit stable ordering |
| `UserRepository` | User scalar state, password hash, lifecycle, authentication/assignment/email revisions, role assignments, and canonical/live-reservation email claims are persisted. A normalized email-claim table gives one unique canonical value across canonical addresses and live reservations; assignments have unique pairs and foreign keys | Complete-state conditional operations update only the allowed field group and exact revision increments. A per-user authentication-authority transaction lock serializes authority replacement, reset scans, confirmation, and coupled session insertion. Role assignment uses the role-reference fence. `replaceAuthenticationAuthorityAndAddRefreshSession()` uses targeted DBAL on the shared connection so neither half can commit alone |
| `RefreshSessionRepository` | Session ownership, current one-way credential digest, historical used digests, expiry, authentication version, remembered/revoked state, and revision are persisted with unique IDs/digests and user references | Replacement is `UPDATE ... WHERE id = ? AND revision = ?` plus complete immutable-state checks and exact `+1` revision. Active scans evaluate both expiries and revocation at the supplied time. Current and used digest indexes support exact lookup without storing raw credentials |
| `ActivationGrantRepository` | Purpose-specific grant generations and owned delivery generations store only package-approved credential digests plus recoverable encrypted delivery ciphertext; IDs, delivery IDs, per-user generation numbers, and per-user historical digests are unique and user references are enforced | A per-user grant transaction lock plus latest-row `FOR UPDATE` serializes add, replace, `replaceWithSuccessor`, and `addSuccessor`. The adapter compares complete security-relevant predecessor state and exact next revision. Terminalization and successor insert are one transaction. Latest order is generation descending |
| `PasswordResetGrantRepository` | Separate purpose-specific tables use the same safe shape without sharing activation identity or credential namespaces accidentally; raw reset credentials are never stored | A per-user reset-grant transaction lock plus latest-row `FOR UPDATE` enforces complete-state replacement, terminal append, and atomic succession. Purpose, ownership, exact next revision, terminal state, and historical digest freshness are checked before mutation |
| `AuditEvidenceRepository` | Append-only rows store actor ID, action, explicit `user` or `agent` subject type and ID, and bounded deterministic string context. Agent subjects intentionally have no Agent foreign key because Agent persistence is deferred | Inserts participate in the enclosing transaction. No update/delete repository operation is exposed. Context rejects secrets, unsupported values, and configured size excess before persistence; stable created-at/ID ordering supports later operations without implying an audit UI |

The authorization fences are PostgreSQL transaction-scoped advisory locks in separate fixed namespaces for
permission references and role references. All participants acquire the applicable global fence before row locks,
and multi-row locks use ascending stable IDs. Per-user authentication and purpose-specific grant fences use
transaction-scoped advisory locks derived from a collision-resistant stable key and separate namespaces. Schema
foreign keys and unique constraints remain the final authority. This fixed acquisition order prevents a permission
or role from disappearing between validation and mutation and avoids deadlocks caused by inconsistent lock order.

A normal stale predecessor, invalid transition, missing reference, duplicate identity, or known constraint race maps
to the repository's declared `false`, absence, or known safe Domain/Application conflict. It does not expose which
secret-bearing value collided. Unknown driver errors are internal failures, logged only through sanitized
correlation context, and are not reclassified broadly by SQLSTATE alone.

### Durable delivery intent

Required external delivery uses an application-owned transactional outbox named durable delivery intent. Creating
an intent is an Application capability invoked inside the same `commitTransactional()` operation as authoritative
business state and required audit evidence. Each row contains:

- a stable intent ID and globally unique idempotency key;
- one allowlisted effect type and non-secret aggregate/delivery reference;
- minimal provider-neutral, versioned, non-secret routing metadata;
- `pending`, `claimed`, `retryable`, `delivered`, or `terminal` status;
- monotonic revision, attempt count, next-eligible time, lease owner and expiry;
- safe failure class/code without arbitrary provider text; and
- created, updated, delivered/terminal, and retention-eligible timestamps.

Raw activation credentials, reset credentials, password hashes, access/refresh tokens, provider secrets,
credential-bearing URLs, and plaintext mail bodies are prohibited in intent payloads. Invitation/reset intent
references the package delivery ID; later processing resolves the authoritative encrypted delivery material and
uses the registered decrypting capability only after claim. Routine reads and diagnostics expose references and
safe classifications, never ciphertext or decrypted content.

Discovery selects bounded eligible rows in `next_attempt_at, created_at, id` order and claims them in a short
transaction with `FOR UPDATE SKIP LOCKED`, a lease, and an exact revision advance. The transaction commits before
any provider call. Success, retry, or terminal failure is recorded in a later transaction only when lease identity
and expected revision still match. Expired claims become eligible for recovery. Retry delay and maximum attempts
are bounded configuration owned by TICKET-00011 and driven by an injected clock; changing those values does not
change the persistence guarantee.

This closes the business-commit/immediate-dispatch crash window because a committed pending intent remains
queryable after process restart. It does not provide exactly-once delivery. A crash after provider acceptance but
before recording success causes another attempt. The stable idempotency key is supplied to capabilities/providers
that support deduplication, and consumers must tolerate duplicates. Provider execution, process triggering,
retries, operational views, and provider enrollment belong to [TICKET-00011](../tickets/00011-TICKET.md).

Delivered and terminal intent metadata is retained for 90 days after terminalization, after which a separate
explicit maintenance operation may purge it in bounded batches. Pending, claimed, and retryable intents are never
age-purged. Purging intent metadata does not delete package grant or audit history, and retention changes require an
operationally reviewed configuration or migration rather than request-time cleanup.

### Guarded test databases

Integration tests use a dedicated PostgreSQL database and role, never development or production credentials. The
test role is granted ownership only of the test database and has no privilege on known development or production
databases. Secrets remain external to the repository and logs.

Before connecting for migration or reset, tooling requires explicit test mode and parses configuration to require
an allowlisted test host/service, dedicated test role, and database name ending `_test`; it refuses known local,
staging, and production names and roles. After connecting but before mutation, it verifies server-reported host,
`current_database()`, and `current_user` against the same allowlist. Any missing, ambiguous, or mismatched signal
fails closed.

A deterministic reset drops and recreates only the guarded test schema, reapplies every migration from zero, and
loads only explicitly requested fixtures. Tests may use transaction rollback where behavior permits, but
transaction/concurrency suites use isolated committed fixtures and deterministic cleanup. Destructive reset never
runs from application requests or ordinary boot. Controlled races use separate real PostgreSQL connections and
bounded synchronization, not sleeps.

## Alternatives considered

### SQLite for fast repository and concurrency evidence

Rejected. Its types, constraints, lock behavior, isolation, and SQL differ from PostgreSQL and cannot establish the
production contract. Database-independent unit tests may still use no database or an intentional in-memory fake.

### Request-time schema creation or ORM schema update

Rejected. It makes application traffic mutate infrastructure, bypasses reviewable migration history, and produces
non-deterministic environments.

### ORM-only generic repositories

Rejected. Generic identity persistence does not express complete expected-state comparison, reference fences,
coupled writes, grant succession, deterministic claiming, or safe conflict translation. ORM remains useful where
mapping is faithful; targeted DBAL is required for explicit atomic contracts.

### DBAL row arrays as Domain entities

Rejected. Persistence records would leak across the Adapter boundary and could bypass package invariants. Exact
reconstruction is mandatory; unsupported reconstruction requires an upstream release rather than reflection or
serialization tricks.

### One global optimistic version for every aggregate

Rejected. The package intentionally exposes independent authentication, role-assignment, email-reservation,
canonical-email, session, and grant revisions plus complete-state preconditions. A universal version would either
reject unrelated valid work or fail to enforce the actual authority being changed.

### External effects inside the database transaction

Rejected. PostgreSQL cannot roll back accepted email, HTTP, filesystem, or provider effects, while a slow provider
would hold locks and increase deadlocks. Provider work starts only after commit.

### In-memory post-commit events without durable intent

Rejected. A process can fail after commit and before dispatch, permanently losing a required effect.

### Event sourcing or a general job platform

Rejected. The foundation needs authoritative current state and one bounded recoverable-delivery mechanism, not an
event store, projectors, or speculative background infrastructure.

### Exactly-once delivery

Rejected. The provider-success/record-success boundary cannot be made atomic with PostgreSQL. The honest guarantee
is durable at-least-once attempts with stable idempotency identity.

## Consequences

- Persistence and concurrency behavior are production-faithful and directly testable against PostgreSQL.
- Repository implementations are more explicit than generic CRUD and require disciplined SQL, lock ordering, and
  contract tests.
- All state, audit, and required intent writes can commit or roll back together on one connection.
- External delivery survives the commit/dispatch crash window, but downstream effects may be attempted more than
  once.
- Test setup is slower than SQLite but deterministic and structurally guarded against destructive target mistakes.
- Stable package reconstruction is a hard qualification gate; a missing supported path causes upstream work rather
  than a brittle local workaround.
- The temporary `UnitOfWork` compatibility binding is visible and removable instead of creating a second hidden
  transaction authority.

## Enforcement

| Rule | Enforcement owner |
|---|---|
| Guarded PostgreSQL lifecycle, migrations, shared connection, compatibility binding, permission/role mappings and reference fences | [TASK-00012](../tasks/00012-TASK.md) |
| User, email-claim, assignment, refresh-session, authentication-authority, and coupled session contracts | [TASK-00013](../tasks/00013-TASK.md) |
| Activation-grant generations, encrypted delivery state, complete-state comparison, and succession races | [TASK-00014](../tasks/00014-TASK.md) |
| Purpose-separated password-reset generations, terminal append, comparison, and succession races | [TASK-00015](../tasks/00015-TASK.md) |
| Audit evidence, durable-intent schema/capability, atomicity, claiming, restart discovery, and safe metadata | [TASK-00016](../tasks/00016-TASK.md) |
| Post-commit provider capabilities, processing, retry policy, recovery operation, observability, and redaction | [TICKET-00011](../tickets/00011-TICKET.md) |
| PostgreSQL suite and complete quality-gate enforcement | [TICKET-00013](../tickets/00013-TICKET.md) |

Every implementing TASK must test migrations from zero, exact round trips and absence behavior, known constraint
translation, rollback, and each selected competing-write strategy. Concurrency tests use controlled independent
connections and assert final authoritative state. Direct review must verify no request-time schema path, no
external effect inside transactions, no raw secret in intent/audit/logging, and no SQLite concurrency claim.

## Deferred concerns

`AgentRepository`, Agent credential/nonces, and `EmailChangeGrantRepository` are present in Fight Access Control
`v0.2.0` but are deferred because the approved foundation implementation does not yet persist Agent authorities or
deliver the email-change journey. Audit evidence still accepts Agent subjects without requiring an Agent row.
`UserRepository` email reservation and confirmation state is not deferred: the installed contract requires exact
round-trip persistence even though the product email-change journey is deferred.

Exact migration names, PostgreSQL server version, Doctrine Migrations version, table/column dimensions, retry
counts/delays, operational trigger, and provider enrollment are implementation details for their owning TASKs so
long as they preserve this decision. Any package API needed for exact reconstitution requires separately
authorized upstream work, a tagged stable release, and an updated lock; it must not be patched in `vendor/`.

## Evidence reviewed

- [EPIC-00003](../epics/00003-EPIC.md), [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md),
  [TICKET-00009](../tickets/00009-TICKET.md), and [ADR 0001](0001-application-ownership-and-orchestration.md).
- Locked Fight Access Control `v0.2.0` repository contracts and entities for permissions, roles, users, refresh
  sessions, activation grants, password-reset grants, and audit evidence, including package in-memory contract
  fixtures used to clarify complete-state, fence, rollback, and latest-generation behavior.
- Locked Fight Common `v1.2.0` `TransactionalUnitOfWork`, compatibility `UnitOfWork`,
  `DoctrineTransactionalUnitOfWork`, and deprecated `DoctrineUnitOfWork`.
- Current `config/common/persistence.php`, which already constructs one DBAL connection and EntityManager but uses
  SQLite and has no production repository mappings; TASK-00012 owns replacing that scaffold under this decision.
- [TASK-00012](../tasks/00012-TASK.md) through [TASK-00016](../tasks/00016-TASK.md) and
  [TICKET-00011](../tickets/00011-TICKET.md), used to assign implementation and verification ownership.

## Acceptance

The human maintainer explicitly accepted ADR 0002 as drafted on 2026-09-23. Acceptance covers the PostgreSQL,
migration, shared-connection transaction, contract-specific persistence/concurrency, guarded-test, and durable-
intent decisions above. It does not claim that downstream schemas, adapters, providers, workers, or operational
tooling are implemented.
