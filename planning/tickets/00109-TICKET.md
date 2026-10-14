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
| [TASK-00441](../tasks/00441-TASK.md) | Check and inspect designated-checkout update availability | needs-info |
| [TASK-00442](../tasks/00442-TASK.md) | Fast-forward and reconcile the designated checkout safely | needs-info |
| [TASK-00443](../tasks/00443-TASK.md) | Configure and run currently authorized scheduled synchronization | needs-info |
| [TASK-00444](../tasks/00444-TASK.md) | Synchronize from the Dashboard and complete the instruction-change journey | ready-for-agent |
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
TASK work. TICKET-00106 now maps TASK-00432's exact Save/Delete/result/currentness contracts and TASK-00433's
form/recovery extension. Its protected mutation/shared-custody/physical-guarantee hold remains; compose those early
owners without reverse-blocking writes on final sync or fabricating physical exclusion from database metadata.
TICKET-00107 now maps TASK-00434/00435's bounded suggestions and existing-form adoption, supplying the optional
assistance portion of this final real instruction-change journey. Its inherited runtime holds remain, and no
completed sync journey reverse-blocks assistance or grants it file effects. TICKET-00108 now maps TASK-00436–00440's
exact publication admission, original local/provider effects, verified safe-default return and browser handoff.
Compose its early shared custody/currentness/results without a second registry; verified PR alone is not return,
merge, released ownership or Sync consent. The final integrated journey consumes actual TASK-00440 delivery, but
early sync primitives must not reverse-block publication or direct editing. All protected-operation holds remain.
These incoming mappings did not allocate sync TASKs; the subsequent approval below does. Neither requirement
approval nor decomposition qualifies schedule/actor/Git effects or creates operational authority.

### Approved TASK decomposition and current handoff

The maintainer approved four slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked features with blank PRs, normally one independently reviewable PR each.

| Slice | Accepted owner | Status | Exact blockers | Delivered boundary |
|---|---|---|---|---|
| A | [TASK-00441](../tasks/00441-TASK.md) | needs-info | TASK-00430, TASK-00117 | Retained status and explicit protected bounded remote check/fetch with original recovery |
| B | [TASK-00442](../tasks/00442-TASK.md) | needs-info | TASK-00441, TASK-00432 | Exact manual preview/confirmation, protected clean default fast-forward, recovery and drift integration |
| C | [TASK-00443](../tasks/00443-TASK.md) | needs-info | TASK-00442 | Revisioned settings, actual current scheduled actor/delegation and bounded scheduled checks/updates |
| D | [TASK-00444](../tasks/00444-TASK.md) | ready-for-agent | TASK-00443, TASK-00435, TASK-00440 | Existing Dashboard sync/settings/recovery and final real instruction-change acceptance |

TASK-00441 retains protected source-read/fetch transport and exact shared-custody mapping holds. TASK-00442 separately
needs protected conditional Git update and physical exclusion guarantees. TASK-00443 separately needs actual
scheduled actor/delegation/revocation participants and protected schedule identity/runtime mapping. No unknown
supplier ID is guessed, and no ready metadata qualifies runtime. Enrollment, Workflow resource metadata, generic
Scheduler configuration and credential-recovery schedules do not supply those capabilities automatically.

Reuse registered identity/configuration, TASK-00430 inspection, TASK-00432 result/currentness, TASK-00341/00084 drift
and actual shared custody participants. Coordinate early TASK-00436 publication admission and TASK-00369 resource
contracts without requiring complete publication, creation, credential-delivery or coding/Review journeys. No second
registry, scheduler platform, implicit administrator, fabricated Workflow/Agent or borrowed grant. No prior TASK
metadata, priority or independent hold changes and no early owner gains a reverse sync/browser dependency.

Retained queries expose bounded applicable freshness/incoming facts without network or Git work. Explicit checks
require current check/source authority and separately committed original intent; bounded fetch may change recorded
remote-reference evidence but never updates the worktree implicitly. Exact source/default/principal, custody and
physical safety remain mandatory even for fetch. Unsupported or incomplete evidence remains unknown, not safe.

