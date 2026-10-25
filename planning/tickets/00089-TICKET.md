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

For hosted browser Planning and assistance, use the **initiating user's own protected OpenRouter connection**,
scoped to the environment and exact connection generation. No shared administrator/family key or another user's
connection is a fallback. Independently retain Pi's authenticated local `openai-codex` path and its credential-store/
Pi ownership; it is not hosted ChatGPT support or a prerequisite for first OpenRouter production. Each claimed
provider path needs its own actual qualification. Do not silently route failures to another account/model/path.

Installation administrators own allowed-provider/model policy and environment limits, not automatic access to
other users' connections. Authorized users manage their own connections and choose compatible permitted models.
TASK-00343 owns safe catalog/policy facts; TASK-00346 owns protected OpenRouter metadata transport, independently
of generation. Existing user-profile/default writers own authorized defaults. Expose current or last-known models,
capabilities, reported pricing and observation time through a light server cache/on-load refresh, plus explicit
Refresh models and Set as default. Coalesce/rate-limit refreshes; no per-render/token/reconnect polling. No fixed
model, benchmark project or mandatory cache TTL is selected. Authenticated filtered observations are scoped to the
user/environment/connection generation; public nonsecret metadata may be shared where appropriate.

Catalog refresh neither changes a selection/default nor refreshes allowance or generates text. Default changes
apply to future conversations only. Keep requested versus reported model identity distinct. Explicit between-turn
model changes use TICKET-00088's linked immutable continuation after finish/Stop and original-outcome reconciliation,
never an in-place replacement, replay of uncertain work or automatic fallback. Browser v1 switches OpenRouter
models on the selected user connection; other providers remain separately qualified optional routes.

Enforce current provider access separately from local source access and business Permissions. Minimize expressly
disclosure-approved context; bound input/output, elapsed time, concurrency and aggregate spend for the admitted
assignment/attempt and applicable user/connection/environment limits. Staging and production budgets are independent;
unknown usage/billing is not zero or released budget. Keep provider authentication in the protected owner, outside
browser readback/persistence, Pi children, model context, repository, transcripts, logs and evidence. Deliberate
one-time key entry uses a transient secret field and protected submission, not a browser-held inference credential.
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

### Per-user connection lifecycle and metadata ownership

This TICKET owns the provider-specific self-service connection requirements: authorized creation/list/inspection,
replacement and disconnect, protected encrypted server-side storage with separate encryption-key custody,
nonsecret IDs/status and generation fencing. Preserve `/api/v1/me/provider-connections` and
`/api/v1/me/provider-connections/{connection_id}/allowance` as the approved Fight route direction; exact methods,
payloads and explicit refresh/lifecycle routes remain delivery design. Allowance GET returns sanitized recorded
observations, never credentials or an implicit external read. Resource naming permits multiple connections without
selecting additional providers. Agent credentials and provider keys remain different authorities and lifecycles.

No key readback, browser persistence, credential dumps or sharing through conversation access. Replacing or
disconnecting a connection invalidates future use and stale results without rebinding admitted work or erasing
original charges/reservations. Explain provider-side revocation separately; local disconnect does not prove upstream
revocation. Recovery retains required encryption versions and original liabilities. Actual storage/current-use
participants and the complete lifecycle UI are allocated to TASK-00520 under this TICKET, not a second auth
platform. TASK-00521 separately consumes the existing optional allowance contract in connection settings; neither
allocation establishes actual protected runtime or source qualification.

TASK-00346 supplies narrowly bounded non-generative adapters for documented OpenRouter `GET /api/v1/key` and
model metadata (`/api/v1/models`, authenticated `/api/v1/models/user` where applicable). TASK-00399 under
TICKET-00098 owns explicit allowance refresh admission, sanitized observations and recorded queries/presentation.
TASK-00343 stays nonsecret and performs no network/credential effects; early catalog contracts must not depend on
completed generation or final browser journeys. Preserve actual host/credential/source qualification holds.

