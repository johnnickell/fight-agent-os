---
id: TICKET-00003
epic: EPIC-00002
title: Establish independent implementation review
status: ready-for-agent
---

# Establish independent implementation review

## Problem statement

Implementation claims need an independent, adversarial check against approved requirements, actual changes, tests, architecture, security, and disclosed evidence before landing. A self-review or green command alone is not sufficient acceptance evidence.

## Solution and boundaries

Create a `review` skill and concise shared review standards that inspect a local branch or PR against its TASK, diff, tests, and evidence; attempt to disprove acceptance; and return a structured verdict. Review must distinguish blocking findings, non-blocking findings, and residual risks while keeping observations traceable to files, behavior, requirements, or fresh verification.

The reviewer does not repair findings, finalize planning, mark the TASK done, push, merge, deploy, or replace human PR review. A reviewer must not present review of its own implementation as independent evidence.

Out of scope: implementation and remediation, design critique, landing, planning decomposition, PR approval/merge, release certification, and deployment.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Establish review scope | N/A — the skill coordinates repository inspection | Read TASK, parent TICKET, accepted decisions, claimed evidence, branch/PR diff, and repository status | N/A — no application domain event is produced | Review target, baseline, requirements, and independence limitations are explicit |
| Attempt to disprove implementation acceptance | N/A — direct read-only inspection and verification tools | Inspect implementation, architecture boundaries, tests, security/authorization behavior, planning updates, and warnings | N/A — application events belong to the reviewed use case | Fresh evidence confirms or contradicts claimed behavior without modifying implementation |
| Report an independent verdict | N/A — reporting is a workflow effect | Reconcile findings with requirements and verification output | N/A — no workflow domain event exists | Blocking and non-blocking findings, residual risks, and a clear accept/revise verdict are handed back |

## Validation and permissions

Review must stop or qualify its verdict when the TASK, diff baseline, claimed evidence, or independence is ambiguous. Findings must identify severity, evidence, affected requirement, and required correction where applicable; subjective preferences must not masquerade as blocking defects. The review must verify scope control, Domain/Application/Adapter and CQRS boundaries, Action–Domain–Responder behavior where relevant, authorization and secret handling, regression coverage, warnings, and truthful planning state.

The reviewer may run focused and canonical checks but must not claim checks it did not execute or treat inherited receipts as fresh application evidence. It must preserve unrelated work and avoid write operations except explicitly authorized scratch evidence under `.runs/`.

No application permission model applies. The skill receives read/verification authority for the authorized branch or PR, not implementation, planning-finalization, push, merge, or deployment authority.

## Acceptance and evidence

- `.pi/skills/review/SKILL.md` exists and uses project-owned review standards.
- The skill defines review intake, independence disclosure, baseline/diff inspection, adversarial verification, and structured verdict behavior.
- Review coverage explicitly includes approved scope, architecture, CQRS/HTTP boundaries, security and authorization, tests, warning disclosure, evidence provenance, and planning accuracy.
- The report format separates blocking findings, non-blocking findings, residual risks, and verification performed.
- A bounded demonstration includes at least one seeded defect or unsupported acceptance claim and shows that review reports it with traceable evidence.
- Fresh focused verification is recorded where useful, and `./bin/build` is used when required by the reviewed TASK or final acceptance claim.
- Demonstration evidence confirms the reviewer did not repair, land, push, merge, or approve its own implementation.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00003](../tasks/00003-TASK.md) | Establish and prove independent implementation review | done |
<!-- /planning:children -->

## Decisions and progress

Implements the independent `review` boundary approved by [WF-002](../wayfinder/tickets/WF-002-shape-agent-skill-suite.md). It follows [TICKET-00002](00002-TICKET.md)'s shared execution standards and must review the bootstrap `work` capability before that capability is relied upon for production application changes.
