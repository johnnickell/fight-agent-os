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
- `policy.runtime_authority` records already-approved temporary-runtime disposal without publication, retaining
  source/ref/private settings. `bin/down` then mechanically generates and executes the SAME runtime-only plan;
  it never calls generic Compose teardown or discards unknown/durable data. A delivery plan is never replaced.
- `policy.oneoff_authority` for standalone disposable workloads; `policy.cancel_authority` only when stopping
  the exact original one-off is authorized. Builds additionally require `policy.image_authority` and `policy.builder`,
  naming an already-running same-context local Docker builder. The helper never bootstraps a builder.
- `policy.recoverable_storage`: `{}` selects the approved bounded recoverable-image/cache policy for Land;
  optional narrower selectors/settings use the cleanup contract. It does not grant disposable database authority.
- `private_paths`: `{path, disposition: preserve, reason}` for settings, keys, reports and runtime evidence.
  Only positively reproducible caches/dependencies may use `disposition: disposable`, with nonempty original
  `authority` and `reason`. These produce mechanical preservation exclusions, never guessed exclusions at Land.
  Keep probes/reports outside removable checkouts where practical; private data is never a cache by association.
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
or captured. Unbound detached HEAD (including a CI synthetic PR merge) uses Git's quiet symbolic-ref semantics;
real Git observation errors still hold. An existing locator must validate its exact named TASK branch and runtime,
never fall back to ordinary CI. A named TASK feature branch must initialize before using the owning wrappers. Explicit Compose build
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

## Standing cleanup readiness

Existing task allocation authority includes later retirement of positively disposable local test/dev resources;
Land does not ask again for each eligible effect. Original creation/data authority, exact creator generations,
configuration/env-file digests, creating mounts and dependencies must be captured by the owning commands before
creators disappear. Unknown/shared/durable data, unique work, protected files and active consumers stay held.
This standing policy does not clear historical foreign holds or authorize global prune, protection changes,
unrelated shutdown, publication or independent self-acceptance.

Classify owned generated IPC or temporary links **while the actual creator is still observable**, before losing
its identity. Retain the link itself, not its target; never unlink an unclassified artifact merely to clear a hold:

```sh
./bin/runtime classify-temporary --path "$artifact" --creator-pid "$original_creator_pid" \
  --authority "$original_disposal_authority" --reason "$temporary_artifact_purpose"
./bin/runtime validate --stage work --attempt "$attempt" --handoff
```

`--handoff` runs the executable `readiness --stage work` operation. Review and QA use their own actual stage.
It checks original receipt/spec/creator completeness, current identities, command capabilities and the SAME
executor's descriptor/peer capability. It inventories all source/private metadata and actually copies required
source/settings/keys/reports outside the candidate through the SAME cleanup copier, then independently verifies
full records and the completeness manifest. Explicit generated IPC/link generations are no-follow exclusions;
unknown special files, changed generations, ACLs/flags, missing metadata capabilities or conflicting copies hold,
without stripping protections or pretending they were copied. Darwin IPC xattrs use bounded native no-follow
reads because the CLI can reject sockets despite available metadata. Original worker references and finite
creator lifetimes survive; current workers are not required to exit before returning their own handoff.

Inspect `lifecycle.readiness` and its immutable external proof, including every `resource_checks` hold. Exit 0
is upstream readiness for this source/input snapshot, not Review/QA, publication, final consumer eligibility or a
universal cleanup guarantee. Missing service capability is surfaced before publication, not repaired by changing
users/capabilities or stopping consumers. A failed readiness observation still permits a mechanically generated
**incomplete** plan: affected resources/source/ref hold while independent eligible effects remain executable.
After source/artifact changes, capture a fresh readiness proof; keep every earlier observation/copy unchanged.
Land refreshes it after current acceptance/QA and closeout, then binds actual publication and original Land
monitor/Pi exits through the existing plan/continuation. Technical candidate acceptance precedes independent QA;
actual Land effects occur later, never as a circular prerequisite for initial Review.

