# Initial DigitalOcean region for San Antonio

## Question

After selecting Droplets with separate production/staging Managed PostgreSQL clusters, John delegated the region
choice to whatever works best for San Antonio, Texas. Which region is a defensible planning default without
claiming measured latency, account capacity or an approved budget?

## Short answer

Use **SFO3 as the planning default**, applying John's subsequent cost-first preference to choose the shorter
straight-line distance between San Francisco and New York. This supersedes the initial provisional ATL1
recommendation: John judged Premium-only regional plans unlikely to justify the unmeasured latency benefit.
SFO3 advertises Regular Basic Droplet/shared PostgreSQL availability; actual SKU capacity and the complete
operating budget still need qualification. [S1–S2]

Approximate city-center inputs (degrees latitude/longitude): San Antonio `(29.42, -98.49)`, San Francisco
`(37.77, -122.42)`, New York `(40.71, -74.01)`. A local haversine calculation using mean Earth radius 6,371.0088 km
returns approximately **2,394 km / 1,488 miles to San Francisco** versus **2,545 km / 1,581 miles to New York**.
These rounded inputs are a coarse city-to-city comparison, not sourced datacenter coordinates, measured network
routes or a promise of lower latency. The approximately 93-mile difference selects SFO under John's stated rule.
No network benchmark, account call or new public fetch was needed for this calculation.

The earlier ATL1 recommendation used nearby geography and advertised service breadth; MKC1/MEM1 were also
considered. Retain that comparison below as research history, not the selected topology. Neither the Premium
price difference nor latency advantage was measured. Do not put the application and database in different regions
merely to preserve a cheaper SKU. [S1–S2]

## Findings

Public documentation rechecked **2026-10-23 UTC**, approximately 18:24 UTC; four unauthenticated GETs returned 200.
No cloud/account API, provider qualification, resource creation or latency/load test occurred.

| Candidate | Advertised regional characteristics | Planning implication |
|---|---|---|
| ATL1 — Atlanta | Basic Droplets and shared PostgreSQL restricted to Premium CPUs; v5 also available. Backups, VPC, volumes, Spaces Standard and Container Registry advertised | Earlier nearby full-service candidate, superseded by John's cost-first SFO/NYC criterion |
| MKC1 — Kansas City | Basic Droplets/shared PostgreSQL restricted to Premium Intel; v5 available. Backups, VPC, volumes and Spaces Standard advertised; Container Registry not listed there | Viable nearby alternative; nonlocal registry is possible but adds an artifact-service placement consideration, not a runtime DB dependency or categorical blocker |
| MEM1 — Memphis | v5 Droplets and AMDv5 PostgreSQL listed; bundled Basic Droplets, Spaces Standard and Container Registry not listed | More changed SKU/storage assumptions; no demonstrated benefit over ATL1 without price/latency evidence |
| SFO3 — San Francisco | Broad Basic/shared PostgreSQL, backup, object storage and registry availability | Retains the original Regular-plan comparison assumptions more directly, but no San Antonio latency evidence |
| NYC-area | Broad Basic/shared PostgreSQL support; registry/Spaces at NYC3 rather than NYC1 | Farther than San Francisco under the approximate city-to-city comparison; not selected |

All rows derive from the regional matrix and PostgreSQL availability page. Empty cells are reported as not listed,
not a claim of permanent impossibility. Provider-advertised availability does not establish John's account quota,
actual creation options or final deployed topology. [S1–S2]

### Cost and recovery implications

- WF-045's $87.10/month Droplet comparison used Regular bundled application sizes and minimum database prices.
  **Do not present that figure as an ATL1 quote or launch budget.** Exact Premium PostgreSQL and Droplet offerings,
  edition/HA, capacity and complete operations costs remain unqualified. No updated total is invented. [S1–S4]
- v5 has per-resource hourly rates without the bundled plans' monthly cap; public IPv4's introductory line item is
  currently zero and subject to change. Merely seeing v5 availability does not select it over a bundled Premium
  plan. Select actual SKUs only after pricing the full intended profile. [S3]
- PostgreSQL's pricing page retains both older HA descriptions and an explicit October/November Standard-edition
  transition notice. WF-045's warning remains: no assumed grandfathering or obsolete ~$60 new-HA design. [S4]
- Place each environment's application and database in the selected SFO3 region with separate resources/access. Using the same region
  does not merge their authority or make staging a disaster-recovery site. Independent recovery copy placement
  and actual RPO/RTO remain WF-050.
- Listing Spaces/Registry supports evaluating the region, not approval to replace private SeaweedFS, select an
  object-store supplier, or add a provider metrics/logs product to the accepted diagnosis design.

## Sources

- [S1 — DigitalOcean regional availability](https://docs.digitalocean.com/platform/regional-availability/)
- [S2 — PostgreSQL availability](https://docs.digitalocean.com/products/databases/postgresql/details/availability/)
- [S3 — Droplet pricing and billing](https://docs.digitalocean.com/products/droplets/details/pricing/)
- [S4 — PostgreSQL pricing](https://docs.digitalocean.com/products/databases/postgresql/details/pricing/)
- [Prior dated hosting comparison — WF-045](WF-045-digitalocean-hosting-and-costs.md)

Ignored public-source snapshots and timestamp/status/SHA-256 receipts are in the selected planning checkout's
`.runs/notes/wf-048-regions/`. Markdown source URLs append `index.html.md` to the cited directory URLs.

## Open questions

Actual route latency from the user's San Antonio connection, account/SKU availability and complete prices are
unknown. Recheck them before provisioning; return for a material budget/topology change rather than treating
regional delegation as spending authority. No network benchmark was run from this session and no claim is made
that its current machine/network is the user's San Antonio connection.

John subsequently selected smaller staging with the same software and deployment setup, non-HA production
conditional on verified recovery, a combined below-$100/month infrastructure preference and operator-approved
exact-artifact promotion. [WF-048](../tickets/WF-048-define-staging-and-artifact-promotion.md) now closes policy
and owner handoffs; actual cost/capacity and recovery qualification remain open under the WF-050 handoff.
Existing application/security/storage requirements remain; small staging cannot prove production-load capacity.

Next: `/skill:wayfinder work WF-049`
