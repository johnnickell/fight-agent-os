# Define the coordinated TASK authority protocol

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md), [WF-012](WF-012-define-browser-planning-and-conversation-ownership.md), [WF-014](WF-014-define-skill-trust-and-harness-distribution.md)

## Question

What authoritative state machine and handoff contract lets a coordinator carry one approved TASK through work, independent review, bounded revision, and PR publication without expanding human-granted authority?

## Must decide

- Coordinator, worker, independent reviewer, and publisher responsibilities, identities, tool access, session separation, and independence requirements.
- Atomic eligibility check and claim using the canonical next-TASK query; lease/claim ownership, duplicate prevention, cancellation, retry, stale-claim recovery, and release.
- Commit-specific handoffs: approved TASK revision, base/head, changed tree, checks, artifacts, findings, verdict, limits, and preserved unrelated work.
- Revision loop policy: blocking versus non-blocking findings, dispositions, conflicting findings, unavailable evidence, implementation changes invalidating acceptance, retry/time/cost limits, and human escalation.
- Exact acceptance rule: reviewed implementation commit, every criterion evidenced, required checks passing, no unresolved blocker; acceptance is not a defect-free guarantee.
- Separate treatment of mechanical landing-record commits without allowing substantive changes to bypass re-review.
- Checkout/worktree ownership, feature branch/base policy, cleanup ownership, and protection of unrelated local work.
- Workflow-start grants for commit, push, and PR create/update; `land` remains publisher, never merger, and merge remains human-controlled.
- TASK done versus unmerged PR and prerequisite availability; compare waiting for merge with explicitly supported stacked PRs rather than assuming stacking.
- Scope for continuing to additional eligible TASKs, including count/time/cost limits and a visible stop/resume boundary.

## Resolution boundary

This decision defines application workflow policy and phase contracts. It must not start a coordinator, claim a TASK, create a branch/worktree, run agents, push, publish, or merge. Runner transport belongs to WF-016; event persistence belongs to WF-017.

## Preferences required

John must choose publication authority at workflow start, acceptable automated revision limits, whether any stacked-PR mode is worth supporting, and how far one coordinator may continue. The recommendation should start with one TASK per explicit grant, no stacking, commit-specific acceptance, and a hard return to human control after PR handoff.

## Resolution

### Managed profiles and authority

Harness provides six initial managed Agent profile templates with separate authenticated Agent identities, HMAC credentials, direct Permissions, tool sets, and Pi sessions:

- **Explorer** performs bounded research, prototypes, and disposable design exploration without Planning or delivery authority.
- **Planner** performs authorized Wayfinder and EPIC → TICKET → TASK planning operations without implementation or publication authority.
- **Coordinator** claims one approved TASK, validates the workflow grant and context, dispatches peers, validates handoffs and limits, pauses or escalates, and completes orchestration without editing implementation, issuing a review verdict, or publishing.
- **Builder** owns the isolated implementation worktree, implementation and tests, required checks, corrections, and implementation commits. It cannot independently accept or publish its work.
- **Reviewer** has read-only authority over the implementation and Planning records and write authority only for review-owned reports, immutable review history, and append-only annotations. It never repairs the reviewed target.
- **Publisher** consumes a current independent acceptance, performs the bounded landing operations, records completion and delivery facts, pushes without force, creates or updates the PR, and cleans ownership-proven resources. It never approves or merges.

Explorer and Planner are part of the initial managed catalog but are not peers in the coordinated implementation state machine. Coordinator, Builder, Reviewer, and Publisher always use distinct Agent identities and isolated sessions. No session changes roles or inherits the Coordinator's credential or authority. Reviewer independence requires that its Agent and session did not contribute to the implementation or its acceptance evidence. A later re-review may use the same managed Reviewer identity in a fresh isolated session, provided independence still holds.

Browser and terminal entry points invoke the same server-authoritative application workflow. A terminal Coordinator may eventually dispatch separate local Pi subprocesses, but they remain authenticated peer Agent sessions with their own profiles, Harness and repository-context snapshots, and permission-filtered tools—not authority-inheriting local subagents. WF-016 owns subprocess, runner, and transport mechanics.

