<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/assets/fight-agent-os-dark.svg">
  <img src="docs/assets/fight-agent-os-light.svg" alt="FIGHT Agent OS" width="440">
</picture>

# Fight Agent OS

A personal AI operating system, starting with software engineering: plan in the browser, build in a customized
Pi terminal, and understand the work through a companion dashboard.

**Status: application foundation in progress.** Slim/PHP, guarded PostgreSQL persistence, identity/grant storage,
API validation/error handling and local engineering skills are present. These pieces do not yet constitute the
complete authenticated browser journey. React UI, managed Pi execution and database-authoritative Planning remain
planned. See the [capability inventory](planning/FOUNDATION.md).

## Start planning

Read the [foundation brief](planning/FOUNDATION.md), [planning conventions](planning/CONVENTIONS.md), and
[TASK Board](planning/tasks/BOARD.md). Use your preferred planning skills in the terminal; saved artifacts follow
**EPIC → TICKET → TASK**, with dependency-ordered SUBTASKs during implementation.

Fresh project skills live in [.pi/skills/](.pi/skills/README.md). Markdown remains authoritative until an
explicit, verified migration to the future project-scoped planning database.

## Fight terminal

Install the approved Fight welcome and compact working header for all your Pi sessions:

```sh
pi install ./harness/pi
```

Restart Pi, then use `/fight-header` to choose a presentation or `off` to restore its default header.
See the [package instructions](harness/pi/README.md) for quiet/ASCII modes and removal.
The [terminal-first workflow](docs/engineering/TERMINAL_EXECUTION.md) is under implementation; branding does not
enable managed TASK execution or sandbox access.

## Development

Docker Compose is required. This is an application with committed Composer lockfiles:

```sh
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/up
./bin/database migrate
```

The inherited root endpoint runs at http://localhost:18087. Override the port with `FIGHT_AGENT_OS_PORT`.
Before application boot, configure `APP_BROWSER_ORIGIN` as the exact trusted HTTPS origin and
`APP_CSRF_MAC_KEY` as an independent, external hex-encoded 32-byte-or-stronger random key
(e.g. generate with `openssl rand -hex 32`); do not commit either secret or use the JWT/HMAC signing key.
The public `GET /api/v1/auth/csrf` bootstrap only works over that HTTPS origin. The inherited local
HTTP port is **not** an authenticated browser deployment; HTTPS/proxy enrollment is separate work.
Development and test PostgreSQL identities are separate; override their local-only Compose defaults through the
variables shown in `.env.example`. Destructive test operations require explicit test mode and a guarded `_test`
database, role, and allowlisted host. PostgreSQL migrations live in `database/migrations/`; future database
schemas and fixtures belong alongside `migrations/` under `database/`.

```sh
./bin/database check       # Check development and guarded test PostgreSQL services
./bin/database status      # Inspect development migration status
./bin/database test-reset  # Guard, reset, and migrate only the dedicated test database
./bin/database test        # Run focused PostgreSQL repository tests
./bin/planning-check --write  # Refresh planning views
./bin/planning-check          # Validate planning
./bin/build                   # Complete gate in the running web service
./bin/down                    # Stop the development service
```

Use the current project-owned `./bin/build` gate and [engineering standards](docs/engineering/STANDARDS.md).
Scratch and evidence belong under ignored `.runs/` subfolders; managed worktrees use approved dedicated roots
(such as `.runs/worktrees/`, never directly in `.runs/`). Runtime sandboxing remains planned, not implied by the development Compose stack.

See [architecture](ARCHITECTURE.md), [roadmap](planning/ROADMAP.md), and [scaffold origin](docs/ORIGIN.md).
The inherited starter retains its [MIT license](LICENSE).
