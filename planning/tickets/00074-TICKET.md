---
id: TICKET-00074
epic: EPIC-00016
title: Retire only currently unreferenced Features and reintroduce safely
status: ready-for-agent
---

# Retire only currently unreferenced Features and reintroduce safely

## Problem statement

ON does not mean a Feature record is safe to delete, and candidate code without declarations does not prove
that running workers/modules no longer use it. Premature deletion breaks runtime checks; name-based deletion
or reintroduction can accidentally revive an old audience or remove a newly created identity.

## Solution and boundaries

Supply an authorized browser/server retirement journey using package `RemoveFeature` only after a human-approved
**separate cleanup chore** has removed all source declarations, explicit registrations and runtime checks, and
that removal is deployed. Identify all actual currently running versions/modules, including overlap, and obtain
fresh complete **CURRENT** discovery via [TICKET-00070](00070-TICKET.md). Refuse deletion while any reference
remains regardless of status, including OFF/ON. Discovery failure, partial/wrong-scope/stale inventory or deployment
uncertainty holds safely; no substitution of candidate absence. Protect the code identity/current-inventory
observation through the qualified consumer deployment policy, without inventing a package cross-deployment lock
or asserting that a prior scan fences arbitrary later activations.

After current absence is established, a separately authorized administrator confirms removal by stable Feature
ID and current positive expected revision. Package removal compares the *entire* expected tuple at the final
write boundary under the shared transaction/reference fence; removal and Permission-reference release commit
or roll back together. Do not delete the Permission or its grants. Show truthful referenced/stale/held/unknown/
uncertain outcomes, refetch and reconfirm instead of blindly retrying. A broken binding does not require
fabricated repair before otherwise safe retirement. Later authorized provisioning of the same name gets
a **new identity**, OFF with the current existing selected/default Permission, not resurrected ON/PREVIEW or
the old audience; old commands cannot mutate/remove the new identity.

This TICKET implements the reusable retirement capability and defines its operational prerequisites. It does
not automatically create a cleanup TASK on ON, select a fake product Feature, force-delete references, clean
source during a management request, bypass deploy approval or create a permanent name-reservation ledger.
An actual Feature's later cleanup chore belongs to its actual owner when it becomes permanent.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Establish current absence | Human-authorized owner cleanup/deploy via separate chore, not the Feature API | Fresh complete CURRENT inventory over actual running code/modules and all explicit checks | Existing owning cleanup/deploy facts; no Feature removal fact yet | References removed and deployed under owner policy, or visible safe hold |
| Retire approved record | Authorized package `RemoveFeature` with stable ID/positive revision after confirmation | Current target and complete CURRENT references at package boundary | Package `FeatureRemoved` after confirmed commit | Record and its Permission reference removed atomically; grants/Permission preserved |
| Reintroduce name later | Existing separately authorized `ProvisionFeatures` | Fresh candidate and authoritative absence/default or selected existing Permission | New package `FeatureCreated` for new OFF identity | New state starts OFF; stale old ID commands cannot touch it |

## Validation and permissions

Use the distinct current Feature-management Permission plus qualified cleanup/current-scope and target/intent
authority for privileged retirement on every HTTP, CLI, worker and direct-bus path. Testing Permission and ON
status are not removal rights. UI confirmation occurs only for an actual current candidate; stale state,
unknown ID, deployment overlap, incomplete/current-reference presence or a final expected-state mismatch must
not be silently repaired or forced. Permission removal/policy reconciliation remains reference-fenced through
Feature deletion commit, including rollback. Lost commit acknowledgement/post-commit publication failure is
not rollback proof; refetch cannot retarget deletion by name to a new ID. Safe diagnostics never expose
arbitrary scanner/SQL/exception details.

## Acceptance and evidence

- Actual scanner/current-deployment qualification covers both native declarations and explicit registration,
  retained checks, failed/incomplete/malformed/wrong-scope inventories and overlapping running versions; every
  referenced status refuses removal. A truly complete empty CURRENT inventory can permit it.
- PostgreSQL races prove full-state final comparison, concurrent status/rebind versus remove, atomic reference
  release and rollback, remaining Role/Agent/Feature references blocking subsequent Permission removal, and
  safe unknown-versus-uncertain outcomes.
- Owned composed lifecycle tests demonstrate retirement/reintroduction as a new OFF identity, preservation of
  new choices and rejection of old commands, without adding a fake Feature or proof-only project to the product.
- Accessible admin confirmation/refusal/recovery and directly qualified deploy/scanner/DB tools plus owned
  tests and `./bin/build` pass with exact tagged/source/lock and limitations recorded. Package fixture retirement
  receipts are not actual current-code absence or consumer deployment evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00298](../tasks/00298-TASK.md) | Retire Feature settings safely after deployed reference removal | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 5 of EPIC-00016's five-TICKET split. Depends on
