# Define runner dispatch and recovery

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-008](WF-008-establish-repository-identity-and-registration-boundaries.md), [WF-015](WF-015-define-coordinated-task-authority-protocol.md)

## Question

How should a browser-created durable workflow job be claimed and executed by an authorized local runner that owns repository, Git, Docker, Pi, and tool access independently of the browser tab?

## Must decide

- Runner identity, enrollment, authentication, capabilities, health/availability, disable/revoke behavior, and least-privilege permissions.
- Repository-to-checkout mapping using WF-008 identities, including validation, multiple checkouts, worktree roots, branch ownership, and unavailable/moved checkout behavior.
- Durable job creation, dispatch, atomic claim/lease, heartbeat, progress, cancellation, deadline, retry, reconnection, restart, and orphan recovery semantics.
- Ownership split among application workflow, runner process, coordinator, Pi subprocess/session, and browser conversation.
- Safe command/tool environment, credential scope, network authority, Docker/service ownership, secret redaction, and evidence/artifact upload boundaries.
- Exactly what browser closure, runner shutdown, host reboot, and Pi crash do to authoritative job and claim state.
- Human intervention, pause, takeover, resume, and terminal-failure behavior without duplicate work.

## Resolution boundary

This decision defines the job/runner protocol and operational authority. It must not enroll a runner, start a daemon, map a live checkout, change host/global configuration, create containers, or dispatch a workflow. Detailed execution-event storage belongs to WF-017.

## Preferences required

John must choose the initial trust model for a personal local runner, whether enrollment is one-time or frequently confirmed, and what survives host restarts. The recommendation should begin with one explicitly enrolled local runner, durable application-owned jobs, leased claims with heartbeats, and no browser-process ownership of execution.

## Resolution

### Container-native runner and identity

Run Execution inside a dedicated `agent-runner` Compose service built from the application's PHP CLI runtime with Git, Pi, the trusted Harness, sandbox support, and container-native repository tooling. It has no Docker socket and does not manage its own container, sibling containers, or the host Compose lifecycle. A command that requires rebuilding or reconfiguring that boundary stops for an explicit operator action. Host wrappers may enter or start containers; managed Agent sessions use container-native commands.

One explicitly enabled runner service is the MVP. Installation bootstrap creates one durable `RunnerId`; recreating its container or disposable PHP consumers does not require pairing again. The service declares its Pi/Harness and sandbox versions, approved workspace roots, available tools, network and artifact capabilities, and configured concurrency, then emits health and availability heartbeats. Capability or version mismatch leaves work queued with an understandable reason. Disablement, halt, credential rotation or revocation, and de-registration preserve the Runner identity and history rather than deleting them.

Supervisor starts three Agent-workflow consumers by default, configurable through installation environment because it determines process topology. Each consumer handles at most one workflow Command, exits even after success, and is restarted by Supervisor. A long workflow may keep that one PHP process alive for its duration but never reuses it for a second workflow. The service has one identity and credential boundary; container boots, consumer processes, workflow leases, and process attempts receive generated instance identities for recovery and attribution without separate enrollment.

The runner is deterministic infrastructure, not an Agent. Coordinator, Builder, Reviewer, and Publisher retain the distinct Agent identities and sessions established by WF-015.

### Durable dispatch and admission

Browser and console entry points invoke the same application start operation. The operation atomically validates the WF-015 grant and current TASK eligibility, creates the Workflow and TASK claim, and records a durable dispatch intent before returning the Workflow identity. HTTP returns `202 Accepted`; a console caller prints the same identity and may follow the authoritative query. Neither browser, console, Mercure connection, nor initiating PHP request owns execution.

A reconciler submits one Workflow-ID-bearing Command through a dedicated Fight Common `AsynchronousCommandBus` to a PostgreSQL Doctrine Messenger transport. Agent workflow and failed queues are separate from ordinary application work. Delivery is at least once; Messenger keepalive prevents premature redelivery of a live long-running Command, while an application workflow lease independently controls who may advance authoritative state. Queue rows, failed-transport placement, logs, and Mercure messages are never workflow authority. An uncertain dispatch is reconciled from the durable intent before sending again.

