---
id: TICKET-00049
epic: EPIC-00011
title: Retrieve scoped memory selectively with Jev
status: ready-for-agent
---

# Retrieve scoped memory selectively with Jev

## Problem statement

Agents should be able to ask focused questions about their permitted memory without loading an entire bank
into model context. A typed classifier can narrow candidate records, but its judgment is not a summary, an
exact-source read, evidence of completeness or permission to see more than the authenticated Agent can read.

## Solution and boundaries

- Reuse the accepted private, shared and promotion Application reads and exact-entry first-party MCP tools from
  TICKET-00046 through TICKET-00048; do not build a duplicate memory authority, database credential or second
  MCP implementation just for Jev. HMAC identifies the invoking Agent; the application checks current scope
  IDs, permission, assignment and lifecycle for each requested memory operation. MCP Tools suffice; no
  preloaded MCP Resource or host-memory mount is required.
- Start an Agent with concise memory-use instructions and its authoritative TASK/role handoff. It retrieves
  memory as needed, without mandatory preloading. An optional small start index is a later measured choice,
  not a prerequisite or implicit permission. Exact reads return original entry text and its source/revision/
  status under the caller's current authority, not Jev-generated prose.
- Offer typed Jev yes/no and predeclared-choice questions about one memory or memories in a permitted scope.
  For many entries, allow Level 9-style selection of matching entry IDs followed by separate ordinary exact
  reads as needed. Jev evaluates eligible source content on behalf of the existing caller; it is not another
  Agent, a free-form summarizer, an independent SQL reader or an authorization service. Its judgments never
  ingest, correct or promote memory.
- Preserve the selected memory/source revision, evaluator/question and observed outcome so the caller can
  tell which entries were judged. Normal helpful-memory lookup excludes retracted claims, pending and rejected
  promotion proposals; an explicitly authorized historical/self-learning inquiry is separate. Completed-work
  entries remain eligible where the caller's current repository, TASK, audit or human scope permits them.
- Use no memory-specific Jev judgment cache. On timeout, provider error, input limit or other known failure,
  return the actual failure, not a fabricated negative or silent alternate search. A normally authorized
  known-ID read is separate when the application remains healthy; database/application failure stops the
  application, not a switch to an offline memory copy.
- Reuse the bounded Jev adapter/receipts from [TICKET-00037](00037-TICKET.md) and the shared typed-question
  contract from [TICKET-00042](00042-TICKET.md). Qualify memory-specific question wording, source selection,
  threshold if one is needed, missed useful entries, latency, spend and actual provider disclosure before
  claiming useful retrieval. Do not inherit the file tool's 0.70 bar or the Level 9 example's file cap by
  analogy. Provider egress must protect secrets and prohibited payloads without granting Jev normal Agent
  Permissions or broad independent database access.

TICKET-00039 owns repository-file screening; TICKET-00043 and its future use cases own SQL and application
analysis. This TICKET imposes no new blanket SQL/file restrictions, chooses no embedding provider and creates
no autonomous Agent able to traverse another principal's memory. Prohibited-content removal belongs to
TICKET-00050.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Ask about one permitted entry | N/A — judgment does not mutate memory | Typed Jev yes/no or choice over exact authorized entry/revision | Judgment completed or failed observation | Bounded authorized provider request; typed outcome/known failure, no Agent-context source dump |
| Find entries in an authorized scope | N/A — selection is read-only | Typed question over scoped entries; return matching IDs or actual failure | Selection completed or failed with cause | Only permitted IDs, judgments and safe source references; no memory writes or context bulk load |
| Read selected source | N/A — read-only | Exact entry read through existing scoped MCP operation | N/A — read does not change memory | Authorized original text/provenance enters the Agent's context only when requested |
| Continue after Jev outage | N/A — no fallback mutation | Known-ID exact read if application is available | Provider failure observation | No fabricated negative, offline cache or silent fallback search |

## Validation and permissions

Preserve the caller's authenticated identity and authorized target scope through memory queries and provider
input; never authorize from Jev's answer or an Agent-supplied ID. Respect ordinary versus historical access,
private audit-TASK rules, Workflow/TASK closure and independent reviewer isolation. Protect prohibited
content, credentials and provider disclosure before sending eligible input; client-readable metadata must not
leak another Agent's private content. Judge only permitted source revisions, bound actual usage through the
qualified shared evaluator and return precise unknown/error outcomes rather than claiming negative coverage
from unevaluated records. No second authorization ceremony inside one already authorized request is required;
a distinct later exact read is its own authorized operation.

## Acceptance and evidence

- Demonstrate a typed question against one permitted entry and a scope-wide question that narrows to IDs,
  followed by an exact source read only when requested. Verify matched IDs, source revisions and failures
  without asking Jev to generate explanations or memory text.
- Compare question-based lookup with authorized exact reads for missed useful entries, private-scope isolation,
  cost/latency and actual provider-data handling. A vendor demo is not Fight production qualification.
- Demonstrate actual timeout/provider/input-limit reporting, safe known-ID operation while Jev is unavailable,
  excluded rejected/retracted guidance and no cached judgment reused as memory authority. Verify owned tool
  behavior and relevant integration contracts, not the Jev provider's internals or MCP configuration text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved under [EPIC-00011](../epics/00011-EPIC.md) and
[WF-026](../wayfinder/tickets/WF-026-define-sandbox-memory-retrieval-and-mcp-access.md), reusing the
[context-loading research](../wayfinder/research/WF-026-agent-memory-context-loading-research.md).
Qualified file, SQL and generic Jev tools are upstream capabilities, not proof that memory lookup is already
available. No provider request, MCP tool enrollment or live retrieval was performed by accepting this TICKET.
TASK decomposition is separate.
