---
id: TICKET-00108
epic: EPIC-00010
title: Publish an instruction change through a recoverable PR
status: ready-for-agent
---

# Publish an instruction change through a recoverable PR

## Problem statement

Saving a local instruction file is not publication consent. A human needs to publish only the selected reviewed
change, recover partial Git/GitHub effects and return the designated checkout safely without including unrelated
work or borrowing managed Release Manager authority.

## Solution and boundaries

Provide Create pull request as the default separate action after a local instruction change. Require current Publish
repository changes authority and the designated checkout's exact registered identity, source, default base and
eligible Runner. Reuse safe Git/provider capabilities, not EPIC-00008's managed Review/Release Manager state machine.
No browser or instruction-writing Agent receives source-host credentials or authority to invoke this operation.

Before preview establish a clean publication baseline except for the selected recognized instruction-file change.
Refuse unrelated staged, modified, untracked, ignored-sensitive, unpushed, divergent, conflicting or operation-owned
state. Report ignored/untracked selected-file publication eligibility explicitly; do not force-add ignored content,
hide unrelated work, auto-stash, reset, discard or infer that a loaded file is already tracked. No arbitrary file,
recursive staging or implicit additional changes may enter the subject.

Preview the exact selected-file diff, collision-safe owned branch, editable commit message, registered remote and
provider identity, registered default target branch and all intended Git/source-host/return-to-default effects.
Bind file digest/absence, baseline Git state, checkout/context, live authority and ownership to one confirmed plan.
Material changes invalidate it; a saved draft or previous confirmation grants no consent for a changed target or
subject. Revalidate exact current facts at each effect boundary under shared checkout/resource coordination.

One explicit confirmation admits a durable publication operation. Deterministically create/switch to the owned
branch, stage only the selected recognized change (including a permitted creation or deletion), create one exact
commit, push without force and create one GitHub PR against the registered default base. Record actual branch/ref,
commit/tree, remote provider identity and PR subject evidence. Use protected qualified source-host authentication
and transport; no token/key reaches content, browser Agent, generated evidence or ordinary logs. Missing current
identity, permission, scope, organization policy, SAML, connectivity or safe host support fails visibly.

Persist original operation identity, immutable plan, actor/grant, individual effect intents and verified outcomes.
Commit state/audit/intent before external effects and reconcile outcome separately. A queue acknowledgement, process
exit or disappeared process cannot prove publication. Reconcile interrupted branch/stage/commit/push/PR operations
against the exact owned subject before retry; matching names or an unrelated existing PR are not adoption authority.
Do not duplicate commits or PRs, blindly replay uncertain pushes or force an unexpected branch/ref back into place.

Preserve truthful partial results for commit failure, unknown push, push-success/PR-failure, an existing exact
operation-owned PR, authority change and application/Runner/browser interruption. Expose bounded original-operation
status/recovery controls, warnings and retained resources. An explicit stop prevents subsequent effects but does not
undo a completed push or PR. No automatic destructive compensation or remote branch cleanup is included.

After verified PR creation, return to the registered default branch only when the operation-owned transition is
safe. Verify that checkout is clean at its prior/default revision; never leave proposal content applied on default,
discard unrelated new work or claim successful return while process/provider/ownership state is uncertain. Returning
from the proposal branch can change effective instruction composition, so use the existing context/form drift
contracts and preserve immutable session history. A blocked return remains an explicit partial outcome even though
the PR exists; future editing/sync must respect retained ownership until safe reconciliation.

Show provider identity, PR number/title/source/target, exact pushed commit, known publication/merge observations,
warnings, retained local state and next action, with Open pull request on GitHub in a new tab. Merge observation
is read-only evidence, never approval or merge authority. Saving, commit, push, PR creation, safe return, human merge
and later synchronization remain separate facts. TICKET-00109 owns bringing merged changes into the default checkout.

