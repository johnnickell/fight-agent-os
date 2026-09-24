# Application Ownership Enforcement

[ADR 0001](../../planning/adr/0001-application-ownership-and-orchestration.md) is the authority for package ownership and dependency direction. This document records the first executable proof and the boundaries automated by TASK-00010.

## Representative composition

The outer query configuration registers Fight Access Control's public `ListPermissions` query directly to its public `ListPermissionsHandler`. The handler receives the package-owned `PermissionRepository` capability through constructor injection. The production composition intentionally does not bind a repository implementation: PostgreSQL authority and that adapter belong to TICKET-00009.

This is query-only, non-user-facing composition. A command and event are not added because they would require transaction, persistence, audit, or delivery behavior owned by later requirements. No `App\Application` orchestrator is justified or introduced because this interaction is already wholly owned by Fight Access Control. Configuration wiring is not a product behavior and has no dedicated PHPUnit test; any real interaction that uses this path will fail directly if composition is invalid.

## Automated rules

Run the focused check with:

```sh
./bin/architecture
```

Deptrac analyses owned production source and enforces these currently representable directions:

- `App\Domain` may depend only on owned Domain, package Domain contracts, Fight Common Domain, and PHP internals.
- `App\Application` may depend inward and on public Fight Access Control Domain/Application and Fight Common Domain/Application contracts, but not adapters or infrastructure.
- `App\Adapter` may depend inward, on selected package layers, and on infrastructure.
- Dependencies from an owned layer to an unclassified type are reported and fail the check, so an external framework cannot bypass the rules merely because its namespace is absent from the configured infrastructure collector.

Package ownership, service-location, wrapper intent, and other semantic concerns not represented by dependency direction remain code-review responsibilities. The build does not test Deptrac with deliberately invalid source. TICKET-00013 owns integration and extension of the focused dependency check into the final pre-submit gate.

## Deferred enforcement

| Boundary | Why it is not enforced here | Downstream owner |
|---|---|---|
| PostgreSQL repository implementation, exact transaction capability, shared connection, and durable intent | No persistence adapter or schema exists in this proof | TICKET-00009 |
| One-Action/one-interaction delivery, validation, safe errors, Responders, and transport-safe Views | This proof deliberately exposes no endpoint | TICKET-00010 |
| Post-commit provider effects, recovery, retries, leasing, and idempotency | This query has no event or external effect | TICKET-00011 |
| Semantic authorization placement and absence of role-name bypass | No protected product interaction exists yet | TICKET-00010 and feature TICKETs |
| Complete syntax, style, static-analysis, Rector, coverage, and architecture gate integration | TASK-00010 introduces only the focused architecture boundary | TICKET-00013 |
| Genuine-orchestration and anti-speculation judgments not reducible to dependency or name checks | These require use-case evidence and review | TICKET-00013 and independent review |
