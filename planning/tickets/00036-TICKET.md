---
id: TICKET-00036
epic: EPIC-00003
title: Share server-owned form validation with the browser
status: ready-for-agent
---

# Share server-owned form validation with the browser

## Problem statement

The API can enforce Fight Common `#[Validation]` rules, but browsers have no named public-rule catalog or
shared Formik integration. Independent client rules can drift from PHP; field errors can remain after edits,
late responses can restore obsolete errors, and server message lists currently collapse to a generic client
validation failure. Users need timely, accessible feedback without weakening authoritative server enforcement.

## Solution and boundaries

Adopt one explicit pipeline: named PHP validation attributes → deterministic JSON export → public-safe schema
read → typed client validators → Formik integration. Reuse Fight Common's public `Validation` and `RulesParser`
contracts; qualify the installed versions and actual semantics rather than copying package behavior. Export
only explicitly allowlisted form names, fields, rule types/arguments and public messages. A `formName` alone
is not permission to publish every attribute. The JSON is a generated projection, never a second authored
rule source. Framework/filesystem/HTTP concerns stay in Adapter under [ADR 0001](../adr/0001-application-ownership-and-orchestration.md).

The approved `GET /api/v1/validations/{form_name}` is explicitly unauthenticated and requires neither
`VIEW_DASHBOARD` nor another permission. It returns only approved browser-safe metadata, not form values,
identity state or authorization. This is a narrow public-read exception, not a weakening of JWT-by-default
APIs or public credential-flow CSRF/Origin/rate/grant controls. [TASK-00117](../tasks/00117-TASK.md) must preserve
this exact Action exception when its authentication guard is installed.

Initially support the browser-safe rules required by real activation, login, reset-request, reset-completion
and password-change forms as those journeys are implemented. Existing journey TASKs own their real named
attributes/allowlist registration and end-to-end adoption. The foundation can precede them with an honest empty
catalog; do not invent a product POST, placeholder authentication behavior or an attribute-bearing test route
solely to populate export output. Later forms opt in through their owning scope.

Out of scope: product authentication implementation, automatic promotion of all attributes, dynamic PHP
scanning at request time, client-side authority, remote uniqueness/credential/password-breach checks, a general
form-builder/rendering engine, frontend-generated server schemas, validation-library replacements beyond the
approved Formik adoption, broad E2E, Swagger export/UI and changing domain password or security policy.

### Explicit-source coordination

