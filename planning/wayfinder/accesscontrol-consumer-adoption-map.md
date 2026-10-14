# Wayfinder Map: Complete Fight AccessControl consumer adoption

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map summarizes linked resolutions and the approved planning handoff.

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

### Accepted routing and future EPIC handoffs

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

**Approved destinations (all five EPICs written):** the maintainer explicitly combined WF-033's Agent
operation/credential and WF-035's Team/assignment destinations in
[EPIC-00014 — Deliver the working managed Agent team](../epics/00014-EPIC.md), superseding their original
separate-EPIC handoff and the assumption that Explorer use precedes Teams. The first real Explorer must belong
to one Team and use a registered Repository; no unassigned bootstrap Agent. EPIC-00014's **full completion bar**
requires all eight managed roles to perform their intended work in real authorized product journeys, not just
profile definitions or first-Explorer proof. EPIC-00006/00007/00008/00012/00013 retain their owned Harness,
Planning, Workflow, Triage, review, QA, hotfix and release implementations; EPIC-00014 supplies missing Agent/
Team integration and refuses a complete-team claim until those paths work. Full Workflow automation is not a
prerequisite to the *first Explorer use*, but is required for the complete team's acceptance. Team membership,
Repository default/alternate **whole-TASK** intake and same-Team delegation are included in EPIC-00014. Distinct
managed Team, Agent and template administration Permissions are initially granted only to Super Admin; future
scoped administrators need separate approval. [EPIC-00017](../epics/00017-EPIC.md) separately owns the
human-edited, AI-assisted template editor, not the eight initial managed templates required by EPIC-00014. WF-032
approved **two other new linked EPICs** after EPIC-00004's foundation. The human email-change destination
is now [EPIC-00015](../epics/00015-EPIC.md), and Feature Flags is now
[EPIC-00016](../epics/00016-EPIC.md). Neither retroactively rewrites EPIC-00004's historical deferral or
TICKET-00030/00031. In the approved EPIC-00015 grill, the maintainer
specified one purpose-bound new-mailbox secret used on the non-mutating landing request and again on explicit
confirmation; afterwards login uses the new address and existing password, or a **separate** password reset.
The old mailbox receives secret-free notices on request and completion, including administrator-initiated
changes. No request, confirmation, notice, provider or UI is live merely because this EPIC was written.
The EPIC-00016 grill further approved package-backed `FeatureFlagMiddleware` for matched HTTP Actions:
OFF and PREVIEW-ineligible return 404; ON and PREVIEW-eligible continue under ordinary action authorization.
One authenticated endpoint lists **all** Feature states and bound testing Permissions to signed-in users with
server-computed per-user `available`, used alongside `/me`'s planned ordinary Permission-name projection for
frontend visibility. This expands WF-032's admin-only management *reads*, not its management writes or the
requirement for current server-side checks. When an ON Feature becomes permanent, a separately planned chore
removes all code references and deploys their removal **before** guarded record retirement. An empty installed
catalog is honest; no fabricated Feature or extra proof-only project is required to claim that the reusable
capability was built. The maintainer has now selected `ai-template-proposals` under EPIC-00017/TICKET-00077;
that owner integrates it and supplies real-use evidence. Selection is not implementation or live enablement. The separate AI-assisted template-authoring grill produced
[EPIC-00017](../epics/00017-EPIC.md): after the approved initial managed template seeds, Agent templates are
database-authoritative, including Super Admin-reviewed browser revisions of the starting roles and new roles.
Publishing a revision does not change existing Agents; one-time Super Admin-confirmed additions-only or full
Permission sync follows visible drift. New Team-assigned Agents may try small, real qualified manual assignments
before a Workflow is deliberately built for the role. `SUPER_ADMIN_ONLY` controls administration of these
entities in the UI, not ordinary self-service or Agent work; it never allows assigning protected Permissions
to an Agent. Future custom Workspace authority and seed-file retirement need separate approval. This explicitly
supersedes the prior repeat-source-reconciliation assumption for Agent templates only, without moving Planning
Markdown or generic Skills authority. WF-034's separate reference-consumer handoff is
[EPIC-00018](../epics/00018-EPIC.md): the maintainer **superseded staged external guidance** with one
post-qualification v0.5.0 journey snapshot,
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
unchanged. This is an explicit planning amendment, not live Swagger or deployed non-local exposure. WF-034's
immutable reference-consumer snapshot is approved only **after** the complete qualified v0.5.0 journey; it
identifies the actual AccessControl lock/source, Agent OS commit, verified OpenAPI hashes and redacted consumer
evidence by capability. Changes require impact review and a newly qualified bundle, not silent receipt reuse. The EPIC-00018 grill
also clarified that a particular live flagged product capability is **not** a prerequisite: qualify and
accurately present the Feature infrastructure without inventing a Feature or requiring a special caveat about
one not yet being selected. All five EPIC handoffs and their TICKET decompositions are now approved;
separate TASK decomposition is the next map-wide phase.

## Decisions so far

1. **[WF-032 — Reconcile AccessControl coverage with accepted Agent OS scope](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) is closed.** Self-service and Super Admin cross-user email-change request/cancel with mailbox confirmation, distinct protected management Permission, and Super Admin Feature Flags management (immutable-name creation, testing Permission, OFF/PREVIEW/ON, guarded removal) with initially Super Admin-granted `ADMIN_SAFE` management Permission are approved. Two separate linked new EPIC destinations explicitly extend EPIC-00004's deferred scope; sensitive operational paths are protected non-UI, unsupported features are not invented.
2. **[WF-033 — Define live Agent operation adoption and safe surfaces](tickets/WF-033-define-agent-operation-adoption-boundary.md) is closed.** Its Agent lifecycle and first Explorer boundary now feeds the explicitly consolidated [EPIC-00014](../epics/00014-EPIC.md); its separate later AI-assisted template editor is [EPIC-00017](../epics/00017-EPIC.md). Live current-contract consumer security qualification accompanies owned implementation, not a separate test harness.
3. **[WF-034 — Set the Agent OS reference-consumer and starter handoff contract](tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) is closed.** A separate Agent OS-owned EPIC supplies **one immutable post-qualification** complete v0.5.0 consumer/UI snapshot with exact package lock/source, Agent OS commit, verified private OpenAPI hashes and redacted per-capability evidence, backend references, working protected Swagger/Try it out and adaptable React suggestions. No staged unqualified external guidance or starter certification/release is approved. Appropriate human UI and protected non-UI operational paths both need honest dispositions. TASK-00154 and its parents/ADR were explicitly aligned as planning for exact configured Swagger environments (initially `["local"]` here), independent safe APP_DEBUG and startup failure under `prod`/`production` plus debug true. Nothing is implemented by this resolution.
4. **[WF-035 — Define project-linked Teams and available Agent selection](tickets/WF-035-define-project-teams-and-agent-selection.md) is closed.** Teams serve Repositories, one Team per Agent; whole-TASK alternate-Team intake is permitted, while subsequent delegation stays within the chosen Team. Pending start is unclaimed until revalidated admission; dependent TASKs require verified base inclusion. Its Team/assignment EPIC handoff has been explicitly consolidated into [EPIC-00014](../epics/00014-EPIC.md) without rewriting existing Workflow ownership. Autonomous TASK selection remains v2.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) | Reconcile AccessControl coverage with accepted Agent OS scope | wayfinder:grill | HITL | Closed | — |
| [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md) | Define live Agent operation adoption and safe surfaces | wayfinder:grill | HITL | Closed | [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md) |
| [WF-034](tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md) | Set the Agent OS reference-consumer and starter handoff contract | wayfinder:grill | HITL | Closed | [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md), [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md) |
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

