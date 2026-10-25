# Define private launch readiness and recovery

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-048](WF-048-define-staging-and-artifact-promotion.md), [WF-049](WF-049-select-first-browser-journey-and-switching-behavior.md)

## Question

What minimum evidence and operator decisions make the selected private production journey safe to launch and
recover, and which implementation suppliers are still missing?

## Must decide

- Define production authentication/security acceptance: current permissions, trusted HTTPS/ingress, exact origin,
  environment-scoped cookies, debug-off, throttling, security headers, secret strength/delivery/rotation and
  safe diagnostics. Authentication marked done is not the production envelope accepted.
- Identify a real credential-email provider and recoverable operating schedule where required. Existing null,
  memory and catcher adapters do not deliver real inbox mail. Reuse package credential recovery; no second outbox.
- Bind exact staged artifact/configuration, migration plan, required workers/schedules and target identity to a
  project-owned deployment runbook and bounded operator approval. Hosting availability is not product readiness.
- Agree health checks, logs and independent failure/collection status; map actual observability suppliers and
  constraints. A minimum launch boundary cannot silently erase the full accepted diagnosis scope or its holds.
- Reconcile WF-048's combined below-$100/month infrastructure/operations preference against complete actual costs,
  including staging, accepted diagnosis/retention, required mail/workers and bounded temporary restore/testing.
  Apply the accepted tax-inclusive preference and separately approved temporary costs below. Return overage for human scope/budget
  choice; do not treat the $87.10 illustrative floor as a complete quote or remove requirements to fit it.
- Define backup targets/retention/access, protected encryption/signing recovery, restore proof and recovery-point/
  recovery-time objectives. A local retained archive is not protection from host loss.
- Separate application rollback from database/data recovery. Rehearse migration compatibility, failed/uncertain
  deployment reconciliation and recovery from lost operator/application access. Decide acceptable downtime,
  maintenance communication, operator access and any human-only recovery path.
- Reuse the existing owner/evidence handoff to identify concrete missing behavior and target-specific operations.
  Distinguish development checks from observations obtained during the real deployment; no demand for a previously
  running production environment, separate readiness framework/TASK or tests of planning rules. A missing target
  blocks only operations requiring that target, not unrelated implementation. Do not invent acceptance from old receipts.
- Specify the final named human go/no-go decision and what changes invalidate it. Rollback authority must be
  explicit rather than inferred from permission to deploy. First release may be operator-run; full managed
  Release Manager automation, optional ChatGPT, domain UI, candidate workers and legacy retirement are not gates.
- Record proposed existing-owner amendments versus genuinely new destinations for later authorization. Preserve
  Board order and map-wide planning phases; no EPIC/TICKET/TASK creation/amendment follows automatically.

## Resolution boundary

Set a finite, evidence-based launch/recovery acceptance contract through interview and existing-owner traces.
It is not itself a go-live approval. No provision, migration, email send, backup restore, runtime probe, deployment,
commit or publication is authorized here. Reuse TICKET-00022/00011/00023/00058/00117 and the accepted journey owners;
record missing concrete suppliers honestly instead of assuming all production work is already covered.

## Resolution

Closed on John's recorded hosting, recovery, operator and budget choices and the final existing-owner handoff
below. This resolves planning policy, not implementation, a complete quote, proven recovery or go-live authority.
Actual cost/capacity and operational suppliers remain explicit delivery work; no production-exists prerequisite,
readiness project or new EPIC/TICKET/TASK is created.

### Accepted recovery targets and cost flexibility

John accepted the proposed initial household-use targets:

- Recover accepted Planning work with no more than **one hour of data loss** as the preferred recovery point.
- Restore usable service within **24 hours after discovering a failure**, with John operating recovery, not a
  staffed overnight response. Undetected failure time is additional; this is not a maximum total outage promise.
- Recover database records, referenced files and necessary encryption keys together. A database-only backup
  does not establish complete application recovery; actual supported mechanisms and evidence remain delivery work.

