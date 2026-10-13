---
id: TICKET-00034
epic: EPIC-00006
title: Define the SDLC team templates and role-specific capabilities
status: ready-for-agent
---

# Define the SDLC team templates and role-specific capabilities

## Problem statement

John needs familiar team responsibilities, one Team Lead contact during execution, and explicit QA, release and
emergency responsibilities without a confusing overlap between Agent templates and Access Control Roles.

## Solution and boundaries

Define the eight revisioned templates in [Team roles](../../docs/engineering/TEAM.md). This EPIC runs only
Explorer and Project Manager; later execution EPICs activate the other identities. Architect is a Senior Engineer
specialization. Hotfix has senior capability and a shortened, explicitly scoped incident process. Release Manager
owns TASK landing and later project-specific release/deployment runbook assistance, with separate effect grants.
Defining a template does not implement those future flows. Preserve the first Team Lead → Software Engineer slice.

The 2026-10-13 [Pi Durable direction](00032-TICKET.md#pi-durable-integration-direction--2026-10-13) refines the
session integration consumed by TASK-00250: browser and terminal attach to the same authorized durable Agent
conversation. A client is not a new Agent or credential holder, and reconnect does not reselect its role.
Protected Harness/session primitives remain with EPIC-00006's existing ownership and unresolved decomposition;
this template TICKET does not become a second runtime implementation or unblock those missing capabilities.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Manage team template | Existing Harness template/reconciliation commands | Revisions, requested permissions, skills and model capability | Revision and authorized reconciliation facts | Future/managed-bound Agent configuration as explicitly approved |
| Select role for work | Resolve effective Agent profile | Availability, independence and provenance | Existing session snapshot facts | Role-bound session with permission-filtered tools |

## Validation and permissions

Templates are semantic resources, not Access Control Roles. Authority remains with actual direct Permissions,
grant and server validation. Separate credential holders/sessions; no mid-session role escalation. Unsupported
model choices are explicit. Explorer favors measured speed/cost suitability; never guess provider provenance.

## Acceptance and evidence

- Each template has distinct responsibilities, inputs/outputs, skills, permissions, model suitability and limits.
- Team Lead owns initial bounded execution planning and human escalation; PHP owns state and provisioning.
- Senior Engineer independence includes design/build contribution; QA runs after review and before land.
- Preserve historical identity through versioned aliases; renaming alone neither rewrites events nor grants powers.
- Hotfix and release assistance define explicit exceptions/grants and handoffs without implementing deployment.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00248](../tasks/00248-TASK.md) | Define the eight reviewed SDLC template specifications | ready-for-agent |
| [TASK-00249](../tasks/00249-TASK.md) | Resolve exact role profiles with truthful capability availability | needs-info |
| [TASK-00250](../tasks/00250-TASK.md) | Bind role selections to isolated immutable Agent sessions | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved amendment to WF-014/WF-015 and EPIC-00006–00008. The maintainer explicitly selected this TICKET ahead
of the AccessControl map's next TICKET and approved three TASKs:

- [TASK-00248](../tasks/00248-TASK.md): complete reviewed eight-role specifications, prompts, requested capabilities,
  limits, handoffs and historical aliases; no runtime or ninth required Triage role.
- [TASK-00249](../tasks/00249-TASK.md): exact authorized profile resolution, complete references, model/resource
  availability and provenance without grants or silent substitution.
- [TASK-00250](../tasks/00250-TASK.md): integrate immutable role binding and current authority with actual isolated
  session owners, without another launcher or duplicate role journey.

TASK-00248 is `ready-for-agent`; TASK-00249/00250 retain `needs-info` for remaining resource/model,
Harness session/snapshot and actual named-Agent composition prerequisites. TICKET-00075 now maps
[TASK-00299](../tasks/00299-TASK.md)'s database initialization/history owner directly before TASK-00249;
its resource/reference and independent Triage-input holds remain explicit. All are unranked. TASK-00200 now consumes TASK-00249 for
its separately owned Triage definition, while TASK-00206 and TASK-00247 consume TASK-00250. These consumers keep
their remaining real runtime/provisioning holds; records or definitions alone establish no usable identity.

Apply EPIC-00017's accepted amendment to the older management/reconciliation wording above: TICKET-00075 owns
initial seeding and database template authority, TICKET-00076 owns browser publication, and TICKET-00078 owns
explicit one-time Permission reconciliation. TASK-00248 supplies reviewed seed inputs; TASK-00249 consumes the
resulting database capability, never the reverse. Publication changes no existing Agent/session, and no recurring
source reconciler or automatic grant propagation is introduced. Those primitives cannot depend on this whole
TICKET or its final session integration. The later editor is not a prerequisite for initial managed roles.

TICKET-00063 retains named Team-bound creation; TASK-00247 and existing Workflow owners retain real role outcomes,
with TICKET-00065 coordinating complete team acceptance. Preserve the first Team Lead → Software Engineer →
Awaiting review milestone, active TASK-00138/TASK-00226 and all existing execution priorities. No implementation,
Agent, grant, session, provider call or operational enablement follows from this planning approval.

This completed only the explicitly selected TICKET's decomposition; at that historical handoff the AccessControl
map still had 21 TICKETs needing TASK splits and next returned to TICKET-00062. With subsequent splits through
TICKET-00077, nineteen of the map's 24 are now decomposed; five remain, TICKET-00078–00082. Older EPIC-00006
Harness/browser and managed review requirements remain incompletely decomposed. Next planning operation:
`/skill:to-tasks TICKET-00078`. Checkout/worktree choice, implementation, actual qualification, independent review
and publication remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 350 records,
263 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T181430Z-40635`; summary log:
`.runs/build/ticket-00034-to-tasks.log`. These checks validate planning and the existing repository, not future
catalog content, profile resolution, managed sessions or role journeys. Independent review and publication
remain unperformed.
