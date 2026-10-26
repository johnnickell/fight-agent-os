# Execution Standards

These rules govern implementation of one approved TASK.

## Scope and ownership

The TASK and its accepted parents authorize the change. Resolve ambiguous acceptance, blockers, repository identity, destructive operations, and file ownership before writing. Planning decomposition and product-scope expansion require a separate handoff.

Treat pre-existing modifications as unrelated until ownership is proved. Preserve them in place and exclude them from TASK diffs and commits. Put notes, logs, generated evidence, and other scratch under the [ignored scratch layout](SCRATCH.md); put isolated worktrees beneath `.runs/worktrees/`. Do not reorganize another owner's evidence to make a checkout tidy.

## Branches and commits

For a new TASK use `feature/task-NNNNN-<slug>` from `develop` in the user-selected main checkout or isolated worktree; preserve an already established branch even if named under the prior `feature/*` convention. Establish branch ancestry and a status baseline before implementation. Never use cleanup, reset, checkout, or force operations against uncertain work.

One TASK normally produces one independently reviewable PR outcome. Stage explicit TASK-owned paths, inspect the staged diff, and keep the implementation commit bounded. A commit grants no push, publication, review, landing, merge, archive, release, or deployment authority.

## Review handoff intake

Resolve the base worktree through Git's common directory and registered-worktree metadata. The canonical handoff is `<base-worktree>/.runs/reviews/<TASK-ID>/review.md` ([review standards](REVIEW.md)); numbered siblings, if present, are historical and never select the current verdict.

For first implementation, a missing report is normal. For revision, read the canonical report and match its TASK, branch, reviewed base/head, status, findings and verdict to the implementation snapshot being corrected.

Version 3 is the current format; version-2 canonical reports remain usable if their identity and recorded snapshot match. If the branch has moved, trace the intervening changes rather than guessing that findings still apply.

