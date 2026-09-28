# TASK-00138 R2/R3 qualification follow-up

Started: 2026-09-27; continued 2026-09-28 UTC. Status: partial qualification; R2/R3 remain open.
This is implementation evidence, not an independent review verdict. No autonomous Agent, real credential, provider request or TASK-00139 work occurred.

## Baseline and scope

The authorized isolated branch is `feature/task-00138-r2-r3-qualification`, based on develop `a39bfc3`.
Four previously unmerged checkpoints were restored without conflicts: `aaa8a05` → `036bd85`, `c8cd176` →
`53ff590`, `ff3e0f1` → `594e67f`, and `ecd849e` → `3136608`. Their old evidence remains historical.
The accepted boundary still permits Engineer root and a private Docker engine in an Engineer-controlled VM.
The canonical TASK review remains revise; this follow-up does not amend its disposition.

Direct qualification used Docker Sandboxes 0.45.1, executable SHA-256
`f007d36533f5b8af4b0347c859443ca884276cf04bb61e366aa3aa30e47f6981`, and shell image
`docker.io/docker/sandbox-templates@sha256:b2c007bbb5dcdb60a29f3019c7160ebe0c50c968e08854fbba6093834f8f6a1b`.
Two mountless VMs used one CPU, 1 GiB memory, 2 GiB private-engine disks, shared skills off and deny-all network
policy. The private-engine fixture used offline image
`sha256:639ab7ceb90e13123085b741fb31ef493fba25463002f6da665352e7b534b652`. No host source, Docker socket or
credential directory was mounted. Synthetic host listeners served fixture bytes only.

Raw evidence is under `.runs/task-00138/r2-r3-study/`: `probe.py`, `commands.jsonl`, `result.json`,
`assessment.json`, and preserved `attempt-1-*` failures. The final result digest is
`bc8021cef18bebcab188f6a1f0ee5fab4c86aee60533387c62d36e733eef9dec`.
Thirteen scoped assertions passed; `launch_qualified` is false.

## Retained 2026-09-27 observations

| Boundary | Evidence | Limit |
|---|---|---|
| Guest authority and VM identities | Both probes ran as UID 0; private engines had different IDs; a privileged container used guest host networking. | Expected authority, not an escape-resistance proof. |
| Host TCP/UDP fixtures | Host-side IPv4 TCP/UDP and IPv6 TCP positive controls worked. Root probes delivered no fixture payload to host listeners. HTTP through the explicit proxy returned 403 for the host alias; direct HTTP produced no response. | A TCP connect or private-container write sometimes returned success followed by EOF. Those exit codes are not denial evidence; destination receipts are the relevant observation. |
| Sibling service | Engineer-root probes to validator IPv4 addresses received no application response. A privileged private-engine container also attempted a write. The validator's one-accept server remained available and subsequently returned its marker to a local positive control. | Sampled IPv4 service separation only; not all ports, encodings, gateways, protocols or policy mutation paths. |
| DNS and IPv6 | `example.com` lookup failed; guest IPv6 loopback connection was refused. | No DNS tunneling or non-loopback IPv6 qualification. The guessed IPv4 gateway sample is not exhaustive route coverage. |
| Independent result checking | A false worker claim accompanied immutable captured fixture bytes. A different VM checked the same SHA-256 and rejected the bytes; a valid control was accepted. | Establishes a primitive, not trusted application test execution, signed evidence, role authorization or Git admission. |
| Resource observations | Each guest saw one CPU and 1,023,824 KiB RAM. Four active backing files totaled 47,244,640,256 logical bytes (44 GiB), with 842,838,016 allocated bytes at the sample. | Host inventory agrees with requested CPU/memory; this is not external ceiling enforcement or an admission ledger. Allocation is a sample, not a maximum. |
| Retirement | Persisted VM IDs matched before stop/removal; final inventory and backing-file scan were empty; idle daemon stopped. | Only this attempt's backing files. Does not measure shared caches, retained artifacts/logs or paused-resource accounting. |