Exclude PR approval/merge, force push, unrelated changes, credential enrollment, managed Review/QA, Agent submission,
source release/deployment, destructive recovery and remote branch deletion. The optional repository-policy-enabled
Commit and push current branch alternative remains deferred: it would require a separate explicit preview and
confirmation under the same rules, never permission inferred from this default PR flow or Save.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Preview selected-file publication | N/A — read-only preflight | Exact diff, baseline, remote/base, authority, collision and ownership facts | N/A — preview commits/publishes nothing | Complete effect summary or actionable refusal |
| Confirm and publish | Confirm exact PR operation; execute admitted deterministic stages | Original command result and fresh per-stage preconditions | Committed admission and verified Git/provider outcome facts | Owned branch, exact selected-file commit, non-force push and one PR |
| Reconcile partial effects | Explicit original-operation recovery/retry/stop | Actual local Git/process/remote/PR state and retained resources | Confirmed recovery/hold facts | Resume only safe missing effects; preserve original evidence |
| Return to default safely | Admitted owned return-to-default stage | Exact current branch/index/worktree, default revision and ownership | Verified return or blocked-state fact; existing effective-context consequences | Clean designated default checkout only when proven safe |
| Inspect handoff or merge observation | N/A — bounded authorized reads/navigation | Exact PR subject, observed merge state, warnings and recovery | N/A for reads; observations are not merge authority | Human GitHub handoff, no approval/merge or automatic synchronization |

## Validation and permissions

Require current operation-specific human publication and source-host authority at exact Repository/checkout/remote
scope, independent of View, Update or AI-use grants. Validate bounded commit message, branch/ref/URL/provider and
complete selected-file diff; reject command injection, changed source identity and any unpreviewed subject. Apply
shared resource/currentness fences with save and sync; a publication preview is not permanent ownership.

ADR 0002 governs required state/audit/intent and post-commit effects. Retry must reconcile original owned identities
and unknown outcomes, not promise exactly-once provider calls. Preserve safe failure/correlation evidence without
raw credentials/provider dumps. ADR 0003/0004 no-store, current-context response fences and no automatic business
replay protect browser controls. Navigation to GitHub contains no credentials and does not broaden application grants.

## Acceptance and evidence

- A real selected instruction change is separately previewed/confirmed, committed alone on its owned branch,
  pushed without force and represented by one exact GitHub PR with a usable human handoff.
- Unrelated/ignored-sensitive/staged/dirty/divergent/unpushed/conflicting state and material preview drift refuse
  unsafe publication without hiding changes or weakening source/authority checks.
- Lost acknowledgement, stage failures and restart retain correct commit/ref/PR identity and truthful partial
  outcomes. Retrying cannot create a duplicate commit/PR, overwrite another resource or expose source-host secrets.
- Verified return leaves default clean without proposal content; unsafe return holds visibly and protects unrelated
  work. Context/form freshness follows real effective-file changes, not a false claim that publication merged it.
- Owned operation/authorization/PostgreSQL/client tests, separately authorized real Git/Runner/GitHub evidence and
  `./bin/build` pass. Do not test Git/GitHub/library internals, wrappers or configuration. Final real merge/sync
  evidence belongs to TICKET-00109's integrated journey with human merge authority kept separate.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00436](../tasks/00436-TASK.md) | Preview and admit exact instruction publication | needs-info |
| [TASK-00437](../tasks/00437-TASK.md) | Create and recover the selected-file commit | needs-info |
| [TASK-00438](../tasks/00438-TASK.md) | Push and reconcile one exact GitHub PR | needs-info |
| [TASK-00439](../tasks/00439-TASK.md) | Return the published checkout safely to default | ready-for-agent |
| [TASK-00440](../tasks/00440-TASK.md) | Publish and recover instruction changes in the Dashboard | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md),
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume [TICKET-00105](00105-TICKET.md)/[TICKET-00106](00106-TICKET.md) selected-file evidence and exact early
identity/context/Runner/Git/provider contracts. TICKET-00102's initial-project publication and TICKET-00083's managed
publication retain different intents and authority; reusable adapters do not make those completed journeys
prerequisites. Share checkout ownership and drift primitives with [TICKET-00109](00109-TICKET.md), not a circular
whole-TICKET dependency. TICKET-00106 now maps TASK-00432's exact Save/Delete/result/currentness contracts and
TASK-00433's existing-Dashboard form. Its protected mutation/shared-custody/physical-guarantee hold remains;
publication must compose the actual early owners without reverse-blocking Save or inventing a second lock registry.
TICKET-00107 now maps TASK-00434/00435's protected suggestions and explicit existing-form adoption. Neither
proposal nor Save grants publication; assistance availability must not gate an independently authorized direct-edit
publication path. These incoming mappings did not allocate publication TASKs; the subsequent approval below does.
Neither requirement approval nor decomposition qualifies Git/provider effects or grants operational authority.