Bound TASK wrappers run `bin/runtime storage-admit` under the SAME receipt lock, using that exact effective
Compose model and local build inputs, not ambient Compose overrides or another `FIGHT_STORAGE_RECEIPT`.
Unbound non-TASK wrappers retain their ordinary path. Storage-specific measurement/builder failures refuse
allocation or hold affected effects, without skipping independently eligible recoverable artifacts.

### Historical Work receipt adoption

For an existing owning Work companion with the supported `work2.final_setup` schema, initialization may use
`--adopt-work-receipt-sha256 "$original_receipt_hash"` with the SAME receipt and exact current private spec.
It retains the original bytes externally, corroborates original intent/authority/source and current original
IDs/configuration/mounts, and records a **reuse-only** binding. No stack, key, replacement intent or new allocation
is created. Original budget/retired generations/cache/image/standalone uncertainty remain in the immutable
history; historical storage holds are not silently upgraded. Run `./bin/runtime reconcile-legacy-storage` once
after this supported adoption: it mechanically projects original image IDs, exact cache-member IDs and the old
standalone creator as holds, retaining the immutable original receipt and all retired history. It allocates
nothing, never assumes config/root equivalence and never manufactures an empty inventory. Changed/missing
evidence holds adoption/reconciliation. Other
historical formats keep their existing resource-specific evidence holds; never hand-reconstruct new authority.

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
unknown cleanup fields or unqualified command forms remain held in this unpublished mode. The narrowly supported
published setup-failure return below is explicit; neither mode supports post-removal recovery.

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
success cannot make the stale plan executable. Merged/post-effect recovery contracts are unchanged.

### Return a published candidate after hosted setup failure

When the exact current published candidate has a confirmed defect that requires preserving its runtime for
repair, use the same transition with an explicit hosted run ID:

```sh
./bin/runtime return-to-verification --plan-sha256 "$original_plan_sha256" --failed-setup-run "$run_id"
```

All original capture, subject/runtime, protected-file metadata, persistent-lock, no-pending-allocation and
original exited Land monitor/Pi guards above still apply. Every prior attempt must have complete resource
coverage, only held/retained outcomes, read-only commands and a failed final verification. A completed APPLY
attempt is supported **only for the exact current plan with those proven no-effect outcomes**. Effect intents,
removed/absent/preview/unknown results, interrupted attempts, unknown fields or commands still hold. No receipt,
plan, attempt, snapshot, transition or uncertainty is cleared or rewritten.

The owning observation must freshly establish the exact same-repository ready OPEN PR, URL/number/head/ref,
local and remote candidate OID, intended local/remote base OID, and that run's matching PR head/base. Only this
target's `.github/workflows/build.yml` pull-request run with a completed FAILURE, one completed failed owning
job, failed `Explicit locked tooling and dependency setup` and skipped `Run the sole canonical gate` qualifies.
Unavailable/malformed/changed/closed/merged/draft/pending or contradictory observations hold. The immutable
return retains those actual hosting observations; this is not a hosted corrected-source PASS.

The resulting freeze and finite SAME-runtime noncreation verification are identical to the unpublished return.
Allocation, rebind, cancellation/recovery, removal and stale cleanup stay frozen. After fresh independent
acceptance/QA, Land must publish the repaired candidate and explicitly regenerate a linked current-source plan
with fresh protected records. Replanning the original failed candidate is refused. Work cannot supply its own
independent acceptance or silently relabel the failed publication as verified delivery.

### Recover a completed legacy no-effect append during suspension

If an older cleanup helper appended one attempt to a suspended published setup-failure receipt, normal stage
entry must continue to refuse the changed history. An explicitly authorized recovery Engineer may use:

```sh
./bin/runtime recover-verification --receipt-sha256 "$incident_receipt_sha256" \
  --legacy-source "$original_legacy_commit" --protected-history "$original_report_locators"
```

This is a bounded compatibility recovery, not arbitrary receipt repair. The private JSON input maps each changed
canonical Review/QA report path to its already-preserved original file under the same TASK's canonical runs.
All original bytes and full protected-file metadata must match; unchanged protected files must still match live.
No other protected-path replacement is supported. The operation records current report metadata, not acceptance.

