# Engineering Standards

These are the minimum shared rules for application changes. `ARCHITECTURE.md`, accepted ADRs, and nearby owned code supply decisions that are specific to a use case.

## Architecture

Design architecture, domain language and capability interfaces for human readers first. Ordinary feature work
need not follow TDD: settle ownership and interfaces, implement testable behavior, then prove it with meaningful
tests. Test convenience must not dictate public APIs or fragment cohesive concepts.

Dependencies point `Adapter → Application → Domain`.

- Domain owns business concepts, state, invariants, value objects, and policy decisions.
- Application handlers coordinate commands and queries through injected capabilities.
- Adapters own HTTP, persistence, framework, presentation, and external-service concerns.
- Add capability-named interfaces at demonstrated boundaries; omit an `Interface` suffix and speculative abstractions.
- State transaction, delivery, retry, idempotency, and recovery guarantees from observed behavior. CQRS alone implies none of them.

Keep `App\` mapped directly to `src/`: `App\Domain`, `App\Application`, and `App\Adapter`. Organize below each layer by the responsibility actually owned, then by capability or transport where useful. `Middleware` holds only code that implements a middleware pipeline; HTTP failure classification, mapped representations, validation values, and response mapping belong in the appropriate `Adapter/Http/Api/` or other transport home, not `Middleware`. Responders and safe transport representations belong in Adapter; Domain policy stays with the concept that knows the rule. Do not create speculative `Service`, `Manager`, `Helper`, `Value`, or protocol-neutral buckets for types without a demonstrated boundary. Review an existing exception against its actual role, callers and accepted contract; move it only when the scoped work owns the change and can prove compatibility. Do not bulk-move unrelated source to conform to a diagram.

[Fight Access Control ownership](../../planning/adr/0001-application-ownership-and-orchestration.md) governs identity and authorization. Dispatch its public package messages, handlers and safe Views directly for package-owned use cases; do not recreate them behind Agent OS aliases. Introduce owned Application orchestration only when a missing package behavior enforces Agent OS policy or coordinates real ownership boundaries with observable input, result/failure and injected inward capabilities. Place state-based business decisions with their Domain owner; a handler coordinates them and an adapter translates them, not the other way around. A repository-backed condition may need an injected capability or application policy rather than a getter-based replica of entity policy. Assess each use case against the accepted ownership ADR before extracting a specification or adding a handler.

Review namespace cohesion as a project taste criterion: when a directory combines unrelated responsibilities,
propose deeper capability-based subnamespaces with examples of callers and ownership. Class count is a signal,
not a numeric limit. Prefer meaningful depth over flat miscellaneous buckets; avoid one-class folders or layers
created only to satisfy a diagram. `Adapter/Persistence` is a candidate for this assessment (repositories, grant
transitions, hydration, locking and infrastructure), not authorization to move it during unrelated work.

## CQRS and HTTP

Commands express intent and may change state. Queries return data without business mutation. Events report facts that already happened. Messages carry use-case context, not transport details, and handlers do not reconstruct Domain policy from exposed state.

Use Action–Domain–Responder for HTTP interactions:

- One Action handles one interaction, maps validated transport input and authenticated actor/target context, dispatches one package or owned use case, and delegates presentation. Do not put business policy or transaction control in an Action.
- Authentication middleware and transport permission checks provide an early server-side gate, not the sole authority: package or justified Agent OS Application policy must enforce target, ownership and permission decisions against authoritative state on every entry path, including non-HTTP callers. Do not infer authorization from role names, UI reachability or a request attribute. Do not duplicate package-owned invariants in handlers or adapters.
- A Responder owns status, headers, response shape, and boundary mapping; it neither fetches missing business data nor decides policy. JSend is scoped to the documented API, not every HTTP path or other transport; an arbitrary lookup failure does not become a public 404 without explicit use-case context.
- Cross presentation boundaries with explicit safe Views, not raw entities. Keep PHP properties camelCase and map HTTP JSON and database fields to snake_case.
- Enforce authorization on the server. Sanitize public errors and keep secrets out of diagnostics.

## Frontend conventions

For frontend changes, follow the [client conventions](../../client/README.md#frontend-conventions): mirrored
`client/tests/`, application-root imports, semantic/import lint and explicit formatting, with default SCSS
separate in `client/styles/`. That document owns composition order, theme-extension limits, supported tooling
and read-only check versus explicit fix/install commands. Preserve runtime behavior and accessibility when
changing organization or tooling; verify the production shell and catalog through their actual builds/browsers.

## PHP and naming

Prefer `final` classes and readonly properties, with Doctrine entity exceptions; make value objects readonly. Use constructor injection, strict types, guard clauses, and Fight helpers where they improve clarity. Name commands, queries, and events with intent or fact (`RegisterUser`, `GetUserById`, `UserRegistered`) and name their handlers with `Handler`.

Use multiline docblocks with capitalized active-verb summaries and no terminal period. Document constructors as `Constructs ClassName`; inherited behavior uses multiline `@inheritDoc`.

## Tests

Write testable behavior, then tests at the narrowest boundary that proves it. Prefer Domain/Application unit tests, Adapter integration tests, and functional HTTP tests as their contracts require. Cover meaningful success, rejection and failure outcomes, state changes and external effects where applicable; document why a category does not apply. Test the actual owned policy or important package integration contract, not just a mock's call sequence. An explicit mapper contract can be proven directly; end-to-end claims require a real interaction that reaches it. Do not add a test-only production route, a gratuitous whole-app harness or non-API presentation just to exercise a scoped failure. Keep existing smoke behavior intact.

Product suites test owned application behavior and important integration contracts. They do not test architecture or dependency rules, container/configuration wiring, migrations, generated files, repository wrappers, build/planning/static-analysis mechanisms, test infrastructure, or upstream package behavior. Validate those concerns directly with their owning tools. Never add deliberately invalid fixtures merely to prove that a test or quality tool detects them. Do not test the tests.

Aim for 100% unit-test coverage of meaningful owned unit-testable behavior where practical. Report unit coverage
separately from integration/functional coverage, with denominator, driver availability and justified gaps; a combined
suite percentage is not a unit-coverage result. This is a quality goal, not an already enforced numeric gate.
Coverage remains a completeness aid, including Adapter behavior where practical. Report actual configured coverage and omissions without claiming that a percentage establishes correctness or protocol conformance. A covered line does not justify a test by itself: tests must assert an observable contract, outcome, or side effect. Exclude non-behavioral composition and generated code rather than manufacturing tests for percentages.

For a bug, reproduce the behavior and make one regression test fail before repair when technically possible. Record why when it is not possible. Focused checks shorten the loop; only the repository canonical gate establishes complete local verification.
