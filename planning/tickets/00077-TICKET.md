---
id: TICKET-00077
epic: EPIC-00017
title: Generate editable AI template proposals
status: ready-for-agent
---

# Generate editable AI template proposals

## Problem statement

An administrator needs help turning a functional role/persona into a useful system prompt and desired
Permissions. Model output is untrusted advice: it cannot publish a template, grant authority or quietly replace
human edits after a delayed response or browser disconnect.

## Solution and boundaries

Add bounded AI assistance to [TICKET-00076](00076-TICKET.md)'s template editor using
[EPIC-00006](../epics/00006-EPIC.md)'s qualified protected provider/Harness capabilities and human proposal
patterns. The current authorized human selects the template/draft, intended role, scope and allowed provider/model.
The acting Agent receives only authorized, disclosure-approved context and relevant current eligible Permission
choices. Return an editable proposed system prompt and complete requested Permission set, with uncertainties,
provenance and applicable constraints made explicit. Preserve stable Permission identities and distinguish an
unsupported request from an available grant; neither the model's role label nor its explanation proves eligibility.

Let the human edit, discard or request a new bounded suggestion and explicitly review **both** prompt and
Permissions before invoking the same publication path. A proposal is not a published revision or trusted
instruction. Bind responses to the initiating authority, scope, draft/base revision and proposal attempt so late
responses cannot overwrite newer edits, revive cancelled work or approve themselves. Preserve truthful pending,
completed, failed, stale and uncertain states; losing a browser connection never triggers publication. Any
resumption uses authorized protected proposal state under the existing Harness ownership, not a new conversation
platform or persistent browser draft store.

Keep human-only editing available and label AI unavailability honestly. Actual provider-assisted editing must be
qualified before claiming this TICKET delivered; a fixture response or manual fallback is insufficient. Do not
silently switch providers/models, broaden source disclosure, grant the assisting Agent management Permissions,
or introduce a separate untrusted model endpoint. General repository instruction/Skill editing and autonomous
publication are excluded.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Request a suggestion | Authorized bounded Harness proposal request | Current template/draft revision, allowed context/resources and eligible Permission choices | Attributable proposal-request/result facts where persisted, not template publication facts | Protected provider call within approved limits; editable untrusted candidate |
| Review and edit output | Human draft edits; explicit discard or new proposal request as applicable | Proposed prompt, complete requested set, provenance, uncertainty and current draft state | N/A for inspection; draft/proposal lifecycle observations are not grants | Human controls candidate content; stale output cannot overwrite newer work |
| Publish reviewed proposal | Existing TICKET-00076 publication operation after explicit human review | Fresh actor/scope/reference and revision checks | Only the owning committed template publication fact | Published revision for future use, with no Agent creation or Permission assignment |

## Validation and permissions

Require current protected template-management authority for the human and independently valid delegated
Agent/tool/provider authority on every request and result read. The assisting Agent has only eligible direct
Permissions; it cannot hold the `SUPER_ADMIN_ONLY` publication right. Reading local content does not authorize
provider disclosure. Restrict context to approved Repository scope, minimize private inputs and enforce protected
credential handling, bounded input/output, time, concurrency and spend through the existing provider owner.

Validate structured candidate content and references; malformed, partial or hallucinated Permission requests
remain unresolved rather than silently accepted. Recheck authorization, base revisions and current constraints
at publication regardless of earlier proposal validation. Provider timeout, cancellation, disconnect and unknown
outcomes must not cause silent duplicate paid requests, automatic publication or false success. External calls
stay outside business transactions; required recoverable proposal state uses supported durable ownership rather
than an in-memory success event. Keep raw provider/Agent credentials, private source, unauthorized prompt/Permission
details and arbitrary provider errors out of browser responses, model context, logs and ordinary history.

## Acceptance and evidence

- A real qualified provider produces a persona-based prompt and desired Permission proposal; the administrator
  can inspect/edit both and explicitly publish through the ordinary editor. Record actual provider/model and
  scoped consumer evidence; unavailable metadata is not invented.
- Prompt-injection attempts, fabricated/protected Permissions, cross-Repository content and direct Agent
  publication attempts cannot expand authority or disclosure. Human approval still undergoes current validation.
- Delayed responses, concurrent human edits, revocation, cancellation, disconnect and provider failure preserve
  draft ownership and safe recovery without hidden publication, credential exposure or silent provider fallback.
- Owned proposal/provider/browser tests, direct configured-provider qualification and `./bin/build` pass.
  Reused historical provider receipts and synthetic model output are not fresh assisted-authoring acceptance.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00017's five-TICKET split. Depends on
[TICKET-00076](00076-TICKET.md)'s review/publication boundary and the actually qualified provider/Harness
capabilities owned by EPIC-00006; missing capabilities remain explicit dependencies, not a reason to duplicate
that platform. Apply [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md). No provider call, credential access or AI proposal
was performed by decomposition.
