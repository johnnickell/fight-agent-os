# Wayfinder Map: Complete Fight AccessControl consumer adoption

**Label:** `wayfinder:map`
**Status:** Active

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map summarizes linked resolution and points to one next decision frontier.

## Destination

Give Fight Agent OS a decision-complete route to account for supported Fight AccessControl use cases as an
**authorized, evidence-backed consumer**: human-facing management where appropriate, protected operational
paths for sensitive effects, a fully qualified Agent credential/operation integration, and a versioned Agent
OS-owned reference handoff for five managed starter maintainers. “Complete” means every relevant current public
capability has an owned usable integration or a human-approved explicit non-UI/exclusion disposition; it does
**not** mean one endpoint per method, a general secret console, package v1.0.0, or starter implementation.

**Done** = [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) through
[WF-035](tickets/WF-035-define-project-teams-and-agent-selection.md) are resolved or explicitly
excluded with maintainer approval; ownership/amendment paths for conflicts are recorded; the
[capability matrix](research/WF-032-accesscontrol-consumer-capability-matrix.md) identifies gaps and proposed
surfaces; and an approved next bounded EPIC destination is linked. Done is **planning handoff**, not live
AccessControl adoption, actual Agent qualification, starter certification, production enrollment or release.

## Notes

### Established facts (not new approval)

- Static inspection of current Agent OS `develop` `7002441` and installed `fight-access-control` **v0.5.0** at
  `46ffcf75f1e14dbc5b58a5049e05a600ce5cf8a0` is recorded in the
  [traceable package → consumer → owner → gap matrix](research/WF-032-accesscontrol-consumer-capability-matrix.md).
  The branch initially adds only a separate committed README scope update. The package's full v0.5.0 Agent
  operation/cohort, Feature and MCP contracts exceed the current consumer paths. No runtime/source/provider
  qualification was executed for this map. Package tests, repository injection, and a storage-only
  `AgentRepository` are not proof of live Agent authority.
- The installed `PostgresAgentRepository` supplies ordinary storage and direct Permission/reference protection
  but rejects `getOperationContract()` with `UNAVAILABLE`. No `AgentOperationRepository`, persisted cohort,
  protected sink, independent restore witness or Agent authentication/worker is installed. [TASK-00126](../tasks/00126-TASK.md)
  accepted this **limited** boundary, not complete Agent use. The only API-v1 Actions currently registered are
  CSRF bootstrap and public-safe validation metadata; the React shell is non-product. Credential-delivery/expiry
  console paths serve **human** grants with a null local/test delivery provider, not Agent issuance or a production
  enrollment. Feature storage/reference checks do not constitute Feature scanning, runtime enforcement or UI.
- [EPIC-00004](../epics/00004-EPIC.md) explicitly defers email-address change and broad profile mutation,
  although [TICKET-00030](../tickets/00030-TICKET.md)/[TICKET-00031](../tickets/00031-TICKET.md) now own
  specific user/session/role administration; changing the deferral needs the decision owner, not a planning
  index edit. [EPIC-00006](../epics/00006-EPIC.md) promises managed identities, direct Permissions and Harness
  credential isolation, but its TICKET-00034 templates and presentation/terminal work do not already own every
  v0.5.0 operation/cohort/delivery/recovery proof. [EPIC-00009](../epics/00009-EPIC.md) owns project creation
  and the five managed starter *catalog entries*, not starter-source adoption or release.
- [ADR 0001](../adr/0001-application-ownership-and-orchestration.md) assigns package Domain/Application authority
  to AccessControl and transport, persistence, provider and demonstrated cross-boundary orchestration to Agent
  OS. [ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) requires a shared PostgreSQL
  transaction and package-owned durable state, and historically deferred Agent authority. A newly approved
  Agent adoption must explicitly update/supplement that deferred boundary using the actual locked contract,
  without rewriting its accepted historical evidence. The [closed vision map](complete-agent-os-vision-map.md)
  supplies accepted Repository/Harness/Runner direction and remains closed.
- The five starter handoff recipients are `johnnickell/project-symfony`, `project-slim`, `project-laravel`,
  `project-yii`, and `project-codeigniter`. EPIC-00009 already permits **each individual** eligible managed
  starter to be offered after its own immutable stable SemVer release `>=1.0.0`, required generation manifest
  and verified snapshot. No all-five prerequisite or AccessControl 1.0.0 gate is introduced here.

### Accepted routing and future EPIC handoffs (remaining decisions below)

