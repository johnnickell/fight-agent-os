---
id: TICKET-00088
epic: EPIC-00006
title: Resolve immutable Harness session snapshots
status: ready-for-agent
---

# Resolve immutable Harness session snapshots

## Problem statement

A session must be explainable and reproducible without treating pinned instructions as live authority. Mutable
profiles, package updates or provider changes must not silently change the behavior behind an accepted assignment.

## Solution and boundaries

Resolve one exact immutable HarnessSnapshot beside the RepositoryContextSnapshot for every participating Agent
session. Record connection/profile revisions, exact package source/version/digest, executable extension versions,
selected type-specific resource revisions, aliases/collision choices, host-assigned origin identities, Skill URIs
and complete manifests, chosen provider and requested model. Record reported model/response identity as attributed
runtime evidence when available; do not mutate historical selected configuration or invent a model identity.

Consume [TICKET-00034](00034-TICKET.md)'s exact role-profile resolver and actual named-Agent binding where applicable.
A template selection is not an Agent, Role or Permission grant. Snapshot resolution supplies generic session facts;
TASK-00250 retains role-session integration and independence. Do not require its completed integration to implement
the underlying snapshot capability it consumes.

Snapshot resolution is deterministic and bounded. Inspect safe exact configuration, differences and missing
capabilities. Reject unresolved aliases, missing revisions, unqualified resources/models or conflicting scope rather
than silently substituting. Honor [TICKET-00025](00025-TICKET.md)'s repository policy precedence; Harness resources
cannot override it. Keep secret handles private and raw credentials outside snapshots and model context.

Never refresh an active snapshot silently. Meaningful effective instruction/policy, mutable-package, resource,
connection, profile or provider drift stops new authority-bearing operations and shows a concise change summary.
An explicit authorized continuation resolves a new snapshot and links old/new session lineage; history and pending
proposals survive but must be revalidated. Unchanged reconnect requires no new snapshot or acceptance ceremony.
Resource rollback likewise affects future resolution, not historical bytes or active sessions.

Pinned content does not pin grants. Current human/Agent Permissions, delegation, Team/Repository/host scope,
credential activation and applicable claim/lease authority may narrow or revoke use immediately. Unavailable
current authority denies protected work even if all snapshot bytes are retained. Provider changes never fail over
silently and must follow the same explicit continuation boundary.

Exclude another role resolver, template registry, credential lifecycle, session launcher, Workflow grant engine,
repository context authority or migration of Markdown Planning. Session persistence/execution uses
[TICKET-00090](00090-TICKET.md); provider support uses [TICKET-00089](00089-TICKET.md).

### Approved per-user provider binding and model continuation — 2026-10-24

Bind hosted browser requests to the initiating user's nonsecret provider-connection identity, environment and exact
generation beside model/configuration provenance. Agent OS connection profiles and provider connections remain
distinct; raw secrets or credential handles do not enter snapshots/views. Current connection/disclosure authority
still controls dispatch. Replacement, disconnect or changed-away-and-back selection cannot rebind original work,
revive stale authorization or erase consumed/reserved uncertainty.

WF-049's explicit OpenRouter model switch uses TASK-00342 between turns: finish or Stop/reconcile original work,
preview the old/new model and authorized context carried forward (including any truncation), then confirm one
linked successor. Preserve original history/usage attribution, accepted answers and proposal references; revalidate
proposals with no transferred approval, regeneration or automatic Apply. Default/catalog refresh alone does not
change the active selection, while actual policy revocation still denies current use. Same-provider switching
is not cross-provider qualification. TASK-00367 owns browser controls; TASK-00325 retains terminal behavior.
No existing host/continuation hold is cleared. See the
[reconciliation receipt](../wayfinder/research/private-production-owner-reconciliation.md).

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Pin a session's effective resources | Resolve and retain exact snapshot for admitted session | Authorized profile, package, resources/origins/models and repository context | Committed snapshot/binding facts at their owner | Immutable references and provenance, never extra authority |
| Inspect reproducibility/readiness | N/A — read-only | Safe exact snapshot, current compatibility and differences | N/A — inspection does not revise session state | Explain unavailable/stale resources without leaking secrets |
| Continue after material change | Explicit linked continuation with expected prior state | Exact prior/new revisions, current authority and pending effects | Committed continuation/binding facts | New immutable snapshot; prior history preserved |
| Reconnect or detect revocation | Existing owning reconnect/hold operation where state changes | Original binding and live authority/drift | Actual hold/reconnect observations, not a new grant | Same session when valid, otherwise visible denial or required continuation |