Reject an unreadable, ambiguous or stale handoff and request a fresh review. A `revise` report supplies correction
input; after `accept`, satisfy [QA applicability](QA.md#applicability) before ordinary landing. Landing can
reconcile a moving base under [landing standards](LANDING.md) without pretending the old report names new OIDs.

A `revise` verdict does not always require source changes. Before initial acceptance, if the only remaining findings are missing explicitly required hosted
verification, preserve the implementation and report the evidence action. Where CI requires publication, route
to [draft publication](LANDING.md#draft-publication-to-obtain-hosted-evidence) within existing user authority, or
request only the missing publication authority. `work` does not push, create a PR, make empty commits, or repeat
successful checks merely to turn an evidence-only finding into an implementation revision. When evidence arrives,
route to focused independent review; a confirmed implementation failure returns here for repair. If accepted
candidate A already has administrative closeout B, resume [final delivery verification](LANDING.md#verify-and-publish)
through `land`, including when the required proof arrives. Administrative closeout alone needs no independent
review or canonical review update; do not repeat initial draft intake or toggle A's tracked completion checkpoint.

## QA handoff intake

Read `<base-worktree>/.runs/qa/<TASK-ID>/qa.md` when present using [QA standards](QA.md). Match its subject and
requirements to current work, inspect linked evidence, and revalidate each confirmed finding before repairing.
A current QA FAIL can invalidate an older technical accept; it does not require QA to rewrite the review first.
Preserve QA finding IDs and record dispositions in implementation evidence. Reproduce confirmed bugs and add the
required failing regression before repair where technically possible. Out-of-scope findings require a scope decision.

INCOMPLETE is a prerequisite/evidence handoff unless it also contains a confirmed defect; do not manufacture source
changes to fix missing browser access or unavailable test data. After material repairs, run the applicable gates,
obtain independent technical review and repeat affected QA. The implementer neither edits QA verdicts nor marks its
own repaired behavior independently passed. Mechanical changes may reuse evidence only through the existing bridge.

## Verification and evidence

Use focused owning checks while iterating. Run the target's complete local gate (`./bin/build` here) once on
the finished, frozen implementation, then apply [gate evidence reuse](#gate-evidence-reuse) across later roles
and administrative updates. Hosted CI is optional unless explicitly required by the target's accepted
repository/TASK policy; follow the [verification policy](REVIEW.md#local-gate-and-optional-hosted-checks).
Do not create a publication prerequisite from a workflow file or repository visibility alone. Also inspect the final diff and status and run `git diff --check`. Refresh generated planning views before their read-only checks when the TASK authorizes planning edits.

Evidence names the approved scope, changed files, commands, fresh exit results, and test counts. Report all warnings, notices, deprecations, skips, unavailable checks, failures, and uncertainty. Historical or inherited receipts remain labeled as context. Keep secrets and credentials out of commands, logs, fixtures, evidence, and commits.

### Gate evidence reuse

Before running a complete gate, inspect the existing receipt and the actual intervening changes. Work, Review,
QA, Build and Land reuse a qualifying pass rather than rerunning the suite for a new role, changed commit OID,
commit operation, PR locator or completion metadata. Reuse is evidence assessment, not a cache or acceptance verdict.

- Bind the receipt to its repository, exact tested head/tree or before/after source snapshots, index, unignored
  file contents and executable modes. Verify command, complete phase exits, source stability, owning reports and
  retained artifact digests. Missing, failed, interrupted or contradictory evidence is not a pass; never fall
  back to an older green report after a relevant failure.
- Compare actual source and inputs, not Git status alone: implementation, tests, executable instructions,
  requirements, tool/runtime configuration, manifests/locks, installed dependency receipts, image/tool versions,
  environment identity (without disclosing secrets), database isolation and required modes. Check for live
  writers, replaced installs, runtime drift and interacting base changes. Unknown identity holds reuse until
  resolved. Keep a concise provenance bridge and evidence locators in the existing ignored handoff/report.
- Substantive changes invalidate affected evidence. Run focused owning checks during repair, then one complete
  final gate for the changed implementation. An advancing base needs its actual interaction assessment; an
  unchanged patch is not proof of unchanged inputs. Independent review and affected behavioral QA remain
  required for substantive changes; unaffected scenario evidence may remain applicable with provenance.
- Administrative-only changes are truthful completion/review/PR/status metadata and their generated planning
  views, not requirements, executable instructions, code, dependencies or tool configuration. Inspect the exact
  diff and provenance, run planning validation and `git diff --check`, and perform applicable targeted live
  delivery checks. These changes alone do not trigger a full gate or new independent acceptance. Keep the
  original gate subject accurate, including when that subject was a dirty precommit snapshot.
- Required hosted or literal-final-head acceptance/delivery contracts still bind. Reuse does not waive them or
  turn an implementation-candidate run into final-head proof; obtain an authorized requirement amendment when
  needed under the [closeout contract](LANDING.md#acceptance-candidate-and-administrative-closeout).

Each role records what it checked freshly, what it reused, why it remains applicable and what is unverified.
Review owns its independent verdict; QA must still exercise the changed behavior with appropriate adversarial
probes. Neither role repeats the complete suite merely to establish independence. A receipt with unknown inputs
requires input recovery or a fresh gate after setup, not an inferred pass or speculative implementation edits.

## Handoff

Before publication, update the TASK with completed implementation and verification while keeping review and merge state separate. The implementation handoff identifies branch and commit, acceptance covered, files changed, checks and counts, warnings, unresolved risks, unrelated work preserved, and the next authorized action.

Independent review must be performed by another reviewer. TASK `done` records accepted implementation and all
required acceptance verification, including hosted proof when required; it does not claim successful delivery,
PR publication, merge, deployment, or release. Required final-head delivery checks remain separate only under the
accepted [closeout contract](LANDING.md#acceptance-candidate-and-administrative-closeout); do not reinterpret an
existing literal-head acceptance requirement without an authorized owner amendment.

Whenever an authorized operation closes a TASK, apply [automatic parent completion](../../planning/CONVENTIONS.md#automatic-parent-completion)
in the same change. Run `./bin/planning-check --write` so eligible TICKETs and EPICs close and views refresh;
do not stop at “ready for closeout” or ask the user to run another skill. This does not grant work the independent acceptance needed to close a TASK.
