# Trusted Super Admin bootstrap

Run this manually, **one trusted operator at a time**. Bootstrap refuses if any assignment to the installed
Super Admin role exists, regardless of user lifecycle, or if the authoritative presence query fails. There is no
bootstrap lock, concurrency guarantee or permanent admission claim. After failure/partial completion, another
manual attempt is permitted when no Super Admin exists. Inspect retained state first; an existing email remains
a normal invitation conflict, not an implicit resend, reset or resume.

This TASK-00446 policy supersedes TASK-00038/TICKET-00015's permanent-claim/no-retry, concurrent admission and
command-specific composition requirements. Their old implementation reports remain historical evidence.

## Prepare and confirm

Restrict shell, checkout, Docker and database access to trusted operators. Verify the intended Compose project
and database privately; never print connection strings. Apply approved migrations with `./bin/database migrate`.
Preserve the independent external delivery key for outstanding work. Local null/memory providers send no email;
production provider/scheduler enrollment is separate. See [credential recovery](CREDENTIAL_RECOVERY.md).

Enroll an operator UUID using the owning ID factory or a UUID library and retain its trusted identity mapping
outside the command. It need not identify a database User. Do not substitute the target ID as operator.

```sh
./bin/console user:bootstrap-super-admin --internal --confirm-bootstrap --operator=OPERATOR_UUID --email=person@example.test
```

Both acknowledgements are mandatory, including interactive and non-interactive invocations. Neither a flag nor an
operator UUID authenticates a person. Email uses the ordinary console validation and lowercase canonicalization;
repeated, unknown and positional options fail safely. No role, password, target-ID, provider or credential selector
is available. The pending user must complete normal activation.

## Independent steps, not an atomic result

1. Query authoritative assignments to the stable installed Super Admin ID. Any assignment or lookup failure
   refuses **before mutation**, including policy reconciliation. Assume no simultaneous bootstrap operator.
2. Reconcile the fixed `InstallationManagedPolicy`, verify its exact public preview and stable managed role.
3. Dispatch package `InvitePendingUser`. Its transaction owns pending/passwordless identity, seven-day activation
   grant, encrypted recoverable delivery and `user.invited` audit. Postcommit delivery may start **before elevation**.
4. Dispatch package `AssignRoleToUser` for the stable Super Admin ID with the explicit typed operator as actor.
   Package transaction, reference checks and events are unchanged.
5. Independently inspect the exact pending target/email and exact Super Admin membership, then append
   `user.bootstrap_completed` in a separate audit transaction. Fresh inspection must confirm all required facts
   before success is reported.

Completion audit uses `bootstrap:OPERATOR_UUID`; package invitation/delivery audit uses `console:OPERATOR_UUID`.
The assignment event carries the typed operator UUID, never the pending target as its own authorizer. No new
`user.bootstrap_claimed` evidence or singleton claim is written. Existing `installation_bootstrap` rows and old
audit records remain untouched as historical evidence; runtime neither reads them for admission nor deletes them.
The historical migration and its immutable table remain for database compatibility, not as an active retry blocker.

No outer/joining transaction, direct package handler/repository write, deferred event buffer, consumer delivery
queue, compensation or automatic retry is added. Package transaction/reference machinery remains normal; removing
bootstrap-specific locking does not remove package-owned consistency protections.

## Results and manual decisions

| Exit | Meaning |
|---|---|
| `0` | Exact pending target, elevated membership and separate completion audit independently verified |
| `1` | Refusal, partial execution or uncertainty; never proof of rollback |
| `2` | Invalid input or missing confirmation; no operation executed |

Success stdout is one JSON line with `status: bootstrap_complete`, `admission`, `invitation`, `assignment`, `audit`
and `user_id`. Failure stdout is empty; stderr reports bounded step facts with `status: partial_or_uncertain` and
inspection/retry guidance. `admission` is `admitted`, `refused` or `unknown`; it is a presence-check result, not a
stored claim or an authentication credential. A target ID is shown only when this invocation's intended pending
invitation is confirmed. Invitation/assignment facts are `confirmed`, `missing` or `unknown`; audit is `confirmed`
or `unconfirmed`. Quiet/silent behavior follows Symfony Console.

