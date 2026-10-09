---
id: TICKET-00040
epic: EPIC-00006
title: Preserve context through verified compaction and resume
status: ready-for-agent
---

# Preserve context through verified compaction and resume

## Problem statement

Agents need to continue useful work after compaction without losing constraints, human questions or outstanding
effects, reloading every source, or treating a summary as new authority. Required recovery state must survive
interrupted creation and remain private to the authorized conversation and role.

## Solution and boundaries

Implement the checkpoint and resume contract accepted in
[WF-030](../wayfinder/tickets/WF-030-define-context-retention-and-resume-contract.md) and the
[Jev follow-on amendment](../epics/00006-EPIC.md#jev-compaction-and-judgment-workflows--2026-10-08).
This TICKET owns what survives and how continuation is validated; [TICKET-00041](00041-TICKET.md) owns timing,
notifications and lifecycle observation inspection. Both consume one checkpoint contract without a circular
implementation prerequisite: this capability emits checkpoint/resume observations even before timing automation.

- Automatically prepare a versioned protected checkpoint bound to exact Pi/conversation lineage, source boundary,
  assignment, role and context/policy versions. The Agent drafts semantic facts; Harness checks authoritative
  identity, approvals, operation state, required fields, references and declared gaps. Field presence does not
  establish semantic completeness. Pi owns resumable model context; auxiliary facts use protected session-linked
  storage, not tracked files, ordinary telemetry or a new Planning database.
- Keep objective/assignment, accepted decisions and constraints, completed/remaining work, pending questions,
  uncertain effects, source revisions/evidence references and next action directly available. Separate facts,
  proposals and unknowns; retain supporting material behind scoped references.
- Use [TICKET-00037](00037-TICKET.md) to classify optional context as helpful now, possibly useful later or
  unnecessary for this continuation, then rank within categories. Retain helpful material first, then later-useful
  material if room permits; unknown is not unnecessary. Jev selects caller-defined categories/ranks, not prose.
- Never discard required facts to fit a budget. Remove optional context first; permit bounded additional space
  only within qualified profile limits while reserving instructions and the next step. If necessary facts still
  cannot fit, preserve state and pause. Do not silently switch models.
- If optional selection fails, use qualified native Pi summarization of older context and recent retention while
  preserving this contract. Native fallback can require model access and cost. Recover missing facts through
  bounded authorized reads, repair and validation; exhaustion or unresolved conflicts requires intervention.
- On resume, apply [TICKET-00039](00039-TICKET.md) before re-ingesting supporting sources: ask a relevance question,
  use its initial 0.70 positive-read bar and retrieve minimal useful ranges. Preserve its mandatory instruction,
  selected-skill, evidence-backed known-target and required-evidence exceptions with recorded grounds. A checkpoint
  reference alone is not a known-target exception; no bulk reloads. Account for Jev's own source-read cost.
- Persist and validate a complete candidate before activation; retain old recoverable state until activation
  succeeds. Corrections create attributable revisions. Missing, corrupt or purged bytes remain explicit and
  metadata alone does not establish recoverability.
- Verify integrity/binding, governing instructions/skills, live permissions, applicable assignment/claim/lease,
  evidence subjects and outstanding operations. Meaningful governing instruction/policy drift uses WF-009's linked
  continuation; ordinary supporting-source changes may be reconciled within existing scope using minimal reads
  and new provenance. Conflicting scope/approval/evidence requires intervention, not transferred acceptance.
- Preserve only actual valid approvals within unchanged scope, expiry and consumption constraints. Reconcile
  unknown consumption, billing, effects and concurrent human answers before dependent action. Keep unanswered
  questions and their exact identities/content/context pending. Never replay uncertain effects automatically.
- Separate Agent/sub-agent private checkpoints and authorized parent handoffs; independent reviewers retain fresh
  conversations. Follow WF-017 retention: preserve active/paused/recoverable/needs-human state, then configurable
  terminal grace initially 30 days and applicable holds. Audit history keeps its existing categories; prohibited
  sensitive content remains prohibited despite a hold.

Exclude memory promotion/ownership, timing tiers, managed Workflow authority creation, automatic provider changes
and cleanup during planning. Support qualified local and managed capabilities honestly; no invented managed identity.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Prepare recoverable context | Prepare, validate and activate checkpoint | Current required facts, optional relevance/rank, source and operation state | Candidate prepared/validated/activated or failed | Protected checkpoint revision and bounded authorized model calls |
| Resume or repair continuation | Validate resume; repair within bounds; pause on failure | Integrity, current authority, outstanding effects/questions, screened sources | Resume validated/resumed/blocked; repair outcome | Authorized minimal reads and activation only after validation |
| Reconcile changed evidence | Reconcile supporting-source revisions | Current source identity and applicability | Reconciliation recorded or intervention required | New attributable provenance; no broadened approval |
| Retain or remove eligible context | Apply existing authorized retention policy | Session terminal state, holds, retained content availability | Retention/deletion outcome under owning policy | Protected storage changes only under current retention authority |

Names are semantic operations, not mandated wire methods. Checkpoint observations include required-field results,
optional selection identifiers, versions/digests and context/usage facts for TICKET-00041, never segment bodies.

## Validation and permissions

Check source access and provider disclosure separately, including selection and recovery. Bound payload, time,
attempts, concurrency and spending; invalid/unknown answers cannot discard required facts. Keep protected references
access-controlled and credentials/transcripts/checkpoint prose out of ordinary logs. Observations cannot authorize
resume or substitute for durable recovery state. Missing telemetry alone is distinct from checkpoint failure.
Respect cancellation and stale responses across reload; no fallback may manufacture authority or replay effects.

## Acceptance and evidence

- Trace required facts, question identities and operation/approval state through compaction and validated resume,
  including concurrent replies, revoked authority, expired/consumed approvals and changed evidence/instructions.
- Demonstrate interrupted/corrupt candidate creation and activation preserve a usable prior state or explicit pause;
  demonstrate bounded repair, overflow handling and qualified native fallback when Jev is unavailable.
- Demonstrate selective question-first retrieval, justified exceptions and no loss of required evidence to an
  unavailable/false-negative relevance answer. Compare recall, context size, repeat reads and total cost/latency.
- Verify actual supported Pi storage/hooks, cancellation/reload, parent-child privacy, independent review and
  authorized retention/holds. Record native fallback prerequisites and unsupported capabilities honestly.
- Verify sanitized attributable observations with missing/duplicate delivery; test owned continuation behavior,
  qualify infrastructure with its owning tools and run applicable canonical gates at implementation.

## Sequencing and TASK readiness

Consume the needed evaluator/setup and source-screening slices of TICKET-00037/00039. This can advance independently
of the general-purpose tool and need not await the entire browser, memory or sandbox EPICs. TICKET-00041 consumes
this validated contract. At TASK decomposition, settle native/companion storage, schema/rank encoding, supported
Pi lifecycle and repair/resource limits; unqualified necessary guarantees block the affected TASK, not unrelated work.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00164](../tasks/00164-TASK.md) | Preserve required context through compaction and validated resume | ready-for-agent |
| [TASK-00165](../tasks/00165-TASK.md) | Select optional retained context with Jev | ready-for-agent |
| [TASK-00166](../tasks/00166-TASK.md) | Recover missing context through selective source reads | ready-for-agent |
| [TASK-00167](../tasks/00167-TASK.md) | Enforce checkpoint retention and recovery holds | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement split on 2026-10-08 for the selected Jev follow-on scope. This record is ready for
TASK decomposition, not implementation acceptance. WF-030's full accepted contract remains authoritative input;
WF-009/WF-017 retain instruction, conversation and retention ownership. No live compaction, cleanup, provider call,
TASK allocation or publication follows from this record. Existing six first-milestone TASKs and Board stay unchanged.

