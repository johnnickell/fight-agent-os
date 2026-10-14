# Trusted ordinary-user console invitations

## Current owner-approved revision — blocked, not shipped

[TASK-00037](../../planning/tasks/00037-TASK.md) now accepts independently committed invitation and normal
managed `ROLE_USER` assignment, and requests trusted existing-role correction excluding authoritative Super Admin.
**The current source still contains the earlier atomic participant and has no `user:role:add` command.**
Canonical Review R2 remains REVISE; historical QA does not accept the revised behavior. Do not deploy the current
participant as the approved two-step model or run the planned correction contract below as if it existed.

The approved replacement must use normal package `TransactionalUnitOfWork` and `EventDispatcher`, with shared
container/configured buses. Application owns sequencing/partial-result policy; Console only maps input/output.
No outer/nested/joining transaction decorator, direct handler call, copied policy, repository audit hook or
consumer delivery lifecycle workaround is authorized. ADR 0001/0002 remain unchanged.

### Approved operation and recovery contract

Invitation retains its input contract. Its first package commit creates the pending identity, seven-day grant,
recoverable delivery state and package invitation audit. Normal events/delivery may run **before** the independent
ordinary-role assignment. “Two transactions” means two independent business commits, not exactly two physical
transactions across delivery claims/outcomes or audit. Assignment failure may leave an invitation committed and
potentially emailed; it must return non-success, not claim rollback. After a postcommit exception, inspect safe
actual persisted state before asserting what committed. Distinguish known invitation from missing/uncertain role,
include a safe target UUID/corrective instruction, and never print private exceptions or credential material.
Do not blindly reinvite an uncertain or existing identity.

Planned correction contract (**unimplemented**):

```sh
./bin/console user:role:add --internal --operator=OPERATOR_UUID --user=EXISTING_USER_UUID --role=EXISTING_ROLE_UUID
```

Use enrolled lowercase operator UUID attribution and existing user/role UUID selectors, not names. Correction is
additive/idempotent, retains existing roles and creates no identity, grant, credential delivery or role definition.
No registration, resend, password, role removal, bootstrap or web access. Restart with a fresh process after a
failed manager; correction must not duplicate originating work. Prohibit authoritative Super Admin by stable
managed ID **and package meaning**, not label alone, checking the actual filtered payload before mutation while
preserving actor/target and package protected-tier/reference rules. Unknown/malformed/stale identities fail safely.
Operator/target audit remains mandatory evidence; disclose any separate postcommit audit/crash boundary and missing
or uncertain evidence instead of claiming assignment/audit atomicity. No production audit boundary is implemented
for this revision yet.

### Safety prerequisite

The locked Access Control v0.5.0 `AssignRoleToUser` accepts actor/target/role IDs but no expected-role snapshot or
consumer transactional authorization callback. A terminal filter can inspect the actual payload and safe role View,
but its check precedes the handler's transaction. Fresh guarded qualification validates even an exact installation
preview, then interleaves two **public managed-policy commits** that retire the original designation and re-designate
the selected ordinary managed ID as Super Admin. The unchanged assignment subsequently commits **19 protected
permissions** and no assignment audit. Role-reference fencing checks current existence, not the prior non-Super-Admin
snapshot. The one-commit transfer is correctly blocked by name-owner rules; the supported two-stage change succeeds.

This is an API concurrency counterexample, **not a demonstrated exploit of the current CLI**: the controlled writer
uses an alternate package policy, not the shipped fixed installation-policy CLI. Custom-role promotion/protected-tier
rules still hold. Resolve whether this supported mutable-policy interleaving is in the required stale/alias threat
model, or qualify an owning-package transactional authorization/expected-state capability under separate authority.
Do not silently weaken the requirement or add consumer locks/transactions/upstream patches. See TASK-00037 for the
exact probe/evidence and limits. The revision remains incomplete; green tests of the preserved implementation do
not establish the new behavior.

## Earlier implementation runbook — historical mechanism, not revision-5 acceptance

The remainder documents the still-present earlier implementation. Its all-or-nothing/deferred-event mechanics
and failure output below are **not** the owner-approved replacement. Retained commands describe current code only.

This operation invites one ordinary user into this installation. It is not public registration, a web invitation,
a Super Admin bootstrap, an arbitrary role assignment, or an activation journey. Restrict shell, checkout,
Docker and database access to trusted operators. `--internal` acknowledges this boundary; it is not authentication
or an end-user permission check.

## Preparation and command

Confirm the intended Compose project and database through trusted private configuration, without printing its
connection strings. Install the committed Composer lock and apply the installation's approved migrations through
`./bin/database migrate`. Preview and explicitly reconcile the complete managed policy using the
[managed policy runbook](MANAGED_POLICY.md). Invitation refuses missing or drifted policy; it never repairs it.
Supply the independent external `APP_CREDENTIAL_DELIVERY_KEY` and retain it while invitations are outstanding.
The existing [credential recovery runbook](CREDENTIAL_RECOVERY.md) owns provider setup, safe status and recovery.
Local/test null and memory providers do not send email; production provider enrollment remains separate.

