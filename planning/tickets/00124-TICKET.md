---
id: TICKET-00124
epic: EPIC-00021
title: Back up and restore coherent application state
status: ready-for-agent
---

# Back up and restore coherent application state

## Problem statement

Non-HA hosting is acceptable only with usable recovery. VM images and recent managed database backups do not by
themselves recover coherent Planning records, referenced files and required encryption keys. John needs an
independently accessible operator path that states what survived, expected data loss and actual recovery time.

## Solution and boundaries

Deliver actual backup and isolated restoration for the selected staging/production topology under
[EPIC-00021](../epics/00021-EPIC.md). Use **Spaces-only backup storage**, accepting shared DigitalOcean provider/
account exposure. No claim of surviving every provider/account outage and no independent B2/Cold provider gate.
Keep backups separate from TASK-00374's private application/evidence storage responsibility and preserve the full
TICKET-00117 diagnosis retention/recovery contract rather than treating an application snapshot as its replacement.

Retain thirty days of daily coherent database/referenced-file/required-key recovery points conditional on reasonable
cost. Prefer no more than one hour of accepted Planning data loss and usable service within 24 hours **after
discovery**, with John operating rather than staffed overnight response; undetected time is additional. Select and
verify a practical database/file capture and recovery method against that goal during delivery. A daily schedule
alone does not establish a one-hour loss bound; return any daily/worse-loss or capacity/cost tradeoff explicitly
before claiming acceptance. Thirty-day retention is not thirty-day PITR.

Integrate current Managed PostgreSQL recovery/export capabilities for production and consistent PostgreSQL exports
for self-managed staging. Record capture boundaries, referenced file versions, required key versions and known
gaps so the chosen recovery point is coherent and inspectable. Managed backups and crash-consistent boot images
are supplementary, not the complete supplier. Qualify actual restore behavior, account deletion/backup limits,
storage access and retention/cleanup rules rather than relying on documentary availability.

Keep a human emergency kit in Bitwarden with necessary backup/application key versions, DigitalOcean account
recovery material and concise access/restore instructions. It must be usable without Fight or its failed host and
avoid circular vault-unlock/second-factor recovery. This grants no runtime vault integration, vault inspection/
export, Agent credential access or plan purchase. Preserve versions needed by retained recovery points and
outstanding operations; cleanup cannot silently make retained recovery points unreadable.

Restore first into an explicitly authorized isolated target. Suppress mail/provider effects and prevent stale
restored credentials, grants or pending work from becoming current merely because data was restored. Reconcile
original operations, current authority and consumed/uncertain liabilities through their existing owners before
allowing effectful operation. Preserve source data/original resources until separately approved cleanup. A restored
backup must not become a second authoritative Planning writer or bypass TICKET-00028's authority boundary.

Database restoration requires John's approval of the exact recovery point, expected data loss and target. Deployment
or application rollback approval does not authorize data rewind. Quote temporary recovery resources separately:
sizes, duration, coexistence, storage/transfer and cleanup, with bounded extension authority. Powering off a retained
VM does not stop billing. Include independent outage/restore instructions and truthful recovery/backup health;
human communication outside Fight is sufficient, without a new status vendor or operations platform.

Out of scope: automatic destructive restoration, runtime vault integration, a new backup provider, generic disaster-
recovery orchestration, unbounded retention/spending, reduced diagnosis requirements and a certification-only TASK.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Capture a recovery point | Explicit configured operator/schedule operation; no invented Domain command | Exact database/file/key identities, prior capture state and storage limits | Safe operational completion/gap receipts, not new business events | Publish a verified coherent point or visible incomplete outcome |
| Inspect retained recovery options | None; read-only inventory/health inspection | Coverage, time, integrity, key availability, age, retention and known loss | None from reads | Show usable, incomplete, stale or unavailable points without starting a restore |
| Prepare independent recovery | Authorized human kit/setup operation | Required access/key versions, independence, temporary resource quote and target | Operational receipts without secrets | Establish approved recovery access/resources, not runtime vault privileges |
| Restore an approved point | Explicit John-approved isolated restore operation | Approved point/data loss, actual target, schema/files/keys and suppressed effects | Existing owner reconciliation facts and safe restore evidence | Recover inspectable usable state; no automatic production authority or external replay |
| Resume or clean up recovery | Separate authorized owner/resumption/cleanup operations | Current authority, original liabilities, verified result and retained resources/cost | Existing owner outcomes only | Resume only allowed work and remove only approved resources after reconciliation |