[TICKET-00010's approved input contract](00010-TICKET.md#explicit-request-input-sources) makes `JsonBody` and
`QueryString` source indicators without DTO references; `Validation` remains the field/rule authority for both.
TASK-00155 owns runtime enforcement and checked request access. Export consumes actual named Validation fields,
not constructor-reflected DTO shape or OpenAPI as a second rule source. A query source or named attribute does
not opt its rules into this public catalog; the existing safe-publication allowlist remains mandatory. Preserve
source-specific wire types and explicit normalization when qualifying PHP/client parity, rather than promising
that all query strings become JSON-native values. No dependency on a populated product form is introduced.

### Public schema and generation contract

- Explicit CLI generation runs through repository Docker tooling against installed locked packages. It does
  not install/update dependencies, connect to external services or mutate production source.
- Read an explicit eligible source set. Form names must be bounded canonical identifiers; reject duplicates,
  unsafe names, ambiguous field mappings and unsupported publishable rule semantics with safe diagnostics.
- Export a versioned safe schema with stable field/rule ordering and no timestamps or environment-dependent
  values. Identify its content revision so a client can reject unsupported/stale representations and invalidate
  cached metadata on a new deployment. No generated artifact is authoritative over its PHP source.
- Publish complete generations without partially replacing the live catalog. Remove obsolete exported forms
  through generation replacement; failed generation preserves the last complete artifact but reports failure.
  Missing/corrupt required runtime artifacts fail safely, not as an empty success or request-time regeneration.
- Keep generated output outside public static assets and source/secret directories; the read Action resolves
  only allowlisted names through a bounded catalog, never concatenates arbitrary input into a filesystem path.
  Return safe JSend and documented unavailable/not-found/malformed outcomes without paths or diagnostics.
- Export only required/type/length/format/comparison constraints whose PHP/JavaScript semantics are qualified.
  Do not expose database queries, uniqueness lookups, private configuration, role/permission checks, credential
  validity, compromised-password corpus/lookup material, closures or arbitrary class/method names.
- Regex or Unicode rules need equivalent bounded semantics; do not ship PHP regex for arbitrary JavaScript
  execution. Server-only checks remain server-only. Login validates credential input shape, not new-password
  composition: existing credentials must not be rejected by activation/reset password-setting requirements.

### Unified field-error lifecycle

- Render one accessible list of one or more safe plain-text messages per field, regardless of Formik/client
  or server origin. Deduplicate without losing distinct messages or depending on incidental server ordering.
  Preserve provenance internally so a fresh client result cannot silently erase a current server rejection.
- Every actual value change, including programmatic updates, immediately invalidates that field's prior
  client/server errors. A fresh validation result for the new value may appear immediately; clearing old errors
  does not mean declaring the new value valid. Touched/submit visibility stays explicit and consistent.
- Preserve errors on unrelated unchanged fields. Invalidate and revalidate declared dependent fields when an
  input they depend on changes, including password confirmation; do not clear the entire form on every edit.
- Bind submissions and asynchronous validation to form/schema and field/dependency revision generations.
  Ignore superseded submissions, reset/unmount responses and errors for edited fields, including A → B → A
  changes. Comparing only current value equality is insufficient. Known cross-field errors track every input
  dependency; where dependency provenance is unavailable, conservatively discard the stale response after
  any relevant form revision rather than attaching possibly obsolete errors to unchanged fields.
- A pending response may still attach independent errors to unchanged fields when their dependency revision
  is known current. Never suppress an actual server success or assume client cancellation undid server writes;
  owning journeys reconcile successful mutation and authentication state through their existing contracts.
- Form reset, successful completion and terminal/unmount cleanup clear owned error/request state as appropriate
  to the journey. Do not persist submitted passwords/credentials or copy complete values into error metadata,
  diagnostic receipts, browser persistence or snapshots; revision counters provide freshness without secret copies.
- Map bounded snake_case/dotted server field paths to the form's explicit camelCase field registry, including
  declared aliases/nesting. Reject unknown/unsafe paths and unexpected payload shapes; never use unchecked paths
  to mutate arbitrary objects. Generic authentication/authorization/system failures remain form-level, not
  invented field-specific disclosures. Render only contract-approved safe messages, never raw exception prose.
- Loading/unavailable/unsupported schemas are explicit states with retry. Do not silently treat absent rules as
  successful validation or submit a form that requires an unavailable schema. Client validation is usability;
  the server always validates and authorizes independently.
- Focus the first current invalid field after a rejected submission; associate the complete error list through
  native/ARIA semantics and retain visible keyboard focus. Background validation or stale responses must not
  steal focus. Use existing production components/React-Bootstrap where their contracts fit.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Export approved form schemas | Explicit repository CLI; no business command | Read eligible named attributes and safe publication policy | N/A — generation is tooling | Complete deterministic JSON generation replaces only owned artifact output |
| Read a form schema before login | N/A — read-only API | Resolve allowlisted form schema and revision | N/A — no domain event | Safe schema returned without authentication, credentials, cookies or user lookup |
| Validate or edit a field | N/A — local input | Evaluate supported rules and current dependency revisions | Local input/validation signals only | Current feedback updates; obsolete field errors clear; no server write |
| Submit and display rejection | Owning journey's existing API operation | Decode safe field messages against submitted revisions | Owning journey events only | Client/server errors share presentation; stale results cannot overwrite current input |
| Recover unavailable metadata | N/A beyond bounded retry | Fetch the supported current schema | No domain event | Explicit loading/failure state; no false validation success or automatic mutation |

## Validation and permissions

The public catalog discloses deliberately non-sensitive rules only. No actor is required to read it; permission
and credential checks on the operations it describes remain mandatory. Public transport stays bounded and
same-origin under existing HTTP/CORS policy; no blanket cross-origin approval or credentialed cache is added.
Server field messages must remain safe even when JavaScript is absent or schema metadata is manipulated.

Respect [ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md): no credentials or form values in browser
persistence, schema caches, errors, analytics or logs. Share only safe metadata in a revision-aware memory cache;
abort/supersede fetches and clear caller state on form lifecycle changes. The shared API boundary may expose
safe validation detail through a deliberately qualified contract extension, never arbitrary server prose.

## Acceptance and evidence

- Explicit deterministic attribute export and anonymous schema read form one owned contract with documented
  version/field/rule semantics, safe failures, generation replacement and no private content exposure.
- Future JWT guard explicitly admits only the approved schema-read Action; other protected/public-credential
  routes retain their respective authentication, permission, CSRF, rate and grant protections.
- Formik is deliberately version-qualified and locked against the current React/TypeScript stack, with no forced
  peers or unrelated dependency changes. Shared validators and adapter integrate with existing controls.
- One/multiple client/server errors share accessible presentation; edits clear old errors, preserve unrelated
  current errors and refresh dependent fields. Reset, concurrent submissions, A → B → A, schema replacement,
  unmount and stale cross-field responses cannot restore obsolete errors.
- Server rejection remains authoritative; no client schema bypass enables unauthorized or invalid writes.
  Unknown server fields/messages and unavailable schemas fail safely without leaking secrets or inventing validity.
- Existing journey TASKs register only their real forms and prove PHP export/read/client mapping and real server
  rejection when those endpoints exist. Foundation acceptance does not claim those future journeys are complete.
- Test owned schema-read behavior, client rule semantics, important PHP/client contracts and observable form
  lifecycle. Validate exporters/generated artifacts directly with owning tools; no product tests of tools,
  generated-file contents or deliberately seeded tooling failures.
- Focused HTTP/OpenAPI/client/component checks, applicable independent browser QA, planning validation and
  `./bin/build` supply fresh results, counts, warnings and explicit limits.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00151](../tasks/00151-TASK.md) | Export and serve public-safe form validation schemas | done |
| [TASK-00152](../tasks/00152-TASK.md) | Integrate shared validation and field-error lifecycle with Formik | in-progress |
<!-- /planning:children -->

## Decisions and progress

The maintainer approved public allowlisted browser-safe validation metadata, shared Formik integration,
multiple field errors from client/server, reset-on-change and stale-response protection on 2026-09-30.
The two-TASK decomposition was explicitly approved: export/serve the safe catalog, then integrate typed
validators and the unified Formik lifecycle. This is an approved bounded addition under EPIC-00003, not a new
EPIC or a reopening of the closed foundation Wayfinder map; unrelated map phases and active work are unchanged.

Fight CMS and Omphalos were inspected as read-only examples: `ValidationsExportCommand`, `ReadValidationsAction`,
`validationService` and `buildFormikValidate`. Their `/api/validations/{formName}` and `ROLE_USER` policy are
reference implementations, not imported authority. Agent OS uses its approved API-v1 public allowlist and
existing server-security/error conventions. Implementation/package qualification is still pending.
