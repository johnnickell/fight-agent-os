<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/assets/fight-agent-os-dark.svg">
  <img src="docs/assets/fight-agent-os-light.svg" alt="FIGHT Agent OS" width="440">
</picture>

# Fight Agent OS

An open-source software factory: software to build software. Plan engineering work, carry it through a
customized Pi terminal and managed workflow, and inspect progress in a browser dashboard as those capabilities
are delivered. This project is not a general-purpose personal AI operating system or an Internet homepage;
those belong to a separate future project.

**Status: application foundation in progress.** Slim/PHP, guarded PostgreSQL persistence, identity/grant storage,
API validation/error handling, public-safe form metadata and local engineering skills are
present. Explicit initial Workspace bootstrap and protected Repository registration/inspection are implemented under
[TASK-00078's runbook](docs/engineering/REPOSITORY_REGISTRATION.md), pending fresh independent Review/QA after repair. They add no
checkout, access grant, Planning or execution authority. Public invitation activation is implemented at `/app/activate`, pending independent acceptance;
canonical login is implemented at `/app/login`; refresh/current-session logout are implemented pending independent acceptance. Bounded multi-tab continuity at `/app/login` is implemented pending independent Review/QA; TASK-00049 connects the sole typed client `/me` authority cache to login/restoration, pending independent Review/QA;
TASK-00051 mounts the [responsive authoritative application frame](docs/engineering/APPLICATION_FRAME.md),
pending independent Review/QA, at `/`, `/app` and registered protected destinations. Local system/light/dark
appearance and truthful future account/admin seams are available. TASK-00052 adds the honest permission-aware
Dashboard using the sole principal cache, safe identity, independent administration shortcuts and explicit
unavailable-capability copy (independent Review/QA pending). Other product UI journeys,
managed Pi execution and database-authoritative Planning remain planned. See the [capability inventory](planning/FOUNDATION.md).

## Start planning

Read the [foundation brief](planning/FOUNDATION.md), [planning conventions](planning/CONVENTIONS.md), and
[TASK Board](planning/tasks/BOARD.md). Use your preferred planning skills in the terminal; saved artifacts follow
**EPIC → TICKET → TASK**, with dependency-ordered SUBTASKs during implementation.

Use the [ubiquitous language](docs/UBIQUITOUS_LANGUAGE.md) for human Roles, Agent Team roles, Permissions and
Repository access. Fresh project skills live in [.pi/skills/](.pi/skills/README.md). Markdown remains authoritative
until an explicit, verified migration to the future project-scoped planning database.

## Fight terminal

Install the approved Fight welcome and compact working header for all your Pi sessions:

```sh
pi install ./harness/pi
```

Restart Pi, then use `/fight-header` to choose a presentation or `off` to restore its default header.
See the [package instructions](harness/pi/README.md) for quiet/ASCII modes and removal.
The [managed terminal workflow](docs/engineering/TERMINAL_EXECUTION.md) is under implementation alongside
browser and shared-foundation work under the [blended roadmap](planning/ROADMAP.md#approved-blended-delivery-strategy).
TASK-00510 adds a native `/fight-workspace [open|close]` Herdr candidate with honest placeholder companions,
keeping Pi's existing editor/completion/session intact; see [native ownership and recovery](harness/pi/WORKSPACE.md#native-pi-in-herdr-task-00510-candidate).
Independent Review/QA remain pending; child/log producers are not included.
An unaccepted [local workspace candidate](harness/pi/WORKSPACE.md) is available through the package-owned
`fight-terminal` command after [explicit user-level installation](harness/pi/README.md#install-the-global-workspace-command), with
responsive conversation/observation panes, opt-in local child observation (TASK-00448 candidate), and an opt-in
human-only shared main-runtime log reader (TASK-00449 candidate). Earlier real PTY/provider/file-hook
checks, held-state terminal images and a passing exact-head baseline gate are recorded. Review's long-dialog
visibility repair passes focused checks and the coordinator's frozen-candidate full gate. Coordinator-owned real
native-selection/same-session resume qualification now passes, preserving conversation and actual workflow binding;
earlier helper failures remain recorded. The literal post-commit gate and fresh independent Review/QA remain
pending at this tracked checkpoint; the workspace runbook names the final ignored handoff for gate results and limits. Keep normal Pi for setup and native-only controls.
Branding does not enable managed TASK execution or sandbox access. The optional [deterministic tool policy](harness/pi/TOOL_POLICY.md)
requires separate operator selection on the qualified runtime; it is not Jev screening or sandbox isolation.

## Development

Docker Compose is required. This is an application with committed Composer lockfiles:

```sh
export FIGHT_AGENT_OS_USER="$(id -u):$(id -g)"
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/up
./bin/database migrate
```

The application root runs at http://localhost:18087 (authentication still requires the trusted HTTPS origin). Build the browser foundation with
`./bin/client setup`, `./bin/client storybook-setup`, `./bin/client check`, then `./bin/client build` and open
`/app`; see [client commands and boundaries](client/README.md).
`./bin/build` is the [complete read-only gate](docs/engineering/BUILD.md), including backend coverage and
frontend/browser checks without duplicate test executions. Dependencies and private OpenAPI/validation exports
need explicit setup first; acceptance never installs or exports. `./bin/backend-tests` remains a focused command;
see its [guarded lifecycle and reports](docs/engineering/BACKEND_TESTS.md). Override the port with `FIGHT_AGENT_OS_PORT`.
Before application boot, configure `APP_BROWSER_ORIGIN` as the exact trusted HTTPS origin and
`APP_CSRF_MAC_KEY` as an independent, external hex-encoded 32-byte-or-stronger random key
(e.g. generate with `openssl rand -hex 32`); do not commit either secret or use the JWT/HMAC signing key.
Also configure the external `APP_ACCESS_TOKEN_CONFIG` HS256 keyring before boot; follow
[access-token configuration](docs/engineering/ACCESS_TOKENS.md) for its exact shape and finite rotation bounds.
JWT issuance/consumption, request-scoped PostgreSQL authentication and a JWT-by-default matched-Action
API-v1 gate are available. The protected `GET /api/v1/me` returns safe identity and current role/permission
names without a dashboard permission prerequisite. Protected read-only role and permission catalogs are implemented at `GET /api/v1/roles`, `/roles/{roleId}`,
`/permissions` and `/permissions/{permissionId}`, pending independent Review/QA; see
[guarded authority catalogs](docs/engineering/AUTHORITY_CATALOGS.md) for exact permissions, pagination and redaction.
Guarded custom Role create/rename/remove APIs now carry exact displayed revisions and atomic safe audit, pending
independent Review/QA; no Role UI or Permission delegation is enabled. Read [custom Roles and v0.8.0 adoption](docs/engineering/CUSTOM_ROLES.md)
before upgrading an existing database: historical reset authority is never guessed or discarded.
Permission-protected invitation issuance and safe status are available at `POST /api/v1/invitations` and
`GET /api/v1/invitations/{user_id}`. Permission-controlled retry, resend and atomic pending-email correction
are available at `POST /api/v1/invitations/{user_id}/retry`, `POST /api/v1/invitations/{user_id}/resend`, and
`PATCH /api/v1/invitations/{user_id}/email`; see [invitation API](docs/engineering/INVITATION_API.md) for exact
permissions, confirmation, bounded atomic limits and retained partial/uncertain outcomes. Public `POST /api/v1/auth/activate` establishes an ordinary session by invitation possession with
Origin/CSRF/JSON/admission protections; see [activation contracts and limits](docs/engineering/ACTIVATION.md).
Configure independent external `APP_ACTIVATION_RATE_KEY` and `APP_AUTH_RATE_KEY` before HTTP boot.
The reusable [browser authentication request controls](docs/engineering/BROWSER_AUTH_REQUESTS.md) provide
fixed session/persistent refresh cookies, nonce lifecycle, strict browser guards and independently committed
rolling email/network/endpoint admission. Production-like HTTP boot requires explicit direct TLS authority,
no trusted forwarding proxies, and available migrated limiter storage. Canonical `POST /api/v1/auth/login` and `/app/login` are implemented; see [login contracts](docs/engineering/LOGIN.md). Cookie-only `POST /api/v1/auth/refresh` and `/api/v1/auth/logout`, plus current-session logout on the login success screen, are implemented pending independent Review/QA; see [session continuity](docs/engineering/SESSION_CONTINUITY.md). Bounded multi-tab coordination at `/app/login` is implemented pending independent Review/QA; TASK-00051 now mounts the responsive authenticated frame, pending independent Review/QA. The public `GET /api/v1/auth/csrf` bootstrap only works over that HTTPS origin. For opt-in local TLS at
**https://localhost:18443**, follow [local HTTPS setup](docs/engineering/LOCAL_HTTPS.md):
`./bin/https setup`, `./bin/https up`, then `./bin/https status`. Setup preserves your CSRF key and changes only
the browser origin in an ignored `.env`; host CA trust enrollment is explicit. Keep `FIGHT_AGENT_OS_PORT=18087`
for the separate HTTP listener. Neither local TLS nor the inherited HTTP port is a complete authenticated browser
deployment; integrated security and production enrollment remain separate work.
For PHP error logs, process limits and opt-in IDE debugging, see [local PHP diagnostics](docs/engineering/LOCAL_PHP.md).
Development and test PostgreSQL identities are separate; override their local-only Compose defaults through the
variables shown in `.env.example`. Destructive test operations require explicit test mode and a guarded `_test`
database, role, and allowlisted host. PostgreSQL migrations live in `database/migrations/`; future database
schemas and fixtures belong alongside `migrations/` under `database/`.
The shared [password-setting boundary](docs/engineering/PASSWORDS.md) enforces Unicode code-point composition,
checks a pinned local hashed common-password list and uses explicit Argon2id parameters. Application startup
verifies the corpus and platform support before use. Include `resources/passwords/` and its CC BY-SA 4.0 notice
in deployment artifacts. Activation consumes this boundary; reset/change consumers remain planned. It is not a standalone password endpoint.

Direct Fight Access Control invitation/reset delivery handlers now require an independent
`APP_CREDENTIAL_DELIVERY_KEY` (hex-encoded 32-byte random key, generated with `openssl rand -hex 32`)
for recoverable encrypted material. Keep it external to source control and preserve it while work is outstanding.
Local/test delivery defaults to a retryable null provider; an in-memory provider is opt-in. Invitation email can
explicitly use the disposable loopback Mailpit catcher with `APP_CREDENTIAL_DELIVERY_ADAPTER=catcher` and validated
`APP_ACTIVATION_ORIGIN`/`APP_ACTIVATION_PATH`, plus `APP_PASSWORD_RESET_ORIGIN`/`APP_PASSWORD_RESET_PATH`; see
[local invitation email](docs/engineering/INVITATION_EMAIL.md) and
[password-reset email](docs/engineering/PASSWORD_RESET_EMAIL.md) for exact `.env`, routing/fragment and
start/send/recovery/cleanup contracts. No real inbox or reset-completion UI is supplied. TASK-00054 adds the [generic password-reset request](docs/engineering/PASSWORD_RESET_REQUEST.md)
at `/app/reset-password` and `POST /api/v1/auth/password-reset`, pending independent Review/QA.
Reset mail now requires the bounded internal runner rather than synchronous request-time SMTP. The public activation UI requires the
configured canonical `/app/activate` path and the qualified fragment-only link shape described in the activation runbook.
A bounded internal
`./bin/credential-delivery run --internal` runner, key-free `expire --internal` cleanup and safe generation status
command are available. See [credential recovery](docs/engineering/CREDENTIAL_RECOVERY.md) for the v0.5.0 adoption
migration, data guards, bounded downtime-expiry cleanup and exit codes. There is no production provider or enrolled recovery schedule, so this does not
enable real-inbox delivery or establish complete production recovery. Production-like HTTP boot and delivery
preflight fail closed; Compose now consumes `APP_ENV` rather than hardcoding development.

Trusted operators can explicitly preview and apply the version-controlled installation authority with
`./bin/managed-policy preview --internal` and `./bin/managed-policy apply --internal`. Read the
[managed policy runbook](docs/engineering/MANAGED_POLICY.md) before applying: this reconciles the exact managed
catalog, not users, bootstrap, Swagger routes or live Agent operations. It never runs automatically.
After reconciliation, trusted operators can invite an ordinary pending user with
`./bin/console user:invite --internal --operator=OPERATOR_UUID --email=person@example.test`.
Read the [console invitation runbook](docs/engineering/CONSOLE_INVITATIONS.md) before use. Invitation and
`ROLE_USER` assignment commit independently: delivery may begin before assignment, and a later failure retains
the invitation. Safe partial output identifies the known target and corrective command. Trusted operators can add
an existing eligible role with `./bin/console user:role:add --internal --operator=OPERATOR_UUID --user=USER_UUID --role=ROLE_UUID`;
this is additive/idempotent and excludes authoritative Super Admin. Role confirmation audit commits separately,
with disclosed crash/uncertainty boundaries. TASK-00037 implementation awaits fresh independent Review/QA;
the earlier alternate-policy probe is conditional history, not a shipped fixed-catalog prerequisite.
This is not bootstrap/public registration; null/memory adapters send no email, while the opt-in catcher stores
local test invitation/reset mail only.
The separate `./bin/console user:bootstrap-super-admin --internal --confirm-bootstrap --operator=OPERATOR_UUID --email=person@example.test`
checks authoritative Super Admin presence and refuses if one exists or lookup fails. Run it manually, one operator
at a time; no concurrent admission guarantee is provided. Another manual attempt is allowed after partial/failure
when no Super Admin exists; retained same-email invitations still conflict normally. Read the
[bootstrap runbook](docs/engineering/SUPER_ADMIN_BOOTSTRAP.md) before confirming. Invitation, assignment and completion
audit commit independently; early delivery and retained partial state are deliberate, not an atomic rollback guarantee.
All three Console commands share the normal glob-loaded container and bus. Raw package dispatch is trusted internal
infrastructure; nontrusted role assignment must use the explicit authenticated Application use case. No direct HTTP/Agent
role-assignment endpoint or managed Agent CLI restriction is supplied here; invitation-specific authenticated
managed assignment is available through the separate invitation API.

```sh
./bin/database check       # Check development and guarded test PostgreSQL services
./bin/database status      # Inspect development migration status
./bin/database test-reset  # Guard, reset, and migrate only the dedicated test database
./bin/database test        # Run focused PostgreSQL repository tests
./bin/backend-tests        # Reset guarded test DB, run all backend suites once with coverage
./bin/planning-check --write  # Refresh planning views
./bin/planning-check          # Validate planning
./bin/build                   # Complete gate: backend coverage + frontend/catalog in running web service
./bin/down                    # Stop the development service
```

Export the attribute-owned [representative OpenAPI contract](docs/api/README.md) with `./bin/openapi export`,
then validate freshness and real responses with `./bin/openapi check`. Generate the public-safe
form catalog (including the real `invitation_activation` form) with `./bin/validations export` and verify it with `./bin/validations check`; see
[validation metadata](docs/api/VALIDATIONS.md). Generated JSON stays under ignored `var/openapi/` and
`var/validations/`, not public assets. Compiled browser assets live in `var/client/build/` and local TLS state
in `var/https/`. Application runtime never depends on `.runs/`. The [private Swagger viewer](docs/engineering/SWAGGER.md)
at `/app/swagger` requires an explicit exact environment allowlist and current server-side `READ_SWAGGER` for
both initialization and direct spec reads. `.env.example` starts with `["local"]`; absent/malformed settings disable
it. Independent strict APP_DEBUG supports only closed sanitized diagnostics and refuses true in prod/production.
No public spec, long-lived Swagger credential or production-mail exception is supplied. Full production startup
qualification remains explicitly deferred until the independent real-mail prerequisite exists.

Use the current project-owned `./bin/build` gate and [engineering standards](docs/engineering/STANDARDS.md).
Scratch and evidence belong under ignored `.runs/` subfolders; managed worktrees use approved dedicated roots
(such as `.runs/worktrees/`, never directly in `.runs/`). Runtime sandboxing remains planned, not implied by the development Compose stack.

See [architecture](ARCHITECTURE.md), [roadmap](planning/ROADMAP.md), and [scaffold origin](docs/ORIGIN.md).
The inherited starter retains its [MIT license](LICENSE).