Under the existing persistent lock, the owner qualifies only the exact legacy cleanup source whose early-hold
path is implemented in `qualify_legacy_append`; it reads that source from the named ancestor Git commit and
**never executes it**. The original return, subject, plan chain, attempts, runtime, original capture/spec, budgets
and unknowns must be unchanged, except exactly one completed legacy APPLY without a plan-hash field. Every
result must exactly match that source's no-effect path: nonremoval early holds, observed reused images, and
failed protected-file final verification. Complete ordered resource/result coverage and exact successful
read-only image-command/output coverage are required. Effect intents, extra/unknown fields, other commands,
missing/interrupted outcomes, changed workers, unavailable visibility or changed resource identities hold.
Fresh resource presence must agree with the original immutable pre-incident noncreation census; previously
nonlisted identities remain unknown/nonlisted, never a new retirement claim.

Success adds a version-2 immutable successor to the SAME return binding, with the exact incident snapshot,
predecessor, qualified source, original report records and fresh identity/worker observations. It neither adds
fields to the legacy attempt nor replaces/truncates any attempt, plan, snapshot or transition. Normal stage
validation and finite supervised SAME-runtime verification may then resume. Allocation, rebind, allocation
recovery, removal and stale preview/apply stay frozen. A second recovery is refused. Later independently
accepted repaired publication and explicit fresh linked Land replan must retain and revalidate the entire
incident/successor history; required resource holds and all budgets survive.

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

Before generating a plan, run `./bin/runtime readiness --stage land` on the current source and inspect its
external preservation proof and affected holds. Supply only actual acceptance/publication/worker/report facts;
source/private exclusions and protected-copy mappings are mechanically derived from original classifications.
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

Run `python3 .pi/skills/land/scripts/check-readiness.py --evidence "$new_evidence_directory"` within bounded
TASK fixture authority for real oneoff creation, named/anonymous mount capture, stage handoff, unknown-artifact
refusals, live-creator IPC/link classification, actual external preservation, generated plan, preview, original
finite worker exits and SAME-receipt Build continuation/apply/absent resume. Inspect all streams/results.
Hosting and worker-role fixtures are controlled, not genuine independent roles or actual publication. Failed
fixtures retain their original receipts and precise holds; never create a replacement receipt to reset bounds.


Run `python3 .pi/skills/land/scripts/check-runtime-paths.py --evidence "$new_evidence_directory"` for actual local
Git named/detached/PR-merge path admission and malformed/unavailable observation checks. It retains one local
repository and exact owning wrapper/helper bytes, without Docker, dependency installation or hosting effects.
Inspect `result.json`; actual hosted corrected-source proof still belongs to later Land.

Run `python3 .pi/skills/land/scripts/check-verification-return.py --evidence "$new_evidence_directory"` for
preview-return regression and adverse-state qualification. Add `--published-setup-failure` for the explicit
published-open failed-setup/no-effect-APPLY mode and its live-observation adversaries. It creates one bounded local Git fixture/worktree,
uses real private files, persistent locks, plan/preview/CLI and finite supervised host children, and substitutes
Docker observations, hosting and original-worker outcomes. It performs no Docker/hosting effects and retains
its fixture source/evidence. Inspect `result.json`; simulated publication is not delivery or independent QA.

For this bounded recovery, run:

```sh
python3 .pi/skills/land/scripts/check-verification-recovery.py --evidence "$new_evidence_directory" \
  --receipt "$immutable_incident_snapshot" --legacy-source "$original_legacy_commit" \
  --protected-history "$original_report_locators"
```

It operates on isolated private receipt copies, with real original evidence, Git, locks and read-only runtime/worker observations. Adversarial
visibility/identity inputs and future repaired-publication hosting are explicitly substituted. No main receipt,
workload, original artifact or resource is mutated; inspect every result and reached hold in `result.json`.

Run `python3 .pi/skills/land/scripts/check-pull.py --evidence "$new_evidence_directory"` for direct pull
capacity and SAME-operation recovery regression checks. It uses actual dispatcher/receipt/supervisor/capture
methods with substituted Docker observations and finite host children; it performs no Docker pull, registry
access or image acquisition. Inspect `result.json`; this is owning-tool evidence, not independent QA.

Run `python3 .pi/skills/land/scripts/check-creation.py --task "$task_id" --evidence "$new_evidence_directory"` only within bounded
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
