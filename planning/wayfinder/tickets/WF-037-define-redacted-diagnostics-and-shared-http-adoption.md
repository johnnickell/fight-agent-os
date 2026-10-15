# Define redacted diagnostics and shared HTTP adoption

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** [WF-036](WF-036-select-initial-log-sources-and-diagnosis-journeys.md)

## Question

What diagnostic contract gives authorized operators useful exception detail, and can qualified Fight Common
safe HTTP handling supply its shared reporting boundary without silently changing Agent OS behavior?

## Must decide

- Use Monolog's native record structure; add required source/service, release and trusted available project,
  Workflow, TASK, phase/process/run, actor and correlation attribution through context/processor extra metadata.
  Distinguish record time from collector receipt time without replacing the record with a bespoke logging protocol.
  Validate client-supplied correlation and preserve propagation; it must not establish identity or authority.
- Retain useful exception type, emitted message, source-relative file/line, stack frames and chained causes.
  Decide bounded event/transport handling and explicit chunking/truncation semantics without disguising content
  changes as verbatim preservation. Stack traces are local call stacks, not a distributed-tracing commitment.
- Consume accepted WF-036 coverage with the later no-pipeline-redaction amendment below: all Agent OS
  commands/queries/events and all its Monolog records/channels/levels reach collection as emitted. Preserve useful
  payload/context, not a blanket exclusion. Producers avoid deliberately emitting credentials/tokens/cookies/keys;
  do not replace exception messages merely because their text lacks a reviewed redaction rule. This does not add
  SQL statement logging, raw request/response dumping or transcript/prompt/hidden-reasoning ingestion. A special
  Handler/Formatter is permitted for collection/encoding, not to reinstate rejected content redaction. Define
  producer data selection, serialization and source-specific handling honestly, without claiming incidental text
  can never contain secrets. The inspected Common hook's pre-logger redaction and no-raw-Throwable requirements
  remain a package-compatibility constraint, not an implicitly accepted application policy.
- Use Monolog's eight standard level definitions and ordinary engineering judgment at each producer; severity
  assignment is not a human approval gate. Distinguish expected rejection from unexpected failure and retain
  repeated/security observations. Common's hook includes expected failures. Accepted WF-036 does not permit
  dropping Monolog records through thresholds, sampling or channel gaps; reporting ownership must prevent double
  emission without suppressing distinct records, attempts or outcomes.
- Prevent duplicates through one reporting owner at each selected transport/process boundary; distinguish retries
  and multiple symptoms from the same reported failure. Preserve the original Throwable inside the trusted
  boundary even if safe response classification uses another representation.
- Define reporter, producer/extractor, formatter and sink failure behavior: generic safe public fallback, no recursive
  logging/retry, no claim of delivery, and independently observable bounded health/failure signaling that does
  not depend on the failed logger. Distinguish local emission from collector/storage/archive confirmation.
- Define current authorization, sensitive-detail scope, data retention and access/purge participation for the
  diagnostic fields, including exports/archives and limitations of backups. No Docker authority follows.
- Decide whether a Common update/adoption is needed at all. If selecting a new release, recheck available tags,
  qualify its complete consumer impact and record exact version/source/lock references; plan a deliberate manifest/
  lock update and full consumer gate. Declining the opt-in HTTP path does not require installing it to prove its
  documented incompatibility. No package update is authorized by this decision interview.
- Preserve API-only scoping, successful responses, no-store and correlation behavior, and application-specific
  response classifications. Current 4xx `error` envelopes use Common's supported JSend API; do not treat them as
  a conformance defect or change them merely for observability. Resolve safe-middleware default/status differences
  explicitly rather than assuming replacement or a projector preserves them. Any proposed migration needs an
  independent rationale, explicit approval and coordinated client/server/contract changes.

## Resolution boundary