[TICKET-00070](00070-TICKET.md)'s exact CURRENT discovery/reference fences and
[TICKET-00073](00073-TICKET.md)'s management authority/confirmation; respects
[TICKET-00071](00071-TICKET.md)'s supported runtime registrations. The first real Feature is now
`ai-template-proposals` under TICKET-00077; its later retirement still needs a separate owner-approved cleanup
and deployed CURRENT absence, not premature removal to demonstrate this capability. Manual-create UI/API is
deferred; selected reintroduction uses protected provisioning, without claiming package `CreateFeature` consumer
qualification. No cleanup chore, deployment or removal was performed by decomposition.

TICKET-00070's approved TASK-00290 now supplies reusable exact-scope discovery; TASK-00291 supplies shared final
expected-state/reference-write integrity and safe missing-OFF provisioning. This TICKET retains actual complete
CURRENT running-version/deployment-overlap qualification and protection of its observation, separate cleanup/
deploy authority, guarded removal and the true retirement/reintroduction lifecycle. Unknown current coverage
holds even when local scanning works. TASK-00292's candidate preparation is not retirement evidence. No finished
retirement UI or operational deployment journey reverse-blocks these primitives; their reusable implementation
and local receipts alone cannot authorize removing a live Feature.

TICKET-00071 now maps TASK-00293's native HTTP declarations and TASK-00294's registered non-Action/Agent
checks. Actual later cleanup must remove every applicable declaration, explicit registration and runtime check,
not just a route Attribute or Tool-list entry. Those gates do not qualify complete CURRENT absence, deploy the
cleanup or revoke already-admitted work; this TICKET retains that separate operational/retirement boundary.

TICKET-00072's TASK-00295/00296 now supply complete catalog observation and volatile browser invalidation.
Qualify actual removal/reintroduction and uncertain-outcome invalidation through those owners, preserving stable-ID
change, honest absence and current server evaluation. Do not patch browser availability from an event or reuse a
retired identity; affected data/late results must be fenced. These catalog primitives do not establish deployed
CURRENT absence or authorize retirement, and neither waits for this completed cleanup journey.

TICKET-00073 now maps [TASK-00297](../tasks/00297-TASK.md)'s complete management authority/detail/confirmation
and mutation invalidation path, after TASK-00036/TASK-00296. Consume its approved `MANAGE_FEATURES` ADMIN_SAFE
policy rather than duplicating authorization. Its trusted rollout/active-repair holds are not retirement evidence;
this TICKET still owns actual CURRENT absence and protection of that observation. No cleanup TASK or removal is
authorized merely because `ai-template-proposals` has been selected.

### Approved TASK decomposition

[TASK-00298](../tasks/00298-TASK.md) supplies one complete retirement browser/server path after TASK-00297.
Its dependency chain supplies discovery, shared persistence, management authority and catalog invalidation;
no separate scanning, confirmation, reintroduction or proof-only TASK is needed. Reuse package `RemoveFeature`
with stable ID/positive revision, complete CURRENT discovery, final full-state comparison and atomic reference
release. Preserve Permissions/grants, permit otherwise-safe broken-binding retirement, and retain truthful
conflict/unknown/uncertain outcomes with no blind replay or same-name retargeting.

The TASK is an unranked feature with status `needs-info`. Actual running-version/module coverage and protection
against conflicting activation between discovery and deletion remain unqualified. A scope enum, local scan,
candidate absence, database reference fence or prepared result cannot supply that deployment guarantee. Name and
qualify the actual trusted participants/policy; unknown support holds without a new deployment platform, vendor
patch, nested transaction or invented blocker ID.

Later reintroduction uses existing protected provisioning: new identity, OFF, revision 1, current existing default
Permission and no resurrection of old settings. Lifecycle evidence belongs inside this TASK; it does not certify
deferred manual `CreateFeature` exposure. Actual source cleanup/deployment still requires a separate future
product-owner decision and authority. No cleanup TASK for `ai-template-proposals` is created now, and retirement
support does not block that product's initial shipping or the first terminal Awaiting review milestone.

All five EPIC-00016 TICKETs now have approved TASK decompositions, not implementation acceptance. With
TICKET-00075, seventeen of the map's 24 are decomposed; seven remain, TICKET-00076–00082, alongside older
incomplete Harness/managed review planning. Active work and priorities remain unchanged.
Next planning operation: `/skill:to-tasks TICKET-00076`.

Planning verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with
398 records and 311 active. `./bin/build` passed: 459 tests, 3499 assertions, 78 captures, zero violations.
Evidence: `.runs/build/20261013T025411Z-70103`; log: `.runs/build/ticket-00074-to-tasks.log`.
Sass deprecations, React-Bootstrap `use client` and >500 kB bundle warnings remain. These are fresh planning/
existing-repository checks, not implementation, CURRENT-deployment qualification or actual retirement evidence.
Independent review and publication remain unperformed; no code cleanup, deployment, deletion or reintroduction ran.
