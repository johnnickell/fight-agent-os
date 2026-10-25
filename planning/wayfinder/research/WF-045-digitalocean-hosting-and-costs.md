# DigitalOcean hosting and operating costs for the first private rollout

## Question

Which DigitalOcean topology and finite operating budget should John consider for the first private Fight Agent
OS web/API installation and production-like staging, without buying a candidate-execution platform?

Research for [WF-045](../tickets/WF-045-compare-digitalocean-hosting-and-operating-costs.md), observed
**2026-10-23 UTC**. Currency: **USD**, published list prices before taxes, credits, discounts or currency conversion.
Public documentation is evidence of advertised facilities, not a quote, account entitlement, capacity reservation,
qualified image, deployment or human selection. No cloud/account API, credential enrollment, application-provider
qualification, model generation, SMTP, DNS, runtime or infrastructure operation was used. Optional Jev relevance
screening used two authorized evaluator requests (reported $0.000076734), not application-provider qualification.

## Short answer

**Recommend considering separate production and staging Droplets running production NGINX/PHP-FPM, with separate
Managed PostgreSQL clusters.** Retain GitHub CI for immutable release artifacts. This best accommodates the current
installation/operating contracts without App Platform-specific storage and collection redesign. It costs more
operator effort: OS/container patching, TLS renewal, supervision, capacity, release health and recovery remain ours.
This is a recommendation, not acceptance of a platform or a new production topology.

Comparable web/database/storage/registry floors are **$87.10/month for Droplets** or **$77.30/month for App Platform**
with reduced-capacity staging. These are **not complete launch costs**: diagnosis capacity, scheduled work, email,
restore overlap, storage growth, tax and operator time must be added. A same-size staging illustration raises the
floors to $102.70 and $90.30 respectively. No workload sizing or launch readiness is established by these numbers.

For discussion, propose **$200/month recurring pre-tax plus a separately authorized $100 temporary recovery/test
reserve**, not a spending grant or guaranteed invoice cap. A priced Droplet/operations/email illustration is
$148.30/month ($163.90 with same-size staging), but its diagnostic resources are unqualified and volume snapshots,
additional staging diagnosis, off-account recovery and growth are not a complete priced design. If accepted
requirements do not fit the ceiling after qualification, return for a budget/design decision; never quietly drop them.

**Important current change:** new Standard PostgreSQL clusters lose standby/read-replica support on the published
October/November 2026 transition. Do not budget a durable new HA design at the older ~$60/month figure. New Advanced
shared-CPU plans complicate the current price comparison; a complete current HA quote remains unknown. [S5–S8]

## Findings

### 1. Two proportionate options

| Concern | Droplet + Managed PostgreSQL | App Platform + Managed PostgreSQL |
|---|---|---|
| Web/API | Production NGINX/PHP-FPM on an owned Linux VM; full operator control of health, connections and disk | Managed HTTP service/container; no host SSH or Compose deployment; image must serve HTTP, not just FastCGI |
| Maintenance | We patch host OS, runtime/images, ingress and supervisor; managed DB provider handles its layer | Provider handles underlying host/platform; we still patch application/dependencies/custom image, qualify PHP/extensions and operations |
| Durable files | Bounded boot disk/volumes are possible; mount/secret separation and recovery must be configured | Local filesystem is ephemeral, limited to 4 GiB, and volumes are unsupported; durable files need external services [S4] |
| Private DB | Same-region VPC/private endpoint plus DB trusted sources, distinct DB roles and verified TLS | VPC support is documented; app region maps to a particular datacenter; enable it and allow exact VPC egress sources [S11] |
| HTTPS | We own canonical authority, certificate lifecycle and request facts passed from NGINX to FPM | Managed domain certificates/edge; application still needs qualified trusted ingress, origin/cookies, HSTS and client-IP handling [S4, S13] |
| Secrets | Separate externally held keys, per-service delivery, protected recovery; not image/source files | Encrypted runtime-only component variables are available, but console/code/config administrators can recover plaintext; not isolation from operators [S12] |
| Deployment | Operator-run digest/config switch, health proof and explicit migration/recovery plan | Built-in deployment history/rollback; last ten successful deployments, same region/DB configuration; database data does not roll back [S14] |
| Diagnosis | Host/service readers, finite local archives and independent supervision can fit existing ownership, subject to qualification | Platform log forwarding has a 2,048-byte message limit and no durable local volume; accepted full-source/archive behavior is not established [S4, R4] |
| Scheduling | A supervised bounded runner/timer can share trusted application capacity, but is not installed or free headroom by assertion | Native scheduled jobs exist, minimum interval 15 minutes, billed while running; faster recovery may need a continuously billed worker [S15] |
| HA | A single application VM is not HA; multi-node web/LB and database HA are separate upgrades | One container is not HA; docs require two or more for application HA, and database HA is separate [S4] |

