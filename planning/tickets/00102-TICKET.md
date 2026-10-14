---
id: TICKET-00102
epic: EPIC-00009
title: Publish the confirmed initial project to GitHub
status: ready-for-agent
---

# Publish the confirmed initial project to GitHub

## Problem statement

A human may want the new project's initial commit published to GitHub, but that choice must not authorize arbitrary
pushes or expose source-host credentials to generated code or Agents. Creation, push and default-branch effects
may succeed separately and need truthful recovery.

## Solution and boundaries

Supply the optional GitHub stage of TICKET-00101's exact confirmed creation plan. Use the existing authenticated
`gh` identity without collecting or displaying its token; verify current identity and authority for the independently
selected personal or organization owner. Suggest the personal owner and project slug as repository name, but permit
other authorized owners/names. Default private; public requires the owning complete preview's heightened warning
and explicit acknowledgement. Bind description, owner, name, visibility, selected initial branch and exact initial
commit to that confirmation. No extra ordinary-stage prompt or inferred consent from a previous attempt.

Expose bounded read-only preflight for identity, owner authority, repository-name availability, visibility/policy
and connectivity. Respect organization policy, SAML, scopes, Permissions, naming collisions and source-host outages.
Unknown availability is not proof that a name is free. No automatic login, key creation, organization enrollment or
credential repair occurs; direct the human to existing versioned operator guidance.

Create only the exact confirmed repository, establish its attributable provider-native identity, configure the
new local repository's intended remote, push the generated initial commit and establish/verify the selected remote
default branch. Prefer configured SSH Git transport; an explicitly configured supported transport must preserve the
same credential and target boundaries. Verify actual local/remote identity, visibility, branch/ref and commit
outcomes, not just CLI exit. Do not stage or commit preparation changes or unrelated files; publication of the
initial subject remains distinct from observed post-preparation worktree state. Never force-push or overwrite
unrelated local/remote state to satisfy an attempt.

Keep source-host authority in its protected operation owner, not the preparation process, browser/support Agent,
Pi coding child or generated project. Human-authorized project publication is distinct from EPIC-00008 Release
Manager authority and grants no standing implementation/push/PR access to any Agent. Reuse qualified Git/provider
adapters when their contracts fit, without depending on completed managed Review or release workflows.

Record original intent/result evidence for remote creation, local remote setup, push and default-branch setting
within the same creation attempt. Reconcile provider uncertainty and exact operation-owned resources before retry.
A matching name alone does not establish ownership or authorize adopting an existing repository. Create failure
retains local Git; push failure retains local and remote state; interrupted/unknown effects remain visible until
reconciled. No duplicate remote, commit, push effect or inferred success from disappeared processes. When current
policy permits, TICKET-00101 owns explicit continuation without publication, preserving remote state already created.

Cancellation stops future effects, not completed publication. No automatic remote/directory deletion or cleanup.
If TICKET-00101 admits separately confirmed cleanup, execute only the exact currently authorized ownership-checked
provider effect, verify its outcome and preserve safe history. Refuse uncertain, referenced or unrelated resources;
cleanup authority never comes from the original create confirmation alone.

Local-only creation does not invoke this stage and needs no GitHub publication identity or connectivity when its
installed source is already available. Exclude existing-repository adoption, non-GitHub hosting, credential enrollment,
arbitrary source-host administration, unrelated push, PR creation/approval/merge, releases/deployment and source
starter publication. This TICKET does not own catalog retrieval or private-source connection management.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect publication feasibility | N/A — read-only preflight | Safe authenticated identity, owner policy, name availability and transport capability | N/A — inspection publishes nothing | Exact preview facts or policy/connectivity failure |
| Create the confirmed repository | Execute the original admitted remote-creation stage | Current grant/target, original operation and provider outcome | Confirmed source-host creation facts at the attempt owner | One exact repository with the selected visibility/description |
| Publish the initial subject | Execute admitted remote setup/push/default-branch operations | Local identity, exact initial commit and actual remote refs/default | Verified publication-stage facts | Intended remote/ref/default branch only; no new local commit |
| Reconcile partial publication | Explicit original-operation reconciliation or safe retry | Provider-native identity, prior results, current authority and actual local/remote state | Confirmed recovery/hold facts | Preserve local/remote evidence and avoid duplicate or unrelated effects |
| Clean an owned remote when separately authorized | Exact confirmed provider cleanup operation | Current ownership, references, target and actual provider outcome | Verified cleanup or uncertainty fact | Only the separately approved cleanup; no automatic compensation |

