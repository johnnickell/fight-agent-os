# Local task resources

One task uses one runtime, one small private resource record and one lock across Work, independent Review, QA,
repairs and Land. `bin/runtime` calls the neutral `scripts/resource_lifecycle/` capability. Allocation and retirement
share normal callable operations; no role owns another role's internals. There is no transition ledger, per-stage
source copy, permanent local archive, migration engine or global cleaner. These trusted local tools are not an OS
sandbox. Native CI still calls `php scripts/build.php` directly.

[Worktrunk](https://worktrunk.dev/) (`wt` on machine PATH) owns Git worktree/local-branch removal, not Docker
creation or retirement. Only that Git path requires its CLI; ordinary Pi, runtime preparation and independent
Docker retirement do not. No Worktrunk Pi plugin, Rust/Homebrew CI dependency or automatic installation is needed.
Herdr remains conditional on the [optional Pi workspace](../../harness/pi/README.md#native-herdr-workspace), not the
ordinary workflow. Shared preview ingress remains Nginx; this lifecycle adds no preview proxy or routing.

## Prepare once, before allocation

Verify target origin/branch/base and use the operator-selected checkout. Do not create another worktree or use a
shared developer database as fallback. Ordinary callers do not author specs, Compose overrides or resource JSON:

```sh
./bin/runtime prepare --task TASK-NNNNN --checkout /absolute/selected/checkout
./bin/runtime inspect --task TASK-NNNNN
# Existing bin/composer, bin/client, bin/build and focused wrappers remain.
```

Prepare binds the selected `feature/task-NNNNN-*` checkout, creates independent private local/test keys and output
paths, and combines reviewed `compose.yaml` with `scripts/resource_lifecycle/task.compose.yaml`. Task PostgreSQL
uses disposable tmpfs, including development-mode data; no existing developer database is selected or imported.
Web output/cache mounts are task-owned, not the main checkout's private `var/` state. No service starts, image
pulls/builds, dependency installation, TLS trust enrollment, application migration or export occurs implicitly.
Use only needed services, for example `./bin/runtime docker compose up --detach --wait web`. Normal non-TASK
wrappers retain ordinary developer configuration. This task-local profile does not publish HTTP/HTTPS ports;
`bin/up`'s full public/TLS readiness journey needs a deliberately reviewed serving configuration, not a silent
fallback to shared defaults. Automatic local serving preparation remains TASK-00543's boundary.

The record lives at `<canonical-safe>/.runs/tasks/TASK-NNNNN/runs/runtime/resources.json`, outside disposable
source. `var/resource-lifecycle.json` is its private locator. Repeated prepare reuses the matching binding or
returns an exact conflict. `--source-only` prepares Git-only work without Docker prerequisites or fake services;
it cannot silently turn into a runtime later. Existing current-task v2 bindings reuse their original config,
project and native identities, without a historical-schema conversion. Old legacy receipts remain read-only
original evidence; qualify bounded actual effects separately, never invent old captures or recreate fixtures.

Created locator/files/output leaves carry exact ownership and lifetime identity. Only newly created parents are
eligible for empty-directory removal; pre-existing parents/contents are never adopted. Wrapper `paths` records
new disposable output automatically, not entire existing `var/`, dependency installs or shared scratch trees.
An interrupted preparation retains its original binding with a specific hold, never a second allocation.

## Inspect and register consumers

`inspect` observes current binding, resources, consumers, pending work and precise holds. It does not create a
lock, save the record, register a worker, reconcile an interruption or allocate anything. Its exit indicates
binding/pending readiness, **not** that all previewed cleanup effects are complete. Eligible/held outcomes remain
visible. Docker inventory errors never mean absence and do not globally prevent Git-only observation.

A monitored child registers its actual live monitor/Pi identities once, after its original `process.json` exists:

```sh
./bin/runtime register-worker --task TASK-NNNNN --stage work --attempt /absolute/original/attempt
```

Use the actual `work`, `review`, `qa` or `land` stage. Build passes the original attempt; each child registers
before using the runtime. Land's attempt is retained for the post-exit continuation. Registration is explicit
mutation, not an option on inspection. Preserve native session-file device/inode/birthtime, ancestry/custody and
independent-role admission; these resource records do not replace or weaken the native Harness contracts.

Wrappers capture native generations/configuration and original mounts before removing creators. Completed one-offs
and proven-disposable unused volumes retire immediately through the shared retirement capability. A nonzero
workload keeps its exit even if cleanup is held, with that separate hold reported. Builds use one task-owned
native builder/cache. Explicitly install `moby/buildkit:buildx-stable-1` if missing; builders reuse its already-local
immutable ID, not a fresh shared mutable-tag pull. Host admission checks reserve/allowance and daemon availability,
never global-prunes. Commands have a finite 1,800-second wait and 16-container admission bound, not a residue quota.

## Failure, cancellation and resume

Inspect the single pending operation and original CLI identity. Do not infer remote execution ended with its CLI,
signal reused/unknown PIDs, repeat setup or erase an uncertain outcome. After the original CLI ends:

```sh
./bin/runtime reconcile --task TASK-NNNNN
```

Reconciliation captures surviving original creators, without allocation or an invented success result. An uncertain
remote exec remains held until its original affected target ends. Authorized native retirement can stop that exact
disposable target; then reconcile and reuse the same binding. If CLI identity was never captured, retain that
specific uncertainty rather than guessing. Keep diagnostics only while an active issue or independent role needs
them. Never retry an unchanged hold indefinitely or reset consumed repairs.

## Retire runtime, then finish the task

Routine owned disposal is standing authority on success, failure and cancellation. From the canonical safe checkout:

```sh
./bin/runtime retire --task TASK-NNNNN
# bin/down uses exactly this operation for its bound task (or explicit --receipt).
```

Retire removes independently eligible owned runtime resources, retaining source and evidence needed by later
roles. It is not final completion/publication. Containers retire without `-v`, independently of source-copy,
ignored-file, LINK or ACL eligibility. Unknown/durable/shared volumes and images remain specifically held. Volumes
need original disposable creation and zero current/stopped mounts; networks need original ownership and no
endpoints/configured consumers. Images need no foreign references or consumers; dedicated builders/cache use
native `docker buildx rm`, never prune. Active workers hold affected resources. Per-effect Docker identity checks
apply only where needed; unavailable original bind inventory specifically holds affected filesystem/source
removal, not unrelated effects. Main/current checkouts, unique work and real user protections remain protected.

After required acceptance/delivery or authorized cancellation outcomes reach the existing TASK/PR, record the
validated disposition through a typed command, not JSON editing:

```sh
./bin/runtime complete --task TASK-NNNNN --outcome /absolute/existing/TASK-or-durable-outcome \
  --published-oid FULL_OID --remote-ref refs/heads/feature/task-NNNNN-slug --pr EXACT_PR_URL
# For an actually authorized cancellation instead, replace publication options with:
# --cancel-oid FULL_OID --authority 'Original explicit source/ref cancellation authority'
./bin/runtime finish --task TASK-NNNNN --outcome /absolute/existing/TASK-or-durable-outcome
```

An outcome path alone grants no source-disposal authority. Completion checks the exact local tip and live origin-owned
GitHub PR/base/head. Open non-draft publication additionally requires the exact remote ref; merged publication
requires positive merge evidence and local intended-base inclusion, never just a missing remote branch. Git disposal
refreshes that proof. Acceptance, required delivery checks and cancellation authority remain Land/operator-owned
under [landing standards](LANDING.md#qualify-local-git-cleanup); this command cannot supply independent acceptance.
The outcome must survive disposal. Unknown/dirty/private data, changed branch/tip, current workers and unavailable
consumer visibility hold only their affected effects. Never force-remove a worktree/ref or delete a remote ref.

After these project-specific checks, finish invokes `wt remove --foreground --format=json` with the exact target
from the safe checkout, closed stdin and no force, reap, approval or hook-skipping options. It verifies JSON **and**
actual path/ref/registration absence. Worktrunk can remove a clean worktree but retain its unmerged branch with exit
0; that is a partial Git hold, not success. Retry observes the surviving exact ref and delegates branch-only removal
to Worktrunk. Cancellation/publication authority does not override Worktrunk integration checks; the operator owns
any genuine integration decision. There is no custom Git deletion fallback or implicit checkout/branch switch.
The selected primary checkout/current branch remains a reported hold, including its resource record, not a claim
of complete Git retirement. Missing/unsupported `wt` holds Git only; eligible Docker effects still run first.
Worktrunk may also leave the deleted branch's local Git configuration. That is a specific metadata hold retaining
the record, including on retries after ref absence; the operator owns its exact disposition. This command neither
edits Git configuration nor treats ref deletion as proof that all branch metadata is retired.

Automatic finish holds when pre-remove or post-remove hooks are configured in either relevant checkout, including
already approved/user hooks: arbitrary shell and detached post-hooks cannot be qualified by this command. The
operator owns attended hook execution/approval and its outcomes; the tool never displays personal hook templates,
approves commands or silently skips hooks. Resolve that precise hold before retrying the remaining Git effect.
Worktrunk itself deletes ignored files by default, so the project's ignored/private-content guard is mandatory.
Its repository-shared `<git-common-dir>/wt/` caches/logs and Worktrunk history/default-branch hints in local Git
config are not task-owned output. These differ from the exact task branch configuration hold above. Foreground hook-free
qualification left integration caches, not removal logs; do not clear shared state or claim universal zero residue.

Finish retires owned locator/output **before** ignored-file/worktree eligibility, then eligible isolated checkout/ref,
and finally the record/lock and empty newly created parents. Pre-existing parent contents survive. Filesystem,
Git and Docker are separate effects: partial failure is recorded, not transactional rollback or exactly-once
cleanup. Actual postconditions, not an exit alone, determine removed/absent/held/failed counts. No routine local
archive or cleanup-certification commit loop. Temporary role evidence created outside wrapper-owned paths stays
its original creator's teardown obligation; keep it while consumed, then retire it under the same ownership rules.
Do not bulk-classify pre-existing evidence, private trees or another task's history to make counts pass.

If original Land is itself a consumer, Build observes its original monitor/Pi exits and PID absence, then calls
`scripts/cleanup-continuation.py --attempt ORIGINAL --receipt SAME --apply --finish` beside the Build skill from
the safe checkout. It reuses the public commands, never republishes or changes source. Keep active coordinator
and independent consumers protected. No shared-service/VM shutdown or ACL/flag stripping; only genuine original
bounded restore proof permits restoring a probe's protection.

## Direct qualification

Run `python3 scripts/check_resource_lifecycle.py --evidence /absolute/new/task-run`. It exercises public owning
commands against finite real Git/Docker fixtures and inspects actual absence/protected state. Fixtures have bounded
teardown even on failure; an unresolved original fixture is retained only for its active issue, never replaced
blindly. Worktrunk receives a fixture-only HOME/config/approvals and repository; Docker retains its original local
context. Qualify ignored/private protection, missing CLI, hook holds, retained unmerged branches, branch-only resume,
retained task-branch configuration and absent-ref retries,
main/current holds and fixture-local cache/log outcomes. Retire only fixture-local state; never clear shared caches.
Removed giant qualification matrices have no current callers; behavior-focused command qualification replaces
their obsolete proof/JSON mechanisms. Native session-lifetime/role tests remain required. These checks
are infrastructure evidence, not product-suite tests, independent Review/QA or live publication acceptance.
