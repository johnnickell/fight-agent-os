# Define general-purpose bounded judgments

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
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

## Accepted direction — 2026-10-08

### Agent-authored questions and reusable presets

John selected Agent-authored bounded questions alongside reusable presets. Authorized callers may formulate
yes/no questions with explicit criteria, choice questions with caller-defined options, and score questions with
described scale anchors. Recurring uses, including change-quality assessment, should have reusable versioned
presets so Agents do not have to reconstruct the same rubric each time. Presets are not the only permitted
question families.

Use TICKET-00037's shared typed evaluator, validation, source/disclosure permissions and payload/question/choice,
time, concurrency and spending limits. Record the actual question definition and rubric identity through the
existing protected provenance contract; an ad hoc question must not be reported as an unchanged approved preset.
Jev selects or scores within the supplied schema; it does not generate explanation prose. Question authorship
adds no execution, disclosure, acceptance or other operational authority. Specialized consumer policies remain
with their owning decisions rather than becoming editable through an ad hoc general-purpose question.

### Broad file investigation in bounded stages

John selected staged investigation for codebase-wide questions and emphasized that inexpensive classification
across many files is a core reason to build this tool. Design for broad permitted file fan-out, not a tiny sample
that forces the main Agent to ingest the rest. The Harness owns discovery, batch progression, authorized source
reads and aggregation; Jev classifies the supplied evidence through typed questions. This remains a tool under
the invoking principal, not an independently privileged or free-form reasoning Agent.

Support bounded supplied state, authorized paths/lists/globs, repository-scoped discovery and existing evidence
references, together with the accepted MCP evidence-gathering path. Reuse the file-screening resolver's containment,
exclusion and disclosure checks. Discover candidates, classify/evaluate batches, and narrow or expand within the
authorized scope and aggregate budget as the question requires. Source bodies stay in the classifier input path;
return compact typed results and evidence references to the main Agent. Internal source resolution must not cause
recursive judgment calls merely to authorize its own evidence reads.

Reuse first-milestone per-file/per-batch safety limits where applicable, but do not interpret a batch limit as a
fixed whole-investigation coverage ceiling. Progress through multiple bounded batches under configurable total
file, byte, time, concurrency and spending limits. This follow-on design does not silently increase the approved
first-milestone defaults or bypass a configured total budget. Preserve progress, report remaining coverage when
limits are reached, and avoid rediscovering/re-reading the same evidence unnecessarily. Exact resumable-work and
limit configuration details require implementation qualification.

Cheap classification is the intended operating model; qualify the cost of broad scans and their reduction in
main-Agent context, not just the price of an individual call. Measure total discovery/read/provider cost, latency,
coverage, repeat work and answer usefulness. Optimize economical broad coverage while retaining current access
and disclosure controls. A partial or relevance-filtered scan cannot establish codebase-wide absence, and even
full classified coverage is not proof of semantic correctness. Keep the evaluated scope and exclusions explicit.

### Tool selection and specialized policy ownership

John selected Jev as the preferred tool for semantic investigation: locating relevant behavior, classifying
candidate evidence and narrowing likely problem areas through economical broad evaluation. Use exact search,
structural tools and graphs for precise symbols, references, dependency relationships and source evidence.
Prefer the tool suited to the question rather than requiring Jev to emulate an exact index or declaring it a
universal replacement for graph search. Measure end-to-end usefulness and cost under this policy.

Retain TICKET-00039's question-first file-read convention and its accepted exceptions. General semantic judgments
do not bypass required exact-evidence inspection or independently establish complete absence. Specialized tools
and policies remain authoritative: memory retrieval with WF-026, TASK readiness/classification with WF-027,
dispatch with WF-028, command screening with TICKET-00038, and compaction/retention with WF-029/WF-030. Reuse the
shared evaluator and preset definitions without allowing an ad hoc question to rewrite those consumers' rules.

### Typed results and preconfigured answer choices

