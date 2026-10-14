---
id: TICKET-00083
epic: EPIC-00008
title: Deliver managed review through verified PR handoff
status: ready-for-agent
---

# Deliver managed review through verified PR handoff

## Problem statement

EPIC-00008 was rolled up from a completed local QA skill even though its managed review-to-publication journey
has not been implemented. An existing Awaiting review Workflow needs an attributable, recoverable continuation
to a human-reviewable GitHub PR without the human manually coordinating each role.

## Solution and boundaries

Retain the complete accepted [EPIC-00008](../epics/00008-EPIC.md) requirements as this TICKET's authority:
fresh independent Senior Engineer review, immutable subjects and findings, bounded Engineer revision, semantic
PHP acceptance, independent behavioral QA, Release Manager publication, exact PR/evidence verification, safe
owned cleanup, Dashboard history/forecast and trusted merge observation/reconciliation. The managed QA scope
previously left undecomposed under [TICKET-00033](00033-TICKET.md) is explicitly owned here without weakening it.
Local `review`, `qa`, `land` and `build` skills do not satisfy these runtime requirements.

Consume EPIC-00007's same Workflow identity and EPIC-00006's qualified Harness/session capabilities; preserve
database-authoritative Planning and the accepted terminal-first milestone. Qualify actual Pi or Pi Durable
coordination APIs, extension needs, pinned runtime/profile compatibility and protected session storage through
those owners. A local subprocess is not a managed Agent, credential boundary, durable Workflow or permission grant.
No extension installation or second orchestration engine is selected by this planning correction.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Continue Awaiting review | Existing Workflow authority plus the approved phase operations below | Exact Workflow, subject, grants, evidence and limits | Attributable phase/review facts | Distinct fresh role sessions and immutable review |
| Repair and verify | Bounded revision, acceptance and QA operations | Current findings, subject, review and QA | Findings, dispositions and invalidation/acceptance facts | Engineer repairs followed by fresh review and affected QA |
| Publish and hand off | Authorized non-force publication and owned reconciliation | Original effect outcome, remote OID, PR identity and artifact access | Durable publication intent/outcome and delivery observations | One verified open PR with applicable captures, then human review/merge |

Operation names describe accepted responsibilities, not newly invented transport schemas or implementation.

## Validation and permissions

PHP remains authoritative for claims, grants, transitions, evidence eligibility and bounded effects. Distinct
role credentials, independent fresh sessions, current exact-subject evidence and existing privacy/disclosure
rules apply. Missing, stale, contradictory or uncertain facts hold rather than replaying effects or weakening
checks. Authorized publication proceeds without another human approval; merge, release and deployment remain
outside that grant. Retain the EPIC's five-cycle default, ten-cycle installation ceiling and separate resource limits.

## Acceptance and evidence

Implement the approved delivery slices below, covering the EPIC's full managed state machine and recovery,
role independence, QA/acceptance authority, idempotent publication, current PR and artifact reachability, Dashboard
projections and safe post-merge observations. Actual production Workflow continuation and owned behavioral tests
must substantiate acceptance; local skill walkthroughs cannot close this TICKET. Preserve explicit unmapped
suppliers and existing consumer TASK-00267/00268/00269 holds until their precise lower-level owners are allocated;
new generic supplier TASKs establish ownership, not runtime qualification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00310](../tasks/00310-TASK.md) | Execute bounded finding-driven revisions | ready-for-agent |
| [TASK-00311](../tasks/00311-TASK.md) | Authorize exact-subject acceptance | ready-for-agent |
| [TASK-00312](../tasks/00312-TASK.md) | Verify accepted behavior through managed QA | ready-for-agent |
| [TASK-00313](../tasks/00313-TASK.md) | Preserve applicability across mechanical reconciliation | ready-for-agent |
| [TASK-00314](../tasks/00314-TASK.md) | Prepare the exact publication candidate | ready-for-agent |
| [TASK-00315](../tasks/00315-TASK.md) | Publish one recoverable GitHub PR | ready-for-agent |
| [TASK-00316](../tasks/00316-TASK.md) | Verify evidence and finish human PR handoff | ready-for-agent |
| [TASK-00317](../tasks/00317-TASK.md) | Reconcile trusted merge and downstream eligibility | ready-for-agent |
| [TASK-00318](../tasks/00318-TASK.md) | Safely reconcile the enrolled local checkout | ready-for-agent |
| [TASK-00319](../tasks/00319-TASK.md) | Inspect review-to-merge history in the Dashboard | needs-info |
| [TASK-00320](../tasks/00320-TASK.md) | Forecast remaining work through PR handoff | ready-for-agent |
| [TASK-00445](../tasks/00445-TASK.md) | Record independent managed Reviews | needs-info |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer explicitly selected TICKET-00083 ahead of the map's default TICKET-00085 sequence, requested an
isolated worktree and approved the proposed twelve delivery slices. TASK-00445 and TASK-00310–00320 implement that approved
split without expanding the accepted EPIC or starting execution. Each is an independently reviewable product
path, normally one PR, with recovery and current authority owned inside the relevant slice.

