# Owned active sessions

TASK-00057 implements `GET /api/v1/me/sessions` and `/app/account/sessions`.
TASK-00058 adds confirmed other-owned revocation; its independent Review and behavioral QA remain pending.

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
once per page. Both idle and absolute equality with the package clock are expired at persisted precision;
revoked sessions are absent.

JSend success data contains `page`, `per_page`, `total_pages`, `total_records`, `records`. Each explicit record
contains only `session_id`, `browser_family`, `device_class`, `created_at`, `last_activity_at`, `idle_expires_at`,
`absolute_expires_at`, `remembered`, `current`. All timestamps are UTC seconds with `+00:00`; ordering still uses
persisted subsecond precision, so clients must not reconstruct a UUID tie-break from displayed timestamps.
A usable session refreshed within the final fractional second before its absolute ceiling can have equal public
activity, idle and absolute timestamps. The decoder permits that projected equality but rejects reversed
instants; only the server's precise clock/filter establishes usability, never the browser clock.
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
frame still owns navigation, appearance and current-session Sign Out; the current card also routes to that same Logout operation.

Behavior tests cover parser/capture failure, safe View/UTC, non-HTTP permission/ownership/bounds, real package
and PostgreSQL selection, HTTP query rejection and redaction, activation/login capture and refresh preservation,
strict client decoding, denied-read replay regression, scope retirement, keyboard focus and page states.
The production-backed catalog adds eight owned-session examples. Owning tools validate migrations, OpenAPI,
architecture, dependencies, static analysis and the complete `./bin/build`; product tests do not test those tools.
See TASK-00057's completion evidence and ignored Work handoff for exact receipts and limitations. Automated axe,
Chromium and inspected captures are not screen-reader, other-engine, physical-device or universal WCAG certification.

## Confirmed other-owned revocation (TASK-00058)

`DELETE /api/v1/me/sessions/{sessionId}` accepts a lowercase canonical UUID and exactly an empty JSON object
(`{}`), with no query parameters. Bearer authentication and exact current `DELETE_OWN_SESSIONS` are required
both at the early route gate and in `RevokeOwnedSession`. The sole request-scoped principal supplies actor and
current session; no body identity, reason, Role or administrative permission is accepted. The configured command
bus dispatches package `RevokeSession(actor, current, target, null)`. A dedicated `DenySessionAdministration`
callback is composed into that handler, including trusted raw dispatch: all cross-user management is denied,
regardless of `DELETE_SESSIONS`, Super Admin or a supplied reason. The pre-existing read callback is unchanged.

The qualified latest tag is Fight Access Control v0.8.0, source/Composer lock reference
`319738255e682d587642b67856039f882b9588d6`; no dependency update was needed. The package owns usability,
current-session exclusion, ownership, optimistic target replacement and post-commit `RefreshSessionRevoked`.
Agent OS does not update session flags in this use case. Only target revision/revoked state changes; credentials,
digests and other sessions are unchanged. Events are synchronous post-commit notifications, not durable delivery.
Unexpected notification/storage failure is an uncertain sanitized failure, not permission to replay a mutation.

| Outcome | HTTP and body |
|---|---|
| Target committed revoked | 200 `{"status":"success","data":null}` |
| Current session | 409 `{"status":"error","message":"Use Logout for the current session.","code":5801}` |
| Unknown, cross-user, idle/absolute-expired, already revoked or competing non-current target | Identical 409 `{"status":"error","message":"Conflict."}` |
| Malformed target/body/query | Safe 400 validation, without lookup or value echo |
| Missing/invalid authority; missing exact permission | Sanitized 401; 403 |

Every response is no-store. Target access tokens fail the existing authoritative session lookup immediately,
even while the signature and JWT lifetime remain valid; target refresh credentials likewise fail. Current refresh
and access authority remain usable. Other devices have no push guarantee; their next server request is authoritative.
Package conflicts may close their transactional ORM unit: an HTTP request ends after its one dispatched command;
callers must not reuse a failed unit for later mutations. Ordinary PHP requests construct a fresh container.

### Confirmation, convergence and volatile coordination

Only other-session cards with fresh exact delete permission expose Revoke. React-Bootstrap's controlled modal
names the closed coarse descriptor and UTC creation/activity instants, focuses Cancel, traps focus and restores
it on cancellation. Escape/backdrop cancellation is unavailable during dispatch; a synchronous busy fence and
shared busy button prevent duplicate activation. The wait is bounded to eight seconds. Success, conflict and
uncertain failure announce fixed safe copy and refetch, never fabricate removed rows or replay automatically.
After completion focus moves to the page heading. Current cards expose established Sign out (Logout) instead.
The modal and rows retire with the existing AuthorityCache scope, including late success/failure/unmount fences.

The existing channel `fight-agent-os.session.v1` gains two strictly decoded version-1 variants, with its existing
32-hex sender and positive safe-integer sequence:

- `session-revoked` adds only `sessionId`, emitted after an observed server commit.
- `sessions-invalidated` has no payload, emitted after a safe target/current conflict without asserting revocation.

Every participating mounted list invalidates/refetches. Sequence checks reject duplicates/out-of-order tracked
senders; replay bookkeeping remains bounded to 64 peers. No queue or browser persistence is added. Missing/denied
BroadcastChannel retains local refetch and server enforcement. Unknown fields, malformed IDs and incompatible
versions do not change state. Only a server-marked current row from a live scoped list can teach the existing
credential coordinator its own stable ID; the coordinator never trusts JWT decoding. Credential replacement,
restoration or terminal teardown forgets it. A matching revoked target retires only that tab's memory, principal,
intent and pending work into terminal Login, without broadcasting a blanket terminal event to unrelated sessions.
A tab that has not safely learned its ID cannot promise immediate terminal UI; the next server rejection still
fails closed. This feature adds neither a second principal cache nor cross-device/exactly-immediate UI guarantees.

No session ID, credential, authority or coordination state is written to browser storage, history, HTML or query
parameters. Target ID appears only in necessary API path/channel transport and volatile state; no custom diagnostic
includes it. Coarse descriptor input remains closed/escaped through the original list decoder. Required integration
coverage includes real ownership, exact permission, no-override HTTP/OpenAPI, post-commit event, target-only state,
access/refresh rejection and barrier-synchronized independent PostgreSQL contenders for revoke/refresh/logout/expiry.
Client/component/channel and six production-backed catalog scenarios cover confirmation, focus, pending, success,
conflict, error, scope retirement and storage policy. Fresh commands and accessibility limits belong to TASK-00058's
Work receipt; builder evidence is not independent acceptance. Broad automation, cross-user administration,
logout-everywhere and guaranteed cross-device push remain excluded.
