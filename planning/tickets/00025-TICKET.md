---
id: TICKET-00025
epic: EPIC-00005
title: Resolve versioned repository context
status: ready-for-agent
---

# Resolve versioned repository context

## Problem statement

Every registered repository session needs one explainable immutable context without silently overriding application
policy, registered configuration, or Pi-native local instructions. Live changes, service loss, and ambiguous
conflicts must never refresh an active context invisibly or expand stale authority.

## Solution and boundaries

Deliver a single application query that resolves a lean versioned `RepositoryContextSnapshot` from TICKET-00024
identity and designated-checkout evidence, revisioned repository configuration, application-enforced restrictions,
and recognized Pi context files. Retain the complete snapshot outside model context; expose a bounded projection
and provenance suitable for the Dashboard and later Harness sessions without duplicating whole instruction files.

Support explicit unregistered/local, registered/Markdown-authoritative, and registered/database-authoritative modes.
Provide semantic revision commands, conflict preview and resolution, append-only rollback, stale-session detection,
and fail-closed availability behavior. This requirement recognizes instruction files but does not edit them.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Revise repository configuration | `ReviseRepositoryConfiguration` | Preview current revision, validation, affected context fields, and active-session impact | `RepositoryConfigurationRevised` | A validated immutable revision records actor, reason, values, and provenance without rewriting local files |
| Roll configuration back | `RestoreRepositoryConfigurationRevision` | Compare current and selected prior revisions | `RepositoryConfigurationRevisionRestored` | A new revision copies approved prior values; historical revisions remain unchanged |
| Resolve repository context | N/A — read-only resolution query | `ResolveRepositoryContext` | N/A — resolution itself is not authority mutation | One typed snapshot binds Workspace, Repository, Host, Checkout, Worktree, planning mode, branch/base guidance, named checks, restrictions, instruction paths/digests, conflicts, revision, and availability |
| Resolve a recognized conflict for one session | `RecordSessionContextChoice` where persistence is required | Preview each structured source and recommended narrowing choice | `SessionContextChoiceRecorded` | The chosen snapshot preserves or narrows authority for that session only |
| Save a conflict choice for future sessions | `ReviseRepositoryConfiguration` | Revalidate the choice against current configuration and instruction digests | `RepositoryConfigurationRevised` | An authorized configuration manager creates a future revision; no instruction file is changed |
| Detect context drift | N/A — comparison/projection operation | Compare bound snapshot with current configuration revision and effective instruction digests | N/A — drift is derived | Existing sessions become visibly stale and new authority-bearing operations stop without rewriting session history |
| Continue during service loss | N/A — explicit local-mode choice | Query current service and cached snapshot availability | N/A — no authority event | New registered authority fails closed; an explicit unregistered/local session may proceed without Agent OS Planning authority |

## Validation and permissions

Use a closed versioned schema with optional typed sections rather than arbitrary JSON. Validate repository binding,
planning mode, configured base and branch patterns, ordered named verification commands, recognized instruction
locations/digests, and known restrictions. Do not parse arbitrary prose into enforceable policy or claim perfect
conflict detection.

Application safety and live authorization are non-overridable. Registered configuration may narrow but not widen
those controls. Local instructions and Skills may guide more strictly but cannot grant application Permissions.
Recognized ambiguity in planning mode, branch/base guidance, or required verification pauses interactive creation
or fails non-interactive creation with actionable diagnostics.

Viewing resolved context, updating repository configuration, saving conflict choices, and rollback require separate
server-side Permissions. Session-local choices may preserve or narrow the initiating actor's existing authority;
they never widen it. Secrets, credential locators, internal service URLs, complete arbitrary plugin data, and
unnecessary identifiers do not enter prompt projections.

## Acceptance and evidence

- Context resolution returns one deterministic typed snapshot and bounded projection for the same registered facts.
- Resolution records configuration revision, instruction paths/digests and provenance without copying complete
  instruction contents into the projection.
- Application restrictions, registered values, local guidance, and recognized conflicts compose with the accepted
  precedence; contradictory authority fails closed.
- A configuration or effective-instruction change preserves existing snapshots, marks affected sessions stale,
  and prevents new controlled operations until explicit continuation.
- Rollback appends a new attributable revision and never deletes or rewrites history.
- Unavailable-service and offline cases cannot inherit or expand registered authority from cache.
- Focused behavior tests cover schema validation, precedence, conflict, revision concurrency, rollback and drift;
  Pi file discovery is verified through important integration contracts and direct manual observation rather than
  tests of prose or Pi itself.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Implements [WF-009 — Define repository context and policy precedence](../wayfinder/tickets/WF-009-define-repository-context-and-policy-precedence.md).
It depends on TICKET-00024 identities and designated-checkout evidence. TICKET-00028 uses the explicit planning
mode during migration/cutover; later Harness and Execution EPICs bind Agent sessions to these snapshots.
