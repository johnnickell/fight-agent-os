---
id: TICKET-00106
epic: EPIC-00010
title: Save and delete instruction files safely
status: ready-for-agent
---

# Save and delete instruction files safely

## Problem statement

A reviewed instruction draft must not overwrite intervening work, target another checkout or silently publish Git
changes. Humans need one safe local-save path for direct and assisted content, with clear conflict and deletion
consequences and preserved session history.

## Solution and boundaries

Extend TICKET-00105's focused one-file view with an editable Markdown form and complete changed-file diff. Human-
authored and TICKET-00107-assisted content use the same Save changes operation. Unsaved manual edits remain volatile;
warn before leaving a dirty form, but do not add autosave, simultaneous co-editing or persisted browser drafts.
A durable AI proposal is distinct from unsubmitted manual form state and grants no write permission.

Submit only the exact allowlisted relative root filename, proposed complete content, expected current digest or
expected absence, and necessary current checkout/context evidence. Creating a missing recognized file uses expected
absence, not an assumed empty digest. Preview the full diff and effective-instruction consequence. Revalidate live
Update repository instructions authority, actual designated checkout identity, containment, regular-file safety,
branch/ownership, encoding/size, expected content state and conflicting operations immediately before one atomic
write. The deterministic Runner applies exactly that admitted effect and rereads actual result digest/Git state
before success is recorded. It cannot choose content, interpret prose as authority or pick another worktree.

Provide distinct Delete file with complete deletion diff, tracked/ignored state and the remaining effective-file
consequence. Require explicit confirmation and expected current digest; delete only the selected recognized file.
A stale preview, disappeared/appeared/changed/unsafe file or changed checkout/context is a conflict, never permission
for last-write-wins, automatic merge or deleting a replacement object.

Preserve the user's current volatile draft while the authorized page remains active and retain the durable AI
proposal through its existing owner. On a safe conflict return currently authorized content/diff beside the rejected
draft for human reconciliation or a separately requested new suggestion. Do not expose current content after read
permission is lost, preserve private form state across authentication teardown, or let the Agent resolve and apply
a conflict. A changed digest is not silently rebased under the original submission.

Retain attributed mutation intent/outcome with actual initiating user, acting Agent for proposal provenance where
applicable, exact selected file role/path, base/result digest, Repository/checkout/context/Harness/Skill references,
known Git result and stale consequence. Use durable admission and verified outcome/reconciliation around filesystem
effects rather than a database transaction pretending to roll back a write. Unknown acknowledgement/process state
requires inspection of the original effect before retry; a read/refresh must not repeat a write. Preserve uncertainty
when observed bytes alone cannot establish the original operation's outcome.

Use existing TICKET-00025/00088/00090 context/session drift contracts. Only changes to the effective Pi instruction
set stale relevant snapshots/sessions; an unloaded-file change updates evidence without unnecessary session
invalidation unless composition changes. Preserve immutable historical snapshots and proposal/session lineage.
Never silently reload Pi, update repository configuration, grant authority or continue an Agent session.

Create/update/delete remains local filesystem state. Do not stage, commit, push, open/update a PR or merge on Save.
Show dirty state and deliberate next actions. Publication and synchronization remain TICKET-00108/00109 with
separate authority and shared checkout/resource coordination. Writing uses the registered default-branch/designated-
checkout operating contract; refuse conflicting or unexpected ownership/state rather than silently switch branches.
Exclude arbitrary files, recursive browsing, `.pi` editing, destructive conflict repair and a generic text editor.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Draft and preview a change | N/A — volatile form editing/preview | Safe original content/digest and effective-file consequence | N/A — a draft is not a filesystem fact | Complete editable draft/diff, no file mutation |
| Save exact content | Expected-digest or expected-absence Save changes | Fresh target/context/authority, original operation and actual result | Committed verified file-change facts and owning drift consequences | One authorized atomic local write, no Git publication |
| Delete one recognized file | Explicit confirmed expected-digest Delete file | Complete deletion/remaining-effective-state preview and current safety | Verified deletion fact and owning drift consequences | Exact local deletion only |
| Resolve a stale submission | N/A for inspection; a revised human submission is a new explicit command | Currently authorized safe content/diff, original attempt and current ownership | No success event for a rejected write | Preserved permitted draft/proposal and manual decision, not auto-merge |
| Reconcile an interrupted effect | Original-operation reconciliation, not blind replay | Exact intent/result, live filesystem/Git state and current authority | Confirmed outcome or explicit uncertainty/hold | No duplicate effect or inferred rollback |