Use the generic non-interactive Console entry point (option order is flexible):

```sh
./bin/console user:invite --internal --operator=OPERATOR_UUID --email=person@example.test
```

Replace `OPERATOR_UUID` with the operator's explicitly enrolled, stable, lowercase UUID. Generate an operator ID
once with a UUID library/owning package ID factory and retain its trusted local identity mapping; do not use the
invited user's ID or infer an actor from environment variables. The UUID is bounded identity evidence, **not**
proof of a logged-in user, database User membership, or authorization. The package role command accepts a typed
`UserId` actor, so the same operator UUID occupies that typed field without claiming an authenticated User.
Durable audit uses the distinct `console:UUID` namespace. Keep the trusted operator mapping outside this command.

The operation accepts `--internal`, `--operator=...`, and `--email=...`, plus standard Symfony options such as
`--help`, `--no-interaction` and verbosity. Option order is flexible; `--operator UUID` and `--email EMAIL` also work.
Missing, duplicated operation options, positional, unknown or extra arguments produce a controlled failure without
dispatch. There is no role, permission, password, user-ID, URL, provider or credential selector. Use
`./bin/console help user:invite` for help. `--quiet` suppresses successful output; `--silent` suppresses all output.
When rendered, failures remain fixed and safe at every verbosity. Operator input must be a valid lowercase UUID.
Email is at most
254 ASCII bytes, contains no spaces/control bytes, passes Fight Common email parsing, and is lowercased before
any package dispatch. Case-equivalent existing identities conflict; repeat invocation does not resend or replace
an existing invitation.

Successful stdout is one JSON line containing only canonical `email`, package-generated `user_id`, and
`status: "invited"`. This status means the complete originating invitation committed, **not** that email arrived
or the user activated. A retryable/permanent provider outcome is safe persisted delivery state; provider exceptions
are classified by the package, not printed. No password, credential/link, digest, ciphertext, claim token, private
provider diagnostics, SQL or stack trace is printed. This CLI disables PHP engine logging/display and converts
runtime warnings to its fixed error boundary. Symfony parsing/unknown-option/unknown-command errors and verbose
modes never render raw input, exception details or stack traces; interactive suggestions are disabled. Docker can
still report operational errors outside PHP; do not capture verbose configuration or secret-bearing diagnostics.

| Exit | Meaning |
|---|---|
| `0` | Originating invitation committed and immediate event dispatch returned; consult delivery status separately |
| `1` | Setup/orchestration failed, or a post-commit outcome is uncertain; stderr is a fixed diagnostic |
| `2` | Invalid input; stderr is fixed usage or a fixed Console input diagnostic, with no dispatch or writes |

Failure stdout is empty. Do not interpret a nonzero exit as proof of rollback: post-commit events, claim/outcome
writes, output or process failure cannot undo an originating commit. Inspect trusted persisted identity and safe
delivery status before taking further action. Do not blindly reinvite or create a replacement user to clear an
uncertain result. Preserve evidence and restart failed Doctrine managers with a fresh invocation.

## Ownership and coherent commit

The current lock remains Fight Access Control **v0.5.0**, source/lock reference
`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`, and Fight Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. No Fight package version or upstream source changed.
Symfony Console is now an explicit runtime dependency (`^8.1`), retaining the already-locked **v8.1.6**.

The Adapter `InviteUserCommand` injects `CommandBus`/`QueryBus` and dispatches the owned `InviteOrdinaryUser`
message. [`App\Application\Invitation\CommandHandler\InviteOrdinaryUserHandler`](../../src/Application/Invitation/CommandHandler/InviteOrdinaryUserHandler.php)
owns installation orchestration: it dispatches public `InvitePendingUser`
and `AssignRoleToUser` messages through the same configured command pipeline, and policy/outcome reads through the
query pipeline. It never injects/calls those handlers directly. It does not copy identity, grant, assignment or
delivery policy, insert assignments, or register consumer delivery intent. This owned message is justified by the
installation policy, atomic cross-package workflow and required operator audit, not a package forwarding alias.
The target UUID comes from the package factory.
The only role is the version-controlled `InstallationManagedPolicy::USER_ROLE_ID`; the package preview must show
the complete managed policy unchanged under shared PostgreSQL permission/role-reference fences. Those fences
remain held through commit, serializing ordinary repository policy changes/removal. Trusted out-of-band SQL is
not an authorized application writer or part of that concurrency guarantee.

