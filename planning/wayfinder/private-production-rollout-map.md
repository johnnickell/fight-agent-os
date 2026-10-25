# Wayfinder Map: Private production rollout, local environments and provider access

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an index, not a parallel delivery backlog. Existing EPIC/TICKET/TASK records retain ownership,
> acceptance requirements and priority. Initial work was limited to Wayfinder records/research. John subsequently
> approved the bounded first-production planning handoff below. Its sole new EPIC is written and the approved
> existing-owner requirements are reconciled. The five deployment TICKETs and provider lifecycle/settings
> TASK-00520/00521 and deployment TASK-00522–00530 plus TASK-00532/00533 are accepted/written. All selected
> planning phases are complete, not implementation or operational acceptance; TASK-00531 is unrelated work.

## Destination

Plan a useful, privately operated Fight Agent OS at **https://fight-agent.dev**, with reliable local development,
production-like staging, browser Planning/help, honest usage and provider allowance, and recoverable deployment.
Keep a future path to local-domain management and preserve existing applications until replacements are verified.

**Done** = every linked decision is resolved or explicitly excluded/deferred by the human; remaining fog is
resolved or excluded; existing owners, proposed amendments and genuinely missing suppliers are mapped; and the map
links a precise later planning handoff. No EPIC/TICKET/TASK creation or amendment is authorized in this session.
Map completion is not product readiness, implementation acceptance, delivery or production go-live. The later
approved planning handoff supersedes the initial prohibition on its specifically named owner amendments and
shaping one new EPIC; it does not authorize decomposition, implementation or external effects.

## Notes

### Approved boundary

John approved the revised release boundary and production-matching local names in this session; see
[WF-043 — Bound the private release and approve environment names](tickets/WF-043-bound-private-release-and-environment-names.md).

- Start with John, then likely his wife and son: private household use is intended. Whether a provider treats
  multi-user self-hosting as an eligible hosted service remains research; broader distribution/commercialization
  is not approved by this scope.
- First browser Planning/help uses **the logged-in user's own OpenRouter API key**, protected server-side.
  No shared administrator key, another family member's key or silent paid fallback. Local Pi/development providers
  retain their independent existing boundary. Remote ChatGPT OAuth is an optional later investigation.
- First useful release targets one accepted browser Planning/assistant journey, honest usage/unavailable allowance,
  production security and recovery. Actual qualified sources are required before claiming allowance integration;
  missing quota does not block useful recorded usage. Both browser providers are no longer a launch requirement.
- Production: `https://fight-agent.dev`; staging: `https://staging.fight-agent.dev`;
  local: `https://local.fight-agent.dev`; worktree: `<actual-task-id>.local.fight-agent.dev`, disambiguating
  concurrent checkouts. No TASK ID is allocated by a hostname example.
- Future separate projects retain distinct names: `https://local.nickell.app` and
  `https://local.nickell.financial`. This map does not plan or implement either application.
- `.test` was a reasonable local convention, not a public-TLD collision; shortening both Nickell production
  domains to `nickell.test` would lose application identity. Earlier `fight-agent.test`,
  `local.fight-agent.test` and `local.fight-agent.dev` alternatives remain historical reasoning.
  Avoid `.local`; `.dev` and `.app` require trusted HTTPS in mainstream browsers.

### Ownership and evidence

The [bounded baseline and amendment register](research/private-production-rollout-baseline.md) separates current
requirements, inspected configuration, approved direction, recommendations and missing qualification. Existing
closed maps stay closed. Their historical approvals are not silently rewritten by this map.

| Concern | Existing owner to reuse | Unsettled boundary |
|---|---|---|
| Providers and original accounting | [TICKET-00089](../tickets/00089-TICKET.md) | Per-user browser credentials differ from its installation-held key; preserve local Codex development support |
| Recorded usage / account allowance | [TICKET-00098](../tickets/00098-TICKET.md), [TASK-00399](../tasks/00399-TASK.md) | WF-044 documents `/key` candidate source and proposed TASK-00346 adapter/TASK-00399 refresh split; accepted mapping and qualification remain held; account wallet is unavailable without broader management authority |
| Browser Planning / durable conversations | [TICKET-00092](../tickets/00092-TICKET.md), [TICKET-00090](../tickets/00090-TICKET.md), [TICKET-00091](../tickets/00091-TICKET.md) | Choose one accepted journey and per-user provider integration; no second conversation engine |
| Browser support / instruction assistance | [TICKET-00104](../tickets/00104-TICKET.md), [TICKET-00107](../tickets/00107-TICKET.md) | TICKET-00104 explicitly requires an installation key; generic chat is not automatically owned by either |
| Local installation / capability reporting | [TICKET-00023](../tickets/00023-TICKET.md) | Optional private routing and production-specific foundations are not proven by current local TLS |
| Authenticated Agent API/MCP | [TICKET-00061](../tickets/00061-TICKET.md) | Actual remote transport/enrollment and protected participants remain qualification work |
| Git / candidate execution | [Tool-mediated execution](../../docs/engineering/TOOL_MEDIATED_EXECUTION.md), [TICKET-00032](../tickets/00032-TICKET.md), [TICKET-00102](../tickets/00102-TICKET.md) | Trusted Git owner, SSH/API scopes and candidate-check location; initial GitHub publication is not general Git administration |
| Authentication / production envelope | [TICKET-00017](../tickets/00017-TICKET.md), [TICKET-00022](../tickets/00022-TICKET.md) | Authentication is recorded done; production security acceptance remains separate |
| Credential email / recovery | [TICKET-00011](../tickets/00011-TICKET.md) | Real-inbox provider and operating schedule are excluded from its completed foundation |
| Deployment / diagnosis | [TICKET-00058](../tickets/00058-TICKET.md), [TICKET-00117](../tickets/00117-TICKET.md) | Project-owned rollout/recovery and actual operations suppliers still need qualification |

The existing private shared proxy documents registration, certificates, validation, recovery and login startup.
Its managed hosts block and installed startup configuration were inspected, not changed. Fight's checked-in
`compose.yaml`, `bin/https` and NGINX configuration still fix local HTTPS to `localhost:18443`.
A hosts entry alone cannot adapt authority, Origin, cookie or TLS configuration. Current Docker/proxy health was
not tested; earlier unavailable-Docker evidence is historical, not a new result. Private machine inventory,
paths, keys, certificate state and generated routes stay outside publishable planning.

### Rollout and planning order

1. Set scope and implement the real product/supplier work through existing owners, without requiring production
   to exist before local development or staging checks that do not need it.
2. Prepare local/staging delivery with contributor-independent defaults and ordinary implementation acceptance.
3. Target database-backed Planning plus the selected usable browser Wayfinder journey as the first production
   rollout milestone; retain explicit migration and operator promotion with the agreed backup/recovery precautions.
4. Deploy through the practical operator path, observe actual behavior, and let John assess real production use.
   Address observed defects through concrete bugfix work when requested, not a speculative readiness/test project.
5. Independently selectable provider/domain/workflow expansion and separately authorized legacy retirement.

These are proposed delivery stages, not new TASK dependencies or Board priorities. Optional ChatGPT, domain UI,
legacy replacement, candidate workers and automated deployment orchestration do not become first-release gates.
Follow map-wide planning order for any later authorized EPIC → TICKET → TASK handoff; no such handoff is invoked now.

## Decisions so far

