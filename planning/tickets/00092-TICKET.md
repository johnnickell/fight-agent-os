---
id: TICKET-00092
epic: EPIC-00006
title: Deliver the browser Planning Agent journey
status: ready-for-agent
---

# Deliver the browser Planning Agent journey

## Problem statement

Authorized planners need to continue real repository planning across questions, research, proposals and conflicts
without losing private context or confusing Agent activity with accepted changes. The existing Dashboard shell
and local skills do not provide that durable product journey.

## Solution and boundaries

Extend [TICKET-00029](00029-TICKET.md)'s authenticated registered Planning Dashboard with repository-scoped durable
intake and conversations. Keep What do you want to build? prominent. Submission creates an intake, not a map;
inspect existing maps/artifacts and recommend meaningful matches, then let the human choose continuation or new
work. Do not attach, reparent or duplicate artifacts automatically.

For new work, enter Wayfinder questions and show an editable proposed title, repository-unique mutable slug,
destination, done condition, initial decisions/dependencies and frontier. Explicit Create Wayfinder Map commits
through [TICKET-00091](00091-TICKET.md), then advances to the first eligible decision within the authorized
operation. HITL opens at its question; authorized AFK research/prototype preparation shows real stages and results
for review. Explain dependencies/unavailable capabilities when no decision is eligible. Map opening resumes the
current decision, not an arbitrary new conversation.

Use distinct managed Project Manager and Explorer identities, exact role/context/Harness snapshots and current
human/Agent authority. Project Manager owns human-governed Planning; Explorer supports bounded factual inspection.
Consume existing Agent/Team/credential owners and TASK-00250's role integration. TASK-00266 retains Project Manager
identity integration and complete team evidence; TASK-00247 retains the manual Explorer outcome. This journey
owns browser behavior, not another role registry or per-template proof ceremony.

Preserve distinct Wayfinder, grill, Make Tickets and Make Tasks operations even when navigation is continuous.
Expose prominent initial decomposition and deliberate Continue planning for existing children; inspect existing
scope and require the proper reopen/restore path for terminal/archived parents. Show editable previews and explicit
Create/Apply/reconciliation from TICKET-00091. Final accepted proposal completes that operation; no automatic next
planning operation, execution grant or task launch follows.

Reconnect to [TICKET-00090](00090-TICKET.md)'s same durable conversation with unresolved question, accepted answers,
recent transcript, progress, pending proposal/conflict and retry state. Earlier history loads progressively without
exposing internal Pi boundaries. Disconnect/logout detaches the client, not the conversation; current authorization
still controls subsequent operations. Stop requests cancellation; separately confirmed Abandon preserves history
and removes the operation from active work. Completed/abandoned history stays discoverable through artifact activity.

Visibility is owner-first: other authorized planners see accepted artifacts/decisions and progress, not transcript,
rejected ideas or drafts unless explicitly shared or handed off for read/continue access. Authorize every share,
handoff and subsequent read/use; v1 has no simultaneous free-form co-editing. Provide only narrowly authorized
redaction/retention operations through the protected owner; normal users cannot permanently delete history.

Show honest map progress as closed decisions over currently known decisions, and active interview progress as
settled decision points over currently known required points. Explain new denominator items. AFK work uses queued,
inspecting, running, preparing, ready-for-review, interrupted/failed stages and actual last activity, not fabricated
percentages from tokens/turns/time. Distinguish stale context, unavailable provider, denied authority and uncertain
apply. Resolve the original apply outcome before enabling another. Explicit changed-context/provider continuation
shows a concise change summary and revalidates retained proposals; unchanged reconnect needs no ceremony.

Exclude a second hierarchy/queue/Dashboard authority, coding Workflow controls, completion forecasting, comments,
mentions, teammate notifications, simultaneous editing, persistent direct-edit drafts, automatic acceptance and
repository instruction mutation. No standalone conversation demo or certification path substitutes for real use.

### Approved first-production journey and model/usage presentation

The first production milestone pairs verified database Planning cutover under TICKET-00028 with this real journey:
sign in/select an authorized registered Repository; submit an idea and select matching or new work; review/edit/
explicitly create a map when needed; work one HITL decision and explicitly Apply its result; leave and resume the
same conversation with truthful progress and usage. Private discussion/drafts are not accepted state. This selects
an initial acceptance scenario, not a reduction of the other accepted journeys or a new demo/readiness TASK.

Use the initiating user's protected OpenRouter environment connection from TICKET-00089. No administrator/family
key, local Codex credential reuse or simultaneous two-provider setup is required. Distinct named Planning roles
may use the same suitable model. Keep actual role/runtime/authority suppliers and all their holds.

