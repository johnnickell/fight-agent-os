# Recover Fight Software Factory source conventions

## Question

Which conventions from `fight-software-factory` should become Fight Agent OS guidance for building the first usable web application foundation, and which Factory-specific material must remain reference-only?

This matters because the new `work`, `review`, and `land` process needs consistent architecture and quality rules before agents implement registration, login, sessions, authorization, or UI.

## Short answer

Adopt a reviewed, project-local subset of the Factory branch's portable engineering standards: DDD and ports/adapters direction, CQRS semantics, Action–Domain–Responder HTTP boundaries, Fight naming/PHP conventions, behavior-oriented testing, independent review criteria, and resource-conscious delivery rules. Keep the existing `App\` PSR-4 root and the repository's Domain/Application/Adapter top-level layout.

Do not import the Factory's SSSF/ADW implementation, `bin/fight-*` launchers, Python coordinator, Vue observatory, model roster, prompts, planning history, or workflow authority. Their useful process ideas should be restated in fresh Pi skills rather than copied as executable workflows.

The exact bounded-context subdirectories and authentication use-case layout remain a downstream architecture decision in [WF-004](../tickets/WF-004-define-application-foundation-architecture.md); the source material does not settle them.

## Evidence scope and provenance

- The remote's `main` at `de31374882e7a4e3e5b7bb9bd09e69dc2f779356` is the upstream **Super Simple Software Factory** skill. It does not contain the Fight-specific PHP/React standards and is not the right source for application conventions.
- The Fight-specific standards are present on `feature/repository-workflows` at `75a59ffd82d8b065cc153d1d336da9ed31f5e4f9`, building on the Fight foundation branch at `0339b3e20717f366a12f24bc6a3f2b0c707e6805`.
- The workflow branch itself says it is a Python/Vue execution tool and that PHP/React rules apply to consuming projects, not to the Factory implementation. Its governance standard requires explicit reviewed copies, local exceptions, and no automatic synchronization.
- Fight Agent OS already records compatible high-level rules in `AGENTS.md`, `ARCHITECTURE.md`, and `planning/CONVENTIONS.md`. Adoption should reconcile and deepen those rules, not add a competing authority.

## Findings

### 1. Adopt the architecture rules, not a framework-shaped application skeleton

The source architecture standard establishes the direction `Adapter → Application → Domain`:

- Domain owns business concepts, state, invariants, value objects, and state-based decisions.
- Application handlers coordinate use cases through injected Domain/Application capabilities.
- Adapters own framework, persistence, external service, and presentation concerns.
- Commands express intent, queries return data without business mutation, and events express past facts.
- Transport details stay out of domain messages unless they are meaningful use-case context.
- Entities and value objects retain domain knowledge; handlers and adapters must not reconstruct policy from getters.
- Capability interfaces are added at demonstrated boundaries, not for speculative extensibility.
- Transaction, event-delivery, retry, idempotency, and recovery guarantees must be stated from actual behavior rather than inferred from CQRS terminology.

These rules fit the accepted Agent OS architecture and should become durable project guidance. The source does **not** require event sourcing, an outbox, one class per abstraction, or blanket transactions around every handler.

### 2. Keep `App\` and make path/namespace mapping explicit