### Single-TASK workflow and claim

The MVP coordinates exactly one approved TASK to one PR per explicit workflow grant. Its logical path is:

```text
Requested → Claimed → Implementing → Awaiting review
  → Revising → Awaiting review
  → Accepted → Publishing → PR handed off → Stopped
```

Paused, Needs Human, Cancelling, Cancelled, and Failed are explicit side paths. These names define policy, not WF-017's eventual event schema. The MVP does not scan for, claim, or continue to another TASK after PR handoff. A future pipeline or Roadmap workflow may call the canonical next-executable-TASK query and invoke one or more single-TASK workflows, but that is outside this decision and the MVP.

Workflow start atomically reruns the WF-010 canonical eligibility query and claims the selected TASK against its current Planning revision. One active workflow claim may own a TASK. Idempotent repetition of the same start command returns that workflow; a competing command fails with the owning workflow and an understandable exclusion reason. Claiming records the repository and TASK identities, expected Planning revision, configured base, grant, initiating user when present, Coordinator Agent, context and Harness snapshots, time and cost budgets, and revision-cycle allowance.

The TASK claim is durable and is not a process lease. A renewable Coordinator lease establishes who may advance the workflow. Lease expiry pauses dispatch and permits an authorized Coordinator to recover the same workflow; it never releases the TASK or creates a second attempt. Recovery preserves the grant, attempt history, sessions, commits, artifacts, findings, and consumed revision allowance. WF-016 decides lease timing and takeover mechanics.

Stop requests are durable cancellation requests. The Coordinator stops or accounts for every active peer operation before cancellation may release the claim. If quiescence cannot be established, the workflow remains Cancelling or Needs Human and the TASK remains claimed. A human may cancel or release a proved-inactive claim with a reason. Retry reuses idempotent phase commands and the same workflow; an uncertain command outcome must be resolved before another effect is attempted.

### Checkout, branch, and handoff contract

A coordinated implementation uses one workflow-owned isolated worktree and a `feature/<description>` branch from the repository's configured base, initially `develop` where applicable. It never repurposes or cleans a human-owned checkout. The Builder owns implementation writes; the Reviewer preserves the target; the Publisher removes the isolated worktree only after successful publication and only when its ownership, cleanliness, and published commit are proved. Unrelated work is always excluded and preserved.

Every phase handoff is durable and identifies the workflow, TASK and revision, repository and checkout identities, role and session, immutable context and Harness snapshots, configured base, full base and head commit IDs, branch, complete status and changed-tree manifest, selected ignored artifacts and digests, commands and checks with fresh results, warnings and limitations, and the next role's bounded authority. Review handoffs additionally carry ordered findings, criterion dispositions, evidence references, and the exact verdict. Publisher handoffs distinguish the independently accepted implementation commit from later mechanical publication commits and the final remote head.

A branch name, latest branch head, PR, chat transcript, or process-local result is never sufficient handoff identity. A consumer rereads authoritative state and fails closed on stale, partial, ambiguous, mismatched, or superseded input. This requirement does not prohibit the proportionate repair operations below.

### Review and bounded revision

Only persisted blocking findings authorize automatic implementation revision. Non-blocking findings are recorded for human judgment and do not consume or trigger a revision cycle. One cycle means the Builder addresses the current blocking findings, records their dispositions, runs the required checks, and creates a new implementation commit, after which an independent Reviewer evaluates that exact new snapshot.

A workflow defaults to three revision cycles. Terminal and browser start surfaces expose one optional positive per-workflow override, such as a future `--revision-cycles 5` option. The installation supplies a server-side maximum through environment configuration, initially defaulting to ten; clients cannot exceed the effective ceiling. The start confirmation and workflow history show the default, requested, effective, consumed, and remaining values. Time and cost budgets independently bound work.

Reaching any cap causes a hard stop in Needs Human. Continuation requires a new human grant after examining findings, instructions, TASK scope, and evidence; limits never extend automatically. Escalate before exhaustion for scope expansion, contradictory or ambiguous blocking findings, unavailable required evidence, unsafe repository state, changed authority or context, an exception request, or inability to preserve unrelated work.

