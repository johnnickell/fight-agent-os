# Bounded typed judgments

TASK-00159 implements the shared local capability for trusted TICKET-00038 tool guards and TICKET-00039 file
screening. Controlled tests and real Pi 1.2.0 SDK integration are available; **live mixed-question qualification
is outstanding**. TASK-00184's earlier fixed yes/no check is inherited evidence, not qualification of these forms.
No Agent-callable judgment tool is installed. No guard, file-read permission, relevance threshold, sandbox,
Workflow transition, savings estimate or model-accuracy claim is supplied here.

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
same absolute tolerance. Unsupported/missing measures are invalid, not defaulted. Live compatibility of these
strict requirements remains to be established. Provider-generated explanations/quotations are never returned.

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

**Proposal only; do not dispatch without a new explicit authorization:** one mixed-question paid request in a
fresh dedicated qualification session, request/session ceilings **$0.002688 / $0.002688**, no retries, no second
readiness check and no alternative credential source. State: `{"statement":"The synthetic kettle is blue and warm."}`.
Questions: `blue` asks whether that kettle is blue (`yes-no`); `color` selects `blue` or `unknown` with those declared
descriptions; `warmth` rates `['Cold', 'Warm', 'Hot']` on [0,2]. All question/rubric, policy, source and authority
versions must be explicitly synthetic v1 identities. The operator must reconfirm current public prices/build,
authorize Pi-managed credential resolution and this exact disclosure, and enable the trusted synthetic consumer
with its session-bound allocation. A failure/unknown still consumes the single-call allowance: stop and report.
Retain only redacted result/usage and inspect actual output/session artifacts without printing credentials.
Further calls, live cancellation probes or private-source tests require separate bounded approval. No such
approval was available for TASK-00159 implementation; the prior TASK-00184 one-call allowance was exhausted.
