---
id: TICKET-00032
epic: EPIC-00007
title: Start and recover TASK execution in an isolated local workspace
status: ready-for-agent
---

# Start and recover TASK execution in an isolated local workspace

## Problem statement

John needs the Agent to work autonomously inside a verified local boundary without risking human checkouts,
parallel TASKs or installation secrets. A separate worktree or Compose project name alone is insufficient.

## Solution and boundaries

Deterministic PHP owns the following recoverable sequence; Agents never generate its host-privileged commands.
The approved Engineer profile uses a dedicated, mountless VM; the entire guest is Engineer-controlled:

1. Authorize the TASK revision, grant, dependencies and Runner capabilities; atomically claim and persist dispatch.
2. Resolve an exact base commit, create an owned branch/worktree under an approved root and persist identity.
3. Ask a constrained installation-owned provisioner for a role-specific VM, external limits/network policy and
   approved immutable inputs. Engineer-owned application/database/cache containers and private volumes live inside
   its VM; its private engine cannot provision host or sibling resources.
4. Verify actual ownership, mounts, filesystem/process/network boundaries, service endpoints, credentials and
   resource limits. Persist the effective isolation profile and results before marking the environment ready.
5. Launch the authorized Pi role with immutable context/Harness versions and container-native build/test commands.
6. Reconcile interrupted provisioning and attempts; pause preserves state; retire only owned resources after
   authorized cancellation/publication policy and proved quiescence. Never delete failed evidence silently.

Use proven VM mechanisms before custom enforcement. PHP owns policy and lifecycle adapters, not a new kernel
sandbox. Qualify macOS/ARM64 locally and native Linux separately; unsupported hosts/profiles remain unavailable
with no unsandboxed fallback. A VM boundary is not a claim of resistance to every hostile workload or hypervisor bug.

The Engineer may use sudo/root, writable guest runtime and its private Docker engine, including privileged guest
containers. Guest users, cgroups, mounts, Docker policy and checks are not trusted boundaries against the Engineer.
No installation Docker socket/engine credentials, host home, human checkout/Git administration, sibling VM/worktree,
installation database or broad host network is available. The host provisioner accepts only server-selected immutable
VM profiles and canonical contained transfer paths; it rejects arbitrary host mounts/images/privilege flags, symlink
escapes and worktree-authored host policy. Repository Compose/Dockerfile edits may affect the private guest only.

Keep provider/source-host/storage and Agent HMAC credentials in the external protected broker. Only short-lived,
VM-local test-service credentials may enter the guest. Bind broker capabilities to the actual VM/Workflow/role/attempt,
not guest-reported identity. Enforce default-deny egress and
explicit package traffic policy outside the guest. CPU, memory, disk/aggregate host storage, I/O/output and host-side
process/service limits must be externally enforced and accounted for; guest PID limits cannot protect host authority.
Do not require a guest PID ceiling that guest root can remove or present it as isolation evidence. Bound the VM and
its host control-plane impact instead. No mutable cache crosses role/Workflow boundaries without explicit policy.

Team Lead and independent reviewer run in separate trust boundaries with separate broker bindings; neither
credentials nor authoritative evidence reside in the Engineer VM. Worker-generated checks, Git objects and reports
remain untrusted until independently validated. Pinned bootstrap/Harness digests identify supplied inputs, not
continuous guest integrity; root can modify them, so policy must not depend on an untampered Pi process.

Keep one private Git store and worktree inside the Engineer VM. Trusted PHP seeds the approved base and validates
bounded exports in quarantine before any canonical ref publication. No shared canonical Git administration or
sibling metadata enters the VM. Exact ancestry/ref checks do not establish that exported content is safe to execute.

