# Current client authority

TASK-00049 connects the existing authenticated `GET /api/v1/me` to the login/restoration lifecycle.
That slice adds no frame or dashboard. TASK-00051 now mounts the [authoritative application frame](APPLICATION_FRAME.md)
using the same sole graph; its independent Review/QA remains pending. Dashboard content remains unavailable.
[ADR 0004](../../planning/adr/0004-client-authority-and-runtime-state.md) owns the state contract;
[ADR 0003](../../planning/adr/0003-browser-authentication-security-profile.md) owns credentials.

## One source and owner

The existing server Action uses the request-scoped package `CurrentPrincipalProvider` and the package
`GetUserById` query through the normal bus. `/me` requires authentication, not `VIEW_DASHBOARD`.
The server returns a JSend success containing exactly `user_id`, canonical `email`, sorted unique `roles`
and `permissions`, with `Cache-Control: no-store`. It never returns a session ID, authentication version,
credential, grant, lifecycle/revision, delivery/audit state or repository object. See
[matched-Action security](../api/README.md#matched-action-security-task-00117).

`CurrentPrincipalService` makes only the fixed protected `/me` GET through `ApiClient`. Its codec maps
snake_case to the immutable `CurrentPrincipal` model and rejects missing/extra fields, malformed UUID/email,
noncanonical names, duplicate names and sparse/non-string lists atomically. Names follow the package RoleName
and PermissionName grammar, with the database/catalog's 255-character bound and a 1,024-name list bound.
Custom package-shaped names remain valid: a client-side managed-catalog allowlist would incorrectly discard
current server authority. Only exact permissions, never role names, grant presentation through `evaluateAccess`.

The service retains no projection. `AuthorityCache` remains the single volatile owner/evaluator input.
`useApplicationSession` now lifts `LoginJourney`'s original graph into root/Login/protected routing: one cache
with this service is passed to `SessionCoordinator`, with foreground observation once and disposal on unmount/
pagehide. Public activation, sign-in help and unknown routes remain inert: they do not initiate restoration or
create another principal store. The production frame withholds protected pages/navigation until both the
credential coordinator and the sole cache are current; no credential-ready panel is treated as route authority.

## Transitions and handoff

- Login retires the previous context. Its single-use authentication attempt starts `/me` only after accepted
  credentials; token readiness alone cannot authenticate a principal.
- Every refresh clears dispatch authority before sending; successful local rotation or validated peer token
  handoff refetches `/me` for that tab. Under TASK-00505's authorized ADR amendment, the sole cache may retain
  last-verified same-context presentation through routine checks, never permission to dispatch protected work.
  Recoverable failure exposes unavailable status and explicit retry without claiming revocation.
- Logout/terminal failure immediately retire scopes and establish a context barrier. A current `/me` 401
  becomes terminal and clears coordinator credentials; it never starts an interceptor loop or replays a mutation.
  Only explicit login crosses the barrier. Old success **and** error results are ignored by cache generations
  before they can affect credential state.
- Authority-changing consumers call `SessionCoordinator.authorityChanged()` after completion, including an
  uncertain outcome when authority might have changed. This retires private scopes and refetches locally and
  best-effort in peers. It does not mutate authority, infer a grant or retry the original operation. A 403 remains
  a denial even if a refetch changes presentation.
- The version-1 session channel's exact `authority-changed` message has only `version`, `sender`, `sequence`
  and `kind`. It carries intent, not a principal, permission list, identity or token. Existing credential
  `invalidate` retains its separate refresh/context meaning. Tabs without usable credentials invalidate without
  starting `/me` or restoration. Logout/terminal barriers still reject peer resurrection. Older clients ignore
  the new kind; freshness and independent server checks remain the backstop, not instant cross-version sync.
- A maximum 60-second request-start monotonic budget triggers one coalesced foreground background read.
  Routine timers pause while hidden; return coalesces a check with other demand, retaining a usable memory token.
  Reads have an 8-second observation bound, including transports that ignore abort. Failed attempts do not
  schedule automatic retries. `getSnapshot()` and `captureScope()` always fail closed at expiry/pending/error;
  `getPresentationSnapshot()` is display-only and references the same cache-owned projection. No new protected
  outlet/loader or permission action may use it for admission. Genuine authority invalidation removes affected
  presentation; logout, terminal failure and identity replacement retire the old private context. No persistence,
  HTML hydration, URL state or principal broadcast is introduced.

The TASK-00051 frame subscribes directly. Future private-data owners use this same cache/scopes and must not
mirror the principal in another context/store. See its route, restoration and safe intent matrix in the frame runbook.

## Verification boundaries

`ApiActionSecurityTest` exercises the real registered HTTP route, package resolution, guarded PostgreSQL state,
managed memberships, no-dashboard access, current permission changes using the same access token, expired and
mismatched authority, incomplete/duplicate repository results, request snapshot reuse, OpenAPI and redaction.
Its fixed clock is installed before the real route table captures middleware. Corrupt-reference scenarios use
repository fault doubles because PostgreSQL foreign keys/uniqueness prevent those corrupt rows normally.
`ReadCurrentPrincipalActionTest` separately challenges safe identity/principal mismatch and inactive/missing Views.

`CurrentPrincipalService`, `PrincipalContinuity` and mounted `LoginJourney` tests drive actual client transport,
services and cache with controlled HTTP promises. They cover decoding, storage absence, peer-intent-only
refetch, refresh/logout/terminal barriers and out-of-order 200/401 completion. These deterministic tests are not
live browser/server evidence. The Authority catalog uses synthetic safe HTTP Views through the production codec
for its principal examples; state-only examples retain explicit deterministic loaders. Full build, live probes,
counts, warnings and limitations belong in TASK-00049's completion evidence. Independent acceptance, deployment,
ingress/header certification and universal accessibility are separate.