No open Wayfinder decision remains. This map is **Closed**; its combined first destination produced
[EPIC-00014](../epics/00014-EPIC.md) and its email-change destination produced
[EPIC-00015](../epics/00015-EPIC.md), and its Feature Flags destination produced
[EPIC-00016](../epics/00016-EPIC.md), and its template-authoring destination produced
[EPIC-00017](../epics/00017-EPIC.md), and its final reference-consumer handoff produced
[EPIC-00018](../epics/00018-EPIC.md). All five approved EPIC destinations and their accepted TICKET
decompositions are now written. Continue with separate **TASK decomposition**, not implementation execution.
[EPIC-00014](../epics/00014-EPIC.md)'s seven requirement
[TICKETs](../epics/00014-EPIC.md#tickets), [EPIC-00015](../epics/00015-EPIC.md)'s four requirement
[TICKETs](../epics/00015-EPIC.md#tickets) and [EPIC-00016](../epics/00016-EPIC.md)'s five requirement
[TICKETs](../epics/00016-EPIC.md#tickets) were approved and written. Email change retains the hard atomic
old-mailbox notice seam before request/confirmation exposure. Feature Flags retains full discovery,
preparation, registered runtime enforcement, complete catalog, management and CURRENT-code retirement
without an invented product Feature. [EPIC-00017](../epics/00017-EPIC.md)'s five requirement
[TICKETs](../epics/00017-EPIC.md#tickets) are also approved and written: database template authority,
human publication/ancestor review, AI proposals, explicit one-time Permission reconciliation and new-role
manual use through existing Agent creation. Publication changes no existing Agent or session.
[EPIC-00018](../epics/00018-EPIC.md)'s three requirement
[TICKETs](../epics/00018-EPIC.md#tickets) are also approved and written: current coverage/qualification,
reproducible actual references and one immutable controlled-access maintainer handoff with successor
requalification. Final external publication still requires the complete qualified consumer journey.
All 24 requirements ([TICKET-00059](../tickets/00059-TICKET.md) through
[TICKET-00082](../tickets/00082-TICKET.md)) have accepted TICKET records.
[TICKET-00059](../tickets/00059-TICKET.md)'s seven-TASK split is now approved and written as
[TASK-00227](../tasks/00227-TASK.md) through [TASK-00233](../tasks/00233-TASK.md).
[TICKET-00060](../tickets/00060-TICKET.md)'s eight-TASK split is approved and written as
[TASK-00234](../tasks/00234-TASK.md) through [TASK-00241](../tasks/00241-TASK.md). Its concrete authority/key/sink/
lifecycle/worker/maintenance components are linked into TASK-00231–00233.
[TICKET-00061](../tickets/00061-TICKET.md)'s six-TASK split is also approved and written as
[TASK-00242](../tasks/00242-TASK.md) through [TASK-00247](../tasks/00247-TASK.md). Its authentication/activation/
current-use and first-party MCP components now supply the exact TICKET-00061 blockers for TASK-00231–00233 and
TASK-00241. Those existing TASKs are `ready-for-agent` behind unfinished blockers, not implemented or qualified.
TASK-00244/00245 retain `needs-info` for actual Team/Harness/host/origin owners; TASK-00247 also needs actual named
Agent/template/manual-session/provider prerequisites. Components consume early persistence/admission work;
writer/switch/restore acceptance consumes components; TASK-00241 follows that qualification; TASK-00247's real
Explorer integration follows operational readiness. No whole-parent cycle or premature enablement is introduced.
TASK-00171 consumes the shared MCP boundary while retaining judgment integration; TICKET-00065 consumes real
Explorer evidence without a duplicate engine or run. At that checkpoint, 21 TICKETs still needed accepted TASK
decomposition; the subsequent approved Team split below leaves 20.
No working product journey, live consumer qualification or published reference bundle follows from these records.

The maintainer subsequently selected the existing [TICKET-00034](../tickets/00034-TICKET.md) as an explicit
prerequisite-planning detour and approved [TASK-00248](../tasks/00248-TASK.md) through
[TASK-00250](../tasks/00250-TASK.md): reviewed eight-role definitions, exact profile resolution and role-session
integration. TASK-00247 now consumes TASK-00250; the Triage/Hotfix owners consume their applicable slices.
Actual database-template/resource/model/session/Agent mappings remain held. TICKET-00075 consumes the reviewed
definitions and supplies database authority to the resolver, avoiding a whole-parent cycle or duplicate catalog.
That detour did not reduce this map's then-21 remaining TICKET decompositions or complete older Harness requirements.

The maintainer then approved [TICKET-00062](../tickets/00062-TICKET.md)'s five-TASK split:
[TASK-00251](../tasks/00251-TASK.md) establishes protected Teams on registered Repositories;
[TASK-00252](../tasks/00252-TASK.md) manages associations/defaults;
[TASK-00253](../tasks/00253-TASK.md) supplies one-Team membership and admission fences;
[TASK-00254](../tasks/00254-TASK.md) supplies guarded idle-transfer/removal; and
[TASK-00255](../tasks/00255-TASK.md) integrates actual browser Team/roster administration.
At that approval TASK-00253–00255 retained `needs-info` for the supported creation/membership seam, actual
session/commitment participants and named-Agent creation; the subsequent creation mapping below resolves
TASK-00255's direct mapping only. The package's internal issuance transaction does not imply atomic Team
binding; unknown seams block creation rather than permit committed orphan Agents or post-commit repair.

TASK-00244/00247/00250 now consume TASK-00253 and TASK-00231 consumes TASK-00254's real writer chain. These
mappings do not clear actual contract/participant qualification holds. Creation/current-use consume early Team
primitives, not final roster UI; named creation will supply TASK-00255. No full Workflow automation or Planning
cutover gate is added to first manual use. All new TASKs remain unranked, with active work unchanged. No Team,
Agent, grant, session, runtime or live proof follows. That split brought the map to four accepted TASK
decompositions, leaving 20 at that checkpoint.

The maintainer subsequently approved [TICKET-00063](../tickets/00063-TICKET.md)'s five-TASK split:
[TASK-00256](../tasks/00256-TASK.md) protects human management/name updates;
[TASK-00257](../tasks/00257-TASK.md) previews exact reviewed named creation;
[TASK-00258](../tasks/00258-TASK.md) creates one Team-bound Agent with complete initial authority from retained
intent; [TASK-00259](../tasks/00259-TASK.md) exposes safe current administration/lifecycle; and
[TASK-00260](../tasks/00260-TASK.md) integrates accessible real browser administration and eight-template creation.

TASK-00258 retains `needs-info` for supported template/scope/current-authority/initial-assignment composition,
in addition to TASK-00253's one-Team-at-creation seam. The inspected provision request contains name/destination,
its service generates identity and commits internally, and full Permission replacement owns a separate transaction.
Sequential calls, outer nesting, hidden audit policy or post-commit orphan repair do not establish the required
binding or safe completion. Any supported intermediate assignment phase must remain incomplete and unable to
activate/use; no committed Agent may be teamless. TASK-00260 retains the exact Triage-definition/registration
primitive handoff to the existing seed/resource owner, not the completed diagnosis that itself needs an Agent.

TASK-00247/00250/00255/00200/00206 now consume TASK-00258. TASK-00255 becomes `ready-for-agent` behind these
unfinished blockers, resolving its direct creation mapping only; all actual contract/participant holds remain.
Other held consumers keep `needs-info`. TASK-00231 consumes TASK-00259 and its management/creation writer chain;
full readiness follows qualification and final TASK-00260 browser acceptance follows readiness. No primitive
depends on a completed role/diagnosis, browser administration, full Workflow or Planning cutover. Existing-Agent
drift operations remain TICKET-00078, template publication remains EPIC-00017 and working roles remain TICKET-00065.

That split brought the map to five written TASK decompositions, leaving 19 at that checkpoint. No Agent,
binding, direct Permission, credential, session, live qualification or operational authority followed.

The maintainer subsequently approved [TICKET-00064](../tickets/00064-TICKET.md)'s five TASKs:
[TASK-00261](../tasks/00261-TASK.md) resolves selected-work Agent eligibility;
[TASK-00262](../tasks/00262-TASK.md) recommends fair default/alternate whole-Team intake;
[TASK-00263](../tasks/00263-TASK.md) binds selection through the existing authoritative Start;
[TASK-00264](../tasks/00264-TASK.md) enforces same-Team phase admission/independence; and
[TASK-00265](../tasks/00265-TASK.md) integrates durable withdrawable Pending start through existing entry paths.
TASK-00261/00263/00264 retain `needs-info` for lower-level commitment/resource, configured-base inclusion,
current admission and contribution/fresh-session primitives; consumer planning cannot qualify those facts.

Fairness prefers an eligible default Team, then least-recent committed Team/Lead intake among eligible equivalents
with stable identity ties. Owning Workflow history advances only on committed new intake, not preview, denial,
rollback or original-command duplicates. Pending intent remains one finite human-selected consent, neither claim
nor queued Runner, with fresh start checks and serialized withdrawal. Waiting for PR review/merge alone does not
occupy an Agent; unknown activity still holds. Done is not proof of required change inclusion on the configured
base, and a fresh reviewer name/session does not erase design/build/evidence contribution.

TASK-00141 now consumes TASK-00264, TASK-00204 explicitly consumes TASK-00263 and TASK-00207 consumes
TASK-00264 without clearing missing managed Review/QA/publication. TASK-00139 retains upstream Start/Markdown
eligibility; TASK-00205 supplies the browser gesture and later TASK-00265 extends its owned pending/intake path.
No second queue, claim, eligibility algorithm, automatic starter or full database Planning prerequisite is added
to core terminal selection. The first Awaiting review milestone and authored execution orders are unchanged.

TASK-00254's lower-level commitment-writer holds remain; do not reverse-block those suppliers, TASK-00244 or
TASK-00241/manual Explorer on selection or complete Workflow journeys. Every newly enabled participant still
requires refreshed current shared-fence/cohort evidence. No cross-Team peer delegation, autonomous TASK pick,
Agent, grant, Workflow, process, merge or live qualification follows from these records.

That split brought the map to six written TASK decompositions, leaving 18 at that checkpoint.

The maintainer subsequently approved [TICKET-00065](../tickets/00065-TICKET.md)'s seven integration TASKs:
[TASK-00266](../tasks/00266-TASK.md) connects Project Manager to human-governed browser Planning and consumes
TASK-00247's original real Explorer result; [TASK-00267](../tasks/00267-TASK.md) continues actual Team Lead/
Software Engineer work and human-selected Triage intake into independent same-Team Senior Engineer review;
[TASK-00268](../tasks/00268-TASK.md) integrates separate QA; [TASK-00269](../tasks/00269-TASK.md) integrates
bounded Release Manager PR publication; [TASK-00270](../tasks/00270-TASK.md) consumes the actual confirmed direct
Hotfix Engineer handoff; and [TASK-00271](../tasks/00271-TASK.md)/[TASK-00272](../tasks/00272-TASK.md) integrate
qualified library/application release paths independently.

TASK-00266–00269 retain `needs-info` for actual browser conversation/proposal and generic managed Review/QA/
publication owners. TASK-00270 waits on TASK-00207/00250/00241 and their unresolved capabilities. TASK-00271/00272
retain `needs-info` for real qualified projects/runbooks/providers and signer/environment/health prerequisites,
plus existing generic Review/QA and applicable merge-observation holds. Local Skills, template rows, fixture
success and EPIC-00008's terminal metadata do not qualify these paths. No target or production effect is authorized.

These final Agent/Team integrations consume the existing owners, implement only demonstrated missing bindings
and reuse attributable valid outcomes without another Explorer launch, incident, release, proof registry or
credential ceremony. They are not the missing generic primitives for TASK-00207/00215/00250/00264. Do not add
reverse cycles through completed role journeys or gate the first manual Explorer/terminal implementation on
browser Planning or full Review/QA/publication. Current shared qualification still applies to every enabled
participant; changed inputs require affected evidence refresh rather than relabeling old receipts.

The normal review → QA → PR integration chain is explicit; Project Manager, direct hotfix and the separate
release paths retain their own prerequisites. One actual release TASK/Workflow per candidate remains unchanged,
with separate human merge/signing/exact-effect authority. A PR alone is not a release/deployment, missing targets
stay unaccepted, and meaningful real QA cannot be replaced by all-N/A paperwork. TICKET-00065 maps all eight
role outcomes and the supporting Triage journey to actual owning evidence, without another parent acceptance gate.

All seven EPIC-00014 requirement TICKETs now have approved written TASK decompositions, not completed runtime.
That split brought the map to seven approved written TASK decompositions, leaving 17 at that checkpoint.
No Agent, grant, conversation, Workflow, review, PR, release, deployment or operational evidence was created.

The maintainer subsequently approved [TICKET-00066](../tickets/00066-TICKET.md)'s five slices:
[TASK-00273](../tasks/00273-TASK.md) qualifies supported atomic old-mailbox notice coordination;
[TASK-00274](../tasks/00274-TASK.md) records accepted request notices;
[TASK-00275](../tasks/00275-TASK.md) records completion notices bound to the original old recipient;
[TASK-00276](../tasks/00276-TASK.md) dispatches one protected exact notice; and
[TASK-00277](../tasks/00277-TASK.md) operates scheduled recovery, safe inspection and retention.

TASK-00273 remains `needs-info` for the supported package seam/owner resolution and any required tagged release.
The inspected request/confirmation commit before their events, which cannot establish the required atomic
old-recipient/generation handoff. No after-commit subscriber, nested transaction, audit side effect or vendor
patch may bypass this hold. Required state/audit/notice writes commit or roll back together; post-commit
publication failure is not evidence of rollback or authority to replay a mutation.

TASK-00276 consumes TASK-00274 and TASK-00040's shared mail capability, independently of TASK-00275.
TASK-00277 consumes both captures through TASK-00275, dispatch through TASK-00276 and TASK-00024's existing
facilities; it retains `needs-info` for real provider/schedule, protected target and retention/failure-policy
enrollment. These informational effects are not a second credential queue. Cancellation/credential expiry
cannot erase accepted notices, and request delivery cannot purge an original binding still needed by confirmation.
Finite recovery preserves original identities; provider acceptance is not mailbox receipt or exactly-once proof.

TICKET-00067 will consume request capture/readiness and retain self/admin entry authority; TICKET-00069 consumes
completion capture/readiness and retains explicit confirmation/login return. Both still need TICKET-00068's
credential path. Reuse qualified mail/schedule primitives without merging notice and credential lifecycle or
introducing whole-parent cycles. Final browser journeys are consumers, not prerequisites of notice primitives.
Missing production authority or a real provider never becomes success from a fixture, catcher or historical receipt.

That split brought the map to eight approved written TASK decompositions, leaving 16 at that checkpoint.
No email change, notice, provider/schedule enrollment, upstream edit or operational qualification was performed.

The maintainer subsequently approved [TICKET-00067](../tickets/00067-TICKET.md)'s five slices:
[TASK-00278](../tasks/00278-TASK.md) establishes current self/admin authority and a distinct protected managed
Permission; [TASK-00279](../tasks/00279-TASK.md) delivers coherent scoped status and a real read-only account
panel; [TASK-00280](../tasks/00280-TASK.md) delivers self request;
[TASK-00281](../tasks/00281-TASK.md) delivers exact confirmed cancellation; and
[TASK-00282](../tasks/00282-TASK.md) integrates cross-user operations into the existing user-detail journey.

At that checkpoint, TASK-00280 consumed TASK-00274/00277 and retained `needs-info` for unmapped TICKET-00068
issuance/cipher/provider/recovery suppliers, alongside the unresolved notice seam. TASK-00281 retains `needs-info` for supported exact-
intent/generation fencing, original-outcome observation and required atomic self-cancellation audit. The inspected
cancel command takes only actor/target IDs and selects the latest eligible change; its handler audits only
administrator calls. A UI revision/preflight or TASK-00273's separate request/confirmation seam does not establish
these guarantees. Seek supported owner/tagged resolution, never hidden repository policy or nested transactions.

The administration callback cannot authenticate supplied actor IDs and skips actor == target; shared trusted
self/cross-user checks apply at all supported entries. TASK-00278 preserves the complete managed catalog with
its distinct `SUPER_ADMIN_ONLY` extension, not role-name authority or a direct Agent grant. TASK-00279 composes
current User/reservation/grant/delivery facts without exposing grants or mutating on read; delivery is not usable
change or confirmation. Browser state stays volatile under one current-principal owner and local generation fences.

TASK-00280 waits for qualified notices and new-mail sending; TASK-00281 cancellation does not depend on sending
or the request journey and cannot affect a successor. Post-commit exception/unknown acknowledgement does not prove
rollback or allow mutation replay; accepted request notices survive cancellation. TASK-00282 uses separate detail/
action permissions, explicit target/intent confirmation and attributable shared operations, never a credential
for the administrator or confirmation bypass. TICKET-00069 retains actual confirmation and login return.

That split left exact TICKET-00068 suppliers unmapped without making them or notice primitives depend on
completed account/admin/browser journeys. No second credential queue, profile editor or final proof-only TASK
was introduced, and no Permission, API, UI, request, cancellation or credential was enabled.

The maintainer subsequently approved [TICKET-00068](../tickets/00068-TICKET.md)'s five slices:
[TASK-00283](../tasks/00283-TASK.md) protects issuance material;
[TASK-00284](../tasks/00284-TASK.md) enrolls one shared real mail transport;
[TASK-00285](../tasks/00285-TASK.md) delivers one exact new-mailbox link;
[TASK-00286](../tasks/00286-TASK.md) extends bounded recovery/inspection; and
[TASK-00287](../tasks/00287-TASK.md) operates actual scheduled credential delivery/recovery.

TASK-00274 consumes TASK-00283, which does not depend on finished capture or credential operation. TASK-00284
consumes TASK-00040 and owns the shared real-provider primitive previously unmapped inside TASK-00277. The notice
TASK consumes that transport but retains its own schedule, retention and both-stage acceptance. TASK-00287 also
consumes TASK-00284 plus TASK-00286 and accepted request capture. Neither completed recovery journey blocks the
other. TASK-00280 gains TASK-00287 as a concrete supplier, not proof of runtime readiness. Status/cancellation
remain independent of sending, and final account/admin/confirmation journeys do not reverse-block these primitives.

The inspected cipher, registrations, runner dispatch and narrow worker audit allowance lack email delivery.
These slices extend actual supported boundaries, not package grant/claim/backoff policy. Provider invocation has
no User ID/expiry; fixed link composition resolves immutable facts through exact public delivery-ID lookup, never
address/time inference or invented fields. Keep secrets out of status/logs/evidence and old-mail notices, with
configured HTTPS, stable original delivery identity and honest at-least-once/provider-acceptance limits.

TASK-00287 schedules independent key/provider-free expiry because delivery preflight can fail before cleanup.
Reuse existing exact reservation/authority expiry, preserve outstanding human-delivery key access and qualify
actual process restart, receipt and provider/outcome uncertainty. TASK-00284/00287 remain `needs-info` for actual
provider/sender/mailbox and worker/schedule/key/target authority/qualification. TASK-00273's atomic notice seam and
TASK-00281's cancellation-generation/self-audit hold are not resolved by this mapping. TICKET-00069 still owns
safe landing, explicit confirmation, session invalidation and login return; link delivery alone is not that journey.

That split brought the map to ten approved written TASK decompositions without provider/schedule enrollment,
credential generation or live effects.

The maintainer subsequently approved [TICKET-00069](../tickets/00069-TICKET.md)'s two use cases:
[TASK-00288](../tasks/00288-TASK.md) opens confirmation links without account-state changes, consuming
TASK-00285 and TASK-00051; [TASK-00289](../tasks/00289-TASK.md) confirms explicitly and returns to fresh login,
consuming TASK-00288, TASK-00275, TASK-00277, TASK-00287, TASK-00282 and TASK-00063.

The landing captures the exact purpose fragment in bounded volatile memory and promptly removes URL/history
material through the existing bootstrap. Public mail navigation needs no session/Permission; GET/prefetch never
starts confirmation, sending or automatic authentication restoration/refresh. Neutral guidance deliberately
omits the optional validity query. Deliberate protected POST submits the same secret, with shared request/CSRF/
rate controls and explicit public Action classification, not a new password form or administrator bypass.

TASK-00289 retains `needs-info` for atomic notice/reservation guarantees and actual operational qualification.
The inspected `confirmEmail()` checks latest usable grant, credential and destination equality without explicitly
comparing the bound reservation revision or calling `matchesReservation()`. TASK-00273/00275 now carry exact
binding/supported-writer/same-address-ABA qualification; this is not a demonstrated exploit or upstream repair
authority. Missing support holds, without vendor patches, copied policy, hidden repository effects, preflight-only
checks or nested transactions. The service returns `void` and emits after commit; unknown acknowledgement or
post-commit failure is not rollback or permission for replay.

TASK-00275 owns atomic consumption/promotion/authentication/session/audit/completion intent and original-recipient
binding; required notice failure rolls the transition back. Later provider delay cannot undo it or preserve revoked
sessions. Once operationally qualified, confirmation of a held usable credential needs no online mail provider or
delivery decryption. Existing auth/principal/cache/cookie/tab owners retire old authority and fence late responses,
without instantaneous cross-tab promises or changes to unrelated server identities. Existing new-email/password
login and separate reset request remain distinct; no session comes from confirmation proof.

TASK-00282 supplies real self/admin initiation and cancellation. TASK-00277/00287 remain independent operational
suppliers; reuse attributable valid mailbox/restart evidence and refresh affected integration. No complete browser
journey reverse-blocks those primitives, no second queue/provider/client owner is created and no proof-only final
TASK or parent ceremony is added. All four EPIC-00015 TICKETs now have approved TASK decompositions, not live adoption.

That split brought the map to eleven approved TASK decompositions without opening a route, confirming an
account, revoking a session or performing live effects.

The maintainer subsequently approved [TICKET-00070](../tickets/00070-TICKET.md)'s three use cases:
[TASK-00290](../tasks/00290-TASK.md) discovers complete Feature references for exact code scopes after TASK-00126;
[TASK-00291](../tasks/00291-TASK.md) provisions missing Features atomically through protected operations after
TASK-00290 and TASK-00036; [TASK-00292](../tasks/00292-TASK.md) inspects fresh candidate preparation using that
qualified operational/persistence boundary. All three are features, ready behind listed blockers and unranked.

Reuse existing Feature repositories/reference fences and attributable scoped evidence rather than another storage
system or a proof-only qualification TASK. The inspected consumer has no composed Feature provisioning/preparation
handlers or scanner. Exact CANDIDATE/CURRENT discovery validates native Attributes and explicit registrations
across the supported application/module surface; partial, inaccessible, changed, malformed or wrong-scope input
cannot become complete empty success. Unknown running-version coverage holds; TICKET-00074 retains actual
retirement-time deployment/overlap qualification, not substitution of candidate absence or local scan receipts.

Protected provisioning uses the package's one canonical transaction. Only missing names require the configured
existing default Permission; all new definitions commit OFF together and existing identity/settings/binding/
revision survive. TASK-00291 completes affected all-path reference integrity, including managed-policy preview/
final removal, unpaginated all-status checks, rebind/no-op and competing-pass races and whole-pass rollback.
Preserve applicable cohort-first authority ordering without a new fence owner or full live-Agent prerequisite.
Creation facts are best-effort after commit; unknown acknowledgement/publication failure is not rollback or
permission to replay facts. Protect the package's original-command/error-bearing failure event diagnostics.

Separate read-only validation is usable before provisioning. Check every reference against fresh stored state
and original bound Permission ID, with safe missing/broken/invalid categories and explicit malformed hydration
qualification. Infrastructure/discovery failure is not absence; no fallback-by-name, stale cache or auto-repair.
Prepared OFF is an exact candidate/target observation, not availability, distributed readiness, deployment approval
or CURRENT-code retirement proof. No product Feature is invented to demonstrate the reusable infrastructure.

TICKET-00071 consumes discovery/registration, persistence and preparation before rollout; TICKET-00073 consumes
safe reference/preparation primitives; TICKET-00074 consumes CURRENT discovery and final reference release with
separate cleanup/deployment authority. No finished UI/runtime/retirement or managed-release journey reverse-blocks
these suppliers. Each slice owns affected behavior/contract evidence and the full gate; tooling/configuration are
qualified directly, not product-tested as text or wiring. Missing support holds the affected path for owner resolution.

That split brought the map to twelve approved TASK decompositions and EPIC-00016 to one of five,
without running a scanner/provision/query, changing a catalog or enabling a Feature/deployment.

The maintainer subsequently approved [TICKET-00071](../tickets/00071-TICKET.md)'s two enforcement paths:
[TASK-00293](../tasks/00293-TASK.md) guards matched HTTP Actions with current Feature availability after
TASK-00117 and TASK-00292; [TASK-00294](../tasks/00294-TASK.md) enforces registered checks outside HTTP Actions
after TASK-00293 and TASK-00245. Both are unranked features, ready behind blockers. Existing first-party trust/
current-use holds remain transitive through TASK-00245, without delaying human HTTP enforcement.

Inspect final matched FQCN/native method metadata without resolving/constructing Actions. Apply the package
Feature decision after routing/trusted authentication and before dispatch, retaining ordinary permissions,
target/request controls, public classification and route/method misses. OFF/ineligible PREVIEW denies with 404;
positive availability is no authorization grant. No new public exception, Agent-on-JWT route or scheme fallback.

The package rereads Feature and bound Permission ID on every call, including OFF/ON, but the principal is an
immutable request snapshot. Fresh settings and fresh authority have distinct lifetimes; reuse the Agent provider
within one request without another nonce, and resolve current authority on later requests/jobs. Unknown/broken/
malformed/outage states are safe classified errors, not silent OFF/ON, raw diagnostics or a successful empty
Tool catalog. No stale identity-map/result cache, name-to-ID substitution or retroactive cancellation promise.

Non-Action enforcement uses the existing registration source, Common Tool registry and current caller/target/
grant boundary. Intersect Feature availability with ordinary Tool authority; filter before pagination/disclosure
and recheck invocation/qualified continuations before protected validation, restoration/consumption, progress and
handling. Direct alternatives need real guards; an Attribute, prior page or unknown worker identity is not
admission. Unsupported paths remain unavailable rather than borrowing credentials or inventing another owner.

TICKET-00072 consumes human availability without an unrelated Agent dependency. TICKET-00073 needs all applicable
entries covered before rollout; TICKET-00074 later removes all these declarations/registrations/checks under
separate cleanup/deployment authority. No runtime consumer reverse-blocks its auth/scanner/preparation/MCP supplier.
Each slice owns affected implementation and actual package/PostgreSQL/framework evidence plus the full gate;
controlled fixtures are not installed dummy Features/Tools or live-use claims. Tooling/configuration is qualified
directly, not product-tested as text. TICKET-00077 now owns the selected first Feature, `ai-template-proposals`.

That split brought the map to thirteen approved TASK decompositions and EPIC-00016 to two of five, without
enabling middleware, a route, Tool gate, worker, nonce, Feature or deployment.

The maintainer subsequently approved [TICKET-00072](../tickets/00072-TICKET.md)'s two paths:
[TASK-00295](../tasks/00295-TASK.md) reads a complete authenticated Feature catalog after TASK-00293;
[TASK-00296](../tasks/00296-TASK.md) gates browser Feature presentation with current catalog availability after
TASK-00295 and TASK-00051. Both are unranked features, ready behind blockers, without an Agent runtime dependency.

One authenticated `GET /api/v1/features` composes package listing/availability for all signed-in Users, not just
Dashboard/testing/management Permission holders. Return one bounded complete response with explicit total and
completeness, all OFF records and safe identities/settings/original binding IDs/nullable names/repair marker and
server availability. No client paging/filter/actor input, whole management-View serialization or edit revision.
Qualified missing binding is unavailable; inconsistent IDs/state, outages and uncertain completeness fail the read.

The current repository's separate row/count queries and later binding/availability reads need a qualified coherent
boundary. TASK-00295 owns it against actual writers using existing canonical connection/reference ownership,
not count equality, an assumed all-writer fence, stale snapshot or a new global catalog store. Preserve fresh
READ COMMITTED semantics and bounded resource/lock lifetime; no GET writes/repair, partial success or held browser
transaction. Establish finite row/page/query/byte/time limits and fail safely on overflow. Complete-empty is valid.

Browser integration uses one volatile private-data owner with the sole `/me` source, existing exact ordinary
Permission evaluation and context/catalog-generation fences. Gate outlets/loaders/navigation/controls/forms and
activation with current server availability. At most 60 monotonic seconds from request start, additionally bounded
by `/me` authority, governs freshness. Expiry/resume/invalidation retires affected data/late results before bounded
refetch; Feature-only invalidation also fences its dependent data/drafts. No persistence, broadcast authority,
second principal, name-to-ID shortcut, mutation replay or instant push/cross-tab consistency is claimed.

TICKET-00073 owns management UI/detail/mutations and their invalidation; TICKET-00074 owns removal/reintroduction
integration. Neither reverse-blocks these primitives. Each slice owns affected actual server/browser/accessibility
evidence and the full gate; controlled fixtures are not installed dummy product Features/routes. The first real
flagged capability is now `ai-template-proposals` under TICKET-00077. Tooling/configuration is validated directly,
not product-tested.

All new orders remain unranked and active work unchanged. With the subsequent splits through TICKET-00082 below,
**all 24 map TICKETs have approved written TASK splits**. Older Harness/managed review planning remains incomplete. All five EPIC-00016 TICKETs are decomposed, not implemented. Planning
exposed/fetched no catalog, changed no UI and enabled no Feature or deployment.

## Approved real Feature use and delivery-value amendment

The maintainer selected [EPIC-00017](../epics/00017-EPIC.md) /
[TICKET-00077](../tickets/00077-TICKET.md)'s actual AI proposal journey for `ai-template-proposals` and rejected
further TASKs for speculative features or infrastructure demonstrations. New TASKs must name the accepted
product outcome or concrete delivery blocker under planning/CONVENTIONS.md; necessary safety/verification stays
with that real path, not an extra proof-only project.

OFF prevents new proposal/regeneration admission; PREVIEW requires ordinary template-management authority plus
the bound testing Permission; ON retains ordinary authority. Human-only editing/publication and safe status,
result/cancellation/recovery for admitted attempts remain available when OFF. No new paid request may masquerade
as recovery. TICKET-00077 owns real-provider proposal → human prompt/Permission editing → explicit publication
and denied-provider-request evidence across actual supported admission paths.

Declare the actual Feature in product code and use protected OFF provisioning. Defer TICKET-00073's standalone
manual-create UI/API; its earlier three-TASK draft was not approved and is replaced by the single TASK below.
TICKET-00080 retains honest per-contract accounting for deferred `CreateFeature` exposure; do not silently
certify or permanently exclude it. That selection created no EPIC, TICKET, TASK, fake Feature or demonstration project. TASK-00290–00296 remain
upstream without reverse dependencies on the completed AI journey. The testing Permission/audience and rollout
readiness remain to be qualified, not automatically granted/enabled by this decision.

That selection changed neither planning counts nor active priorities. The accepted terminal-first TASK through Lead/Engineer to
Awaiting review remains the first execution milestone; this later product use does not precede or block it.

## Approved rollout-management TASK decomposition

[TASK-00297](../tasks/00297-TASK.md) delivers TICKET-00073 as one complete audience/status management browser/API
path for `ai-template-proposals`, after TASK-00036 and TASK-00296. It owns the approved `MANAGE_FEATURES`
ADMIN_SAFE extension initially granted only to managed Super Admin, protected detail/choices, package setters,
current authorization, revision-bound confirmation and actual catalog invalidation. No separate inspection,
policy, manual-create or demonstration TASK is introduced.

The TASK is unranked and `needs-info`: trusted code/target/preparation/applicable-entry readiness composition
and safe active broken-binding repair remain unqualified. Existing preparation is an observation, not deployment
authority; repair cannot demand that broken pre-state pass preparation or restore live access before safety checks.
Preserve package transaction ownership and distinct protected installation authority. TICKET-00077 owns real AI
admission/provider evidence and applicable non-HTTP coverage; neither finished AI authoring nor management
reverse-blocks reusable suppliers. TICKET-00074 consumes authority/detail/confirmation for separate retirement.

That management split brought the map to fifteen of 24 TASK decompositions and EPIC-00016 to four of five,
not implementation acceptance. Active work and the terminal-first milestone remain unchanged. Planning
created no Permission, Feature, UI, provider request or deployment.

## Approved guarded-retirement TASK decomposition

[TASK-00298](../tasks/00298-TASK.md) delivers TICKET-00074 as one complete retirement browser/server path after
TASK-00297. It consumes existing management authority/confirmation, discovery, reference persistence and browser
invalidation; package `RemoveFeature` owns stable-ID/revision removal after complete CURRENT absence. Full-state
comparison, atomic reference release, preservation of Permissions/grants and truthful conflict/uncertainty are
qualified with actual consumer/PostgreSQL/browser evidence within the same TASK.

The unranked feature remains `needs-info` for trusted all-running-version/module coverage and protection against
conflicting activation between discovery and deletion. Neither candidate preparation, a database reference lock
nor a package scope enum establishes deployment safety. Unknown support holds without a new platform, vendor
patch or invented dependency. Existing protected provisioning owns later reintroduction as a new OFF/revision-1
identity with the current existing default Permission; old state/audience and old-ID commands cannot revive it.

No manual-create endpoint, dummy Feature, separate reintroduction/proof TASK or cleanup chore for AI proposals is
created. A product owner's later explicit cleanup/deploy decision remains separate. Retirement does not block
initial AI proposal delivery, any upstream primitive or the first terminal Awaiting review milestone.

That retirement split completed EPIC-00016's five decompositions and brought the map to sixteen of 24,
not implementation acceptance. Older Harness/managed review planning remains incomplete.
Planning performed no reference cleanup, deployment, Feature deletion, Permission/grant mutation or reintroduction.

## Approved database-template initialization TASK decomposition

[TASK-00299](../tasks/00299-TASK.md) delivers TICKET-00075 as one protected setup/inspection outcome after
TASK-00248, TASK-00036 and TASK-00078. It removes TASK-00249's database prerequisite gap: one Harness template
identity/history/current-selection authority, complete atomic initial catalog, preserved custom/edited revisions,
scoped inspection and exclusion of recurring source overwrites. Required audit, PostgreSQL competition/rollback/
uncertainty and direct deployment qualification belong inside this outcome, not separate infrastructure/proof TASKs.

The approved managed `MANAGE_AGENT_TEMPLATES` Permission is SUPER_ADMIN_ONLY, assigned only through managed
Super Admin while preserving the complete managed policy. TICKET-00076 consumes the same store/authority for
publication; initialization grants no Agent authority and changes no existing binding or active session snapshot.

The unranked TASK remains `needs-info` for precise required Harness resource/reference primitives and the independent
Triage definition/registration handoff under EPIC-00012/TASK-00200. Completed diagnosis cannot be a seed prerequisite
because it already consumes the named Agent. No ninth mandatory starting role, new Triage catalog, fake legacy
migration or source/seed deletion is introduced. Source inspection found no implemented template store/reconciler
in the inspected application/configuration/migrations, but actual target data/writers still require qualification.

The sequence is TASK-00248 → TASK-00299 → TASK-00249 → actual Agent consumers. Browser publication, AI proposals
and final role/session journeys do not reverse-block the initial catalog. TASK-00249 retains other resource/model/
current-read holds. Initial terminal-first priorities and active work are unchanged.

That initialization split brought EPIC-00017 to one of five and the map to seventeen of 24 decompositions,
not implementation acceptance; older Harness/managed review planning remains incomplete. No seed, Permission, Agent, grant,
session, provider call or deployment occurred during planning.

## Approved human-template publication TASK decomposition

TICKET-00076 maps two unranked vertical features. [TASK-00300](../tasks/00300-TASK.md) delivers human browser/API
browse/create/revise/review/publish with history/diff, separately authorized Agent impact and original-outcome
recovery after TASK-00249, TASK-00259 and TASK-00051. TASK-00299's store and MANAGE_AGENT_TEMPLATES authority
remain upstream. It is needs-info for complete candidate-validation and final reference/current-authority fencing;
read-only profile resolution and a Permission catalog are not that atomic guarantee.

[TASK-00301](../tasks/00301-TASK.md) delivers exact old-ancestor/new-ancestor/custom review and deliberate retained-
revision restoration through the same publication boundary. It is ready-for-agent behind TASK-00300 and inherited
holds. Keep-current is read-only; publication/selection preserves historical bytes and every existing Agent grant,
binding and active snapshot. TICKET-00078 retains separately confirmed Agent Permission changes, not automatic sync.

TICKET-00077's AI proposals and TICKET-00079's custom use can consume TASK-00300 without TASK-00301 or whole-TICKET
completion. Human authoring/publication remains independent of ai-template-proposals availability. No duplicated
editor, provider endpoint, inspection-only or proof-only TASK is added; supplier direction and terminal-first
priorities remain unchanged. Missing composition guarantees hold without vendor patches or nested transactions.

That split brought EPIC-00017 to two of five and the map to eighteen of 24 approved TASK decompositions.
No template, publication, selection, Agent/grant/session mutation or provider effect occurred during planning.

## Approved AI template proposal TASK decomposition

TICKET-00077 maps [TASK-00302](../tasks/00302-TASK.md)'s one unranked real provider → human prompt/Permission
editing → explicit publication outcome after TASK-00300, TASK-00297, TASK-00294, TASK-00250 and TASK-00241.
Recovery, actual ai-template-proposals rollout and evidence belong inside this delivery, not separate proof work.

It remains needs-info for exact EPIC-00006 protected attempt/provider/delegation/disclosure/budget/recovery
supplier mappings and qualification, plus inherited editor/session/rollout holds. Jev readiness is not a generative
provider. Actual provider/model and existing testing Permission/audience require deliberate selection and live
qualification. No fictional blocker, second proposal platform or provider endpoint is introduced.

New requests/regeneration use the initiating human's current Feature/management authority at every supported
admission. The Agent gains no protected human right, and shared provider tools are not globally flagged. Manual
publication and authorized existing-attempt inspection/cancellation/safe recovery remain available when OFF;
recovery cannot duplicate uncertain paid work or revive a cancelled/superseded draft. TASK-00301, completed
Explorer/browser Planning and retirement are not prerequisites; suppliers and terminal-first priorities stay upstream.

That split brought EPIC-00017 to three of five and the map to nineteen of 24 decompositions, not implementation.
No Feature, provider request, testing grant, template publication, Agent or session effect occurred during planning.

## Approved existing-Agent Permission reconciliation TASK decomposition

TICKET-00078 maps [TASK-00303](../tasks/00303-TASK.md)'s one unranked browser/API drift preview and explicit
Add Missing/Auto-sync outcome after TASK-00300, TASK-00241 and TASK-00250. TASK-00259's safe inspection and
TASK-00256's separate assignment authority remain upstream; no competing Agent/Permission catalog is added.

It stays needs-info for supported atomic current-caller/template/scope authorization, complete-set assignment,
required reviewed-source audit and original-outcome composition, including no-op races. The public package
replacement transaction/event is not proof of these consumer guarantees. Missing seams require owner resolution,
not vendor patches, internal-coordinator calls, nested transactions or event-only audit. Refresh affected writer/
cohort evidence without making qualification primitives depend on this finished UI.

Add Missing preserves extras and validates the complete resulting set; Auto-sync confirms additions/removals and
requires the complete eligible desired set. Bound and compared revisions stay distinct; prompts, bindings,
credentials and immutable snapshots stay unchanged. A real next-use check observes revocation despite the snapshot.
No recurring rule, AI/ancestor-review dependency, whole role journey or separate proof TASK is introduced.

That split brought EPIC-00017 to four of five and the map to twenty of 24 decompositions, not implementation.
No Agent Permission, binding, credential or session changed during planning.

## Approved custom-template Agent use TASK decomposition

TICKET-00079 maps [TASK-00304](../tasks/00304-TASK.md)'s one unranked existing-browser creation → separate human
assignment → useful actual manual-work result after TASK-00300, TASK-00260 and TASK-00250. Preview/retained
creation, complete initial assignment, one-Team binding and TASK-00241 readiness remain upstream. No duplicate
Agent directory, credential flow, manual-work engine, demonstration or per-template certification TASK is added.

It remains needs-info for precise EPIC-00006 manual-assignment/runtime/provider/disclosure/host/recovery suppliers
and inherited creation/reference, administration/resource and session holds. The durable-session direction needs
qualification, not assumed support. Reattach preserves the same authorized Agent/conversation without duplicate
work or snapshot refresh. Finished Explorer/browser Planning cannot replace missing primitive contracts.

Custom creation pins the exact published revision, one Team, Repository scope and complete confirmed eligible
grants; it starts no work. Separate assignment retains current authority, protected credentials, disclosure and
host limits. Later publication changes no bound Agent/session; Workflow stays unavailable without an explicit
owned route. TASK-00301/00302/00303 are not prerequisites and no supplier/initial-role reverse blocker is added.

That split completed EPIC-00017's five decompositions and brought the map to twenty-one of 24, not runtime
acceptance. No Agent, grant, credential, session, provider request, manual assignment or Workflow was created.

## Approved coverage-accounting TASK decomposition

TICKET-00080 maps [TASK-00305](../tasks/00305-TASK.md)'s one unranked chore to establish qualified consumer
coverage for the maintainer handoff. It consumes existing human/Agent/Team, email, Feature and template evidence
owners and their upstream contracts, not EPIC completion labels. Internal inventory can precede qualification;
final acceptance requires actual qualified use or approved explicit dispositions for every relevant operation.
No second test platform, proof product or coverage-driven endpoint/feature TASK is introduced.

Status needs-info retains the final owner disposition of deferred manual CreateFeature, other discovered
unsupported/unmapped public operations and inherited runtime/composition holds. Provisioning does not qualify
manual creation; deferral is not permanent exclusion. Missing guarantees return to existing owners or explicit
scope decisions, not invented prerequisites or silently weakened acceptance.

Preserve WF-032's historical planning matrix and produce one current operation-level consumer matrix with exact
package/source/lock, consumer/configuration/tool subjects and safe attributable receipts. Reused evidence retains
its original identity and justified applicability; changed claims require owning requalification. TASK-00302
supplies real ai-template-proposals evidence without another demonstration or forced retirement.

TICKET-00081 consumes this coverage for actual references; TICKET-00082 retains immutable packaging/authorized
delivery. Neither completed deliverable reverse-blocks accounting or product suppliers. That split brought
EPIC-00018 to one of three and the map to twenty-two of 24 decompositions, not runtime acceptance. No qualified
matrix, exclusion approval, live scenario, source/package change or publication occurred.

## Approved integration-reference TASK decomposition

TICKET-00081 maps [TASK-00307](../tasks/00307-TASK.md)'s one unranked chore to document the qualified backend,
API and browser integration reference. It is ready-for-agent behind TASK-00305, including inherited scope/runtime
holds and TASK-00153/00154; no redundant direct blockers or separate documentation projects are introduced.

Supply one coherent reference set for every in-scope family: exact actual code/authority/transaction/recovery
boundaries, safe scenario inputs/commands, expected versus observed results and applicable redacted owner receipts.
React guidance reflects observed accessible states, not planned UI or mandatory starter design. Refresh affected
claims through their owners without another demonstration, certification campaign or coverage-driven product.

Verify private export/source/content identity with owning tools and agreement with the actual viewer-served spec
and request evidence. The bounded source digest does not qualify the whole runtime; failed/stale export cannot
qualify retained bytes. READ_SWAGGER, environment, independent debug, memory-only credentials/CSRF, no-store and
endpoint authority stay intact. No public spec, token examples, unauthorized redistribution or automatic effects.

TICKET-00082 retains immutable archive/manifest, recipient access, publication and successor handling, with no
reverse blocker on references/accounting/product suppliers. That split brought EPIC-00018 to two of three and
the map to twenty-three of 24 decompositions, not runtime acceptance. No guide, export, live request, starter edit,
access grant or bundle was created.

## Approved immutable-handoff TASK decomposition

TICKET-00082 maps [TASK-00308](../tasks/00308-TASK.md)'s one unranked chore to publish the immutable qualified
reference bundle to the five starter maintainers. It is `needs-info`, blocked only by TASK-00307 with TASK-00305
already upstream. Concrete authenticated channel, publisher authority, recipient identities/access and retention
must be selected and verified. All inherited coverage/runtime holds remain; no supplier depends on publication.

One versioned `.tar.gz` archive, JSON identity manifest and detached SHA-256 identify every component and actual
package/lock/qualified implementation versus later documentation, OpenAPI source/content and evidence subjects
without self-hashing. Redacted evidence travels with the archive or at retained immutable controlled references;
ignored scratch alone is insufficient. No new publication service, duplicate qualification or managed-release gate.

Under separate publication authority, deliver the same identified bytes/instructions/caveats with verified
recipient and required-reference access for all five destinations. Remote/retrieved hashes must agree; partial,
conflicting or uncertain outcomes stay incomplete and reconcile by exact version before retry. Never overwrite
published bytes, expose private specs publicly or infer deployment/Swagger authority from artifact readership.

A manual successor procedure retains previous bytes, assesses changed claims, obtains owning requalification and
authorizes a complete newly identified bundle. No artificial second release, starter source/certification/release,
acknowledgement condition or common five-starter update pipeline follows.

**All five EPICs, all 24 accepted TICKETs and their approved TASK decompositions are now written for this map.**
This completes the selected map's planning phases, not implementation, qualification or publication. Older
Harness/managed-review planning remains open elsewhere; active TASK-00138/TASK-00226/TASK-00306, priorities and
terminal-first delivery stay unchanged. No bundle, upload, access grant, maintainer contact or live scenario occurred.

## Approved EPIC handoff

Destinations 1–5 were written through **separate grills**; no EPIC handoff remains unwritten.
Consume its linked decision and the [capability matrix](research/WF-032-accesscontrol-consumer-capability-matrix.md)
without re-deciding settled boundaries. This is an authored planning order, not a new implementation priority or
a release gate for an unrelated starter:

1. **[EPIC-00014 — Deliver the working managed Agent team](../epics/00014-EPIC.md)** — explicitly combines
   [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md) and
   [WF-035](tickets/WF-035-define-project-teams-and-agent-selection.md). One-Team-per-Agent creation, complete
   reviewed managed template catalog, real authenticated first Explorer, qualified shared AccessControl
   operation/credential lifecycle, Team/Repository membership and selection boundary. **Full acceptance** needs
   every one of the eight roles doing real authorized work through its existing owned product path; Team/Triage
   Workflow owners retain their implementations, and their historical completion labels are not live proof.
   Neither package fixtures nor storage-only tests qualify actual use.
2. **[EPIC-00015 — Deliver mailbox-confirmed human email change](../epics/00015-EPIC.md)** —
   [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md). Self-service and guarded
   cross-user request/cancel, new-mailbox confirmation with non-mutating landing and explicit one-time submit,
   safe old-mailbox notices at request/completion, real provider/recovery and a distinct protected Permission.
   Linked after [EPIC-00004](../epics/00004-EPIC.md) without silently removing its historical deferral.
3. **[EPIC-00016 — Deliver usable Feature Flags management and enforcement](../epics/00016-EPIC.md)** —
   [WF-032](tickets/WF-032-reconcile-accesscontrol-coverage-and-accepted-scope.md). Complete discovery,
   provisioning, preparation, package-backed Action middleware and other registered runtime checks, authorized
   administration, complete authenticated catalog with per-user availability, and cautious retirement after a
   source-cleanup chore. No invented Feature or extra proof-only release gate.
4. **[EPIC-00017 — Author versioned Agent templates with human-approved AI assistance](../epics/00017-EPIC.md)** —
   [WF-033](tickets/WF-033-define-agent-operation-adoption-boundary.md). Browser-authored, database-authoritative
   managed and custom Agent template revisions after initial seeds; human-edited AI suggestions, Super Admin
   administration, explicit one-time existing-Agent Permission reconciliation and real bounded manual use of
   newly defined roles before separately approved Workflow routes.
5. **[EPIC-00018 — Publish the qualified Agent OS AccessControl reference-consumer handoff](../epics/00018-EPIC.md)** —
   [WF-034](tickets/WF-034-set-reference-consumer-and-starter-handoff-contract.md). Take **one immutable**
   package/consumer-identified snapshot only after all relevant public v0.5.0 families have qualified use or an
   approved explicit exclusion, with appropriate UI, protected operations and implemented
   [TASK-00154](../tasks/00154-TASK.md) Swagger/Try it out. Include backend references, verified private
   OpenAPI hashes, optional React suggestions, redacted per-capability receipts/limits and change invalidation.
   Starters can adapt independently; no starter-source edits, certification, release, all-five gate or
   modification of [EPIC-00009](../epics/00009-EPIC.md)'s per-starter eligibility.

**Next:** `/skill:next` to consult the authoritative Board after this map's completed planning phases. This is
read-only routing, not automatic implementation selection or execution. Preserve existing active work and
priorities; older Harness/managed-review planning outside this map remains incomplete, including TICKET-00083's
unwritten TASK split. Implementation/worktree choice, independent review, repository publication/merge, bundle
publication and starter maintenance remain separate from this decision map.

## Downstream implementation unknowns (not open Wayfinder decisions)

- `ai-template-proposals` is selected under EPIC-00017/TICKET-00077. Actual declaration/admission integration,
  the testing Permission/audience, provider qualification and runtime evidence remain unimplemented; selection
  does not enable it. EPIC-00016 supplies reusable lifecycle primitives, not a fake Feature.
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
rewriting of EPIC-00004/00006/00007/00008/00009, ADR 0001/0002, the closed vision/triage maps or other
pre-existing TASKs beyond the **explicitly approved** TASK-00154/TASK-00063 and owning-policy planning
alignment in WF-034. No EPIC creation or TICKET/TASK decomposition in this decision-resolution pass.
