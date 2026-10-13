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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-009](../wayfinder/tickets/WF-009-define-repository-context-and-policy-precedence.md),
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md), including the
[Pi Durable direction](00032-TICKET.md#pi-durable-integration-direction--2026-10-13).

Consume TICKET-00085 profiles/package facts, TICKET-00086 resource identities, TICKET-00087 origin/manifest facts,
TICKET-00089 model availability and TICKET-00025 context. Decompose early exact-reference contracts independently
of complete journeys; provider catalog and resource primitives must not wait for snapshots or final session use.
TASK-00250 remains the existing role integration consumer. Its `needs-info` and actual host/Agent/contribution
prerequisites remain until precise TASK mapping and qualification. No live snapshot/session is created here.