### Approved TASK decomposition and current handoff

The maintainer approved five slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked features with blank PRs, normally one independently reviewable PR each.

| Slice | Accepted owner | Status | Exact blockers | Delivered boundary |
|---|---|---|---|---|
| A | [TASK-00436](../tasks/00436-TASK.md) | needs-info | TASK-00432, TASK-00117 | Complete selected-file publication preview/confirmation API, original admission and early stage/custody participants |
| B | [TASK-00437](../tasks/00437-TASK.md) | needs-info | TASK-00436 | Owned branch/switch, exact selected-file staging/commit and original local-effect recovery |
| C | [TASK-00438](../tasks/00438-TASK.md) | needs-info | TASK-00437 | Protected non-force push, one attributable GitHub PR and original provider-effect reconciliation |
| D | [TASK-00439](../tasks/00439-TASK.md) | ready-for-agent | TASK-00438 | Safe owned return to original registered-default revision, verified clean state, currentness and custody disposition |
| E | [TASK-00440](../tasks/00440-TASK.md) | ready-for-agent | TASK-00439, TASK-00433 | Existing Dashboard preview/confirmation, original recovery, retained-state warnings and real PR/return handoff |

TASK-00436 retains exact human publication admission/shared-custody and protected source-host identity/preflight
mapping holds. TASK-00437 separately needs protected existing-checkout Git branch/index/commit invocation and physical
exclusion guarantees. TASK-00438 separately needs protected authenticated Git transport and GitHub PR invocation.
Map actual narrow suppliers under existing execution/Runner/source-host owners before implementation; no unknown
supplier ID, second registry/provisioner, fabricated Workflow/Agent or borrowed Release Manager grant is introduced.
TASK-00418/00420 retain related creation-specific `gh`/Git holds, not whole-journey prerequisites or already-qualified
suppliers. TASK-00315's broker remains Release-Manager-only. Enrollment and database locks do not prove physical
Git safety. TASK-00439/00440 are ready behind unfinished/held suppliers, not runtime-qualified.

TASK-00432 supplies early exact Save/result/custody/currentness contracts, transitively including TASK-00430's read
boundary and TASK-00341/00084's immutable drift rules. TASK-00433 supplies the existing form, not publication consent.
All independent read/mutation/runtime holds remain. Early stage/ownership/result contracts are supplied without
waiting for completed later publication/browser/sync consumers. No direct edit, optional assistance or terminal
owner gains a reverse dependency or stronger clean-tree requirement for ordinary Save.

Only a selected recognized root file can become the confirmed subject, with an otherwise eligible clean baseline.
No force-add of ignored content, unrelated staging, stash/reset/discard, branch adoption or hidden normalization.
Preview exact diff, owned collision-safe branch, bounded commit message, source/native provider identity and default
base plus all intended effects. One confirmation admits that exact plan; current authority and expected state are
rechecked at every effect boundary, and material changes require renewed human consent. Source-host API identity
does not establish the Git transport principal. No browser/Agent receives credentials or publication authority.

Commit required state/audit/original effect intent before external work; verify outcomes separately through actual
PostgreSQL participants. Branch/switch/staging/commit, push/PR and return each retain their own original identity and
reconciliation. Process exit, matching names/bytes, queue acknowledgement and expired lease are not success or
ownership. Partial/unknown outcomes remain held; no duplicate commit/PR, blind push replay, automatic compensation,
remote branch deletion or exactly-once claim. Stop blocks subsequent effects, not undoing completed publication.

