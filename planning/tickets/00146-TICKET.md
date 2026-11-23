---
id: TICKET-00146
epic: EPIC-00028
title: Verify PHP independently and enforce Unit-only coverage
status: ready-for-agent
---

# Verify PHP independently and enforce Unit-only coverage

## Problem statement

PHP checks currently share the application gate with frontend checks. The current coverage gate counts combined Domain/Application coverage and reports Unit attribution, but does not enforce the required Unit-only result.

## Solution and boundaries

Run PHP syntax, style, static analysis, dependency rules, generated contract freshness, and PHPUnit as an independent pass. Keep integration, functional, and PostgreSQL-backed tests. Enforce 100% Unit-only line coverage of owned unit-testable code, with an explicit denominator and justified exclusions. Do not count coverage from other suites toward it or weaken existing coverage checks to reach it.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Verify a PHP change | Local Docker-backed pass and direct PHP checks on GitHub | PHP sources, test suites, contract artifacts, and coverage results | N/A: verification | Reports and guarded test database setup and cleanup |

## Validation and permissions

Preserve source stability, offline checks after setup, and test database identity safeguards. Preserve the [accepted execution model](../adr/0001-application-ownership-and-orchestration.md): do not rerun suites merely to obtain a second report. Qualify the existing coverage attribution before choosing enforcement; an incompatible execution change needs an explicit ADR amendment.

## Acceptance and evidence

Demonstrate the pass locally and on a container-free runner without frontend setup. Show that unexecuted owned source remains in scope, Unit-only results are independently attributable, and a combined pass cannot conceal a Unit-only failure. Account for every existing PHP check and the retained database-backed tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00611](../tasks/00611-TASK.md) | Run the existing PHP checks as a standalone pass | done |
| [TASK-00612](../tasks/00612-TASK.md) | Enforce 100% Unit-only line coverage of owned unit-testable PHP behavior | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Pass separation can proceed while coverage gaps are closed; it is not Unit-only compliance. Browser smoke belongs to [TICKET-00147](00147-TICKET.md); shared cutover belongs to [TICKET-00149](00149-TICKET.md).