The inexpensive App Platform option is credible only if its external storage, full diagnosis capture, request
security and background runtime are qualified and accepted. A private Git/check host is not the remedy for every
web limitation. Kubernetes offers no demonstrated benefit for this initial household-sized browser journey.

### 2. Dated price inputs and uncertainty

These are **advertised unit prices**, not observed bills. The examples use bundled Regular/Basic Droplets in a
region that offers them, not the newer v5 resource-priced configurations. All sizes are candidates for measurement.

| Item | Published price/capacity used | Important boundary |
|---|---|---|
| Basic Regular Droplet | 2 GiB/1 shared vCPU/50 GiB SSD/2,000 GiB transfer: $12/month; 4 GiB/2 shared vCPU/80 GiB/4,000 GiB: $24; 8 GiB/4 shared vCPU/160 GiB: $48 [S1] | Bursty shared CPUs, not guaranteed dedicated throughput |
| Droplet billing/egress | Bundled monthly cap at 672 hours; per-second billing with minimum; powered-off VM still bills; additional egress $0.01/GiB [S2] | Destroying ends compute billing; independent disks/snapshots can still bill |
| v5 Droplets | Per-resource hourly prices, no monthly cap; public IPv4 introductory line item $0, subject to change [S2] | Do not apply bundled $12/$24 totals to v5-only datacenters |
| App Platform | 1 GiB fixed/non-scaling $10; scalable 1 GiB $12/150 GiB transfer; 2 GiB $25/200 GiB; 4 GiB $50/250 GiB per instance/month [S3] | $5/512 MiB exists, but is not a qualified application/worker size; each worker/container adds a bill |
| App billing/egress | Components billed per second, minimum applies, first 28 days/month; jobs only while running; excess transfer $0.02/GiB [S3] | Component renaming can restart billing-day count; allowances pool at team level, not isolated project budgets |
| Standard PostgreSQL | Public table: 1 GiB/1 vCPU, 10–30 GiB storage, $15.15/month at displayed minimum; 2 GiB/1 vCPU, 30–60 GiB, $30.45; 4 GiB/2 vCPU, 60–120 GiB, $60.90 [S6] | Docs separately say starts at $15; use table's $15.15, do not silently merge rounded figures; inspect actual creation quote later [S5] |
| Additional DB storage | Standard $0.215/GiB/month, table shows 10 GiB increments and size-dependent ranges; Advanced $0.115/GiB/month [S5, S6] | Do not add minimum storage twice to displayed total; growth/resize and storage limits require actual quote |
| Spaces Standard | $5/month subscription, 250 GiB across buckets, 1,024 GiB outbound; excess $0.02/GiB-month storage and $0.01/GiB outbound [S16] | Team/subscription-wide pool; separate environment buckets/credentials still required; not unlimited independent backup |
| Container registry | Basic $5/month: five repositories/5 GiB; extra $0.02/GiB-month; free Starter only one repository/500 MiB [S17] | Retain current/previous digests; untagged layers still count until garbage collection |
| Droplet backups | Basic weekly +20%, daily +30% of VM cost; usage-based rates/retention also available [S18, S19] | Cost examples assume daily percentage plan, not multiple backup plans; verify selected retention before purchase |
| Additional block storage | $0.10/GiB-month, minimum 1 GiB, bills while attached or detached [S20] | 100 GiB illustration costs $10/month; VM backups exclude it |
| Snapshots | $0.06/GB-month for Droplet snapshots, $0.06/GiB-month for volumes, minimum $0.01 [S21] | Include retained copies and cleanup; snapshots are not off-account recovery |
| Regional LB, optional | HTTP $12/month/node, network $15/month/node [S22] | Not in single-VM floor; adding an LB alone does not make the application HA |
| Credential email example | Resend advertises Free 3,000/month, 100/day; Pro $20/month for 50,000, then $0.90/1,000 [S23] | Candidate only, not selected/qualified; sender/domain eligibility, delivery, privacy and quotas need acceptance |

