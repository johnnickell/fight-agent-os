# Operate the Planning runtime

One application Compose project owns Nginx/FPM, PostgreSQL, private SeaweedFS and disposable Mailpit.
The existing `web` service is **tooling only**, not another HTTP server. The separately operated shared
Nginx/docker-gen supplier owns public HTTPS. Only the application TLS gateway joins `fight-local-ingress`;
no database, storage, SMTP, mailbox or tooling port is published. The direct localhost gateway remains an
explicit local compatibility entry into the **same** Nginx/FPM stack, not another application architecture.

This is local foundation operation, not production deployment, Planning database cutover, Artifact admission,
registered-project control or Agent execution. TASK-00374 and TASK-00389 have no implemented early suppliers
at this transition. The former will consume this SeaweedFS service. No foundation caller requires Redis or
Mercure, so neither is started or advertised. Real email-provider enrollment remains unfinished.

## Prepare explicitly

Use Git, Docker/Compose and the existing repository wrappers. Export the installation owner's identity:

```sh
export FIGHT_AGENT_OS_USER="$(id -u):$(id -g)"
# Main: create ignored .env and configure independent keys/passwords from .env.example first.
./bin/runtime setup
# Inside a linked checkout, setup instead creates independent disposable task settings/secrets.
./bin/up --worktree /absolute/registered/checkout --build-only
./bin/composer install --no-interaction --prefer-dist --no-progress
./bin/client setup
./bin/client build
```

`runtime setup` is the explicit secret-generation operation for a selected worktree, not an application
startup side effect. Main setup imports the operator's configured keys/passwords and generates storage
credentials into ignored `var/runtime/secrets/`. Task secrets live in the canonical checkout's
`var/worktrees/<project>/secrets/`, outside task source and application output. Existing complete files are
retained, never silently rotated. A partial set requires private inspection, not a blind overwrite. Keep
these files owner-readable and back them up separately with appropriate protection. Changing `.env` does
not rotate mounted credentials; reconcile database roles, outstanding encrypted delivery work and token
keyrings explicitly before replacing a stopped installation's files. Do not print secrets or resolved
`compose config` environments into tickets or logs.

Compose mounts a separate password file into each PostgreSQL service. FPM receives only application keys
and its database URL; tooling additionally receives the guarded test URL. SeaweedFS alone receives its S3
identity file. PHP loads the mounted JSON configuration for both CLI scripts and FPM without overriding
explicit invocation/test settings. Native CI retains its explicit environment configuration. FPM runs as
the selected owner so owner-readable bind secrets need no world-readable permissions. Its source mounts
exclude Git, task reports and installation secret/certificate directories. Tooling is trusted operator code,
not a sandbox; its output mount masks the installation's secret/certificate subdirectories.

SeaweedFS runs its native binary directly with only `DAC_OVERRIDE`, needed for its image-owned named volume
and owner-readable host secret. It has no Docker socket, public port, admin UI, WebDAV, IAM mutation endpoint,
Iceberg/Lance listener or telemetry. The private installation network remains a trusted service boundary:
SeaweedFS master/filer APIs are not independently authenticated against a compromised private peer. S3 requires
signed credentials, and anonymous object access must be refused. Storage credentials are not app capabilities
and are not passed to Agent children. A future application storage adapter needs its own scoped qualification.

## Schema before traffic

Prepare the shared supplier and exact-name leaf through [LOCAL_INGRESS.md](LOCAL_INGRESS.md). Reuse a healthy
supplier; application startup does not operate it or change trust, DNS, hosts files or privileges.
`<project>.localhost` is the unenrolled default. An explicitly enrolled project uses its existing exact task
name. Custom domains retain [their explicit enrollment](LOCAL_DOMAINS.md). Trust/standard-port setup is a
separate operator decision, not proof supplied by this runtime.

```sh
./bin/database start
./bin/database status
./bin/database migrate       # Explicit reviewed migration only; never called by up
./bin/up --worktree /absolute/registered/checkout
# Main installation: ./bin/up --shared-ingress
./bin/openapi export
./bin/validations export
./bin/browser-smoke setup --ca /absolute/existing/public/rootCA.pem
./bin/build
```

