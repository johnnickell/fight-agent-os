---
id: TICKET-00148
epic: EPIC-00028
title: Verify the Pi Harness independently
status: ready-for-agent
---

# Verify the Pi Harness independently

## Problem statement

The Harness and vendored engine have separate checks today, outside the canonical hosted application workflow. Their verification should be a visible pass that does not provision the application stack.

## Solution and boundaries

Run owning Harness and vendored engine checks, including applicable native behavior and packaging checks. Keep existing Node and TypeScript checks, and use TypeScript for checks and probes where it fits. PHP is not required for the checks themselves. Port the live Python runtime helpers needed by the pass to PHP and remove their superseded implementations. Keep a host-facing Docker-backed path and direct owning commands on GitHub without containers. Do not rewrite the TypeScript engine, pin Pi, or alter role authority.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Verify a Harness or vendored engine change | Harness, engine, and applicable native or packaging checks | Locked dependencies and supported Pi capabilities | N/A: verification | Bounded test and packaging evidence; no installation rollout |

## Validation and permissions

Preserve existing native test boundaries, session and checkout authority, and isolation of runtime evidence. Qualify supported Pi behavior without a Pi version pin. A mocked or scripted check does not stand in for a genuine assigned stage where that evidence is required.

## Acceptance and evidence

Exercise the local pass and native GitHub execution without the application stack. Account for existing Harness and vendored engine checks; show how changes to each select its checks and any affected integration checks. Verify replacement helpers and preserve package contents and attribution.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00615](../tasks/00615-TASK.md) | Run Pi Harness verification as a standalone pass | ready-for-agent |
| [TASK-00616](../tasks/00616-TASK.md) | Port the Harness session-file birth helper to PHP | ready-for-agent |
| [TASK-00617](../tasks/00617-TASK.md) | Port the Build stage launcher and monitor to PHP | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Can proceed independently. Shared selection and cutover belong to [TICKET-00149](00149-TICKET.md); Python helpers used outside the pass are coordinated with [TICKET-00150](00150-TICKET.md).
