---
id: TICKET-00084
epic: EPIC-00008
title: Coordinate local TASK delivery through an open reviewed PR
status: done
---

# Coordinate local TASK delivery through an open reviewed PR

## Problem statement

The operator currently invokes work, review, QA and land separately. A local Team Lead should coordinate those
existing responsibilities so the human's normal next action is reviewing an already-open PR with applicable
screenshots, while the managed Workflow is still being built.

## Solution and boundaries

Provide `/skill:build TASK-NNNNN`, also accepting a TICKET that resolves unambiguously to one eligible TASK.
The Team Lead delegates implementation, independent technical review, independent QA and Release Manager land
to fresh Pi processes. It routes confirmed blocking findings through at most five shared repair cycles and
continues authorized non-force push, PR creation/update and sanitized evidence upload without another human
approval. Preserve existing skill/report contracts, screenshot applicability and GitHub ownership checks.

One invocation owns one TASK, not a TICKET-wide implementation pipeline. Preserve selected checkout, active work,
source permissions and task identity. Local role separation is instruction/context separation under the operator's
OS account, not managed identity or security isolation. No imported Factory workflow, global installation,
new product runtime, autonomous merge, release or deployment.

The maintainer also requested that `next` recommend `build` instead of `work` by default for executable
implementation and confirmed repair work. Preserve read-only recommendation, Board selection and existing
evidence-only/QA/delivery/planning/prerequisite routes; an explicit implementation-only choice may still use `work`.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Coordinate one approved TASK | `build` delegates `work`, `review`, `qa`, `land` | Planning, Git, current reports and artifacts | Local run records only; no Domain events | Owned commits, independent evidence and one verified PR |
| Handle a blocker or interruption | Bounded repair or evidence recovery | Current subject, process state and original GitHub result | Local decisions and stop reason | Fresh verification or a precise hold; no blind publication retry |

## Validation and permissions

Invocation authorizes the named local delivery workflow including land. Ask only for missing scope/checkout or
effects beyond that authority. Current review and applicable QA remain mandatory. Preserve separate role sessions,
exact heads, shared repair limits and report provenance. Reject ambiguous TICKET selection or concurrent ownership.
Retain evidence privately under the canonical base checkout's ignored `.runs/`; publish only audience-safe captures.

## Acceptance and evidence

The skill can be invoked in Pi, launches fresh role sessions using qualified installed CLI options, routes a
normal flow and blocking/ambiguous cases correctly, and explicitly hands off verified PR/evidence or the exact
unfinished prerequisite. Check discovery, links, commands and instruction walkthroughs directly; distinguish
local qualification from an actual end-to-end TASK publication. No product-suite tests of skill prose.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00306](../tasks/00306-TASK.md) | Add a local build coordinator through automatic PR publication | done |
<!-- /planning:children -->

## Decisions and progress

Approved for immediate local implementation on the current checkout by the maintainer on 2026-10-13, named
`build` at the maintainer's request. Plan and implementation commits remain separate from concurrent TICKET
decomposition. [TICKET-00083](00083-TICKET.md) owns the distinct managed delivery requirement.

On 2026-10-13 the operator authorized complete TASK-00306 closeout, including target-context and phase-model
amendments, and explicitly deferred behavioral QA to live Harness testing during the next actual TASK. Its
independent technical acceptance and direct qualification are recorded in the child; live QA is **deferred,
not PASS or N/A**. This exception applies only to qualification of the local coordinator itself. It does not
weaken the review/QA/publication gates that the coordinator must apply to subsequent TASKs, close managed
TICKET-00083, or claim live end-to-end delivery. Eligible parent completion follows the child's recorded outcome.
