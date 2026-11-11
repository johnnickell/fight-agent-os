---
id: TICKET-00138
epic: EPIC-00025
title: Serve browser journeys from root URLs
status: done
---

# Serve browser journeys from root URLs

## Problem statement

The browser currently mixes root entry with `/app` destinations. Hardcoded paths extend beyond React routing
into credential intake, generated email links, strict security configuration, local runtime setup, tests and
unfinished plans. A route-only replacement would leave broken journeys and inconsistent security boundaries.

## Solution and boundaries

Deliver the complete root-URL cutover approved in [EPIC-00025](../epics/00025-EPIC.md):

- Make `/` the canonical existing permission-aware Dashboard. Unauthenticated visitors go to `/login`;
  authenticated visitors without `VIEW_DASHBOARD` retain Forbidden behavior. Preserve safe restoration,
  admitted post-login navigation intent, independently permitted shortcuts and existing unavailable-content copy.
- Remove the browser `/app` prefix from existing public, account, administration and Swagger destinations,
  route lookups and links, without redirects or compatibility aliases. Use `/activate`, `/login`,
  `/reset-password` and `/reset-password/complete`. The Dashboard's canonical destination is `/`, not a
  requirement to retain a second `/dashboard` alias.
- Serve React as the final browser GET fallback after server-owned routes and asset handling. Direct entry
  and refresh work on registered destinations, while unknown browser destinations show meaningful Not Found.
  API/MCP failures, missing assets and unsupported methods must not become successful shell responses.
- Keep `/api/v1` unchanged and reserve `/mcp/v1` for future MCP. Do not implement MCP or alter its separate
  authentication/admission contract.
- Update activation/reset email generation, fragment capture and early URL clearing, trusted-origin/exact-path
  checks, environment examples, isolated-runtime preparation and synthetic-mail QA extraction together.
- Update current documentation and **unfinished forward planning requirements** that still prescribe browser
  `/app/...` URLs or unversioned `/mcp`. Amend applicable prospective requirements in their owning records;
  do not rewrite completed implementation/acceptance records, historical decisions, receipts or publication
  evidence. Preserve unrelated requirements, priority, dependencies and qualification holds. Validate remaining
  matches and explain historical or non-URL exceptions rather than applying a blind repository replacement.
- Determine configuration/operator and retained credential-delivery material obligations before implementation
  cutover. Preserve needed durable identities and invitation/reset recovery state; no data disposal is authorized.

Initial source orientation is recorded in the EPIC. Recheck the live inventory, including frame/brand and page
links, intended-route admission and credential prepaint handling. Container filesystem `/app` paths, mounts,
workdirs, filenames and import aliases are not browser URLs and must not change merely because they match text.

Excluded: Dashboard feature expansion, legacy redirects/aliases, API version changes, MCP implementation,
filesystem moves, invitation transaction redesign, production deployment and speculative routing machinery.
The sibling [TICKET-00139](00139-TICKET.md) owns the managed authority catalog update. Neither TICKET has a
prerequisite dependency on the other; this URL cutover uses existing Permission semantics.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Enter or restore the application at `/` | No new business command; reuse existing login/refresh/logout operations | Existing sole principal cache and `/api/v1/me` authority read | No new event; existing authentication events remain unchanged | Safe session restoration, login redirect or permitted Dashboard presentation; no redirect loop |
| Navigate or refresh a protected destination | No new business command; existing destination operations remain unchanged | Existing route metadata, current authority and destination reads | No new routing event | Root-level location and preserved exact permission admission; unauthorized content stays inaccessible |
| Activate or complete password recovery from email | Existing sensitive package operations remain synchronous under their supported contracts | Existing credential/form metadata and admission reads | Existing lifecycle/session/delivery events only | Newly generated root links reach the correct intake; fragments are cleared early and no secrets reach diagnostics |
| Resolve server-owned or unknown paths | N/A: HTTP dispatch is not a business mutation | Existing server dispatch/asset resolution | N/A: route matching needs no Domain event | Browser fallback only where intended; API/MCP/assets/method errors retain their owning response behavior |
| Keep current guidance and unfinished plans usable | N/A: authored documentation/planning updates, not runtime Commands | Bounded live URL-reference inventory | N/A: planning changes need no runtime event | Current and future consumers prescribe the new URLs without altering historical evidence |