### Commit-specific acceptance

Independent acceptance applies to one immutable reviewed snapshot: TASK and revision, repository, base and implementation head commits, changed-tree and status manifest, selected ignored artifacts and digests, checks, criteria, findings, and evidence. Acceptance requires every criterion to be satisfied or validly waived, every required check to pass, sufficient evidence, and no unresolved blocking finding. It is not a defect-free guarantee, human PR approval, merge, release, deployment, or certification.

A material change to that subject—including implementation, tests, configuration, reviewed artifact bytes, amend, rebase, or conflict resolution—invalidates acceptance and requires another independent review. A later-discovered fact invalidates a current acceptance only when it establishes an unresolved blocker against the accepted subject. A floating branch or PR cannot retain acceptance after its reviewed snapshot changes.

The Publisher may append narrowly mechanical landing commits without another model review. The allowed envelope includes TASK completion and evidence facts, generated Planning views, PR URL and publication state, and equivalent delivery bookkeeping performed through allowlisted semantic operations with deterministic verification and a complete manifest. It cannot change requirements, implementation, tests, configuration, permission or authority, reviewed artifacts, or the substance of an acceptance claim. Any material or ambiguous change leaves the accepted implementation commit intact but returns the workflow to review or human judgment.

### Proportionate corrections and repair

Immutability preserves history; it does not turn record correction into a new model run. Historical review reports remain byte-preserved, while authorized actors may append attributed annotations that record a correction, later-discovered omission, supersession, or link to a newer finding. An annotation never silently rewrites the original verdict. If a missed finding undermines the acceptance currently being relied upon, the Coordinator invalidates that acceptance and resumes the bounded revision path; annotating a historical or already-superseded review requires no re-review.

The Coordinator and Publisher may repair incomplete or inaccurate workflow bookkeeping within the existing grant: idempotent command recovery, generated-view refreshes, completion and publication facts, evidence references, links, provenance, annotations, and harmless narrative or typographical corrections that do not alter the approved TASK contract. Repairs are attributed, revision-checked, and proportionately verified. A changed evidence record alone does not invalidate implementation acceptance; the accepted subject or the truth supporting its verdict must materially change. Uncertainty returns the specific repair to a human instead of automatically spending an entire review run.

### Publication, dependencies, and return to human control

The explicit start grant authorizes the named workflow to claim, implement, commit, independently review, perform allowed revisions, finalize Planning, push its feature branch without force, create or update its PR against the configured base, and clean exact ownership-proven workflow resources. The confirmation names the TASK, repository, base, profiles, revision allowance, and publication effects. Live permission revocation, material context drift, or a changed grant stops later authority-bearing operations.

The grant never authorizes force-push, PR approval, merge, remote-branch deletion, unrelated cleanup, archive, release, deployment, or certification. After verifying the open PR's base, head, and remote commit, the workflow hands the canonical PR URL and retained human actions to the user and stops.

MVP coordination does not support stacked PRs. Planning `done`, review acceptance, publication, and merge remain distinct. A dependent TASK whose blocker is Done but whose accepted change is not verified on the dependent TASK's required base is excluded by prerequisite availability with an explicit reason. Independent TASKs remain eligible. A trusted provider refresh that confirms merge projects the delivered TASK into the Kanban Complete lane and recomputes direct downstream dependents; eligible dependents then project into Ready without another Planning status mutation.

Post-merge reconciliation may fetch and prune an explicitly enrolled main worktree and switch it to its configured base only when it is clean, idle, unreserved, and not preserving unrelated work. Base update is fast-forward-only. It may remove only the merged workflow's local branch after proving merge, remote state, and absence from every worktree. Other gone branches are reported rather than force-deleted. Merge and eligibility refresh still complete when checkout maintenance must remain pending. WF-016 owns the executing process and recovery; WF-017 owns authoritative workflow events, provider observations, and projections.

This decision creates no running workflow, claim, Agent, credential, branch, worktree, commit, push, PR, merge, schema, event table, environment setting, or production implementation. Exact Permissions, command and event names, lease durations, model assignments, budget values, runner topology, provider-refresh transport, and UI controls remain downstream implementation choices within these boundaries.
