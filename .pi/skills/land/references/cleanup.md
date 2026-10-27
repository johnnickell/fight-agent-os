# Exact-subject local cleanup

Read this before any Land cleanup, including cleanup-only. [Landing Standards](../../../../docs/engineering/LANDING.md#cleanup-and-handoff)
own policy. Run `scripts/cleanup.py` beside the owning Land skill from the subject's canonical **safe** checkout,
never from the removal candidate. Build's post-worker continuation uses
`../../build/scripts/cleanup-continuation.py` instead. Both execute the same operation; neither publishes.

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
- `exited_workers`: list of `{pid}` from the original monitor's `process.json`. Any live/reused PID holds effects.
  Initially use `[]` when no original worker is pending. `original_land_attempt` binds the original monitor attempt
  for Build continuation. Set it and both PIDs before the worker's first operation, once `process.json` exists.
- `containers`, `networks`: explicit lists (at most 32 each), including `[]` when proven none. No discovery
  expands these lists. `protected_files` is bounded to 128 entries.
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
| container | `identity`: full Docker ID; `name`: exact `/name`; `service`; `inspection_sha256`: `container_identity(docker inspect result)`; `processes_sha256`: SHA-256 of current `docker top ID -eo pid,ppid,args` stdout (null if stopped); `data`: `disposable` only for positively qualified temporary data; `bind_data`: map of exact writable bind source to `preserve`. Writable binds outside the candidate and volume-backed containers hold. Hashes do not replace corroboration of actual mounts, service processes, current consumers and handoff ownership. |
| network | `identity`: full ID; `name`; `key`: exact Compose network key; `created`: Docker's exact Created value. Removal requires a nonexternal current configured network, matching project/key/name and zero current endpoints. |

For Docker effects, `runtime` must carry the **already qualified** `project`, `working_directory`, `ownership`,
`config_files`, `env_files` (ordered `{path, sha256}` lists; no ambient/default-file fallback), and
`resolved_sha256` (helper `fingerprint` of `docker compose --project-name PROJECT --project-directory CWD
--env-file FILE --file CONFIG … config --format json`, parsed as JSON). Retain the exact wrapper invocation in
its existing handoff. The helper resolves that same context, compares its digest and each container's current
Compose hash/working/configuration paths, and never starts setup. Environment changes that alter the resolved
model hold it. Implicit Compose env-file loading is disabled; only enumerated env-file arguments are used.
Do not print raw configuration or container environments to obtain these hashes.

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
copied records. macOS may add `com.apple.provenance` when copying a Docker-created file; only that additional
origin attribute is allowed, recorded and checked unchanged afterward. All source attributes must remain
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
are distinguished from cwd/writers and requalified through the signed executable, process owner/birth, open
Docker VM disk/kernel, local context and engine. Other reader topologies remain holds. Parent-only container
mounts require successful live process cwd/descriptor inspection establishing no actual candidate use; explicit
candidate mounts always hold. The receipt records these observations; no shared VM/container is stopped.

If interrupted, resume the **same receipt** once after reconciling original worker exit and current resources.
A successful full Docker inventory reconciles an absent ID/name before reading runtime inputs: the candidate's
Compose files, env files and cwd may already have been retired. An absent ID with a recreated name still holds,
even when those inputs are gone. Present resources require all original runtime, ownership, consumer and data
checks; missing configuration never qualifies a removal. An absent checkout must still have a verifiable prior
external preservation manifest. Already-absent effects are observed, not replayed. Changed inventory needs a new explicit
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
external file digests and retained protected files. `removed`, already `absent`, policy `retained`, `held` and
`failed` remain separate results. Publication with any unresolved cleanup hold remains incomplete Land/Build.

## Enforcement and qualification boundary

This is a deterministic operation and its local skill callers, **not** a sandbox, shell interceptor or universal
capability restriction. An unrestricted agent can bypass it; instructions forbid doing so, but the OS does not.
Host scanning is conservative, not an atomic lock against arbitrary outside writers/consumers. Each effect
rechecks mutable facts; new external activity in the final check-to-effect interval cannot be universally fenced
by this local helper. Unavailable visibility holds instead of asserting absence. Missing capabilities hold only
affected resources; no Pi version equality check exists.

TASK-00540's canonical ignored Work run records actual installed-Pi/owning-tool qualification, configured model
identity, controlled hosting responses, live Git/Docker/filesystem observations, failures and remaining coverage.
Those diagnostic probes are not independent Review or QA acceptance. Do not claim unrun scenarios or cross-project
adoption from helper-only success.