John selected typed answers and preconfigured enumerated answer choices as the result contract. Define choices,
yes/no criteria and any score anchors before evaluation, either through a reusable preset or the accepted
Agent-authored request. Agent-authored questions do not permit Jev to invent a new answer label, reason or prose
explanation. Preserve each answer type's meaning, including supported probabilities, confidence and anchored
numeric scores; do not silently convert them into a universal confidence measure or generated narrative.

Return the typed judgment with compact authorized source/receipt references and bounded metadata for coverage,
question/rubric identity and outcome. Keep source bodies, excerpts and raw command/query output out of the
calling Agent's default result. Evidence references point to actual resolved inputs or execution receipts;
Jev does not generate quotations or citations. Exact evidence, when needed, is retrieved separately through the
existing authorized source tools and read policy.

Represent unknown, insufficient evidence, refusal, partial coverage and execution/evaluation failure explicitly
through declared choices or typed status fields as appropriate. An unavailable answer is not a negative answer,
a low quality score or permission to proceed. Any human-readable label or notification text is prewritten and
rendered by the Harness from the typed result, not generated by Jev. Detailed response encoding remains
implementation work under the shared bounded-result contract.

### General judgment reuse and live-observation freshness

John selected reuse of unchanged file/evidence judgments and fresh application/SQL observations by default.
Before returning a cached judgment, verify the actual source/evidence identity and revision, complete question
and supplied context, rubric/preset, evaluator identity and applicable policy. Recheck current caller access and
source/disclosure permissions; cache possession or an earlier successful request grants no present authority.
Unknown or changed dependencies make reuse ineligible. Preserve the original evaluation time and provenance and
label reuse explicitly, with coverage reconciled to the current request rather than inherited blindly.

An immutable execution receipt may be reused to answer a question about that recorded execution; it does not
establish the current state of a running application or database. For live-state questions, obtain fresh permitted
observations through the owning API/MCP/SQL capability by default, then judge those observations. Preserve target,
observation time and replica freshness where applicable. A cached classification cannot turn an old observation
into current evidence or authorize rerunning an effectful command.

Reuse remains bounded and scope-protected under the shared evidence/retention policy. Do not cache raw source or
query bodies merely to support judgment reuse where the existing policy permits only judgment metadata and
protected references. Exact cache keys, storage, expiry and invalidation mechanisms require qualification; this
choice does not expand the first-milestone cache capacity or authorize a shared cross-principal data store.

### MCP composition under the invoking principal

John selected evidence gathering through existing MCP commands and queries available to the calling Agent under
its current permissions. The Jev-facing capability acts as an MCP tool, not as a newly authenticated privileged
Agent or a replacement principal. Carry the invoking principal's authenticated identity and applicable Agent,
assignment, repository and environment scope through every sub-command and sub-query. This is tool composition,
not creation of an authority-inheriting peer Agent session under WF-015.

Bind the identity to trusted server-side invocation/delegation context, not an Agent-supplied principal identifier
or a claim in the question. Downstream capabilities must verify the delegated identity and current authorization
for each sub-call, including revocation, resource scope and any required effect approval. Never substitute a
broader service identity or fall back to ambient credentials when delegation is unavailable. Keep credentials and
authentication material out of Jev inputs and ordinary logs; exact verified delegation transport is implementation
qualification work.

The Harness resolves and executes declared MCP sub-calls, then supplies bounded authorized evidence to the typed
evaluator. Jev does not generate shell commands or acquire permissions by selecting an answer. Existing command
contracts retain their side-effect checks, approval requirements, idempotency and uncertain-outcome handling;
wrapping a command in a judgment request changes none of those rules. Record caller identity, tool/sub-call
correlation, repository/environment, outcomes and evidence references in the existing protected audit contract.
Apply bounds to the entire composed operation as well as individual calls, preventing unbounded recursive calls
or retries. This accepts MCP composition, not an unrestricted command-string execution endpoint.

### Repository and running-application analysis

John requested safe analysis of the current repository and its associated running application, locally and in
production when the invoking principal is authorized. Distinguish static analysis of code or deployment artifacts
from runtime inspection of application state, diagnostics and data. Bind inputs and receipts to the actual
repository, deployment/environment and relevant revisions or observation times; a local checkout must not be
represented as the production version without evidence.