Actual tax jurisdiction, exchange rate, account credits, existing pooled usage, supported size/edition and
regional capacity are unknown. No account was inspected. Published general lists do not prove the chosen SKU is
available to John. CI usage/artifact storage beyond the registry is also not priced: current GitHub entitlement,
retention and release build duration were not inspected. Keeping CI is not a claim that builds cost zero.

### 3. PostgreSQL: major version, isolation, HA and recovery

- Current repository development/test images use **PostgreSQL 17**. Standard documents new v15–18 clusters;
  Advanced supports v16–18 and defaults to v18. Propose explicitly selecting **v17** for staging/production parity,
  not accepting a platform default or silently upgrading the application. Installed migrations, extensions,
  transactions, permissions, authentication limiters and drivers must still be qualified. No database was queried.
  PostgreSQL 17's documented EOL is 2029-11-08. [R1, S8]
- Standard 1 GiB has only **22 backend connections**; 2 GiB has 47 and 4 GiB has 97. Count web FPM workers,
  independently committed admission connections, background work, migrations and operator recovery. Pooling has
  transaction/session compatibility implications; it is not permission to share application transaction state or
  to assume unlimited concurrency. A $15.15 node is an economical non-HA comparison input, not a proven production
  recommendation. The provider itself positions the smallest single-node plans for preliminary development/testing.
  [S5, S8, R1]
- Prefer independent production/staging clusters rather than two databases in a single small production cluster.
  This avoids sharing connection/storage/maintenance exhaustion and grants. It is not separate-provider/account
  disaster protection. Preserve the shared authoritative business transaction boundary per environment and the
  independently committed admission connection; do not change Doctrine/package semantics for hosting. [R1, R2]
- **Plan transition:** effective 2026-10-15 for accounts that have never created a PostgreSQL/MySQL cluster, and
  2026-11-30 for all accounts creating new clusters, Standard is limited to 1/2/4 GiB shared plans, without standby
  or read-only nodes. Existing qualifying clusters have grandfathering; John's project existence proves no such
  entitlement. The same announcement adds 8–64 GiB shared-CPU Advanced plans. [S7]
- The pricing page still describes legacy HA starting with ~$30 primary + ~$30 standby, and the limits page
  still says Advanced shared CPU is unavailable. Prefer the later explicit dated transition for eligibility;
  preserve these conflicts instead of treating either generic statement as a current quote. Advanced pricing docs
  advertise an 8 GiB/2 vCPU primary starting at $130/month and matching standby prices. **$260/month for two such
  nodes is an illustration, not the proven cheapest current Advanced HA option**: the fetched public pricing
  table defaults to Standard, and new Advanced shared-plan pricing was not established. A complete HA budget and
  supported node-count/quorum/failover profile remain unqualified. [S5–S8]