## Validation and permissions

Check current human creation/publication authority, precise owner/target scope and actual source-host authority at
each admitted effect. Revalidate plan/currentness and ownership; public acknowledgement is specific to the exact
preview, not a reusable permission. Validate bounded names, description, branch/ref, remote URL/provider identity
and command arguments; reject credential-bearing URLs, injection, changed targets and unsupported transitions.

Use protected credentials only within the qualified adapter's invocation lifetime; never expose tokens, SSH keys,
passphrases, raw provider errors or credential-store contents through browser, logs, events or receipts. Required
state/audit/intent commits under ADR 0002 precede external Git/HTTP/CLI effects. Reconciliation uses actual provider
facilities; unavailable evidence remains unknown rather than an exactly-once promise or blind replay.

## Acceptance and evidence

- Real authorized GitHub creation is private by default; selected organization/name/branch and explicitly
  acknowledged public choice are enforced with independent names and safe source-host authority checks.
- Verify the exact initial commit, actual remote identity/visibility/default branch and secret-free receipt.
  Preparation changes and unrelated content are neither committed nor published implicitly.
- Collisions, SAML/scope/policy failures, revocation, outage, lost acknowledgement and partial push/default-branch
  results preserve local/remote resources and permit only reconciled safe retry or explicit continuation.
- Local-only creation succeeds without this stage. Agents and generated code receive no source-host credentials;
  cleanup never follows automatically from failure or cancellation.
- Owned adapter/authority/recovery tests, separately authorized real GitHub/Git evidence and `./bin/build` pass.
  Doubles prove fault behavior, not live support; do not test GitHub, `gh`, SSH or Git internals or publish fixtures
  to unrelated repositories. TICKET-00103 consumes the actual integrated creation receipt.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00418](../tasks/00418-TASK.md) | Inspect protected GitHub publication feasibility | needs-info |
| [TASK-00419](../tasks/00419-TASK.md) | Create and reconcile the exact confirmed repository | ready-for-agent |
| [TASK-00420](../tasks/00420-TASK.md) | Publish and verify the original initial commit | needs-info |
| [TASK-00421](../tasks/00421-TASK.md) | Inspect and clean separately authorized remote resources | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved four slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked features with blank PRs. Remote configuration, initial push and
default-branch establishment stay together as one publication outcome, with separate durable intents/results and
reconciliation for each effect. No recovery-only or proof-only TASK is introduced.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00418](../tasks/00418-TASK.md) | TASK-00117, TASK-00368; protected `gh` invocation/current-identity supplier unallocated | Early protected read-only publication feasibility before attempt/local repository creation |
| B | [TASK-00419](../tasks/00419-TASK.md) | TASK-00418, TASK-00412, TASK-00408 | Exact confirmed remote creation, attributable native identity and original-effect reconciliation |
| C | [TASK-00420](../tasks/00420-TASK.md) | TASK-00419; protected Git/SSH invocation supplier unallocated | Intended local remote, original-commit non-force push and verified selected default branch |
| D | [TASK-00421](../tasks/00421-TASK.md) | TASK-00419, TASK-00413 | Exact remote impact inspection and separately authorized ownership-checked cleanup |

TASK-00418/00420 remain `needs-info` for exact protected-operation suppliers under existing host/source-host
ownership. Add only approved supplier IDs later; enrollment facts, qualification-only TASK-00138, operator guidance
and Release-Manager-only TASK-00315 do not implement these human-operation boundaries. No fabricated Workflow,
Agent grant, second provisioner/vault or completed Team/Review journey fills them. TASK-00419/00421 are ready behind
unfinished/held suppliers, not runtime-qualified. TASK-00403's Workspace private-source connection gap is separate.

