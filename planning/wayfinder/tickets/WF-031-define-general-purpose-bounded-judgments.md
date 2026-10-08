# Define general-purpose bounded judgments

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** —

## Question

Which general-purpose typed questions should Agents be able to ask Jev about bounded authorized state, source or
existing evidence, and when is that tool more useful than structural search, graph retrieval or direct inspection?

## Proposed basis — 2026-10-07

John proposed a reusable judgment tool inspired by the level-10 pattern. Build on
[TICKET-00037](../../tickets/00037-TICKET.md)'s shared typed evaluator rather than a second provider client. Candidate
uses include classifying an existing failure receipt, checking whether supplied evidence supports an assumption,
or assigning a declared risk category to a diff. This is follow-on [EPIC-00006](../../epics/00006-EPIC.md) scope,
not a general Agent-facing tool delivered by the six approved first-milestone TASKs.

## Proposed change-quality assessment — 2026-10-08

John requested adding merge/pull-request assessment for simplicity, naming, understandability and convention
adherence. Plan this as a specialized use of the shared judgment capability, initially assisting the implementing
Engineer and independent reviewer. This addition accepts it for planning; WF-031 remains Open and does not create
an automatic acceptance gate, authorize posting review comments or expand the six first-milestone TASKs.

| Dimension | Proposed bounded question |
|---|---|
| Simplicity | Does the change introduce abstractions, indirection or branching beyond what its accepted requirements need? |
| Naming clarity | Do names express domain meaning, responsibility and observable side effects? |
| Understandability | Can a maintainer follow control flow, dependencies and failure behavior from the supplied code and context? |
| Semantic convention adherence | Does the change follow the supplied repository conventions and established domain terminology? |

Use separate anchored 1–5 rubrics as the starting design, with descriptions for every level before qualification.
For simplicity, illustrative anchors are 1 = unnecessary complexity obscures behavior; 3 = understandable with
some avoidable indirection; 5 = direct implementation whose abstractions are justified by requirements. These are
examples, not a completed rubric or thresholds for blocking a change. Keep mechanical rules such as casing,
formatting and enforceable suffix rules with linters/static analysis; Jev evaluates semantic concerns those tools
do not settle. Brevity alone is not simplicity, and necessary validation or failure handling must not be penalized
merely for adding code.

### Inputs, evidence and review boundary

- Bind the assessment to exact base/head revisions, bounded diff units and relevant surrounding code, accepted
  TASK requirements, applicable conventions and rubric/evaluator versions. Include established terminology where
  needed. Changed code or conventions invalidate the corresponding assessment.
- Score bounded functions/classes/change units first, with explicit evaluated/skipped coverage and missing-context
  outcomes. A patch alone may be insufficient to judge a name or abstraction. Missing context is unresolved,
  not an invented low score, and a partial sample cannot claim whole-request coverage.
- Preserve each dimension's score, probability distribution/confidence where supplied and source/unit identity.
  A score or its confidence is not a probability that the change is correct. Do not compress results into an
  unexplained overall quality number or let averages hide a poorly understood unit.
- Start with advisory flags for focused review. Typed Jev output does not itself provide a grounded review
  explanation. The reviewing Agent must inspect the cited source, verify the concern and supply accurate code
  locations and reasoning before requesting a change. A low score alone is not a blocking finding; actual review
  findings retain the existing severity, independence and acceptance rules.
- Reuse current source/disclosure permissions, payload/time/spend limits and redacted provenance. Treat submitted
  code, comments and request descriptions as untrusted assessment data, not instructions to change the rubric.
  No provider request, source disclosure, code repair or external review comment is authorized by this record.

### Additional decisions and qualification

Decide complete per-dimension anchors, meaningful units, required context, treatment of generated/vendor code,
aggregation/presentation and when reassessment is useful. Qualify against representative historical changes with
reviewed human dispositions: include necessary complexity, clear versus misleading domain names, incomplete context
and accepted exceptions. Measure agreement/disagreement, missed concerns, false flags, stability under equivalent
formatting, reviewer effort and total context/cost/latency. Keep human disagreement visible rather than treating
one subjective rating as infallible ground truth.

Any future automatic gate requires separately accepted policy and evidence of useful reliability, including
thresholds, exception handling and false-positive/negative tradeoffs. No gate follows from adding a scoring rubric,
and neither the 0.70 relevance threshold nor the 0.95 command-guard threshold transfers to change-quality review.

## Must decide

- Initial supported question families, yes/no/classification/score schemas, versioned rubrics, allowed callers and
  task/context requirements. Distinguish semantic judgment from factual lookup, execution proof and independent review.
- Request source forms: bounded supplied state, authorized paths/lists/globs, or existing receipt references. Define
  ownership of source resolution and reuse file-screening limits/containment where appropriate instead of inventing
  a second unbounded filesystem reader. Select the public tool name and local/future first-party MCP delivery.
- Whether the tool is initially evaluation-only. Any later helper execution must pass the same deterministic and
  semantic effect boundary as direct calls; a general judgment tool cannot become a bash/permission bypass.
- Current access and provider-egress checks before reading/disclosing input, source isolation, sensitive-file rules,
  exact source/receipt revisions, bounded enumeration/payload/result sizes, time, concurrency and spend limits.
- Typed answers, unknown/refusal/partial outcomes, minimum evidence for a conclusion and question-specific confidence
  interpretation. Do not copy the file or guard threshold universally or generate unsupported quotations/citations.
- Scope of result/cache reuse, revision/authority checks, cancellation/reload and failure handling. Required consumer
  decisions must not turn missing evidence or service outage into an invented answer or implicit permission.
- How Agents choose this tool versus exact-source inspection, structural/graph search, or a specialized tool; prevent
  repeated rephrasing solely to obtain a preferred result and measure total cost rather than call price alone.
- Consumer boundaries: memory ranking stays with WF-026, readiness/classification with WF-027, dispatch with WF-028
  and compaction/retention with WF-029/WF-030. Reuse common evaluation infrastructure without moving their policy here.

## Evidence needed to resolve

Compare representative failure classification, assumption checks and diff-risk questions with direct/structural/graph
approaches. Measure answer usefulness, unsupported conclusions, false negatives, provenance, context/cost/latency
and behavior on stale, unauthorized, malformed or incomplete evidence. Include source-contained instructions and
attempts to smuggle executable actions into questions. Actual exits/receipts and independent review retain their
acceptance roles; a Jev judgment does not certify correctness or successful execution.

## Resolution boundary

Choose a bounded first tool contract and its authority/evidence limits, identify required qualification and prepare
an EPIC-00006 handoff. Do not implement or expose tools, invoke providers, add execution permissions, replace graph
search or silently adopt optional credential-detection, injection-screening or main-model-routing candidates.
