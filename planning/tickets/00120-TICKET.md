---
id: TICKET-00120
epic: EPIC-00020
title: Align the nine active projects with current delivery handoffs
status: ready-for-agent
---

# Align the nine active projects with current delivery handoffs

## Problem statement

A shared Land/routing contract cannot prevent stale handoffs where project-local planning scripts and
conventions still implement different ownership or validation behavior. Maintainers need a verified alignment
disposition for each approved project, without nine duplicate planning queues or unrelated adoption work.

## Approved amendment — 2026-10-26

John approved the [TASK-00519 amendment](../tasks/00519-TASK.md#approved-amendment--2026-10-26): adopt Common's
read-only resolution of exact merged/integrated `done` handoffs and idempotent retirement during authorized
Work/Land/planning intake, without routine correction prompts. Bind publication to each live default branch,
reconcile authored and generated documentation, and qualify TASK-00534/00537's resource cleanup contract through
each local workflow binding. Nine active projects remain; deprecated CMS is excluded. Existing accepted receipts
remain history, and new behavior requires its own per-project evidence. Isolated worktrees are selected.

## Solution and boundaries

Apply the approved contracts from [TICKET-00118](00118-TICKET.md) and [TICKET-00119](00119-TICKET.md) to exactly:

| Project | Required disposition |
|---|---|
| `fight-agent-os` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `fight-common` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `fight-access-control` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `epic` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `project-slim` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `project-symfony` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `project-laravel` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `project-yii` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |
| `project-codeigniter` | Verified alignment, evidenced no-change conclusion, or explicit unresolved blocker |

Resolve each real checkout and inspect its local instructions, workflow bindings, planning-check scripts,
current-handoff representation and `planning/CONVENTIONS.md`. Determine the minimum changes needed for Land
ownership, explicit current delivery identity, offline ordinary validation, bounded live checks and accurate
current Board/Roadmap/README handoffs. Record absent capabilities as gaps, not presumed equivalence.

Change necessary scripts and documentation only. Reuse adequate existing safeguards and preserve project-specific
structures and independent planning/execution order. A project whose existing behavior satisfies the contract
needs an evidenced no-change conclusion, not a cosmetic rewrite. Fight Common's integrated-delivery behavior is the approved reference to qualify; it is not an exemption
from direct evidence or target-specific ownership alignment.

Use an isolated registered worktree for every affected project, following its local branch, verification and
delivery conventions. Preserve human checkouts, unrelated changes and other worktrees. Resolve checkout paths,
ownership and mutation/publication authority before acting; this planning approval does not create worktrees
or publish changes. Project execution can proceed independently once the two shared contracts are settled.

Keep coordination and later implementation planning in Fight Agent OS. Do not add per-project TASK records or
separate project-local backlogs for this rollout. Each project's evidence must identify its exact subject,
changes or no-change rationale, checks, limits, acceptance and delivery state. A blocked or unchecked project
is not counted as aligned; report remaining coverage without claiming rollout completion.

Out of scope: general framework/project adoption, application behavior changes, historical-evidence rewriting,
unrelated cleanup, extra projects, automatic merge, release/deployment and managed database Workflow work.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Assess one named project | Read-only repository/tool inspection | Local instructions, owning scripts, conventions, workflow bindings and exact revision | Assessment evidence only | Record concrete gaps, a verified no-change conclusion or a blocker; no project TASK creation |
| Align an affected project | Authorized edits in an isolated registered worktree; project-owned planning tools | Qualified shared contracts and project-local behavior | Local verification/coordination evidence, not Domain events | Minimal script/document changes with consistent current handoffs and preserved historical evidence |
| Verify and hand off the result | Direct tooling probes, applicable project gates and authorized delivery workflow | Exact changed tree, offline/live results, independent reports and publication state | Existing review/delivery receipts only | Honest per-project disposition and next action; no assumed acceptance or merge |
| Resume partial alignment | Existing owning workflow for the exact project/subject | Retained worktree/evidence and current local/remote state | Local recovery evidence only | Continue only unfinished owned work without overwriting unrelated changes or duplicating delivery |

These operations introduce no application Commands, Queries, permissions, Domain events or database records.

## Validation and permissions

Respect each repository's governing instructions and required gates. Inspect source ownership before modifying
or generating views; confirm exact registered worktree identity before executing mutations. Isolated worktrees
protect unrelated working copies but are not an OS security boundary or additional publication authority.

Prove the local/offline behavior separately from the bounded live-delivery behavior. Live checks use existing
operator credentials without disclosure; missing access is an explicit verification blocker, not a skipped pass.
Keep independent review/QA and human merge separate from builder verification and project alignment status.

Use direct tooling/workflow probes and each project's applicable canonical full gate, not new product-suite
tests of planning scripts or documentation. Surface warnings and incomplete coverage. Existing historical
receipts may explain provenance but cannot replace fresh evidence for the changed project tree.

## Acceptance and evidence

- All nine active projects have a traceable assessment against both shared contracts, with exact checkout/revision,
  inspected ownership/binding, identified gaps and an explicit outcome. Unassessed projects are visible.
- Necessary script/convention/current-handoff changes are minimal and consistent with Land ownership and
  read-only routing. Already-compliant projects have direct evidence supporting a no-change conclusion.
- Changed projects preserve offline ordinary validation, bounded read-only live verification and distinct
  open/merged/closed-unmerged/unverified outcomes. Current handoffs agree with their authoritative sources;
  historical acceptance and completion checkpoints remain intact.
- Each affected project uses an isolated registered worktree with documented ownership. No per-project TASKs,
  unrelated repairs or human-checkout mutations are introduced.
- Evidence records project-owned checks and full-gate results, warnings, independent acceptance, publication
  state and remaining human actions separately. Blockers or incomplete live verification prevent a false
  all-projects-aligned claim; partial progress can be resumed from the retained evidence.
- Verification and no-change evidence remain traceable from the central owning work without copying private
  credentials or creating an application runtime dependency on ignored execution scratch.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00519](../tasks/00519-TASK.md) | Adopt integrated delivery reconciliation across the nine active projects | in-progress |
