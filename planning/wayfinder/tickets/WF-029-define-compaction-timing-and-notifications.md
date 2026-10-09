# Define compaction timing and notifications

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-030](WF-030-define-context-retention-and-resume-contract.md)

## Question

When should the Harness recommend or initiate context compaction for each Agent and sub-agent, and how should
Jev improve timing while deterministic context limits, safe checkpoints and operator control remain authoritative?

## Proposed basis — 2026-10-07

John requested custom "should I compact" notifications to preserve useful reasoning capacity. Use deterministic
context accounting to bound operation and Jev to assess a useful work boundary or the likely relevance of prior
context to the next step. Reuse [TICKET-00037](../../tickets/00037-TICKET.md)'s shared judgment capability.
[WF-030](WF-030-define-context-retention-and-resume-contract.md) first defines what a valid checkpoint retains and
how continuation is verified. This is follow-on scope under [EPIC-00006](../../epics/00006-EPIC.md), separate from
the six approved first-milestone TASKs; no runtime compaction behavior is activated by this record.

## Related accepted direction — 2026-10-08

WF-030 was accepted and closed on 2026-10-08, so this decision is now unblocked. It supplies automatic
checkpointing and validated resume with intervention on exceptions, plus correlated
compaction/checkpoint/resume observations for later improvement. Consume that shared observability contract when
settling triggers, deferrals and notifications; retain distinct recommendation, attempt and continuation outcomes.
This decision owns initiation and exact timing; WF-030 checkpoint automation alone did not settle them.

## Accepted direction — 2026-10-08

### Automatic initiation

John selected automatic initiation by the Harness when the accepted timing policy and WF-030 checkpoint checks
permit compaction. The working Agent receives the applicable notification, and attempt/outcome observations follow
WF-030. Routine eligible compaction requires no additional human approval. Before the request tier, the Agent
may request early compaction or defer a recommendation under the accepted policy below; at the request tier,
initiation does not wait for discretionary Agent approval. Unresolved exceptions are surfaced for human
intervention under the accepted recovery rules.

Automatic initiation does not waive source/disclosure permissions, model/provider authorization, context/spending
limits, outstanding-operation reconciliation or independent-review boundaries. If the context hard limit is reached
without a safe continuation, pause further work rather than exceed the limit or discard required facts. The
accepted tiers and safe-point policy below require runtime qualification, including reserved capacity. This accepts
future behavior for a qualified profile; no live compaction or runtime setting is activated during planning.

### Staged context-pressure thresholds

John selected staged notifications ahead of native automatic compaction, with these initial targets for the
current profile: notice around 180,000 context tokens, recommend compaction around 210,000 tokens (revised from
200,000), and request compaction around 240,000 tokens. Preserve native automatic compaction as the final
fallback. The reported current native level of approximately 272,000 tokens is profile-specific context, not a
universal runtime limit or a verified setting.

