# One-time trusted Super Admin bootstrap

**A committed bootstrap claim is permanent, even if no user is created. There is no reset, resume, second-target
or bootstrap retry command. Recovery of an interrupted claimed bootstrap is separately planned and is not
available in this release. Do not use ordinary invitation or `user:role:add` as an elevation workaround.**

This is a trusted installation console capability, not authentication, public registration or a web permission
bypass. Restrict shell, checkout, Docker and database access. Verify the intended Compose project/database privately;
never print connection strings. Apply approved migrations with `./bin/database migrate`. Preserve the independent
external delivery key for outstanding work. Local null/memory providers send no email; production provider and
scheduler enrollment remain separate. See [credential recovery](CREDENTIAL_RECOVERY.md).

## Confirm the permanent claim

Enroll an operator UUID using the owning ID factory or a UUID library and retain its trusted identity mapping
outside the command. It need not identify a database User. Do not substitute the invited target as operator.

```sh
./bin/console user:bootstrap-super-admin --internal --confirm-bootstrap --operator=OPERATOR_UUID --email=person@example.test
```

Both acknowledgements are mandatory, including interactive shells and `--no-interaction`. There is no default-yes
prompt. `--confirm-bootstrap` deliberately accepts permanent consumption before invitation and possible early mail
delivery. Operator input is a lowercase UUID. Email follows ordinary console validation (at most 254 ASCII bytes,
no whitespace/control characters, Fight Common parsing) and is canonicalized to lowercase. There are no role,
target-ID, password, credential, provider or reset selectors. Repeated/unknown/positional options fail safely.

The operation first dispatches the fixed `ReconcileManagedPolicy`, verifies its exact public preview and resolves
the stable managed Super Admin ID. Policy failure alone cannot consume bootstrap. Reconciliation may also run for
a refused later invocation; it creates no second invitation or success audit.

## Commit boundaries and guard semantics

1. A short, independent **READ COMMITTED PostgreSQL transaction** takes advisory transaction lock `(24121, 1)`
   and the existing role-reference fence. It rechecks `installation_bootstrap` and authoritative assignments to
   the stable Super Admin role, without lifecycle filtering. Pending, active, disabled and deleted users all count.
   It atomically inserts the singleton claim and `user.bootstrap_claimed` audit, then commits.
2. Only the invocation acknowledging that commit dispatches normal package `InvitePendingUser`. Its independent
   transaction owns the pending/passwordless user, seven-day activation grant, encrypted recoverable delivery and
   `user.invited` audit. Package delivery can start immediately after this commit, **before elevation**.
3. That invocation dispatches normal package `AssignRoleToUser` for the hardcoded stable Super Admin ID, with
   the explicit operator UUID as typed package actor. The package owns its own commit, reference checks and event.
4. After independent safe-state inspection confirms the exact pending target/email and exact Super Admin
   membership, a separate transaction appends mandatory `user.bootstrap_completed`. Final fresh inspection must
   confirm membership and both required bootstrap audits before success is reported.

The singleton claim contains immutable operator UUID, target UUID, canonical email and database claim time.
It deliberately has no user foreign key: it precedes identity creation and survives user/role lifecycle changes.
Database UPDATE/DELETE are rejected; schema rollback is irreversible. Privileged database/schema operations are
not an application recovery API. Never truncate, delete or edit this record to reopen bootstrap.

Claim/completion audit actors are `bootstrap:OPERATOR_UUID`; package invitation/delivery audit uses
`console:OPERATOR_UUID`. The package assignment event carries the typed operator UUID, never the pending target
as an authenticated authorizer. Audit contexts contain no email, credential or private diagnostic payload.

There is no outer/nested/joining unit of work, deferred event buffer, identity/assignment repository bypass,
consumer delivery queue/participant, compensation or uncertain-mutation retry. The durable claim is bootstrap
eligibility state, not credential delivery intent. Policy and delivery transactions are additional independent
transactions, not part of an atomic four-step result.

## Results, partial state and uncertainty

| Exit | Meaning |
|---|---|
| `0` | Exact pending target, elevated membership, permanent claim and required audit independently verified |
| `1` | Refusal, partial execution or uncertainty; never proof that earlier steps rolled back |
| `2` | Invalid input or absent deliberate confirmation; no operation dispatched |

