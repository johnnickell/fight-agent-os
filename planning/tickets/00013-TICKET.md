---
id: TICKET-00013
epic: EPIC-00003
title: Complete the owned-code quality gate
status: ready-for-agent
---

# Complete the owned-code quality gate

## Problem statement

The inherited build proves a narrow package-support baseline but does not yet enforce the code style, static correctness, dependency direction, owned behavior coverage, frontend verification, production dependency safety, and warning disclosure required for Agent OS application work.

## Solution and boundaries

Evolve `./bin/build` into the complete read-only application gate while preserving it as the sole canonical acceptance command. Add PHPCS, PHPStan, dependency-boundary enforcement, backend coverage policy, deterministic frontend checks, planning validation, security audit, and clean production-install proof. Keep focused commands available for iteration but never present them as substitutes for the full gate.

Require 100% line coverage for meaningful owned Domain/Application behavior, including rejection/failure paths. Select an evidence-based Adapter threshold after representative persistence/HTTP/composition code exists; measure adapters separately and exclude only generated or explicitly justified composition code. Coverage supports, but does not replace, behavior assertions and review.

Out of scope: tests of Markdown/wrapper/configuration wording, repeated upstream package suites, lowest-version/candidate lanes, network-dependent checks, flaky timing/shared-state suites, and percentage-only quality claims.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Run focused development checks | N/A — lint/static/test commands are repository tooling | Inspect changed backend/frontend/planning scope and tool output | N/A — no application domain event is produced | Developers receive fast deterministic feedback without changing authoritative files unexpectedly |
| Run the canonical gate | N/A — `./bin/build` orchestrates read-only verification | Inspect planning freshness, source, tests, coverage, dependencies, and production install | N/A — no application domain event is produced | One command fails on any required quality or contract violation and returns clear evidence |
| Enforce architecture and coverage | N/A — analysis/instrumentation operations are tooling | Inspect dependency graph, types, style, executable lines, exclusions, and uncovered behavior | N/A — no application domain event is produced | Invalid dependencies/style/types and insufficient owned coverage fail deterministically |
| Prove production dependency safety | N/A — audit and clean install are build operations | Inspect lock, advisories, platform requirements, production packages, and autoload | N/A — no application domain event is produced | Vulnerable or invalid production graphs fail without mutating the working tree |

## Validation and permissions

Every gate step must be deterministic, non-interactive, containerized through repository `./bin/*` wrappers where applicable, and read-only with respect to tracked planning/source. Network access may be used only where the dependency audit/install contract explicitly requires it and must not make product tests flaky. Generated caches and reports belong under ignored `var/` or `.runs/` paths.

No runtime permission model applies. Tool configuration changes require approved repository scope. The gate may fail work but cannot approve merge, alter requirements, suppress warnings silently, or treat inherited receipts as fresh application evidence.

## Acceptance and evidence

- PHPCS enforces accepted Fight/PHP conventions for owned code.
- PHPStan runs at a documented accepted baseline without hiding owned-code failures in a broad ignore list.
- Deptrac or equivalent enforces Domain/Application/Adapter and package ownership decisions with justified exceptions only.
- Meaningful owned Domain/Application behavior meets 100% line coverage with rejection/failure paths tested; Adapter coverage is separately measured against an accepted evidence-based threshold.
- Frontend type/build, Vitest/component checks, and any required Storybook build are deterministic gate inputs.
- Planning read-only validation, backend tests, PostgreSQL integration checks where required, dependency audit, and clean production install are included.
- `./bin/build` does not rewrite tracked files and fails clearly for representative seeded violations.
- Fresh focused and canonical results record versions, counts, coverage, production-install outcome, duration where useful, warnings, and justified exclusions.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00030](../tasks/00030-TASK.md) | Complete PHP style static-analysis and dependency enforcement | ready-for-agent |
| [TASK-00031](../tasks/00031-TASK.md) | Enforce backend behavior coverage and PostgreSQL verification | ready-for-agent |
| [TASK-00032](../tasks/00032-TASK.md) | Enforce deterministic frontend quality checks | ready-for-agent |
| [TASK-00033](../tasks/00033-TASK.md) | Make bin build the complete read-only application gate | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the quality direction approved by [WF-003](../wayfinder/tickets/WF-003-qualify-dependency-baseline.md), [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md), and [WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md). Tool adoption may begin after [TICKET-00007](00007-TICKET.md), but final thresholds and acceptance follow representative backend, persistence, HTTP, and frontend code from TICKET-00008 through TICKET-00012.
