# Owned active sessions

TASK-00057 implements read-only `GET /api/v1/me/sessions` and `/app/account/sessions`.
Independent Review and behavioral QA remain separate. Individual revocation belongs to TASK-00058.

## Authority and wire contract

Bearer authentication creates one request-scoped package `CurrentPrincipalProvider`. Both the early HTTP
attribute and `ReadOwnedSessions` require exact authoritative `READ_OWN_SESSIONS`; a Role name or administrative
permission is not a substitute. Application derives actor, target and current refresh-session ID exclusively
from that same principal. Non-HTTP callers must supply an authoritative provider too. The configured query bus
dispatches package `ListActiveSessions`; its handler, repository and clock own selection and `SessionView` creation.
No package handler is invoked privately and no sidecar lookup can establish authority.

The only request fields are optional query `page` (1–1000, default 1) and `per_page` (1–100, default 25), using
the source-only QueryString/Validation convention. Decoded numeric encoding is accepted; duplicate decoded keys,
arrays, unknown selectors and GET bodies are rejected before dispatch. Actor/user/current IDs, sorting,
administrative options and internal fields cannot be selected. The repository fixes `created_at ASC, id ASC`
at persisted precision. Offset pages describe committed reads, not a cross-request snapshot; concurrent changes
can move rows or make a page empty. The current marker occurs only on the page containing that session, not
once per page. Both idle and absolute equality are expired; revoked sessions are absent.

JSend success data contains `page`, `per_page`, `total_pages`, `total_records`, `records`. Each explicit record
contains only `session_id`, `browser_family`, `device_class`, `created_at`, `last_activity_at`, `idle_expires_at`,
`absolute_expires_at`, `remembered`, `current`. All timestamps are UTC seconds with `+00:00`; ordering still uses
persisted subsecond precision, so clients must not reconstruct a UUID tie-break from displayed timestamps.
Activity is creation or successful refresh rotation, never page views or online presence. `Cache-Control: no-store`
is mandatory. OpenAPI is attribute-owned; re-export explicitly after source contract changes.

The View deliberately omits owner identity, credentials/digests/history, authentication version/revision,
revoked/audit/provider state, cookies, raw User-Agent/IP, location and fingerprints. Stable session identity is
presentation data for the later revocation use case, never a credential.

## Original coarse presentation

`session_presentation` has a unique foreign-key `session_id` with delete cascade, closed `browser_family` and
`device_class`, and the original absolute `expires_at` retention deadline. Migration `Version20261025224641`
was generated using Doctrine's owning command. There is no raw header, IP, browser/OS version or tracking field.

After successful browser activation/login, `CaptureSessionPresentation` parses at most **512 printable ASCII
bytes** of one User-Agent header locally. Missing, multiple, oversized or control/non-ASCII input maps to unknown.
Browser values are exactly Chrome, Edge, Firefox, Safari, Unknown; device classes are desktop, mobile, tablet,
unknown. Fixed recognizers prioritize Edge and Firefox before Chrome and Safari; unrecognized families remain
unknown, including headless/other browser identifiers. These are untrusted coarse hints, not device identification.
No enrichment, OS storage, fingerprint, network metadata or later activity tracking is performed.

Capture uses insert-on-conflict-do-nothing after package session creation has committed. Storage failures are
suppressed without copying request/credential material to logs; enclosing writes use a savepoint. Missing rows
and metadata read failures produce fixed unknown descriptors. Authoritative query failures are not converted
into successful empty results. Refresh never calls capture and never rewrites the descriptor or its retention
ceiling. It updates only package-owned rotation/activity/idle state. Client text is rendered normally by React,
not as markup, and all presentation strings are closed enums or fixed labels.

### Retention

Run the explicit trusted command periodically, for example daily, using the intended stack's Compose environment:

```sh
./bin/console session-presentation:prune --internal --limit=100
```

Each invocation deletes at most 1–1000 presentation rows (default 100), ordered by retention deadline and stable
ID, whose deadline has passed or whose authoritative session is absent, revoked, idle-expired or absolute-expired.
It never modifies session rows, credentials, lifetimes or authority. Repeat bounded invocations when a backlog
exists; failure is a safe nonzero exit and explicit retry. FK cascade also removes metadata when a session is
physically deleted. No automatic scheduler is enrolled by this TASK: operators own periodic invocation and
monitoring. Missing metadata after cleanup remains harmless unknown presentation.

## Client behavior and verification

`OwnedSessionsService` strictly decodes a fixed protected path through the shared ApiClient and memory-token owner.
It validates exact fields, enums, UUIDs, UTC instants, lifetimes, page/count consistency, duplicate IDs and competing
current markers. No rows, tokens or authority are persisted. The page alone holds volatile rows, captures the
sole AuthorityCache scope, aborts and clears rows on retirement, and fences late success/failure/unmount results.
A server 401 asks the existing coordinator for bounded restoration; 403 revalidates the sole authority cache.
The service retains only a safe refusal category until explicit user retry, preventing guard remounts from
replaying a denied read. It is not a second authority cache. The page preserves forbidden/error outcomes and
never retries automatically.

One semantic card list reflows from narrow stacked cards to a wide grid, without competing accessibility trees.
Current state has text plus a border, timestamps have labels and UTC, session types are explained, and loading,
empty, failures and pagination have status feedback and explicit controls. Native controls retain shared 44px
targets, focus and reduced-motion/theme rules; keyboard pagination/retry hands focus to the page heading. The
frame still owns navigation, appearance and current-session Sign Out; this page offers no revocation mutation.

Behavior tests cover parser/capture failure, safe View/UTC, non-HTTP permission/ownership/bounds, real package
and PostgreSQL selection, HTTP query rejection and redaction, activation/login capture and refresh preservation,
strict client decoding, denied-read replay regression, scope retirement, keyboard focus and page states.
The production-backed catalog adds eight owned-session examples. Owning tools validate migrations, OpenAPI,
architecture, dependencies, static analysis and the complete `./bin/build`; product tests do not test those tools.
See TASK-00057's completion evidence and ignored Work handoff for exact receipts and limitations. Automated axe,
Chromium and inspected captures are not screen-reader, other-engine, physical-device or universal WCAG certification.