## Validation and permissions

Enforce separate View and Update capabilities against current trusted identity and scope; assistance or publication
permission is not Update authority. Apply the exact TICKET-00105 allowlist and bounded safe text/path rules. Coordinate
writers, publication and sync under qualified shared ownership/currentness fences; a read-time digest check alone
is not sufficient protection against a replacement during mutation. Recheck branch/context and expected state at
the actual effect boundary; qualification must establish the selected atomic-write/delete guarantees and limits.

Use ADR 0002 state/audit/intent transactions with external filesystem work outside them. Keep secret-bearing content
out of ordinary events/logs; retained proposals/content use their protected owners. ADR 0003/0004 guard loaders,
actions and late responses, invalidate private state on authority loss and forbid automatic mutation replay after
refresh. Render complete diffs safely with accessible confirmation/focus and explicit failed/partial/unknown states.

## Acceptance and evidence

- Actual direct and assisted forms call the same safe Save path; expected absence protects creation and expected
  digest protects replacement/deletion. Full diffs and deletion consequences precede human actions.
- Races with external changes, changed checkout, revocation, unsafe files, publication and synchronization cannot
  overwrite unrelated work or report unverified success. Interrupted effects reconcile rather than replay blindly.
- Effective-set changes stale existing contexts/sessions without rewriting history or reloading them; unloaded-file
  edits do not falsely invalidate unchanged effective composition. Saved state remains uncommitted and unpublished.
- Real browser keyboard/focus/dirty-leave/conflict behavior preserves the correct draft/proposal and authority
  boundaries. Owned application/PostgreSQL/client tests, actual Runner/filesystem/Git evidence and `./bin/build`
  pass. No tests of Markdown prose, filesystem libraries, configuration or generated outputs as tooling artifacts.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00432](../tasks/00432-TASK.md) | Save, delete and reconcile exact instruction-file changes | needs-info |
| [TASK-00433](../tasks/00433-TASK.md) | Edit and recover instruction changes in the Dashboard | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md),
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

[TICKET-00105](00105-TICKET.md) now maps TASK-00430's bounded inspection API and TASK-00431's existing-Dashboard
viewer. TASK-00430 retains its exact protected-read/current-custody supplier hold; a content/digest observation grants
no later write. Consume those owners and existing identity/context/Runner primitives without reverse-blocking
inspection on completed editing, publication or synchronization. That incoming mapping alone did not decompose this
TICKET, qualify atomic writes or resolve its shared ownership/currentness requirements. Define shared
checkout ownership and effective-change/result contracts before final publication/sync integration, not mutually
blocked whole-TICKET journeys. [TICKET-00107](00107-TICKET.md) supplies optional proposals; direct editing never
waits for provider assistance. [TICKET-00108](00108-TICKET.md)/[TICKET-00109](00109-TICKET.md) retain publication
and synchronization. The requirement approval created no TASK or operational authority; the subsequent approval
below allocates TASK records only, not instruction mutation, Agent continuation or source-host authority.

### Approved TASK decomposition and current handoff

The maintainer approved two slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. Both are unranked features with blank PRs, normally one independently reviewable PR.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00432](../tasks/00432-TASK.md) | TASK-00430, TASK-00341 | Protected exact Save/Delete API, original durable intent/outcome/reconciliation and existing effective-context drift integration |
| B | [TASK-00433](../tasks/00433-TASK.md) | TASK-00432, TASK-00431 | Existing Dashboard form, complete diffs/consequences, confirmation, dirty-leave, conflict and original-operation recovery |

TASK-00432 starts `needs-info`: map the precise human-authorized mutation transport and shared checkout custody/
currentness participants under existing TICKET-00032/00093 execution/Runner owners, with supported atomic physical
mutation guarantees. TASK-00430's protected-read hold remains separate; successful reads would not qualify writes.
Enrollment and TASK-00369's Workflow resource contracts alone do not supply this route. No unknown supplier ID,
Workflow, TASK claim, Agent, alternate checkout or second custody registry is invented. TASK-00433 is ready behind
unfinished/held dependencies, not runtime-qualified. Existing TASK metadata and terminal priority remain unchanged.

