---
id: TICKET-00125
epic: EPIC-00021
title: Promote and recover an explicitly approved release
status: ready-for-agent
---

# Promote and recover an explicitly approved release

## Problem statement

A passing build, merged PR or running container does not identify an approved production release. John needs an
operator-run path that stages one exact candidate, obtains bounded approval, applies only the intended effects
and reconciles interrupted or failed outcomes without blindly repeating migrations, provider work or email.

## Solution and boundaries

Deliver the actual Fight-specific staging-to-production promotion and recovery path under
[EPIC-00021](../epics/00021-EPIC.md), not a runbook-only demonstration or new release engine. Consume the immutable
artifact from [TICKET-00121](00121-TICKET.md), isolated environment composition from
[TICKET-00122](00122-TICKET.md), real mail from [TICKET-00123](00123-TICKET.md) and coherent recovery from
[TICKET-00124](00124-TICKET.md).

Build once, validate the exact artifact digest in staging and promote those bytes without rebuilding. Identify
source/artifact, exact target, configuration and secret revisions (not values), schema changes, required services/
schedules and the permitted rollback/stop behavior. John approves that exact candidate and bounded effects;
material changes invalidate approval. Push, merge, green CI and record status never deploy. Optional Fight-specific
human UAT is candidate/configuration-bound, not a universal phase or substitute for independent Review/QA.

Use TICKET-00121's application CalVer identifiers and `release/<version>` branches, with no requirement to retain
Git release tags. Retain the exact release-to-source-commit/artifact-digest association in the release record;
a branch name or its current tip is not immutable provenance or deployment approval. Optional candidate-to-final
labeling does not rebuild bytes. CalVer does not establish schema compatibility or authorize rollback. This
selection creates no branch, changes no CI trigger and authorizes no deletion of existing tags.

Apply approved schema evolution explicitly through the existing Doctrine migration path before dependent traffic;
do not create schema opportunistically at startup. Coordinate initial database Planning migration with
TICKET-00028's immutable inventory/digests, rehearsal, exact approval, complete atomic import/authority switch and
retained frozen Markdown. TICKET-00026/00027 retain semantics/order. Until verified approved cutover Markdown stays
authoritative: no partial import, dual writer, automatic archive or second migration engine.

First production must deliver the existing database-backed browser Wayfinder journey: authorized Repository,
explicit matching/new map choice, initiating user's protected OpenRouter connection, one HITL decision and explicit
Apply, then durable reconnect with truthful progress/usage. Consume TICKET-00089/00090/00092 and the reconciled
model/default/continuation/accounting requirements. Missing optional allowance is unavailable, not zero/unlimited
and not a separate launch gate. No shared family key or implicit provider fallback. Connection lifecycle TASK
allocation and protected runtime/continuation/source qualification remain with their existing owners, not hidden
inside release work.

Record and inspect actual deployment identity and independent application/service/diagnosis/backup health, including
partial/unknown states. Interrupted commands or connection loss do not prove that an effect failed. Inspect the
original operation and actual target before resuming; keep provider/credential liabilities and original delivery
identities intact. Preserve safe operator records/instructions accessible when the application is unavailable;
application runtime never depends on `.runs/` execution receipts.

Allow application-only rollback within the exact approved deployment only when compatible with the current
database, artifact/configuration and permitted target. Unknown/incompatible schema stops for a human recovery
choice; no implied reverse migration. Database restore instead uses TICKET-00124 with explicit point/data-loss
approval and isolated effect suppression. Retain originals and account for costs until cleanup is authorized.
Provide practical maintenance/outage communication outside Fight, without a staffed status service.

Out of scope: automatic deployment on merge, managed Release Manager implementation, another Workflow/session/
usage/credential system, replacement Planning/migration, general release automation, optional wizard/ChatGPT/
managed coding workers, legacy retirement and a standalone readiness/certification framework.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Stage a candidate | Explicit authorized staging deployment and existing migration operations | Artifact/configuration identity, schema, service requirements and actual state | Safe operational outcomes and existing owner facts only | Run the exact candidate in isolated staging; no automatic production effect |
| Prepare and approve promotion | Human approval of the exact candidate/target/effects; no new Domain approval engine | Staging evidence, changed revisions, budgets, migration and rollback conditions | Recorded human decision, not a fabricated Workflow transition | Bounded permission to act; any material drift invalidates it |
| Promote and verify production | Explicit approved operator rollout and existing owner operations | Actual digest/configuration/schema, independent health and first-journey results | Observed partial/success/failure receipts separately from requested intent | Only approved production/cutover effects with independently observed outcomes |
| Reconcile interrupted rollout | Inspect original operation; explicitly resume only supported remaining work | Actual target state, prior effect outcomes and current approval | Existing owner outcomes plus safe operational reconciliation | No blind paid/message/migration replay or false success |
| Roll back or request restore | Compatible bounded application rollback, otherwise separate human recovery decision | Current schema compatibility, exact candidate and recovery point/data loss | Actual observed recovery state, not inferred completion | Application rollback without data rewind, or separately approved TICKET-00124 restoration |

