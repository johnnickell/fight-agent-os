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
| Database writes | 128 MiB `/pgdata`, 16 MiB `/tmp` tmpfs, plus 64 MiB `/dev/shm`; an unused image-declared anonymous volume was also present, as corrected below |
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
  confirmed no owned containers remained. No networks were created. October 13 profile reinspection found two
  image-declared anonymous volumes; see the [storage-profile correction](#storage-profile-correction-and-retained-attempts).
  One unrelated `omphalos-semantic-worker`
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
The [terminal-removal follow-up](#terminal-removal-and-late-effect-follow-up--2026-10-06-utc) below advances known-ID
start/removal ordering. Unknown create outcomes and how reconciliation retains admission/reservations until
quiescence is established remain open. The controlled pre-launch barrier is one cancellation ordering, not
concurrent stress or a distributed fence. Missing inventory, wrong ownership, engine outage, suspend/reboot and service
restart failure were not fault-injected here. Previous ownership-denial evidence remains historical.

Aggregate admission, repeated-request/retained-byte exhaustion, shared image/engine overhead and durable cleanup
accounting remain open. The reservation booleans are still disposition flags, not a ledger. The local state file,
service stdout/stderr, captured snapshots and engine metadata have no qualified aggregate storage quota. R1 and
R3 are unchanged. TASK-00140 owns production PHP/service composition and TASK-00142 its integrated recovery proof;
TASK-00138 still owes the remaining minimum-mechanism qualification before independent full-TASK acceptance.

## Terminal removal and late-effect follow-up — 2026-10-06 UTC

This **R2 / AC-4** continuation measures a terminal primitive for a **known immutable container ID**. A stopped
container can restart; neither a released host lock nor a stopped-state observation fences a late Engine request.
For authorized cancellation/retirement of disposable check runtimes, select ownership-checked terminal removal,
then exact-ID absence confirmation, rather than treating stop alone as terminal. Persist the cancellation intent,
subject and retained diagnostic evidence outside the runtime first. Never address start/stop/removal by a reusable
name or ID prefix. This is a mechanism direction for TASK-00140, not an implemented durable cancellation protocol.
Pause, evidence retention and worktree/database retirement remain separate operations; this selection does not
authorize automatic deletion of failed evidence or retained application data.

Direct Engine API 1.55 probes used Docker Desktop 4.86.0 / Engine 29.7.2, LinuxKit 6.12.76 arm64. The immutable
image was `sha256:e4e87bae623511ed0ad325774c701cf2902f01d4d906cfd2dcf74da32fb7e1fe`. Each fixture was configured
with a finite 120-second sleep, UID/GID 65534, read-only root, no mounts, network none, all capabilities dropped,
no-new-privileges, 0.25 CPU, 128 MiB memory/no extra swap, 16 PIDs, 1 MiB shared memory, 64 file descriptors,
zero core limit and logging disabled. At most a sleeping sibling and one subject ran concurrently: 0.5 CPU quota
and 256 MiB configured memory. The trusted instrument used the host Engine socket; no candidate received it.
Requests had ten-second client timeouts and 64 KiB response limits. Timeout would be uncertainty, not success.

Evidence is in `.runs/tasks/TASK-00138/effect-fencing/` in the authorized isolated worktree. Each run retains its
exact `instrument.py`, bounded request/response transcript, initial profiles and `result.json`:

| Probe | Observation and limit |
|---|---|
| Stop positive control | After acknowledged SIGKILL and observed non-running state, a late start returned 204 and the same container ran again. Stop alone demonstrably fails terminality. |
| Known-ID removal | Ownership-checked `DELETE ...?force=true&v=false` returned 204, inspect returned 404 and subsequent full-ID start returned 404. No anonymous volumes or durable candidate artifacts existed. |
| Simultaneous start/removal | First run `4f515530-5ee4-4921-82fb-a441430e0661` (08:31:41–08:31:46 UTC): all eight starts returned 409 while all removals returned 204. This samples removal-winning scheduling only. |
| Varied ordering | Follow-up `aa4c44e3-1801-477d-9b60-cd46765a127b` (08:33:32–08:33:37 UTC) delayed removal by 0, 0, 1, 5, 50, 150, 300 and 600 ms. Four starts returned 409; four returned 204. Every removal returned 204 and every post-removal inspect/start returned 404. Offsets vary ordering; they do not prove a specific internal daemon overlap. |
| Unobserved start response | Four cases per run sent the start request then closed the connection without reading a response. Successful removal and exact-ID absence/retry followed. Local send completion is **not** proof of daemon acceptance; these cases deliberately retain that uncertainty about the start. |
| Reused-name countercheck | After removing an original container, a replacement received a different ID under the same name. Old-ID start returned 404 without starting the replacement; name-addressed start returned 204 and started it. Production operations must not substitute a name after losing an ID. |
| Delayed create countercheck | A name was absent at inspection, then an explicitly delayed create produced an owned container in `created` state. An empty scan cannot certify that a pending create will never arrive. This is controlled client delay, not an injected accepted-but-pending daemon create. |
| Preservation and cleanup | Sibling remained running with the same start time. Both runs removed all 17 owned containers by exact ID/owner and ended with empty owner inventories. Existing Agent OS service starts, unrelated main-checkout files and canonical review bytes were unchanged. |

The first run passed 50 scoped observations; the ordering sweep passed 51. Both successful runs are retained,
not a failed attempt silently replaced. Their repeated checks are not 101 independent security properties or
product tests. No service was installed, engine restarted, host exhausted, production credential used, candidate
quality gate executed or autonomous Agent launched. All 34 fixture containers were retired; no volumes/networks
were created. Retained scratch/Engine metadata overhead has no qualified aggregate quota.

### Admission and remaining uncertainty

An acknowledged terminal removal plus absence is stronger evidence for a known ID than an isolated 404 or stopped
state. Preserve that exact effect/subject tombstone and never recreate or restart a cancelled subject. A new attempt
requires a new authorized identity and reservation. Unknown create outcomes are a different problem: reserve before
create, persist a unique intent/owner selector, keep create separate from start, and let only the fenced supervisor
start an exactly registered current subject. Late-created cancelled resources must stay inert and be reconciled by
ownership. Do not turn an empty inventory, client timeout or arbitrary quiet interval into proof of settled creation.

Keep admission/claims and the uncertain-effect reservation closed/held until outstanding creators are causally
settled or a separately qualified engine/service fence rules out their future effects. This checkpoint does **not**
qualify that fence, a finite settling deadline, a durable intent/tombstone ledger or cross-process takeover. Failure
to remove/inspect remains `EffectUncertain`. Even successful runtime removal does not release retained source,
outputs, receipts, dependency/image storage or shared Engine overhead; account for those separately.

Finite API sampling does not prove every Engine interleaving, cancellation latency, daemon crash/restart or restored
Engine metadata behavior. The client-side response-loss cases did not inject a known accepted request stuck inside
the daemon. Supported service lifecycle, unknown-create reconciliation and aggregate/retention bounds remain R2
obligations; R1 storage/recovery and R3 independently authoritative checks are unchanged. TASK-00140 owns production
composition and TASK-00142 integrated interruption/recovery proof. Check readiness remains disabled.

## Durable reservation and late-create accounting — 2026-10-13 UTC

This **R2 / AC-4** continuation selects a conservative minimum direction: reserve before dispatch, retain every
unresolved effect across takeover, and account separately for runtime, retained artifacts and permanent history.
Unknown creation keeps new admission closed; neither an empty Engine scan nor elapsed time settles it. This is a
PostgreSQL mechanism probe plus owned Engine observations, not the production admission service or a daemon fence.

Run `542fc9d2-1b61-4400-9436-aca826f09ce5`, 21:57:40–21:57:52 UTC, passed **70 scoped observations**. Exact SQL/Python,
independent session inputs/results, recorded `pg_blocking_pids`, profiles, commands and final ledger are under
`.runs/tasks/TASK-00138/reservation-ledger/` in the authorized worktree. No failed experiment attempt occurred.

A singleton PostgreSQL pool-row lock serializes reserve, dispatch, cancel, takeover and release. Each immutable
fixture operation binds its request digest and epoch, one runtime unit and 65,536 retained bytes; the pool permits
**two runtime units, 131,072 retained bytes and four lifetime history rows**. Usage is reconstructed from retained
rows, not process-local counters. Exact retry is distinguished from conflicting reuse, cancellation never clears
its tombstone, and runtime removal does not release retained bytes or history. These deliberately tiny logical
budgets are not physical quotas, production schema choices or permission checks.

| Direct probe | Observation |
|---|---|
| Backend loss before reservation commit | `pg_terminate_backend` caused the held transaction to roll back; no history/reservation remained. The client exited 2 as expected. |
| Last runtime slot contention | The losing independent session demonstrably blocked on PostgreSQL, then returned runtime-capacity after the winner committed. Exactly two reservations remained; exact existing retry still worked and changed digest conflicted. |
| Cancellation and terminal removal | Two owned finite-sleep containers ran under their registered IDs. Cancellation retained the runtime reservation. Identity/owner-checked Engine removal and confirmed absence preceded SQL release; repeated release did not over-credit capacity. |
| Runtime versus retention | After one runtime was removed, a new admission still failed retained-byte capacity. Only removal of its actual 65,536-byte inert fixture artifact preceded retained-byte release. History remained. |
| Unresolved create and takeover | A committed dispatch intent was cancelled before the controlled client sent create. Takeover advanced epoch; controlled database restart retained the complete pending reservation/cancellation state. Stale dispatch and cancelled redispatch failed. An empty exact-effect Engine inventory did not reopen admission. |
| Delayed creation | The driver then created that exact labelled effect. An old-epoch observer could not register it; the current observer registered the returned full ID, but the cancelled effect remained start-ineligible and Docker observed it in `created` state. Owned terminal removal and separate artifact retirement followed. |
| Repeated work/history exhaustion | Four effects eventually had zero runtime/retained-byte use but four permanent history rows. A fifth admission failed history-capacity; an old exact retry remained cancelled. Replacement with a different database container ID on the same volume preserved the exact ledger and denial. |

The observer functions accept trusted evidence supplied by the experiment driver; SQL cannot authenticate an Engine
result. `may_start` is a sampled eligibility query, **not an atomic launch fence**. The delayed create is a controlled
client sequence, not a request proved accepted and stalled inside the daemon. Clearing pending followed the one
known create response; this does not settle multiple unknown requests, authorize retrying create or qualify stale
process exclusion. The selected production direction must retain uncertainty indefinitely rather than invent a
quiet-period deadline; recovery requires positive causal settlement or a separately qualified service/engine fence.

The PostgreSQL 17.10 image is `sha256:742f40ea20b9ff2ff31db5458d127452988a2164df9e17441e191f3b72252193`;
workloads use the earlier pinned PHP/Python image with a fixed `sleep 60`, no mounts or network. Database UID 70
and workload UID 65534 use read-only root, dropped capabilities, no-new-privileges, disabled logging, 0.5 CPU each,
64 PIDs, 16 MiB shared memory and no extra swap; database memory is 512 MiB, each workload 128 MiB. Database clients
use only trusted `docker exec` Unix-socket sessions. Only the volume initializer uses root/CHOWN/FOWNER. At most one
database and two workloads ran concurrently. Seven containers and one owned data volume were retired by exact
identity/owner; final owner inventories were empty and main/TASK-00044 service starts were unchanged.

The named database volume and host artifact directory have **no hard byte quota**. Artifact deletion was limited
to four explicitly disposable inert payloads, checked against their recorded size/digest; evidence/diagnostics remain.
WAL, indexes, snapshots, images, service metadata and all retained evidence are outside these logical pools. No
engine restart, host exhaustion, credential, Agent/provider, service enrollment or product test participated. The
fixture does not compose the earlier supervisor or qualify an all-writer authority boundary. R2 advances for
contention, durable holds and separate retention/history accounting; physical aggregate bounds and lifecycle/unknown-
effect fencing remain open. R1 protected restore admission and R3 semantic-check authority are not supplied by it.

## Fixed gate capabilities and external phase receipts — 2026-10-13 UTC

This **R3 / AC-4** continuation selects a fixed, trusted phase catalog and exact-subject externally collected receipts
as the mechanical checker direction. It does **not** select a universal semantic attestation policy or enable check
readiness. The catalog is outside candidate source authority; changes to tools, dependencies, commands, configuration
or bootstrap need a separately qualified selection. Production selection/collection belongs to TASK-00140.

Final run `eb09b54c-c4af-4498-bc81-49ead335ba61`, 22:08:23–22:08:56 UTC, passed **30 scoped observations** in
`.runs/tasks/TASK-00138/fixed-gate/`. It captured branch commit `59f406a8300c38358c2c9e914fb19d53b82f1146` via
`git archive` (**453 source files**) and copied **12,919 existing vendor files** with contained links resolved.
This is exact-byte identification, not package supply-chain certification or qualification of newer develop.
Subject digests below hash compact sorted JSON without the stored file's trailing newline.

| Captured subject | SHA-256 |
|---|---|
| Source manifest | `a257d82df24b74769247ad364e4015b449bfe63e03309246d257db175ee2a7f6` |
| Vendor manifest | `a2c2d19c8b6f1603c8c56b7bf6846618a0525ae3024cd253ddedc4fb671edb80` |
| Trusted fixture policy | `e304da2cfd43736a2df766b52081138b9e21e648a202b305ecafd02d5e5808d2` |

The trusted driver selected six required phase IDs/argv/image pairs rather than executing candidate `bin/build` or
`build.php`: private database migration preparation; Pi presentation tests; Composer strict validation; Planning;
Deptrac; PHPUnit. Separate read-only policy mounts fixed `composer.json`, lockfile, PHPUnit/Deptrac configuration,
Planning executable and test bootstrap to the captured baseline. Candidate changes to those protected paths were
not exercised or approved. Composer additionally disabled plugins/scripts; that intentionally narrower invocation
qualified manifest validation only, not arbitrary project Composer capabilities.

All six phases completed: **six migrations / 29 queries with two routine notices; seven Pi tests; valid Composer
manifest; 188 Planning records / 141 active; Deptrac 651 allowed with zero violations/skips/uncovered/warnings/errors;
147 PHP tests / 1,133 assertions**. No test warnings/deprecations/skips appeared; no coverage was measured. These are
fresh restricted-profile observations of the branch's gate components, **not execution of the canonical wrapper**
or proof of complete required phases for another revision. Mandatory repository `./bin/build` is verified separately.

The policy also pins the exact instrument digest, including fixed runtime/mount/resource configuration. The host
collector bound attempt, source/vendor/policy digests, phase, image/argv, exact container ID and captured profile-file
digest, observed exit, termination classification and bounded output digest. A fresh in-memory host-only HMAC key
authenticated fixture receipts; neither key nor receipt directory was mounted into candidate processes. The key was not persisted, so
this is not durable receipt custody or independently replayable signature verification after controller restart.
Captured exact instruments, profiles, logs and receipt/file digests support inspection of what was measured.

- A real candidate-shaped process could write a forged success artifact in its own tmpfs but could not write the
  policy, protected PHPUnit/Planning paths or collector receipt path. Its real additional-phase receipt could not
  replace a missing required phase.
- Omitting each of the six required receipts yielded incomplete. Duplicate phase, modified receipt and unsigned
  success failed collection checks. Valid old receipts failed against a different attempt/source/vendor/policy
  subject. No candidate-authored success text or artifact was consumed as authority.
- Source, vendor and policy bytes were unchanged. No intentionally failing product test or altered quality-tool
  fixture was introduced; these are collector-boundary probes, not tests proving that quality tools detect defects.

### Storage-profile correction and retained attempts

The first attempt `d4394808-ce1e-4ea8-931d-145e77ac509c` stopped at Planning: its ignore validation requires Git
metadata and the archive had none. The corrected profile uses an **empty, template-free, read-only private bare Git
directory** in the policy mount, explicit work-tree `/app` and disabled global/system Git configuration. It contains
no canonical refs, objects, hooks, sibling metadata or human checkout administration; it qualifies this ignore query,
not real Git import/commit/publication. Run `6a5e1017-a55a-4d21-91a7-e7aac8594671` then passed the 30 phase/collector
observations, but subsequent evidence checking found an **unbounded, unused anonymous volume** at the image-declared
`/var/lib/postgresql/data` path. Both attempts used tmpfs `/pgdata` for their actual database; neither original profile
qualified the claim of no persistent mounts. A successful owner-label inventory had missed these unlabelled volumes.

The two current volumes were removed only after matching saved creating-container mount identities, anonymous-volume
metadata and creation times, and confirming no current container reference. `anonymous-volume-cleanup.json` retains
that correlation; no global prune was used. The corrected profile covers the image-declared path with **1 MiB tmpfs**
and rejects any persistent volume in captured mounts. Run `dc5a39b9-d34b-4b56-9aef-be0f25000ffc` repeated all 30
observations with that correction. The final run additionally binds instrument/runtime configuration and captured
profile-file digests into policy/receipts. All four exact instruments and results remain; the intermediate passes
are limited to their recorded profiles, not relabelled final qualification.

The four runs retired respectively **5, 8, 8 and 8 containers** by full ID/owner. The last two profiles created no
volumes; the first two anonymous volumes were explicitly retired as above. No networks were created. Final owner
container inventories were empty and successful runs preserved main/TASK-00044 service starts.

A bounded reinspection of the original October 6 successful run's two saved database profiles found the same unused
anonymous mounts. Its earlier “no volumes” statement above is corrected; its recorded container retirement does
**not** prove those volumes' retirement or a wholly tmpfs-only writable profile. Their exact saved names/creating IDs
are in `fixed-gate/historical-volume-caveat.json`. No current historical-volume inventory or mutation was performed;
retirement remains unverified. Do not transfer the new profile's cleanup to that old run. Its measured `/pgdata`
behavior remains evidence, with this added storage/accounting limit.

PHP/Python and PostgreSQL use the preceding restricted-check image IDs; Node uses
`sha256:dd9d21971ec4395903fa6143c2b9267d048ae01ca6d3ea96f16cb30df6187d94`. Non-root read-only containers have dropped
capabilities, no-new-privileges, disabled logging, 0.5 CPU, no extra swap, 16 MiB shared memory, 256 descriptors and
zero core limit. Candidates have 256 MiB memory/64 PIDs with 16 MiB `/tmp` and `var` tmpfs; PostgreSQL has 512 MiB/
128 PIDs, 128 MiB data, 16 MiB `/tmp` and 1 MiB image-declared-path tmpfs. Only migration/PHPUnit join the exact
private database's loopback-only network namespace; all other phases have network none. No external installs, credential/socket/home mounts or
canonical Git are available. Output is capped at 65,536 bytes per phase with a 90-second driver deadline. This
run did not fault the collector or compose controller-independent supervision. Host snapshots/evidence remain
unquota-qualified; do not combine this result with the separate ledger into a claim of integrated accounting.

**Remaining R3 gap:** measured-phase completeness is not semantic completeness. Candidate application/tests still
execute in their language runtimes and can affect their own behavior. Required independent behavioral observations,
review/acceptance of verification-surface changes, production policy provenance and durable collector composition
remain unqualified. The experiment deliberately neither seeds product tests nor claims to defeat arbitrary malicious
PHP. Newer develop's frontend/coverage/export gates and dependency acquisition also remain unqualified here. No
check-ready state, Awaiting review transition, production Agent launch or full-TASK acceptance follows.

## External HTTP behavioral observation — 2026-10-13 UTC

This **R3 / AC-4** follow-up measures four existing public HTTP contracts from a separate trusted process rather than
accepting candidate PHPUnit output. Run `7034378e-3bdf-4c00-bc4b-52b8dafcaaf7`, 22:30:26–22:30:38 UTC, passed
**17 scoped observations** in `.runs/tasks/TASK-00138/semantic-observer/`. This advances one concrete independent
behavioral-observer capability; it does not establish complete required semantic coverage or replace the full gate.

The fixed observer implements the existing `ApplicationSmokeTest` expectations outside the candidate runtime:

- `GET /`: exact 200, plain-text content type and `Fight Agent OS is ready.` bytes.
- `GET /api` and `GET /api/v1/not-an-agent-os-route`: exact sanitized 404 JSON, JSON content type and `no-store`.
- `POST /api/v1/auth/csrf`: exact sanitized 405 JSON with the same error response controls.
- The three errors receive an oversized supplied correlation header; returned correlation IDs must be 32 lowercase
  hexadecimal characters. Method/path detail must not appear in the exact two-field error body.

An archived snapshot of `b5e15f3e2fc6941ae37e8ecf39246f3d9242731b` and a separately copied vendor tree mounted
read-only into the candidate. PHP's built-in HTTP server ran the actual `public/index.php` bootstrap/routes; no test
route, candidate test, PHPUnit runner or quality-tool mutation participated. The separate Python HTTP process mounted
**only its pinned observer script**, not candidate source, vendor, tests or receipt storage. It sent actual TCP requests
through the candidate's private loopback namespace, parsed bounded responses and computed these assertions itself.
The controller retained exact source/vendor manifests, instrument/observer digests, candidate/observer profile digests,
IDs, requests, output bytes/digest and exit status. This is host-collected evidence, not newly qualified durable
signature custody. Independence here means runtime/source separation, **not independent technical review or QA**.

After the four responses matched, pausing only the owned candidate left its namespace present but unable to answer.
A fresh observer returned **unavailable / exit 23**, not its previous success. Unpausing and starting another fresh
observer obtained all four matching responses again. This is a controlled paused-server availability sample, not
service lifecycle, cancellation, scheduler readiness or all failure/interleaving qualification.

The first run `b7e73eca-be44-4f56-a48b-199f5fbf917c` observed four empty 500 responses and failed (observer exit 24).
Its launcher omitted required bootstrap environment. Source inspection found mandatory database-URL configuration
and eager CSRF configuration validation. The corrected fixed launcher supplies an inert loopback database URL and
test origin, and generates a fresh synthetic CSRF key **inside the candidate**, without putting it into host command
arguments, profiles or logs. No database service is started, no credential/CSRF success operation is exercised, and
no real secret or production provider is enrolled. Both exact instruments, raw results and failures remain. This
corrects the experiment's startup, not application source or expected assertions.

Both processes use pinned PHP/Python image `sha256:e4e87bae623511ed0ad325774c701cf2902f01d4d906cfd2dcf74da32fb7e1fe`,
UID 65534, read-only root, dropped capabilities, no-new-privileges, 0.5 CPU, 256 MiB/no extra swap, 64 PIDs,
16 MiB shared memory, 256 descriptors, zero core limit and disabled Docker logging. Each has 16 MiB `/tmp`; only the
candidate has a further 16 MiB `var` tmpfs. Candidate network is none; observers join only that exact candidate's
network namespace, not its PID or mount namespace. No host port, new network, volume, home, socket or canonical Git
mount exists. At most candidate plus one observer run concurrently. HTTP reads cap bodies at 4,096 bytes, header
lines at 4,096 bytes and headers at 32, with two-second socket timeouts; the controller imposes a 15-second observer
deadline and 32,768-byte output cap. Those overflow/deadline branches were not separately stress-tested here.

The failed run retired two containers; the repeat retired four by exact ID/owner. Both owner inventories were empty;
the successful run retained unchanged source/vendor/observer bytes and main/TASK-00044 service starts. Snapshot and
output retention remains outside qualified physical quotas; no new aggregate-accounting or historical-volume cleanup
claim follows. The earlier historical anonymous-volume retirement caveat remains unresolved.

**Selection limit:** these four baseline contracts are the entire selected observation set, not the full product
specification. A candidate can deliberately produce those responses; the evidence proves the observed external
behavior, not its internal implementation, every route, database effects, future requests or execution of every
intended assertion. Verification-surface changes still need authorized expectation selection/review. The minimum
production direction is protected exact-subject phase collection **plus named external observations where needed**,
not generic semantic attestation. Required policy/coverage qualification remains R3 here; production selector/
collector integration belongs to TASK-00140. This disposable observer enables neither check readiness nor Awaiting review.

## Fixed retention store and restart-failure bounds — 2026-10-13 UTC

This **R2 / AC-4** follow-up measures a trusted retained-artifact storage boundary and service failure behavior,
not a complete physical host quota or Engine fence. Final run `93a1faa9-cd0a-4bd7-95be-2e0df1ffd7de`,
23:17:38–23:17:53 UTC, passed **35 scoped observations**. Exact driver/supervisor/fill instruments, configuration,
launchd records, container profiles, disk-image associations and results are under
`.runs/tasks/TASK-00138/retention-fence/` in the authorized worktree. No production service, Agent or provider was enrolled.

### Fixed-size trusted retention candidate

`hdiutil` created a **64 MiB non-sparse read/write UDIF image with Journaled HFS+**, mounted only at an owned scratch
path with ownership enabled and Spotlight creation disabled. This is a trusted-host retention experiment: **no
container received this mount**. It neither retries nor rehabilitates the earlier APFS/container-bind lifecycle.
HFS+ here is a local candidate, not a cross-platform selection or encrypted credential store.

| Actual operation | Observed result |
|---|---|
| Two independent writers, each capped at 40 MiB | Both reached ENOSPC; together they wrote 64,815,104 bytes. They shared one filesystem limit, not two independent per-file allowances. A pre-existing 128 KiB inert artifact retained its hash. |
| Smaller writes after the large-write failure | 16,384 bytes remained usable. A bounded 512-byte tail writer exhausted that remainder, then observed ENOSPC and zero available blocks. Large-write ENOSPC alone had not established inability to commit a small control record. |
| Unlink while retaining an open descriptor | The 34,078,720-byte file lost its directory entry but retained its allocation. A fresh write still returned ENOSPC with zero new bytes. Directory absence is not physical reclamation. |
| Close descriptor and delete fill files | A fresh 1 MiB write and `fsync` succeeded. This demonstrates scoped reuse, not an assertion that every filesystem block or host allocation was reclaimed. |
| Normal detach and remount | Original artifact hash, complete terminal control state and volume UUID matched. Both detach operations succeeded without force, with jobs already unloaded. This is clean lifecycle continuity, not abrupt-power-loss durability. |
| Image extent and host allocation | Image length and sampled `st_blocks × 512` were both exactly 67,108,864 bytes before and after. Deleting interior files did not shrink this reservation. |

Reserve the **whole image**, filesystem metadata and explicitly separate overhead before creation; summing live
artifact lengths would under-account it. A fixed image bounds its addressable filesystem writes, not outer APFS
copy-on-write/snapshots, arbitrary numbers of images, controller evidence, Docker images/metadata or PostgreSQL WAL.
The trusted operator can resize/replace an image; no protection from that authority is claimed. This does not
qualify Docker reading snapshots from the mount, concurrent detach, native Linux, encryption, backup rollback,
complete quota-reclamation accounting or the existing production storage adapters. No host exhaustion was attempted.

### Service retry budget and unavailable control storage

A disposable launchd GUI-session job loads fixed trusted configuration outside the mount. Its narrow operation is
ownership-checked **removal of one already known inert container**, not create/start, Workflow execution or admission.
It requires the expected mounted device, an existing owner/subject record and existing lock; no missing state is
initialized. Under `flock`, it persists an attempt counter with file sync, atomic replacement and directory sync
**before** accessing the Engine. Two attempts are allowed. This file is a fixture control record, not the earlier
PostgreSQL reservation ledger or a complete independent authority store.

`KeepAlive = { SuccessfulExit = false }` and `ThrottleInterval = 3` request restart after the fixture's failure exit
17. Budget exhaustion deliberately exits **zero to stop launchd retry**, never to report a successful check or
cancellation. The hold remains true even after the positive known-ID removal; no capacity release was implemented.
Managed stdout/stderr go to `/dev/null`, while an 8 KiB file-size limit bounds each supervisor-written state file.
This avoids an application log growth path but does not bound launchd/system diagnostics or total host processes.

| Probe | Observed result |
|---|---|
| Truly full control filesystem | The service exited without changing the reserved record or removing the running container. Persist-before-effect failed closed; it did **not** prove quiescence. |
| Space restored; valid connector | A fresh job committed an attempt, inspected full ID/owner, removed that exact container and observed 404. The controller independently observed absence. |
| Missing Engine socket | Actual Unix `connect` failed with `FileNotFoundError` / ENOENT. Launchd performed three runs: two persisted unsuccessful attempts, then a budget-exhausted exit zero. The container stayed running and the hold stayed true. |
| Connector restored, explicit kickstart | A new PID still saw the exhausted counter, made no new removal attempt and retained the hold. Merely making Docker reachable did not renew authority or reset history. |
| State file removed | The service stopped without recreating it or removing the live container. The trusted driver restored the exact saved fixture bytes afterward. |
| Image normally detached | A new service process rejected the bare mountpoint rather than writing control state into the underlying host directory. After remount the terminal state was unchanged. |

**Critical limit:** refusing a new effect because the control store is full leaves an existing workload alive.
The fixture is not an adequate production cancellation/watchdog composition. Separate protected control/emergency
capacity and an independently enforceable retirement path still need qualification; a diagnostic/artifact pool must
not consume the last capacity needed to preserve cancellation intent. The budget guards only failures **after** the
trusted supervisor initializes: invalid executable/configuration, pre-guard crashes, hostile replacement, logout,
suspend/reboot, absolute watchdog deadlines and service-manager restart failures remain unqualified. The mounted
device check is scoped to this controlled lifecycle, not authenticated storage-incarnation or race-proof path custody.

A missing connector is **not a stopped Engine**: the actual shared daemon remained healthy and the workload continued.
No accepted-but-stalled create/start, daemon restart or lost daemon response was injected. Neither an exhausted
supervisor nor an empty inventory settles unknown effects. Keep their admission/claims/reservations held until causal
settlement or a separately qualified fence; a dedicated owned Engine/failure domain is needed to test daemon faults
without disturbing shared workloads. No such execution-domain fence was qualified here.

### Retained failures, isolation and disposition

- `b2bc8a93-3c1d-4b8d-9e80-a4f8e3615f7c` failed the full-store expectation: large allocation failures still allowed
  the small control commit and owned-container removal. The instrument had not exhausted smaller allocations.
  Its result/commands survive, but it did not save that intermediate control record before retiring the image.
- `b749788d-c7b0-4528-a7a2-c5fca574ecba` passed its 33 assertions after adding tail exhaustion. Subsequent evidence
  inspection found its intended unavailable connector exceeded the Unix socket address limit. Direct recheck of
  that 178-character address returned `AF_UNIX path too long`; it qualifies address failure, **not connector loss**.
  The final run uses a short absent path relative to the fixed job working directory and explicitly verifies ENOENT
  and three launchd runs. It also retains the original synthetic artifact bytes and initial service configurations.
- Each run retained its exact instruments. Respectively one/two/two inert containers, one/five/five temporary jobs
  and one disk image per run were retired. Images were detached normally after checking their exact image-path /
  device / mount association, then deleted. No force detach, global cleanup or unrelated service mutation occurred.
- Containers used the preceding pinned `e4e87b…` PHP/Python image, UID 65534, read-only root, no mounts/network,
  dropped capabilities, no-new-privileges, disabled logging, 0.25 CPU, 128 MiB/no extra swap, 16 PIDs, 1 MiB shared
  memory, 64 descriptors and zero core limit. At most one ran, with a finite 120-second inert sleep. That fallback
  deadline was not the cancellation mechanism tested. No new Docker volume/network or credential mount was created.
- Main service start times were unchanged. Images/cache and bounded experiment receipts remain outside the tested
  retention pool. Earlier historical anonymous-volume retirement is still unverified; this cleanup does not cover it.

R2 advances for this fixed filesystem, deletion/reuse distinction and post-initialization restart-budget primitive.
Supported complete lifecycle, unknown daemon effects, aggregate host/retained-storage bounds and composition with
actual durable authority remain open. R1/R3 receive no new qualification. TASK-00139 remains blocked; TASK-00140
owns production composition, not a transfer of these still-missing minimum proofs out of TASK-00138.

## Owned execution-domain retirement — Q4 qualification

The [finite completion contract](../../planning/tasks/00138-TASK.md#completion-contract-and-resume-plan--2026-10-13)
requires a measured independent fence, not another missing-socket or empty-inventory sample. Run
`ada70cfc-fe45-46fa-ba93-0b6ac5056fa0` in `.runs/tasks/TASK-00138/engine-fence/` passed **25 scoped observations**
on October 13, 23:39:30–23:39:51 UTC. It closes the prior full-store retirement gap for a **pre-owned private
execution domain with a healthy outer control plane**, not for an arbitrary unavailable host.

### Selected minimum profile and predeclared bounds

Select non-root fixed check containers in an installation-controlled, pre-owned private Engine domain, with an
independently armed host retirement capability outside ordinary application/artifact storage. Agents still receive
no shell, root, socket, VM administration or mutable check policy. The disposable VM below is a **trusted fault
injection domain**, not reinstatement of the superseded Engineer-root workspace profile. Actual deployment of the
combined ledger/checker/fence remains TASK-00140; production check readiness stays disabled until that composition
and its actual activation source are verified.

| Profile element | Fixed input and supported boundary |
|---|---|
| Host/lifecycle | macOS 26.6.2 arm64, logged-in GUI session, available host storage and healthy outer sandbox service. Logout/reboot/suspend and outer-service failure are unsupported here |
| Check capabilities | The six fixed phases below, read-only source/vendor/policy, private loopback test DB, no Agent keys/socket. Existing measured PHP/Node ceilings are 0.5 CPU, 256 MiB/no extra swap, 64 PIDs; DB is 0.5 CPU, 512 MiB, 128 PIDs; 90-second phase deadline and 65,536-byte output cap |
| Owned fault domain | One VM, one CPU, 1 GiB RAM, 20 GiB logical root plus 2 GiB private Docker disk; no workspace/skills/SSH-agent forwarding. Cached pinned shell template, `--pull never`, deny-all network policy |
| Inert fault workload | At most one running container: UID 65534, read-only, network none, no capabilities, no-new-privileges, disabled logging, 0.25 CPU, 64 MiB/no extra swap, 16 PIDs, bounded tmpfs and finite 180-second loop. One additional create request was queued, not launched |
| Admission/retention witnesses | Earlier durable ledger: two runtime units, 131,072 retained bytes, four lifetime history rows; exact retries and exhaustion preserve holds/cancellation intent across replacement. Separate fixed-image witness: 64 MiB retention pool, whole-image reservation rather than live-file sums. These are measured fixture thresholds, **not a deployed aggregate ledger or freely interchangeable units** |
| Cancellation | Host manifest armed before faults, with a 120-second trigger deadline and 90-second owning-tool call timeouts. The sample requests retirement before expiry and observes it within that window. This is not a universal hard-latency SLA or proof of the deadline-only fallback; missed/uncertain completion cannot release holds |
| Emergency custody | Read-only exact VM name/UUID manifest and separate host reaper/receipt location, outside the deliberately full application store. This budget must not be charged to an exhaustible artifact allowance |

The private-domain apparatus used retained, natively signature-verified Docker Sandboxes **0.45.1**, revision
`9d79d90…`, binary SHA-256 `f007d36533f5b8af4b0347c859443ca884276cf04bb61e366aa3aa30e47f6981`, and pinned ARM64
shell template `b2c007bb…`. The exact full template identity is in `setup.json`/the kit. An already retained
PostgreSQL image archive was transferred offline solely to supply the inert shell workload. No database server,
Agent/model, provider request or personal credential was enrolled inside that domain.

### Actual failure and retirement sequence

1. Record the VM UUID, outer container ID, private Engine/boot identities, workload ID/PID and matching native
   `containerd-shim-nerdbox-v1` process. Arm a distinct launchd-owned reaper with the immutable domain manifest.
2. Exhaust a separate 64 MiB HFS+ application-control image down to small allocations. A new control write returns
   ENOSPC. Kill a synthetic controller client (`sbx exec … sleep 180`, observed exit -9); do not renew or release
   the fixture hold. This client is a stand-in, not an implemented application controller.
3. Through an already established diagnostic channel, suspend **only the owned private `dockerd`** with SIGSTOP.
   Its process reports stopped; a real Engine query times out. Direct guest process observation shows the workload's
   counter still increasing. Engine unavailability and client death therefore do not establish cancellation.
4. Send a bounded create request into that Engine's Unix socket. No response arrives and the socket remains open.
   This is **kernel-queued/unacknowledged**, not evidence that the daemon accepted the create or entered a particular
   internal transaction. The distinction is retained in the instrument and result.
5. The independent host reaper rechecks UUID ownership, stops and then removes that entire domain through the
   outer service. Stop alone explicitly retains restartable state. Removal completes at 23:39:49 UTC; the pending
   diagnostic client exits 137, the exact native shim disappears, and both recorded backing files are absent.
   A subsequent `exec` of the retired name fails without recreating it. The full application store and hold remain
   unchanged throughout; the retirement path does not need its next write to succeed.

This supplies a bounded **whole-domain retirement primitive** for guest-local late effects. It is stronger than
scanning the private Engine: its process boundary and retained execution state are removed. The fixture has one
serialized outer owner; it does not qualify a race with another outer provisioner recreating the same name, unknown
**outer VM** creation, package claim release or durable fence-certificate publication. Those need TASK-00140's
actual authority/ledger/provisioner composition. The trusted experiment driver remained alive and requested the
pre-armed reaper; autonomous application-controller failure detection was **not** demonstrated. The Q4 retirement
claim is supported by the actual full-store condition, not a claim that killing this stand-in qualified a complete
controller lifecycle. An already-issued external provider effect would not be cancelled
by removing this domain; the sample is mountless/deny-all with no such effect. No shared Docker Engine was stopped.

### Failures and retirement receipts

The first setup (`9c06ffab…`) rejected `--deny-network '*'` before creation; the documented pattern is `**`.
The first created-domain run (`5c7956aa…`) reached the full-store/client-death/frozen-daemon condition, then failed
because a **fresh** `sbx exec` required Engine readiness and returned a start error instead of running the intended
query. The corrected instrument opens one observation channel before the fault. The first domain also stopped
between preparatory inspections; those observations are not uninterrupted-availability evidence. Exact failed
sources/results remain. Unneeded main-service configuration fields were discarded from the initial setup capture;
only identity/start-time metadata was retained, without displaying environment values.

Both created domains, their known workload resources and two backing images each were retired; both HFS+ images
were normally detached/deleted; both temporary reaper jobs were unloaded. `rm --force` skips the owning tool's
confirmation, not filesystem force-detach. Causally associated image paths and shim identity support cleanup;
no anonymous-volume label inference or global prune was used. The sandbox daemon was initially stopped, was started
by this experiment, and was returned to stopped only after empty inventory/backing verification. Shared main
service starts were unchanged. Cached templates/images and bounded receipts remain; historical anonymous-volume
retirement is still unverified and was not inspected or mutated.

## Frozen required-check manifest — Q5 qualification

The qualification manifest is **exactly** policy `e304da2c…` from fixed-gate run `eb09b54c…`, source `59f406a…`,
source manifest `a257d82d…` and vendor manifest `a2c2d19c…` (full identities above). Its pinned instrument and protected
file hashes are retained in `policy/policy.json`. An installation-owned selector, not candidate source or an Agent,
chooses this manifest. It contains the following complete finite phase set:

| Required phase ID | Fixed capability / what the protected result establishes |
|---|---|
| `database-prepare` | PHP Doctrine migration invocation against its disposable private DB; selected preparation completed, not production schema/restore acceptance |
| `harness` | Node's selected Pi presentation tests ran on the frozen input; no qualification of managed Agent identities |
| `composer` | Strict manifest validation with plugins/scripts disabled; not installation or dependency supply-chain certification |
| `planning` | Trusted read-only Planning validator completed against the subject; no publication or TASK acceptance |
| `architecture` | Protected Deptrac selection completed; not independent semantic architecture approval |
| `phpunit` | Protected runner/config/bootstrap executed the selected suite; process outcome, not proof hostile PHP ran every assertion |

All six have measured successful capabilities. Each omitted phase, duplicates, unsigned/modified receipts and
cross-attempt/source/vendor/policy replay were rejected. Extra-phase success could not fill a required slot. Reuse
those exact-subject observations; do not relabel them as fresh final-head receipts. Subsequent branch changes are
Markdown-only; the final repository build separately checks the actual candidate's changed Planning/docs.

Verification-surface changes (tests, bootstrap, tooling, dependencies, configuration and selected expectations)
require independent review and a newly authorized policy/source binding. Candidate-controlled semantic claims are
not accepted merely from counts/JUnit/exit zero. The four HTTP contracts observed externally at `b5e15f3…` establish
that selected out-of-process behavioral capability on **that** subject; they are supplemental evidence, not a seventh
receipt silently rebound into the `59f406a…` manifest. Additional product contracts need named observations or review
when the production policy selects them; universal PHP attestation is not required.

No manifest phase lacks a supportable mechanism for this exact qualification source. Durable signing-key custody,
actual selector/collector/domain composition and qualification of newer develop's frontend/coverage/export phases
are explicit TASK-00140 activation blockers, not unmeasured phases hidden in this frozen manifest. The disposable
HMAC key was not retained, so later review can correlate recorded bytes but cannot replay HMAC verification. No
production check-ready or Awaiting review state follows from Q5 qualification alone.

## Qualification disposition and next owners

The following historical inventories retain risks and activation obligations. The approved finite Q1–Q6 mapping
in the TASK now owns qualification closeout; these lists do not create additional experiment queues. Q4/Q5 are
builder-supported at the bounded mechanism level above, not independently accepted or production-composed.

- **R1 / AC-1:** see [current authority decisions](TERMINAL_AUTHORITY.md#current-authority-decisions--2026-10-06).
  The catalog, explicit `SUPER_ADMIN` seed and storage qualification candidate are now owner-approved. Package
  release/adoption, concrete managed assignments and protected sink/key qualification remain prerequisites.
  [Protected-file custody](PROTECTED_STORAGE_QUALIFICATION.md) supersedes the failed Keychain candidate and has
  separate bounded access/restart/recovery evidence, retaining broker-held signing. That evidence does not qualify
  ordered storage, actual broker authority or always-on host/runtime support. These check experiments use no
  package authority and supply none of that proof.
- **R2 / AC-4:** local bounded execution, private services, controller-loss stop, service-managed restart/serialized
  launch, known-ID terminal removal and durable logical reservation/retention/history primitives are measured.
  A fixed trusted retention filesystem and bounded post-initialization service retries are additionally measured.
  Complete physical aggregate/retained-storage bounds, supported service lifecycle, unknown-create/engine-failure
  fencing and the composed authority/ledger/supervisor remain open. Separate samples do not qualify their integration.
- **R3 / AC-3/4/5:** fixed branch-gate phase capabilities, external exact-subject receipts, omission/forgery rejection
  and four externally observed HTTP contracts are measured, not complete required semantic coverage, durable
  policy/receipt custody, full file-tool race resistance,
  managed role identities or production source publication. Prior file/handoff evidence was not rerun.

These experiments do not replace the mandatory repository `./bin/build`. The original run omitted Composer/Planning/
architecture/Pi; the fixed-gate follow-up measures those branch-specific components. Newer develop's frontend/coverage/
export gates, dependency installation and actual Git operations still need fixed qualified capabilities; no host-shell
fallback is allowed. Kernel/engine or trusted-controller compromise, native Linux, hostile multi-tenant support and
universal isolation are not qualified.
TASK-00138 remains in progress and TASK-00139 blocked. Independent review must assess this checkpoint's claims and
remaining requirements; passing these primitives cannot independently accept the whole TASK.