This follows the staged notice/recommend/request pattern in
[Jev Level 7](https://github.com/disler/ten-levels-of-jev#level-7-should-i-compact): deterministic context accounting
owns the pressure thresholds, while bounded Jev judgments can inform work-boundary suitability and history needs.
Do not adopt the demonstration's small token thresholds or evaluation frequency as production policy.

These are planning targets subject to qualification against the effective native trigger and reserved checkpoint
and continuation capacity. Installed Pi compaction documentation defines the threshold trigger as context window
minus the resolved reserve, with per-model overrides; do not assume every model/runtime has the same window or
272,000-token fallback. Adapt other profiles through the accepted scaling policy below. Native fallback remains subject to
WF-030's checkpoint, authorization and recovery contract, and requires integration qualification.

John clarified the Agent's discretion across the tiers:

- From the notice tier (initially 180k), the Agent may request compaction at a safe point, for example before a
  substantial new work unit. Jev can inform this choice, but a positive Jev verdict is not required for an explicit
  Agent request. Merely crossing the notice threshold does not force compaction.
- From the recommendation tier (initially 210k), actively recommend compaction at a safe point and have the Agent
  give it greater consideration. A positive Jev recommendation can initiate automatic compaction when the Agent
  has not deferred and deterministic safety and WF-030 checkpoint checks pass. The Agent may also request it
  directly. No additional human approval is needed.
- Between recommendation and request (initially 210k–240k), the Agent may snooze the recommendation based on how
  deep it is in the current work. Record the deferral and reason in observability; do not misreport it as failed
  or completed compaction. Continued work remains subject to context limits and reserved checkpoint capacity.
  The deferral expires no later than the request tier, and the Agent may end it sooner at a suitable boundary.

A snooze defers early initiation, not context accounting or the accepted Jev evaluation cadence. Suppress repetitive
notices while retaining stage and deferral state. Neither a snooze nor a Jev defer/uncertain verdict extends task
execution beyond the request-tier boundary. Use the predefined timing judgment contract and accepted fallback
below; never interpret an unavailable judgment as a positive recommendation. Exact deferral encoding and runtime
coordination require qualification. This clarification supersedes the earlier prohibition on Agent-initiated
compaction during the notice tier.

John selected automatic compaction at the next safe point once the 240,000-token request threshold is reached:
finish in-flight work, create and validate the WF-030 checkpoint, then compact and validate continuation. Routine
initiation does not wait for Agent or human approval. Jev cannot veto the deterministic request indefinitely;
unsafe or unresolved operations follow WF-030 recovery rules rather than being replayed or silently dropped.
At the request threshold, John selected finishing and reconciling already in-flight operations, then checkpointing
and compacting before starting another work step. Do not extend execution with additional task work merely to
finish a larger work unit. Necessary reconciliation and checkpoint actions remain subject to existing authority
and reserved capacity; uncertain external effects must not be retried automatically. If a safe checkpoint cannot
be reached within the available capacity, preserve recoverable state and surface the WF-030 intervention path.
Exact runtime detection and enforcement of this boundary require qualification. No active runtime settings are
changed here.

### Automatic profile scaling with overrides

John selected automatic scaling for other model/runtime profiles, with configurable overrides. Derive the staged
thresholds from the profile's effective native automatic-compaction trigger, preserving similar relative spacing
to the initial 180k/210k/240k targets while leaving sufficient capacity for checkpoint creation and continuation.
The reported 272k reference is an initial planning baseline to verify, not a hardcoded runtime fact. Exact ratios,
rounding and reserve calculations belong to implementation qualification.

Resolve the actual model/runtime limits and native settings before applying a profile. Overrides must preserve
ordered notice/recommend/request thresholds and the required safety margin; they cannot authorize exceeding
context limits or removing checkpoint capacity. If the effective trigger or required reserve cannot be established,
do not invent scaled thresholds. Use the accepted verified-native fallback policy below when custom accounting
or profile limits cannot be established reliably.
Re-evaluate the applicable profile on a model or relevant settings change; scaling itself does not authorize a
model/provider switch. Record the resolved profile, thresholds and policy version through the shared observability
contract so later analysis can explain why a tier or compaction was triggered.

### Evaluation after each completed turn above the notice threshold

John revised the earlier work-boundary/cooldown cadence: once the notice threshold is reached (initially 180k),
evaluate with Jev after every completed Agent turn. Use the corresponding scaled notice threshold for other
profiles. A meaningful work boundary remains evidence for the judgment, rather than a prerequisite for calling
Jev. A completed turn alone does not establish a safe compaction point.

Between notice and recommendation (initially 180k–210k), Jev judgments inform the Agent's optional early-compaction
choice; an explicit Agent request can initiate safe compaction. Between recommendation and request (initially
210k–240k), a positive recommendation can initiate compaction unless the Agent has deferred, and the Agent may
request compaction directly. All paths retain safety and checkpoint checks. At the request threshold,
deterministic initiation at the next safe point does not wait for Jev or depend on a positive verdict or snooze.

Do not use a time cooldown to skip otherwise eligible completed turns. Suppress duplicate evaluations for the
same completed turn, including duplicate hook delivery; bound concurrency and apply the shared judgment
cost/latency limits. Evaluation frequency and notification frequency are distinct: repeated unchanged verdicts
must not generate repetitive notices. If evaluation is unavailable or budget-limited, record that condition rather
than inventing a verdict; deterministic context accounting and threshold escalation remain active. Detailed
fallback behavior and exact runtime turn-completion hooks remain to qualify. No active runtime behavior changes
as part of this planning revision.

### Routine visibility and intervention alerts

John selected notices to the working Agent plus quiet session status for routine compaction stages. Direct
human alerts are reserved for conditions requiring intervention, rather than each notice/recommend/request tier.
Record all stages through WF-030's correlated observability contract, including evaluations, recommendations,
deferrals, compaction attempts and continuation outcomes. Repeated unchanged evaluations do not repeat notices.

Quiet status must distinguish a recommendation, a pending safe point, an attempt and a completed continuation;
it must not present an attempted or failed compaction as successful. Preserve the existing sanitized metadata and
access boundaries. Exact supported Agent notification and session-status surfaces require runtime qualification;
this policy does not imply that unsupported child sessions already expose those capabilities.

### Independent Agent and sub-agent sessions

John selected independent compaction for each Agent session, including sub-agents. Apply the accepted tiers,
evaluation cadence and safe-point rules to that session's own context usage and resolved model/runtime profile.
Routine eligible compaction does not require parent approval. Send authorized status updates to the parent using
the accepted quiet-status policy and retain parent/child correlation in observability.

Independence does not grant new spending authority or remove shared judgment limits. Attribute usage to the
owning session under the applicable budget policy; qualify aggregate limits and concurrency across child sessions.
Preserve WF-030's private checkpoint and independent-review boundaries: parent status does not disclose private
checkpoint contents or authorize one Agent to resume another Agent's context. Qualify accounting, hooks and
notification delivery separately for supported child-session paths, and explicitly surface unsupported paths.

### Deterministic fallback when Jev is unavailable

John selected continued operation under deterministic thresholds when Jev is unavailable or its evaluation
budget is exhausted. An outage, refusal, timeout or unusable response supplies no positive recommendation:
skip Jev-assisted early compaction, retain the request-tier automatic compaction at the next safe point, and
preserve native automatic compaction as the final fallback. Apply WF-030 checkpoint and continuation checks
throughout; this does not permit proceeding without a safe checkpoint or exceeding context limits. Jev failure
does not remove the Agent's accepted ability to request early compaction through a qualified compaction path.

Record the unavailable or budget-limited evaluator and the selected fallback in correlated observability and quiet
session status. An evaluator failure alone does not require human intervention if the qualified deterministic
path remains usable. Shared retry, concurrency and spending limits remain authoritative; do not retry repeatedly
or increase a budget to restore advisory evaluation. Missing or stale context accounting is a separate condition
from evaluator failure and uses the verified-native fallback policy below.

### Verified native fallback for unreliable accounting

John selected verified native compaction when current context usage or model/runtime limits cannot be determined
reliably. Suspend custom tiers and usage-dependent Jev initiation rather than inventing counts, trusting stale
estimates or applying guessed thresholds. Show the limitation in quiet session status and record the reason and
fallback path in observability.

Continue only where the native path is qualified for the active runtime/profile and can preserve WF-030's
checkpoint, authorization and continuation contract. Native capability must be established independently of the
missing custom accounting; merely assuming that every runtime compacts safely is insufficient. If safe native
continuation cannot be established, preserve recoverable state and pause for intervention. Re-enable custom tiers
only after current accounting and the applicable profile have been verified again. This accepts a fallback policy,
not a claim that any currently unqualified integration already supports it.

### Predefined timing decisions and reason choices

John selected a compact/defer/uncertain judgment and clarified that Jev cannot generate a free-form brief reason:
the caller must author the available choices first. Define and version the decision choices and their criteria,
and any reason choices, before dispatch through TICKET-00037. Jev selects from those declared options; the Harness
renders prewritten notification text from the selected labels. Do not require generated explanation prose or
represent a selected reason label as a model-authored explanation or proof of causality.

Base the bounded questions on completed work, dependence on earlier history and unfinished operations. Possible
reason labels include work-unit-complete, prior-history-needed, operation-unfinished and insufficient-evidence;
these illustrate the intended contract, while the final finite vocabulary and question wording require
qualification. Include an uncertainty choice rather than forcing an unsupported positive judgment. Invalid or
contradictory answers cannot become a positive compaction recommendation.

Do not introduce a new readiness score or borrow the file-read or tool-guard confidence threshold. A compact
selection informs the Agent's optional choice during the notice tier and can initiate earlier compaction from the
recommendation tier when no Agent deferral applies and deterministic safety and WF-030 checkpoint checks pass.
Explicit Agent requests remain eligible from the notice tier. Defer or uncertain cannot veto the request-tier
deterministic policy. Record selected option identities and question/rubric versions through the
existing observability contract; exact wire encoding and policy qualification remain implementation work.

### Compaction while awaiting a human answer

John selected safe compaction while an Agent is waiting for a human answer. Preserve the exact pending question,
its identity, necessary decision context and unanswered status in the WF-030 checkpoint, then return to the same
waiting state after validated compaction. The pending question does not itself defer otherwise eligible
compaction. Compaction neither answers the question nor grants approval for dependent work.

Reconcile any reply arriving during checkpoint creation or compaction with the authoritative question state
before resuming dependent work. Do not lose, duplicate or invent replies, or treat the compaction event as an
answer. Existing operation reconciliation, authorization and checkpoint validation requirements still apply;
qualification must cover replies arriving during the transition.

### Verified recovery after interruption or reload

John selected automatic recovery after interrupted compaction or runtime reload when the saved state can be
verified. Reconcile the durable checkpoint/activation state, pending operations and questions, current context
accounting and the applicable runtime profile before resuming. Discard stale or late Jev judgments tied to the
previous state; neither a reload nor a late response may initiate a duplicate compaction or replay an operation.
Use WF-030's bounded recovery and resume validation, preserving the previous recoverable state until a valid
replacement is activated. A completed compaction must be recognized rather than blindly attempted again.

Honor explicit human stops and cancellations: automatic recovery does not authorize restarting stopped work.
On an authorized later resume, re-evaluate current state and tier rather than reuse stale eligibility or reset a
request-tier obligation. Preserve a valid snooze only within its accepted scope and expiry; it cannot survive past
the request threshold. If authoritative state cannot be reconciled within the accepted recovery limits, preserve
recoverable state and escalate for intervention. Record interrupted, recovered, cancelled and blocked outcomes
separately under the shared observability contract. Harmless reload recovery does not override WF-009's stricter
rules for governing instruction or policy drift, or WF-030's independent-review boundaries.

## Implementation choices and qualification

The product decisions above are accepted. The following are delivery details and required evidence, rather than
additional unresolved product choices:

- Establish the context-accounting source and current usage for each supported runtime/profile, distinguish
  measured and estimated values, and qualify effective native limits, scaled thresholds, rounding and reserved
  checkpoint/continuation capacity. Apply the accepted unreliable-accounting fallback rather than guessed limits.
- Qualify bounded inputs and the predefined compact/defer/uncertain question and caller-authored reason choices
  against representative work boundaries; do not require Jev to generate free-form explanations.
- Qualify the finish-and-reconcile boundary for in-flight operations and preserved waiting state for pending
  human questions, including replies arriving during compaction. Preserve review boundaries without losing
  decisions or replaying operations.
- Qualify session-attributed and aggregate judgment budgets, independent triggers, parent status and instruction
  delivery for each supported child-session path; explicitly identify unsupported runtime capabilities.
- Qualify Agent-requested compaction from notice, recommendation-tier snoozing that expires at request, and shared
  cost/latency ceilings. Verify every-completed-turn evaluation, duplicate suppression and quiet notification
  surfaces without excessive repeated context or spend.
- Verify interruption, cancellation, reload and late-result behavior, including saved-state reconciliation,
  preserved stops, request-tier enforcement and bounded recovery. Verify native fallback for unreliable accounting
  and deterministic fallback for unavailable Jev, without inventing a verdict or exceeding a hard limit.
- Start with the existing Pi Harness integration and identify exact qualified runtime versions, actual hooks,
  compaction/continuation semantics and supported local versus managed child-session paths. No accepted policy
  alone establishes a runtime capability or live acceptance result.

## Required delivery evidence

Compare deterministic-only timing with bounded Jev recommendations across short/long parent and child sessions.
Measure completion quality, lost/repeated work, checkpoint cost, total context/cost/latency and notification burden.
Include in-flight effects, unanswered human decisions, evaluator outage, unknown token counts and reload. Do not
claim an optimal intelligence level merely because a notification or compaction completed.

## Resolution boundary

Set timing, notification/automation authority and failure behavior using WF-030's checkpoint contract, then identify
bounded EPIC-00006 amendments and qualification work. Do not compact live sessions, change active prompts, select
production thresholds without evidence, launch Agents or decompose implementation TASKs in this decision.

## Resolution — 2026-10-08

WF-029 is Closed following John's accepted wizard decisions and final recovery selection. The policy combines
optional Agent-requested compaction from notice, stronger recommendation and bounded Agent deferral from the
recommendation tier, and deterministic safe-point compaction at the request tier. Every-turn Jev evaluation above
notice, predefined choice outputs, profile scaling, independent sessions, quiet visibility, correlated observability,
native/deterministic fallbacks, preserved pending questions and verified recovery are accepted. Numeric/runtime
qualification and implementation evidence remain future delivery work; no live compaction is enabled here.

[WF-031 — Define general-purpose bounded judgments](WF-031-define-general-purpose-bounded-judgments.md) is the
next open decision in the vision map. After that decision, the map's approved follow-on handoff can reconcile
WF-029, WF-030 and WF-031 into EPIC-00006. This closure does not itself create requirements/TASKs, expand the six
first-milestone TASKs, commit or publish changes.

Next: `/skill:wayfinder work WF-031`
