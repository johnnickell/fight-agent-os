# Application frame

TASK-00051 mounts the responsive application frame and route decisions. Implementation verification is not
independent acceptance. TASK-00052 adds the honest Dashboard described below (independent Review/QA pending);
TASK-00059 adds the [permission-controlled Users directory](USER_DIRECTORY.md); later invitation and lifecycle
operations remain unavailable.

## Ownership and routing

`client/src/routes/applicationRoutes.ts` is the sole complete route manifest. It provides public/protected
classification, ordered exact access requirements, destination/title/group and classified intended-route metadata.
`evaluateAccess` remains the only permission evaluator; `AuthorityCache` remains the only principal owner.
`useApplicationSession` lifts the existing login composition into one document-scoped graph. It owns memory-only
credentials, the existing `SessionCoordinator`, `/me` service/cache, public form schemas and `IntendedRoute`.
No principal, permission, token, password or intended route is persisted or embedded in HTML/history state.
Server authorization remains authoritative; an SPA route response is not an access-control boundary.

| Registered destination | Complete presentation requirements |
|---|---|
| `/`, `/app`, `/app/` | Public root decision: wait for restoration; anonymous → Login; fresh principal → permitted intent or Dashboard; denied Dashboard → authenticated Forbidden |
| `/app/login` | Public; fresh authenticated authority selects one permitted landing |
| `/app/login/recovery`, `/app/activate` | Public/inert: no automatic cookie restoration; existing recovery/activation owners |
| `/app/dashboard` | Authenticated + `VIEW_DASHBOARD`; honest Dashboard, no product data |
| `/app/account`, `/app/account/password` | Authenticated; truthful Account & Security and Change Password seams |
| `/app/account/sessions` | Authenticated + `READ_OWN_SESSIONS` |
| `/app/admin/users` | Authenticated + `READ_USERS` |
| `/app/admin/invite` | Authenticated + `INVITE_USERS` |
| `/app/admin/invite-super-admin` | Authenticated + both `INVITE_USERS` and `ASSIGN_SUPER_ADMIN` |
| `/app/admin/invitations` | Authenticated + `MANAGE_USER_INVITATIONS`, independent of Users directory access |

The Users route now loads its guarded directory; remaining operational landing destinations supply no data,
forms or unavailable enabled actions. Directory invitation links reach these truthful static seams. Parameterized
invitation status is not registered until its owning journey supplies a safe target contract. No fake records,
role-name bypass, workspace switcher, planning/agent/observability content or Feature gate is introduced.

The HTTP `/` now serves the same no-store client document as `/app`; the old PHP root smoke representation
is intentionally superseded by the accepted root decision. Other unrelated paths and API routing remain
server-owned. Missing/incomplete production assets retain the shell's generic no-store HTTP 503, with no
broken asset references or authentication challenge; a deployable shell requires an explicit client build.
HTTP/security tests supply isolated available/unavailable deployment inputs rather than depending on
`var/client/build/`. The canonical gate checks scratch assets and does not publish a live deployment.
Unknown `/app/...` paths produce a generic application not-found page without echoing the path.
Native links remain real destinations. Same-origin registered, unmodified clicks use null-state history
push/replace to preserve the single owner. Back/forward reads the pathname. Download/modified/target/external
links and fixed reload recovery remain native. Refresh/direct load creates a fresh context and restores once.

## State and recovery

- Restoration retains TASK-00048's bounds: at most two 2-second lock elections; an 8-second request and at most
  one 8-second conflict recovery, with bounded 50/250ms coordination waits. There is no route-driven refresh loop.
- A cold refresh `401` cannot distinguish an absent from expired cookie. The application opts into cold-anonymous
  presentation: retire deferred work, establish a credential barrier and offer canonical Login, retaining only a
  classified destination. It does not broadcast an invented authenticated principal. A later `/me` `401` or
  established-session termination is terminal, clears intent and removes protected UI before explicit sign-in.
- Credential readiness alone never admits private pages, email or navigation. Cold unknown/loading states
  withhold them. TASK-00505's authorized ADR amendment separates an already admitted same-context display from
  dispatch authority: routine freshness/return/renewal checks retain the mounted frame and outlet, without
  resetting route, scroll, focus or navigation state. The sole cache triggers coalesced foreground `/me` at its
  maximum 60-second request-start budget, pauses timers while hidden and resumes once. Pending/stale/error
  authority denies new protected outlets/loaders and permission actions, even while last-verified display remains.
  Reads are bounded to 8 seconds; failure stops automatic attempts and shows explicit recovery. Reads never
  extend freshness. Authority-change signals, confirmed denial, identity replacement and session end retire
  affected private presentation; late results cannot resurrect it.
- A fresh principal lacking exact route requirements sees Forbidden, not Login; no role name grants access.
- Network recovery and unconfirmed logout do not claim revocation. Logout retires local authority immediately;
  the existing API attempts only current-session revocation. No command is replayed automatically.
- Pagehide disposes all owners and clears fields before bfcache. The retained document offers a native reload
  sign-in link. Rendering/import failures remove the entire frame and expose a fixed native reload destination,
  not stack/URL/server diagnostics or a silently authorized fallback.

## Safe intent and presentation

