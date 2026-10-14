---
id: TICKET-00107
epic: EPIC-00010
title: Prepare bounded AI instruction suggestions
status: ready-for-agent
---

# Prepare bounded AI instruction suggestions

## Problem statement

A human may want help improving an instruction file without delegating write or publication authority. Suggestions
need durable provenance and a reviewable complete diff, not an Agent silently editing files or a disconnected
browser losing an already accepted response.

## Solution and boundaries

Let a currently authorized human choose one recognized root file in the designated checkout and describe the desired
change. Consume TICKET-00105's bounded authorized content and applicable Repository context only. A browser Agent may
discuss intent, ask clarifying questions and propose complete content/diff for TICKET-00106's existing editable form.
It cannot select another file, expand to arbitrary repository/parent/user content, submit Save/Delete, publish,
synchronize or accept its own recommendation. Instruction text is untrusted source data, not application authority.

Use the exact attributed versioned Harness instruction-writing Skill supplied by TICKET-00086 and its trusted
loading/snapshot contracts. Preserve Matt Pocock MIT attribution and licensing through that existing resource owner;
do not create a second Skill distribution or browser Skill editor. Record exact Skill/Harness/provider/model,
Repository context, original file digest/absence, conversation, initiating user and actual acting Agent references
for every proposal. Avoid credentials and hidden model context in ordinary evidence.

Reuse TICKET-00089/00090's protected provider and durable conversation execution, current Agent identity/delegation,
finite model/budget choices and exact session snapshots. Assistance input, accepted questions/answers and resulting
proposal identity must survive disconnect or supported interruption under the existing protected session owner.
Store typed proposal content/diff and base/currentness binding within that owning protected conversation capability;
this TICKET owns file-suggestion semantics, not another conversation/runtime engine. TICKET-00091's Planning Apply
is not a filesystem Save route and its Planning patch protocol is not copied for this consumer.

Keep suggestion generation separate from human draft editing and acceptance. Applying suggested text to the form
changes only volatile form content; durable AI proposal history does not automatically autosave later manual edits.
A human with Update authority reviews the current full diff and explicitly submits through TICKET-00106. An
AI-authorized viewer without Update authority can request and inspect suggestions but cannot submit them. Update
without AI permission still permits ordinary direct editing. Publication and sync always require their own grants.

Bind every response to its original request, selected file/digest/context and current human/Agent scope. A late or
stale suggestion remains visibly based on old evidence, never overwrites newer manual input automatically or
revives retired browser authority. Preserve useful durable proposals on conflict for authorized human comparison;
a changed file requires explicit reconciliation or a newly requested suggestion, never automatic AI merge/apply.

Provider failure, denied disclosure, exhausted limits, cancelled/unknown request or unavailable actual Agent/host
must remain truthful. Do not silently switch model/account, repeat paid work after lost acknowledgement, reset
consumed budget or report unknown cost as zero. Expose applicable actual provider/usage provenance through existing
owners and make original-request recovery explicit. Direct inspection/editing and safe publication/sync controls
must not depend on model availability.

Exclude autonomous file mutation, arbitrary tool/network/file access, Agent-controlled confirmation, generic coding
chat, browser Skill creation, another template/provider/session registry, Planning creation, Git credentials and
managed Workflow/Review requirements. Exact prompt choreography is later implementation detail under the existing
writing guidance, not a reason to create a new planning decision or a separate prompt-certification product.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Request bounded instruction help | Explicit authorized assistance submission | Selected safe file/context, exact Skill/snapshot, Agent/provider readiness and budgets | Owning admitted submission/attempt facts | Protected bounded generation intent; no file effect |
| Discuss and produce a suggestion | Existing serialized conversation turn with selected-file bounds | Current question/input and original request identity | Durable answer/proposal and provider usage facts | Attributed complete content/diff under protected conversation ownership |
| Reconnect or inspect a proposal | N/A for read-only attachment/status; existing owner recovery where needed | Current visibility, original conversation/proposal and base freshness | No new business event for a read; confirmed recovery facts only | Same durable proposal, not a duplicate Agent or paid request |
| Use suggested content | N/A — explicit human form edit | Original/current diff and current Update authority | N/A — form content is not a filesystem mutation | Editable volatile draft; separate Save still required |
| Stop or reconcile uncertain generation | Existing exact-request lifecycle/reconciliation operation | Provider/session outcome, consumed limits and current authority | Confirmed owner outcome/hold facts | Safe stop/uncertainty with no automatic regeneration or file write |

