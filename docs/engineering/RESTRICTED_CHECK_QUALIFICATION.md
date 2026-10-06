# Restricted candidate-check qualification

## Decision and scope

For [TASK-00138](../../planning/tasks/00138-TASK.md), select fixed-image, non-root Docker containers with a frozen
read-only source snapshot and **loopback-only private test services** as the next check-runtime mechanism.
This follows the [tool-mediated profile](TOOL_MEDIATED_EXECUTION.md), not the retired Engineer-root VM strategy.
The October 6 experiment below qualifies bounded primitives and executes the existing PHP/PostgreSQL suite;
it does **not** qualify the complete checker, aggregate admission, protected semantic results or production launch.
TASK-00140 must implement and qualify the actual composition before enabling a check ID.

No Agent, live provider, operator enrollment or production credentials participated. Fixture Docker authority
belonged to the human-authorized experiment, never the candidate. All scratch is in the isolated worktree's
`.runs/tasks/TASK-00138/authority-check/`; no repository test or production Runner was added.

## Check request and result contract

The Application capability accepts a fixed check ID, Workflow/assignment identity, expected revision/generation
and immutable source manifest ID. Trusted authentication supplies the role identity; the model supplies no image,
command, network, mount, URL or resource flags. The server selects the independently registered profile, command
argv, required phases, dependency snapshot and limits. A profile change needs separate validation, not an edited
repository wrapper or an automatic retry with more privileges.

Admission must atomically check current Permission/grant, file/check readiness, frozen subject and remaining
budget, then persist the effect ID and full reservation. Repeated command/digest resolves the original operation;
a conflicting digest rejects. Execution happens after commit. Unknown create/start/stop results retain reservation
and claim pending ownership-based reconciliation. Source edits invalidate the old subject; stale results cannot
advance the new generation. Check unavailability is a checkpoint, not Awaiting review.

The trusted collector creates a receipt outside candidate mounts, binding:

- Workflow, assignment, role/attempt/generation, command/effect IDs and current grant revision;
- base/head or exact uncommitted source manifest, dependency and trusted profile/config/image digests;
- required phase IDs, exact observed container/service IDs, start/stop observations and bounded output digests;
- process exit, OOM, timeout, output/storage excess, collector failure and cleanup/retention disposition separately.

Collector failure is `EffectUncertain`, never exit zero. Timeout, OOM, nonzero exit, truncated output, missing phase
or mismatched subject makes that required phase unsuccessful. A file named `result.json` or success text from the
candidate is only an artifact; it cannot overwrite the receipt or authorize a transition. Keep receipt provenance
separate from formal independent review and application-owned completion.

### Execution evidence is not semantic attestation

A protected process receipt proves which command ran on which immutable inputs and how it terminated. It does
**not** prove that hostile PHP actually executed every expected assertion. Candidate source/bootstrap/tests share
the PHP process and can terminate it, falsify output or alter in-process behavior even when PHPUnit and its config
are read-only. Test counts, JUnit files and a zero exit code are not independent proof of test completeness.

Required-check selection must therefore be outside the candidate. TASK-00140 must qualify a project-specific
verification policy: protect the authoritative harness/dependency/config selection, review changes to tests and
bootstrap, and use independent out-of-process behavioral observations where required. Candidate-authored tests
remain useful but cannot self-certify that policy. Do not invent a universal PHP attestation claim or silently
weaken the parent contract to accept forged completeness. **That policy and its adversarial qualification remain
open in TASK-00138's minimum checker selection; its implementation belongs to TASK-00140.** This experiment does
not mark check-ready or satisfy every required check merely because the known baseline suite passed.

## Measured candidate profile

Host: macOS 26.6.2 / build 25G83, arm64; Docker Desktop, client/server 29.7.2, LinuxKit
6.12.76-linuxkit aarch64, `desktop-linux` context. These are local observations, not native Linux support.
Images were already local and used by immutable image ID, with no pull/build during the experiment:

