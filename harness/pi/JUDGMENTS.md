# Bounded typed judgments

TASK-00159 implements the shared local capability for trusted TICKET-00038 tool guards and TICKET-00039 file
screening. Controlled tests, real Pi 1.2.0 SDK lifecycle evidence and **one authorized live synthetic mixed-form
qualification** are available. Independent Review/QA remain pending. TASK-00184's earlier fixed yes/no check is
inherited evidence, not qualification of these forms.
No generic Agent-callable judgment tool is installed. The [single-file consumer](FILE_SCREENING.md) separately
owns `jev_read_file`, its access/disclosure policy and relevance threshold. This shared layer supplies no guard,
file-read permission, relevance threshold, sandbox, Workflow transition, savings estimate or model-accuracy claim.

## Configure and connect

The operator uses [Jev setup](README.md#jev-setup-and-readiness-operator-only) to select the pinned connection,
confirm current pricing and set explicit request/session ceilings. Existing `fixed-synthetic-only` settings
continue to refuse consumer-supplied state. Enabling `authorized-supplied-state` sets the operator's maximum
permitted disclosure; it does **not** grant a consumer permission to disclose any particular source or authorize
a paid qualification. Setup never resolves credentials or dispatches requests.

A trusted in-process Pi extension connects through `pi.events` after `session_start`:

```ts
import type { ConsumerConnection } from './jev/judgments.mjs';

const connection: ConsumerConnection = {
  callerId: 'file-screen-v1',
  authorize: context => currentDisclosurePolicy.permits(context),
  connected: capability => {
    // Retain only for this session/runtime. An unavailable connection is not a judgment.
    if (capability.state !== 'available') return;
    useSessionJudgments(capability);
  },
};
pi.events.emit('fight:jev:connect', connection);
```

`currentDisclosurePolicy` and `useSessionJudgments` above belong to the consumer, not this package. The callback
must synchronously return exactly `true` only when **current** authority permits the caller, actual Pi session,
source identity/digest, policy, question/request digests, configuration and explicit spending allocation.
False, thrown errors, promises and other values deny disclosure. Checks run at admission, after credential
resolution and after the provider returns. Never replace them with unconditional approval in a real consumer.
Source-read permission alone is insufficient. Consumer metadata must be nonsecret identifiers, not prose,
paths containing secrets or credential values. This is a same-process trust boundary, not a security boundary
against malicious extensions or same-user tools. The event cannot be invoked by an Agent through a registered tool.

An `available` connection only means local configuration was loaded, **not** that authentication, billing,
provider availability or live form qualification succeeded. It supplies `configuration.identity` and
`evaluate(request, { signal? })` / `inspect(count?)`. Credentials never appear in that interface. The default
adapter resolves only Pi-managed OpenRouter through the existing protected boundary, on admitted evaluation.
The main Agent model is neither read for routing nor changed.

## Request contract

The exported declarations in `src/jev/judgments.d.mts` define the discriminated question/answer types. Submit:

- `version: 'fight-judgments-v1'`, the connection's exact `configuration` identity, current `authorityRevision`
  and consumer-owned `policyVersion`.
- `source: { id, revision }`, supplied by the authorized source operation, plus its bounded JSON `state`.
  The service computes SHA-256 over the exact serialized state; it does not enumerate/read files or verify the
  caller's external revision claim. It also hashes the questions and actual provider request.
- Positive integer `requestMicroUsd` and `sessionMicroUsd`, no larger than operator ceilings, with request no
  larger than session. A source snapshot and questions are copied before asynchronous work. Endpoint, evaluator,
  provider routing, price maxima and retries cannot be overridden by the request or source text.
- One to eight `questions`, each with a unique `id`, `version`, `rubricVersion` and nonempty `instructions`:

| Type | Additional input | Validated output and meaning |
|---|---|---|
| `yes-no` | None | `yesProbability` in [0,1], the probability of **Yes**, not classification confidence or an automatic boolean |
| `choice` | 2–16 `choices` mapping identifiers to descriptions, and a declared `unknownChoice` identifier | A declared winning `choice`, required [0,1] `confidence` and complete normalized probabilities; confidence is not the winning probability |
| `score` | 2–10 ordered `levels`, finite `min < max` with a finite span | The provider's probability-weighted zero-based level index, its required confidence/distribution, and a score affinely mapped to the requested [min,max] anchored scale |

Example score: three levels `['Cold', 'Warm', 'Hot']` with requested bounds -10 to 10 map native indices 0, 1, 2
to -10, 0, 10. A native weighted index 1.5 maps to 5. This is a scale transformation, not a probability of
correctness. Provider score probabilities and legend must cover every declared level, legend descriptions must
match, and the weighted mean must agree with `score` within 0.000001. Distributions must sum to one within that
same absolute tolerance. Unsupported/missing measures are invalid, not defaulted. The pinned evaluator supplied
these strict measures in the authorized synthetic batch below; future missing/malformed measures still fail closed.
Provider-generated explanations/quotations are never returned.

Identifiers are 1–128 characters in `[A-Za-z0-9._:/-]`, starting with an alphanumeric character. Inputs must be
lossless plain JSON data: no non-finite numbers, undefined values, accessors, custom serializers, sparse arrays,
cycles or prototype-related keys. Nested depth is at most 32. Both the complete serialized consumer request and
the provider payload must be at most 65,536 UTF-8 bytes. The transport also caps responses at 65,536 bytes and
rejects invalid UTF-8, malformed JSON and duplicate member names, including escaped-equivalent names.

## Results and failures

`evaluate` returns `{ state, answers, receipt }`. Only `complete` contains a wholly answered batch. An explicitly
selected `unknownChoice` yields `incomplete`, with that answer marked `unknown`; consumers cannot treat it as an
ordinary positive/negative classification. Missing, duplicate, mismatched, undeclared, non-finite, out-of-range
or otherwise invalid answers yield `invalid-result` and **no partial answers**. Provider state/text is untrusted
data, never permission, a tool instruction or a reason to select another provider.

Admission distinguishes invalid input, count/payload limits, stale configuration, denied disclosure, expired
price confirmation, request/session budgets, unavailable accounting, unsupported host and concurrency exhaustion.
Authentication is opaque `credential-unavailable`; HTTP denial, billing, provider rejection, timeout and service
unavailability remain distinct. Absent responses, explicit refusal, cancellation and invalid results do not become
No, low scores or fabricated judgments. The `refusal: true` response convention is controlled evidence only;
HTTP 422 alone remains `provider-rejected`, not a claimed live refusal schema.

Consumers apply their own versioned policy to complete results. The shared layer does not import the file
screening 0.70 threshold, tool-risk thresholds, retry decisions or fallback permissions. Failed required evaluation
must remain unresolved under the consumer's policy, not silently bypass its protection.

## Session limits and accounting

The [TASK-00309 enrolled-profile candidate](FILE_SCREENING_PROFILE.md) supplies shared durable monthly accounting
and automatic actual-session limits for explicitly enrolled roots. The independent session-ledger behavior below
remains the legacy consumer contract; it is not an aggregate guarantee for arbitrary clients. No provider client
or judgment cache is duplicated by the profile, and existing legacy exposure is preserved.

The participating Pi session has **two shared outstanding requests**, including `/jev check`, across all connected
consumers. A fifteen-second deadline includes credential resolution, and there are **zero automatic retries**.
Trusted host embeddings may lower bytes, questions, choices or timeout through `Judgments`' injected `ceilings`,
and lower concurrency through its shared `SessionRequests`. They cannot raise hard ceilings. The shipped local
extension uses the published ceilings; it does not discover changing provider limits automatically. A newly
tighter provider limit requires explicit requalification/lowered host configuration, never optimistic dispatch.

Every admitted attempt reserves **2,688 micro-USD ($0.002688)**, the cost of the entire published 64,000-token
context at $0.042/M input with free output. Token estimates never lower that reservation. Explicit request/session
ceilings must accommodate it. The protected session ledger includes both readiness and judgments and survives
reload/resume. Known provider cost settles once, rounded upward to whole micro-USD. Valid reported usage can
settle a request whose answers are invalid; invalid usage cannot release its reservation. Duplicate settlement
cannot double count. Missing cost is `null`, never zero. Zero is accepted only when actually reported.

A timeout/cancellation after possible dispatch, missing/invalid cost or otherwise ambiguous billed outcome retains
the reservation. Late responses do not change returned results or silently settle uncertain charges. Predispatch
failure releases only its own unspent reservation. A reservation breach charges the reported actual amount and
**halts further session admission in the persisted ledger**, even after reload; human reconciliation and price
requalification are required. Corrupted/contended accounting fails closed. Do not delete ledgers to regain budget
or replay an ambiguous paid operation.

Child consumers must obtain an explicit allocation for their actual Pi session in their current authorizer and
request ceilings. No automatic parent-to-child allocation, lineage propagation, parent aggregate, account-wide
budget or cross-process concurrency is implemented. Independent sessions have independent ceilings; they do not
inherit a shared aggregate guarantee. Supported local use has one owning Pi runtime per session; concurrent
processes resuming the same session are unsupported. Hosts needing parent/child aggregate enforcement must supply
it before enabling those consumers, rather than assuming a shared settings file provides it.

## Receipts and lifecycle

Receipts contain evaluation/caller identity, exact evaluator/endpoint/schema, configuration revision/digest,
authority/policy versions, supplied source revision and computed digest, question/rubric versions, question/request
and session digests, outcome, elapsed time, available token/cost usage and reservation-retention state. They omit
source bodies, instructions, rubric descriptions, credentials, raw errors and upstream quotations. Supplied source
revisions are attributed claims, not independent execution evidence. Local receipts grant no permissions and
are not review, QA or Workflow transitions.

The engine keeps at most 32 metadata-only receipts, returns the latest eight by default and permits inspection
of 1–32. A connected consumer sees only its own caller ID. Copies cannot mutate retained records. Receipts are
in-memory observations, not another durable workflow store; after reload only the protected spending ledger
survives. Old configuration-bound observations must not be presented as current judgments.

Consumers propagate their operation's `AbortSignal`. Operator reconfiguration, reload and `session_shutdown`
cancel pending work, clear current observations and invalidate old capabilities; cleanup is idempotent. Source
permission/configuration are checked again before dispatch and delivery. External settings drift is checked at
these boundaries, not by a file watcher. A slot remains occupied until the underlying operation settles even when
it ignores cancellation; reload in the same process retains those slots. Pi credential resolution cannot itself
be interrupted. A permanently unresolved credential source may require intervention/restart, not more attempts.
SDK hosts must use orderly lifecycle shutdown (for example `AgentSessionRuntime.dispose()`); bare
`AgentSession.dispose()` does not emit Pi's `session_shutdown` event in 1.2.0. Abrupt process death cannot guarantee
transport cancellation; the persisted reservation remains conservative.

## Qualification boundary and proposed next authorization

Fresh public schema/endpoint/guide reads on October 14, 2026 confirmed Decisions `noul`, `choice` and `score`
forms; TypeSafe build `typesafe/jev-1.13-20260917`; 64,000 context / 32,000 prompt-token limits; and the above
pricing. These unauthenticated reads do not prove a request's billed result or live compatibility.

Actual Pi 1.2.0 on macOS loaded the extension through its SDK using an isolated, memory-only model runtime and
synthetic OpenRouter authentication. Five controlled transport dispatches exercised mixed forms, reload with
two in-flight operations, stale capabilities/configuration and orderly shutdown. Network access was disabled;
there were **zero external/paid requests**. The selected main model remained unchanged. This qualifies the local
integration under synthetic injection, not the remote provider. No live native-terminal or pending-network
cancellation capture is claimed.

John approved the earlier proposal for **exactly one** mixed-question paid request in a fresh dedicated Pi
session, request/session ceilings **$0.002688 / $0.002688**, zero retries, no extra readiness check and no alternate
credential source. The exclusive `live-BqDtKP` grant is now **consumed; zero calls remain**. This documentation
is not standing authorization. Further calls, live cancellation probes or private-source tests need new bounded
approval; never rerun a dispatched/uncertain one-call attempt or reset its claims/accounting.

On October 14, 2026, fresh public endpoint/schema/pricing checks reconfirmed the pinned build and reservation.
Actual Pi **1.2.0/macOS SDK/json**, Node **v22.23.1**, loaded the production capability, resolved its configured
Pi-managed OpenRouter source and forwarded the production request through **native HTTPS**, with no fabricated
response, transport substitution or relaxed validation. Only private session-scoped settings/accounting were
allocated; existing operator settings/accounting were unchanged. The trusted source owner checked the exact
synthetic input digests, session, configuration revision and 2,688/2,688 micro-USD allocation at all current
permission boundaries. Durable exclusive launch/dispatch claims precede possible provider effect.

State was `{"statement":"The synthetic kettle is blue and warm."}`. `blue` asked `Is the synthetic kettle blue?`;
`color` asked `Select its color.` with blue=`Blue`, unknown=`Unknown` and explicit unknown; `warmth` asked
`Rate its warmth.` with Cold/Warm/Hot on [0,2]. Source/question/rubric/authority/policy identities were `synthetic-v1`.
HTTP **200** reported **TypeSafe**, evaluator **typesafe/jev-1.13-20260917**, for requested **typesafe/jev-1.13**.
Production validation returned **complete**: Yes probability **0.9**, color **blue** with confidence **0.98** and
blue/unknown probabilities **0.99/0.01**, warmth score **1**, confidence **1**, distribution **0/1/0**, exact legend.
The observation qualifies this pinned synthetic batch, not model accuracy or universal future response validity.

Capability latency was **321 ms**; provider usage **366 input / 62 output tokens**, cost **$0.000015372**.
The production ledger settled **16 micro-USD** once, rounded upward, with no pending reservation. Invoice,
cache/reasoning usage and separate provider-reported Engineer identity are unavailable, not zero. Exact-key
in-process scans found zero credential occurrences in actual output/session artifacts, native body and retained
metadata, and zero source-body occurrences in production receipts. Raw auth files/keys/headers were not retained.
Actual orderly start/shutdown kept the main model **openai-codex/gpt-6.1-sol/high** unchanged, with zero extension
errors. Reload/pending cancellation above remains inherited controlled evidence; no live pending-network or TUI
qualification/captures are claimed.

[The TASK qualification checkpoint](../../planning/tasks/00159-TASK.md#authorized-r-02-live-evidence-recovery--2026-10-14)
owns current acceptance limits. Redacted claims, native facts, production result/usage/accounting, actual in-memory
session snapshot and disclosure inspection are retained in ignored checkout-local
`.runs/build/TASK-00159/run-V3MunP/live-BqDtKP/evidence/`; they are not public PR evidence. Independent Review of
R-02 and applicable QA remain pending, and the TASK is not done.
