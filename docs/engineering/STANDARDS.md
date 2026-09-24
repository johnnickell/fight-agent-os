# Engineering Standards

These are the minimum shared rules for application changes. `ARCHITECTURE.md`, accepted ADRs, and nearby owned code supply decisions that are specific to a use case.

## Architecture

Dependencies point `Adapter → Application → Domain`.

- Domain owns business concepts, state, invariants, value objects, and policy decisions.
- Application handlers coordinate commands and queries through injected capabilities.
- Adapters own HTTP, persistence, framework, presentation, and external-service concerns.
- Add capability-named interfaces at demonstrated boundaries; omit an `Interface` suffix and speculative abstractions.
- State transaction, delivery, retry, idempotency, and recovery guarantees from observed behavior. CQRS alone implies none of them.

Keep `App\` mapped directly to `src/`: `App\Domain`, `App\Application`, and `App\Adapter`. Follow accepted nearby structure below those layers rather than inventing type buckets.

## CQRS and HTTP

Commands express intent and may change state. Queries return data without business mutation. Events report facts that already happened. Messages carry use-case context, not transport details, and handlers do not reconstruct Domain policy from exposed state.

Use Action–Domain–Responder for HTTP interactions:

- One Action handles one interaction, validates transport input, accounts for authorization, dispatches the use case, and delegates presentation.
- Application and Domain own workflow and policy.
- A Responder owns status, headers, response shape, and boundary mapping; it neither fetches missing business data nor decides policy.
- Cross presentation boundaries with explicit safe Views, not raw entities. Keep PHP properties camelCase and map HTTP JSON and database fields to snake_case.
- Enforce authorization on the server. Sanitize public errors and keep secrets out of diagnostics.

## PHP and naming

Prefer `final` classes and readonly properties, with Doctrine entity exceptions; make value objects readonly. Use constructor injection, strict types, guard clauses, and Fight helpers where they improve clarity. Name commands, queries, and events with intent or fact (`RegisterUser`, `GetUserById`, `UserRegistered`) and name their handlers with `Handler`.

Use multiline docblocks with capitalized active-verb summaries and no terminal period. Document constructors as `Constructs ClassName`; inherited behavior uses multiline `@inheritDoc`.

## Tests

Write testable behavior, then tests at the narrowest boundary that proves it. Prefer Domain/Application unit tests, Adapter integration tests, and functional HTTP tests as their contracts require. Prove success, rejection, failure, and business effects rather than implementation shape or source/configuration text.

Product suites test owned application behavior and important integration contracts. They do not test architecture or dependency rules, container/configuration wiring, migrations, generated files, repository wrappers, build/planning/static-analysis mechanisms, test infrastructure, or upstream package behavior. Validate those concerns directly with their owning tools. Never add deliberately invalid fixtures merely to prove that a test or quality tool detects them. Do not test the tests.

Coverage is a completeness goal for meaningful owned behavior, including Adapter behavior where practical. A covered line does not justify a test by itself: tests must assert an observable contract, outcome, or side effect. Exclude non-behavioral composition and generated code rather than manufacturing tests for percentages.

For a bug, reproduce the behavior and make one regression test fail before repair when technically possible. Record why when it is not possible. Focused checks shorten the loop; only the repository canonical gate establishes complete local verification.
