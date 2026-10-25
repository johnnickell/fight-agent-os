---
id: TICKET-00122
epic: EPIC-00021
title: Operate isolated staging and production environments
status: ready-for-agent
---

# Operate isolated staging and production environments

## Problem statement

The private application needs actual secure, maintainable deployment targets, not development Compose relabeled
as production. John must know which environment is running, what authority/data it can reach, whether required
services are healthy and whether the complete installation fits the agreed cost and resource preference.

## Solution and boundaries

Supply the Fight-specific runtime composition and operator procedures for `staging.fight-agent.dev` and
`fight-agent.dev`, consuming [TICKET-00121](00121-TICKET.md)'s exact artifact. Use separate SFO3 application Droplets,
initially 2 GiB RAM / 50 GiB SSD / one shared vCPU each. Production uses Managed PostgreSQL; staging PostgreSQL
runs on its own application Droplet. Recheck actual supported SKUs, prices and capabilities before separately
approved provisioning. These sizes are initial choices, not evidence of fit or a purchase order.

Separate hosts, data, database roles, credentials, signing/encryption keys, storage namespaces and network
administration. Staging cannot administer production or implicitly copy its data. Match artifact/runtime,
database major/extensions, schema and relevant services/security while documenting the managed/self-managed
operational differences. Maintain staging database updates, supervision and consistent export integration;
[TICKET-00124](00124-TICKET.md) owns coherent backup/restore behavior. Never execute candidate code on either host.

Deliver real public TLS and renewal, exact trusted origins/proxies, scoped secure cookies without parent-domain
sharing, debug-off operation and the existing authorization/header/throttling behavior. Reuse
[TICKET-00022](00022-TICKET.md)'s security contracts and [TICKET-00023](00023-TICKET.md)'s applicable installation
seams; do not import workstation trust/root mounts or claim local-only tasks already deliver public hosting.
Keep secrets outside images, source, browser persistence/readback and ordinary logs. Preserve encryption/key
versions required by outstanding operations and retained backups; Bitwarden's human recovery role grants no
runtime vault integration.

Configure and supervise required application, worker, projector and scheduling processes, including explicit
startup/update/stop/restart procedures and safe reconciliation after interruption. Start staging with one worker
if possible, at most one genuinely required projector and one cron container. The requested 512 MB PHP limit is
per-process, not an aggregate memory allowance; preserve OS/database/service headroom and modest concurrency.
Production counts require actual measurement. Coordinate email cadence with TICKET-00123 and backups with
TICKET-00124 rather than inventing additional runners.

Integrate private application/evidence storage from TASK-00374 and diagnosis from TICKET-00117. Report application,
worker, scheduling, collection/archive and backup health independently, including freshness, pressure and unknowns.
Reuse existing status/guide suppliers, not another monitoring dashboard, queue or alert platform. Missing logs,
mail or scheduled work is not green. Preserve accepted diagnosis retention, coverage and hard resource isolation;
return conflicts instead of silently shrinking requirements to fit 2 GiB.

Consume per-user protected OpenRouter connections and their exact environment/generation/admission boundaries
from the existing owners. Routine staging checks use labeled simulations; live checks need explicit enablement,
staging user connections and independent budgets. No shared key, fallback, borrowed production budget or automatic
cap increase. Distinct keys do not prove separate upstream wallets. Missing allowance stays unavailable.

Report current resource use and combined tax-inclusive recurring costs against the sub-$100/month preference,
including database, storage/overages, diagnosis, registry, mail, CI/network and recurring domain costs. Inference
is separate. The old $56.35 pre-tax subtotal is not a complete quote. Present unavoidable cost/fit choices to John;
no automatic cutoff, paid upgrade or speculative capacity. Temporary recovery has separate TICKET-00124 approval.

