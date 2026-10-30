# Local resource lifecycle

Use this contract when Work/Build or a later TASK stage creates or reuses a local runtime through this target's
`bin/runtime`. [Landing Standards](LANDING.md#cleanup-and-handoff) remain cleanup policy authority; the
[cleanup operation](../../.pi/skills/land/references/cleanup.md) owns retirement schema and safety checks.
This is owning-command capture, not shell interception, a global registry or permission to delete resources.
Uninstrumented historical resources retain evidence-specific holds; never retrofit original intent.

## Prepare once, before allocation

Resolve the canonical safe checkout from `git worktree list --porcelain`. Create one private run beneath its
`.runs/tasks/TASK-NNNNN/runs/`, outside the selected disposable checkout. Use `umask 077`. Write a private
`spec.json` there with these explicit inputs (absolute canonical paths, no secret values):

- `project`: exact `taskNNNNN-<isolated-suffix>`; `config_files` and `env_files`: ordered path lists, with no
  default/ambient env-file fallback. Include all target isolation overrides. The helper records input and resolved
  configuration digests. Changes require an explicit rebind, not editing an already bound file.
- `policy.services`: every service/profile mapped to `{data, authority}`. Data is `disposable`, `durable` or
  `unknown`; authority names the approved workload/data disposition, not merely a task-like resource name.
- `policy.budget`: integer `operations`, `seconds` (per command), `containers`, `networks`, `volumes`, `images`,
  `caches`. Maximums are 128 operations, 1,800 seconds and 32 of each resource kind; select smaller real budgets.
  Historical/replaced resources still consume evidence capacity. Do not reset a receipt or allowance to continue.
- `policy.oneoff_authority` for standalone disposable workloads; `policy.cancel_authority` only when stopping
  the exact original one-off is authorized. Builds additionally require `policy.image_authority` and `policy.builder`,
  naming an already-running same-context local Docker builder. The helper never bootstraps a builder.
- `private_paths`: `{path, disposition: preserve, reason}` entries for expected generated/runtime/cache directories.
  Classify private settings, credentials and evidence explicitly; do not exclude them as reproducible caches.
- `protected_files`: exact file paths, or an evidenced empty list. The helper captures full file records.

Run the selected target's owning commands:

```sh
./bin/runtime init --task "$task_id" --receipt "$receipt" --spec "$spec"
./bin/runtime validate --stage work
# Now use the target's existing bin/composer, bin/client, bin/up and other wrappers as needed.
```

`$receipt` is the SAME durable companion used through Work, repairs, Review, QA, Land and resume. The private
`var/resource-lifecycle.json` locator binds it to the selected checkout; application runtime never reads `.runs/`.
Intent is flushed before effects. Original command returns, creator inspections, mounts, image declarations,
source identity, builder observations and process identities are private immutable evidence beside that receipt.
Never dump raw config, environment or Docker inspections to public output.

The wrappers route Docker allocation and path preparation through this interface. Compose one-offs no longer
auto-remove: their exact original creators remain until qualified Land retirement. Compose create captures stopped
creators before startup; returned IDs/operation labels plus original dispatch/absence and mount declarations
corroborate allocation. Standalone runs use create then attach. An asynchronous event watcher is unnecessary.
Command subprocesses run from the safe checkout, have recorded identity and finite supervision, and do not need
an abandoned observer cwd inside the candidate. Active coordinators, reused PIDs and foreign processes remain
protected; there is no arbitrary process-kill capability.

Existing non-TASK/CI callers without a locator preserve their ordinary wrappers; they are not claimed as enrolled
or captured. A TASK feature branch must initialize before using the owning wrappers. Explicit Compose build
inputs are supported conservatively; implicit pulls, external/unknown builders, output/cache exports and opaque
Dockerfile inputs hold before the affected effect. Pull bases explicitly; built tags must be task-project-owned
and absent. Existing task image references are never silently overwritten. Normal runtime reuse needs no setup.

## Stage intake and recovery

At every actual role entry and before Build advances, run `./bin/runtime validate --stage <actual-stage>` from
the selected checkout. When its original monitor `process.json` exists, add `--attempt "$attempt"` to retain the
original monitor/Pi identities as protected observations. The Build entrypoint validates before dispatch; Build
also validates after consuming the child's handoff. Missing required binding, pending allocation, changed inputs,
uninstrumented project resources or contradictory identity holds advancement. An exit 0 is completeness evidence,
not independent Review/QA or cleanup authorization. Pure source inspection does not allocate another runtime.

Every role uses the SAME locator/runtime and owning wrappers for its additions. Never initialize a second stack
just because the role, session, commit OID or repair attempt changed. Retain original failed attempts and unknown
outcomes. A source change alone is recorded in the next operation/stage snapshot; a changed runtime configuration
requires a new private spec and `./bin/runtime rebind --spec "$replacement_spec"`. This records the old binding
without claiming allocation. Subsequent owning creation records the new generation; old resources remain inventoried.

On failure, inspect the last original operation and its exact evidence, reconcile original CLI/workload liveness,
and stop dependent allocation. Do not blindly repeat setup. The supported finite recovery commands are:

```sh
./bin/runtime reconcile --operation "$original_operation"
./bin/runtime resume --operation "$original_startup_operation"
./bin/runtime cancel --operation "$original_oneoff_operation"
```

Reconcile observes the retained original creator, dispatch, returned ID/iid and mounts without creating another
Docker resource. A pending original directory preparation may complete only its recorded paths. Resume starts
only an incomplete original startup's retained creators, never recreates them. Cancel requires original explicit
authority, exact disposable one-off identity and then records its stopped outcome. A missing original proof,
changed identity or still-running unknown CLI stays held. If initialization wrote its receipt but failed writing
the locator, retry the exact original init/spec/receipt on unchanged source; it verifies the original intent and
binds only that initialization. Never replace its receipt. No atomic Docker/filesystem transaction or exactly-once
effect is promised. Restore unavailable evidence storage through its owner, not protection-changing cleanup.

Build's cleanup-only post-worker launch is the one safe-checkout exception. Supply `--cleanup-receipt "$receipt"`
to the provider stage entrypoint as well as its normal pinned model/attempt arguments. It verifies an existing
exact-task plan at that safe checkout instead of requiring the disposable runtime locator. The child must use
Build's existing `cleanup-continuation.py` with the ORIGINAL Land attempt; that caller independently qualifies
both original exits/PIDs and invokes the same cleanup operation. This flag neither publishes nor authorizes
an alternative retirement path.

## Generate and execute the existing cleanup plan

Land supplies only actual delivery/worker/preservation facts, not a manually reconstructed ownership inventory.
Write a private delivery JSON with `oid`, `publication`, `exited_workers`, `protected_files` and, for Build's
post-worker path, `original_land_attempt`, using the existing cleanup schema. Optional `worktree`/`branch` entries
can explicitly retain source/ref with reasons; otherwise the isolated candidate is proposed for removal.
`worktree.preserve_to` and exact justified `exclusions` use the existing preservation contract. Bind independent
acceptance, QA, actual publication and original worker evidence at Land, never during Work to manufacture eligibility.

```sh
./bin/runtime plan --delivery "$delivery"
# From the canonical safe checkout, using the owning Land helper:
python3 "$land_skill_dir/scripts/cleanup.py" --receipt "$receipt"
python3 "$land_skill_dir/scripts/cleanup.py" --receipt "$receipt" --apply
```

Plan generation copies original identities, creator bindings, authority, dependencies and holds mechanically.
Fresh consumer checks are deferred, not replaced by asserted empty lists. Pending operations hold their dependent
resources/source/ref; unrelated independently eligible effects still run. Preview records qualification but exits
1; apply completion requires all required effects to be resolved. Inspect every per-resource result and independent
absence/preservation observations. Held/failed/removed/absent/policy-retained are distinct; a shared/unknown resource
cannot become retained simply to obtain aggregate completion. A cache association/retirement capability that is
unqualified remains a cache-specific hold, with complete original builder/worker/ID observations, never prune.

To change a candidate after new legitimate evidence, use `plan --delivery "$delivery" --refresh`. It archives the
old immutable plan/hash in this SAME receipt and links the next hash; all attempts and uncertain effects survive.
`qualify-image --identity "$exact_original_root"` may add the supported root-reference retirement capability only
from unchanged original build/input/naming/data proof. It does not upgrade reused images or unknown cache ownership.
Refresh explicitly afterward. Legacy receipts without lifecycle capture still use the existing cleanup contract's
bounded historical adoption/evidence holds, not this original-creation path.

## Direct qualification

Run `python3 .pi/skills/land/scripts/check-creation.py --evidence "$new_evidence_directory"` only within bounded
TASK fixture authority. It records original intent, allocates two independent fixture Git worktrees, uses the
already-present PostgreSQL image without pulling, and builds one metadata-only scratch image on the already-running
local builder. It exercises wrapper creation, short-lived mounts, shared data, concurrent worktrees, nonzero workload,
interruption/cancel/resume, real evidence-write failure, same-operation recovery, replacement and interrupted cleanup.
Controlled hosting is explicitly NOT actual publication or independent role execution. Inspect `result.json`, commands,
all per-resource outcomes and exact absence/protected-state observations; cache uncertainty remains incomplete cleanup.
The check is an owning-tool qualification, never a product-suite test. Actual independent role callers must separately
record their own stage journey and verdicts. Failed/partial fixtures retain original evidence and finite dispositions.