John added that he could potentially accept worse if needed and has historically used daily backups. Treat
**daily-backup recovery as an explicit lower-cost option to evaluate**, not a demand for hourly backup copies or
an immutable one-hour launch gate. If the practical proposal needs that tradeoff, state its actual recoverable
window, cost and limitations for confirmation. His flexibility is not unlimited loss/downtime acceptance or an
automatic extension of the 24-hour restoration target. A daily schedule alone cannot guarantee a 24-hour recovery
point when jobs fail or dependent files/keys are missing.

These are accepted planning objectives, not proven service levels, spending authority, new readiness TASKs or
permission to provision/run backups. Prefer supported existing facilities; retain ordinary deployment/recovery
checks rather than inventing a certification project.

John accepted **30 days of daily recovery points, provided the cost is reasonable**. This is a conditional
retention preference, not acceptance of a quoted price, an increased monthly ceiling or spending authority.
It is distinct from the preferred recent one-hour recovery point, provider-native PITR coverage and accepted
diagnosis/log retention; it does not promise 30-day provider PITR or silently change other retention requirements.

### Initial cost comparison and start-small direction

This comparison preceded the accepted hybrid topology and 2 GiB staging follow-up below; its two-managed-database
figures are historical alternatives, not the current $56.35/month planning subtotal.

[Public backup/restore and lean-hosting comparison](../research/WF-050-backup-storage-and-restore-costs.md), observed
2026-10-23, now supplies the requested estimate. During research John specifically asked to include DigitalOcean
Spaces and clarified that initial application Droplets should not need much CPU/memory. Carry that start-small
direction forward; the old 4 GiB production / 2 GiB staging illustration was not a required minimum.

Recommend **2 GiB production / 1 GiB staging** as an initial candidate, not an already-approved SKU or measured fit.
With two separate minimum Managed PostgreSQL clusters, daily boot backups, Spaces and registry, the comparable
pre-tax floor is **$63.70/month**, versus the earlier $87.10. Use actual needs to size upward; do not pre-buy
hypothetical traffic or silently omit existing background/storage/diagnosis behavior to fit a VM.

Spaces Standard's **$5/month / 250 GiB** shared pool is already included in the floor. Thirty 5 GiB full daily
archives retain 150 GiB and add no Standard storage charge if the pool has space; thirty 10 GiB archives retain
300 GiB and add $1/month if nothing else uses it. Cold Storage is separately priced, with retrieval/minimum-retention
tradeoffs; it is not automatically cheaper for small backups fitting the Standard pool. Spaces is a backup target
candidate, not a replacement of existing private application-storage ownership.

A separate encrypted Backblaze B2 copy is approximately **$1.12/month at 150 GiB / $2.24 at 300 GiB**, before tax and
transfer exceptions. A 300 GiB Standard history plus an independent matching B2 copy brings the lean subtotal to
**$66.94/month**, still excluding unpriced operating items. B2 is a researched recommendation, not selected/enrolled.
Shared-provider Spaces alone is simpler but does not protect against every DigitalOcean account/provider failure.

Temporary lean app/minimum-DB recovery compute is approximately **$0.97 for 24 hours**, not the full restore bill:
include larger targets, file/snapshot storage, transfer, original resources and actual duration. No standing reserve
or spending is approved. Native PostgreSQL recovery retains seven days; it does not deliver the thirty-day history
or complete files/key recovery on its own. Existing daily boot backups likewise are not coherent cross-service
thirty-day retention. Do not count the same $5 subscription twice or treat unknown operating costs as zero.

Prefer the accepted history where economical; if the complete proposal exceeds the cost preference, bring the
actual tradeoff to John. Ordinary implementation/deployment work supplies actual sizing and recovery observations,
not a readiness/benchmark project or production-exists prerequisite. Account inspection/provisioning remains
unauthorized. Fourteen public fetch attempts yielded thirteen sources and one documented 404; no live provider,
cloud-account or runtime operations occurred.

### Approved hybrid database hosting and Spaces-only backups

After comparing the managed-database cost, John proposed and then explicitly accepted:

- **Production:** one Managed PostgreSQL cluster, separate from its application Droplet.
- **Staging:** self-managed PostgreSQL on the staging application Droplet, with separate data, roles, credentials
  and network authority from production. No shared production database/server or production-data copy is implied.
