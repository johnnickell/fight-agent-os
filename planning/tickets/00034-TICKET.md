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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved amendment to WF-014/WF-015 and EPIC-00006–00008. TASK decomposition is the next planning operation.
