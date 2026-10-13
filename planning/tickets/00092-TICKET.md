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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 8 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-012](../wayfinder/tickets/WF-012-define-browser-planning-and-conversation-ownership.md),
[WF-019](../wayfinder/tickets/WF-019-select-end-to-end-proof-and-epic-handoff.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Requires the real authenticated registered database-authoritative Planning Dashboard plus the exact TICKET-00085–91
capabilities used by each interaction. Integrate TICKET-00061/65's real Agent authority and role journeys without
moving their owners or making final browser Planning a blocker of early session/provider/proposal suppliers.
Task decomposition must separate generic journey operations from final managed-role composition to avoid circular
TASK-00266 dependencies. Preserve the terminal-first milestone and Board priority. No browser runtime, Agent,
provider call, Planning cutover or implementation TASK is delivered by this requirement approval.
