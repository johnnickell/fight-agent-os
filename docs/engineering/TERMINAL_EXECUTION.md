# Terminal-first execution contract

Implementation handoff for [TICKET-00032](../../planning/tickets/00032-TICKET.md) and
[TASK-00138](../../planning/tasks/00138-TASK.md). The sequence is approved; the backend below is a candidate under
qualification, not an enabled Agent sandbox. No autonomous TASK execution is available yet.

## Ownership and entry points

The console and future browser call the same PHP application operations. Pi is a client and worker. It does not
own claims, grants, process fencing, sandbox policy or the authoritative transition to Awaiting review.

| Owner | Responsibility |
|---|---|
| Domain / Execution / Workflow | Grant, TASK claim, phase eligibility, attempts and terminal state |
| Domain / Execution / Sandbox | Resource ownership and accepted isolation profile identity |
| Application / Execution | Start, inspect, reconcile, pause, cancel and accept implementation handoff |
| Adapter / Console / Execution | Explicit operator input and safe status/error presentation |
| Adapter / Persistence / Execution | PostgreSQL transactions, event-store integration, dispatch and projections |
| Adapter / Runner / Provisioning | Constrained Git/container operations and observed ownership/readiness |
| Adapter / Runner / Pi | Distinct role sessions, brokered capabilities and process/session lineage |
| Harness / Pi | Presentation and typed client integration; no Docker or host-shell provisioning authority |

These are responsibility boundaries to guide implementation, not instructions to create empty classes. Keep
capabilities such as TaskEligibility, SandboxProvisioner and AgentLauncher small and use-case driven. Design the
public commands, safe results and denial reasons before persistence/transport code.

Until cutover, a Markdown adapter supplies immutable TASK, dependency and effective-context revisions. Planning
authority stays in Markdown. PostgreSQL owns runtime claims, grants, Workflow events and durable effect intents.
Re-read the selected revision and dependencies while acquiring a claim; stale or conflicting input rejects start.
The database Planning adapter later replaces the read source without replacing Workflow orchestration.

An enrolled operator explicitly grants one TASK, repository/base, permitted roles/tools, limits and cleanup
policy. The console authenticates that enrollment; possession of a shell or a role name is insufficient. Managed
Agents use separate package-owned identities and bounded credentials. The exact enrollment/credential protocol
must be accepted and implemented before TASK-00139 can launch work.

## Recoverable lifecycle

Start validates authority and eligibility and atomically records the Workflow, exclusive claim, idempotency key
and dispatch intent. The Runner resolves the configured base to an exact OID, prepares an owned worktree and
requests an immutable provisioner profile. Each external effect follows persisted intent → effect → observed
identity → authoritative completion. A name alone is never proof of ownership or success.

Verified readiness precedes Agent launch. Every attempt has a fencing generation; stale processes cannot report
completion after takeover. An ambiguous live process pauses reconciliation rather than starting a second Agent.
Pause preserves resources. Cancellation proves workload stop. Cleanup checks durable ownership and policy and
preserves required evidence; it does not reset, stash, rebase or delete a human checkout.

Team Lead is the human contact and delegates the bounded implementation to Software Engineer. Their credentials,
tools and sessions are separate. The Engineer submits an exact-commit handoff with required check exits and
evidence. PHP validates it and transitions to Awaiting review. Senior Engineer, QA and Release Manager later
extend this same Workflow. They are not silently activated by installing the branding package.

## Local candidate and measured evidence

On 2026-09-26, Docker Desktop on linux/aarch64 reported built-in seccomp and cgroup namespaces, but did not report
rootless mode. A bounded experiment created two concurrent fixture environments, each with a separate source
directory, PostgreSQL container, temporary data and Unix-socket volume. Both application containers used:

- Network mode `none`, no published ports, no Docker socket or host home.
- Non-root UID 65532, read-only root filesystem, no effective Linux capabilities, no-new-privileges and seccomp.
- One CPU, 256 MiB memory, 64 processes and a 16 MiB private temporary filesystem.
- Only their own fixture source and PostgreSQL socket mounted; each database had separate credentials/data.

Both could write their own source and query their own database marker. Neither could read the sibling fixture
through a host-path symlink, see the host home or Docker socket, write the root filesystem, or connect to the
external/host endpoints probed. The observed mount inventory and limits matched the requested application profile.
Four owned containers and two socket volumes were removed after checking their unique ownership labels.

Raw local evidence: `.runs/task-00138/qualification.json` and `qualify.py`. This was direct tooling qualification,
not a Workflow, real TASK implementation, product test suite or proof of arbitrary escape resistance. The fixture
directories remain as evidence. The host connectivity check covers the attempted endpoint, not a port scan.

This supports evaluating **networkless execution with per-Workflow Unix sockets** for PostgreSQL. It does not
yet settle provider/package traffic. A scoped broker must mediate model requests and permitted dependency fetches;
the Agent must not receive installation provider credentials or unrestricted host/network access. Broker design
and qualification are required before accepting the profile.

## Remaining launch blockers

1. Bound writable source storage. A normal host bind mount has no per-Workflow hard disk quota; temporary-volume
   limits do not solve that. Qualify a quota-backed filesystem/VM disk or another enforced bound before launch.
2. Isolate Git administration. A normal worktree's `.git` pointer exposes shared administration when its target is
   mounted. Keep shared refs and sibling metadata outside the Agent and qualify a constrained commit/diff broker.
3. Implement and qualify the provider/dependency broker, role credentials and authenticated operator enrollment.
4. Exercise provisioning/launch crash points, process fencing, quiescence and ownership-checked retirement through
   the production PHP path. The disposable probe is not a recovery implementation.
5. Qualify native Linux separately. Unsupported platforms/profiles must remain unavailable; no host-shell fallback.

The current candidate therefore remains **unqualified for autonomous Agents**. TASK-00138 stays in progress;
TASK-00139–00142 remain dependency-blocked. The next engineering decision is the bounded source-storage and Git
view design, not a model prompt or a broader Docker mount.

Docker's [security guidance](https://docs.docker.com/engine/security/) describes namespace/capability boundaries
and the powerful daemon API; [rootless mode](https://docs.docker.com/engine/security/rootless/) reduces daemon/runtime
privilege when supported. These references inform the candidate, while local observations determine qualification.
