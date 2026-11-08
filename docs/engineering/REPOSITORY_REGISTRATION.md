# Workspace bootstrap and Project registration

TASK-00575 cuts creation, inspection and conflict preview over together to Project-owned CQRS. This runbook
keeps its existing documentation URL; no legacy HTTP route, PHP facade or replay endpoint remains. Independent
Review/QA are separate from implementation. Follow [the architecture contract](STANDARDS.md#architecture)
and [ADR 0005's amendments](../../planning/adr/0005-repository-identity-and-link-consistency.md).
The maintained [Project creation ownership view](project-registration/README.md) provides revision-linked source
and arrow traces; it is not independent acceptance.

## Explicit installation setup

Apply migrations explicitly with `./bin/database migrate`, then an authorized local installation operator runs:

```sh
./bin/console workspace:bootstrap --internal --confirm-bootstrap --operator="$OPERATOR_ID"
```

`OPERATOR_ID` is a canonical non-nil UUID for trusted attribution, not browser authentication. Machine/process
access is the Console trust boundary; never expose this command to an untrusted shell or Agent. Console uses
registered Workspace queries and an ID-bearing void bootstrap command. One generic handler transaction adds
Workspace state and required audit on the shared connection. Repeated explicit invocation reads current state;
competing absent reads may conflict. Unknown execution/presentation failure is uncertainty, not proof of rollback.
Queries and application startup never bootstrap identities, grant access or enroll a Host.

Apply generated `Version20261108052913` **before deploying the Project code**. Back up normally. The migration:

- Renames `repository_registrations` to `projects`, `repository_sources` to `project_sources`, and their related
  `repository_id` columns to `project_id`; identities, source bytes, revisions, authorship and timestamps remain.
  Existing same-Workspace relationships and active slug/source/primary uniqueness remain authoritative.
- Keeps every `workspace_audit` identity, attribution, action, revision, time and Project reference. Historical
  `repository_registered` actions retain their original meaning; new audit records use `project_registered`.
- Converts `workspace_operations` to historical-only `workspace_history`: record/event identities, attribution,
  correlation with audit IDs and times survive. Initial display name, slug and canonical primary/equivalent source
  facts are preserved separately from current Project facts, including when they differ. Only obsolete ordinary
  replay keys, digests and original HTTP-response snapshots are removed. No live path reads or writes replay data.
- Renames the two installation-owned permission identities in place to `READ_PROJECTS` and `REGISTER_PROJECTS`.
  Existing role/user relationships continue referring to the same IDs. No new membership or access is assigned.
  A conflicting unexpected permission name fails migration transactionally; resolve the actual data conflict,
  never dispose grants or overwrite identities to make adoption pass.

The migration is irreversible: replay responses cannot be reconstructed honestly. Older applied migrations are
unchanged. Current source provides no downgrade/dual mode. The task's populated, disposable PostgreSQL proof
compares current state, distinct historical facts, source claims, audit correlation and a custom-role grant.
Main data was inspected read-only, not migrated or reset by this Work stage.

## Admission and authority

Every request receives fresh bearer authentication and central `ApiActionSecurity` operation permission
admission. The HTTP adapter additionally checks the exact explicitly bootstrapped initial Workspace and
managed Super Admin identity/membership. A matching Role name or operation permission alone is insufficient.
Unknown Workspace, missing bootstrap, stale authentication and missing authority fail closed. No second
Application gate or permission-revocation lock exists. An already admitted ordinary request may finish after
revocation; the next request checks fresh authority and refuses. Business claim uniqueness is not weakened.

| Permission | Tier | Interaction |
|---|---|---|
| `READ_PROJECTS` | `ADMIN_SAFE` | Detail, bounded list and advisory conflict preview |
| `REGISTER_PROJECTS` | `ADMIN_SAFE` | Ordinary creation, independently of read permission |
| `GENERATE_REFERENCE_IDS` | `ADMIN_SAFE` | Exceptional non-reserving technical reference generation |

The catalog defines/references these permissions but assigns none to managed Roles, including Super Admin.
Obtain separately authorized grants through existing assignment contracts; reconciliation alone enables no
endpoint. TASK-00498 still owns future Workspace Admin and explicit User/Agent Project access/filtering.
Registration grants no Project access, checkout, Planning, Agent, Runner or publication authority.

## Versioned HTTP API

Prefix each path with `/api/v1/workspaces/{workspace_id}` using the exact bootstrapped ID. JSend responses are
JSON, snake_case, no-store and safely correlated. Existing bearer input has a 16 KiB body bound. No client
field asserts the aggregate identity, actor, timestamp, lifecycle, revision or authority. There is no product UI.
Private Swagger consumes the attribute-owned contract: explicitly run `./bin/openapi export` after changes.

| Method/path | Input | Success |
|---|---|---|
| `POST /projects` | JSON display name, slug and optional sources; no idempotency key | `201` with only `project_id` |
| `GET /projects/{project_id}` | No body, Content-Type or query | `200` current safe Project facts; intentional `404` absence |
| `GET /projects` | Optional offset 0–100000, limit 1–100; defaults 0/25 | `200` items, has_more, offset, limit |
| `POST /projects/conflict-preview` | Same metadata/source shape as creation | `200` advisory `slug`/`source` kinds only |
| `POST /references` | No body, Content-Type or query | `200` COMB UUID reference, reserved: false |

```json
{
  "display_name": "Project Alpha",
  "slug": "project-alpha",
  "primary_source": {"type": "git_network_v1", "locator": "https://example.test/Team/Alpha.git"},
  "equivalent_sources": [{"type": "git_network_v1", "locator": "git@mirror.example.test:Team/Alpha"}]
}
```

The adapter generates `ProjectId` with its owning factory **before** void `RegisterProject` bus dispatch and
returns that known ID on success, without a mutable command result or post-command identity fetch. Detail/list
queries return typed `ProjectView`/`ProjectPage`; HTTP Data/Responders own the complete transport representation.
Pages order by `created_at, id`: deterministic for a committed state, not a cross-request immutable snapshot.
Reads never mutate business state, append success audit/events or implicitly bootstrap a Workspace.

Display names use 1–120 Unicode code points, at most 480 bytes, with no control/format/line separators or boundary
whitespace. Slugs remain exact lowercase ASCII, 1–80 bytes, single-hyphen-separated alphanumeric segments. Reserved
slugs remain `admin`, `api`, `app`, `auth`, `new`, `null`, `repositories`, `system`, `workspace`, `workspaces`.
Unknown top-level/nested fields and duplicate JSON keys are refused, not echoed.

## Source facts, claims and recovery

Missing/null primary and empty equivalents are valid. Equivalents require a primary; at most seven equivalents
and eight total members are accepted. Duplicate canonical claims are rejected. Supported closed `{type, locator}`
inputs are `git_network_v1` and `github_repository_v1`; credential-bearing, local and provider-native authority
are refused. Locator/path/host/label/SSH-user byte bounds remain 2048/1024/253/63/64. No network, provider or Git
operation occurs. Canonical responses contain validated safe fields, never the original submitted locator.

GitHub HTTPS/SSH/scp aliases always use the most specific supported GitHub profile, even under generic input:
owner/repository are lowercase and exactly one terminal `.git` is removed. Generic profiles retain transport,
path case, suffix and SSH absolute/home-relative distinctions. Explicit equivalence claims the same intended
collaboration; forks/upstreams remain distinct and are not inferred or fetched. Metadata proves neither remote
existence/ownership nor a linked/available physical checkout. Host, Checkout and Worktree stay separate identities.

`RegisterProjectHandler` owns one generic transaction: add the Project and all source claims, then explicitly
record required audit, all through the same shared connection. Repositories never commit or flush. Authoritative
partial unique indexes `uq_workspace_project_active_slug` and `uq_workspace_project_active_source` arbitrate
competing creation; only those recognized constraint violations become safe conflicts. Unknown identity/driver/
commit failures remain internal failures. Advisory preview reserves nothing and reveals no other Project ID.

`ProjectRegistered` dispatch occurs only **after commit**, in process. Dispatch failure may return `500` after
successful creation; it cannot roll back committed state and provides no durable/exactly-once notification promise.
After response loss, do not blindly retry expecting replay: a new create may safely return `409`. A fresh,
independently authorized list/detail read reveals authoritative Project state and its ID. Creation-only permission
does not grant recovery reads. No idempotency input, immutable original response, result journal or replay exists.

Transport shape errors are sanitized `400`; Domain metadata/source/identity refusal is `422`; missing/stale bearer
is `401`; authority/scope denial is `403`; required absence is `404`; recognized claim conflict is generic `409`.
Unknown infrastructure faults are sanitized `500`. The existing Common safe error owner performs redaction and
reporting; no second classifier/framework is introduced. Common v1.4.1 and Access Control v0.8.0 remain locked.

Exceptional references retain their separate atomic technical limiter: ten per UTC 60-second window, at most
10000 current actor buckets. An existing actor can use remaining budget at capacity; new buckets are refused.
`429` includes Retry-After 1–60. Only actor/window/count metadata persists; UUIDs are not reserved or recorded.

## Verification and exclusions

Use `./bin/database test-reset` only for the guarded dedicated test database, then
`./bin/phpunit tests/Unit/Workspace tests/Integration/Postgres/ProjectRegistrationTest.php`. Direct migration proof
uses Doctrine/PostgreSQL rather than product tests of schema/tooling. Use `./bin/architecture`, generated-contract
checks and normal `./bin/build`. Passing tests are not independent Review/QA or deployment acceptance.

The cutover implements no Host enrollment, checkout linking/relinking, filesystem/marker work, lifecycle/source/
access writer, generation, import, Planning cutover, Agent/Runner/Workflow execution or Git publication. Required
physical/multi-resource reconciliation remains with its separate owners. Markdown remains authoritative.
