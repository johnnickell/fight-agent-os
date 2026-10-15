# Trusted console invitations and role correction

Ordinary invitation uses two independent package business steps and a narrowly trusted corrective command.
TASK-00446 simplifies their composition and Application sequencing; its fresh independent Review/QA are separate
from historical TASK-00037/00038 acceptance. No public HTTP or browser role-administration surface is introduced.

## Prepare the intended installation

Restrict shell, checkout, Docker and database access to trusted operators. `--internal` acknowledges that trust
boundary; it is not authentication or an end-user permission check. Verify the intended Compose project/database
through private configuration without printing connection strings. Apply approved migrations through
`./bin/database migrate`. Preview and explicitly reconcile the exact version-controlled catalog using the
[managed policy runbook](MANAGED_POLICY.md). Both operations reject missing/drifted installation authority; neither
repairs it automatically. Keep the independent external `APP_CREDENTIAL_DELIVERY_KEY` while work is outstanding.
The [credential recovery runbook](CREDENTIAL_RECOVERY.md) owns provider configuration and safe delivery recovery.
Local null/memory providers send no email; production provider/scheduler enrollment remains separate.

Enroll a stable lowercase operator UUID once through the owning ID factory or a UUID library, and keep its trusted
identity mapping outside the command. Do not substitute the target ID or infer an operator from environment input.
For the separate manually operated first Super Admin operation, read the
[bootstrap runbook](SUPER_ADMIN_BOOTSTRAP.md); neither ordinary command can elevate or resume bootstrap.
This bounded attribution does not require a database User or claim a logged-in principal. Package assignment uses
the typed operator UUID as actor; durable console audit uses the distinct `console:UUID` namespace.

## Invite an ordinary pending user

```sh
./bin/console user:invite --internal --operator=OPERATOR_UUID --email=person@example.test
```

Email is at most 254 ASCII bytes, contains no spaces/control characters and passes Fight Common parsing. It is
lowercased before dispatch. Case-equivalent identities conflict without replacing grants or resending. There is no
role, permission, user-ID, password, provider or credential selector on invitation.

Application dispatches public `InvitePendingUser`, then public `AssignRoleToUser` for the stable installed
`ROLE_USER`. Each normal package handler owns its independent canonical Doctrine transaction and normal postcommit
events. The first commit atomically records the pending/passwordless identity, email claim, seven-day activation
grant, encrypted recoverable delivery state and `user.invited` audit. Its delivery subscriber may attempt email
**before assignment**. The second business commit adds `ROLE_USER`; it cannot roll back the first.

Success stdout is one JSON line: canonical `email`, safe `user_id`, and `status: "invited"`. It means both business
steps, ordinary pending-state verification and separate role evidence completed, not email arrival or activation.
Provider retryable/permanent outcomes are safe persisted state; provider exceptions are classified by the package.

## Correct existing membership without reinvitation

```sh
./bin/console user:role:add --internal --operator=OPERATOR_UUID --user=EXISTING_USER_UUID --role=EXISTING_ROLE_UUID
```

All selectors are lowercase UUIDs, not names. Both user and role must already exist. The operation is additive and
membership-idempotent: existing roles remain, and repeated addition creates no extra membership. An existing
eligible custom role, including its ADMIN_SAFE permissions, is permitted. This command creates no identity, grant,
credential delivery, password, role definition or permission, and removes nothing. It is not bootstrap, resend,
registration, elevation or general web/custom-role administration.

The stable installed Super Admin ID is always refused, including if renamed. Package-semantic `ROLE_SUPER_ADMIN`
and unknown managed-catalog roles are also refused. Exact installation reconciliation rejects aliased/drifted
managed definitions. Actual user/role/permission query identities are checked; unavailable or protected permission
memberships are refused. Package role consistency, protected-tier and current-reference checks remain in force.

Success stdout contains `user_id`, `role_id`, `status: "role_present"` and `audit: "confirmed"`. This includes a
safe repeat: it confirms observed membership, not that this invocation necessarily created it.

## Failure, uncertainty and recovery