## Validation and permissions

Authorize snapshot creation/read/continuation against the real principal, assignment and repository, not supplied
identity strings. Bound resource/reference sets and validate complete immutable references and selection generations;
changed-away-and-back current selections cannot make stale approval valid. Preserve shared transaction/reference
fences and atomic required audit for owned binding state under ADR 0002. External resolution cannot occur inside a
long business transaction; validate current exact references at the final owned commit.

Snapshots expose only authorized details. Live operations recheck current authority and provider disclosure through
their owners; a previously valid snapshot cannot authorize a currently denied source read, tool or provider call.
Reconcile unknown effects before dependent continuation; preserve explicit stops and do not replay uncertain work.

## Acceptance and evidence

- The same exact inputs produce the same resolved resource choices and inspectable provenance; incomplete or
  unauthorized references fail without fallback. Runtime-reported facts remain distinct from requested settings.
- Real sessions keep original snapshots through updates and reconnect; material drift requires an explicit linked
  continuation that preserves pending history and revalidates proposals.
- Permission revocation and authority-store outage deny subsequent protected use despite an unchanged snapshot.
- Owned deterministic resolution, reference/concurrency and continuation tests plus actual session integration
  evidence and `./bin/build` pass. No tests of generated files, configuration text or package installation mechanics.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00339](../tasks/00339-TASK.md) | Resolve and inspect exact Harness configuration | ready-for-agent |
| [TASK-00340](../tasks/00340-TASK.md) | Retain immutable Harness snapshot bindings | ready-for-agent |
| [TASK-00341](../tasks/00341-TASK.md) | Hold protected work on Harness drift | ready-for-agent |
| [TASK-00342](../tasks/00342-TASK.md) | Resume and explicitly continue real sessions | needs-info |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved four delivery slices in the existing isolated planning worktree, normally one bounded PR
each. Early resolution/binding/currentness contracts remain independent of completed runtime continuation so
protected brokers and loaders can consume them without circular dependencies.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00339](../tasks/00339-TASK.md) | Deterministic authorized exact Harness configuration and safe reproducibility/readiness inspection |
| B | [TASK-00340](../tasks/00340-TASK.md) | Immutable snapshot retention/binding with reference fencing, atomic required audit and original-outcome recovery |
| C | [TASK-00341](../tasks/00341-TASK.md) | Exact drift/currentness inspection and reusable guard for new protected operations |
| D | [TASK-00342](../tasks/00342-TASK.md) | Actual runtime binding/reconnect, explicit linked continuation and separately attributed reported identity |

TASK-00339 consumes TASK-00323/00334/00083 and now TICKET-00089's TASK-00343 early model/provider catalog.
That resolves its sole mapping hold: status is `ready-for-agent` behind unfinished dependencies, not live
qualification. TASK-00340 now consumes TASK-00339 plus TASK-00348's early authorized session-identity/intent and
binding transaction contract under TICKET-00090, resolving its sole mapping hold; it is ready behind unfinished suppliers. TASK-00341 consumes TASK-00340/00084; its
`ready-for-agent` status retains those unfinished dependencies. TASK-00342 consumes TASK-00340/00341/00244/00336
and TASK-00345's original-provider-outcome/accounting, now with TASK-00351/00352/00353 invocation/attachment/recovery.
It retains `needs-info` for TASK-00350's exact operational host/broker gap and actual authority composition. All orders remain unranked; priorities stay
unchanged. TASK-00346/00347 independently qualify actual provider paths; neither depends on completed continuation.

