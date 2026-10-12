---
id: TICKET-00063
epic: EPIC-00014
title: Administer named managed Agents and safe lifecycle intent
status: ready-for-agent
---

# Administer named managed Agents and safe lifecycle intent

## Problem statement

A stored Agent row or semantic template cannot be used by a human. Administrators need a truthful, authorized
journey to select a reviewed versioned role, name a distinct Agent, assign its real Team and Repository scope,
preview an eligible desired Permission set, confirm provisioning and inspect lifecycle/operation status without
receiving a raw secret or treating issuance as active use.

## Solution and boundaries

Make the eight reviewed managed SDLC templates from
[TICKET-00034](00034-TICKET.md) real versioned Harness resources eligible for distinctly named Agent
creation, and incorporate the separately approved Triage template under its existing EPIC-00012 owner. This
TICKET integrates the managed catalog with Agent creation; it does **not** redefine the roles, build a second
template registry or implement [EPIC-00017](../epics/00017-EPIC.md)'s AI-assisted template editor.
From one safe Super Admin UI, show authorized Agent list/detail, current name, immutable identity, Team and
Repository scope, selected template revision, safe actual-versus-requested direct Permissions, usable/unavailable
state and package operation status. Select a registered Team and allowed Repository scope, preview the **complete**
reviewed template desired Permission set, confirm a distinct Agent provisioning intent and inspect its issued,
delivered, activated and usable outcomes separately. Offer name-only updates through the package handler and
confirmed rotation/revocation intent through [TICKET-00060](00060-TICKET.md). Never present a finished Agent on
partial delivery; support safe original-operation status/recovery rather than creating a second Agent on retry.
Only an active qualified credential and current tool authorization under [TICKET-00061](00061-TICKET.md) make
an Agent usable. Future eligible custom templates may be selectable through EPIC-00017's **separate** approved
publication flow; no arbitrary browser prompt or permission list provisions a managed Agent here.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create a named Agent | Authorized Agent OS creation intent plus package provision with retained operation key | Reviewed Harness template revision, Team/Repository scope, eligible complete desired set and sink readiness | Package issuance and attributable creation intent only after commit | One Team-assigned Agent with its own protected holder and truthful delivery status |
| Inspect/manage Agent | Package list/detail/status, name-only `UpdateAgent`, confirmed rotate/revoke intent | Fresh Agent/operation/roster/Permission state and expected revisions | Existing package change facts and safe audit; N/A for read | Safe Views; name-only update, recoverable credential replacement or current revocation |
| Preview complete managed Permissions | No grant from reading; separate authorized complete-set reconciliation if approved | Authoritative Permission definitions/tiers and Agent actual set | Grant/revoke package facts only after a confirmed change | No implicit drift repair or `SUPER_ADMIN_ONLY` assignment to an Agent |

## Validation and permissions

Agent lifecycle and direct-Permission management are separate intent-specific managed
`SUPER_ADMIN_ONLY` **management rights** held only by current Super Admin authority, checked for every UI,
HTTP/CLI and direct-bus path with current actor, target, scope and confirmation. Agents may hold only eligible
`ADMIN_SAFE` **direct** Permissions; a Super Admin caller cannot put a protected Permission on an Agent. Template
revision requests are not grants. Managed-bound assignment follows reviewed complete desired sets, not ad hoc
checkbox overrides. Cohort/version/key/sink/readiness checks from TICKET-00059/60 and Team membership/active
session checks from TICKET-00062 still apply. Safe Views exclude credentials, raw material, claim/lease, sink
receipts, operation requests, keys and maintenance internals; respond to stale/conflicting edits without false
success or leaked target detail. Self-profile Tools belong to TICKET-00061, not the admin page.

## Acceptance and evidence

- A Super Admin creates a named Agent from each reviewed managed template with exactly one Team and correct
  eligible direct authority; initially unavailable role actions are clearly labeled rather than faked.
- List/detail, name changes, provision/rotate/revoke statuses and competing changes remain current, authorized,
  accessible and secret-free; unknown/missing destination or partially delivered key never appears usable.
- Every direct management path requires current intent-specific authority; template drift does not silently
  grant Permissions. Package-owned transition tests, owned PostgreSQL/HTTP/client checks and `./bin/build` pass.
- Real authenticated work and full role completion belong to TICKET-00065, not a seed-only acceptance claim.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00256](../tasks/00256-TASK.md) | Protect human Agent administration and name updates | ready-for-agent |
| [TASK-00257](../tasks/00257-TASK.md) | Preview exact managed-Agent creation | ready-for-agent |
| [TASK-00258](../tasks/00258-TASK.md) | Create one named Team-bound Agent from retained intent | needs-info |
| [TASK-00259](../tasks/00259-TASK.md) | Inspect current Agents and manage lifecycle intent | ready-for-agent |
| [TASK-00260](../tasks/00260-TASK.md) | Complete accessible managed-Agent administration | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 5 of EPIC-00014's seven-TICKET split. Requires the shared TICKET-00059/60
operation/lifecycle, [TICKET-00062](00062-TICKET.md)'s Team and
[TICKET-00061](00061-TICKET.md)'s live authorization before calling an Agent usable. Preserve
[TICKET-00034](00034-TICKET.md)'s original role ownership and [EPIC-00017](../epics/00017-EPIC.md)'s later
human-edited template publication/drift actions. This planning record creates no Agent or credential.

