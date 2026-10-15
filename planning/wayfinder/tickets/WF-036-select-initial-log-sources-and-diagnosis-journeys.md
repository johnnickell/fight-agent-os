# Select initial log sources and diagnosis journeys

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Operational logs and failure diagnostics](../operational-observability-map.md)
**Depends on:** —

## Question

Which sources and concrete operator debugging journeys make the first observability release useful without
turning log access into broad project, transcript or Docker authority?

## Must decide

- Agent OS services: which HTTP, worker, scheduler, runner, ingress and supporting-service failures belong in the
  first release, with actual entry points such as an operation's public correlation reference.
- Managed execution: which sanitized operational events/output diagnose a failed run, check or protected tool
  operation; relate available Workflow, TASK, phase/process/run and project identifiers without inventing them.
- Registered project services: explicitly excluded by John, not deferred delivery. Do not collect runtime logs
  from applications/projects Agent OS manages; registration grants no collection authority.
- Browser JavaScript errors: explicitly excluded by John. Server-side diagnostics for browser requests remain
  included; no client exception-reporting/source-map ingestion is implied.
- What the operator sees when identifiers, source coverage or logs are absent, purged, delayed or unavailable.
- Initial audience and access boundary: installation operator versus project-scoped viewer and any sensitive-detail
  distinction, including streams, details, search, exports and archives. Correlation is a reference, not authority.

## Resolution boundary

Resolve sources, entry journeys and required relationships only. Do not select a backend/viewer, change permissions,
install collection or import transcripts. Preserve WF-017's authoritative/observational distinction and the existing
[TICKET-00096](../../tickets/00096-TICKET.md)/[TICKET-00097](../../tickets/00097-TICKET.md) history/browser owners.
Follow-on questions about data handling belong to WF-037; collection and product qualification remain downstream.

## Accepted source direction — application activity and Monolog

John rejected the proposed blanket exclusion of query/payload logging. SQL query logging is not required, but
**all application commands, application queries and application events belong in the operational log scope**,
not only failures. Everything the application sends to Monolog must enter the logging/collection pipeline,
including all application channels and levels. The current general NullHandler is a baseline gap, not the intended
behavior. Do not quietly discard records through channel omissions, level thresholds or sampling.

A special Handler or Formatter is permitted if needed; exact placement and behavior remain WF-037/038 decisions.
This direction requires useful application-activity context, not unrestricted serialization of message objects,
query results or payloads. Redact credentials and prohibited sensitive content while preserving safe debugging
value; payload fields and redaction rules must be decided explicitly in WF-037, not excluded wholesale here.
The SafeHttpDiagnostics contract still requires sanitizing Throwable-derived data before a logger/formatter;
a downstream handler is not permission to forward a raw Throwable as PSR-3 exception context.

The intended additional journey is: failed operation → correlated command/query/event activity → relevant
sanitized inputs/context, outcome and exception → surrounding application and service activity. Log observations
of application events remain distinct from authoritative Workflow events and audit evidence. Logging does not
prove transaction commit or successful event delivery; dispatch, execution, commit, retry and replay semantics
must be distinguished in the downstream diagnostic contract.

Normal delivery must include all these records. This is not a claim of infallible or exactly-once storage:
WF-038/039 must specify buffering, recovery, capacity and visible loss/delivery-failure behavior without silent
success claims. No implementation, logger reconfiguration or runtime activation is authorized here.

## Source boundary and diagnosis journeys

The source boundary covers Agent OS application activity/diagnostics, its own supporting-service operational logs
and sanitized managed-execution output. Relevant supporting services are ingress/Nginx, PHP, PostgreSQL, Redis,
Mercure and SeaweedFS where actually deployed. The source set is a delivery requirement, not a claim that the
complete planned runtime or managed execution exists today.

- Failed HTTP/worker operation → correlation → application commands/queries/events, useful exception and surrounding
  service activity. SQL statement logging is not required; safe application context is not excluded wholesale.
- Failed managed run → known Workflow/TASK/run/process identifiers → runner lifecycle, protected-operation and
  check/build/test output → failure and surrounding execution context. This is not agent transcript ingestion.
- Suspected missing logs → actual coverage and delivery state. Distinguish no records in the requested window from
  not captured, delayed, unavailable, expired or purged; unavailable identities/chronology remain unknown. Exact
  collection status and gap/recovery mechanics are WF-038's downstream responsibility.

John explicitly excluded collecting runtime/service logs from registered applications/projects managed by Agent OS.
This is **out of scope, not deferred work or an implicit later milestone**. Project identifiers may still scope
Agent OS's own activity and managed-run output. An explicitly run check's output belongs to its managed attempt;
that does not enroll the project's persistent application or database-service logs. John explicitly confirmed that
managed-run and check output is part of Agent OS operation, whereas CMS runtime logs belong to the CMS and its
own observability.

John also excluded browser JavaScript-error ingestion. Server-side handling of browser HTTP requests remains in
scope, but browser-only exceptions/source maps are not ingested. No automatic later browser-error capability is
promised. The current terminal-first milestone is unchanged.

## Accepted initial access boundary

John approved installation-wide access for explicitly permissioned log operators, not an automatic grant from
ordinary project access. Log viewing/stream/search/detail and export/archive operations require their applicable
explicit permissions; links and correlation identifiers never grant access or Docker authority. Exact permission
naming, sensitive-detail policy and enforcement mechanics remain WF-037/041 decisions within this boundary.

## Resolution

John accepted the source/journey and operator-access boundary and explicitly confirmed the ownership distinction:
managed-run/check output is Agent OS operation; the CMS owns its runtime logs and its own observability.

The first release covers all Agent OS application commands, application queries and application events, all its
Monolog records/channels/levels, its supporting-service operational logs and sanitized managed-run/check output.
Useful payload/context is subject to explicit redaction, not blanket exclusion. SQL statement logging is not
required. Registered-project runtime-log collection and browser JavaScript-error ingestion are excluded, not
implicitly deferred. No agent transcript ingestion or authoritative Workflow/audit replacement is introduced.

The diagnosis journeys above and explicitly permissioned installation-wide operator access are accepted. Missing
coverage/identifiers and unavailable/late/expired/purged data must remain distinguishable rather than fabricated.
This is planning acceptance, not implemented collection, a guarantee of perfect delivery, a backend selection or
an amendment to API responses. No service, dependency, runtime configuration or Permission is changed. The existing
terminal-first milestone and Board priority remain unchanged.

[WF-037 — Define redacted diagnostics and shared HTTP adoption](WF-037-define-redacted-diagnostics-and-shared-http-adoption.md)
is the next unblocked decision. It must consume these source/access constraints, including useful sanitized
command/query/event context, complete Monolog coverage and the prohibition on raw Throwable logging. WF-038/039
own failure recovery and preservation, and WF-041 owns actual browser/authorization integration. No EPIC, TICKET
or TASK is created or authorized by closing this decision.

## Later diagnostic-policy amendment — WF-037

In the WF-037 interview John rejected log redaction and accepted the recommendation to preserve emitted logs
while producers avoid emitting secrets. The original redaction/sanitization premise above is historical, not
permission to reintroduce pipeline redaction. The accepted amendment and remaining diagnostic/Common compatibility
questions are owned by [WF-037](WF-037-define-redacted-diagnostics-and-shared-http-adoption.md#accepted-partial-direction--no-pipeline-redaction-avoid-secret-emission).
Source/journey scope, all-Monolog coverage, explicit installation-operator access, transcript exclusions and API
response behavior remain unchanged. WF-036 stays Closed; no implementation or package-contract amendment is implied.
