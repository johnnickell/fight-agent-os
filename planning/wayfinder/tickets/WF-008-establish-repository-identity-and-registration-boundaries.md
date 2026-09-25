# Establish repository identity and registration boundaries

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** —

## Question

What identities and boundaries let Agent OS register one logical repository once while safely recognizing its clones, Git worktrees, remotes, workspaces, execution hosts, and runner checkouts?

## Must decide

- Distinct meanings and stable identifiers for installation/workspace, registered repository, source-control remote or fork, checkout, worktree, and execution host.
- Which evidence may match a checkout to a registered repository, how ambiguity and changed remotes are handled, and which identity survives moving or recloning a checkout.
- Whether repository identity is minted by Agent OS, derived from source control, or composed from both; include repositories with no remote and multiple remotes.
- Registration, re-linking, de-registration, and checkout-mapping journeys, including required human confirmation and repository-isolation checks.
- Minimum registered metadata and configuration ownership without yet choosing every policy field.
- Behavior for unregistered repositories and the explicit rule that registration alone neither migrates planning nor grants commit, push, PR, runner, or agent authority.
- Relationship to the accepted one-private-installation foundation in EPIC-00004 without silently claiming multi-tenant isolation.

## Resolution boundary

This decision establishes identity and registration semantics used as foreign-key and authorization boundaries. It may recommend a small disposable identity/matching prototype if collision or worktree behavior cannot be settled from Git facts. It must not register a repository, define the complete policy-precedence contract owned by WF-009, create database schemas, or implement discovery.

## Preferences required

John must choose the product meaning of workspace and the acceptable confirmation burden when Agent OS cannot prove that two checkouts represent the same logical repository. The recommendation should favor an Agent-OS-minted repository identity with explicit checkout links, treating remotes and Git common-directory facts as matching evidence rather than universal identity.

## Resolution

### Context and language

Use **Workspace**, **Planning**, and **Execution** as distinct Agent OS bounded contexts. Fight Access Control remains its imported bounded context. **Dashboard** is the preferred informal name for the browser surface that composes context projections; it is not another bounded context.

The first private installation has one explicit, durable Workspace that owns multiple registered repositories. Workspace is the future home for multi-workspace membership and isolation, but this decision does not claim those capabilities now. A later workspace may register the same source independently and own separate planning and execution history.

Workspace owns registered repository identity, source descriptors, and checkout/worktree links. Planning and Execution refer to its stable repository identity rather than duplicating its aggregate. Execution-host and runner enrollment details remain for WF-016.

### Identity and registration

`RepositoryId` is the sole system identity for a registered repository. It extends Fight Common's `UniqueId`, whose generation path uses `Uuid::comb()`. Agent-OS-owned host, checkout, and worktree records likewise receive application-generated COMB-backed identities; paths, slugs, names, and remotes are locators or evidence, never identity.

One registered repository represents one intended collaboration and publication target. It includes an owning `WorkspaceId`, mutable display name, mutable workspace-unique slug, active/archive lifecycle, optional primary source, equivalent source locators, and creation/update authorship and timestamps. Checkout and worktree links are separate records. Complete engineering and workflow configuration belongs to WF-009.

Equivalent remote names, mirrors, and normalized SSH/HTTPS/provider locators may describe the same source target. Provider-native immutable IDs strengthen matching when available but do not replace `RepositoryId`. Forks and upstream repositories are explicit relationships and never inherit identity or authority automatically. Within one workspace, one approved source identity has one active registration; another workspace may register it independently.

### Physical Git locations and matching

An execution host is a registered machine boundary. A checkout is one clone/object store identified locally by its Git common directory. A worktree is one concrete working tree attached to that checkout. Agent OS preserves distinct host, checkout, and worktree records so related worktrees are not mistaken for independent clones.

Resolution is tiered and conservative:

1. An existing checkout link resolves automatically.
2. Worktrees sharing its Git common directory resolve beneath that checkout.
3. A unique normalized primary/equivalent source match proposes a repository, but a human confirms the first durable checkout link.
4. Fork, upstream, conflicting, multiple, missing, or changed-source evidence requires explicit selection or re-linking.
5. Directory names and slugs never create links.

After explicit confirmation, Agent OS writes the generated `CheckoutId` to repository-local, uncommitted Git configuration under an Agent-OS-owned namespace. The key must not collide with Git-reserved configuration, and an existing value must be inspected before writing. Normal clones do not inherit the marker; linked worktrees share the checkout marker; directory moves preserve it. A copied or conflicting marker fails closed for explicit reconciliation.

### Lifecycle and authority

Registration, source changes, checkout linking, and re-linking are explicit, audited operations. Source and checkout availability is derived separately from repository lifecycle. Removal archives the repository, disables new Agent OS planning and execution, and preserves identity, links, planning, and execution history. Restore is explicit; permanent deletion is not a normal journey.

Unregistered repositories continue using local instructions and conventions. Registration alone does not migrate planning, override instructions, create or change branches, grant runner or workflow authority, authorize commits/pushes/PRs, or start agents.

Normal create operations generate their typed IDs internally and do not accept caller-selected identities. Plan one shared application operation that can return a valid, non-reserved COMB UUID for exceptional seeding or manual-reference needs; HTTP and a future MCP tool may expose the same operation. Controlled migration/import operations may accept preserved IDs only through explicit validation paths.

The newly exposed onboarding concern is tracked by [WF-020 — Define developer onboarding and operator guidance](WF-020-define-developer-onboarding-and-operator-guidance.md). No EPIC, implementation record, schema, endpoint, registration, or Git configuration change is authorized by this resolution.
