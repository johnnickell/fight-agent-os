---
id: TICKET-00118
epic: EPIC-00020
title: Reconcile current delivery handoffs through Land
status: ready-for-agent
---

# Reconcile current delivery handoffs through Land

## Problem statement

Land currently refreshes parent statuses and generated views, but does not explicitly reconcile every authored
Board, Roadmap and README handoff. Historical delivery-pending checkpoints can consequently be mistaken for
current instructions, and a later human merge can leave an obsolete handoff behind. Maintainers need Land to
own these corrections without rewriting independent acceptance evidence or granting automatic merge authority.

## Solution and boundaries

Extend the shared Land contract to reconcile applicable current routing and documentation for its exact
subject during authorized landing and when resumed after an ended merge handoff. Inventory the subject's
current handoffs, reconcile their authoritative sources, refresh derived views and report any unresolved
contradiction. Do not substitute generated-view freshness for inspection of authored current instructions.

Keep historical review/QA reports, accepted-candidate provenance, completion notes and delivery-pending
checkpoints intact and identifiable as history. Current instructions must be distinguishable from those
checkpoints. Acceptance, publication, delivery verification, human merge and later reconciliation remain
separate facts; neither `done` nor a PR URL proves merge.

Define a bounded post-merge or post-closure correction/publication route, including resumption when the original
feature worktree has been cleaned up. Use a safely selected checkout and authorized branch/publication path;
never assume a merged PR remains writable or repurpose a human checkout. Inspect an existing corrective change
before retrying so interruption does not cause duplicate publication. Repeating reconciliation against unchanged,
already-consistent state must not manufacture another correction commit or alter acceptance.

For a verified merge, retire the ended merge instruction and reconcile the relevant current routing. For a
verified closed-unmerged PR, remove the false merge instruction but retain the actual non-delivery and name the
owner decision needed; do not mark it merged, reopen implementation automatically or invent a replacement PR.
Unverified identity or state blocks the corresponding correction until authoritative evidence is recovered.

This extends, rather than reopens, completed [TICKET-00004](00004-TICKET.md) and preserves local coordination
boundaries in [TICKET-00084](00084-TICKET.md). [TICKET-00119](00119-TICKET.md) supplies the current-state validation
and read-only routing contract. [TICKET-00120](00120-TICKET.md) owns project-local alignment.

Out of scope: implementation defect repair, new independent-review machinery, rewritten historical evidence,
automatic merge/approval, release/deployment, broad cleanup and the managed database Workflow of
[EPIC-00008](../epics/00008-EPIC.md).

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Synchronize an authorized landing | Existing `land` entry point; authorized edits and planning refresh | Subject, current authored handoffs, source records, generated views and verified delivery evidence | N/A — local workflow, no Domain events | Current documents agree with the actual landing stage; historical checkpoints remain intact |
| Reconcile a later merge or closure | Explicit Land resume and bounded corrective publication within authority | Exact PR identity/state, current checkout, existing correction and acceptance provenance | Local reconciliation receipt only | Ended handoff is retired truthfully; any remaining human action is explicit |
| Resume after cleanup or interruption | Land recovery under the documented checkout/publication procedure | Worktree ownership, current base, remote correction state and unchanged-source checks | Local recovery evidence only | Safe correction or precise hold; no duplicate PR, destructive checkout reuse or fabricated acceptance |

These are tool and skill operations, not new application Commands, Queries or database records.

## Validation and permissions

Follow [landing standards](../../docs/engineering/LANDING.md),
[planning conventions](../CONVENTIONS.md) and [handoff rules](../../docs/engineering/HANDOFFS.md).
Land may mutate only within the operator's existing subject, checkout and publication authority. Routing merely
recommends Land; observing a merge does not invoke it or authorize new effects. Preserve unrelated work and
historical review/QA ownership. Stop for missing authority, ambiguous identity, unsafe checkout state or a change
that would alter requirements or implementation.

