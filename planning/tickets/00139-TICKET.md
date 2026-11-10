---
id: TICKET-00139
epic: EPIC-00025
title: Reconcile JSON-managed authority and access defaults
status: ready-for-agent
---

# Reconcile JSON-managed authority and access defaults

## Problem statement

Fight already has idempotent managed-authority reconciliation, but its complete catalog is embedded in PHP.
The current Super Admin membership omits three defined Project Permissions. Operators need one readable
version-controlled catalog with the approved User baseline and complete current/future Super Admin membership,
without introducing a second seeder or weakening existing authority protections.

## Solution and boundaries

Deliver the bounded authority change approved in [EPIC-00025](../epics/00025-EPIC.md):

- Make `database/seeds/permissions.json` and `database/seeds/roles.json` the single authoritative source of
  managed definitions and memberships. Replace superseded PHP catalog literals after qualifying the replacement;
  do not keep duplicate desired-state catalogs or an Omphalos synchronizer alongside Fight's reconciler.
- Preserve stable Permission/Role IDs, current names/tiers, complete application references and supported typed
  package contracts. Stable managed identities remain usable by invitation/bootstrap and other existing consumers.
- Keep `ROLE_USER` membership exactly `VIEW_DASHBOARD`, `READ_OWN_SESSIONS`, `DELETE_OWN_SESSIONS`. The earlier
  dashboard-only suggestion was superseded; there is no approval to remove own-session access.
- Give `ROLE_SUPER_ADMIN` every current and subsequently added managed Permission. Represent this continuing
  complete-catalog rule without a separately maintained duplicate list that can drift. Amend the applicable
  [managed authority contract](../../docs/engineering/MANAGED_POLICY.md#permission-eligibility-is-not-assignment)
  and unfinished forward requirements to reflect the approved Super Admin default. Definition/tier still grants
  nothing automatically to ordinary Users, custom Roles or Agents; no role-name authorization bypass is added.
- Retain explicit trusted-operator preview/apply through the existing package `PreviewManagedPolicy` and
  `ReconcileManagedPolicy` operations and configured buses. Current commands are
  `./bin/managed-policy preview --internal` and `./bin/managed-policy apply --internal`. No automatic application
  startup reconciliation, parallel loader, reset or fixture-only shortcut is authorized.
- Preserve managed convergence, unchanged-state idempotency, safe collision rejection, custom/unmanaged authority
  protection, reference/removal fences, transactional writes and truthful post-commit uncertainty.
- Preserve ordinary HTTP/Console invitation assignment of `ROLE_USER`, and existing elevated invitation/bootstrap
  assignment semantics. Those flows require the complete reconciled catalog. Their independently committed
  invitation, membership and audit steps retain the disclosed partial-failure boundary.

Omphalos's stable-ID JSON catalogs and Permission-name membership references are representation evidence only.
Use Fight's existing canonical package ownership and reconciliation semantics, not the example's unrelated
reference-data catalogs, creator identities or custom synchronizer.

Excluded: a wider Permission audit, new User defaults, User backfill, invitation transaction/recovery redesign,
changes to elevated/bootstrap policy, removal of custom authority, automatic startup loading, database resets,
production deployment and a new generic seeding framework. The sibling [TICKET-00138](00138-TICKET.md) owns URL
cutover; neither TICKET requires an artificial dependency on the other.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Preview desired managed authority from JSON | N/A: preview performs no business mutation | Existing package `PreviewManagedPolicy` over validated complete desired policy | N/A: read-only preview needs no event | Safe deterministic create/reconcile/remove/unchanged plan; no authority writes |
| Apply or repeat desired authority | Existing package `ReconcileManagedPolicy` through configured CommandBus | Existing package planner and authoritative repository reads inside its supported transaction | Existing package `ManagedPolicyReconciled` after commit, including its existing no-op semantics | Complete managed definitions/memberships converge; unchanged state/timestamps remain unchanged; custom authority survives |
| Add a future managed Permission | Explicit catalog maintenance, then the same reconciliation command | Same preview and reference checks | Same existing reconciliation event | Super Admin gains the new definition upon apply; User/custom/Agent grants do not change implicitly |
| Invite an ordinary or elevated user against reconciled authority | Existing invitation and Role-assignment operations, unchanged | Existing complete-policy and admission reads | Existing invitation/assignment events and audit boundaries | Ordinary success retains the User Role; elevated/bootstrap semantics remain unchanged; partial issuance remains truthful |

## Validation and permissions

Validate complete catalog structure, stable typed identities, supported names/tiers, duplicate IDs/names,
role membership references and the approved complete-Super-Admin/three-Permission-User policy before unsafe
effects. Malformed, incomplete or conflicting desired state must not silently grant authority or become an
empty replacement policy. Preserve complete application references rather than deriving removal safety from
catalog presence alone.

Keep unmanaged name/identity collisions as explicit failures, not automatic adoption. Preserve protected-tier
restrictions and repository reference/removal fences; do not delete or recreate identities to make a seed run
pass. Preserve needed durable authority data and required audit/recovery history.

The command is trusted local operator access: `--internal` acknowledges that boundary, not end-user
authentication. Do not expose it through HTTP or automatic boot. Preview is advisory; apply re-reads current
state through the existing canonical transaction contract. A post-commit event/output failure may leave policy
committed; a fresh preview establishes actual state rather than an inferred rollback or blind replay.

No new Permission, public endpoint, authentication scheme, durable event journal or audit protocol is required
merely for the JSON representation. Qualify available tagged package contracts and actual consumer integration
at implementation; retain existing guarantees and hold any unsupported behavior rather than imitate package
internals, patch vendor or call private handlers. Follow the shared architecture contract and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) as amended.