## Validation and permissions

Check current exact target/artifact/configuration/schema and applicable approval before effects. Production
credentials remain in their protected operational owners, never ordinary Agent-visible environment/arguments,
logs, prompts or artifacts. Code/PR authority is not deployment, spending, DNS, migration or restore authority.
Missing target-specific access/evidence blocks that operation without prohibiting independent development/staging.

Revalidate approvals after material drift; stop on uncertain incompatible migration/rollback or unresolved original
effects. Read-only inspection must not repair, restart or redeploy. Existing application command/query permissions
and events remain with their owners; infrastructure actions do not justify new Domain message aliases or a
universal orchestration platform. Current account/connection authority is not granted by historical release approval.

## Acceptance and evidence

- Stage and promote the exact artifact digest with inspectable configuration/secret revisions and schema/service
  effects. Demonstrate rejection of stale/mismatched approval and absence of deploy-on-push/merge/green behavior.
- Actual authorized rollout produces observed target identity and useful database-backed browser Wayfinder through
  the qualified existing owners. Explicit Apply, private history, per-user connection, reconnect and truthful usage
  survive the release; simulations do not qualify live production generation.
- Planning cutover uses its owner's approved immutable inventory/rehearsal and atomic switch, preserving frozen
  Markdown without dual authority or premature archive. No release-specific migration substitute is introduced.
- Interrupted and partial operations remain inspectable and recover through original identities; no duplicate paid
  retry, blind mail resend, erased uncertainty or inference of completion from command acknowledgment.
- Exercise compatible application rollback without data rewind. Incompatible/unknown schema requires a human choice;
  restore consumes TICKET-00124's separately authorized recovery point/data-loss and effect-suppression contract.
- Operator guidance works during application failure and explains maintenance, diagnosis, stop/recovery and cost/
  cleanup boundaries. Actual target evidence accompanies the guide rather than documentation alone proving delivery.
- Full `./bin/build`, independent Review/QA and bounded target-specific probes provide ordinary acceptance evidence.
  Validate infrastructure/runbooks directly, not product tests of wrappers, configuration or quality tooling.
  Missing evidence remains explicit; first launch is not a circular prerequisite for implementing its suppliers.

## Coordination