Reconciliation must preserve existing accepted-candidate and administrative-closeout rules. Current wording
cannot promise final-head delivery success before verification. Do not create a recurring metadata-commit loop
to turn historical checkpoints into a live run ledger. Missing proof remains visible and final delivery checks
remain applicable to the actual published candidate.

Validate instruction links, normal/resume paths, evidence preservation and correction limits directly. Use the
owning planning tools, applicable full gates and independent review/QA; do not add product-suite tests of skills,
Markdown or planning machinery. No application permission or schema change is needed.

## Acceptance and evidence

- Shared Land instructions and their owning standards explicitly cover all applicable authored current
  Board/Roadmap/README handoffs and derived planning views, not just parent status rollups.
- A bounded normal-landing walkthrough identifies current handoffs, synchronizes them to the actual stage and
  leaves historical acceptance and delivery checkpoints unmodified and clearly historical.
- Verified merged and closed-unmerged examples produce distinct current instructions without false delivery
  claims, acceptance toggles or automatic merge. Unavailable or contradictory evidence produces a precise hold.
- A documented correction/publication path works after original-worktree cleanup and handles interruption or
  an already-existing correction without duplicate publication. Checkout and effect authority remain explicit.
- Repeated reconciliation of unchanged, consistent state needs no further correction commit; no step rewrites
  an independent report or requires a new review merely to reinterpret historical delivery evidence.
- Evidence records the subject, inspected sources, live observation, changed current documents, preserved
  historical material, verification, publication state and remaining human actions. Planning approval is not
  implementation evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00515](../tasks/00515-TASK.md) | Synchronize current handoffs during Land publication | done |
| [TASK-00516](../tasks/00516-TASK.md) | Resume Land to reconcile ended merge handoffs | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as the first requirement area in the maintainer-accepted three-TICKET split of
[EPIC-00020](../epics/00020-EPIC.md). This TICKET establishes the shared ownership and reconciliation contract;
TICKET-00119 consumes it, and TICKET-00120 aligns the ten named projects against both contracts. This sequencing
does not create TASK blocker metadata or authorize implementation.

No application ADR amendment, application event or database migration is required: this is the current
Markdown-authoritative shared workflow, not the managed runtime. Exact corrective command/branch mechanics
must be qualified during implementation without broadening the approved authority boundary.

### Approved TASK decomposition and current handoff

The maintainer approved the complete two-TASK split:

| TASK | Delivered outcome | Blockers |
|---|---|---|
| [TASK-00515 — Synchronize current handoffs during Land publication](../tasks/00515-TASK.md) | Normal landing reconciles authored current handoffs and generated views, preserving historical acceptance and delivery checkpoints | None |
| [TASK-00516 — Resume Land to reconcile ended merge handoffs](../tasks/00516-TASK.md) | Verified merge/closure correction, safe recovery after cleanup or interruption, and no duplicate publication or no-op commits | TASK-00515 |

Both were initially unranked chores in Fight Agent OS, not per-project rollout records. The maintainer
subsequently made EPIC-00020 the top priority; both now use order 1 with their dependency preserved. IDs were checked against live and
archived records and registered worktrees; TASK-00514 was already allocated elsewhere and was not reused.
The split is accepted and complete for this TICKET. No implementation, execution checks, review/QA, publication
or merge is claimed; the later explicit priority decision does not authorize execution.

Planning records remain on `feature/epic-00020-delivery-handoffs` in the selected isolated planning worktree.
TICKET-00119 subsequently received its approved TASK-00517/TASK-00518 split and TICKET-00120 its approved
central TASK-00519. All EPIC-00020 planning phases are complete; TASK-00515–00519 share top priority order 1,
with blockers and unrelated work's relative order preserved.

Next: `/skill:next` from this isolated checkout for read-only Board-based selection. Do not start TASK-00515
merely because planning and priority decisions are complete.
