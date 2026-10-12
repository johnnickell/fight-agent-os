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
fabricated repair before otherwise safe retirement. A later authorized provision/create of the same name gets
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
| Reintroduce name later | Ordinary separately authorized `ProvisionFeatures` or `CreateFeature` | Fresh candidate and authoritative absence/default or selected existing Permission | New package `FeatureCreated` for new OFF identity | New state starts OFF; stale old ID commands cannot touch it |

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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 5 of EPIC-00016's five-TICKET split. Depends on
[TICKET-00070](00070-TICKET.md)'s exact CURRENT discovery/reference fences and
[TICKET-00073](00073-TICKET.md)'s management authority/confirmation; respects
[TICKET-00071](00071-TICKET.md)'s supported runtime registrations. The first live flagged capability remains
a later explicit owner choice, not an EPIC completion gate. No cleanup chore, deployment or removal was
performed by decomposition.

TICKET-00070's approved TASK-00290 now supplies reusable exact-scope discovery; TASK-00291 supplies shared final
expected-state/reference-write integrity and safe missing-OFF provisioning. This TICKET retains actual complete
CURRENT running-version/deployment-overlap qualification and protection of its observation, separate cleanup/
deploy authority, guarded removal and the true retirement/reintroduction lifecycle. Unknown current coverage
holds even when local scanning works. TASK-00292's candidate preparation is not retirement evidence. No finished
retirement UI or operational deployment journey reverse-blocks these primitives; their reusable implementation
and local receipts alone cannot authorize removing a live Feature.
