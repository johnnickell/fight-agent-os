---
id: TICKET-00100
epic: EPIC-00009
title: Generate and prepare a fresh local project
status: ready-for-agent
---

# Generate and prepare a fresh local project

## Problem statement

A confirmed project must become a predictable new local Git repository, not a copy of the starter's history,
ignored dependencies or credentials. Unsafe paths, mismatched transformations and failed preparation must preserve
truthful partial state instead of triggering broad replacement or AI repair.

## Solution and boundaries

Supply deterministic local stages to TICKET-00101's single durable creation attempt. Use only TICKET-00099's pinned
tracked archive and generation manifest. Materialize reusable source, instructions, tests, architecture and tooling
according to that contract, excluding starter `.git`, remotes, ignored dependencies, caches, local state, secrets
and generated credentials. Apply exact declared substitutions; validate every expected target/match and observed
result. Changed, absent, duplicate, unsafe or out-of-bounds targets stop before later effects. Never broad-search/
replace or ask a model to repair generation. Remove or neutralize source package identity only as declared; do not
ask for Composer vendor/package identity merely because Composer is present.

Keep human name, Workspace slug, parent path, local directory name, selected branch and remote repository name
independent. Operate only beneath configured Runner-accessible approved roots, including safe nested parents.
Canonicalize destination and ownership, prevent symlink escape and collisions, and refuse pre-existing or unrelated
content. Revalidate immediately before mutation; a prior successful preview is not a permanent path reservation.
An outside-root location needs TICKET-00023's explicit installation/mount operation, never an implicit browser fix.

Initialize a fresh Git repository without inherited commits or remotes. Suggest `develop` but create only the
selected initial branch, such as `main`, and exactly one initial commit over deterministic generated state.
Record actual commit/tree/branch evidence and distinguish deterministic content from incidental commit metadata;
recovery must reconcile the original commit rather than create another one. Local-only is a complete Git result
with no GitHub remote, not a no-Git fallback.

After the initial commit, optionally run only the exact manifest preparation command, selected by default and
previewed as potentially networked executable work. Use qualified Runner containment, bounded time/resources,
credential isolation, egress, process supervision, safe observations and redaction. The manifest cannot grant
network or secret access beyond confirmed and current policy. Missing capability holds instead of executing on
an unrestricted host. Preserve declared tracked preparation changes as observable dirty state; do not silently
commit, discard or include them in the initial-commit publication. Report the exact initial subject separately.

Preparation failure retains the valid generated Git repository for explicit reconciled retry or deliberate
completion where policy permits. Return exact stage receipts, current filesystem/Git/process observations, known
retained resources and safe failure reasons to the attempt owner. Uncertain process liveness/effects block another
launch; no success is inferred from exit alone or a missing process. Cancellation and cleanup are separately
controlled by TICKET-00101; this capability executes only its exact authorized, ownership-checked local effects.
Never automatically delete a directory or hide unrelated changes to make recovery appear successful.

Exclude catalog policy, another attempt state machine, GitHub publication, Repository registration, arbitrary
commands, AI-chosen paths/transformations, existing-content adoption, source releases and a coding Workflow/TASK
claim. Reuse qualified deterministic Runner primitives without requiring completed Team/Agent execution.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect generation feasibility | N/A — read-only validation | Exact snapshot/manifest, substitutions, canonical paths, collisions and capabilities | N/A — inspection changes no project | Safe facts for the complete preview |
| Generate tracked source | Execute the exact admitted generation stage | Original intent/result and current destination ownership | Verified stage facts recorded by the attempt owner | New owned files with exact manifest transformations |
| Initialize fresh Git | Execute the original Git-initialization stage | Current Git shape, branch, tree and prior commit evidence | Confirmed local Git stage facts | One selected branch and one initial commit, no inherited history/remotes |
| Prepare dependencies | Execute or explicitly retry the admitted manifest preparation stage | Current authority, command, capability, process and prior outcome | Confirmed preparation/partial-result facts | Bounded executable/network effects with visible tracked changes |
| Reconcile or clean local effects | Reconcile original operation; separately authorized precise cleanup | Actual files/Git/process identity, ownership and retained resources | Confirmed outcome/cleanup facts only | Resume safely or hold; delete only separately authorized owned resources |

## Validation and permissions

Require current initiating-human grant, exact immutable plan/stage identity, authenticated eligible Runner and
operation-specific local filesystem/Git/preparation authority. A queue delivery, profile or manifest is not consent.
Use shared root/resource fences where needed so another operation cannot replace a validated destination or run
conflicting preparation. Reject malformed names/branch inputs, command injection, unsafe archive paths/file types,
ambiguous ownership, stale plan or unexpected Git state. Bound all extraction, transformation, process and evidence
operations; secret-safe errors must not expose provider credentials or unrelated host contents.

