# Trusted ordinary-user console invitations

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

Use exactly this non-interactive argument order:

```sh
./bin/invite-user --internal --operator=OPERATOR_UUID --email=person@example.test
```

Replace `OPERATOR_UUID` with the operator's explicitly enrolled, stable, lowercase UUID. Generate an operator ID
once with a UUID library/owning package ID factory and retain its trusted local identity mapping; do not use the
invited user's ID or infer an actor from environment variables. The UUID is bounded identity evidence, **not**
proof of a logged-in user, database User membership, or authorization. The package role command accepts a typed
`UserId` actor, so the same operator UUID occupies that typed field without claiming an authenticated User.
Durable audit uses the distinct `console:UUID` namespace. Keep the trusted operator mapping outside this command.

Only `--internal`, `--operator=...`, and `--email=...` are accepted. Missing, duplicated, reordered, positional,
unknown or extra arguments fail before resolving runtime dependencies. There is no role, permission, password,
user-ID, URL, provider or credential selector. Operator input must be a valid lowercase UUID. Email is at most
254 ASCII bytes, contains no spaces/control bytes, passes Fight Common email parsing, and is lowercased before
any package dispatch. Case-equivalent existing identities conflict; repeat invocation does not resend or replace
an existing invitation.

Successful stdout is one JSON line containing only canonical `email`, package-generated `user_id`, and
`status: "invited"`. This status means the complete originating invitation committed, **not** that email arrived
or the user activated. A retryable/permanent provider outcome is safe persisted delivery state; provider exceptions
are classified by the package, not printed. No password, credential/link, digest, ciphertext, claim token, private
provider diagnostics, SQL or stack trace is printed. This CLI disables PHP engine logging/display and converts
runtime warnings to its fixed error boundary. Docker can still report operational errors outside PHP; do not
capture verbose configuration or secret-bearing engine diagnostics.

| Exit | Meaning |
|---|---|
| `0` | Originating invitation committed and immediate event dispatch returned; consult delivery status separately |
| `1` | Setup/orchestration failed, or a post-commit outcome is uncertain; stderr is a fixed diagnostic |
| `2` | Invalid input; stderr is fixed usage, with no runtime resolution or writes |

Failure stdout is empty. Do not interpret a nonzero exit as proof of rollback: post-commit events, claim/outcome
writes, output or process failure cannot undo an originating commit. Inspect trusted persisted identity and safe
delivery status before taking further action. Do not blindly reinvite or create a replacement user to clear an
uncertain result. Preserve evidence and restart failed Doctrine managers with a fresh invocation.

## Ownership and coherent commit

The current lock remains Fight Access Control **v0.5.0**, source/lock reference
`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`, and Fight Common **v1.3.0**,
`7de6cad6e8a9752973ad9f8e27e285b0c1510582`. No dependency or upstream source change is required.

`InviteOrdinaryUser` owns installation orchestration only. It directly calls public `InvitePendingUserHandler`
and `AssignRoleToUserHandler` with their package commands. It does not copy identity, grant, assignment or delivery
policy, insert assignments, or register consumer delivery intent. The target UUID comes from the package factory.
The only role is the version-controlled `InstallationManagedPolicy::USER_ROLE_ID`; the package preview must show
the complete managed policy unchanged under shared PostgreSQL permission/role-reference fences. Those fences
remain held through commit, serializing ordinary repository policy changes/removal. Trusted out-of-band SQL is
not an authorized application writer or part of that concurrency guarantee.

The canonical Doctrine unit of work rejects nested transactions. Calling the two handlers sequentially with it
would permit a committed unassigned invitation, and wrapping those unchanged handlers in another transaction
would fail. The **CLI-only** composition instead injects `PostgresInvitationTransaction` as a narrowly scoped
participant into those two handlers. Its outer scope uses the canonical Doctrine commit. Participant callbacks
require that active scope and reject nested participation; they cannot independently commit. The general
application unit of work and delivery handlers remain unchanged. This is an explicitly owned transaction adapter,
not a claim that the package's default transaction adapter supports nesting.

Within the originating transaction:

1. Acquire the existing permission and role reference fences and require exact reconciled managed authority.
2. Invoke package pending invitation behavior: identity/email reservation, seven-day activation grant,
   encrypted recoverable delivery generation, and `user.invited` audit attributed to `console:UUID`.
3. Invoke package role assignment with the **operator**, never the target, as actor; assign only managed `ROLE_USER`.
4. Append required `user.console_role_assigned` evidence for the same console actor and target, with empty,
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
fixed least authority, drift rejection, safe transport output/failures and random credential representation.
`tests/Integration/Postgres/OrdinaryInvitationTest.php` runs real package handlers and the owned repositories:
pending state, exact permissions, seven-day lifetime, console audit, duplicate conflict, missing/drifted/stale
policy, material/write/audit/commit failures, shared reference fencing, lost immediate delivery, post-commit event
failure, claim/outcome failures, provider exceptions and fresh-worker recovery. Markers in credentials, provider
exceptions and originating errors are checked against output, safe success facts, audit and package status Views;
private database rows are inspected in tests but never copied to operator receipts. Existing delivery integration
tests additionally cover successor/outcome fencing, cancellation, expiry and competing claims.

Use `./bin/database test-reset` before focused PostgreSQL tests, then `./bin/phpunit --filter OrdinaryInvitation`.
`./bin/backend-tests` measures all backend suites; `./bin/build` remains the mandatory complete local gate.
No new HTTP route or UI exists. Automated implementation evidence is not independent technical acceptance,
behavioral QA, real-provider qualification, deployed scheduling or production privilege certification.
