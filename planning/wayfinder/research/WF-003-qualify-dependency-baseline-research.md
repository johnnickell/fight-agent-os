# Qualify the Fight dependency baseline

## Question

What Fight Common and Fight Access Control dependency baseline should Fight Agent OS use before authentication and UI feature planning, which inherited tests belong only to the former framework-support campaign, and what should the application build gate prove afterward?

This matters because the current manifest follows two moving development branches, the lock predates both available stable releases, and most tests prove the inherited Slim starter's Fight Common support receipt rather than Agent OS behavior.

## Short answer

Use stable minor-line constraints:

```json
"johnnickell/fight-common": "~1.2.0",
"johnnickell/fight-access-control": "^0.2.0"
```

Set Composer's minimum stability to `stable` (or remove the key, whose default is stable), update the lock to Fight Common `v1.2.0` at `a2cd615d9b5064c9c30e994655536176249cd73b` and Fight Access Control `v0.2.0` at `c986b488ef8490c16c7a69e4675b5348f8d6dbf5`, and stop using branch aliases or commit references as the normal application baseline. The lock gives exact reproducibility; the constraints admit compatible patch releases while requiring a deliberate decision for Fight Common `1.3` or Access Control `0.3`.

Retire the inherited support-campaign integration suite and its fixtures. Preserve only application-owned HTTP behavior by rewriting the useful root/404/error assertions as a minimal Agent OS smoke boundary until registration/login tests supersede them.

Keep `./bin/build` as the sole application gate, but make it prove the locked stable install, strict Composer validity, production install, application tests, planning consistency, and the actual project quality tools. Do not restore lowest-version lanes, support receipts, candidate identity checks, or tests of those artifacts.

## Findings

### 1. The current lock follows old development snapshots

The current manifest requires:

- Fight Common `dev-develop#fad24ae9fdcf4ac00fa55c59ef7d35f7c7531911 as 1.2.0-dev`.
- Fight Access Control `dev-develop`.
- `minimum-stability: dev` with `prefer-stable: true`.

The lock resolves Access Control to `4259ea0b77bc2e1d87e62ab70b64af63bae99477`, where its own requirement was still Fight Common `^1.1`. Both locked commits are ancestors of later releases, so the lock omits released changes rather than intentionally testing future work.

The commit-ref alias is also the source of Composer's known bad-practice warning. Composer can reproduce a lock containing a commit reference, but its own warning says this can cause unforeseen issues. A stable application baseline no longer needs that exception.

### 2. Stable releases now exist and are mutually compatible

Remote and package evidence shows:

| Package | Stable tag | Peeled commit | Package requirement |
|---|---|---|---|
| Fight Common | `v1.2.0` | `a2cd615d9b5064c9c30e994655536176249cd73b` | PHP `>=8.5` |
| Fight Access Control | `v0.2.0` | `c986b488ef8490c16c7a69e4675b5348f8d6dbf5` | Fight Common `^1.2`, PHP `>=8.5` |

Both tags are annotated and contain signatures from key `2BA572D6713AEFFF5C026F4C516849DF2A8C6248`. Local cryptographic verification could not complete because that public key is not installed; this research therefore confirms signed tag structure and exact object identity, not signature trust.

A disposable Composer resolution using `~1.2.0`, `^0.2.0`, and stable minimum stability completed without conflict. Composer selected exactly the two releases above and `composer validate --strict` passed without the commit-ref warning.

### 3. Narrow line constraints match the requested iteration model

Composer defines:

- `~1.2.0` as allowing patch updates from `1.2.0` while staying below `1.3.0`.
- `^0.2.0` as allowing patch updates from `0.2.0` while staying below `0.3.0` because the package is pre-`1.0`.

This is safer than `^1.2`, which would admit later Fight Common minors, and safer than `0.*`, which would admit every Access Control minor below `1.0` even though pre-`1.0` minors may refine public contracts. It also avoids an exact `1.2.0`/`0.2.0` pin that would prevent patch fixes. The committed lock still fixes the exact installed versions until an intentional Composer update changes it.

If Agent OS exposes a required upstream package change, the preferred loop is: implement and qualify it in the owning package, publish the next appropriate tag, then deliberately update Agent OS. An unreleased branch or commit may be used only as a temporary, recorded integration exception—not the default manifest policy.

### 4. Access Control remains an incubation dependency despite its tags

Access Control `v0.2.0` describes itself as intentionally pre-`1.0`; tagged versions precede full starter integration so consumers can test immutable versions and report findings. It provides Domain and Application behavior but intentionally no production Adapter layer. Agent OS still owns persistence, HTTP, cookies, signing-key configuration, CSRF/CORS, mail/queue delivery, and composition.

Its `SECURITY.md` is stale or stricter than its README and changelog: it says there is “no released or supported version,” despite public `v0.1.0` and `v0.2.0` tags. Therefore Agent OS should call `v0.2.0` a stable Composer baseline, not claim an upstream support guarantee. Consumer integration and security tests remain mandatory.

