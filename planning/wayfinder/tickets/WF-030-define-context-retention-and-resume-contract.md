# Define context retention and resume contract

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** —

## Question

What must survive compaction, how may Jev help select additional useful context, and what must be revalidated before
an Agent or sub-agent resumes work from the resulting checkpoint?

## Proposed basis — 2026-10-07

John's context-efficiency direction calls for smaller continuation context without losing objectives, constraints
or evidence. Use Jev to rank optional supplied context segments under a bounded question, while deterministic
requirements preserve the mandatory checkpoint facts. Reuse
[TICKET-00037](../../tickets/00037-TICKET.md)'s shared judgment capability and the accepted context/authority
boundaries. This belongs to [EPIC-00006](../../epics/00006-EPIC.md), independently of durable memory implementation.

## Accepted direction — 2026-10-08

John selected automatic checkpoint creation and validated resume, with human intervention on exceptions. Normal
checkpoints do not require a human sign-off or a separate reviewing Agent. The working Agent drafts the checkpoint;
the Harness validates required fields, source/evidence references and outstanding-operation state, and rechecks
current access and assignment before consequential work resumes. Structural validation cannot prove semantic
completeness; preserve protected source/evidence references for authorized recovery of omissions. Missing required
facts, conflicting state and unresolved effects prevent the affected continuation until reconciled; surface cases
that need human judgment. The accepted continuation contract below governs subsequent implementation and qualification.
WF-029 separately decides compaction triggers and initiation authority; this choice does not enable live compaction.

### Required facts and question-first source retrieval

John selected concise required facts plus source references. Carry the objective and current TASK/assignment,
accepted decisions and constraints, completed/remaining work, unanswered human questions, outstanding or uncertain
operations, relevant source revisions, evidence references and next action directly in the checkpoint. Distinguish
facts, proposals and uncertainty. References identify authoritative sources and exact evidence subjects; a summary
is not a substitute authority record or proof of an operation's success. Keep full supporting detail behind those
references rather than embedding source files in the checkpoint.

John also requires the resumed Agent to formulate the information need and ask a bounded question about referenced
sources before ingesting those files again. Reuse [TICKET-00039](../../tickets/00039-TICKET.md)'s `jev_read_file`
policy: screen the specific path or bounded candidate set with a yes/no or declared relevance-class question,
then read only the necessary source range when the accepted positive-read bar is met. Do not bulk-reload referenced
files just because they appear in a checkpoint. Prior reference membership alone is not a known-target exception;
reassess the present question, source identity and need. A complete useful typed judgment may avoid a source read
when exact-source evidence is not required, but it cannot invent quotations, certify execution or prove absence.

Preserve the accepted mandatory-governing-instruction, evidence-backed known-target and required-exact-evidence
exceptions, with path, purpose and establishing evidence/obligation recorded. A reference used for a required
verification may qualify for that bounded exception; it does not exempt the entire reference set. Current access
and provider-disclosure checks apply before screening. Jev still reads authorized source for evaluation; the saving
is reduced source ingestion into the main Agent's context, not zero source reads or guaranteed total cost savings.
Unknown, negative, partial or failed screening does not prove the source irrelevant or permit unrestricted reads;
use the accepted bounded search/required-evidence paths and surface unresolved required facts under the recovery
policy. Missing instructions, denied access or stale evidence cannot be waived merely to keep the checkpoint small.

### Required-context overflow

John agreed to remove optional context first, then allow a larger continuation budget within configured runtime,
context and spending limits. If required continuation facts still cannot fit, preserve the last recoverable state
and request intervention. Never silently drop constraints, pending questions or outstanding operations, exceed a
hard limit, or change models/providers merely to fit. Reserve space for governing instructions and the next useful
step; a checkpoint that fits by consuming all continuation capacity is not a usable resume. Exact budget sizing
and accounting remain qualification details; WF-029 owns advance timing and capacity-reservation triggers.

### Jev-unavailable fallback

John chose automatic fallback and clarified that it should reuse Pi's existing native compaction rather than
introduce a separate recent-context selection algorithm. On unavailable, refused or unusable optional Jev
selection, use the qualified native summarizer/recent-message retention path, record the fallback reason and
continue only after mandatory checkpoint and resume checks pass. Do not discard the separately required facts
because the native summary omits them. Native compaction is itself model-assisted; the fallback routing policy
is deterministic, not the summary, and this is not an offline or zero-cost guarantee. Preserve configured provider
authorization, disclosure and spending limits without choosing an unapproved provider/model.

