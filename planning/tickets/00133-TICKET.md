---
id: TICKET-00133
epic: EPIC-00023
title: Adopt canonical transaction and audit contracts
status: ready-for-agent
---

# Adopt canonical transaction and audit contracts

## Problem statement

Transaction mechanics and required audit must be explicit, reusable responsibilities. Agent OS currently uses
`RoleMutationUnitOfWork` to infer Role audit from a transaction callback result. Replacing names or introducing a
DBAL unit of work alone does not remove that hidden business orchestration. Real package/persistence composition
must adopt canonical upstream contracts and preserve required mutation/audit atomicity.

## Solution and boundaries

- Inventory affected consumed Common/Access Control transaction, repository, handler and audit contracts against
  actual source, supported tags and local consumers. Distinguish planned, implemented, released and qualified APIs.
  Record exact source/release/lock identities when adopting; update Composer manifest/lock deliberately.
- Use the existing `TransactionalUnitOfWork` directly. Select the Doctrine implementation where ORM participates,
  or qualified DBAL-only support where appropriate. Do not replace all ORM use with DBAL, introduce a consumer
  alias or subtype, or create two transaction authorities on one connection.
- Keep transaction completion with owning handlers. Aggregate persistence, required audit and real consistency
  writes share the same owning connection/transaction. Repositories do not begin/commit transactions, flush as a
  separate completion boundary, intercept events to add audit, or hide coordination in a Store.
- Adopt explicit upstream handler-owned atomic audit for custom Role create/rename/remove when available. The
  supplier must support the required Role subject and safe actor/action evidence. Its separate audit planning owns
  API and optional-injection choices; required Agent OS audit cannot silently become a null/no-op binding.
- Qualify real Role mutation through package bus routing, PostgreSQL repositories, the generic transaction adapter,
  explicit audit persistence and post-commit publication. Audit failure rolls back the mutation; rejected and
  unchanged-name no-op operations do not record successful mutations. Preserve existing business concurrency
  and truthful uncertain/post-commit outcomes without asserting ordinary admission/revocation serialization.
- Remove `RoleMutationUnitOfWork`, superseded local audit interfaces and redundant persistence machinery after
  equivalent replacement is proven. Reuse/adapt needed PostgreSQL audit persistence rather than deleting a live
  mechanism for a deletion count. An outer nested transaction or post-commit subscriber is not equivalent.
- Reconcile shared composition, actual affected tests, runbooks and current docs in the same adoption slices.
  Related source owners use the qualified contracts directly, not provisional signatures or facade graphs.

Excludes implementing/releasing Common or Access Control, vendor patches, guessed future versions, generic retry
or outbox platforms, unsafe nested/savepoint workarounds, and blanket ORM replacement. Shared Application failure
vocabulary and safe HTTP migration belong to [TICKET-00136](00136-TICKET.md); ordinary identity/use-case reshaping
belongs to [TICKET-00135](00135-TICKET.md), except this ticket owns the complete Role audit replacement integration.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create, rename or remove a custom Role | Existing package custom Role commands | Existing authoritative reads, unchanged in purpose | Existing package Role facts after commit | Required mutation and safe audit commit together, or neither commits |
| Owning handler persists an aggregate using ORM or DBAL | Existing owned/package command | Existing lookups | Existing success/failure facts under their contract | One shared atomic persistence boundary with truthful failure outcomes |
| Maintainer adopts a canonical package contract | N/A; dependency/composition change | N/A | No new domain event | Reproducible manifest/lock, direct imports, qualified real integration |

This requirement adds no business permission, transport endpoint or new audit publication provider.

## Validation and permissions

Entry adapters retain their approved authentication and permission/scope admission. Transaction service presence
is not access authority; the bus is trusted internal composition. Preserve Domain invariants, required audit safety,
known conflict semantics and package sensitive-input restrictions. Never emit credential-bearing audit or expose
unknown driver exceptions as expected public failures. A database commit cannot undo external effects.

## Dependencies and sequencing

Use [TICKET-00131](00131-TICKET.md)'s reconciled rules. Common's DBAL implementation and Access Control's explicit
Role-audit contract are capability suppliers, not assumed releases. Reinspect their actual availability when each
slice is planned/executed; no sibling TASK ID belongs in local `blocked_by` metadata. Unavailable proof holds only
its dependent adoption. Existing generic-transaction corrections and unrelated source work can proceed independently.