| Input | Identity |
|---|---|
| PHP 8.5.10 / Python control probes | `sha256:e4e87bae623511ed0ad325774c701cf2902f01d4d906cfd2dcf74da32fb7e1fe` |
| PostgreSQL 17 image | `sha256:639ab7ceb90e13123085b741fb31ef493fba25463002f6da665352e7b534b652` |
| Source commit | `edd03cf3f3948188e47b255f03f54167ed93d471` |
| 451-file source manifest SHA-256 | `cbe997dda63ca4e779afa0cf451258d8d0bc64114aef7fa4eaa1787d481d6d05` |
| 12,919-file captured vendor manifest SHA-256 | `676fc966740a48807784255cf4e3b0861d00a3fa4218f8bd6f823e416c9b49c2` |
| Externally selected PHPUnit config SHA-256 | `19dad8b71dbf3defb30f2b69af0552b412790125290a101e4ebe319dfc8ba4ad` |

The source came from `git archive`, not a live writable checkout; no Git administration, ignored `.env`, home or
controller state was included. Existing vendor bytes were copied with contained links resolved and independently
hashed, not freshly installed or certified against release distributions. PHPUnit config was copied from the
known base and only its relative paths rebased to `/app`. This is exact-byte identification, not a supply-chain
signature or policy for accepting future dependency changes. There were no candidate source/test changes.

| Boundary | Effective fixture limit / selection |
|---|---|
| PHP/control process | UID/GID 65534, read-only root, all capabilities dropped, no-new-privileges, default engine seccomp |
| Candidate mounts | Frozen source `/app`, captured dependencies `/app/vendor`, trusted selection `/policy`, all read-only; no host socket or Git |
| Candidate writes | 16 MiB `/tmp` and 16 MiB `/app/var` tmpfs, noexec/nosuid/nodev; Docker's separately bounded 64 MiB `/dev/shm` |
| Candidate resources | 0.5 CPU, 256 MiB memory, no swap, 32 PIDs, 256 file descriptors, zero core dump limit |
| Database | Separate non-root UID/GID 999 container per environment; read-only root, dropped capabilities, no-new-privileges; 0.5 CPU, 512 MiB memory/no swap, 128 PIDs |
| Database writes | 128 MiB `/pgdata`, 16 MiB `/tmp` tmpfs, plus 64 MiB `/dev/shm`; no persistent volumes |
| Database workload | 100 connections, 16 MiB shared buffers; fixture-only `agent_os_test` superuser/database, trust authentication inside its private loopback namespace |
| Network | Database uses `--network none`; its candidate joins **that exact container's** network namespace and reaches only private loopback PostgreSQL; no ports published, bridges or external dependency fetch |
| Execution | Direct fixed PHP argv, 90-second owner deadline; a separate one-second cancellation probe; no repository host shell/wrapper execution |
| Logs | Docker logging disabled; collector retains at most 65,536 bytes per invocation, rejects excess and stops the owned container |

The candidate is allowed to destroy its disposable database; it has no production database credential or endpoint.
Sharing that one private network namespace is intentional, not cross-Workflow network sharing. PID/mount namespaces
stay separate. No bind-mounted PostgreSQL socket or canonical Git data is needed. Two independent private service
namespaces had different marker values despite using the same loopback address and database name.

## Fresh observations — 2026-10-06 UTC

Successful run `run-f56b25f2-a203-40a6-9add-5d4fdb6c0b8c` lasted 06:08:17–06:08:52 UTC. `result.json` records
**23 passing scoped observations**, nine attached invocations and eleven total containers (two databases).
`instrument.py`, `commands.jsonl`, source/vendor manifests and bounded phase logs identify the exact experiment.

- Both private PostgreSQL services became ready; PHP read each environment's distinct marker. After executing
  checks and resource probes in A, B retained its marker and both services still answered readiness queries.
- Doctrine ran **six migrations / 29 queries**, reporting two routine migration notices. The unmodified baseline
  PHPUnit suite completed with **147 tests / 1,133 assertions**, no reported warnings, deprecations or skips.
  No coverage was measured. This is actual candidate-code execution, not a synthetic allocation-manifest check.
- Source, config, dependency and root writes returned EROFS; the absent controller receipt path returned ENOENT.
  Sampled Git administration and Docker socket paths were absent. All source/vendor/config hashes were unchanged.
