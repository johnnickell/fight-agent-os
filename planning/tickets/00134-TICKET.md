---
id: TICKET-00134
epic: EPIC-00023
title: Register and inspect Projects through aggregate-owned CQRS
status: ready-for-agent
---

# Register and inspect Projects through aggregate-owned CQRS

## Problem statement

Current registration has handler-shaped services, a mixed RegistrationStore and an accidental registration-shaped
model. Its names and original-result machinery obscure the actual Project being managed. Correct the existing
registered collaboration capability end to end, so maintainers and users encounter one Project model and clear
creation/read behavior under [EPIC-00023](../epics/00023-EPIC.md).

## Solution and boundaries

- Model Project as the current intended collaboration/publication target within a Workspace, with stable ProjectId,
  metadata, optional primary Git source and explicit equivalent sources. Retain existing source normalization,
  fork distinctions and uniqueness meaning; no multi-repository umbrella is introduced.
- Provide a coherent Domain aggregate, ID, value objects, collection-like ProjectRepository and owned messages.
  Use RegisterProject, a registered RegisterProjectHandler, and ProjectRegistered. The aggregate accepts the ID
  supplied by the command; the entry adapter generates it before void bus execution.
- Keep aggregate persistence separate from transaction completion, operation orchestration and presentation.
  ProjectRepository manages Project roots, not registration payloads, original HTTP responses, Workspace bootstrap
  or unrelated reference admission. Keep explicit initial Workspace bootstrap and existing reference-generation
  behavior in correctly named owned capabilities rather than hiding them inside ProjectRepository.
- Preserve required state/source/audit atomicity and authoritative database uniqueness through a generic handler-
  owned transaction, with success-event publication after commit. Separate required audit from discarded ordinary
  creation-response bookkeeping. Unknown infrastructure failure must not be relabeled as a known uniqueness conflict.
- Remove ordinary-create immutable response snapshots, original-result journals and replay-only code/schema/tests
  where they have no independent required purpose. A duplicate attempt may conflict; fresh list/detail reads show
  the committed Project and ID after response loss. Do not add a mutable command result or generic operation engine.
- Use registered GetProjectById/ListProjects-style reads and safe typed ProjectView results containing relevant
  Git-source facts. The repository returns its declared aggregates; query handlers produce safe Views. Do not expose
  raw entities/rows or turn an Application View into a snake_case HTTP array. Required absence maps intentionally;
  source metadata does not imply a linked or available checkout.
- Reconcile the primary projects table and related storage, identifiers, routes, permission names, API Data/Responders,
  existing Console entry points, generated contracts and owned callers together. Entry adapters perform current
  authentication and permission/scope admission and dispatch through buses. Remove Application admission wrappers
  and locks used solely for ordinary permission-revocation serialization; retain business uniqueness/consistency.
- Establish whether real durable data needs preservation/migration or authorized disposal. Breaking application
  contracts are acceptable, but identity/history loss is not implied. Remove obsolete paths instead of aliases,
  dual endpoints or application deprecation machinery added solely for compatibility.

Excludes new checkout linking/relinking, lifecycle, source-edit, access-management or project-generation features
already owned by [TICKET-00024](00024-TICKET.md), related records and [EPIC-00009](../epics/00009-EPIC.md). Correct
existing behavior and its contracts; [TICKET-00132](00132-TICKET.md) updates future plans without implementing them.
Host, Checkout and Worktree remain distinct identities. Genuine physical/multi-resource recovery is not removed.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Register a Project | RegisterProject carrying the adapter-generated ProjectId | Existing advisory conflict reads where useful | ProjectRegistered after commit | One Project, source claims and required audit; known ID returned after successful void execution |
| Refresh after a lost creation response | A new creation may conflict; no transparent replay command | ListProjects / GetProjectById | None from reads | Authoritative Project and ID visible without original-response recovery |
| Inspect Projects and source facts | N/A; read-only | GetProjectById, ListProjects, existing conflict preview | None | Safe typed results, bounded deterministic pagination and no creation/physical authority |
| Bootstrap initial Workspace or generate an exceptional reference | Correctly owned existing intents, not ProjectRepository methods | Existing explicit scope reads | Existing justified facts only | Preserve bounded existing outcomes without implicit bootstrap or registration |

## Validation and permissions

Map trusted actor/scope at each supported entry, perform its current operation access checks and validate transport
input before dispatch. Domain values own metadata/source rules. Preserve workspace scope and independently
justified permissions; registration grants no Project access, checkout, Planning, Agent, Runner or Git publication
authority. No untrusted request field can assert the actor or bypass admission. Ordinary admitted requests may
finish after revocation. Read handlers do not mutate aggregates, bootstrap identities or publish business events.

