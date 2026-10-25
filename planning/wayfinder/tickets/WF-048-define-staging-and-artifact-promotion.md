# Define staging and artifact promotion

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-045](WF-045-compare-digitalocean-hosting-and-operating-costs.md), [WF-047](WF-047-bound-remote-harness-git-and-candidate-execution.md)

## Question

What production-like staging and build-once promotion contract is sufficient for a recoverable first private
release on the selected hosting topology?

## Must decide

- Use the same immutable built artifact, runtime/extensions and database major as production, with smaller
  staging capacity. Identify required build exports/assets and packaging suppliers; current development Compose
  does not prove a deployable production image.
- Agree HTTPS, trusted ingress/proxy behavior, same-origin authentication, Secure environment-scoped cookies,
  debug-off configuration and restricted staging access with a visible staging identity.
- Separate databases/data, secrets, credentials, provider budgets and operational targets. Use synthetic/sanitized
  data and captured/test email in staging; no production credential leakage, shared sessions or real-user mail.
- Preserve the same migration, required application-worker/scheduling and deployment mechanisms. Distinguish
  application conversation/credential workers from optional candidate-code execution; no extra execution fleet
  is required merely to validate browser planning.
- Prefer build once, validate in staging and promote that exact artifact. Bind candidate digest, configuration
  revision, migration compatibility, approval, observed target identity and health to the deployment record.
- Compare separate DigitalOcean project grouping with actual account/network/credential isolation; grouping alone
  is not a security boundary. Include staging storage/backup/log/email costs in the selected finite budget.
- Define operator-run promotion, failed/uncertain promotion reconciliation and retention of the previous artifact.
  Application rollback and data recovery differ; migration forward/rollback compatibility is explicit, not assumed.
- Map missing staging/artifact/runbook suppliers into existing installation/security/deployment owners or a later
  proposed destination. First operator-run deployment does not require the entire managed Release Manager product.

## Resolution boundary

This decision chooses staging/promotion policy and expected later evidence, not a deployment or artifact build.
Use hosting research and the remote-operation boundary; request human input for cost/downtime or recovery choices.
No provisioning, secrets, email, migrations, backup/restore exercise or release effect is authorized here.

## Resolution

Closed on John's recorded policy decisions and the existing-owner/evidence reconciliation below. This is not
installed hosting, implementation acceptance, a cost quote or delivery readiness. WF-045 supplies dated research
and WF-047 supplies the hosted/local boundary; their qualification holds remain. John clarified
that interruption behavior follows the already-established client/server separation: do not reopen that choice
through repetitive recovery approvals. This is not an instruction to skip genuinely unresolved safety tradeoffs.

### Subsequent WF-050 cost decision — preserve this record's history

