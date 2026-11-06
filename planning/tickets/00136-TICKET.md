---
id: TICKET-00136
epic: EPIC-00023
title: Adopt shared failures and safe error presentation
status: ready-for-agent
---

# Adopt shared failures and safe error presentation

## Problem statement

Agent OS duplicates generic Application failure vocabulary and owns substantial HTTP error translation. Canonical
Common categories and safe presentation should replace redundant machinery, not be stacked beside it. The move
must distinguish intentional business failure from internal faults and deliberately update wire contracts rather
than silently changing what existing clients interpret.

## Solution and boundaries

- Qualify actual released Common Application failure categories and safe HTTP contracts against the application's
  real composition. Record source/release/lock identities and deliberately update Composer requirements and lock
  when needed. Local v1.4.0 tag/source observations identify a candidate, not completed distribution or consumer
  qualification. Do not wait for unrelated DBAL or Role-audit work to adopt independently available error support.
- Use shared Application categories directly where their meanings fit: authentication required, permission denied,
  intentional resource absence/gone, conflict/precondition, invalid input, business-rule rejection, rate limiting,
  temporary unavailability and server failure. Keep a local subtype only when it adds a useful real contract.
- Reconcile required-query result-or-failure behavior with package contracts and the capability owners. Domain
  repositories retain aggregate-or-null semantics; optional discovery/status is explicit. Wrong identity/result
  contracts, unknown persistence faults and programmer errors remain internal failures, not fabricated 404/409/422.
  Domain exceptions stay Domain-owned and must not import or extend Common Application categories.
- Preserve structured field validation rather than flattening it into a generic exception. Use deliberate safe
  projections for public messages/field data; diagnostic messages, codes, causes and raw validator maps are not
  automatically public-safe because an exception belongs to a recognized category.
- Compose Common's safe HTTP boundary as the one public error handling/reporting owner. Account for actual Slim/
  middleware invocation order and all covered versus uncovered boundaries. Keep necessary adapter-only routing,
  validation, authentication challenge and other protocol mappings without recreating the generic classifier.
- Deliberately reconcile JSend fail/error shapes, HTTP status, relevant headers, safe validation data and affected
  browser/API consumers. Common expected failures use fail; update current client contracts, OpenAPI/validation
  representations and tests instead of retaining dual modes or fallback classifiers solely for compatibility.
- Keep exactly one redaction-aware diagnostic reporting path and truthful safe handling when reporting/projection
  fails. Unknown Throwable gets safe server-failure treatment; do not classify by arbitrary message/code/cause or
  forward private exceptions to an unredacted logger. Use the qualified package behavior rather than copying it.
- Inventory and delete superseded generic local exceptions, classifiers, projectors, middleware branches and tests
  after replacement coverage. Retain only genuinely owned transport projection/protocol responsibilities and any
  useful specific local failure contract. No permanent parallel error framework or compatibility alias layer.
- Keep Console failure/exit handling transport-appropriate. Planned MCP/OAuth mappings retain their own protocol
  boundaries; HTTP error middleware does not qualify an MCP endpoint or HMAC authentication mechanism.

Excludes implementing Common, creating endpoints/permissions, designing a new generic error framework, wholesale
frontend restructuring, or replacing every Domain exception. [TICKET-00134](00134-TICKET.md) and
[TICKET-00135](00135-TICKET.md) own capability-specific typed Views and use-case correction; this requirement owns
the shared failure vocabulary, transport boundary and affected consumers, not those entire workflows.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Existing use case intentionally refuses an operation | Existing owning command/method, unchanged in purpose | Existing required-resource or optional reads | No new domain event from error presentation | Correct transport-neutral failure intent and safe public outcome |
| Unknown/internal failure reaches HTTP | Existing invocation | Existing invocation | No invented event | Generic safe server response and one redacted diagnostic path |
| Client receives expected refusal or field errors | N/A; presentation | N/A; presentation | N/A | Agreed fail/error shape, status, safe data and required protocol metadata |
| Console or future protocol consumer sees a shared category | Existing intent when applicable | Existing read when applicable | No new event | Protocol-appropriate output without importing HTTP status/response objects inward |

Error response creation and diagnostics are technical effects, not business mutations or guarantees of rollback.

## Validation and permissions

Adapters own admission under EPIC-00023. An exception category reports intent but performs no permission check,
retries no command and proves no transaction rollback. Use only independently authored/allowlisted public data;
keep credentials, raw request fields, traces and arbitrary exception context private. Preserve necessary native
protocol metadata through explicit mapping. Required lookup absence is intentional; generic lookup/storage/auth
exceptions cannot be guessed into a public category. Test actual covered boundaries, not merely type existence.

## Dependencies and sequencing

Use [TICKET-00131](00131-TICKET.md)'s authority. This slice owns its necessary Common manifest/lock update and real
qualification, coordinated once with [TICKET-00133](00133-TICKET.md)'s separate consumed-contract adoption. Supplier
availability holds only affected mappings/consumers; the entire transaction/audit ticket is not a blanket blocker.
Coordinate concrete capability failures and owned client changes with TICKET-00134/00135. Each change still owns
its complete behavior evidence; a mapper-only partial slice does not complete integration.

