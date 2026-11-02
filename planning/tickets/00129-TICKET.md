---
id: TICKET-00129
epic: EPIC-00006
title: Adopt upstream Pi subagents with Fight observation
status: done
---

# Adopt upstream Pi subagents with Fight observation

## Problem statement

John wants Claude-style delegation, steering and resume while retaining the upper-right Fight activity pane.
Maintaining less bespoke code is the primary design goal. On 2026-11-01 he authorized planning and starting
implementation in an isolated worktree, prioritizing this integration ahead of a later MCP client evaluation.

## Solution and boundaries

Use upstream `@tintinweb/pi-subagents` as a companion package. Fight owns role configuration and a passive
observation bridge; upstream owns agent execution. Prefer supported extension/session APIs; contribute a small
missing hook upstream before considering a maintained fork. No copied subagent engine or alternate workflow
authority. Preserve independent Work/Review/QA/Land roles and qualified tool restrictions.

[TASK-00544](../tasks/00544-TASK.md) remains a separate existing-monitor repair. Neither its scope nor its
acceptance is absorbed here. Existing monitor code remains live until all actual callers are migrated.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Delegate and steer | Upstream Agent and steering tools | Upstream agent records | Upstream lifecycle | Authorized agent session |
| Observe a top-level child | Existing pane selection/pin/pause | Verified parent and child session | Pi session activity | Bounded human-only display |
| Remove superseded machinery | Owning implementation edits | Caller inventory | N/A, source maintenance | Delete replaced code and obsolete tests/docs |

## Validation and permissions

Qualify installed Pi APIs by behavior; versions are evidence, never Pi equality gates. Observation must not
launch, steer, stop, grant tools or inject transcripts into model context. Reject cross-parent, stale and nested
records; preserve safe rendering and bounded transport. Closing readers must leave agents running.
Keep Fight worktree/Docker lifecycle authoritative; disable upstream automatic worktree management in the
supported Fight profile. Package adoption is not production managed-runner isolation.

## Acceptance and evidence

TASK-00554 owns actual installed Pi/Herdr qualification, direct Harness checks, full gate and independent
Review/QA. Include foreground/background, delayed session creation, concurrency, resume, completion, reload,
missing package and failure paths. Record deletions and remaining live callers; do not introduce compatibility
layers for unused behavior or delete active callers to achieve an arbitrary line-count target.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00554](../tasks/00554-TASK.md) | Observe upstream Pi subagents in the Fight workspace | done |
<!-- /planning:children -->

## Decisions and progress

Implementation authorized 2026-11-01. Deferred MCP options are recorded in
[TASK-00555](../tasks/00555-TASK.md); that evaluation is not a prerequisite for subagent adoption.