John later selected [WF-050's hybrid database and Spaces-only direction](WF-050-define-private-launch-readiness-and-recovery.md#approved-hybrid-database-hosting-and-spaces-only-backups):
managed PostgreSQL for production, self-managed PostgreSQL on staging, and no additional B2 backup copy. He favors
2 GiB RAM / 50 GiB SSD application Droplets in both environments so staging can accommodate its database plus PHP,
workers and other services. This supersedes the two-managed-cluster and smaller-staging-VM illustration below,
not separate environments, PostgreSQL major/extensions/schema alignment or operator-approved exact-artifact
promotion. Managed-versus-self-managed operations are explicitly different. WF-048 remains Closed; historical
prices/decisions below are not rewritten as fresh evidence or current spending authority.

### Selected application hosting model: Droplets

John selected the proposed Droplet direction: separate production and staging DigitalOcean application Droplets
with separate Managed PostgreSQL clusters, production NGINX/PHP-FPM and GitHub CI for immutable artifacts. Host
patching, TLS renewal and supervision remain our operational responsibility. App Platform is not selected.
That initial answer selected hosting direction only; subsequent regional, non-HA and budget decisions are recorded
below. Exact sizes, recovery targets, a storage replacement, provisioning and spending remain unapproved.

The meaningful tradeoff is operational responsibility versus platform constraints:

- Droplets fit the existing host/service diagnosis and durable-storage needs more directly, but we own OS/image
  patching, TLS renewal, supervision, capacity and deployment operations. Managed PostgreSQL reduces database
  operations, not the application-host workload. No claim that the current development Compose stack is production.
- App Platform manages the underlying application hosts and edge, but the researched ephemeral filesystem,
  lack of volumes and 2,048-byte forwarded-log limit leave accepted storage/full-diagnosis requirements unqualified.
  Trusted ingress and background-work support also need composed qualification. Choosing it would require
  resolving those adaptations rather than silently dropping existing requirements.
- WF-045's 2026-10-23 pre-tax comparison floors are $87.10/month for Droplets and $77.30 for App Platform with
  reduced staging. These are dated illustrative web/DB/storage/registry floors using minimum non-HA databases,
  not complete launch costs, equivalent measured capacity, selected SKUs or current account quotes. Its proposed
  $200 recurring/$100 temporary reserve is not approved; full operations cost and actual HA pricing remain unknown.

Use [WF-045's comparison](../research/WF-045-digitalocean-hosting-and-costs.md) for evidence and limitations.
The subsequent answers below resolve regional direction, smaller staging, budget preference and non-HA intent;
next reconcile build-once promotion and operator-run release ownership. WF-050 retains final downtime/RPO/RTO, complete recovery cost and launch acceptance.
Non-HA production acceptance comes from John's explicit answer below, not the minimum-price table. Existing TICKET-00023 private SeaweedFS and
local installation contracts are not silently replaced by Managed PostgreSQL/Spaces or a production variant;
actual owner amendments and packaging/security/runbook suppliers remain later authorized planning.

No cloud/account calls, provider qualification, provisioning, secrets, builds or delivery records changed.

### Regional direction: SFO3 under John's cost-first distance criterion

John initially delegated regional fit for San Antonio, Texas. After the provisional ATL1 recommendation, he
judged Premium CPU plans unlikely to justify the unmeasured latency benefit and suggested choosing the shorter
straight-line distance between SFO and NYC instead. Apply that criterion: **SFO3 is the planning default** for
both environments, superseding ATL1. Keep each application's database colocated with it and environments
independently isolated. No fastest-region claim, spend approval or resource creation follows.

[The regional follow-up](../research/WF-048-region-for-san-antonio.md) retains the four official public-source
checks and records an approximate city-center great-circle calculation: San Antonio–San Francisco 1,488 miles,
versus San Antonio–New York 1,581 miles. Those are not datacenter coordinates or measured network paths. SFO3's
advertised Regular Basic/shared PostgreSQL support avoids ATL1's Premium-only constraint; exact account/SKU
availability, database edition, sizing and full cost remain unqualified; non-HA intent is accepted below. WF-045's $87.10 floor is still only
its dated minimum-plan illustration, not an approved SFO3 launch budget. Do not infer quantified savings or
latency benefit from either regional proposal.

Staging in the same region is not a disaster-recovery site. Independent recovery-copy placement remains WF-050.
No Spaces/SeaweedFS replacement or provider diagnosis product is selected by this regional choice. The four
unauthenticated source fetches were public documentation research, not cloud/account API or application-provider
calls; no latency probes, credentials or infrastructure effects occurred.

### Approved staging capacity: smaller, with the same software and deployment setup

John selected smaller staging capacity with the same software and deployment setup. Preserve the production
artifact, runtime/extensions, database major, migrations, required background services and deployment/security mechanisms.
Use it for functional/integration and release rehearsals; do not claim it proves production concurrency or capacity.
Production-sized performance/recovery exercises require a separately budgeted temporary test profile when needed.
Do not shrink below measured requirements for the same supported mechanisms or silently omit diagnostics/retention.

Continuously production-sized staging is not selected. Exact SFO3 sizes, supported DB plans and full
recurring/temporary budgets remain undecided; no numeric savings, spend authority or cost ceiling is inferred.

### Budget preference: below $100/month combined, if feasible

John prefers **below $100/month for staging and production together, if possible**. This replaces the proposed
$200 recurring planning ceiling; it is a target to investigate, not proof of feasibility, a hard provider cap or
spending authority. The separate proposed $100 temporary recovery/test allowance was not accepted and remains
unapproved. Do not turn recurring budget preference into permission for temporary charges.

Continue the infrastructure/operations scope of the question: both application hosts, databases, required
background services, storage/registry, backups, accepted diagnosis/retention, email and applicable CI/network/domain
costs. Model inference remains separately bounded, not silently included or newly authorized. Research prices are
pre-tax; John's answer does not settle tax treatment or establish an all-in invoice ceiling. Operator time is not
priced. Actual full cost remains unknown; return with any unavoidable overage instead of interpreting “if possible”
as permission to exceed the target or drop requirements.

WF-045's dated non-HA comparison is $87.10/month, leaving less than $12.90 below the target before missing costs.
It includes illustrative Spaces/registry choices, not an accepted replacement for the existing storage owner.
The illustrative operations addition and paid email bring that comparison to $148.30, still not a qualified
launch total. Neither that extra operations VM nor a paid email tier is established as mandatory: investigate
supported consolidation/free-tier fit without assuming spare capacity, waiving isolation or reducing retention.
Sub-$100 operation is therefore **not yet demonstrated**, but the illustrative higher total is not proof that it
is impossible. Actual supported SKUs, mail/diagnosis/storage requirements and recurring versus recovery costs
must be reconciled before any purchasing decision.

### Approved production availability tradeoff: non-HA with verified recovery

John explicitly accepted maintenance/failure outages provided verified backups and recovery exist. Plan one
production application Droplet and a single-node Managed PostgreSQL cluster, with smaller independent staging,
to pursue the combined sub-$100/month target. This is **not highly available**: maintenance or failures may
interrupt service while the provider or operator recovers it. Managed backups and replacement behavior do not
make a single node an uninterrupted service or establish a recovery deadline.

This accepts the non-HA topology, not unbounded downtime, data loss or an untested restore process. Backups,
protected keys, recoverable object data, verified restore and operational diagnosis remain required. Exact
acceptable downtime/RPO/RTO and recovery-budget acceptance remain WF-050. No size, spending, temporary reserve,
complete-cost claim or recovery-time commitment is approved. HA is not a first-release requirement; if later
needed, obtain a current supported quote rather than relying on obsolete Standard standby pricing.

### Approved production promotion authority: operator-approved exact artifact

John agreed to operator-approved promotion for the first private release: GitHub CI builds an immutable artifact,
staging validates that artifact, then John or an explicitly authorized operator approves promotion of its exact
digest to production. A push, merge or green staging result alone must not deploy production. Fixed deployment
steps may be scripted; this is a human approval boundary, not a requirement to perform every step by hand, nor
a choice of a new deployment UI/tool or the full managed Release Manager product.

Carry the already-selected software/deployment parity into promotion: no production rebuild, environment
configuration and secrets supplied separately, migrations rehearsed against staging, and exact artifact/config/
migration/target/approval/health evidence recorded. Retain the previous artifact, but allow application rollback
only where schema/data compatibility is established; otherwise use the qualified forward-fix/recovery plan.
An uncertain deployment outcome requires inspecting the actual target and reconciling the original operation,
not blindly repeating migrations or calling an unverified rollback successful. Required verification, independent
Review/QA and protected release authority remain separate from candidate CI execution.

### Approved project-specific optional UAT phase

John additionally specified that staging for **Fight Agent OS** should correlate with an optional user acceptance
testing (UAT) phase. This is a project-specific release workflow choice, not a default imposed on other projects,
a new mandatory phase for every TASK, or a change to repository-wide skills and delivery rules.

The release flow is: build once → technical staging validation → **optional human UAT in staging** → explicit
production promotion approval. UAT lets intended users assess the actual staged candidate's behavior and usability;
it is distinct from automated checks and independent technical Review/QA, and replaces none of those requirements.
Staging remains useful for technical validation when UAT is not selected. Production still requires approval.

Record whether UAT was selected for the release and its outcome against the exact artifact and relevant staging
configuration. “Not selected” is not “passed.” If selected, unresolved rejection or incomplete acceptance must be
visible to the promotion decision, not silently bypassed as an optional step. A changed candidate must not inherit
acceptance of different behavior; reconcile affected validation/UAT before promotion. Human UAT is not authority
for real-user mail, production data access, provider spending, migration effects or production deployment.

No generic UAT engine, new delivery record, permanent test fleet or cross-project phase is created here. Map the
project-specific phase into the later deployment/runbook handoff; exact UI and implementation remain unselected.

### Approved staging/UAT live provider use and per-environment budgets

Carry forward separate staging data, credentials, environment-scoped sessions and provider budgets, restricted
access with visible staging identity, HTTPS/debug-off behavior, synthetic/sanitized fixtures and captured test email.
DigitalOcean project grouping alone supplies no access boundary. These requirements are not relaxed for human UAT.

John approved **explicitly enabled, tightly capped live OpenRouter checks** for meaningful browser Planning UAT,
using each participating user's own separate staging connection/key. Keep routine automated checks
synthetic; clearly identify simulated responses and do not present them as live-provider qualification. No shared
admin/family key, production-key reuse, silent fallback or automated budget expansion. A permitted live run must
use the accepted credential custody/admission/accounting path, including outstanding/uncertain cost, not bypass it.

John specified **budgets set per environment**, with production caps likely higher than staging/testing caps.
Configure production and staging independently rather than using one undifferentiated application budget. Higher
production allowance is an expected usage pattern, not a hard-coded inequality, chosen amount or automatic
increase. Do not copy production limits into staging when promoting an artifact: budgets are environment policy,
not built-artifact content.

Preserve per-user protected provider connections and their applicable limits alongside environment budgets;
a separate environment cap does not create a shared family/admin credential or override a stricter connection,
user or provider limit. Attribute admission, reservations, final usage and uncertain liability to the actual
environment and connection generation. Exhausted staging budget cannot borrow production allowance, silently
switch credentials or expand itself. Replacing/disconnecting a key or deploying a new artifact does not erase
admitted liability. Separate local caps/keys do not prove separate upstream wallets or account-wide quota;
present any shared provider-account constraints truthfully rather than promising independent provider capacity.

This selects future staging-use and budget policy, not a live call, numeric allowance, reset period or new spend
authority. Specific limits, applicable enforcement and enabled users require explicit configuration/qualification
before any paid run. Simulated-only sessions must be identified and cannot establish actual provider behavior.
The combined sub-$100/month hosting/operations preference remains separate from these model-inference budgets.

### Reconciled staging isolation and artifact evidence contract

These are delivery requirements derived from the approved policy and existing owners, not newly qualified
mechanisms or another architecture interview:

- Keep production and staging on separate application hosts and separate database clusters, with distinct data,
  credentials, signing/encryption keys, storage access and environment policy. Explicit network/firewall and
  service authorization must deny cross-environment access; DigitalOcean project labels alone provide none of
  this. Ordinary staging services cannot administer production. Restrict staging to authorized testers and show
  its environment identity clearly. Staging is not a DR site and never substitutes for an independent recovery copy.
- Both deployments must qualify HTTPS, exact canonical same-origin browser/API behavior, Secure environment-scoped
  cookies without parent-domain session sharing, current permissions, CSP/headers, throttling and debug-off behavior.
  Verify actual TLS termination and trusted hops; do not enable arbitrary forwarded headers or assume local
  `localhost:18443` configuration becomes production-ready through a DNS change. Public certificate enrollment,
  renewal and external ingress operation still require their own operator supplier and evidence.
- Use synthetic or expressly sanitized staging data, captured/test email and isolated provider connections.
  A production restore into staging is not permitted merely because restore tooling exists. Test-email capture
  must remain private and separate from the promoted production artifact's developer-tool exposure. Any later
  real-mail qualification needs separately authorized recipients/effects; optional UAT grants none of them.
- Build the exact release source once in the approved GitHub CI path, not on an application host. Record source
  commit, lockfiles, artifact digest(s), runtime/extensions, build exports and provenance. Stage and promote the
  same immutable application bytes; no mutable `latest` reference, production dependency install/rebuild, source
  bind mount or `.runs/` runtime dependency. Version environment configuration independently, with secrets supplied
  only to the intended services. Required assets include `var/client/build/`, runtime-needed private exports and
  the password corpus/license; identify the exact package manifest rather than copying a development checkout.
- Preserve the same PHP/runtime-extension set, PostgreSQL major, migration and required worker/scheduling mechanisms
  across staging and production. The inspected checkout uses PHP 8.5.12 FPM with Xdebug/local settings and PostgreSQL
  17; those are development facts, not a qualified production artifact or a new eternal version constraint.
  PostgreSQL 17 is the existing parity candidate; the delivery owner must qualify the selected Managed PostgreSQL
  major/extensions/connection limits against both environments before any provisioning or migration.
- Rehearse explicit migrations and worker shutdown/drain/restart behavior with the exact candidate and supported
  prior schema. Startup does not silently migrate. Smaller staging proves functional/deployment behavior, not
  production throughput, retention capacity or restore duration. Retain previous artifact/config references and
  actual health evidence; reject stale approval after relevant candidate/configuration/migration changes.
- Promotion must name target, artifact/configuration/migration revisions, applicable technical acceptance and UAT
  disposition, bounded effects, operator approval, actual observed deployment identity and health. A request
  acknowledgement is not completion. Reconcile an uncertain effect before retry; rollback requires compatible
  schema/data and explicit authority, otherwise stop for the qualified forward-fix/recovery path. Existing managed
  release contracts remain intact; this project-specific operator path does not implement them.

### Existing-owner and missing-supplier handoff

The records below were re-read for this reconciliation. References identify reuse or proposed later amendments,
**not completed suppliers, cleared holds, newly accepted delivery scope or a change to Board priority**.

| Concern | Existing owner / precise boundary | Remaining handoff before delivery or use |
|---|---|---|
| Runtime and production packaging | [TICKET-00023](../../tickets/00023-TICKET.md), [TASK-00072](../../tasks/00072-TASK.md) own the local installation and guidance; TASK-00072 explicitly excludes production deployment and observability | Missing Fight-specific production artifact/profile and operator installation allocation. Reuse the application topology and guide, but obtain an authorized owner amendment or bounded production supplier; do not silently expand TASK-00072 |
| Private data and object storage | TASK-00072 plus [TASK-00374](../../tasks/00374-TASK.md) own the private SeaweedFS boundary and verified evidence storage; [TICKET-00117](../../tickets/00117-TICKET.md) owns operational storage qualification | Qualify managed DB connectivity/migrations and isolated environment storage, durable bytes, credentials, references and restore. Spaces in the price comparison is not an accepted replacement; artifact registry storage is distinct from business evidence storage |
| HTTPS and browser isolation | [TICKET-00022](../../tickets/00022-TICKET.md), [TASK-00063](../../tasks/00063-TASK.md), TASK-00064 and the existing authentication journeys | Reuse envelope/denial/redaction behavior, with actual production/staging origin, cookie and proxy evidence. TASK-00063 excludes public certificate/reverse-proxy enrollment; the production operator supplier must own that operational gap |
| Credential email and schedules | [TICKET-00011](../../tickets/00011-TICKET.md) owns package-backed delivery/recovery; its completed scope excludes scheduling and real-provider enrollment | Allocate and qualify the real provider adapter/enrollment and protected operating schedule in later planning. Reuse the package queue and bounded runner; null/memory/catcher are not production delivery. HTTPS mail remains a candidate approach, not an enrolled supplier |
| Application conversation workers | [TASK-00350](../../tasks/00350-TASK.md) and the existing TICKET-00090 invocation/attempt owners | Protected host/broker/storage/process participants remain unmapped/unqualified. A Droplet or app service supervisor does not satisfy them. Do not confuse these required application workers with the deferred candidate-code fleet |
| Environment provider budgets and usage | [TASK-00344](../../tasks/00344-TASK.md) admission/reservations, [TASK-00345](../../tasks/00345-TASK.md) original outcomes, [TASK-00346](../../tasks/00346-TASK.md) protected OpenRouter; [TASK-00399](../../tasks/00399-TASK.md) separate allowance observations | Carry WF-044's proposed per-user connection amendment plus environment-policy dimensions into existing owners, not a second accounting store. Exact budget configuration/management surface remains to allocate. Preserve TASK-00346/00350 host holds and TASK-00399's source/refresh mapping hold; no key enrollment or source qualification here |
| Project policy and exact certification | [TASK-00212](../../tasks/00212-TASK.md) under TICKET-00055; [TASK-00215](../../tasks/00215-TASK.md) under TICKET-00056 | These consume a qualified project runbook; they do not supply Fight's production packaging. TASK-00215 remains `needs-info` for managed independent Review/QA primitives. Keep current native/local independent acceptance without claiming the managed implementation is delivered |
| Deployment and project-specific UAT | [TICKET-00058](../../tickets/00058-TICKET.md), [TASK-00222](../../tasks/00222-TASK.md) preflight and its rollout/health consumers | Missing qualified Fight operator runbook/target/observation/recovery supplier. Place this project's optional UAT disposition and human promotion in that runbook; no generic UAT platform or new per-stage TASKs. TASK-00222 remains `needs-info`; an operator-run first deployment is not managed Release Manager completion |
| Diagnosis capacity and recovery | [TICKET-00117](../../tickets/00117-TICKET.md), early TASK-00481–00485 and composed TASK-00486/00487 | Preserve actual authority, physical storage/isolation, supervision, temporary restore and ingress qualification holds. Full accepted source/retention/recovery behavior remains; do not trade it away to fit a price. The illustrative extra operations VM is not selected or proved necessary |

Proposed missing destination for later authorized planning: **Fight-specific production packaging and operator-run
staging/promotion/recovery**, composed with the existing installation/security/deployment owners. This is a named
delivery gap, not an approved new EPIC, allocated TICKET/TASK or permission to reopen closed owner maps. Later
planning must decide the precise reuse/amendment/new-supplier split, including real email/scheduling and operational
service allocation. No production capability is hidden behind a completed local installation or managed consumer.

### Verification still required — none executed here

| Qualification | Required observable evidence / failure boundary |
|---|---|
| Artifact completeness and parity | Fresh exact-source build/required checks, independent Review/QA, image/runtime/extension identities and staged-versus-promoted digest equality; runnable without checkout mounts or `.runs/`; production assets/exports present and secrets/developer exposure absent |
| Environment isolation | Actual HTTPS/trust/origin/cookie/proxy/CORS/permission denial results; staging credentials cannot read/write production DB, objects, secrets or administration; captured mail remains restricted |
| Provider controls | Authorized bounded real staging interaction, actual per-user/environment admission and accounting, exhaustion-to-zero-new-dispatch, retained uncertain liability and no cross-environment borrowing; fixtures do not qualify provider behavior |
| Deployment and migrations | Exact target/config/migration/approval observations, healthy application and required workers, interrupted/partial promotion reconciliation, prior artifact retention and demonstrably compatible rollback or separately authorized forward recovery |
| Data and keys | Verified DB and object restoration with required protected key custody, retention and operator recovery access; provider DB PITR or VM snapshots alone do not prove complete recovery |
| Capacity and cost | Actual supported SKU/DB limits and measured application, worker, storage/diagnosis/retention headroom; full recurring quote including staging and bounded temporary restore/test needs, taxes and overages made explicit |
| Human acceptance | Candidate-bound optional UAT disposition, required technical acceptance and distinct production approval; no UAT, build success or provider API acknowledgement treated as deployment success |

Use owning infrastructure tools directly and product tests only for owned behavior/integration contracts. The full
`./bin/build` remains a future implementation acceptance requirement; this planning edit runs only planning checks.
Fresh runtime/cloud/provider/restore exercises each require their own authorized scope, finite costs and cleanup.
No account inspection, build, deployment, live email/provider request, native runtime qualification or independent
Review/QA was performed for this reconciliation.

### Closure and remaining decisions

WF-048 closes **policy and handoff only**: SFO3 separate Droplets/databases, smaller staging with parity, non-HA
production conditional on verified recovery, sub-$100 combined monthly cost preference, operator-approved immutable
promotion, project-specific optional UAT and separate environment provider budgets. Exact capacity, pricing and
numeric budget/reset policy remain deliberately unqualified, not silently delegated spending authority.

[WF-049](WF-049-select-first-browser-journey-and-switching-behavior.md) is the next authored frontier for the
bounded browser journey/model/switching contract. [WF-050](WF-050-define-private-launch-readiness-and-recovery.md)
retains full operating/recovery cost feasibility, tax/temporary allowance decisions, exact RPO/RTO and backup/key/
object recovery, final supplier acceptance and human go/no-go. It must return any unavoidable sub-$100 mismatch
for a genuine scope/budget decision, not waive existing requirements. No new HA quote is a launch prerequisite
for the selected non-HA topology; revisit only if availability requirements materially change.

Reconciliation used repository reads and one optional four-candidate relevance batch, complete coverage at
$0.000376488 reported cost and no unknown cost. TASK-00215 screened positive; TASK-00212/00374/00063 were not-positive
and read directly because inspected owners explicitly referenced their concrete contracts. This is discovery,
not live application-provider qualification. No delivery source, metadata, owner hold or TASK-00510–00512 changed.

Post-reconciliation `./bin/planning-check --write` and `./bin/planning-check` passed: **645 delivery records,
496 active**, generated views current. Tracked `git diff --check` and all changed/untracked whitespace/path-scope
checks passed across **17 Wayfinder-only paths**. Tracked delivery/configuration files remain byte-identical to
HEAD. Work remains uncommitted in the isolated planning checkout; these are planning receipts only.

Next: `/skill:wayfinder work WF-049` — select the first browser journey; stop here without starting its interview.