## Validation and permissions

Backup read/write, key custody, restore, production activation and destruction are separate authorities. Validate
exact target and current approval before effects; stale approval, mismatched versions, missing keys, inconsistent
coverage or unknown original outcomes stop with a concrete recovery decision. Do not dump secrets to verify keys.
Protect backup confidentiality and access, verify actual integrity/readability and record the limits of checksums.
Read-only inspection performs no repair, deletion, network enrollment or restoration implicitly.

## Acceptance and evidence

- Actual staging exports and production recovery integration produce inspectable coherent points covering database,
  referenced files and required keys, with verified publication and explicit incomplete/stale/failed capture states.
- An authorized isolated restoration recovers usable accepted Planning and referenced content, with matching
  artifact/schema/key requirements and current authorization. Inspect gaps and measure actual achieved data loss/
  recovery time; a configured timer, downloaded archive or simulated clock is not retention/durability evidence.
- Thirty-day daily retention and the preferred loss/time objectives have supported capacity/cost and observed
  mechanism evidence, or John accepts an explicit concrete tradeoff. No unobserved thirty-day operational history
  or one-hour guarantee is fabricated.
- Recovery kit/access instructions remain usable independently of Fight/the failed host, without circular access
  or disclosure of keys. Missing required versions cannot be reported as recoverable.
- Restored mail/provider/grant state remains suppressed until owner reconciliation; no repeated paid work, stale
  authority revival, liability reset, unauthorized data rewind or second Planning writer occurs.
- Temporary target costs, coexistence, limits and actual cleanup/disposition are recorded; failed/abandoned resources
  remain accounted for. Retained originals and recovery points are not deleted by inferred consent.
- Use owning backup/database/storage tools directly, owned application integration tests where applicable, full
  `./bin/build` and independent Review/QA. Authorized target probes establish only their actual coverage; no product
  tests of backup wrappers/configuration or a separate certification platform.

## Coordination

[TICKET-00122](00122-TICKET.md) supplies isolated targets, storage/service configuration and physical limits; provide
capture/key/version/quiescence requirements early so neither waits for the other's final composed acceptance.
TICKET-00028 owns Planning authority/cutover, TICKET-00089/00090 own original provider/conversation effects and
TICKET-00011/00123 own mail state/delivery. [TICKET-00125](00125-TICKET.md) consumes this qualified recovery path but
retains compatible application-only rollback. Staging restoration can be developed and exercised before production
exists; missing production-specific evidence blocks only dependent production recovery claims/effects.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00529](../tasks/00529-TASK.md) | Back up and restore coherent staging state | ready-for-agent |
| [TASK-00530](../tasks/00530-TASK.md) | Recover production state through Managed PostgreSQL and Spaces | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved slice D of EPIC-00021's five-TICKET split. WF-050's hybrid topology, Spaces-only custody, Bitwarden,
conditional retention and explicit restoration/cost boundaries remain settled. Follow
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and existing identity/authority owners.
Initial requirement acceptance allocated no TASKs. John subsequently approved the two-TASK split below; neither
approval grants backup access, vault inspection, restoration, resource purchases or cleanup. Actual mechanisms,
target probes and independent acceptance remain delivery work.

### Approved TASK decomposition and current handoff

| Slice | Accepted TASK | Blockers | Delivered outcome |
|---|---|---|---|
| A | [TASK-00529 — Back up and restore coherent staging state](../tasks/00529-TASK.md) | TASK-00525, TASK-00093, TASK-00233 | Actual consistent staging capture, protected Spaces points, isolated usable Planning/file restoration, independent human recovery access and measured loss/time/cost |
| B | [TASK-00530 — Recover production state through Managed PostgreSQL and Spaces](../tasks/00530-TASK.md) | TASK-00529, TASK-00528 | Actual managed-production integration, coherent isolated restoration, qualified mail/provider suppression and owner reconciliation, exact approvals and resource disposition |