<!-- /planning:children -->

## Decisions and progress

Approved as the third requirement area in the maintainer-accepted three-TICKET split of
[EPIC-00020](../epics/00020-EPIC.md). Consumes TICKET-00118's Land ownership/correction contract and
TICKET-00119's identity, validation and routing contract. This is one cross-project alignment requirement,
not one TICKET or TASK per project. The approved central implementation slice is recorded below.

No project is claimed aligned by this planning record. The 2026-10-26 amendment has a selected isolated
Agent OS checkout; fleet implementation and verification are still separate from that planning change.
The existing application ADRs do not need amendment for this tooling/documentation-only requirement; a discovered
need for application behavior would exceed scope and require a separate decision.

### Approved TASK decomposition and current handoff

The maintainer approved one central [TASK-00519 — Adopt integrated delivery reconciliation across the nine active projects](../tasks/00519-TASK.md),
blocked by TASK-00518, which transitively consumes the preceding shared-contract work. It owns assessment,
minimal alignment, verification and resumable coverage for all nine active projects. Do not create project-local,
assessment-only or verification-only TASKs.

The approved multi-repository exception permits separate independently reviewable project-owned PRs under this
central TASK. Project-level assignments and evidence remain under its ignored scratch; the central record
retains complete coverage responsibility and exact evidence/PR references. Acceptance, publication and human
merge remain distinct. Unchecked or blocked projects prevent an all-projects-aligned claim and TASK acceptance.
This exception grants no additional publication or merge authority.

TASK decomposition is accepted and complete for this TICKET and completes EPIC-00020's planning phases. The
maintainer subsequently made EPIC-00020 the top priority: TASK-00515–00519 all use order 1, preserving blockers
and unrelated work's relative order. No implementation or project alignment is claimed.

Next: `/skill:next` from the selected isolated planning checkout for read-only Board-based execution selection.
Execution still requires an explicit workflow invocation and the approved isolated-worktree approach.