- Daily database backups and WAL support **seven-day PITR**. Restore creates a **new cluster/node**, takes
  data-dependent time, and requires re-adding replicas; reserve concurrent billable resources and quota. Default
  team limit is ten clusters, but actual remaining quota is unknown. Deleting a cluster deletes its backups.
  Seven-day PITR is not a tested RPO, measured RTO, long-term retention or independent copy. [S8–S10]
- Require private endpoint/trusted sources and verified TLS, not merely VPC presence. Standard uses its downloaded
  CA; Advanced uses system trust with documented libpq requirements. Reconnection after failover does not authorize
  repeating an ambiguous paid/provider/business effect. Upgrade to HA addresses database node failure, not region
  loss, stolen operator access, lost external encryption keys or application-VM failure. [S8, S9, S11]

### 4. Regional and project organization

Candidate regions **SFO3** and **NYC1** advertise Basic Droplets, shared PostgreSQL and backups. Spaces Standard is
available at SFO3 and NYC3; an NYC1 VM can use NYC3 under documented regional/private-routing rules. App Platform
regions `sfo` and `nyc` map to VPCs in **SFO3** and **NYC1**, respectively. Prefer one colocated application/DB VPC per
environment, no cross-region DB dependency or peering just for the initial release. [S11, S16, S24]

No household location, latency result, residency requirement or account capacity was inferred. John must choose
region; SFO3 is a simple all-services candidate, NYC1/NYC3 a documented alternative, not a claimed faster choice.
Other regions remain available. In particular ATL/RIC/MKC advertise restricted premium/v5 SKUs: do not transplant
Regular $12/$24 assumptions there. Availability can change; public region checks are not account qualification.

Recommend separate DigitalOcean project groupings for production and staging if convenient. **Projects organize
resources; they do not isolate authority, data or billing.** VPCs/firewalls/alerts are among independent resources,
and resource billing pools can cross projects. John's existing project is context only. Use separate DB users,
private bucket scopes, runtime keys, exact origins, host-only cookies, environment budgets and controlled operator/
CI credentials regardless of grouping. VPC connectivity alone is not service authorization. If separate teams or
accounts are needed for stronger administration/billing separation, price their duplicated subscriptions and recovery
access explicitly; no team/account changes are authorized here. [S2, S3, S16, S25]

### 5. Comparable recurring floors, not launch totals

Assumptions: continuously running single application instance per environment; non-HA Standard 1 GiB/minimum
10 GiB DB per environment; one team-level Spaces subscription with separate private environment scopes; DOCR Basic;
no dedicated egress IP/LB, extra disk or compute fleet. Dollar arithmetic is calculated, not provider quotation.

| Monthly line | Droplet, reduced staging | App Platform, reduced staging |
|---|---:|---:|
| Production web/API | $24.00 (4 GiB/2 vCPU) | $25.00 (2 GiB/shared vCPU) |
| Separate staging web/API | $12.00 (2 GiB/1 vCPU) | $12.00 (1 GiB/scalable shared vCPU) |
| Two independent minimum Managed PostgreSQL clusters | $30.30 | $30.30 |
| Daily Droplet boot-disk backups, 30% | $10.80 | N/A — no persistent app disk |
| Private object storage subscription | $5.00 | $5.00 |
| Registry Basic | $5.00 | $5.00 |
| **Calculated comparable floor** | **$87.10** | **$77.30** |
| **Same-size web staging instead** | **$102.70** | **$90.30** |

The different web RAM/CPU footprints are **not performance-equivalent**: Droplet includes our OS/NGINX overhead,
App Platform includes provider overhead outside the container. Smaller staging preserves mechanisms, not production
load proof. Same-size figures change staging compute and Droplet backup percentage only; they still do not qualify
full diagnosis/resource parity. A private browser web service is not the App Platform free static-site tier.

**Additions must remain visible:**

- Diagnosis/operational storage: an **illustrative**, unqualified 4 GiB $24 operations VM + $7.20 daily boot backup
  + 100 GiB $10 volume adds **$41.20/month**. This is costing space, not selection of a second stack or evidence that
  the accepted deployment can be split. It excludes volume snapshots, extra staging collectors/archives and unknown
  Loki/SeaweedFS/index/WAL/restore needs. Existing installation-owned topology must be preserved or explicitly amended.
