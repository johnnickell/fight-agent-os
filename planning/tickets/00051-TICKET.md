---
id: TICKET-00051
epic: EPIC-00012
title: Classify a selected TASK for routing
status: ready-for-agent
---

# Classify a selected TASK for routing

## Problem statement

A selected Ready TASK has an authored Planning kind but no bounded, explainable judgment about its work category
or relative priority for the future routing catalog. A second readiness gate or an Agent that silently rewrites
TASK metadata would make start less autonomous and create a competing Planning authority.

## Solution and boundaries

Introduce a distinct, permission-limited Triage team profile, not an Access Control Role. On **every** authorized
human-selected TASK, run a bounded, permission-filtered Jev classification against current TASK intent,
declared kind and relevant evidence. Return `feature`, `bugfix`, `chore` or explicit unknown/ambiguity, plus an
advisory `low`, `medium`, `high` or `urgent` priority band when available. Treat enhancement as feature and map
Planning `bug` to routing `bugfix`; these labels do not change Planning kinds or Board priority. Preserve typed
reasons, evidence, evaluator/rubric and subject revisions for the subsequent catalog decision and human view.

A usable Jev category that materially conflicts with the TASK kind and would change its Workflow route pauses
for human resolution. When Jev is unavailable or cannot classify, label a fallback to a current, supported
declared kind for a **normal route only**; priority becomes unknown and no hotfix is inferred. Missing,
unsupported or inconsistent kind stops rather than guessing. A human may separately request a diagnosis of an
incomplete TASK without making it executable. This requirement owns classification, not the route catalog,
start, claim, Repository branch configuration, or a release kind.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Classify a selected TASK | Authorized triage request; exact command name deferred | Current permission-filtered TASK kind, intent, context/eligibility and bounded evidence | Attributed recommendation/failure observation, **not** an eligibility or Workflow transition | One bounded Jev request and a revision-bound recommendation/receipt; no Planning mutation |
| Explain conflict or unavailable Jev | Record a human routing disposition when needed, without changing approved TASK requirements | Recommendation, declared kind, uncertainty and safe reasons | Human decision or fallback provenance recorded; no execution grant | Pause on material mismatch or return labeled normal-route fallback with unknown priority |
| Diagnose incomplete work explicitly | Authorized diagnostic request without start grant | Canonical exclusion reasons and available TASK facts | Diagnostic history only; no TASK lifecycle event | Explain missing facts without making an incomplete TASK executable |

These names are semantic descriptions, not preselected PHP types or another Planning state machine.

## Validation and permissions

The Triage Agent has only its own direct Permissions and approved source disclosure. Its template, prompt and
Jev output do not authorize work, change scope/priority, claim, grant, or resolve eligibility. Apply bounded
question/source/time/spend limits and validated typed outcomes; TASK text is untrusted. Tie each result and
human disposition to TASK, Planning/context/eligibility and rubric/evaluator revisions; stale judgments are
invalid. Separate denied input, stale facts, provider outage and honest `unknown` from deterministic Planning
ineligibility. Do not copy thresholds from unrelated Jev use cases or persist raw sensitive source text in
receipts. The recipient must revalidate current grants and eligibility before start.

## Acceptance and evidence

- A clearly declared TASK still causes a bounded Jev classification call; category and priority are advisory and
  never reorder the Board or silently edit kind, scope or lifecycle.
- A feature refined as enhancement remains feature; a material feature/bugfix mismatch surfaces the evidence
  and waits for a human rather than selecting a different Workflow automatically.
- Jev outage, invalid/unknown outcome and ambiguity use only a labeled supported declared-kind normal fallback
  with unknown priority; unsupported/missing/conflicting facts stop, without fabricated model confidence.
- Stale revisions, unauthorized facts, disallowed disclosure and exhausted limits fail safely without a claim.
  Safe reasons and provenance identify what the classifier actually saw without exposing hidden TASK data.
- Verify owned judgment/fallback and permission boundaries with focused behavior evidence; qualify real Jev
  accuracy, spend, latency and failures separately from fixtures and without tests of skill prose or tool wiring.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00200](../tasks/00200-TASK.md) | Diagnose selected TASKs through a permission-limited Triage profile | needs-info |
| [TASK-00201](../tasks/00201-TASK.md) | Classify every selected TASK with bounded Jev and safe fallback | ready-for-agent |
| [TASK-00202](../tasks/00202-TASK.md) | Resolve material classification conflicts with human disposition | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00012](../epics/00012-EPIC.md), consuming
[WF-027](../wayfinder/tickets/WF-027-define-task-triage-readiness-and-classification.md) and the shared Jev
capability planned in [TICKET-00037](00037-TICKET.md). [TICKET-00052](00052-TICKET.md) consumes its typed
recommendation for deterministic catalog routing. Actual Jev access and managed profile capability still require qualification; no classifier or runtime call
was created by approving the requirement.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00200 — Diagnose selected TASKs through a permission-limited Triage profile](../tasks/00200-TASK.md),
[TASK-00201 — Classify every selected TASK with bounded Jev and safe fallback](../tasks/00201-TASK.md), and
[TASK-00202 — Resolve material classification conflicts with human disposition](../tasks/00202-TASK.md).
TASK-00200 depends on canonical eligibility (TASK-00096) and remains `needs-info` until the still-unplanned
TICKET-00034 template implementation can be identified and qualified; no fictional blocker ID was assigned.
TASK-00201 depends on TASK-00200 and the bounded Jev capability (TASK-00159); TASK-00202 depends on TASK-00201.
All are unranked to preserve Board priority. TICKET-00052 owns routing and authoritative start. Remaining map
TICKETs require separate TASK decomposition; no implementation was performed here.
