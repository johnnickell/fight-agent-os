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

## Provisioner and model broker handoff

Implement these capabilities through the existing PHP ownership boundaries, after TASK-00138 settles their
remaining enrollment/profile decisions. The synthetic Pi fixture establishes transport feasibility; it supplies
no production grant, authenticated operator or recoverable Runner. Capability names below describe cohesive
contracts, not a requirement to introduce speculative classes before their use cases.

| Capability | Trusted input | Result and uncertainty handling |
|---|---|---|
| TaskEligibility | Enrolled operator, selected TASK/dependency/context revisions and requested grant | An immutable eligible snapshot or a specific denial; revalidate when claiming, never accept client-authored eligibility. |
| SandboxProvisioner | Persisted effect ID, Workflow/role ownership, exact source OID and server-selected immutable profile | Observed runtime identity and resource inventory. After timeout, reconcile the original effect; never blindly create a second VM. |
| SandboxReadiness | Persisted ownership and observed VM, source, services, network, credentials and limits | Evidence bound to profile and attempt generation, or an actionable failure. VM start alone does not mean database, broker or source readiness. |
| AgentLauncher | Current fenced attempt, verified readiness, pinned Harness/Pi inputs and bounded role grant | Process/session identity persisted before accepting progress. Unknown liveness requires reconciliation; worker reports cannot establish their own authority. |
| ModelRequestAuthorizer | Broker-authenticated launch binding, current Workflow/Agent/profile/PiSession/attempt and requested operation | One permitted request with provider/model/reasoning/budget limits, or denial before upstream dispatch. Public placeholders and worker-supplied account/role headers are never authority. |

The protected broker owns real access/refresh tokens and account headers. It must derive routing from the grant,
reject arbitrary URLs and disallowed model changes, bound payload/decompression and stream resources, redact
secrets, and stop admitting requests for revoked or stale attempts. Define how already admitted streams terminate
on cancellation and measure that behavior; deleting a proxy secret alone is insufficient. No fallback to global
credentials or another model/provider is permitted when scoped readiness fails.

The inspected Pi 0.87.1 native Codex path expects OAuth-shaped storage and parses an account claim before sending
a request. Its successful fixture used a synthetic record and explicit SSE, not actual OAuth or refresh.
Production must qualify a broker-managed credential adapter and renewal protocol that never stores real tokens
inside the worker; it must not turn the fixture's artificial expiry into a long-lived authorization mechanism.
See the [measured credential result](DOCKER_SANDBOX_QUALIFICATION.md#pi-transport-and-credential-substitution-result)
before choosing the adapter. Host authentication readiness and worker grant readiness are separate checks.

Implement command behavior and durable effect reconciliation in TASK-00139/00140, then Pi delegation in
TASK-00141. Direct infrastructure probes remain qualification evidence; do not turn the disposable probe scripts
into a second production runner or add tests that merely inspect their configuration text.

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

1. Accept and enforce the complete storage profile. The Docker Sandboxes follow-up below observed bounded
   VM-local disks and successful reclamation; configurable workspace limits and aggregate host storage remain
   unqualified. A normal host bind mount or Docker volume name supplies no per-Workflow hard quota.
2. Qualify controlled Git ingestion/export. Private Git administration worked in synthetic fixtures. Keep shared
   refs and sibling metadata outside the Agent; enforce exact ancestry, allowed refs and bounded untrusted input.
3. Implement and qualify the provider/dependency broker, role credentials and authenticated operator enrollment.
4. Exercise provisioning/launch crash points, process fencing, quiescence and ownership-checked retirement through
   the production PHP path. The disposable probe is not a recovery implementation.
5. Qualify native Linux separately. Unsupported platforms/profiles must remain unavailable; no host-shell fallback.

## Bounded storage and private Git experiment

The follow-up on 2026-09-26 evaluated a fixed-size 256 MiB, case-sensitive APFS disk image mounted into a
networkless container. The disk contained a private bare Git repository and a real linked worktree, seeded from
a synthetic fixture bundle. Git could create an implementation commit and export a verified bundle without
mounting the human checkout, its common Git directory or sibling worktrees. A replacement container could read
that commit from the still-mounted disk. This was container replacement, not disk remount or Runner recovery.

The storage lifecycle **failed qualification**. After the fill exercise and deletion, a fresh write returned
ENOSPC before writing any bytes. Normal disk detach failed with Resource busy even after the container was
removed. Open-file inspection showed the macOS virtualization process retaining handles to the mounted files.
These observations are consistent with retained filesystem state, but do not establish its precise cause or a
usable capacity threshold. The planned second disk and remount recovery checks were not reached.

Cleanup verified the image path/device and absence of owned containers, then forcibly detached and removed only
the disposable fixture disk. This rescue operation is not an accepted production retirement strategy. Docker
Desktop and unrelated containers were not restarted. Raw evidence and the failed result are retained under
`.runs/task-00138/storage-study/fight-storage-9710c285f9/`; the owning probe and reconciliation scripts are beside
that directory. No application source or human Git refs were mounted or modified by the experiment.

Retain **one private Git store per Workflow** as a candidate: the Engineer can use ordinary Git inside its own
boundary, while PHP alone seeds approved objects and imports an explicitly named handoff. Shared canonical refs
remain outside the sandbox. Before adoption, qualify bounded bundle/object ingestion, exact base ancestry,
allowed refs, hook/config isolation and untrusted-content handling. A valid bundle alone does not authorize an
import or prove the submitted tree is safe. This is an alternative to a commit broker, not an expansion of Agent
authority over the canonical repository.

The next experiment used VM-local storage through Docker Sandboxes. Read the
[candidate profile and measured results](DOCKER_SANDBOX_QUALIFICATION.md) before implementing this backend.
Two independent VMs passed synthetic capacity exhaustion/reclamation and Git/database persistence checks,
including container and VM restart. The probe used private Git stores and private in-VM Docker engines, with no
host workspace mount or model credentials. Host backing storage remained allocated after guest deletion, so
host accounting and retirement are distinct requirements. This candidate's sudo/private-engine model also needs
an explicit profile decision; it does not silently replace the restricted-container contract above.

The current candidate therefore remains **unqualified for autonomous Agents**. TASK-00138 stays in progress;
TASK-00139–00142 remain dependency-blocked. Pinned Pi now passes synthetic SSE, host secret substitution and
revocation checks. Next complete real-provider/broker qualification and settle the VM profile and operator
enrollment, then implement the production PHP lifecycle against those boundaries.

Docker's [security guidance](https://docs.docker.com/engine/security/) describes namespace/capability boundaries
and the powerful daemon API; [rootless mode](https://docs.docker.com/engine/security/rootless/) reduces daemon/runtime
privilege when supported. These references inform the candidate, while local observations determine qualification.
