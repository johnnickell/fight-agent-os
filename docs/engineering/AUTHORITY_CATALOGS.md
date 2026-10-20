# Guarded authority catalogs

TASK-00124 adds four read-only, JWT-by-default API Actions:

| GET path | Exact current protected permission | Safe record fields |
|---|---|---|
| `/api/v1/roles` | `READ_ROLES` | `role_id`, `name`, `managed` |
| `/api/v1/roles/{roleId}` | `READ_ROLES` | Same role fields |
| `/api/v1/permissions` | `READ_PERMISSIONS` | `permission_id`, `name`, `tier`, `managed` |
| `/api/v1/permissions/{permissionId}` | `READ_PERMISSIONS` | Same permission fields |

The matched-Action gate authenticates and checks exact current permission names before Action construction.
`ReadAuthorityCatalogs` independently resolves invocation-bound authentication and all current principal
references, then requires the managed `SUPER_ADMIN_ONLY` installation permission and its current assignment
before package query dispatch. Role names or JWT role claims alone confer no authority. All real callers,
including future non-HTTP consumers, must use that Application boundary; the raw package bus remains trusted
in-process infrastructure, not an authenticated forwarding API.

Lists accept only `page` (1–1000, default 1) and `per_page` (1–100, default 25), as validated query strings.
Ordering is fixed to `created_at ASC, id ASC`; caller-selected ordering/filtering is unavailable.
Application also bounds numeric pagination for non-HTTP callers. Package `ListRoles`/`ListPermissions` return
safe Views through the configured query bus; get operations use package `GetRoleById`/`GetPermissionById`.
IDs must parse as package UUID identifiers. Invalid inputs fail safely without catalog dispatch.

Successful JSend `data` is the record above or a page with exactly `page`, `per_page`, `total_pages`,
`total_records`, `records`. An empty catalog reports one total page; an in-bounds page beyond the end is empty.
Pages are deterministic for a committed state, not snapshots across requests or concurrent writes.
Authorization is freshly checked before dispatch, not held as a lock throughout response serialization.
Revocations after the final authorization check are not retroactive cancellation of an in-flight read.

No role permission IDs, user/agent memberships, grants, timestamps, revisions, sessions, credentials or raw
entities are serialized. `managed` truthfully describes version-controlled policy ownership: managed roles
are not custom-role mutation targets. Custom-role presentation does not promise an available mutation API;
permission tiers and managed markers never confer CRUD authority. This TASK adds no mutation routes or UI.

Responses use `application/json`, JSend and `Cache-Control: no-store`, with safe correlation headers.
Anonymous/stale authentication is refused, forbidden callers cannot probe target existence, and only an
authorized absent target becomes safe 404. Unavailable permission declarations fail closed as configuration
errors through the existing API gate. OpenAPI attributes on the real Actions and responder own the private
export; no public specification or policy-dump route is added.

`client/src/features/authority/CatalogService.ts` exposes typed role/permission page and get reads via
`ApiClient`, requiring the current memory-only Bearer credential each time. It maps only exact safe fields,
checks returned get identity, tiers and page metadata, freezes results, and retains no catalog cache or
authority. The shared GET transport supports only a closed numeric pagination option, never raw query URLs.
Future protected outlets must own result lifetime and honor their authority scope fences; this service does
not mount a UI, restore authentication or retry failed reads.

Focused checks (prepared, exclusively owned runtime):

```sh
./bin/database test-reset
./bin/phpunit tests/Unit/Authorization/ReadAuthorityCatalogsTest.php
./bin/phpunit tests/Integration/Postgres/AuthorityCatalogApiTest.php
./bin/client test
./bin/openapi export
./bin/openapi check
./bin/planning-check
./bin/build
```

The full gate cleans its guarded test schema; reset it before later focused PostgreSQL checks. Do not run
resets/tests concurrently against the same test database. Review and QA remain independent of these checks.
