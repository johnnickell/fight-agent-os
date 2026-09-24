# ADR 0002: Define PostgreSQL consistency and durable effects

- **Status:** Accepted
- **Date:** 2026-09-24
- **Decision owners:** Fight Agent OS maintainers
- **Acceptance:** Explicitly reaccepted by the human maintainer after the package-owned delivery revision under TASK-00011 on 2026-09-24

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

The locked package does not yet expose a viable durable-intent integration. `InvitePendingUserHandler` owns and
commits the user, activation grant, encrypted delivery state, and audit transaction, then emits `UserInvited` only
after commit. The grant state survives a crash, but `v0.2.0` has no deterministic due-work discovery, committed
lease, stale-claim fence, or recovery runner contract. `DeliverUserInvitationHandler` claims delivery, invokes
`InvitationDeliveryInvoker`, and records success or failure inside one transaction, so binding that invoker to an
external provider violates the no-external-effect rule.

Fight Access Control's accepted `EPIC-00006`, `WF-009`, and `WF-010` planning on `develop` at
`182748eec05aeeeed1390b9d104f68ca219d2a0d` resolves the missing boundary for proposed `v0.3.0`: existing
package-owned grant delivery state, not a consumer outbox, is the authoritative durable queue for invitation,
password-reset, and email-change credentials. That plan is implementation-ready but is not yet a tagged dependency
available to Agent OS.

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
occur only after a successful commit. A post-commit callback is an optimization, not recovery authority. The
current package handlers cannot be surrounded by an Agent OS transaction, decorated through audit persistence,
or followed by an event subscriber to manufacture the missing atomicity; those approaches respectively create a
nested transaction, hide delivery policy in an unrelated repository, or retain the crash window.

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
| `ActivationGrantRepository` | Purpose-specific grant generations and owned delivery generations store only package-approved credential digests plus recoverable encrypted delivery ciphertext; IDs, delivery IDs, per-user generation numbers, and per-user historical digests are unique and user references are enforced | A per-user grant transaction lock plus latest-row `FOR UPDATE` serializes add, replace, `replaceWithSuccessor`, and `addSuccessor`. The adapter compares complete security-relevant predecessor state and exact next revision. Terminalization and successor insert are one transaction. The locked `v0.3.0` contract must additionally govern due discovery, committed leases, retry timing, and expected outcomes |
| `PasswordResetGrantRepository` | Separate purpose-specific tables use the same safe shape without sharing activation identity or credential namespaces accidentally; raw reset credentials are never stored | A per-user reset-grant transaction lock plus latest-row `FOR UPDATE` enforces complete-state replacement, terminal append, and atomic succession. Purpose, ownership, exact next revision, terminal state, and historical digest freshness are checked before mutation. The locked `v0.3.0` contract must add the same recoverable delivery lifecycle without merging purpose namespaces |
| `EmailChangeGrantRepository` | The qualified `v0.3.0` mapping persists package-owned email-change grant and delivery generations, encrypted material, reservation references, stable delivery identity, and terminal ciphertext destruction; email-change provider wiring remains deferred | Per-user email-change fences and complete-state conditional writes protect request, replacement, cancellation, expiry, claim, retry, and outcome transitions. Due-work behavior must match the package contract rather than an Agent OS variant |
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

### Durable credential delivery intent

The authoritative durable queue for invitation, password-reset, and email-change credentials is the delivery state
owned by each Fight Access Control grant generation. Agent OS must not create a parallel credential outbox. The
originating package handler atomically commits aggregate state, encrypted delivery material, and required audit
evidence through the shared `commitTransactional()` boundary; that committed package state remains recoverable if
post-commit event dispatch never occurs.

Integration is blocked on separately authorized completion and qualification of Fight Access Control EPIC-00006, a
tagged stable `v0.3.0` release, and an Agent OS lock update. That release must provide the accepted package contract:

- secret-free deterministic bounded discovery across pending, due-retry, and expired-lease invitation, reset, and
  email-change generations;