| Exit | Meaning |
|---|---|
| `0` | Requested state verified and this invocation's role confirmation audit committed |
| `1` | Rejection, execution failure or uncertain completion; never proof of rollback |
| `2` | Invalid input, with fixed usage/parser diagnostic and no operation dispatch |

Failure stdout is empty. If Application can inspect the intended target safely after failure, stderr is a JSON
line with `status: "partial_or_uncertain"`, `user_id`, `role_id`, `assignment: "confirmed"` or `"missing"`, and
`audit: "confirmed"` or `"unconfirmed"`. A failed invitation also includes an exact `correction` command for that
known target and managed ROLE_USER. A failed correction reports state but does not recommend a rejected role.
No target is asserted when the authoritative read fails, returns absent/wrong identity, or does not match the
invited pending email/state: stderr instead uses a fixed failure/uncertainty diagnostic. Inspect trusted state in
that case; absence of a target in output does **not** prove the invitation failed to commit.

A postcommit event exception can leave invitation or membership committed. Application reads public safe state
before reporting it; it does not infer rollback from an exception. Assignment failure retains first-step audit,
grant and delivery, even if mail was already offered. Audit failure retains membership. Preserve the safe output,
inspect intended persisted state, correct reconciled authority if necessary through its owning operation, then use
the existing target's corrective command in a **fresh process**. Never blindly reinvite, regenerate credentials or
create a replacement identity. A Doctrine rollback closes that container's manager; successful invocations may
reuse shared instances, but a failed manager must not be resurrected.

Delivery recovery is independent of role correction. The package's bounded due-work runner finds the same durable
generation after lost events, failed claims/outcomes or restart. Provider invocation occurs outside transactions.
A provider acceptance followed by lost outcome commit can be retried with the same delivery identity: the guarantee
is durable at-least-once attempts, not exactly once.

## Audit boundary and repeat semantics

`user.invited` remains atomic with invitation/grant/delivery material in the first package transaction.
The installed package assignment handler has no AuditEvidenceRepository. Application therefore verifies additive
membership after the independent assignment returns, then uses a **third, separate audit transaction** to append
`user.console_role_assigned`, with actor `console:OPERATOR_UUID`, subject target UUID and bounded context
`{role_id: ROLE_UUID, result: "membership_confirmed"}`. This records an observed confirmation, not an atomic
assignment/audit fact. Extra delivery claim/outcome bookkeeping transactions are separate too: “two steps” means
two business transactions, not exactly two physical commits overall.

A crash or postcommit exception between assignment and audit can leave membership with no role evidence. A failed
or uncertain audit is non-success; no automatic rollback, audit replay or exactly-once evidence claim is made.
A fresh corrective invocation dispatches the idempotent package assignment, verifies retained membership and
appends its own attributed confirmation. **Every successful repeat adds one confirmation audit row**, even if no
membership changed. It does not backfill a historical actor or assert that an interrupted earlier assignment was
audited. An audit commit whose acknowledgement is lost may produce duplicate confirmation evidence on repeat;
trusted investigation, not this CLI, resolves historical gaps. No repository hook, participant, deferred-event
buffer or transaction decorator manufactures atomicity.

## Shared dispatch and supported writer boundary

[`ConsoleUserOperation`](../../src/Application/Invitation/ConsoleUserOperation.php) owns installation/eligible-role
policy, package sequencing, partial-state inspection and separate audit coordination. Symfony adapters only map
validated input and render safe results. The operation directly dispatches package messages through the one
configured command bus; queries use the configured query bus. Package handlers retain normal transaction/event
capabilities. The old cyclic operation/owned-handler forwarding, command intent and terminal guard pipelines are
removed, not renamed. No direct handler call, outer/joining UoW or copied package business policy is introduced.

