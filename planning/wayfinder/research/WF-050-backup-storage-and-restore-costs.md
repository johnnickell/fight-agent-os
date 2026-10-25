# Lean hosting, Spaces and 30-day backup costs

## Question

For [WF-050](../tickets/WF-050-define-private-launch-readiness-and-recovery.md), what do 30 days of daily recovery
points and a temporary restore cost alongside the selected separate SFO3 application/Managed PostgreSQL topology?
John explicitly requested [DigitalOcean Spaces](https://www.digitalocean.com/products/spaces) pricing and expects
low initial application CPU/memory needs. This is cost research, not an account quote or infrastructure purchase.

Observed **2026-10-23 UTC**; USD list prices before tax. Fourteen public fetch attempts produced thirteen successful
snapshots and one 404, recorded under checkout-local `.runs/notes/wf-050-backup-costs/` with timestamp/URL/status/hash
receipts and arithmetic. No accounts, credentials, application-provider calls, infrastructure or runtime were used.
No Jev evaluator requests were needed. Earlier research remains dated evidence, not fresh operational proof.

## Short answer

**Subsequent human selection:** John chose managed production PostgreSQL, PostgreSQL on staging, and Spaces-only
backups. He then favored 2 GiB RAM / 50 GiB SSD app Droplets for both environments, giving a **$56.35/month**
subtotal. See section 7 for current arithmetic and the bounded staging process plan. Sections 1–6 preserve
the earlier two-managed-database/provider comparison, not the current purchase recommendation.

- **Start-small direction is reasonable.** The old 4 GiB production / 2 GiB staging estimate was not a minimum.
  Recommend evaluating **2 GiB production ($12/month) / 1 GiB staging ($6/month)**, preserving separate Managed
  PostgreSQL and staging mechanisms. That reduces the comparable recurring floor from **$87.10 to $63.70**.
  These sizes are recommendations, not John's exact SKU selection or demonstrated capacity.
- **Spaces Standard costs $5/month for a shared 250 GiB pool and 1 TiB outbound allowance**. That subscription is
  already counted in both floors; do not add it twice. It is a practical backup destination, not automatic
  replacement of the existing private SeaweedFS/application-storage contract. [S1, S2]
- Thirty full daily archives of **5 GiB each retain 150 GiB**: no extra Standard storage charge if that much of
  the pool is unused. Thirty archives of **10 GiB retain 300 GiB**: **$1/month extra**, if nothing else uses the pool.
- An independent encrypted **Backblaze B2** copy costs about **$1.12/month at 150 GiB** or **$2.24 at 300 GiB**,
  excluding transfer exceptions and tax. Extra provider/account/key operation is the tradeoff, not high storage cost.
- A temporary lean application VM plus minimum DB costs approximately **$0.97 for 24 hours of compute**. Actual
  restore costs also include storage, transfers, larger targets if required and time until resource destruction.
- **Below $100 looks more plausible with lean application VMs, but is not a complete verified bill.** Diagnosis,
  actual persistent storage, trusted background work, mail, CI, domain, tax and recovery overlap still need their
  real allocations. None is silently deleted or assumed free to fit the target.

## Findings

### 1. Lean recurring hosting comparison

Public Basic Regular Droplet and PostgreSQL tables confirm these inputs. Same-region availability and John's
actual account quote still require later checking. The minimum 1 GiB database is a cost candidate with only
22 backend connections per earlier WF-045 evidence, not proven production sizing. [S3–S5, R1]

| Monthly line | Earlier illustration | Recommended lean candidate |
|---|---:|---:|
| Production application | 4 GiB / 2 shared vCPU: $24.00 | 2 GiB / 1 shared vCPU: $12.00 |
| Staging application | 2 GiB / 1 shared vCPU: $12.00 | 1 GiB / 1 shared vCPU: $6.00 |
| Two separate minimum Managed PostgreSQL clusters | $30.30 | $30.30 |
| Daily Basic Droplet boot backups (30% of VM cost) | $10.80 | $5.40 |
| Spaces Standard subscription | $5.00 | $5.00 |
| Registry Basic, carried from WF-045 | $5.00 | $5.00 |
| **Comparable pre-tax floor** | **$87.10** | **$63.70** |

A more aggressive **1 GiB / 1 GiB** application pair gives a **$55.90** floor on the same assumptions. It has less
production headroom and is not the preferred starting suggestion. Neither table runs model inference locally;
OpenRouter inference remains a separate per-user/environment expense. Smaller VM sizes do not imply permission
to omit required services, weaken isolation, shorten diagnosis retention or move candidate builds onto app hosts.

Use ordinary implementation/deployment observations to tune PHP workers, trusted jobs, memory and disk. Resize
when actual workload requires it; do not buy hypothetical growth or invent a capacity-certification TASK first.
Smaller staging still needs the selected software and deployment parity, not production-load equivalence.
The previous illustrative $24 operations VM was not a selected mandatory purchase; actual accepted diagnosis and
storage requirements still need a workable allocation rather than assumed spare app-VM memory. [R1]

### 2. Spaces: explicit price and recovery boundaries

**Standard:** $5/month subscription includes 250 GiB across buckets and 1,024 GiB outbound. Storage overage is
$0.02/GiB-month; transfer overage $0.01/GiB. Different environment buckets do not get separate included pools.
An already-used team pool is not free capacity. For existing Standard usage `S` and new retained backup bytes `B`
in GiB, incremental storage cost is `0.02 × (max(0, S + B - 250) - max(0, S - 250))`. [S1, S2]

Spaces-to-SFO Droplet transfers have documented free same-region-group routes; cross-region-group public transfers
use ordinary allowances. Droplet-to-Spaces uploads do not consume Spaces outbound allowance but **do consume the
Droplet outbound allowance** under the current docs. Check the exact route; neither private networking nor inbound
Spaces traffic makes every leg free. No API-call fee is listed in the cited Standard price schedule; rate limits
and transfer/storage billing still apply. [S2]

**Cold Storage:** $0.007/GiB-month, additional to the base subscription rather than using the Standard 250 GiB pool.
Retrieval is $0.01/GiB, waived up to average daily Cold Storage usage for the month. Early deletion/update before
30 days can incur additional charges; the documented first-250-GiB allowance applies to early-deletion usage,
not free cold storage. Minimum billable object/read size is 128 KiB. The product advertises instant retrieval,
not a demonstrated full-application restore time. Do not infer a hard 24-hour restore guarantee. [S1, S2]

For small backups fitting the included Standard pool, **Standard is cheaper and simpler than Cold**. Cold becomes
interesting at larger retained sizes, but deletion/rewrite behavior, actual region support and retrieval charges
need to fit the backup mechanism. No cold-storage selection, lock configuration or lifecycle rule is authorized.

Spaces is object storage, not an automated coherent database/files/keys backup by itself. The runtime storage
owner remains unchanged. Separate scoped credentials, protected recovery keys and restorable data exports are
still required. Another bucket in the same account is not protection against account loss or broad administrator
compromise. A different region reduces some geographic risk, not shared-account risk.

### 3. Retained-data scenarios, not workload predictions

The following assume thirty complete daily archives, constant size, a full steady-state month, no compression or
deduplication savings, and **all 250 GiB of Standard capacity available**. `B` covers the total retained material
being priced, not merely a live database size; aggregate environment histories and referenced files must be counted.
Incremental and versioned copies still need all referenced chunks to make every retained recovery point usable.

| Complete archive each day | Retained after 30 copies | Spaces Standard extra above existing $5 | Spaces Cold extra above base | B2 separate copy |
|---|---:|---:|---:|---:|
| 1 GiB | 30 GiB | $0.00 | $0.21 | $0.22 |
| 5 GiB | 150 GiB | $0.00 | $1.05 | $1.12 |
| 10 GiB | 300 GiB | $1.00 | $2.10 | $2.24 |
| 50 GiB | 1,500 GiB | $25.00 | $10.50 | $11.19 |

Spaces figures exclude transfers/retrieval/early-deletion fees. B2 uses **$6.95 per decimal TB per 30 days**,
converting GiB with `2^30 / 10^12` and conservatively not deducting the first 10 GB free. B2 bills byte-hours;
calendar length and changing average usage affect the invoice. Prices are rounded only for display. [S1, S2, S6]

B2 includes egress up to three times average monthly storage; excess is $0.01/GB. Ordinary Class A/B/C API calls
are free under the fetched pricing; Class D notifications have a separate rate/free allowance. Its API transaction
page contains a conflicting **$0.005/GB-month storage aside**; use the dedicated current pricing page's explicit
$6.95/TB rate for this estimate, not that lower aside. Recheck the actual quote before purchase. [S6, S7]

For example, 5 GiB full DB exports daily plus a file base of 10 GiB and 0.25 GiB of retained new/changed data per
day would retain approximately `30×5 + 10 + 29×0.25 = 167.25 GiB`, before metadata/versions/overlap. That is an
illustration of incremental storage, not evidence that a chosen backup tool achieves this or that database dumps
deduplicate well. The earlier diagnosis volume extrapolation is not measured household traffic; neither ignore
its accepted retention nor multiply every immutable archive thirty times unnecessarily. [R1]

Keep the 30-day target for now: at modest size, shortening it saves little compared with sensible VM sizing.
Do not assume unlimited log growth or a large retained dataset costs pennies. No existing data was inspected.

### 4. What existing managed backups do and do not buy

Managed PostgreSQL daily backups retain **seven days**, with point-in-time selection/latest available transaction.
Restoring creates another primary cluster; deleting the original destroys its managed backups. Native PITR can
help the preferred recent one-hour recovery point, but does not establish cross-service consistency, an independent
copy, a measured RPO or thirty-day history. Daily external coherent exports provide the longer history; older or
provider-loss recovery may be daily-resolution, a concrete tradeoff for John's accepted flexibility. [S8]

Daily Basic Droplet backups cost 30% of VM price, already included above. They are crash-consistent boot-disk
images in the same datacenter and exclude attached volumes; they do not capture a separate Managed DB. The fetched
pages do not establish thirty-day Basic daily retention. Usage-based plans allow configurable retention and quote
$0.03/GiB-month for daily restorable backup size, not just uniquely changed blocks. Do not assume the fixed $5.40
line supplies thirty complete coherent recovery points. [S9–S11]

Retaining thirty full-size VM snapshots at the published $0.06/GB-month can be substantially more expensive than
bounded application exports; volume snapshots are separately $0.06/GiB-month. They still do not replace managed
DB/file/key coordination. Retain the existing boot-backup estimate until recovery owners decide what is safely
rebuildable; this research does not remove it to advertise a lower bill. [S12]

### 5. Temporary restore cost

Published hourly inputs: lean 2 GiB application VM **$0.01786/hour**, minimum 1 GiB DB **$0.02254/hour**. Assuming
those sizes actually suffice, incremental compute for a parallel recovery target is: [S3, S4]

| Target lifetime | Lean app + minimum DB | Earlier 4 GiB app + minimum DB |
|---|---:|---:|
| 6 hours | $0.24 | $0.35 |
| 24 hours | $0.97 | $1.40 |
| 72 hours | $2.91 | $4.19 |

This is **compute only**, not a recovery spending cap or promise the work finishes in that time. Count original
resources while they remain, bigger database/disk requirements, temporary files, snapshots, transfer, tests and
any enabled backup plan on the new VM. Public hourly rates are calculation inputs, not account-specific quotes.
Powering off a Droplet does not stop its allocation charges; destroy only confirmed disposable recovery resources
under the actual operator's authority. Retained snapshots/volumes/buckets can continue billing. [S5, R1]

B2 retrieval within the allowance has no egress charge; if all **100 GiB** of a transfer were billable, egress would
be approximately **$1.07**. New-account low monthly-average storage, repeated downloads and other activity can
change allowance. Spaces Standard has its pool/same-region route rules; Cold retrieval charges and network egress
are distinct. Include upload egress from the original host and restore scratch, not just the download. [S2, S6]

No permanent $100 recovery reserve is justified by these small compute examples, nor is a $1 complete recovery
promise. Quote the actual bounded target/duration when needed; no standing temporary spend is approved here.
Restores must keep production data isolated and mail/provider effects disabled until deliberately reconciled.
Restoring an older database cannot erase charges or authorize replay of external operations since that snapshot.

### 6. Combined budget and recommendation

| Lean candidate scenario | Priced recurring subtotal |
|---|---:|
| Base floor, backups fit unused Standard pool | $63.70 |
| 300 GiB retained in Standard, no other pool usage | $64.70 |
| Base plus separate 300 GiB B2 copy; Standard pool otherwise within allowance | $65.94 |
| 300 GiB in Standard **and** matching separate B2 copy | $66.94 |

All exclude the still-unpriced operating items below; the $5 Spaces subscription is counted once, including when
B2 holds backups. Do not subtract Spaces or substitute B2 for application storage without the owning design.
Compared with $100, the dual-copy illustration leaves **$33.06 before tax and omitted costs**, not guaranteed
spare money. A production DB step to the displayed $30.45 plan adds $15.30/month; paid mail, actual diagnosis/disk,
worker isolation, domain, CI and transfer can still consume the remainder. The old $148.30 example included an
unselected operations VM and paid mail, not mandatory initial purchases. [R1]

**Recommend:** start with small application VMs, retain thirty-day history, use Spaces Standard's included pool
where available, and consider a separately controlled encrypted B2 copy for cloud-account/provider-loss recovery.
The added vendor/custody operation is a genuine human tradeoff. Spaces-only is simpler but leaves shared-provider/
account risk; geographic separation alone does not remove it. No second provider has been selected or enrolled.

Use ordinary packaging/deployment work to identify actual service footprint and storage growth. These unknowns
prevent claiming a complete bill, not local implementation or initial staging. No production-before-production
gate, new readiness TASK, test suite or speculative capacity/benchmark project is created.

### 7. Accepted hybrid direction and staging memory follow-up

John accepted the hybrid proposal and Spaces-only backups after the alternatives were explained. Production keeps
one managed PostgreSQL cluster; staging runs its own PostgreSQL on its app Droplet, with environment data/authority
still separate. Staging no longer matches production's managed operation, while PostgreSQL major/extensions/schema
remain aligned. Keep this explicit in normal deployment work rather than inventing a new parity gate.

After clarifying that the advertised GiB meant RAM, John favors **2 GiB RAM / 50 GiB SSD staging**, not the 1 GiB /
25 GiB alternative. Production also has the proposed 2 GiB RAM / 50 GiB SSD app VM; its separate minimum managed
DB is priced with 1 GiB RAM / 10 GiB DB storage. These are initial planning sizes, not measured workload capacity.

| Current monthly line | Cost |
|---|---:|
| Production app, 2 GiB RAM / 50 GiB SSD / one shared vCPU | $12.00 |
| Staging app + PostgreSQL, 2 GiB RAM / 50 GiB SSD / one shared vCPU | $12.00 |
| One production minimum Managed PostgreSQL cluster | $15.15 |
| Daily boot backups, 30% of both app VM prices | $7.20 |
| Spaces Standard subscription | $5.00 |
| Registry Basic | $5.00 |
| **Current pre-tax subtotal** | **$56.35** |

The preceding hybrid suggestion with 1 GiB staging was $48.55; increasing staging compute by $6 and boot backups
by $1.80 gives $56.35. At 300 GiB total Standard usage, storage overage adds $1 for a $57.35 subtotal. Neither
includes unpriced operations, extra service capacity/disk, tax, domain/CI, mail or temporary recovery. Reuse the
already-fetched unit prices; this arithmetic update involved no new quote, account or public fetch.

John wants **512 MB for PHP** and bounds staging to **one worker if possible, at most one projector if needed for
event sourcing, and one cron container**. This is not a production concurrency choice. Do not add a projector
without a real need, multiply background services speculatively or merge security participants merely to fit RAM.
He expects non-simultaneous activity; idle processes may retain memory and scheduled work may overlap. Account for
web/queue/projector/cron process footprints and OS/PostgreSQL/service headroom, with modest web concurrency. Exact
PHP memory limits and process caps remain ordinary configuration, not a new approval gate or proof of capacity.
Return genuine cost/behavior tradeoffs if this envelope cannot fit; do not create sizing-certification work.

Spaces-only settles the initial backup-provider choice. B2 is not selected or a launch prerequisite; shared
DigitalOcean account/provider exposure was explained before John chose simplicity. Keep thirty-day daily recovery
history conditional on reasonable cost, isolated environment scopes, coherent exports and protected independent
key custody. Native production PITR is supplemental; boot snapshots alone do not supply staging's consistent DB
backup or complete thirty-day data/key recovery. No public buckets, runtime-storage replacement or provider
account enrollment follows. Exact operator/key recovery remains WF-050 work.

## Sources

Fresh public observations on 2026-10-23; scratch receipt paths are evidence only, never runtime dependencies.

- S1: [Spaces product and displayed pricing](https://www.digitalocean.com/products/spaces).
- S2: [Spaces Standard/Cold pricing and transfer](https://docs.digitalocean.com/products/spaces/details/pricing/).
- S3: [Basic Droplet plans and hourly rates](https://www.digitalocean.com/pricing/droplets).
- S4: [Managed Database price table](https://www.digitalocean.com/pricing/managed-databases).
- S5: [Droplet billing](https://docs.digitalocean.com/products/droplets/details/pricing/) and
  [PostgreSQL pricing](https://docs.digitalocean.com/products/databases/postgresql/details/pricing/).
- S6: [Backblaze B2 pricing](https://www.backblaze.com/cloud-storage/pricing).
- S7: [B2 transaction classes](https://www.backblaze.com/cloud-storage/transaction-pricing).
  A guessed `/docs/cloud-storage-transaction-pricing` URL returned 404; the product page linked this successful URL.
- S8: [PostgreSQL restore and retention](https://docs.digitalocean.com/products/databases/postgresql/how-to/restore-from-backups/).
- S9: [Droplet backup pricing](https://docs.digitalocean.com/products/backups/details/pricing/).
- S10: [Droplet backup scope](https://docs.digitalocean.com/products/backups/details/features/).
- S11: [Backup configuration](https://docs.digitalocean.com/products/backups/how-to/enable/).
- S12: [Snapshot pricing](https://docs.digitalocean.com/products/snapshots/details/pricing/).
- R1: [Earlier hosting costs and existing-owner boundaries](WF-045-digitalocean-hosting-and-costs.md), including
  registry/mail/diagnosis and connection-limit evidence reused as dated facts, not fresh qualification.

## Open questions and handoff

- Backup-provider choice is settled: Spaces-only; Bitwarden holds the human recovery kit. Actual protected key
  recovery/access still needs ordinary delivery checks; no broad cloud access or public bucket/CDN is authorized.
- Retain the bounded staging process direction; actual PHP limits/concurrency/footprint, combined stored-data/change
  rate, safe export/schedule/restore participants and full recurring costs remain ordinary owner work. Preserve holds.
- John confirmed the sub-$100 recurring preference **includes tax and recurring services**. Temporary recovery costs
  need a separate quote/approval; no automatic reserve or spending is authorized. The $56.35 pre-tax subtotal is not
  a complete tax-inclusive bill. Resend Free is selected at $0 subscription within eligible limits; Gmail remains
  his inbox. Cost-conditional thirty-day history and recovery flexibility remain. WF-050 closes policy/owner handoff,
  not actual feasibility, provider eligibility or operational recovery.

Only public documentation and arithmetic supplied technical evidence. No application build, independent Review/QA,
live cloud-account, mail, model, database or recovery probe occurred. Changes are uncommitted Wayfinder work only.
`./bin/planning-check --write` and `./bin/planning-check` passed: 645 delivery records, 496 active, generated views
current. Tracked diff plus changed/untracked whitespace/path-scope checks passed across 18 Wayfinder-only paths;
tracked delivery/configuration files remain unchanged. These are planning receipts, not operational qualification.

Next: `/skill:wayfinder work WF-051` — optional research or explicit deferral. WF-050 is closed; carry actual cost,
capacity and recovery suppliers into ordinary owner work. Do not start the next decision automatically.
