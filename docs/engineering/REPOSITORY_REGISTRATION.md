# Explicit Workspace bootstrap and Repository registration

TASK-00078 supplies registration metadata, not a checkout, Repository access writer or execution capability.
[ADR 0005](../../planning/adr/0005-repository-identity-and-link-consistency.md) and its supporting contract remain
normative. John's approved 2026-10-20 amendment separates explicit Workspace bootstrap here from initial Host
enrollment in TASK-00079. No Host/Checkout/Worktree persistence or physical linking is implemented here.

## Explicit installation setup

Apply the normal Doctrine migrations explicitly with `./bin/database migrate` in the intended installation.
Migration `Version20261020122131` introduces empty Workspace-owned tables; it does not silently create an identity.
An already authorized local installation operator then invokes:

```sh
./bin/console workspace:bootstrap --internal --confirm-bootstrap --operator="$OPERATOR_ID"
```

`OPERATOR_ID` is the trusted operator's canonical non-nil UUID attribution. Machine/process access is the existing
trusted Console boundary: these flags and the supplied attribution do not authenticate a browser User, grant a
Permission or enroll a Host/Runner. Never expose this command through an untrusted shell, Agent or HTTP caller.
The normal shared container loads the operation without running it at startup or during queries.

Success returns exactly `workspace_id` and `created_at`. Agent OS generates a typed COMB Workspace ID inside the
owning transaction. A unique `initial` installation slot and transaction fence choose one winner; retries,
including response-loss retries, return the same committed identity/time without another audit record. Current
state, bounded operator attribution, audit and original result commit together. Failures report uncertainty,
not proof of rollback: retry this same explicit bootstrap after restoring the required database capability.
Never delete the slot or generate a replacement identity to recover a failed query/startup. Preserve database
history/backups. There is one initial Workspace, not implemented multi-workspace membership or tenancy.

## Current access and permissions

Every API interaction freshly resolves its bearer authentication and package authority. Application operations
recheck authority independently of HTTP. The implemented initial-Workspace access path is the authoritative
managed Super Admin identity, checked against the installed stable Role ID and current membership, **plus the
interaction's separate Permission**. A matching Role name or operation Permission alone is insufficient.
Unknown Workspace, missing bootstrap, stale authentication and missing authority fail closed.

| Permission | Tier | Interaction |
|---|---|---|
| `READ_REPOSITORY_REGISTRATIONS` | `ADMIN_SAFE` | Get, list, conflict preview and original registration result disclosure |
| `REGISTER_REPOSITORIES` | `ADMIN_SAFE` | New registration; retry also needs current read permission |
| `GENERATE_REFERENCE_IDS` | `ADMIN_SAFE` | Exceptional non-reserving reference generation |