The first attempt failed because the pinned guest lacked `ip`. Normal stop succeeded; non-interactive removal
required `--force` to suppress confirmation. A fresh identity check preceded that removal. The corrected attempt
used `hostname` and `/proc/net/route`; its ordinary owned-resource cleanup completed. Neither failure was erased.

## Required work before R2/R3 can close

| Finding | Remaining evidence | Concrete next qualification |
|---|---|---|
| R2: complete bootstrap | The shell digest does not include the complete pinned Pi, Harness and project toolchain. | Assemble the exact immutable profile and verify installed identities and restart/recovery without model execution. Do not call a floating install a pinned profile. |
| R2: external limits | Guest-visible capacity and CLI configuration do not prove externally enforced CPU/memory ceilings, serialized aggregate admission or storage headroom. | Qualify host-controlled limits and concurrent reservation/recovery with active and paused VMs; include workspace/private-engine disks, shared caches, logs, artifacts, snapshots and overhead. Bound experiments; never fill the host. |
| R2: broker | Synthetic substitution does not qualify OAuth/account renewal, durable role/attempt/model/budget admission, global fallback denial or bounded admitted-stream cancellation. | Qualify the external broker and cancellation controller with synthetic providers first. Real-provider qualification requires a separately authorized credential path. Secret removal alone is insufficient. |
| R3: network/control surfaces | Current probes sample IPv4/IPv6 TCP/UDP but do not establish complete channel coverage, policy immutability, DNS tunneling resistance or host provisioning-control isolation. | Use controlled destination positive controls for each channel and attempt policy changes from guest root/private-engine containers. Include host aliases, actual gateways and sibling roles; disable unsupported channels. |
| R3: hostile Git | The 2026-09-28 checkpoint below qualifies a deliberately small, full-bundle quarantine and sampled hostile cases. | Select production-compatible limits/formats and qualify transfer interruption, adversarial delta/graph variants, prerequisite admission and atomic canonical publication; no publication was attempted here. |
| R3: independent evidence | The 2026-09-28 checkpoint checks an exact admitted commit using external, trusted structural and synthetic allocation checks; forged worker success is inert. | Qualify the actual repository quality gate, independently selected required checks/configuration, protected receipt persistence and current role/attempt authority. Arbitrary candidate code must not gain authority to manufacture its own successful receipt. |

Docker's current [architecture documentation](https://docs.docker.com/ai/sandboxes/architecture/) describes a
host-side MCP gateway, including host execution for local stdio servers. That path needs explicit treatment in the
profile: proposed default is no worker-controlled gateway registration or arbitrary host stdio/OCI tools. A future
first-party remote MCP allowlist must preserve independent authorization and cannot inherit permission merely from
network reachability. Verify behavior against the pinned release; current documentation is not runtime evidence.

The [authority contract](TERMINAL_AUTHORITY.md) remains the design reference. No production provisioner, broker,
reservation ledger or quarantine was implemented by these probes. Missing behavior remains Unverified; passing
repository checks cannot replace it. R1 also retains prerequisites beyond the upstream enrollment proposal.

## Repository verification environment

The retained 2026-09-27 full gate ran in a uniquely named `fight-agent-os-r23` Compose stack mounting this isolated
worktree, with its own PostgreSQL services and test database. This trusted repository-check stack is separate from the disposable
Engineer VMs and is not an approved autonomous execution profile. Locked dependencies were copied into a private
worktree vendor directory after comparing lockfiles.

The first full gate stopped at planning: a linked worktree's `.git` file points to an absolute host path unavailable
inside `/app`. The failed receipt is retained. An ignored verification-only Compose override supplies `GIT_DIR` and
`GIT_WORK_TREE` using a private, non-hardlinked metadata clone of the restored branch. No shared human Git directory
is mounted, no check is skipped and no tracked wrapper is changed. This also identifies a future worktree-wrapper
requirement; the current default wrappers are not sufficient for a linked-worktree container without configuration.
The second gate reached PHPUnit but failed because the newly created test database had not yet been migrated.
The documented `./bin/database test-reset` initialized only this stack's guarded test database; that failed receipt
also remains preserved. Final exits, timestamps and tested file digests are in `.runs/task-00138/r2-r3-study/verification.json`.

