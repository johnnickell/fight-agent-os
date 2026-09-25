<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/assets/fight-agent-os-dark.svg">
  <img src="docs/assets/fight-agent-os-light.svg" alt="FIGHT Agent OS" width="440">
</picture>

# Fight Agent OS

A personal AI operating system, starting with software engineering: plan in the browser, build in a customized
Pi terminal, and understand the work through a companion dashboard.

**Status: planning-ready scaffold.** The application currently contains an inherited Slim/PHP foundation.
The React client, Swagger UI, application authentication, Pi workflows, and database-backed planning are not built yet.

## Start planning

Read the [foundation brief](planning/FOUNDATION.md), [planning conventions](planning/CONVENTIONS.md), and
[TASK Board](planning/tasks/BOARD.md). Use your preferred planning skills in the terminal; saved artifacts follow
**EPIC → TICKET → TASK**, with dependency-ordered SUBTASKs during implementation.

Fresh project skills will live in [.pi/skills/](.pi/skills/README.md). Markdown remains authoritative until an
explicit, verified migration to the future project-scoped planning database.

## Development

Docker Compose is required. This is an application with committed Composer lockfiles:

```sh
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/up
```

The inherited endpoint runs at http://localhost:18087. Override the port with `FIGHT_AGENT_OS_PORT`.
LocalDevelopment domain enrollment and the production/runtime topology remain to be planned.

```sh
./bin/planning-check --write  # Refresh planning views
./bin/planning-check          # Validate planning
./bin/build                   # Complete gate in the running web service
./bin/down                    # Stop the development service
```

Runtime, PHP tests, and dependency qualification come from the starter; its full gate is not yet an Agent OS
quality standard. Scratch, evidence, and worktrees belong under ignored `.runs/` subfolders.

See [architecture](ARCHITECTURE.md), [roadmap](planning/ROADMAP.md), and [scaffold origin](docs/ORIGIN.md).
The inherited starter retains its [MIT license](LICENSE).