| Slice | Accepted owner | Requirements covered |
|---|---|---|
| A | [TASK-00445](../tasks/00445-TASK.md) | Same-Workflow fresh independent Review, exact immutable subject, findings, finalization and annotations |
| B | [TASK-00310](../tasks/00310-TASK.md) | Shared technical/QA revision allowance, Engineer lineage, fresh re-review, limits and human continuation |
| C | [TASK-00311](../tasks/00311-TASK.md) | PHP semantic acceptance, current checks/criteria/findings, invalidation and retained provenance |
| D | [TASK-00312](../tasks/00312-TASK.md) | Independent managed behavioral QA, actual captures, justified applicability and publication gate |
| E | [TASK-00313](../tasks/00313-TASK.md) | Verified mechanical reconciliation and Review/QA applicability bridges without cycle charge |
| F | [TASK-00314](../tasks/00314-TASK.md) | Release Manager admission, database Planning closeout, bounded administrative candidate and final checks |
| G | [TASK-00315](../tasks/00315-TASK.md) | Protected source-host broker, durable publication intent, non-force push/PR and exact-effect recovery |
| H | [TASK-00316](../tasks/00316-TASK.md) | Audience-accessible evidence, exact final PR, safe owned cleanup, claim release, stop and real production continuation |
| I | [TASK-00317](../tasks/00317-TASK.md) | Trusted merge/base inclusion, Complete/delivery projections and canonical dependent eligibility |
| J | [TASK-00318](../tasks/00318-TASK.md) | Separately authorized safe local fast-forward and exact merged-branch retirement |
| K | [TASK-00319](../tasks/00319-TASK.md) | Existing Dashboard Summary/Changes/Activity/Evidence extensions, safe history and optional intervention |
| L | [TASK-00320](../tasks/00320-TASK.md) | Existing usage/forecast extension through PR handoff, honest ranges and calibration |

TASK-00445 remains `needs-info` for exact lower-level session/admission/evidence mappings under
TICKET-00088/00093/00094. TASK-00264's contribution/phase/fresh-session suppliers must remain below the Review
journey that consumes its guard; never resolve its hold by depending on TASK-00445 or completed TASK-00141.
TASK-00319 retains its independent history/Dashboard mapping hold. TICKET-00098 now maps TASK-00400 as TASK-00320's
base measurement/forecast/query/UI supplier, resolving only that allocation hold; later-phase composition still
requires actual TASK-00316/00400 evidence and all inherited host/source/authority guarantees. Other new TASKs are
`ready-for-agent` subject to their explicit blocker chains, not independently executable. All are unranked;
TASK-00138 and the terminal-first Board priority remain unchanged. TASK-00314 explicitly requires verified
Planning cutover through TASK-00104; no earlier terminal milestone is reverse-blocked by that requirement.