Human browser surfaces: authorized user/role/session/invitation management as planned; **approved in WF-032**:
self-service and authorized cross-user email-change request/cancel with mailbox confirmation and a Super Admin
Feature Flags screen for safe listing, immutable-name creation, testing Permission selection/rebinding, OFF/PREVIEW/ON
and guarded removal. Feature management has a separate `ADMIN_SAFE` Permission initially granted only to
`ROLE_SUPER_ADMIN`; cross-user email management has a distinct `SUPER_ADMIN_ONLY` Permission. Operational
console/worker surfaces: bootstrap, managed-policy preview/reconcile where necessary, Agent credentials,
sink/receipt/recovery/maintenance, key/cohort/restore operations and Feature discovery/provisioning/preparation.
Explicit non-UI: raw material, claim/lease, nonce, key destruction, background expiry/cleanup and other privileged
worker internals. Every path needs fresh server-side actor/target/delegation and exact Permission authority, safe
projections, bounded effects and transaction/recovery evidence; neither a UI nor an HMAC signature grants ongoing
authorization. Registered checks, current-code retirement evidence and separate action authorization must qualify
Feature activation and removal; no fake Feature is needed to render the screen.

**Approved future destinations (not yet EPIC records):** WF-033 approved a **new bounded linked EPIC** for the
shared AccessControl Agent operation/credential lifecycle, protected delivery/recovery, Agent management/creation
UI and first *working* managed Explorer doing real exploration. Project Manager and later Agent instances reuse the
boundary; full Workflow automation and a disposable Explorer-only test harness are not prerequisites. Real v0.5.0
consumer qualification is integrated into implementation before live use, not replaced by manual dogfooding.
EPIC-00006 retains Harness/template and browser Planning-Agent outcomes; the new EPIC supplies their credential
prerequisite without silently expanding its existing TASKs. A **separate later linked EPIC** delivers human-edited,
AI-assisted template authoring. WF-035 approved a **linked MVP Team/assignment EPIC** supplying EPIC-00007's
first full coordinated Workflow: one default Team per Repository, alternate eligible Team Lead handoff for a
*whole new TASK*, and strictly same-Team peer delegation thereafter. Distinct managed Team, Agent and template
administration Permissions are initially granted only to Super Admin; future scoped admins require separate
approval, not title-based authority. WF-032 approved **two other new linked EPICs** for human email change and
Feature Flags lifecycle after EPIC-00004's foundation, without rewriting its historical deferral or
TICKET-00030/00031. WF-034 has accepted a **separate linked Agent OS reference-consumer handoff EPIC**:
the maintainer **superseded staged external guidance** with one post-qualification v0.5.0 journey snapshot,
including suitable UI, backend references, working protected Swagger/OpenAPI and optional React suggestions for
the five starter maintainers; starters may adapt freely. Every supported public v0.5.0 use case must be
accounted for: in-scope capabilities need appropriate authorized UI **or** qualified protected non-UI treatment,
with approved exclusions explicit and no unassigned gaps. The already planned [TASK-00154](../tasks/00154-TASK.md) viewer is a prerequisite but is not implemented.
The maintainer explicitly expanded that TASK rather than creating a separate follow-up. Its original local-only
scope was explicitly aligned in ADR 0004, EPIC-00003, TICKET-00010, TICKET-00022 and TASK-00063. This project's
initial exact Swagger allowed set is `["local"]`; deliberate deployments can choose empty or other exact named
environments, including production and `test`, without weakening current `READ_SWAGGER` or endpoint checks.
APP_DEBUG is an independent strict boolean, intended true locally and false in staging/production here;
sanitized optional diagnostics can be enabled outside local, but never turn on Swagger or PHP error display.
Exact `APP_ENV=prod`/`production` with `APP_DEBUG=true` fails startup safely. Test database isolation is
unchanged. This is an explicit planning amendment, not live Swagger or deployed non-local exposure. Do not grill any EPIC before WF-034 finishes or descend to TICKET/TASK planning.

## Decisions so far

