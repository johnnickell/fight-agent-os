---
id: TICKET-00119
epic: EPIC-00020
title: Route delivery handoffs using verified current state
status: ready-for-agent
---

# Route delivery handoffs using verified current state

## Problem statement

Read-only routing can repeat an obsolete human-merge instruction when it treats authored handoff prose,
completion status or a historical PR URL as current delivery evidence. Maintainers need a bounded live-state
check that distinguishes ended handoffs from unavailable evidence without making ordinary planning validation
network-dependent or giving routing mutation authority.

## Solution and boundaries

Define an explicit current-handoff identity linked to its authoritative subject and exact PR. Historical prose,
`done` and an unmarked PR reference do not independently create a live delivery claim. Generated current views
must derive from the owning records; authored current handoffs must agree with or point to that authority.
Retain distinct planning/decomposition handoffs rather than replacing them with delivery state.

Use Fight Common's existing `delivery: awaiting-merge`, generated delivery block and explicit
`./bin/planning-check --check-delivery` convention as reference inputs. Qualify their actual implementation
before reuse, document the supported representation and exact commands, and avoid demanding identical internal
scripts where a project already meets the contract. Shared guidance must bind correction ownership to Land,
not leave it with an unnamed planning owner.

Keep ordinary planning validation and generated-view refreshes offline and deterministic. Offline validation
checks current-handoff metadata, local identity consistency and derived-view freshness; it neither queries
hosting nor claims live delivery freshness. The explicit live operation is read-only and bounded to relevant
current delivery/merge handoffs. Document finite request/count limits and timeouts; do not scan all historical
PRs, fetch repository changes, inspect unrelated CI or perform a fleet audit on a routing request.

Require relevant live verification when Land or routing evaluates a delivery/merge handoff. Preserve exact
repository/PR identity and distinguish:

| Observation | Required route |
|---|---|
| Verified open PR | Report the actual current delivery/human action; open alone does not prove readiness, acceptance or CI success |
| Verified merged PR | Do not repeat the merge instruction; report merge evidence and the needed Land reconciliation |
| Verified closed-unmerged PR | Report non-delivery and hand correction to Land; any reopen/replacement decision remains explicitly owned |
| Identity mismatch or contradictory evidence | Stop the affected recommendation and name the authoritative identity/evidence needed |
| Missing CLI/authentication, inaccessible hosting or incomplete response | Report unverified state and the recovery action; infer neither open nor merged |

Routing never edits records, refreshes views or invokes Land automatically. The Land correction defined by
[TICKET-00118](00118-TICKET.md) retires ended current markers and reconciles documents. Once reconciled,
historical PR references are not repeatedly queried merely because they remain in preserved evidence.

Out of scope: automatic repairs, historical-text/PR scans, background monitoring, new hosted-CI requirements,
network-dependent ordinary builds, application APIs/events and managed database Workflow transitions.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Validate local planning | Ordinary project planning check | Authoritative local records, handoff identity and generated views | N/A — tooling only | Read-only result; stale or malformed local state is reported without hosted access |
| Refresh authorized current views | Existing explicit planning write command | Validated source records | N/A — tooling only | Derived views refreshed only within mutation authority, without live hosted checks |
| Verify a current handoff | Explicit bounded live-delivery check, consumed by Land/routing | Exact current PR identity and live state using existing operator access | Observation evidence, not Domain events | Read-only verified state or explicit incomplete result; no mutation or publication |
| Route an ended or unverifiable handoff | Existing read-only routing entry point | Live result, owning record and Land contract | N/A — recommendation only | Precise Land or evidence-recovery handoff; obsolete merge instruction is not repeated |

## Validation and permissions