Manual consent binds exact base/target and complete eligibility. Every update rechecks current Sync authority,
registered default/source identity, online safe checkout, clean worktree/index, no unpushed/divergent/conflicting
state and strictly behind fast-forward relationship at the physical effect boundary. Material changes invalidate
manual consent. Scheduled updates use the same path under their own current actual actor/delegation, policy/settings
revision and exact target; default-enabled checks/configuration are not permanent grants or stronger privileges.

Actual authority/resource/configuration/revocation participants share PostgreSQL fences and atomic state/audit/intent;
external operations remain outside transactions. Database locks do not freeze refs or filesystem writers. Deny
unsupported physical guarantees, preserve original partial/unknown outcomes and custody, and reconcile before another
update. No blind retry, duplicate reported update, false success from process exit/ACK, timeout-based release,
stash/reset/rebase/discard, branch switch/deletion or destructive repair. A stop cannot undo already-admitted work.

Observed file digests stale forms through expected-state rejection without replacing drafts; effective composition
and other meaningful Git/context drift follow existing owners without rewriting immutable snapshots/proposals,
reloading Pi or resuming Agents. Unloaded-file changes do not invent effective drift. Sync imports committed Git
content, not source execution, permissions or Planning authority. Browser state stays volatile/no-store with late
success/error fences, distinct permissions and truthful disabled/blocked/partial/unavailable recovery.

TASK-00444 delivers the focused browser controls and owns the real Fight Agent OS acceptance journey across all five
TICKETs: inspect, qualify assistance, human Save, exact PR/human handoff and safe return, separately authorized normal
human GitHub merge, then verified clean default sync. Assistance remains optional for individual edits, not optional
qualification; native sync has no AI dependency. Missing actual merge/sync remains incomplete, never synthetic proof.
Tests, real PostgreSQL/Git/Runner/GitHub/browser qualification, direct scheduler evidence, accessible responsive
light/dark evidence, guidance and full `./bin/build` stay within delivery, not a proof-only TASK.

This completes EPIC-00010's five approved TASK splits and the selected original vision map's remaining decomposition
phase; it does not complete implementation, runtime qualification or unrelated maps. All original EPIC-00005–00010
destinations retain their accepted requirements and existing work. Next: `/skill:next` for authoritative Board-based
selection, preserving upstream TASK-00138 completion and terminal-first priorities. This recommendation starts no implementation.
Checkout choice and finite operational authority remain separate; Markdown remains Planning authority.

### Planning verification

This is the historical pre-publication checkpoint. The authorized publication reconciliation below supersedes its
counts, unchanged-HEAD comparisons and active TASK-00138 observation; it does not relabel them as fresh evidence.

Recorded the approved split in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving
prior uncommitted planning. Highest live ID before allocation was TASK-00440; no archived TASK records existed.
TASK-00441–00444 match the approved titles, exact blockers/statuses and template sections. All are unranked features
with blank PRs. TASK-00441–00443 retain distinct protected transport/custody, conditional Git/physical-exclusion and
scheduled actor/delegation/runtime holds; TASK-00444 is ready behind dependencies, not runtime-qualified.

`./bin/planning-check --write` and `./bin/planning-check` passed with 571 records, 479 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 136 new/untracked TASK
whitespace checks passed. Before/after hashing of every pre-existing TASK's frontmatter confirmed no existing
status, priority, dependency or PR metadata changed in this recording pass. No existing owner gained a reverse sync
dependency and no unknown supplier ID was invented.

TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD. TASK-00138 remains the sole
active Board item, order 2. Source-linked approved map/EPIC/TICKET handoffs record completion of original EPIC-00005–
00010 planning phases; a metadata cross-check also found TASK coverage for their TICKETs. This is decomposition
coverage, not implementation acceptance or qualification. TICKET-00109 is removed from the generated Planning
Frontier; unrelated frontier work remains. No stale current TICKET-00109 decomposition command remains. Current
handoffs recommend `/skill:next` for Board-based selection without invoking execution or changing active work.

