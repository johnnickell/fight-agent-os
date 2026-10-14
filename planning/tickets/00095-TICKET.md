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

### Required Jev gate before web-content ingestion

Screen external web content through the shared typed Jev capability in [TICKET-00037](00037-TICKET.md) before
releasing it to the working Agent. Apply the gate to search titles/snippets, retrieved page content and extracted
link labels, including every separately authorized follow-up page/subpage. Artifact reads, cached results and
resumed context must not bypass admission. Bind screening to the exact content representation released, its digest,
origin and current policy; changed content or policy requires a new evaluation. Retained content remains untrusted.

Use a protected, versioned question/rubric asking whether the supplied web content contains a likely prompt-injection
attempt. Treat that content as untrusted evaluator input, never instructions or executable tools. Return a bounded
typed judgment with explicit uncertainty through the shared evaluator; PHP owns the admission decision. Define and
qualify this consumer's conservative thresholds before activation; do not reuse the file-relevance 0.70 threshold
or confuse Yes probability with classification confidence. A valid negative that satisfies the admission policy
may release only the evaluated content. A likely injection, uncertain/incomplete answer, refusal, invalid result,
timeout, unavailable Jev or exhausted budget withholds the content and returns a safe reason without echoing it.
Do not silently downgrade to unscreened delivery or rephrase/retry until the material passes.

Authorize disclosure to Jev separately from retrieval and reuse protected provider access, strict response
validation, bounded input/time/concurrency/spend and cancellation from the shared capability. Never silently
truncate an oversized page and mark the whole page screened. Any chunked policy must cover every released segment
and qualify context-spanning attacks within bounded aggregate work; otherwise return unsupported/incomplete.
The evaluator has no tool or permission-granting authority. Screening can miss attacks or flag benign material;
passing never certifies safety or weakens SSRF, disclosure, tool, filesystem or Workflow restrictions.

Keep a bounded, access-controlled audit trail linking the invoking human/Agent, Workflow/assignment, authorized
query/retrieval and redirect chain, source digest, evaluator/rubric/policy versions, coverage, admission decision,
failure category and available usage. Minimize sensitive query/URL fields; exclude raw hostile bodies, credentials
and private content from ordinary logs, receipts and denial responses. Auditing does not authorize additional
retention or disclosure. Reuse the first-party broker and shared evaluator, not a second Jev client or authority model.

Exclude arbitrary third-party MCP, direct child credentials, generic web crawling/browser automation, unrestricted
network, another search-provider platform, autonomous work selection and a demonstration-only research screen.
This is the accepted research seam for useful scoped work, not a prerequisite on every TASK that needs no web access.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Search public references | N/A — search is an application Query, not a Planning mutation | Bounded authorized web search with current context | Sanitized operational/usage observations, not accepted Planning or Workflow transitions | Protected external request and compact attributable results; possible provider cost |
| Retrieve a selected page | N/A — retrieval is an independently authorized Query | Exact bounded URL request with checked resolved destinations | Safe retrieval/coverage observations, not authority-bearing events | Bounded external read; no arbitrary proxy or follow-up execution |
| Screen web content before release | Internal bounded Jev evaluation; no permission or Workflow mutation | Typed injection judgment and deterministic admission outcome | Redacted source/policy-bound screening and usage observations | Separately authorized evaluator disclosure/cost; withheld content never reaches the working Agent |
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
- Search and retrieval release no external content before the mandatory Jev gate admits the exact delivered
  representation. Follow-up pages, link labels, private Artifact reads and cached/resumed evidence cannot bypass it.
  Changed source/policy invalidates admission; suspicious, uncertain, partial, failed or unavailable screening holds
  content without leaking it through errors, logs or alternate tools. Protected receipts retain attribution and usage.
- Qualify the versioned rubric and thresholds with bounded benign references, quoted attack examples and adversarial
  web content, including attempts to influence the evaluator itself. Report false positives, missed detections and
  coverage limits honestly. Controlled outcomes verify admission/failure behavior; they do not establish live
  detection accuracy. Missing disclosure authority or unqualified screening keeps web-content delivery unavailable.
