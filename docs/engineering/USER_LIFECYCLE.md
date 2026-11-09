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

No administrative UI, delete/restore, role removal, email mutation endpoint or supplier upgrade is delivered.
Independent acceptance remains recorded in the owning TASK and canonical Review/QA reports, not inferred here.
