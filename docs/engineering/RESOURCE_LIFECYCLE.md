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
and absent. Exactly one separate `--tag`/`-t` output is supported; repeated tags, compact/equal aliases and
other output/export forms are rejected before effects. Existing task image references are never silently
overwritten. Explicit pulls reserve a new image-evidence entry before dispatch unless an exact immutable reference
is locally inspected and its full result is already represented. Mutable tags or ambiguous/unavailable identity
still need spare original capacity; shared pulled roots never acquire removal ownership. Explicit startup builds qualify the actual enabled-profile dependency closure before creation;
`up --no-deps` suppresses dependency creation/builds, while one-offs remain dependency-free. Missing/opaque
inputs hold the whole build selection before its first build. Normal runtime reuse needs no setup.

Standalone runs preflight the exact present image, image-declared anonymous volumes, existing network and
historical evidence capacity before `docker create`. Supported explicit bind sources must already exist;
volume/driver mounts and unsupported allocation options are rejected, not forwarded. Replaced/absent evidence
still consumes the original bound. Noncreation Compose commands also use recorded finite supervision with
ordinary streamed output. Enrolled `exec` requires an explicit noninteractive `-T` target/command; detached
execution is unsupported.

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
changed identity or still-running unknown CLI stays held. Recovery checks every original dispatch before
releasing completeness or starting anything else. A partial creator does not prove the original CLI ended;
CLI absence alone does not resolve an unknown allocation/start outcome. Never kill a recovered/reused PID.
Cancellation may end an authorized exact one-off while its original read-only `docker wait` is active, but
completeness remains held until that wait also exits. After an exec timeout/interruption, CLI termination does
not establish remote workload exit: retain the hold until the exact original target container has stopped
naturally or its owner resolves that workload. Never stop unrelated service consumers to clear the hold.
Original exit uncertainty stays explicit after reconciliation. If initialization wrote its receipt but failed writing
the locator, retry the exact original init/spec/receipt on unchanged source; it verifies the original intent and
binds only that initialization. Never replace its receipt. No atomic Docker/filesystem transaction or exactly-once
effect is promised. Restore unavailable evidence storage through its owner, not protection-changing cleanup.

### Return from an unpublished preview to verification

If Land returns an unpublished candidate for integration or repair **after preview**, use the explicit owning
transition, never delete/edit its plan or replace the receipt/runtime:

```sh
./bin/runtime return-to-verification --plan-sha256 "$original_plan_sha256"
./bin/runtime validate --stage work
# Existing supervised Compose status/exec and the normal verification wrappers may now reuse this runtime.
```

This command uses the SAME exclusive persistent receipt lock as allocation and cleanup. It requires the exact
original subject/runtime and immutable plan/hash/history, complete original capture, unchanged original spec,
protected records and current creator/storage identities; no pending allocation or known original consumer
prerequisite; original Land monitor/process binding, recorded exits and both PIDs freshly absent; and successful
exact-ref/all-state exact-head hosting observations proving no publication. Missing or contradictory proof holds.
The supported preview is a finished, non-destructive attempt whose resources are held/retained and whose final
verification failed. No preview, partial/unknown outcomes, apply/effect records, published/delivery-qualified plans,
unknown cleanup fields or unqualified command forms remain held. This is deliberately not post-removal recovery.

The transition retains the **exact original receipt bytes** in a private immutable external snapshot and adds a
versioned immutable return record in the SAME receipt. The old plan/hash, attempts, protected records, original
allocation/budgets and unknowns stay unchanged. While suspended, cleanup preview/apply refuses before creating
an attempt; stale apply and resumed verification cannot race through the shared lock. Only finite supervised
noncreation Compose commands and ordinary inspection/validation are available. Allocation, rebind, cancellation
and allocation recovery remain frozen; this does not grant removal authority, clear resource holds or sandbox exec.
Focused wrappers that allocate a new one-off also remain frozen: use supported `bin/runtime docker compose exec -T`
on the recorded service for guarded test reset/migration and focused tests, rather than starting another creator.

After fresh independent Review/QA and actual publication, Land must explicitly run `plan --delivery ... --refresh`
with current-source, qualified delivery facts. Replanning requires a freshly matching published-open remote/PR
identity, current head and preserved protected paths (explicit new records for legitimately replaced canonical
reports). It retains the old plan and return/snapshot link in immutable plan history, with the new hash linked
from the old one; original attempts and uncertainty survive. Until that explicit replan, even asserted delivery
success cannot make the stale plan executable. Other published/merged/post-effect recovery contracts are unchanged.

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

Run `python3 .pi/skills/land/scripts/check-verification-return.py --evidence "$new_evidence_directory"` for
preview-return regression and adverse-state qualification. It creates one bounded local Git fixture/worktree,
uses real private files, persistent locks, plan/preview/CLI and finite supervised host children, and substitutes
Docker observations, hosting and original-worker outcomes. It performs no Docker/hosting effects and retains
its fixture source/evidence. Inspect `result.json`; simulated publication is not delivery or independent QA.

Run `python3 .pi/skills/land/scripts/check-pull.py --evidence "$new_evidence_directory"` for direct pull
capacity and SAME-operation recovery regression checks. It uses actual dispatcher/receipt/supervisor/capture
methods with substituted Docker observations and finite host children; it performs no Docker pull, registry
access or image acquisition. Inspect `result.json`; this is owning-tool evidence, not independent QA.

Run `python3 .pi/skills/land/scripts/check-creation.py --evidence "$new_evidence_directory"` only within bounded
TASK fixture authority. It records original intent, allocates two independent fixture Git worktrees, uses the
already-present PostgreSQL image without pulling, and builds one metadata-only scratch image on the already-running
local builder. It exercises wrapper creation, short-lived mounts, shared data, concurrent worktrees, nonzero workload,
interruption/cancel/resume, real evidence-write failure, same-operation recovery, replacement and interrupted cleanup.
Add `--repair-boundaries` for cold profile/dependency builds, standalone capacity rejection, original live-CLI
interruption holds and real reused-exec timeout/recovery. These use four bounded fixture worktrees; the live-CLI
case stops before Docker creation and deliberately preserves its unresolved original dispatch (no allocated
resources), while real cold/standalone/exec cases reach Docker. Controlled hosting is explicitly NOT actual
publication or independent role execution. The qualifier is bounded to 1,800 seconds total and 256 recorded
commands, in addition to per-command limits. Normal interruption waits for the original wait-process identity
to be durably captured. A failed allocation may receive one nonrecursive reconciliation observation; a failed
recovery command never triggers fallback. An unchanged hold stops with original evidence and its owner prerequisite;
missing pre-capture identity remains held, not successful cancel/resume. Inspect `result.json`, commands,
all per-resource outcomes and exact absence/protected-state observations; cache uncertainty remains incomplete cleanup.
The check is an owning-tool qualification, never a product-suite test. Actual independent role callers must separately
record their own stage journey and verdicts. Failed/partial fixtures retain original evidence and finite dispositions.
