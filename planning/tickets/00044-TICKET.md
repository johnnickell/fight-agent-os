---
id: TICKET-00044
epic: EPIC-00006
title: Assess change quality before review handoff
status: ready-for-agent
---

# Assess change quality before review handoff

## Problem statement

Engineers and reviewers need economical, consistent direction toward unnecessarily complex, poorly named or hard-
to-understand changes without confusing an automated score with correctness, review acceptance or a merge gate.

## Solution and boundaries

Implement the advisory change-quality assessment accepted in
[WF-031](../wayfinder/tickets/WF-031-define-general-purpose-bounded-judgments.md), using
[TICKET-00042](00042-TICKET.md)'s shared typed judgment capability. Integrate automatic assessment before Engineer
review handoff and focused reviewer follow-ups through governed Harness/skill paths. Instructions only activate
with qualified tools; they do not introduce managed review dispatch or publication authority.

- Use separate simplicity, naming clarity, understandability and semantic-convention dimensions. Maintain shared
  versioned score anchors across repositories, with local requirements/terminology/conventions as question context,
  not a changed scale. The initial proposed 1–5 scales need all anchors defined and qualified before use. No overall
  quality percentage, uncalibrated correctness claim or automatic acceptance cutoff.
- Inventory every eligible maintained changed unit at exact base/head and assess in bounded batches, including
  maintained tests and applicable configuration. Exclude generated output and unmodified third-party code;
  assess maintained generators and deliberate vendor patches where applicable. A dependency import does not make
  upstream code owned. Report exclusions without waiving other review/security obligations.
- Bind results to exact inputs, requirements/conventions, rubric/evaluator and policy versions. Return preconfigured
  choices/scores, authorized candidate references and explicit not-applicable, missing-context and incomplete
  outcomes. Jev cannot invent free-form reasons, quotations or locations. Do not score N/A as bad or perfect.
- Reassess affected units; reuse unaffected results only if evidence, question/context, requirements/conventions,
  evaluator/policy and current access match. Unchanged text alone is insufficient. Rebuild current coverage with
  original provenance/time and a reuse label; removed units no longer count. Do not silently raise budgets to
  claim full coverage or repeatedly sample until a preferred score appears.
- Let independent reviewers ask focused follow-up questions within their own permissions/session to sharpen
  inspection. They inspect exact source/evidence before asserting findings. Private Engineer context, Jev output
  or an unchanged score cannot substitute for fresh independent review or transfer acceptance.
- On unavailable Jev, invalid results or exhausted budget, report assessment/coverage gaps and allow normal review
  handoff without a special waiver solely for this advisor. Existing required gates and verified finding severity
  still apply. Any later automatic score gate needs separately accepted policy and qualification.

[TICKET-00045](00045-TICKET.md) separately owns PHPUnit usefulness criteria and whole-suite review, sharing the
assessment capability and compatible coverage/reuse conventions without duplicating this policy. Exclude mechanical
lint/style rule replacement, automatic code repair, formal acceptance, automatic publication and score-based gates.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Assess an Engineer handoff | Start bounded assessment for exact review subject | Eligible diff units, repository conventions and per-dimension judgments | Assessment completed/partial/unavailable | Authorized reads/provider calls and advisory report; no source mutation |
| Update an assessment after changes | Reassess affected units | Current subject, reuse eligibility and coverage | New/reused outcome observations | Current report preserving original provenance of reused judgments |
| Investigate as reviewer | N/A: inquiry does not mutate source or accept work | Focused typed questions and separate exact-source inspection | Inquiry observation; verified findings use existing review process | Authorized analysis under reviewer identity, no private Engineer-context transfer |
| Proceed with coverage gaps | Existing review handoff, not a new waiver | Assessment availability and unaffected required gates | Explicit gap/handoff facts | Review may proceed; mandatory checks and findings remain authoritative |

## Validation and permissions