### Approved TASK decomposition — 2026-10-08

John approved four independently reviewable outcomes and explicitly confirmed **Pi 1.1.0** as the target runtime:

| TASK | Outcome | Required blockers |
|---|---|---|
| [TASK-00164](../tasks/00164-TASK.md) | Required checkpoint facts, protected native/companion activation and validated baseline resume | [TASK-00160](../tasks/00160-TASK.md), protecting configured checkpoint resources through supported Agent routes |
| [TASK-00165](../tasks/00165-TASK.md) | Jev optional-context categories/ranking, budget fit and qualified native fallback | TASK-00164 and [TASK-00159](../tasks/00159-TASK.md) |
| [TASK-00166](../tasks/00166-TASK.md) | Question-first source recovery and changed-support reconciliation with validated continuation | TASK-00164 and [TASK-00162](../tasks/00162-TASK.md) |
| [TASK-00167](../tasks/00167-TASK.md) | Terminal checkpoint retention, recovery holds and guarded deletion with honest availability | TASK-00164 |

Pi remains the model-context owner; additional required checkpoint state uses protected session-linked companion
storage under operator policy outside the repository. Use a versioned format, predefined optional categories and
bounded rank values with deterministic ties. First establish the safe baseline and pause on repair-required facts;
selection, repair and retention then proceed independently when their own blockers permit.

The accepted repair limit is one bounded repair pass per resume attempt, followed by validation or preserved pause.
Attempt identity/budget survives reload; further attempts require an explicit authorized retry rather than a hidden
loop. Existing evaluator and file-read ceilings stay binding. Context allowances derive from the effective profile
and reserve instructions plus the next action; implementation pins concrete checkpoint/segment/aggregate limits
before activation and must qualify them on Pi 1.1.0. No historical Pi 0.87.1 presentation receipt establishes
compaction behavior. Unqualified required guarantees prevent acceptance or activation of the affected route.

Deliver local sessions first with their actual permissions and supported child paths; do not invent managed grants,
claims or leases. Required checkpoint privacy/protection and resume observations belong to the baseline; lifecycle
inspection and timing remain with TICKET-00041. Existing sandbox authority is not weakened by local protection.
All four TASKs are unranked and leave existing TASK priorities/dependencies unchanged. Implementation, checkout
choice, runtime qualification, review and publication remain separate from this planning approval.

The selected Jev follow-on still needs TASK decomposition for TICKET-00041 through TICKET-00045. Older EPIC/map
planning retains its existing status. No implementation, live provider call, compaction or cleanup ran here.

Next: `/skill:to-tasks TICKET-00041`
