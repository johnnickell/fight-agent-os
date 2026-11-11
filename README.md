<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/assets/fight-agent-os-dark.svg">
  <img src="docs/assets/fight-agent-os-light.svg" alt="FIGHT Agent OS" width="440">
</picture>

# Fight Agent OS

An open-source software factory: software to build software. Fight brings engineering planning,
agent-assisted implementation, independent review and delivery into a customized Pi terminal and a
browser dashboard.

## Project status

**Active development; not production-ready.** The application uses Slim/PHP, PostgreSQL and
React/TypeScript.

- **Application foundation:** authentication, invitation and password flows, session management,
  permission-controlled administration, and an authenticated application frame are implemented.
  Individual features have different verification and integration boundaries; this is not a claim
  of complete application readiness.
- **Planning:** Markdown records are authoritative, organized as EPIC → TICKET → TASK. Workspace
  bootstrap and Project registration APIs are implemented; browser Planning and database cutover
  remain planned.
- **Agent tooling:** the Pi package provides terminal presentation, workflow roles, local skills and
  stage coordination. Complete managed execution, isolation, accounting and recovery remain under
  development; a worktree or workflow role is not a security sandbox.
- **Local development:** Docker tooling, HTTPS, isolated worktree runtimes and optional local-domain
  registration are available. DNS, certificate trust and shared ingress require explicit setup.
- **Deployment:** production qualification and real email-provider integration remain unfinished.
  Local email testing uses a disposable catcher, not real inbox delivery.

See the [capability inventory](planning/FOUNDATION.md), [roadmap](planning/ROADMAP.md) and
[TASK Board](planning/tasks/BOARD.md) for scope and remaining work.

## Development setup

Use Git and Docker with Compose. Application tooling runs through the repository's `bin/` wrappers.
Local HTTPS also requires [mkcert and explicit trust enrollment](docs/engineering/LOCAL_HTTPS.md).

Create an ignored local configuration:

```sh
cp .env.example .env
chmod 600 .env
export FIGHT_AGENT_OS_USER="$(id -u):$(id -g)"
```

Before startup, configure independent secrets and the access-token keyring in `.env`; example values
are not credentials. Follow [access-token configuration](docs/engineering/ACCESS_TOKENS.md),
[browser request security](docs/engineering/BROWSER_AUTH_REQUESTS.md) and
[credential delivery](docs/engineering/CREDENTIAL_RECOVERY.md). Never commit secrets or reuse one key
across these boundaries.

Prepare dependencies and the application:

```sh
./bin/up --build-only
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/client setup
./bin/client build
./bin/https setup
./bin/up
./bin/database migrate
```

For an unenrolled checkout, the default HTTPS address is **https://localhost:18443**. Explicit
[local-domain enrollment](docs/engineering/LOCAL_DOMAINS.md) selects a separately prepared shared
HTTPS origin. Use the verified URL reported by startup. TLS setup changes local trust only through
explicit enrollment; ordinary startup does not install trust or generate certificates.

Prepare private metadata, then run the complete gate:

```sh
./bin/openapi export
./bin/validations export
./bin/build
```

`./bin/build` validates planning freshly and automatically reuses qualifying unchanged application
results. Use `./bin/build --force` for a deliberate fresh run. See the [build guide](docs/engineering/BUILD.md)
for prerequisites and verification boundaries.

Stop the main stack with `./bin/down`; its database data is retained. For isolated worktrees, follow
[local tooling](docs/engineering/LOCAL_TOOLING.md) and the [resource lifecycle](docs/engineering/RESOURCE_LIFECYCLE.md)
for explicit startup and disposable-runtime cleanup.

## Fight terminal

The optional [Pi package](harness/pi/README.md) adds Fight's terminal interface and workflow tools.
Follow its prerequisites, installation and profile-migration instructions before enabling it.
The [workspace guide](harness/pi/WORKSPACE.md) covers Herdr panes and Docker logs; ordinary Pi remains
available without the workspace integration.

## Documentation

- [Architecture](ARCHITECTURE.md) and [engineering standards](docs/engineering/STANDARDS.md)
- [Planning conventions](planning/CONVENTIONS.md), [Board](planning/tasks/BOARD.md) and [Wayfinder](planning/wayfinder/README.md)
- [Client development](client/README.md) and [API documentation](docs/api/README.md)
- [Local HTTPS](docs/engineering/LOCAL_HTTPS.md), [shared ingress](docs/engineering/LOCAL_INGRESS.md) and [local domains](docs/engineering/LOCAL_DOMAINS.md)
- [Human QA environments](docs/engineering/HUMAN_QA.md) and [project skills](.pi/skills/README.md)
- [Project origin](docs/ORIGIN.md)

## License

[MIT](LICENSE).