TASK-00367 supplies initial and between-turn model selection, a current/light-cached compatible picker, Refresh
models, authorized Set as default and the existing linked-continuation confirmation. It consumes TASK-00343's safe
catalog, TASK-00346's protected metadata operation and TASK-00323's existing profile/default writer. Refreshing a
catalog does not change a default, generate text or refresh allowance. No fixed model ID, mandatory 15-minute TTL,
automatic fallback or benchmark project is selected. Defaults affect future conversations; an active turn must
finish or follow Stop/original-effect reconciliation before a confirmed model change. Preview authorized carried
context and any reduction; preserve history and revalidate proposals without transferring approval or applying them.
First browser switching is between OpenRouter models, not proof of cross-provider support.

TASK-00365 renders independently loaded conversation/turn usage from TASK-00352's query, keeping recorded tokens/
cost, applicable environment budgets/reserved uncertainty and separately authorized provider allowance distinct.
Show source/time, requested/reported models, reported versus estimated cost/currency and unsupported categories.
Known failed/stopped/superseded costs remain; do not sum cumulative streams again, double-count continuations or
nested activity, combine incompatible units/currencies or reveal other users' usage. Secondary display failures
leave otherwise readable Planning usable and never reset admission limits. Allowance is optional, with its explicit
refresh/recorded-read contract owned by TASK-00399; missing integration displays unavailable, not zero or unlimited.
Connection self-service belongs to TICKET-00089, not a duplicate credential form/owner here.

Database migration and product implementation can proceed locally/in staging without production already existing.
EPIC-00021 supplies deployment integration and consumes these outcomes; it does not replace their owners. Retain
ordinary checks, independent acceptance and approved migration/promotion/recovery, not a second certification gate.
Optional creation/instruction assistance, domain UI, ChatGPT and legacy replacement are not first-journey gates.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Start or continue intake | Create durable intake; explicitly select existing/new work | Registered Repository, current authority, existing artifacts and active conversations | Committed intake/operation facts, not automatic map creation | Durable question/intent under one Repository |
| Conduct Planning with managed Agents | Submit answers, explicitly request next distinct operation, Create/Apply approved candidate | Current question, decisions, proposal, revisions, model choice and capability readiness | Existing conversation/answer/Planning facts from their owners | Authorized bounded work and human-approved artifacts |
| Resume and inspect progress | Authorized attach; queries remain read-only | Safe status, known-unit counts, progressive authorized history and original outcomes | Attachment/progress observations; no invented completion event | Same durable journey with truthful interruption/conflict states |
| Share or hand off | Explicit read/continue sharing or handoff command | Current ownership, recipient repository authority and allowed scope | Committed sharing/handoff facts | Deliberately bounded transcript/draft access, not public visibility |
| Stop or abandon | Stop; separately confirmed Abandon | Current turn, uncertain effects and retained history | Confirmed owning lifecycle facts | Cancellation request or removal from active work; history preserved |
| Continue after context/provider change | Explicit linked continuation | Prior/new snapshot differences and current authority | Existing continuation facts | New immutable context with revalidated proposals, never silent failover |

## Validation and permissions

Server operations enforce current intent-specific viewing, conversation, acceptance, sharing/handoff and guarded
lifecycle permissions through Fight Access Control, never Role names. Browser presentation consumes current `/me`
and owning scope projections under ADR 0003/0004. Retire private data on authority/context change; stale callbacks
cannot restore a logged-out identity or leak another conversation. Apply no-store, safe rendering, bounded inputs
and pagination, exact current revisions and strict response decoding.

Provider/Agent credentials and hidden reasoning never reach browser clients. Authorized transcript/candidate
content remains protected data, not ordinary logs or public observation. No browser-persistent business drafts or
mutation-replay interceptor; warn before leaving unsaved preview edits without claiming server draft recovery.
Human decisions remain required even during AFK work. Current revocation overrides pinned session snapshots.

## Acceptance and evidence

- The selected first journey uses the initiating user's OpenRouter connection, a visible compatible model and
  explicit decisions/Apply, preserving private history on reconnect. Default/catalog changes never switch an
  existing conversation; between-turn model change uses the real confirmed continuation and context preview.
- Conversation/turn usage, applicable budgets and optional allowance load independently with honest unknowns,
  no double counting or cross-user leakage. Catalog refresh and ordinary reads do not poll allowance or infer
  spending authority; unavailable secondary displays do not destroy readable Planning.
- Use real Fight Agent OS Planning from intake through managed conversation, human answers, editable proposal and
  explicit authoritative Apply, preserving separate operations and no automatic duplicate/new artifact creation.