After verified PR creation, return only when the owned checkout transition is physically safe, the default identity/
original revision is unchanged and unrelated work is preserved. Independently verify clean default and absence of
proposal content; blocked return remains partial even when the PR exists. Release only proved releasable custody.
Use actual observed file/effective changes for existing form/context drift, preserving immutable history without
Pi reload or Agent continuation. Return does not fetch, move default to a new revision, merge or synchronize.

The browser publishes saved local state, never unsent drafts or an Agent suggestion implicitly. Keep explicit
confirmation, exact original recovery, no-store volatile state and authentication/context/form-generation fences.
Show PR identity/title/base/head/OID, truthful publication versus merge observations and retained local-state warnings;
open only the qualified credential-free GitHub link in a protected new tab. No newer-draft overwrite, automatic
business replay, second editor/session or AI prerequisite. TICKET-00109 retains synchronization and final real Fight
Agent OS instruction-change acceptance with separately human-controlled merge.

Owned tests, actual PostgreSQL/Git/Runner/GitHub integration, fresh accessible responsive light/dark browser evidence,
guidance and `./bin/build` remain inside these delivery slices. Qualify tools/configuration directly, not product
tests of Git/GitHub, wrappers, generated artifacts or infrastructure; no proof-only TASK. Implementation checkout
and finite private-read/credential/file/provider effects require separate authority. Preserve active TASK-00138,
terminal-first priorities, existing holds and Markdown Planning authority.

This completes this TICKET's split only. Continue `/skill:next` for Board-based selection after TICKET-00109's approved TASK-00441–00444 split.
Its core check/update/scheduling primitives consume early custody/results without reverse-blocking publication;
TASK-00444 consumes actual browser/PR/safe-return delivery before separate human merge and final sync acceptance. No implementation, live qualification, full build, independent review or publication occurred.

### Planning verification

This historical publication-split receipt predates TICKET-00109's final split; latest planning verification is in
TICKET-00109. Counts and handoff state below describe the earlier checkpoint, not runtime evidence.

Recorded the approved split in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving
prior uncommitted planning. Highest live ID before allocation was TASK-00435; no archived TASK records existed.
TASK-00436–00440 match approved titles, exact blockers and template sections, all unranked features with blank PRs.
TASK-00436–00438 remain `needs-info` for their distinct exact protected-operation mappings; TASK-00439/00440 are
ready behind dependencies, not runtime-qualified. Existing read/mutation/custody/host/provider holds remain intact.

`./bin/planning-check --write` and `./bin/planning-check` passed with 567 records, 475 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 132 new/untracked TASK
whitespace checks passed. Before/after hashing of every pre-existing TASK's frontmatter confirmed no status,
priority, dependency or PR metadata changed in this recording pass. No existing owner gained a reverse publication
dependency and no unknown supplier ID was allocated.

TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD. TASK-00138 remains the sole
active Board item, order 2. Save/form/currentness/custody and related creation/managed-publication owners record
narrow coordination without scope expansion or metadata changes. TICKET-00109 records incoming publication/result/
return contracts, not an approved sync split or operational authority. Current handoffs advance to TICKET-00109;
it remains on the Planning Frontier, TICKET-00108 is removed, and no stale current TICKET-00108 command remains.

Full `./bin/build` was not run and remains required before commit/publication. These are planning checks, not fresh
PostgreSQL/Git/Runner/GitHub/browser acceptance evidence. No private instruction read, credential access, provider
request, reservation, branch/index/commit/push/PR effect, default return, merge, cleanup or sync occurred. Markdown
Planning authority is unchanged. Implementation, actual qualification, independent review and publication remain
outstanding. All changes remain uncommitted; no commit, push, PR or merge occurred. Unrelated main-checkout work
was untouched and is not claimed clean; earlier receipts remain historical.
