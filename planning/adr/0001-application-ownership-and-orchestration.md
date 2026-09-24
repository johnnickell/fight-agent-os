# ADR 0001: Define application ownership and orchestration boundaries

- **Status:** Accepted
- **Date:** 2026-09-23
- **Decision owners:** Fight Agent OS maintainers
- **Acceptance:** Explicitly accepted by the human maintainer under TASK-00009 on 2026-09-23

## Context

Fight Access Control is the authoritative, framework-neutral identity and authorization package. Its public
`Fight\AccessControl\Domain` and `Fight\AccessControl\Application` namespaces already provide identity and
agent entities, value objects, repositories, commands, queries, safe Views, events, policies, handlers, and
capability contracts. Fight Common provides the command, query, event, and transaction capabilities used to
compose those contracts.

Fight Agent OS must integrate that behavior into Slim, PostgreSQL, external providers, and HTTP delivery without
copying package policy, hiding package contracts behind aliases, or moving business decisions into framework
code. It also needs a narrow place for behavior that genuinely belongs to this application or coordinates more
than one ownership boundary.

This decision specializes [EPIC-00003](../epics/00003-EPIC.md) and the closed
[WF-004 decision](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md). It retains the
source and dependency conventions accepted by [WF-001](../wayfinder/tickets/WF-001-recover-source-conventions.md)
and [WF-003](../wayfinder/tickets/WF-003-qualify-dependency-baseline.md).

## Decision

### Package and application ownership

Fight Access Control owns identity and authorization Domain and Application behavior. Agent OS uses its public
commands, queries, entities, value objects, repositories, policies, safe Views, events, handlers, and capability
contracts directly when that package already expresses the use case. Agent OS must not copy those types into the
`App` namespace or add wrappers whose only purpose is renaming, forwarding, reshaping a constructor, or hiding a
package namespace.

Fight Agent OS owns:

- Slim and runtime integration, configuration, and composition;
- PostgreSQL mappings, migrations, and repository implementations of package capabilities;
- HTTP transport, validation integration, authentication-context integration, endpoint presentation, and safe
  error mapping;
- provider adapters and recoverable external-effect delivery;
- Agent OS-specific Domain behavior; and
- Application orchestration only where an Agent OS policy or a use case crossing ownership boundaries requires it.

Fight Common continues to own its generic messaging and transaction contracts and adapters. Agent OS owns their
selection and wiring, not replacement aliases for them.

For example, a future user-list interaction dispatches the package `ListUsers` query and consumes its package
`UserView`; it does not introduce `App\Application\ListUsers` or `App\Domain\UserView` aliases. An Agent OS
Responder may map that safe View into the endpoint's documented representation. Likewise, an Agent OS persistence
adapter implements the package `UserRepository` contract directly rather than defining an identical owned
repository interface.

### Test for owned Application orchestration

An `App\Application` use case is justified only when all of these statements are true:

1. Fight Access Control or another owning package does not already provide the behavior.
2. The behavior enforces an Agent OS application policy or coordinates two or more ownership boundaries, such as
   package behavior plus an Agent OS-owned durable capability.
3. It has its own observable input, result or event, failures, and behavior tests; its responsibility can be stated
   without reference to a transport or framework.
4. It depends on injected inward contracts and capabilities rather than concrete adapters or a service container.

A type that merely forwards to one package command, query, handler, or View fails this test. Cross-boundary work
must not be invented in anticipation of a future use case; omit the orchestrator until a requirement demonstrates
it.

### Dependency direction and composition

Dependencies point inward with the following rules:

- `App\Domain` owns Agent OS business concepts and policy. It may use stable public Domain types that are part of
  its business language, but it must not import Application, Adapter, service-container, persistence, HTTP,
  framework, filesystem, mail, network, or provider implementations.
- `App\Application` may depend on `App\Domain`, public Fight Access Control Domain/Application contracts, and
  capability abstractions. It must not depend on `App\Adapter`, Slim, Doctrine implementations, provider SDKs, or
  other framework implementations.
- `App\Adapter` implements inward capabilities and may depend on Application, Domain, frameworks, persistence,
  transport, and providers. Adapter organization is capability-first and subdivided semantically only when needed.
- `config/`, runtime bootstrapping, and route registration form the composition root. They may know concrete inner
  and outer types to construct the graph and register routing. This exception does not permit business code to
  retrieve dependencies from the service container.

Constructors receive dependencies explicitly. A capability is named for the behavior it supplies and has no
`Interface` suffix. New abstractions require a demonstrated boundary; speculative context, service, manager,
repository, or provider wrappers are prohibited.

The existing service-aware routers may use the container because they are Adapter/composition machinery. Commands,
queries, handlers, Domain services, and owned orchestrators may not.

### CQRS ownership and dispatch

Commands express intent and may change state. Queries retrieve information without business mutation. Events state
facts that have happened. The owner of the behavior owns its message and handler:

- dispatch package commands and queries through the Fight Common `CommandBus` and `QueryBus` without translating
  them into owned aliases;
- register the package handler against the package message in the composition root and inject its package-declared
  capabilities there;
- preserve package events as package events, and do not translate or re-emit them merely to change their names;
- add an Agent OS command, query, event, or handler only for behavior that passes the owned-orchestration test; and
- dispatch an owned event only for an Agent OS-owned fact, after the state change establishing that fact succeeds.

CQRS does not imply a transaction, asynchronous execution, durable delivery, retry, ordering, idempotency, or
exactly-once behavior. Each such guarantee must come from the owning use case and adapter contract.

### HTTP interaction and presentation

Each HTTP interaction has one final Action. The Action maps already validated transport input and authenticated
context into exactly one package/owned command, one package/owned query, or one explicitly justified orchestrator,
then delegates successful presentation. It contains no Domain policy, authorization policy, persistence logic,
transaction control, or external-provider logic.

An endpoint-specific Responder owns successful status, headers, JSend envelope, and transport mapping. A package
safe View may cross into the Responder, but package entities, password hashes, credentials, grants, secret-bearing
values, and persistence models must not become transport models. Transport fields are explicitly mapped to
`snake_case`; PHP properties remain camelCase. Validation and centralized safe error mapping remain transport
concerns and must not expose arbitrary exception messages or internals.

Authentication middleware may establish trusted principal context, but authorization remains a server-side
business/application decision for every protected command and query. Enforcement uses explicit permission and
ownership policy against authoritative state. An Action, middleware, or handler must not grant access because of a
role name, UI visibility, route reachability, or client-provided authority. The transport supplies actor/target
context to the owning package policy or justified owned use case and does not reproduce that policy itself.

### Transactions and external effects

The Application handler or explicit orchestrator that owns a state-changing use case owns its transaction boundary
through an injected transaction capability. Persistence adapters participate on the same connection. The
transaction includes authoritative business state, required audit evidence, and any required durable delivery
intent that must survive a process failure.

Email, HTTP calls, filesystem publication, provider calls, and other external effects must not execute inside that
business transaction. Immediate event dispatch and delivery attempts occur only after commit. Required effects are
recoverable from durable intent, with later requirements defining retry, leasing, idempotency, and at-least-once
behavior. No component may claim exactly-once external delivery.

The installed Fight Access Control handlers currently declare the Fight Common compatibility `UnitOfWork`, which
extends `TransactionalUnitOfWork`, while the preferred new consumer contract is `TransactionalUnitOfWork`. Agent OS
must honor the exact installed public handler contract directly and must not add a consumer-owned alias to conceal
it. The persistence decision under TICKET-00009 must reconcile the concrete binding and upgrade path with the
canonical Doctrine transaction adapter before production persistence is accepted.

## Prohibited designs

The following are explicitly prohibited:

- owned aliases or renaming wrappers around Fight Access Control commands, queries, handlers, entities, Views,
  events, policies, repositories, or capabilities;
- service location from Domain or Application code;
- framework, HTTP, persistence, or provider imports into Domain, or concrete Adapter/framework imports into
  Application;
- business, authorization, persistence, transaction, or external-effect policy in Actions or Responders;
- authorization based on role names or client/UI decisions;
- exposing entities, hashes, credentials, grants, secret-bearing values, or persistence models at transport
  boundaries;
- direct external effects inside business transactions;
- translating package events merely to rename them; and
- speculative contexts, abstractions, message types, handlers, or adapters without a demonstrated use case.

## Enforcement

| Rule | Enforcement owner |
|---|---|
| Direct package-type composition and inward dependency direction | [TASK-00010](../tasks/00010-TASK.md) introduces the representative composition and Deptrac checks; semantic ownership and injection concerns remain review responsibilities |
| Exact transaction capability, PostgreSQL adapter contracts, shared connection, and atomic state/durable-intent behavior | [TICKET-00009](../tickets/00009-TICKET.md) and its persistence ADR/tests |
| One-Action/one-interaction, endpoint Responders, explicit safe representations, transport validation, and centralized safe errors | [TICKET-00010](../tickets/00010-TICKET.md) with functional HTTP evidence |
| Post-commit external delivery, recovery, retry, leasing, idempotency, and secret-safe provider adapters | [TICKET-00011](../tickets/00011-TICKET.md) with behavior and PostgreSQL concurrency evidence |
| PHP syntax, the bundled Fight Common 1.2 PHPCS standard, PHPStan, Deptrac, Rector dry-run, backend tests/coverage, bounded frontend checks, and final gate integration | [TICKET-00013](../tickets/00013-TICKET.md) extends the early checks into `./bin/build` |

The final pre-submit gate uses a thin `bin/build` entrypoint to invoke a PHP phase orchestrator such as
`scripts/build.php` inside the already-running Docker Compose PHP service. It does not rebuild a one-off image on
every invocation. The orchestrator runs named, fail-fast phases and does not introduce Python quality-gate
orchestration. Existing planning validation remains an input rather than a reason to duplicate it in PHP.