If native compaction fails, is unsupported by the qualified integration, or cannot preserve the mandatory
continuation contract within limits, preserve recoverable state and apply the exception/overflow rules. No blind
compaction retries or automatic replay of uncertain tools follows from selecting native fallback. A Jev outage
alone does not require human intervention when the native path and all required checks succeed.

On 2026-10-08, the installed managed Pi package identified itself as version 1.1.0. Its bundled compaction reference
describes summarizing earlier messages, persisting a compaction entry and rebuilding context from the summary plus
retained recent messages; the [upstream compaction reference](https://github.com/earendil-works/pi/blob/main/packages/coding-agent/docs/compaction.md#how-it-works)
also documents this behavior. This is documentation inspection, not execution qualification. Implementation must
pin and qualify the actual runtime, native/custom hook interaction, mandatory-fact preservation and usage/failure
observations. No live session was compacted or personal settings changed by this planning decision.

### Recovery of missing required checkpoint information

John selected bounded automatic recovery before human intervention when validation detects missing required
checkpoint information. Consult currently authorized source records and evidence, repair the candidate checkpoint
with attributable references, then validate again. Preserve the prior recoverable state until the replacement
passes validation; a repair attempt is not a successful resume. Source facts, proposals and unresolved uncertainty
remain distinct, and structural validation still cannot prove semantic completeness.

Apply explicit attempt, time, context and spending limits; record the detected gap, recovery attempt and result in
the accepted observability history. Do not loop indefinitely, broaden access, fabricate absent facts, reinterpret
approval or replay an external operation to reconstruct its outcome. If authoritative information is inaccessible,
conflicting or still missing, or the recovery budget is exhausted, surface the precise unresolved issue for human
intervention and keep the affected continuation blocked. Exact numeric recovery ceilings remain implementation
qualification details under this bounded policy.

### Optional-context classification and ranking

John selected a combination of categories and usefulness ranking. Supply bounded, authorized optional context
segments identified by stable IDs and exact source revisions/digests, together with the objective and next work
boundary. Jev classifies each segment as helpful now, possibly useful later, or unnecessary for the current
continuation, then assesses relative usefulness within each category under a versioned rubric. Preserve typed
category and ranking judgments; confidence is not itself usefulness or a guarantee of recall.

The Harness applies the result within the remaining context budget after mandatory facts, governing instructions
and continuation capacity are reserved: consider helpful-now segments first in ranked order, then possibly-useful-
later segments if space remains. Omit unnecessary optional segments from the continuation, without deleting their
underlying protected source history or changing its retention policy. Retention decisions and budget-driven skips
remain inspectable through the accepted bounded observations. Required facts never enter this selection pool.

Do not impose a universal confidence cutoff or inherit the 0.70 file-relevance or 0.95 command-guard thresholds.
Unknown, missing or malformed classifications/ranks remain unresolved rather than becoming unnecessary; use the
accepted native-compaction fallback when selection is unusable. Implementation must qualify segment boundaries,
ranking representation, stable tie handling, completeness and budget accounting without silently truncating a
segment or treating a partial evaluation as full coverage. Provider disclosure, payload and spend limits remain
binding; the ranking tool cannot acquire broader context or hidden/private scopes to improve its answer.

### Approval continuity across compaction

John selected preservation of an existing approval when it remains valid. Compaction alone does not revoke an
approval or require the human to approve the same unchanged action again. Retain an attributable reference to the
original approval and its subject/scope; before use, verify current authority, applicable expiry and revisions,
unchanged action/arguments, and unconsumed status where the approval is single-use. A checkpoint summary claiming
approval is not the approval record and cannot broaden its subject or transfer it to another actor/session unless
the original authorization permits that continuation.

If execution or approval consumption is uncertain, reconcile against the owning operation/approval evidence before
proceeding. Do not execute again merely because the new context lacks a completion message. Expired, revoked,
consumed or materially changed approvals cannot be reused. Missing evidence follows the accepted bounded recovery
policy, with intervention if still unresolved. Qualify actual approval validation and single-use consumption in the
supported local or managed integration; a prose reminder alone does not provide atomic consumption enforcement.

### Reconciliation of changed supporting sources

John selected automatic reconciliation of supporting sources that changed since the checkpoint, within the
existing assignment and authority. Detect revision/digest drift, use the accepted question-first retrieval and
bounded exact-evidence exceptions to inspect relevant changes, update affected continuation facts with provenance,
and validate again before relying on them. Do not bulk-refresh every reference or rewrite historical snapshots.

Escalate conflicting facts, changed scope and material changes that invalidate approval or verification; a prior
approval, test receipt or review verdict cannot silently transfer to a changed subject. Required checkpoint
information that cannot be safely reconciled follows the bounded recovery/exception rules. Governing instruction
and policy drift retains WF-009's stricter pinned-context rules, and current permissions may narrow or revoke
access immediately. This choice does not authorize silent adoption of new instructions or expanded permissions.

### Checkpoint retention

John selected the existing WF-017 retention policy for checkpoint contents and resume-only material. Keep them
available while work is active, paused, recoverable or Needs Human. After terminal completion, cancellation or
failure, apply the existing configurable cleanup grace period, initially 30 days. Evidence, recovery, security or
other explicit holds prevent premature deletion; authorized retention extensions or early deletion follow the
same established rules. Do not introduce indefinite checkpoint retention or a separate longer default merely for
compaction analysis. Durable audit metadata and usage totals retain their existing history categories.

This policy does not authorize cleanup during planning. Missing, purged or corrupt referenced contents remain
explicit during recovery and historical investigation; a metadata record alone does not imply recoverable bytes.
Privacy restrictions and prohibited-sensitive-content handling continue to apply even when a retention hold exists.

### Compaction observability and improvement

John requested historical observability so compaction behavior can be investigated and improved. Reuse
[WF-017's execution history and observations](WF-017-define-execution-history-and-event-authority.md), preserving
its separation between protected Pi context, sanitized observations and application-owned Workflow transitions.

- Correlate each compaction attempt and checkpoint revision with its session/conversation lineage, predecessor,
  Agent/role, and parent-child relationship where authorized; include TASK, Workflow, phase and process identities
  when present. Local human-operated sessions must not invent managed Workflow identities.
- Record the lifecycle: requested or recommended, deferred or cancelled, checkpoint creation/validation, compaction
  outcome, resume validation and resumed/blocked/failed outcome. Include available timestamps, bounded reason
  categories, initiating actor and automatic/manual mode. WF-029 supplies trigger and deferral policy; a model
  recommendation, saved checkpoint and successful continuation are distinct observations.
- Retain policy/rubric/Harness/runtime/model versions, input/checkpoint digests and protected references. Record
  required-field validation and optional segment identifiers, inclusion/exclusion decisions and available Jev
  judgments. Selection detail is bounded and access-controlled; no raw segment bodies enter ordinary telemetry.
- Record available before/after context counts and accounting source, identifying estimates versus measured values;
  checkpoint and Jev usage/cost/latency separately where available, deterministic fallback and incomplete coverage.
  Unknown values stay unknown. Correlate stable usage identities to avoid double counting; smaller context alone
  does not establish lower total cost, better recall or better task outcomes.
- Support later attributed annotations for lost constraints, missed questions, repeated work, recovered context and
  uncertain/replayed operations, with authorized evidence references. Distinguish observed incidents and human or
  Agent assessments from proven causes; association with compaction is not proof compaction caused a failure.
  Keep corrections attributable rather than rewriting the original outcome.
- Allow bounded per-session inspection and aggregate comparisons of policy versions, fallback/blocked-resume rates,
  context reduction, available total cost/latency and reported recovery incidents. Respect scope and reviewer
  independence across parent/child sessions; visibility into one session grants no access to another's context.
- Preserve WF-017's privacy and retention categories. Do not ingest raw conversations, checkpoint prose, hidden
  reasoning, credentials or full tool output into the Dashboard or ordinary logs. Authorized investigation resolves
  protected references through the owning context/evidence capability; missing or purged material is explicit.
- Telemetry may be missing, delayed or duplicated and cannot authorize resume or become its sole recovery record.
  Display incomplete history honestly; failure to persist the required recoverable checkpoint is a separate safety
  failure from delayed observational delivery. Exact storage, delivery and validation mechanics remain implementation details.

These are planning requirements, not existing observability capabilities. Qualify actual runtime coverage and
measure improvement using representative continuation outcomes, including cases with missing telemetry.

## Resolution — accepted 2026-10-08

John accepted and closed WF-030 on 2026-10-08. The choices above and the consolidated contract below constitute
the decision, preserving existing WF-009/WF-017 authority, conversation and history boundaries. Closure accepts
the design; implementation and runtime qualification remain outstanding.

- **Protected per-conversation checkpoint:** retain Pi as the owner of resumable model context. Bind a versioned
  checkpoint to the exact session lineage/branch, source boundary, assignment, role and applicable context/policy
  versions. Store any additional required facts through protected session-linked storage, not repository files,
  ordinary telemetry or a new planning database. Managed services retain authorized opaque references and necessary
  metadata under WF-017; native entry versus companion representation remains an implementation choice.
- **Recoverable creation:** the Agent drafts concise semantic facts; the Harness captures/checks available
  authoritative identity, approval and operation state, required fields, source references and declared gaps.
  Persist a complete validated candidate before treating it as usable and retain the prior recoverable state
  through activation. Interrupted creation, corrupt bytes or failed activation never become successful resume.
  Corrections produce attributable revisions. Field completeness does not prove all relevant meaning was retained.
- **Resume checks:** verify checkpoint integrity and binding, governing instructions and selected skills, current
  permissions and applicable assignment/claim/lease state, source/evidence subjects, required facts and outstanding
  operations. Qualify local versus managed capabilities explicitly. Preserve pinned context under WF-009: meaningful
  instruction/policy drift stops new authority-bearing operations and requires the established linked-session path,
  while harmless reloads and ordinary source reconciliation follow their accepted rules.
- **Outstanding operations and questions:** retain operation and question identities and their last verified state.
  Reconcile live completion, approval consumption, billing uncertainty and human replies before dependent action.
  Known completed effects are not repeated; unknown outcomes never become success or automatic retry. Unanswered
  questions remain pending rather than acquiring invented answers. Timing around live tools belongs to WF-029;
  a checkpoint must describe unresolved work honestly whenever it is created.
- **Agent separation:** each Agent/sub-agent owns its permitted checkpoint; parent links carry only authorized
  handoff facts and status. No role resumes another role's private context. Retain contribution history across
  compaction and linked successors; independent Review attempts keep fresh conversations under WF-017. Checkpointing
  does not promote content into broader memory or depend on the future memory implementation.
- **Disclosure and observability:** apply the accepted provider/source permissions and bounded payload, time and
  spending rules to optional selection and recovery. Unknown/unsupported input remains explicit. Preserve scoped
  protected references, sanitized observations and the selected retention rules; no broad transcript/hidden-scope
  ingestion or operational authority through telemetry. Missing telemetry is distinct from missing durable recovery
  state, and no observability outage can manufacture permission or a successful checkpoint.

## Delegated implementation and qualification

Exact serialization, native/companion storage mechanics, schema versions, segment sizes, rank encoding, stable tie
handling, token accounting, monetary/time/attempt ceilings, adapter hooks and runtime-specific validation belong
to subsequent requirements and implementation under these boundaries. Qualify the actual supported Pi version;
unsupported lifecycle or enforcement behavior cannot be advertised as a protected profile. No numerical threshold
or complete semantic-recall guarantee follows from this planning decision.

## Required implementation qualification

Trace mandatory facts through representative compaction/resume scenarios, including conflicting source revisions,
revoked access, pending questions, uncertain effects and separated parent/child or reviewer contexts. Compare optional
Jev selection with deterministic retention for useful recall, context size, cost/latency and lost/repeated work.
Checkpoints must retain inspectable provenance; successful summarization is not proof of successful continuation.
Verify that continuation uses question-first source retrieval, useful typed answers and minimal exact-source reads
without bulk reloading references or losing required facts to false-negative/unavailable screening.
Trace correlated lifecycle and usage observations through failures, missing/duplicate delivery and recovery. Confirm
that later incident annotations and authorized source references permit investigation without transcript ingestion
or unsupported claims of causal improvement.

## Resolution boundary

Set the checkpoint and resume contract that [WF-029](WF-029-define-compaction-timing-and-notifications.md) consumes.
Memory ownership/promotion stay with WF-024/WF-025 and memory retrieval with WF-026; this decision does not require
those implementations or grant memory writes. Do not compact live sessions, edit personal memory, change current
runtime behavior or create implementation records while resolving this decision.

## Decision closeout and handoff

WF-030 is Closed by John's explicit selection of the final contract. Required context plus protected references,
question-first retrieval, category/rank selection, native Pi fallback, bounded recovery and source reconciliation,
approval continuity, retention and compaction observability are accepted. No unresolved product choice remains
inside this decision; the listed technical choices and measured qualification belong to subsequent delivery.

[WF-029](WF-029-define-compaction-timing-and-notifications.md) is now unblocked and owns timing, notifications and
automatic-initiation authority. WF-031 remains independently open. This contract is an accepted input to the future
EPIC-00006 follow-on requirements handoff; no implementation TICKET/TASK is created or existing first-milestone TASK
expanded here. No live compaction, provider call, runtime change, publication or merge is performed by this closure.

Next: `/skill:wayfinder work WF-029`