Workflows may be queued while no eligible runner is online or enabled. They remain visibly `Queued — waiting for an available runner`; execution is never implied. Cancellation before delivery makes a later transport delivery an idempotent no-op.

The scheduler admits at most three workflows concurrently by default. Different repositories and independent TASKs in the same repository may run together when they own distinct branches, isolated worktrees, temporary paths, Pi sessions, and artifact namespaces. Do not hold a repository-wide lock for a workflow's duration. Short per-checkout locks protect fetch, worktree creation/removal, pruning, and other shared Git administration. Repository policy may declare narrower resource claims for non-isolated databases, ports, preview names, services, or other shared state. A temporarily blocked command does not prevent another safe queued workflow from filling capacity.

### Checkout and process custody

Only explicitly approved host workspace roots are mounted into `agent-runner`, preferably at identical absolute paths, and never the complete home directory. Mounts are the physical authority boundary: application checks cannot hide one mounted path from a compromised container process. Before work, the runner resolves the WF-008 Host, Checkout, Worktree, and Repository identities; canonicalizes paths; verifies checkout markers, Git common-directory evidence, ownership and containment; and rejects ambiguity, symlink escape, a moved or unavailable checkout, or an unapproved root.

One active workflow owns one branch and isolated worktree. Pi peer sessions receive a workflow-specific filesystem view rather than unrestricted access to every mounted root: their worktree and TASK-owned artifacts are writable, required Harness and runtime content is read-only, temporary storage is private, and unrelated roots, worktrees, environment files, credentials, and process state are unavailable. Git worktrees still share object and reference storage, so this is deliberate accidental-damage containment rather than a claim of hostile multi-tenant isolation. Strong hostile isolation would require separate clones and container or VM boundaries.

The Runner launches Pi through a supervised process capability that exposes process groups, attempts, output callbacks, cancellation, and terminal outcomes. Fight Common's current blocking `ProcessRunner` and safe `ProcessBuilder` are useful starting seams but do not yet provide the required durable handle, heartbeat, adoption, or cancellation behavior; implementation may evolve a policy-neutral Fight Common capability or compose an application-owned supervisor without moving workflow policy into the adapter.

### Sandboxed tools, credentials, and network

Managed Pi sessions keep `read`, `edit`, `write`, and `bash` because coding work requires them, but trusted Harness extensions canonicalize file paths, observe tool calls, and wrap shell execution in an OS-enforced per-workflow sandbox. Linux may use a proven bubblewrap-based adapter such as the pattern supported by Pi's sandbox example. Managed execution fails closed when the configured sandbox cannot initialize and offers no model-selectable bypass. Local build and test commands remain observable Pi tool calls.

General external effects flow through permission-filtered Agent OS tools rather than raw child credentials: MCP, web search and retrieval, Git-provider publication, artifact transfer, and other authenticated APIs. General network access from sandboxed bash is denied by default. Package-manager traffic may use an installation-controlled egress proxy or explicit destination policy; Pi model-provider transport is infrastructure traffic and its usage remains observable through Pi events. `agent-runner` exposes no inbound Agent-session port.

Initial web search is a first-party Agent OS MCP tool mapped to an application Query. Its handler validates bounded count, filters, pagination, Permission and workflow context, then invokes an injected web-search capability whose Adapter uses the HTTP client and an application-held Brave Search API key. Direct Brave MCP or API access is not granted to Pi. Results retain source and provider provenance; large/raw results use artifact references. Page retrieval remains a separately permissioned, SSRF-protected operation.

The runner never passes its database, Messenger, Mercure, provider, or application secrets to Pi. A runner-owned credential broker binds a protected channel to the actual Workflow, Agent, profile revision, and Pi Session selected at launch. It performs complete authorized MCP requests and HMAC signing for that Agent; it neither accepts a caller-asserted identity as authority nor offers arbitrary-byte signing. Raw Agent secrets never enter child environment variables, files, transcripts, events, logs, artifacts, or command output. The server still authenticates every request, applies direct Agent Permissions and the live workflow grant, and honors credential rotation or revocation immediately. Publisher-only source-host authority is unavailable to Builder and Reviewer sessions.

