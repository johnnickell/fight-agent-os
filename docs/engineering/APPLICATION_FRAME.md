# Application frame

TASK-00051 mounts the responsive application frame and route decisions. Implementation verification is not
independent acceptance. Dashboard content and later account/administration operations remain unavailable.

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
| `/app/dashboard` | Authenticated + `VIEW_DASHBOARD`; destination only, not dashboard content |
| `/app/account`, `/app/account/password` | Authenticated; truthful Account/Security and Change password seams |
| `/app/account/sessions` | Authenticated + `READ_OWN_SESSIONS` |
| `/app/admin/users` | Authenticated + `READ_USERS` |
| `/app/admin/invite` | Authenticated + `INVITE_USERS` |
| `/app/admin/invite-super-admin` | Authenticated + both `INVITE_USERS` and `ASSIGN_SUPER_ADMIN` |
| `/app/admin/invitations` | Authenticated + `MANAGE_USER_INVITATIONS`, independent of Users directory access |

The operational landing destinations supply no data, forms or unavailable enabled actions. Parameterized
invitation status is not registered until its owning journey supplies a safe target contract. No fake records,
role-name bypass, workspace switcher, planning/agent/observability content or Feature gate is introduced.

The HTTP `/` now serves the same no-store client document as `/app`; the old PHP root smoke representation
is intentionally superseded by the accepted root decision. Other unrelated paths and API routing remain
server-owned. Unknown `/app/...` paths produce a generic application not-found page without echoing the path.
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
- Credential readiness alone never renders private pages, email or navigation. Unknown/loading/stale/malformed/
  failed projections fail closed. `/me` retains its 60-second monotonic request-start budget and generation fences.
  Foreground return and refresh retire authority; navigation demand or explicit retry can load stale authority.
  Errors require explicit retry. The cache does not poll or extend freshness through reads.
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
At ≤860px it starts closed; Open is a 44×52 edge control. Expanded navigation has only its internal Close.
The narrow React-Bootstrap Offcanvas inerts background, enforces modal focus, cycles native Tab boundaries,
handles Escape/backdrop/Close and returns focus. Route changes reset the frame; breakpoint changes reset
only navigation mechanics while preserving the mounted workspace. Focus owned by disappearing navigation moves
to the available Close (wide) or Open (narrow); workspace/outside focus is not claimed. Terminal changes unmount
protected presentation immediately. Wide Open focuses Close. Group disclosures are independent native details;
current navigation uses `aria-current`. A skip link, focusable named main, route-title/status announcements,
wrapping email, 44px controls, reduced motion and the existing three-state appearance selector remain available.
Bootstrap link RGB variables now map to the accepted action colors rather than its inherited blue fallback.

## Verification boundary

Owned tests use controlled principal/session promises and real production evaluators, caches and coordinators;
existing stale-response, permission, deadline and intended-route fuzz coverage remains applicable. Twelve
production-backed Frame stories cover narrow/wide, light/dark, long email, forbidden, pending/malformed authority,
network/expired session, account/invitation seams and drawer keyboard interaction. Automated axe/browser results
are samples, not universal WCAG or physical AT/device certification.

TASK evidence records exact initial-source Before captures, fresh After captures and real trusted-HTTPS
login/restoration/history/logout/denied/admin probes in an isolated development database. No capture contains
real user data. Application assets/TLS/browser profiles and private disposable fixture credentials stay under
ignored `var/`; sanitized execution evidence stays under task-owned `.runs/`. A fresh full `./bin/build` remains
mandatory. Independent Review and QA, hosted publication, merge and production enrollment are separate.
