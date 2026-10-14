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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md),
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume [TICKET-00105](00105-TICKET.md) inspection and existing identity/context/Runner primitives. Define shared
checkout ownership and effective-change/result contracts before final publication/sync integration, not mutually
blocked whole-TICKET journeys. [TICKET-00107](00107-TICKET.md) supplies optional proposals; direct editing never
waits for provider assistance. [TICKET-00108](00108-TICKET.md)/[TICKET-00109](00109-TICKET.md) retain publication
and synchronization. No TASK, instruction mutation, Agent continuation or source-host authority is created here.
