---
id: TICKET-00078
epic: EPIC-00017
title: Review and reconcile existing Agent Permission drift
status: ready-for-agent
---

# Review and reconcile existing Agent Permission drift

## Problem statement

Template publication must not silently change existing Agents, but administrators need to understand and
explicitly resolve differences between a chosen published desired set and actual direct Permissions. An
additions-only update must not be mislabeled complete synchronization, and an old preview cannot authorize a
new grant or removal after concurrent changes.

## Solution and boundaries

Show versioned current drift for an authorized Agent against an explicitly selected published template revision:
desired set, actual direct set, additions, removals, invalid/ineligible items and uncertainty. Show the Agent's
existing bound revision separately from the compared revision. Reading this view grants nothing, and publication
under [TICKET-00076](00076-TICKET.md) never triggers an Agent update.

Offer two distinct one-time human-confirmed operations:

- **Add Missing Permissions:** grant only explicitly reviewed eligible missing items, preserve existing extras
  and truthfully display remaining drift. Evaluate the complete resulting assignment under package rules;
  invalid existing state cannot be silently laundered by a partial grant.
- **Auto-sync Agent Permissions:** replace the actual direct set with the reviewed complete eligible desired
  set, explicitly showing and confirming both additions and removals. Do not quietly drop invalid requested
  items and call the result synchronized.

Revalidate the current actor, Agent, template selection, Permission IDs/tiers, Team/Repository scope, owner-approved
grant limits and expected state at mutation time, including no-ops. Use the package-owned assignment operations
and actual shared transaction/reference fences. The chosen assignment commits atomically or fails; fresh state
resolves an uncertain outcome before another attempt. Stale previews need refresh and renewed human confirmation,
not automatic replay. Record attributable, secret-free evidence of the reviewed source revision and actual outcome.

Neither operation installs an always-on sync rule, changes an Agent's bound template revision, changes its prompt
or rewrites an active session's immutable Harness snapshot. Current Permission revocation nevertheless applies
immediately through [TICKET-00061](00061-TICKET.md)'s independent live authorization. No credential rotation,
Team transfer, ad hoc generic Permission editor, custom Role authority or automatic ancestor propagation is
introduced. Explicit permission reconciliation is separate from any future Agent profile revision change.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect drift | N/A — read-only preview | Current Agent assignment, selected published template, Permission definitions/tiers and scope | N/A — preview does not grant or revoke | Version-bound additions/removals/invalid/uncertain view |
| Add missing eligible items | Confirmed additions-only reconciliation composed with package assignment behavior | Fresh complete resulting set and expected state | Package assignment facts and owned reconciliation evidence only after actual committed effects | Reviewed missing grants added atomically; extras and remaining drift remain visible |
| Replace complete desired set | Confirmed full reconciliation composed with package assignment behavior | Fresh complete desired/actual sets, references, grants and revisions | Package grant/revoke facts with attributable reviewed reconciliation outcome | Exact eligible desired set replaces actual set; no persistent synchronization rule |

## Validation and permissions

Direct-Permission assignment/reconciliation requires its distinct current managed `SUPER_ADMIN_ONLY`
management Permission, held only through authoritative managed Super Admin membership, on every HTTP, CLI,
worker and direct-bus path. Template-management rights alone do not authorize Agent assignment. An actor ID,
role name, signed request or captured preview is not current authority. Even Super Admin cannot assign
`SUPER_ADMIN_ONLY` Permissions to an Agent; only eligible current `ADMIN_SAFE` direct Permissions are allowed.

Protect final full expected-state comparison and complete-set tier/reference validation against concurrent
Agent assignment, Permission removal/promotion, template selection and scope changes, including desired-state
no-ops. Compose through supported package contracts without copied lifecycle policy, nested outer transactions
or audit-repository side effects. A missing atomic authorization/assignment seam blocks exposure pending explicit
owner resolution and qualification of the tagged locked package. Do not infer concurrency safety from preflight
reads or package fixtures. Safe failures distinguish denial, invalid set, stale preview, storage failure and
uncertain commit; no false rollback, partial-success claim or name-based retargeting. Audit/projections exclude
credentials, protected operation internals and unauthorized prompt/Permission details.

## Acceptance and evidence

- Publishing/reselecting any template revision changes no existing Agent. The drift view identifies the exact
  compared versions and shows extras and invalid/unknown items without implied repair.
- Additions-only preserves extras and reports remaining drift; full sync confirms removals and atomically reaches
  the reviewed complete eligible set. Cancellation has no assignment effect; no-op still revalidates authority.
- Actual PostgreSQL races cover assignment changes, Permission reference/tier changes, stale previews, rollback
  and unknown commits. Direct unauthorized calls and protected-tier grants fail, even from a Super Admin caller.