Exceptions can follow commits. Inspection uses an independent connection rather than a failed Doctrine manager.
Invitation/assignment/completion-audit failures retain every earlier committed step, including delivery work.
A crash between assignment and completion audit can leave a Super Admin without completion evidence; later
bootstrap then refuses. This command does not repair that audit gap. A failed invitation/assignment with **no**
Super Admin permits a later manual attempt in a fresh process. A retained same-email invitation still conflicts;
there is no automatic recovery, reinvitation, rollback, reset or resume. Normal package delivery recovery can
process an existing generation independently of bootstrap. Do not use ordinary role correction as an elevation
workaround.

## Composition and authenticated callers

`config/services.php` deterministically loads all parameter/common/application modules. Command, query and event
modules register one shared bus graph. `config/application/console_invitation.php` registers the Console
application with all three commands; `bootstrap_super_admin.php` registers the bootstrap dependencies lazily.
`scripts/console.php` only boots that container and runs the registered application. HTTP uses the same graph;
registration does not execute a command, reconcile policy, require the unused delivery key or grant authority.
See [shared services and dispatch](STANDARDS.md#shared-services-and-bus-dispatch).

**Raw package bus dispatch is trusted in-process infrastructure, not an authenticated API.** TASK-00446 removes
the old raw `AssignRoleToUser` authentication filter, not merely its name. Authenticated/nontrusted assignment
must invoke `AuthenticatedRoleAssignment::assign()`: it freshly resolves the authoritative principal, matches
actor, requires exact `MANAGE_USER_ROLES`, and additionally exact `ASSIGN_SUPER_ADMIN` for stable or
package-semantic Super Admin. Unknown, stale, revoked, absent or mismatched authority fails before dispatch.
Role names identify a protected target, never grant authority. The default authentication provider refuses;
a future authenticated adapter must inject verified invocation-bound context, not mutate a shared global principal.

There is currently no HTTP/Agent assignment consumer, raw-message forwarding endpoint or role-administration UI.
The existing `/me` and authentication middleware are read/security paths, not assignment entry points. Tests that
previously dispatched raw assignments as authenticated callers now invoke this explicit use case. Trusted console
orchestration dispatches package messages through the same bus without fabricating a browser principal. Installed
filters/handlers are trusted code, not a separate hostile-caller boundary; no terminal intent scope protects
arbitrary transformations of package messages. Never forward untrusted raw messages into this graph.

The future managed no-Agent-shell/CLI boundary is **not implemented here**. Do not expose these operator use cases
through HTTP, Agent tools or arbitrary-process capabilities. Hiding commands or registering separate containers
would not enforce that boundary.

## Diagnostics and verification

Parsing and execution failures never render arbitrary input, exceptions, SQL, private configuration, credentials,
credential URLs, tokens or provider secrets, including at verbose levels. Package `CommandFailedEvent` remains
private diagnostic material; no operator-facing failure subscriber is installed. Docker errors outside PHP remain
a separate operational boundary.

Access Control remains v0.5.0 (`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`) and Common v1.3.0
(`7de6cad6e8a9752973ad9f8e27e285b0c1510582`). Tests protect authoritative admission/refusal, fresh permissions,
independent commits and retained partial steps, manual retry, historical evidence, delivery recovery and safe output.
Concurrent admission and intent/claim-guard tests are deliberately removed with their superseded mechanics.
Validate loader/aliases/discovery directly with owning tools, never product wiring tests. Serialize focused
PostgreSQL checks after `./bin/database test-reset`; `./bin/build` remains mandatory. Fresh counts, coverage,
warnings and limits belong to [TASK-00446](../../planning/tasks/00446-TASK.md). Independent Review/QA and publication
remain separate.