Out of scope: replacement security/diagnosis/provider systems, HA, candidate execution, optional local routing,
managed worker privileges, automatic production promotion and a standalone capacity/certification project.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Prepare a named environment | Explicit authorized infrastructure/configuration operations; no invented Domain command | Target/SKU, current quote, privileges, intended artifact/configuration and expected effects | Infrastructure receipts and existing configuration/audit facts only | Only approved host/network/database/storage/TLS changes |
| Start or maintain the runtime | Explicit bounded operator start/update/stop/restart operations | Current identity, schema compatibility, services/schedules and original interrupted operation | Existing service outcomes, not Workflow lifecycle events | Supervised services reach observed state or a truthful partial/unknown stop |
| Inspect health and capacity | None; read-only owner/status inspection | Separate service, diagnosis, backup and resource/cost observations with freshness | None from reads | Safe actionable status without automatic repair or secret disclosure |
| Configure protected service integration | Existing owner operations under exact environment authority | Required provider/mail/storage capabilities and allowed configuration revisions | Existing owner facts only | Environment-scoped integration, without replacing ownership or borrowing production credentials |

## Validation and permissions

Provisioning, spending, DNS/certificate enrollment, credentials and service changes require exact target/effect
approval. Check current authority and actual supported capabilities; a read-only report cannot enroll or repair.
Fail closed on insecure origins/proxy trust, invalid/missing secrets, unsafe debug configuration or unauthorized
cross-environment reach. Keep runtime state outside `.runs/`; operational guidance must work during application
failure without exposing secrets. Current application authorities remain server-owned.

## Acceptance and evidence

- Actual authorized targets run the selected artifact with isolated data/roles/keys/storage/network authority;
  demonstrate denied cross-environment access and no implicit production-data transfer.
- Public ingress, renewal, exact-origin/proxy behavior, secure-cookie isolation, debug-off and application security
  work at the real boundary; DNS records or localhost checks alone do not qualify production.
- Start, update, restart and interruption inspection report actual artifact/configuration/service state without
  blind effect replay. Independent health remains useful when application search or one service is unavailable.
- Measure aggregate process/container/storage pressure and headroom with required services and diagnosis intact.
  Record actual recurring estimates/known charges, tax assumptions and missing figures; unresolved fit/cost requires
  a human tradeoff, not a false feasibility claim.
- Qualify private storage, protected runtime and service participants through their real owners. Labeled simulations
  do not qualify live providers, and allocation does not clear TASK-00342/00346/00350/00399 or observability holds.
- Validate infrastructure through its owning tools, application behavior through owned tests, then full
  `./bin/build` and independent Review/QA with bounded target probes. No product tests of Compose, wrappers,
  configuration text or quality mechanisms; missing production evidence blocks only production-dependent effects.

## Coordination

A's artifact and early application/storage/diagnosis contracts feed this installation. Supply topology, service,
secret-version and resource interfaces early to [TICKET-00123](00123-TICKET.md), [TICKET-00124](00124-TICKET.md) and
[TICKET-00125](00125-TICKET.md); do not create whole-TICKET cycles or wait for their completed production journeys.
Existing owners retain code/policy and actual qualification; production application of their contracts belongs
here. Ordinary local development and unrelated installations remain unchanged.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00524](../tasks/00524-TASK.md) | Run the staging application behind real HTTPS | ready-for-agent |
| [TASK-00525](../tasks/00525-TASK.md) | Operate staging's protected services and diagnosis | ready-for-agent |
| [TASK-00526](../tasks/00526-TASK.md) | Operate the isolated production target with Managed PostgreSQL | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved slice B in [EPIC-00021](../epics/00021-EPIC.md)'s five-TICKET split, applying WF-043/048/050 and the
[reconciled owner boundaries](../wayfinder/research/private-production-owner-reconciliation.md).
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md) remain governing contracts. The requirement approval
allocated no TASKs; John subsequently approved the three-slice decomposition below. Neither approval authorizes
provisioning, spending or deployment; Board priority and supplier holds remain.

### Approved TASK decomposition and current handoff

