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
  of complete application readiness. The integrated local security/accessibility baseline and five
  critical browser-platform smoke scenarios have independent acceptance; production qualification
  and later user/role administration remain separate.
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

Prepare service-specific mounted secrets, dependencies and browser assets. See the
[Planning runtime guide](docs/engineering/PLANNING_RUNTIME.md) for schema-before-traffic startup,
private storage, status, updates and backup/recovery. The `web` container is tooling only; all
application traffic uses Nginx/PHP-FPM. Mailpit and SeaweedFS have no public or host ports.

```sh
./bin/runtime setup
./bin/up --build-only
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/client setup
./bin/client build
```

### Choose how to serve HTTPS

Use one of the following setups. DockerProxy is optional and is operated separately from Fight.
Run Fight commands below from this repository's main checkout.

#### Direct localhost: no shared ingress or DockerProxy

For an unenrolled checkout, the simplest setup serves **https://localhost:18443**:

```sh
./bin/https setup  # Initial certificate/trust setup; may ask for your system password
./bin/up
```

For later starts, run only `./bin/up`. Do not use this setup to recover an already enrolled custom
hostname: direct `https setup` changes the browser origin to localhost. Use shared ingress below for
[enrolled local domains](docs/engineering/LOCAL_DOMAINS.md) and worktree HTTPS.

#### Shared ingress: initial setup

Choose who owns host ports **80 and 443**. Run only the setup command for your chosen mode:

```sh
# Without a separate DockerProxy: Fight owns loopback ports 80/443.
./bin/ingress setup --front-door independent
```

```sh
# With a separate DockerProxy: Fight publishes no host ports.
./bin/ingress setup --front-door external
```

Stop the previous owner before switching modes; two proxies cannot own the same host ports.
`setup` writes configuration and creates the shared network, but does not start services.

Shared ingress requires a trusted local CA and a certificate for the checkout's hostname. On macOS,
select Homebrew OpenSSL in each terminal used for shared ingress operations:

```sh
export FIGHT_INGRESS_OPENSSL=/opt/homebrew/opt/openssl@3/bin/openssl
```

On other systems, use an installed OpenSSL with hostname-verification support. Custom names such as
`local.fight-agent.dev` also need explicit [domain and DNS enrollment](docs/engineering/LOCAL_DOMAINS.md).
Prepare that enrollment before issuing the hostname certificate; shared ingress alone does not configure DNS.

For first-time trust and certificate preparation, run:

```sh
mkcert -install             # Once per machine; may ask for your system password
./bin/https setup --ingress # Issue the checkout certificate using that CA
```

Then use the startup sequence for your mode below.

#### Shared ingress: routine startup or recovery after a Docker reset

Existing certificates, local configuration and dependencies can be reused. If Docker's networks were
deleted, first repeat the `ingress setup` command for your chosen mode above.

**Without DockerProxy:**

```sh
./bin/ingress up --bind-ports
./bin/up --shared-ingress
```

**With DockerProxy** installed at `~/Projects/Infrastructure/DockerProxy`:

```sh
(cd ~/Projects/Infrastructure/DockerProxy && ./bin/proxy up)
./bin/ingress up --bind-ports
./bin/up --shared-ingress
```

The `--bind-ports` flag is required in both modes as explicit permission to start/recreate shared
ingress. In `external` mode it does **not** publish host ports or take 80/443 from DockerProxy.
The separate router must join `fight-local-ingress` and forward Fight HTTP/TLS to its supplier;
see [external-proxy integration](docs/engineering/LOCAL_INGRESS.md#external-proxy-front-door) and
DockerProxy's own README for its installation and DirectMeds integration.

If you already enrolled custom domains, start their DNS service separately with `./bin/local-dns up`.
An enrolled main checkout also selects shared ingress with plain `./bin/up`; the explicit flag above
makes the startup mode clear. Use the verified URL printed by application startup. A healthy proxy
alone does not mean the application is running.

### Database and validation

Before starting application traffic, start the private database and apply reviewed migrations:

```sh
./bin/database start
./bin/database migrate
```

Run this before either startup sequence above. Startup checks migration currency but never changes schema.

A Docker reset that removed database volumes also removed their data; migrations recreate schema,
not lost records. Restore any needed data from backups separately.

Prepare private metadata, then run the complete gate:

```sh
./bin/openapi export
./bin/validations export
# Direct localhost setup; shared origins use their existing public CA instead (see build guide).
./bin/browser-smoke setup --ca "$(mkcert -CAROOT)/rootCA.pem"
./bin/build
```

`./bin/build` validates planning freshly and automatically reuses qualifying unchanged application
results. Use `./bin/build --force` for a deliberate fresh run. See the [build guide](docs/engineering/BUILD.md)
for prerequisites and verification boundaries.

Stop the main stack with `./bin/down`; its database data is retained. For isolated worktrees, follow
[local tooling](docs/engineering/LOCAL_TOOLING.md) and the [resource lifecycle](docs/engineering/RESOURCE_LIFECYCLE.md)
for explicit startup and disposable-runtime cleanup.

## Comparison configuration

[Comparison scope and host settings](docs/engineering/COMPARISON_SCOPE.md) defines the shared starter list,
complete local replacement and explicit-selection syntax. The host-only `./bin/env PROJECT_BASE`
returns one supported setting without Docker; configure it explicitly as described there.
Use the local [compare skill](.pi/skills/compare/SKILL.md) with `/skill:compare` or
`/skill:compare --projects project-slim,project-yii` for bounded peer analysis and an offline HTML report.
Request optional PDF export or Desktop copies separately. Reports and project-owned proposals are not approved
work, independent acceptance or source repairs. See the [skill boundary and adoption notes](.pi/skills/README.md).

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