## Validation and permissions

Preserve server-enforced exact operation permissions, Swagger environment restrictions and current principal
ownership. Route reachability is not authorization. Keep `VIEW_DASHBOARD` admission and existing Forbidden
behavior, not role-name bypasses or redirects that conceal denial.

Update intended-route grammar and registered-destination admission without admitting arbitrary same-origin or
external URLs, unsafe query values or credential fragments. Retain fragment-only credential transport, early
URL sanitization, trusted HTTPS origins, exact canonical path validation, CSRF/request controls and redaction.
Renaming paths is not permission to weaken configuration admission or expose API/OpenAPI data through fallback.

The dispatch decision must distinguish reserved server namespaces and assets, including missing endpoints, from
browser navigation. No new business command, event, permission or database migration is inherently required for
URL matching; preserve the existing operations behind renamed destinations.

## Acceptance and evidence

- Anonymous `/` enters `/login`; authenticated permitted `/` shows the existing Dashboard. A principal lacking
  `VIEW_DASHBOARD` receives Forbidden without login loops or unauthorized content.
- Direct entry, refresh, internal links, brand/account navigation, logout and safe post-login intent use canonical
  root destinations across existing public and protected journeys. Old `/app` locations are not supported aliases.
- Generated activation/reset links and actual pre-render browser credential capture agree with strict runtime
  configuration. Fresh local delivery/QA journeys work without leaked credential material; operator obligations
  for old configuration or retained delivery work are explicit and preserve needed data.
- `/api/v1` remains functional. Reserved API/MCP misses, missing assets and unsupported server methods do not
  receive a successful React shell; unknown browser paths retain Not Found presentation.
- A bounded final URL inventory accounts for surviving code/tool/config/current-document references and unfinished
  forward plans. Historical evidence and unrelated filesystem paths remain intact. Updated planning remains
  offline-valid without removing other work's requirements, ordering, dependencies or qualification holds.
- Provide useful application regression/integration and real browser evidence for routing, permissions, restored
  sessions and credential journeys. Verify tooling/configuration/docs with owning tools rather than adding
  product-suite tests of file text, wrappers or planning mechanisms. Run ordinary `./bin/build`; distinguish
  fresh checks from qualified reuse and disclose warnings or incomplete verification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00590](../tasks/00590-TASK.md) | Cut over browser journeys and forward plans to root URLs | done |
<!-- /planning:children -->

## Decisions and progress

The human approved this TICKET as the first of two independent requirement areas under EPIC-00025 and explicitly
confirmed that unfinished forward planning documentation is included. The EPIC's URL decisions and exclusions
remain authoritative; existing capability owners retain their business behavior and historical evidence.

### Approved TASK decomposition

The human approved one cohesive implementation [TASK-00590 — Cut over browser journeys and forward plans to
root URLs](../tasks/00590-TASK.md). It owns the complete route, credential, configuration, tooling,
documentation/unfinished-plan and verification cutover in one independently reviewable PR, avoiding a broken
route-only intermediate state. There are no identified blockers or assigned execution priority and no dependency
on TICKET-00139's authority change.

This TICKET's TASK decomposition was approved and written in full. TICKET-00139's independent decomposition
was subsequently approved and written as [TASK-00591](../tasks/00591-TASK.md), completing the EPIC's planning
phase without changing existing active work or priority. No runtime configuration or authority data was changed
by those planning operations.

### Current implementation handoff

The sole child [TASK-00590](../tasks/00590-TASK.md#accepted-implementation-and-administrative-closeout--2026-11-10)
now records independently accepted complete root-URL implementation and applicable behavioral/browser QA.
This TICKET closes through its child; historical planning and Work checkpoints remain evidence. Actual
publication/final-head and cleanup results belong to the child's canonical Land receipt and PR, not a claim
of human merge, production cutover or discarded credential recovery material. Use the Board for subsequent
work selection; TASK-00589's active authority and priority remain independent.
