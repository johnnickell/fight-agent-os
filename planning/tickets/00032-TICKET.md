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

Deterministic PHP owns the following recoverable sequence; Agents never generate its privileged commands:

1. Authorize the TASK revision, grant, dependencies and Runner capabilities; atomically claim and persist dispatch.
2. Resolve an exact base commit, create an owned branch/worktree under an approved root and persist identity.
3. Ask a constrained installation-owned provisioner for Workflow-specific execution containers, private networks,
   temporary paths, writable volumes and required application/database/cache test services.
4. Verify actual ownership, mounts, filesystem/process/network boundaries, service endpoints, credentials and
   resource limits. Persist the effective isolation profile and results before marking the environment ready.
5. Launch the authorized Pi role with immutable context/Harness versions and container-native build/test commands.
6. Reconcile interrupted provisioning and attempts; pause preserves state; retire only owned resources after
   authorized cancellation/publication policy and proved quiescence. Never delete failed evidence silently.

Use proven mechanisms before custom enforcement: evaluate hardened/rootless OCI execution and, where suitable,
Linux namespace confinement such as bubblewrap. PHP owns policy and lifecycle adapters, not a new kernel sandbox.
The selected backend must demonstrate required behavior on native Linux and the supported local Docker Desktop
environment; unsupported hosts stay queued/Needs Human with no unsandboxed fallback. Record remaining shared-host
and shared-Git risks; stronger hostile-workload containment requires an explicitly selected VM boundary.

No Docker socket, engine credentials, host home, sibling worktrees, installation database or broad host network
is available to Pi. The provisioner validates server-selected immutable profiles and canonical contained paths,
rejects symlink escapes, arbitrary mounts/images/privilege flags and mutable worktree Compose policy. Changes
to a repository Dockerfile/Compose definition are untrusted input for later review, never permission expansion.
Keep provider/source-host/storage credentials in the protected broker. Provide short-lived sandbox-only test
credentials where needed. Default-deny outbound shell traffic; allow required package traffic by explicit policy.
Use non-root execution, minimal capabilities, read-only runtime, private process namespace and bounded CPU,
memory/process/storage consumption as supported and verified. Sharing mutable caches requires an explicit policy.

Git worktrees share administrative storage. Keep shared ref/worktree administration behind trusted PHP operations;
qualify the minimal Git view and commit operation required by the Engineer without exposing sibling metadata or
arbitrary refs. Record the chosen boundary and its limitations before declaring sandbox readiness.

Persist intent → idempotent effect → observed result → authoritative completion for each step. Resource names and
labels derive from persisted Workflow/sandbox identities. Retried dispatch reconciles actual resources and verifies
ownership/profile before reuse. Fence stale consumers, serialize short Git administration and never launch a second
agent while the first may remain active. A worktree without containers is partial preparation, not running work.

## Pi Durable integration direction — 2026-10-13

John requested this bounded refinement of existing Harness and execution work after reviewing Pi Durable:
prefer `@earendil-works/pi-durable` for durable conversation execution where qualification satisfies our contracts.
Evaluate it before implementing equivalent checkpoint, queued-input and reconnect machinery. Record the exact
package/source revision, supported runtime, storage choice and observed limitations in the implementing TASK;
if it cannot meet a required contract, record the specific gap before selecting an alternative. This is an
integration preference, not package adoption or proof that existing Pi extensions work unchanged.

The product goal is one installation-owned durable Agent conversation reachable from authorized terminal and
browser clients. Closing either client detaches it; it does not cancel accepted work. Reattachment resolves the
same Agent/session and committed state. The owning machine must be running to execute work; durable recovery
does not imply execution while powered off, automatic cross-machine migration or a new always-online hub.
Keep the terminal-first milestone; browser interaction arrives through its existing Planning/session owners.

PHP/PostgreSQL retain Workflow grants, claims, fencing, provisioning and phase transitions. Pi Durable may own
transcripts and internal execution checkpoints behind the Harness adapter; its internal tasks are not Planning
TASKs. Map stable conversation/submission/attempt references across that boundary. Reconcile lost acknowledgements
against the original PHP operation before retrying; a durable submission does not make external effects exactly-once.
Only demonstrably replay-safe work may rerun. Unknown effects, expired authority and uncertain liveness hold.
Stored approval answers retain their original scope and validity; they never bypass current authorization.

