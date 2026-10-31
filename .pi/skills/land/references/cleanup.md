# Exact-subject local cleanup

Read this before any Land cleanup, including cleanup-only. [Landing Standards](../../../../docs/engineering/LANDING.md#cleanup-and-handoff)
own policy. Run `scripts/cleanup.py` beside the owning Land skill from the subject's canonical **safe** checkout,
never from the removal candidate. Build's post-worker continuation uses
`../../build/scripts/cleanup-continuation.py` instead. Both execute the same operation; neither publishes.

## Creation-to-retirement binding

For targets with `bin/runtime`, first read the target's [local resource lifecycle](../../../../docs/engineering/RESOURCE_LIFECYCLE.md).
It owns original capture, same-receipt stage validation/recovery and mechanical plan generation. Land supplies actual
delivery/worker facts only; it must not reconstruct ownership from Markdown. The generated plan uses this SAME
schema/executor. `consumer_policy: fresh` with `consumers: null` defers the conclusion to the executor's existing
live consumer checks; a known nonempty consumer list never qualifies. Per-resource `runtime` binds original
creator generations (including explicit profiles), rather than applying the newest runtime to older resources.
Compose one-offs additionally carry immutable original dispatch/returned-ID/configuration proof; stopped standalone
one-offs may retire their creator without claiming or removing its reused network. Unsupported running workloads
remain held. No publication, preservation or fresh consumer guard is waived.

A lifecycle `return-to-verification --plan-sha256 HASH` is the explicit return from a finished unpublished,
non-destructive held/retained preview to supervised SAME-runtime verification. Read its
[supported/rejected states](../../../../docs/engineering/RESOURCE_LIFECYCLE.md#return-from-an-unpublished-preview-to-verification)
before use. It preserves exact original receipt bytes and an immutable versioned transition; the old plan is
ineligible for preview/apply under the SAME persistent lock. No allocation/removal authority is added. Actual
publication and fresh qualified current-source `plan --refresh` are required to leave that state; the new plan
retains the original plan, attempts, protected records, uncertainty and transition/snapshot link. The explicit
`--failed-setup-run RUN_ID` mode additionally supports an exact ready OPEN published candidate after its actual
current-head hosted setup failure and skipped gate, with completed strictly no-effect held/retained attempts.
It preserves the same guards/freeze and all original history; read the lifecycle's
[published setup-failure contract](../../../../docs/engineering/RESOURCE_LIFECYCLE.md#return-a-published-candidate-after-hosted-setup-failure)
before use. Fresh independent acceptance/QA, repaired publication and linked replan remain necessary. Effects,
interruption, merged/closed/changed identity or unknown outcomes never gain return authority.

An erroneous completed legacy append to an already suspended published return is **not** a retry/reset case.
Normal entry must hold. Explicitly authorized recovery uses only the lifecycle's
[bounded audited successor](../../../../docs/engineering/RESOURCE_LIFECYCLE.md#recover-a-completed-legacy-no-effect-append-during-suspension),
which qualifies the exact original source/result/read-only-command path and current identity/protected evidence.
It preserves every actual attempt verbatim, including the legacy attempt's missing plan-hash field. The old
plan stays ineligible; later accepted repaired publication and explicit linked Land replan retain the incident.
Never run an older helper, hand-edit a transition or replace the main receipt to bypass suspension.

A lifecycle `plan --refresh` archives the old immutable plan/hash and links the new hash inside the SAME receipt;
legacy linked-companion recovery below remains for historical receipts without this versioned lifecycle. Do not
rewrite old attempts or relabel eligible unknowns retained. Direct single-manifest image roots may omit platform
in their descriptor; the helper corroborates the separate engine platform with exact `inspect --platform`, retaining
the original descriptor unchanged. Unknown/incomplete graphs still hold.

## Inputs and result

Extend the existing private JSON Work/Land handoff or receipt with `cleanup` below. Do not create a resource
registry. If the narrative receipt is Markdown, keep its existing companion JSON and link it from that receipt.
The JSON must already be owned and mode `0600`, under `<safe>/.runs/tasks/<TASK-ID>/runs/`, outside all removal
candidates. Preserve original narrative, acceptance and delivery evidence. Never put credentials/environment
values in this document; store only locators and digests. Ordinary existing Land authority applies, without
another approval prompt. A missing proof produces a hold, not authority from the input file.

The following is a **schema description**, not a runnable fixture or established ownership:

- `cleanup.version`: `1`; `cleanup.plan`: the immutable exact-subject inventory below.
- `task`, `safe_checkout`, `git_common_dir`, `origin`: verified TASK and canonical absolute Git identity.
- `ref`, `oid`: full local `refs/heads/feature/task-NNNNN-…` and exact 40-hex published candidate. Retain the
  accepted-candidate/closeout bridge in the existing receipt; no unpublished local commits are eligible.
- `publication`: `url`, `repository` (`owner/repository`), numeric `number`, `base`, local `base_ref`,
  `delivery_verified` (true only after the owning intake establishes applicable QA/provenance/delivery gates),
  `merge_oid` and `merged_at` (null for open). Each effect freshly reads this exact PR. Merged retirement requires
  the recorded merge in the intended local base; published-open additionally freshly reads the exact remote ref.
- `protected_files`: list of `{path, record}` for canonical reports and retained evidence/private files. Populate
  `record` with this helper's `file_record(Path(path))`; include explicit `[]` only when none exist. These bytes,
  modes, ownership, flags, extended attributes and macOS ACLs are checked before effects and at final intake.
  An explicit retained checkout may contain these files; an actual worktree removal candidate may not.
  Integrity verification still applies to retained/held checkouts and malformed dispositions. A held or
  missing/invalid disposition cannot produce completion; retention grants no removal authority.
- `exited_workers`: list of `{pid}` from the original monitor's `process.json`. Any live/reused PID holds effects.
  Initially use `[]` when no original worker is pending. `original_land_attempt` binds the original monitor attempt
  for Build continuation. Set it and both PIDs before the worker's first operation, once `process.json` exists.
- `containers`, `networks`, `volumes`, `images`, `caches`: explicit lists (at most 32 each), including `[]`
  only when proven none. Missing storage inventory holds legacy receipts until the owner recovers it in a
  linked new receipt; no discovery expands lists. `protected_files` is bounded to 128 entries.
- `worktree`, `branch`: the entries below. A non-removal entry still requires an explicit identity and reason.

Every resource entry has `identity`, `disposition` (`remove`, `retain`, or `hold`), `reason`, `ownership`
(`proven` or `unknown`) and `consumers` (list; only an established empty list qualifies). Missing/unknown fields
hold that resource. `retain` is an intentional policy exclusion, **not** a substitute for an unresolved cleanup
hold. Do not relabel an unsafe eligible resource retained to manufacture completion.

Additional entry fields:

| Kind | Fields and observations |
|---|---|
| worktree | `identity`: absolute registered path; `inode`: current directory inode; `preserve_to`: absolute new/private canonical TASK run directory; `exclusions`: explicit `{path, reason, disposable: true}` relative directory/file entries (or `[]`). Exclude only proven reproducible caches/dependencies, never reports, captures, private settings, keys or unknown files. Everything untracked/ignored not excluded is copied; all source files enter stability checks. |
| branch | `identity`: same full `ref`. Checked against the exact OID and every worktree; deletion uses compare-and-delete, never force branch deletion. |
| container | `identity`: full Docker ID; `name`: exact `/name`; `service`; `inspection_sha256`: `container_identity(docker inspect result)`; `processes_sha256`: SHA-256 of current `docker top ID -eo pid,ppid,args` stdout (null if stopped); `data`: `disposable` only for positively qualified temporary data; `bind_data`: map of exact writable bind source to `preserve`. Writable binds outside the candidate hold. Every volume mount must be inventoried; the helper durably captures exact creating-container mount provenance before stop/removal. Volume removal is a separate qualified effect. Hashes do not replace corroboration of actual mounts, service processes, current consumers and handoff ownership. |
| network | `identity`: full ID; `name`; `key`: exact Compose network key; `created`: Docker's exact Created value. Removal requires a nonexternal current configured network, matching project/key/name, zero endpoints and no stopped configured consumer. |
| volume | `identity`: exact Docker name (Docker exposes no separate immutable volume ID); `inspection_sha256`: `volume_identity(inspect)` including creation time, driver, mountpoint, labels/options/scope; `data: disposable`; nonempty `authority`; `creation` below, with exact `container` and `destination`. Unknown/durable/shared/external volumes are never discarded by task association. Recreated identity holds. Docker's logical display size/links are freshly observed; physical reclaimed bytes remain unknown. |
| image | `identity`: full `sha256:` Docker image ID returned by exact inspection, not an assumed config/reference/index equivalent; `creation.kind`: created/reused; size/dependencies/retention reason. Removal additionally needs complete inspect `inspection_sha256`, `data: disposable`, explicit `authority`, proven creation and no current/stopped consumers. Without explicit `retirement` proof, tagged/digest-referenced images and indexes still hold. The bounded owned-root/reference capability below supports qualified immutable graphs and exact owned references; it never grants layer ownership or general GC. Docker's normal shared-layer protections remain. Reused images are observed retained, never discarded by association. |
| cache | Exact builder-owned identity, creation versus reuse, size/dependencies and disposition/reason. A reused cache may be policy-retained only with explicit `builder`, sorted `worker_ids`, `created_at`, `parents` and `type`, freshly matched against `buildx ls`/exact-ID `du`; mutable, missing or changed identity holds. Size/shared/reclaimable/dependencies are observed, never reclaimed-byte claims. Created or removal-requested cache still holds: exact deletion is unqualified. Never substitute prune, nominal retention or a manufactured `[]`. |

Image absence requires an explicit exact `docker image inspect` not-found response corroborated by successful
`docker image ls --all --no-trunc --quiet`, both after removal and at final verification. Default listings hide
dangling/intermediate images and never prove absence. Inspection/permission errors or contradictory observations
hold. Same-receipt already-absent resume additionally requires a prior matching `image_observation` of the exact
ID and creation inspection hash; an unresolvable config digest alone cannot establish image absence. Legacy
config-ID consumers and current-daemon manifest-descriptor consumers are distinguished; an unknown or mixed
relationship holds. No reference is silently untagged and no index is guessed equivalent to a config/manifest.

### Immutable declarations and owned root references

The normal creator config-ID declaration lookup remains first. Only an explicit not-found response can enter
`container.image_declaration: {path, sha256}` recovery; denied/ambiguous lookup still holds. This private retained
JSON record has `version: 1`, exact `task`, `origin`, `container`, `config_id`, immutable `root` and full
`platform` (`os`, `architecture`, optional `variant`). It references original private snapshots through
`original_containers`, `original_platform_images` and `original_root_plan`, each `{path, sha256}`. The first two
are original Docker inspect objects/lists; the last is an immutable original cleanup receipt whose image entry
binds the root's complete inspection hash. The operation matches the original creator/config/mount identity,
creator manifest/platform descriptor, immutable root and current platform Config/RootFS/declarations. A mutable
tag, guessed config/index equivalence, newly asserted empty declarations or missing original evidence never
qualifies. Named and image-declared anonymous mounts still traverse every existing declaration, creator,
provenance, authority and consumer check. The declaration observation is retained separately in each attempt;
existing mount-provenance records are not rewritten.

For an explicitly owned tagged/index root, add `image.retirement: {kind: owned-root-references, proof: {path,
sha256}}` in a **new linked plan**, never by turning an old HOLD into remove. The retained private proof has:

- `version: 1`, exact `task`, `origin`, `root`, `data: disposable` and the entry's nonempty `authority`;
- `original_image: {path, sha256}`: the original full root inspection, equal to `inspection_sha256`;
- `references`: sorted complete original `RepoTags` plus `RepoDigests`, without duplicates (maximum 32);
- `graph`: the owning `image_root_graph(safe, original_image)` result: exact root descriptor, every available
  image child descriptor/platform and Config/RootFS hashes, and every qualified attestation's target;
- `basis: {source_revision, evidence}`: exact original creating Git revision and 1–16 retained original
  source/build/allocation/naming records, each `{path, sha256}`. These corroborate the exported root and every
  owned tag; digest references must bind that same root and owned repository naming relationship. Inspect the
  actual original intent/allocation/build/source/data authority before assembling proof. A new assertion,
  task-like name, unique tag or absent consumer is not original creation or discard authority.

Original `creation.kind: created`, intent/hash, data/authority and all subject/publication/protected/worker
checks remain required. Recover actual original proof or return a specific evidence hold; do not invent
retrospective intent. All evidence is owned/private, bounded and outside removal candidates. Hashes detect
drift; they do not authenticate arbitrary caller assertions or grant permission.

This capability reads the SAME local CLI engine's bounded read-only image/manifest API, corroborating its engine
ID before and after observations. Root tags must agree exactly as sets between exact CLI inspection and the
complete engine summary; duplicate summary entries do not create references. A summary may omit repository
digests only when exact CLI and same-engine API root/digest inspections positively agree on the complete root,
immutable identity and reference set. Every digest must name that root and an owned repository/tag relationship;
no digest is inferred from a tag. Extra/contradictory summary references, foreign/rebound claims, changed census
or denied lookup hold. Raw OCI-load tag names remain exact census names, not guessed normalized aliases.
A remote/unknown endpoint, unavailable API, incomplete/unknown child, denied observation or platform/config
mismatch holds. No version equality gate, registry query, save/build/pull, shared-cache growth or installation
is needed. Complete all-current/stopped container and engine-platform consumer observations precede each effect.
A consumer through a shared-platform alias still holds. All foreign/reused roots, references and child descriptors
are observed before/after every effect; shared children/base layers need not be exclusively owned merely to
retire an independently owned root reference.

Normal `docker image rm` retires only an exact owned reference/root. The complete owned before-set and immutable
proof/graph are saved before effects; the last naming relationship may remove its repository digest and root.
A refusal holds, never force/prune/blanket untagging. Exact root not-found plus all-image inventory and each
original reference's exact not-found/census establish postconditions. Rebound tags, unknown absence, changed
protected roots or failed inspection hold. Same-receipt interruption recovery needs the prior matching root
observation **and effect intent**; it requalifies the current graph/reference subset, protected roots and all
consumers, then performs only missing effects. No prior plan, attempt or unknown outcome is erased. Physical
reclaimed bytes and layer GC remain unknown/unpromised. Existing untagged-image absence/legacy input guards
remain separate and unchanged; the helper never silently upgrades old HOLD plans.

Every present Docker removal entry has `creation.kind: created`, `creation.intent` (absolute retained private
0600 creation-intent locator outside removal candidates) and `creation.intent_sha256`. Volume creation additionally
binds exact creating container/destination and source revision. The caller records intent and exact named-volume
absence before creating workloads, then captures returned container IDs and actual mounts while creators still
exist; distinguish image-declared anonymous storage from configured named storage and merely used resources.
The intent must corroborate real workload creation/source/data authority; a hash or receipt never creates discard
authority. The helper persists `cleanup.mount_provenance` atomically before container effects, binds it to the
original container inspection/runtime, and retains it after disappearance. Missing provenance holds volume
removal even for an already-absent creator. Never overwrite original evidence to qualify a changed workload.
Docker volume identity fields are conservative recreation detection, not a hostile-actor fence or opaque ID.

For Compose Docker effects (`runtime.kind: compose`, also the legacy default), `runtime` must carry the **already qualified** `project`, `working_directory`, `ownership`,
`config_files`, `env_files` (ordered `{path, sha256}` lists; no ambient/default-file fallback), and
`resolved_sha256` (helper `fingerprint` of `docker compose --project-name PROJECT --project-directory CWD
--env-file FILE --file CONFIG … config --format json`, parsed as JSON). Retain the exact wrapper invocation in
its existing handoff. The helper resolves that same context, compares its digest and each container's current
Compose hash/working/configuration paths, and never starts setup. Environment changes that alter the resolved
model hold it. Implicit Compose env-file loading is disabled; only enumerated env-file arguments are used.
Do not print raw configuration or container environments to obtain these hashes.

### Standalone creation capability

For independently owned `docker run` resources, select **`runtime.kind: standalone`**, not an optional Compose
validation switch. Set `ownership: proven`, `creation_record: {path, sha256}` and `resolved_sha256` (helper
`fingerprint` of that parsed record). This capability never invents Compose labels/configuration, calls setup,
requires the old source worktree to exist or relaxes the Compose path. Compose-labelled subjects hold here.
All ordinary subject/publication/protected-file/worker/host-session checks still apply.

Keep the immutable owned/private 0600 creation record and each evidence file outside removal candidates. Its
schema is `version: 1`, exact `task`, `origin`, `source: {revision, workload}`, nonempty `authority`,
`data: disposable`, `basis`, and maps `containers`/`networks` keyed by full returned IDs (maximum 32 each).
`source.revision` is the original creating revision, not automatically the later published candidate. `basis`
has `kind: allocation` and `evidence: [{path, sha256}]` (1–16 retained original allocation/intent files).
Evidence must corroborate that revision, each returned ID, container configuration fingerprint and network
creation timestamp/configuration. Names, labels, a before/after delta or newly written assertion alone do not
establish creation. Capture the allocation output and inspected identities at creation; retain unknowns.

Each container proof has exact `name`, `image`, `inspection_sha256` (`container_identity`), `mounts_sha256`
(`fingerprint` of actual Mounts), `workload: {kind, processes_sha256}` and `volume_declarations`. Each network
proof has `name`, `created`, `inspection_sha256` (`network_identity` excluding mutable endpoints/counters).
Ordinary container/network entries and creation-intent fields above remain required. The helper checks exact
current config/image/mounts, all connected networks and stopped/shared/unknown peers before normal stop/remove.
No host/published-port, privileged, auto-removing or Compose-labelled standalone runtime is qualified.

Supported consumer inspection is deliberately finite: `idle` (one exact sleep process), `postgres` (local
psql client-session count using the existing POSTGRES_USER/POSTGRES_DB defaults), and `mysql` (local root client-session count using the existing
container environment, never printing its password). Running subjects require exact current `docker top`
processes, zero established TCP/IPv6/connected Unix sockets, and the applicable live DB-session query. A changed
process, client connection, unavailable tool/permission or unsupported workload holds; do not stop its consumers.
Other session/authentication/topology needs belong to their resource-specific capability hold, not an arbitrary
caller-provided shell probe. Stopped subjects must still match immutable identity and qualified workload type.

Volumes use the SAME durable creator-mount capture and all-current/stopped-consumer removal checks. For every
standalone removable volume, `volume_declarations` must contain `{type: volume, target, allocated_name,
created_absent: true}` corroborated by the declaration-list fingerprint in original allocation evidence.
The exact volume entry still needs creator/destination, private original intent, `volume_identity`, disposability
and authority. Merely attached/pre-existing/external or ambiguous volumes hold; unknown/durable data is not
converted to disposable. No global registry, implicit volume discovery or association-based storage deletion.

### Immutable historical adoption (never retrospective intent)

For a valid historical standalone receipt, leave its bytes, attempts, plan hash, subject, unknowns and original
allocation evidence untouched. Write a **new linked companion receipt** in the same subject's canonical runs.
Copy the original plan and explicitly reconcile only the supported resources/evidence; carry original protected
files, publication, exclusions and uncertainty. The new creation record uses `basis.kind: legacy-adoption`,
`basis.prior_receipt: {path, sha256}`, `basis.prior_plan_sha256` (fingerprint of the original cleanup plan), and
`basis.evidence` pointing to original allocation outputs/configuration observations. Each adopted container or
network entry has `creation: {kind: legacy-created}`; do not manufacture `intent` or change an original hash.
Original task/origin/candidate must match; adopted IDs must be a subset of the original plan. Actual allocation
proof/source/config fingerprints must still corroborate every adopted ID. Legacy volume evidence without the
original creator/destination/intent is a **volume-specific hold**, not retrospective creation proof.

The owning intake must inspect original allocation/narrative evidence and corroborate authority/data before
writing the linked record. Hashes protect the selected proof from drift; they do not authenticate arbitrary
assertions or grant discard authority. The helper independently requalifies live identity, sessions, processes,
connections, mounts, consumers, permission and publication before any effect. Missing historical immutable
configuration/allocation proof is a precise resource-specific evidence hold, never inferred from its name.
The old worktree is not a prerequisite for independently owned resources; retained evidence lives externally.
Absent IDs/names are observed without reading retired runtime inputs; an absent ID with a recreated name holds.
For Common TASK-00166 all four historical standalone effects are already complete: **do not run adoption or
cleanup again for them**, recreate resources or repeat Git/publication. Its original diagnostic and final manual
result remain chronological evidence under TASK-00542's ignored handoff, not an outstanding disposal request.

Use Python's `importlib.util.spec_from_file_location` to load the owning helper for `file_record`,
`container_identity`, `fingerprint` and `digest` while assembling the receipt from bounded owning-tool
observations. These are observation functions, not authorization. Preserve private observation captures outside
the candidate; independently inspect ownership and data before selecting `remove`.

## Execute finitely

Bind shell variables `land_skill_dir` and `receipt` to the owning skill and exact absolute JSON receipt.

```sh
python3 "$land_skill_dir/scripts/cleanup.py" --receipt "$receipt"
python3 "$land_skill_dir/scripts/cleanup.py" --receipt "$receipt" --apply
```

The first invocation records a non-destructive preview. It exits 1 because no cleanup has been completed;
inspect every resource result, not just the exit. The apply invocation records intent before each effect,
performs only eligible normal removal and reobserves actual state. Each effect retains command argv/exits and
output digests privately (not environment values); stdout stays a resource summary. It continues independent resources once;
there are no automatic retries. A persistent sidecar flock prevents overlapping invocations. Atomic `0600`
receipt updates preserve previous attempts, manifests and uncertain effects. Exit 0 requires apply completion;
exit 1 includes holds, errors, preview or failed postconditions. Inspect `cleanup.attempts[-1]`, all statuses,
external manifest bytes and independent Git/Docker postconditions before accepting its claim.

Normal Land must use this operation for **all** local retirement. Do not substitute shell `rm`, `docker stop/rm`,
Compose teardown, `git worktree remove` or branch deletion after a helper hold. Do not alter ACLs, modes,
ownership or flags, force, sudo, disconnect foreign endpoints, kill consumers or prune to clear it. Permission
failure retains the affected resource and protections. Return resource/reason/recovery owner/action once.

The operation preserves every nonexcluded ignored/untracked artifact with old/new locators, per-file digests,
modes/ownership/extended attributes/ACLs and source stability checks. Each manifest retains both source and
copied records. Copy selection comes from the complete no-follow filesystem inventory minus exact tracked
files and approved exclusions, not Git's incomplete untracked listing. Nonexcluded directories retain their
metadata. On macOS the helper transfers directory ACLs/stat/xattrs with native metadata-only, no-follow
`copyfile`, without data or recursive-copy flags; directory children still enter only through the exact inventory
selection. Python `copystat` alone is not full macOS xattr support. Only the checkout's root `.git` entry is Git
metadata, never nested Git-shaped evidence. A digest of
the retained inventory binds every expected source path/record to the manifest; completeness and copy integrity
are verified before removal, afterward and on resume. macOS may add `com.apple.provenance` when copying a
Docker-created file; only that additional origin attribute is allowed, recorded and checked unchanged afterward. All source attributes must remain
present, with identical bytes/modes/owners/ACLs/flags; no metadata is stripped to force equality.
Validate exclusions before inventory: they must name nonempty relative paths beneath the candidate, not the
candidate itself or a parent. A symlink at or beneath an explicitly approved disposable exclusion is recorded
using its own metadata and link target, without following it, copying it or weakening source-stability checks.
Nonexcluded symlinks, special files, foreign-owned and flag-protected artifacts still hold. Every file remains
in the stability inventory, including excluded dependency trees; exclusions do not waive ACL/ownership checks.
On macOS, native no-follow `find -xattr`/`find -acl` observations before and after each inventory identify paths
needing detailed metadata reads, avoiding per-file subprocesses for absent metadata. An unavailable or changing
metadata observation holds rather than assuming no attributes/ACLs. Unavailable consumer visibility also holds. Existing conflicting
copies are never overwritten. Retain the source until its owner resolves the exact copy/visibility prerequisite.
Canonical reports are never rewritten. Deny ACLs and unavailable directory write/search permissions hold
before Git can partially remove a checkout. On the qualified macOS Docker Desktop topology, read-only handles
are distinguished from cwd/writers and requalified through the signed executable, process owner/birth,
descriptor type/mode, local context and engine. The Apple-signed VM must hold its Docker disk/kernel;
the Docker Inc-signed backend must hold the actual local engine/backend Unix sockets. Process/executable and
matching descriptors are reobserved unchanged. A read-only numeric LINK descriptor additionally requires the
same approved worktree and an explicitly disposable exclusion, owned no-follow symlink metadata/target, and
ordinary owned parents from the candidate to the link. Missing/invalid exclusions, protected evidence, flags,
deny ACLs or unavailable directory write/search access hold. Link and parent identity (including inode and
change times) and full metadata must remain identical across the SAME signed-reader qualification; the receipt
records them separately. Never follow a target to establish ownership, admit arbitrary readers or add LINK to
an unconditional type exemption. This does not waive any all-container/parent-mount/process,
preservation or source-stability check. Other readers, unknown visibility and changed identities remain holds. Parent-only container
mounts require successful live process cwd/descriptor inspection establishing no actual candidate use; explicit
candidate mounts always hold. The receipt records these observations; no shared VM/container is stopped.

If interrupted, resume the **same receipt** once after reconciling original worker exit and current resources.
A successful full Docker inventory reconciles an absent ID/name before reading runtime inputs: the candidate's
Compose files, env files and cwd may already have been retired. An absent ID with a recreated name still holds,
even when those inputs are gone. Present resources require all original runtime, ownership, consumer and data
checks; missing configuration never qualifies a removal. An absent checkout must still have a verifiable prior
external preservation manifest with matching retained-inventory completeness evidence. A legacy manifest
without that evidence holds: recorded-copy integrity alone cannot establish completeness. Recover evidence
through the resource owner; never recreate a removed checkout or assert retrospective preservation.
Already-absent effects are observed, not replayed. Changed inventory needs a new explicit
plan in a linked companion receipt preserving prior attempts/manifests; never erase uncertainty or change an
old plan hash. Do not repeat push/PR, recreate a removed checkout or commit cleanup results.

## Build's original-worker continuation

The original worker records its exact attempt and both monitor/Pi PIDs in the plan. It returns holds while it
is still active. Build observes that **original** monitor return and both exit files, verifies the processes
are absent, and dispatches its cleanup-only Land continuation from the canonical safe checkout. Carry the same
receipt and model pins; exclude the active coordinator and every unrelated resource. The continuation reads
these instructions and runs the Build caller, binding `build_skill_dir`, `original_attempt`, `receipt`:

```sh
python3 "$build_skill_dir/scripts/cleanup-continuation.py" \
  --attempt "$original_attempt" --receipt "$receipt" --apply
```

This caller refuses absent/wrong monitor evidence, live or reused PIDs, wrong subject and unsafe cwd, then
executes the same operation. Build independently reobserves Git registration/filesystem/ref and Docker IDs,
external file digests and retained protected files. Containers, named/anonymous volumes, networks, images/cache holds, checkout and ref use this same plan and
operation; no continuation-specific volume teardown exists. `removed`, already `absent`, policy `retained`,
`held` and `failed` remain separate results. Publication with any unresolved cleanup hold remains incomplete Land/Build.

## Enforcement and qualification boundary

This is a deterministic operation and its local skill callers, **not** a sandbox, shell interceptor or universal
capability restriction. An unrestricted agent can bypass it; instructions forbid doing so, but the OS does not.
Host scanning is conservative, not an atomic lock against arbitrary outside writers/consumers. Each effect
rechecks mutable facts; new external activity in the final check-to-effect interval cannot be universally fenced
by this local helper. Unavailable visibility holds instead of asserting absence. Missing capabilities hold only
affected resources; no Pi version equality check exists.

## Bounded direct qualification

`python3 scripts/check-lifecycle.py --evidence ABSOLUTE_NEW_TASK_RUN_DIRECTORY` creates at most one
Compose fixture container (two with `--case shared-stopped` or `--case durable`), two volumes (named and
image-declared anonymous) and one network,
reusing the already-present `postgres:17-bookworm` image without pull/build/install. It uses a task-local Git
fixture and explicitly controlled hosting response, never a real PR or live publication claim. Creation intent,
configuration, exact IDs/mounts, helper digest, stdout/stderr, exits and receipts remain in that directory.
`--resume` observes and resumes the same receipt without recreating resources; partial pre-receipt creation
recovers only from original intent/current fixture identity. No destructive fallback runs after a hold.

`--runtime standalone --prepare-only` records one new docker-run tmpfs container/network, retained allocation
proof and an explicit standalone plan for actual installed-Pi callers. Its default is an idle workload;
`--case database` uses the already-present PostgreSQL fixture image and `--case mysql` uses the already-present
MySQL fixture image, each tmpfs-only with disposable local test authentication, no published ports/pull/build.
They qualify the actual supported database consumer checks, not production authentication or general topologies. Without `--prepare-only`, it invokes the
same helper; `--resume --runtime standalone` observes the same receipt without recreating resources.
`--standalone-matrix` runs bounded normal/preview, interruption after real stop/removal with same-receipt resume,
missing/contradictory proof, unavailable inspection, an owned permission-denied socket, a live extra consumer,
stopped/shared/unknown endpoint, immutable legacy adoption versus missing-proof hold, and recreated-name probes.
It preserves an independent exact tmpfs sentinel/config/mount/mode/owner across the probes. An unsuccessful setup
retains its resources/evidence; inspect the exact receipt before a bounded `--resume` (never blanket recreation).
These direct fixtures use controlled hosting and do not establish independent acceptance or actual publication.

`--prepare-only` records the Compose fixture without invoking cleanup, for actual installed-Pi callers.
`--case running`, `failure` (observed workload exit 7) and `cancel` (explicit owning Compose stop) qualify their
actual workload states before retirement. `--case image` imports one uniquely labeled empty disposable image,
without registry/build cache or shared base layers, then qualifies exact image removal. The checker independently
requires exact inspect not-found plus an all-image inventory for removable images, and exact inspect presence for
retained images; helper success/volume absence alone cannot pass the image case. Original import argv/exits/output,
creation inspection, postcondition commands and observations are retained.
`--case image-root` loads two uniquely owned empty OCI roots (two platforms in the primary, one shared child
in its protected alias), without layers, builds, pulls or shared cache. It creates at most one **stopped**
Compose creator, one network and two volumes (named plus image-declared anonymous). Original intent/allocation,
source/ref/graph/declaration proof and controlled hosting remain private. It first observes the real stopped
image-consumer hold, then uses the SAME operation for eligible retirement and independent exact Docker
postconditions/alias preservation. `--interrupt` terminates only its helper after a real root/reference effect;
`--resume` reconciles the same receipt, never reallocates. `--resume --release-image-alias` explicitly releases
only the separately owned preserved alias via a linked new plan. `--original-land-attempt ABSOLUTE_ATTEMPT`
binds actual exited original monitor/Pi evidence and invokes the existing Build cleanup-only caller, not a
Build workflow or a live publication. It cannot be combined with interruption (never kill a caller parent).
These fixtures do not establish real delivery eligibility, independent Review/QA or physical reclaim.

`--case unavailable` selects an intentionally unavailable task-only Docker socket; `denied` applies real read
denial to the owned fixture receipt; `changed-identity` changes the owned container name. Each records the
nonzero safe hold and resource preservation, then releases only its explicitly controlled probe precondition.
Separate `--resume` qualifies recovery. Fixture permission/rename release is declared probe teardown, never
permission to clear a real resource hold. `--case durable` preserves a running consumer, declared durable volume
and exact data sentinel before an explicitly linked owned-fixture release.

`--case shared-stopped` additionally verifies an unchanged owned stopped consumer and held shared volume;
`--resume --release-consumer` writes a linked new fixture receipt to retire only that owned consumer and
remaining resources, preserving the original hold. `--interrupt` terminates only the newly created helper
subprocess after its first persisted container removal; a separate `--resume` qualifies recovery using retained
mount provenance. It never kills Pi, the coordinator or another owner's process.

Exit 0 means fixture retirement, an expected preservation/denial/identity hold, or observed controlled
interruption: inspect `pass`, `expected_stopped_shared_hold_verified`, `expected_probe_hold_verified`,
`interrupted_after_container_effect` and exact helper exit separately. A hold/interruption is not cleanup
completion. This is not fleet/installed-Pi proof, exhaustive interruption coverage, independent Review/QA or the
canonical gate. Actual installed-Pi and Common caller attempts are separately linked in TASK-00542's ignored
handoff, including unsuccessful attempts. Output lists separate qualification boundaries explicitly.
The pre-repair failing invocation and repaired same-receipt
resume live under TASK-00542's canonical ignored `runs/regression/`. All omitted required cases remain finite
qualification holds, not exemptions or acceptance. Use each actual owning runtime/caller for fleet proof.

TASK-00540's canonical ignored Work run records historical actual installed-Pi/owning-tool qualification, configured model
identity, controlled hosting responses, live Git/Docker/filesystem observations, failures and remaining coverage.
Those diagnostic probes are not independent Review or QA acceptance. Do not claim unrun scenarios or cross-project
adoption from helper-only success.