- With that illustrative addition and a $20 email allowance, Droplet floors become **$148.30**, or **$163.90** with
  same-size web staging. They are not complete supported-launch totals; the full recurring total is **unknown**.
- Accepted diagnosis qualification uses 10 records/second at roughly 2 KiB plus bursts. Extrapolated continuously,
  this is about **1.65 GiB/day, 148.3 GiB/90 days raw**, before indexes, WAL, versions, holds and temporary restore.
  The one-hour qualification profile does not establish a continuous production rate or compression ratio. Neither
  the $24 VM's 80 GiB disk, the illustrative 100 GiB volume nor a 250 GiB object pool proves retention or hard
  isolation. Do not halve retention, truncate raw records or label App Platform stdout complete to fit a price. [R4]
- On Droplet, a trusted credential runner can use measured reserved capacity on the application host. On App
  Platform, a continuously running 1 GiB scalable worker would add **$12/environment/month**; smaller $5/$10 plans
  exist but have no measured fit. Scheduled-job usage is additional and depends on duration/frequency/minimums.
  Native 15-minute schedules do not prove acceptable credential/conversation recovery latency. [S3, S15]
- Upgrade production DB to 2 GiB adds **$15.30/month** in the displayed Standard table; upgrading both adds $30.60.
  HA is a different edition/topology decision, not a second 1 GiB node. A second $24 production web VM with daily
  backup adds $31.20, plus at least the $12 regional HTTP LB illustration; no end-to-end HA claim follows.
- Recovery needs an overlapping DB/application target, retained artifact digests/configuration, protected external
  key copies, data/object backup, finite restore scratch and operator access. Temporary compute/storage can bill
  even when the failed target remains; an emergency $100 reserve is only a proposed approval bound. Larger HA or
  longer recovery may exceed it and must stop for explicit authority, not silently expand spend.
- Egress, snapshot/version accumulation, off-account/off-region independent copy, domain renewal, paid CI,
  additional services and taxes are **not zero because they are unknown**. Domain cost was not re-quoted or inferred
  from registration. Team spend alerts notify; they are not a general hard stop for Droplet/database/storage bills.
  Define bounded resources/retention and operator response as well as alerts. [S2, S3, S16–S22, S26]

### 6. Packaging, security, email and operating gaps

Observed repository configuration is development evidence, not a production artifact: [R1]

- `compose.yaml` starts PHP's development server and separate development/test databases; its optional HTTPS path
  fixes `localhost:18443`. The FPM Dockerfile uses PHP 8.5.12, installs Xdebug and local/development configuration,
  relies on a mounted source tree, and does not package application code/dependencies/assets into a release image.
- Composer requires PHP >=8.5 and PDO PostgreSQL. App Platform's fetched PHP page advertises 8.5 support but lists
  8.5.0–8.5.2 runtimes; it does not prove the repository-tested 8.5.12 environment or every extension/platform behavior.
  A qualified immutable custom Linux/amd64 image may avoid buildpack mismatch; a bare FPM image still needs HTTP
  serving. Verify actual platform requirements/Argon2id/password corpus and preserve notices. Do not lower a runtime
  requirement or run a dependency update merely to fit hosting. [S4, S27, R1]
- Build artifact must contain production dependencies, PHP/application configuration, compiled `var/client/build/`,
  required generated application exports, migrations and password resources/notices. Runtime may use ignored `var/`,
  never `.runs/`. Keep source locks reproducible and build-only credentials out of image layers. Disable production
  debug and diagnostic routes; no application/provider/DB encryption secrets at build time.