Exclude Jev evaluator reimplementation, additional hosted providers/ChatGPT OAuth, automatic routing, arbitrary
third-party MCP, provider account enrollment during planning, a parallel generic credential lifecycle and a
standalone certification/demo application. Existing local development provider choices and Jev remain independent.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Configure allowed provider choices | Protected connection/catalog revision | Authorized safe catalog, exact runtime support and readiness | Committed configuration facts where owned; no secret payload | Deliberate available choices, no session mutation |
| Choose or change a provider | Initial allowed selection or explicit linked continuation | Current profile/default, allowed options, scope and limits | Owning snapshot/continuation facts | Pinned provider/model; no automatic fallback |
| Invoke bounded generation | Execute exact admitted request through protected adapter | Current provider/disclosure/budget/host authority | Attributable attempt/outcome/usage facts at their owner | One intended external request, with honest billing/outcome uncertainty |
| Inspect, stop or reconcile | Explicit stop/original-attempt reconciliation; status reads are queries | Exact provider outcome, consumed limits and protected result | Confirmed lifecycle observations only | Safe hold/result/stop state; no new paid call as a read side effect |

## Validation and permissions

Require current intent-specific installation authority for policy/environment settings, current ownership and
connection-management authority for self-service operations, and actual human/Agent, assignment, Repository/
environment, host and selected user-connection authority for work. Authenticate protected participants;
model-supplied principal/account IDs or administrative access do not authorize egress. Reject disallowed choices,
unsafe redirects, unbounded input, malformed/partial output and stale attempt callbacks. Structured consumer
validation remains with its owning proposal/use case, not a provider's claim of valid JSON.

Treat model output as untrusted data; it cannot alter tool scope, limits, publication or provider configuration.
Sanitize provider errors and preserve no-store/private-response boundaries. Apply ADR 0001 ownership and ADR 0002
separate transaction/external-effect/outcome phases; no raw request/credential dumps in state, events or diagnostics.
Qualified protected state may retain authorized content under its own access and retention rules.

## Acceptance and evidence

- Per-user connect/replace/disconnect and protected storage enforce ownership, environment and generation binding;
  transient entry is never persisted/read back by the browser. Cross-user/admin-only access, late metadata/results
  and key replacement cannot borrow a connection, reset budgets or erase original uncertain liabilities.
- Current/light-cached model discovery, stale/absent lists and authorized default updates work without changing
  active selection, calling inference or refreshing allowance. Actual protected metadata qualification is separate
  from catalog fixtures; no listing claims guaranteed capacity.
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
| [TASK-00520](../tasks/00520-TASK.md) | Manage your protected OpenRouter connections | ready-for-agent |
| [TASK-00521](../tasks/00521-TASK.md) | Inspect optional allowance in connection settings | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved private-production reconciliation — 2026-10-24

John approved the rollout map's existing-owner amendments and explicitly requested reconciliation. The requirements
above supersede installation-key-only browser behavior. TASK-00343/00344/00345/00346 carry the bounded catalog,
user/environment binding, accounting and protected metadata extensions; TASK-00399's source mapping is accepted,
not qualified. Its `needs-info` and TASK-00346/00350 runtime holds remain. Local TASK-00347 remains independent.

At reconciliation, the complete per-user connection lifecycle/secret storage/self-service UI remained unallocated.
John subsequently approved the two additions below after the map's TICKET phase completed. They cover the delta
without absorbing it into TASK-00343, TASK-00399 or the deployment EPIC. Historical five-slice completion below
covers the earlier requirement; the explicit new coverage completes this TICKET's amended TASK decomposition,
not implementation or qualification.

[Current ownership and phase receipt](../wayfinder/research/private-production-owner-reconciliation.md) distinguishes
these changes from implementation and retains all current holds, permissions and Board priority.

### Approved connection lifecycle additions and current handoff

John accepted two additional independently reviewable slices and the bounded existing-owner amendments:

| Addition | Accepted TASK | Blockers | Complete delivered boundary |
|---|---|---|---|
| A | [TASK-00520 — Manage your protected OpenRouter connections](../tasks/00520-TASK.md) | TASK-00072, TASK-00343 | Browser/API create/list/inspect/replace/disconnect, encrypted server custody with separate keys, exact ownership/environment/generation, current-use fencing and original-mutation recovery |
| B | [TASK-00521 — Inspect optional allowance in connection settings](../tasks/00521-TASK.md) | TASK-00520, TASK-00399 | Self-service recorded allowance and explicit refresh through existing owners, honest provenance/unknowns and browser race/recovery; optional for first production |

TASK-00344 gains TASK-00520 as its real hosted connection-authority dependency. TASK-00345 inherits it through
admission; TASK-00346/00399 consume that same lifecycle/custody participant through existing dependencies, without
redundant edges. TASK-00343 remains an early nonsecret/network-free policy supplier and gains no reverse dependency.
TASK-00346 still owns protected metadata/generation; TASK-00399 still owns allowance refresh/observations and its
TASK-00391 delivery edge. TASK-00521 needs no Workflow record and adds no second ledger, source or refresh engine.

