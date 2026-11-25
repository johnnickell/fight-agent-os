---
id: TICKET-00150
epic: EPIC-00028
title: Remove the remaining repository-owned Python tooling
status: ready-for-agent
---

# Remove the remaining repository-owned Python tooling

## Problem statement

Runtime, QA, and Harness paths still depend on repository-owned Python tooling. Moving maintained behavior to PHP removes a prerequisite and fits the repository's tooling model.

## Solution and boundaries

Trace current callers. Port the live tools not already covered by a pass, remove unused tooling, and retire replaced implementations and prerequisites after verification. Keep the supported `bin/*` entry points thin and host-facing, generally invoking PHP under `scripts/*` inside Docker. Use Composer autoloading, namespaces, and locked third-party libraries when they simplify maintenance. Keep the Python Diagrams authoring and rendering tools, maintained diagram sources, and Graphviz dependency under [EPIC-00022](../epics/00022-EPIC.md). A PHP replacement is not part of this TICKET; the four verification passes and routine Harness operation must not depend on the diagram tooling.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Operate local tooling | Existing runtime, QA, and Harness commands | Existing resource identities, fixtures, and maintained sources | Existing tool outcomes; no new product events | Preserve supported setup, checks, and cleanup |

## Validation and permissions

Preserve resource ownership, isolation, rejection paths, failure handling, and cleanup order. Do not broaden permissions, install tools silently during checks, or apply changes to unrelated checkouts or projects. Keep the client and Pi engine in TypeScript.

## Acceptance and evidence

Identify surviving callers and verify each supported replacement through its owning command, including failure paths for lifecycle-sensitive tools. Verify that supported workflows outside the diagram-authoring exception do not require repository-owned Python tooling or a Python prerequisite. Update active runbooks while preserving historical acceptance and evidence. Once Fight Access Control has runnable PHP entry points, compare actual behavior and note what is reusable; its unfinished refactor does not block this work or authorize a cross-project rollout.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00622](../tasks/00622-TASK.md) | Move local runtime and resource lifecycle to PHP | done |
| [TASK-00623](../tasks/00623-TASK.md) | Move local domains DNS ingress and HTTPS to PHP | ready-for-agent |
| [TASK-00624](../tasks/00624-TASK.md) | Move human QA setup and cleanup to PHP | ready-for-agent |
| [TASK-00625](../tasks/00625-TASK.md) | Move Land target binding to PHP | ready-for-agent |
| [TASK-00626](../tasks/00626-TASK.md) | Move Planning archive maintenance to PHP | ready-for-agent |
| [TASK-00627](../tasks/00627-TASK.md) | Remove Python from the remaining Harness custody helper | ready-for-agent |
| [TASK-00628](../tasks/00628-TASK.md) | Retire the remaining Python prerequisites outside diagram authoring | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Can proceed in parallel with the pass split. Planning, browser-smoke, and Harness check prerequisites stay with their pass TICKETs; reconcile shared callers before retiring them. Removal outside the diagram-authoring exception is an EPIC completion outcome, not a prerequisite for qualifying the four-pass split.
