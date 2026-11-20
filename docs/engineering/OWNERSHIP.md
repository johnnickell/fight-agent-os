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
| Historical registration used an unregistered Application service, internal IDs and a mixed Store; TASK-00575 replaces that whole path with Project-owned registered commands/queries, an aggregate repository and adapter admission | Current [Project runbook](REPOSITORY_REGISTRATION.md) and TASK-00575 own implementation/evidence; independent acceptance is separate |
| The former `RoleMutationUnitOfWork` inferred audit from the callback's returned Role event | TASK-00573 replaces it with [explicit package-handler audit](CUSTOM_ROLES.md) on the shared transaction |
| TASK-00572 binds Common v1.5.0 `DbalTransactionalUnitOfWork` directly to the shared DBAL Connection; unused EntityManager scaffolding and direct ORM dependency are removed | [Current transaction composition](TRANSACTIONS.md); no genuine ORM participant or second transaction authority is introduced |
| No authenticated User Role assignment entry exists | The trusted Console path remains; any future adapter must own actor, operation and target admission before dispatch |
| `ChangePasswordAction` calls owned `ChangePassword`, which invokes public package `PasswordManagement::changePassword()` synchronously with sensitive inputs | Actual sensitive non-serializable exception, not a reason to put passwords/tokens in messages; broader correction belongs to TASK-00580 |
| TASK-00582 selects released Common v1.4.1 and preserves Access Control v0.8.0; canonical categories and the Common safe HTTP boundary replace the local generic classifier | [Safe API failures](SAFE_HTTP_ERRORS.md) records actual composition, direct cookie effects, strict client cutover and scoped independent HTTP/browser/security acceptance; capability-specific corrections and deployment remain separate |

This documentation changes no source, executable dependency rule, dependency lock, runtime route/permission or
data. TICKET-00132 owns unfinished individual-plan reconciliation; TICKET-00133 through TICKET-00136 own source
corrections. A missing supplier holds only its affected integration, not unrelated local work. Upstream packages
retain their own compatibility obligations; deliberate Agent OS breaks still require needed-data preservation,
migration or explicitly authorized disposal.

## Agent setup, review and supplier qualification

Follow [Agent setup and review ownership](STANDARDS.md#agent-setup-and-template-review-ownership) and the
[revision-linked maintainer amendment](../../planning/adr/0001-application-ownership-and-orchestration.md#maintainer-accesscontrol-audit-amendment).
Agent OS owns separate-phase Team/template setup, incomplete-use exclusion and recovery, and template review/
reconciliation evidence including no-change. AccessControl owns Permission mutation and actual-change audit.
Do not ask the supplier to own Teams/templates, commit all three setup stages together or record review no-ops
as Permission changes. Current supplier source has bindings/scoped reads, email-change self-audit/revision checks
and Role audit; tagged release/adoption and consumer evidence remain separate. Redundant invitation/recovery
admission removal, old-mailbox atomic intent participation and exact request-ID cancellation remain unimplemented/
unreleased gaps. No planning refresh clears real qualification holds.

## Current Workspace and technical-reference cutover

TASK-00574 replaces the extracted mixed-store paths with `Domain/Workspace/Workspace` (root, ID, repository,
bootstrap command and current-state queries) and mirrored registered Application handlers/typed Views. Console
owns trusted-operator admission and generates the ID before void dispatch. The bootstrap handler coordinates
collection persistence and an explicit required audit writer through the qualified Common DBAL transaction;
all participating writes share its connection. No audit is inferred from a callback result.

The reference HTTP Action owns current Workspace admission after central authentication/permission enforcement;
`Adapter/Reference/ExceptionalReferences` owns technical limiter metadata and the UUID factory, not a business
command or Application forwarder. Project HTTP admission uses the registered initial-Workspace query and current
managed administrator membership after central operation permission enforcement. `Domain/Workspace/Project` and
mirrored registered Application handlers now own creation, detail/list and advisory preview. The collection port
returns aggregates; typed Application Views carry safe current facts; HTTP Data/Responders serialize them.
`RegisterProjectHandler` explicitly coordinates state/claims and required audit on the shared generic transaction,
then publishes `ProjectRegistered` after commit. No ordinary authority-serialization fence or replay Store survives.
The generated migration preserves current identities/source facts/grants and historical attribution/initial facts
while removing ordinary original-response bookkeeping. See the [current runbook](REPOSITORY_REGISTRATION.md).

## Planned canonical observations and MCP binding

[TICKET-00096's supplier mapping](../../planning/tickets/00096-TICKET.md#canonical-observation-supplier-and-delivery--2026-11-06)
selects Common-owned canonical observations, best-effort secondary recording Commands and application-owned Workflow
relationships/projections, not a mirrored raw ledger. Common TASK-00205 is approved planning for optional typed
activity target/timing/result facts, not implementation acceptance or tagged availability. Required audit/reference/
lifecycle guarantees and richer history/accounting remain separate consumer obligations with explicit holds.
[TASK-00245](../../planning/tasks/00245-TASK.md#external-tool-supplier-and-scope) consumes AccessControl TASK-00084's
map-plus-attributes design and separate TASK-00089/00090 scoped reads. Current AccessControl source now supplies
external bindings and scoped-read ports, superseding the earlier attribute-only observation. Actual tagged-release,
Composer-adoption and consumer/host admission qualification remain open; source availability is not an installed
consumer capability, grant, dependency update or Common release gate.

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