Success stdout is one safe JSON line (`status: bootstrap_complete`, `claim`, `invitation`, `assignment`, `audit`,
`user_id`). Failure stdout is empty; stderr reports the same bounded step facts with
`status: partial_or_uncertain` and recovery guidance. A target ID is shown only for a claim matching this invocation.
No previous contender's target/email/operator is disclosed. `claim: absent` means a fresh read observed no claim;
verify a known pre-claim failure before starting a new invocation. `claim: unavailable` means another claim owns
bootstrap; `unknown` is not permission to retry. `invitation`/`assignment` are `confirmed`, `missing` or `unknown`;
`audit` is `confirmed` or `unconfirmed`. Quiet/silent behavior follows Symfony Console.

Exceptions may follow commits. Inspection uses a separately composed connection, not a failed/closed Doctrine
manager or a stale identity map. An acknowledged claim whose invitation fails remains consumed. Invitation failure
can retain its target/grant/delivery; assignment failure retains the invited identity; completion-audit failure
retains membership. A process exit between any steps retains everything already committed. A claim commit whose
acknowledgement is lost **does not dispatch invitation**, even when subsequent inspection finds the claim.
A completion commit whose acknowledgement is lost can be verified from its durable required evidence.

There is an explicit assignment-to-completion-audit crash window. No success is reported without required audit.
Do not blindly reinvite, choose a replacement target, replay bootstrap or infer that a failed command rolled back.
Preserve safe output and inspect trusted persisted state. **Interrupted-claim recovery requires separately planned
work; this release intentionally supplies no command to complete it.** Normal package delivery recovery can still
process the existing generation without changing bootstrap, membership or claim state.

## Composition and normal authenticated assignment

Only the exact dedicated console selection loads `config/application/bootstrap_super_admin.php`. Normal HTTP and
ordinary console containers register no bootstrap operation, scope, handler or claim capability. The dedicated
terminal guard runs after configured transformations, checks retained exact class/actor/target/email/role intent,
and binds exactly one invitation and one hardcoded elevation to this invocation's claimed operator, target and email.
It runs in a separate routing pipeline behind configurable filters, as do the normal and ordinary-console guards:
Common's mutable outer execution stack cannot consume the terminal guard through nested filter dispatch. Unsupported
continuation reentry may safely interrupt the invocation; it never authorizes another target or reopens the claim.
Transient scope is cleared in `finally`; it never resets durable state. Ordinary `user:role:add` continues to refuse Super Admin.
The supported policy writer remains the fixed installation catalog, not arbitrary alternate catalog writers or
out-of-band database redesignation; see [ordinary console boundaries](CONSOLE_INVITATIONS.md).

Normal `AssignRoleToUser` dispatch has its own innermost Application authorization filter. It resolves a fresh
package authoritative principal for **each mutation**, using a verified invocation-bound
`AuthenticationContextProvider`; it does not reuse a cached permission projection. The default shared provider
denies authentication. Future authenticated adapters must bind verified context in their invocation-scoped graph
before resolving the bus. Current login/HTTP assignment UI and explicit web confirmation remain unimplemented.
The guard requires exact `MANAGE_USER_ROLES` and, for stable or package-semantic Super Admin, exact
`ASSIGN_SUPER_ADMIN`. Unknown, stale, revoked or actor-mismatched authority fails closed. A role name only identifies
the protected target; it never grants authority. The bootstrap console does not install a permanent exception in
this normal guard. Package assignment policy/reference fences remain unchanged.

## Diagnostics and verification

Parsing and handler failures never render raw input, exceptions, stack traces, SQL, private configuration,
passwords, credential URLs/digests/ciphertext, tokens or provider secrets, including verbose output. Package
`CommandFailedEvent` is private diagnostic material, not an operator log; no operator-facing failure subscriber is
added. Do not enroll one without sanitization. Docker operational errors outside PHP remain a separate boundary.

The unchanged lock qualifies Access Control v0.5.0 (`46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0`) and Common v1.3.0
(`7de6cad6e8a9752973ad9f8e27e285b0c1510582`). The owner-approved separate-commit contract needs no upstream edit.
Run focused checks serially after `./bin/database test-reset`; `./bin/build` remains the full gate.
Unit/PostgreSQL tests cover attribution, explicit confirmation, retained partial steps, postcommit events,
claim/audit rollback, provider and delivery bookkeeping failures, exact transformed payloads, concurrent console
claim contenders synchronized by real locks without sleeps, abrupt claimed-process exit, normal current-permission
authorization and seeded redaction. Validate migration/composition directly with their owning tools, not product
configuration tests. Actual counts, coverage, warnings and evidence belong to [TASK-00038](../../planning/tasks/00038-TASK.md).
Independent Review/QA, hosted verification, publication and production enrollment are separate.