## Bounded external handoff checkpoint — 2026-09-28

### Review trace and experiment boundary

The canonical version-3 TASK review still says **revise**, for original head `c8cd176` on
`feature/task-00135-planning-and-skills`, base `96ef597`. Stable patch IDs match all four restored commits listed
above. The reviewed authority/qualification documents match `53ff590`; subsequent `594e67f`/`3136608` refine only
the R1 proposal/dependency/completion notes. They do not implement R1 or resolve R2/R3. `develop` remains `a39bfc3`,
the restored branch's ancestor. Existing uncommitted follow-up documents and both checkouts' retained receipts were
inspected and preserved; shared probe/result copies have identical digests. They are prior evidence, not rerun results.
The separate MCP discussion draft remains untouched and outside this qualification checkpoint's commit.

Fresh raw evidence is under this isolated worktree's `.runs/task-00138/quarantine-study/`. `baseline.json` records
restoration patch IDs, original document digests and the canonical review digest. `worker-capture.json`,
`worker-manifest.json`, `captured/`, `result.json`, `network/result.json` and `network-v2/result.json` retain inputs,
observed identities, outcomes and retirement. The Python instruments and command transcripts live beside them;
they are disposable qualification tooling, not product code, tests or an alternate Workflow runner.

One mountless Engineer VM ran the synthetic Git generator as UID 0 using the same pinned shell template/CLI,
one CPU, 1 GiB RAM, 2 GiB private-engine disk, shared skills/SSH forwarding off and deny-all network. The host
independently derived the approved synthetic base `526d5120040482d04fe3ac97ebdfa494ac70b55f` from fixed seed bytes.
Twenty-one bundles were copied out individually, bounded to 1,200 KiB for these known fixtures, hashed and frozen in
host-owned capture files. No worker path, archive extraction, project source or canonical Git metadata was exposed.
This selected-file synthetic capture is **not** qualification of a production streaming ingress byte quota.

### Quarantine profile and outcomes

Each admission ran in a fresh external Docker Desktop container, never inside the Engineer VM. Image identity:
`sha256:4ffdac3e75b5161eef9a84f7306e24468f2a607e7c0ad52180fbc8931d2aa9f2` (existing local verification toolchain).
Only one frozen input file and the trusted instrument were mounted read-only. There was no socket, canonical store,
network, checkout, hook, global Git config, replacement object or execution of candidate content. The image is a
pinned local qualification input, not the missing complete production Pi/Harness profile.

| Limit | Applied boundary |
|---|---|
| Input | Regular non-symlink file; SHA-256 binding; 1 MiB admitted bundle; 8 KiB header; exactly one full v2 handoff ref matching the selected 40-character commit OID; no prerequisites |
| Git objects | `index-pack --strict --max-input-size`, full strict `fsck`, approved base and selected head must be commits with ancestry; at most 128 objects, 512 KiB each, 2 MiB total expanded object bytes |
| Trees | Inspect every imported tree, including history; at most 64 visited entries per commit, depth 8 and path length 240; bounded ASCII components; reject dot/traversal/absolute/control paths, case collisions, `.git`, attributes/modules, all symlinks and gitlinks |
| Container | Non-root external validator, read-only root, no capabilities, no-new-privileges, network none, 0.5 CPU, 256 MiB memory with no additional swap, 32 PIDs, 16 MiB writable tmpfs, Docker log driver disabled; one at a time |
| Process/recovery | 192 MiB address-space, 10 CPU seconds, 8 MiB file-size and 64 descriptor limits; each Git command deadline at most 8 seconds; external 25-second container deadline; identity-checked whole-container retirement |

The external validator's restrictions do not change the approved Engineer-root/private-engine model. Logical
object/tree limits are checked **after** pack parsing, so the hard container/process ceilings remain necessary.
These conservative synthetic limits are not proposed as production repository limits. Untrusted tree content is
never materialized on the host, and no canonical publication occurs, even for passing inputs.

Final matrix: **26 matched outcomes** (24 admission/transport cases and two independent semantic checks):