Qualify a single active storage owner, protected storage outside disposable worktrees, brokered tools/providers,
and explicit stop/pause/cancel behavior including owned background work. Pin executable extension versions as well
as names across restart; mutable upstream configuration must not silently replace our immutable Harness snapshots.
Clients use the same authorized operations with original request identities and current-state resynchronization;
concurrent input must preserve one bounded active turn and expose conflicts rather than duplicate execution.
Transcript access remains owner-first, and Workflow observation retains its exclusion of private Pi transcripts.

References inspected on 2026-10-13: [Earendil's introduction](https://earendil.com/posts/pi-durable/), the
[experimental package contract](https://github.com/earendil-works/pi/blob/main/packages/durable/README.md), and
[IndyDevDan's playground](https://github.com/disler/durable-pi). The playground supplies browser/terminal design
examples, not our authentication, sandbox or Workflow policy. The linked
[video](https://www.youtube.com/watch?v=DNDYYHsypZg) motivated this request; its contents were not independently
retrieved. These upstream references are mutable; implementation must qualify pinned code.

TASK-00141 owns the execution integration and TASK-00142 its recovery proof. TASK-00250 consumes the protected
session primitives; TASK-00266 consumes the browser Planning owners. Their existing unmapped prerequisites remain
explicit. TASK-00164's separately approved Pi 1.1.0 local compaction scope is preserved. No new TASK, dependency,
priority, Wayfinder session, runtime launch or database Planning cutover follows from this amendment.

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
cleanup are not model tools granting arbitrary container access. This TICKET does not authorize live provisioning.

## Acceptance and evidence

- Two concurrent TASKs cannot change each other's source, database, cache, temporary files or artifacts.
- Directly qualify filesystem escape, sibling access, network/credential exposure and forbidden provisioning.
  Use the owning sandbox tooling in disposable environments; do not add configuration-text tests to product suites.
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
storage and persistence checks. Its worker sudo/private-engine model is a profile proposal requiring an explicit
decision before Agent launch; it does not relax the installation-engine, credentials or role boundaries above.
Pinned Pi subsequently passed a synthetic Codex transport/substitution/revocation fixture. Real OAuth, refresh,
account mapping and broker-enforced grants remain unqualified; the fixture is not a managed Workflow.

TICKET-00064's accepted TASK-00261–00264 now supply Team/Agent eligibility, fair whole-TASK intake, atomic
Team admission and same-Team phase constraints. TASK-00139 retains the base Start and immutable Markdown
eligibility capability; TASK-00263 extends that same owner, never creates another claim engine or reverse
prerequisite. TASK-00141 explicitly consumes TASK-00264 for its real Team Lead/Software Engineer handoff.
Lower-level commitment/resource/base/independence and phase/session mappings remain `needs-info`, not supplied
by these consumers or by a completed role journey. No Planning database cutover is required by core selection.
TASK-00265 later extends owned pending-intent/withdrawal and final terminal/browser integration, without changing
the first Awaiting review milestone, authored order or active work. No live launch was enabled by planning.

### Remaining EPIC-00007 requirement ownership

John approved six additional TICKETs on the existing isolated planning branch without changing TASK-00138–00142.
This TICKET remains the core Start, grant/claim/dispatch-intent, sandbox, preparation, Workflow/phase/process custody,
Team Lead/Software Engineer handoff and recovery owner through Awaiting review. Apply the full accepted EPIC
contracts, including event-store/Command authority, exact idempotency, current grant/lease fences, immutable subject
and safe quiescence; this note creates no second implementation slice or new status/blocker.

[TICKET-00093](00093-TICKET.md) owns enrolled Runner operation, transport/capacity and drain/halt integration over
those existing contracts. [TICKET-00094](00094-TICKET.md) supplies exact evidence/private Artifacts and their safe
retention; this TICKET still validates the complete handoff and transitions to Awaiting review.
[TICKET-00095](00095-TICKET.md) provides the separately authorized web research seam.
[TICKET-00096](00096-TICKET.md), [TICKET-00097](00097-TICKET.md) and [TICKET-00098](00098-TICKET.md) own safe
history/projections, sessions browser presentation and honest usage/forecasting respectively. None replaces
execution authority or adds a browser/forecast/Planning-cutover blocker to the first terminal milestone.

Map exact early suppliers during later TASK decomposition rather than depending on completed TICKETs or final
Team/role journeys. Existing Team/Agent, Harness and shared-fence readiness holds remain; these requirement records
are not runtime evidence. The EPIC records approval/verification and the next map-wide planning handoff.