| Slice | Accepted TASK | Blockers | Delivered outcome |
|---|---|---|---|
| A | [TASK-00524 — Run the staging application behind real HTTPS](../tasks/00524-TASK.md) | TASK-00523, TASK-00063, TASK-00284 | Actual core staging browser/API target with exact CI artifact, public TLS/renewal, private local PostgreSQL, isolation and bounded lifecycle procedures |
| B | [TASK-00525 — Operate staging's protected services and diagnosis](../tasks/00525-TASK.md) | TASK-00524, TASK-00346, TASK-00487 | Actual qualified private storage/provider/worker/projector/schedule/diagnosis composition, independent status and operator recovery, aggregate resource/retention fit and cost observations |
| C | [TASK-00526 — Operate the isolated production target with Managed PostgreSQL](../tasks/00526-TASK.md) | TASK-00525 | Separate production host/managed database, exact-artifact parity, cross-environment denial, production operations/headroom and combined tax-inclusive recurring-cost reconciliation |

All three are unranked `chore` TASKs, `ready-for-agent` only when their dependencies permit. B transitively consumes
TASK-00374's storage, TASK-00072's installation seams, TASK-00520's connection custody and existing diagnosis/status/
guide suppliers. Allocation does not resolve TASK-00346/00350 or diagnosis native authority/isolation holds. No
existing TASK status, order, dependency or qualification requirement is changed; EPIC-00020 retains top priority.

A is a useful core staging target, not complete service/provider/diagnosis readiness. B installs the qualified
composition rather than creating a capacity-only project; C applies it to the separate real production boundary.
Each owns direct tooling/target checks, meaningful application behavior evidence where applicable, full build and
independent Review/QA. No product tests of infrastructure configuration/wrappers or standalone certification TASKs.

Mail and backup owners consume concrete early target, storage, secret-version, schedule and freshness interfaces;
no slice waits for their completed journeys or implements replacement engines. Absent real integration remains
unavailable, never green. TICKET-00125 still owns exact release approval, composed first-production activation and
TICKET-00028's Planning cutover. Missing production authority/evidence blocks production-dependent effects, not
unrelated local/staging work. No implementation, provisioning, credential access or live operation follows here.

Immediately before allocation, checked live/archive TASK filenames across seventeen local/cached refs and nine
registered worktrees, including untracked files; highest observed was TASK-00523. TASK-00524–00526 are local
allocations, not globally reserved IDs. The pre-edit receipt under ignored
`.runs/notes/environment-decomposition/before.json` covers 778 planning Markdown files.

This TICKET's accepted requirements are fully decomposed, not implemented or operationally accepted. The map's
TASK phase is now complete after TICKET-00123's TASK-00527/00528, TICKET-00124's TASK-00529/00530 and TICKET-00125's
subsequently accepted TASK-00532/00533. Work remains uncommitted/unpublished.

#### Planning verification

`./bin/planning-check --write` and `./bin/planning-check` passed: **671 records, 515 active**, generated views current.
`git diff --check` and changed/untracked scope/whitespace checks passed across **62 planning paths** including earlier
uncommitted work; 4,038 relative link targets were checked.

Against the 778-file pre-edit baseline, exactly thirteen existing handoff/generated-view paths changed and three
TASKs were added. No prior file was removed. All existing frontmatter and TASK files remain byte-identical, preserving
statuses, orders, dependencies, qualification holds and EPIC-00020 priority. Verified the three new records' exact
parent/kind/status/blank-order/blank-PR metadata and approved blocker lists, including B's transitive storage/runtime/
connection-custody/protected-host/operator-guide suppliers. Generated planning validation confirms an acyclic graph.

This is planning evidence only: no application build, independent Review/QA, infrastructure configuration, target
probe, provisioning, spending, credential access, DNS/TLS enrollment, provider/mail request, migration, deployment
or recovery occurred. No commit or push occurred. Prior lifecycle/artifact/TICKET closeout counts remain historical.

#### Subsequent approved mail-supplier amendment

During TICKET-00123's separately approved decomposition, John added TASK-00284 to TASK-00524's blockers. The
existing real credential-provider startup guard cannot be met by a development fallback; TASK-00284 owns the
shared Resend HTTPS primitive and qualifies independently of hosted staging. Its `needs-info` account/target/effect
holds remain. This is a real early supplier edge, not a wait for completed invitation/reset/mail scheduling.
TASK-00525/00526 inherit it without redundant edges. No existing status/order or other dependency changes.

TICKET-00123 records the mail amendment's verification; the environment counts above remain historical to that step.

Next: `/skill:next` for read-only Board-based selection after completed selected-map planning. No automatic implementation.