The managed catalog defines/references these three Permissions but adds **no** managed Role membership or
User/Agent assignment, including to Super Admin. Its original 22 Super Admin and three ordinary User memberships
are unchanged. An operator must obtain separately authorized grants through existing Role/Permission assignment
contracts; catalog reconciliation alone does not make these endpoints usable. Tests use explicit fixture grants,
not new installation defaults. See [managed policy](MANAGED_POLICY.md#permission-eligibility-is-not-assignment).
TASK-00498 owns later Workspace Admin and explicit User/Agent Repository access and corresponding read filtering;
there is no synthetic membership or access grant in registration. Agent authentication is not exposed here.

Protected mutations hold the existing permission/role-reference fences, per-user authentication fence and current
User/session rows before resolving authority. Those locks serialize actual Permission membership, Role assignment,
authentication and session writers with the operation. Registration then holds the initial-Workspace claim fence.
All use the exact shared EntityManager/DBAL connection and canonical `commitTransactional()` at READ COMMITTED.
Queries use a short transaction for coherent current disclosure checks; they persist no business state or events.

## Versioned API

All paths below are prefixed with `/api/v1/workspaces/{workspace_id}` using the exact bootstrapped ID. Responses
are explicit JSend, `snake_case`, `application/json`, no-store and safely correlated. No cookies or CORS authority
are added. Existing bearer transport checks retain their 16 KiB body bound. No Swagger/spec-serving route is added;
OpenAPI attributes remain authoritative and private exports require `./bin/openapi export` after source changes.

| Method/path | Input | Successful result |
|---|---|---|
| `POST /repositories` | JSON display name, slug, idempotency key and optional sources | `201` safe original registration |
| `GET /repositories/{repository_id}` | No body, Content-Type or query | `200` exact scoped registration; safe `404` when absent |
| `GET /repositories` | Optional `offset` 0–100000 and `limit` 1–100; defaults 0/25 | `200` items, `has_more`, offset and limit |
| `POST /repositories/conflict-preview` | JSON display name, slug and optional sources; no idempotency key | `200` advisory `slug`/`source` conflict kinds only |
| `POST /references` | No body, Content-Type or query | `200` COMB UUID `reference`, `reserved: false` |

A registration request has this shape; locators are invented examples, not observed remote authority:

```json
{
  "display_name": "Project Alpha",
  "slug": "project-alpha",
  "idempotency_key": "register-alpha-0001",
  "primary_source": {"type": "git_network_v1", "locator": "https://example.test/Team/Alpha.git"},
  "equivalent_sources": [
    {"type": "git_network_v1", "locator": "git@mirror.example.test:Team/Alpha"}
  ]
}
```

Missing or null primary source and an empty equivalent set are valid. Equivalents require a primary and explicitly
claim every member as the same intended collaboration; at most seven equivalents/eight total sources are accepted.
Duplicate canonical members are rejected. Unknown fields, including nested fields, are never accepted or echoed.
No caller-supplied aggregate ID, actor, timestamp, lifecycle or revision is accepted. Normal Repository IDs are
generated internally; metadata/source/lifecycle revisions start independently at one, with active lifecycle and
server creation/update authorship/time. COMB is neither secrecy nor authorization nor a user-visible sort order.

Display names contain 1–120 Unicode code points, at most 480 bytes, no control/format/line-separator characters or
leading/trailing whitespace. Slugs are exact lowercase ASCII, 1–80 bytes, alphanumeric segments separated by single
hyphens. Reject rather than rename. Reserved slugs are `admin`, `api`, `app`, `auth`, `new`, `null`, `repositories`,
`system`, `workspace`, `workspaces`. Pagination orders by `created_at, id`; offset pages are deterministic for a
committed state, not an immutable snapshot across requests or a promise against later insertions.

## Supported sources and limits

The input descriptor is a closed `{type, locator}` object. Supported types are `git_network_v1` and
`github_repository_v1`; canonical responses contain safe typed fields, never the unvalidated locator. The accepted
ADR grammar governs transport, DNS, path case, literal suffixes and distinct SSH absolute/home-relative modes.
Additional bounds are 2048 bytes per locator, 1024 bytes per path, 253 host bytes, 63 bytes per DNS label and 64
bytes per SSH username. Total authenticated input is still bounded to 16 KiB. No network/provider/Git call occurs.

Canonical GitHub HTTPS/SSH/scp inputs always use the most specific approved GitHub profile, even when submitted
as generic network descriptors; this prevents parallel generic claims bypassing uniqueness. Its explicit approved
profile joins transport aliases, lowercases owner/repository and removes exactly one terminal `.git`. Generic
network descriptors do not infer transport, suffix, path-case or SSH-mode equivalence. An explicit equivalent set
asserts the same collaboration, not a fork/upstream relationship. Those directional relationships are not modeled
by this slice and are never inferred, fetched or silently treated as equivalence.

`git_local_v1` is refused because enrolled Host/approved-root resolution is deferred. Provider-native IDs, credentials,
unknown providers/versions/fields and malformed locators are refused rather than inventing provenance or authority.
A GitHub locator is approved matching evidence, not proof of provider existence, ownership or immutable native ID.

## Atomicity, retries and safe failures

Partial unique indexes `uq_workspace_repository_active_slug` and `uq_workspace_repository_active_source` are final
claim authority; the latter stores versioned canonical bytes, not only a hash. Source rows have same-Workspace
Repository foreign keys. Under the Workspace fence, preflight improves errors but never reserves a claim.
State, all primary/equivalent claims, actor/time/revisions, owned audit, immutable original result and the committed
`RepositoryRegistered` fact commit or roll back together. Only recognized named constraint violations become safe
conflicts. The operation never independently flushes, opens another write connection or nests a business transaction.

The unique original-intent key is Workspace + verified actor type/ID + command kind + idempotency key. The stored
SHA-256 binds canonical metadata and the sorted explicit source set; equivalent input ordering is immaterial,
primary designation is not. Same intent returns the original result under current register **and** read authority;
changed intent with that key conflicts. Another actor cannot retrieve that result using the same key. A failed or
lost response does not prove rollback: retry the same key, never replace it to discover success.

`RepositoryRegistered` uses normal post-commit in-process dispatch. Its committed fact/audit/result survive lost
notification, but delivery itself is not durable or exactly-once. A dispatch failure can return `500` after a
successful commit; an authorized exact retry reads the original result without minting another ID or dispatching
another fact. There is no new generic outbox or external audit sink.

Transport shape errors are sanitized `400`; invalid metadata/descriptor/identity is `422`; absent/stale bearer
identity is `401`; current authority/scope denial is `403`; known claim/intent conflicts are generic `409` without
another registration's identity. Unknown driver/provider details remain sanitized `500`. Preview requires current
view authority and emits no event or business write; it guarantees neither later claim availability nor linkage.

Exceptional references use an independent current Permission and atomic actor budget: ten per UTC 60-second fixed
window, at most 10000 current actor buckets. Exhaustion returns safe `429` and `Retry-After` 1–60. Only bounded
limiter metadata is persisted; no generated reference is persisted/reserved, no aggregate is created and no future
aggregate ID is predicted. The operation accepts no kind, requested ID, timestamp or import authority. It cannot
bypass a separately controlled migration/import path.

## Verification and exclusions

Use `./bin/database test-reset` after a gate cleans the guarded test schema, then focused
`./bin/phpunit tests/Unit/Workspace tests/Integration/Postgres/RepositoryRegistrationTest.php`.
The tests exercise owned Domain/Application, real API/JSend/OpenAPI/redaction, PostgreSQL round trips, exact retries,
source/slug final constraints, atomic evidence rollback, and ten separate-connection claim/bootstrap/authority
interleavings. No migration/configuration/generated-text/tooling tests or vendor patches are introduced.
Run the full `./bin/build`; focused checks and this runbook are not independent acceptance.

Registration performs no Host/Checkout/Worktree persistence, directory or marker write, physical observation,
branch/remote mutation, source update, lifecycle change, designation, Planning import/context/cutover, Agent/Runner/
Workflow execution or Git publication. Markdown remains authoritative. Evidence and exact limits belong in
[TASK-00078](../../planning/tasks/00078-TASK.md); independent Review/QA and delivery remain separate.
