---
id: TICKET-00076
epic: EPIC-00017
title: Author and publish reviewed template revisions
status: ready-for-agent
---

# Author and publish reviewed template revisions

## Problem statement

Super Admin needs to inspect and evolve managed and custom Agent templates without source-file edits, while
understanding history, inherited changes and impact. Saving a prompt must not silently grant Permissions,
replace an existing Agent's configuration or invent eligibility for a Workflow.

## Solution and boundaries

Deliver authorized browser browse/detail, editable draft, history/diff and impact journeys over
[TICKET-00075](00075-TICKET.md)'s database-authoritative Harness templates. Support human-only creation and
revision of managed starting templates and authorized installation/Workspace-wide or registered-Repository-scoped
custom templates. Show stable template identity, functional role/persona, human-reviewed system prompt, complete
requested direct-Permission set, applicable resource/model/Repository constraints, managed origin, ancestry,
revision and provenance. Names and prompts describe behavior; they do not establish authenticated identity.

A human explicitly reviews both prompt and requested Permissions before publication. Validate the complete
candidate and expected current state, append/select an immutable revision atomically and retain historical bytes.
Offer explicit rollback by selecting a retained revision after current validation and impact review, never by
rewriting history. Publication or rollback affects future selection only: it changes no existing Agent's stored
Permissions, bound template revision or active session snapshot. Existing-Agent reconciliation belongs to
[TICKET-00078](00078-TICKET.md), and new-Agent use belongs to [TICKET-00079](00079-TICKET.md).

When a custom template's ancestor changes, prepare an explicit three-way comparison of the previous ancestor,
new ancestor and custom differences. The administrator may retain the current custom revision, publish a reviewed
update for future Agents or separately review affected Agents. Do not silently inherit authority or combine
publication with Agent updates. Draft handling must distinguish unsaved, reviewed, published, stale and uncertain
states; loss of a browser connection cannot publish a draft. Exact form layout, transport and any protected
server proposal persistence are implementation choices, not permission for browser-storage drafts.

No generic Skill/instruction editor, Role/Permission CRUD, source-defined policy mutation, new scoped administrator
Role, automatic template sync or Workflow routing is included. This editor consumes existing Harness resource and
Permission catalogs rather than creating competing definitions.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect template and impact | N/A — read-only browse/detail/diff | Authorized revisions, ancestry, resource constraints and affected Agent references | N/A — inspection emits no publication or grant fact | Safe history and future/existing-Agent impact remain distinct |
| Author and publish | Owned Harness create/revise/publish operation after explicit human review | Current template state, candidate validation and authoritative reference choices | Owned committed template revision/publication facts | Immutable reviewed revision selected for future use; no Agent mutation |
| Roll back or review inheritance | Explicit select-retained-revision or publish-reviewed-ancestor-update operation | Retained history, three-way diff and fresh impact | Owned revision-selection/publication fact on committed change; none for keep-current inspection | Explicit future template selection without rewriting existing Agents or sessions |

## Validation and permissions

Template management/publication requires its distinct current managed `SUPER_ADMIN_ONLY` management Permission
through the authoritative managed Super Admin Role on every HTTP and non-HTTP entry. Revalidate actor, target,
scope, expected revisions and referenced resources; a browser guard, role-name check or model assertion is not
authorization. Requested Agent Permissions must resolve to current eligible `ADMIN_SAFE` IDs within approved
grant limits, not names guessed by the editor or a model. Surface missing/ineligible entries for correction;
do not silently drop them and publish a different desired set. Recheck again at actual assignment.

Enforce registered-Repository applicability and current model/resource constraints. Conflicts require refresh and
human review; uncertain commits require status reconciliation before another publication attempt. Protect prompt
and Permission details by scope and keep secrets/private cross-Repository content out of responses, logs and
ordinary history. Use [ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md) for browser transport, current authority and
memory-only client state. Required revision/audit effects are atomic; no external provider call occurs in that transaction.

## Acceptance and evidence

- A currently authorized administrator can inspect, create, revise, publish and explicitly roll back managed
  and custom templates without changing seed files or silently changing any existing Agent/session.
- PostgreSQL revision races and rollback preserve complete candidates/history; stale or uncertain publication
  never becomes false success. Unauthorized direct entry and out-of-scope reads/writes fail safely.
- Three-way ancestor updates preserve custom choices and show affected Agents; accepting a template update
  alone grants nothing. Invalid/ineligible references require explicit correction and renewed review.