## Acceptance and evidence

- Actual qualified upstream APIs and a reproducible dependency selection support the adopted categories and safe
  HTTP composition. All surviving consumers use canonical names; unavailable capabilities remain explicit holds.
- Representative intentional absence, optional status, conflict/precondition, validation/rule refusal, admission
  rejection and internal faults map according to agreed intent, not diagnostic strings or numeric codes. Current
  Domain/Application dependency direction is preserved.
- Real HTTP interactions prove public non-disclosure, agreed fail/error/status/data shapes, necessary protocol
  metadata and one diagnostic owner. Reporting/projection failures remain safe and truthful under package contracts.
  Ordinary successful responses remain correct; a 500 never claims that earlier business effects rolled back.
- Affected browser/client error handling, OpenAPI, field validation presentation and docs agree with deliberate
  breaking changes. No old/new response mode, exception alias or competing classifier remains solely for application
  compatibility. Do not advertise the hypothetical MCP boundary as implemented or verified.
- Superseded local machinery/tests are removed after meaningful replacement coverage; keep useful application/
  transport contracts and safe projections. Run focused behavior checks and owning generated-contract/dependency
  tools, then the canonical gate with truthful fresh/reused evidence, independent Review and applicable QA.
  Do not add product tests of wiring/configuration, architecture or the quality tools themselves.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00582](../tasks/00582-TASK.md) | Adopt Common failures and cut HTTP error handling over end to end | in-progress |
<!-- /planning:children -->

## Decisions and progress

John approved this sixth requirement area and then the single complete integration slice, now written as
[TASK-00582](../tasks/00582-TASK.md), kind `chore`, order `19`, `ready-for-agent`, with TASK-00564 as its sole local
blocker. One coherent PR owns actual Common dependency qualification/adoption, generic failure consumers, the live
Slim/Common error boundary, safe projection/diagnostics, direct response producers, structured validation, affected
client decoding/presentation, Console behavior, OpenAPI/docs, meaningful verification and obsolete-path removal.
Separate exception/middleware/client PRs would require dual formats or competing classifiers.

Approved observable changes include Common's expected-4xx fail versus server/unavailable error shapes; deliberate
401 challenge, 405 Allow and 429/503 Retry-After; safe structured field projection; useful partial-invitation data
under the 503 error envelope; and replacement of top-level current-session code-5801 coupling with an explicit
safe public intent contract. Keep refresh/logout cookie effects, current-session guidance, no automatic mutation
retry and honest partial/post-commit uncertainty. Correlation/no-store remain owned transport responsibilities.

Common offers each handled Throwable once to one redaction-aware diagnostics sink; any deliberate expected-failure
filtering happens there, before a raw exception/request/context could reach a logger formatter. Follow qualified
safe reporter/projector/factory fallbacks without recursive reporting or false delivery/response guarantees.
Public data is independently authored/allowlisted, not private exception text, arbitrary objects or validator maps.

A concrete inspected Common safe-error candidate supports a ready integration plan, not a claim of distribution,
installation or consumer qualification. Current Access Control's Common ^1.3 requirement permits the observed
minor candidate; actual resolution/signatures/transitive requirements and manifest/lock/consumer proof belong to
TASK-00582. If an actually required capability is unavailable, hold this TASK precisely as needs-info; do not invent
a shim or impose a complete upstream/DBAL/Role-audit/Project/identity-refactor prerequisite. Capability-specific
query/result and activation/correction repairs remain with TICKET-00134/00135, whose existing readiness is unchanged.

Each real affected throwing/catching/direct-response/client boundary must have replacement or justified-retained
evidence, safe actual HTTP/browser/Console behavior, direct owning-tool checks, normal canonical gate with truthful
fresh/reused evidence, independent Review and applicable QA. Generic local exceptions, ApiFailureMapper,
MappedApiFailure, superseded middleware branches/projectors/tests and old/new compatibility modes are removed after
replacement proof. Domain exceptions and useful owned transport/specific failure contracts retain their real owners.
No new endpoint/permission or MCP/OAuth/HMAC qualification is implied.

TICKET-00136's TASK decomposition is complete; all six EPIC-00023 requirement areas now have approved TASK plans.
This is planning only: no package/source/error-boundary/client/schema/data/grant change, product verification,
Review, QA, commit/publication or upstream work begins here. The completed-planning versus unstarted-execution
handoff is owned by [EPIC-00023's current progress](../epics/00023-EPIC.md#progress).

Planning-only verification passes: `./bin/planning-check --write` and `./bin/planning-check` report 741 records,
543 active and current generated views; tracked/new-record whitespace checks pass. TASK-00582 appears on the Board
waiting on TASK-00564, not as active work. No product build was run; these checks establish planning integrity,
not dependency availability/qualification, runtime behavior or independent implementation acceptance.