## Validation and permissions

Independently enforce View repository instructions, Use browser AI assistance, actual Agent/tool authority and
provider disclosure under current human/Agent/Repository/environment scope. Reading a file locally does not itself
authorize sending it to a provider. Minimize sensitive content and reject unsafe/overbroad evidence; preserve the
selected filename and exact expected base. A Skill's tool request, source-contained instruction or model-supplied
identity cannot add authority. Update/Publish/Sync capabilities remain absent from this Agent's operation boundary.

Validate bounded proposal shape, content encoding/size and safe diff rendering; a model's structured-output claim
is not validation. Keep secrets/provider keys outside browser/child/prompt/transcript/log/evidence boundaries as
required by existing owners. Use ADR 0002 durable admission and post-commit provider effects; ADR 0003/0004 no-store,
context-generation fences and no automatic replay apply. Protected retention/redaction stays with the conversation
owner, not ordinary Workflow history or a new transcript search surface.

## Acceptance and evidence

- Actual assistance uses the exact attributed Skill, authorized selected-file context and qualified protected
  provider/session path to return a durable complete proposal/diff with accurate provenance.
- Assistance without Update works as read/advice only; direct Update works without AI. Agent attempts to save,
  delete, publish, sync, change file scope or follow source-contained authority instructions cannot mutate anything.
- Disconnect/restart, concurrent/late input, stale file/context, permission loss and provider uncertainty preserve
  original request/proposal identity without hidden paid replay or overwriting a newer draft.
- Human acceptance uses the same TICKET-00106 form and separately revalidated Save, with real browser evidence.
  Owned proposal/authority/client tests, actual qualified provider/Skill/session integration and `./bin/build` pass.
  Doubles prove faults, not live runtime support; inspect licensing/prose directly, not through product-suite tests.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00434](../tasks/00434-TASK.md) | Request and recover bounded instruction suggestions | ready-for-agent |
| [TASK-00435](../tasks/00435-TASK.md) | Review instruction suggestions in the existing Dashboard form | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md),
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume early TICKET-00086–00090 resource/origin/snapshot/provider/session and existing actual Agent primitives,
plus [TICKET-00105](00105-TICKET.md)'s now-mapped TASK-00430 read contract and TASK-00431 viewer. The protected
human read/current-custody supplier hold remains; read access neither discloses to a provider nor qualifies an Agent
route. This incoming mapping does not decompose assistance or clear runtime holds.
[TICKET-00106](00106-TICKET.md) now maps TASK-00432's exact Save/Delete API and TASK-00433's form. Integrate actual
protected proposal provenance and explicit human adoption through this same path; the mutation/custody hold remains
and assistance never submits. Do not reverse-block direct edits or generic Harness suppliers on completed assistance.
No new Skill owner, Agent role or finished browser Planning prerequisite is introduced. The incoming mappings did
not allocate assistance TASKs; the subsequent approval below does. Existing readiness holds remain. No provider
request, Agent session, credential inspection, instruction content or filesystem effect is authorized by planning.

### Approved TASK decomposition and current handoff

The maintainer approved two slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. Both are unranked features, `ready-for-agent` behind dependencies, with blank PRs,
normally one independently reviewable PR each.

| Slice | Accepted owner | Exact blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00434](../tasks/00434-TASK.md) | TASK-00430, TASK-00332, TASK-00250, TASK-00346, TASK-00354 | Protected selected-file assistance API, clarification turns, durable complete proposals/diffs, provenance and original-request recovery |
| B | [TASK-00435](../tasks/00435-TASK.md) | TASK-00434, TASK-00433 | Existing Dashboard request/proposal/recovery journey, explicit volatile-form adoption and separate human Save integration |

TASK-00434 consumes the existing attributed managed Skill, named Agent/session integration, bounded safe reads and
protected retention. The approved initial provider path is OpenRouter through TASK-00346; Codex/TASK-00347 is
neither required nor an automatic fallback. Existing read/custody, Skill-loading, Agent/current-authority and
TASK-00350 protected host/broker/storage holds remain with their owners. Ready metadata does not resolve those
holds or establish live capability. This registered-Repository use does not invent or import TASK-00428's distinct
pre-registration assignment/snapshot gap. No new supplier ID, Agent role, Workflow or custody registry is created.