- a short transaction that claims one exact generation with an opaque token, lease deadline, attempt metadata, and
  compare-and-set revision, and commits before provider invocation;
- package-controlled decryption only after that committed claim and only for the invocation lifetime;
- a provider-neutral consumer capability receiving the immutable delivery-generation ID as stable idempotency
  identity and returning typed delivered, retryable, or permanent outcomes;
- a separate expected-state transaction that accepts only the matching live claim token and revision, rejects stale
  claimants, records safe outcome/audit evidence, and destroys ciphertext on delivered or permanent outcomes;
- package-owned bounded increasing backoff for retryable outcomes until the owning grant expires, with expired claims
  becoming discoverable for safe recovery; and
- direct package command/handler ownership without an Agent OS alias, duplicated lifecycle policy, outer transaction,
  or post-commit event as recovery authority.

Raw credentials, hashes, ciphertext, access/refresh tokens, provider secrets, credential-bearing URLs, plaintext
mail bodies, and arbitrary provider messages are prohibited in Commands, Events, Queries, safe Views, audit context,
and ordinary diagnostics. Consumer provider adapters hold plaintext only during invocation and must honor the stable
idempotency identity. A crash after provider acceptance but before outcome commit may produce another attempt after
lease recovery, so the guarantee is durable at-least-once delivery, never exactly once.

Post-commit package events may request immediate processing, but a bounded Agent OS scheduler must use package due-
work discovery and dispatch the same direct package handlers after restart. Agent OS owns PostgreSQL repository
adapters on the shared connection, provider implementations, operational scheduling/capacity, and composition; the
package owns discovery semantics, claims, leases, retry timing, terminal policy, stale-outcome fencing, credential
materialization, and handler orchestration.

Fight Access Control includes all three credential families to avoid incompatible public delivery models. Agent OS
may defer email-change product/provider wiring, but its persistence mapping must not invent a different lifecycle.
The exact `v0.3.0` public types must be taken from the qualified tagged release, not guessed from planning or patched
in `vendor/`. Until that release is locked, TASK-00016 and TICKET-00011 remain `needs-info`; independent guarded
PostgreSQL and `v0.2.0` repository work may continue after this ADR is accepted.

Durable audit evidence committed through `AuditEvidenceRepository` is the authoritative audit effect for the current
foundation. No external audit-publication provider is demonstrated, so this ADR does not create a speculative second
outbox for it. A future external audit sink requires separately accepted requirements for durable publication,
idempotency, retention, and recovery rather than reusing credential delivery state.

Delivered, permanent, expired, replaced, revoked, and cancelled credential generations retain only package-approved
secret-free historical status and audit evidence; ciphertext is destroyed when the package contract makes work
terminal. This ADR selects no age-based purge of package grant history. Any later purge requires explicit retention
requirements and must never remove pending, retryable, or live claimed work.

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

### In-memory post-commit events without durable discovery

Rejected. A process can fail after commit and before dispatch. Persisted package delivery state closes that window
only when deterministic discovery and claim/recovery contracts can find and process it after restart.

### Consumer-owned credential outbox

Rejected. It duplicates package-owned grant delivery state, creates two lifecycle authorities requiring
reconciliation, and makes recovery depend on consumer registration. The qualified package state is the sole
credential queue.

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
- Existing package delivery state remains the single credential-work authority; Agent OS does not reconcile a
  duplicate outbox.
- Once the required tagged package release is locked, aggregate state, encrypted delivery material, and audit evidence
  commit or roll back together, while provider invocation and outcome persistence use separate later phases.
- External credential delivery survives the commit/dispatch crash window after that integration, but provider
  effects may be attempted more than once.
- Fight Access Control `v0.2.0` is insufficient for recoverable provider integration; attempting to work around it
  in Agent OS is a stop condition rather than an implementation option.