Both are unranked `feature` TASKs, `ready-for-agent` when their dependencies permit, with blank PR fields. Each owns
a complete capture-and-restore path and its target evidence, not a layer-only component, documentation-only change
or separate readiness/certification TASK. Use direct owning-tool checks, owned application integration tests where
applicable, full build and independent Review/QA. No product tests of backup wrappers/configuration or quality tools.

#### Supplier and acceptance boundaries

TASK-00525 supplies staging's concrete storage/service/scheduler/status/diagnosis interfaces. TASK-00093 supplies
Planning replay/rebuild, projection validation and redaction/backup obligations. TASK-00233 supplies the existing
controlled Agent-authority restoration contract and independently retained history/witness controls, not complete
managed-team acceptance or a new credential engine. Their actual qualification holds remain. These suppliers can
expose early interfaces without waiting for completed backup acceptance; no reverse dependency is added.

TASK-00529 has no production prerequisite. TASK-00530 consumes TASK-00528's actual immediate/scheduled mail
suppression/reconciliation and inherits TASK-00526's production target, avoiding a redundant direct edge. Staging
receipt does not qualify production recovery. Approved representative data on the actual managed target can qualify
the mechanism before launch without importing live Markdown authority or claiming coverage of a nonexistent live
dataset. TICKET-00125 retains final composed launch/promotion and TICKET-00028 the actual atomic cutover.

Both slices preserve Spaces-only custody/shared-provider exposure, human-only Bitwarden access, full diagnosis
retention and existing ownership of current authority, original operations and redaction. Independent facts needed
for reconciliation must survive outside the dataset being rewound. Missing facts hold restored effects/authority;
no liability reset, stale grant revival, blind replay or second Planning writer. Point/loss/target approval, resource
spending, activation and destruction remain separate authorities, with originals and failed resources accounted for.

Thirty daily coherent points remain cost-conditional. Preferred one-hour loss and 24-hour usable recovery after
discovery need actual mechanism/measurement evidence; daily copies alone prove neither one-hour loss nor thirty-day
PITR. A concrete shortfall returns to John for acceptance. Do not fabricate thirty-day observed history or reduce
security/diagnosis to fit the combined tax-inclusive recurring preference. Temporary recovery costs remain separate.

Immediately before allocation, checked live/archive TASK filenames across eighteen local/cached refs and nine
registered worktrees, including untracked records; highest observed was TASK-00528. TASK-00529/00530 are local
allocations, not a global reservation. The ignored `.runs/notes/recovery-decomposition/before.json` receipt captures
783 planning Markdown files before this step. No existing TASK metadata/status/order/dependency amendment is proposed
or applied; retain EPIC-00020 priority, TASK-00510–00512 and every actual qualification hold.

This TICKET's accepted scope is fully decomposed, not implemented or operationally accepted. TICKET-00125's subsequent
TASK-00532/00533 acceptance completes this selected map's TASK-planning phase. Work remains uncommitted/unpublished;
no backup, account/vault/key access, restore,
provisioning, spending, activation or cleanup operation follows from decomposition.

#### Planning verification

`./bin/planning-check --write` and `./bin/planning-check` passed: **675 records, 519 active**, generated views current.
`git diff --check` and changed/untracked scope/whitespace checks passed across **68 planning paths** including earlier
uncommitted work; 4,101 relative link targets were checked.

Against the 783-file pre-edit baseline, exactly fifteen existing handoff/generated paths changed and TASK-00529/00530
were added; no prior file was removed. Every pre-existing frontmatter block and TASK file remains byte-identical,
including all statuses/orders/dependencies, TASK-00510–00512 and actual qualification holds. EPIC-00020 remains
unchanged. Verified exact new parent/kind/status/blank-order/blank-PR metadata and blocker lists; the complete TASK
graph is acyclic. TASK-00529 has no production or completed production-mail prerequisite; TASK-00530 inherits the
actual production target through TASK-00528 without a redundant direct edge.

These are planning checks, not application or recovery acceptance. No full build, independent Review/QA, cloud/vault/
credential access, backup/upload, restoration, resource purchase, deployment, data rewind or cleanup occurred. No
commit or push occurred. Earlier lifecycle/artifact/environment/mail receipts remain historical to their own steps.

Next: `/skill:next` for read-only Board-based selection after completed selected-map planning. No automatic implementation.
