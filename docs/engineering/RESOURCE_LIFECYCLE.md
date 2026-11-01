# Local task resources

Use native Compose projects, not a second ownership ledger. Main and each registered worktree have explicit,
independent lifecycles. These trusted operator tools are not an OS sandbox and do not confer application or
managed Runner authority. Native session custody and independent-role Harness admission remain separate.

## Explicit commands

```sh
./bin/up                                 # Main checkout's full declared application stack
./bin/down                               # Main down; retain normal persistent data
./bin/up --worktree task-00543            # One registered worktree, disposable isolated stack
./bin/down --worktree task-00543          # Dispose that stack, volumes and private networks
# If several checkouts match, use the exact registered path or full branch:
./bin/down --worktree /absolute/registered/checkout
```

Git's registered-worktree list resolves selectors; never guess a directory from a TASK ID. Worktrunk operates
on that same registration. An ambiguous selector fails before effects. Every isolated project always includes
a deterministic hash of repository/main identity and canonical checkout path, so adding another same-task
checkout cannot rename an existing project. Branch changes do not change a checkout's project.

`bin/up` without a selector always means main, even on a feature branch or when legacy `var/resource-lifecycle.json`
exists. `bin/down` without a selector never disposes task storage. Native tooling wrappers invoked inside a linked
checkout select that checkout's isolated project; in main they use main. From main, explicit focused operations
can use `bin/runtime --worktree /absolute/path docker compose ...`. Library projects use their own actual test
dependency commands rather than inventing this application's stack.

`--build-only` explicitly builds application images without starting services or enrolling TLS. Dependency
installation, schema migrations, exports and certificate setup remain explicit owning commands. Main retains
its `.env` and persistent PostgreSQL volume. Worktree settings have independent keys, credentials, exact origin,
project-local PostgreSQL volume and ignored output, never imported main data. Task storage is disposable by
definition; shared infrastructure belongs in separate projects.

## Configuration and readiness

Compose files, project and env files are selected explicitly, independent of ambient `COMPOSE_FILE`, project
names or auto-loaded overrides. Ordinary main uses only `compose.yaml` and its explicit `.env`; it never loads
`var/main-ingress.yaml` or generated task overrides. Worktree overrides/settings live in canonical ignored
`var/worktrees/<project>/`. Source mounts/build contexts point to the registered checkout; output is separate
per project and execution evidence stays under
`.runs/compose/<project>/`. Application boot never depends on execution evidence under `.runs/`.

Full startup enables declared application TLS and mail services, not the Composer maintenance job. Current
application services are web tooling/HTTP, TLS gateway, FPM, development/test PostgreSQL and private Mailpit;
future installation services are not fabricated. Expensive wrappers check host space (20 GiB reserve plus
4 GiB allowance by default) and daemon availability; configure `FIGHT_STORAGE_RESERVE_BYTES` and
`FIGHT_STORAGE_ALLOCATION_BYTES` explicitly. Existing service CPU/memory/PID limits remain in Compose. No new
aggregate quota/ownership system or global prune is introduced.

Ordinary main startup reuses [local HTTPS](LOCAL_HTTPS.md)'s explicit `.env`, independent keys and existing
localhost leaf/key preflight. It starts the full declared stack, validates/reloads Nginx and verifies root/CSRF
readiness at **https://localhost:18443** before printing that URL. It does not call shared ingress, change trust,
generate certificates, migrate data or select a proxy based on stale configuration. Main down retains persistent
data. The earlier TASK-00543 main-up failure was a regression, not an acceptable supplier prerequisite.

[TASK-00553](../../planning/tasks/00553-TASK.md) is the currently undelivered shared HTTPS supplier for
`bin/up --worktree SELECTOR` and future shared-topology integration, NOT ordinary main operation. The task
consumer expects separately operated `fight-local-ingress`, one public Nginx plus nonpublic docker-gen, and
actual-name certificates through TASK-00145's explicit setup flow. Only each application's TLS gateway joins
ingress, using HTTPS upstream on 8443; inherited task publishing is stripped and private services remain private.
Intended shared origins are `https://fight-agent-os.localhost` and `https://<project>.localhost`. No custom DNS,
trust enrollment or shared infrastructure startup is implicit; future main integration must be explicit and
preserve ordinary direct startup.

The application gateway owns direct TLS authority and canonical FastCGI Host/scheme/port; arbitrary forwarded
headers stay stripped. The supplier must qualify end-to-end TLS, exact origin, secure cookies, available auth
flows and route removal. Config scaffolding is NOT delivery. Task up fails clearly without the supplier, never
falls back to main, and prints a usable URL only after verified HTTPS root/CSRF readiness. Task down and repeated
teardown work without ingress. Do not claim browser-ready from Compose health alone.

## Retire runtime, then finish the task

