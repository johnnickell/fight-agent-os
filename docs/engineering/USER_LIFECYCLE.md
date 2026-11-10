# Confirmed user disable and enable

TASK-00119 adds `POST /api/v1/users/{userId}/disable` and `/enable`. Each requires Bearer authentication and
only its own exact current `DISABLE_USERS` or `ENABLE_USERS`, declared once on the Action and enforced by
`ApiActionSecurity`. Neither READ_USERS nor general UPDATE_USERS is required. The actor comes exclusively from
the authenticated principal; the bounded UUID comes from the route. The only JSON input is `{"confirm":true}`.
No query selectors, actor/target overrides or displayed whole-account revision are accepted.

Self-target transitions reject. Owned `PreventAdministratorLockout` checks current managed administrator
membership through `EffectiveAdministrators` before disabling. This is deliberately a **non-atomic precheck**:
concurrent requests can both pass and disable different administrators. The operator explicitly accepted that
race for this TASK; there is no count/check/mutation lock or global last-admin reservation. The persistence
query binds the managed authority's stable ID, managed status and canonical name, not a caller-supplied label.
It counts active memberships only and fails closed if that managed definition is unavailable. It does not
recheck the caller's operation permission or require unrelated catalog grants.

Actions submit public package `DisableUser`/`EnableUser` directly through the configured CommandBus. The
package owns eligibility and one shared transaction for lifecycle, required safe audit and, on disable,
reset-grant/session revocation. Disable increments authentication version; enable does not restore old sessions
or issue a new one. Existing request authentication resolves fresh authority on the next request. An admitted
request can finish after concurrent permission revocation.

## Email concurrency and persistence

The locked Access Control v0.8.0 repository contract requires complete expected-state comparison, including
email fields. Narrowing that CAS would violate the supported contract and could lose concurrent authentication
version changes. Instead, `PostgresUserRepository::getById()` takes the existing per-user transaction-duration
writer fence **before loading the authoritative snapshot**, using the `lockLifecycleSnapshot` binding injected
only into the registered DisableUser/EnableUser handlers. Both registered repository bindings use the same
adapter, connection and shared fence; neither adds policy interception or transaction completion. An in-flight
email writer completes first; the lifecycle snapshot then includes its committed email, revisions and
authentication version. Later writers wait for lifecycle completion. Other package handlers and ordinary
queries keep their existing snapshot/grant coordination; broadening their read locks would change their
independent concurrency contracts and is deliberately avoided. This per-target consistency does not serialize administrator counts across different users.

## Responses and recovery

Success is HTTP 200, no-store, `{"status":"success","data":null}`: completion of void dispatch, not a
post-command read or a user-detail payload. Invalid input is 400; unauthenticated/stale authority is 401 and
missing operation permission is 403 before target execution. Self, observed last-admin, unknown target and
invalid/concurrent lifecycle transitions use generic 409 without exposing target state. Internal faults remain
redacted 500, not invented conflicts. Only the locked supplier's exact lifecycle CAS rejection is mapped from
LogicException; unrelated faults are not hidden.

An exception or lost response can follow commit, especially during post-commit event dispatch. Do not auto-retry;
use a fresh separately authorized READ_USERS detail request to resolve uncertainty. Required package audit
contains actor, target, action and time, never passwords, tokens, hashes or private delivery material.

No administrative UI, role removal, email mutation endpoint or supplier upgrade is delivered.
Independent acceptance remains recorded in the owning TASK and canonical Review/QA reports, not inferred here.

## Confirmed soft deletion and restoration — TASK-00120

`DELETE /api/v1/users/{userId}` requires exact current `DELETE_USERS` and `{"confirm":true}`.
`POST /api/v1/users/{userId}/restore` requires exact current `RESTORE_USERS` and
`{"confirm":true,"restoration_state":"active"}` or `pending_activation`. Both permissions remain
SUPER_ADMIN_ONLY in the existing managed policy; admission uses current permission, not a role/JWT shortcut.
Each Action declares its permission once for central enforcement, maps the principal actor and route target,
and submits public package DeleteUser/RestoreUser through the configured CommandBus. No additional
Application admission service, READ_USERS prerequisite or user-data response is introduced.

The explicit 2026-11-10 operator amendment makes confirmation current-state intent, not confirmation of a
previously displayed revision. There is **no administrator-count precheck or atomic last-admin decision** for
these operations; two admitted administrators may delete each other. Self-deletion rejects. The expectation
of a single administrator is deployment context, not a product constraint. Disable/enable's separately owned
precheck remains unchanged. No new revision protocol is introduced.

Access Control v0.8.0 (`319738255e682d587642b67856039f882b9588d6`, unchanged Composer lock) permits deletion
of active or disabled users only; pending and already-deleted targets conflict. Restore accepts only deleted
targets and explicit active/pending_activation destinations, not disabled. Active restoration retains the
password. Pending restoration clears it and issues fresh internal activation authority against the terminal
predecessor invitation; this is not login, automatic activation or session issuance. An absent/nonterminal
predecessor prevents issuance and rolls back restoration. No credential or delivery material is returned.
Real activated invitation identities retain their terminal predecessor. Restore never revives old sessions/JWTs.

Deletion retains the stable identity, canonical email and pending email claims; invitation is not replacement
or implicit restoration, and a claimed email remains unavailable to another identity. The existing normal
repository binding enforces the supplier's complete expected-state CAS. A writer that changes the snapshot
after the handler loads it causes conflict rather than lost data. Unlike disable/enable, delete/restore do not
promise waiting for unrelated email changes before their snapshot. Package lifecycle, reset/session retirement
on delete, required audit and pending activation issuance on restore use the same transaction/connection;
events follow commit. No nested consumer transaction or repository/audit interception is added.

Success and failure envelopes are the no-store contracts above. Missing/ineligible targets and lifecycle CAS
conflicts are generic 409; malformed confirmation/state/UUID and extra body/query selectors are 400. Internal
faults, including required audit or activation issuance failure, remain redacted 500. A post-commit event
failure can also produce 500 after durable state/audit; do not blindly retry. Resolve uncertainty through a
fresh separately authorized detail query. This API-only slice adds no browser administration UI; TASK-00123
owns that journey. Independent Review/QA remain separate from implementation checks.