The maintainer approved five dependency-ordered TASKs:

- [TASK-00256](../tasks/00256-TASK.md): explicit protected human Agent-management rights and package name-only
  updates, preserving complete policy, current authorization and actual acknowledgement/failure semantics.
- [TASK-00257](../tasks/00257-TASK.md): read-only exact managed creation preview with complete reviewed desired
  Permissions, Team/Repository scope and registered holder/destination readiness, never a grant.
- [TASK-00258](../tasks/00258-TASK.md): one named Team-bound Agent from retained intent, exact template/scope
  binding and complete initial assignment through a supported qualified composition.
- [TASK-00259](../tasks/00259-TASK.md): safe current directory/detail and original-operation status, confirmed
  rotation/revocation, separating issued, delivered, activated and bounded currently usable observations.
- [TASK-00260](../tasks/00260-TASK.md): accessible real browser administration and creation from the eight
  reviewed managed templates, incorporating the separately owned Triage definition without claiming role work.

TASK-00256/00257/00259 are `ready-for-agent` only behind unfinished blockers. TASK-00258 retains `needs-info`:
inspected `AgentProvisioningRequest` contains name and destination, the service generates the Agent ID and commits
internally, and complete Permission replacement owns a separate package transaction. TASK-00253 retains supported
one-Team initial binding; TASK-00258 additionally qualifies exact template/scope/current-authority and initial-
assignment coordination. No nested outer transaction, subscriber repair, hidden audit policy, vendor edit or
internal coordinator call can manufacture a public guarantee. Any supported separate assignment phase needs
truthful incomplete state and actual activation/use exclusion; a committed Agent must never be teamless.

TASK-00260 retains `needs-info` for a precise reviewed Triage definition/registration primitive under TASK-00200/
EPIC-00012 and TICKET-00075's existing owners. Hand that definition to seed/resource authority independently of
the completed diagnosis journey, which itself needs named creation. No invented ID, duplicate Triage catalog or
whole-diagnosis/Workflow dependency is added to the creation primitive.

TASK-00258 now supplies precise creation blockers to TASK-00247, TASK-00250, TASK-00255, TASK-00200 and TASK-00206.
TASK-00255's direct mapping is resolved, so it becomes `ready-for-agent` behind unfinished blockers; all actual
creation/membership/commitment holds remain transitively effective. Explorer/session/Triage/Hotfix consumers keep
their other `needs-info` holds. Reviewed definitions/profile resolution remain TASK-00248/00249's responsibility;
creation does not depend on a running session, completed role journey or final administration UI.

TASK-00231 now consumes TASK-00259 and the TASK-00256–00258 dependency chain for actual human management,
name, creation/initial-assignment and lifecycle writer/entry qualification. Downstream switch/restore/readiness
inherit those blockers. These primitives do not depend on TASK-00241; final browser TASK-00260 does. Reusable
TASK-00242–00245 controls supply activation/current-use/MCP through TASK-00259. No live operation is enabled
before full composition qualification, and no full Workflow or Planning database-cutover gate is imposed on
first manual Explorer use.

Name updates preserve the package's last-committed-name semantics, not an invented expected name revision;
acknowledgement describes the successful normalized request, not a fresh latest-name read. Other expected-state
contracts stay intact. Distinct `SUPER_ADMIN_ONLY` human management rights never become direct Agent grants;
only the complete eligible `ADMIN_SAFE` initial set may be assigned. Existing-Agent Add Missing/Auto-sync stays
with TICKET-00078, self-profile Tools with TASK-00246, Team transfer with TASK-00254 and real role work with
TICKET-00065. Publication never silently rebinds existing Agents or sessions.

All new TASK orders remain unranked; active TASK-00138/TASK-00226 and implementation priority are unchanged.
Planning creates no Agent, Permission, credential, binding, session or live evidence. The subsequent
TICKET-00064–00069 splits bring the map to eleven accepted TASK decompositions; 13 remain, TICKET-00070–00082.
TASK-00261 consumes actual named creation for eligibility; TASK-00263/00264 supply current Team admission/
delegation, while TASK-00266–00272 consume actual owned role journeys. None becomes a prerequisite of creation
or first manual Explorer use, and none resolves the supported creation/initial-assignment composition holds.
The separate TICKET-00034 detour does not complete older Harness planning.
Next planning operation: `/skill:to-tasks TICKET-00070`.
Implementation/worktree choice, fresh qualification, independent review, publication and live effects remain
separately authorized.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 360 records,
273 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T201449Z-44833`; summary log:
`.runs/build/ticket-00063-to-tasks.log`. These validate planning and the existing repository, not future named
creation, supported binding/assignment composition, lifecycle administration or browser/role qualification.
Independent review and publication remain unperformed.