- Demonstrate authorized browser reconnect and process replacement, pending human questions, cancellation versus
  Abandon, owner-only drafts, explicit sharing/handoff, stale context and uncertain apply without lost history or
  duplicate execution. Exercise supported terminal attachment through its owner without claiming unsupported parity.
- Show truthful counts/stages, unavailable/denied states, accessible keyboard/focus behavior and readable responsive
  light/dark layouts with actual browser evidence. UI presence alone is not managed role or provider qualification.
- Independently supported provider choices are available under TICKET-00089; changed provider/context requires the
  actual continuation path. Record real Agent/Team/provider/snapshot provenance without secrets.
- Owned API/client/journey tests, focused security/recovery evidence, manual real-use captures and `./bin/build`
  pass. Do not duplicate framework/browser mechanics or build a proof-only route/test product.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00361](../tasks/00361-TASK.md) | Start and continue repository Planning intake | ready-for-agent |
| [TASK-00362](../tasks/00362-TASK.md) | Conduct Wayfinder interviews and create reviewed maps | ready-for-agent |
| [TASK-00363](../tasks/00363-TASK.md) | Review and apply artifact Planning operations | ready-for-agent |
| [TASK-00364](../tasks/00364-TASK.md) | Conduct bounded AFK Planning preparation | needs-info |
| [TASK-00365](../tasks/00365-TASK.md) | Resume stop and abandon Planning conversations | ready-for-agent |
| [TASK-00366](../tasks/00366-TASK.md) | Share and hand off protected Planning history | ready-for-agent |
| [TASK-00367](../tasks/00367-TASK.md) | Explicitly continue after context or provider changes | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved private-production reconciliation — 2026-10-24

John's WF-049 journey/model/usage decisions now amend this requirement. TASK-00365 gains the bounded usage UI;
TASK-00367 gains initial picker/default controls and explicit same-provider switching. TICKET-00090/TASK-00352
supply conversation usage; TICKET-00085/TASK-00323 own default persistence; TICKET-00089 owns protected connections/
metadata. Workflow-only TASK-00396/00391 are not standalone Planning suppliers. No duplicate state/ledger or new
TASK is created; the connection lifecycle's undecomposed delta remains under TICKET-00089. Existing host, prototype,
Agent and cutover holds remain, with no priority change or implied delivery. See the
[reconciliation receipt](../wayfinder/research/private-production-owner-reconciliation.md).

### Approved TASK decomposition and current handoff

The maintainer approved seven bounded implementation slices in the existing isolated planning worktree. Each owns
its application/browser behavior, actual acceptance evidence and operator guidance; no proof-only TASK is added.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00361](../tasks/00361-TASK.md) | Durable repository intake, existing-work recommendations and explicit continuation/new-work choice |
| B | [TASK-00362](../tasks/00362-TASK.md) | Wayfinder answers/corrections, reviewed atomic map creation and truthful decision progress |
| C | [TASK-00363](../tasks/00363-TASK.md) | Distinct artifact operations, editable previews, explicit atomic Apply and reviewed reconciliation |
| D | [TASK-00364](../tasks/00364-TASK.md) | Bounded AFK preparation with actual stages, recovery and reviewable research/prototype findings |
| E | [TASK-00365](../tasks/00365-TASK.md) | Same-conversation resume/history, Stop, confirmed Abandon and retained activity |
| F | [TASK-00366](../tasks/00366-TASK.md) | Owner-first read/continue sharing, handoff and narrowly authorized retention/redaction |
| G | [TASK-00367](../tasks/00367-TASK.md) | Explicit context/provider change summary, linked continuation and retained-proposal revalidation |

TASK-00361 consumes TASK-00114/00250/00352. TASK-00362 consumes TASK-00361/00359; TASK-00363 consumes
TASK-00361/00360 independently of completed map creation. TASK-00364 consumes TASK-00362/00353/00087;
TASK-00365 consumes TASK-00361/00353; TASK-00366 consumes TASK-00361/00354; TASK-00367 consumes
TASK-00361/00342/00357. These are capability dependencies, not a new execution priority. All orders are unranked.

TASK-00364 is `needs-info` until precise protected prototype-execution participants and supported scope are mapped
under existing TICKET-00032 and relevant TICKET-00093 owners. TASK-00087 stores evidence; it does not run prototypes.
TASK-00350's operational host/broker gap persists. TICKET-00095 now maps TASK-00382 search and independently
TASK-00383 SSRF-safe retrieval, both through TASK-00381's exact-content Jev admission; TASK-00384 owns retained/
cached/resumed web delivery. Each used route requires current disclosure/authority and actual qualification;
unavailable web tools do not authorize fallback or become blanket blockers on local-only preparation. The other six TASKs are ready behind unfinished suppliers, not operationally ready.
All independent Team/creation, profile/Triage/scoped-read, contribution and current-authority holds remain.