## Acceptance and evidence

- The two approved JSON files supply one complete typed managed policy; superseded catalog literals/duplicate
  membership authority are removed and surviving consumers use the same stable IDs and supported contracts.
- Fresh preview/apply establishes the complete catalog, including all current Project Permissions. User membership
  is exactly the approved three; Super Admin membership covers the entire managed catalog, including future
  valid additions without a second manually expanded Super Admin list.
- Repeat reconciliation preserves unchanged records, memberships and timestamps. Managed drift converges; custom
  Roles/Permissions survive; collisions, incompatible grants and referenced removals retain existing safe failures.
- Invalid structures, duplicate identities/names, unknown references and policy-invalid memberships fail safely
  without partial authority changes. Sensitive data and raw failure internals are not exposed in diagnostics.
- Ordinary invitation success still assigns `ROLE_USER`; elevated/bootstrap behavior and complete-policy admission
  remain compatible. No atomic invitation/assignment guarantee or blanket existing-user backfill is claimed.
- Read-only preview, apply, transaction failure and post-commit uncertainty remain truthful through the existing
  command/bus/package path. Relevant current guidance and prospective plans express the new default while
  historical acceptance/publication evidence remains unchanged.
- Verify owned policy/reconciliation behavior with focused application and PostgreSQL integration evidence;
  validate console/tooling/configuration directly with owning tools. Do not add product-suite tests of JSON file
  text, wrapper/configuration wiring or planning mechanisms. Run ordinary `./bin/build`, distinguish fresh
  checks from qualified reuse and report warnings/limits. Browser/visual evidence is not independently required
  for a catalog-only change; any affected existing user journey still needs appropriate behavioral verification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00591](../tasks/00591-TASK.md) | Move managed authority to JSON catalogs and reconcile approved defaults | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

The human approved this TICKET as the second independent requirement area under EPIC-00025. It amends managed
catalog representation and Super Admin defaults without reopening the completed foundation TICKET as new work.
The three-Permission User baseline and ordinary invitation behavior remain approved existing behavior.

### Approved TASK decomposition

The human approved one cohesive [TASK-00591 — Move managed authority to JSON catalogs and reconcile approved
defaults](../tasks/00591-TASK.md). It owns the catalog, actual reconciliation path, approved defaults, surviving
consumers, current/forward guidance and complete verification in one independently reviewable PR. No blockers or
execution priority are assigned; it is independent of TASK-00590's URL cutover.

Both TICKETs under EPIC-00025 now have their complete approved TASK decompositions written. No implementation or
reconciliation has started from these planning operations. Next use Board-based work selection; existing active
work and independent implementation acceptance obligations remain unchanged.
