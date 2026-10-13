---
id: TICKET-00095
epic: EPIC-00007
title: Provide bounded first-party web research
status: ready-for-agent
---

# Provide bounded first-party web research

## Problem statement

An isolated Agent may need external factual references without receiving a provider key or unrestricted shell
network. Search and page retrieval have different disclosure and SSRF risks and must remain separately authorized.

## Solution and boundaries

Expose a first-party Agent OS MCP search tool backed by an application Query and injected web-search capability;
the protected adapter uses the HTTP client and an installation-held Brave Search API key. Validate bounded query,
count, filters and pagination against current caller/Workflow context. Return compact results with source/provider
provenance, exact query/page identity, coverage and safe failures. Large or raw material uses authorized private
Artifact references through TICKET-00094, not unbounded tool output or event payloads.

Provide separately permissioned page retrieval with enforced SSRF protection. Local file/network read authority or
permission to search does not authorize arbitrary retrieval. Validate URL scheme/host/port and every actual resolved
connection/redirect destination; protect loopback, private/link-local/internal services, metadata endpoints and other
forbidden targets against alternate encodings, DNS changes and redirect chains. Bound redirects, requests, response
bytes, decompression, content types, elapsed time and aggregate work. Never use returned source URLs as automatic
permission or allow provider/server redirects to disclose credentials to another origin.

Consume TICKET-00061's real current Agent authentication, delegated authority and tool availability plus
TICKET-00087's first-party origin boundary. Preserve the actual invoking human/Agent, assignment, Repository and
environment scope on every request. The broker performs the permitted operation, not arbitrary HTTP proxying or
arbitrary HMAC signing. Direct Brave API/MCP access and raw provider credentials stay unavailable to Pi. Shell network
remains default-deny under TICKET-00032; this tool is not a network bypass switch.

Authorize outbound query/content disclosure separately from local access. Minimize sensitive query inputs; prevent
credentials, private source or disallowed personal data from being sent merely because an Agent supplied them.
Apply explicit provider/request limits and report unavailable configuration, budget/capacity, denied access, timeout,
partial results and unsupported content truthfully. No silent provider replacement or unreported broad crawl.

Treat retrieved pages/snippets as untrusted source data. Embedded instructions cannot alter identity, call other
tools, expand disclosure, update policy or authorize follow-up retrieval. Preserve exact fetched identity/time and
content digest when retained; do not fabricate quotations or confuse a search snippet with a verified page read.
Reused evidence remains historical with current access checks, never reported as a fresh retrieval. Stop/cancellation
and uncertain provider outcomes retain actual original correlation and bounded recovery, not a promise of no billing.

Exclude arbitrary third-party MCP, direct child credentials, generic web crawling/browser automation, unrestricted
network, another search-provider platform, autonomous work selection and a demonstration-only research screen.
This is the accepted research seam for useful scoped work, not a prerequisite on every TASK that needs no web access.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Search public references | N/A — search is an application Query, not a Planning mutation | Bounded authorized web search with current context | Sanitized operational/usage observations, not accepted Planning or Workflow transitions | Protected external request and compact attributable results; possible provider cost |
| Retrieve a selected page | N/A — retrieval is an independently authorized Query | Exact bounded URL request with checked resolved destinations | Safe retrieval/coverage observations, not authority-bearing events | Bounded external read; no arbitrary proxy or follow-up execution |
| Retain large useful evidence | Existing Artifact admission/verification operation | Current content identity, visibility and limits | Existing committed Artifact facts | Private immutable evidence reference without raw payload in events |
| Inspect availability or original result | N/A — read-only status | Safe tool/provider availability and authorized retained result | N/A — inspection does not retry or invoke a provider | Honest denial/unavailable/partial status and provenance |

## Validation and permissions

Require separate current intent-specific search and retrieval Permissions and applicable human/Workflow delegation,
scope, host constraints and disclosure policy. Check at real entry/invocation, not only cached tool discovery. A
Skill, alias, source-contained instruction or UI visibility is not authority. Fail closed on missing current authority
or unsafe/ambiguous destination; a denied request must not retry through another tool or direct shell route.

Configure protected provider access explicitly, keep raw keys/private request content out of browser/model-facing
errors, child environments, transcripts and logs. Validate strict bounded inputs and safe result shapes; escape
untrusted output at presentation. Queries must not mutate Planning or claim execution success, though their external
I/O/cost and optional separately commanded evidence retention remain explicit side effects. Any owned durable intent
or audit uses the canonical transaction boundary with external calls outside it.

## Acceptance and evidence

- An actually authorized Agent performs useful bounded first-party search and separately authorized retrieval with
  source/provider provenance through the protected broker; no direct Brave key/API access is exposed to the child.
- Denied/revoked authority, unapproved disclosure, unsupported input and unconfigured provider fail safely. Search
  permission alone cannot retrieve a page, and content cannot grant further tools or instruct policy changes.
- Actual HTTP adapter behavior covers redirect/DNS/address SSRF boundaries, response/decompression/time limits,
  unavailable/partial results and secret-safe diagnostics. Use controlled safe targets, never production attack probes.
- Large evidence is private and digest-bound; unknown retrieval/usage and historical reuse are labelled honestly.
  Owned application/adapter tests, directly authorized provider/network qualification and `./bin/build` pass.
  Doubles test fault contracts, not live Brave support; no HTTP-library, configuration or sandbox-tool tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00007's remaining six-TICKET split. Implements the bounded search and retrieval seam in
[WF-016](../wayfinder/tickets/WF-016-define-runner-dispatch-and-recovery.md), with
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume exact early contracts from [TICKET-00061](00061-TICKET.md), [TICKET-00087](00087-TICKET.md) and
[TICKET-00094](00094-TICKET.md), not finished browser Planning, managed review or the whole execution journey.
TICKET-00043 may compose these operations only under the same caller authority; Jev is not a privileged principal.
Provider enrollment/live calls need separate authorization and qualified limits. No API key, MCP connection,
retrieval, disclosure or implementation TASK is created by this planning approval.
