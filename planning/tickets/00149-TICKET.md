---
id: TICKET-00149
epic: EPIC-00028
title: Select, reuse, and require the four verification passes
status: ready-for-agent
---

# Select, reuse, and require the four verification passes

## Problem statement

The current application gate ties unrelated checks together. Skills and GitHub need a common way to select affected checks and distinguish a verified reuse from a check that did not run.

## Solution and boundaries

Use one change-selection policy for local skills and four parallel GitHub workflows with stable check names. Print the comparison base and reasons for selection. Shared changes can select several passes; unknown scope must run conservatively. Preserve an explicit full run. Assign other live repository checks to their owning pass so none disappear in the cutover.

Give each pass its own input fingerprint and verifiable successful-run receipt. Local stages may reuse valid evidence for unchanged relevant source, tests, configuration, dependencies, tool identity, and required artifacts. Report ran, reused, and not affected separately; support a forced fresh run. GitHub may reuse dependency downloads, not prior check results for merge decisions. Preserve independent Review and QA.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Verify a local change across Fight stages | Local affected or full check entry point | Candidate diff, input fingerprints, and receipts | N/A: verification | Run selected checks or report verified reuse; retain evidence |
| Validate a GitHub candidate | GitHub workflows on the relevant candidate | Candidate scope and four stable check results | Existing GitHub check results | Each workflow reports success, failure, or explicit not affected |

## Validation and permissions

Failed, interrupted, missing, or unverifiable evidence is not a local cache hit. Missing history or unclassified shared inputs cannot produce a successful skip. Only an authorized repository operator can change merge requirements. Planning approval does not change settings or grant publication rights.

## Acceptance and evidence

Pilot PHP-only, frontend-only, Pi-only, planning-only, and shared-input changes, including smoke selected from either side. Show cache hits for unaffected inputs, invalidation for affected inputs, and behavior with unusable receipts and unknown scope. Compare cold and representative-change turnaround locally and on GitHub. Account for every old required check before cutover. After successful qualification, verify the actual settings on `develop` and `main`: all four checks are required, explicit not affected can satisfy a check, and failed or missing checks cannot.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00618](../tasks/00618-TASK.md) | Report which verification passes a change affects | done |
| [TASK-00619](../tasks/00619-TASK.md) | Run and reuse selected verification passes locally | ready-for-agent |
| [TASK-00620](../tasks/00620-TASK.md) | Pilot four parallel GitHub verification workflows | ready-for-agent |
| [TASK-00621](../tasks/00621-TASK.md) | Require the four checks and retire the old hosted gate | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Design and pilot may overlap the pass work. Cutover consumes verified outcomes from [Planning](00145-TICKET.md), [PHP](00146-TICKET.md), [Frontend](00147-TICKET.md), and [Pi Harness](00148-TICKET.md). Do not leave the old and new layouts as permanent gates. No rollout to other repositories in this TICKET.