## Dependencies and sequencing

Use [TICKET-00131](00131-TICKET.md)'s rules and only necessary canonical contracts qualified under
[TICKET-00133](00133-TICKET.md). Do not block Project on unrelated Role-audit or DBAL supplier work when the current
qualified generic transaction contract suffices. Coordinate shared failure mapping with
[TICKET-00136](00136-TICKET.md), and Project terminology in other plans with TICKET-00132. Each implementation
slice owns callers, Views, persistence, tests/docs and its actual prerequisite edges; this TICKET is not one PR.

## Acceptance and evidence

- The existing register/inspect capability has a cohesive Project root, ID, repository, owned messages/handlers,
  typed Views and thin bus-dispatching entries. No accidental Registration aggregate or mixed Store survives.
- Real PostgreSQL and entry-path evidence proves creation, safe conflicts, required state/source/audit rollback,
  deterministic safe reads and post-commit event timing. Source normalization/fork distinction and business
  uniqueness remain meaningful; permission-race serialization and original-response replay are no longer promised.
- Response-loss reconciliation works through fresh authoritative reads. Creation IDs are present before dispatch,
  commands stay void, and HTTP serialization lives in Data/Responders rather than Application result arrays.
- Bootstrap/reference behavior remains explicit and correctly owned. Existing grants and physical/execution
  separation remain intact; no new product journey is claimed from renaming or refactoring.
- Schema/API/permission changes, data disposition, current docs and owned consumers agree. No needed data/history
  is silently dropped and no obsolete compatibility path is retained solely to protect the rejected design.
- Preserve or replace meaningful behavioral tests, run focused owning checks and the canonical gate, report
  fresh/reused evidence and warnings, and obtain independent Review/applicable QA. Use tooling directly for schema,
  wiring/dependency and generated-contract validation rather than product tests of architecture/configuration text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00574](../tasks/00574-TASK.md) | Give Workspace bootstrap and reference generation explicit owners | ready-for-agent |
| [TASK-00575](../tasks/00575-TASK.md) | Cut registration and inspection over to Project-owned CQRS | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this fourth requirement area, including Project terminology, ID-bearing void creation, ordinary
unique-conflict/fresh-query reconciliation and admission-time access checks, then approved two complete cutovers:

- [TASK-00574](../tasks/00574-TASK.md), order `11`, blocked by TASK-00564: give initial Workspace bootstrap a coherent
  aggregate/repository, registered ID-bearing void command and typed current-state reads; give the existing
  exceptional-reference endpoint adapter-owned scope/permission admission, bounded technical rate limiting and
  the owning UUID factory. Reference generation creates no aggregate, reservation, import authority or fake business
  command. Migrate existing callers and separate needed audit from original-response bookkeeping.
- [TASK-00575](../tasks/00575-TASK.md), order `12`, blocked by TASK-00574: cut Project creation, detail/list/preview,
  persistence, actual entry admission, safe typed Views/HTTP presentation, schema/API/permissions, consumers and
  removal over together. Keep required audit/business consistency and needed data/history, remove ordinary replay
  and solely permission-race serialization, and delete the remaining mixed Store/registration-shaped model.

Both are `kind: chore` and `ready-for-agent` when their local dependencies permit. Neither waits for TASK-00572/
00573 or the whole portfolio review: the existing qualified generic transaction boundary suffices. Any required
available Common failure contract is qualified in the actual Project path with working mappings through the
existing error owner, coordinated with TICKET-00136 without a competing framework or whole-migration prerequisite.

Each TASK owns its affected meaningful behavior tests, current docs, direct schema/generated-contract/composition
checks, normal canonical gate with truthful fresh/reused evidence, independent Review and applicable QA. Needed
identities/source facts/audit/access meaning are preserved through explicit migrations; disposal is not implicitly
authorized. Registration and reads move together to avoid temporary Store adapters, aliases or dual endpoints.

TICKET-00134's TASK decomposition is complete. This remains correction of current capability, not a duplicate
backlog or implementation of unfinished TICKET-00024/new-project/physical-link features. No source, schema/data,
grant, dependency, implementation verification, Review, QA or upstream change begins from these planning records.
Remaining TICKET decomposition and the next target are owned by
[EPIC-00023's current handoff](../epics/00023-EPIC.md#progress).

Planning-only verification passes: `./bin/planning-check --write` and `./bin/planning-check` report 734 records,
536 active and current generated views; tracked/new-record whitespace checks pass. Both TASKs appear as waiting
on their actual local blockers. No product build was run; these checks do not prove implementation, data migration
or runtime behavior, and no source/data/permission changes were made.