TASK-00432 consumes TASK-00430's exact allowlist/content/discovery/context evidence and TASK-00341's currentness
contract, including TASK-00084 transitively. Save creates with expected absence or replaces with expected digest;
Delete requires its distinct full-diff/effective-consequence confirmation. Revalidate current View/Update, registered
default-branch identity, scope, containment, actual ownership and complete expected state at the effect boundary.
A database lock or digest-check-then-rename is not proof against external file/parent replacement. Qualify supported
conditional mutation and stale-worker guarantees; unsupported enforcement remains blocked, not silently weakened.
Local Save preserves unrelated work without importing publication's clean-tree-only prerequisite.

Commit owning state/audit/intent atomically before external filesystem work; then record actual reread outcomes and
safe attributed provenance. Recover the original effect, never infer success from matching bytes, absence or exit
status. Unknown outcome/custody stays held and refresh never repeats a write. Keep necessary delivery payloads
protected, not ordinary logs/events or a second proposal/transcript store. Existing Repository/Harness guards own
effective-set consequences; unloaded-file changes do not falsely stale unchanged composition. History stays
immutable and no Pi reload, configuration update or Agent continuation follows.

TASK-00433 extends TASK-00431's viewer rather than a new editor/workspace. One volatile form and human Save path
serve native and later assisted content. Safe conflicts preserve still-authorized drafts beside current content;
late results never erase newer edits or revive retired scope. Authentication/View loss clears private state and
cannot be bypassed by dirty-leave warnings. Full diff, distinct Delete confirmation, current `/me`/server authority,
no-store, original status/recovery and fresh accessible responsive light/dark evidence are required.

TICKET-00107 now maps TASK-00434/00435's durable suggestions/provenance and integration into this same form/Save
path without gating direct editing or granting Agent submission. Actual runtime and inherited supplier holds remain. TICKET-00108/00109 consume early ownership/result/currentness contracts;
completed publication/sync must not reverse-block writes, reads or terminal delivery. No staging, commit, push,
fetch, branch switching, automatic merge/rollback or arbitrary/.pi file editing. TICKET-00109 retains the integrated
real instruction-change journey and separately authorized human merge.

Owned behavior tests, actual PostgreSQL/Runner/filesystem/Git or browser evidence, guidance and `./bin/build` belong
inside these slices, not a proof-only TASK. Tools, configuration, Markdown and generated tooling artifacts are
qualified directly rather than product-tested. Implementation checkout and finite fixture/mutation authority remain
separate; planning performs no live instruction read/write, runtime/provider operation or Planning cutover.

This completes this TICKET's split only. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138, terminal-first Board priority, independent holds and Markdown authority.
Full build, implementation, independent review and publication remain outstanding.

### Planning verification

This historical Save/Delete-split receipt predates TICKET-00107's assistance split; latest planning verification
is in TICKET-00109. Counts and handoff state below describe the earlier checkpoint, not runtime evidence.

Recorded the approved split in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving
prior uncommitted planning. Highest live ID before allocation was TASK-00431; no archived TASK records existed.
TASK-00432/00433 match approved exact known blockers, statuses and template sections, both unranked features with
blank PRs. The protected mutation/shared-custody/physical-guarantee supplier remains explicitly unallocated, not
guessed or claimed cycle-checked; TASK-00432 stays `needs-info`. TASK-00430's read hold remains distinct and
TASK-00433's ready metadata is not runtime qualification.

`./bin/planning-check --write` and `./bin/planning-check` passed with 560 records, 468 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 125 new/untracked TASK
whitespace checks passed. Before/after hashing of every pre-existing TASK's frontmatter confirmed no status,
priority, dependency or PR metadata changed in this recording pass. No existing owner gained a reverse mutation
dependency; previous mappings and independent runtime/authority holds remain preserved.

TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD. TASK-00138 remains the sole
active Board item, order 2. Inspection/viewer, Repository/Harness-currentness and Runner resource coordination
recorded the new consumers without expanding supplier authority. TICKET-00107–00109 recorded incoming form/result/
ownership contracts, not approved decompositions or qualification at that checkpoint. That split advanced handoffs
to TICKET-00107, then TICKET-00108–00109; all three remained on the Planning Frontier and no stale TICKET-00106
command remained.

Full `./bin/build` was not run and remains required before commit/publication. These are planning checks, not actual
PostgreSQL mutation-participant, protected Runner/filesystem/Git, Pi/session or browser acceptance evidence. No
live instruction read/write, runtime/credential/provider action, Git effect, Agent continuation, Planning import
or cutover occurred. All changes remain uncommitted; implementation, independent review and publication remain
outstanding. No commit, push, PR or merge occurred. Unrelated main-checkout work was untouched and is not claimed
clean; earlier receipts remain historical.
