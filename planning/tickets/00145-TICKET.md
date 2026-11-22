---
id: TICKET-00145
epic: EPIC-00028
title: Validate Planning without the application stack
status: ready-for-agent
---

# Validate Planning without the application stack

## Problem statement

A planning-only edit should get fast feedback without application setup. The current `./bin/planning-check` depends on host Python.

## Solution and boundaries

Port the active planning validator and generated-view refresh to PHP. Keep `./bin/planning-check` as the host command, backed by a lightweight one-shot Docker container. No host PHP or Composer, web stack, database, or frontend setup is needed. Prepare the tooling image and any locked Composer dependencies explicitly; a validation run does not install them. Give GitHub a direct PHP entry point that does not start a container.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Check a planning-only edit | `./bin/planning-check` locally; owning PHP entry point on GitHub | Markdown records and generated views | N/A: local verification | Check results and diagnostics; no source writes |
| Refresh generated views | `./bin/planning-check --write` locally | Same records and view relationships | N/A: local maintenance | Rewrite only the existing generated sections and views |

## Validation and permissions

Retain read-only defaults, explicit write admission, record/reference validation, stale-view diagnostics, and exit outcomes. No new Planning permissions, database contracts, or publication effects.

## Acceptance and evidence

Show valid, invalid, and stale-view cases through the owning command. Show that refresh preserves authored content and that subsequent validation passes. Demonstrate a host without PHP or Composer and a GitHub runner invoking PHP directly. Remove the superseded Python validator once callers are migrated and behavior is verified.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00610](../tasks/00610-TASK.md) | Run Planning validation and refresh through PHP without the application stack | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as a parallel pass. Shared selection, receipts, and required-check cutover belong to [TICKET-00149](00149-TICKET.md).
