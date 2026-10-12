---
id: TICKET-00072
epic: EPIC-00016
title: Show a complete authenticated Feature catalog and availability
status: ready-for-agent
---

# Show a complete authenticated Feature catalog and availability

## Problem statement

A rollout screen alone cannot tell ordinary signed-in users which Features to present. Conversely, deriving
PREVIEW eligibility from browser Permission **names** would confuse them with the Feature's stored bound
Permission **ID** and allow stale or incomplete catalogs to masquerade as availability.

## Solution and boundaries

Provide **one authenticated read-only Feature catalog endpoint** for all signed-in Users, returning **every**
Feature, including OFF, with safe immutable name, current status, bound testing Permission ID/name where
available and server-computed `available` for that request's authoritative principal. Compose the package
`ListFeatures` safe Views with `FeatureAvailability`; a broken binding may be shown with an explicit safe repair
marker and `available: false` without fabricating another Permission, while operational failure is an error,
not an empty or successful catalog. Choose bounded pagination or a bounded complete-response protocol that
allows the client to verify complete enumeration (including changes during paging); a first page silently
presented as the whole catalog is unacceptable. Empty *confirmed complete* catalog is a valid fresh install.
Never expose hidden credential or management internals in this View.

The frontend uses `available` to hide/gate relevant routes, navigation, controls and forms, **together** with
the planned `/api/v1/me` current Permission **names** for ordinary Action rights. A tester in PREVIEW sees a
complete capability only where both checks allow it; ineligible users do not see its controls. Refetch on
Feature setting/binding or principal authority changes using the existing single volatile authority context,
bounded cache generation/freshness and protected-data invalidation. Unknown, stale, incomplete or failed
reads suppress feature presentation; no local name-to-ID approximation or persisted/broadcast Feature authority.
Every actual operation still performs TICKET-00071's current server check and ordinary authorization.

No anonymous or public catalog, duplicate `/me` principal store, client-side security decision, Feature
management write, token/role permission snapshot in HTML or API response, or dummy Feature/flagged route is
created for the read endpoint. Agent tools evaluate their own authenticated principal at the server boundary;
this human browser catalog does not authenticate Agents.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Read complete catalog | N/A — authenticated read-only request | Package `ListFeatures` over complete bounded pages and `FeatureAvailability` for current User | N/A — reads do not publish business facts | Safe full catalog including OFF with current per-user Boolean; no mutation |
| Repair visibility after change | N/A — refetch, not grant | Current Feature/binding and `/me` Permission-name projection | Client invalidation signal only, not package event | Stale or incomplete presentation fails closed until both current reads succeed |
| View a broken binding | N/A — read does not repair | Safe package `FeatureView` with `binding_missing` and qualified known binding failure | N/A | Authorized operator can diagnose, ordinary client does not mark it available |

## Validation and permissions

Require active current User authentication for every page/read; all signed-in Users may inspect safe catalog
state under the EPIC's explicit disclosure approval, while management still needs a **distinct** Permission.
Reject unsigned/expired authority, unbounded/ambiguous pagination, missing/mismatched IDs and unreadable
response shapes. Bind availability by stored ID server-side, not same-named replacement; fresh package reads
must see committed settings. Recoverable known broken binding may fail just that Feature closed with a safe
repair marker; a database outage, invalid repository state or indeterminate completeness fails the read rather
than presenting empty/available. `Cache-Control: no-store` and current-context fencing protect private per-user
responses; client guarding never grants server access.

## Acceptance and evidence

- Signed-in ordinary and Super Admin Users receive the same complete safe Feature identities/status/bindings
  with different server-computed availability as appropriate; anonymous receives no catalog. An honestly empty
  initial catalog is complete without inventing a Feature.
- Multi-page, changing-catalog and broken-binding scenarios either produce a provably complete current result
  or a safe incomplete/error state; a stale Permission name or missing ID never yields true availability.
- Browser routes/controls fail closed during reload, revocation, stale/incomplete/outage states and reconcile
  after fresh `/me`/Feature reads; direct requests still meet TICKET-00071's independent server gate.
- Owned PostgreSQL/HTTP/client and accessible presentation checks plus `./bin/build` pass with exact chosen
  package/source/lock evidence; package fixtures alone do not establish a consumer browser journey.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00016's five-TICKET split. Uses
[TICKET-00070](00070-TICKET.md)'s catalog consistency and
[TICKET-00071](00071-TICKET.md)'s current availability, with the sole `/me` owner
[TICKET-00018](00018-TICKET.md) and [ADR 0004](../adr/0004-client-authority-and-runtime-state.md).
This broad authenticated **read** is an explicit extension of WF-032's admin-only management reads, not
permission to mutate. No catalog endpoint or client gate was created during planning.

TICKET-00071's accepted TASK-00293 now supplies qualified human HTTP/package availability and fresh-read
semantics; consume it without an unrelated dependency on TASK-00294's Agent/non-Action integration. The package
rereads settings/bindings on every call but uses the immutable request principal. This TICKET owns complete
catalog composition, safe repair presentation and browser invalidation, not another evaluator, principal source
or runtime authorization owner. Operational failures cannot become empty catalogs or policy false.