Expose qualified analysis capabilities through the same permission-checked MCP path. Qualify analyzer behavior,
including any code/configuration/plugin execution and filesystem or network effects, rather than declaring a
command safe solely because it is called static analysis. Runtime and database analysis must enforce the intended
read-only access plus resource/time/result limits; permitted reads must not imply arbitrary production execution
or permission to modify data. Other explicitly authorized MCP commands retain their owning effect contracts.

Access to source or production data does not automatically authorize disclosure to the Jev provider. Resolve
permitted evidence with current access checks, minimize/filter sensitive inputs, and apply the separate disclosure
policy before sending anything upstream. Preserve exact execution receipts and observations as evidence; Jev's
classification is not proof that an analyzer or query succeeded.

### Agent-controlled SQL versus application operations

John clarified that the read-replica policy applies to any capability that lets an Agent run SQL, including SQL
submitted through an MCP tool. It is not a blanket routing requirement for API or MCP business operations merely
because their implementation uses a database. Normal application API and MCP commands/queries may be backed by
the production primary under their existing domain contracts and current invoking-principal permissions; those
calls do not need a separate primary-SQL exception. Authorized business commands retain their normal mutation
semantics and effect approvals. Classify the capability by whether it exposes Agent-controlled SQL, not by its
transport or the internal use of SQL by the application.

For Agent-controlled SQL against production data, John selected a read replica as the preferred path, while
allowing explicitly authorized read-only SQL against the primary. Both SQL paths require a dedicated database
identity with enforced read-only permissions and bounded query time, resource use and result size. Preserve the
invoking principal's identity and current resource/environment authorization through the owning capability;
database credentials alone do not establish the caller's permission. Application-operation permissions do not
automatically grant Agent-controlled SQL access.

Never silently switch an Agent SQL request from replica to primary when the replica is absent, unavailable or
too stale. Primary SQL analysis requires an explicit applicable authorization and a visible selected target;
this design choice does not itself grant production access. Record the selected endpoint role and available
replication lag or observation time, and do not claim current-primary completeness when freshness is unknown.
A replica alone is not an authorization or resource-safety boundary. Exact grants, SQL controls, freshness limits
and deployment of a replica require implementation qualification. This planning record provisions no database
and executes no analysis.

### Automatic change-quality assessment and reviewer follow-up

John selected automatic change-quality assessment before the implementing Engineer's review handoff. Use the
reusable simplicity, naming clarity, understandability and semantic-convention presets against the current change
and supply the independent reviewer with compact typed results, exact evidence references and coverage/status
metadata. These results direct attention; scores are advisory and are not a pass/fail acceptance gate.

John also explicitly requested that reviewers can use the general-purpose Jev tool to sharpen the direction of
inspection. Reviewers may submit focused typed questions over authorized change units, narrower candidate sets or
additional relevant evidence, defining options and score anchors before each call. Narrowing must remain bounded
and evidence-driven, not repeated rephrasing to obtain a preferred answer. Jev selects or scores the supplied
candidates; it does not generate a review explanation or invent source locations.

The reviewer independently inspects source and verifies a suspected problem before issuing a finding. Engineer
assessment results are attributed advisory inputs, not a transferred review verdict or access to the Engineer's
private checkpoint. Reviewer follow-up uses the reviewer's own invoking principal, permissions and independent
session; no assessment grants repair or publication authority. A changed assessment subject invalidates affected
results, and unassessed or unavailable results remain explicit rather than becoming a clean-review claim.

### Missing assessment coverage at handoff

John selected proceeding to independent review when Jev is unavailable or the assessment budget is exhausted.
The automatic assessment remains part of the handoff workflow, but report failed attempts and incomplete coverage
explicitly, without a positive score or clean-assessment claim. The reviewer performs normal independent review
and can inspect unassessed areas directly. Do not raise budgets, loop on failures or require a new waiver solely
for unavailable advisory scores. Existing required tests, review findings and acceptance gates remain authoritative.

### PHPUnit test-usefulness assessment

