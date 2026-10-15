# Wayfinder

<!-- planning:maps -->
| Map | Status |
|---|---|
| [accesscontrol-consumer-adoption-map](accesscontrol-consumer-adoption-map.md) | Closed |
| [complete-agent-os-vision-map](complete-agent-os-vision-map.md) | Closed |
| [hierarchical-agent-memory-map](hierarchical-agent-memory-map.md) | Closed |
| [operational-observability-map](operational-observability-map.md) | Closed |
| [sdlc-intake-triage-map](sdlc-intake-triage-map.md) | Closed |
| [usable-web-application-foundation-map](usable-web-application-foundation-map.md) | Closed |
<!-- /planning:maps -->

## Current routing

Use the [Roadmap's current planning handoff](../ROADMAP.md#current-planning-handoff) and
[blended delivery strategy](../ROADMAP.md#approved-blended-delivery-strategy) after the planning merges.
The maps below retain their accepted scope and historical approvals; closed maps have no decision frontier.
Consult the [Board](../tasks/BOARD.md) for active work and executable priority, not historical map snapshots or
retired planning-worktree paths. A missing implementation supplier remains a hold, not a reason to reopen every map.

## Observability planning handoff

[Operational logs and failure diagnostics](operational-observability-map.md) is **Closed**, with WF-036–042 resolved
and no open decision frontier. John approved the complete live-and-archived diagnosis release, explicit adjacent-
product deferrals and one [EPIC destination](operational-observability-map.md#approved-epic-handoff):
[**EPIC-00019 — Diagnose Agent OS operations and managed runs with live and archived logs**](../epics/00019-EPIC.md).
The separately confirmed grill wrote/approved this sole EPIC after rebasing onto fetched `develop` at `ac41c28` and
checking live/archive IDs across branches/worktrees. Its [seven-TICKET split](../epics/00019-EPIC.md#approved-ticket-decomposition-and-current-handoff)
is now approved/written as TICKET-00111–00117 after another numbering check. EPIC/TICKET phases are complete;
[TICKET-00111's four-TASK split](../tickets/00111-TICKET.md#approved-task-decomposition-and-current-handoff) is now
accepted/written as TASK-00451–00454; [TICKET-00112's six-TASK split](../tickets/00112-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00455–00460; [TICKET-00113's six-TASK split](../tickets/00113-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00461–00466; [TICKET-00114's seven-TASK split](../tickets/00114-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00467–00473; [TICKET-00115's four-TASK split](../tickets/00115-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00474–00477; [TICKET-00116's three-TASK split](../tickets/00116-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00478–00480; [TICKET-00117's seven-TASK split and completed handoff](../tickets/00117-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00481–00487. **All map planning phases are complete**: one EPIC, seven TICKETs and
thirty-seven unranked TASKs. Sixteen receiving TASKs now name early installation suppliers, retaining qualification
gates. TASK-00487 owns composed operator/workload acceptance, not an extra unrecorded parent gate.
Upstream TICKET-00110's local terminal log pane remains independent; no existing TICKET/TASK IDs or priorities changed.

The handoff retains conditional Loki, native Super Admin-only Logs, independent local verified archives and all
mandatory collection/storage/authority/browser/resource qualification gates. General metrics, tracing, external
alerting and error grouping are deferred; independent health/loss visibility remains required. No implementation,
permission assignment, production deployment or ongoing runtime grant follows from planning closure.

Next: `/skill:next` from the current checkout for read-only Board-based work selection.
No implementation, runtime experiment or publication is invoked. Recheck upstream/IDs before future allocation;
TASK priorities and qualification holds are unchanged. Earlier branch/worktree snapshots record planning history,
not the current delivery route.
