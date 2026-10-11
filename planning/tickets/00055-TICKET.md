---
id: TICKET-00055
epic: EPIC-00013
title: Define release TASKs and project release policy
status: ready-for-agent
---

# Define release TASKs and project release policy

## Problem statement

A library version or production deployment needs one Planning identity and a known project procedure. Today's
standalone TASK kinds and normal execution route do not express a release, while TASK PR landing does not
supply release authority. Guessing a project's release commands or treating `chore` as a release would make
its effects unsafe and its completion misleading.

## Solution and boundaries

Extend Planning to accept a **standalone `release` TASK kind**, with one TASK per concrete version or named
deployment candidate, not one TASK for each stage. Keep the accepted statuses, Board ordering and canonical
eligibility owner. A release TASK reaches `done` only after its project's required effects, checks and
follow-through are verified; a successful preparation or ordinary PR handoff is not release completion. Preserve
separate implementation acceptance, PR/merge, signing, publication and deployment evidence.

At Repository setup, version a project-owned policy/runbook reference with release type, supported release
line and branching strategy, source/target identities, environment or host/registry, expected certification,
verification and rollback/forward requirements. Resolve the appropriate qualified procedure for the exact
candidate; missing, contradictory, stale or unsupported facts stop with safe reasons. Extend the versioned
Triage route catalog to `release` **only when supported**, rather than treating a release TASK as a normal
feature/bugfix/chore. Jev classification remains advisory, not a source of policy or authority. This TICKET
owns eligibility and project-policy boundaries, not execution, tagging or deploying.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create or revise one release TASK | Existing authorized Planning lifecycle/metadata commands extended for the new kind | Inspect candidate identity, status, scope and canonical eligibility reasons | Existing Planning revision/lifecycle facts | One standalone release record, without inventing a parent TICKET or a per-stage TASK |
| Set or restore project release policy | Existing versioned Repository configuration operations | Preview effective release type, branch strategy, procedure, destinations, verification and change impact | Attributed Repository configuration revision/restore fact | A validated future policy revision, not an executed release or overwritten historical Workflow |
| Resolve release support | N/A — read-only policy and eligibility resolution | Exact TASK/context/configuration/catalog revisions and supported runbook | N/A — resolution alone emits no execution event | Typed route or safe unsupported reason; no claim, tag or deployment |

The names identify owning capabilities, not additional Planning status values or preselected PHP message types.

## Validation and permissions

Only actors with scoped Planning authority create/revise release TASKs; Repository-policy changes require their
own server-side grant and revision checks. A model, TASK prose, selected profile or ordinary landing grant
cannot install a runbook, widen effect Permissions or choose an unapproved destination. Validate project
identity, release candidate shape, policy version, supported route, branch targets and context currentness.
Fail closed on missing or unavailable authority while explaining unsupported capability separately from an
ineligible TASK. Preserve Markdown as authority until an approved, verified cutover; adapt its existing
Planning representations deliberately rather than rewriting history.

## Acceptance and evidence

- A standalone release TASK can name a concrete library version or application environment/candidate with no
  parent TICKET, while existing feature/bug/chore TASKs retain their accepted meanings and queue order.
- Canonical eligibility and the route catalog identify supported release work without making a `chore`
  executable as a release or adding a second readiness algorithm.
- Repository setup and later revisions resolve an explicit project release policy; absent, stale or incompatible
  runbooks and destinations stop before any release effect, with explainable current revisions.
- `done` for release work requires verified project-specific release completion; partial or uncertain effects
  remain visibly incomplete. No other TASK kind silently changes its completion semantics.
- Exercise owned kind/lifecycle, policy validation, authorization, drift and eligibility behavior with focused
  application evidence; validate planning tooling and configuration directly rather than testing Markdown or
  migration files as product behavior.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00211](../tasks/00211-TASK.md) | Create standalone release TASKs with guarded completion | ready-for-agent |
| [TASK-00212](../tasks/00212-TASK.md) | Configure and preview project release policy | ready-for-agent |
| [TASK-00213](../tasks/00213-TASK.md) | Resolve supported release eligibility and route | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00013](../epics/00013-EPIC.md), under
[WF-028](../wayfinder/tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md). Reuse
[TICKET-00025](00025-TICKET.md)'s versioned context, [TICKET-00027](00027-TICKET.md)'s canonical queue and
[EPIC-00012](../epics/00012-EPIC.md)'s eventual Triage catalog. [TICKET-00056](00056-TICKET.md) consumes
its supported release candidate/policy. Approving this requirement did not add a live kind, change
Repository configuration or perform a release.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00211 — Create standalone release TASKs with guarded completion](../tasks/00211-TASK.md),
[TASK-00212 — Configure and preview project release policy](../tasks/00212-TASK.md), and
[TASK-00213 — Resolve supported release eligibility and route](../tasks/00213-TASK.md). TASK-00211 consumes
Planning authoring/lifecycle (TASK-00089/00092); TASK-00212 consumes versioned Repository policy/context
and branching (TASK-00081/00082/00202) independently. TASK-00213 consumes both plus Triage classification
and route catalog (TASK-00201/00202), but leaves start/claim to TICKET-00056. All are unranked.
Release-specific `done` remains guarded pending verified project effects; downstream TICKET-00056–00058 own
their actual evidence and qualification. This planning split did not change Markdown/database authority, add a
live release TASK or grant a release effect. TICKET-00056 is next to decompose; the other EPIC-00013 TICKETs
remain.