- Valid and false-success commits both passed structural admission. Extra refs, stale head, digest mismatch,
  unrelated history without the approved base, symlink bundle input, oversized compressed input, malformed header,
  truncated pack and checksummed malformed commit object were rejected.
- A 4,579-byte bundle containing a 4 MiB expanded blob was rejected at the per-object limit. A 3,164-byte bundle
  expanded to 2,458,318 object bytes and was rejected at the aggregate limit. A 145-object fixture, 70-file tree
  and nested depth-10 fixture were rejected at the corresponding object/tree limits.
- Parent/absolute/`.GiT` tree names were rejected by strict Git parsing. Control-character paths, case collisions,
  escaping symlinks, submodules and `.gitattributes` were rejected before any checkout/publication.
- The first matrix exited 1 solely because its expected reason for unrelated history was ancestry rejection:
  that full bundle omitted the approved base and was correctly rejected earlier as `commit-type`. The expectation
  was corrected, not the admission policy. `attempt-1-*` preserves the original result, instrument and transcript.
  The complete repeat passed; neither attempt left an owned container behind.

Six additional direct resource/recovery observations passed: tmpfs returned ENOSPC at 16 MiB and permitted a 1 MiB
write after deletion; process creation returned EAGAIN after 31 children; a bounded 0.6 CPU-second workload increased
external cgroup throttling; a 300 MiB allocation was OOM-killed within the 256 MiB container ceiling (exit 137,
Docker `OOMKilled=true`); a sleeping fixture hit the shortened one-second watchdog and was externally killed;
a fresh independent valid-commit check succeeded afterward. The OOM/timeout outcomes were expected rejection
controls, not host exhaustion or successful candidate checks. These qualify this external quarantine's controls,
**not** Engineer-VM ceilings, sibling-load guarantees or aggregate host admission/accounting.

### Independent validation of the exact admitted commit

Fresh external validator containers repeated admission against the same captured digest and exact head, then used
the host-owned checker to read `allocation.json` directly from that commit. The synthetic contract requires bounded
integer capacity/allocations with total allocation no greater than capacity; it is not a production reservation ledger.
No worker test script, configuration or success report is executed or used as a verdict.

| Subject | Independent outcome |
|---|---|
| `5c3a9474f6028ff3acd6667e90638faa887c2713` | Admitted; trusted structural/schema/allocation check accepted allocations 2 + 3 within capacity 10 |
| `527ac25a6debef8b077991502c667d22385cfecc` | Admitted; trusted check rejected allocations 8 + 8 against capacity 10, despite a committed always-successful worker script and forged `accepted: true` receipt |

Host-retained receipts bind attempt, exact head, input SHA-256, validator image and trusted checker digest
`9dec25f04b428ed40d2b57517f2ae1e42fe6655312d9cb63d61ea74143749d57`. Seven fixture receipt-consumption controls
accepted only the passing exact subject and rejected failed/cross-commit evidence and changed attempt, image,
checker or bundle bindings. This is trusted, external validation of immutable synthetic commit content, stronger
than the earlier byte comparator; it is **not** a production receipt service, cryptographic attestation, independent
human review, arbitrary-code quality gate or role/attempt authorization integration. The repository's own full gate
below validates this documentation checkpoint separately; it is not a check run on these synthetic commits.

### Further root/private-container network sampling

Two successive two-VM probes used separate private engines and bounded host/sibling TCP/UDP fixtures with positive
controls before and after probes. `network/` retains the IPv4 run; `network-v2/` adds actual non-loopback IPv6
sibling addresses and default gateways derived from guest route tables, rather than the earlier guessed gateway
or IPv6 loopback-only sample. The second run issued 12 socket probes from guest UID 0 and repeated all 12 from a
privileged private-engine container with guest host networking and a read-only guest-root mount for its Python
runtime. This mount exposes only that disposable guest, never the human host.