Read/Save/form/currentness/configuration/custody and publication owners record early versus final consumer
coordination without scope expansion or metadata changes. All independent holds and Markdown Planning authority
remain. Full `./bin/build` was not run and remains required before commit/publication. These are planning checks,
not fresh PostgreSQL/Git/Runner/GitHub/scheduler/browser acceptance evidence. No private instruction read, credential
access, provider request, schedule/delegation, fetch/update, Save, reservation, PR, human merge, cleanup or cutover
occurred. Implementation, actual qualification, independent review and publication remain outstanding.

All changes remain uncommitted; no commit, push, PR or merge occurred. Unrelated main-checkout work was untouched
and is not claimed clean. Earlier receipts remain historical; final cross-journey acceptance and separately human-
controlled merge authority are not supplied by this planning approval.

### Authorized planning publication checkpoint

John requested committing/pushing the approved planning changes, opening a PR to `develop`, then removing only the
extra planning worktree. After an upstream identity collision was reported, he explicitly approved renumbering only
this worktree's unpublished managed-Review TASK-00309 to TASK-00445 and reconciling current upstream contracts.
Upstream TASK-00309 remains the distinct TICKET-00039 local file-screening activation/enforcement record, unchanged.
The 136 new TASKs are now TASK-00310–00445; no scope, status, priority or blocker meaning changed from renumbering.

The unpublished branch advanced from `dcb5d96` to `origin/develop` at `e2557b5`, retaining the independently committed
web-screening requirement amendment as rebased `a6190ae`. `git range-diff` reports that amendment's patch unchanged.
The owned uncommitted planning was backed up outside the disposable worktree and reapplied. Generated Board/index
conflicts were regenerated from records; TICKET-00037 retained both upstream credential/live-evidence history and
our managed web-consumer coordination. Upstream source, execution contracts, local screening ownership and completed
TASKs remain intact. This is authorized planning publication, not independently accepted TASK implementation.

The October 6 tool-mediated execution contract governs all execution consumers: Engineer edits scoped files/notes,
Team Lead requests permitted fixed checks, and trusted application operations execute checks and prepare commits.
TASK-00310's repair wording now names those owners explicitly rather than implying Engineer shell/Git access.
No general MCP prerequisite, new grant or relaxation of current authority/physical qualification follows. Existing
supplier holds remain unless their actual owning record separately resolves them; reconciliation is not live proof.

Current upstream TASK-00138 is done, not active; the refreshed Board has no active TASK and TASK-00139 is first ready.
TASK-00117/00159/00162/00163/00184 completion and upstream local screening TASK-00309 are preserved. Earlier
pre-publication active-work/count/readiness receipts throughout this planning batch remain historical, not current
Board facts. No terminal priority is changed and no implementation is started. Planning remains Markdown-authoritative.

Fresh reconciled planning checks pass with 572 records, 473 active and current generated views. Fresh full
`./bin/build` passed: receipt `20261014T210423Z-19`, 205.67 seconds; 703 backend tests/4,969 assertions, 409 frontend
tests, 39 Storybook tests and 78 catalog captures with zero violations, overflow, external requests or page errors.
Domain/Application coverage is 203/203 lines; Adapter coverage is 3,183/3,248 lines (65 uncovered). Source stability
passed. Existing Sass/Bootstrap import/if/global-builtin/color-function deprecations remain (311 repetitive warnings
omitted in each of three compile reports), along with the Storybook bundle-size warning; none was suppressed.
The final documented/staged tree must pass the full gate again before commit; retain that receipt outside the
worktree and in the PR body without pretending this earlier receipt covers later edits. The isolated build uses its own `fight-planning-tasks` Compose project and databases,
locked dependencies copied from the matching prepared main checkout, newly generated disposable local keys, and
read-only common Git metadata visibility; no other worktree's services, databases or credentials are reused.

Behavioral/visual QA is not applicable to this Markdown-only planning diff: no product UI or runtime behavior changes.
The required full gate is still mandatory; its baseline browser captures cannot prove planned product journeys.
Independent technical review and human PR review/merge remain pending and separate. No hosted acceptance gate is
required by this planning scope. Preserve sanitized receipts outside the disposable worktree under
`.runs/handoffs/planning-tasks-00083-00109/`; publication/cleanup must verify the final head and clean ownership first.
