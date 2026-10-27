# Architecture

Fight Agent OS is an open-source software factory: software for planning, building, reviewing and delivering
software. Its browser and Pi surfaces serve engineering workflows, not a general-purpose personal AI operating
system or an Internet homepage. It starts with the Slim composition from `johnnickell/project-slim`.

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
  TASK-00058 adds principal-only other-owned `RevokeSession` dispatch with a deny-all administrative callback,
  explicit accessible confirmation and bounded volatile list/target invalidation (independent Review/QA accepted; [PR #173](https://github.com/johnnickell/fight-agent-os/pull/173) merged into `develop` at `0ec84eb67043e40bb84890d45ed1d2887a4a5573`);
  the installation-wide [user directory](docs/engineering/USER_DIRECTORY.md) at `/app/admin/users` and
  `GET /api/v1/users` dispatches package `ListUsers` only after exact authoritative `READ_USERS`, projects
  complete safe role presentation and fences volatile browser pages with the sole cache (TASK-00059,
  independently reviewed and QA passed; [PR #172](https://github.com/johnnickell/fight-agent-os/pull/172) merged into `develop` at `d6b9db3c8c46f1c2853bd1469fd003cb6445c3d5`; invitation links remain truthful registered seams, not mutation UI);
  canonical login at `/app/login` reuses package authentication, browser guards,
  schema/Formik and memory/cookie seams, also pending independent acceptance; rotating cookie-only refresh and current-session logout now have HTTP/client services and login-success UI pending independent Review/QA; TASK-00048 adds bounded memory-only multi-tab continuity/restoration at `/app/login`, pending independent Review/QA; TASK-00049 connects one strict typed client `/me` service and the existing cache to login/restoration, pending independent Review/QA; the protected frame now uses that same sole graph, with truthful future landing seams and explicit unavailable-product copy
  (TASK-00027's transport and TASK-00028's injected authority foundation have independent review/scoped QA acceptance)
- `.pi/skills/`: local planning, engineering, Graphify and instruction-writing guidance; managed runtime execution remains planned
- `harness/pi/`: installable terminal identity, Jev/file-screening capabilities and an opt-in
  [deterministic Pi tool policy](harness/pi/TOOL_POLICY.md); future typed Workflow clients, semantic tool screening
  and qualified process isolation remain separate

Fight Common and Fight Access Control remain Composer dependencies. The inherited `App` namespace and
hello-world HTTP behavior are preserved for the initial scaffold. Framework migration is a future decision. The
accepted package boundary and its focused automated checks are summarized in
[application ownership enforcement](docs/engineering/OWNERSHIP.md).

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