The current Agent OS source/tests contain no `Fight\AccessControl` use at all; `scripts/public-contract-check.php` verifies only that the package is installed. Passing the inherited suite does not qualify registration, login, session, role, or permission behavior.

### 5. The stable pair already boots the current scaffold

A disposable copy of the committed application was resolved and installed with the proposed stable constraints. Results:

- `composer validate --strict`: passed.
- Stable dependency resolution: passed.
- Locked development install: passed.
- Current PHPUnit suite: **26 tests, 152 assertions, passed**.
- Current `scripts/public-contract-check.php`: passed.
- Locked `--no-dev` production install: passed.
- `composer audit --locked --no-dev`: no known security advisories at the time of research.

This is compatibility evidence for the current inherited scaffold only. It is not fresh evidence for the real checkout, future application behavior, Access Control integration, release readiness, or production deployment. Scratch logs are retained under ignored `.runs/notes/WF-003-*` directories.

### 6. Nearly the entire current test suite was created for the support receipt

Project Slim history gives direct provenance:

- `tests/BootstrapTest.php` predates the support campaign and was introduced with the starter bootstrap.
- `tests/Functional/Http/HttpPipelineTest.php`, `tests/Integration/ContainerContractsTest.php`, messaging fixtures, and related provider tests were introduced by `435739b` (`fix: complete Slim support receipt evidence`).
- `tests/Functional/Http/ProductionRouteBoundaryTest.php`, the remaining Integration journey tests, and the HTTP/security/serialization fixtures were introduced or hardened by `a76202a` (`fix: harden Slim support receipt evidence`).
- The former receipt explicitly cites `tests/Integration` and `tests/Functional` as evidence for the “latest booted Slim capabilities” journey.

Recommended disposition:

| Current tests | Disposition |
|---|---|
| `tests/Integration/*.php` | Remove. They prove selected Fight Common container capabilities, fallbacks, and package helpers—not Agent OS use cases. |
| `tests/Fixture/Messaging/*`, `Serialization/*`, `Socket/*` | Remove with their support-only journeys. |
| `tests/Fixture/Security/Environment.php` | Remove with the inherited security-configuration tests; create feature-local environment helpers later only if actual auth composition needs them. |
| `tests/Fixture/Http/boot.php` | Remove. Its echo/fail routes are proof-only fixtures. |
| `tests/Functional/Http/HttpPipelineTest.php` | Replace. Drop proof-only echo/fail journeys; keep only equivalent behavior exercised through real application routes when those routes exist. |
| `tests/Functional/Http/ProductionRouteBoundaryTest.php` | Replace. Drop the historical assertion that receipt-only routes are absent; retain a concise application-owned unknown-route/safe-error assertion if that remains the HTTP contract. |
| `tests/BootstrapTest.php` | Keep temporarily or fold into the application HTTP smoke test. Replace the inherited greeting assertion when the dashboard shell owns `/`. |
| `tests/bootstrap.php` | Simplify to Composer autoload after support fixtures are removed; add only application-required test setup later. |

The cleanup should leave at least a minimal Agent OS boot/HTTP boundary test rather than a broad package conformance suite or an empty test gate. Every new feature then supplies Domain/Application unit tests and meaningful adapter/HTTP integration tests for its own behavior.

### 7. The production contract script will block the planned architecture

`scripts/public-contract-check.php` currently fails if either `src/Domain` or `src/Application` exists. That was a starter safeguard against copying Fight Common source, but those are now the accepted homes for Agent OS business logic and orchestration. Leaving this check unchanged would make the first DDD feature fail the production gate.

The baseline cleanup should replace that assertion with a boundary that permits application-owned `App\Domain` and `App\Application` code while still rejecting copied `Fight\Common` or `Fight\AccessControl` source/namespaces. It should continue verifying both Composer packages are present in the production install. Renaming the script to describe production dependency/boundary validation would avoid retaining obsolete “public contract” terminology.

### 8. The canonical application gate should be project-owned

The current `./bin/build` now performs Composer validation/install, planning validation, PHPUnit, a clean production install, and the public-contract script. It no longer runs the deleted lowest lane or support-receipt machinery. That is the correct direction, but it is not yet the desired Agent OS quality gate.

The dependency-baseline TASK should make the immediate gate require, in order:

1. `composer validate --strict` against the stable manifest and lock.
2. Locked dependency installation without an implicit update.
3. `composer audit --locked --no-dev`, with network/advisory availability reported honestly.
4. `./bin/planning-check` in read-only mode.
5. The application-owned PHPUnit suite.
6. A clean `composer install --no-dev` production copy.
7. Production platform/package checks and the corrected no-copied-library-source boundary.

Before registration/login implementation is treated as production-quality, a planned quality-foundation TASK should add and configure PHP_CodeSniffer, PHPStan, Deptrac, and owned-code coverage to that same `./bin/build`. Exact tool versions, rules, and the initial honest coverage baseline belong in implementation planning; the gate must not claim checks that are not configured. React lint, format, type, test, and production-build checks join `./bin/build` only after `client/` exists.

