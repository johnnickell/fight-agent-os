---
id: TICKET-00037
epic: EPIC-00006
title: Configure and qualify Jev access for the Harness
status: ready-for-agent
---

# Configure and qualify Jev access for the Harness

## Problem statement

The human-operated Pi Harness needs one dependable way to obtain bounded Jev judgments before tool safeguards
and required file screening can use them. Existing main-Agent provider login does not establish decision-client
readiness. Future adopters need explicit setup, credential protection, cost limits and actionable failures.

## Solution and boundaries

Deliver the shared access capability from the [incremental Harness amendment](../epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07).
Use the OpenRouter Decisions API first, with an injected provider adapter, typed questions/results, strict response
validation and exact qualified evaluator identity. Keep connection configuration outside repository-controlled
instructions; credentials must remain outside Agent-visible source, prompts, logs and session artifacts.

Support bounded yes/no, choice and score judgments. Preserve the distinction between Yes probability and choice
confidence, including explicit unknown/failure outcomes; never fabricate certainty or substitute default answers
when the response is incomplete. Version questions/rubrics and record policy/evaluator/source identity with redacted
receipts. Consumers own their decision policy; this capability does not decide whether a tool effect is authorized.

Provide setup and readiness inspection for human-operated sessions without waiting for browser journeys, database
Planning cutover, full managed Harness distribution or TASK-00138 completion. Pin the minimal local integration and
protected credential boundary during TASK decomposition. Preserve the future first-party MCP service boundary;
this TICKET does not implement a second Workflow authority or grant arbitrary MCP origins. Main-Agent provider
selection remains independent of Jev access.

Exclude automatic main-model routing, workflow dispatch, sandbox provisioning, memory ingestion and the consumer
policies owned by [TICKET-00038](00038-TICKET.md) and [TICKET-00039](00039-TICKET.md). No current credential readiness,
provider access or net savings is presumed. No real-account setup or live request occurs merely by approving this plan.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Configure decision access | Set/revise explicit connection and credential-store reference | Inspect redacted effective connection, evaluator and limits | Local configuration revision fact; no Workflow transition | Protected local configuration; credential storage only through the selected secure capability |
| Verify readiness | Run a bounded synthetic decision check | Inspect readiness and categorized failure | Check succeeded/failed with configuration identity | Small provider request without repository/private content and an observed result |
| Evaluate bounded questions | Submit authorized state and typed question block | Inspect typed answers, uncertainty and receipt | Evaluation completed/failed; observational only | Bounded upstream request and redacted usage/evidence |
| Inspect usage and failures | N/A: inspection is read-only | Read available cost, latency, limits and failure reasons | N/A: no domain transition from inspection | Bounded local presentation; no invented billing facts |

Names describe semantic operations, not preselected PHP classes, MCP wire methods or terminal commands.

## Validation and permissions

- Keep connection/model selection and configured limits under operator control; Agent-authored state cannot redirect
  the endpoint, select unapproved models, read credentials or expand disclosure permissions.
- Confirm permitted data disclosure before upstream dispatch. A file read permission alone is not provider-egress
  permission. Record only redacted bounded metadata by default; do not persist source bodies or credentials in receipts.
- Bound payloads, question/choice counts, concurrency, time, retries and spend. Track provider-reported usage without
  double counting; unavailable cost is not zero. Account for uncertain request outcomes before retrying billed work.
- Reject malformed/out-of-range/non-finite or mismatched answers, missing required measures, undeclared choices and
  stale configuration. Keep absence, refusal, timeout, denied access, billing failure and invalid response distinct.
- Verify an existing Pi credential path before reuse; do not copy private auth files into a worktree or expose keys to
  child Agents. A managed installation may later provide the protected service without per-Agent personal keys.
- Missing initial setup makes the Jev-enabled profile unavailable. Consumers receive explicit failures; this layer
  cannot silently downgrade their protection, choose another provider or fabricate successful evaluation.

## Acceptance and evidence

- A user can configure access, inspect redacted settings and run the documented synthetic readiness check with a
  bounded cost. Show missing/denied/billing/provider failure states without secret exposure.
- Valid typed answers preserve their meanings; invalid responses and timeouts cannot be mistaken for judgments.
  Exercise owned request/validation/budget behavior with controlled transport outcomes and qualify the real endpoint
  separately. Deterministic fixtures are not evidence of live model accuracy.
- Receipts identify evaluator, question/policy versions, supplied source revisions, outcome, elapsed time and available
  usage. Inspect actual logs/session output for credential disclosure and verify cancellation/resource cleanup.
- The chosen Pi integration loads and serves a real bounded synthetic question without changing the main Agent model.
  Document supported runtime versions and limitations, including reload/shutdown behavior and unavailable services.
- Record current endpoint/schema/model qualification and usage rather than relying on demo claims. Follow the
  repository-owned checks and full build for application changes; qualify infrastructure through its owning tools.

## Sequencing and TASK readiness

This is the first requirement area in the selected milestone. TICKET-00038 and TICKET-00039 consume its qualified
capability and may proceed independently after their needed slices are available; encode exact TASK dependencies
rather than blocking on unrelated EPIC work. At decomposition, settle the minimal runtime integration, protected
credential access and initial resource limits before marking affected TASKs executable. If a necessary capability
cannot be qualified, keep the affected TASK needs-info with its precise blocker. Live acceptance requires usable
OpenRouter access; a missing key does not prevent bounded implementation with controlled transport evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00159](../tasks/00159-TASK.md) | Evaluate bounded typed questions with usage receipts | ready-for-agent |
| [TASK-00184](../tasks/00184-TASK.md) | Configure protected Jev access and verify Pi readiness | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved the three-TICKET split on 2026-10-07. This record is ready for TASK decomposition, not a claim of
implementation readiness for uncreated TASKs. The narrower Harness milestone is explicitly selected; the rest of
EPIC-00006 and the open memory/triage maps remain separate planning work. No TASKs, credentials or provider calls
were created by this decomposition.

### Approved TASK decomposition — 2026-10-07

John approved [TASK-00184](../tasks/00184-TASK.md), protected Jev setup and fixed synthetic Pi readiness, and
[TASK-00159](../tasks/00159-TASK.md), bounded typed judgments with receipts. TASK-00159 depends on TASK-00184.
The selected local integration is the existing TypeScript Pi Harness and an injected macOS Keychain credential
capability, with operator-owned settings outside the repository. Initial ceilings are 64 KiB serialized input,
eight questions, sixteen choices, two concurrent requests, fifteen seconds and zero automatic retries; tighter
provider limits apply. Request/session monetary ceilings require explicit operator configuration and conservative
reservation, with unknown charges retained. Both TASKs are unranked; current Board priority is unchanged.
Implementation and live acceptance remain outstanding. No credentials or provider calls were used in planning.