TASK-00266 gains TASK-00362–00367, consuming TASK-00361 transitively, while retaining final named Project Manager
integration and TASK-00247's original Explorer evidence. Its browser allocation gap resolves but `needs-info`
remains for independent host/authority/role composition and TASK-00364's operational mapping hold. No generic browser
TASK depends on completed TASK-00266, and no early session/provider/proposal capability depends on this final UI.
Existing semantic Planning, queue, snapshot, credential, conversation and retention owners remain authoritative.
Browser delivery inherits TASK-00104/00114's verified database authority without moving cutover into early primitives.

Actual real-use evidence belongs within these slices and existing role owners. Use a selected qualified provider,
not mandatory simultaneous setup; cross-provider continuation needs independently supported paths and actual route
evidence before being claimed. Accessible keyboard/focus, responsive light/dark, privacy, stale/denied/unavailable
and original-outcome recovery evidence are required across the delivered interactions, not a final ceremony TASK.

TICKET-00093 now maps TASK-00368–00373's Runner operation, not the early protected host/broker or prototype
execution gaps. Enrollment/capacity and completed Workflow consumers do not clear TASK-00350/00364's holds.
TICKET-00094 now maps TASK-00374–00380's evidence storage/provenance/lifecycle, not prototype execution or screened
web retrieval; actual host/prototype and authority holds remain.
This decomposition is complete, not browser or managed-runtime delivery. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve terminal-first Board priority and Markdown
Planning authority until separately approved verified cutover. No implementation, provider call, prototype, intake,
share/handoff, history read, live Planning mutation, cutover, cleanup or publication is authorized by this planning.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving all prior approved
uncommitted planning work. Live and archived identities were rechecked before allocation: the live ceiling was
TASK-00360 and the archive contained no TASK records. TASK-00361–00367 match the approved metadata/dependencies;
TASK-00364 is `needs-info`, the other six are ready behind unfinished suppliers, and all orders are unranked.

`./bin/planning-check --write` and `./bin/planning-check` passed with 494 records, 402 active and current generated
views. No invalid planning links or dependency cycles were reported. Tracked `git diff --check` and all 59 new/
untracked TASK whitespace checks passed, including these seven records. That historical handoff advanced to TICKET-00093;
Board priority and active TASK-00138 remain unchanged.

These are planning receipts, not actual browser, provider, prototype, role or database qualification. The full
`./bin/build` was not run and remains required before commit/publication. No implementation, model call, credential
access, history read, intake/answer, live Planning mutation, cutover, share/handoff, redaction, cleanup, commit,
push, PR or merge was performed. Changes remain uncommitted; independent review/publication are separate. Unrelated
main-checkout work was left untouched, not claimed clean. Earlier receipts remain historical, not fresh runtime
evidence for these undelivered capabilities.

### Original requirement approval and supplier handoff

The earlier allocation requests below are superseded by the approved mappings above, not runtime qualification.

Approved as area 8 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-012](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md),
[WF-019](../wayfinder/tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Requires the real authenticated registered database-authoritative Planning Dashboard plus the exact TICKET-00085–91
capabilities used by each interaction. Integrate TICKET-00061/65's real Agent authority and role journeys without
moving their owners or making final browser Planning a blocker of early session/provider/proposal suppliers.
TICKET-00090 now maps TASK-00348/00349 assignment/input/attempts, TASK-00351/00352/00353 invocation/attachment/
recovery and TASK-00354 protected retention. Reuse those operations and owner-first access; browser sharing/
handoff and abandonment confirmations remain here without creating another transcript writer. TASK-00350's
operational host/broker hold persists. TICKET-00091 now maps TASK-00355 early protected proposals, TASK-00356
revisioned human answers/operation boundaries, TASK-00357 typed candidates, TASK-00358 explicit human Apply,
TASK-00359 atomic multi-record changes and TASK-00360 separately reviewed reconciliation. Consume those exact
operations without another proposal engine; TASK-00356's verified cutover requirement does not flow backward into
TASK-00355 or template assistance. TASK-00266 consumes TASK-00360 and now this TICKET's mapped TASK-00362–00367
browser operations, with TASK-00361 transitively, while retaining independent role/host/authority and AFK participant
holds. Generic journey operations remain separate from completed role use, avoiding circular TASK-00266 dependencies.
The original requirement approval created no TASKs; the subsequent approved split creates planning records only.
No browser runtime, Agent, provider call or Planning cutover is delivered by these approvals.