John requested automatic assessment of added or materially changed PHPUnit tests before review handoff, plus
the same assessment across the entire owned PHPUnit suite during an architectural review with that suite scope.
Use the established [test-quality criteria](../../../docs/engineering/QUALITY.md#tests-that-protect-contracts) and
existing ST-04/SP-05 finding rules, rather than inventing a separate formal review score or acceptance standard.
The current guidance establishes qualitative criteria, not numerical good/bad cutoffs:

- Identify the observable contract and an actual plausible defect the test should detect.
- Establish an independent basis for expected results: requirements, specifications, worked examples or reviewed
  fixtures, rather than the same implementation, copied outputs/constants without a contract, or its repeated algorithm.
- Detect suspicious self-comparisons, assertions that cannot discriminate the claimed behavior, echo-only mocks,
  unreviewed snapshots and checks of incidental internals; investigate instead of declaring a defect by pattern alone.
- Assess resilience to behavior-preserving refactors while preserving legitimate wire contracts, side effects,
  absence of effects, transaction/retry ordering and independently justified boundary interactions.
- Preserve uniquely useful coverage: weak tests need a contract-grounded replacement where required, and no
  test is deleted automatically because a model flags it.

Run this as a bounded Harness assessment over test cases, related owned behavior, requirements and available
execution evidence. Jev returns predefined per-criterion answers and candidate test identifiers, with explicit
uncertain/missing-context outcomes; the reviewer verifies any claimed defect against source and evidence.
Distinguish suspected non-discriminating tests from demonstrated tautologies and from unknown usefulness.
Neither a green PHPUnit result nor a Jev choice proves that a test can never fail or will catch every defect.
Use available failing-regression and execution receipts where relevant; these reasoning checks do not require
new product tests of tests, mutation tooling or deliberately seeded failures. Any future fault-injection execution
would need its own bounded design and authority, and is not introduced by this assessment request.

For change review, bind case-level assessments to the current diff and apply the accepted affected-unit reuse
policy. For an architectural review of the entire suite, inventory all owned PHPUnit test cases, assess them in
bounded batches, and report assessed/excluded/incomplete coverage and source revisions. The accepted full-suite
mode is invoked as part of that review scope; this planning change does not run a repository-wide audit now.

Report counts and proportions by inspected criterion/outcome, verified versus unverified concerns, and coverage,
with explicit denominators, exclusions and uncertainty. These are proposed derived reporting measures for the
assessment, not claimed pre-existing numerical thresholds or an overall correctness percentage. Keep source
findings, execution evidence and model classifications distinguishable. Whole-suite analysis retains budgets,
caller permissions, safe local test-execution boundaries and protected evidence; it does not run fault injection
or PHPUnit against production or use production data as test fixtures implicitly.

### Coverage of all eligible changed code

John selected automatic assessment of all eligible changed code in bounded batches, rather than a selected
high-risk sample. Establish a revision-bound inventory of eligible changed units and track each unit's assessed,
skipped or incomplete status, with a bounded reason for gaps. Preserve per-dimension outcomes and relevant
context requirements; a missing-context unit cannot be assigned a fabricated score. Batch results must remain
traceable to the same change subject and actual evaluated inputs.

Shared payload, time, concurrency and spending limits remain authoritative. If the inventory exceeds available
capacity, report the remaining coverage explicitly rather than silently sampling, raising budgets or claiming
whole-change assessment. Surface coverage gaps in the review handoff alongside advisory scores so the reviewer
can inspect or request further evaluation within authority. This coverage target does not make scores an
acceptance gate or allow assessment output to replace independent review.

John selected maintained code as the automatic scoring scope, including tests and configuration where a rubric
applies. Exclude generated output and third-party content not deliberately modified by the project from these
quality scores; assess the maintained generators and deliberate vendor modifications where applicable. Report
exclusions and their reasons in coverage rather than treating excluded units as assessed. A changed dependency
import does not make all upstream code project-maintained. This scoring exclusion does not waive independent
review, security checks or other required verification of generated artifacts or dependency changes.

Apply dimensions only where their meaning is supported by the input; an inapplicable dimension is explicit,
not an invented low or perfect score. Exact classification signals, unit boundaries and supported file types
are implementation qualification details within this accepted eligibility policy.

### Reassessment and reuse of unchanged quality results

John selected reassessment of affected change units, with reuse of valid unchanged quality results. Before reuse,
verify that the evaluated code, relevant surrounding context and dependencies, requirements/conventions,
question/rubric and evaluator identity are unchanged, and that current caller access and applicable disclosure
policy permit use. A unit's unchanged text alone is insufficient when its meaning or requirements changed.
Unknown dependency coverage or unverifiable identity makes reuse ineligible; reassess or report incomplete status.

Rebuild coverage against the current base/head change inventory and bind any reused result to its unchanged
input identity within that inventory. Retain the original assessment revision, timestamp and provenance; clearly
label reused versus freshly evaluated units rather than rewriting old results as new evidence. Removed units do
not count toward current assessed coverage. Reviewer follow-up can request fresh assessment within existing
limits, and independent source verification remains required for findings.

This choice concerns advisory code-quality judgments, not cached execution of MCP commands, replay of operations,
a transfer of review approval or permission to reuse stale runtime/SQL observations. General-purpose reuse follows
the accepted evidence-identity and live-observation freshness policy above.

### Shared scoring anchors with repository context

John selected shared code-quality scoring anchors across repositories for simplicity, naming clarity,
understandability and semantic-convention adherence. Apply the same versioned meaning for each score level to
the supplied repository's accepted requirements, terminology and conventions. Repository context changes what
counts as an appropriate implementation or name; it does not silently redefine the scoring scale. Missing
required local context remains an explicit gap, not an assumption about the repository's standards.

Keep dimensions separate and retain rubric/evaluator versions so differences in assessments can be interpreted.
Any revised shared anchors require a new rubric identity and reassessment of affected cached judgments. Exact
per-level wording and examples require qualification across representative repositories; a common scale alone
does not prove equally calibrated scores across languages, domains or evaluator versions. This choice does not
introduce a composite acceptance score, automatic blocking threshold or a separate PHPUnit test-quality score.

## Proposed change-quality assessment — 2026-10-08

Use the shared judgment capability for merge/pull-request assessment of simplicity, naming, understandability and
convention adherence. Automatic pre-handoff use, reviewer follow-up and all-eligible-change coverage are accepted
above along with maintained-code eligibility and shared scoring anchors; exact rubric wording and presentation
remain implementation qualification details.
This accepted planning direction does not activate an acceptance gate, authorize posting review comments or
expand the six first-milestone TASKs.

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

Qualify the test-usefulness question set against independently inspected PHPUnit cases, including justified
mocks/snapshots, genuine tautologies, missing context and behavior-preserving refactors. Measure missed concerns,
false flags, reviewer effort and coverage in changed-test and whole-suite modes. This supplements the existing
qualitative test-quality criteria; final enumerations and any numerical alert thresholds require qualification.

Qualify the complete shared per-dimension anchors and presentation without hiding individual unit scores or
coverage. Qualify meaningful units, required context, maintained-code eligibility, generated/vendor exclusions,
affected-unit reassessment and unchanged-result reuse against representative historical changes with reviewed
human dispositions: include necessary complexity, clear versus misleading domain names, incomplete context
and accepted exceptions. Measure agreement/disagreement, missed concerns, false flags, stability under equivalent
formatting, reviewer effort and total context/cost/latency. Keep human disagreement visible rather than treating
one subjective rating as infallible ground truth.

Any future automatic gate requires separately accepted policy and evidence of useful reliability, including
thresholds, exception handling and false-positive/negative tradeoffs. No gate follows from adding a scoring rubric,
and neither the 0.70 relevance threshold nor the 0.95 command-guard threshold transfers to change-quality review.

## Implementation choices and qualification

The product decisions above are accepted. Deliver the general-purpose capability through the first-party MCP
boundary requested by John, reusing TICKET-00037's evaluator and shared source-resolution infrastructure.
Expose it to qualified Pi/Harness callers under their actual authenticated invocation context; a local adapter
cannot manufacture a managed identity or bypass downstream permissions. Exact public tool name, adapter wiring,
wire schemas and packaging are implementation choices, not permission to duplicate policy or broaden authority.
The six approved first-milestone TASKs remain unchanged; this follow-on needs the separate EPIC handoff below.

- Qualify Agent-authored yes/no, choice and anchored-score questions and versioned presets with explicit
  unknown/partial/failure outcomes. Distinguish semantic judgment, factual evidence and independent acceptance.
- Qualify state/path/list/glob/repository/evidence inputs and Harness-owned staged resolution through the shared
  resolver. Establish configurable aggregate limits that support economical broad coverage, plus resumable
  progress that remains bound to source, question, principal and applicable policy identity.
- Verify MCP sub-call composition, trusted principal propagation and request-fresh downstream authorization,
  including replica-preferred Agent-controlled SQL and explicitly authorized read-only primary SQL. Preserve
  normal primary-backed API/MCP business operations and their domain/effect contracts.
- Verify access and provider-disclosure checks before reading/sending evidence, source isolation, sensitive-file
  rules, revision/provenance, and bounded enumeration, payload/result size, duration, concurrency and spend.
- Qualify compact typed results, evidence references and coverage without generated explanations or default
  source-body output. Keep answer-specific probability/confidence interpretation distinct; no universal threshold.
- Verify unchanged-evidence reuse and fresh live observations, including current authority checks, stale/late
  responses, cancellation and reload. Preserve explicit stops, reconcile effects before any retry, and never
  report old results as fresh or silently replay a command. Invalid, unavailable or budget-exhausted judgments
  remain explicit; each consumer's established failure policy owns whether its work can continue.
- Qualify automatic change-quality and PHPUnit usefulness assessments, full-suite architectural-review coverage,
  shared anchors, exact case/unit mapping, eligibility, affected-unit reassessment and independently verified
  findings. Score and coverage gaps remain advisory inputs under the accepted handoff policy.
- Implement instruction/preset delivery through the existing governed Harness/skill paths, including semantic
  tool preference and unchanged specialized consumer policies. Do not add automatic delegation or new operational
  permissions merely by publishing a preset.

## Required delivery evidence

Compare representative failure classification, assumption checks and diff-risk questions with direct/structural/graph
approaches. Measure answer usefulness, unsupported conclusions, false negatives, provenance, context/cost/latency
and behavior on stale, unauthorized, malformed or incomplete evidence. Include source-contained instructions,
forged caller identifiers, lost delegation context, mid-chain permission revocation, cross-repository/environment
requests, recursive composition, unsafe analyzers and attempts to smuggle executable actions into questions.
Verify the distinction between normal primary-backed API/MCP business operations and Agent-controlled SQL,
including SQL exposed through MCP. Verify read-only SQL permissions, query/resource bounds, replica freshness
and provider-disclosure controls against the qualified integration. Actual exits/receipts and independent review retain their
acceptance roles; a Jev judgment does not certify correctness or successful execution.

## Resolution boundary

Choose a bounded first tool contract and its authority/evidence limits, identify required qualification and prepare
an EPIC-00006 handoff. Do not implement or expose tools, invoke providers, add execution permissions, replace graph
search or silently adopt optional credential-detection, injection-screening or main-model-routing candidates.

## Resolution — 2026-10-08

WF-031 is Closed following John's accepted wizard decisions and final semantic-tool preference. The contract
covers Agent-authored typed questions and shared presets, economical staged codebase investigation, authenticated
MCP composition, safe repository/application analysis, the Agent-controlled SQL replica boundary, compact typed
answers, evidence-bound reuse, advisory change-quality and PHPUnit usefulness assessments, and independent review.
Exact implementation details and empirical qualification remain future delivery work; no provider call, SQL query,
test execution, production access, tool installation or publication was performed by closing this decision.

All three follow-on decisions, WF-029, WF-030 and WF-031, are now resolved. The existing vision map owns the next
handoff to amend EPIC-00006 with these accepted contracts. Keep the original six first-milestone TASKs intact;
TICKET and TASK decomposition follow the approved EPIC handoff, not this decision closure.

Next: `/skill:grill planning/wayfinder/complete-agent-os-vision-map.md "Extend EPIC-00006 with Jev compaction and judgment workflows"`
