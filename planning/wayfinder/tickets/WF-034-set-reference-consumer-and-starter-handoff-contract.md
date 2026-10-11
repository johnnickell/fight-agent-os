# Set the Agent OS reference-consumer and starter handoff contract

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete AccessControl consumer adoption](../accesscontrol-consumer-adoption-map.md)
**Depends on:** [WF-032 — Reconcile AccessControl coverage with accepted Agent OS scope](WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md), [WF-033 — Define live Agent operation adoption and safe surfaces](WF-033-define-agent-operation-adoption-boundary.md)

## Question

What versioned evidence must Agent OS deliver to the five managed starter maintainers so it is a useful
reference consumer without becoming the implementer, certifier or release owner of those repositories?

## Must decide

- **Recommend** an Agent OS-owned, versioned capability/contract matrix linked to the exact AccessControl tag,
  Composer lock and Agent OS commit; for each family record implemented/planned/excluded/unassigned, actor and
  delivery boundary, authorized UI or explicit non-UI treatment, and known limits. Extend the
  [planning matrix](../research/WF-032-accesscontrol-consumer-capability-matrix.md) with verified adoption evidence
  when it actually exists; do not label this planning snapshot a completed conformance report.
- **Recommend** consumer integration/security guidance and reproducible scenarios for real PostgreSQL
  transaction/CAS/cohort races, authenticating/using an Agent, protected sink delivery/restart/rotation/revocation,
  restoration, Feature preparation/runtime availability, human account/expiry and safe admin UI; include inputs,
  commands/tool versions, expected observations and redacted receipts. Attribute package conformance versus Agent
  OS evidence; document failed/unavailable qualifications and supported release identity honestly.
- **Recommend** a separately accepted Agent OS deliverable only. Provide the five named starter maintainers
  (`project-symfony`, `project-slim`, `project-laravel`, `project-yii`, `project-codeigniter`) the matrix and
  guidance, but do not edit, certify, test, tag or release their repositories here. Determine handoff versioning
  and how later package/consumer changes invalidate stale claims; recommend explicit revision/digest and change
  log without a new automatic pipeline.
- Retain [EPIC-00009](../../epics/00009-EPIC.md)'s *individual* managed starter eligibility: immutable stable
  SemVer `>=1.0.0`, required generation manifest and verified snapshot. No all-five release gate; starter 1.0.0
  is **not** an AccessControl 1.0.0 decision. Decide whether handoff acceptance is part of the new Agent OS
  reference-consumer EPIC or its own linked EPIC; recommend a bounded separate evidence handoff after real
  consumer qualification, without blocking eligible project creation on unrelated starter releases.

## Resolution boundary

Approve deliverable format/ownership and honest acceptance evidence. No starter code, certifications, releases,
EPIC/TICKET/TASK creation, deployment or upstream API changes are performed by this interview.

## Resolution

Pending maintainer decision after WF-032 and WF-033.
