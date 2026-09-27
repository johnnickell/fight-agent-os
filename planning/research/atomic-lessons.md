# External workflow study: accepted local adaptations

Reviewed as inspiration for TASK-00135, 2026-09-26. The [Atomic repository](https://github.com/bastani-inc/atomic)
and [runtime overview](https://github.com/bastani-inc/atomic/blob/main/README.md) describe explicit execution
stages, checkpoints, scoped handoffs, verification and bounded repair. These are upstream claims/features, not
proof of Agent OS behavior. The linked [video](https://www.youtube.com/watch?v=epg292lGCZ4) motivated the study;
this amendment does not claim a verified transcript or timestamp-by-timestamp comparison.

## Local choices

- Make phase transitions, required evidence and bounded loops explicit PHP contracts; agent prose cannot advance
  authoritative state. Validate graph/contract versions and dependencies before dispatch.
- Retain durable intent, checkpoints and attempt identity; reconcile uncertain effects and fence stale workers.
- Separate human decisions, agent judgment and deterministic effects. Team Lead routes work; the Runner executes
  authorized lifecycle operations. Independent review and post-review QA have separate evidence.
- Show actual phase/owner, waits, retries, recovery and correlated artifacts. Observations remain non-authoritative.
- Keep the first one-TASK production slice; avoid adopting another runtime, workflow language or unbounded agent team.

These adaptations are specified in [EPIC-00007](../epics/00007-EPIC.md),
[EPIC-00008](../epics/00008-EPIC.md), [isolated startup](../tickets/00032-TICKET.md) and
[QA](../tickets/00033-TICKET.md). Existing Agent OS event authority, privacy and package boundaries prevail.