The lifecycle supplier does not wait for completed generation, accounting, allowance or final browser Planning.
Its own current-use/reference contract is supplied early; existing admission/outcome/adapter owners integrate it
and prove original-liability preservation. Current connection management authority never grants paid work or
historical key use. No OpenRouter connection enrollment is required for the independent local Pi/Codex path.
TASK-00346/00350/00399's actual host/source/composition holds remain; a named supplier is not qualified support.

Both additions are `ready-for-agent` behind explicit unfinished dependencies, with unranked order. Preserve all
existing statuses/orders and EPIC-00020 priority. Checked live/archive TASK filenames across sixteen local/cached
refs and eight registered worktrees, including untracked files; highest observed was TASK-00519. Allocated
TASK-00520/00521 locally without remote fetch or global reservation. Recheck IDs before any later allocation.

The approved amended TICKET scope is now decomposed: original five slices plus lifecycle A and optional settings B.
The rollout map's decisions/EPIC/TICKET phases are complete. TICKET-00121's subsequent artifact/CI split is now
TASK-00522/00523, TICKET-00122's environment split is TASK-00524–00526, and TICKET-00123's mail split is
TASK-00527/00528. TICKET-00124's recovery split is TASK-00529/00530; TICKET-00125's accepted TASK-00532/00533 now
complete the selected rollout map's TASK phase, without claiming implementation or operational acceptance.
No implementation, credential access, provider request, spending or deployment is authorized by this planning.
Ordinary owned-behavior tests, full build and independent Review/QA remain part of each TASK, not extra proof TASKs.

#### Lifecycle decomposition verification

In the selected `feature/wayfinder-private-production-rollout` planning checkout, `./bin/planning-check --write`
and `./bin/planning-check` passed: **666 records, 510 active**, generated views current. `git diff --check` and
changed/untracked whitespace/scope checks passed across **57 planning paths**, including earlier uncommitted work;
3,996 relative link targets were checked.

Compared the 774 pre-existing planning Markdown files with ignored
`.runs/notes/provider-lifecycle-decomposition/before.json`: exactly fifteen existing paths changed (the four
approved TASK amendments, this TICKET, two EPIC handoffs, Roadmap, five Wayfinder handoffs, generated Board and
TASK index), plus TASK-00520/00521. Only existing frontmatter change: TASK-00344 adds TASK-00520 to `blocked_by`.
All pre-existing statuses/orders are unchanged. TASK-00345/00347/00350, TASK-00510–00512 and EPIC-00020 remain
byte-identical to the pre-step receipt. The generated TASK index adds only the two accepted records.

These are planning checks, not application/provider acceptance. No full build, independent Review/QA, credential
access, account/provider operation, runtime/deployment effect, commit or push occurred. Work remains uncommitted.
Historical receipts below retain their original counts and limits.

Next: `/skill:next` for read-only Board-based selection after completed selected-map planning.
No automatic execution; the lifecycle verification counts above remain historical to that step.

### Approved TASK decomposition and current handoff

This section records the original five-slice approval; the lifecycle additions and current route above supersede
its historical next-step recommendation without changing its evidence.

The maintainer approved five independently reviewable delivery slices in the existing isolated planning worktree.
Early catalog facts, admission and original-outcome accounting remain independent of completed provider adapters
and final conversation journeys; the two actual providers are independently usable and qualified.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00343](../tasks/00343-TASK.md) | Protected revisioned provider/model choices and safe early compatibility/readiness inspection |
| B | [TASK-00344](../tasks/00344-TASK.md) | Exact disclosure-approved admission with durable finite budget reservations before effects |
| C | [TASK-00345](../tasks/00345-TASK.md) | Original outcome inspection/reconciliation, attributed usage and honest uncertainty/accounting |
| D | [TASK-00346](../tasks/00346-TASK.md) | User-owned server-protected OpenRouter generation and bounded metadata adapters; real Planning consumption, not the complete self-service lifecycle |
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
actual correlation and projections, not another reservation ledger or raw streaming sum. The later reconciliation
maps TASK-00399's safe ordinary-key `/key` source to TASK-00346's protected metadata adapter; actual source/host/
connection lifecycle qualification remains held. Generation support alone does not qualify allowance.

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