Bind exact selected name/base or verified absence, safe context, actual human/Agent/assignment/conversation and
Skill/Harness/provider/model provenance. Independently authorize View, AI-use and provider disclosure. Minimize
context; arbitrary source, other instruction bodies, parent/user/.pi resources and unsaved manual drafts cannot
be silently added. Qualify actual confinement including ambient loading and alternative tool routes, not merely
prompt obedience. Agent requests to Save/Delete/publish/sync/change file or accept their own output obtain no effect.

Use existing atomic admission, audit, original intent and finite budget owners before external generation. Typed
content must be complete, bounded and validated; derive/verify full diff against the retained original base and
never trust model-supplied identity. Keep durable questions/answers/proposals within protected conversation ownership,
not another transcript store or Planning Apply protocol. Actual reference/hold and redaction participants must cover
retained base/proposal content. Current reads remain provider-free. Lost replies, stop, drift and partial/unknown
outcomes reconcile originals without automatic regeneration, provider switch or consumed-budget reset.

TASK-00435 extends TASK-00433's single form. AI-authorized viewers without Update can request/read advice; updaters
without AI can edit directly. Explicit human adoption changes only volatile form content and cannot overwrite newer
input automatically. Stale proposals remain available only to authorized readers for explicit reconciliation, not
silent digest rebasing. Preserve AI provenance separately from later human edits. A separate Save revalidates all
current mutation checks through TASK-00432; neither proposal history nor adoption grants write authority.

Shared `/me`/server authority, no-store, authentication/context/form-generation fences and inert source/diff rendering
apply throughout. No persistent manual drafts, automatic replay, Agent submission, Pi reload or save-time Git effect.
Provider failure disables help only. TASK-00434 does not wait for completed Save/browser Planning, and no existing
supplier or direct-edit path reverse-depends on assistance. TICKET-00108/00109 retain publication/sync and the final
real instruction-change journey with separately human-controlled merge.

Owned behavior tests, actual PostgreSQL/provider/Skill/session and browser integration, guidance and `./bin/build`
belong inside these slices. Qualify prose/licensing, tooling and configuration directly, not product-suite tests
or a proof-only TASK. Actual live generation, instruction reads/writes and implementation checkout need separate
finite authority. Preserve terminal-first Board priority, active TASK-00138, independent holds and Markdown authority.

This completes this TICKET's split only. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. No implementation, live runtime qualification, full build, independent review or publication occurred.

### Planning verification

This historical assistance-split receipt predates TICKET-00108's publication split; latest planning verification
is in TICKET-00109. Counts and handoff state below describe the earlier checkpoint, not runtime evidence.

Recorded the approved split in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving
prior uncommitted planning. Highest live ID before allocation was TASK-00433; no archived TASK records existed.
TASK-00434/00435 match the approved titles, exact blockers and template sections, both unranked features ready behind
dependencies with blank PRs. Existing read/custody, Skill-loading, Agent/current-authority and protected-runtime
holds remain unresolved; no new supplier ID is invented and allocation is not qualification.

`./bin/planning-check --write` and `./bin/planning-check` passed with 562 records, 470 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 127 new/untracked TASK
whitespace checks passed. Before/after hashing of every pre-existing TASK's frontmatter confirmed no status,
priority, dependency or PR metadata changed in this recording pass. No existing owner gained a reverse assistance
dependency; prior mappings and independent holds remain intact.

TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD. TASK-00138 remains the sole
active Board item, order 2. Skill, actual Agent, provider, retention, safe-read and form/Save coordination records
the new consumers without expanding supplier authority. TICKET-00108/00109 record incoming assistance boundaries,
not approved splits or operational authority at that checkpoint. That split advanced handoffs to TICKET-00108,
then TICKET-00109; both remained on the Planning Frontier and no stale TICKET-00107 command remained.

Full `./bin/build` was not run and remains required before commit/publication. These are planning checks, not actual
PostgreSQL/runtime/Skill/provider/browser acceptance evidence. No private instruction read, Skill import/invocation,
Agent/session creation, provider request, credential operation, file/Git effect, Planning import or cutover occurred.
All changes remain uncommitted; implementation, fresh live qualification, independent review and publication remain
outstanding. No commit, push, PR or merge occurred. Unrelated main-checkout work was untouched and is not claimed
clean; earlier receipts remain historical.