- The candidate had no IPv4 routes. Loopback port 18087 returned refusal; documentation-only 192.0.2.1 returned
  network unreachable, while its assigned loopback PostgreSQL was reachable. These two samples do not certify every
  DNS/IPv6/engine-control attack or replace namespace-policy inspection.
- A bounded 20 MiB fill attempt reached ENOSPC after 16 MiB; deletion allowed immediate reuse. At most 31 sleeping
  children started before EAGAIN under the 32-PID ceiling; all were reaped. The short CPU probe increased throttled
  periods from 1 to 13 under `cpu.max = 50000 100000`.
- A single 300 MiB allocation was OOM-killed inside the 256 MiB container; a sleeping candidate was externally stopped
  at the one-second deadline. Finite 128 KiB output was rejected with only 64 KiB retained. Neither became success.
- A candidate wrote a local forged success artifact then exited 7; the external receipt retained the actual nonzero
  outcome and did not consume that file. This probes collector ownership, **not** PHPUnit's resistance to malicious
  tests or exit-zero spoofing. No deliberately failing product tests or quality-tool failure fixtures were added.
- All eleven containers were retired after matching recorded IDs and owner labels; a successful Docker inventory
  confirmed no owned containers remained. No volumes/networks were created. One unrelated `omphalos-semantic-worker`
  restart was observed during the run; the instrument never operated on it. Existing Agent OS service start times
  were unchanged. Do not present unrelated host activity as either our effect or a controlled isolation result.

### Failed attempts retained

1. `run-7106594f-1bf5-4f44-b078-f04f01880ee1`: host Python lacked the newer tar extraction `filter` argument;
   failed before resource creation. The correction explicitly rejects links/nonregular entries, absolute paths and
   parent traversal before extracting the trusted Git archive.
2. `run-b291962e-0b8e-4509-bf3f-7ebb09c52c7c`: nested tmpfs mount target was absent from the read-only archive.
   OCI startup failed; the correction prepares empty `var`/`vendor` mountpoints before freezing the snapshot.
3. `run-e90c0d10-8210-418e-b539-0526376dac09`: an initial 12-connection database profile was too small for the actual
   suite (53 connection errors). The recorded profile was revised to 100 connections / 128 PIDs / 512 MiB database
   memory, then the complete run repeated. This was not a test change or permission to silently enlarge production
   limits. Failed logs/receipts remain; all resources from these attempts were retired. The first script version
   predates per-run instrument capture; its invocation/error receipt remains, not a claimed exact-source replay.

## Aggregate and retention boundary

The single experiment limits its inventory to four simultaneous containers; the successful path used two database
containers plus one candidate at a time: at most 1.5 CPU quota and 1,280 MiB configured memory, with tmpfs charged
to the relevant container memory ceilings. It does **not** implement atomic global reservations, host disk quotas
or a crash-surviving watchdog. Snapshot/vendor copies, retained logs, image storage, Docker daemon metadata and
controller overhead live outside those cgroups. Finite per-invocation logs alone do not bound repeated requests.
No host exhaustion, fork bomb, engine mutation or shared-service restart was performed.

Production admission must reserve for all active, paused, failed and retained attempts before effects: source and
dependency bytes/file counts, writable tmpfs/service ceilings, retained output/receipts, controller buffers/processes
and separately bounded shared image/engine overhead. Reserve conservatively and refuse on uncertainty; do not
replace a hard writable-volume quota with a free-space sample. The first candidate profile is offline and tmpfs-only
for **execution** writes, not a claim that host source/evidence retention is already bounded. Stopped containers'
remaining metadata/snapshots still count until verified retirement. Keep diagnostic evidence under explicit bounded
retention without erasing active authority/deduplication history.

Required next proof includes simultaneous admission contention, restart inventory reconciliation and
repeated-check/retention exhaustion. The follow-up below measures controller loss during output/deadline handling
and owned stop without harming a sibling, but not supervisor availability or complete admission.
TASK-00138 must settle minimum accounting/watchdog mechanisms; TASK-00140 implements durable reservations and
supervision, and TASK-00142 qualifies the composed parallel/recovery behavior. A failed controller cannot promise
timely cancellation from the original experiment's Python `finally` block.

## Controller-loss supervision follow-up — 2026-10-06 UTC