- External audit publication is deferred rather than supported by a speculative generic outbox.
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
| Audit evidence plus qualified `v0.3.0` cross-family due-work/claim/outcome persistence integration after the tagged package prerequisite is locked | [TASK-00016](../tasks/00016-TASK.md) |
| Provider adapters, direct package-handler composition, scheduled discovery, recovery operation, observability, and redaction after the same prerequisite | [TICKET-00011](../tickets/00011-TICKET.md) |
| PostgreSQL suite and complete quality-gate enforcement | [TICKET-00013](../tickets/00013-TICKET.md) |

Every implementing TASK must test migrations from zero, exact round trips and absence behavior, known constraint
translation, rollback, and each selected competing-write strategy. Concurrency tests use controlled independent
connections and assert final authoritative state. Direct review must verify no request-time schema path, no
external effect inside transactions, no raw secret in intent/audit/logging, and no SQLite concurrency claim.

## Deferred concerns

`AgentRepository` and Agent credential/nonces are deferred because the approved foundation does not yet persist
Agent authorities. Audit evidence still accepts Agent subjects without requiring an Agent row. Email-change product
routes and provider wiring remain deferred, but the qualified `v0.3.0` `EmailChangeGrantRepository` persistence
contract is included so Agent OS does not invent an incompatible credential lifecycle. `UserRepository` email
reservation and confirmation state also remains required for exact round-trip persistence.

Exact migration names, PostgreSQL server version, Doctrine Migrations version, table/column dimensions, operational
trigger, worker cadence/capacity, and provider enrollment are implementation details for their owning
TASKs so long as they preserve package-owned retry timing and this decision. Package APIs needed for exact
reconstitution or recoverable credential delivery require separately authorized upstream work, a tagged stable
release, and an updated lock; they must not be patched in `vendor/`. External audit publication and its retention
policy remain deferred until a provider requirement demonstrates a separate durable-publication boundary.

## Evidence reviewed

- [EPIC-00003](../epics/00003-EPIC.md), [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md),
  [TICKET-00009](../tickets/00009-TICKET.md), and [ADR 0001](0001-application-ownership-and-orchestration.md).
- Locked Fight Access Control `v0.2.0` repository contracts and entities for permissions, roles, users, refresh
  sessions, activation grants, password-reset grants, and audit evidence, including package in-memory contract
  fixtures used to clarify complete-state, fence, rollback, and latest-generation behavior.
- Locked invitation, password-reset, and email-change originating/delivery handlers and subscribers, whose
  transaction ownership, event timing, missing due-work discovery, and in-transaction invokers prove that `v0.2.0`
  lacks the required recovery contract.
- Fight Access Control `develop` at `182748eec05aeeeed1390b9d104f68ca219d2a0d`: accepted EPIC-00006, WF-009,
  WF-010, TICKET-00007, and TASK-00037 through TASK-00039 planning for package-owned `v0.3.0` credential delivery.
- Locked Fight Common `v1.2.0` `TransactionalUnitOfWork`, compatibility `UnitOfWork`,
  `DoctrineTransactionalUnitOfWork`, and deprecated `DoctrineUnitOfWork`.
- Current `config/common/persistence.php`, which already constructs one DBAL connection and EntityManager but uses
  SQLite and has no production repository mappings; TASK-00012 owns replacing that scaffold under this decision.
- [TASK-00012](../tasks/00012-TASK.md) through [TASK-00016](../tasks/00016-TASK.md) and
  [TICKET-00011](../tickets/00011-TICKET.md), used to assign implementation and verification ownership.

## Acceptance

The human maintainer explicitly reaccepted ADR 0002 on 2026-09-24 after reviewing the package-owned durable queue,
its Fight Access Control `v0.3.0` release prerequisite, and the corrected downstream ownership. Acceptance covers
the PostgreSQL/persistence decisions and permits independent schema/repository work beginning with TASK-00012. It
does not claim that upstream EPIC-00006, a tagged package release, the dependency update, schemas, adapters,
providers, workers, or operational tooling are implemented; TASK-00016 and TICKET-00011 remain `needs-info` until
the package prerequisite is satisfied.