`config/services.php` loads all common/application modules, including lazy Console registrations, for both HTTP
and Console. The normal Console application contains invitation, additive role and bootstrap commands together.
There is no argv-selected graph, postboot registration or command-specific bus replacement. See
[shared services and dispatch](STANDARDS.md#shared-services-and-bus-dispatch).

Raw package messages and installed filters are trusted in-process infrastructure, not an authenticated API or a
hostile-transformer boundary. Application validates its input/current references and inspects outcomes; no ambient
intent/claim scope protects arbitrary filter rewrites. Membership confirmation records observed additive state,
not proof that a void bus dispatch executed a mutation: an already-present role remains membership-idempotent.
Throwing dispatch failures remain non-success, including repeats. Missing new membership cannot be reported as
success. Queries must still return matching identities and exact desired policy definitions, not merely an empty
unchanged plan. Authenticated/nontrusted assignment must use `AuthenticatedRoleAssignment::assign()` for fresh
principal/actor/exact permission checks; never forward raw messages from HTTP or Agent tools. No such assignment
endpoint currently exists. Trusted machine/process access remains the CLI boundary; future managed Agent CLI
exclusion is not implemented by service registration.

The supported installation writer supplies only `InstallationManagedPolicy::create()` via the shipped
preview/apply CLI. Reapplying that fixed catalog cannot redesignate ordinary ROLE_USER as Super Admin. Stable
identity, package semantics, current-state checks and package reference fences establish the supported boundary;
this is not a guarantee against arbitrary newly authored runtime writers or out-of-band authority corruption.
Existing corrupted/alternate catalog state fails reconciliation rather than being accepted as valid.

Historical revision-5 qualification demonstrated a **conditional** gap: two alternate public managed policies can
retire the original designation and redesignate an earlier ordinary managed ID between a preflight and unrestricted
package assignment. A terminal preflight is not atomic against such arbitrary alternate catalog writers. Independent
Review reproduced that diagnostic and counterchecked the shipped fixed writer. It found no in-scope prerequisite
requiring a new product decision or upstream release. The old unconditional qualification hold is superseded.
If a future feature introduces alternate catalog writers, qualify that expanded boundary before claiming safety;
do not import that hypothetical writer as a blocker or silently broaden this implementation's guarantee.

## Safe operation and verification

Both commands require `--internal` and explicit operator attribution. Missing/repeated operation options, positional
or unknown selectors fail safely. Option order and `--option VALUE` forms work. Standard help and verbosity remain;
`--quiet` suppresses success and `--silent` suppresses everything. No password, credential/link, digest, ciphertext,
claim token, cookie/token, provider secret, SQL, stack trace or raw exception is rendered at any verbosity. The CLI
suppresses PHP engine display/logging and interactive suggestions. Docker can report operational errors outside
PHP: never capture verbose private configuration. Normal package `CommandFailedEvent` remains private diagnostic
material; no operator-facing logging subscriber is registered, and one must not be added without sanitization.

The unchanged lock is Access Control **v0.5.0** (`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`), Common **v1.3.0**
(`7de6cad6e8a9752973ad9f8e27e285b0c1510582`) and Symfony Console **v8.1.6**. No package/upstream/ADR edit is required.
Unit tests protect input, policy, package effects, uncertainty and rendering. PostgreSQL integration tests exercise
real package commits/rollback, early provider timing, retained invitations, assignment/audit failures, postcommit
inspection, correction/repeats, ordinary/custom roles, current drift/stale references and dispatch failures/vetoes.
Tests specific to superseded terminal-intent/hostile-transformer protection were removed with that mechanism. Secret markers are checked only against safe output/events/audit/status,
not copied from private rows into evidence. Old all-or-nothing tests were replaced with independent-boundary
assertions, not silently removed. Owning tools validate wiring/source paths directly, not product-suite tests.

Serialize `./bin/database test-reset` then `./bin/phpunit --filter OrdinaryInvitation` for focused PostgreSQL work.
`./bin/backend-tests` measures backend coverage; `./bin/build` remains the mandatory full local gate. Fresh-process
recovery history belongs to [TASK-00037](../../planning/tasks/00037-TASK.md); current refactor evidence and
counts/warnings belong to [TASK-00446](../../planning/tasks/00446-TASK.md). No real email,
scheduler, production certification, crash-kill testing, hosted CI, independent acceptance or publication follows
from an Engineer gate. Fresh independent Review and affected behavioral QA remain required; visual screenshots
are N/A for these non-interactive commands.