`IntendedRoute` retains at most one path for ten minutes and 2,048 characters, only in memory. Current routes
allow no query keys; password/auth/grant/invite paths never opt in. External/scheme/protocol-relative/control/
backslash/oversized/ambiguous/encoded-path/unknown-query/credential-bearing inputs are refused; fragments are
discarded. The first raw attempt is evaluated once **before** query/fragment sanitation, so rejection cannot
be recaptured later as a sanitized valid intent. Fresh authority consumes it once, even on a directly restored
protected destination. Denied/expired intent falls back to Dashboard or Forbidden. Terminal/logout clears it.
Activation's existing fragment intake remains separately owned and precedes React.

The frame recreates the accepted C/round-4 264px grouped rail and existing Ubuntu-heading/Open-Sans-body theme.
At ≤860px it starts closed; the double-right-chevron Open navigation control is a 44×52 edge control.
Expanded navigation has only its internal double-left-chevron Close navigation control, with expanded state.
The narrow React-Bootstrap Offcanvas inerts background, enforces modal focus, cycles native Tab boundaries,
handles Escape/backdrop/Close and returns focus. Route changes reset the frame; breakpoint changes reset
only navigation mechanics while preserving the mounted workspace. Routine background verification does neither.
Its fixed status notice does not change outlet geometry; route changes and genuine forbidden/session-end states
retain accessible main focus behavior. Focus owned by disappearing navigation moves
to the available Close (wide) or Open (narrow); workspace/outside focus is not claimed. Terminal changes unmount
protected presentation immediately. Wide Open focuses Close. Group disclosures are independent native details;
current navigation uses `aria-current`. A skip link, focusable named main, route-title/status announcements,
wrapping identity, 44px controls, reduced motion and the existing three-state appearance selector remain available.

TASK-00506 compacts the desktop utility header to a normally single 61px row at default text size, without a fixed
height. A named user-outline User Menu dropdown contains the sole cache's safe email, Account & Security,
independently permitted Active Sessions, local appearance and current-session Sign Out. React-Bootstrap owns
opening, destination arrows, dismissal and focus return; native radio arrows own the compact sun/moon/monitor
Light/Dark/System choices. The selected preference stays explicit, including System when following the OS.
The frame keeps the existing appearance store subscribed outside its pathname-keyed content, so media/storage
updates remain active before the lazy menu opens and after navigation. That subscription is released with the
frame; it adds no store or persistence policy. Public login/activation retain their existing full selector.
Native links recheck current authority at activation; paused dropdown destinations prevent native navigation
as well as library selection. Menu Escape does not collapse
the wide rail. Every rail destination retains its full Title Case text alongside a decorative self-contained SVG.
No account operation, endpoint, permission, logout scope, authority owner or theme-persistence policy is added.
Bootstrap link RGB variables now map to the accepted action colors rather than its inherited blue fallback.

## Honest Dashboard (TASK-00052)

`pages/DashboardPage.tsx` is a guarded lazy page within the same application session, not a second authority or
capability registry. It subscribes directly to the sole cache, shows only its safe email, and uses the existing
manifest requirements and shared evaluator for every shortcut. Unknown or genuinely invalidated authority
removes the page and links. Routine checks retain last-verified same-context display; shortcuts suppress navigation
while dispatch authority is pending/stale/error. Newly admitted routes still require fresh evaluation. Late
projection results remain fenced by the existing cache. A resolved principal
without exact `VIEW_DASHBOARD` receives authenticated Forbidden, not Login.

The accepted C composition uses the existing semantic tokens, Ubuntu headings/Open Sans body, named panels,
wrapping link lists and responsive account/administration cards. Account & Security requires authentication;
Active Sessions independently needs `READ_OWN_SESSIONS`. Users, Invite User and Invitation Administration independently
need `READ_USERS`, `INVITE_USERS` and `MANAGE_USER_INVITATIONS`. The Dashboard does not advertise elevated assignment;
the existing elevated route/rail still needs both `INVITE_USERS` and `ASSIGN_SUPER_ADMIN`. Role names grant nothing.
Each Dashboard link includes the manifest's truthful availability copy and reaches its registered
destination. Users opens its read-only directory; invitation destinations remain unavailable seams.
There are no enabled mutation controls or fake forms, records or session rows.

“Available today” describes installed sign-in/restoration/current-access/appearance/current-session sign-out.
“Not installed yet” explicitly names planning, observability, agent sessions, charts, metrics and workspace
switching. It is not a zero-result query or synthetic telemetry. The page makes no API request; only existing
session and `/me` owners supply authority. All linked APIs still require server authorization/owner checks when
those journeys ship. No principal, role, permission or credential is persisted for Dashboard presentation.

## Verification boundary

Owned tests use controlled principal/session promises and real production evaluators, caches and coordinators;
existing stale-response, permission, deadline and intended-route fuzz coverage remains applicable. Sixteen
production-backed Frame stories cover narrow/wide, light/dark, long email, forbidden, pending/malformed authority,
network/expired session, retained-display background failure, account/invitation seams, drawer keyboard interaction,
account-menu keyboard/theme interaction and open long-identity menus at wide/narrow sizes. Automated axe/browser results
are samples, not universal WCAG or physical AT/device certification.

TASK evidence records exact initial-source Before captures, fresh After captures and real trusted-HTTPS
login/restoration/history/logout/denied/admin probes in an isolated development database. No capture contains
real user data. Application assets/TLS/browser profiles and private disposable fixture credentials stay under
ignored `var/`; sanitized execution evidence stays under task-owned `.runs/`. A fresh full `./bin/build` remains
mandatory. Independent Review and QA, hosted publication, merge and production enrollment are separate.
