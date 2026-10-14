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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md),
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume early TICKET-00086–00090 resource/origin/snapshot/provider/session and existing actual Agent primitives,
plus [TICKET-00105](00105-TICKET.md)'s read contract. Integrate [TICKET-00106](00106-TICKET.md)'s form without
reverse-blocking its direct-edit path or generic Harness suppliers on completed assistance. No new Skill owner,
Agent role or finished browser Planning prerequisite is introduced. Existing readiness holds remain. No TASK,
provider request, Agent session, credential inspection, instruction content or filesystem effect is created here.
