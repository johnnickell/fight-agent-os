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
| [TASK-00343](../tasks/00343-TASK.md) | Configure and inspect allowed provider choices | ready-for-agent |
| [TASK-00344](../tasks/00344-TASK.md) | Admit disclosure-approved budgeted generation | ready-for-agent |
| [TASK-00345](../tasks/00345-TASK.md) | Inspect and reconcile original generation outcomes | ready-for-agent |
| [TASK-00346](../tasks/00346-TASK.md) | Generate through protected OpenRouter | needs-info |
| [TASK-00347](../tasks/00347-TASK.md) | Generate through protected local Codex | needs-info |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved five independently reviewable delivery slices in the existing isolated planning worktree.
Early catalog facts, admission and original-outcome accounting remain independent of completed provider adapters
and final conversation journeys; the two actual providers are independently usable and qualified.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00343](../tasks/00343-TASK.md) | Protected revisioned provider/model choices and safe early compatibility/readiness inspection |
| B | [TASK-00344](../tasks/00344-TASK.md) | Exact disclosure-approved admission with durable finite budget reservations before effects |
| C | [TASK-00345](../tasks/00345-TASK.md) | Original outcome inspection/reconciliation, attributed usage and honest uncertainty/accounting |
| D | [TASK-00346](../tasks/00346-TASK.md) | Actual installation-protected OpenRouter generation and real Planning consumption |
| E | [TASK-00347](../tasks/00347-TASK.md) | Actual Pi/provider-owned local openai-codex generation and real Planning consumption |

TASK-00343 consumes TASK-00322 and is `ready-for-agent` behind unfinished dependencies, not live support.
TICKET-00090's subsequent approved split maps TASK-00349's early assignment/submission/attempt/cancellation and
transaction participants to TASK-00344/00345, resolving their sole mapping holds; both become `ready-for-agent`
behind unfinished dependencies. TASK-00346 consumes TASK-00344/00345/00072 plus TASK-00351 invocation; TASK-00347
consumes TASK-00344/00345 plus TASK-00351 independently. Both retain `needs-info` for TASK-00350's exact reusable
operational host/broker participant gap and actual authority composition. Live provider qualification is separate. Early
identity/attempt/invocation contracts cannot depend on completed provider-driven execution. All orders are unranked;
Board priority and the terminal-first milestone remain unchanged.

TASK-00323 and TASK-00339 gain TASK-00343, resolving their sole remaining supplier-mapping holds and changing to
`ready-for-agent` behind unfinished dependencies. TASK-00299 gains TASK-00343 and TASK-00249 inherits it: independent
Triage/scoped-read holds and actual seed/reference/resolution qualification remain. TASK-00300 retains complete
publication/current-authority composition. TASK-00342 gains TASK-00345 for original-provider outcomes, preserving
session/host/continuation holds. TASK-00302/00304 gain TASK-00344/00345; their proposal, manual-assignment, runtime,
Feature or publication holds remain. Consumers qualify a selected permitted real path; neither provider must be
configured when the other is usable, and no blanket both-provider setup dependency is imposed.

Provider configuration is not live qualification, disclosure permission or a session grant. TASK-00346/00347 own
actual provider-specific status/stop/usage semantics and real Planning consumption within their outcomes, not a
separate proof-only application. Their early runtime seams do not require final browser Planning, role journeys or
database Planning cutover. TASK-00342/00325 retain linked continuation/switching; no adapter reverse-depends on them.
Jev Decisions and Agent credential delivery remain distinct; no second queue, auth lifecycle or session store is added.

TICKET-00098 now maps TASK-00396's Workflow-attributed usage consumer of TASK-00345's original accounting. It adds
actual correlation and projections, not another reservation ledger or raw streaming sum. TASK-00399's separate safe
account/quota source and protected refresh supplier remain unallocated; generation support qualifies neither.

This decomposition is complete, not implementation. Following TICKET-00090–00099's approved conversation/proposal/browser/Runner/evidence/web-research/history/sessions/measurement/catalog
splits, continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve Markdown authority and all unresolved participant holds.
Planning authorizes no login, key inspection/enrollment, session launch, provider request or paid qualification.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving all prior approved
uncommitted planning work. Live and archived TASK identities were rechecked before allocating TASK-00343–00347;
the archive contained no TASK records. `./bin/planning-check --write` and `./bin/planning-check` passed with
474 records, 382 active and current generated views. Tracked `git diff --check` and all 39 new/untracked TASK
whitespace checks passed, including these five records. No dependency cycles or invalid planning links were
reported. Active TASK-00138 and existing Board priorities remain unchanged; new orders remain unranked.

These are planning receipts, not provider, credential, host or live Planning qualification. The full `./bin/build`
was not run and remains required before commit/publication. No implementation, account/login setup, credential
inspection/enrollment, session launch, provider call, commit, push, PR or merge occurred. Independent review and
delivery remain separate. All planning changes remain uncommitted in the isolated worktree. Separately observed
Jev/Harness/build changes in the main checkout were left untouched; no claim that the main checkout is clean.

### Original requirement approval

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
The prior TICKET-00088 handoff requested exact early catalog and original-provider-outcome suppliers. The approved
TASK-00343 and TASK-00345 mappings above now resolve that ownership without claiming live support. Early catalog/
readiness facts do not depend on completed snapshots, provider switching or conversation execution. Uncertainty
never permits another generation or spend reset; precise TICKET-00090 participant holds remain. No provider request,
credential inspection, enrollment or runtime readiness clearance occurs through this planning.
