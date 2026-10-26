# Permission-controlled user directory

TASK-00059 implements `GET /api/v1/users` and `/app/admin/users`. Local implementation evidence is in the
TASK; independent Review/QA and delivery remain separate.

## Authority and ownership

The matched API Action requires Bearer authentication and exact current `READ_USERS` through the existing
request-scoped principal provider. `ReadUserDirectory` then re-resolves invocation-bound authentication with
`AuthenticatedPermissions` and checks the protected installation permission through `InstallationAuthority`
before dispatching package `ListUsers` on the configured shared query bus. Missing, stale, incomplete or
non-active authority fails closed. A displayed `ROLE_SUPER_ADMIN`, JWT/HTML claim, submitted actor or client
route never grants a read. No new identity, principal, session or authorization store is introduced.

Fight Access Control owns identity pagination and safe `UserView` production. The existing PostgreSQL user
repository fixes ordering to `created_at ASC, id ASC`; callers supply no ordering expression. Application
projection resolves the union of assigned role IDs through the authoritative role repository. Missing,
unexpected or duplicate returned references, duplicate assignments/identities or duplicate resolved names
fail the whole response with a sanitized system failure, not an incomplete role list. Each user's assignments
are sorted by exact role name. An empty assignment remains empty; it does not imply `ROLE_USER`.

## HTTP contract

- Optional source-only `QueryString`/Validation fields: `page` **1–1000**, `per_page` **1–100**; defaults **1/25**.
  Checked decimal-digit strings are explicitly converted to package pagination integers. Leading zeroes and
  valid percent-encoded names/values are accepted; empty, signed, fractional, ambiguous, duplicate decoded,
  nested, unknown or malformed fields are rejected before the Action/query. A GET body is rejected, not merged.
- Includes installation-wide **pending_activation, active, disabled and deleted** identities. No query can
  select actor, authority, workspace, role, relations, hidden fields or visibility. There is no search, filter,
  free-form ordering or mutation. The client does not filter one partial page as though it were the installation.
- Offset pages are deterministic for a committed state, **not** a transactionally consistent cross-request
  snapshot. Concurrent writes can shift rows/counts; explicitly reload or return to page one. The browser never
  extrapolates unseen identities. Page 1000 is the last navigable page even if the count implies more.
- Success is JSend `{status: "success", data: {page, per_page, total_pages, total_records, records}}`.
  Empty results have at least one total page; out-of-range pages can have no records.
- Every record has exactly `user_id`, canonical `email`, `state`, `roles`, `created_at`, `updated_at`.
  A role has exactly `name` and boolean `managed`. The marker describes ownership, not editability or authority.
  Timestamps are UTC ISO-8601 at second precision; persistence's finer ordering precision is not exposed.
- All responses use the shared **`Cache-Control: no-store`** policy; successful reads set no cookies. `401` is
  authentication failure, `403` is current permission failure, `400` is sanitized input rejection and `500` is
  generic unavailable state. Correlation is metadata, not a source of authority.
- No password/hash, session/refresh material, grant/digest/ciphertext/link, authentication/authorization revision,
  pending email/reservation, permission membership/snapshot, audit/delivery/provider internals or entity is
  serialized. Explicit `UserDirectoryData` maps only approved fields; it never delegates entity serialization.
  Attribute-owned OpenAPI documents the actual read and closed schemas; exports stay private under `var/`.

## Browser behavior and invitation handoffs

`UserDirectoryService` uses the existing same-origin, current-memory Bearer API transport and strict exact-key
codecs. Its only retained state is a safe refusal category to prevent a guard remount from replaying a denied
read. `UsersPage` owns one volatile page and an AuthorityCache scope: authority retirement, context replacement,
unmount and page changes abort/fence pending work and remove private rows. `401` uses the sole bounded session
restoration owner; `403` revalidates current authority. Neither automatically replays the refused operation;
recovery needs explicit Retry. Nothing is stored in browser persistence or history.

The single production route manifest independently guards every destination, and shared `evaluateAccess`
controls presentation and link activation:

| Control | Exact current permissions | Registered destination |
|---|---|---|
| Directory | `READ_USERS` | `/app/admin/users` |
| Invite User | `INVITE_USERS` | `/app/admin/invite` |
| Invite Super Admin | `INVITE_USERS` **and** `ASSIGN_SUPER_ADMIN` | `/app/admin/invite-super-admin` |
| Pending row's Invitation status | `MANAGE_USER_INVITATIONS`; target must be pending | `/app/admin/invitations` |

Invitation destinations remain **truthful unavailable landing states**. The status link opens the existing
administration seam, not a target-specific status query: no user ID, email, credential or invented target route
is placed in the URL/history. TASK-00060 owns the target contract and working status/recovery journey;
TASK-00061/00062 own ordinary/elevated invitation forms. No invitation API is duplicated and no link sends mail.
Forbidden controls are absent, not hidden focus targets. Client presentation never replaces server enforcement.

## Responsive and verification boundaries

The same captioned table, scoped headers and rows reflow as cards below 1100px; no duplicate hidden data tree
or second mobile loader exists. Long canonical email and role labels wrap. Text lifecycle labels and managed/
custom markers do not depend on color. Native links/buttons, at least 44px controls, explicit loading/empty/
forbidden/system states, polite page-count announcements and post-pagination/retry heading focus use the
existing frame, themes, fonts, focus and reduced-motion rules. Pagination clears previous-page rows while
loading and offers First page after navigation.

Owned tests exercise exact permission subsets, no-dispatch rejections, stale authority races, PostgreSQL ties
and lifecycle visibility, complete role resolution, safe Views/OpenAPI, strict client decoding, cancellation,
volatile retirement, registered destinations, keyboard recovery and semantics. Twelve production-backed Users
stories cover ready/dark/narrow/long-email/loading/empty/system/server-forbidden/role-name-denied/read-only/
pagination/retirement states. Catalog authority/data are synthetic fixtures, not authentication E2E proof.
Actual browser axe/reflow/focus samples and isolated live probes are recorded separately in TASK evidence.
Automation and sampled observations do not establish universal WCAG conformance, physical device or screen-reader
certification. The fresh canonical `./bin/build`, independent Review and applicable QA remain mandatory.
