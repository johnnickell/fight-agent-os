# Define local runtime and shared ingress topology

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-016](WF-016-define-runner-dispatch-and-recovery.md), [WF-017](WF-017-define-execution-history-and-event-authority.md)

## Question

What complete local Compose and shared-ingress topology should run Agent OS, expose stable project hostnames, support container-native application and Agent work, and leave other registered project stacks independently operable?

## Must decide

- Installation-level versus application-level Compose ownership, including lifecycle, naming, profiles, health, restart, startup ordering, persistent volumes, secrets, and upgrade behavior.
- Initial Agent OS services and boundaries: Nginx, PHP-FPM, scheduler, ordinary PHP workers, dedicated Agent runner, PostgreSQL, Redis, Mercure, and the S3-compatible artifact service selected under WF-017.
- Default and configurable process/container scaling, with one scheduler container, job-level locking, ordinary-worker isolation, three one-shot Agent consumers by default, and no silent unbounded concurrency.
- A container-native command and full-gate contract with host `bin/*` wrappers, so running inside a prepared container never requires recursive Docker or a mounted Docker socket.
- Ownership of a durable external ingress network and automated reverse proxy, initially evaluating `nginx-proxy/nginx-proxy`; only project ingress containers join it while databases, caches, application runtimes, workers, runners, publishers, and storage remain private.
- Local hostname, HTTP/HTTPS, certificate and DNS behavior, including whether `*.localhost` avoids privileged setup and how custom domains become an explicit host-side operation without collecting sudo credentials in the browser.
- How Create Project can request or report ingress and hostname setup, preserve partial-failure evidence, and hand privileged work to an exact CLI command rather than mutating `/etc/hosts` from a container.
- Baseline structured logs, health and metrics versus deferred log/search/telemetry infrastructure; PostgreSQL Messenger versus any evidence-based future RabbitMQ need; and object-storage deployment without duplicating WF-017 retention policy.
- Whether the topology should reserve a safe future seam for isolated-worktree preview URLs without promising dynamic preview environments, wildcard routing, or per-worktree service orchestration in the initial runtime.

## Resolution boundary

This decision defines the local runtime, ingress, service, and operator boundary consumed by onboarding and project creation. It must not start or rebuild containers, create a Docker network, mount the Docker socket, edit host DNS or `/etc/hosts`, issue certificates, provision storage, expose a preview URL, or implement production configuration. Runner dispatch and sandbox authority remain settled by WF-016; execution artifact semantics remain with WF-017; the user journey that creates and registers a project remains with WF-021.

## Preferences required

John must choose the installation-level proxy and hostname experience, acceptable one-time privileged host setup, initial artifact service, and how much optional observability infrastructure belongs in the first complete stack. The recommendation should adapt the proven Fight CMS FPM/Nginx/cron/worker/Redis/Mercure pattern to PostgreSQL and Agent OS, keep the Agent runner free of Docker authority, isolate project-private services, prefer PostgreSQL Messenger before RabbitMQ, and defer speculative infrastructure until a concrete use case requires it.

## Resolution

### Installation and network ownership

One installation-level Compose project owns the complete Agent OS local runtime and shared ingress. Registered
project stacks remain independently operable: Agent OS neither starts nor stops them as an implicit side effect.
Only a project's public ingress container joins the installation-owned external ingress network; databases,
caches, application runtimes, workers, runners, and storage stay on private networks.

A Workspace may deliberately own shared runtime resources such as a database or cache. In that case it owns a
separate private external network, and only repository services that consume the resource join it explicitly.
This is a supported topology rather than an architecture violation. It does not merge the private network with
shared ingress or make Agent OS the hidden lifecycle owner of the participating stacks.

Use pinned `nginx-proxy/nginx-proxy` releases for automatic virtual-host discovery. Follow its supported
separate-container topology: public Nginx and `docker-gen` share generated configuration, while only the
non-public `docker-gen` container receives the read-only Docker socket required by the product. This confines
but does not eliminate Docker API risk; neither application Nginx, PHP, nor the Agent runner receives that
socket. Do not claim stronger isolation than the selected proxy supports.

### Hostnames, HTTPS, and privileged setup

Use standard ports 80 and 443, with HTTPS on 443 as the canonical browser origin. `<name>.localhost` is the
zero-host-mutation default. Also permit an operator to choose arbitrary local names such as `project.dev` or
`project.io`; explain that these are public DNS namespaces, warn about ownership and collision risk, and require
an explicit local DNS or hosts override plus a matching local certificate. `.dev` names necessarily use HTTPS
because browsers preload HSTS for that namespace.

Certificate creation, trust enrollment, local DNS or hosts changes, and removal are explicit, reversible
host-side operations. The Dashboard may diagnose state and prepare an exact command, but a human runs it in a
terminal and responds directly to any operating-system privilege prompt. Agent OS never collects sudo or
administrator credentials. The CLI is idempotent, reports each completed and failed effect, and provides status
and removal operations. The Dashboard verifies observed results and preserves partial-failure evidence instead
of reporting incomplete setup as successful.

### Complete local service graph

Normal `bin/up` starts the complete declared development stack, and `bin/down` stops it:

