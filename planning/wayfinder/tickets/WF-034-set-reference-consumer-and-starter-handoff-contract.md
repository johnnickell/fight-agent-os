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

## Interview progress

- **Ownership and timing (revised by maintainer):** plan a **separate linked Agent OS-owned reference-consumer
  handoff EPIC**, not starter implementation/certification/release work or an EPIC-00009 amendment. **Supersede**
  the earlier proposal to publish staged, unqualified starter guidance: take and deliver **one coherent handoff
  snapshot only after the entire approved v0.5.0 Agent OS consumer journey is implemented and qualified, including
  its appropriate UI**. Internal planning notes may exist earlier but are not the starter handoff. Package
  fixtures, static planning and stored Agent rows are not real consumer evidence. No starter maintainer's
  adoption, certification or release is required for Agent OS acceptance; EPIC-00009's individually eligible
  immutable starter remains independent of this handoff and unrelated starter releases. The complete-journey
  gate inventories **every supported public v0.5.0 consumer use case**: in-scope use must be implemented and
  qualified, while any excluded or unsupported operation needs its approved explicit disposition. No relevant
  capability may remain unassigned, be invented as UI or be claimed implemented without actual consumer use.
- **Deliverable breadth accepted:** give all five maintainers **backend implementation references**, the actual
  implemented API's **OpenAPI/Swagger documentation references**, and **suggestions for React applications** as
  adaptable examples, alongside the capability matrix, security/integration guidance and honest evidence. A
  starter may adjust implementation and presentation to its own framework and needs; this is not mandatory
  source, architecture, UI or certification policy. Agent OS currently exports private OpenAPI 3.0.3 JSON for
  only CSRF bootstrap and public-safe validation metadata (`docs/api/README.md`); it exposes **no** Swagger UI
  or HTTP spec endpoint today. These **must be implemented before** the final starter handoff can claim the
  full UI/API reference journey. A handoff cannot portray proposed endpoints or a future viewer as implemented,
  ship ignored `.runs/openapi/` output without a current verified export/source/content hash, or expose private
  documentation or secrets through a public starter route. Exact handoff artifact/package versioning and
  later invalidation/evidence format remain to decide.
- **UI versus protected operations accepted:** 'all features including UI' means authorized UI for appropriate
  human-facing v0.5.0 use cases and actual qualified protected non-UI paths for secrets, delivery, recovery and
  internal operational effects, **not** a browser endpoint for every package method. Preserve WF-032/WF-033's
  surface decisions and package-owned authority; document each supported family's status and truthful limits
  in the final snapshot. No stored repository row, proposed screen or package-only fixture can satisfy the
  complete consumer-journey gate.
- **Protected Swagger prerequisite and explicit amendment owner accepted:** the maintainer wants a usable
  `READ_SWAGGER` (`SUPER_ADMIN_ONLY`) page with explicit real **Try it out** API requests before handoff, initially
  local and optionally enabled in other explicitly configured allowed environments, including production.
  **Expand the existing [TASK-00154](../../tasks/00154-TASK.md)** rather than creating a separate follow-up
  implementation TASK/EPIC for this opt-in. Its local-only viewer is *planned, not implemented*. The maintainer
  expressly supersedes the local-only eligibility requirement in that TASK and its owning
  [TICKET-00010](../../tickets/00010-TICKET.md), [EPIC-00003](../../epics/00003-EPIC.md),
  [ADR 0004](../../adr/0004-client-authority-and-runtime-state.md) and dependent
  [TASK-00063](../../tasks/00063-TASK.md). **Allowed-environment direction:** this project's starting
  allowed set is exactly `["local"]`; a separately configured exact set may be empty or include another named
  environment (including production) if that deployment deliberately opts in. Other starter projects choose
  their own policy; Agent OS does not certify or impose its default on them. The configured set is not
  authorization: both viewer and direct spec reads still require current `READ_SWAGGER`, and each Try it out
  request needs its own current endpoint authority. Fail closed on an unset/invalid or unlisted current
  environment, malformed list or wildcard. **`test` is off under this project's initial `["local"]` set but
  may be included explicitly** like any other exact name; it does not relax guarded test-database isolation,
  `READ_SWAGGER`, per-operation checks or credential protections. Decide the exact configuration representation
  during implementation. **APP_DEBUG is a separate strict boolean independent of Swagger**;
  this project intends `true` locally and `false` in staging/production. The maintainer explicitly approves
  sanitized optional debug output in a deliberately enabled **non-local** environment, superseding ADR 0004 and
  TASK-00154's current effective-local-only debug rule. It never enables Swagger, PHP stack/error display,
  sensitive data logging or weaker authorization. **Production debug guard accepted:** if exact `APP_ENV` is
  `prod` or `production` and strictly parsed `APP_DEBUG=true`, reject application startup with a non-sensitive
  configuration error rather than silently changing its effective value. Keep `APP_DEBUG` false for this
  project's staging/production deployments; the same safe optional diagnostics may be deliberately enabled
  in other named environments. With this explicit owner approval, [TASK-00154](../../tasks/00154-TASK.md),
  [TICKET-00010](../../tickets/00010-TICKET.md), [EPIC-00003](../../epics/00003-EPIC.md),
  [ADR 0004](../../adr/0004-client-authority-and-runtime-state.md), and the security-envelope
  [TICKET-00022](../../tickets/00022-TICKET.md)/[TASK-00063](../../tasks/00063-TASK.md) have been aligned
  as planning **without changing implementation**. They retain server permission/endpoint authorization,
  same-origin memory credentials, CSRF, private spec, CSP, redaction and actual-execution safety.

## Resolution

Pending final-snapshot versioning/attribution. Approved Swagger ownership and its dependent planning records
are aligned; no code, enabled route, deployment or EPIC was created. Partial notes do not close WF-034.