All roles and Worktrunk use the SAME `bin/down --worktree SELECTOR`. It calls Compose
`down --volumes --remove-orphans`, then removes remaining native project-labeled resources and task-specific
image tags, plus a dedicated reserved project builder/backing volume if created. Native removal refuses foreign
volume/network consumers. It never removes a shared immutable image ID to force task-tag cleanup. Real command
failures and remaining resources produce nonzero/incomplete results; inventory failure is not absence.

Ordinary builds use Docker's shared builder/cache; downloaded base images and shared build caches are NOT
task-exclusive. Separate operator maintenance can inspect `docker system df -v` and `docker buildx du`, then use
an explicitly selected builder's `docker buildx prune --builder NAME` after reviewing shared consumers and cache
policy. This is never invoked by task down/Land. Do not run machine-wide prune/reset or prune foreign projects.

Task down removes reserved disposable runtime output. Keep active role evidence only while consumed, then retire
its exact `.runs/compose/<project>/` evidence/cache after outcomes reach the existing TASK/PR. The small
`locator.json` and secret-free `down.json` survive outside removable source to support interrupted removal and
harmless repeated teardown. They locate Compose configuration; they are not resource inventories or admission
receipts. After successful source retirement, delete the exact generated `var/worktrees/<project>/` directory.
No PID/worker/branch/receipt prerequisite governs explicit disposable Docker teardown. Never signal sessions to
make down succeed. Old `.runs/.../resources.json` remains historical evidence until its consumers finish, not
an active lifecycle or compatibility system.

`.config/wt.toml` supplies a pre-remove hook that invokes this same command from the canonical main checkout,
before source/config disappears. Routine owned cleanup includes unattended execution of this exact reviewed
project hook; do not manufacture a human-approval hold. Land and qualification fixtures share
`scripts/resource_lifecycle/worktrunk.py`'s guarded `remove` callable. It invokes public `bin/down` FIRST, then
qualifies source/ref removal. Immediately before native removal it inventories complete effective pre/post-remove
hooks in canonical AND target contexts, including accumulated system/user/project sources. Only absent hooks or
project `pre-remove.runtime` with the exact template
`{{ primary_worktree_path }}/bin/down --worktree {{ worktree_path }}` are admitted. User hooks (even identical),
unknown names/phases or changed templates refuse source removal without exposing personal command text.
The installed Worktrunk must support `--foreground --format=json --yes`; `--yes` supplies consent for that
invocation only. It never adds/clears saved approvals, changes global/user config, hides hooks or skips them.
Main/current source, unique/dirty work, needed private ignored data, actual source consumers and filesystem
protections remain independent holds. A source hold cannot block Docker teardown.

After independent acceptance, final delivery verification and durable outcomes, Land may call:

```sh
./bin/runtime finish --worktree /absolute/checkout --outcome /absolute/surviving/TASK.md \
  --published-oid FULL_OID --pr https://github.com/OWNER/REPO/pull/NUMBER
```

Finish repeats task down, checks the exact live publication/ref and clean source/private data, then delegates
non-force Git removal to the shared guarded callable's `wt remove --foreground --format=json --yes` with closed
stdin, after the effective-hook checks above. Known-hook cleanup does not prompt or persist trust; unqualified
hooks need their owner's separate decision, never blanket consent. Clean owned qualification fixtures use the
same callable with exact OID/ancestor proof instead of fabricated PR proof. Genuine parent mount use and
host readers/writers hold source; parent-only exposure and cached read-only Docker file-sharing handles require
positive native consumer qualification, not shared-service/VM shutdown. Never force/reap, bypass hooks, delete
refs/config by custom fallback, remove the main checkout or stop shared VMs. Worktrunk may retain an unmerged
branch or metadata: inspect actual path/registration/ref/config and report partial Git outcomes separately.
If source is already gone, repeat down through its locator, then let Worktrunk handle the exact surviving branch
under Land's source/delivery qualification. Do not recreate source or publish again for cleanup.

If Land's own session consumes source, Build observes its real exit before source retirement from the safe
checkout. Docker down itself does not need that exit. There is no post-worker receipt/PID cleanup helper. The
existing Build ledger/handoff carries remaining effects and native locators, not another resource record.

## Direct qualification

`python3 -B scripts/check_resource_lifecycle.py --evidence .runs/tasks/TASK-00543/runs/NEW_RUN` exercises real
registered same-task fixture worktrees/branches, Compose, PostgreSQL, isolated output, repeated teardown and the
SAME guarded native Worktrunk removal used by Land. Fixture tips start at integrated `develop` so native branch
removal needs no force. It requires the existing declared main web image tag and preserves main/foreign services. It does NOT
supply full-stack HTTPS/auth/route acceptance before TASK-00553. Record all limits, actual postconditions and
cleanup failures. Validate infrastructure directly, not with product tests of wrappers, Docker or quality tools.
