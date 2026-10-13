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
| None | — | — |
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

Next planning operation: `/skill:to-tasks TICKET-00076`; no editor, publication or provider effect is implemented.