TASK-00244 and TASK-00336 gain TASK-00340/00341, not completed TASK-00342. Their remaining admission, signing,
current-authority, host and acting-window holds remain. TASK-00250 and TASK-00325 gain TASK-00342 and retain their
separate role/provider/contribution or protected-session holds. Exact role facts remain with TASK-00249 and
TASK-00250's named-Agent integration; no generic snapshot primitive waits for a completed role journey.

TICKET-00090 maps early identity/assignment to TASK-00348 and attempts to TASK-00349, independently of completed
execution/continuation. TASK-00348 consumes TASK-00339; TASK-00351 activation composes bindings/currentness with
TASK-00244/00336/00344/00345 and TASK-00350's protected host adapter. Neither a persisted binding nor supplied session ID grants authority. Final loader observations and reported
model/response identities remain separately attributed evidence, never a silent edit to selected configuration.
No new session, transcript, grant, credential or provider platform is introduced. Real runtime qualification and
owned tests stay within their product outcomes, not separate proof-only TASKs.

This decomposition is complete, not implementation. Following TICKET-00089–00099's provider/conversation/proposal/browser/Runner/evidence/web-research/history/sessions/measurement/catalog
splits, continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve Markdown authority, terminal-first execution priority
and all unresolved supplier holds. Planning authorizes no session launch, provider call or operational enrollment.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
planning changes. Live and archived TASK identities were rechecked before allocating TASK-00339–00342; the archive
contained no TASK records. `./bin/planning-check --write` and `./bin/planning-check` passed with 469 records,
377 active and current generated views. Tracked `git diff --check` and all four new-record whitespace checks
passed. Validation found no dependency cycles or invalid planning links. New orders remain unranked; active
TASK-00138 and the Board's existing priorities are unchanged.

These are planning receipts, not actual snapshot, session, provider or host qualification. The full `./bin/build`
was not run and remains required before commit/publication. No implementation, session launch/reconnect, provider
call, credential access, enrollment, commit, push, PR or merge was performed. Changes remain uncommitted in the
isolated worktree; independent review and delivery remain separate.

### Original requirement approval

Approved as area 4 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-009](../wayfinder/tickets/WF-009-define-repository-context-and-policy-precedence.md),
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md), including the
[Pi Durable direction](00032-TICKET.md#pi-durable-integration-direction--2026-10-13).

Consume TICKET-00085's approved [TASK-00321](../tasks/00321-TASK.md) package facts,
[TASK-00322](../tasks/00322-TASK.md) exact connection/origin facts and
[TASK-00323](../tasks/00323-TASK.md) owned profile revisions, plus TICKET-00086's exact resource owners
TASK-00327/00329/00330, TICKET-00087's early TASK-00333 trust and TASK-00334 choice facts, TICKET-00089's TASK-00343
model/compatibility facts and TICKET-00025 context. TASK-00335 owns actual protected MCP publication; its completed transport
must not reverse-block the lower-level snapshot/admission facts required by TASK-00244/00245. Define exact
reference/manifest inspection through existing early owners rather than assuming full remote loading is available.

TASK-00336's previously requested snapshot/selection/currentness mappings are now supplied by TASK-00340/00341
above. TICKET-00090 now maps TASK-00350's session/host/acting-window adapter with early TASK-00348/00349 facts.
It retains `needs-info` for TASK-00350's exact operational host/broker participant gap and actual supported scope. Map those independently of completed
loading/cache, final switching and onboarding. Actual verified selection provenance can then flow from the loader
to its session consumer without a reverse prerequisite on the completed loader. No invented bootstrap grant,
second session store or fixture substitutes for the unresolved admission/authority contracts.
[TASK-00325](../tasks/00325-TASK.md) consumes this TICKET's snapshot/continuation primitives for terminal switching;
those primitives must not depend on that completed consumer or TASK-00326's onboarding journey. The new package/
profile/model owner mappings are planning facts, not cleared remaining session/host holds or runtime qualification.
Decompose early exact-reference contracts independently
of complete journeys; provider catalog and resource primitives must not wait for snapshots or final session use.
TASK-00250 remains the existing role integration consumer and now names TASK-00342 for generic snapshot/continuation
behavior. Its `needs-info` and remaining actual host/Agent/contribution prerequisites remain until precise TASK
mapping and qualification. No live snapshot/session is created here.
