# Qualify dependency baseline

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** —

## Question

What dependency baseline should the application use before authentication and UI feature planning begins?

## Must decide

- How to pin or qualify Fight Common `1.2` for this repository.
- How to constrain Fight Access Control `0.*` while allowing expected iteration.
- Which inherited tests were only present to satisfy Fight Common support qualification and can be removed or replaced.
- What the canonical build gate should require after framework-support and lowest-lock artifacts were removed.

## Resolution boundary

This ticket may recommend composer constraints, gate changes, and test cleanup. It must not make dependency changes directly without a TASK handoff.

## Resolution

[Research](../research/WF-003-qualify-dependency-baseline-research.md) qualifies Fight Common `~1.2.0` and Fight Access Control `^0.2.0` with stable minimum stability. The current releases resolve together as Fight Common `v1.2.0` (`a2cd615d`) and Fight Access Control `v0.2.0` (`c986b488`), and the current scaffold passed 26 tests / 152 assertions plus development and production installs against that pair in disposable research copies.

A later implementation TASK must update `composer.json` and `composer.lock`; this decision makes no dependency change. Normal application development must use tagged patch-compatible lines rather than branch aliases or commit references. A required unreleased package change is a temporary, recorded integration exception followed by an owning-package release.

Remove the inherited support-campaign Integration/Functional journeys and fixtures, replacing only useful root/unknown-route/safe-error behavior with a minimal application-owned HTTP smoke boundary. Keep new tests focused on Agent OS Domain, Application, adapter, and HTTP behavior. Correct the production contract script so accepted `src/Domain` and `src/Application` paths are allowed while copied library namespaces remain forbidden.

`./bin/build` remains the sole application gate. It must validate the stable locked graph strictly, audit production dependencies, run planning and application tests, and prove a clean production install. Add PHPCS, PHPStan, Deptrac, and owned-code coverage through planned implementation before authentication is considered production-quality. Do not restore lowest-version lanes, framework-support receipts, candidate identity checks, or product tests of tooling text.
