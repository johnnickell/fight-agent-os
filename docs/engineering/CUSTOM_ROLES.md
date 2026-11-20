# Custom Role definitions and Access Control adoption

TASK-00125 implements definition-only mutations, pending independent Review/QA. No Role UI, Permission delegation,
registration, managed-policy editing or User assignment endpoint is enabled by this change.

## API and Application authority

| Operation | Endpoint | Exact protected permission | JSON input | Success |
|---|---|---|---|---|
| Create | `POST /api/v1/roles` | `CREATE_ROLES` | `name` only | 201, generated `role_id` only |
| Rename | `PATCH /api/v1/roles/{roleId}` | `UPDATE_ROLES` | `name`, `expected_revision` | 200, current safe Role |
| Remove | `DELETE /api/v1/roles/{roleId}` | `DELETE_ROLES` | `expected_revision`, `confirm: true` | 200, null data |

Send a Bearer credential and a closed JSON object. Actor, membership, managed ownership and grant fields are never
caller inputs. Creation also rejects a caller-supplied `role_id`: send, for example, `{"name":"ROLE_CUSTOM"}`.
The HTTP gate authenticates and checks the exact operation permission. Each Action verifies the permission’s
protected installation assignment and dispatches the package command directly. Creation generates and supplies
`RoleId::generate()` before dispatch, then returns that same ID without a lookup. A caller with `READ_ROLES`
can read the current Role and its revision for rename/removal. Mutations do not themselves require catalog
permissions. The package bus is trusted internal infrastructure, not an authenticated entry point.

Names and identifiers use package value objects. Catalog detail/list and the rename response expose a required
`revision` alongside `role_id`, `name`, `managed`, never Permission membership or persistence internals. Carry the exact
displayed token to rename/removal. The package compares it and retains complete-state persistence fences through
commit. A stale same-name request also conflicts; a current same-name rename validates without writing, changing
timestamps/revision, auditing a mutation or publishing success. Role membership writers and managed reconciliation
advance real changes too. Retired incarnations remain reserved so a recreated ID cannot reuse old intent.

Managed, reserved, duplicate, missing, stale or referenced mutation targets return a safe 409. Invalid sources,
unknown fields, IDs, names, tokens or unconfirmed removal return sanitized 400. No operation silently retries a stale
revision. Refresh state, display it and obtain fresh confirmation. Every response is no-store JSend; current OpenAPI
is exported from the Actions/Responders using `./bin/openapi export`, then checked without rewriting.

The Access Control create, rename and remove handlers append required audit inside their mutation transaction.
The Role and audit repositories share the same PostgreSQL connection and generic DBAL transaction boundary.
An audit write failure rolls back the Role change; handlers publish success only after commit. New records use
`role.created`, `role.renamed` and `role.removed`, with typed actor and Role subject, before/after revisions and
message correlation. They contain no email, credential, Role name or serialized entity. Historical
`role.custom_*` rows remain in place with their original action and context; include both action families when
reading across the cutover. Only new typed records can be hydrated as package `AuditEvidence`. Publication failure or a lost response can occur after a durable commit;
a 500 is not proof of rollback. Reconcile current state under current authorization, never blindly replay. There is
no outbox or guaranteed replay. `CustomRoleService` refetches authority after every attempted network mutation,
including conflict/uncertainty, and preserves the caller's displayed token without retry. Its name-only create validates
the returned generated UUID and expects no other data. Rename reads current Role state after dispatch, not an
immutable record of the mutation.
A lost creation response may leave a Role whose ID the caller has not received; reconcile the authorized catalog instead
of retrying creation. The service is not mounted as new UI.

See the [Role mutation ownership diagram](custom-roles/README.md) for the protected entry, configured bus
and handler-owned transaction.

## v0.11.0 source cutover

TASK-00573 selects Access Control **v0.11.0** (`6258682512e2c8d76a3fa655dd633167bd8f55b6`) and Common
**v1.5.1** (`c73690164a9f112dbf14d774c56a7c01519b88aa`), with manifest constraints `0.11.*` and `1.5.*`.
This release of Access Control requires Common `^1.5.1`. See TASK-00573 for consumer verification status.