Commit required stage intent before filesystem/process/Git effects and record verified outcomes separately under
ADR 0002. Do not keep database transactions open across these effects or claim exactly-once external execution.
On uncertain retry, inspect original effect ownership before deciding anything remains safe to repeat.

## Acceptance and evidence

- A real eligible installed starter generates exact declared content under an approved root, with independent
  names, no copied secret/history/remotes, one selected branch and one original initial commit.
- Traversal, symlink/root escape, pre-existing content, changed targets and concurrent destination collisions are
  refused without damaging unrelated work; incomplete owned stages remain attributable and recoverable.
- Actual bounded preparation reports success, failed/unknown process outcomes and tracked changes honestly.
  Retry never hides effects or duplicates commits; skipping preparation remains an explicit recorded choice.
- Owned generation/authorization/reconciliation tests and real Git/Runner integration support TICKET-00103's
  create/register acceptance. Run `./bin/build`; qualify infrastructure directly rather than test Git, package
  managers, frameworks, Compose, generated files or sandbox tooling as product behavior.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00406](../tasks/00406-TASK.md) | Inspect exact local-generation feasibility | ready-for-agent |
| [TASK-00407](../tasks/00407-TASK.md) | Materialize pinned source into one owned destination | needs-info |
| [TASK-00408](../tasks/00408-TASK.md) | Initialize and reconcile one fresh Git repository | ready-for-agent |
| [TASK-00409](../tasks/00409-TASK.md) | Run and recover the declared preparation command | needs-info |
| [TASK-00410](../tasks/00410-TASK.md) | Clean separately authorized local creation resources | needs-info |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved five slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked features with blank PRs. Four local-generation capabilities carry
their own reconciliation; the fifth executes separately authorized local cleanup, not deferred normal recovery.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00406](../tasks/00406-TASK.md) | TASK-00402, TASK-00073, TASK-00368 | Protected read-only exact input/path/Runner feasibility; no reservation or effects |
| B | [TASK-00407](../tasks/00407-TASK.md) | TASK-00406, TASK-00412; protected local-operation supplier unallocated | Exact pinned-source materialization, ownership and original-effect reconciliation |
| C | [TASK-00408](../tasks/00408-TASK.md) | TASK-00407 | Fresh selected branch and one reconciled initial commit, no inherited history/remotes |
| D | [TASK-00409](../tasks/00409-TASK.md) | TASK-00408; protected preparation supplier unallocated | Exact contained optional command, honest process outcomes and visible tracked changes |
| E | [TASK-00410](../tasks/00410-TASK.md) | TASK-00409, TASK-00413; actual reference composition and inherited host/process holds | Precise separately authorized local cleanup with ownership/quiescence/reference fences |

TASK-00406/00408 are ready behind unfinished suppliers, not operationally qualified. TASK-00407 remains `needs-info`
for the exact protected local-operation boundary under existing execution owners; TASK-00412 now supplies early
stage participants with TASK-00411 admission transitively. TASK-00409 retains `needs-info` for actual protected
process supervision, containment, dependency egress and credential isolation. TASK-00410 gains TASK-00413 cleanup
authority but retains `needs-info` for actual reference composition and inherited host/process holds.
Add only exact approved supplier IDs when allocated; no future blocker is invented or claimed cycle-checked.

TASK-00368 provides enrolled identity/capability facts, TASK-00073 approved-root access and TASK-00138 qualification,
not an implemented protected host or arbitrary deterministic execution authority. Reuse precise early participants
without fabricating a Workflow, TASK claim, Repository or Agent, moving a provisioner into creation, or requiring
completed Team/Agent/browser journeys. Existing terminal owners gain no reverse blocker or priority change.

TICKET-00101 retains full preview/confirmation, attempt identity/transitions, delivery and explicit recovery/cleanup
consent. Its early participants may consume TASK-00406/00402 first, but must not wait for completed effect adapters
or final browser integration. TASK-00404 now gains TASK-00411 and becomes ready behind suppliers, resolving only
its early attempt/reference allocation hold. Ordinary stage
reconciliation stays in TASK-00407/00408/00409; final cleanup cannot become their reverse prerequisite.

Generation consumes TASK-00402's immutable bytes/shared manifest and rechecks targets/matches/values independently.
Its real managed-release prerequisite remains; actual generation evidence belongs within TASK-00407, while
TICKET-00103 retains integrated create/register acceptance. Source release is a separately authorized maintainer
action. No inherited credentials/history/remotes, broad replacement, model repair or source-defined grants.

