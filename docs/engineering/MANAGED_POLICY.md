# Managed installation authority

Use this operation when preparing an installation or reconciling managed authority after an approved policy
change. It does not create users, invite anyone, bootstrap a Super Admin, expose Swagger, or enable live Agents.
The required protected-tier and PostgreSQL contracts were qualified in
[TASK-00126](../../planning/tasks/00126-TASK.md); the current lock selects Fight Access Control **v0.5.0** at
`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`. Agent storage/reference observations do not qualify credential delivery.

## Policy ownership

[`InstallationManagedPolicy`](../../src/Domain/Authorization/InstallationManagedPolicy.php) is the typed,
version-controlled source of all stable identities, definitions, memberships and application references.
The initial catalog is exactly [TICKET-00015](../../planning/tickets/00015-TICKET.md)'s 22 permissions:

- Managed `ROLE_USER` (`01a132ed-a1d6-4d5a-ba84-55c8cafc8f43`) receives only `VIEW_DASHBOARD`,
  `READ_OWN_SESSIONS` and `DELETE_OWN_SESSIONS`; these definitions are `ADMIN_SAFE`.
- Managed `ROLE_SUPER_ADMIN` (`01a132ed-a1d6-48d4-8ab3-ee366b77ee3c`) receives all 22 permissions.
  The other 19 definitions are `SUPER_ADMIN_ONLY`, including `READ_SWAGGER`. Package/repository invariants
  reject custom Role membership and direct Agent grants of protected definitions.
- Every catalog permission is included in `referenced_permission_ids`, including the current invitation and
  session administration callback checks. No role-name authorization or new entry path is implied by membership.

The IDs were generated once with the owning package's UUID factory, then committed; they are not regenerated
on startup or reconciliation. The initial canonical representation is `ManagedPolicy::toArray()` encoded with
PHP `json_encode(..., JSON_THROW_ON_ERROR)`; its SHA-256 is
`bcf091f15a00e0611174a14344242bff0e448a8b2e3dde2300cc3c64a0129e8b`.
This hash identifies desired policy, **not** a database snapshot, signed approval or concurrency token.
Preserve existing identities when extending the complete catalog. Before removing a definition or reference,
inspect every consumer check and persisted assignment; do not submit a one-permission replacement policy.

## Trusted local operation

Only an operator already authorized to access this installation's source, Docker runtime and database should
run these commands. `--internal` is an explicit acknowledgement, not an authentication mechanism. Do not expose
these scripts through HTTP, a public job runner, or automatic startup. There is no end-user permission check or
interactive prompt. Application authorization for later user-facing operations remains separate.

Confirm the intended Compose project and database configuration through trusted local configuration management.
Do not print connection strings or credentials. Install locked dependencies and apply the installation's approved
migrations explicitly before running policy operations. The command itself never migrates or resets a database.

```sh
./bin/managed-policy preview --internal
./bin/managed-policy apply --internal
./bin/managed-policy preview --internal
```

Preview dispatches package `PreviewManagedPolicy`; it performs reads only and emits one JSON line with
`operation`, `policy_sha256`, and `plan`. The plan has `permissions` and `roles`, sorted by canonical name, with
stable IDs, desired names/tiers/memberships and `CREATE`, `RECONCILE`, `REMOVE` or `UNCHANGED` actions.
A fresh installation shows 22 Permission creates and two Role creates. After apply, preview should show all
24 definitions unchanged. Inspect this plan before authorizing apply; the preview is advisory and is **not** saved
as an executable plan.

Apply synchronously dispatches package `ReconcileManagedPolicy`. Its planner re-reads state inside the canonical
Doctrine transaction and owned PostgreSQL reference/optimistic fences protect writes. Successful output is:

```json
{"operation":"apply","policy_sha256":"bcf091f15a00e0611174a14344242bff0e448a8b2e3dde2300cc3c64a0129e8b","status":"applied"}
```

Repeat apply leaves unchanged definitions, memberships and timestamps unchanged. The tagged package emits
`ManagedPolicyReconciled` **after commit even for a no-op**; this is an in-process event, not a durable audit/outbox
receipt. There is no managed-policy subscriber installed. The console does not print raw events or serialize
`CommandFailedEvent`, whose package payload can contain private failure details. Future subscribers must not
log that payload unredacted.

| Exit | Meaning |
|---|---|
| `0` | Preview succeeded (whether drift exists or not), or apply completed |
| `1` | Operation/setup failed; only a fixed diagnostic is emitted |
| `2` | Invalid arguments; safe usage text is emitted, without dispatch |

Arguments must be exactly `preview --internal` or `apply --internal`; extra, repeated or unknown options are
rejected. stdout contains only successful JSON; stderr contains fixed application errors or usage. PHP runtime
warnings and exception details are not printed or logged by this entry point. Docker itself can emit operational
status/errors outside the PHP boundary; do not enable verbose secret-bearing Compose diagnostics for receipts.

## Conflict and recovery

Custom/unmanaged definitions are never silently claimed. Name/ID conflicts, malformed stored definitions,
protected promotions with incompatible membership, obsolete assigned Roles, and obsolete Permissions still
referenced by code, Roles, Features or Agents fail safely. The planner may preview an Agent-referenced removal,
but the repository rejects its application atomically. Identity collisions require an explicit authority/data
reconciliation decision; changing stable IDs or deleting assignments merely to make apply pass is not recovery.

On failure, preserve the intended policy and investigate through trusted, redacted operational access. Do not
retry in a loop or bypass package/repository fences. A competing write may require a fresh preview and an
explicit retry. Write failures roll back partial authority. A failure **after commit** (event delivery, output,
process or transport loss) cannot undo the committed policy; treat an uncertain result as unknown until a new
preview establishes current state. Apply is idempotent, but a zero exit is not deployment, bootstrap, or independent
security acceptance.

## Verification boundary

`tests/Unit/Authorization/InstallationManagedPolicyTest.php` protects the exact catalog and least-privilege
memberships. `ManagedPolicyConsoleTest.php` covers dispatch selection, argument rejection, deterministic output
and redaction. `tests/Integration/Postgres/ManagedPolicyTest.php` crosses real package buses/handlers and owned
repositories: database-enforced read-only preview, exact creation, repeat stability, name/tier/membership drift,
removal/reference fences, unmanaged collisions, malformed data, Swagger delegation rejection, injected rollback,
stale preview and three controlled two-connection interleavings. The Agent fixtures are storage-only test data,
not enrolled live credentials. No product tests of container wiring, scripts, migrations or tools are added.

Run `./bin/database test-reset` before focused PostgreSQL tests after a gate cleans the test schema. Use
`./bin/phpunit --filter ManagedPolicy` for this boundary and `./bin/build` for the complete gate. Actual counts,
warnings and local operational receipts belong in [TASK-00036](../../planning/tasks/00036-TASK.md).
