# Architecture

Fight Agent OS is an open-source software factory: software for planning, building, reviewing and delivering
software. Its browser and Pi surfaces serve engineering workflows, not a general-purpose personal AI operating
system or an Internet homepage. It starts with the Slim composition from `johnnickell/project-slim`.

## Contract and implementation status

[Engineering standards](docs/engineering/STANDARDS.md#architecture) own the shared normative contract approved
in [EPIC-00023](planning/epics/00023-EPIC.md). ADR 0001/0002/0005, WF-008 and the binding link note retain rationale,
history and explicit amendments. Follow that contract for new/revised PHP work and assessments, not source shape
as an alternative rule. The approved target is aggregate-first Project-owned CQRS with registered coordinating
handlers, collection-like repositories, adapter-owned admission and presentation, generic transactions with
required audit, and deliberate application cutovers that preserve needed data. This is direction, not a claim
that the existing PHP inventory already conforms.

Initial Workspace bootstrap now uses an aggregate-owned repository, an adapter-generated typed ID in a registered
void command, explicit handler-owned state/audit transaction and registered typed current-state reads. Exceptional
references have HTTP-owned permission/Workspace admission and adapter-owned atomic limiting/UUID generation, without
aggregate creation or reservation. [The runbook](docs/engineering/REPOSITORY_REGISTRATION.md) distinguishes these
paths from Project creation/inspection, which TASK-00575 now cuts over to aggregate-owned registered commands/queries,
entry-generated IDs, independent adapter admission and conflict/fresh-query recovery. The Project handler coordinates
state/source claims and explicit required audit in one shared transaction, then dispatches ProjectRegistered after
commit; safe typed Views cross into HTTP Data/Responders. The Role audit transaction decorator still exists; DBAL-only
transactions need actual supplier/consumer qualification. TASK-00582's [Common failure/safe HTTP cutover](docs/engineering/SAFE_HTTP_ERRORS.md) replaces
the local generic classifier and updates actual client/error contracts, with scoped independent technical and
HTTP/browser/security QA acceptance. This does not qualify the remaining capability-specific corrections or
production deployment. [Ownership guidance](docs/engineering/OWNERSHIP.md)
records those source facts and scoped correction owners. No runtime command, route, permission, table or marker
is renamed by this documentation change. Unfinished individual plans remain TICKET-00132's separate portfolio.

## Implementation inventory and historical checkpoints

For TASK-00059, [the authoritative TASK](planning/tasks/00059-TASK.md) records current acceptance provenance and
merged/integrated PR #172. Its pending-merge wording below remains a historical implementation checkpoint, not
a current human merge action. Other capability states are unchanged.

- `src/Domain/`: business state, invariants, value objects, and capability interfaces as introduced
- `src/Application/`: command/query handlers and application coordination as introduced
- `src/Adapter/`: HTTP Actions/Responders, persistence, external services, and runtime integrations
- `client/`: React/strict TypeScript application frame at `/` and `/app`; Routes/Layouts/Pages/Components, typed public
  boot config, Docker-pinned tooling, typed API/CSRF service boundary, and a semantic production-component
  Storybook catalog; injectable fail-closed authority cache, shared permissions, guarded outlets/actions and
  bounded in-memory navigation intent now drive the single production route manifest and responsive frame (TASK-00051, pending independent Review/QA); an independently
  scoped semantic system/light/dark presentation selector and CSP-compatible prepaint are mounted; TASK-00052
  adds an honest Dashboard using only that cache/manifest, with independent permission shortcuts and no product-data
  endpoint (independent Review/QA pending); the server `/api/v1/me` safe identity/current-authority query and JWT-by-default matched-Action gate are
  available; public invitation activation is now mounted at `/app/activate` using real schema/Formik, protected JSON
  activation and memory/cookie session seams, pending independent acceptance; generic reset requests at `/app/reset-password` reuse package `RequestPasswordReset`, atomic browser admission,
  bounded response normalization and asynchronous package recovery (TASK-00054, independent Review/QA pending);
  one-time completion at `/app/reset-password/complete` captures fragment possession before render and delegates shared policy/package reset with target-scoped session retirement (TASK-00055, independent Review/QA pending);
  authenticated change at `/app/account/password` derives principal-only ownership and delegates current proof/atomic all-session invalidation to the package, then terminally clears the sole browser graph across tabs (TASK-00056, independent Review/QA accepted; [PR #159](https://github.com/johnnickell/fight-agent-os/pull/159) awaits human review and merge);
  owned-session visibility at `/app/account/sessions` delegates principal-owned selection to package
  `ListActiveSessions`, maps an explicit API View and joins an optional coarse presentation sidecar captured
  once after activation/login (TASK-00057, independent Review/QA accepted; [PR #167](https://github.com/johnnickell/fight-agent-os/pull/167) merged into `develop` at `0629697bd1a51b88720b1881aa5024928981f5ce`; read-only baseline);
  TASK-00058 adds principal-only other-owned `RevokeSession` dispatch (originally a deny-all administrative callback),
  explicit accessible confirmation and bounded volatile list/target invalidation (independent Review/QA accepted; [PR #173](https://github.com/johnnickell/fight-agent-os/pull/173) merged into `develop` at `0ec84eb67043e40bb84890d45ed1d2887a4a5573`);
  [installation session administration](docs/engineering/SESSION_ADMINISTRATION.md) now composes separate
  read/revoke package actor/target callbacks, with exact HTTP permission metadata, explicit immutable owner
  admission and package-handler-owned revocation/audit on the shared transaction; self-service remains owner-only;
  the installation-wide [user directory](docs/engineering/USER_DIRECTORY.md) at `/app/admin/users` and
  `GET /api/v1/users` dispatches package `ListUsers` only after exact authoritative `READ_USERS`, projects
  complete safe role presentation and fences volatile browser pages with the sole cache (TASK-00059,
  independently reviewed and QA passed; [PR #172](https://github.com/johnnickell/fight-agent-os/pull/172) merged into `develop` at `d6b9db3c8c46f1c2853bd1469fd003cb6445c3d5`);
  TASK-00061's [ordinary invitation form](docs/engineering/ORDINARY_INVITATIONS.md) uses exact INVITE_USERS,
  a fixed ordinary API request and strict safe-ID projection; directory/status invalidation and READ_USERS-only
  continuation reuse the same client graph. It does not replace the existing PHP issuance composition owned by
  TASK-00577 or add elevated input. Independent technical Review and behavioral QA accepted the ordinary
  journey within the evidence boundaries recorded by its TASK. TASK-00062 adds the separate
  [confirmed elevated route](docs/engineering/ELEVATED_INVITATIONS.md) using the same issuance transport
  and existing API, both exact invitation/elevation permissions, a canonical-target Cancel-first dialog,
  independent-commit uncertainty and operator investigation without browser assignment repair.
  Independent technical Review and behavioral QA accepted this bounded journey, including retained
  uncertainty through routine authority verification; production/inbox qualification remains separate.
  TASK-00060's invitation page deliberately reads safe user detail and package delivery status under READ_USERS.
  Mutation Actions admit MANAGE_USER_INVITATIONS and dispatch package commands with no data response; manage-only
  grants no reading. Page management requires both permissions. This maintainer revision awaits independent acceptance;
  TASK-00118 adds safe package `GetUserById` detail with a single `READ_USERS` declaration on each detail
  and directory HTTP Action, enforced centrally by `ApiActionSecurity` before execution, referenced package
  `GetRoleById` reads through injected QueryBus, and complete
  safe View/HTTP mapping in Responders without fetching there. The redundant consumer read services are removed.
  Volatile in-directory detail remains; no mutation or self-service expansion. The pagination-only correction
  has independent Review/QA acceptance on `55c55ad`; delivery results are in the receipt and PR below.
  Removal of duplicate permission
  checks in both Actions has independent Review/QA acceptance on `ba7f652`. Earlier acceptance on `c2227fa`
  is historical; publication/final checks were pending at this checkpoint.
  Current delivery results: `.runs/tasks/TASK-00118/runs/land/receipt.md` and [PR #200](https://github.com/johnnickell/fight-agent-os/pull/200);
  canonical login at `/app/login` reuses package authentication, browser guards,
  schema/Formik and memory/cookie seams, also pending independent acceptance; rotating cookie-only refresh and current-session logout now have HTTP/client services and login-success UI pending independent Review/QA; TASK-00048 adds bounded memory-only multi-tab continuity/restoration at `/app/login`, pending independent Review/QA; TASK-00049 connects one strict typed client `/me` service and the existing cache to login/restoration, pending independent Review/QA; the protected frame now uses that same sole graph, with truthful future landing seams and explicit unavailable-product copy
  (TASK-00027's transport and TASK-00028's injected authority foundation have independent review/scoped QA acceptance)
- `.pi/skills/`: local planning, engineering, Graphify and instruction-writing guidance; managed runtime execution remains planned
- `harness/pi/`: installable terminal identity, native session-local workflow binding, an opt-in
  [deterministic Pi tool policy](harness/pi/TOOL_POLICY.md), and a Fight-maintained MIT-licensed upstream SDK-agent
  engine under `vendor/pi-subagents/`. The Harness packages one engine and owns policy/observation, not a second
  scheduler. The sole fresh Build default uses `fight_build_stage` for all four roles, binding real session-local
  role/effects before execution and reusing passive native observation. Actual Build/TASK/checkout selection and
  supported capabilities/profile are required; SDK subordinates cannot coordinate nested stages, and no selector
  flag or process fallback remains. TASK-00560's permitted Engineer/Herdr journey has independent Review and QA
  acceptance; delivery results remain in `.runs/tasks/TASK-00560/runs/land/receipt.md`. TASK-00561's staged contract
  separates bootstrap evidence, final source assessment and actual authorized same-TASK Land delivery; the source
  cutover alone is not final acceptance or rollout. Explicit Sync retains its demonstrated process Engineer/Land
  path and shared custody. Active installed hosts change only by deliberate safe-boundary adoption. Future typed Workflow clients, semantic tool screening and
  qualified process isolation remain separate

Fight Common and Fight Access Control remain Composer dependencies. The inherited `App` namespace and
hello-world HTTP behavior remain in the scaffold. Framework migration needs an observed use case. The
package boundary, historical proof and current executable-check limits are summarized in
[application ownership enforcement](docs/engineering/OWNERSHIP.md); a passing checker is not whole-source
conformity with the approved target.

The proposed web application owns planning and workflow records; Pi performs assigned agent work. The browser
will support planning and observation first, and workflow commands as they become well defined. These are
design directions, not claims of implemented behavior. See [the foundation brief](planning/FOUNDATION.md).

Human-readable architecture and interfaces come first; tests prove behavior rather than dictate the design.
See [engineering standards](docs/engineering/STANDARDS.md), [Team roles](docs/engineering/TEAM.md) and
[isolated TASK startup](planning/tickets/00032-TICKET.md). PHP owns deterministic workflow transitions and
sandbox provisioning requests; model-generated instructions cannot expand permissions or container policy.

The [terminal execution contract](docs/engineering/TERMINAL_EXECUTION.md) records the managed execution
boundary, measured local sandbox primitives and remaining launch blockers. The
[Roadmap](planning/ROADMAP.md#approved-blended-delivery-strategy) governs blended terminal, browser and
shared-foundation delivery; interface choice does not bypass an operation's authority or qualification gates.