Follow [lightweight routing](../CONVENTIONS.md#lightweight-work-routing),
[landing standards](../../docs/engineering/LANDING.md) and the parent
[EPIC's validation boundary](../epics/00020-EPIC.md#validation-boundary).
Live reads use existing operator access, without printing credentials or creating new grants. Local validation
must not require credentials. Keep live checking separate from write mode and make error/incomplete outcomes
observable rather than silently falling back to a fresh-pass claim.

Preserve authoritative Board ordering, active work, unrelated ready work and planning frontiers. A delivery
observation is neither implementation acceptance nor authorization to execute the next task. Ambiguity blocks
the affected recommendation rather than granting permission to rewrite history or audit unrelated projects.

Exercise actual tooling and workflow behavior directly under ignored execution scratch. Do not add
product-suite tests of planning files, wrappers, validator machinery or seeded quality-tool failures. No
application permission, database record or Domain event is introduced.

## Acceptance and evidence

- Current handoffs have an explicit, documented identity and authority; historical completion prose and
  unmarked PR references cannot silently become current merge instructions. Supported command shapes and live
  bounds are documented alongside their failure and recovery behavior.
- Ordinary check and write paths remain independent of live hosting access. Read-only paths leave tracked
  files unchanged, and only explicitly authorized write operations refresh views.
- Direct bounded probes cover open, merged, closed-unmerged, identity mismatch, malformed/incomplete evidence,
  missing authentication and unavailable hosting, with distinct truthful outcomes and exact next actions.
- Routing never repeats a verified ended merge handoff, guesses state on failure, modifies tracked files or
  dispatches Land. Land receives the relevant identity/state evidence under TICKET-00118's correction contract.
- Properly reconciled historical work does not trigger recurring live queries or block routing solely because
  its historical PR/checkpoint remains recorded. Planning and execution priority retain their existing owners.
- Evidence distinguishes reference inspection, local/direct verification and actual live observations. A
  reported historical gate or a stubbed provider probe is not relabelled as fresh live integration evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00517](../tasks/00517-TASK.md) | Represent and validate current delivery handoffs offline | done |
| [TASK-00518](../tasks/00518-TASK.md) | Verify live handoffs and route corrections to Land | in-progress |
<!-- /planning:children -->

## Decisions and progress

Approved as the second requirement area in the maintainer-accepted three-TICKET split of
[EPIC-00020](../epics/00020-EPIC.md). Depends on TICKET-00118's ownership/reconciliation contract;
[TICKET-00120](00120-TICKET.md) consumes both for project-local alignment. The accepted TASK split below records
the concrete consumer dependencies.

Planning discovery inspected Fight Common's `planning/CONVENTIONS.md` current-delivery section and
TASK-00176's scope/work checkpoint. Those documents describe explicit markers, bounded read-only live checking,
offline validation and a planning-owner correction handoff. They are reference evidence, not independent
acceptance or a compatibility guarantee for the ten projects; Land ownership must be aligned explicitly.

No application ADR change is needed. Implementation must qualify the concrete command interfaces and limits
against the shared workflow without introducing new permission or managed-runtime scope.

### Approved TASK decomposition and current handoff

The maintainer approved the complete two-TASK split:

| TASK | Delivered outcome | Blockers |
|---|---|---|
| [TASK-00517 — Represent and validate current delivery handoffs offline](../tasks/00517-TASK.md) | Explicit current identity and consistent Agent OS reference views without network-dependent ordinary validation | TASK-00515 |
| [TASK-00518 — Verify live handoffs and route corrections to Land](../tasks/00518-TASK.md) | Bounded live verification and shared Land/routing integration with truthful state-specific handoffs | TASK-00517, TASK-00516 |

Both were initially unranked chores in Fight Agent OS, not per-project rollout TASKs. The maintainer
subsequently made EPIC-00020 the top priority; both now use order 1 with their blockers preserved. The reference implementation and
shared guidance belong here; TICKET-00120 assesses all ten projects and implements only remaining local deltas,
including an evidenced Agent OS disposition without duplicating these changes.

TASK decomposition is accepted and complete for this TICKET. Planning remains on the selected isolated
`feature/epic-00020-delivery-handoffs` branch; no implementation, execution verification, independent acceptance
or publication is claimed. The later priority approval does not authorize execution or waive readiness holds.

TICKET-00120 subsequently received its approved central TASK-00519, completing EPIC-00020's planning phases.
All five child TASKs use top priority order 1, with blockers and unrelated work's relative order preserved.

Next: `/skill:next` from this isolated checkout for read-only Board-based execution selection. No implementation
starts from planning or priority approval alone.