Agree the diagnostic policy and adoption/migration plan, not PHP implementation or a dependency update. Keep public
error responses fixed and generic; approved useful detail belongs only in authorized diagnostics. Consume WF-036's
sources and explicitly permissioned installation-wide operator access; ordinary project access grants no log or
Docker authority. Registered-project runtime logs belong to those projects' own observability, and JavaScript-error
ingestion is excluded. Preserve non-API transport ownership. Source-specific collection mechanics are WF-038; final
acceptance and milestone boundaries are WF-042. Do not treat Common's reporting hook as a complete logger.

## Verified starting evidence, not adoption approval

Agent OS `develop` `6263dc6e660f989ac425e4d07a90dc7bc990a92f` has `~1.3.0` in
[composer.json](../../../composer.json), with **v1.3.0** source
`7de6cad6e8a9752973ad9f8e27e285b0c1510582` in [composer.lock](../../../composer.lock) and local installed metadata.
The locally inspected Common **v1.4.0** tag resolves to `87b9580c072f3149d574e09865daa79620c92118`.
Its `SafeJSendErrorMiddleware` calls `SafeHttpDiagnostics::report` once with the original Throwable before
classification/projection, including expected failures. The consumer must sanitize before logging. Failed reporting
returns generic safe 500 without recursive reporting or a delivered claim. Source inspection is not installed
consumer/runtime qualification. Primary sources for requalification:
[Common safe HTTP guide](https://github.com/johnnickell/fight-common/blob/87b9580c072f3149d574e09865daa79620c92118/docs/safe-http-errors.md),
[middleware](https://github.com/johnnickell/fight-common/blob/87b9580c072f3149d574e09865daa79620c92118/src/Adapter/Middleware/Psr15/SafeJSendErrorMiddleware.php),
[reporter contract](https://github.com/johnnickell/fight-common/blob/87b9580c072f3149d574e09865daa79620c92118/src/Adapter/Http/Error/SafeHttpDiagnostics.php)
and [release changes](https://github.com/johnnickell/fight-common/blob/87b9580c072f3149d574e09865daa79620c92118/CHANGELOG.md).
Check release-wide changes, including StreamId behavior and existing consumer integrations, not only new middleware.

[ApiFailureMiddleware](../../../src/Adapter/Http/Middleware/Api/ApiFailureMiddleware.php) currently scopes `/api`
and descendants, propagates validated/generated `X-Correlation-ID`, and logs only unknown errors with fixed fields.
[ApiFailureMapper](../../../src/Adapter/Http/Api/Failure/ApiFailureMapper.php) uses Common's validation `fail`
envelope and its `error` envelope for other known 4xx responses. Common's general `JSendResponseFactory` takes a
controller-selected HTTP status independently of the envelope. Neither these APIs nor a blanket 4xx-to-`fail`
rule requires changing Agent OS. John corrected the earlier conflation of the safe middleware's defaults with a
universal JSend/package requirement; this planning decision must not invent such a requirement.

The opt-in v1.4.0 safe policy does emit `fail` for its expected client-failure categories, differs in validation
classification and requires valid 401 challenge/405 Allow. Its projector cannot change status or freely switch
envelope kind. Application marker exceptions and Slim types are not automatically Common categories. These are
specific adoption constraints, not evidence that Agent OS uses a nonstandard envelope or is required to migrate.

The application mapping was introduced in [TASK-00020](../../tasks/00020-TASK.md#completion-notes) and is now enforced
by [the client decoder](../../../client/src/api/decodeResponse.ts), tests and OpenAPI contracts. Safety, HTTP status
and envelope selection are separate concerns. Preserve current behavior unless John approves a specifically
justified contract amendment; do not silently normalize responses as part of logging adoption.

## Requalification checkpoint — WF-037 interview

Read-only `git ls-remote --tags origin` against Fight Common currently advertises v1.4.0 as the highest versioned
release tag, peeling to `87b9580c072f3149d574e09865daa79620c92118`. The local sibling checkout is at
`c4b52969686fd6581868d262e8ec8d4e3e0389a6`; its inspected safe HTTP guide, changelog, Error directory and safe
PSR-15 middleware have no differences from v1.4.0. Re-reading the reporter contract, middleware and policy confirms
the reporting-before-classification boundary and the existing API compatibility constraints above. The Agent OS
planning worktree still declares `~1.3.0` and locks v1.3.0 at the recorded source reference. No update, installation,
consumer gate or runtime qualification occurred. Advertised tags do not prove Packagist availability or complete
release-wide consumer compatibility; those checks remain required before selecting an adoption plan.

## Earlier proposal — unreviewed-text withholding rejected

The following proposal was **rejected by John** in favor of no log redaction. It is retained as interview history,
not an accepted policy or the current recommendation:

- Preserve useful command/query/event payload and context through explicit field/type policies. Retain safe
  identifiers, operation names, lifecycle/outcome and diagnostic values; omit or replace credentials and prohibited
  sensitive fields. Do not serialize arbitrary message objects or query results as a shortcut.
- Exception diagnostics retain type, release, source-relative location, argument/object-free stack frames and
  chained-cause structure, with reviewed safe message text and parameters. Exact limits/schema remain to decide.
- For arbitrary exception/third-party message text with no reviewed safe rule, withhold only that text and mark
  why; preserve the event and its safe type/trace/context. Do not substitute a generic public error for the whole
  diagnostic record. Known-secret/pattern filters provide defense in depth, not a guarantee that arbitrary text
  is safe. Reviewing a new source/type can add a useful safe message policy.
- Agent OS service and managed-process output need source-specific policies and coverage rather than silently
  applying the exception-message rule to discard output wholesale. Their detailed policy remains in this decision;
  collection placement and chunking/recovery mechanics belong to WF-038.

## Accepted partial direction — no pipeline redaction; avoid secret emission

John responded: **"No, I would prefer not to redact the logs at all"**. The proposal to withhold unreviewed message
text is rejected. Do not treat the preceding redaction assumptions as settled requirements for WF-037 or silently
reinstate that proposal through a formatter, collector or viewer.

John then answered **"yes"** to the follow-up recommendation to preserve emitted logs while producers avoid
emitting secrets. This is recorded as acceptance of that recommended producer-side boundary, **not** a request to
intentionally emit credentials. The assistant made that interpretation explicit in its response.

Preserve emitted log messages/context without pipeline redaction or withholding unreviewed exception messages.
Application producers avoid deliberately emitting passwords, authentication/session tokens, cookies, private keys
and other secret material. This is producer data selection, not a collector that edits already-emitted content.
Useful application payload/context, exception messages, traces and causes remain intended diagnostic data.

This explicitly amends this map's earlier redaction premise; WF-036's original resolution stays historical with
an appended cross-reference. Source scope, full Monolog coverage, installation-operator access, generic public
responses and project runtime-log ownership remain unchanged. No extra raw request/response dumping, SQL statement
logging or transcript/prompt/hidden-reasoning ingestion follows. WF-017 preservation/privacy ownership is not
silently amended; downstream requirements must surface any concrete conflict.

Producer avoidance does not guarantee arbitrary exception text or third-party/service/process output is secret-free.
No automatic pipeline removal is promised; any incidental sensitive content can persist through authorized search,
exports, archives and backups. Restrict access and address exposures through the agreed operational/purge policy,
not by claiming that operator authorization or search expiry removes already-stored copies.

The inspected Common SafeHttpDiagnostics contract requires redaction before logging and forbids forwarding raw
Throwable context. A no-redaction policy needs an explicit compatibility decision; it is not permission to claim
that the current hook automatically supports it or to fall back to Common's unsafe legacy public-error output.
Remaining diagnostic detail, failure behavior, access and the Common compatibility path are addressed by the
proposal below; ordinary severity assignment is engineering judgment. No implementation or dependency update is
performed here; the accepted no-redaction amendment is planning-only.

## Engineering correction — use Monolog, not a severity interview

John clarified that ordinary log-level selection does not need his approval and directed the assistant to read
Monolog. The earlier three-bucket severity proposal is withdrawn as a human decision. Use all eight standard
Monolog/PSR-3 levels and their documented meanings; choose the level for each producer through engineering judgment,
not blanket HTTP-status rules. Preserve existing records' levels/channels and collect all levels. Levels do not
implicitly enable alerts or authorize sampling, suppression or deduplication of emitted records.

### Read-only Monolog evidence

The planning worktree locks **Monolog 3.11.0**, source `147f303310f06334f03f409e49d7ad1e275ff05a`.
The base checkout's installed Composer metadata reports the same version/source. Read the pinned upstream
[usage guide](https://github.com/Seldaek/monolog/blob/147f303310f06334f03f409e49d7ad1e275ff05a/doc/01-usage.md),
[handlers/formatters/processors](https://github.com/Seldaek/monolog/blob/147f303310f06334f03f409e49d7ad1e275ff05a/doc/02-handlers-formatters-processors.md),
[utilities](https://github.com/Seldaek/monolog/blob/147f303310f06334f03f409e49d7ad1e275ff05a/doc/03-utilities.md),
[extension guide](https://github.com/Seldaek/monolog/blob/147f303310f06334f03f409e49d7ad1e275ff05a/doc/04-extending.md)
and [record structure](https://github.com/Seldaek/monolog/blob/147f303310f06334f03f409e49d7ad1e275ff05a/doc/message-structure.md),
and inspected the corresponding installed Logger, Level, LogRecord, handlers and formatters.

Implementation guidance from those contracts, not new product approval:

- Reuse native message, context, level, channel, datetime and extra fields. Producers supply context; processors
  add trusted correlation/release/run metadata to extra without overwriting producer context.
- Prefer existing StreamHandler plus JsonFormatter for structured stdout/stderr. Level::Debug includes all eight
  levels. Ensure each selected logger reaches collection despite handler ordering/bubbling; a null/filter/sampling/
  deduplication or failure-activated handler must not discard required records. Custom plumbing needs a demonstrated
  gap, not a new abstraction merely to restate Monolog.
- JsonFormatter already normalizes Throwable values, including class, message, code, file/line and previous causes;
  traces require includeStacktraces(true). Its normalizer emits frame locations/internal call identifiers, not a
  complete structured function/argument snapshot. Check any additional frame requirements against actual output.
  Normalization depth/item limits and serialization are not redaction and do not guarantee lossless object dumps.
- Monolog supports exception context and does not require blanket redaction. Common SafeHttpDiagnostics has a
  **separate, stricter consumer contract** forbidding raw Throwable forwarding and requiring pre-logger redaction.
  Do not misstate Common's constraint as a universal Monolog/PSR-3 prohibition or infer compatibility from Monolog's
  formatter capability. The no-redaction direction does not select Common's opt-in HTTP middleware.
- Current Agent OS composition still has a general NullHandler and an Error-threshold HTTP stderr handler with
  default JsonFormatter (traces disabled). Source inspection identifies these gaps; no configuration was changed.
- Monolog's default logger propagates handler exceptions unless given an exception handler. Local processing/write
  success is not collector or archive confirmation. Buffered-handler memory, reset between long-running jobs,
  nonrecursive failure signaling and loss/recovery need explicit application/collector handling, not a success claim
  inferred from a logger call or a failure-swallowing wrapper.

## Additional source evidence — activity instrumentation

The inspected worktree's [command](../../../config/common/command.php) and
[query](../../../config/common/query.php) configurations register Common pipelines with empty filter lists.
The [event configuration](../../../config/common/event.php) selects ServiceAwareEventDispatcher and registers
specific business subscribers; none of these configurations establishes comprehensive activity logging.
Installed Common v1.3.0 supplies CommandFilter and QueryFilter extension points. Its MetricsCommandFilter and
MetricsQueryFilter emit metrics through MetricsCollector, not Monolog activity logs, so they do not satisfy
WF-036. Common's Message/BaseMessage already supply identity, message kind, timestamp, payload type and metadata.
An unrestricted BaseMessage::toArray() calls payload::toArray(); it is not proof of safe producer field selection.
These are read-only source facts, not evidence of installed activity-instrumentation behavior.

## Diagnostic contract

The engineering design below follows the accepted sources, no-redaction and access boundaries and John's resilience
clarification in the resolution. Routine Monolog settings and severity assignment are not separate human gates.
The original decision title predates the no-redaction amendment; the current contract below governs.

### Native diagnostics and application activity

- Use Monolog's existing record/JSON format and all eight standard levels. Prefer StreamHandler to structured
  stdout/stderr with JsonFormatter and stack traces enabled. Preserve message/context/channel/level and add service,
  release and trusted available correlation/project/Workflow/TASK/run/process/actor relationships in processor extra
  metadata. HTTP correlation retains its existing validation/generated value and client/generated provenance;
  client input never establishes trusted identity or permission. Missing relationships stay absent/unknown.
- Use existing Common message identity/type/time/payload-type where available. Producer context includes the operation,
  lifecycle stage, attempt/retry identity where known, outcome, elapsed time and useful explicitly selected payload
  fields. Avoid deliberate secret emission, unrestricted object/request dumps, SQL statement logging and transcript
  ingestion; do not replace emitted text with generic messages or apply collector/viewer redaction.
- Observe every command/query invocation and event dispatch, not just failures. Use Common's filter seams for command/
  query activity and an application-owned observation boundary around event dispatch, including events without
  subscribers. Ensure nested and non-HTTP/worker invocations are covered. Do not require a wildcard business subscriber
  or change event/transaction delivery semantics merely to observe them.
- Distinguish started, returned/completed and failed observations. A returned command/query or dispatcher is not proof
  of database commit, durable publication or every consumer's success. Record an actual commit/publication stage only
  where the owner can confirm it. Preserve retries, replay and distinct boundary observations; duration metadata
  does not implicitly add metrics or distributed tracing.
- In the application-owned Monolog path, use its native Throwable normalization for useful exception class, message,
  code, file/line, trace locations and chained causes. Do not add a bespoke exception serializer merely to duplicate
  this. Strip the known application base path using the formatter's built-in facility where appropriate, not content
  redaction. Do not deliberately capture stack arguments, object snapshots or local variables. Native JSON traces
  satisfy this initial diagnosis contract; richer per-frame function metadata is not a prerequisite.
- Keep the documented normalization defaults (depth 9, 1,000 items per level; native limit markers). Do not add an
  arbitrary message/frame cap or claim a lossless object dump. These limits are not a whole-record byte bound.
  WF-038 must set finite transport/collector capacity and preserve or explicitly account for oversized records and
  managed-output chunking, not introduce silent message/trace truncation or intentional source-level sampling.
- One reporting owner per actual HTTP/worker/process boundary; no competing global ErrorHandler/subscriber for the
  same catch. Bus lifecycle and HTTP outcome records are distinct observations, not records to deduplicate away.
  Normalization/reporting never changes the original failure or business return value. Reset contextual processor/
  handler state between worker jobs so identities do not leak across runs.

### Existing Common HTTP usage; no new safe-middleware adoption

Retain Agent OS's API-only boundary and mapper with Common's existing JSend response/envelope APIs. Preserve status,
JSend kind, safe public messages/data, successful responses, correlation and no-store behavior. Enrich its operational
reporting through ordinary Monolog, including expected failures, without copying diagnostic data into public responses.
The current code's restricted unknown-error fields are a baseline to improve, not the final diagnostic contract.

Do **not** adopt v1.4.0's opt-in SafeJSendErrorMiddleware or implement its SafeHttpDiagnostics hook for this no-redaction
path. Its pre-logger redaction contract, fixed presentation behavior and reporting-failure fallback are not the
selected contract. A projector cannot resolve them. Do not misuse Common's legacy unsafe public-error handler as an
alternative. This is not a rejection of Common generally: Agent OS continues using its already-locked APIs and
messaging seams. No new Common release is needed solely to emit these Monolog diagnostics; no manifest/lock update,
upstream package amendment or package-adoption blocker is introduced by this recommendation. If a later concrete
capability requires an upgrade, qualify that release and existing consumers deliberately before implementation.

### Operational logging failures — resilience intent

**Keep as much working as possible; do not deliberately fail unrelated operations because operational logging fails.**
Isolate diagnostic failures and make degraded observability visible rather than deliberately converting successful
operations/expected rejections into server errors or masking the original failure. This is resilience intent, not a
guarantee that particular operations survive every outage: shared disk, memory, process or dependency failures can
also affect the application. WF-038 must model those concrete failure modes and limits honestly.

An operational log is observational, not a transaction participant. Existing mandatory audit, authorization and
execution-evidence requirements remain enforced by their owners; their failure is not waived by this intent.
Failure to build a safe HTTP response is also separate from failure to log.

- Guard producer/projection/normalization/handler failures without repeating the business operation. Retain the
  original outcome and generic public-error policy. A logger's normal return is local processing, not confirmed
  collection, indexing or archival. Do not let remote search/archive availability become an inline request dependency.
- Signal degraded health through a bounded independent status/error path that does not depend on the failed logger;
  include component, stage, time and known delivery/loss uncertainty, not the failed raw payload. Prevent recursive
  logging loops. Swallowing a logger exception by itself does not satisfy the health/coverage requirement.
- WF-038 chooses concrete buffering, retries, checkpoints, exhaustion behavior and observable gaps; WF-039 chooses
  archive confirmation/retrieval and preservation. Continue-to-operate is not permission to intentionally filter
  required records or claim infallible delivery: a real outage/exhaustion can leave missing logs, which must be visible.

### Access and lifecycle

All searchable diagnostic detail uses the accepted installation-wide explicit log-access boundary. Do not introduce
an additional raw-detail tier just to compensate for a nonexistent redacted tier. Viewing, streaming, search/detail,
exports and archive operations still require their applicable current authorization; identifiers/deep links confer
no authority. Browser/API responses to unauthorized users expose no diagnostic content. Safe rendering treats log
text as data, not executable markup. No Docker access follows.

Exception fields and payload/context follow the same preservation/access policy as their record, including exports,
archives and backups. Search expiry is not deletion or cancellation of a hold. Existing WF-017 holds and post-terminal
grace are preserved; WF-039 owns exact archive/purge mechanics and must report copies that cannot be removed. A secret
incident requires access containment, credential rotation where applicable and owner-controlled cleanup rather than
an assertion that absence from search proves destruction. No generic transcript or Artifact duplication is added.

## Resolution and handoff

John rejected turning routine log levels into approval questions, required reading Monolog, and chose no pipeline
redaction with producer-side secret avoidance. In response to the final outage question he clarified: **"we should
try to keep things working as much as possible"**, not deliberately break everything else. The resilience wording
above records that intent without inventing a promise about which operations survive an actual infrastructure fault.

WF-037 is Closed with native Monolog diagnostics/activity, full selected-record coverage, useful emitted exception
messages/traces/causes, trusted available metadata, single boundary reporting ownership and explicit log-operator
access. Keep existing Common-backed API responses; do not adopt the opt-in SafeJSendErrorMiddleware/
SafeHttpDiagnostics contract for this no-redaction path. No Common dependency upgrade is needed solely for logging.
These are the engineering conclusions under the accepted boundaries, not claims that John approved each routine
library setting or that package behavior was amended.

[WF-038 — Define log collection and failure recovery](WF-038-define-log-collection-and-failure-recovery.md) is the next
unblocked decision. It must specify actual collector/buffer ownership, resource limits, oversized-output handling,
acknowledgements, restart recovery, outage/exhaustion behavior and visible delivery uncertainty/gaps. WF-039 retains
archive/preservation ownership; WF-041 retains browser/authorization integration. Do not ask whether to deliberately
break unrelated features; propose concrete failure handling based on source/capacity evidence and surface only real
tradeoffs. No new backend, service, runtime/configuration change, dependency update, EPIC, TICKET or TASK is authorized
by closing this planning decision.