One initial commit precedes optional preparation. TICKET-00102 consumes TASK-00408's exact initial subject, never
silently committed preparation changes; TICKET-00103 consumes the identified checkout and stage provenance.
Explicitly skipping preparation requires no preparation network/credential capability. Public installed/local-only
creation does not require private-source access, GitHub publication or completed managed roles. TASK-00410's full
local-resource coverage does not require running preparation/cleanup before normal completion.

Owned behavior tests, real PostgreSQL/Git/Runner/source integration, direct infrastructure qualification, guidance
and `./bin/build` stay within each delivery. No product tests of generated layouts, tools, configuration or wrappers,
and no proof-only TASKs. Current rights, complete expected state, real resource/reference fences and original-effect
reconciliation remain mandatory; unknown state preserves resources rather than infers success or safe deletion.

This completes this TICKET's split, not delivery or the rest of the map. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138, terminal-first priorities,
independent holds and Markdown Planning authority until separately verified cutover. Implementation checkout,
finite source/host/credential/destructive effects, full build, review and publication remain separate.

### Planning verification

This historical local-stage receipt predates TICKET-00101's attempt split and supplier mappings; latest planning
verification is in TICKET-00109. The original counts and unresolved allocations below describe that earlier point.

Recorded the approved five-slice split in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`, preserving prior uncommitted planning. Before allocation the highest live TASK was
TASK-00405 and the archive contained no TASK records. TASK-00406–00410 match the approved metadata, known blockers
and all template sections; all are unranked features with blank PRs. A/C are ready behind unfinished suppliers and
B/D/E retain their explicit early stage/host, preparation and cleanup participant gaps. Unallocated future supplier
edges are not claimed verified; mapping alone will not establish operational qualification.

`./bin/planning-check --write` and `./bin/planning-check` passed with 537 records, 445 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 102 new/untracked TASK
whitespace checks passed, including these five. Explicit checks confirmed preserved root/identity/Runner/catalog
and terminal dependencies/priority, independent source-release/private-access/attempt-reference, profile/Triage,
host/prototype/provider/current-authority and quota holds, and prior TASK-00387/00320 measurement/forecast mappings.
TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD; TASK-00138 remains the sole
active Board item, order 2. No existing supplier gains a reverse creation blocker.

That split advanced handoffs to TICKET-00101, then TICKET-00102–00109; no stale current TICKET-00100 command remained.
Incoming stage/initial-subject/cleanup coordination is recorded in TICKET-00101/00102/00103 without allocating their
TASKs or completing their splits. Existing catalog and terminal ownership remains separate. Earlier receipts are
historical, not fresh source-release, filesystem, Git, Runner, PostgreSQL-stage or browser evidence.

Full `./bin/build` was not run and remains required before commit/publication. All planning changes remain
uncommitted; implementation, independent review, live qualification and publication are outstanding. No external
source/private-path/credential access, project generation, Git initialization/commit, preparation/process operation,
cleanup, live Planning mutation, cutover, commit, push, PR or merge occurred. Unrelated main-checkout work was
untouched and is not claimed clean. Implementation checkout and finite operational authority remain separate.

### Incoming catalog supplier handoff before this split

TICKET-00099 now maps TASK-00401's catalog, TASK-00402's immutable snapshot/shared manifest/early pin-reference
participants, TASK-00403's optional protected private-source route, TASK-00404's actual lifecycle integration and
TASK-00405's exact existing-template recommendations. Generation consumes pinned bytes and independently validates
selected substitutions/targets/results; it must not duplicate the manifest schema, fetch another release or execute
preparation during inspection. Public/local delivery does not require the private-source route.

TASK-00402 retains its actual managed stable-release prerequisite; TASK-00403 its protected credential/access
supplier gap; TASK-00404 the early attempt/reference mapping requested from TICKET-00101. Early stage/admission
primitives use TASK-00402's shared fences, not completed lifecycle/browser acceptance. Preserve existing Runner,
root, profile and current-authority holds. This incoming mapping creates no local effects or implementation TASKs
for this TICKET at that point. The approved split above now supersedes that pending handoff; TICKET-00101–00109
remain separate approved requirements awaiting decomposition.

### Original requirement approval

Approved as area 2 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume [TICKET-00099](00099-TICKET.md)'s manifest/snapshot and precise approved-root/Runner capabilities from
TICKET-00023/00032/00093. Those owners do not gain a new dependency on project creation or lose existing holds.
[TICKET-00101](00101-TICKET.md) owns authorization/stage sequencing/recovery; define its admission/result contract
early without whole-TICKET cycles. [TICKET-00102](00102-TICKET.md) consumes the exact initial commit and
[TICKET-00103](00103-TICKET.md) consumes the identified local checkout. No TASK or live directory, Git commit,
preparation command, cleanup or Runner activation is authorized through this planning approval.
