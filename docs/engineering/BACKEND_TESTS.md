# Backend behavior and coverage

Use `./bin/backend-tests` for the complete backend phase. Run `./bin/up` after changing the CLI image to
install Xdebug **3.5.0**. Locked Composer dependencies and a current private public-form catalog must already
be present (`./bin/validations check`; use `./bin/validations export` to prepare it). The phase does not
install packages or contact providers. Xdebug is off for ordinary application execution.

The phase requires the running Compose `web` service and explicit test mode. It rejects unsafe PostgreSQL
targets before connection, verifies the connected identity, resets only the guarded test schema, and runs its
migrations. It runs each of the disjoint suites in `phpunit.xml` once, with coverage enabled only for Unit:

- **Unit:** isolated application and adapter behavior.
- **Integration:** runtime filesystem/HTTP adapter contracts, excluding PostgreSQL.
- **Functional:** real HTTP interactions.
- **Postgres:** real persistence, rollback, constraints, locking and competing writes.

A shared local phase lock rejects overlapping runs. Do not run focused Postgres tests or reset the same test
database concurrently with this phase. Completion or failure after a successful reset performs a fresh
identity-guarded cleanup. Reconcile processes and database state after interruption or engine failure before
retrying. Development data is not reset. Reinitialize with `./bin/database test-reset` before subsequent
focused PostgreSQL tests. PostgreSQL races use separate connections, transaction-held locks and bounded
`lock_timeout` failures; they do not depend on sleeps.

## Deterministic test time

For scenarios involving time, construct an explicit fixed UTC `DateTimeImmutable` and inject the existing
`Tests\Support\AccessClock` before resolving time-dependent services. Derive fixture creation, token claims,
session/grant deadlines and relative dates from that clock; do not use wall time, sleeps or retries to keep
requests inside a window. Advance the fake explicitly to test expiry or rollover, including denial just before
and admission at the exact documented boundary.

Keep every participating clock coherent: delivery's `Clock` binding does not replace access-token verification
or authoritative session/principal clocks. Use their existing constructor seams with the same fake and the real
JWT verifier/repositories; never bypass API authentication or retain stale credentials to reach the target check.
When advancing beyond authentication expiry, establish a fresh valid fixture session/token at the advanced time.
Pass the fixed instant to race workers before they construct their services. See
`tests/Integration/Postgres/InvitationRecoveryApiTest.php` and its support worker for the complete pattern.
If a required consumer has no injectable seam, stop and report that boundary rather than mixing fixed and real
time or changing production behavior solely to make a test pass. This convention does not authorize a broad
conversion of unrelated fixtures.

Package-generated IDs/nonces remain opaque values held stable within each scenario; ordering expectations
come from the specified order, not generation timing. These are not byte-identical fixture snapshots.

## Reports and policy

The backend phase requires 100% Unit coverage of owned unit-testable executable PHP lines under `src/`.
The checker compiles every owned PHP file with CLI OPcache (optimization off) and uses the same Xdebug and
PHPUnit line analysis for all of them. This keeps the denominator independent of which files the tests load.
PHPUnit's parser-only fallback for unloaded files can count different lines than its runtime analysis. The
compiler does not run the files, and only hits from the Unit invocation count. Inline coverage-ignore
annotations are disabled. The checker rejects missing or unusable data, an incomplete source inventory, and
Unit line data outside that inventory. It compares exact integers, not rounded percentages. Other suites
retain their existing failure criteria with no coverage quotas. There is no combined result or second test run.

The ignored `.runs/build/reports/backend/` directory holds one JUnit log per suite and `unit-coverage.json`,
which records counts, gaps by file and line, and exact excluded lines with reasons. `unit-coverage.php` is
trusted local output from the Unit invocation; never load an externally supplied PHP artifact. The phase
removes its current outputs and the retired `coverage.php`, `clover.xml`, and `junit.xml` under the phase lock
before running. A failed phase is not a success receipt
regardless of which reports exist. Line coverage does not establish branch completeness or assertion quality.

The existing coverage exclusions identify:

- `Adapter/Persistence/Guard/DatabaseTargetGuard.php`: destructive test-tool safety, probed directly.
- `Adapter/Validation/PublicForms.php`: explicit publication composition registry.
- The named bus registration methods listed in `scripts/backend_coverage.php`: static message-class metadata,
  not use-case behavior.
- The named empty private constructors listed there: prevent instantiating static persistence helpers without
  excluding any of their behavior.
- Exact native filesystem statements in `PrivateOpenApi::readArtifact`, `Catalog::readArtifact`, and
  `JsonManagedPolicy::readArtifact`: file path, accessibility, size and read operations. The checker rejects
  drift at the listed lines; new statements are not automatically exempt. `SwaggerApiTest`,
  `ValidationCatalogTest`, and the Integration authorization catalog tests exercise real files. Unit tests
  still cover decoding, validation, and what happens when either installation catalog cannot be read.
- The missing Argon2id branch and the two native error handlers in `Argon2idPasswords`: depend on missing
  platform support or native hashing failure with fixed options. `PasswordSettingTest` exercises the real
  supported runtime; it does not simulate an unavailable or failing native implementation. The remaining
  lines, including the probe result check and ordinary hash operation, still count.

The checker resolves exclusions against the current files and verifies that each exact line exclusion still
matches the expected source statement. No entire persistence, HTTP, delivery, hydration or validation adapter
is exempted for runtime admission. Vendor code, tests, bootstrap/configuration, migrations, public entrypoint
wiring, scripts/wrappers, generated assets and caches are outside `src/`. Validate those with their owning tools.

## Focused iteration and build integration

```sh
./bin/phpunit --testsuite Unit --filter GetCsrfProofHandlerTest
./bin/phpunit --testsuite Integration,Functional
./bin/database test                  # Existing migrated guarded test database
./bin/backend-tests                  # Guarded database and all suites; Unit coverage only
./bin/build                          # Complete repository gate
```

Focused commands are not additional canonical phases. The [complete gate](BUILD.md) calls
`scripts/backend_tests.php` once with explicit test and coverage environment. Hosted CI uses the same gate.
