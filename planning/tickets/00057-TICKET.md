---
id: TICKET-00057
epic: EPIC-00013
title: Publish a library release
status: ready-for-agent
---

# Publish a library release

## Problem statement

A certified library commit, local signed tag, remote tag, GitHub release and registry projection are different
facts. Publication cannot safely infer any of them from a clean tree, a TASK PR landing or a successful provider
request. A timeout must not produce another release or overwrite an existing tag.

## Solution and boundaries

Continue the **same** release TASK and Workflow prepared under [TICKET-00056](00056-TICKET.md), using its
project-qualified library runbook, exact candidate and bounded named-effect approval. Honor the human's
interactive signing step where required; verify the signed tag's object, signer and peeled certified commit
before any authorized push. Release Manager uses only its direct Permissions to push the exact tag, create or
advance the intended GitHub release and verify identities and effects. Check registry projection or consumer
qualification only where the project's runbook requires it; not every library uses Packagist or public assets.

Keep required GitFlow merge-back/forward-port after verified release **inside this Workflow**, with applicable
approved authority, current source/target, project branch protection, review and checks. Do not create another
TASK or silently merge a protected branch. Conflicts or changed identities pause with an actionable resolution,
not a forced or invented success. A release TASK becomes `done` only after all required project-specific
publication, verification and follow-through facts are proven. This TICKET excludes application deployment,
automatic signing, PR approval/merge and tag replacement.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Verify signed candidate and remote state | N/A — read-only inspection until an effect is approved | Exact certification, local/remote tag object, signer, peeled commit and existing release | N/A — observations are not authoritative transitions | Reuse matching existing state or stop on unknown/conflicting identities |
| Publish approved library version | Durable, idempotent effect operations under the exact grant | Recheck certified commit, tag/remote, Release Manager Permission and GitHub release state | Intent, verified tag push, draft/publication or failure facts, separately | Only the approved tag/release effects; no implicit branch push or duplicate tag/release |
| Verify registry and finish branch follow-through | Scoped verify/merge-back operations where policy requires | Release identity, registry/consumer projection, configured branch targets and inclusion | Attributed verified effect or blocked/recovery facts | Actual registry evidence and safe project-required follow-through within the same Workflow |
| Reconcile uncertain effect | Resume the same durable effect identity | Current provider state and prior intent/result | Recovery outcome, never inferred success | No force-retry or duplicate publication on lost response |

The table describes application-owned interactions; Git/GitHub/registry transport details follow the target
runbook, not a universal package release script.

## Validation and permissions

Release Manager has separate direct source-host/registry authority; Team Lead coordinates without acquiring its
credentials. Before each effect, recheck current grant, exact certified commit/version/tag, remote identity,
runbook revision, prior effects and required checks. Reject mismatched signer, conflicting or lightweight tag
where signing is required, retargeted/advanced release line, changed assets or unauthorized publication.
External effects follow durable intent, verified result and reconciled uncertainty; no force-push, tag rewrite,
raw signing secret or automatic approval. Registry absence, stale response and unavailability are distinct
from verified success. Protected-branch merge-back/forward-port needs its own permitted effect under the bounded
plan; no effect derives authority from a Jev classification or ordinary `land` grant.

## Acceptance and evidence

- Verify exact signed tag and certified commit independently; a local tag, remote tag and published GitHub
  release have separate identity/evidence, with no claim based only on a queued request.
- Reuse matching existing draft/tag/release; conflicting identities and uncertain responses stop without
  destructive replacement or duplicate provider effects.
- Required assets, registry projection or consumer proof are evaluated against the project's policy, with
  unavailable results disclosed rather than invented or assumed universal.
- Configured GitFlow follow-through uses the same release history, preserves newer development changes and
  stops on conflicts/protected branches; no extra TASK or automatic PR approval/merge.
- `done` is recorded only after the project's required library release and checks are verified. Prove owned
  effect authorization, idempotency and recovery with focused integration contracts and direct provider/registry
  evidence; do not test GitHub CLI or runbook prose as product code.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00218](../tasks/00218-TASK.md) | Verify the library candidate and publish its exact remote tag | ready-for-agent |
| [TASK-00219](../tasks/00219-TASK.md) | Publish the project-required hosted library release | ready-for-agent |
| [TASK-00220](../tasks/00220-TASK.md) | Verify library distribution against project policy | ready-for-agent |
| [TASK-00221](../tasks/00221-TASK.md) | Finish library branch follow-through and release closeout | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00013](../epics/00013-EPIC.md), consuming
[TICKET-00055](00055-TICKET.md)'s project policy and [TICKET-00056](00056-TICKET.md)'s exact-candidate
approval/signing boundary. Existing local [release guidance](../../.pi/skills/release/SKILL.md) is a
project-owned process reference, not a live managed Workflow or blanket publication permission. No tag,
provider effect, registry projection or actual release was created by requirement planning.

### Approved TASK decomposition — 2026-10-11

John approved [TASK-00218 — Verify the library candidate and publish its exact remote tag](../tasks/00218-TASK.md),
[TASK-00219 — Publish the project-required hosted library release](../tasks/00219-TASK.md),
[TASK-00220 — Verify library distribution against project policy](../tasks/00220-TASK.md), and
[TASK-00221 — Finish library branch follow-through and release closeout](../tasks/00221-TASK.md). Their
implementation order is remote tag → project-required hosted publication or policy N/A → required registry/
consumer proof or policy N/A → applicable branch follow-through and guarded `done`. Each stage continues the
**same** release TASK and Workflow from TICKET-00056; these TASKs implement the capability, not one release
TASK per tag, provider effect or stage. All are unranked.

TASK-00221 is `needs-info` for an accepted implementing plan and real dependencies for trusted managed
merge observation/recovery where GitFlow or forward-port applies; [TASK-00209](../tasks/00209-TASK.md)'s
hotfix-specific plan and [EPIC-00008](../epics/00008-EPIC.md)'s `done` do not establish that capability.
No fabricated blocker IDs, live tag, publication, registry observation, provider grant, branch change or
release completion were produced by this decomposition. TICKET-00058 still needs TASK decomposition.
