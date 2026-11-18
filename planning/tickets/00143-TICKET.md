---
id: TICKET-00143
epic: EPIC-00027
title: Browse shared stateful tables with restorable URL queries
status: ready-for-agent
---

# Browse shared stateful tables with restorable URL queries

## Problem statement

Users, Roles and Permissions need consistent list controls and shareable navigation. The current Users page
only paginates, and sorting/filtering a fetched page would misrepresent the complete authorized result set.

## Solution and boundaries

### Table owns interactions; the page owns navigation and data

Deliver a wrapping shared table that owns current page, page size, sort field/direction, committed search and
filters. Compose nested pagination, page-size selection, page X of N display and optional search components
through the table's own context. Expose typed row/column definitions, stable row identity, cell/action composition,
loading/error/empty states and normalized change callbacks. Names such as `DataTable`, `Pagination`, `PageSummary`
and `Search` describe responsibilities; final export naming belongs to implementation.

The page supplies initial state parsed from its URL, supported columns/search/filter policy, rows and authoritative
total count. User interactions update the table once and emit the complete normalized state plus a change reason
to the page. The page mirrors meaningful state to history and performs the authorized API read. The table does
not import a router, read global location, fetch data, write storage or create another session/authority owner.

Provide an explicit external restoration input for URL navigation, with an identity/revision or equivalent
supported mechanism. Initial mount, Back/Forward, programmatic navigation and server page correction restore
table state without firing another user-change callback or writing another history entry. Do not make the page
continually control every interaction through a second mutable copy. Search can keep an internal draft; commit
after bounded debounce or explicit submission, reset to page 1 and notify once. Cancel pending search work when
restoring navigation/unmounting. Per-page, sort and filter changes also reset to page 1 in the same transition.

### URL and history contract

The page tracks these exact keys:

`page=X&per_page=Y&sort=ID&order=desc&search=gmail&filters=<base64url-json>`

- Page is one-based; use the existing bounded page/per-page limits unless a real owner approves a change.
  Always serialize canonical page, per_page, sort and order. Column identifiers are explicit public values
  such as `ID`, mapped by an allowlist to server fields; never SQL, arbitrary object paths or client authority.
  Order is exactly `asc` or `desc`. One selected sort has a deterministic identity tie-break on the server.
- Search is optional per table/page and disabled for all Access Control features, including Users, Roles and
  Permissions. Omit empty search and empty filters. With search disabled, remove search state during URL
  normalization and never send it to the API; a manually edited URL cannot enable search. Access Control APIs
  do not accept a free-text search contract. Application-specific pages explicitly enable search and own their
  searchable queries and fields. Filter schema is page-owned and typed, not a generic query language; expose
  only filters actually supported by the qualified query. No feature exists merely because a URL carries its key.
- Filters encode a deterministic UTF-8 JSON object as unpadded base64url. Sort object keys for stable output,
  preserve meaningful array order, and use URLSearchParams or equivalent safe URL serialization. Base64url
  does not hide sensitive values. Search text in copied URLs/history is visible; exclude credentials,
  permission snapshots, account mutation inputs and future HMAC secrets from every URL-state path.
- Parse and validate before fetching: bounded numeric values, string lengths, total URL/decoded-filter bytes,
  depth, object keys/types, column/filter allowlists, duplicates and invalid Unicode/base64/JSON. Missing values
  use documented defaults; malformed known state resets safely with an understandable notice and one canonical
  replace, never raw exception text or a crash. Reject unsupported filter fields/operators and prototype keys.
  Preserve unrelated host query parameters without forwarding them to list APIs. Invalid API query input is
  rejected by the server even if a client would normalize it.
- Initial normalization and debounced search replace history. Explicit page/page-size/sort/filter transitions
  push one entry. Back/Forward restores the prior normalized state and fetches once without push/replace loops.
  A corrected out-of-range page replaces the current entry. Define equality on normalized values, not object
  identity, to avoid duplicate notifications/requests.

### Whole-dataset API and data ownership