- Accessible browser success, empty, denial, conflict and disconnect states work with owned tests and
  `./bin/build`. Human-only publication is not evidence that the separate AI/provider path works.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00300](../tasks/00300-TASK.md) | Author and publish human-reviewed Agent templates | needs-info |
| [TASK-00301](../tasks/00301-TASK.md) | Review ancestor updates and restore retained template revisions | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00017's five-TICKET split. Depends on
[TICKET-00075](00075-TICKET.md)'s database-authority transition and existing
[EPIC-00006](../epics/00006-EPIC.md) Harness resources, current principal and protected administration.
[TICKET-00077](00077-TICKET.md) contributes editable proposals, not a second publication path.
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) preserves Harness versus AccessControl
ownership. No template revision, browser editor or publication was implemented here.

TICKET-00075 now maps [TASK-00299](../tasks/00299-TASK.md)'s single protected initialization/inspection and
immutable database-template authority. Consume its approved managed `MANAGE_AGENT_TEMPLATES` SUPER_ADMIN_ONLY
Permission through managed Super Admin and the same store/current-selection contracts, not a second catalog or
policy. Its source-writer transition must be qualified before publication is enabled; its required resource/
reference and independent Triage-input holds remain explicit. Completed editing never becomes an upstream seed
prerequisite. This TICKET retains actual browser publication, history/rollback/ancestor review and impact evidence.

### Approved TASK decomposition

The maintainer approved two dependency-ordered vertical features, both unranked:

- [TASK-00300](../tasks/00300-TASK.md): human browse/create/revise/review/publish through a complete browser/API
  path, with history/diff, authorized Agent impact and original-outcome recovery. Blocked by TASK-00249,
  TASK-00259 and TASK-00051; TASK-00299's database authority is upstream. Status `needs-info` until complete
  unpublished-candidate validation and final reference/current-authority fencing composition are qualified.
- [TASK-00301](../tasks/00301-TASK.md): explicit retained-revision restoration and exact old-ancestor/new-ancestor/
  custom review through that same publication boundary. Status `ready-for-agent`, blocked by TASK-00300 and its
  inherited holds. Keeping current mutates nothing; accepting changes never silently updates existing Agents.

TASK-00300 reuses MANAGE_AGENT_TEMPLATES and TASK-00259's separately protected Agent inspection. Read-only profile
resolution and Permission lists are not an atomic publication guarantee. Qualify actual participant/currentness/
transaction contracts; unknown support holds without vendor patches, nested transactions, a duplicate catalog or
invented dependency IDs. Complete prompt/Permission review, expected-selection generations, immutable history and
atomic required audit protect both slices. No publication assigns Permissions, rebinds Agents or refreshes sessions.

TICKET-00077's actual AI proposal integration and TICKET-00079's custom use can consume TASK-00300 without waiting
for TASK-00301 or whole-TICKET completion. Human-only editing/publication remains available independently of
`ai-template-proposals`; this split implements no provider call or second publisher. TICKET-00078 retains the
separately authorized existing-Agent Permission actions. Ancestor review offers that real handoff only when
available, never a fake sync or a reverse prerequisite.

With subsequent splits through TICKET-00080, all five EPIC-00017 TICKETs and twenty-two of 24 map TICKETs have
approved TASK decompositions, not runtime acceptance. Two remain, TICKET-00081/00082, alongside older incomplete Harness/
managed review planning. Priorities, active work and the terminal-first milestone are unchanged.
Next: `/skill:to-tasks TICKET-00081`.

TICKET-00077 now maps [TASK-00302](../tasks/00302-TASK.md)'s real AI suggestion → human prompt/Permission edits →
explicit publication integration. It consumes TASK-00300 without TASK-00301 and retains its precise provider/
protected-attempt recovery holds. Human authoring remains independent of AI Feature/provider availability.
No editor, publication, template selection, Agent/grant/session change or provider effect occurred during planning.

Planning verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with
401 records and 314 active. `./bin/build` passed: 459 tests, 3499 assertions, 78 captures, zero violations.
Evidence: `.runs/build/20261013T154606Z-74329`; log: `.runs/build/ticket-00076-to-tasks.log`.
Sass deprecations, React-Bootstrap `use client` and >500 kB bundle warnings remain. These are fresh planning/
existing-repository checks, not editor/publication/reference-fencing, ancestor review or AI/provider qualification.
Independent review and publication remain unperformed; no runtime template or Agent state was changed.
