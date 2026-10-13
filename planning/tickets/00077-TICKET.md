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

Use `ai-template-proposals` as the first real Feature, approved under EPIC-00016. Gate new proposal requests,
including deliberate regeneration, at the real HTTP Action and every supported alternative admission path.
OFF admits no new proposal/provider request; PREVIEW requires the initiating authorized template administrator's
bound testing Permission ID; ON permits otherwise-authorized administrators. This never grants template-management,
Agent/tool/provider, disclosure or publication authority. The assisting Agent does not acquire protected human
management Permissions to satisfy the gate, and shared provider tools are not globally flagged for unrelated work.

Keep human-only editing and publication available at every flag state. Status, result inspection, cancellation
and qualified recovery of already-admitted attempts retain their ordinary authorization and safe lifecycle; OFF
is not a retroactive cancellation or a reason to strand paid work. Recovery cannot become a new/regenerated
proposal or silently duplicate an uncertain provider call. Consume TICKET-00071's registered server boundaries
and TICKET-00072's availability plus `/me` controls at the actual Generate/Regenerate interaction.

Declare the name in real proposal code and use TASK-00290/00291 discovery/provisioning to create it OFF against
an explicitly selected existing testing Permission; qualify TASK-00292 preparation before rollout. No seed/demo
Feature, separate manual-create screen/API or new test product is needed. TICKET-00073 owns authorized status/
binding management. This TICKET owns the actual integrated rollout evidence and affected alternative-path coverage,
not a new proof-only TASK or reverse dependency from reusable infrastructure onto completed AI authoring.

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
- `ai-template-proposals` OFF and PREVIEW-ineligible users cannot initiate provider work through HTTP or an
  alternative admission path. Eligible PREVIEW and ordinary-authorized ON users complete real provider proposal,
  prompt/Permission editing and explicit publication; the flag never bypasses management or provider authority.
- Manual editing/publication and authorized status/result/cancellation/recovery remain usable when OFF. Test
  switching OFF with an admitted request and distinguish safe completion from a fresh paid proposal.
- Owned proposal/provider/browser tests, direct configured-provider qualification and `./bin/build` pass.
  Reused historical provider receipts and synthetic model output are not fresh assisted-authoring acceptance.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00302](../tasks/00302-TASK.md) | Generate and review real AI template proposals behind controlled rollout | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00017's five-TICKET split. Depends on
[TICKET-00076](00076-TICKET.md)'s review/publication boundary and the actually qualified provider/Harness
capabilities owned by EPIC-00006; missing capabilities remain explicit dependencies, not a reason to duplicate
that platform. Apply [ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md). No provider call, credential access or AI proposal
was performed by decomposition.

The maintainer subsequently approved `ai-template-proposals` as the first real Feature instead of creating a
flag demonstration. TASK-00290–00296 remain upstream suppliers as applicable; this TICKET's eventual TASKs own
real product integration and provider evidence. A testing Permission/audience must be deliberately selected and
qualified, not invented or automatically granted by planning. The separate manual-create browser/API proposal
under TICKET-00073 is deferred; its revised single-TASK decomposition was subsequently approved below. That
amendment created no TASK, changed no execution priority and did not insert AI authoring before the terminal-first
Awaiting review milestone.

Amendment verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed;
396 records, 309 active, no new IDs. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations. Sass deprecations, React-Bootstrap `use client` and >500 kB chunk warnings remain.
Evidence: `.runs/build/20261013T021935Z-65872`; log: `.runs/build/ai-template-proposals-scope.log`.
These are fresh planning/existing-repository checks, not Feature/provider/browser runtime evidence. No Feature,
Permission, provider call or deployment was enabled; independent review and publication remain unperformed.

TICKET-00073 now maps [TASK-00297](../tasks/00297-TASK.md)'s complete management path, including approved
`MANAGE_FEATURES` authority, protected detail/choices, status/rebinding, confirmation and invalidation. Its trusted
readiness and active-repair composition remain needs-info. This TICKET consumes that outcome and retains the real
AI admission/provider/editor integration, deliberate testing audience and all applicable alternative-path evidence.
Neither the provider journey nor management reverse-blocks reusable suppliers; manual authoring and safe handling
of admitted requests remain independent of this Feature's availability. No TASK for this TICKET was allocated
at that management-decomposition stage; the subsequent approved split is recorded below.

TICKET-00076 now maps [TASK-00300](../tasks/00300-TASK.md)'s actual human editor/review/publication boundary.
Consume that specific outcome, not completed TASK-00301 ancestor review/rollback or the whole parent TICKET.
TASK-00300 retains its candidate-validation/reference-fencing and inherited resource/Agent holds. AI proposals use
its same exact human draft/review and original-publication outcome path; they never create a second publisher,
self-approve or gate manual editing on the Feature. Actual provider/Harness admission/recovery remains here.

### Approved TASK decomposition

The maintainer approved one unranked feature: [TASK-00302](../tasks/00302-TASK.md), **Generate and review real AI
template proposals behind controlled rollout**. It consumes TASK-00300's actual human editor/publication,
TASK-00297's Feature management/catalog chain, TASK-00294's alternative-admission enforcement, TASK-00250's
immutable Agent-session integration and TASK-00241's qualified credential/current-use readiness.

Status is `needs-info`: exact EPIC-00006 suppliers for protected durable proposals, delegated generative execution,
provider disclosure/budgets and uncertain-call recovery remain unmapped/unqualified. Inherited publication,
session/host/origin and rollout holds remain. The actual provider/model, limits and existing testing Permission/
audience require selection and qualification, not invented identifiers or grants. Inspected application/configuration/
Harness sources did not establish the required runtime; Jev Decisions readiness does not substitute for it.

One actual provider suggestion → human prompt/Permission editing → explicit publication journey owns recovery,
rollout and evidence. New requests/regeneration are flagged; manual publication and authorized existing-attempt
status/result/cancellation/safe recovery are not. OFF is neither cancellation nor permission to duplicate uncertain
paid work. No second publisher, conversation store, provider endpoint, infrastructure-only or proof-only TASK is added.
TASK-00301, completed Explorer/browser Planning and Feature retirement are not prerequisites. Suppliers and the
terminal-first milestone are not reverse-blocked by this later product integration.

With subsequent splits through TICKET-00082, all five EPIC-00017 TICKETs and all 24 map TICKETs are
decomposed, not implemented. Older Harness/managed review planning remains incomplete. Existing priorities and active work
remain unchanged. Next: `/skill:next` for read-only Board routing. No provider request, testing grant, Feature rollout,
template publication, Agent or session effect occurred during planning.

Planning verification: `./bin/planning-check --write`, `./bin/planning-check` and `git diff --check` passed with
402 records and 315 active. `./bin/build` passed: 459 tests, 3499 assertions, 78 captures, zero violations.
Evidence: `.runs/build/20261013T161019Z-76454`; log: `.runs/build/ticket-00077-to-tasks.log`.
Sass deprecations, React-Bootstrap `use client` and >500 kB bundle warnings remain. These are fresh planning/
existing-repository checks, not protected proposal/provider, Feature rollout or AI authoring qualification.
Independent review and publication remain unperformed; no live enrollment, request or grant occurred.
