# Define local runtime and shared ingress topology

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
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

Write this only when the Compose service graph, shared ingress ownership, container-native command contract, hostname setup, scaling defaults, and optional-infrastructure boundary are approved.
