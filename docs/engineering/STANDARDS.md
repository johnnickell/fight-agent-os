# Engineering Standards

This is the shared normative architecture contract for Agent OS, including the approved
[EPIC-00023 amendments](../../planning/epics/00023-EPIC.md). ADRs retain decision rationale, history and
non-conflicting use-case guarantees; [ARCHITECTURE](../../ARCHITECTURE.md) describes implementation and direction.
Nearby code is evidence, not an alternative authority. A source mismatch belongs to its scoped correction TASK;
a missing supplier capability holds that slice. Neither permits silently weakening this contract.

Review owns the detailed [quality checks](QUALITY.md) for changed code, tests and documentation. Work follows
applicable standards and consults relevant detail when making an implementation decision; it need not load every
checklist or duplicate review's assessment. Audit and architecture apply the checks within their declared scope.
Use the instruction/documentation sections when maintaining AGENTS.md, README or CHANGELOG.

## Architecture

Design architecture, domain language and capability interfaces for human readers first. Ordinary feature work
need not follow TDD: settle ownership and interfaces, implement testable behavior, then prove it with meaningful
tests. Test convenience must not dictate public APIs or fragment cohesive concepts.

### Aggregate ownership and naming

Keep `App\` mapped directly to `src/`. Domain uses `Domain/{BoundedContext}/{Aggregate}`: the aggregate root,
its typed ID, collection-like repository interface, owned values and optional nested entities live together.
Put its verb-first Commands and Queries in `Command/` and `Query/`, and past-fact Events in `Event/` below that
aggregate. For example, `Domain/Workspace/Project/` owns `Project`, `ProjectId`, `ProjectRepository`,
`Command/RegisterProject` and `Event/ProjectRegistered`. A nested entity is not automatically another root.
Additional Domain services, DTOs or other building blocks need a demonstrated Domain responsibility; folders
and test convenience do not justify them.

Application mirrors that ownership: `Application/Workspace/Project/CommandHandler/RegisterProjectHandler`,
matching `QueryHandler` homes, policy-named `EventSubscriber` types and cohesive semantic capabilities.
Registered handlers own coordination. `Service` is a fallback for a genuine cohesive responsibility, not a
miscellaneous bucket, broad lifecycle forwarder or unregistered dispatcher hidden behind a command-shaped name.
Domain owns business state, invariants and policy; handlers coordinate them rather than reconstructing policy
from getters. A repository-backed decision may need an injected capability with its actual business owner.

Adapters are capability-first, then aggregate/transport-specific where useful: aggregate persistence,
HTTP Actions/Data/Responders, Symfony Console Commands, MCP Tools and provider bindings belong outside Domain.
`Middleware` holds actual pipeline middleware, not arbitrary HTTP values, failures or representations.
Avoid speculative `Manager`, `Helper`, `Value` or protocol-neutral buckets. Class count is a cohesion signal,
not a threshold; meaningful ownership matters more than folder depth. Apply these PHP rules to PHP, not as a
mandate to reorganize React, Pi or tooling. Source moves belong to their approved slice, not incidental cleanup.

**Project** is the business collaboration/publication target in a Workspace, initially one intended target,
not a new multi-repository umbrella. Use `Project`, `ProjectId`, `ProjectRepository`, `RegisterProject`,
`RegisterProjectHandler` and `ProjectRegistered`; registration is an operation, not a replacement aggregate or
Store. The primary table should normally be `projects`; related names express actual relationships. Qualified
**Git repository** still means source control. A source descriptor is mutable evidence; forks/upstreams remain
distinct targets, not equivalences. Host, Checkout (one Git common directory on a Host) and Worktree retain
separate identities and links. Source metadata never proves physical availability or grants authority.
[TASK-00575](../../planning/tasks/00575-TASK.md) owns the actual registration cutover: this naming contract does
not rename today's commands, routes, permissions, markers or tables. Preserve the exact physical/source
contracts in [ADR 0005](../../planning/adr/0005-repository-identity-and-link-consistency.md) as amended.

### Inward dependencies and canonical contracts

Dependencies point `Adapter → Application → Domain`. Domain may use pure PHP and supported upstream Domain
contracts. Application may use owned Domain, supported upstream Domain/Application contracts and PSR contracts,
not concrete framework/provider/persistence implementations. Adapter and the composition root own those
implementations. A PSR dependency in Application is not, by itself, a forbidden outward dependency.

Inject fully descriptive collaborators: `$projectRepository`, `$queryBus`, `$commandBus`,
`$transactionalUnitOfWork`, `$eventDispatcher`. Natural plurals remain appropriate for actual collections.
Use capability-named interfaces without an `Interface` suffix at demonstrated boundaries. Do not manufacture
getters, aliases, parallel value/identity bases or extra layers just to satisfy a naming example.

[Fight package ownership](../../planning/adr/0001-application-ownership-and-orchestration.md) remains direct:
reuse canonical identities, messages, repositories, capabilities, events and safe Views, without renaming or
forwarding aliases. Dispatch public package commands/queries through configured buses, never private handler
calls. Owned orchestration needs missing Agent OS behavior or real cross-owner coordination, observable
input/outcome/failures and injected inward capabilities; do not invent a boundary to justify a wrapper.
Actions may compose required package user/role queries; Responders map complete safe Views without fetching.

### Handler-owned transactions and audit

A registered CommandHandler coordinates aggregate behavior, collection-like repository persistence and required
audit through the generic injected `TransactionalUnitOfWork`. Repositories do not own transaction completion,
unrelated admission, response construction or recovery orchestration. Do not combine Store and UoW contracts
or hide business/audit policy in transaction decorators, repository interception or post-commit subscribers.

Use one transaction authority and the exact shared connection for all participating aggregate, required audit
and justified durable-intent writes. Qualified Common Doctrine and DBAL-only implementations are alternatives
for real persistence needs, not two authorities in an operation or a requirement to replace ORM. Preserve
uniqueness, complete expected-state comparisons, aggregate invariants and locks needed for actual business
consistency. No independent repository commit/flush, nested consumer transaction around package handlers or
second write connection can manufacture atomicity.

Success events dispatch only after commit. Dispatch failure can follow a successful commit; do not report
rollback from an exception. An event does not make email, filesystem, Git or remote publication rollback-safe,
durable or exactly once. Required external effects retain their justified intent/reconciliation contracts;
state actual attempt/delivery and post-commit uncertainty guarantees, not guarantees inferred from CQRS.

Common's DBAL-only transaction API and explicit Access Control Role-handler audit are capability prerequisites
to qualify, not imports to guess. The current `RoleMutationUnitOfWork` consumer decorator is a correction target,
not the approved audit design. Preserve required Role mutation/audit atomicity until its separately owned
replacement is qualified; DBAL support alone does not supply audit. See
[TICKET-00133](../../planning/tickets/00133-TICKET.md) for supplier and adoption ownership.

### Shared services and bus dispatch

Use the existing container's shared registrations for stateless services, command/query handlers, buses and
framework command instances. Construct each registered service in a composition-root factory from references to
other registered services in that same container. Do not construct unregistered parallel service graphs in an
adapter, handler or factory, and do not use a container as an Application/Domain service locator. Shared means
one instance per container, not PHP static globals. Fresh messages, values and results remain appropriate;
request/invocation-scoped stateful infrastructure must have explicit isolation and cleanup on success and failure.

Every command/query dispatch, including nested package work inside owned orchestration, traverses its configured
bus pipeline. Inject buses, not handlers, for dispatch. An empty filter list is not permission to bypass it.
Preserve use-case transaction, authorization, audit and recovery guarantees when composing package handlers;
if the installed transaction contract cannot support them, report the concrete blocker rather than call handlers
privately or weaken the contract. Void command dispatch alone does not prove a filter allowed all required effects.

`config/services.php` loads sorted `config/{parameters,common,application}/*.php` globs. Every matched file returns
one `callable(Container): void` registration module. Load all modules exactly once; keep metadata helpers outside
these globs or convert them to that contract. Do not add a file manifest, filename exceptions, argv-selected
profiles or nested module loads. Common `command.php`, `query.php` and `event.php` own their respective shared
pipelines, routers, handlers and aliases. Application modules add lazy services and registrations, never replace
the bus. HTTP and Console load the same graph; `scripts/console.php` only boots and runs the registered Console
application. Register all commands together, without resolving invocation-only delivery keys or principals on
unrelated boot/list/help. Service registration is neither execution nor access control.

The raw bus is trusted in-process composition infrastructure, not an authenticated public API. Every entry
owns [admission](#entry-admission-and-authorization-timing) before dispatch. Trusted operators use the same bus
without pretending to authenticate as browser users. Never forward untrusted raw messages, authorize by service
presence, install ambient trust flags/intent scopes or create an alternate bus to grant Console authority.
Today's `AuthenticatedRoleAssignment::assign()` is the existing nontrusted assignment admission path; do not
bypass its checks before its owning correction moves that responsibility to adapters. It is not a normative
second Application gate. [Console](CONSOLE_INVITATIONS.md) and [bootstrap](SUPER_ADMIN_BOOTSTRAP.md) runbooks
retain their current executable procedures and partial-result limits. Machine/process access is today's Console
trust boundary; managed Agent shell/CLI exclusion is not implemented by container registration.

For new or revised console entry points, use an Adapter Symfony Console Command registered with the existing
container and exposed through generic `bin/console`. The command validates/maps transport input, dispatches a
package or justified owned use case through injected buses, and presents safe output/exit status. Business and
transaction orchestration stay with their Domain/Application owners. Sanitize parsing/unknown-option errors,
interactive suggestions and verbose rendering as well as handler failures; never echo private input or exceptions.
This does not authorize migrating unrelated entry points or replacing the application's framework.

### Module depth and interface simplicity

Prefer a small, coherent domain-facing interface that hides substantial implementation complexity. A module's
depth is how much useful work it encapsulates relative to what callers must understand; it is not namespace
depth, class size or method count. An interface includes inputs, outputs, errors, configuration and required call
ordering, not only method signatures. Keep necessary domain choices, failure contracts and effect guarantees
explicit while hiding internal coordination and mechanisms.

Challenge wide interfaces, mode flags, implementation-shaped parameters, caller-managed setup sequences and
forwarding layers that leave callers carrying the complexity. Compare actual caller code before and after a
proposal. Prefer fewer concepts and decisions for the same use case; one generic method with an opaque options
bag is not simpler. Do not preserve an unwieldy interface merely because it already exists: propose a smaller
contract with affected consumers and a deliberate cutover/data-migration decision under
[application compatibility](#application-compatibility-and-data).

Deepening must preserve DDD ownership, domain language, aggregate invariants and bounded-context/package
boundaries, alongside inward dependencies and CQRS command/query/event semantics. Consolidate only cohesive
responsibilities with the same owner and reason to change. Do not merge unrelated use cases into a broad service,
move Domain policy into Application/Adapter, blur reads with mutations, or hide authorization, transaction and
external-effect obligations. Honor accepted package contracts directly under ADR 0001; improvements to an owned
interface or an upstream proposal do not authorize consumer aliases or speculative wrappers.

## CQRS and HTTP

Commands express intent and may change state; execution is `void`. Queries return data without business
mutation. Events report past facts. Messages carry use-case context, not HTTP credentials or protocol policy.

### Entry admission and authorization timing

Every HTTP, Console, MCP or other entry adapter authenticates its actual caller, validates/maps input, checks
operation permission and target/scope admission, dispatches through injected configured buses and presents the
outcome. A claimed actor field is not authentication. HTTP may declare permission once on an Action for central
`ApiActionSecurity` enforcement; other entries need their own appropriate checks, not assumptions about HTTP.
Prospective MCP HMAC verification belongs to that adapter, not business handlers.

Do not impose a second blanket authentication/permission gate in Application or bus filters. The bus is trusted
internal infrastructure. Domain/package ownership and intrinsic business invariants remain with their owner;
adapter admission is not permission to duplicate or move those rules. Genuine cohesive Application business
policy remains reusable across entries without becoming transport authentication.

For ordinary requests, admission-time authorization is sufficient: an admitted request may finish after a
concurrent revocation; the next request checks fresh authority and refuses. Do not require locks or tests solely
to serialize permission revocation through ordinary commit. Preserve locks for real business consistency.
Stronger operation-specific requirements need separate justification. Exact physical confirmation, current
phase admission and writer exclusion in recoverable linking remain required; they do not establish a universal
revocation/commit serialization rule.

TASK-00060's [invitation amendment](../../planning/adr/0001-application-ownership-and-orchestration.md#task-00060-invitation-admission-amendment--2026-11-03)
remains a concrete example: READ_USERS for user/status/page reads; MANAGE_USER_INVITATIONS alone for mutation
APIs with no data response; both for page management. Preserve independent throttling, package generation
comparisons and atomic correction/audit. No post-command repository peeking to fabricate a void-command result.

### Creation identity and recovery

The creation entry adapter generates the typed aggregate ID with its owning factory and puts it in the command;
the aggregate accepts it. After successful void dispatch the adapter can return that known ID without a mutable
message result, extra identity-result DTO or identity-recovery fetch. This is adapter-generated, not arbitrary
untrusted client selection. Controlled preserved-ID import remains a separate validated operation.

Ordinary database creation promises neither transparent replay nor an immutable original response. After a
commit with lost response, a new create attempt may conflict on unique fields. Refresh or a fresh authorized
list/detail query reveals authoritative state and the created ID. Remove original-response snapshots, result
journals and generic replay machinery used only for the superseded ordinary-create guarantee; retain required
audit independently. A post-commit failure is uncertainty, not proof of rollback or permission for blind replay.

Filesystem creation, Git initialization/linking and remote publication can require durable intent, generations,
partial-outcome records, fencing and reconciliation. Retain those separately justified multi-resource contracts,
including exact same-operation results where required by
[ADR 0005's technical contract](REPOSITORY_LINK_CONSISTENCY_PROPOSAL.md). Do not generalize ordinary-create
recovery to physical effects or build a generic operation engine to replace them.

### Safe typed reads and intentional failures

Return explicit safe typed Views such as `ProjectView`, not persistence rows or raw entities. Include appropriate
Project/Git-source facts without exposing credentials or implying checkout availability. PHP properties are
descriptive camelCase; HTTP Data/Responders own snake_case JSON, formatting, status and headers. Console and
MCP own their respective representations.

Use qualified Common Application failure categories directly where their meaning fits, including
`ResourceNotFound` for a required missing-resource query. Repository lookups return aggregate-or-null; optional
discovery returns explicit absent/unavailable/ambiguous states, not an invented required-resource failure.
Domain exceptions stay Domain-owned and do not extend Application categories. Local subtypes need a useful
additional contract. Internal faults must not become expected not-found/conflict merely because a catch is broad.

Adopt Common's safe HTTP boundary only after qualification: deliberate public projections, non-disclosure and
one redaction-aware reporting owner, not stacked competing classifiers/reporters. Reconcile status, headers,
validation, JSend shapes, OpenAPI and frontend consumers together. Common expected failures use `fail`, which
is not automatically today's Agent OS wire contract. Protocol-specific metadata and mappings remain explicit;
MCP/Console do not inherit HTTP status/envelopes. [TICKET-00136](../../planning/tickets/00136-TICKET.md) owns this
cutover; existing safe errors stay executable until replaced, not silently reformatted by this document.

### Sensitive synchronous operations and supplier qualification

Preserve upstream synchronous non-serializable operations for sensitive authentication/password/token inputs
where their public contract requires them. Do not put raw passwords or tokens into Commands/Queries/Events for
superficial bus uniformity; do not persist/log secrets, hashes, ciphertext or credential URLs as message/audit/View
payloads. This actual exception is not permission to bypass buses for ordinary message dispatch. Keep sensitive
material scoped to the synchronous invocation and the package's qualified secret-handling capabilities.

Package ownership is not API availability. Inspect supported tagged signatures, behavior, exact source/release
identity and real consumer composition before adoption; deliberately update manifest/lock in the owning TASK.
Inspected plans/local tags are not remotely verified distribution or Agent OS qualification. Do not guess future
versions/imports, patch vendor, use development snapshots as stable dependencies or add imitation/forwarding
layers. A missing supplier holds its consumers only. Current lock facts and historical receipts remain evidence,
not a guarantee that every target API is available.

### Application compatibility and data

Agent OS is an application, not a library. Deliberate breaking changes are allowed at any stage, including
production: update owned PHP/HTTP/permission/schema consumers and tests/current plans together, verify the
replacement and remove superseded paths. There is no standing application facade, alias, legacy/dual mode or
deprecation obligation solely to preserve abandoned design. Upstream packages retain their own compatibility
obligations; this policy does not waive them.

Breaking changes do not authorize data loss. Determine whether real durable data needs preservation, schema/data
migration or explicit authorized disposal. Preserve needed identities/history and required reconstruction/recovery
contracts, and document observable changes and operator/consumer obligations. An unrelated TASK cannot perform
that disposal or a whole-application cutover merely because compatibility is not mandatory.

### HTTP interactions and presentation

Use Action–Domain–Responder for HTTP interactions. Actions expose `handle()` as their sole entry point;
Responders present through `respond()`. Register API Actions with Slim's `Class:handle` callable so
post-routing validation examines the executed method; web routes call the injected Action's `handle()`.

- One Action handles one interaction, maps validated transport input and authenticated actor/target context, dispatches package queries or one justified owned use case, and delegates presentation. For safe user detail/directory reads, `#[RequiresPermission('READ_USERS')]` on each Action is the single permission declaration, enforced by existing `ApiActionSecurity` against authoritative current request state before execution or user/role dispatch. Do not repeat permission or installation-policy checks in the Actions or relocate duplication into a wrapper; repeated lookups do not establish atomic authorization. Do not put Domain business policy or transaction control in an Action.
- Package/Domain owners retain intrinsic ownership and business invariants; entry adapters own caller permission/target-scope admission under the shared rule above. Genuinely owned Application business policy remains reusable. Do not infer authority from role names, UI reachability, request claims or service presence, or duplicate package-owned invariants in handlers/adapters.
- A Responder owns status, headers, response shape, and boundary mapping; it neither fetches missing business data nor decides policy. JSend is scoped to the documented API, not every HTTP path or other transport; an arbitrary lookup failure does not become a public 404 without explicit use-case context.
- Cross presentation boundaries with explicit safe Views, not raw entities. Keep PHP properties camelCase and map HTTP JSON and database fields to snake_case.
- Enforce authorization on the server. Sanitize public errors and keep secrets out of diagnostics.
- Permission eligibility never implies assignment. Keep tiers, explicit managed Role defaults and human/Agent grants independent; no new membership follows merely from defining or making a Permission delegable. Follow [the managed authority assignment contract](MANAGED_POLICY.md#permission-eligibility-is-not-assignment), including for Super Admin defaults.

For API Action and Responder class/method declarations, group applicable attributes in source order:
**OpenAPI → input source → Validation → access requirements → other applicable metadata**. Keep each
attribute in its own block and on its supported target; Responders normally own only OpenAPI response
metadata. Preserve deliberate order within repeatable groups. This is presentation, not middleware or
permission execution order. See [API input sources and limits](../api/README.md#explicit-input-sources-task-00155).

## Frontend conventions

For frontend changes, follow the [client conventions](../../client/README.md#frontend-conventions): mirrored
`client/tests/`, application-root imports, semantic/import lint and explicit formatting, with default SCSS
separate in `client/styles/`. That document owns composition order, theme-extension limits, supported tooling
and read-only check versus explicit fix/install commands. Preserve runtime behavior and accessibility when
changing organization or tooling; verify the production shell and catalog through their actual builds/browsers.

## Pi and TypeScript module naming

Name Pi modules for the capability they own, using kebab-case filenames. Reserve `-extension.ts` for entry
points loaded by the Pi package manifest: `header-extension.ts`, `jev-extension.ts` and
`tool-policy-extension.ts`. Use `-integration.ts` for an internal SDK registration boundary when a suffix
clarifies its role: `file-screening-integration.ts` is installed by the Jev adapter, not loaded by Pi itself.
Reusable logic keeps capability names such as `header.mjs`, `file-admission.mjs` and `workflow-access.mjs`.

Use camelCase functions, PascalCase exported classes/types, and `install<Capability>` registration functions
(`installHeader`, `installJev`, `installFileScreening`, `installToolPolicy`). Keep a provider name when the
module actually adapts that provider, as `jev-extension.ts` does. Avoid generic `extension`, `manager`, `utils`
or `service` names that hide responsibility. Apply capability ownership without importing PHP namespaces or
class boilerplate; do not add wrappers or directories solely for uniformity.

When renaming, inventory package and explicit-path loading plus exported callers first. Migrate owned imports,
declarations, tests/probes, typecheck inputs, package contents and runnable documentation together. Document
removed paths and full-process restart instructions; retain a compatibility export only for a demonstrated
supported caller with an explicitly justified transition, outside the manifest and with duplicate-registration qualification;
do not assume a standing compatibility obligation. Preserve public command/tool
IDs, operator state and historical evidence. See the [package migration instructions](../../harness/pi/README.md#module-path-migration).

## PHP and naming

Prefer `final` classes and readonly properties, with Doctrine entity exceptions; make value objects readonly. Use constructor injection, strict types, guard clauses, and Fight helpers where they improve clarity. Name commands, queries, and events with intent or fact (`RegisterUser`, `GetUserById`, `UserRegistered`) and name their handlers with `Handler`.

Use multiline docblocks with capitalized active-verb summaries and no terminal period. Document constructors as `Constructs ClassName`; inherited behavior uses multiline `@inheritDoc`.

## Tests

Write testable behavior, then tests at the narrowest boundary that proves it. Prefer Domain/Application unit tests, Adapter integration tests, and functional HTTP tests as their contracts require. Cover meaningful success, rejection and failure outcomes, state changes and external effects where applicable; document why a category does not apply. Test the actual owned policy or important package integration contract, not just a mock's call sequence. An explicit mapper contract can be proven directly; end-to-end claims require a real interaction that reaches it. Do not add a test-only production route, a gratuitous whole-app harness or non-API presentation just to exercise a scoped failure. Keep existing smoke behavior intact.

Product suites test owned application behavior and important integration contracts. They do not test architecture or dependency rules, container/configuration wiring, migrations, generated files, repository wrappers, build/planning/static-analysis mechanisms, test infrastructure, or upstream package behavior. Validate those concerns directly with their owning tools. Never add deliberately invalid fixtures merely to prove that a test or quality tool detects them. Do not test the tests.

Aim for 100% unit-test coverage of meaningful owned unit-testable behavior where practical. Report unit coverage
separately from integration/functional coverage, with denominator, driver availability and justified gaps; a combined
suite percentage is not a unit-coverage result. The [backend coverage phase](BACKEND_TESTS.md) enforces 100%
meaningful Domain/Application line coverage across one all-suite execution and reports unit attribution separately;
the [canonical gate](BUILD.md) includes it once. Adapter 100% remains a practical target, with gaps visible.
Coverage remains a completeness aid, including Adapter behavior where practical. Report actual configured coverage and omissions without claiming that a percentage establishes correctness or protocol conformance. A covered line does not justify a test by itself: tests must assert an observable contract, outcome, or side effect. Exclude non-behavioral composition and generated code rather than manufacturing tests for percentages.

For a bug, reproduce the behavior and make one regression test fail before repair when technically possible. Record why when it is not possible. Focused checks shorten the loop; only the repository canonical gate establishes complete local verification.