- shared public Nginx and its separate `docker-gen` discovery service;
- application Nginx and PHP-FPM;
- PostgreSQL, Redis, Mercure, and private single-node SeaweedFS in `weed mini` mode;
- one scheduler, one ordinary application worker, and one dedicated Agent runner;
- three bounded one-shot Agent consumers supervised inside that runner, as settled by WF-016; and
- a pinned development-only Mailpit service with private browser access and disposable mailbox storage.

Mailpit receives invitation and reset messages without external delivery or provider rate limits. It is not a
production fallback, and its credential-bearing test mailbox has explicit access and cleanup boundaries.
SeaweedFS is the concrete local S3-compatible backend for WF-017 Artifacts. It is private, pinned, backed by an
installation-owned durable volume, and accessed through the application-owned object capability rather than
public ingress. LocalStack may later support optional AWS-emulation tests, but its snapshot-oriented emulator
state is not authoritative Artifact storage. MinIO is not the initial default because its upstream community
repository and binary-distribution direction no longer provide the desired maintained local default.

PostgreSQL Doctrine Messenger remains the initial ordinary and Agent-workflow transport. RabbitMQ is deferred
until measured queue or routing needs justify another service. Redis serves only explicit cache, coordination,
session, or rate-limit roles assigned by later implementation; it does not replace PostgreSQL workflow,
planning, event, or queue authority.

### Commands and bounded processes

Host-facing `bin/*` files should normally be thin `docker compose exec -T ... "$@"` wrappers over PHP entrypoints
in `scripts/*` or approved executables in `vendor/bin/*`. The application console entrypoint is
`scripts/console.php`; a purpose-specific wrapper such as `bin/migrate` invokes
`php scripts/console.php doctrine:migrations:migrate "$@"`. `bin/phpunit` similarly invokes the approved PHPUnit
entrypoint. Business and orchestration logic does not live in shell wrappers.

`bin/build` runs PHP scripts and PHP tools inside the already running PHP-FPM container. The underlying scripts
remain Docker-free. Inside a prepared PHP-FPM or Agent-runner environment, container-native callers execute the
underlying scripts directly, so managed work never invokes recursive Docker. The Agent runner has no Docker
socket and cannot control its own or sibling containers.

Host-invoked `bin/composer` and `bin/npm` may create and remove pinned disposable Composer and Node tool
containers. No other ad hoc or persistent container joins the topology without John's explicit approval. The
browser suite runs through approved disposable tooling rather than a persistent browser service and is capped
at ten named end-to-end scenarios across at most four explicitly configured browser projects. The complete
matrix therefore permits at most forty scenario/browser executions. Keep the named inventory, projects, retries,
and quarantine policy explicit and inspect them directly; do not add product-suite tests or seeded failures for
browser configuration, wrappers, or build machinery.

Run one container for each application role initially, one scheduler process, one ordinary worker process, and
the accepted three one-shot Agent consumers. Counts may change only through explicit bounded installation
configuration; there is no autoscaling or silent unbounded concurrency. The scheduler uses job-level locks so
one scheduler container does not imply one global lock across unrelated jobs.

### Health, persistence, upgrades, and secrets

Pin every image and tool version. Use service health checks and dependency readiness rather than sleep-based
startup assumptions. Apply bounded restart policies for runtime failure, but never treat restart as an upgrade,
migration, or recovery decision. PostgreSQL, SeaweedFS, and any other state whose loss is not permitted use
installation-owned named volumes; Redis persistence follows its declared application role rather than being
assumed. Schema migrations, image upgrades, backup, restore, and rollback are explicit operator commands with
observable preconditions and results, never automatic container-start side effects.

Installation bootstrap generates local secrets into ignored owner-readable files or an operating-system
credential store. Compose gives each service only the material it requires. Secrets never enter Git, images,
browser forms, logs, Harness profiles, or Agent child environments. Rotation and verification are explicit
operations, preserving the credential-broker boundaries accepted in WF-016.

### Logging and deferred infrastructure

The initial stack emits structured logs to stdout and stderr with consistent service, correlation, Workflow,
and available actor identities. Compose health checks, application health and status queries, and durable
Workflow projections provide the initial operational view. Do not add Prometheus, Grafana, Loki,
Elasticsearch, OpenObserve, tracing, or another observability service yet.

Preserve adapters for a later Papertrail/syslog destination and for a separately selected open-source browser
log viewer. Any such service requires demonstrated need and explicit approval. A future log viewer does not
receive the Docker socket without a separate security decision.

Reserve only a collision-safe naming and metadata seam for future isolated-worktree preview URLs. This decision
does not promise dynamic preview stacks, wildcard preview routing, database cloning, or per-worktree service
orchestration.

### Boundary and follow-up

WF-020 may now define platform-specific onboarding, prerequisite checks, credential-store guidance, runner
enrollment, and the exact operator documentation for these commands. WF-021 owns the Create Project journey
and its application-level partial-effect model while consuming this topology. Exact Compose files, image tags,
health intervals, restart values, volume names, proxy labels, certificate utility, DNS implementation, secret
file formats, backup commands, SeaweedFS configuration, and UI components remain implementation choices within
these boundaries.

This decision starts no container, creates no Docker network or volume, exposes no port, edits no host mapping,
installs no certificate, stores no secret, migrates no schema, and creates no preview environment, EPIC,
requirement TICKET, or implementation TASK.
