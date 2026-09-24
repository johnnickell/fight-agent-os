---
id: TICKET-00009
epic: EPIC-00003
title: Establish authoritative PostgreSQL persistence
status: ready-for-agent
---

# Establish authoritative PostgreSQL persistence

## Problem statement

Fight Access Control state needs production-faithful persistence with authoritative invariants, transaction boundaries, concurrency control, and isolated deterministic tests. SQLite or request-time schema creation cannot prove the PostgreSQL behavior on which authentication and authorization depend.

## Solution and boundaries

Record the PostgreSQL consistency and durable-effects ADR before schema implementation. Establish Doctrine Migrations as the only schema-evolution path, `DoctrineTransactionalUnitOfWork::commitTransactional()` as the canonical transaction boundary, and contract-first persistence adapters for the Fight Access Control repositories required by the approved foundation. Choose ORM mapping, versioning, pessimistic locking, conditional updates, or targeted DBAL on the same connection according to each contract.

Enforce uniqueness, references, lifecycle invariants, and concurrency in PostgreSQL. Establish isolated databases that are migrated and reset deterministically and are structurally unable to target development or production. Select the durable-intent mechanism needed by TICKET-00011, but leave external delivery implementation there.

Out of scope: SQLite as persistence/concurrency evidence, request-time schema creation, event sourcing, external effects inside transactions, provider delivery, HTTP endpoints, and feature UI.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Persist package-owned state changes | Existing Fight Access Control commands are handled by package Application services | Repository reads required by command handlers and authoritative resolution | Preserve package events for post-commit coordination; durable required effects follow the ADR | Aggregate changes and required durable intent commit atomically or roll back together |
| Resolve authoritative state | N/A — repository reads support package queries and principal resolution | Existing package queries and repository lookup contracts | N/A for read-only resolution | Exact entities/views are reconstructed without leaking persistence models |
| Evolve and validate schema | N/A — migrations are operational changes | Inspect migration status, schema metadata, constraints, and mappings | N/A — no application domain event is produced | Versioned migrations create/update the PostgreSQL contract outside requests |
| Prove competing writes | Package commands or repository contract operations drive the race | Query final authoritative state after controlled concurrency | Only events from committed outcomes may continue | Locking/version/conditional behavior permits one valid outcome and rejects stale/conflicting writes safely |

## Validation and permissions

Repositories must satisfy package contracts exactly, including canonical identifiers, absence behavior, aggregate version/state, ordering where promised, and atomicity. Database constraints remain authoritative for uniqueness and references. Concurrency failures must map to known safe application conflicts without exposing SQL or internal identifiers.

Test configuration must use dedicated credentials/database names, require explicit test mode, migrate before use, reset deterministically, and refuse known development/production targets. Secrets stay external. No end-user permission is implemented here; package handlers and later HTTP Actions remain responsible for authorization before protected mutations and reads.

## Acceptance and evidence

- An accepted ADR records transaction ownership, mapping/repository strategy, migration policy, concurrency controls, and the selected recoverable durable-intent mechanism.
- Doctrine Migrations define the required schema with snake_case identifiers, authoritative constraints, and no request-time creation path.
- Fight Access Control repository contracts needed by the foundation have production adapters on the shared transactional connection.
- Mapping and repository contract tests run against PostgreSQL, including absence, uniqueness/reference, rollback, and lifecycle behavior.
- Controlled competing-write tests prove each selected locking/versioning/conditional-update contract.
- Test databases are isolated, migrated/reset deterministically, and guarded against non-test targets.
- Focused migration, repository, transaction, and concurrency checks pass, followed by `./bin/build` with fresh evidence and warnings.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00011](../tasks/00011-TASK.md) | Accept the PostgreSQL consistency and durable-effects ADR | done |
| [TASK-00012](../tasks/00012-TASK.md) | Establish guarded PostgreSQL persistence through managed authority | done |
| [TASK-00013](../tasks/00013-TASK.md) | Persist identities and refresh sessions atomically | ready-for-agent |
| [TASK-00014](../tasks/00014-TASK.md) | Persist activation grants and replacement races | ready-for-agent |
| [TASK-00015](../tasks/00015-TASK.md) | Persist password-reset grants and terminal succession | ready-for-agent |
| [TASK-00016](../tasks/00016-TASK.md) | Establish atomic audit evidence and package delivery persistence | needs-info |
<!-- /planning:children -->

## Decisions and progress

Implements the persistence direction approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md). It depends on [TICKET-00008](00008-TICKET.md)'s ownership boundary and gates durable delivery plus EPIC-00004 identity/session persistence.
