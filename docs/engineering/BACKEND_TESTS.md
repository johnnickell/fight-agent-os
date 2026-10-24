# Backend behavior and coverage

Use `./bin/backend-tests` for the complete backend coverage phase. Run `./bin/up` after changing the CLI
image to install the pinned Xdebug **3.5.0** driver. Locked Composer dependencies must already be installed;
the phase does not install packages or contact providers. Xdebug is off for ordinary application execution.

The phase requires the running Compose `web` service and explicit test mode. It rejects unsafe PostgreSQL
targets before connection, verifies the connected identity, resets only the guarded test schema, runs its
migrations, then invokes PHPUnit **once**, without a suite filter. A shared local phase lock rejects overlapping
coverage runs. Normal completion or failure after a successful reset performs a fresh guarded test-schema cleanup;
interruption/engine failure requires process reconciliation before retry. Reinitialize with `./bin/database test-reset`
before subsequent focused PostgreSQL tests. `phpunit.xml` partitions all tests into:

- **Unit:** isolated application and adapter behavior.
- **Integration:** runtime filesystem/HTTP adapter contracts, excluding PostgreSQL.
- **Functional:** real HTTP interactions.
- **Postgres:** real persistence, rollback, constraints, locking and competing writes.

Do not run this destructive test phase concurrently against the same test database. Development data is not
reset. PostgreSQL races use separate connections, transaction-held locks and bounded `lock_timeout` failures;
they do not depend on sleep-based scheduling.

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

One execution writes ignored `.runs/build/reports/backend/coverage.php`, `clover.xml`, `junit.xml`, `summary.json`,
`domain-application.json`, and `adapter.json`.
Old reports are removed before execution. A failed phase is incomplete even if PHPUnit emitted report files;
only a successful complete command is a verification receipt. The PHP coverage artifact is trusted local
output; never replace it with an externally supplied PHP file. The coverage-policy subprocess receives an explicit
`256M` PHP memory limit to load and process this artifact. TASK-00154 reproduced the hosted `160M`
unserialize exhaustion directly on the 1,758-test artifact; the same policy/artifact passed at `256M`, peaking
at 161.5 MiB allocated (159.1 MiB used). The former `160M` allowance was based on a smaller 1,577-test
report's 129.6 MiB peak. This finite allowance applies only to policy processing, not PHPUnit or application
execution, and leaves the web container's `2 GiB` ceiling unchanged. Coverage denominators, required threshold,
report attribution and failure/cleanup behavior are unchanged; reassess measured growth rather than using
unlimited memory or dropping coverage.

The two layer reports and combined `summary.json` report **Domain/Application** and **Adapter**, with per-file executable-line counts,
covered lines, uncovered line numbers and exact excluded executable lines with reasons. Unit, Integration
(including Postgres), and Functional line attribution comes from test identities in that same execution.
Those lane counts overlap and must not be summed; combined coverage is not unit coverage. Clover retains the
raw source denominator, including the explicitly classified non-behavioral paths, for inspection.

`scripts/backend_coverage.php` enforces 100% meaningful Domain/Application **line** coverage. It rejects an empty
required denominator or an omitted PHP source file. PHPUnit includes unexecuted source and disables inline
coverage-ignore annotations. Adapter behavior targets 100% where practical: every remaining gap stays in the
Adapter denominator and is reported, not silently excluded or represented as 100%. The TASK records the current
capability measurements and limits for independent assessment. Branch/path coverage is not collected by this
phase; a line percentage does not prove branch completeness or the asserted outcome.

The only source exclusions are:

- `Adapter/Persistence/Guard/DatabaseTargetGuard.php`: destructive test-tool safety, probed directly rather than
  tested as product behavior.
- `Adapter/Validation/PublicForms.php`: the explicit publication registry (composition).
- `GetCsrfProofHandler::queryRegistration()`: static query-bus registration metadata.
- The eight explicitly named empty private constructors in the coverage policy: prevent instantiation of static
  persistence helpers; their actual adapter methods remain covered.

The report resolves exact executable line numbers against the current files. No entire persistence, HTTP,
delivery, hydration or validation capability is excluded. The source boundary is `src/`; vendor code, tests,
bootstrap/configuration, migrations, public entrypoint wiring, scripts/wrappers, generated assets and caches are
outside it. Validate those with their owning tools, not product-suite tests or seeded tool failures.

## Focused iteration and build integration

```sh
./bin/phpunit --testsuite Unit --filter GetCsrfProofHandlerTest
./bin/phpunit --testsuite Integration,Functional
./bin/database test                  # Existing migrated guarded test database
./bin/backend-tests                  # Reset + migrate + one all-suite coverage execution + policy
./bin/build                          # Still mandatory as the repository gate
```

Focused commands are not additional canonical backend phases. The [complete gate](BUILD.md) calls
`scripts/backend_tests.php` once with explicit test/coverage environment, replacing the old PHPUnit build step.
It does not prepend Unit/coverage passes or rerun any suite. Run `./bin/build` for final TASK verification;
`./bin/backend-tests` remains useful for backend-only iteration. Hosted CI uses the same complete gate.
