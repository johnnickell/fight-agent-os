---
id: TICKET-00013
epic: EPIC-00003
title: Complete the owned-code quality gate
status: ready-for-agent
---

# Complete the owned-code quality gate

## Problem statement

The current build provides a lean application baseline but does not yet enforce the complete code style, static correctness, dependency direction, owned behavior coverage, frontend verification, and warning disclosure required for Agent OS application work.

## Solution and boundaries

Evolve `./bin/build` into the complete application gate while preserving it as the sole canonical acceptance command. Add PHPCS, PHPStan, dependency-boundary enforcement, backend coverage policy, deterministic frontend checks, and planning validation. Keep focused commands available for iteration but never present them as substitutes for the full gate. Dependency installation, updates, and audits remain explicit maintenance operations rather than pre-submit tests.

Require 100% line coverage for meaningful owned Domain/Application behavior, including rejection/failure paths, and target 100% for meaningful Adapter behavior where practical. Exclude configuration wiring, migrations, generated code, wrappers, and tooling rather than manufacturing tests for percentages. Coverage supports, but does not replace, behavior assertions and review.

Out of scope: product tests of architecture, dependencies, Markdown, configuration/container wiring, migrations, generated files, wrappers, build/planning/static-analysis mechanisms, or test infrastructure; deliberately seeded tool failures; repeated upstream package suites; lowest-version/candidate lanes; network-dependent checks; flaky timing/shared-state suites; and percentage-only quality claims.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Run focused development checks | N/A — lint/static/test commands are repository tooling | Inspect changed backend/frontend/planning scope and tool output | N/A — no application domain event is produced | Developers receive fast deterministic feedback without changing authoritative files unexpectedly |
| Run the canonical gate | N/A — `./bin/build` orchestrates verification | Inspect planning freshness, source, tests, coverage, and dependency direction | N/A — no application domain event is produced | One command fails on any required quality or contract violation and returns clear evidence |
| Enforce architecture and coverage | N/A — analysis/instrumentation operations are tooling | Inspect dependency graph, types, style, executable lines, exclusions, and uncovered behavior | N/A — no application domain event is produced | Invalid dependencies/style/types and insufficient owned coverage fail deterministically |

## Validation and permissions

Every gate step must be deterministic, non-interactive, containerized through repository `./bin/*` wrappers where applicable, and non-mutating with respect to tracked planning/source. The gate is network-free and assumes explicit setup installed locked dependencies. Generated caches and reports belong under ignored `var/` or `.runs/` paths.

No runtime permission model applies. Tool configuration changes require approved repository scope. The gate may fail work but cannot approve merge, alter requirements, suppress warnings silently, or treat inherited receipts as fresh application evidence.

## Acceptance and evidence

- PHPCS enforces accepted Fight/PHP conventions for owned code.
- PHPStan runs at a documented accepted baseline without hiding owned-code failures in a broad ignore list.
- Deptrac or equivalent enforces Domain/Application/Adapter and package ownership decisions with justified exceptions only.
- Meaningful owned Domain/Application behavior meets 100% line coverage with rejection/failure paths tested; meaningful Adapter behavior targets 100% where practical with exact non-behavioral exclusions.
- Frontend type/build, Vitest/component checks, and any required Storybook build are deterministic gate inputs.
- Planning read-only validation, backend behavior tests, PostgreSQL integration checks where required, and frontend checks are included.
- `./bin/build` does not install, update, audit, or rewrite tracked files and does not test itself with seeded violations.
- Fresh focused and canonical results record versions, counts, coverage, duration where useful, warnings, and justified exclusions.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00030](../tasks/00030-TASK.md) | Complete PHP style and static analysis | done |
| [TASK-00031](../tasks/00031-TASK.md) | Enforce backend behavior coverage and PostgreSQL verification | ready-for-agent |
| [TASK-00032](../tasks/00032-TASK.md) | Enforce deterministic frontend quality checks | ready-for-agent |
| [TASK-00033](../tasks/00033-TASK.md) | Make bin build the complete read-only application gate | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the quality direction approved by [WF-003](../wayfinder/tickets/WF-003-qualify-dependency-baseline.md), [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md), and [WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md). Tool adoption may begin after [TICKET-00007](00007-TICKET.md), but final thresholds and acceptance follow representative backend, persistence, HTTP, and frontend code from TICKET-00008 through TICKET-00012.