Apply supported filter/sort on the authorized server dataset before counting/pagination. Application-specific
searchable queries also apply search before counting/pagination; Access Control queries do not provide search.
Return safe typed rows, authoritative totals and bounded pagination
metadata. Counts and data must use identical filtering/admission; they are current observations, not immutable
snapshots across concurrent mutations. Empty results have a clear no-results state and disabled navigation,
never page 1 of 0. Shrinking datasets trigger at most one bounded page correction; continued churn is explicit.

Qualify supported package pagination/orderings and use those contracts for Access Control features. Do not
add free-text search through a package extension, application projection or browser filtering of Users, Roles
or Permissions. Applications implement their own searchable queries with registered handlers, bounded input,
parameterized allowlisted queries and safe typed Views. That application work does not require an Access
Control search capability or a generic library-owned backend. Do not filter a fetched page or scan all pages
to imitate whole-dataset search. The first library release includes working optional search interactions,
callbacks, URL restoration and an application-query integration example, qualified with a representative
consumer fixture. Real domain query implementations belong to each consuming application's named work;
the example is not evidence that those application backends have been implemented.

Extend the closed typed client GET options and HTTP/OpenAPI contracts deliberately; raw arbitrary query strings
remain unsupported. Preserve current READ_USERS/READ_ROLES/READ_PERMISSIONS entry admission, no-store responses
and safe errors. Abort superseded requests and use normalized request/authority identities so late success or
failure cannot replace newer results. Page/search/filter state is never a reusable authorization grant. On
principal change clear old rows and re-admit; permission changes and mutations refetch under current authority.

No bulk actions, export engine, virtualized grid, arbitrary expression language, persistent selection or separate
table state framework. Use semantic accessible tables, aria-sort, labelled controls, keyboard/focus, status
announcements and responsive layouts consistent with React-Bootstrap and the shared theme. No Tailwind.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Page, sort, search or filter | N/A — read-only | Authorized whole-dataset list query | One normalized table callback | Table state changes; page updates URL and rows |
| Reload or navigate history | N/A | Validated URL state and list read | External restoration, no user-change event | Table and page converge without loops |
| Handle denial or late response | N/A | Current session/authority as already owned | Existing invalidation | Stale/unauthorized rows never replace current results |

## Validation and permissions

Validate both URL input and server query input. Page controls are presentation only; APIs independently admit
current permissions and scope. Query values never become SQL identifiers or permission claims. Use safe text
rendering and bounded decoding; do not record raw search/filter values in diagnostics. Table callbacks contain
only list state, never authentication material.

## Acceptance and evidence

- A real Users directory demonstrates nested controls, callbacks, page summaries and restored query state.
- Backend catalog reads support pagination/order and any qualified filters before TASK-00128 mounts its tables.
- Real Access Control data spanning several pages proves global ordering and totals, including stable ties;
  all Access Control screens keep search disabled, including for manually supplied search URL parameters.
- Optional search is delivered in the first library release. A representative application-query consumer
  fixture proves committed search callbacks, page reset, URL restoration and fetching integration without
  adding Access Control search. Receiving applications own whole-dataset search and matching totals.
- Reload, deep links, Back/Forward, malformed/duplicate/oversized parameters, Unicode filters, search-off mode,
  count shrinkage, rapid changes, stale responses and principal retirement behave as specified.
- Keyboard, focus, screen-reader labels/status, reflow and themes are exercised on the real shared component.
- Reuse/strengthen meaningful existing tests; maintain 100% Unit line coverage of owned testable behavior.
  No tests of URL libraries, framework machinery or generated tooling outputs as product behavior.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00597](../tasks/00597-TASK.md) | Browse Users through shared stateful tables and URL-backed queries | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John specified stateful nested controls, page callbacks and these URL keys on 2026-11-17. TASK-00597 delivers
the complete shared-table/Users/API journey; existing TASK-00123/00128 consume it for new administration.
History normalization and security/error details are implementation requirements within that approved outcome,
not a new approval gate. John then clarified that free-text search is for application-specific queries only,
and will not be enabled for Access Control features. The library capability remains required; no Access Control
search implementation or release dependency is planned.