Startup builds once, waits for private dependencies, queries Doctrine `up-to-date`, then admits Nginx/FPM
traffic. A pending schema fails without applying it. During an update, stop **this gateway first** so already
running traffic cannot use a changing schema. Startup does not terminate an existing gateway on a failed
preflight. A healthy container alone is not application readiness: startup must also verify canonical HTTPS
root and CSRF through the existing front door. Key/bootstrap failures remain closed by the application.

## Status, stop and restart

Run inside the selected checkout; wrappers select its existing deterministic project. From main, prefix
Docker operations with `./bin/runtime --worktree /absolute/registered/checkout docker compose ...`.

```sh
./bin/runtime inspect
./bin/runtime docker compose --profile '*' ps
./bin/database status
./bin/https status --ingress --worktree /absolute/registered/checkout
./bin/runtime docker compose stop https
./bin/runtime docker compose stop
./bin/up --worktree /absolute/registered/checkout
```

Services use `on-failure:3`, finite health retries and resource ceilings. Exhausted failures require an
operator diagnosis and explicit restart; do not promise automatic reboot, wake, recovery or upgrade. Shared
ingress retains its independently owned policy. Mailpit and the test database use tmpfs and lose data on
recreation; they are not backups. Main PostgreSQL and SeaweedFS use named volumes retained by main `bin/down`.
Task down **destroys both disposable volumes**:

```sh
./bin/down --worktree /absolute/registered/checkout
```

Use that exact command only after subsequent roles no longer need the task evidence. Never use a global
prune, foreign project down, shared-ingress restart or main data as a substitute for an isolated clean state.
Mailpit has no browser/host path; inspect its API from trusted tooling on the private network and clear it
with `./bin/mail-catcher clear`. No invitation/reset credential-bearing mailbox is publicly routed.

## Backup and recovery

These are explicit operator operations, not application permissions or startup automation. Select the exact
project with `runtime inspect`, identify its volumes through native labels, and choose an ignored private
backup directory outside disposable task output if it must survive task down. Use restrictive umask. Do not
copy active SeaweedFS files or claim that a tar exit alone proves a restorable backup.

```sh
umask 077
./bin/runtime docker compose stop https https_php seaweedfs
./bin/runtime docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > /private/backup/postgres.dump
./bin/runtime docker compose run --rm --no-deps --entrypoint /bin/sh seaweedfs -c 'tar -C /data -cf - .' > /private/backup/seaweedfs.tar
```

Stop other explicit writers before the coordinated snapshot. Preserve the matching service secrets separately,
record source/schema/image revisions and archive digests, and verify `pg_restore --list` and tar integrity.
Recovery is into an explicitly selected, stopped, disposable/approved empty target, using the **same pinned
images and credentials**, never blindly over the live main volumes:

```sh
./bin/runtime docker compose exec -T postgres sh -c 'pg_restore --exit-on-error --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < /private/backup/postgres.dump
./bin/runtime docker compose run --rm --no-deps --entrypoint /bin/sh seaweedfs -c 'tar -C /data -xf -' < /private/backup/seaweedfs.tar
./bin/database status
./bin/up --worktree /absolute/registered/checkout
```

A target with existing schema/data is not empty: stop and resolve it before restore. Restore both metadata
and object bytes from the same quiet interval, then compare known non-sensitive object size/digest through
signed S3, database marker/schema and canonical HTTPS/security. Do not delete the source backup until those
checks pass. A bounded restart probe is not disaster recovery, cross-version restoration, power-loss testing,
replication, encryption at rest or production backup certification. The TASK records which recovery was actually
exercised; untested platform/recovery paths remain unverified.

## Deliberate updates

Record current image digests, schema and verified backup first. Review an explicit new image/tool version;
edit and commit its pin, rebuild the selected project, inspect available migrations, apply only approved
migrations explicitly and verify readiness/persistence again. Never pull `latest`, run automatic upgrades,
or assume that reverting an image reverses a schema. Preserve a verified compatible data restore path.
OS security packages in PHP/diagram image builds follow their pinned distribution's package repository;
image digests and application tools are pinned, not a claim of byte-identical future apt resolution.

Run application checks inside prepared containers with native `php scripts/build.php`, Doctrine and approved
vendor executables. No Docker CLI/socket or recursive host wrapper is needed there. See [BUILD.md](BUILD.md)
for cache evidence, browser setup and the single canonical gate.