TASK-00411 gains TASK-00418 and now TASK-00422 for publication and registration/context preflight. TASK-00414 gains
TASK-00420 and now TASK-00425, with remote creation and registration/link transitively. Only allocation gaps resolve;
initial-mode, actual first-link/stage and protected-operation holds remain. TASK-00417 gains TASK-00421 but retains
actual reference/local-host/process/provider holds. TASK-00413 now gains TASK-00423's early reference participants
and becomes ready behind suppliers, not operationally qualified. TASK-00426 owns final receipt/integrated evidence.

TASK-00418 cannot depend on completed attempt admission. Publication effects consume TASK-00412 early contracts and
TASK-00408's verified initial subject; runtime admission also respects selected preparation disposition through the
single attempt owner. TASK-00421 depends on remote creation and early cleanup authority, not successful publication,
completed TASK-00417 or final receipt. Partial remote creation can therefore be cleaned without completing a push.
No root/identity/Runner/catalog/terminal producer gains a reverse publication dependency or priority change.

Use existing authenticated `gh` identity through its protected owner without login, enrollment or token collection.
Qualify actual Git/SSH principal separately; API identity alone is not push authority. Independent owner/name and
private-default visibility remain bound to the complete preview, with exact heightened public acknowledgement.
Current intent-specific rights, real local/provider identity, complete expected state and actual shared PostgreSQL
participants fence each operation. State/audit/original intent precedes external effects; verified matching outcomes
follow. Name similarity, CLI exit, process disappearance or ambiguous provider absence never prove ownership/success.

Qualify provider-native attribution, target enforcement and recovery rather than promise exactly-once effects or
infer provider guarantees from local locks. Unknown outcomes hold; no blind create/push/delete retry or adoption of
an existing repository. Initial publication never stages/commits preparation changes, force-pushes or overwrites
unrelated local/remote content. Cancellation retains effects; explicit continuation stays with TASK-00416. Cleanup
requires separate exact consent and durable reference/admission exclusion, and cannot delete registrations/history.

Local-only creation with permitted installed inputs invokes none of these GitHub operations and requires no
publication identity/connectivity. Optional preparation skip requires no preparation-network capability. Browser
form/progress/recovery/cleanup remain TASK-00415/00416/00417; this split adds no duplicate wizard. TICKET-00103 retains
registration/context, final receipt/Dashboard handoff and real integrated local-only/private-publication acceptance.

Each delivery includes owned behavior tests, real applicable PostgreSQL/Git/GitHub integration, useful versioned
operator guidance and `./bin/build`. Directly qualify tools/configuration rather than product-test GitHub, `gh`, SSH,
Git, wrappers or generated artifacts. Doubles establish fault behavior, not live credential/provider support. Actual
read/create/push/delete qualification requires separately scoped finite authority; this planning grants none.

This completes this TICKET's split only. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138, terminal-first order, independent holds and Markdown Planning authority.
Implementation checkout, source/private/account reads, credential effects, full build, review and publication remain
separate; no source release or remote operation is authorized by allocation.

### Planning verification

This historical publication-split receipt predates TICKET-00103's mappings; latest planning verification is in
TICKET-00109. Counts and then-unresolved allocations below describe the earlier point.