- Live tool checks observe revoked Permissions despite pinned snapshots; UI and audit identify actual outcomes
  without claiming a prompt/template rebind. Owned tests, real consumer contract qualification and
  `./bin/build` pass with exact package/source/lock and limitations recorded.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00303](../tasks/00303-TASK.md) | Review and explicitly reconcile an Agent's template Permission drift | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of EPIC-00017's five-TICKET split. Extends
[TICKET-00063](00063-TICKET.md)'s managed Agent administration with the explicit EPIC-00017 drift actions,
consumes [TICKET-00076](00076-TICKET.md)'s published revisions and uses the qualified shared Agent/Permission
persistence rather than a second grant system. [ADR 0001](../adr/0001-application-ownership-and-orchestration.md)
and [ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) retain ownership/transaction rules.
No Agent Permissions, profile bindings or sessions changed during decomposition.

TICKET-00063's accepted TASK-00256 supplies the distinct protected human management policy and TASK-00258
supplies complete initial assignment for named creation, with supported binding/authorization composition held.
TASK-00259 exposes safe bound/requested-versus-actual inspection without mutation. Reuse those prerequisites
when mapping this TICKET's exact TASKs; do not duplicate initial creation or mistake its authority for approval
to reconcile existing Agents. Add Missing/Auto-sync and their fresh confirmed complete-set concurrency contract
remain here, never a recurring rule or a template/session rebind.

TICKET-00076 now maps [TASK-00300](../tasks/00300-TASK.md)'s published revision/authorized impact boundary and
[TASK-00301](../tasks/00301-TASK.md)'s retained-revision/ancestor review. Consume actual selected immutable
revisions without requiring completed ancestor review for ordinary drift inspection/reconciliation. The editor's
Agent impact does not duplicate this comparison or authorize its assignment effects. TASK-00301 may offer this
separate handoff only when available; publication never dispatches it, and this TICKET cannot reverse-block the editor.

### Approved TASK decomposition

The maintainer approved one unranked feature: [TASK-00303](../tasks/00303-TASK.md), **Review and explicitly reconcile
an Agent's template Permission drift**, blocked by TASK-00300, TASK-00241 and TASK-00250. TASK-00259 inspection,
TASK-00256's distinct assignment authority and initial creation/assignment are upstream. This one browser/API
outcome owns versioned preview, Add Missing and Auto-sync, their explicit confirmations, recovery and evidence;
no inspection-only, infrastructure or proof TASK is added.

Status `needs-info` retains the supported atomic current-caller/target/template/scope authorization, complete-set
assignment, required reviewed-source audit and outcome-recovery seam. The inspected public replacement command
carries actor/Agent/expected assignment revision/IDs, not the full consumer review intent; its internal coordinator
owns a transaction and its handler publishes afterward. Preflight checks, internal-coordinator calls, outer
transactions or post-commit audit cannot supply the missing guarantee. No-op races require explicit qualification.
These are unqualified integration contracts, not a demonstrated deployed exploit or permission to patch vendor code.

Add Missing preserves extras and validates the complete resulting assignment; Auto-sync explicitly confirms
removals and requires the complete eligible desired set. Bound and compared revisions remain separate. Neither
action rebinds a template/prompt, rotates credentials, refreshes sessions or installs recurring sync. Actual next-use
revocation evidence consumes existing credential/session owners. Refresh affected writer/cohort qualification without
making primitives reverse-depend on this finished UI. AI proposals, ancestor review, finished browser administration
and complete role journeys are not prerequisites.

Four of five EPIC-00017 TICKETs and twenty of 24 map TICKETs are decomposed, not implemented. Four remain,
TICKET-00079–00082, alongside older incomplete Harness/managed review planning. Active work and priorities remain
unchanged. Next: `/skill:to-tasks TICKET-00079`. No Agent grants, bindings, credentials or sessions changed.

Planning verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with
403 records and 316 active. `./bin/build` passed: 459 tests, 3499 assertions, 78 captures, zero violations.
Evidence: `.runs/build/20261013T162942Z-80679`; log: `.runs/build/ticket-00078-to-tasks.log`.
Sass deprecations, React-Bootstrap `use client` and >500 kB bundle warnings remain. These are fresh planning/
existing-repository checks, not atomic reconciliation, live assignment or retained-session revocation qualification.
Independent review and publication remain unperformed.

Seven pre-existing planning edits (TASK-00141/00142/00164/00250/00266 and TICKET-00032/00034) were preserved
byte-for-byte and excluded from this change. They were separately committed as `a4b2cfb` during this operation;
the build validates the combined checkout on that baseline, not an isolated tree without those edits.
The current map handoff above supersedes the earlier handoff left untouched in TICKET-00034.
