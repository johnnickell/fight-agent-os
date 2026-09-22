# Establish repository identity and registration boundaries

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
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

Write this only when the decision is closed. Link any newly justified child map or future EPIC-planning handoff; do not create implementation records from this decision.