1. **[WF-032 — Reconcile AccessControl coverage with accepted Agent OS scope](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) is closed.** Self-service and Super Admin cross-user email-change request/cancel with mailbox confirmation, distinct protected management Permission, and Super Admin Feature Flags management (immutable-name creation, testing Permission, OFF/PREVIEW/ON, guarded removal) with initially Super Admin-granted `ADMIN_SAFE` management Permission are approved. Two separate linked new EPIC destinations explicitly extend EPIC-00004's deferred scope; sensitive operational paths are protected non-UI, unsupported features are not invented.
2. **[WF-033 — Define live Agent operation adoption and safe surfaces](tickets/WF-033-define-agent-operation-adoption-boundary.md) is closed.** A new linked Agent lifecycle/management EPIC supplies the real credential boundary and first working Explorer; a separate later linked EPIC owns AI-assisted, human-approved custom-template authoring. Agents/templates can be incrementally dogfooded before full Workflows; actual current-contract consumer security qualification accompanies owned implementation, not a separate per-template harness.
3. **[WF-034 — Set the Agent OS reference-consumer and starter handoff contract](tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) is open.** A separate Agent OS-owned EPIC supplies one **post-qualification** v0.5.0 consumer/UI snapshot with backend references, actually working protected Swagger/OpenAPI documentation/requests and adaptable React suggestions. The earlier staged unqualified external handoff proposal is superseded. UI is for appropriate human actions; sensitive operational paths stay protected non-UI and all supported public v0.5.0 use cases need an approved disposition. Starter implementation/certification/release is not Agent OS acceptance. Expand TASK-00154 for exact configurable allowed environments, initially `["local"]` here; debug remains a separate value. Safe non-local debug is approved independently of Swagger; exact `prod`/`production` plus APP_DEBUG=true must fail startup. `test` is Swagger-ineligible unless explicitly listed. The existing TASK/parents/ADR are now explicitly aligned as planning, not implemented; settle snapshot revision/evidence.
4. **[WF-035 — Define project-linked Teams and available Agent selection](tickets/WF-035-define-project-teams-and-agent-selection.md) is closed.** Teams serve Repositories, one Team per Agent; MVP Triage can hand a *whole* independent TASK to an idle Lead on another associated Team, but all subsequent delegation stays within the chosen Team. A human-selected Pending start is unclaimed until revalidated admission; dependent TASKs require verified base inclusion. Team/Agent/template administration uses distinct current managed Permissions initially granted only to Super Admin; future scoped admins need separate authority planning. A linked Team/assignment EPIC is an MVP supplier to EPIC-00007; autonomous TASK selection is v2, not part of this MVP.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) | Reconcile AccessControl coverage with accepted Agent OS scope | wayfinder:grill | HITL | Closed | — |
| [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md) | Define live Agent operation adoption and safe surfaces | wayfinder:grill | HITL | Closed | [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) |
| [WF-034](tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) | Set the Agent OS reference-consumer and starter handoff contract | wayfinder:grill | HITL | Open | [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md), [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md) |
| [WF-035](tickets/WF-035-define-project-teams-and-agent-selection.md) | Define project-linked Teams and available Agent selection | wayfinder:grill | HITL | Closed | [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-032 (coverage and accepted-scope owner) → WF-033 (Agent adoption and surface)
                                    WF-032 + WF-033 → WF-034 (reference handoff)
                                             WF-033 → WF-035 (Team assignment/selection)
WF-032 + WF-033 + WF-034 + WF-035 → approved bounded EPIC handoff(s)
```

## Frontier

[WF-034 — Set the Agent OS reference-consumer and starter handoff contract](tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md)
is the current decision interview. **Accepted:** one separate linked Agent OS-owned reference handoff EPIC,
with a single snapshot **after** the entire approved v0.5.0 consumer journey including UI is implemented and
qualified, not staged unqualified starter publication. Give five maintainers adaptable backend references, actual
Swagger/OpenAPI documentation with a working protected Try it out viewer, and React suggestions. Their adoption
and release are not Agent OS acceptance gates; EPIC-00009's per-starter stable SemVer `>=1.0.0` eligibility is
independent. The private export currently covers only two public-safe operations; TASK-00154's configured
`SUPER_ADMIN_ONLY` viewer is planned, not live, and must precede handoff. **Accepted:** all supported public
v0.5.0 consumer use cases need a truthful qualified or approved excluded disposition; appropriate human UI is
required, but secret/operational paths remain protected non-UI. The maintainer explicitly chose to expand
TASK-00154 for configurable allowed environments rather than a separate successor. Its TASK, parents,
ADR 0004 and security-envelope TASK-00063 have been **explicitly aligned as planning**; none is implemented.
This project's initial exact Swagger set is `["local"]`; other environment opt-ins may differ by deployment
or starter. APP_DEBUG is independently configured, intended true locally and false in staging/production here;
sanitized debug can be enabled non-locally, while exact `prod`/`production` with APP_DEBUG=true fails startup.
`test` is ineligible unless expressly listed, with test database isolation intact. **Next:** settle the
final handoff snapshot revision and attributable evidence, without weakening qualified consumer scope.

## Not yet specified (fog)

- Which real Agent OS Feature declaration/runtime use warrants enablement; the approved screen may have an empty
  state or create OFF definitions without inventing a fake Feature or activating an unregistered runtime check.
- Exact existing/deployed Agent or Feature data outside this repository and the actual protection/restore tools;
  owner inventory and runtime evidence are required before enabling effects, not assumptions from empty local data.
- Exact UI routes, permission IDs, PostgreSQL schema, sink implementation, worker topology and conformance run
  schedule belong to later approved EPIC → TICKET → TASK planning; unverified source/package drift must be
  requalified at implementation.

## Out of scope

No upstream AccessControl API edits, package release/major-version decision, startup migration or credential
issuance; no starter source edits/tests/certification/tags/releases, all-five eligibility gate, product endpoints,
Feature toggling or deployment in this Wayfinder decision; no autonomous Triage TASK selection, cross-Team peer
delegation inside a TASK, or unapproved `WORKSPACE_ADMIN`/`REPOSITORY_ADMIN`/`PROJECT_ADMIN` grants; no automatic
rewriting of EPIC-00004/00006/00007/00008/00009, ADR 0001/0002, the closed vision/triage maps or pre-existing
TASKs. No EPIC creation or TICKET/TASK decomposition in this decision-resolution pass.
