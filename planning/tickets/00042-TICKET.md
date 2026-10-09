---
id: TICKET-00042
epic: EPIC-00006
title: Answer typed questions across bounded evidence
status: ready-for-agent
---

# Answer typed questions across bounded evidence

## Problem statement

Agents need economical semantic answers across large evidence sets without loading all source into their own
context. Tiny one-shot limits, unsupported conclusions and untraceable cached answers would defeat that purpose.

## Solution and boundaries

Deliver the first-party MCP judgment capability from
[WF-031](../wayfinder/tickets/WF-031-define-general-purpose-bounded-judgments.md) and
[EPIC-00006](../epics/00006-EPIC.md#jev-compaction-and-judgment-workflows--2026-10-08).
Reuse [TICKET-00037](00037-TICKET.md)'s evaluator and [TICKET-00039](00039-TICKET.md)'s source resolver/policy;
do not create another provider client, privileged Jev identity or duplicate authorization policy.

- Support Agent-authored yes/no, declared-choice and anchored-score questions alongside versioned presets; do not
  limit all questions to a fixed catalog. Qualify Pi/Harness callers through the trusted MCP origin and actual
  invocation context. A local adapter cannot invent managed identity or downstream permissions.
- Return compact typed answers, preconfigured choices/scores, coverage and authorized evidence references/metadata.
  Jev selects caller-written options; no generated explanation, fabricated location or default raw source/excerpt/
  query-output response. Exact-source follow-up is a separate authorized read. Keep probability, score and
  confidence meanings distinct with no universal cutoff.
- Represent unknown, not-applicable, incomplete, unavailable and invalid explicitly; none is a confident negative,
  low score or proof of absence. Each specialized consumer retains its own failure/threshold policy.
- Accept bounded state, paths, lists, globs, repository scope and existing receipts. Harness owns staged discovery
  and resolution across many files, with compact main-Agent results, resumable progress and explicit coverage.
  Configure aggregate enumeration, bytes/output, time, concurrency and spend to support many safe batches;
  neither a tiny whole-job limit nor silent enlargement of existing initial limits is acceptable.
- Bind progress and every result to source identity, question/context, rubric/evaluator and applicable policy/principal.
  Internal resolution does not recursively ask Jev to authorize each input read to its own evaluation. Apply
  per-source containment, symlink/sensitive-file/access and provider-disclosure checks before evaluation.
- Reuse only unchanged evidence and all relevant question/context/evaluator/policy inputs with current access checks.
  Unchanged text alone is insufficient; cache entries grant no cross-principal access. Historical receipt answers
  remain historical, live observations are fresh by default, and reuse never replays an operation.
- Recover/cancel bounded staged jobs without accepting stale/late responses after reload or explicit stop. Preserve
  uncertain billed outcomes and consumer-specific failure policies rather than automatic retries for a desired answer.
- Deliver semantic tool preference through governed Harness instructions/presets only when the capability is available.
  Retain exact search/structural/graph tools for precise symbols, dependencies and evidence. Specialized guard, file,
  compaction, memory, triage and dispatch policies cannot be bypassed by an ad hoc question or preset.

[TICKET-00043](00043-TICKET.md) owns gathering new evidence via MCP operations and analysis. This TICKET accepts
supplied/current authorized source and receipt evidence without executing arbitrary shell/SQL strings. Exclude
memory ingestion, automatic main-model routing, replacement of graph search and a second authority mechanism.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Ask a typed question | N/A: source inquiry does not mutate domain state | Evaluate supplied question/preset against authorized evidence | Judgment completed/failed with provenance | Bounded source reads, provider usage and receipt/cache metadata |
| Investigate a broad scope | Start, resume or cancel bounded investigation state | Candidate discovery, staged answers and coverage | Stage/progress/completion/cancellation observations | Authorized batches and protected progress metadata, no source writes |
| Reuse qualified answers | N/A: result retrieval is read-only | Match evidence/context/policy and live authority | Reuse/miss observation | Avoid upstream request only on a verified match |
| Follow supporting evidence | Existing authorized exact-source read | Resolve permitted evidence reference | Existing read/exception observation | Minimal necessary source enters Agent context |

Command/query labels describe semantic operations; exact wire/tool names, storage and packaging are delegated.

## Validation and permissions

Separate source access from provider disclosure; minimize/redact inputs. Protect endpoint/evaluator/budget settings
from repository instructions and reject executable intent smuggled into data/questions. Bound enumeration as well
as evaluation, traversal/recursion, binary/oversized sources and result metadata. Partial discovery cannot establish
whole-repository absence. Protected references and cache metadata require current scope/permission checks.
Keep raw source, credentials and hidden reasoning out of ordinary receipts/telemetry. Use package-owned authority
contracts directly under ADR 0001 rather than renamed copies; PHP retains authoritative application operations.

## Acceptance and evidence

- Demonstrate yes/no, declared choices and anchored scores with unknown/invalid/partial outcomes and authorized
  evidence references, without free-form Jev reasons or main-Agent source dumps.
- Demonstrate useful many-file investigation across multiple bounded stages, interruption/resume/cancellation,
  honest excluded/incomplete coverage and aggregate limits without silently raising initial safety ceilings.
- Exercise changed evidence/context, revoked access, forged identities, cross-scope cache access, source-contained
  instructions, symlink escapes, disclosure denial and stale responses. No permission or fresh evidence is fabricated.
- Compare representative failure classification, assumption checks and diff-risk questions with direct/structural/
  graph approaches for usefulness, missed evidence, unsupported conclusions, main-context size and total cost/latency.
- Qualify supported MCP/Pi delivery, exact evaluator/schema and actual source handling. Test owned judgment/progress
  behavior and qualify infrastructure directly; a demo or model confidence is not correctness evidence.

## Sequencing and TASK readiness

Consume needed TICKET-00037/00039 slices independently of compaction. TICKET-00043 builds operational evidence
composition on this capability; TICKET-00044/00045 can use static authorized evidence without waiting for production
SQL integration. Define exact public schema, supported callers, progress storage and aggregate budgets at TASK
planning; disclose any necessary unqualified runtime/authority guarantee as an affected-TASK blocker.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00171](../tasks/00171-TASK.md) | Expose authenticated typed judgments through MCP | ready-for-agent |
| [TASK-00172](../tasks/00172-TASK.md) | Investigate large evidence sets through resumable stages | ready-for-agent |
| [TASK-00173](../tasks/00173-TASK.md) | Reuse judgments when evidence and authority still match | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this split on 2026-10-08 for the selected Jev follow-on. WF-031's accepted general-judgment, staged
investigation, result and reuse policies remain input requirements. This is ready for TASK decomposition; it does
not activate a tool, add permission, invoke a provider or claim economical operation before measurement.

