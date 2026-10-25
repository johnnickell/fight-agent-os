# Compare DigitalOcean hosting and operating costs

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Private production rollout](../private-production-rollout-map.md)
**Depends on:** [WF-043](WF-043-bound-private-release-and-environment-names.md)

## Question

Which DigitalOcean topology and finite operating budget best support the first private web/API installation and
production-like staging without buying an unnecessary candidate-execution platform?

## Must decide

- Compare a Droplet with production NGINX/PHP-FPM and Managed PostgreSQL against App Platform web/API with
  execution elsewhere. Droplet was an earlier recommendation, not an accepted selection; Kubernetes is unjustified.
- Use current official pricing, regional availability, database engines/major versions/editions, storage minima,
  backup/retention and restore capabilities, HA premiums, transfer and taxes/uncertainty. Record observation date,
  currency, assumptions and quoted versus estimated costs; do not imply account-specific offers or availability.
- Include separate staging, logs/operational storage, artifact/container registry, secrets, credential email,
  scheduling/background services and backup restoration capacity. Separate minimum and resilience upgrades.
- Bound application/durable-conversation processing versus credential-delivery workers, release artifact builds
  and optional candidate checks. Browser planning must not require a candidate-code execution host.
- Preserve production secrets and resource headroom: untrusted candidate code is not a colocated production
  workload. GitHub CI can keep building release artifacts. Identify when separate workers become necessary.
- Compare operational effort, deployment/rollback control, patching, trusted proxy/TLS behavior, resource limits,
  private database connectivity and operator recovery. Identify application packaging/runtime gaps honestly.
- Evaluate production/staging project organization. A DigitalOcean project is organization, not a security
  boundary; credentials, data, networking and budgets still require actual isolation.
- Recommend alternatives and the remaining human decisions: region, downtime, budget and resilience. If pricing
  or capabilities remain unavailable, label them unknown instead of inventing a total.

## Resolution boundary

Research public primary sources and prepare a dated comparison/recommendation for human selection. John's
existing DigitalOcean project is context, not provisioning authority. No account inspection, resource creation,
credential transfer, DNS change, artifact build or runtime experiment. Reuse existing installation, security,
deployment and observability owners; do not create delivery records or claim a selected image is qualified.

## Resolution

Closed as a dated public-source comparison on 2026-10-23, **not** human selection, budget approval, account/capacity
qualification or production readiness. The [cited comparison and owner handoffs](../research/WF-045-digitalocean-hosting-and-costs.md)
answer the research boundary:

- Recommend considering separate production/staging Droplets with production NGINX/PHP-FPM and separate Managed
  PostgreSQL clusters, retaining GitHub CI artifacts. App Platform reduces host maintenance but requires qualified
  external persistence, complete log capture and actual trusted ingress; its 4 GiB ephemeral disk/no volumes and
  2,048-byte forwarded-log limit are not the accepted full diagnosis/storage contract.
- Calculated reduced-staging web/DB/storage/registry floors are $87.10/month Droplet or $77.30 App Platform, USD
  pre-tax. Same-size web staging yields $102.70/$90.30. These omit unknown full operations, schedules, restoration,
  growth, CI and tax costs, and establish no workload capacity. They are not complete launch totals or quotes.
- Propose discussing $200/month recurring plus a separately authorized $100 temporary recovery/test reserve,
  pre-tax, not a spending grant. Illustrative Droplet operations host/storage and paid email bring the floor to
  $148.30 ($163.90 with same-size web staging), still without qualified full diagnosis, volume snapshots or
  independent recovery. Complete supported-launch cost remains unknown; do not weaken accepted requirements to fit.
- PostgreSQL 17 is a parity candidate, not a selected/qualified engine. Standard minimum table price is $15.15 with
  10 GiB storage, versus rounded $15 documentation; only 22 backend connections. Daily backups/WAL provide seven-day
  PITR into a new billable target; cluster deletion deletes backups. Reserve restore overlap/quota and external keys.
- Dated 2026-10-15/2026-11-30 Standard plan changes remove standby/read-only nodes for new clusters. Do not carry
  forward the older ~$60 HA illustration or infer grandfathering from John's existing project. Later announcement
  adds Advanced shared CPU despite a conflicting generic limits statement; current Advanced shared/HA quote remains
  unknown. Two advertised $130 Advanced nodes illustrate $260, not the proved cheapest available HA plan.
- SFO3 and NYC1/NYC3 are documented region candidates, not latency/availability measurements or a human choice.
  Separate project groups organize environments but do not isolate credentials, networks, DB resources or billing.
- Existing development Compose/FPM configuration is not a production artifact. Map production packaging/installation,
  real HTTPS credential-email adapter/schedule, diagnosis allocations and exact rollout/recovery runbook to existing
  TICKET-00023/00022/00011/00058/00117 owners through later authorized planning. TASK-00072 excludes production and
  mandates private SeaweedFS; Managed DB/Spaces/App Platform are not automatic accepted replacements.
- Trusted conversation work, credential delivery, CI artifact builds and optional candidate checks are distinct.
  No candidate-code host is required merely for browser Planning, and none may share production secrets/resources.

WF-048 must obtain actual topology/region/staging/budget selection using this research and WF-047. WF-050 retains
RPO/RTO/downtime, full operations cost, retention, real suppliers and launch go/no-go decisions. No new delivery
records, owner amendments, provisioning, account inspection, credentials, application-provider calls or runtime
experiments occurred; existing holds and Board priorities remain unchanged.

The next authored frontier is [WF-046 — Define optional local routing, trust and recovery](WF-046-define-optional-local-routing-trust-and-recovery.md).
Stop after this decision; do not begin its interview automatically.