1. **[WF-043 — Bound the private release and approve environment names](tickets/WF-043-bound-private-release-and-environment-names.md) is closed.** John approved OpenRouter-first, per-user browser support, preserved local development providers, optional ChatGPT and production-matching local names. This approves scope, not live eligibility or infrastructure.
2. **[WF-044 — Qualify per-user OpenRouter and allowance ownership](tickets/WF-044-qualify-per-user-openrouter-and-allowance-ownership.md) is closed as factual research.** [Primary-source findings and proposed amendments](research/WF-044-per-user-openrouter-and-allowance.md) document ordinary per-user inference keys, scoped `/key` caps/budgets/free-quota, management-only account credit totals and exact protected refresh responsibilities. The later approved reconciliation accepts TASK-00346's protected `/key` mapping for TASK-00399; TASK-00520/00521 now allocate lifecycle/settings, while actual source/host qualification remains held. TASK-00399/00346/00350 stay `needs-info`.
3. **[WF-045 — Compare DigitalOcean hosting and operating costs](tickets/WF-045-compare-digitalocean-hosting-and-operating-costs.md) is closed as factual research.** [Dated prices, comparison and owner handoffs](research/WF-045-digitalocean-hosting-and-costs.md) recommend considering separate Droplets/Managed PostgreSQL; comparable pre-tax floors are $87.10/month versus App Platform $77.30, not complete launch costs or capacity proof. Standard DB HA eligibility is changing; actual Advanced quote and full operations cost remain unknown. Topology/region/budget selection belongs to WF-048 and recovery acceptance to WF-050; no infrastructure or budget is approved.
4. **[WF-046 — Define optional local routing, trust and recovery](tickets/WF-046-define-optional-local-routing-trust-and-recovery.md) is closed on John's policy approvals.** Shared scoped workstation-only DNS supports separate project namespaces; fail-closed routing, stable TASK/ten-hex checkout names, qualified existing-CA reuse and project base/wildcard leaf certificates are approved. Guided onboarding belongs in eventual easy-install. Independent infrastructure lifecycle covers login/runtime readiness, sleep/wake, shutdown/reboot and runtime restart, with persistent maintenance stop and no automatic startup of all apps. Exact suppliers, platform/container qualification and owner amendments remain held; no installer, runtime or source effect is approved.
5. **[WF-047 — Bound remote harness, trusted Git and candidate execution](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) is closed on architecture/policy decisions.** Hosted coordination, optional human TASK responsibility, pinned latest-`develop` context, central identity/reference allocation and read-only hosted Git are approved. Contributors retain feature/PR publication and native Agent bash checks until a qualified MCP/browser replacement. Browser provisioning includes protected harness-recipient binding and delivery/activation; MCP is stateless authenticated invocation, not another sign-in. Existing recovery safeguards carry forward without repetitive approval. Actual bootstrap, remote delivery, bash-safe custody and source/check suppliers remain unqualified; managed Git writes/workers are deferred and Markdown authority is unchanged.
6. **[WF-048 — Define staging and artifact promotion](tickets/WF-048-define-staging-and-artifact-promotion.md) is closed on policy and owner handoffs.** Originally selected separate SFO3 Droplets/Managed PostgreSQL and smaller staging; WF-050 subsequently replaces staging's managed cluster with local PostgreSQL on a 2 GiB staging Droplet. Environment separation, non-HA production with verified recovery and the combined sub-$100/month preference remain. Operator approval promotes the exact staged artifact; optional human UAT applies to this project only. Controlled live staging checks use per-user connections and independent environment budgets, likely higher in production. Production packaging/operator runbook, mail/scheduling, storage/diagnosis capacity and protected runtime suppliers remain missing or unqualified. Cost feasibility, exact SKUs, numeric caps and recovery targets are not approved spending or launch acceptance; existing delivery holds remain.
7. **[WF-049 — Select the first browser journey and switching behavior](tickets/WF-049-select-first-browser-journey-and-switching-behavior.md) is closed on journey/model/usage policy and owner handoffs.** Database-backed browser Wayfinder is the first production rollout milestone, not a production-exists prerequisite. Current OpenRouter choices use on-load/light-cache refresh and default updates; existing conversations switch only explicitly between turns. Recorded conversation usage, environment budgets and provider allowance stay distinct; missing allowance does not block useful Planning. The later approved reconciliation allocates conversation usage query/UI to TASK-00352/00365 and discovery/default controls to TASK-00343/00346/00323/00367; no second ledger or readiness TASK. Model IDs, caps and cache tuning remain configuration; no spend or deployment is authorized.
8. **[WF-050 — Define private launch readiness and recovery](tickets/WF-050-define-private-launch-readiness-and-recovery.md) is closed on policy and owner handoff.** John accepts a preferred one-hour data-loss target and restoration within 24 hours after discovery, with human-operated recovery rather than staffed overnight response. He is willing to consider daily-backup recovery if needed for practicality/cost; this is not unlimited loss or an automatic longer outage allowance. Thirty days of daily recovery points is accepted conditional on reasonable cost. John selected managed PostgreSQL for production, self-managed PostgreSQL on staging and Spaces-only backups. Plan 2 GiB RAM / 50 GiB SSD app Droplets for both environments, giving a $56.35 recurring subtotal before remaining costs/tax. Staging targets one worker if possible, at most one needed projector and one cron container, with 512 MB requested for PHP. Bitwarden recovery custody, Resend Free, compatible app-only rollback and explicit database-restore approval are selected. The sub-$100 preference includes tax/recurring services; temporary recovery is separately quoted/approved. Actual fit and complete costs remain ordinary delivery work.
9. **[WF-051 — Assess optional household ChatGPT OAuth](tickets/WF-051-assess-optional-household-chatgpt-oauth.md) is closed on research and explicit deferral until after first production.** [Official findings](research/WF-051-chatgpt-plan-usage-and-private-hosting.md) preserve supported SIWC/self-hosted VM routes and unresolved household eligibility/browser-only onboarding. Revisit only through a later explicit selection after the milestone; no automatic enrollment or implementation. Per-user OpenRouter remains the first-release provider.
10. **[WF-052 — Define the future local-domain management flow](tickets/WF-052-define-future-local-domain-management-flow.md) is closed on the optional scripted setup journey and owner handoff.** John selected a browser/Pi wizard: select or create a project, approve its local hostname/application destination, then approve automated Composer/npm preparation, Docker Compose startup and explicitly selected database initialization/migrations. No AI model is required; destructive resets and seed/sample data are off by default. Reuse existing creation/local infrastructure contracts with later explicit owner extensions and qualified suppliers. No host effects or delivery amendments are authorized.
11. **[WF-053 — Inventory legacy capabilities and retirement gates](tickets/WF-053-inventory-legacy-capabilities-and-retirement-gates.md) is closed as bounded source research and a preservation handoff.** [Capability inventory](research/WF-053-legacy-capabilities-and-retirement-boundaries.md) separates the shared router, Omphalos homepage/supporting services, Ideaverse/personal workflows, `$aios`, direct `$fight` and optional Factory runtime. Preserve all; no live continuity or replacement is claimed. Ownership transfer, route/data migration, command replacement and retirement remain separate authorized transitions, never first-production prerequisites.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) | Bound the private release and approve environment names | wayfinder:grill | HITL | Closed | — |
| [WF-044](tickets/WF-044-qualify-per-user-openrouter-and-allowance-ownership.md) | Qualify per-user OpenRouter and allowance ownership | wayfinder:research | AFK | Closed | [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) |
| [WF-045](tickets/WF-045-compare-digitalocean-hosting-and-operating-costs.md) | Compare DigitalOcean hosting and operating costs | wayfinder:research | AFK | Closed | [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) |
| [WF-046](tickets/WF-046-define-optional-local-routing-trust-and-recovery.md) | Define optional local routing, trust and recovery | wayfinder:grill | HITL | Closed | [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) |
| [WF-047](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) | Bound remote harness, trusted Git and candidate execution | wayfinder:grill | HITL | Closed | [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) |
| [WF-048](tickets/WF-048-define-staging-and-artifact-promotion.md) | Define staging and artifact promotion | wayfinder:grill | HITL | Closed | [WF-045](tickets/WF-045-compare-digitalocean-hosting-and-operating-costs.md), [WF-047](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) |
| [WF-049](tickets/WF-049-select-first-browser-journey-and-switching-behavior.md) | Select the first browser journey and switching behavior | wayfinder:grill | HITL | Closed | [WF-044](tickets/WF-044-qualify-per-user-openrouter-and-allowance-ownership.md), [WF-047](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) |
| [WF-050](tickets/WF-050-define-private-launch-readiness-and-recovery.md) | Define private launch readiness and recovery | wayfinder:grill | HITL | Closed | [WF-048](tickets/WF-048-define-staging-and-artifact-promotion.md), [WF-049](tickets/WF-049-select-first-browser-journey-and-switching-behavior.md) |
| [WF-051](tickets/WF-051-assess-optional-household-chatgpt-oauth.md) | Assess optional household ChatGPT OAuth | wayfinder:research | AFK | Closed | [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) |
| [WF-052](tickets/WF-052-define-future-local-domain-management-flow.md) | Define the future local-domain management flow | wayfinder:grill | HITL | Closed | [WF-046](tickets/WF-046-define-optional-local-routing-trust-and-recovery.md), [WF-047](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) |
| [WF-053](tickets/WF-053-inventory-legacy-capabilities-and-retirement-gates.md) | Inventory legacy capabilities and retirement gates | wayfinder:research | AFK | Closed | [WF-043](tickets/WF-043-bound-private-release-and-environment-names.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-043 ──→ WF-044 ────────────────────→ WF-049 ──→ WF-050
       ├─→ WF-045 ──→ WF-048 ───────────────────→ WF-050
       ├─→ WF-047 ──→ WF-048 / WF-049
       ├─→ WF-046 ──→ WF-052 (optional UI)
       │   WF-047 ──→ WF-052
       ├─→ WF-051 (optional ChatGPT; no launch edge)
       └─→ WF-053 (legacy inventory; no launch edge)
```

WF-046's optional local routing does not gate production; reliable standalone local development remains required.
No requirement that a candidate-code worker exist before browser Planning. Dependencies above are decision inputs,
not implementation dependencies. Optional expansion can be explicitly deferred rather than delay launch planning.

## Frontier

**No open decision frontier. WF-043–053 are Closed**, resolving this map's decisions through approved direction,
bounded research or explicit deferral. WF-053's inventory preserves existing applications, infrastructure and
workflow entry points; no legacy transition is required for first production.

The map is Closed on planning, not implementation or production acceptance. John explicitly approved the
[bounded first-production planning handoff](#approved-first-production-planning-handoff): scoped existing-owner
amendments and one new deployment EPIC destination. [EPIC-00021](../epics/00021-EPIC.md) is now written after John's
final grill confirmation. The [existing-owner reconciliation](research/private-production-owner-reconciliation.md)
is recorded, and the [five deployment TICKETs](../epics/00021-EPIC.md#approved-ticket-decomposition-and-current-handoff)
are accepted/written as TICKET-00121–00125. TICKET-00089's complete connection lifecycle and optional allowance
settings are now accepted/written as TASK-00520/00521. TICKET-00121's artifact/CI split is accepted/written as
TASK-00522/00523, TICKET-00122's environment split as TASK-00524–00526, and TICKET-00123's mail split as TASK-00527/00528.
TICKET-00124's recovery split is accepted as TASK-00529/00530. The shared Resend transport remains TASK-00284's
held qualification. TICKET-00125's release split is accepted as TASK-00532/00533, completing selected-map planning.
The Board still owns executable selection; no delivery status or qualification hold is cleared.
No further EPIC destination is approved; further decomposition, implementation and retirement do not start automatically.

WF-052 is closed on the optional deterministic browser/Pi setup journey, including selected local database
initialization/migrations. Destructive resets and sample data are off by default; no Agent is required. WF-046/047
supply the carried-forward local authority/recovery contracts. Existing Create Project/local infrastructure owners
remain, with exact terminal/routing/startup/database extensions requiring later explicit allocation and delivery.
No implementation, new EPIC destination or decomposition is authorized by closure.

WF-051 is closed: John explicitly **deferred ChatGPT integration until after first production**. Preserve the
[official findings observed 2026-10-24](research/WF-051-chatgpt-plan-usage-and-private-hosting.md) on SIWC/self-hosted
VM support, local bootstrap and tool/usage limits, without declaring household eligibility or browser-only onboarding
qualified. A later explicit selection is needed to revisit after the milestone. OpenRouter remains the first-release
provider; no new EPIC destination, decomposition, account enrollment or delivery is authorized.

### WF-050 closed policy and handoff

WF-048–050 are closed on decisions/owner handoffs, not deployment evidence. John accepted preferred recovery of no
more than one hour's accepted work and
usable service within 24 hours after discovering a failure, with him operating recovery rather than overnight
staffing. He would consider worse if needed, specifically daily backups; compare the concrete cost/recovery
tradeoff rather than making hourly backups an inflexible prerequisite or assuming unlimited loss/downtime.
John also accepts 30 days of daily recovery points **provided the cost is reasonable**; no price or spending is
approved. This differs from recent recovery-point precision, provider PITR and existing diagnosis retention.
John selected **hybrid database hosting**: managed PostgreSQL for production; PostgreSQL on the staging Droplet.
He selected **Spaces-only backups**, accepting the explained shared-provider/account exposure; B2 is not required.
This supersedes WF-048's two-managed-cluster plan, not separate environments or exact-artifact promotion. Match
PostgreSQL major/extensions/schema while explicitly retaining the managed-versus-self-managed operational difference.

Plan **2 GiB RAM / 50 GiB SSD application Droplets for both environments**. John prefers the larger staging option
so PHP, workers and other services leave room for PostgreSQL. The [updated cost comparison](research/WF-050-backup-storage-and-restore-costs.md)
gives a **$56.35/month** subtotal including one managed DB, daily boot backups, Spaces and registry, before tax and
remaining operating costs. The old $48.55 hybrid example used 1 GiB staging; $63.70 used two managed databases.
No actual resource or full workload fit is established. Spaces Standard's included 250 GiB pool is shared;
300 GiB total usage adds $1/month at the cited prices. Retain ordinary coherent database/files/key recovery work.

John bounds **staging** to one worker if possible, at most one projector if needed, and one cron container, with
512 MB requested for PHP. Expect a light workload without treating idle processes as memory-free or assuming
scheduled work never overlaps. Exact PHP limits, modest web concurrency and PostgreSQL/OS/service headroom belong
to ordinary deployment configuration; this does not set production worker counts or prove capacity.
John selected **Bitwarden** for the human-held recovery kit: needed backup/application encryption recovery material,
DigitalOcean account recovery codes and restore instructions. This is not application/agent vault access or a
runtime dependency. Keep the recovery path usable without Fight and avoid circular vault-unlock/second-factor
recovery dependencies; actual custody and recovery checks remain ordinary delivery work. No secrets were accessed.
John selected **Resend Free over HTTPS** for automated credential/account-recovery mail; Gmail remains his inbox.
The dated 3,000/month and 100/day allowance is not a live entitlement or delivery receipt. Plan $0 mail subscription
within current eligible limits, without automatic paid upgrade. Reuse existing mail/outbox ownership; actual sender
verification, protected credentials, scheduling and inbox delivery remain ordinary authorized delivery work.
John approved **compatible application-only rollback within an approved deployment**; absent/uncertain database
compatibility does not authorize trying the old version. **Database restore requires his explicit approval** with
the recovery point/data-loss scope stated. No reverse migration or data rewind follows from deployment authority.
John confirmed that the **below-$100/month preference includes tax and recurring services** across both environments;
model inference stays separate. **Temporary recovery costs require a separate quote and approval**, with no automatic
reserve. The $56.35 pre-tax subtotal is not a complete tax-inclusive quote or spend approval. Return actual
unavoidable overage for a human tradeoff; do not omit requirements. WF-050's final owner handoff retains ordinary
supplier/capacity/recovery work without a readiness project. No account enrollment, DNS change, email send,
spending, deployment or restore is authorized now.

John explicitly rejects a requirement for production to already exist before implementation or first deployment.
Use normal repository acceptance and the agreed recovery precautions; actual deployment/use supplies target facts
and observed defects can become requested bugfix work. No readiness framework, policy tests, speculative blocker
backlog or new production-prerequisite TASKs follow from this map. No immediate cutover, dual writer, owner amendment,
spending or deployment is authorized.

Carry forward current OpenRouter model choices with light-cache/on-load refresh, authorized default updates and
explicit between-turn switching; 15 minutes is a suggested cache setting, not a fixed human-approved constant.
Existing conversations retain their selection. Conversation usage, application environment budgets and independently
refreshed provider allowance have separate scope/unknowns; catalog refresh performs neither generation nor allowance
polling. Exact conversation usage/default/discovery integration belongs to the existing product owners.

Carry forward SFO3, smaller isolated staging, non-HA production with verified recovery, operator-approved exact
artifact promotion and optional project-specific UAT. Controlled live staging checks require per-user connections
and independently configured environment budgets; production likely has higher caps, but no amounts or automatic
increases are selected. Staging cannot borrow production allowance. The sub-$100 combined infrastructure target
remains unproven and separate from model inference. WF-050 closes cost treatment/recovery/authority policy; actual
complete pricing, runtime observations and John's exact-candidate go/no-go remain delivery-time work.

John's interruption clarification concerns the already-settled client/server separation, not a blanket dismissal
of safety questions. Do not reopen consequences of that separation; ask about genuinely unresolved cost, scope,
downtime, safety or behavior tradeoffs. Existing local bash checks remain; no candidate execution on application
hosts. Optional local-domain UI, human assignment, managed Git writes/workers and future tool-mediated CI do not
become first-browser gates. Existing holds and Board priority remain; no delivery amendments or external effects
are authorized. Stop after each selected decision rather than automatically beginning the next.

## Deferred delivery details and excluded replacement scope

- WF-049 resolves model-selection/switching and usage presentation. Exact model IDs, finite numeric caps/reset
  windows and cache freshness remain configuration within implementation/use, not another benchmark/readiness
  project. Conversation-to-original-usage query/UI and refreshed catalog/default-setting extensions require later
  authorized allocation within existing owners; Workflow-only TASK-00396/00391 do not already supply them.
  WF-044's proposed TASK-00346 key-info adapter/TASK-00399 allowance mapping and per-user lifecycle/UI still need
  actual integration. Ordinary keys do not provide full account balance; no local-usage subtraction or management
  key collection. Missing allowance does not block useful recorded usage or silently close its owner's hold.
- WF-048 selects separate SFO3 application environments and non-HA production conditional on verified recovery.
  WF-050 supersedes the second managed DB with PostgreSQL on staging; both app VMs now target 2 GiB RAM / 50 GiB SSD.
  Spaces-only backups are selected; actual key/export/restore participants and PHP/worker memory allocation remain. Measured sizes, supported DB edition/major, full diagnosis/storage/retention resources and
  complete operating cost remain unqualified. The combined sub-$100/month preference replaces the proposed $200
  discussion figure and now includes tax/recurring services. No temporary reserve or spend is approved; temporary
  recovery is separately quoted/approved. WF-050 closes policy; delivery owners retain actual cost/backup/key/object
  recovery qualification. Its preferred one-hour recovery point and 24-hour restoration-after-discovery target
  are accepted; a daily-backup cost alternative may be considered. Thirty days of daily recovery points is accepted
  conditional on reasonable cost; concrete mechanisms, volume assumptions and full costs remain open. HA pricing
  is not a gate for the selected non-HA release.
- Actual local-component enrollment/revocation, resolver/OS integration, installer distribution/profile ownership,
  protected apply participant and exact TLS/container/lifecycle qualification remain. WF-046 approves names,
  workstation-only scoped multi-project DNS, fail-closed/conflict policy, CA/leaf custody, guided easy-install and
  independent startup/recovery intent; it does not qualify installed mechanisms. WF-053 preserves current proxy
  ownership until a separately authorized, verified transition; WF-047/052 bound authenticated delegation.
- Optional human TASK responsibility is approved as eventual collaboration direction in WF-047, not a new
  first-browser prerequisite or delivery amendment. Proposed reuse is TICKET-00026/00027 for responsibility/queue
  eligibility and TICKET-00032 for the shared execution claim; TICKET-00064 remains Agent/Team selection. Exact
  lifecycle, permissions, handoff and consumer amendments need later owner planning, not a second claim engine.
- WF-047 approves latest-`develop` snapshot selection on new context/explicit refresh, with feature/PR/local
  worktree selection explicit. Reuse TICKET-00024/00025; actual retrieval/materialization and credential participants
  remain missing/unqualified. Protected read-only hosted retrieval versus contributor-owned publication is
  approved; a dedicated read-only repository SSH key remains a candidate, not selected/enrolled/qualified access.
  General managed Git writes and the actual GitHub API participant remain later qualification/allocation gaps.
- WF-047 approves local native checks/applicable CI, no candidate execution on application hosts and an optional
  separately qualified managed worker. Current local Agents retain bash checks until a qualified MCP/browser
  replacement is ready, including future CI request/status/results. Exact CI supplier, source availability,
  protected policy/results, budgets and dispatch/cancel recovery remain deferred owner work; no hosted Git-read
  privilege expansion or managed-profile bash grant. Preserve required build/Review/QA and existing managed holds.
- WF-047 clarifies browser human sign-in, protected local-harness HMAC credentials for template-based Agent
  instances and stateless authenticated MCP, with no separate client/device enrollment or terminal sign-in step.
  TICKET-00059/00060/00061 retain credential-operation state, protected holder/destination delivery and current-use
  authentication/authorization including replay/revocation checks. Reconciliation proposes the harness store as
  the fixed sink's local destination, followed by exact protected activation/signing. Actual first-recipient proof,
  outbound transfer/receipt path and protection from native same-user bash remain unqualified; a keychain alone
  proves none of those contracts. John approved recipient authorization within browser provisioning, followed
  by protected delivery/activation without another sign-in;
  TICKET-00063's existing administrator-only management boundary remains, not automatic contributor self-service.
  Existing recovery/current-use safeguards carry forward; actual recovery participants remain unqualified.
  No parallel identity registry or promise to stop offline native jobs.
- Unified terminal/browser identity/reference allocation is approved direction reusing TICKET-00026's COMB-backed
  identity, idempotent creation and per-Repository/per-kind atomic non-reused references. Allocate on creation;
  disconnected drafts have no new official references. TICKET-00028 preserves historical references and owns
  approved authority switching. Actual suppliers remain unqualified. No pre-cutover reservation service, number
  pool, renumbering or dual writer is selected; Markdown authority and current numbering procedures are unchanged.
- Browser Wayfinder Planning and its model/default/continuation policy are selected under TICKET-00092, paired
  with the existing TICKET-00028 database transition for first rollout. Actual product delivery remains; generic
  chat and creation/instruction assistance do not become additional first-release gates.
- WF-048's [owner handoff](tickets/WF-048-define-staging-and-artifact-promotion.md#existing-owner-and-missing-supplier-handoff)
  identifies missing Fight production packaging/operator-run release allocation, public TLS operation, real email/
  scheduling and protected provider runtime suppliers. Exact evidence and budget configuration surfaces remain
  unallocated/unqualified; local-only owners are not silently expanded. Full observability requirements remain
  with their owners, not cut to meet a budget. The final handoff now approves one bounded deployment EPIC
  destination, now written as [EPIC-00021](../epics/00021-EPIC.md). The bounded provider/Planning requirements are
  [reconciled](research/private-production-owner-reconciliation.md); actual supplier qualification remains.
- WF-051's official research documents eligible Plus/Pro SIWC, self-hosted VM bootstrap, account-model discovery,
  supported function/custom tools and preview limits. Exact household-service eligibility/browser-only onboarding,
  protected OAuth runtime and authoritative SIWC app allowance remain unqualified. Published plan prices do not
  establish John's/family accounts, unlimited use or a purchase decision. John deferred integration until after
  first production; these gaps belong to a later explicitly selected follow-up, not launch prerequisites.
- WF-053's bounded inventory resolves legacy scope as preserve/hold. Detailed Nickell application requirements,
  whole-vault migration, legacy replacement designs and a future worker fleet remain outside this map's
  initial-release planning boundary. No speculative replacement backlog is created.

The items above are named existing-owner integration/qualification work or explicit optional/out-of-scope
follow-ups, not unanswered decisions required to keep this map open. They retain real delivery holds without
inventing readiness projects or claiming the work already exists.

## Out of scope

- Implementation, provisioning, DNS/hosts mutation, certificate creation/trust enrollment, credential inspection,
  enrollment or transfer, application/provider qualification calls, deployment, commit, push, publication or merge.
- Optional Jev relevance screening is explicitly allowed by John after the initial session disclosure; this is
  narrow discovery authorization, not authority for paid application generation or provider/account qualification.
- Delivery-record changes beyond the explicitly approved first-production planning handoff, TICKET/TASK
  decomposition or new TASK allocation, touching TASK-00510–00512, priority changes, reopening unrelated closed
  maps, Planning database cutover, automatic archiving or declaring existing holds resolved without their owners.
- Replacing GitHub CI merely because candidate checks need isolation; Kubernetes without a justified need;
  shared family provider credentials; silent provider substitution, paid fallback or budget expansion.
- Local CA signing material in the proxy, unrestricted web-app host administration, Docker-socket access or
  candidate code sharing production secrets/resource contention.
- Changing/removing Omphalos, Ideaverse, `$aios` or `$fight`, migrating routes/data or retiring useful operations.
  Investigation is not retirement authority; verified replacements and separate authorization remain mandatory.

## Planning handoff and verification

All eleven decisions are resolved. **John approved the bounded handoff below**, including existing-owner
amendments and one new EPIC destination. [EPIC-00021](../epics/00021-EPIC.md) is written/approved; existing-owner
requirements are [reconciled](research/private-production-owner-reconciliation.md), and its five-TICKET split is
accepted/written. All selected TASK decompositions are now accepted, including TICKET-00125's TASK-00532/00533.
Planning completion preserves existing approvals/holds and Board priority; it does not certify runtime,
authorize spending/cutover or make optional expansion a release prerequisite.

### Approved first-production planning handoff

John explicitly approved this bounded first-production planning pass after the WF-053 inventory closeout:

1. **Reconciled existing first-journey owners, not duplicated.** Applied WF-044/049's per-user OpenRouter connection,
   model/default/continuation and conversation-usage requirements with TICKET-00089/00090/00092/00098 and the
   creation/instruction-assistance consumers TICKET-00104/00107 where affected. Retain TASK-00342/00346/00350/00399
   holds until their actual suppliers are qualified. TICKET-00026/00027/00028 retain authoritative Planning,
   ordering and explicitly approved migration; the first milestone is useful database-backed browser Wayfinder.
   TICKET-00085/00088 and their existing default/continuation TASKs are aligned, as are EPIC-00006/00009/00010.
   The [reconciliation receipt](research/private-production-owner-reconciliation.md) records exact ownership:
   TASK-00352/00365 conversation usage, TASK-00323/00367 default/picker controls, TASK-00346 metadata and TASK-00399
   explicit allowance refresh. The later approved TASK-00520 now owns complete connection self-service/storage/UI;
   TASK-00521 adds optional allowance settings under TICKET-00089. TASK-00344 gains TASK-00520's lifecycle edge;
   the earlier TASK-00399/TASK-00346 edge remains. Neither allocation changes existing statuses/orders or clears holds.
2. **Written and approved: [EPIC-00021 — Deploy and recover private Fight Agent OS in staging and production](../epics/00021-EPIC.md).**
   Shape the actual production artifact/configuration and operator-run staged promotion/recovery supplier missing
   from the existing local installation owners. Include WF-048/050's selected environment/security, public TLS,
   real mail/scheduling integration, budget and coherent recovery boundaries. Reuse TICKET-00011 mail/outbox,
   TASK-00374 storage and TICKET-00117 diagnosis ownership; TICKET-00058/TASK-00222 consume a qualified runbook
   rather than supply it. Do not create a duplicate Planning, diagnosis, mail, credential or deployment engine.
   John confirmed the final scope in the EPIC-producing grill; that sole new-EPIC handoff is complete. This is
   planning approval, not a spending grant, readiness framework, implementation acceptance or mandate that
   production already exist before implementation. Do not reopen settled hosting, budget or recovery choices
   without a genuine new scope conflict.
3. **Explicitly exclude optional expansion from that first-production handoff.** WF-046/047/052's reusable local
   onboarding/wizard, optional human assignment and later managed execution extensions remain separately selectable.
   WF-051 ChatGPT integration is deferred until after first production and later explicit selection. WF-053 leaves
   legacy applications, routes, data and commands preserved; no replacement/retirement EPIC is proposed. Existing
   native local checks and independent provider choices remain.

The sole approved new EPIC is written, existing-owner requirements are reconciled, and John separately approved
the complete [five-TICKET split](../epics/00021-EPIC.md#approved-ticket-decomposition-and-current-handoff):
[TICKET-00121](../tickets/00121-TICKET.md) immutable artifact,
[TICKET-00122](../tickets/00122-TICKET.md) isolated environments,
[TICKET-00123](../tickets/00123-TICKET.md) real account mail,
[TICKET-00124](../tickets/00124-TICKET.md) coherent backup/restore, and
[TICKET-00125](../tickets/00125-TICKET.md) approved promotion/recovery. These are written accepted requirements,
not TASKs or delivered capabilities. No remaining map decision/new-EPIC/TICKET phase precedes TASK planning.

John separately approved TICKET-00089's two additions: [TASK-00520](../tasks/00520-TASK.md) complete connection
lifecycle/custody/API/UI after TASK-00072/00343, and [TASK-00521](../tasks/00521-TASK.md) optional allowance settings
after TASK-00520/00399. TASK-00344 consumes the lifecycle, with no reverse edge from it to admission/generation.
TASK-00343/00346/00399's references now name the supplier; actual qualification holds remain. Both new orders are
unranked. The amended TICKET is fully decomposed, not implemented. John subsequently approved TICKET-00121's
[two-TASK artifact split](../tickets/00121-TICKET.md#approved-task-decomposition-and-current-handoff):
[TASK-00522](../tasks/00522-TASK.md) runnable immutable packaging, then
[TASK-00523](../tasks/00523-TASK.md) actual GitHub CI delivery/retrieval. Application CalVer, `release/<version>`
branches and no required retained Git tags are accepted; exact source/digests remain authoritative. This changes
no existing CI trigger, TASK metadata or publication authority. John also approved TICKET-00122's
[three-TASK environment split](../tickets/00122-TICKET.md#approved-task-decomposition-and-current-handoff):
[TASK-00524](../tasks/00524-TASK.md) core staging HTTPS after TASK-00523/00063 and now the approved TASK-00284 supplier,
[TASK-00525](../tasks/00525-TASK.md) protected staging services/diagnosis after TASK-00524/00346/00487, and
[TASK-00526](../tasks/00526-TASK.md) isolated production with Managed PostgreSQL after TASK-00525.
All are unranked; actual provider/diagnosis/storage holds remain, and early mail/backup interfaces avoid reverse
waits for completed delivery. This grants no provisioning, spending, DNS, credential or target-operation authority.
John also approved TICKET-00123's [two-TASK mail split](../tickets/00123-TICKET.md#approved-task-decomposition-and-current-handoff):
[TASK-00527](../tasks/00527-TASK.md) actual staging invitation/reset receipt after TASK-00524, then
[TASK-00528](../tasks/00528-TASK.md) scheduled account-mail recovery across both environments after TASK-00527/00526.
[TASK-00284](../tasks/00284-TASK.md)/TICKET-00068 retain the sole shared real transport/enrollment primitive, now with
Resend Free HTTPS selected and actual account/target/effect holds unchanged. TASK-00524 adds TASK-00284 because
real-provider startup cannot use a development fallback. The transport qualifies independently of hosted staging;
no completed credential/notice journey reverse-blocks it, and no existing status/order changes.
John also approved TICKET-00124's [two-TASK recovery split](../tickets/00124-TICKET.md#approved-task-decomposition-and-current-handoff):
[TASK-00529](../tasks/00529-TASK.md) complete staging capture/restore after TASK-00525/00093/00233, then
[TASK-00530](../tasks/00530-TASK.md) managed-production recovery after TASK-00529/00528, inheriting TASK-00526.
Reuse existing Planning rebuild/redaction, Agent-authority restoration, mail/provider and diagnosis owners; no
existing TASK metadata changes, cleared holds or reverse dependencies. Spaces-only custody, human Bitwarden access,
conditional retention and exact point/loss/target/resource approvals remain. Staging does not wait for production,
and neither recovery slice performs first launch or the real Planning cutover.
John also approved TICKET-00125's [two-TASK release split](../tickets/00125-TICKET.md#approved-task-decomposition-and-current-handoff):
[TASK-00532](../tasks/00532-TASK.md) exact staging/reconciliation/compatible rollback after TASK-00529, then
[TASK-00533](../tasks/00533-TASK.md) final candidate promotion/browser Wayfinder after TASK-00532/00530/00362/00365/00367.
Final bytes must be re-staged after suppliers qualify; earlier candidate evidence is not final release approval.
TASK-00104 remains the real human-operated Planning cutover owner, with early release/recovery readiness and no
reverse dependency on final browser operation. Preserve frozen Markdown and use database authority for post-cutover
Planning closeout. All selected planning phases are complete: eleven deployment TASKs plus the existing owner's
two lifecycle/settings additions. Other amended catalog/default/continuation/usage requirements retain existing TASK
scopes, not implementation or automatic readiness. Optional expansion/deferred work and legacy retirement are not
silently completed or new release gates. Later invocations/acceptances remain separate. Do not allocate
speculative readiness TASKs, change Board priority or touch TASK-00510–00512. EPIC-00020 retains its approved top
priority. Existing delivery may continue under its own authority.

EPIC-00021 was allocated after synchronizing local `develop` at `d702533` and checking live/archive filenames
across fifteen local/cached refs and seven registered worktrees, including untracked files. This is local collision
evidence, not a remote reservation. That grill wrote exactly one new EPIC and no TICKET/TASK, without owner
amendments. The subsequent requested reconciliation amended existing requirements/TASK acceptance and one real
supplier edge; it allocated no new record. No external project edit, source import, commit or publication occurred.

The approved TICKET allocation checked seventeen local/cached refs and eight registered worktrees, including
live/archive/untracked filenames; TICKET-00120 was the highest observed. TICKET-00121–00125 are now written;
no TASK or operational authority was created. The allocation is local collision evidence, not a global reservation.

The later lifecycle allocation checked sixteen local/cached refs and eight registered worktrees including
live/archive/untracked TASK filenames; TASK-00519 was highest observed. TASK-00520/00521 are accepted/written,
not a global reservation or operational authority. Its [owner handoff](../tickets/00089-TICKET.md#approved-connection-lifecycle-additions-and-current-handoff)
records coverage and verification separately from earlier historical receipts.

The artifact allocation rechecked seventeen local/cached refs and nine registered worktrees, including
live/archive/untracked TASK filenames; highest was TASK-00521. TASK-00522/00523 are accepted local allocations,
not globally reserved or implemented. Their owning TICKET records verification separately from earlier receipts.

The environment allocation rechecked seventeen local/cached refs and nine registered worktrees, including
live/archive/untracked TASK filenames; highest was TASK-00523. TASK-00524–00526 are accepted local allocations,
not globally reserved or operationally qualified. TICKET-00122 owns the current planning-verification receipt.

The mail allocation rechecked seventeen local/cached refs and nine registered worktrees, including live/archive/
untracked TASK filenames; highest was TASK-00526. TASK-00527/00528 are local allocations, not globally reserved or
operationally qualified. TICKET-00123 owns the current planning-verification receipt and bounded supplier amendment.

The recovery allocation rechecked eighteen local/cached refs and nine registered worktrees, including live/archive/
untracked TASK filenames; highest was TASK-00528. TASK-00529/00530 are local allocations, not globally reserved or
operationally qualified. TICKET-00124 owns the current planning-verification receipt; earlier counts remain historical.

The release allocation rechecked eighteen local/cached refs and nine registered worktrees, including live/archive/
untracked TASK filenames; TASK-00531 was already allocated in another registered worktree and left untouched.
TASK-00532/00533 are local allocations, not globally reserved or operationally qualified. TICKET-00125 owns the final
planning-verification receipt; previous receipts remain historical. No existing TASK metadata/status/order changes.

Next: `/skill:next` from the selected planning checkout for read-only Board-based selection.
No execution starts automatically. Historical closeouts below retain their observation-time authority and
evidence limits; this handoff does not authorize implementation, spending, deployment or retirement.

### Charting verification

Initial baseline: read-only planning validation passed with 645 records, 496 active and current views. New WF IDs
were checked against live/archive files, local Git refs and registered worktrees without inspecting unrelated TASK
work. No remote fetch or claim of upstream-global ID reservation. TASK-00510–00512 remain untouched.

Post-edit `./bin/planning-check --write` and `./bin/planning-check` passed with 645 delivery records, 496 active,
current generated map/index views and no reported link/dependency errors. Tracked `git diff --check` and whitespace/
path-scope checks on all 13 new Wayfinder files passed. Only Wayfinder paths changed; no delivery owners or
TASK-00510–00512 changed. All work remains uncommitted in the isolated planning checkout.

The full `./bin/build` was not run: these are planning checks, not application/runtime acceptance, independent
Review/QA or release evidence. Docker/proxy health, providers, hosting and account eligibility remain unqualified.

### WF-044 research handoff

[WF-044 research](research/WF-044-per-user-openrouter-and-allowance.md) closes only the documentary capability and
ownership question. Proposed existing-owner amendments: TICKET-00089/TASK-00346 for protected per-user credentials
and narrow key-info adapter; TASK-00343 stays nonsecret; TASK-00399 owns explicit refresh/observations; TASK-00344/
00345 and conversation owners retain exact user/connection binding and one accounting owner; TICKET-00104/
TASK-00428 and other browser consumers must align. TASK-00396's Workflow-oriented scope does not automatically
cover conversation-only browser usage. Exact per-user lifecycle/UI allocation and operational participants remain
unresolved. All amendments/mappings require later authorized owner planning; no EPIC/TICKET/TASK was changed.

Documented `/key` limits/budget layers/free quota are not full account balance or guaranteed admission; keep
`/credits` management credentials outside the initial connection scope. Models, finite numeric configuration and
routing/privacy acceptance stay in WF-049/050. Optional ChatGPT and OpenRouter OAuth onboarding remain later
choices, not release gates. No extra decision is invented for implementation-owned gaps.

John subsequently accepted Fight's user-owned provider-connection API direction: collection
`/api/v1/me/provider-connections`, with recorded allowance at
`/api/v1/me/provider-connections/{connection_id}/allowance`. Connections identify provider/environment and support
multiple providers/keys without a provider-specific browser allowance interface. OpenRouter's upstream `/api/v1/key`
is unchanged. Safe reads never return secrets or poll providers; explicit refresh remains separate. Exact endpoint
contracts remain later owner work. This clarification does not reopen WF-044 or change the frontier/holds.

WF-044 public documentation was fetched without authentication; no provider/account API or model calls occurred.
`./bin/planning-check --write` and `./bin/planning-check` passed with 645 delivery records, 496 active and current
views. Tracked diff and all changed/untracked Wayfinder whitespace/path-scope checks passed; the 15 changed paths
are Wayfinder-only, and inspected delivery owners remain byte-identical to HEAD. Full `./bin/build` and live
provider/source/host qualification were not run. Work remains uncommitted in the isolated checkout.

### WF-045 research handoff

[WF-045 comparison](research/WF-045-digitalocean-hosting-and-costs.md) closes factual research only. Separate
production/staging Droplets with Managed PostgreSQL are recommended for discussion; App Platform's persistence,
full-source diagnosis and trusted-ingress gaps require explicit qualification/amendment, not silent substitution.
Reduced-staging web/DB/Spaces/registry floors are $87.10/$77.30 per month USD pre-tax; full launch totals remain
unknown. A $200 recurring/$100 temporary recovery discussion ceiling is unapproved. Existing-owner proposals reuse
TICKET-00023/00022/00011/00058/00117; production packaging/installation, real HTTPS mail/schedule, physical diagnosis
resources and the actual project runbook remain missing or unqualified. No EPIC/TICKET/TASK was changed.

Seven-day PostgreSQL PITR creates a new target and does not preserve external keys/object data. New Standard
clusters lose standby/read-only eligibility under the dated 2026-10-15/2026-11-30 transition; no assumed grandfathering
or obsolete ~$60 HA promise. Current Advanced shared-plan pricing/complete HA quote remains unknown. Region,
platform and budget selection must be obtained in WF-048; downtime, complete cost and recovery acceptance remain
WF-050. These known human decisions do not require speculative new records or implementation.

Public sources were fetched unauthenticated; no cloud/account API, provider qualification, provisioning, credential,
DNS/hosts, artifact build or runtime experiment occurred. Application build/live provider/source/host qualification
were not run. All work remains uncommitted in the isolated planning checkout. Post-reconciliation
`./bin/planning-check --write` and `./bin/planning-check` passed: 645 delivery records, 496 active, generated views
current. Tracked `git diff --check` plus whitespace/path-scope checks on all 16 changed/untracked Wayfinder paths
passed; inspected non-Wayfinder owners/configuration remain byte-identical to HEAD. Existing holds and priorities
are unchanged. These are planning checks, not fresh application, hosting or recovery acceptance.

### WF-046 local-operation and easy-install handoff

[WF-046](tickets/WF-046-define-optional-local-routing-trust-and-recovery.md) closes policy, not implementation:
shared scoped workstation DNS, project/worktree isolation, primary alias plus TASK/ten-hex stable Checkout hash,
fail-closed routing, per-machine CA custody with per-project base/wildcard leaves, and guided reusable eventual
easy-install. Explicit privileged steps, validate/apply/verify and independent recovery reuse existing contracts.
Sleep/shutdown may suspend/stop services without callbacks; wake/login/reboot/runtime restart reconcile durable
running intent with bounded recovery. A deliberate maintenance stop survives all those transitions until explicit
resume. No pre-login/asleep/offline availability guarantee, power-setting changes or blanket app/worktree autostart.

Proposed owner amendments reuse TICKET-00023/TASK-00072/00075/00076 and TICKET-00022/TASK-00063, preserving existing
shared-infrastructure ownership and standalone contributor/CI operation. Exact scoped-resolver/OS integration,
installer distribution/laptop profile, protected enrollment/apply and actual certificate/container/lifecycle
suppliers remain unallocated or unqualified. No EPIC/TICKET/TASK or external infrastructure project was changed.
WF-047 owns the intended hosted Planning/task-management and contributor-laptop authenticated boundary; local
preview domains remain workstation-only, and a second authoritative Planning server is not inferred for each
collaborator. WF-052 retains future UI; WF-053 retains migration/legacy retirement. No new EPIC is approved.

Both planning checks passed after reconciliation: 645 delivery records, 496 active, generated views current.
Tracked diff and all changed/new Wayfinder whitespace/path-scope checks passed. Changes remain confined to the
same 16 Wayfinder paths and uncommitted in the isolated checkout. No native infrastructure changes, credential,
certificate, startup/sleep/wake experiment, full application build or live runtime/independent acceptance occurred.
Existing delivery owners, priorities, holds and TASK-00510–00512 remain unchanged.

### WF-047 collaboration, source and credential handoff

[WF-047](tickets/WF-047-bound-remote-harness-git-and-candidate-execution.md) closes the architectural boundary,
including optional human TASK responsibility, Git-flow source snapshots and central UUID/reference allocation.
Proposed owner amendments reuse TICKET-00024/00025 for source/context, TICKET-00026/00027/00028 for responsibility,
queue/identity/cutover, and TICKET-00032/00064 for execution ownership and Team selection. These are not delivery
edits, an immediate numbering change or a second claim engine. Markdown remains authoritative.

TICKET-00059/00060/00061/00063 retain creation → protected sink delivery → exact activation → bounded signing/use.
Browser provisioning includes authorized recipient binding without another login; actual first-recipient proof,
outbound exact-delivery receipts and storage/signer protection alongside native bash remain qualification gaps.
A keychain alone does not prove those guarantees. Existing recovery/revocation requirements are not separate
approval questions or new permission. No native Agent bash access is removed by this map.

Actual hosted Git-read/materialization and future MCP/browser CI suppliers need later owner allocation/qualification.
Managed Git writes and dedicated managed workers are deferred; initial source access is read-only, contributor
publication uses individual GitHub authority, and existing required checks/Review/QA remain. First-browser scope
does not automatically expand to include every collaboration capability. WF-048/049/050 retain hosting/promotion,
journey and go/no-go. No EPIC destination or delivery-record amendment is authorized by this closure.

Planning checks and changed-file whitespace/scope checks are the verification for this decision. No application
build, independent Review/QA, credential/transport/runtime qualification or CI/Git effect occurred. Existing
owners, holds, Board priority and TASK-00510–00512 are unchanged; work remains uncommitted in the isolated checkout.

### WF-048 staging, promotion and environment-budget handoff

[WF-048](tickets/WF-048-define-staging-and-artifact-promotion.md) closes the policy interview and owner/evidence
reconciliation. SFO3 is the cost-first SFO/NYC distance choice, not a measured latency claim. Separate application
Droplets/databases preserve isolation, with smaller parity staging and non-HA production conditional on verified
recovery. The sub-$100/month combined infrastructure preference is not a qualified quote or spend authority.
Operator-approved exact-artifact promotion includes optional project-specific UAT and independently configured
per-environment provider budgets; technical Review/QA and per-user protected keys remain mandatory where applicable.

Re-read owners confirm TASK-00072 excludes production deployment, TASK-00063 excludes public TLS/reverse-proxy
enrollment, TICKET-00011 excludes real-provider enrollment and operating scheduling, and TASK-00222 still lacks a
qualified project target/runbook. TASK-00212/00215 consume release policy/certification rather than supplying the
missing production package; TASK-00215's managed independent acceptance hold remains. TASK-00374 retains private
SeaweedFS; Spaces in the price table is not a storage decision. TICKET-00117's actual physical/authority/supervision/
restore/ingress holds remain. TASK-00344/00345 and TASK-00346/00350/00399 retain original accounting and protected
runtime/source holds; their proposed per-user/environment amendments are not delivery edits.

Propose later authorized allocation of **Fight-specific production packaging and operator-run staging/promotion/
recovery**, reusing installation/security/deployment owners rather than implementing a generic deployer or UAT
platform. Real mail/scheduling, protected host participants, exact capacity and recovery suppliers remain explicit
missing work. No EPIC/TICKET/TASK was created or amended; no unrelated map reopened. WF-050 receives full-cost
feasibility and launch/recovery evidence obligations, not an assumption that $87.10 covers complete operations.

Evidence collection used repository reads and optional discovery: four relevance requests, complete coverage, reported
$0.000376488 and no unknown cost. Three not-positive candidates were read directly for contracts explicitly
referenced by inspected owners. No application build, live provider/cloud/mail request, native runtime probe,
provisioning, migration, restore, independent Review/QA or delivery occurred.

Post-reconciliation `./bin/planning-check --write` and `./bin/planning-check` passed: **645 delivery records,
496 active**, generated map/index views current. Tracked diff and all changed/untracked whitespace/path-scope
checks passed across **17 Wayfinder-only paths**. Tracked delivery/configuration files remain byte-identical to
HEAD, including existing holds, Board order and TASK-00510–00512. All work remains uncommitted in the isolated
planning checkout; this is planning verification, not application or launch acceptance.

### WF-049 browser journey, current models and usage handoff

[WF-049](tickets/WF-049-select-first-browser-journey-and-switching-behavior.md) closes the selected browser
Wayfinder journey, model/default/continuation policy and usage-owner reconciliation. John targets the database
transition plus usable in-browser Planning for first production deployment, followed by real-use assessment and
requested concrete bugfix work. TICKET-00028 retains the real migration/atomic authority switch; no new production
prerequisite, readiness framework, policy tests or automatic TASKs are created.

Current OpenRouter choices are fetched server-side on picker load with light caching and explicit refresh/default
updates. Existing conversations retain their model until explicit between-turn linked continuation. Model IDs,
finite limits/reset periods and suggested 15-minute TTL remain ordinary configuration. Secrets stay in protected
per-user/environment connections; metadata refresh does not run inference or poll allowance.

Usage presentation separates conversation cost/tokens, authoritative environment caps/reservations and independent
provider allowance snapshots. Known/estimated/partial/unknown values stay distinct; cancellation or reconnect does
not erase cost and cumulative streams are normalized once. Missing allowance is visible without disabling useful
Planning. TASK-00344/00345 own admission/original accounting; TASK-00346/00399 retain protected source holds.

Concrete later amendment/allocation: TICKET-00090/00092 consume safe conversation-attributed usage from TASK-00345,
coordinating TICKET-00098 rather than inventing a Workflow or another ledger. TASK-00396 and TASK-00391 are explicitly
Workflow-oriented, not an already-complete browser Planning usage supplier. TASK-00343 remains nonsecret catalog
policy, proposed TASK-00346 serves protected metadata, existing profile/default writers and TASK-00367 consume safe
choices. Exact discovery/default UI and per-user lifecycle wiring remain ordinary product work with the existing
owners. TASK-00342/00367 preserve one continuation and proposal applicability; no duplicate transcript/live channel.

Only known repository contracts were read for this closeout; no fresh provider/research/screening call, application
build, independent Review/QA, credential change, migration, runtime effect or deployment occurred. Existing delivery
records, holds, Board order and TASK-00510–00512 are unchanged. No EPIC destination or decomposition is authorized.

Post-closeout `./bin/planning-check --write` and `./bin/planning-check` passed: **645 delivery records, 496 active**,
current generated views. Tracked diff and all changed/untracked whitespace/path-scope checks passed across the
same **17 Wayfinder-only paths**; tracked delivery/configuration files remain unchanged from HEAD. Work remains
uncommitted in the selected isolated checkout. At WF-049 closeout, WF-050 was still Open; that closeout did not
start its interview. Its subsequent resolution is recorded below.

### WF-050 operations, recovery and cost handoff

[WF-050](tickets/WF-050-define-private-launch-readiness-and-recovery.md#final-practical-owner-handoff) closes its
human decisions and finite operator contract: hybrid PostgreSQL, two 2 GiB app VMs, bounded staging processes,
Spaces-only history, Bitwarden recovery kit, Resend Free, compatible application rollback within approved deployment
and explicit approval for database restore. John remains the exact-candidate production/recovery operator. Under
$100/month is tax-inclusive recurring preference, not a complete quote; temporary recovery needs separate approval.

Reuse WF-048's owner table and WF-049's browser allocation. Retain concrete public TLS/package/runbook, mail/schedule,
protected runtime, coherent DB/files/key recovery, monitoring/diagnosis capacity and full-cost gaps within ordinary
delivery work. Current permissions/security, original usage liabilities, migration authority and all existing
holds remain. Exact target observations belong to authorized deployment, not a production-before-production gate.

The proposed Fight-specific production packaging/operator-run staging/promotion/recovery destination still needs
later authorization; no new EPIC is approved or created, and no delivery owner or Board priority changed. Complete
remaining map decisions/approved EPIC handoffs before TICKET/TASK decomposition. This closure used existing planning
contracts and recorded answers, not fresh provider/account research, application build, independent Review/QA,
runtime/restore evidence or publication. All changes remain uncommitted Wayfinder-only work.

Closure checks passed: `./bin/planning-check --write`, `./bin/planning-check` (645 records, 496 active, generated
views current), tracked diff and whitespace/path-scope checks across all 18 changed/untracked Wayfinder paths.
Delivery/configuration files remain unchanged. These are planning receipts, not application/recovery qualification.

### WF-051 documentary findings and accepted deferral

[Public research](research/WF-051-chatgpt-plan-usage-and-private-hosting.md) found a supported SIWC Responses route
and explicit OSS self-hosted VM guide—not authority to reuse personal Codex credentials. The VM flow requires local
OAuth plus protected transfer; the separate hosted website/partner grant is not assumed available. Exact household
eligibility and browser-only onboarding remain concrete qualification gaps. Per-account model/tool/usage findings
are recorded without performing account/API calls. Codex rate-limit RPCs are not proven app-sharing allowance.

John explicitly chose **deferral until after first production**. WF-051 is Closed; the researched route and
qualification gaps remain a named later follow-up, not a rejected capability or implicit future implementation.
Revisit only after the milestone and a later explicit selection, rechecking current documentation/account/runtime
facts then. No new EPIC destination, implementation TASK or runtime is created. Existing local development providers
are unchanged, and per-user OpenRouter remains the first browser production provider. Existing owners, holds,
Board priority, TASK-00510–00512 and Markdown authority are unchanged; no new research, credential/account/runtime
or publication effect occurred during deferral closeout.

### WF-052 closed scripted setup journey and owner handoff

John selected project selection/creation from the browser or terminal Pi, followed by hostname/destination approval
and an approved scripted Composer/npm/Docker Compose setup sequence. Agent help is optional, never required for
normal execution. [WF-052](tickets/WF-052-define-future-local-domain-management-flow.md) records the approved flow,
existing-owner trace and proposed extensions; WF-021/TICKET-00100–00103 retain creation ownership, and TICKET-00023/
WF-046 retain local installation/routing boundaries. Existing-checkout setup, terminal presentation and app startup
need later explicit allocation. John also approved explicitly selected database initialization/migrations, with
reset and seed/sample-data operations excluded by default. The selected local target/effects belong in the approved
plan; failed/uncertain migrations retain truthful state rather than trigger automatic reset or blind replay.
WF-052 carries forward scoped local administration, original-operation reconciliation, independent recovery and
contributor/CI independence. Exact script/transport/OS/Pi integration remains ordinary later delivery, not another
speculative planning or certification gate. WF-052 is Closed on these decisions, not implemented capability.
No existing source/runtime hold is cleared and no delivery record, account, host, database or runtime is changed.

### WF-053 inventory closeout

[Source research](research/WF-053-legacy-capabilities-and-retirement-boundaries.md) found distinct useful local
infrastructure, homepage/application, vault/personal-workflow and engineering-entry/runtime capabilities. Preserve
all of them. The inspected dispatcher, homepage and brief endpoint provide sampled implementation evidence;
README/catalog/configuration claims remain distinct from untested live startup, routes, jobs and integrations.
Private locators/hashes stay in ignored scratch; no personal records or credentials were accessed. No new public
research, provider/evaluator call, runtime check, source change or legacy transition occurred.

WF-053 and this map are Closed on bounded decisions/research, not delivery acceptance. All unresolved target facts
remain ordinary later owner work or explicitly excluded optional transitions. John subsequently approved the
[first-production planning handoff](#approved-first-production-planning-handoff). Its sole new destination is now
written as [EPIC-00021](../epics/00021-EPIC.md); the subsequent
[approved owner reconciliation](research/private-production-owner-reconciliation.md) is complete at requirement
level. John subsequently accepted EPIC-00021's five deployment TICKETs and, separately, TICKET-00089's two
lifecycle/settings additions as TASK-00520/00521. The subsequent accepted splits below complete deployment TASK
planning; no retirement is invoked.

TICKET-00121's artifact/CI slices were subsequently accepted as TASK-00522/00523.

TICKET-00122's environment slices were subsequently accepted as TASK-00524–00526.

TICKET-00123's mail slices were subsequently accepted as TASK-00527/00528, reusing TASK-00284 rather than a second transport.

TICKET-00124's staging/managed-production recovery split was subsequently accepted as TASK-00529/00530, not live evidence.

TICKET-00125's release slices are accepted as TASK-00532/00533, completing the selected planning phases only.

Next: `/skill:next` for read-only Board-based selection; no automatic implementation or legacy retirement.
