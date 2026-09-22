---
id: TICKET-00007
epic: EPIC-00003
title: Stabilize application dependencies and smoke baseline
status: ready-for-agent
---

# Stabilize application dependencies and smoke baseline

## Problem statement

The scaffold still depends on development references and inherited support-campaign tests that prove upstream compatibility rather than owned Agent OS behavior. Production work needs a stable reproducible dependency graph and a small truthful application smoke boundary.

## Solution and boundaries

Adopt Fight Common `~1.2.0` and Fight Access Control `^0.2.0` with stable minimum stability and a committed reproducible lock. Prove clean development and production installs against that graph. Replace inherited support fixtures and journeys only as application-owned root, unknown-route, and sanitized-error smoke behavior covers the useful boundary, and correct the production dependency contract so legitimate `src/Domain` and `src/Application` code is allowed while copied package namespaces remain forbidden.

Retain `./bin/build` as the sole canonical gate. This requirement establishes the baseline but does not add the complete static-analysis, architecture, frontend, and coverage gate owned by TICKET-00013.

Out of scope: application features, authentication journeys, upstream package changes, commit-reference exceptions, lowest-version support lanes, and the complete quality-tool expansion.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Resolve the stable dependency graph | N/A — Composer operations are repository tooling, not application commands | Inspect package metadata, tagged constraints, lock resolution, and security advisories | N/A — no application domain event is produced | `composer.json` and lock resolve compatible stable Fight package lines |
| Prove install contracts | N/A — development/production installs are build operations | Inspect platform requirements, installed graph, autoload, audit, and production exclusions | N/A — no application domain event is produced | Clean development and `--no-dev` installs succeed reproducibly |
| Replace inherited support receipts | N/A — test/scaffold changes are repository operations | Read existing support tests and application HTTP behavior | N/A — no application domain event is produced | Agent OS owns minimal root, unknown-route, and safe-error smoke evidence; obsolete fixtures are removed |
| Enforce the production source contract | N/A — contract checking is a build operation | Inspect production autoload/source paths and namespaces | N/A — no application domain event is produced | Accepted Agent OS layers pass while copied library namespaces fail |

## Validation and permissions

Composer validation, lock/install verification, production audit, autoload generation, application smoke tests, the production source contract, and `./bin/build` must pass. Removal of inherited tests is allowed only after equivalent useful application boundaries have owned evidence. Warnings, advisories, and platform differences must be reported rather than suppressed.

No runtime permission model applies. Dependency and test changes require approved repository scope; this TICKET grants no authority to modify upstream packages, introduce unreleased refs, weaken audits, or implement product behavior.

## Acceptance and evidence

- `composer.json` uses Fight Common `~1.2.0`, Fight Access Control `^0.2.0`, and stable minimum stability with a committed compatible lock.
- Fresh development and production installs succeed, and production contains no development-only packages.
- Dependency audit and Composer validation results are recorded, including every warning.
- Root, unknown-route, and sanitized unknown-error behavior have Agent OS-owned smoke coverage before inherited support fixtures/tests are removed.
- The production contract permits accepted Agent OS Domain/Application paths and rejects copied Fight package namespaces.
- Focused dependency, smoke, and contract checks pass, followed by `./bin/build` with fresh counts and output summarized.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00007](../tasks/00007-TASK.md) | Adopt and prove the stable dependency graph | ready-for-agent |
| [TASK-00008](../tasks/00008-TASK.md) | Replace inherited receipts with the Agent OS smoke baseline | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the stable dependency and scaffold direction from [WF-003](../wayfinder/tickets/WF-003-qualify-dependency-baseline.md) and its [research](../wayfinder/research/WF-003-qualify-dependency-baseline-research.md). It is the first EPIC-00003 requirement after the EPIC-00002 execution path is usable.
