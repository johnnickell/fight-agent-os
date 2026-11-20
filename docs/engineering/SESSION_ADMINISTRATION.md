# Installation session administration

TASK-00122 adds API-only inspection and revocation of **another** installation user's active sessions.
There is no administrative browser UI, Workspace/Project scope, logout-everywhere or lifetime editing.

| Operation | Exact current permission | Input | Success |
|---|---|---|---|
| `GET /api/v1/users/{userId}/sessions` | `READ_SESSIONS` | Canonical user UUID; `page` 1–1000, `per_page` 1–100 (defaults 1/25) | Safe bounded session page |
| `DELETE /api/v1/users/{userId}/sessions/{sessionId}` | `DELETE_SESSIONS` | Canonical user/session UUIDs; JSON object containing only `reason` | JSend success with null data |

Each HTTP Action declares its permission once; `ApiActionSecurity` checks fresh authoritative request state
before execution. Self-service permissions, role labels and JWT claims confer no administrative permission.
The retained package actor/target callback independently checks the exact operation's protected managed
`SUPER_ADMIN_ONLY` permission, active actor and installed target. Read and revoke use separately composed
callbacks, never a combined read-and-delete fallback. No blanket Application admission gate is added.

Self targets on these administrative paths return 403. Use the owner-only `/me/sessions` paths or current-session
Logout instead. Actor and current-session IDs come exclusively from the request-scoped principal; neither can
be supplied in JSON/query fields. Unknown/forbidden read targets return generic 403. Unknown, wrong-owner,
expired, revoked, forbidden-by-callback or competing revoke targets return generic 409 without session details.
A malformed ID or invalid reason is 400; the existing credential-transport guard may instead reject Bearer/token
material as 401 before validation. Missing or stale operation authority is 401/403 before package dispatch.

## Safe data and reason

The read uses public package `ListActiveSessions` and `SessionView`; only one exact target is selected and the
returned Views are checked against that target. The existing explicit session projection is reused, with
`browser_family: Unknown`, `device_class: unknown` and `current: false`. No presentation sidecar is fetched.
It includes session ID, creation/refresh-activity/idle/absolute timestamps and remembered state. Activity is not
online presence. Order is creation then ID, and offset pages are not a multi-request snapshot. No owner IDs,
credentials/digests, cookies, IP, raw User-Agent, authority versions, audit or internal revisions are returned.
Success and error responses are `Cache-Control: no-store`; the attribute-owned OpenAPI describes both routes.

Deletion requires a reason trimmed by package `SessionRevocationReason`, 1–500 Unicode characters. Agent OS
rejects control characters and its existing audit policy's credential/secret/authorization/URL terms before
mutation. Never put credentials or private information in a reason: lexical checks are not a universal secret
detector. Under the [TASK-00122 amendment](../../planning/tasks/00122-TASK.md#current-amendment--task-00573),
the validated reason is stored in protected audit only. It is not returned in HTTP or logged. Do not log
request bodies or package failure events' original commands, which can carry the reason.

## Qualified package and transaction boundary

Qualification used the installed stable Composer distribution and remote annotated tag:

- Access Control `v0.8.0`, tag object `6d696c0ae5bb843366b601633fed3af3311b7a48`, peeled source and lock reference
  `319738255e682d587642b67856039f882b9588d6`.
- Common `v1.4.1`, tag object `7e1c2fc1989fdf3fef99ddbe4ef74488856318d1`, peeled source and lock reference
  `a482c220d312d2ef7762c3f0d85d9fb76a7021fa`.

These are the original qualification identities, not guarantees for future releases. TASK-00572 subsequently
adopts Common v1.5.0 at `06fb5cd3720af9cf8295198f74040d95b64c5155` while retaining Access Control v0.8.0;
see [current transaction composition and failure recovery](TRANSACTIONS.md). No vendor patch or development snapshot is used.

`RevokeSession` accepts actor/current/target-session/reason, **not an explicit target user**. The HTTP adapter
therefore checks the selected session's owner against the path user before dispatch. Session ownership is
immutable: `PostgresRefreshSessionRepository::replace` rejects owner/ID changes and compares complete expected
state/revision. The package handler rereads usable state and invokes its actor/actual-owner callback, then
optimistically replaces only that session. With Access Control v0.11.0 and Common v1.5.1,
`RevokeSessionHandler` writes `AuditSessionRevocation` in the same generic transaction as the session change.
The `refresh_session.administratively_revoked` action records typed actor and target user, session ID,
before/after revisions, recording time and the validated `facts.reason`. Self-service audit has no reason.
No consumer transaction wraps package dispatch.

Earlier records are unchanged and remain readable under the same audit protections. The v0.10.1 typed
administrative payload has no reason; older flat records use `refresh_session_id` and may carry their original
reason. Read historical records in their stored format. v0.11.0 `AuditEvidence::fromArray` requires a reason
for typed administrative revocation, so do not pass earlier reasonless or flat rows to that hydrator or
supply a reason that was never recorded.

The configured `DbalTransactionalUnitOfWork` uses the exact shared DBAL connection directly;
both session and audit repositories use that same registered connection and require an enclosing transaction.
The repository's conflict-recovery savepoint neither commits the outer transaction nor supplies a second
transaction authority. PostgreSQL integration proves audit-write failure rolls session state back and no success
event fires, and verifies safe durable audit on successful commit. `RefreshSessionRevoked` dispatches after
commit. A subsequent event-dispatch failure can follow a successful commit; an exception is not rollback proof.
Refetch rather than blindly retrying an uncertain response.

Committed revocation immediately invalidates target access and refresh authority. Concurrent revoke, refresh,
logout and expiry are protected by expected-state replacement, not an ordinary permission-revocation commit lock.
An admitted request may finish after permission removal; the next request checks fresh authority. Controlled
independent-process PostgreSQL contenders cover self and administrative revocation without overwriting winners.

See [owned sessions](OWNED_SESSIONS.md) for unchanged self-service presentation and confirmation contracts.
Implementation verification and independent acceptance remain separate in the owning TASK and canonical reports.
