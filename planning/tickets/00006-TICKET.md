---
id: TICKET-00006
epic: EPIC-00002
title: Establish independent design review
status: ready-for-agent
---

# Establish independent design review

## Problem statement

Disposable design exploration cannot validate its own conclusions. Fight Agent OS needs independent visual and interaction critique that checks the brief and rendered evidence, separates objective defects from subjective preference, and prevents unsupported prototypes from becoming production requirements.

## Solution and boundaries

Create a `design-review` skill and supporting review guidance that independently renders artifacts when possible; examines the brief, alternatives, provenance, responsive behavior, interaction states, keyboard/focus behavior, semantics, contrast, copy, errors, recovery paths, and implementation handoff; and returns `accept`, `revise`, or `reject`.

The reviewer must distinguish blocking findings from non-blocking subjective critique and identify the limits of automated, manual, and screenshot evidence. Prove the paired `design`/`design-review` workflow on one bounded UI question before production authentication or dashboard visual work relies on it.

Out of scope: revising the design during review, implementing production UI, accepting the reviewer’s own design as independent evidence, planning decomposition, and granting merge or product-decision authority.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Establish independent design-review scope | N/A — the skill coordinates artifact inspection | Read brief, requirements, prototype handoff, source provenance, claimed checks, and reviewer independence | N/A — no application domain event is produced | Review criteria, available evidence, and limitations are explicit |
| Render and challenge the design | N/A — browser, capture, and accessibility tools are workflow effects | Inspect required viewports, states, interactions, semantics, contrast, copy, errors, and recovery behavior | N/A — no workflow domain event exists | Findings identify reproducible defects, unsupported claims, omissions, and subjective concerns without editing the design |
| Return a design verdict | N/A — reporting is a workflow effect | Reconcile findings with the brief and production handoff | N/A — no application domain event is produced | `accept`, `revise`, or `reject` is recorded with blocking findings separate from optional critique |
| Prove the paired workflow | N/A — disposable demonstration | Read the design output and independent review result | N/A — no application domain event is produced | One bounded question demonstrates revision/acceptance flow before production UI depends on it |

## Validation and permissions

Review must stop or qualify its verdict when the brief, required states, runnable artifact, source provenance, claimed evidence, or independence is missing. A blocking finding must cite an unmet requirement, reproducible interaction/accessibility failure, unsafe provenance/tooling issue, or material handoff omission. Taste and optional polish must be labeled non-blocking unless an approved design requirement makes them objective.

The skill must not claim WCAG conformance from automated tools or screenshots alone. It must disclose untested assistive technology, viewport, browser, interaction, and content boundaries. Review artifacts stay under the owning `.runs/prototypes/<scope>/` or another explicitly authorized ignored scratch path.

No application permission model applies. The reviewer receives read/render/check/report authority for the bounded prototype, not authority to revise production code, change planning scope, accept its own design, merge, or deploy.

## Acceptance and evidence

- `.pi/skills/design-review/SKILL.md` exists and uses project-owned design-review guidance.
- The skill checks the brief, provenance, alternatives, responsive states, keyboard/focus behavior, semantics, contrast, copy, errors, recovery, accessibility evidence limits, and implementation handoff.
- Its report separates blocking findings, non-blocking critique, evidence limitations, and an `accept`, `revise`, or `reject` verdict.
- A bounded paired demonstration uses an artifact produced through `design`, includes at least one meaningful challenge or seeded omission, and records the independent response.
- Rendering and check evidence identifies viewports, states, tools, manual observations, and unverified areas without overstating accessibility conformance.
- The reviewer does not revise the artifact or treat its own prior design work as independent review.
- The paired workflow is accepted before production authentication/dashboard visual implementation depends on it.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00006](../tasks/00006-TASK.md) | Establish and prove independent design review | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the independent `design-review` boundary approved by [WF-002](../wayfinder/tickets/WF-002-shape-agent-skill-suite.md) and the evidence constraints from [WF-006](../wayfinder/tickets/WF-006-research-ui-design-skill-sources.md). Its executable work follows the disposable design contract in [TICKET-00005](00005-TICKET.md).