Existing TASK-00267 consumes generic Review/revision/acceptance/applicability, TASK-00268 consumes managed QA,
and TASK-00269 consumes verified publication/handoff. TASK-00207 maps the same generic PR path under its
hotfix authority; TASK-00215 maps Review/QA/applicability without adding ordinary PR publication to release
certification. Their `needs-info` holds remain for unresolved transitive primitives, not missing generic owner
allocation. No named role consumer becomes its supplier's prerequisite and no second pipeline is introduced.
Reuse attributable ordinary production evidence; do not add a proof-only TASK or duplicate role journeys.

This selected TICKET's TASK decomposition is complete; other accepted map TICKETs remain undecomposed.
TICKET-00085's subsequent [approved six-TASK split](00085-TICKET.md#approved-task-decomposition-and-current-handoff)
now supplies its package/connection/profile and onboarding owners, retaining precise resource/model/session holds.
TICKET-00086's subsequent [approved resource split](00086-TICKET.md#approved-task-decomposition-and-current-handoff)
now adds TASK-00327–00332 with explicit remaining supplier holds.
TICKET-00087's subsequent [approved trusted-loader split](00087-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00333–00338 without clearing lower-level session/host holds.
TICKET-00088's [approved snapshot split](00088-TICKET.md#approved-task-decomposition-and-current-handoff) now adds
TASK-00339–00342, preserving precise model/session/host holds.
TICKET-00089's [approved provider split](00089-TICKET.md#approved-task-decomposition-and-current-handoff) now adds
TASK-00343–00347, retaining early attempt/session/host holds.
TICKET-00090's [approved conversation split](00090-TICKET.md#approved-task-decomposition-and-current-handoff) now adds
TASK-00348–00354, preserving operational host/broker and actual authority holds.
TICKET-00091's [approved proposal split](00091-TICKET.md#approved-task-decomposition-and-current-handoff) adds
TASK-00355–00360 without clearing those holds. TICKET-00092's
[approved browser split](00092-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00361–00367,
retaining operational host/AFK participant and actual authority holds. TICKET-00093's
[approved Runner split](00093-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00368–00373,
without clearing those holds. TICKET-00094's [evidence split](00094-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00374–00380; TASK-00445 gains TASK-00378's evidence chain, preserving independent session/admission holds.
TICKET-00095 now adds TASK-00381–00384's web research without clearing independent holds or requiring a web call
for every Review. TICKET-00096 adds TASK-00385–00389's safe history/live delivery, not new Review authority.
TICKET-00097 adds TASK-00390–00395's sessions delivery, not Review execution or acceptance authority.
TICKET-00098 adds TASK-00396–00400's measurement/explanation/quota/forecast delivery; TASK-00399 retains its separate
source/refresh allocation hold, not a prerequisite of Review or later forecasts.
TICKET-00109's approved split completes original-map planning phases. Next: `/skill:next` for Board-based selection. Planning is not implementation, runtime qualification, publication or merge
permission.

### Publication identity reconciliation

During authorized publication, current `origin/develop` at `e2557b5` already contained TASK-00309 for local Pi
file-screening enforcement under TICKET-00039. John explicitly approved moving only this unpublished managed-Review
record to TASK-00445, preserving TASK-00310–00444 and the upstream identity. Current links/dependencies use
TASK-00445; the original allocation and planning receipts below remain historical. No requirement or grant changes
follow from this identity reconciliation.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, branched from `develop`
at `df365fa`. Live and archived identities were inspected before allocating TASK-00309–00320; the archive had
no TASK records. `./bin/planning-check --write` and `./bin/planning-check` passed with 447 records, 355 active,
and current generated views. Tracked `git diff --check` and all twelve new-record whitespace checks passed.
The dependency validator reports no cycles or invalid links; Board active work and priority remain unchanged.

These are planning receipts, not application/runtime evidence or independent review. The full `./bin/build`
was not run; it remains required before commit or publication. No implementation, Agent session, provider effect,
commit, push, PR or merge was performed. The isolated worktree retains the uncommitted planning changes.

### Original scope correction

Created on the maintainer's 2026-10-13 request to correct the EPIC mismatch. At creation the retained accepted
managed scope awaited TASK decomposition, not new product scope or a placeholder TASK. No runtime or publication
effect was performed. The independently usable local coordinator belongs to [TICKET-00084](00084-TICKET.md).