A–D supply real early artifact/environment/mail/recovery contracts; this later composed journey never reverse-blocks
their development or safe staging verification. The existing Planning/provider/security/diagnosis owners keep their
requirements and actual qualification holds. [TICKET-00058](00058-TICKET.md)/TASK-00222 may later consume this
qualified project target/runbook; this operator-run delivery neither implements that managed Workflow nor clears
its independent acceptance/target holds. EPIC-00020's Board priority remains unchanged.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00532](../tasks/00532-TASK.md) | Stage and recover an exact release candidate | ready-for-agent |
| [TASK-00533](../tasks/00533-TASK.md) | Promote the approved release and deliver browser Wayfinder | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved slice E of EPIC-00021's five-TICKET split, consuming WF-048 promotion and WF-049/050 journey/recovery
choices. Follow [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and the existing browser security/runtime
contracts. Initial TICKET acceptance allocated no TASKs. John subsequently approved the two-TASK split below;
neither approval authorizes deployment, live provider/mail calls, migration, restore, spending or publication.
Planning acceptance is not implementation or production acceptance.

### Approved TASK decomposition and current handoff

| Slice | Accepted TASK | Blockers | Delivered outcome |
|---|---|---|---|
| A | [TASK-00532 — Stage and recover an exact release candidate](../tasks/00532-TASK.md) | TASK-00529 | Executable exact-artifact staging/reconciliation/compatible application rollback, independent health and outage-accessible guidance without a production or finished-browser prerequisite |
| B | [TASK-00533 — Promote the approved release and deliver browser Wayfinder](../tasks/00533-TASK.md) | TASK-00532, TASK-00530, TASK-00362, TASK-00365, TASK-00367 | Re-stage the final qualified candidate, obtain exact approval, promote identical bytes and observe real production Wayfinder/model/reconnect/usage plus safe production interruption/rollback |

A is an unranked `chore`, B an unranked `feature`; both are `ready-for-agent` when dependencies permit, with blank
PR fields. Each owns its actual delivery outcome, direct operational checks, applicable application/integration
tests, full build and independent Review/QA. No checklist-only, universal UAT, certification or generic release
platform TASK is added; no product tests of deployment wrappers/configuration, workflow YAML or quality tools.

#### Supplier and cutover boundaries

TASK-00529 supplies A's staging/recovery/artifact/security/service chain. A qualifies a usable mechanism on actual
staging candidates; B must re-stage its final bytes after feature suppliers qualify. Older candidate receipts do
not certify later bytes/configuration. TASK-00530 supplies production recovery, inheriting actual production/mail
integration. TASK-00362 supplies interview/reviewed map Apply, TASK-00365 durable reconnect/usage and TASK-00367
initial/default/model and explicit continuation controls, with their real source/host/current-authority holds intact.
Optional allowance, AFK work, generic decomposition UI and complete managed teams are not new launch gates.

TASK-00104 remains the existing human-operated real Planning cutover owner, inherited by the browser suppliers.
TASK-00532 and TASK-00530 provide early release/recovery readiness without reverse-waiting for the final browser
journey. No new TASK or release-specific migration replaces the clean source freeze, two equivalent rehearsals,
exact human approval, atomic switch, frozen Markdown retention and first-write boundary. If already cut over,
verify actual current authority instead of repeating import. Different target/data authority needs its own approved
operation. After cutover, Planning evidence/closeout uses database authority, not edits to frozen Markdown.

Both slices preserve exact CalVer/source/digest identity without mandatory Git tags, no deploy-on-merge/green behavior,
explicit Doctrine changes, original-operation reconciliation and compatible application-only rollback. Compatibility
includes current security/authority-mode guards, not merely schema. Unknown/incompatible recovery stops for a separate
point/loss/target decision under TICKET-00124. Retain originals/resources until separately approved disposition.
TASK-00222 may later consume the qualified target/runbook; its managed Workflow/acceptance/target holds remain and
are neither launch gates nor resolved by this allocation. No existing TASK metadata/status/order/dependency changes.

Immediately before allocation, checked live/archive TASK filenames across eighteen local/cached refs and nine
registered worktrees, including untracked records; highest observed was TASK-00531 in another registered worktree.
Leave that unrelated allocation untouched. TASK-00532/00533 are local allocations, not a global reservation. The
ignored `.runs/notes/promotion-decomposition/before.json` receipt captures 785 planning Markdown files before this step.

#### Completed selected-map planning

The private-production rollout map's decisions, approved sole new EPIC, reconciled existing-owner requirements,
five-TICKET split and all selected TASK decompositions are now accepted/written. Deployment owns eleven TASKs:
TASK-00522/00523 (artifact/CI), TASK-00524–00526 (environments), TASK-00527/00528 (mail), TASK-00529/00530 (recovery)
and TASK-00532/00533 (release). TICKET-00089 additionally owns TASK-00520/00521 lifecycle/settings; other amended
requirements retain their existing owners. TASK-00531 is not part of this rollout. Optional expansion/deferred
ChatGPT and legacy retirement remain excluded, not silently completed or turned into release gates.

Planning completion does not change EPIC/TICKET/TASK delivery statuses, EPIC-00020 priority, TASK-00510–00512 or any
actual qualification hold. No implementation, target/spending authority, live evidence or publication is inferred.
Work remains uncommitted/unpublished. The Board, not this map or new IDs, owns executable work selection.

#### Planning verification

`./bin/planning-check --write` and `./bin/planning-check` passed: **677 records, 521 active**, generated views current.
`git diff --check` and changed/untracked scope/whitespace checks passed across **70 planning paths** including earlier
uncommitted work; 4,121 relative link targets were checked.

Against the 785-file pre-edit baseline, exactly sixteen existing handoff/generated paths changed and TASK-00532/00533
were added; no prior file was removed. Every pre-existing frontmatter block and TASK file remains byte-identical,
including TASK-00104's human-operation ownership/status, TASK-00510–00512 and all existing orders/dependencies/holds.
EPIC-00020 remains unchanged. Verified exact new parent/kind/status/blank-order/blank-PR metadata and blocker lists,
the eleven deployment TASK mappings across the five accepted TICKETs, and the complete acyclic TASK dependency graph.
TASK-00532 has no production, actual cutover or finished-browser prerequisite. TASK-00533 inherits actual production,
mail, connection custody and TASK-00104 cutover without managed Release Manager, final named-role, AFK or optional
allowance dependencies. The unrelated TASK-00531 remains outside this selected checkout's rollout records.

These are planning checks only. No full application build, independent Review/QA, deployment, schema migration,
Planning cutover, provider/mail call, credential/vault access, backup/restore/rollback, purchase or cleanup occurred.
No commit or push occurred. Earlier lifecycle/artifact/environment/mail/recovery counts remain historical to their
own steps; no inherited receipt is presented as fresh application or production evidence.

Next: `/skill:next` from the selected planning checkout for read-only Board-based selection. No automatic execution.
