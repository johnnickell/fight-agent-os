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
| [TASK-00295](../tasks/00295-TASK.md) | Read a complete authenticated Feature catalog | ready-for-agent |
| [TASK-00296](../tasks/00296-TASK.md) | Gate browser Feature presentation with current catalog availability | ready-for-agent |
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

### Approved TASK decomposition

The maintainer approved two independently reviewable paths:

| TASK | Outcome | Blockers |
|---|---|---|
| [TASK-00295](../tasks/00295-TASK.md) | Read a complete authenticated Feature catalog | TASK-00293 |
| [TASK-00296](../tasks/00296-TASK.md) | Gate browser Feature presentation with current catalog availability | TASK-00295, TASK-00051 |

Both are unranked features, ready behind listed blockers. TASK-00294's Agent runtime is not a prerequisite.
TASK-00295 owns the real `GET /api/v1/features` read for all currently authenticated Users, without Dashboard,
testing or management Permission requirements. It composes package `ListFeatures` and `FeatureAvailability`
through a justified complete-catalog Application operation, not another evaluator, renamed query or public gate.

The approved protocol is one bounded complete response, not browser-managed paging. Return explicit complete/count
metadata only after buffering and validating every internal page and item. Include OFF and safe Feature identity,
name, status, original testing Permission ID/nullable name, missing-binding marker and server Boolean. Do not
serialize the management View wholesale or expose its edit revision/private internals. No caller-selected actor,
filter, sort, page or binding is accepted. Choose and qualify finite row/page/query/byte/time/lock-wait limits before
exposure; overflow or failure produces a safe error, not a success prefix, truncation or an empty substitute.

Inspected `PostgresFeatureRepository::getAll()` fetches rows and count separately; the package handler then resolves
Permissions and availability rereads settings/bindings. Stable counts/digests alone cannot prove complete enumeration
under concurrent insert/delete. TASK-00295 owns a bounded coherent read boundary through existing canonical
connection/reference ownership, including actual Feature/Permission writers and cleanup after failures. The current
Permission add path does not acquire the fence used by replace/remove; do not assume complete writer participation.
Preserve READ COMMITTED/fresh-per-call semantics without a stale snapshot, hidden transaction, new global epoch or
cross-request held cursor. This is an unqualified catalog guarantee, not a reproduced storage defect.

A narrowly established missing binding may retain the original ID, null name and repair marker with false
availability; mismatched IDs, invalid reconstruction, vanished/reintroduced identities, outages and indeterminate
completeness fail the read. Never blanket-map arbitrary exceptions to false, invent a same-named replacement or
repair state on GET. An observation uses the admitted immutable principal, not a new grant or an atomic snapshot
with a separate `/me` request. Actual runtime operations still enforce current authority independently.

TASK-00296 mounts the production catalog service in the authenticated frame and keeps its private server data
within the existing volatile context/generation lifecycle. `/me` remains the sole ordinary-Permission/principal
source. Decode complete responses atomically, combine server availability with the existing exact Permission
evaluator, and guard outlets/loaders, navigation, controls/forms and activation callbacks. Missing required Features
fail closed; unflagged/public paths retain their own semantics without a new private-catalog prerequisite.

Freshness is at most 60 monotonic seconds from request start and cannot outlive current `/me` authority. Expiry,
resume/clock discontinuity, refresh, logout, context/authority or Feature invalidation removes usable presentation
and dependent private data, fences old successes/errors and permits only bounded current-context refetch/retry.
A Feature-only invalidation must also retire its dependent data/drafts even if the principal scope is unchanged.
Safe intended-route restoration rechecks both authorities. Cross-tab signals carry bounded invalidation intent,
not settings/availability/identity; no new push bus, persistence, instant consistency or mutation replay is promised.

TICKET-00073 consumes the single catalog and owns management UI, protected detail/repair and actual mutation
invalidation; TICKET-00074 owns actual removal/reintroduction integration. They refresh affected evidence without
reverse-blocking these primitives. No dummy Feature/flagged product route, duplicate management page or final
proof-only TASK is introduced. Controlled component/contract fixtures are not live rollout evidence. Each slice
owns actual package/PostgreSQL/HTTP or browser/accessibility behavior and `./bin/build`; tooling/configuration is
validated directly. Confirmed bugs need reproducing failing regressions before repair.

Active TASK-00138/TASK-00226 and existing priorities remain unchanged. With TICKET-00073/00074, all five EPIC-00016 TICKETs
have approved written TASK splits, not implementation acceptance. Sixteen of the map's 24 are decomposed;
**eight remain, TICKET-00075–00082**, alongside older incomplete Harness/managed review planning.
Next planning operation: `/skill:to-tasks TICKET-00075`.
Planning exposed no endpoint, fetched no catalog, changed no UI and enabled no Feature. Implementation/worktree
choice, independent review, publication and runtime/deployment effects remain separately authorized.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 396 records,
309 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations. Repetitive Sass deprecations, React-Bootstrap `use client` bundling and >500 kB chunk warnings remain.
Evidence: `.runs/build/20261013T015846Z-63763`; summary log: `.runs/build/ticket-00072-to-tasks.log`.
These validate planning and the existing repository, not future catalog consistency, authenticated endpoint,
browser authority integration or live Feature use. Independent review and publication remain unperformed.