- **Backups:** **Spaces-only**, without the proposed additional B2 copy. The earlier explanation distinguished
  protection against losing a server from losing access to the DigitalOcean account/provider. John chose the
  simpler shared-provider arrangement; do not reintroduce an independent provider as a mandatory launch gate.

This explicitly supersedes WF-048's two-managed-cluster choice, not its environment isolation or exact-artifact
promotion. Keep PostgreSQL major version, required extensions and schema aligned. The application/migration path
remains comparable, but staging cannot rehearse DigitalOcean-managed maintenance/recovery mechanisms exactly.
Represent that difference honestly in the existing runbook. We own staging database updates, supervision and
consistent exports/restores; a VM snapshot alone is not the database backup contract. No new parity-certification
TASK is needed. Existing delivery records and closed WF-048 status are unchanged.

John subsequently favors the **2 GiB RAM staging alternative**, because PHP, workers and other services must
leave room for staging PostgreSQL. Plan **2 GiB RAM / 50 GiB SSD / one shared vCPU per application Droplet** as the
initial sizing direction, with production's separate minimum Managed PostgreSQL priced at 1 GiB RAM / 10 GiB DB
storage. RAM and disk are distinct; Spaces is separate backup storage. Staging need not have a smaller app VM
than production now that it also hosts its database.

The current estimated subtotal is **$56.35/month**: $12 production app + $12 staging app/database + $15.15 production
Managed PostgreSQL + $7.20 daily boot backups + $5 Spaces + $5 registry. The earlier $48.55 used a 1 GiB staging VM
and is historical, not the current planning target. These are sizing estimates, not a spending grant or proven
workload fit. Storage overages, remaining operations, domain/CI, mail, tax and temporary recovery remain additional
where applicable; 300 GiB total Standard usage would add $1/month at the cited prices.

John requests **512 MB for PHP** and further bounds **staging** to **one worker if possible, at most one projector
if event sourcing needs it, and one cron container**. Do not introduce a separate projector when the owning
projection mechanism does not need one; a single cron container can dispatch the required scheduled work without
inventing another scheduler. These are staging starting constraints, not a production concurrency policy or a
requirement to collapse distinct security/transaction participants into one unsafe process.

John expects that these services will not all be active simultaneously. Account for that expected light workload,
but idle/resident processes can retain memory and scheduled work may overlap. Keep web concurrency modest and
reserve PostgreSQL, OS, proxy and other service headroom. The requested 512 MB PHP allowance is not an automatic
aggregate container cap or guaranteed reservation; exact PHP memory_limit semantics, web pool counts and process
limits remain ordinary deployment configuration. No new human approval gate is needed merely to tune them. Return
only a real cost/capacity or behavior tradeoff if the selected envelope cannot fit the required services.

Two GiB remains a starting plan, not measured capacity. Do not pre-buy speculative traffic capacity or create a
separate sizing-test/readiness TASK. Use normal implementation/deployment observations.

Retain cost-conditional thirty-day daily recovery points in Spaces Standard, separate environment scopes and
protected key custody/recovery. Do not keep the only decryption/recovery material inside an inaccessible encrypted
backup; John subsequently selected Bitwarden for the human recovery kit below, without adding a data-backup vendor.
Spaces-only accepts the discussed shared-provider/account exposure; it does not promise recovery during every
provider/account outage, authorize public buckets or replace private application storage. Production's native
seven-day recovery is supplemental, not thirty-day history. B2 and Cold remain researched but unselected.

No new fetch/account inspection, spending, provisioning, runtime change, delivery amendment or implementation is
authorized by these choices. Subsequent operator/cost decisions and the final handoff below complete this interview.

### Human-held recovery kit: Bitwarden

John selected **Bitwarden** when asked which password manager to use for the proposed human recovery kit. Plan
for John to retain the necessary backup/application encryption recovery material, DigitalOcean account recovery
codes and concise access/restore instructions there. No secrets belong in these planning records, prompts, Git
or logs. This is human-held emergency custody, not a Bitwarden runtime dependency or permission for application
processes/agents to read his vault. Spaces-only remains the data-backup choice.