### Email-change grant upgrade

`Version20261119155505` discards legacy email-change grants, pending and terminal, on upgrade from the previous
Agent OS schema. Their original requester and old mailbox cannot be recovered from current User state. The data
owner approved discarding those grants only. Old links stop working; User rows, existing reservation claims,
current mailboxes, other credential grants and audit history are not changed. A retained reservation must not be
treated as confirmation authority. This release does not expose a confirmation route.

For an authorized installation upgrade, stop the old HTTP and worker writers, back up, and rehearse
`./bin/database migrate` on a private restored copy. This cutover does not override historical preflights on older
schemas. The migration runs in a transaction, refuses unexpected inbound foreign keys and an existing origin schema,
and makes both origin fields mandatory before commit. A database that already recorded this version does not rerun
it or delete new-format grants. After a committed cutover, there is no downgrade: recovery requires an authorized
restore or forward correction. Local Work verification does not authorize a live installation upgrade.

## Historical v0.8.0 adoption

TASK-00125's baseline selected Access Control **v0.8.0**, source/dist
`319738255e682d587642b67856039f882b9588d6`, and Common v1.3.0. Tagged availability alone is not adapter or deployment
qualification. The installed package provides caller Role revisions, immutable reset `RecoveryAuthority`, current
activation origin, shared User recovery validation and coarse failure notifications. The old v0.6.0 revision hold
remains historical in TASK-00125; qualification results belong to its current completion notes and handoff.

`Version20261021182244` was generated with Doctrine's owning tool. Adoption is an explicit maintenance operation:

1. Stop this installation's HTTP/worker writers; inventory and back up its database and retain the old application,
   lock and external delivery keys together. Test this migration on a private restored copy first. Neither Work's
   guarded test reset nor `./bin/build` migrates an existing development/production installation.
2. Inspect reset history privately: `SELECT count(*) FROM password_reset_grants`. **Any retained row blocks this
   migration**, including terminal rows: the issuing authentication version/email revision cannot be proven from a
   current User. Do not delete history, reset the database, infer values, or bypass the guard. Preserve old binaries
   and data; obtain an explicit installation-specific historical-authority reconciliation decision before proceeding.
3. With a compatible dataset and separate migration authority, install the committed lock and run
   `./bin/database migrate`. The all-or-nothing migration locks affected tables and aborts without partial adoption
   when history is incompatible. No downgrade discards the new persisted authority; recovery requires an authorized
   application/database restore or forward correction.
4. Verify Role IDs, names, ownership, membership and timestamps are preserved, with one newly stored random v4
   incarnation at sequence one per existing Role. Existing activation grants are explicitly classified as private:
   this consumer had no registration issuance path before adoption. Do not use this migration for a database
   modified by unsupported out-of-band registration. Restart only the matching new application/workers after checks.

Reset generations now persist both immutable issuing integers, including terminal history; hydration never consults
the latest User to manufacture authority. All User and reset writers share User-before-grant transaction fences on
one PostgreSQL connection at READ COMMITTED. Direct User authority changes atomically cancel outstanding reset
material; normal package operations retain their required atomic audit/session effects. Delivery validates current
eligible User authority in both claim and outcome transactions. A provider call already admitted outside a transaction
may arrive late, but cannot restore cancelled work or acknowledge a successor. Enable/restore does not reissue reset
authority. The shared reset worker and lifecycle handlers use the current constructor dependencies.

Registration remains unavailable. Required repository contracts are implemented for compatibility and fenced
registration-reference conformance, not registered as a public journey. Invitation mail accepts only stored private
invitation origin; unsupported registration-origin delivery fails closed rather than using the wrong template.

## Verification boundary

Use actual PostgreSQL for Role/recovery conformance, complete-state CAS, reopening, rollback and both writer orders.
The consumer binds the released Role and recovery conformance assertions unchanged; package in-memory evidence is
not substituted. Focused commands shorten iteration; `./bin/build` remains mandatory. Record failed attempts,
coverage gaps, warnings and any unexecuted physical-process/provider/production checks. Independent Review/QA and
installation deployment remain separate from local implementation evidence.