- README documents production-like browser boot as direct TLS authority with no trusted forwarding proxies.
  App Platform introduces a managed edge/CDN and origin hop; its existence does not qualify the actual trusted
  proxy/host/IP/origin contract. A Droplet is not automatically correct either: NGINX/FastCGI request facts,
  direct public access restrictions, certificate renewal, `__Host-` cookies, HSTS, CSRF and limiter state need composed
  acceptance under existing security owners/ADR 0003. [R1, R3, S4, S13]
- Neither a managed platform nor encrypted variables implement per-user provider secret lifecycle. Keep keys in
  the protected connection owner from WF-044, with independent encryption-key custody/recovery. CI/container-registry
  pull authority, cloud administrator access and application runtime authority are different. No Agent sees broad
  cloud, Git, DB, mail or provider secrets. Encrypted App variables do not protect against an authorized console or
  modified production image. [S12, R5]
- DigitalOcean documents default Droplet outbound SMTP blocks on ports **25/465/587**, including Reserved IP traffic.
  Use a separately qualified transactional **HTTPS API** supplier rather than assume generic SMTP works. Resend is
  only a priced candidate with documented SDK/API access, not a selected adapter or an assurance of delivery. Local
  null/memory/Mailpit are not real mail. Preserve package credential work, at-least-once identity and expiry/lease
  policy; implement no second outbox. Staging uses synthetic recipients/test transport, not real production mail.
  Provider enrollment, sender/DNS authentication, credential delivery and actual inbox tests require later authority.
  [S23, S28, S29, R6]
- Provider-managed DB backups do not preserve external keys, object data or the whole installation. Droplet backups
  are crash-consistent boot-disk images stored in the same datacenter, **exclude volumes**, and do not prove an
  application-consistent multi-service restore. The inspected basic-backup pages do not establish an exact chosen
  fixed retention; usage-based plans offer configurable retention. Select retention and rehearse restore under
  WF-050; qualify independent copies, deletion/ciphertext/key recovery and residual costs. [S10, S18–S21]

### 7. Keep four kinds of execution separate

1. **Trusted application/durable conversation work** persists original intent/results in application PostgreSQL,
   with bounded provider I/O and recoverable supervision; long streams must not occupy every FPM worker. Whether
   the first journey needs a separate process is still WF-049/implementation evidence, not inferred from chat UI.
2. **Credential delivery/recovery** consumes the existing protected package queue with a supervised finite schedule.
   A scheduled command is not an Agent shell grant; reserve connections/memory separately and prove restart recovery.
3. **Release artifact builds** may remain in GitHub CI; promote the same digest/configuration through staging and
   production. Automatic branch rebuilds can violate build-once promotion. App Platform rollback changes code/spec,
   not database data; both topologies need migration compatibility and a real project runbook. [S4, S14, R7]
4. **Optional candidate checks / trusted Git operations** retain WF-047's separate authority. Untrusted candidate
   code must not share production/staging secrets, storage, Docker socket or resource contention. A registered Git
   worktree is not isolation. No candidate worker, GPU, Kubernetes or managed execution fleet is required simply
   to operate the first browser Planning/help journey. Separate workers become necessary when accepted candidate
   execution or measured trusted background workload cannot fit qualified isolation/headroom, not because of branding.

### 8. Proposed owner handoffs, not accepted amendments

| Existing owner | Proposed later planning action / remaining gap |
|---|---|
| TICKET-00023 / TASK-00072 | Preserve standalone local Compose and shared-ingress scope; explicitly allocate a production packaging/installation variant and cloud recovery supplier. TASK-00072 excludes production deployment and mandates private SeaweedFS; Managed DB/Spaces/App Platform are not automatic substitutions [R2] |
| TICKET-00022 / ADR 0003 | Qualify selected production HTTPS/proxy, origin/cookies, secret delivery and debug-off envelope; do not infer it from authenticated local UI [R3] |
| TICKET-00011 | Reuse completed credential queue/recovery; name a real HTTPS mail adapter, external key operator and production schedule supplier. Provider enrollment/scheduling remain outside its completed foundation [R6] |
| TICKET-00058 / TASK-00222 | Reuse exact artifact/target/runbook/health/recovery semantics; project-owned Fight production artifact/runbook and enrollment remain unqualified. The full managed Release Manager product is not a first operator-run release prerequisite [R7] |
| TICKET-00117 / TASK-00481–00484 | Keep accepted inventory, physical allocations, supervision, temporary restore isolation and independent status participants; no selected Loki image, capacity proof or replacement archive semantics from this cost comparison [R4] |
| TICKET-00089 / TICKET-00098 | Retain WF-044's protected per-user connection/refresh ownership, actual source/host holds and distinct recorded usage/allowance; hosting recommendation clears none of them [R5] |

