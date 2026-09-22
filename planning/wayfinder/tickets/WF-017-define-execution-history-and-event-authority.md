# Define execution history and event authority

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-015](WF-015-define-coordinated-task-authority-protocol.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md)

## Question

What PostgreSQL execution model cleanly relates one coordinated TASK attempt, its agent conversations and phase attempts, authoritative transitions, observational events, evidence, Git/PR outcomes, usage, and recovery state?

## Must decide

- Whether one top-level workflow session represents one coordinated attempt to carry one TASK through PR handoff, and how retries, resumptions, cancellations, and later attempts relate.
- Identities and relationships among repository, TASK revision, workflow session, claim/job, agent conversation, phase attempt, event, finding, verdict, evidence/artifact, branch, commit, and PR.
- Which application commands own authoritative state transitions and which events are observations that may be delayed, duplicated, missing, or reordered.
- Event identifiers, producer sequence/order, timestamps, idempotency, deduplication, transactional capture/outbox boundaries, delivery failure, replay, and reconciliation.
- Commit-specific review acceptance and finding dispositions in the durable model, including invalidation after implementation changes.
- Usage, duration, interruption, failure, and model/skill/policy revision capture when available without inventing unavailable precision.
- Artifact metadata versus external/blob storage, bounded previews, sensitive-content/redaction rules, retention, deletion, and audit needs.
- Separation between dashboard history and Pi's persisted conversation/session tree required to resume agent context; agents call application APIs/tools rather than writing SQL.

## Resolution boundary

This decision defines execution aggregates, events, projections, reconciliation, and retention policy categories. It must not create production tables, import Pi JSONL, start event capture, or claim that every Pi transcript event is authoritative workflow state.

## Preferences required

John must choose retention/privacy expectations, how much raw tool/prompt content may be retained, and whether a resumed failed workflow remains the same top-level attempt. The recommendation should use one workflow session per coordinated TASK attempt, explicit phase attempts beneath it, application-owned transitions, and separately retained resumable Pi conversation references.

## Resolution

Write this only when workflow-session identity, authoritative transitions, event reliability, conversation boundaries, and retention categories are approved.