The recovery path must remain usable without signing into Fight or relying on the failed application host. Avoid
circular recovery: the sole vault-unlock/second-factor recovery route cannot depend on the inaccessible vault,
Fight or the failed hosting account. Bitwarden deployment/account recovery details have not been inspected; its
name alone does not establish that independence. Preserve the key versions needed by retained backups; no blanket
key deletion on rotation or automatic trust in restored credentials. Exact secure provisioning/custody and recovery
checks belong to ordinary delivery/runbook work, not another certification project or present vault-access request.

This selection does not enroll a service, purchase a plan, create/export a vault item, grant anyone else vault
access or establish tested recovery. John remains the accepted recovery operator; delegated emergency access has
not been selected. Actual custody/recovery observations and remaining operator handoffs stay ordinary delivery work.

### Initial transactional email: Resend Free over HTTPS

John uses Gmail for his inbox and accepted **Resend's free tier** as the initial service for Fight's automated
credential/account-recovery email. Use the HTTPS API rather than relying on DigitalOcean SMTP access. This does
not move his inbox, require Gmail credentials or select Gmail SMTP as the application's sending transport.

The previously researched allowance is **3,000 messages/month, capped at 100/day**; this is dated public evidence,
not a freshly checked account entitlement or delivery guarantee. Plan a $0 mail subscription while eligible and
within limits, with no automatic paid upgrade. The $56.35 hosting subtotal is unchanged, not a complete bill.
Sender/domain eligibility, current quotas and actual inbox delivery remain ordinary integration/deployment work.
`noreply@fight-agent.dev` was an example, not a separately fixed sender identity. Sender verification and required
DNS authentication records need later authorized setup; no existing inbound-mail routing is to be changed silently.

Reuse TICKET-00011's package mail/credential recovery ownership and existing scheduling/outbox contract; no second
outbox or parallel recovery system. Keep provider credentials protected server-side, staging effects separate and
actual quota/delivery failures visible through the existing failure/retry path. Do not claim success merely from
provider selection or silently fall back to personal Gmail. Exact adapter/scheduling configuration belongs to the
existing owner and project packaging handoff, not a new mail-readiness project.

This is provider/plan selection only. No account enrollment, API key access, DNS change, email send, spending,
implementation or delivery-record amendment occurred. Subsequent rollback/cost choices and the final handoff
below complete this interview.

### Approved failed-deployment rollback boundary

John approved returning to the previous application version **within an approved deployment**, but only when that
version is compatible with the current database. The existing exact-artifact/configuration/target binding still
applies. This is bounded application-only rollback, not permission to reverse migrations, discard data or infer
compatibility merely because an older artifact exists. If compatibility is absent or uncertain, stop that rollback
and return the concrete recovery choice to John rather than trying it destructively.

**Restoring a database backup requires John's explicit approval**, because newer work may be discarded. Present
the chosen recovery point and expected data-loss scope before that approval; retain coherent files/key recovery
and original-operation/provider-liability reconciliation. Permission to deploy or roll back application code is
not permission to rewind database state. Existing recovery targets and Spaces/Bitwarden choices are unchanged.

This selects the runbook's authority boundary; it does not approve any deployment/restore now or require automated
rollback implementation. John remains the production approval/recovery operator. Carry the boundary into ordinary
project packaging/operator work without new readiness TASKs, certification or delivery-record amendments.

### Accepted tax-inclusive cost preference and temporary recovery spending

John confirmed that the **combined below-$100/month preference includes tax and recurring services** across staging
and production. Include hosting, databases, backup/storage overages, required operations/diagnosis, registry,
mail, CI/network and the recurring domain cost when presenting the complete estimate. Model inference remains a
separate budget. This is a preference, not a hard service cutoff, approved purchase or permission to exceed it.
Return a concrete unavoidable overage for John's cost/scope choice rather than omit requirements or expand spend.

**Temporary recovery costs must be quoted and approved separately**, with no automatic reserve. Include duration,
resource sizes, coexistence with original resources, storage/transfer and cleanup; additional duration/resources
need authority rather than indefinite billing. The earlier ~$0.97/day recovery compute example is not a full quote
or preapproved allowance. The current **$56.35 pre-tax subtotal** is unchanged; full tax-inclusive feasibility is
still unproven. Resend Free contributes $0 subscription only while current eligibility/limits permit.