Recorded the approved four-slice split in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`, preserving prior uncommitted planning. Before allocation the highest live TASK was
TASK-00417 and the archive contained no TASK records. TASK-00418–00421 match approved statuses, exact known blockers
and template sections; all are unranked features with blank PRs. TASK-00418/00420 retain the protected-operation
mapping holds, while TASK-00419/00421 are ready behind unfinished/held suppliers. Unallocated host/preflight/
reference/stage edges are not claimed cycle-checked or runtime-qualified.

`./bin/planning-check --write` and `./bin/planning-check` passed with 548 records, 456 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 113 new/untracked TASK
whitespace checks passed. Explicit checks verified TASK-00411 → TASK-00418, TASK-00414 → TASK-00420 and TASK-00417 →
TASK-00421, resolving only their named allocation gaps. All three retain `needs-info` for the documented remaining
registration/context or actual reference/host/process/provider prerequisites. Earlier catalog/local/measurement/
forecast mappings and independent release/private-source/profile/current-authority/quota/prototype holds remain.

Existing terminal/root/identity/frame/Runner/catalog/profile/early-participant dependencies and priority were
checked. TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD; TASK-00138 remains
the sole active Board item, order 2. No early supplier gained a reverse publication dependency. That split advanced
handoffs to TICKET-00103, then TICKET-00104–00109, and no stale current TICKET-00102 command remains. TICKET-00103
records incoming native-source/publication/cleanup evidence without allocating its TASKs or completing its split.

Full `./bin/build` was not run and remains required before commit/publication. These are planning checks, not fresh
PostgreSQL application-participant, Runner, browser, source-release, credential, Git/SSH/GitHub or cleanup evidence.
All changes remain uncommitted. No live source/account/private-path or credential inspection, authentication,
provider request, remote creation, push, default-branch change, cleanup, live Planning mutation, cutover, commit,
push of this work, PR or merge occurred. Implementation, independent review and publication remain outstanding;
implementation checkout and finite operational authority stay separate. Unrelated main-checkout work was untouched
and is not claimed clean. Earlier planning receipts remain labeled historical.

### Historical incoming publication and cleanup allocation

The requests below predate the approved split above. TASK-00418/00419/00420/00421 resolve the named publication
allocations only; protected-operation and independent registration/reference/runtime holds remain.

TICKET-00101 now maps TASK-00411 complete server preview/admission, TASK-00412 original stage participants,
TASK-00413 separate cleanup authority, TASK-00414 actual stage composition, TASK-00415 browser creation,
TASK-00416 recovery/continuation/cancellation and TASK-00417 final cleanup integration.

During this TICKET's split, allocate exact early read-only publication feasibility for TASK-00411, independently
of completed attempt admission or publication effects. Allocate precise publication-stage adapters for TASK-00414,
consuming TASK-00412 and TASK-00408's initial subject, and the exact remote-cleanup adapter for TASK-00417 consuming
TASK-00413 authority. These suppliers must not depend on completed TASK-00414/00416/00417 or the final TICKET-00103
receipt/journey. Add only approved TASK IDs later; no supplier is allocated by this handoff.

Preserve protected `gh`/Git current identity/owner/target/transport authority and separate credential qualification.
No source-read enrollment, managed Release Manager or fabricated Workflow supplies this human operation. Optional
local-only creation requires no publication credential/connectivity; selected public effects bind exact warnings
and acknowledgement. Confirmation does not authorize remote cleanup, which remains separately scoped and
ownership/reference checked. Existing host/preparation/source-access holds remain; no provider operation is
performed or authorized by that incoming mapping. It selected this TICKET next; the current approved handoff is above.

### Incoming exact local Git subject

TICKET-00100 now maps TASK-00408's actual original branch/tree/initial commit, supplied after TASK-00407 generation
and before TASK-00409 optional preparation. Publish only that exact initial subject; visible preparation changes
remain dirty and cannot be staged, committed or silently included. TASK-00410 owns separately admitted local cleanup,
not this TICKET's provider cleanup. TICKET-00101 still owns confirmation/attempt transitions and separate cleanup
consent. Local-only creation consumes no publication identity, connection or effects.

TASK-00407's early stage/protected-host and TASK-00409's process/egress holds remain; no completed source-host or
managed Review journey reverse-blocks their early local capabilities. This handoff allocates no TASK for this
TICKET and grants no source-host credential, publication or cleanup authority.

### Original requirement approval

Approved as area 4 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

[TICKET-00101](00101-TICKET.md) owns plan/attempt/cleanup authorization; [TICKET-00100](00100-TICKET.md) supplies
the exact new local Git subject. Supply verified source identity to [TICKET-00103](00103-TICKET.md)'s registration
consumer without duplicating TICKET-00024 identity rules. Reuse early Runner/Git/provider capabilities, not final
managed role journeys, as dependencies. Existing source-access/operator owners remain; this planning approval
creates no TASK, credential access, authentication, remote, push, default-branch change or deletion authority.