Explicit exclusions remain:

- No application-level lowest-dependency lock or lane.
- No Fight Common framework-support receipt generation or verification.
- No candidate commit-reference identity check.
- No product-suite tests for Markdown, wrapper text, Composer configuration text, or planning tooling.
- No automatic dependency update during the canonical build.

### 9. Direct optional dependencies should be pruned by use, not by the old receipt matrix

Many current direct requirements and `config/common/*.php` files were added to demonstrate the former Slim capability matrix. Some will likely be useful for authentication—Doctrine, JWT, validation, mail—and others may not be. This research does not recommend removing them blindly before WF-004/WF-005 settles actual adapters and journeys.

Each feature or foundation TASK should remove unused direct packages/configuration when ownership becomes clear. Application tests should cover selected adapters through real use cases rather than recreating Fight Common's full conformance matrix.

## Tradeoffs

- Stable tags remove moving-branch risk and Composer warnings but require deliberate upstream releases for integration changes.
- Narrow minor-line constraints reduce surprise; they also mean a new minor capability requires an explicit Agent OS dependency update.
- Removing support journeys sharply reduces test count, but retaining them would inflate confidence with package behavior the application does not use.
- A minimal product smoke suite is temporarily less broad than the inherited suite and more honest about implemented behavior.
- Composer advisory results are time-sensitive and depend on advisory availability; a clean audit is not a security review.
- Access Control's pre-`1.0` status makes application-owned security and integration evidence more important, not less.

## Sources

### Fight Agent OS

- [`composer.json`](../../../composer.json)
- [`composer.lock`](../../../composer.lock)
- [`bin/build`](../../../bin/build)
- [`scripts/validate-composer-candidate.sh`](../../../scripts/validate-composer-candidate.sh)
- [`scripts/public-contract-check.php`](../../../scripts/public-contract-check.php)
- [`tests/`](../../../tests/)
- [Scaffold origin](../../../docs/ORIGIN.md)

### Fight Common `v1.2.0`

- [Release tag](https://github.com/johnnickell/fight-common/releases/tag/v1.2.0)
- [`composer.json`](https://github.com/johnnickell/fight-common/blob/v1.2.0/composer.json)
- [Framework support contract](https://github.com/johnnickell/fight-common/blob/v1.2.0/docs/framework-support.md)
- [Supported-lines ADR](https://github.com/johnnickell/fight-common/blob/v1.2.0/planning/adr/0012-supported-lines-baselines-and-release-authorization.md)

### Fight Access Control `v0.2.0`

- [Release tag](https://github.com/johnnickell/fight-access-control/releases/tag/v0.2.0)
- [`composer.json`](https://github.com/johnnickell/fight-access-control/blob/v0.2.0/composer.json)
- [`README.md`](https://github.com/johnnickell/fight-access-control/blob/v0.2.0/README.md)
- [`CHANGELOG.md`](https://github.com/johnnickell/fight-access-control/blob/v0.2.0/CHANGELOG.md)
- [`SECURITY.md`](https://github.com/johnnickell/fight-access-control/blob/v0.2.0/SECURITY.md)
- [Package-boundary ADR](https://github.com/johnnickell/fight-access-control/blob/v0.2.0/planning/adr/0001-domain-application-package-boundary.md)
- [JWT authentication profile ADR](https://github.com/johnnickell/fight-access-control/blob/v0.2.0/planning/adr/0003-supported-jwt-authentication-profile.md)

### Inherited Project Slim provenance

- [Source commit `c44192f`](https://github.com/johnnickell/project-slim/tree/c44192f3ad3920b3b0383289f71815cb8b028093)
- [Support completion commit `435739b`](https://github.com/johnnickell/project-slim/commit/435739b)
- [Support hardening commit `a76202a`](https://github.com/johnnickell/project-slim/commit/a76202a)
- [Fight Common support PRD](https://github.com/johnnickell/project-slim/blob/c44192f3ad3920b3b0383289f71815cb8b028093/planning/specs/00002-PRD.md)
- [Historical support receipt](https://github.com/johnnickell/project-slim/blob/c44192f3ad3920b3b0383289f71815cb8b028093/evidence/framework-support/receipt-v1.json)

### Composer

- [Versions and constraints](https://getcomposer.org/doc/articles/versions.md)
- [Schema: minimum stability](https://getcomposer.org/doc/04-schema.md#minimum-stability)

## Open questions

- WF-004 must decide which current direct provider packages and composition files are truly needed by the application architecture.
- WF-005 must turn Access Control's framework-neutral lifecycle into concrete HTTP, cookie/session, CSRF, persistence, and key-management requirements.
- Implementation planning must set exact PHPCS, PHPStan, Deptrac, PHPUnit/coverage versions and an honest starting coverage policy.
- The Access Control repository should eventually reconcile its tagged-release documentation with the “no released or supported version” statement in `SECURITY.md`; this does not block using `v0.2.0` as an explicitly qualified incubation baseline.
- Import the signing public key only if cryptographic tag verification becomes a required application gate.
