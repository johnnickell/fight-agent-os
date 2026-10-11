---
id: TICKET-00054
epic: EPIC-00012
title: Merge a verified hotfix back under one authorization
status: ready-for-agent
---

# Merge a verified hotfix back under one authorization

## Problem statement

A GitFlow hotfix merged into the production branch can leave the same bug in the development branch. Merely
recording a follow-up or creating another TASK does not complete the intended hotfix path; automatically
merging without current authority and conflict checks risks corrupting active development.

## Solution and boundaries

After a trusted observation verifies that the exact hotfix PR was merged into the Repository-configured
production base, offer **one Yes/button** to authorize merging that fix back into the configured development
base inside the **same hotfix Workflow history**. Recheck source/target identities, policy, grant and provider
state before the effect; run the applicable integration checks, verify the resulting branch state and persist
actual outcome. No second TASK, new Workflow, repeated branching-strategy confirmation, automatic PR merge or
unbounded merge grant. A trunk-based Repository without a separate development base has no merge-back action.

This narrowly extends the ordinary WF-015 PR-handoff stop policy for the hotfix's post-PR operation: any claim
already released at PR handoff is **not** silently reacquired. The operation is scoped to the existing hotfix
identity and its confirmed subject/branches. Conflicts, advanced/incompatible branches, protected-branch
restrictions, failed checks or uncertain remote outcomes pause with concrete resolution choices rather than
claiming a successful merge or inventing a new follow-up TASK. Continue only under appropriate fresh authority
when the subject or effect changes.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Offer eligible merge-back | N/A — presentation/query only | Trusted merged hotfix identity, current configured production/development bases, current merge inclusion | N/A — a read is not a merge event | One authorized action is offered only when the target actually needs the fix |
| Authorize and execute merge-back | Scoped confirm/merge-back operation, exact names deferred | Revalidate claim/history, grant, remote refs, source/target and required checks | Authorization, durable effect intent, verified success or blocked outcome | Merge only the confirmed hotfix into configured development base when permitted; no extra TASK/Workflow |
| Reconcile interrupted or conflicting effect | Resume/reconcile the same operation under current authority | Remote refs, prior intent and observed merge result | Recovery or Needs Human fact, never a fabricated success | Already-completed effect reused; uncertain/conflicted effect pauses without duplicate mutation |

## Validation and permissions

A human Yes/button grants only the named merge-back effect for the exact hotfix and branch identities. Triage,
Hotfix Engineer and UI presentation do not inherit generic merge authority. Protected-branch rules and any
required review/PR approval remain separately enforced; if direct merge is not permitted, use the approved
project branch procedure with its own authority without treating the click as PR approval. Validate live
Permissions, revisions, trusted provider merge evidence, expected source/target OIDs, existing inclusion,
conflicts and checks. Reconcile uncertain provider effects before retry. No command may merge development
history into production, force-update a branch, or create a new Workflow to hide failure.

## Acceptance and evidence

- A verified production merge on a configured GitFlow Repository offers one quick scoped action; a pending,
  closed-unmerged, stale, inaccessible or wrong-target PR does not.
- The action verifies the result on the configured development base with current branch identities and checks,
  preserves newer development work, and reports completion only from observed evidence. Trunk-based projects
  do not show an irrelevant `develop` action.
- Conflicts, changed refs, branch protection, denied/revoked authority, failed checks and unknown provider
  outcomes remain visible with safe resume paths; repeated clicks cannot duplicate or falsely complete effects.
- Hotfix PR handoff, post-PR merge-back and ordinary TASK completion remain distinct facts within one historical
  hotfix identity, without a new TASK or renewed execution claim.
- Exercise owned permission, idempotency, stale identity and reconciliation behavior and real provider branch
  rules with relevant integration evidence; do not product-test Git or Markdown mechanics themselves.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00209](../tasks/00209-TASK.md) | Offer a verified hotfix merge-back | needs-info |
| [TASK-00210](../tasks/00210-TASK.md) | Authorize and reconcile the same-hotfix merge-back | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00012](../epics/00012-EPIC.md) under
[WF-028](../wayfinder/tickets/WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md). Consumes the hotfix
handoff of [TICKET-00053](00053-TICKET.md) and the trusted merge observation in
[EPIC-00008](../epics/00008-EPIC.md); extends [WF-015](../wayfinder/tickets/WF-015-define-coordinated-task-authority-protocol.md)
only for this approved post-PR boundary. No real branch or provider operation was performed.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00209 — Offer a verified hotfix merge-back](../tasks/00209-TASK.md) and
[TASK-00210 — Authorize and reconcile the same-hotfix merge-back](../tasks/00210-TASK.md). TASK-00209 depends
on TASK-00207's hotfix PR handoff and remains `needs-info` until EPIC-00008's actual managed trusted merge
observation is qualified and its implementing TASK identified; EPIC-00008's `done` status and a local QA skill
do not establish it. TASK-00210 depends on TASK-00209. Both are unranked, preserve the post-PR claim release,
and create no second hotfix TASK/Workflow or generic merge authority. EPIC-00012's four TICKETs now have
approved TASK decompositions; the four EPIC-00013 TICKETs still require their own TASKs. Planning performed
no provider observation, merge or branch write.
