---
id: TICKET-00001
epic: EPIC-00001
title: Seed the planning skill foundation
status: done
---

# Seed the planning skill foundation

## Problem statement

Fight Agent OS needs repository-specific planning skills before larger application, terminal, observatory, and database work is decomposed. The skills must use the repository's EPIC → TICKET → TASK model instead of inherited Factory terminology.

## Solution and boundaries

Add the first skill documents for grill, Wayfinder, research, prototype, to-tickets, and to-tasks. Capture attribution for external inspiration and remove inherited dependency-support artifacts that are not part of the desired foundation gate.

Out of scope: implementing a Pi terminal extension, database-backed planning records, multi-agent worktree orchestration, browser planning screens, or full workflow automation.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Discover an EPIC through grill | N/A until workflow automation exists | Planning artifact reads | N/A | One EPIC record after confirmation |
| Decompose an EPIC | N/A until workflow automation exists | Existing EPIC/TICKET reads | N/A | Requirement TICKET records |
| Decompose a TICKET | N/A until workflow automation exists | Existing TICKET/TASK reads | N/A | Executable TASK records with blockers |
| Research or prototype a decision | N/A until workflow automation exists | Source and planning reads | N/A | Linked evidence under planning or `.runs/` |

## Validation and permissions

Planning changes must pass `./bin/planning-check --write` followed by `./bin/planning-check`. The skills must preserve human approval points for product decisions and must not start implementation without explicit authorization.

## Acceptance and evidence

- Project-local skill documents exist with Fight Agent OS terminology.
- Third-party inspiration and MIT notice are recorded.
- Inherited support evidence and lowest-lock artifacts are removed from the canonical gate.
- Planning views are refreshed and validation passes.
- The canonical `./bin/build` gate passes with the known Composer warning for the inherited Fight Common commit reference.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00001](../tasks/00001-TASK.md) | Add the initial project-local planning skills | done |
<!-- /planning:children -->

## Decisions and progress

Initial source review covered the previous private Factory SSSF skill and Matt Pocock's public planning-oriented skills. Fight Agent OS adopts the planning patterns but keeps local Markdown planning as the authority. TASK-00001 completed the first skill suite and removed the inherited support artifacts from the application gate.