Continue **R2 / AC-4** with a controller-independent stop mechanism, not a production Runner or a full checker.
A separate trusted host process holds the exact container ID, owner label and a fixed deadline outside candidate
mounts. It verifies identity before arming and again before stop. Missing heartbeat, output excess or deadline
causes external `SIGKILL`; a subsequent engine observation must show stopped. Identity/engine uncertainty is
`EffectUncertain`, never success or permission to release the reservation. Stopped container metadata and retained
artifacts still count until independently verified retirement. The receipt flags demonstrate this disposition,
not a durable accounting ledger.

The supervisor and collector run in separate OS sessions; killing the collector's process group includes its
Docker attach client but not the supervisor. No socket or host mounts enter the candidate. The fixture uses the
same immutable PHP/Python image above, UID/GID 65534, read-only root, network none, dropped capabilities,
no-new-privileges, 0.25 CPU, 128 MiB memory/no swap, 16 PIDs, 64 file descriptors, 1 MiB shared memory and disabled
Docker logging. There is no writable bind mount or additional tmpfs. One sleeping sibling plus one candidate are
active at a time: 0.5 CPU quota and 256 MiB configured memory. This is a fixture inventory bound, not global
reservation. Candidate output is capped at 4,096 retained bytes; finite producers emit at most about 50 KiB/s.
Heartbeat timeout is 0.8 seconds, poll interval 0.05 seconds, and each Docker request has a five-second client
timeout. Observed stop latency is a sample, not a guaranteed sum of these bounds under engine failure.

Evidence in this worktree's `.runs/tasks/TASK-00138/check-supervision/`:

- Successful run `run-86bb1be0-f7ab-4354-ad59-aeca7b7fe853`, 06:27:11–06:27:23 UTC: **33 scoped observations**;
  six containers total, all retired after exact ID/owner checks; successful inventory reported none remaining.
  `instrument.py`, `result.json`, per-case subjects, heartbeats, supervisor receipts and capped output retain the
  experiment. Five cases cover deadline, quiet collector death, collector death during output, output excess and
  supervisor death followed by explicit reconciliation; a sixth denies a mismatched owner targeting the sibling.
- Quiet and output-producing candidates stopped **0.871 and 0.879 seconds** after collector-group `SIGKILL`.
  Deadline and output-excess stops also yielded unsuccessful receipts, not candidate-declared success. The sibling
  stayed running with an unchanged start time throughout. No candidate completed a repository quality gate here.
- Killing both supervisor and collector left the candidate **running beyond its two-second deadline**. A new
  supervisor read the same owned subject/deadline and stopped it without creating a replacement. This is evidence
  of restart reconciliation **and a supervision-availability gap**, not crash-surviving autonomous recovery. No
  success receipt existed during the gap. The experiment driver triggered recovery; an OS service manager did not.
- Initial run `run-afc72f53-1d47-47a6-bccb-6c62f761b2f3` failed the controller-loss elapsed-time assertion.
  On this host Python 3.9.6, `time.monotonic()` has process-relative origins: persisted deadlines/heartbeats could
  not be compared across these processes. The corrected instrument uses `clock_gettime(CLOCK_MONOTONIC)` and
  first brackets a child-process sample between parent samples. The full experiment then repeated. Both exact
  instruments and the failed result remain; all three first-attempt containers were retired. Never transfer
  this clock receipt to another runtime or boot without qualification.
- An unrelated `omphalos-semantic-worker` start time changed during the successful sample. Its identity was only
  inspected, never operated on; this is neither a claimed effect nor controlled isolation evidence. Agent OS
  service starts did not change. Shared host/engine overhead and retained evidence were not quota-qualified.

### Selected direction and remaining qualification

Use controller-independent, installation-owned supervision rather than the requesting controller's cleanup
handler. **Do not enable it yet.** TASK-00140's implementation must put launch and stop under the same fenced
supervision authority: arming a watcher is not permission for a delayed collector to start after stop. This
fixture's collector performs start; it does not qualify the create/start/cancel race or admission protocol.
Candidate processes cannot access host PIDs/heartbeat files, but same-user host compromise remains out of scope.