Persist intent → idempotent effect → observed result → authoritative completion for each step. Resource names and
labels derive from persisted Workflow/sandbox identities. Retried dispatch reconciles actual resources and verifies
ownership/profile before reuse. Fence stale consumers, serialize short Git administration and never launch a second
agent while the first may remain active. A worktree without containers is partial preparation, not running work.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Start approved work | Start coordinated work; prepare isolated execution | Eligibility, grant, capabilities, resource inventory | Preparation requested/verified or failed | Claimed TASK, owned worktree and sandbox, then one bounded process |
| Resume interrupted preparation | Reconcile isolated execution | Persisted intents, resource/process ownership, checkpoints | Reconciliation/continuation recorded | Reuse verified resources or pause uncertainty |
| Pause/cancel/retire | Existing explicit lifecycle operations | Quiescence, ownership, retention | Paused/cancelled/retired facts | Stop owned processes; preserve or remove only authorized resources |

Names above describe semantic operations; exact PHP classes and storage schemas belong to TASK decomposition.

## Validation and permissions

Domain owns grants, claim/transition invariants and resource ownership. Application coordinates typed capabilities;
Adapter implements Git, processes and the constrained provisioner. Persist redacted evidence. Preparation and
host cleanup are not model tools granting arbitrary host container/VM access. Engineer private-engine access is
confined to its VM. This TICKET does not itself authorize live provisioning or autonomous execution.

## Acceptance and evidence

- Two concurrent TASKs cannot change each other's source, database, cache, temporary files or artifacts.
- Qualify from guest root and privileged private-engine containers: host/sibling filesystem and network isolation,
  credential/broker exposure, external policy bypass and forbidden host provisioning. Private-engine access itself
  is permitted. Use owning tools in disposable environments, not configuration-text product tests.
- Prove externally imposed VM/resource ceilings, aggregate host admission/accounting and bounded control-plane,
  cache/log/export growth. Guest settings or resource reports alone are insufficient.
- Prove separate role/broker bindings and independently validate Engineer-generated results outside its VM.
- Owned application tests prove authorization, idempotency, stale-owner fencing, state transitions and recovery.
- Interrupt after claim, worktree creation, container creation, readiness, process spawn and completion; reconcile
  each point without duplicate effects, lost claims or fabricated success.
- Unsupported sandbox profiles and failed readiness prevent launch; cancellation proves complete workload stop.
- Evidence identifies exact base/worktree, image/profile versions, mounts, service identities, limits and results.
- Preserve the first real one-TASK production proof through Awaiting review; no disposable alternate runner path.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00138](../tasks/00138-TASK.md) | Define and qualify the terminal execution sandbox boundary | in-progress |
| [TASK-00139](../tasks/00139-TASK.md) | Start and inspect an authorized TASK from the terminal | ready-for-agent |
| [TASK-00140](../tasks/00140-TASK.md) | Prepare and recover owned worktrees containers and test databases | ready-for-agent |
| [TASK-00141](../tasks/00141-TASK.md) | Delegate one TASK through Team Lead and Software Engineer in Pi | ready-for-agent |
| [TASK-00142](../tasks/00142-TASK.md) | Prove parallel TASK isolation and interrupted execution recovery | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved amendment to WF-016/WF-023. John authorized terminal-first decomposition and implementation on 2026-09-26;
TASK-00138–00142 now own the ordered path. Necessary authorization, durability and isolation stay mandatory. Primary evaluation references:
[Docker security](https://docs.docker.com/engine/security/),
[rootless mode](https://docs.docker.com/engine/security/rootless/), and
[bubblewrap's policy responsibility](https://github.com/containers/bubblewrap#sandbox-security).
These are candidates, not proof of this application's isolation.

The [Docker Sandboxes follow-up](../../docs/engineering/DOCKER_SANDBOX_QUALIFICATION.md) passed scoped two-VM
storage and persistence checks. On 2026-09-27 John explicitly amended this contract to permit Engineer sudo/root
and private Docker-engine access in its dedicated mountless VM, treating the entire VM as Engineer-controlled.
This supersedes the former non-root/read-only/no-private-socket requirements for this profile only; host/sibling
isolation, private Git, external broker/credential authority, externally enforced limits and separate role boundaries
remain mandatory. Worker checks require independent validation. Approval of the boundary is not its qualification.
Pinned Pi passed a synthetic Codex transport/substitution/revocation fixture. Real OAuth, refresh, account mapping,
broker-enforced grants, enrollment and aggregate resource accounting remain unqualified; the fixture is not a Workflow.