No EPIC/TICKET/TASK was created, edited or reprioritized. TASK-00510–00512 and other worktrees remain untouched.
No new WF is needed to restate these known gaps: **WF-048** must obtain actual topology/region/staging/budget
selection using this research and WF-047, and **WF-050** must settle downtime, RPO/RTO, full operations cost,
retention, supplier allocation and go/no-go evidence. Neither research closure nor a later choice grants provisioning.

## Sources

Official public sources fetched on 2026-10-23 UTC; mutable pages must be rechecked before a purchase or deployment.
Scratch snapshots, timestamps, final URLs, HTTP statuses and SHA-256 receipts are checkout-local under ignored
`.runs/notes/wf-045-digitalocean/`, not runtime dependencies. A guessed standalone rollback URL returned 404;
the successful owning deployment page S14 supplies the actual rollback evidence. No authenticated/account URLs
or example commands from the documentation were executed.

- S1: [Droplet plan price table](https://www.digitalocean.com/pricing/droplets).
- S2: [Droplet billing, v5 and transfer](https://docs.digitalocean.com/products/droplets/details/pricing/).
- S3: [App Platform pricing](https://docs.digitalocean.com/products/app-platform/details/pricing/).
- S4: [App Platform limits](https://docs.digitalocean.com/products/app-platform/details/limits/).
- S5: [PostgreSQL pricing](https://docs.digitalocean.com/products/databases/postgresql/details/pricing/).
- S6: [Managed Database price table](https://www.digitalocean.com/pricing/managed-databases#postgresql).
- S7: [Dated PostgreSQL/MySQL plan transition](https://docs.digitalocean.com/release-notes/upcoming/dbaas-plan-changes/).
- S8: [PostgreSQL limits, versions and connections](https://docs.digitalocean.com/products/databases/postgresql/details/limits/).
- S9: [PostgreSQL Advanced Edition](https://docs.digitalocean.com/products/databases/postgresql/concepts/advanced-edition/).
- S10: [PostgreSQL backup restore](https://docs.digitalocean.com/products/databases/postgresql/how-to/restore-from-backups/),
  [features/PITR](https://docs.digitalocean.com/products/databases/postgresql/details/features/).
- S11: [App Platform VPC and datacenter mapping](https://docs.digitalocean.com/products/app-platform/how-to/enable-vpc/).
- S12: [App Platform encrypted variables and operator visibility](https://docs.digitalocean.com/products/app-platform/how-to/use-environment-variables/).
- S13: [App Platform domains/certificates](https://docs.digitalocean.com/products/app-platform/how-to/manage-domains/).
- S14: [App Platform deployments and rollback limits](https://docs.digitalocean.com/products/app-platform/how-to/manage-deployments/).
- S15: [Native scheduled/deployment jobs](https://docs.digitalocean.com/products/app-platform/how-to/manage-jobs/).
- S16: [Spaces pricing and transfer routing](https://docs.digitalocean.com/products/spaces/details/pricing/).
- S17: [Container Registry pricing](https://docs.digitalocean.com/products/container-registry/details/pricing/).
- S18: [Droplet backup pricing](https://docs.digitalocean.com/products/backups/details/pricing/).
- S19: [Backup scope/consistency/retention](https://docs.digitalocean.com/products/backups/details/features/),
  [configuration](https://docs.digitalocean.com/products/backups/how-to/enable/).
- S20: [Volume pricing](https://docs.digitalocean.com/products/volumes/details/pricing/).
- S21: [Snapshot pricing](https://docs.digitalocean.com/products/snapshots/details/pricing/).
- S22: [Load Balancer pricing](https://docs.digitalocean.com/products/networking/load-balancers/details/pricing/).
- S23: [Resend transactional email pricing](https://resend.com/pricing).
- S24: [Regional availability](https://docs.digitalocean.com/platform/regional-availability/),
  [App Platform](https://docs.digitalocean.com/products/app-platform/details/availability/),
  [PostgreSQL](https://docs.digitalocean.com/products/databases/postgresql/details/availability/).
- S25: [Projects are resource organization](https://docs.digitalocean.com/products/projects/details/features/).
- S26: [Spend alert scope and behavior](https://docs.digitalocean.com/platform/billing/spend-alerts/).
- S27: [App Platform PHP buildpack/runtime](https://docs.digitalocean.com/products/app-platform/reference/buildpacks/php/).
- S28: [DigitalOcean default outbound SMTP blocks](https://docs.digitalocean.com/support/why-is-smtp-blocked/).
- S29: [Resend API/SDK introduction](https://resend.com/docs/introduction).

Repository sources at the isolated checkout's base `5e0d0ea4874af638fc7918d07ce0420f286e4ca3` plus these uncommitted
Wayfinder notes; configuration/planning inspection, not a full source audit or fresh runtime evidence:

- R1: [README](../../../README.md), [Compose](../../../compose.yaml),
  [FPM Dockerfile](../../../etc/docker/fpm/Dockerfile), [Composer requirements](../../../composer.json).
- R2: [TICKET-00023](../../tickets/00023-TICKET.md), [TASK-00072](../../tasks/00072-TASK.md).
- R3: [ADR 0003](../../adr/0003-browser-authentication-security-profile.md).
- R4: [TICKET-00117](../../tickets/00117-TICKET.md), including mandatory physical-resource and failure qualification.
- R5: [WF-044 provider research](WF-044-per-user-openrouter-and-allowance.md).
- R6: [TICKET-00011](../../tickets/00011-TICKET.md).
- R7: [TICKET-00058](../../tickets/00058-TICKET.md).

## Verification and limits

`./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed after reconciliation:
645 delivery records, 496 active and generated views current. All changed/untracked paths are Wayfinder-only;
separate whitespace checks include new files that tracked diff checks omit. Inspected non-Wayfinder delivery/
configuration sources remain byte-identical to HEAD. No application build, independent Review/QA, live source,
mail, database, hosting, runtime, throughput or restoration qualification was run. All changes remain uncommitted.

## Open questions

- Human selection, before WF-048 closes: Droplet versus App Platform; region/residency; reduced staging capacity;
  non-HA risk; actual DB/storage size and finite recurring budget. Proposed $200 + $100 reserve is not approved.
- Before WF-050 launch acceptance: downtime/RPO/RTO, independent backups and external-key recovery, finite log/
  archive/restore resources, actual mail provider/schedule, complete operations/CI/egress/tax costs and operators.
- Actual account SKU/edition capacity and current Advanced shared/HA quote; exact basic-backup retention; selected
  trusted TLS/private endpoints and quota for overlap. Public documentation cannot answer these account questions.
- Packaging, cloud installation, full storage/collection and project runbook suppliers are unallocated or
  unqualified; preserve named delivery owners/holds. App Platform full-fidelity diagnosis is not presently established.

WF-045 can close as a **dated comparison and recommendation only**, not a hosting purchase, accepted budget,
production image/source qualification or delivery-owner amendment. Current map frontier after reconciliation:
[WF-046 — Define optional local routing, trust and recovery](../tickets/WF-046-define-optional-local-routing-trust-and-recovery.md).

Next: `/skill:wayfinder work WF-046`
