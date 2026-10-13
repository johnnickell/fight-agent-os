---
id: TICKET-00081
epic: EPIC-00018
title: Document reproducible backend API and browser references
status: ready-for-agent
---

# Document reproducible backend API and browser references

## Problem statement

A capability checklist is not enough for a starter maintainer to understand or reproduce a safe integration.
The handoff needs actual backend, API and browser references with useful scenario inputs and observations,
without mistaking proposed endpoints, generated documentation or example UI for a working consumer journey.

## Solution and boundaries

Produce a Markdown reference set from the actual qualified Agent OS implementation and
[TICKET-00080](00080-TICKET.md)'s per-family matrix. Link exact source revisions for package invocation and
composition, current server authorization on HTTP/direct/worker entry paths, PostgreSQL shared transactions and
expected-state/reference fences, Agent operation/cohort/restore controls, protected sink and recovery, human
credential delivery, Feature lifecycle/runtime enforcement and safe response shaping. Keep package-owned policy
and Agent OS-owned adapters/orchestration visibly distinct; these are references, not copied package implementations.

For each in-scope family provide reproducible scenario prerequisites, safe inputs, commands, tool versions,
expected and actual observations, redacted consumer receipt references and known limits. Describe significant
races, restart, restoration, revocation and browser authority where applicable using evidence supplied by the
owning work. Any missing qualification returns to that owner and blocks the final handoff; this requirement
does not create a second test product or substitute fresh documentation for actual integration evidence.

Include the actual private OpenAPI and working authorized Swagger Try it out journey from
[TASK-00153](../tasks/00153-TASK.md) and [TASK-00154](../tasks/00154-TASK.md), not a new viewer or alternate
specification. Verify the export against the exact source being documented with the owning tools and record
both source and content hashes. An ignored `.runs/openapi/` artifact or an old successful export is not sufficient
provenance. Keep private export material outside public assets and provide access-controlled references; a
sanitized guide does not make the full specification public.

Explain and demonstrate current `READ_SWAGGER` (`SUPER_ADMIN_ONLY`) on viewer and direct spec reads, exact
configured environment eligibility, shared memory-only credentials and CSRF, no-store behavior, endpoint-specific
actor/target checks and deliberate real Try it out effects. Agent OS initially allows only exact `local`;
other expressly enabled environments retain the same authority boundary. APP_DEBUG is independent, with startup
refusal for strict debug true under exact `prod`/`production`. These are existing owners' requirements, not new
policy or evidence that the currently planned viewer already exists.

Provide optional React application suggestions drawn from observed authorized UI states: loading, denial,
conflict, uncertainty/recovery and current client/server authority separation, with accessible examples and
redacted captures as useful. These suggestions are delivered reference material whose adoption is optional,
not a mandated framework/component library or proof that a different starter is secure. No starter-specific
implementation, public spec endpoint, synthetic product operation, dummy Feature or mandatory live product flag
is required. Full external delivery waits for [TICKET-00082](00082-TICKET.md), not staged unqualified advice.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Follow a backend reference | Documentation authoring and existing owning scenario commands, not a new application command | Inspect exact source, actor/effect boundaries and per-family receipts | N/A for documentation; only owning product events when an authorized scenario executes | Reproducible guidance attributed to real consumer behavior |
| Inspect and exercise the actual API | Existing explicit export/check tools and deliberately authorized Try it out operation | Actual private export, hashes, viewer and endpoint authority | Only the exercised endpoint's existing events; generation emits no Domain event | Verified API references and real authorized observations, with effects disclosed |
| Adapt browser examples | N/A — reference reading is not a product mutation | Observed UI states, frontend/server boundaries and optional React patterns | N/A — no UI or authority is installed in a starter | Safe adaptable suggestions, without a starter acceptance claim |

## Validation and permissions

Follow [ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md) for actual browser authority and private API access.
Documentation access never grants endpoint effects. No automatic scenario execution, destructive replay or
mutation retry after uncertain outcomes; retain every owning operation's approval/recovery policy. Validate
reference resolution, exact source identity, current artifact hashes and safe inputs before claiming reproducibility.

Exclude credentials, tokens, private environment values, hidden provider configuration, raw transcripts,
unauthorized source/receipt bodies and sensitive personal data from prose, examples and captures. Use only
reviewed redacted evidence; a receipt URL is not permission to publish its target. Keep controlled spec/receipt
access distinct from reusable safe guidance, and never weaken Swagger permissions to make a handoff link work.

## Acceptance and evidence

- Each in-scope family has understandable actual code references, commands/prerequisites, expected/observed
  outcomes and redacted evidence tied to the coverage matrix and exact qualified package/consumer source.
- The real implemented viewer and private export qualify together: authorized entry and explicit supported
  requests work, denied/direct reads stay protected, and export source/content hashes match the documented subject.
- Optional React guidance reflects observed Agent OS journeys and current server authority, not a planned screen,
  required starter UI or claim that another framework has been certified.
- Direct source/link/export/redaction checks, owned behavioral evidence where necessary and `./bin/build` pass.
  Do not add product-suite tests of Markdown, generated specs, migration files, wrappers or documentation mechanisms.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00018's three-TICKET split. Depends on TICKET-00080's qualified coverage and the
actual behavior owned by EPIC-00004 and EPIC-00014 through EPIC-00017, with existing TASK-00153/00154 supplying
the private API/viewer. Follow [API documentation](../../docs/api/README.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md). No viewer, export, reference bundle,
scenario execution or starter-source change was delivered by decomposition.

TICKET-00080 now maps [TASK-00305](../tasks/00305-TASK.md)'s one maintained current consumer matrix and exact-
subject readiness assessment. Consume its operation-level coverage, actual qualified source/receipt subjects and
approved dispositions without copying the historical WF-032 inventory into a conformance claim. Its needs-info
scope/runtime holds remain; drafting references does not clear them. This TICKET retains reproducible backend/
API/browser guidance and current private export provenance through TASK-00153/00154, not another viewer or
qualification campaign. Completed references/publication do not reverse-block the accounting supplier.