The current Composer contract maps `App\` to `src/`; replacing it has no demonstrated benefit. New code should keep that root and map namespaces directly to paths:

- `App\Domain\…` → `src/Domain/…`
- `App\Application\…` → `src/Application/…`
- `App\Adapter\…` → `src/Adapter/…`
- `Tests\…` → `tests/…`

The source gives one concrete HTTP example: `src/Adapter/Http/Action/Api/User/RegisterUserAction.php`. It does not establish a complete nested directory standard for Domain, Application, persistence, web pages, or responders. [WF-004](../tickets/WF-004-define-application-foundation-architecture.md) should choose the context/use-case nesting before feature TASKs are written. Until then, the binding convention is the top-level layer boundary plus PSR-4 path equality; agents must inspect nearby accepted code rather than inventing type-bucket trees.

### 3. Adopt Action–Domain–Responder with an explicit meaning for each part

The source HTTP standard uses **Action–Domain–Responder** and explicitly distinguishes it from an **Architecture Decision Record**:

- One Action handles one HTTP interaction through one public `handle(...)` method.
- Constructor injection supplies buses and presentation collaborators.
- The Action extracts and validates transport input, accounts for permission enforcement, dispatches a command/query, and delegates output.
- Application and Domain own the use case and policy; the Action is not an alternate workflow.
- Responders own response shape, status, headers, JSend envelopes or templates, and boundary transformations.
- Responders do not fetch missing business data or decide business policy.
- Explicit safe Views cross the presentation boundary; raw entities do not.
- PHP/frontend properties stay camelCase while HTTP JSON and database columns are snake_case, with explicit boundary mapping.
- Public errors are sanitized deliberately; diagnostics are logged without exposing secrets. Route guards never replace server authorization.

This should govern API and server-rendered endpoints. The concrete response type remains Slim/project-specific: the Symfony-specific attribute examples in the source are not applicable here.

### 4. Adopt the Fight naming and PHP conventions already summarized by this repository

The source supports these existing Agent OS rules:

- `RegisterUser`, `GetUserById`, and `UserRegistered` for commands, queries, and events.
- Corresponding `…Handler` classes.
- Capability names such as `UserRepository` and `MailTransport`, without an `Interface` suffix.
- One-interaction names such as `RegisterUserAction` and explicit safe outputs such as `UserView`.
- `final` classes and `readonly` properties by default; readonly value objects; explicit Doctrine entity exceptions.
- Constructor injection, strict types, alphabetized imports, guard clauses, and project Fight helpers where they improve clarity.
- Multiline docblocks, capitalized active-verb summaries without terminal periods, `Constructs ClassName` for constructors, and multiline `@inheritDoc` for inherited documentation.

Mechanical formatting must come from the repository's installed/configured tools once those tools exist. A prose standard must not claim enforcement that `./bin/build` does not yet perform.

### 5. Use behavior-oriented tests and replace scaffold qualification with application evidence over time

The source testing standard recommends testable feature code first and tests second; bug repair starts with reproduction and one failing regression test. It prioritizes:

- Unit tests for owned Domain/Application behavior.
- Integration tests where adapter or composition boundaries need proof.
- Functional tests for actual HTTP behavior.
- Success, rejection, failure, and business effects—not line execution alone.
- Real value objects where practical and mocks chosen according to the seam being proved.
- Meaningful assertions against contracts rather than tests that mirror implementation or inspect source/configuration text.
- Focused checks during iteration and the complete canonical gate before implementation publication.

The inherited Agent OS suite is dominated by broad Fight Common/container qualification journeys such as messaging filters, mail/SMS fallbacks, cache/filesystem behavior, process scheduling, observability contracts, and direct JWT/password helper round trips. Those tests are evidence that the starter wiring worked, not evidence for registration/login/dashboard behavior. [WF-003](../tickets/WF-003-qualify-dependency-baseline.md) should decide each removal or replacement. This decision does not authorize deleting them preemptively.

Recommended eventual test organization, consistent with the current suite, is:

- `tests/Unit/Domain/…`
- `tests/Unit/Application/…`
- `tests/Integration/Adapter/…` or another boundary-specific integration path
- `tests/Functional/Http/…`
- reusable doubles/builders under `tests/Fixture/…`

That nested organization is a project recommendation derived from the layer model and current test categories, not a directory tree explicitly specified by the Factory source. WF-004 should ratify it.

### 6. Carry process principles into fresh skills, but do not import Factory workflows

Useful principles for the future skills are:

- One TASK normally owns one independently reviewable PR.
- Ask for main-checkout versus isolated-worktree placement before implementation and preserve unrelated work.
- Separate implementation, formal review, publication, landing, release, and deployment authority.
- Let deterministic repository commands execute known checks; use agents for reading and judgment.
- Save exact local gate evidence, surface warnings, and bind review to the TASK and reviewed content.
- Review Spec and Standards independently, attempt to disprove findings, and keep builders from self-approving.
- Land only after applicable acceptance, verification, review, and host protections; clean only demonstrably TASK-owned resources.

Material that must remain reference-only:

- `.claude/skills/sssf/`, ADW templates, generated prompts, typed workflow envelopes, and SSSF installation machinery.
- `bin/fight-*`, `factory_*.py`, the bounded automatic build/review loop, and its resume/timeout behavior.
- `fight.config.yaml`, hard-coded model roles/providers, private relay assumptions, and approval preferences.
- SQLite trace schema, Vue observatory implementation, repository registry, usage collector, and workstation service setup.
- Factory TASK history, approvals, evidence, branch policy exceptions, Python/Vue coverage rules, and `main`-targeting behavior.

The new execution skills should therefore be designed from these principles as `work`, `review`, and `land` candidates in [WF-002](../tickets/WF-002-shape-agent-skill-suite.md), not as renamed wrappers around `fight-work`, `fight-review`, or `fight-land`.

### 7. Adopt frontend guidance selectively after the design research

The source frontend standard has reusable responsibility boundaries for React:

- Route owns URL matching and navigation guards.
- Layout owns the shared shell/navigation/outlet.
- Page owns use-case coordination and local state.
- Components/forms own interaction and validation feedback.
- Feature API services own typed operations and snake_case/camelCase mapping.
- A shared API client owns transport, authentication refresh coordination, and common errors.

It also requires loading, empty, failure, and recovery states, and keeps authorization on the server. These are useful inputs for WF-004/WF-005. However, its Bootstrap preference and detailed access-token timing are source-project proposals, not automatically accepted Agent OS decisions. UI stack, session transport, CSRF, token/cookie behavior, and visual language remain open.

## Recommended adoption package

When implementation planning authorizes it, create project-local standards rather than linking agents to the scratch clone:

1. Add a small `docs/engineering/STANDARDS.md` adoption record with source revision, selected documents, and Agent OS deviations.
2. Adapt only Architecture, Naming, PHP, HTTP, Testing, Review, Delivery, and applicable Frontend guidance.
3. Keep `AGENTS.md` as the concise entry point and link standards by work type.
4. Keep domain language and current architecture in a project-owned context document or `ARCHITECTURE.md`; do not copy the Factory's Python/Vue `CONTEXT.md`.
5. Preserve `planning/CONVENTIONS.md` as the authority for EPIC → TICKET → TASK rather than importing the Factory copy.
6. Let future `work`, `review`, and `land` skills load only the standards relevant to their operation.

## Tradeoffs and risks

- Copying every Factory document would introduce contradictory Python/Vue, model, branch, runtime, and observability rules.
- Keeping only terse `AGENTS.md` bullets risks underspecifying HTTP error handling, testing evidence, review independence, and cleanup ownership.
- A reviewed local subset costs some maintenance but makes the rules available in every checkout and prevents hidden dependence on a private source clone.
- Exact nested directories chosen too early could encode an artificial bounded context. Deferring those details to WF-004 keeps this research factual.
- The source branch is unpublished work rather than a versioned standards release. Record the commit hash and review adapted text as project-owned guidance.

## Sources

Primary sources inspected at Fight Software Factory commit `75a59ffd82d8b065cc153d1d336da9ed31f5e4f9`:

- [`AGENTS.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/AGENTS.md)
- [`CONTEXT.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/CONTEXT.md)
- [`docs/engineering/STANDARDS.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/STANDARDS.md)
- [`Architecture.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Architecture.md)
- [`Naming.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Naming.md)
- [`PHP.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/PHP.md)
- [`HTTP.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/HTTP.md)
- [`Testing.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Testing.md)
- [`Review.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Review.md)
- [`Delivery.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Delivery.md)
- [`Frontend.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Frontend.md)
- [`Governance.md`](https://github.com/johnnickell/fight-software-factory/blob/75a59ffd82d8b065cc153d1d336da9ed31f5e4f9/docs/engineering/standards/Governance.md)

Current Fight Agent OS sources:

- [`AGENTS.md`](../../../AGENTS.md)
- [`ARCHITECTURE.md`](../../../ARCHITECTURE.md)
- [`composer.json`](../../../composer.json)
- [`planning/FOUNDATION.md`](../../FOUNDATION.md)
- [`tests/`](../../../tests/)

## Open questions

- WF-004 must choose bounded-context and use-case nesting below the accepted top-level layers.
- WF-003 must identify exactly which inherited compatibility tests remain useful until equivalent application behavior exists.
- WF-002 must decide how much of testing/evidence collection belongs in `work` versus deterministic repository commands.
- WF-005 must decide session transport, cookie/token behavior, CSRF protections, and initial role/permission policy.
- WF-006 must add design-specific guidance; the Factory frontend standard covers engineering responsibility, not visual design quality.
