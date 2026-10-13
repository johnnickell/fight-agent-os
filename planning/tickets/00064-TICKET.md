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
| [TASK-00261](../tasks/00261-TASK.md) | Resolve current Agent eligibility for selected work | needs-info |
| [TASK-00262](../tasks/00262-TASK.md) | Recommend fair default or alternate-Team intake | ready-for-agent |
| [TASK-00263](../tasks/00263-TASK.md) | Bind Team selection at the authoritative start | needs-info |
| [TASK-00264](../tasks/00264-TASK.md) | Enforce same-Team delegation and reviewer independence | needs-info |
| [TASK-00265](../tasks/00265-TASK.md) | Operate withdrawable Pending start through existing entry paths | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 6 of EPIC-00014's seven-TICKET split, consuming
[WF-035](../wayfinder/tickets/WF-035-define-project-teams-and-agent-selection.md) and
[TICKET-00062](00062-TICKET.md)'s roster plus [TICKET-00061](00061-TICKET.md)'s live Agent use. The first
manual Explorer does not wait for a complete Workflow selector. The current parent Workflow and hotfix TICKETs
remain the owners of claim, Kanban, routing and incident operations.

The maintainer approved five dependency-ordered TASKs:

- [TASK-00261](../tasks/00261-TASK.md): current Agent eligibility for selected work, combining owning TASK/base
  facts, exact profiles, Team/Repository scope, current authority and actual idle capacity without reservation.
- [TASK-00262](../tasks/00262-TASK.md): deterministic default-Team preference and fair alternate whole-TASK
  intake, never another TASK selection, claim or cross-Team phase assignment.
- [TASK-00263](../tasks/00263-TASK.md): current Team/Lead admission through TASK-00139's existing Start, with
  atomic owning commitment/claim/grant/dispatch and committed fairness advancement.
- [TASK-00264](../tasks/00264-TASK.md): same-Team phase admission and actual reviewer independence, without
  duplicating managed Review/QA/publication or introducing Team Lead into direct hotfix.
- [TASK-00265](../tasks/00265-TASK.md): durable withdrawable Pending start and actual terminal/browser integration
  through existing Execution and TASK-00204/00205 owners, after shared runtime readiness.

TASK-00261/00263/00264 retain `needs-info` for exact lower-level commitment/resource, verified configured-base
inclusion, current admission and contribution/fresh-session primitives. TASK-00139 supplies the base Start and
pre-cutover immutable Markdown eligibility path, not proof that every such participant is already implemented.
Where database Planning is the actual source, use TASK-00096's canonical typed fact/reason seam without another
algorithm; browser/Triage consumers retain their own actual Planning dependencies. No database cutover gate is
added to core terminal selection or manual Explorer use.

Approved fairness prefers an eligible default Team, then least-recently-admitted eligible alternative Team and
eligible Lead, with stable identity ties and never-admitted equivalents first. Choose Team before Lead so extra
Leads do not multiply a Team's alternate turns. Keep order/history under Workflow ownership and advance only
on actual committed new intake, never preview, rejection, rollback or duplicate original-command resolution.
This policy does not promise starvation freedom under arbitrary changing capacity/eligibility.

Pending intent names one human-selected TASK and bounded consent, not a claim, Workflow start, Agent reservation
or queued Runner. Retry/reconsider only within its finite valid scope/deadline, revalidate at one start and resolve
uncertainty before another effect. Changed scope or expired authority needs renewed human decision. Withdrawal
serializes against admission: a lost race exposes the already-started Workflow, never silent live cancellation.
A PR-wait label alone does not occupy an Agent; active/uncertain commitments hold. A Done prerequisite still needs
actual verified inclusion on the configured base. Neither a prompt nor a new reviewer name/session proves
independence from design/build/evidence contribution.

TASK-00141 now consumes TASK-00264 and TASK-00204 explicitly consumes TASK-00263. TASK-00207 consumes
TASK-00264's same-Team independence guard while retaining all missing managed Review/QA/publication holds.
TASK-00205 remains the browser gesture owner and supplies later TASK-00265; no reverse blocker is added to
TASK-00139 or the completed surface/role journeys. Authored order and the first Awaiting review milestone stay
unchanged. Actual component writers must refresh affected shared cohort/fence evidence before exposure.

TASK-00253/00254 remain the Team membership/fence and guarded-transfer owners. These selector/delegation
consumers do not resolve TASK-00254's missing lower-level commitment writers and must not be reverse blockers
of TASK-00244, TASK-00254, TASK-00241 or final manual Explorer use. No second claim/commitment/queue/Runner,
automatic unrelated TASK selection, cross-Team peer delegation, merge or operational enrollment is introduced.

TICKET-00065's subsequent TASK-00267 consumes actual TASK-00265 intake/Triage and TASK-00141 implementation
for independent managed review, followed by TASK-00268 QA and TASK-00269 publication. Its other slices consume
browser Planning, direct hotfix and independent library/application release paths. These are final integrations,
not the missing generic phase/session/commitment primitives; no reverse blocker or live readiness is inferred.

All new TASKs are unranked and active TASK-00138/TASK-00226 remain unchanged. With the TICKET-00065–00080 splits,
twenty-two of the map's 24 TICKETs have approved written TASK decompositions; two remain, TICKET-00081/00082.
Older Harness/managed review planning is not completed by these mappings.
Next planning operation: `/skill:to-tasks TICKET-00081`, mapping actual backend/API/browser references.
Implementation/worktree choice, fresh runtime qualification, independent review, publication and live effects
remain separately authorized.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 365 records,
278 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T204437Z-46921`; summary log:
`.runs/build/ticket-00064-to-tasks.log`. These validate planning and the existing repository, not future live
eligibility, fairness, atomic Team admission, independent delegation or Pending start/Runner behavior.
Independent review and publication remain unperformed.