The canonical Doctrine unit of work rejects nested transactions. Calling the two handlers sequentially with it
would permit a committed unassigned invitation, and wrapping those unchanged handlers in another transaction
would fail. Console composition registers
[`App\Adapter\Persistence\Invitation\PostgresInvitationTransaction`](../../src/Adapter/Persistence/Invitation/PostgresInvitationTransaction.php)
as a narrowly scoped participant for
the two routed package handlers. Its outer scope uses the canonical Doctrine commit. Participant callbacks require
that active scope and reject nested participation; direct dispatch of either package operation outside the owned
scope fails closed. The general application unit of work and delivery handlers remain unchanged. This is an
explicitly owned transaction adapter, not a claim that the package default supports nesting.

All stateless handlers, the Symfony command, buses, scoped participant and deferred dispatcher are shared
registrations resolved from the same existing container. There is no private handler graph or competing invitation
entry point. Invocation-local participant/event state is cleared on success and failure before post-commit dispatch.
A rollback closes the canonical Doctrine manager: a subsequent operation on that failed container remains fail-closed;
restart with a fresh process/container rather than resurrecting a failed manager. Successful commands can reuse
the shared instances without replaying prior events.

Within the originating transaction:

1. Acquire the existing permission and role reference fences and require exact reconciled managed authority.
2. Invoke package pending invitation behavior: identity/email reservation, seven-day activation grant,
   encrypted recoverable delivery generation, and `user.invited` audit attributed to `console:UUID`.
3. Invoke package role assignment with the **operator**, never the target, as actor; assign only managed `ROLE_USER`.
4. Require the safe queried target to match the requested pending email/identity and exactly `ROLE_USER`; a silent
   nested filter veto or elevated-role transformation must roll back, not leave partial or elevated authority.
   Append required `user.console_role_assigned` evidence for the same console actor and target, with empty,
   approved context. No arbitrary operator metadata enters audit.
5. Commit all those effects together. Any originating write, audit, material preparation, assignment or commit
   failure rolls them back and discards deferred success events.

Only safe `UserInvited` and `RoleAssignedToUser` facts are retained locally until commit. Originating package
`CommandFailedEvent` payloads can contain private exception text and are deliberately not forwarded. After commit,
the normal dispatcher receives success facts and the existing package invitation subscriber starts delivery.
The current console composition has no private-event logging/presentation subscriber; never attach an unredacted
failure-event logger. Delivery's internal package failures are not an operator output contract. There is no durable
event outbox/replay guarantee: if event dispatch fails, inspect persisted state and use recovery. A failing subscriber
may prevent subsequent success facts from being published, but cannot remove the required committed audit.

The committed target is pending activation with exactly `ROLE_USER` (dashboard and own-session permissions), no
password and no elevated role. Normal delivery commits a claim first, calls the provider outside any transaction,
and commits its outcome/audit afterward. Failed claim writes leave pending work; failed outcome writes leave a
recoverable claim until lease expiry. The originating identity/assignment/audit remain committed in both cases,
and the console reports failure/uncertainty rather than apparent success. Provider-success/outcome-loss can cause
a duplicate delivery with the same stable delivery identity: this is honestly at-least-once, not exactly-once.

## Verification boundaries

`tests/Unit/Invitation/OrdinaryInvitationTest.php` protects bounded input, canonicalization, command actors,
fixed least authority, drift rejection, serialized messages, safe Symfony parsing/verbose rendering and random
credential representation.
`tests/Integration/Postgres/OrdinaryInvitationTest.php` runs real package handlers and the owned repositories:
pending state, exact permissions, seven-day lifetime, console audit, duplicate conflict, missing/drifted/stale
policy, material/write/audit/commit failures, shared reference fencing, lost immediate delivery, post-commit event
failure, claim/outcome failures, provider exceptions and fresh-worker recovery. Real command-filter vetoes and
elevated-role message transformation prove rejection at the owned workflow boundary. A query filter intentionally
withholds the required policy-preview message; assertions outside the Console-caught callback verify that the
veto path ran, output stayed safe, and no originating, provider or event effects remained. The installed query-filter
contract forwards messages through a void `$next` callback; these tests do not prove query-result transformation.
Repeated shared invocations prove transaction/event isolation. Markers in credentials, provider exceptions and originating errors are checked against output, safe success facts, audit and package status Views;
private database rows are inspected in tests but never copied to operator receipts. Existing delivery integration
tests additionally cover successor/outcome fencing, cancellation, expiry and competing claims.

Use `./bin/database test-reset` before focused PostgreSQL tests, then `./bin/phpunit --filter OrdinaryInvitation`.
`./bin/backend-tests` measures all backend suites; `./bin/build` remains the mandatory complete local gate.
No new HTTP route or UI exists. Automated implementation evidence is not independent technical acceptance,
behavioral QA, real-provider qualification, deployed scheduling or production privilege certification.
