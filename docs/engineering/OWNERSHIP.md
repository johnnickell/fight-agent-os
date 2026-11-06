# Application ownership and enforcement

[Engineering standards](STANDARDS.md#architecture) own the shared normative architecture contract.
[ADR 0001](../../planning/adr/0001-application-ownership-and-orchestration.md) retains package rationale and its
explicit EPIC-00023 amendment. Use standards for implementation/assessment; current source and a green checker
are evidence, not alternative policy or proof that the approved correction is complete.

## Approved target and current source

The target is aggregate-first Domain and matching Application ownership, registered handler coordination,
collection-like repositories, capability-first adapters, direct canonical Fight contracts and inward dependencies
including supported PSR contracts in Application. Entry adapters own authentication/permission/scope admission;
the raw bus is trusted. Creation uses adapter-generated typed IDs in void commands; ordinary response loss uses
uniqueness and fresh authorized queries. Follow the linked standards for exact transaction/audit, safe typed
read/failure, sensitive synchronous and application compatibility/data requirements rather than copying a second
contract here.

Source observations at TASK-00564's base `eb5fe2f56a8fbb8b5c27f64b959dc2d36d5bc01a`:

| Current implementation, not target conformity | Correction / qualification owner |
|---|---|
| `RegisterRepositoryAction` directly calls container-registered `Application/Workspace/RegisterRepository::register()`, not a registered message handler; it uses internal aggregate IDs, `RegistrationStore`, original results and Application authority fences | TASK-00574/00575; current [registration runbook](REPOSITORY_REGISTRATION.md) remains executable |
| `RoleMutationUnitOfWork` infers required audit from the transaction callback's returned Role event | TASK-00573; preserve existing atomic audit until explicit package-handler support is qualified |
| `config/common/persistence.php` binds one shared EntityManager/DBAL Connection and Common `DoctrineTransactionalUnitOfWork` | TASK-00572 qualifies an actual DBAL-only alternative; no ORM replacement or second transaction authority is implied |
| `AuthenticatedRoleAssignment::assign()` is the existing Application admission path, not a new all-entry rule | TASK-00576 owns its correction; do not bypass current checks or expose raw messages to untrusted callers meanwhile |
| `ChangePasswordAction` calls owned `ChangePassword`, which invokes public package `AuthenticationService::changePassword()` synchronously with sensitive inputs | Actual sensitive non-serializable exception, not a reason to put passwords/tokens in messages; broader correction belongs to TASK-00580 |
| TASK-00582 selects released Common v1.4.1 and preserves Access Control v0.8.0; canonical categories and the Common safe HTTP boundary replace the local generic classifier | [Safe API failures](SAFE_HTTP_ERRORS.md) records actual composition, direct cookie effects, strict client cutover and limits; independent acceptance and capability-specific corrections remain separate |

This documentation changes no source, executable dependency rule, dependency lock, runtime route/permission or
data. TICKET-00132 owns unfinished individual-plan reconciliation; TICKET-00133 through TICKET-00136 own source
corrections. A missing supplier holds only its affected integration, not unrelated local work. Upstream packages
retain their own compatibility obligations; deliberate Agent OS breaks still require needed-data preservation,
migration or explicitly authorized disposal.

## Planned canonical observations and MCP binding

[TICKET-00096's supplier mapping](../../planning/tickets/00096-TICKET.md#canonical-observation-supplier-and-delivery--2026-11-06)
selects Common-owned canonical observations, best-effort secondary recording Commands and application-owned Workflow
relationships/projections, not a mirrored raw ledger. Common TASK-00205 is approved planning for optional typed
activity target/timing/result facts, not implementation acceptance or tagged availability. Required audit/reference/
lifecycle guarantees and richer history/accounting remain separate consumer obligations with explicit holds.
[TASK-00245](../../planning/tasks/00245-TASK.md#external-tool-supplier-and-scope) consumes AccessControl TASK-00084's
approved map-plus-attributes design and separate TASK-00089/00090 scoped reads; current source remains attribute-only.
These are planned integrations, not installed capabilities, grants, dependency updates or Common release gates.

## Current automated boundary

Run the focused owning tool:

```sh
./bin/architecture
```

`deptrac.php` currently classifies these dependency directions:

- `App\Domain` may use owned/package Domain contracts and PHP internals.
- `App\Application` may use owned Domain and public Fight Domain/Application contracts, not adapters/infrastructure.
- `App\Adapter` may depend inward, on selected package layers and infrastructure; composition constructs the graph.
- Unclassified dependencies fail rather than silently bypassing collection.

The current collector groups `Psr` with infrastructure and does **not** yet implement the approved supported-PSR
Application allowance. Its scoped source owner must reconcile the executable rule with actual changes, not weaken
standards or edit this documentation-only TASK's configuration. No current gate failure is inferred from that
latent mismatch. Deptrac cannot prove package ownership, descriptive collaborator names, genuine coordination,
admission timing, required atomic audit, safe results or absence of forwarding aliases: these require review of
real callers and contracts. Validate tooling directly; never seed invalid product fixtures to test the checker.

## Historical TASK-00010 proof

TASK-00010 introduced a query-only representative composition: outer configuration registered package
`ListPermissions` directly with `ListPermissionsHandler` and injected package `PermissionRepository`. At that
checkpoint there was no production repository binding, HTTP endpoint, command, event or justified owned
orchestrator. That established direct package composition, not today's full persistence/application inventory.

Its deferred enforcement owners were TICKET-00009 (PostgreSQL/transaction/durable intent), TICKET-00010
(HTTP/validation/safe presentation), TICKET-00011 (post-commit delivery/recovery), and TICKET-00013 (full gate
and semantic review). Those are historical responsibility assignments, not claims those boundaries remain
absent today. Exact acceptance and verification remain with their owning records; the present canonical
`./bin/build` and independent Review/QA retain their own obligations.