### Final practical owner handoff

Reconcile against [WF-048's existing-owner table](WF-048-define-staging-and-artifact-promotion.md#existing-owner-and-missing-supplier-handoff)
and [WF-049's browser owner handoff](WF-049-select-first-browser-journey-and-switching-behavior.md#existing-owner-handoff-and-concrete-gaps).
These are previously traced contracts, not fresh acceptance of their implementations. Proposed reuse/amendment is
for later authorized planning; no existing delivery record or hold is changed.

| Concern | Existing owner / remaining concrete allocation |
|---|---|
| Production package, public ingress and operator runbook | TICKET-00023/TASK-00072 and TICKET-00022/TASK-00063 remain local-scoped where recorded; do not silently expand them. Retain the proposed Fight-specific production packaging and operator-run staging/promotion/recovery destination for later authorization. Actual public TLS enrollment/renewal, service supervision, protected configuration and package assets still need that supplier |
| Mail and schedules | Reuse TICKET-00011's package mail/outbox and bounded runner. Resend HTTPS adapter/enrollment, verified sender, real delivery and operating schedule remain concrete supplier work. One staging cron container is a process preference, not proof jobs run or mail arrives |
| Planning and database authority | TICKET-00026/00027/00028 retain typed semantics, source inventory, rehearsal, explicit migration approval, atomic authority switch and retained frozen Markdown. TICKET-00090/00092 retain useful browser Planning; WF-049's conversation usage/catalog allocations remain proposed amendments, not a second ledger or transcript |
| Protected application/provider runtime | TASK-00342/00346/00350/00399 retain continuation/runtime/source holds; TASK-00344/00345 retain original accounting and environment limits. A VM, vault selection or restore does not qualify the protected runtime or erase paid/uncertain liability |
| Files, diagnosis and recovery | TASK-00374 retains private application/evidence storage; TICKET-00117/TASK-00481–00487 retain full accepted diagnosis isolation, retention, supervision and restore scope. Spaces is a backup destination, not a runtime-storage replacement. Actual coherent DB/files/key export/restore, retained key versions, freshness/failure observation and physical capacity remain supplier work |
| Promotion/health | TICKET-00058/TASK-00222 consumes the actual qualified target/runbook; TASK-00212/00215 remain managed policy/acceptance consumers, not a production package. Preserve their holds and existing independent Review/QA; first operator deployment does not complete managed Release Manager automation |

Carry these finite operating boundaries into that ordinary delivery/runbook work:

1. Retain WF-048's exact HTTPS/origin/cookie/proxy/current-permission, debug-off, throttling/header and protected
   secret/diagnostic envelope. Check real target behavior during authorized deployment; neither completed login
   code nor local TLS qualifies the public host. Do not require production to pre-exist before implementation.
2. John makes the final production go/no-go for the exact staged artifact, target, configuration/secrets revisions,
   migration plan and required services/schedules. Material changes invalidate that approval. Optional project UAT
   remains optional; ordinary technical acceptance remains distinct from permission to deploy.
3. Record application and required worker/schedule health separately from collector/backup failures. Preserve
   TICKET-00117's independent failure/collection status; no silent green status because logs or email stopped.
   Actual monitoring/diagnosis capacity is still to supply, not a new monitoring-vendor choice inferred here.
4. Use native managed production DB recovery as supplemental recent DB recovery, and coherent exports/files in
   Spaces for the cost-conditional thirty-day daily history. Self-managed staging needs consistent DB recovery,
   not just boot snapshots. Select exact job cadence, manifests and protected key handling in delivery against
   the preferred one-hour objective. If coherent recovery only supports a daily window, return that actual
   tradeoff to John; do not silently declare the preferred objective met or make hourly copies a new rigid gate.
5. Recovery uses John's Bitwarden-held kit without depending on Fight login. Reconcile uncertain deployment effects
   before retry; compatible app-only rollback is within an approved deployment, but database restore needs John's
   explicit recovery-point/data-loss approval and temporary spending approval. Isolate restored production data
   and suppress outbound mail/provider work until reconciled; an older DB never authorizes duplicate paid replay.
6. Keep planned maintenance/outage communication human-operated for the household, through an available channel
   outside Fight when Fight is down. No staffed overnight service or new status-page vendor is implied. Recovery
   remains within 24 hours after discovery as an objective, not an established service level.

The only genuinely new destination proposed here remains **Fight-specific production packaging and operator-run
staging/promotion/recovery**, composed with existing owners. It is not an approved EPIC or permission to create one.
Complete this map's remaining decisions and approved EPIC handoffs before TICKET/TASK decomposition. Preserve Board
order, TASK-00510–00512 and all existing holds. No credential inspection, account/provisioning change, application
build, live mail/provider request, restore, migration, independent Review/QA, commit or publication occurred.

Next: `/skill:wayfinder work WF-051` — assess optional ChatGPT integration or obtain explicit deferral. This is the
next authored frontier, not a launch prerequisite or an automatically started interview/research run.

### Inputs received from WF-048

[WF-048](WF-048-define-staging-and-artifact-promotion.md) closes staging/promotion policy, not readiness. Preserve
SFO3 separate Droplets/databases, smaller staging with software/deployment parity, operator-approved immutable
promotion and optional project-specific UAT. John accepts non-HA production maintenance/failure outages conditional
on verified backups/recovery; do not ask again whether HA is mandatory. Exact tolerable downtime/data loss, backup
retention, key/object recovery and actual restore evidence remain this decision's work; the initial recovery
targets above now resolve the first tolerance question.

The [owner handoff](WF-048-define-staging-and-artifact-promotion.md#existing-owner-and-missing-supplier-handoff)
identifies missing Fight production packaging/operator runbook, real email/scheduling and operational suppliers,
with existing security/storage/provider/diagnosis and managed release holds preserved. It does not qualify any
of them. Independent environment inference budgets and per-user keys are selected; numeric caps/reset windows
remain unset and separate from the combined hosting/operations cost preference. No budget, temporary reserve,
account/API call, provisioning or go-live authority is conferred. WF-049 has now closed; this interview is the
selected interview through John's subsequent explicit invocation, not automatically started by its closeout.

### WF-049 rollout clarification — practical deployment, not a readiness project

John selected the database transition plus useful browser Wayfinder Planning as the production rollout milestone.
He explicitly rejects new TASKs/tests/rules that require production to already exist before implementing or first
deploying it. Compose a practical operator handoff within the real delivery work; do not expand this decision into
an independent certification process or speculative blocker backlog. Normal repository acceptance and the agreed
security, budget, explicit migration/promotion and backup/recovery precautions remain, without duplicate gates.

Target-dependent observations belong to the authorized actual deployment and its smoke/recovery work. John will
judge usefulness in production and request concrete bugfix TASKs for observed defects. No promise of zero defects,
automatic TASK creation, known-unsafe operation or false deployment success follows. Ask only about genuine
unresolved cost/data-loss/recovery tradeoffs, not settled non-HA intent or permission to pursue this milestone.
This is a received planning direction, not the start of WF-050's interview or immediate deployment authority.

### WF-049 completed handoff

The selected browser journey uses current provider-backed model choices, editable defaults and explicit between-turn
switching with preserved history. Conversation usage, application environment limits and independently refreshed
provider allowance are distinct. Missing allowance remains visibly unavailable without disabling useful Planning.
Exact model IDs, numeric caps/reset periods and cache tuning belong to ordinary product configuration, not another
model-benchmark or readiness project.

[WF-049's owner handoff](WF-049-select-first-browser-journey-and-switching-behavior.md#existing-owner-handoff-and-concrete-gaps)
identifies conversation-usage query/UI and catalog/default/lifecycle extensions within existing owners. Workflow-only
TASK-00396/00391 do not supply standalone conversation usage, and no fictitious Workflow/parallel ledger is planned.
Preserve the concrete runtime/provider/source gaps without converting every future managed capability into a launch
prerequisite. The first rollout retains TICKET-00028's actual migration safeguards; no new migration gate is added.

Both decision inputs and this policy interview are closed, not implemented or deployed. No exact application
inference cap, complete cost quote, production success, live integration receipt or spending authority is inferred
from their closure. The final practical owner handoff above preserves those concrete delivery obligations.
