---
id: TICKET-00109
epic: EPIC-00010
title: Synchronize the designated checkout safely
status: ready-for-agent
---

# Synchronize the designated checkout safely

## Problem statement

A merged instruction change should reach the clean designated Dashboard checkout without background work
rewriting drafts, local commits or another operation's work. Humans need honest update availability and a safe
manual or scheduled fast-forward path, not destructive Git repair disguised as synchronization.

## Solution and boundaries

Keep the designated checkout on the registered default branch for ordinary Dashboard operations. Provide an
explicit Sync checkout action and scheduled remote checks enabled by default, with settings configurable only
through current Configure scheduled checkout synchronization authority. Manual synchronization and each admitted
scheduled update require their own current Synchronize the designated checkout authority; schedule configuration
is not a permanent grant, arbitrary source-host access or permission for destructive recovery. Define actual
scheduled actor/delegation and revocation behavior in later TASKs rather than invent an implicit administrator.

Consume TICKET-00024/00025's designated identity/source/default branch and qualified deterministic Runner/Git
capabilities. Before any update require: online eligible identity-matched checkout under approved roots; registered
default branch; clean worktree/index; no unpushed commits or divergence; no active Workflow/publication or other
conflicting owner; registered upstream/source match; and a strictly behind, fast-forward-only relationship to the
verified remote default branch. Current Permission, scope and policy must still hold. Missing/unknown facts deny
update and show the blocking reason, not a default-to-safe assumption.

Keep remote observation/fetch separate from updating branch/worktree. Queries expose known bounded freshness and
incoming-change facts but never initiate mutating sync as a read side effect. An explicitly admitted check may use
protected bounded network/Git fetch operations under current authority, with observed reference effects recorded;
it cannot update the working tree merely because a fetch succeeded. Unsafe identity/ownership must not be bypassed
even for fetch. Failure of any update predicate leaves branch/worktree unchanged and reports known update availability,
incoming commits where safely available, uncertainty and a permitted next action.

Manual Sync previews incoming commits, exact local/remote/default identity and intended fast-forward before the
explicit update, then rechecks the same conditions at the effect boundary. Scheduled work may automatically apply
only the same verified eligible fast-forward under current configured policy and authority. No manual destructive
override or stronger background privilege is available. If the observed target materially changes before the
manual update, invalidate its preview; scheduled admission must likewise bind/recheck its exact authorized target.

Use shared checkout/resource ownership and currentness fencing with TICKET-00106 saves and TICKET-00108 publication,
and existing Workflow custody where applicable. Concurrent browser edits, branch/remote changes or another claimed
operation cannot slip between observation and update. Reject stale workers and conflicting scheduled/manual attempts;
reconcile the original effect before another update. Never auto-stash, reset, rebase, merge divergence, discard work,
switch a dirty checkout, resolve conflicts, delete branches or override another owner.

Retain attributable check/update identity, actor or actual scheduled delegation, settings revision, source/branch,
base/target commit, preconditions and verified outcome. Record durable effect intent before external work and verify
actual refs/worktree afterward. Restart, redelivery, cancellation and lost acknowledgement must distinguish known
completed fast-forward, failed/no-effect and uncertain state; process exit or queue receipt is not completion.
An already reconciled update is not executed again or reported as a second new change. Configuration changes do not
retroactively authorize old queued work; current checks still apply.

When synchronization changes recognized instruction content, update file evidence and stale open forms through the
same expected-digest contract. Only effective Pi composition changes stale relevant RepositoryContextSnapshots/
Agent sessions through their existing owners. Preserve historical snapshots and durable proposals; never silently
replace an edited browser form, reload Pi or resume an Agent. An unloaded-file change must not fabricate effective
drift. Apply these contracts to actual observed outcomes rather than assuming every fetched commit changes context.

Show current known synchronization state, last applicable check/update result, incoming commits and blocked/stale/
partial/unavailable reasons in the focused instruction journey. Existing shell/authentication and theme owners
remain; no general repository synchronization dashboard or scheduler platform is required. Root, credential and
source configuration repairs remain explicit operations under existing owners, never automatic remediation.

Own the integrated production acceptance journey across TICKET-00105–00109 on a real Fight Agent OS instruction
change: inspect effective files, request/review assistance where useful, save with expected digest, publish the
selected-file PR, open its human handoff, merge only under separately authorized normal human GitHub control, then
safely synchronize the clean designated checkout. Preserve ordinary mutation/PR/context/recovery evidence and
qualify actual assistance through TICKET-00107 even if it is not needed for every edit. No proof-only endpoint,
Workflow, recurring certification or extra acceptance TICKET. A missing real merge/sync outcome remains explicit;
planning approval or application publication never grants human merge authority.