No probe payload reached either controlled destination, and no fixture response reached the Engineer. Some TCP
connect/send operations completed with EOF; that is not success or, alone, denial proof. IPv4 private bridge address
`172.18.0.1` exists independently in both VMs, so that sample is not uniquely a sibling endpoint; the sibling's
VM-specific IPv4 and ULA IPv6 addresses supply the actual sibling samples. Both host and sibling IPv4/IPv6 TCP/UDP
positive controls worked. The UDP payload was DNS-shaped but sent only to owned high-port listeners: **normal
resolver forwarding, port-53 DNS tunneling, arbitrary destinations, route-policy mutation and forbidden host
provisioning/MCP control remain unqualified**. Local positive controls prove listener readiness, not an allowed
cross-boundary route; absent payloads do not alone identify the responsible firewall/routing mechanism.

All five new shell VMs (one Git producer, two IPv4 probes, two dual-stack probes) were stopped/removed only after
matching persisted IDs. Their new backing files disappeared, inventories were empty, idle daemons stopped and
host listeners closed. All 56 external quarantine/control containers across the two attempts were identity-checked
and removed. Captured synthetic bundles, transcripts and failed evidence remain under ignored scratch; no global
image/cache pruning, unrelated service restart, real credential, autonomous Agent or upstream write occurred.

### Exact requirement disposition

- **R3 / AC-4, partial:** supplies a concrete bounded external Git quarantine with valid/hostile rejection and
  recovery evidence; trusted external checks on the exact admitted synthetic commit; fabricated worker success
  does not become accepted evidence in this fixture. Adds root/private-container TCP/UDP host/sibling samples,
  including actual non-loopback IPv6. Neither full R3 nor AC-4 is satisfied.
- **R2 / AC-3 and resource portion of AC-4, partial:** supplies direct enforcement/recovery evidence for the external
  quarantine's CPU/memory/PID/tmpfs/deadline profile only. Complete immutable Engineer/Pi/Harness inputs, VM resource
  ceilings, active/paused reservations, shared overhead/retention, bounded sibling impact and production broker
  admission/OAuth/fallback/cancellation remain missing. Neither full R2 nor AC-3 is satisfied.
- **Production dependencies, not implemented here:** choose production-compatible Git formats/limits and immutable
  capture/admission; integrate all-writer locking and compare-and-swap publication; select trusted required checks
  independently of the candidate and protect outcome/receipt collection when executing arbitrary candidate code;
  bind receipt consumption to current role/attempt authority. Qualify normal DNS/policy/control surfaces and the
  external aggregate reservation/broker profile. These belong in separately authorized production work, not a second
  runner hidden inside this qualification TASK. Thin/delta/graph adversarial variants and interrupted transfers
  remain further qualification work; the small full-bundle sample does not establish universal parser resistance.
- **Unchanged:** R1 remains open while the upstream recovery contract is implemented, including its other enrollment,
  permission, secret-store and Markdown-publication prerequisites. TASK-00138 stays in progress/not launchable;
  TASK-00139 stays blocked; the canonical independent verdict remains **revise**.

### Fresh repository verification

The same isolated worktree/`fight-agent-os-r23` project was recreated on port 28088 after inspecting the earlier
private-metadata and unmigrated-database failures. The private, non-hardlinked metadata override was reused, no
canonical Git directory mounted, and `./bin/database test-reset` explicitly initialized this stack's guarded test
database. Database checks, planning regeneration/read-only validation, whitespace and full `./bin/build` passed:
188 planning records / 141 active, seven Pi tests, 147 PHP tests / 1,133 assertions, zero Deptrac warnings or
violations. Two routine migration notices occurred; no warnings/skips/deprecations appeared in the gate and no
coverage report was generated. The first whitespace check found one extra TASK EOF blank line; its exit-2 receipt
is retained and the corrected checks were rerun. No product-suite infrastructure tests were added.

Fresh logs, file digests, environment/ownership observations and local-link verification are in
`.runs/task-00138/quarantine-study/`, separate from the retained September 27 receipts. See the
[TASK completion notes](../../planning/tasks/00138-TASK.md#bounded-external-quarantine-and-independent-validation--2026-09-28-utc)
for the acceptance/Standards mapping and exact changed paths. Repository verification does not alter the canonical
review, accept the synthetic subjects into production or close the unresolved qualification requirements.
