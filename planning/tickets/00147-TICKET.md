---
id: TICKET-00147
epic: EPIC-00028
title: Verify the client and cross-stack browser journeys
status: ready-for-agent
---

# Verify the client and cross-stack browser journeys

## Problem statement

Client checks wait behind backend verification today. The real HTTPS browser smoke tests need a backend environment, but do not need to wait for the backend test suite. Their current CI setup starts containers.

## Solution and boundaries

Keep client type, style, Vitest, production build, and Storybook checks in one independent pass. Own real HTTPS browser smoke here and run it once when a backend or frontend change can affect the journeys it covers. Port live smoke setup from Python to PHP. On GitHub use runner-native software and disposable fixtures for HTTPS, PHP serving, PostgreSQL, and mail capture. Do not run containers, duplicate the PHP suite, or depend on the PHP workflow finishing.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Verify a client change | Existing client checks through the frontend pass | Client source, locked tools, and generated assets | N/A: verification | Client and catalog reports and artifacts |
| Verify a cross-stack change | Existing HTTPS browser smoke through the frontend pass | Browser journey outcomes and test service identity | N/A: verification | Owned disposable fixtures, smoke report, and cleanup |

## Validation and permissions

Preserve real TLS, isolated browser trust and test data, guarded database and catcher cleanup, source stability, and credential-safe reports. Preserve local Docker boundaries and keep install/provisioning separate from checks.

## Acceptance and evidence

Account for existing client and browser checks. Demonstrate smoke selection from either side, with no duplicate run for a change spanning both. Verify existing browser scenarios and cleanup on a native GitHub runner without containers and exercise the local route. Remove replaced Python setup after verification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00613](../tasks/00613-TASK.md) | Run the existing client checks as a standalone pass | done |
| [TASK-00614](../tasks/00614-TASK.md) | Run real HTTPS browser smoke on native GitHub runners and retire its Python setup | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Can proceed independently of PHP pass execution. Selection and merge requirements are coordinated by [TICKET-00149](00149-TICKET.md).
