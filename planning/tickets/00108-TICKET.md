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
| None | — | — |
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
whole-TICKET dependency. No TASK, grant, branch, commit, push, PR, cleanup or merge is created by this approval.