### Approved TASK decomposition — 2026-10-09

John approved three independently reviewable outcomes targeting **Pi 1.1.0**:

- [TASK-00171](../tasks/00171-TASK.md): authenticated first-party MCP typed judgments, blocked by
  TASK-00159/TASK-00162. Own the minimal actual caller/authority integration alongside the usable judgment tool;
  local Pi access alone does not establish trusted MCP authority.
- [TASK-00172](../tasks/00172-TASK.md): many-file staged investigation with protected resumable progress, blocked by
  TASK-00171/TASK-00163. Keep existing request/stage ceilings and require explicit finite whole-investigation
  budgets; interrupted or partial work cannot manufacture complete repository coverage.
- [TASK-00173](../tasks/00173-TASK.md): evidence/context/authority-bound reuse, blocked by TASK-00172. Recheck current
  access and disclosure, retain original provenance and distinguish historical observations from fresh evidence.

The request/result and lifecycle contracts, authenticated caller scope, protected Harness-owned progress storage
and required aggregate-profile fields are recorded in those TASKs. Concrete deployment limits must be finite and
qualified before activation; no absent value means unlimited execution. General judgment has no universal 0.70
cutoff, and reasons remain caller-written choices. Missing required MCP authority or storage/runtime guarantees
remain affected implementation acceptance blockers; they are not inferred from future plans.

All three TASKs remain unranked, preserving current Board priority and the existing evaluator/file-screening TASKs.
Compaction and production SQL are not prerequisites; new observation gathering stays in TICKET-00043. This is
planning completion only, with no implementation, provider invocation, permission grant or publication.

Next: `/skill:to-tasks TICKET-00043`