Before R2 can close, TASK-00138 still needs a supported service-lifecycle mechanism for supervisor loss, startup
inventory reconciliation, bounded retained/aggregate accounting and uncertainty handling. Qualify how admission
closes when supervision disappears and how an independent service recovers owned work; do not advertise an
unmeasured stop guarantee during supervisor/engine/host failure. Absolute monotonic deadlines cannot be reused
after reboot; boot identity, suspended-host behavior and durable recovery require their own proof. A failed
Docker operation retains uncertainty and reservations; this experiment did not inject daemon failure or prove
stop after it. Container stop alone does not release source/output/image/metadata retention.

R1 catalog/storage decisions are untouched. R3 required-phase selection, forged/omitted success resistance and
full fixed-gate support are also untouched: these always-unsuccessful stop receipts establish no semantic check
completeness. AC-3/5's previously accepted bounded file/handoff evidence was not rerun. No product tests, package
adoption, live Agent/provider, production credentials, native-Linux qualification or managed launch participated.

## Service lifecycle and serialized launch follow-up — 2026-10-06 UTC

This **R2 / AC-4** continuation qualifies a temporary macOS `launchd` user job as a local supervisor-restart
primitive. It does not install a production service or adopt the disposable Python instrument as application code.
The job was bootstrapped into the current GUI session from an ignored `.runs/` plist, not installed in a LaunchAgents
search directory. `KeepAlive=true` requests restart; `ThrottleInterval=3` bounds restart frequency, not recovery
latency. The local `launchd.plist(5)` manual describes both settings and default process-group cleanup. No root,
login/logout, host reboot or Docker daemon restart was involved.

The fixture moves start and stop into one supervisor, unlike the preceding collector-start experiment:

- A singleton `flock` denies a concurrent supervisor. A separate state lock serializes request/cancel intent with
  the supervisor's final eligibility check and Docker call. Clients queue an exact subject and expected epoch;
  they do not start containers. Cancellation is terminal for that subject. The fixture driver still creates the
  containers; this is not qualification of the complete create/admission protocol.
- Each startup increments the epoch, keeps readiness closed, scans its exact owner-label inventory, and reconciles
  both recorded subjects and previously unregistered containers. It cancels and stops rather than replaying old
  launches. Readiness opens only after all recorded subjects have a confirmed quiescent observation. Identity or
  engine uncertainty is unsuccessful and retains the reservation flag.
- The trusted client requires readiness, the current epoch and a heartbeat no older than 0.4 seconds. A fresh
  heartbeat is not a lease or proof that the process cannot die immediately afterward: queued work still needs
  the supervisor's final check. Startup invalidates that old epoch before new requests can execute.
- State files are outside candidate mounts, atomically replaced after file `fsync`; locks and file replacement
  are local experiment mechanisms, **not PostgreSQL authority or power-loss-safe storage qualification**. Boot
  identity is captured; old work is cancelled unconditionally on startup, not resumed using old deadlines.

Fresh evidence: `.runs/tasks/TASK-00138/check-lifecycle/run-f28f14d0-c3d1-41b2-899d-4b5bf4d509d5/`,
06:51:26–06:51:34 UTC. The exact `instrument.py`, generated plist, initial/restarted/final state snapshots,
`launchctl print` records and `result.json` retain **25 passing scoped observations**:

| Probe | Observed result |
|---|---|
| Kill the launchd-owned supervisor after its successful start | Launchd, not the driver, created a new PID/epoch; startup stopped the same running container and adopted the unregistered owned container without launching it |
| Request during the observed restart gap | `unavailable`; no candidate start. Recovery/readiness was observed 3.419 seconds after SIGKILL, including polling/inspection overhead, not a cancellation SLA |
| Attempt a second supervisor | Singleton lock denied entry; epoch unchanged. Its expected nonzero exit/traceback is retained separately; managed-service stderr was empty |
| Cancel queued work behind a controlled pre-launch barrier, then release it | Subject remained in Docker `created` state; delayed launch request returned `cancelled` |
| Use a prior epoch on a fresh subject | `stale-epoch`; no start. Current-epoch request subsequently started it, and cancellation yielded externally confirmed stop |
| Replay cancelled work; unload the service | No cancelled subject restarted; after heartbeat expiry the unloaded-service request returned `unavailable` |
| Preserve and retire resources | Controlled sibling stayed running with the same start time; Agent OS service starts were unchanged. Exact-owner cleanup removed all five containers; both owner inventories and the launchd target were absent |

