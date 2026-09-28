---
id: TICKET-00033
epic: EPIC-00008
title: Verify reviewed behavior with independent QA before landing
status: ready-for-agent
---

# Verify reviewed behavior with independent QA before landing

## Problem statement

Technical review alone does not demonstrate that the UI works. John needs browser/TUI interaction evidence and
comparable before/after captures in the eventual PR without managing Engineers directly.

## Solution and boundaries

Implement the QA Engineer phase after Senior Engineer review and before Release Manager `land`, under
[Team roles](../../docs/engineering/TEAM.md) and [QA standards](../../docs/engineering/QA.md). Browser execution
may be headless or visible. QA uses an immutable reviewed subject, separate identity/session and isolated test
services/data. It reports scenario results and captures; it does not change implementation or tests. Team Lead
routes blockers to Software Engineer; material repairs receive technical review and then affected QA again.
Non-interactive TASKs get a justified N/A disposition without a mandatory browser launch. Reuse unaffected
evidence with provenance after mechanical reconciliation. Public preview hosting is not required.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Verify reviewed change | Dispatch QA; record QA disposition | Subject, scenarios, sandbox and authority | QA started/completed/failed | Browser/PTY actions in owned test environment and private artifacts |
| Land verified work | Validate publication eligibility | Technical acceptance, QA and reconciliation bridge | Existing publication facts | PR with accessible Before/After evidence |

## Validation and permissions

PHP binds QA to Workflow/TASK revision, exact subject and evidence digests, and rejects stale/unowned reports.
QA cannot publish or alter source. A QA blocker prevents landing; a newly proven technical defect invalidates
technical acceptance. New supporting QA artifacts alone do not. Capture publication respects audience/privacy.

## Acceptance and evidence

- Verify relevant success, rejection, error and responsive states through actual UI actions, not screenshots alone.
- Capture known baseline and reviewed result with comparable data/dimensions; preserve commit and artifact identity.
- Render a simple Before/After PR table; report unavailable TUI image capture with real transcript/recording evidence.
- Prove fail/incomplete blocks, justified N/A, stale evidence rejection, repair routing and mechanical reuse.
- Qualify browser/PTY tooling directly; test owned workflow decisions rather than configuration or screenshot text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00146](../tasks/00146-TASK.md) | Add the local QA skill and behavioral evidence handoff | in-progress |
<!-- /planning:children -->

## Decisions and progress

Approved planning amendment. [TASK-00146](../tasks/00146-TASK.md) delivers the separately authorized local
QA skill, canonical report and work/review/land routing. Managed QA dispatch, PHP verification of reports and
Workflow phase enforcement remain for separate decomposition; local instructions do not implement those capabilities.

Technical and QA repairs share the Workflow revision-cycle allowance; QA cannot create an unbounded second loop.
Only persisted blocking findings consume a repair cycle; mechanical evidence reconciliation does not.
