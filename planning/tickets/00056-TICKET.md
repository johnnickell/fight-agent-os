---
id: TICKET-00056
epic: EPIC-00013
title: Coordinate and authorize one release
status: ready-for-agent
---

# Coordinate and authorize one release

## Problem statement

A release involves preparation, an exact certified subject, human intervention and external effects. Asking
for approval at every routine stage defeats the desired autonomy; a blanket grant before the candidate is
known is equally unsafe. Lost processes or partial effects must not start a second release TASK or erase what
was actually done.

## Solution and boundaries

Give one supported release TASK a distinct, durable Team Lead-coordinated Workflow through the existing
authoritative start/claim operation. A human's deliberate authorized start intent can pass Triage into that
Workflow without another routing confirmation. Team Lead owns preparation and human communication: inspect
current state, prepare documentation and integration, arrange independent acceptance of changed material,
run project-owned certification on the **exact** source commit/deployable candidate, and assemble a release
plan and signing handoff. Team Lead performs every non-human step within its grant, but cannot inherit Release
Manager effect credentials or independently accept its own contributed work.

After certification, let the human give **one bounded approval** naming the exact candidate, version/tag or
deployment environment, evidence, permitted effects and limits. The plan may cover several named downstream
effects without another routine prompt for each. Signing, when required, remains a separate interactive human
act using their own trusted terminal/key; verify its output rather than accepting a verbal claim or exposing
signing secrets to Agents. Changed commit/target, additional effect, check exception or exhausted authority
requires renewed approval. Library publication and application deployment are separate consumers of this
coordination contract in [TICKET-00057](00057-TICKET.md) and [TICKET-00058](00058-TICKET.md), not new TASKs.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Start and prepare a release | Existing authorized Workflow start and bounded phase operations | Canonical eligibility, project policy, live grants, candidate/provider state and required review | Existing claim/start, preparation and certification facts | One claimed release Workflow and verified preparation evidence; no publication or deployment on start |
| Obtain exact-candidate approval | Scoped approve/continue operation after certification | Inspect immutable candidate, required checks, grant, plan and allowed effect list | Attributed bounded grant or refusal | Authorize only the named effects for that candidate without repetitive routine confirmations |
| Hand off and verify human signing | Prepare exact signing instructions; record verified result | Certified commit, signer policy, tag identity, actual local/remote evidence | Signing handoff/result facts, never a secret or model-generated approval | Human signs interactively where required; the Workflow resumes only after independent postcondition inspection |
| Pause and resume incomplete release | Existing pause/recover/cancel operations | Current stage, evidence, intents, costs, authority and provider observations | Durable blocked/resumed/cancelled facts | Continue the **same** TASK and Workflow safely, not a new release per stage |

Names describe semantic interactions and reuse existing WF-015 authority, not new general-purpose grants.

## Validation and permissions

PHP revalidates canonical release eligibility, TASK/context/policy revisions, actor and role Permissions,
Workflow claim, independent evidence, budgets and exact candidate at start and before any new effect. Team
Lead coordinates, while Release Manager receives separate direct effect grants; role titles, prompts, Jev or
UI requests do not grant authority. Approvals are attributed, bounded and invalid on material subject drift.
Do not capture human signing keys, passphrases or raw credentials in prompts, child processes or receipts.
Record durable stage/effect identities and recover uncertain outcomes against authoritative/provider state
before retry. A failed certification, required check or unavailable evidence pauses with a concrete next
action; it never becomes a success because a process exited or a provider call was queued.

## Acceptance and evidence

- One release TASK proceeds through preparation and exact-candidate certification with Team Lead as human
  contact, independent acceptance where changed material requires it, and truthful warnings and limitations.
- A single scoped human approval can enumerate the downstream effects; no repeated routine approval appears
  unless the candidate, target, authority or effect scope changes. Starting a release is not that approval.
- Required interactive signing has a concrete safe handoff, separate effect identities and independently
  verified postconditions; Agent tools cannot access signing material.
- Pause, timeout, revocation, stale certification and duplicate start/continue commands retain one recoverable
  Workflow and visible history without duplicate effect grants or fabricated completion.
- Prove owned grant, exact-candidate, handoff, idempotency and stage-recovery behavior; qualify certification
  and signer tooling using project-owned tools rather than product-suite tests of scripts or configuration.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00214](../tasks/00214-TASK.md) | Start one recoverable release Workflow | ready-for-agent |
| [TASK-00215](../tasks/00215-TASK.md) | Prepare and certify the exact release candidate | needs-info |
| [TASK-00216](../tasks/00216-TASK.md) | Approve a bounded exact-candidate release plan | ready-for-agent |
| [TASK-00217](../tasks/00217-TASK.md) | Hand off human signing and verify its result | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00013](../epics/00013-EPIC.md). Consume
[TICKET-00055](00055-TICKET.md)'s release kind and project policy, the existing
[WF-015 Workflow authority](../wayfinder/tickets/WF-015-define-coordinated-task-authority-protocol.md) and
[Team roles](../../docs/engineering/TEAM.md). Neither signing nor external publication/deployment is granted
by this planning record. Real project qualification follows separately.

### Approved TASK decomposition — 2026-10-10

John approved [TASK-00214 — Start one recoverable release Workflow](../tasks/00214-TASK.md),
[TASK-00215 — Prepare and certify the exact release candidate](../tasks/00215-TASK.md),
[TASK-00216 — Approve a bounded exact-candidate release plan](../tasks/00216-TASK.md) and
[TASK-00217 — Hand off human signing and verify its result](../tasks/00217-TASK.md). These are sequential
same-TASK Workflow slices, not release-stage TASKs or separate effect grants. TASK-00214 consumes the distinct
release route, existing atomic Triage/start and demonstrated Runner recovery (TASK-00213/00203/00142);
subsequent slices consume its claim and each other's evidence. All are unranked.

TASK-00215 is `needs-info`: [TICKET-00033](00033-TICKET.md)'s local QA skill and
[EPIC-00008](../epics/00008-EPIC.md)'s `done` status do not demonstrate managed independent Review/QA and
acceptance enforcement. Obtain an accepted implementing plan and actual TASK dependencies before execution;
no blocker ID is fabricated or generic capability recreated in release orchestration. TASK-00216 records a
bounded human grant without carrying out TICKET-00057/00058's project effects; TASK-00217's human signing
is conditional on qualified project policy and remains separate from remote publication. No live Agent,
Workflow, claim, signer, approval, publication or deployment was created by this planning split.
TICKET-00057 then TICKET-00058 remain to decompose.