The five containers used the preceding immutable image, no mounts, non-root/read-only/network-none profile,
0.25 CPU, 128 MiB memory/no swap, 16 PIDs, 1 MiB shared memory and disabled Docker logging. At most the sleeping
sibling and one workload ran concurrently (0.5 CPU / 256 MiB configured memory); stopped/created metadata remained
until retirement. Workloads had a finite 60-second sleep; requested execution had a 20-second deadline, and the
fixture's service loop had a 90-second end. Those fallback paths were not the exercised recovery mechanism. No
candidate output, application checks, Agent/provider calls or real credentials participated. No failed experiment
attempt occurred; the deliberate duplicate-process denial is an expected negative observation.

### Remaining service and fencing limits

Select installation-owned, service-managed supervision for the local macOS direction, with one serialized launch/
stop owner and cancel-on-takeover reconciliation. **Do not enable check readiness from this sample.** The selected
primitive is tied to a GUI login session; supported enrollment, logout behavior, restart-loop handling, protected
service configuration, health monitoring and bounded diagnostics still require a lifecycle decision/qualification.
No service remains enrolled. Linux/system-service operation is unqualified.

The experiment killed the supervisor **after** Docker start returned. It did not interrupt an in-flight create,
start or stop. A client timeout or dead Docker CLI does not fence a request already accepted by the daemon;
`flock` release on process death alone cannot rule out a late start after a replacement's stop observation.
Before R2 closes, qualify that uncertain-effect ordering and how reconciliation retains admission/reservations
until quiescence is established. The controlled pre-launch barrier is one cancellation ordering, not concurrent
stress or a distributed fence. Missing inventory, wrong ownership, engine outage, suspend/reboot and service
restart failure were not fault-injected here. Previous ownership-denial evidence remains historical.

Aggregate admission, repeated-request/retained-byte exhaustion, shared image/engine overhead and durable cleanup
accounting remain open. The reservation booleans are still disposition flags, not a ledger. The local state file,
service stdout/stderr, captured snapshots and engine metadata have no qualified aggregate storage quota. R1 and
R3 are unchanged. TASK-00140 owns production PHP/service composition and TASK-00142 its integrated recovery proof;
TASK-00138 still owes the remaining minimum-mechanism qualification before independent full-TASK acceptance.

## Qualification disposition and next owners

- **R1 / AC-1:** see [current authority decisions](TERMINAL_AUTHORITY.md#current-authority-decisions--2026-10-06).
  The catalog, explicit `SUPER_ADMIN` seed and storage qualification candidate are now owner-approved. Package
  release/adoption, concrete managed assignments and protected sink/key qualification remain prerequisites.
  [Protected-file custody](PROTECTED_STORAGE_QUALIFICATION.md) supersedes the failed Keychain candidate and has
  separate bounded access/restart/recovery evidence, retaining broker-held signing. That evidence does not qualify
  ordered storage, actual broker authority or always-on host/runtime support. These check experiments use no
  package authority and supply none of that proof.
- **R2 / AC-4:** local bounded execution, private services, controller-loss stop and a service-managed restart/
  serialized-launch sample are measured. Host retention/aggregate admission, supported service lifecycle,
  uncertain in-flight launch fencing, complete required-check policy and supply-chain qualification remain open.
- **R3 / AC-3/4/5:** protected mounts and externally collected process outcomes are measured, not full file-tool race
  resistance, managed role identities, adversarial semantic check completeness or production source-publication proof.
  The prior file/handoff prototype remains historical evidence and was not rerun.

The experiment is not the mandatory repository `./bin/build` and does not replace it. It omits the full gate's
Composer/planning/architecture/Pi lanes, as well as frontend tooling, dependency installation and canonical Git
operations. Those need explicit fixed capabilities/profiles; no host-shell fallback is allowed. Kernel/engine or
trusted-controller compromise, native Linux, hostile multi-tenant support and universal isolation are not qualified.
TASK-00138 remains in progress and TASK-00139 blocked. Independent review must assess this checkpoint's claims and
remaining requirements; passing these primitives cannot independently accept the whole TASK.