Apply shared source/egress permissions, spend and operation limits; access to a review subject does not grant broad
repository/production access. Govern rubric versions and preserve input identity. Reports/observations exclude raw
source/transcripts/credentials by default and expose evidence references only within current authority. Treat stale,
invalid and partial assessment as such, not a pass. Formal findings remain under existing review severity and evidence
rules; deterministic checks retain their own receipts rather than being inferred from a model score.

## Acceptance and evidence

- Demonstrate automatic pre-handoff assessment of all eligible units or explicit gaps, including tests/configuration,
  generated/third-party exclusions, maintained generator/vendor-patch cases and not-applicable dimensions.
- Qualify all shared scale anchors with representative changes and repository-specific context; measure false flags,
  missed concerns, reviewer usefulness, cost/latency and coverage rather than claiming universal score comparability.
- Verify base/head binding, changed requirements/conventions invalidating otherwise unchanged text, affected-unit
  reassessment, authorized reuse and removal of deleted units from coverage.
- Demonstrate independent reviewer questions followed by exact-source verification, without inherited private
  context, fabricated locations or automatic acceptance. A high score does not erase an independently found defect.
- Exercise unavailable/invalid/budget-exhausted outcomes: handoff carries gaps while ordinary review and required
  gates remain enforced. Test owned assessment/reporting behavior, not model prompt text or quality-tool wiring.

## Sequencing and TASK readiness

Consume TICKET-00042's typed evaluation and source/result contracts and existing Engineer/reviewer handoff seams.
Static change assessment does not depend on TICKET-00043's production SQL route. Share reusable subject/coverage/
provenance mechanics with TICKET-00045 where justified; no whole-ticket dependency merely to reuse a helper.
At TASK decomposition, pin scale anchors, unit mapping, governed preset delivery, supported handoff hooks and limits.
Unsupported automatic-hook coverage is a qualification gap, not a claim that prompting provides enforcement.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00178](../tasks/00178-TASK.md) | Assess change quality automatically before Engineer handoff | ready-for-agent |
| [TASK-00179](../tasks/00179-TASK.md) | Refresh assessments after changes with qualified reuse | ready-for-agent |
| [TASK-00180](../tasks/00180-TASK.md) | Support independent reviewer follow-up judgments | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this split on 2026-10-08 as part of EPIC-00006's Jev follow-on. The WF-031 assessment and reuse
contracts are accepted; implementation, automatic integration and empirical rubric qualification remain future work.
That TICKET decomposition performed no review acceptance, runtime activation, provider call, TASK allocation or publication.


### Approved TASK decomposition — 2026-10-09

John approved three independently reviewable outcomes targeting **Pi 1.1.0**:

- [TASK-00178](../tasks/00178-TASK.md): automatic pre-handoff change-quality assessment, blocked by TASK-00172.
  Own exact base/head inventory, maintained-unit mapping, all four complete initial 1–5 rubric anchors, bounded
  assessment/reporting and actual governed Engineer handoff integration. Unsupported hook coverage is not
  prompt-enforced automation; required live integration qualification remains explicit.
- [TASK-00179](../tasks/00179-TASK.md): current-subject refresh and qualified reuse, blocked by TASK-00178/TASK-00173.
  Reassess affected context, invalidate unverifiable matches and rebuild coverage without removed units while
  retaining original evaluation provenance on reused answers.
- [TASK-00180](../tasks/00180-TASK.md): independent reviewer follow-ups, blocked by TASK-00178. Use the reviewer's
  own identity and permissions, ask focused typed questions and inspect exact source before asserting findings.

Refresh/reuse and reviewer follow-up may proceed independently after their actual blockers. All three remain
unranked and preserve existing TASK scope and executable Board priority. No production SQL, application inspection
or compaction dependency is introduced; TICKET-00045 owns PHPUnit usefulness separately.

Scores remain advisory with explicit N/A, unknown and incomplete outcomes. All eligible units are assessed or
accounted for as gaps, and unavailable/budget-exhausted Jev does not create a special handoff waiver. Required gates,
independence, verified finding severity and existing review acceptance remain authoritative. This approval creates
planning records only; empirical rubric/hook qualification, implementation, publication and runtime activation remain pending.

Next: `/skill:to-tasks TICKET-00045`