The backend phases include PHP syntax linting, PHPCS configured from Fight Common 1.2's shipped standard, PHPStan,
Deptrac, Rector in dry-run mode, and one coherent PHPUnit/coverage execution. Every configured Unit, Integration,
Functional, and PostgreSQL-backed suite runs exactly once in the canonical gate; a coverage policy reads that same
run's report rather than rerunning tests. Focused wrappers remain iteration tools and are not additional canonical
passes. Frontend phases remain bounded to formatting/lint/type checks, Vitest behavior and coverage, production
build, and justified Storybook/component checks. Dependency installation, updates, and audits remain explicit
maintenance operations rather than pre-submit tests.

The gate does not test its wrappers, orchestration, configuration, static-analysis tools, test infrastructure, or
seed deliberately invalid source merely to prove tool behavior. Semantic rules such as “genuine orchestration,”
one interaction per Action, correct authorization placement, event ownership, package ownership, and absence of
speculative abstractions require direct review where Deptrac cannot represent them. Later TICKETs add behavior
evidence only when their real boundaries exist rather than manufacturing placeholder source or tests.

## Alternatives considered

### Recreate package behavior under `App` namespaces

Rejected. It creates competing identity and authorization authorities, obscures upstream behavior, and makes
security fixes and package upgrades diverge.

### Wrap every package contract behind an Agent OS interface

Rejected. A wrapper that only aliases or forwards adds no capability boundary and makes ownership less visible.
Agent OS introduces an abstraction only for demonstrated Agent OS-owned behavior.

### Put all coordination in package handlers

Rejected. Fight Access Control must remain framework-neutral and cannot own Agent OS-specific cross-boundary policy
or provider integration. Genuine Agent OS orchestration belongs in `App\Application` under the narrow test above.

### Put workflow and authorization in Slim Actions

Rejected. Transport code is difficult to reuse and test as business policy, reverses dependency direction, and
risks inconsistent authorization across transports.

### Perform provider effects inside the state transaction

Rejected. Database rollback cannot undo an external effect, and a process failure around commit cannot be recovered
reliably without durable intent.

## Consequences

- Package ownership remains visible in imports, composition, tests, and events.
- Agent OS source stays small until an application-specific boundary is demonstrated.
- Adapters can change without changing Domain/Application policy, and HTTP representations can evolve without
  exposing package entities or secrets.
- Composition must register more explicit package handlers and capabilities, but that wiring remains outside
  business code.
- Some semantic boundaries remain review-enforced until representative persistence, HTTP, authorization, and
  delivery code exists.
- The installed `UnitOfWork` compatibility contract requires explicit persistence follow-up rather than a hidden
  alias or an unapproved upstream change.

## Deferred decisions

This ADR does not choose schemas, mappings, lock strategies, durable-intent storage, endpoint representations,
exception-to-status mappings, browser token/cookie controls, provider enrollment, or an architecture-tool version.
Those decisions remain with TICKET-00009 through TICKET-00011 and TICKET-00013. Feature-specific identity and
session journeys retain their own planning authority. Any required upstream Fight package change needs separate
package authorization, release evidence, and an updated stable dependency baseline.

## Evidence reviewed

- [EPIC-00003](../epics/00003-EPIC.md) and the closed
  [WF-004 decision](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md).
- [Architecture](../../ARCHITECTURE.md), [engineering standards](../../docs/engineering/STANDARDS.md), and the
  current `src/Adapter`, `config/common/{command,query,event,persistence}.php`, and composition files.
- Locked Fight Access Control `v0.2.0` public namespaces and representative contracts: `InvitePendingUser`,
  `InvitePendingUserHandler`, `ListUsers`, `ListUsersHandler`, `UserView`, `UserRepository`, authorization
  capabilities, and package-owned events.
- Locked Fight Common `v1.2.0` public `CommandBus`, `QueryBus`, `EventDispatcher`, `TransactionalUnitOfWork`,
  compatibility `UnitOfWork`, service-aware routers, Doctrine transaction adapters, and shipped PHPCS standard.
- The Fight Common quality-phase order and the Agent OS Compose `web` service/current `bin/build`, used as evidence
  for the accepted PHP-orchestrated, running-container gate direction without importing source-project scripts.
- [TASK-00010](../tasks/00010-TASK.md) and downstream persistence, HTTP, delivery, and quality-gate TICKETs used to
  assign enforcement rather than claiming rules are already automated.

## Acceptance

The human maintainer explicitly accepted ADR 0001 on 2026-09-23 after reviewing the ownership boundary and
requesting the recorded pre-submit gate refinements. Acceptance includes the Fight Common 1.2 PHPCS standard,
PHP-orchestrated running-Compose-service gate, non-duplicated all-suite PHPUnit coverage execution, bounded
frontend checks, and no new Python quality-gate orchestration. It does not assert implementation of deferred
production boundaries or gate tooling.