### Heartbeats, interruption, retry, and recovery

Before each Pi launch the workflow persists a process-attempt identity, phase, Agent and session, checkout/worktree and command descriptor, redacted environment manifest, start time, lease owner, and current checkpoint. PID and process-group evidence are added after spawn. Output is redacted and checkpointed before it becomes an observational update; large output belongs in durable artifact storage by reference. Mercure publishes only committed projections, and browser disconnect has no execution effect. WF-017 owns exact event, log, artifact, and projection schemas.

Queued work survives application, runner, and host restarts. A graceful shutdown stops new claims, requests orderly Pi abort, records the latest checkpoint, and returns unfinished work for redelivery. Abrupt loss leaves the durable Workflow, attempt, worktree, Pi session reference, last heartbeat, and unacknowledged Command. After both transport and application leases establish abandonment, a replacement consumer reconciles process absence, Git/worktree state, session material, checkpoints, and known external effects before resuming the same Workflow. It never launches a duplicate while an earlier outcome is uncertain. Safe recovery resumes automatically; uncertainty enters Needs Human.

A Pi phase allows at most three process attempts by default: one initial launch and up to two safe resumptions. `maximum_process_attempts` is an authorized database-backed installation setting and each Workflow snapshots its effective value when queued, so a later setting change does not expand active work. Authentication, Permission, configuration, exhausted budget, missing evidence, or uncertain-side-effect failures do not retry blindly. Technical process attempts are separate from WF-015 review revision cycles. Exhaustion retains the claim and evidence in Needs Human until an authorized resume, cancellation, or terminal decision.

A deadline or cancellation request is authoritative even if delivery to the child is delayed. The runner sends orderly termination to the complete process group, allows a bounded grace period, then forces termination if needed and records what was observed. A Workflow claim is released only after all active work is stopped or proved absent.

### Operational controls and human recovery

The controls have distinct meanings:

- **Pause Workflow** stops its child work, preserves its TASK claim, worktree, branch, Pi sessions, attempts, and evidence, and permits explicit resume.
- **Cancel Workflow** stops it, records the reason, and releases its TASK claim only after quiescence. Recoverable work remains until explicit ownership-checked cleanup.
- **Disable Runner** prevents new claims while active workflows drain normally.
- **Halt Runner** prevents new claims and requests safe pause of its active workflows.
- **Disable All Runners** closes installation-wide claiming while active workflows drain and queued submissions remain allowed.
- **Halt All Runners** closes installation-wide claiming and requests safe pause of all active workflows.

Installation-wide controls are authoritative gates, so a later-enrolled Runner cannot bypass them. Credential revocation and rotation remain separate security operations from the user-facing Halt action. An authorized takeover always resumes the same durable Workflow and consumes its existing limits; it does not manufacture a second TASK attempt. Direct failed-queue replay cannot bypass reconciliation.

Terminal failure or interruption never silently deletes a branch, worktree, Pi session, log, or artifact. Successful publication cleanup remains WF-015 Publisher authority; cancelled or failed resources require an explicit, ownership-proven cleanup operation. The Dashboard and console expose the current owner, runner health, last heartbeat, queued/running/paused reason, attempts and remaining limits, recovery decision, and next human action without claiming unavailable precision.

### Follow-up boundary

WF-017 owns execution aggregate and event authority, history, transcript and artifact retention, and Dashboard projections. WF-020 owns guided installation, credential-store and runner enrollment instructions. WF-021 owns Create Project filesystem/source/registration effects. [WF-023 — Define local runtime and shared ingress topology](WF-023-define-local-runtime-and-shared-ingress-topology.md) owns the complete Compose service graph, shared reverse proxy/network, container-native command contract, hostname setup, and deployment of the artifact service selected by WF-017.

This decision creates no queue, Runner, container, mount, process, credential, network, host entry, database setting, Brave key, artifact, worktree, Agent session, or production implementation. Exact schemas, command names, lease and grace durations, Supervisor syntax, sandbox package, egress proxy, health intervals, and UI controls remain implementation choices inside these boundaries.