Exclude destructive Git recovery, dirty-tree mutation, force-push, PR approval/merge, remote branch cleanup,
arbitrary checkout selection, instruction auto-editing, Agent execution, general background automation and changes
to the terminal-first TASK milestone. Synchronization imports Git content, not Planning authority or source-host
permissions; no Markdown removal/import/cutover is triggered.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect known update state | N/A — read-only | Authorized last observation, freshness, incoming commits and blocked reasons | N/A — viewing is not a sync event | Honest bounded status without fetch/update as a query side effect |
| Configure scheduled checks | Expected-state schedule configuration operation | Current settings/delegation, scope and capability | Committed settings facts | Future bounded checks enabled/changed/disabled, never permanent authority |
| Check the registered remote | Explicit or currently authorized scheduled bounded check | Exact source/identity, original result and current ownership | Attributed safe check observations | Qualified remote observation/fetch only; no implicit worktree update |
| Synchronize manually or on schedule | Preview/confirm manual Sync; eligible admitted scheduled update | Exact base/target, complete safety predicates and current authority | Verified fast-forward/hold facts and owning drift consequences | Only a proven clean default-branch fast-forward |
| Reconcile interruption | Original-operation reconciliation or safe retry | Actual ref/worktree/process state, prior intent and remaining authority | Confirmed outcome or explicit uncertainty | No duplicate update, takeover of unrelated work or destructive repair |

## Validation and permissions

Keep View, Sync and Configure scheduled sync capabilities distinct; administrator labels and cached schedule state
do not authorize effects. Revalidate source identity, branch/ref, local/root ownership, actual principal/delegation
and every safety predicate under shared coordination. Validate bounded settings, pagination/history and process/
network limits; reject unsupported or ambiguous branch relationships rather than guessing. Keep Git credentials
protected and arbitrary output/secret-bearing paths out of events, logs and browser errors.

Use ADR 0002 atomic state/audit/intent with external fetch/update outside transactions and verified outcome afterward.
Apply ADR 0003/0004 protected loaders/actions, no-store, private-state invalidation and late-response fences. Reconnect
reads durable state, not automatically retries a mutation. A race/failure must preserve unrelated content and honest
uncertainty; no claim of exactly-once external effects or instantaneous client invalidation is implied.

## Acceptance and evidence

- Manual and scheduled eligible updates fast-forward only the clean identity-matched registered default checkout.
  Current authority and separately authorized settings apply; revoked/stale scheduled work cannot continue on old grants.
- Dirty/staged/untracked-sensitive, unpushed, divergent, wrong-branch/source, offline, unsafe and operation-owned
  cases refuse worktree update without stash/reset/rebase/switch/conflict repair. Unknown state remains blocked.
- Concurrent save/publication/sync, target changes, restart and uncertain outcomes preserve original operation and
  unrelated work. Actual updated instruction digests stale forms and only effective changes stale sessions/context.
- Complete real Fight Agent OS instruction-change evidence covers local save, exact PR handoff, separately human
  merged result and safe default sync. Each owning TICKET retains its focused evidence; no inherited receipt is
  labelled fresh application proof and no synthetic-only demonstration substitutes for the real journey.
- Owned safety/authority/recovery/PostgreSQL/client tests, real Git/Runner/GitHub/browser evidence, direct scheduler
  qualification and `./bin/build` pass. Do not add product tests of Git, scheduler/configuration, wrappers, generated
  files, test infrastructure or the quality gate.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 5 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md),
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume early existing registration/context/Runner/Git and shared checkout ownership/drift primitives rather than
completed coding/Review journeys. [TICKET-00105](00105-TICKET.md)/[TICKET-00106](00106-TICKET.md) consume status/
stale facts, [TICKET-00107](00107-TICKET.md) retains assistance and [TICKET-00108](00108-TICKET.md) retains publication.
Final cross-journey acceptance follows these actual suppliers but must not reverse-block their early contracts or
require a separate demonstration TASK. Exact schedule/actor/fencing contracts and implementation slices remain later
TASK work. This approval creates no TASK, schedule, fetch, update, provider access, PR, merge or runtime authority.