[TICKET-00134](00134-TICKET.md) and [TICKET-00135](00135-TICKET.md) consume only contracts they actually need.
Each adoption slice, including available shared-error adoption under TICKET-00136, owns its necessary dependency
update and qualification; do not invent a package-upgrade-only prerequisite or force all changes into one minimum
version. Coordinate overlapping composition/manifest edits once during TASK decomposition.

## Acceptance and evidence

- A consumed-contract inventory identifies real callers, exact available APIs/source/lock evidence, and remaining
  supplier holds. A future plan, local tag alone or imported class name is not real consumer qualification.
- Actual ORM/DBAL participants use one generic transaction authority per operation and the correct shared connection;
  repositories are independent of transaction completion. Validate executable wiring/dependency rules with owning
  tools rather than product tests of configuration text.
- Real PostgreSQL Role mutations prove required audit atomicity, no-op/rejection behavior and retained business
  consistency. Success events follow commit; publication or lost acknowledgment does not masquerade as rollback.
- The decorator and redundant local contracts are removed after replacement. Needed persistence/hydration and safe
  evidence remain; no nested consumer transaction, repository interception or post-commit audit substitutes for
  atomic recording. Missing upstream audit support remains explicit incomplete work.
- Owned package composition/docs/callers agree with deliberate dependency changes. Preserve meaningful integration
  tests, run focused checks and the canonical gate with honest fresh/reused evidence, then independent Review and
  applicable concurrency/failure QA. Package acceptance does not substitute for this consumer verification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00572](../tasks/00572-TASK.md) | Adopt Common DBAL transaction boundary for existing persistence | in-progress |
| [TASK-00573](../tasks/00573-TASK.md) | Replace Role audit inference with explicit package-handler audit | needs-info |
<!-- /planning:children -->

## Decisions and progress

John approved this third requirement area under EPIC-00023 and the two complete integration TASKs, now written:

- [TASK-00572](../tasks/00572-TASK.md), order `9`: adopt Common's qualified DBAL transaction boundary for real
  existing persistence, including deliberate dependency selection, affected consumer fixtures, caller checks,
  obsolete ORM scaffolding removal where genuinely unused and real PostgreSQL integration evidence.
- [TASK-00573](../tasks/00573-TASK.md), order `10`: adopt explicit package-handler required Role audit, qualify
  actual protected entry/bus/persistence behavior, preserve needed evidence/history and remove RoleMutationUnitOfWork
  and superseded local contracts/tests only after replacement proof.

Both are `kind: chore`, have TASK-00564 as their sole local blocker and retain `needs-info` pending qualification.
Common v1.5.0 now supplies the DBAL boundary; TASK-00572 owns its deliberate adoption and actual PostgreSQL proof.
Access Control develop still lacks explicit Role audit, but existing upstream TASK-00122/00153/00167/00168 cover
its evidence, handlers and conformance/composition. TASK-00573 owns tagged consumer adoption, not another supplier
feature plan. Agent OS's required audit must be explicitly enabled and cannot become optional/no-op. Local tag and
plan inspection do not establish installed consumer behavior or independent acceptance.

Neither TASK depends on the other or all seven portfolio corrections. Role audit can use the existing qualified
Doctrine transaction implementation until DBAL adoption, and DBAL adoption may retain the existing required-audit
decorator pending its separate replacement. Both consume the same generic TransactionalUnitOfWork; actual release
coupling and overlapping manifest/composition edits must be coordinated from evidence. Each slice owns its exact
dependency change and complete consumer qualification, without a package-upgrade-only prerequisite or a generic
imitation layer. Apply TICKET-00135's first-release-adoption rule to actual AC coupling: update every affected live
caller with the breaking release, while keeping unrelated cleanup with its existing owner. Source/capability owners
retain broader use-case and safe-error corrections.

TICKET-00133's TASK decomposition is complete; the supplier holds are not cleared by requirements acceptance.
No source refactor, dependency update, upstream mutation, runtime qualification, implementation verification,
Review, QA or publication starts from these records. The remaining TICKET decomposition and next target are owned
by [EPIC-00023's current handoff](../epics/00023-EPIC.md#progress).

Planning-only verification passes: `./bin/planning-check --write` and `./bin/planning-check` report 732 records,
534 active and current generated views; tracked/new-record whitespace checks pass. Both TASKs appear under Needs
Info with their actual local blocker retained. No product build was run, and no supplier or consumer integration
is qualified by these planning checks.
