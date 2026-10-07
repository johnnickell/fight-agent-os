# Canonical application gate

`./bin/build` is the sole complete local acceptance command. Focused wrappers shorten iteration; they do not
replace it. A pass is not independent technical review, behavioral QA, merge, release or deployment approval.

## Explicit setup

Docker Compose, locked Composer dependencies, the pinned frontend installation and private metadata exports must
already exist. Setup may contact registries; acceptance never installs, updates, audits, exports or formats:

```sh
docker compose build web
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/client setup
./bin/up
./bin/openapi export
./bin/validations export
./bin/build
```

`./bin/up` prepares ignored cache mounts and builds/starts services explicitly. After changing only the CLI image
or web service configuration, `docker compose build web` followed by `docker compose up -d --no-deps web` refreshes
just that service, preserving the other services and development database. Never silently refresh a service inside
acceptance. Missing/stale Compose configuration or tooling-image identity fails with an explicit setup instruction.
Export OpenAPI/validation metadata again after their source contracts change; checks only compare freshness.

The CLI image includes Node 24.21.0/npm 11.19.0 from the same digest as `bin/client`, Chromium 149.0.7827.0 from
its pinned Playwright 1.61.0 image, Debian browser libraries/fonts, PHP and Xdebug 3.5.0. Browser binaries are copied
at image setup, not downloaded at runtime. The PHP image is larger; the focused frontend wrapper retains its
separate Node/browser environments and requires `./bin/client storybook-setup` before focused browser checks.
No Docker CLI or daemon socket is added to PHP. Composer maintenance explicitly mounts source writable; ordinary
web execution mounts source, locks and dependencies read-only. Host-side edits remain visible during development.

## One PHP-owned phase graph

The host wrapper checks the running `web` service and its Compose configuration identity, then delegates with
`docker compose exec -T web php scripts/build.php`. PHP runs these named phases, in order:

1. Composer version and strict manifest/lock validation, with network access disabled for Composer.
2. Authoritative `./bin/planning-check`, without `--write`.
3. PHP syntax/namespace checks, locked Fight Common PHPCS, PHPStan level 7, Deptrac and Rector dry-run.
4. OpenAPI and public-validation artifact freshness through their owning PHP scripts.
5. The [backend coverage phase](BACKEND_TESTS.md): guarded PostgreSQL identity/version check, test schema reset,
   migrations, **one unfiltered coverage-enabled PHPUnit execution**, coverage policy, guarded test-schema cleanup.
6. Installed frontend lock/runtime validation and versions, and the Pi presentation tests.
7. Both strict TypeScript projects, zero-warning lint, non-mutating formatting, one V8-instrumented Vitest execution.
8. Scratch production build/artifact inspection, static Storybook build/artifact inspection, Chromium story/a11y
   checks, and static catalog captures/resource checks.
9. Final index/status/content/executable-mode comparison against the starting tracked and unignored files.

Each mandatory command executes once; the orchestrator does not call focused wrappers that duplicate suites.
The complete frontend policy/exclusions remain in [client guidance](../../client/README.md#focused-frontend-quality-gate).
No percentage-only frontend threshold is invented. Backend Domain/Application line coverage requires 100%; Adapter
coverage remains a practical target with counted gaps, not a hidden exclusion baseline or a claimed 100% result.

The gate fails fast and propagates the failing exit status. It checks source stability on failure as well as success;
missing snapshots/evidence or a source/index/status change fails without restoring or discarding anyone's work.
Source hashes detect changes that status alone would miss in an already-dirty checkout. Do not edit or stage files
while the gate is running. One local gate lock prevents overlapping canonical runs; backend coverage shares its own
lock with its focused command. Do not run independent test resets or PostgreSQL tests concurrently against that
same test database, and do not run focused frontend commands concurrently against these report directories.

## Runtime boundaries and outputs

The web service has a read-only root, dropped capabilities, no-new-privileges, 2 CPU/2 GiB/256 PID ceilings,
256 MiB temporary storage and 256 MiB shared memory. Only ignored `.runs/`, `var/` and two frontend dependency-cache
mounts are writable. The dedicated guarded PostgreSQL test service is disposable; development data is not migrated
or reset. Normal backend completion/failure after a successful reset cleans the guarded test schema again. Focused
PostgreSQL iteration therefore needs `./bin/database test-reset` after coverage cleanup.

Frontend/Pi processes receive a minimal environment without application/database/provider secrets, an isolated HOME,
offline npm and telemetry disabled. The service retains its ordinary Compose network for PostgreSQL and local browser
servers: it is **not a hostile-code sandbox or an OS-enforced network-none frontend container**. Checks use installed
local resources, not registry/provider calls; browser captures reject observed external requests. The focused
frontend wrapper still supplies network-none containers. Managed execution isolation remains TASK-00138's separate
boundary. Do not treat this trusted development gate as qualification of arbitrary candidate code.

Each run retains `.runs/build/<UTC-start>-<pid>/receipt.json`, numbered combined stdout/stderr phase logs and
`before.json`/`after.json`. The receipt records PHP/Xdebug/package versions, commands, exit codes, durations,
source stability and hashes of final owning-tool summaries. A failed or interrupted run is not a receipt of success;
retain failed evidence rather than accepting a previous report. Abrupt termination/engine loss cannot guarantee
cleanup: reconcile remaining processes before retry, then the guarded phase resets its own test state.

The build report parent and each new run directory use `0755`; only their selected top-level JSON snapshots,
receipt and phase logs use `0644`, independent of the writer's umask. This lets a non-root Linux host collect
success/failure evidence from the root-running web service. Tool `home/` stays private (`0700`); no recursive
permission change or upload includes it or unrelated scratch. Existing historical run permissions are unchanged.
These diagnostics must remain free of secrets; host readability is not permission to publish arbitrary logs.

Coverage/count/exclusion details remain in `.runs/backend-coverage/` and `.runs/client/coverage/`. Production check
assets remain in `.runs/client/production/`, never replacing live `public/build/`; catalog output and captures stay
under `.runs/client/`. Ignored reports/caches are intentionally retained for review, not automatically archived.
Do not delete another TASK's evidence. No gate checks test the gate, tools, configuration or seeded violations.

Warnings are preserved, not suppressed. PHP/tool/test warning failures remain mandatory; existing disclosed
Sass/Bootstrap deprecations and browser-only Storybook warnings are not silently reclassified as errors or hidden.
Report them with counts, actual coverage gaps and unavailable verification. Hosted CI uses explicit setup followed
by this same `./bin/build`; hosted evidence is optional unless an accepted requirement makes it mandatory.
