---
id: TICKET-00064
epic: EPIC-00014
title: Select eligible Teams and Agents for authorized work
status: ready-for-agent
---

# Select eligible Teams and Agents for authorized work

## Problem statement

A role label, Team membership or available Agent row cannot safely choose who receives a human-selected TASK.
The existing Workflow/Triage plans need one deterministic Team/roster eligibility supplier that prevents
cross-Team peer delegation, double booking and false progress when no eligible Agent exists.

## Solution and boundaries

On a **human-selected** Ready TASK, resolve registered Repository and associated default/alternate Teams,
current role suitability, each Agent's live credential, direct Permissions, repository/context scope, idle
capacity, grant, resources, same-Team review independence and actual unmerged prerequisite base inclusion.
Prefer an eligible idle Team Lead on the default Team; if unavailable, fairly and repeatably recommend an
eligible alternate **whole-TASK** handoff to another Team explicitly associated with the Repository. Hold
with a reason on missing or uncertain eligibility rather than borrowing a cross-Team member. Once a Team
receives a TASK, its Team Lead may delegate only to currently eligible members **of that Team**, including
a non-contributing independent reviewer. A role matching a prompt is not evidence of eligibility.

Supply this selector and current roster-authority check to the **existing** Triage and Workflow owners:
[EPIC-00007](../epics/00007-EPIC.md), [EPIC-00008](../epics/00008-EPIC.md) and
[EPIC-00012](../epics/00012-EPIC.md) / [TICKET-00052](00052-TICKET.md). Their human gesture, route,
exclusive atomic claim/start, Kanban transitions and hotfix confirmation remain their operations. A visible,
withdrawable *Pending start* is an intent, not a claim, queued Runner or actual Agent work. Persist/coordinate
it through its owning Workflow path and revalidate at one authoritative start; do not create a second queue,
claim engine or separate automatic starter here. Without current eligibility, stop and keep the intent
visible; changing scope or expired consent needs a new human decision. Autonomous Triage TASK selection,
automatic PR merge and cross-Team peer dispatch are excluded.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Recommend an intake Team | N/A — selector cannot claim | Current TASK/base, default/alternate associations, actual eligible idle Leads and fairness state | N/A — a recommendation is not a Workflow event | Candidate Team/Lead or explicit hold for the existing start operation |
| Resolve same-Team delegation | Existing owner submits delegation intent | Live Team roster, role suitability, contribution/independence and grant | Existing Workflow's accepted handoff event only after its commit | Eligible same-Team assignment or safe refusal, no role switch |
| Revalidate pending start | Existing Workflow start/cancel commands, not a duplicate Agent OS claim | Changed authority, Team roster, dependency inclusion, context and exclusive claim | Existing pending/queued/started/withdrawn facts | One start or truthful Pending/Needs Human without false in-progress state |

## Validation and permissions

Current Human start authority, Agent direct Permission, Team/Repository scope, verified base/dependencies,
runner capacity, independent reviewer and exclusive claim must all hold at the owning start, not only when
Triage suggested a route. Current roster/default changes race safely against that start. Triage cannot grant
emergency authority or select an unrelated TASK autonomously; Team Lead cannot borrow another Team's Agent.
Hotfix retains explicit human scope confirmation and a non-contributing reviewer. A failure to authenticate,
verify dependencies or determine idle capacity is a hold, not a guess or client-side authorization.

## Acceptance and evidence

- Default versus alternate whole-TASK intake and fair equivalent choices are deterministic; lack of an eligible
  Agent, unavailable dependency base or ambiguous resource produces visible, withdrawable Pending/hold state.
- Same-Team delegation enforces current roster, credential, permission and contribution independence through
  concurrent starts/transfers; no double assignment or false transition occurs.
- Real selected TASK start/dispatch consumes the selector through its owning Workflow/Triage path without
  creating a second claim engine. Owned race/authorization tests and `./bin/build` pass; application tests do
  not replace actual Runner or isolation qualification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 6 of EPIC-00014's seven-TICKET split, consuming
[WF-035](../wayfinder/tickets/WF-035-define-project-teams-and-agent-selection.md) and
[TICKET-00062](00062-TICKET.md)'s roster plus [TICKET-00061](00061-TICKET.md)'s live Agent use. The first
manual Explorer does not wait for a complete Workflow selector. The current parent Workflow and hotfix TICKETs
remain the owners of claim, Kanban, routing and incident operations.