- Large evidence is private and digest-bound; unknown retrieval/usage and historical reuse are labelled honestly.
  Owned application/adapter tests, directly authorized provider/network qualification and `./bin/build` pass.
  Doubles test fault contracts, not live Brave support; no HTTP-library, configuration or sandbox-tool tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00381](../tasks/00381-TASK.md) | Admit exact web content through Jev | ready-for-agent |
| [TASK-00382](../tasks/00382-TASK.md) | Search through protected Brave access | ready-for-agent |
| [TASK-00383](../tasks/00383-TASK.md) | Retrieve separately authorized public pages | ready-for-agent |
| [TASK-00384](../tasks/00384-TASK.md) | Retain and safely reuse web evidence | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved four bounded delivery slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. Each includes owned behavior, actual operational qualification and guidance;
no proof-only TASK or implementation effect is introduced.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00381](../tasks/00381-TASK.md) | Exact-content web-injection screening and PHP admission through protected shared Jev |
| B | [TASK-00382](../tasks/00382-TASK.md) | Current-authorized bounded Brave search and screened compact results |
| C | [TASK-00383](../tasks/00383-TASK.md) | Separately authorized connection-level SSRF-safe retrieval and screened page representations |
| D | [TASK-00384](../tasks/00384-TASK.md) | Verified private web evidence and current screened historical/cached/resumed delivery |

TASK-00381 consumes TASK-00171's actual authenticated shared evaluator integration, transitively TASK-00159/00184
and TASK-00245/00244's first-party broker/current-use boundary. TASK-00382 and TASK-00383 each consume TASK-00381
and are independent of one another. TASK-00384 consumes TASK-00382/00383, TASK-00374 private storage,
TASK-00378 protected reads and TASK-00353 original conversation recovery. All four are feature records,
`ready-for-agent` behind unfinished suppliers, with blank order/pr. No existing TASK gains a blanket web blocker.

Local Pi readiness alone does not qualify managed Jev. TASK-00171 retains the generic authenticated judgment
capability; TASK-00381 owns the versioned web rubric, conservative thresholds, exact representation/coverage binding
and PHP decision. Qualify actual detection limits before activation; the file-relevance 0.70 rule is not reused.
No second provider client, privileged Jev identity, MCP server, credential path or universal tool-result gate is added.

Search and retrieval retain separate Permissions, current caller/delegation and separate outbound/Jev disclosure.
TASK-00382 holds Brave credentials in the installation; TASK-00383 qualifies every actual resolved connection and
redirect with bounded extraction. Safe errors never echo withheld content. Unknown provider outcomes preserve
original correlation and liability, not automatic retries, fresh-success claims or provider fallback.

Initial search/retrieval delivers only fresh bounded screened representations; unqualified oversized, retained,
cached or resumed routes remain explicitly unavailable. TASK-00384 integrates exact-current admission at actual
Agent-facing Artifact and context ingress, including generic reads, without a second transcript store or general
cache. TASK-00378's queries and status inspection remain provider-free: when new screening is needed, withhold
Agent delivery and identify the separately authorized screened-delivery operation. Human evidence inspection keeps
its ordinary current-access/inert-content rules, not a universal Jev call. Historical bytes remain historical even
after new evaluation, and missing/redacted content is never silently refetched.

TASK-00353/00378 retain generic recovery/read ownership and never reverse-depend on completed web research.
TASK-00374's reference/hold and restriction participants remain authoritative; TASK-00379/00380 keep lifecycle/
redaction policy, with actual web-copy participation required before the affected route can expose/delete content.
TASK-00364 now names these conditional external-web suppliers while preserving local-only preparation and its exact
prototype/host hold. TASK-00350's host/broker gap and independent authority/role/source-composition holds remain.
TICKET-00043/TASK-00174 may compose only qualified web capabilities under the same original principal; no web
requirement is added to every judgment, local-only preparation or terminal execution TASK.

