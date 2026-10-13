---
id: TICKET-00089
epic: EPIC-00006
title: Configure and qualify protected generative providers
status: ready-for-agent
---

# Configure and qualify protected generative providers

## Problem statement

Planning and existing AI-assistance consumers need a real protected generative provider, explicit data-disclosure
and spending boundaries, and truthful recovery. Jev Decisions setup or a saved model name proves none of these.

## Solution and boundaries

Independently support and qualify OpenRouter through a protected installation-held API key and local Codex through
Pi's authenticated `openai-codex` subscription path under the accepted local credential-store/Pi ownership boundary.
Neither is mandatory when the other is configured; both must have independent actual evidence before claiming the
EPIC's initial support. Do not add other providers or silently route a failed call to another account/model/path.

Let installation administrators configure protected connections and an allowed-model catalog. Expose only safe
permitted selection/readiness facts to profiles and authorized users. Profiles choose defaults; a user may choose
another allowed provider/model when a conversation starts. Use exact qualified provider/runtime identities and
record requested versus reported model distinctly. An explicit active-provider change uses a linked continuation
with a new [TICKET-00088](00088-TICKET.md) snapshot, never an invisible replacement.

Enforce current provider access separately from local source access and business Permissions. Minimize expressly
disclosure-approved context; bound input/output, elapsed time, concurrency and aggregate spend for the admitted
assignment/attempt. Unknown usage/billing is not zero or released budget. Keep provider authentication in the
protected owner, outside browser clients, Pi children, model context, repository, transcripts, logs and evidence.
Missing configuration, billing capacity, allowed model, disclosure authorization or safe host/broker support yields
an explicit unavailable/denied result, not an unprotected local fallback.

Provide the bounded generative invocation and outcome/reconciliation contract consumed by
[TICKET-00090](00090-TICKET.md)'s durable execution and [TICKET-00091](00091-TICKET.md)'s protected proposal attempts.
Required durable admission commits before external effects. Preserve original attempt/provider correlation,
currentness fences, cancellation and late-outcome handling. After lost acknowledgement, timeout or replacement,
reconcile the original attempt with the provider's actual supported facilities; where status cannot be established,
retain bounded uncertainty/needs-human. Never promise exactly-once paid work, silently regenerate, reset spend or
reinterpret cancellation as proof that an accepted provider request was unsent. Do not create a second generic
provider queue or use AccessControl credential delivery state for model work.

Record available response/session identity and provider-reported token, cache, reasoning, usage, currency and cost
facts with provenance. Distinguish measured, estimated and unavailable values; never invent pricing. Normalize
streaming cumulative versus incremental reports without double counting. Record reasoning quantities only when
reported; hidden reasoning content is not ordinary telemetry. Expose safe facts needed for limits and real consumer
evidence, not a speculative billing or quota Dashboard.

Exclude Jev evaluator reimplementation, additional providers, automatic model routing, arbitrary third-party MCP,
account enrollment during planning, a new credential lifecycle and a standalone certification/demo application.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Configure allowed provider choices | Protected connection/catalog revision | Authorized safe catalog, exact runtime support and readiness | Committed configuration facts where owned; no secret payload | Deliberate available choices, no session mutation |
| Choose or change a provider | Initial allowed selection or explicit linked continuation | Current profile/default, allowed options, scope and limits | Owning snapshot/continuation facts | Pinned provider/model; no automatic fallback |
| Invoke bounded generation | Execute exact admitted request through protected adapter | Current provider/disclosure/budget/host authority | Attributable attempt/outcome/usage facts at their owner | One intended external request, with honest billing/outcome uncertainty |
| Inspect, stop or reconcile | Explicit stop/original-attempt reconciliation; status reads are queries | Exact provider outcome, consumed limits and protected result | Confirmed lifecycle observations only | Safe hold/result/stop state; no new paid call as a read side effect |

## Validation and permissions

Require current intent-specific installation management authority for configuration, and actual human/Agent,
assignment, Repository/environment, host and provider authority for work. Authenticate protected participants;
model-supplied principal/account IDs or administrative access do not authorize egress. Reject disallowed choices,
unsafe redirects, unbounded input, malformed/partial output and stale attempt callbacks. Structured consumer
validation remains with its owning proposal/use case, not a provider's claim of valid JSON.

Treat model output as untrusted data; it cannot alter tool scope, limits, publication or provider configuration.
Sanitize provider errors and preserve no-store/private-response boundaries. Apply ADR 0001 ownership and ADR 0002
separate transaction/external-effect/outcome phases; no raw request/credential dumps in state, events or diagnostics.
Qualified protected state may retain authorized content under its own access and retention rules.

## Acceptance and evidence

- Independently exercise real OpenRouter and local `openai-codex` with exact qualified versions/configurations,
  permitted models, protected credential handling and honest usage provenance. One path can operate when the other
  is absent; neither path's receipts qualify the other. Obtain separate live-call authority and finite limits.
- Demonstrate denied disclosure/model/authority, exhausted limits, missing setup, provider failure and unavailable
  metadata without fallback or secret leakage. Permission to read locally alone permits no provider egress.
- Lost responses, cumulative streaming, cancellation, process replacement and uncertain billing preserve original
  correlation, correct accounting and bounded recovery without claiming an unknown request failed or cost zero.
- Owned adapter/policy/accounting behavior tests, direct provider/host qualification, real Planning consumption and
  `./bin/build` pass. Doubles prove fault contracts, not live provider support; no provider-SDK or wiring tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 5 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes the provider boundary in
[WF-019](../wayfinder/tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md),
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Supplies exact early catalog/provider/disclosure/budget/recovery capabilities to TASK-00249/00250, TASK-00302's
existing template assistance and TASK-00304's custom manual use. These consumers do not reverse-block their
suppliers; finished Explorer/browser Planning or database Planning cutover is not a primitive prerequisite.
TICKET-00090 owns shared durable execution, TICKET-00091 owns proposal attempts/results, and this TICKET owns
provider semantics/accounting; define non-circular contracts during separate TASK decomposition. Existing Agent,
credential and qualified host owners remain mandatory, not silently replaced by generic parent dependencies.
No provider request, credential inspection, enrollment, implementation TASK or readiness clearance occurs here.