TICKET-00096 now maps TASK-00385–00389's safe history/live delivery, not web-content retention or screening.
No transcript/excerpt or raw web body becomes safe history through this mapping; existing owners and holds remain.
This decomposition is complete, not implementation or provider qualification. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve terminal-first Board priority, active
TASK-00138, all existing orders and Markdown authority until separately approved verified cutover. Implementation
checkout, finite provider/network/credential authority, full build, independent review and publication remain separate.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
uncommitted planning. Before allocation the highest live TASK was TASK-00380 and the archive contained no TASK
records. TASK-00381–00384 match the approved four-slice metadata/dependency graph and template sections; all are
unranked feature records, ready behind unfinished suppliers, with no PR or implementation claim.

`./bin/planning-check --write` and `./bin/planning-check` passed with 511 records, 419 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 76 new/untracked TASK
whitespace checks passed, including these four. Explicit checks preserved independent `needs-info` consumer and
host/prototype holds, unchanged TASK-00138/00139/00142/00159/00184/00226 records against this worktree's HEAD and
existing producer priority/status. TASK-00171/00353/00364/00378 keep their existing dependencies; coordination now
names conditional web composition rather than adding blanket web blockers. Active TASK-00138 remains the only
active Board item, order 2. That historical split advanced the handoff to TICKET-00096, with TICKET-00097–00109
still to decompose at that point.

These are planning receipts, not fresh Brave/Jev detection, broker, SSRF, provider, storage or context-recovery
qualification. Full `./bin/build` was not run and remains required before commit/publication. All changes remain
uncommitted; independent review and publication are separate. No implementation, key inspection/configuration,
provider/network call, web-content delivery, Artifact operation, private session read, runtime effect, cleanup,
live Planning mutation, cutover, commit, push, PR or merge occurred. Unrelated main-checkout work was left untouched,
not claimed clean. Earlier verification receipts remain historical.

### Original requirement approval and supplier handoff

The original allocation requests below are resolved only by the precise mappings above; runtime holds persist.

Approved as area 3 of EPIC-00007's remaining six-TICKET split. Implements the bounded search and retrieval seam in
[WF-016](../wayfinder/tickets/WF-016-define-runner-dispatch-and-recovery.md), with
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume exact early contracts from [TICKET-00061](00061-TICKET.md), [TICKET-00087](00087-TICKET.md) and
[TICKET-00094](00094-TICKET.md), not finished browser Planning, managed review or the whole execution journey.
That TICKET now supplies TASK-00374 private Artifact/reference/hold primitives, TASK-00375 contribution facts,
TASK-00378 bounded reads and TASK-00379/00380 retention/redaction composition. Map only the required early
participants during decomposition, not a blanket dependency on full execution check/Review/browser delivery.
Evidence access does not authorize model disclosure: private/cached/resumed web content still needs this TICKET's
qualified exact-content Jev gate. Ordinary inert previews are not prompt-injection screening qualification.
TICKET-00043 may compose these operations only under the same caller authority; Jev is not a privileged principal.
TICKET-00092 now maps TASK-00364's bounded AFK Planning preparation. Its external-web routes consume this TICKET's
separately mapped and qualified search/retrieval/disclosure/SSRF/exact-content Jev gate; until then they remain
unavailable. Local-only preparation does not require completed web research, and this supplier never depends on
finished browser Planning or named Project Manager use. TASK-00364's prototype-execution hold is separate; this
TICKET supplies no prototype runner or generic network bypass.
Provider enrollment/live calls need separate authorization and qualified limits. No API key, MCP connection,
retrieval, disclosure or implementation TASK is created by this planning approval.

### Approved web-content screening amendment — 2026-10-13

John approved mandatory Jev prompt-injection screening for this web-research capability, with an independently
committed amendment in the existing planning worktree. This accepts the web-specific gate previously only a candidate
in EPIC-00006; it does not require universal tool-result screening or change the earlier Harness milestone.
At decomposition, bind the web gate to TICKET-00037's qualified evaluator and the production first-party authority/
broker contracts; local Pi readiness alone does not prove managed-service availability. Settle the rubric, thresholds,
coverage and unavailable behavior before activation. No TASK allocation, implementation, provider call or runtime
activation is performed by this amendment.
