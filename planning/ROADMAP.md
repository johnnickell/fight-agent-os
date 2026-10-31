# Roadmap

The Roadmap owns delivery strategy; the [TASK Board](tasks/BOARD.md) owns executable priority and active work.
EPICs, TICKETs and TASKs retain their accepted requirements, dependencies and evidence. The
[capability inventory](FOUNDATION.md) distinguishes components from complete journeys. Markdown remains
Planning authority until an explicitly approved, verified cutover.

## Approved blended delivery strategy

After the planning audit, John approved a blended approach rather than requiring terminal delivery before browser
journeys. This reconciliation follows the planning merges available in local `develop` at `a0fede1`, including
[operational observability](epics/00019-EPIC.md) and the [local terminal workspace](tickets/00110-TICKET.md).
It supersedes the global terminal-first ordering, not the accepted terminal outcome or its safety contracts.

Interleave terminal, browser and shared-foundation delivery where real dependencies and useful outcomes justify
it. Browser capabilities may precede or accompany managed execution; terminal capabilities need not wait for a
complete browser product or database Planning cutover merely because they belong to the same application.
Neither interface has blanket precedence. Both consume the same PHP-owned application operations and authority.

- Deliver named user/team outcomes or remove concrete delivery blockers, not infrastructure demonstrations.
- Implement and qualify shared suppliers before their consumers. Do not make an early supplier wait for a
  completed downstream browser, Agent or observability journey just to demonstrate its use.
- Preserve actual identity, current permissions, enrollment, immutable context/Harness inputs, durable effects,
  credential isolation, resource limits and recovery prerequisites. An allocated supplier is not a qualified one.
- Keep optional assistance, later Triage and broader managed-team features from becoming incidental prerequisites
  of earlier outcomes. Resolve any conflicting owner contracts explicitly; this strategy does not remove edges.
- Use existing TASK metadata for execution selection. This amendment changes no statuses, orders or dependencies,
  starts no second TASK and does not make a blocked TASK executable. Any reprioritization belongs in the owning
  records through an authorized planning update, not a competing queue in this document.

### Capability milestones

These are useful delivery outcomes, **not serial phases or a second backlog**. Shared prerequisites determine
which slices can proceed together; each owning record supplies acceptance and remaining scope.

| Outcome | Existing owners | Delivery boundary |
|---|---|---|
| Humans can access and operate the application | [Web foundation](epics/00003-EPIC.md), [authenticated shell](epics/00004-EPIC.md), [local installation](tickets/00023-TICKET.md) | Complete invitation/authentication and current-authority journeys with qualified operation; a React shell or identity component alone is not this milestone. |
| A repository has usable, authoritative Planning context | [Registered Planning](epics/00005-EPIC.md), [Harness and browser Planning Agents](epics/00006-EPIC.md) | Registration, context and versioned resources feed their real consumers. Deterministic Planning and protected Agent assistance retain separate prerequisites. Markdown-to-database cutover remains an explicit operation. |
| Human-operated Pi work becomes safer and easier to inspect | [Harness protection](epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07), [terminal workspace](tickets/00110-TICKET.md) | Deliver bounded local controls, screening and the independent local activity/log pane without claiming managed enrollment, isolation or full observability. |
| One approved TASK reaches durable Awaiting review | [Execution](epics/00007-EPIC.md), [startup and recovery](tickets/00032-TICKET.md), [managed team](epics/00014-EPIC.md) | Authorized start/inspect, recoverable preparation, distinct Lead/Engineer sessions, protected evidence and parallel/recovery qualification. This remains the first managed implementation proof, not a requirement to finish before browser work. |
| Reviewed work reaches a human PR handoff | [Review and delivery](epics/00008-EPIC.md) | Extend the same Workflow through independent Senior Engineer review, QA and Release Manager landing. Local skills do not prove managed role delivery; release/deployment remain separately scoped. |
| Operators can diagnose live and historical failures | [Operational observability](epics/00019-EPIC.md) | Deliver shared early diagnostic/runtime suppliers incrementally; complete native authorized Logs, verified archives, recovery and composed workload qualification before claiming the full outcome. Do not gate the independent local terminal log pane on this journey. |
| Humans can create projects and maintain instructions safely | [Project creation](epics/00009-EPIC.md), [instruction editing](epics/00010-EPIC.md) | Follow their own registration, physical custody, conditional mutation and publication dependencies; direct human operations do not acquire mandatory AI or coding-Workflow prerequisites. |

Memory, Triage/hotfix routing, project-specific releases, email change, Feature Flags, template authoring and the
AccessControl reference-consumer handoff remain accepted destinations in the EPIC table below. They proceed
through their own records; blending delivery neither cancels them nor makes their complete journeys blanket gates
for the milestones above. Personal daily workflows remain outside this software-factory project.

### Decisions and qualification still required

The audit exposed shared contract gaps, not permission to weaken them. The following is a source-linked risk
summary, not a complete list of holds or an instruction to implement these records out of Board order.

| Boundary | Owning records and next required evidence/decision |
|---|---|
| Agent creation, Team binding and initial authority | [TASK-00253](tasks/00253-TASK.md), [TASK-00258](tasks/00258-TASK.md): accept and qualify supported package/application composition; sequential calls or nested transactions do not establish the required guarantees. |
| Initial templates and later Triage input | [TASK-00299](tasks/00299-TASK.md), [Triage owner](tasks/00200-TASK.md): reconcile initial-catalog readiness with independently supplied Triage content. The existing hold remains until its owner contract is amended or satisfied. |
| Protected host, broker and real session lifecycle | [TASK-00350](tasks/00350-TASK.md), [delegation](tasks/00264-TASK.md): map reusable operational suppliers and prove authority, contribution and fresh-session enforcement without a circular dependency on completed managed delegation. |
| New-repository database authority | [TASK-00422](tasks/00422-TASK.md), [TASK-00425](tasks/00425-TASK.md), [Planning authority owner](tickets/00028-TICKET.md): allocate and qualify fresh initialization; existing migration/post-cutover consumers are not that supplier. |
| Human filesystem/Git effects and scheduling | [Save/Delete](tasks/00432-TASK.md), [fast-forward](tasks/00442-TASK.md), [scheduled synchronization](tasks/00443-TASK.md): settle protected transport, physical exclusion/conditional effects and actual current actor/delegation. Metadata fences alone do not prove filesystem isolation. |
| Recovery and operational qualification | [Real cutover](tasks/00104-TASK.md), [Agent restoration](tasks/00227-TASK.md), [observability qualification](tasks/00487-TASK.md): retain each owner's actual restore, independent-witness, resource and failure evidence. Restart persistence or a small synthetic probe does not certify recovery. |

Missing contracts need an owner decision or supported upstream capability; missing enrollment needs the named
human action; missing runtime evidence needs qualification of the accepted contract. Keep these distinctions
visible instead of treating every `needs-info` record as ordinary implementation work. Do not reopen closed
Wayfinder decisions merely because their downstream delivery remains unimplemented.

## Current planning handoff

**TASK-00546 repair cycle 3 — current:** [LAND-01 repair](tasks/00546-TASK.md#repair-cycle-3--land-01-2026-10-31)
reopens the actual published-state plan crash on original string creation evidence. Regression-first local repair
passes 32 whole-plan cases and adjacent owning checks. The single new 24-phase gate passed
(`20261031T060343Z-30852`), not READY; canonical Work handoff owns the supported final-source observation/current
proof and exact holds. SAME receipt/runtime, original holds, accepted-A history and PR #186 draft remain
protected. Renewed independent Review and affected QA are next after the committed Work handoff; no Work
publication or cleanup.

**TASK-00546 accepted implementation checkpoint — historical:** [TASK-00546](tasks/00546-TASK.md#accepted-implementation-checkpoint--2026-10-31) has independent Review ACCEPT and nonvisual QA PASS on candidate `6c47704`. Publication and cleanup were pending at this checkpoint; current results are in the canonical ignored TASK Land handoff. Human review/merge remains separate.

**TASK-00546 repair cycle 2 — historical checkpoint:** [R3/R4/R5 repair](tasks/00546-TASK.md#repair-cycle-2--r3r4r5-2026-10-31)
restores shared storage-effect prerequisites, original protected-image policy and truthful cache-inventory outcomes.
Focused owning checks and the new 24-phase final gate pass (`20261031T034831Z-22913`). Gate PASS is not
upstream READY: canonical Work handoff owns current-source observation status, external proof and affected holds.
SAME runtime/receipt/history and existing legacy holds are protected. Renewed independent Review/nonvisual QA
follow the committed Work handoff; no acceptance, retirement or publication. Allowance: 5 effective / 2 consumed /
3 remaining. Canonical ignored Work handoff owns current proof and exact dispositions.

**TASK-00546 integration — historical checkpoint:** [standing Land readiness](tasks/00546-TASK.md#integration-and-standing-land-readiness--2026-10-31)
combines accepted TASK-00547's lifecycle with recoverable storage and executable preservation/capability checks.
Original runtime/receipt/history are reused; the final combined-source 24-phase gate passed
(`20261031T023423Z-15117`). External preservation and bounded real cleanup/resume are qualified; original
capability/ACL and historical resource holds remain explicit. Independent Review/QA remain pending.
Canonical ignored Work handoff owns current proof/affected holds;
allowance stays 5 effective / 1 consumed / 4 remaining, without another routine disposal-approval step.

**TASK-00546 repair cycle 1 — historical checkpoint:** [R1/R2 owning repair](tasks/00546-TASK.md#repair-cycle-1--r1r2-2026-10-29)
passes focused endpoint-alias/effective-Compose-input regressions, but its one final changed-source gate failed in
`ActivationApiTest::testRateBoundary` (429 expected, 500 received). Work verification is incomplete; coordinator
intake owns the out-of-scope gate failure. Renewed independent Review and nonvisual QA remain pending. Scope/order/dependencies and
predecessor protections are unchanged; shared allowance is 5 effective / 1 consumed / 4 remaining. Canonical
ignored Work handoff/resource companion retain current evidence. No independent acceptance or publication.

**TASK-00546 Work2 historical checkpoint — not current review readiness:**
[TASK-00546](tasks/00546-TASK.md#work2-implementation-checkpoint--2026-10-29) implements bounded recoverable
storage, host reserve/retention admission and same-helper disposable-runtime retirement. Focused direct
helper/runtime/Build-caller evidence is retained; the frozen-source 24-phase local gate passed with a complete
source/input/artifact bridge. Independent Review/QA are next from the committed Work handoff. Scope/order/dependencies and prior TASK limits are unchanged. No acceptance, publication,
fleet or physical-reclaim claim. Current evidence is canonical ignored `.runs/tasks/TASK-00546/runs/work-handoff.md`.

**TASK-00546 Work1 historical checkpoint — not current Work readiness:**
[TASK-00546](tasks/00546-TASK.md#current-work-checkpoint--incomplete-2026-10-29) has a partial executor visibility
repair with a 15-case controlled matrix and a bounded fresh worker probe, not full implementation acceptance.
Cache-to-source recoverability remains uncorroborated; the unqualified reclamation/admission draft was withdrawn.
Automatic reclamation, executable retention/reserve and lifecycle/caller work remain unfinished. Build owns
prerequisite recovery/Work continuation via canonical ignored `.runs/tasks/TASK-00546/runs/work-handoff.md`;
no final gate or independent stages have run. Historical ownership is still not required for positively
recoverable artifacts; scope and protections are unchanged. TASK-00545's accepted source is integrated and its
ownership hold is resolved. TASK-00543 depends on TASK-00546; TASK-00544 follows transitively. TASK-00542's
limits/evidence remain separate. No cleanup or physical-reclaim claim. The following closeouts are historical.

**TASK-00545 acceptance checkpoint — 2026-10-29:** [TASK-00545](tasks/00545-TASK.md#accepted-implementation-checkpoint--2026-10-29)
received independent Review ACCEPT and scoped nonvisual QA PASS for candidate `946949e9240b3b16db7bde7425d93da3589b0fb8`.
Publication and eligible cleanup are pending at this checkpoint; actual results and finite actions are in
canonical `.runs/tasks/TASK-00545/runs/land-handoff.md`. Acceptance is not merge or fleet qualification.
The TASK-00542 checkpoints below remain separate, preserved history.

**Accepted H closeout checkpoint — 2026-10-29:**
[TASK-00542](tasks/00542-TASK.md#accepted-h-closeout-checkpoint--2026-10-29) records independent Review11
ACCEPT and QA12 PASS; R10 is resolved. Done records implementation acceptance, not completed delivery.
Repaired publication/checks and eligible cleanup are pending at this frozen checkpoint; actual results and finite
next actions are in canonical ignored `.runs/tasks/TASK-00542/runs/primary-land16-handoff.md` and
[PR #181](https://github.com/johnnickell/fight-agent-os/pull/181). NEW repair6 resources remain obligations;
original retirements are never replayed, the old partial stays immutable. P1/P2/F1/current effects keep full
delivery INCOMPLETE; no all-disk-clean, foreign-preservation, physical-reclaim or integrated-fleet runtime PASS.
Shared allowance is 6/6 consumed, none remaining. John owns merge without a post-merge chore.
TASK-00543/00544 remain unstarted; generated eligibility does not waive cleanup-first effects. TASK-00545 is
separate. Earlier checkpoints below retain original facts and allowances, not current acceptance/readiness.

The following R10 repair paragraph is a preserved checkpoint, not current acceptance/readiness.

**Current repair — R10:** [TASK-00542](tasks/00542-TASK.md#current-repair--r10-directory-metadata) is reopened
for Review10's confirmed macOS directory-xattr defect. Same-helper native metadata-only/no-follow repair needs
fresh final verification, independent Review and affected QA. Sixth/final cycle is consumed (6/6; none remaining).
Fresh isolated verification is authorized with original provenance, never retired-runtime recreation; ignored
`.runs/tasks/TASK-00542/runs/repair-6/handoff.md` owns evidence/resources/finite later recovery. Real source,
private data and partial Land15 copy stay protected. P1/P2/F1 remain separate holds, no real apply/publication,
full delivery or foreign-preservation PASS. TASK-00543/00544 remain unstarted; TASK-00545 stays separate.
Earlier checkpoints below retain original facts/allowances, not current R10 acceptance/readiness.

**Accepted F closeout checkpoint — 2026-10-29:**
[TASK-00542](tasks/00542-TASK.md#accepted-f-closeout-checkpoint--2026-10-29) records independent Review9
ACCEPT and QA11 PASS on repaired F; R9 is resolved. Done records accepted implementation, not full delivery.
Repaired publication/checks and eligible primary cleanup are pending at this frozen checkpoint; actual results
and finite next actions are in canonical ignored `.runs/tasks/TASK-00542/runs/primary-land14-handoff.md` and
[PR #181](https://github.com/johnnickell/fight-agent-os/pull/181). P1/P2 holds and F1 strict preservation FAIL
remain, with 5/5 repairs exhausted. No all-disk-clean, integrated-fleet runtime PASS, future growth cap, queue
or post-merge chore. John owns merge. TASK-00543/00544 remain unstarted; generated eligibility does not waive
outstanding cleanup-first effects. TASK-00545 is separate future work, not a dependency.

The following R9 repair and D acceptance paragraphs are preserved checkpoints, not current stage instructions.

**Current repair — R9:** [TASK-00542](tasks/00542-TASK.md#current-repair--r9-excluded-link-readers) is reopened
for the confirmed excluded read-only LINK capability gap. Final cycle5 is consumed (5/5; none remaining).
New local verification, independent Review and affected QA are required; canonical ignored
`.runs/tasks/TASK-00542/runs/repair-5/handoff.md` owns repair evidence. P1/P2/F1 remain separate holds; no
real apply/publication, requirement expansion or TASK-00545 dependency. Prior D acceptance is historical.

**Current accepted candidate — 2026-10-29:** [TASK-00542](tasks/00542-TASK.md#current-accepted-candidate--2026-10-29)
records independent Review7 ACCEPT and QA10 PASS on repaired D; QA-02 is resolved. Done records accepted
implementation, not full delivery. Repaired publication/checks and eligible cleanup are pending at this frozen
checkpoint; actual results and finite consumer/authority holds are in canonical ignored
`.runs/tasks/TASK-00542/runs/primary-land12-handoff.md` and [PR #181](https://github.com/johnnickell/fight-agent-os/pull/181).
No all-disk-clean, integrated-fleet runtime PASS or future growth-cap claim. John owns merge without a delivery
queue or post-merge Board chore. TASK-00543/00544 remain unstarted; generated eligibility does not waive the
outstanding cleanup-first effects. TASK-00545 remains separate future work, not a dependency.

The revision and Review4/QA8 checkpoints immediately below are preserved history, not current stage instructions.

**Current revision:** [TASK-00542](tasks/00542-TASK.md#current-repair--qa-02) remains in-progress for canonical
QA9 QA-02. The original owned-root fixture is recovered through the repaired helper; final verification and
independent Review/affected QA are required. Earlier acceptance is historical. Real delivery resources and
primary runtime stay protected; TASK-00543/00544 stay unstarted
and blocked. No delivery queue or post-merge chore is introduced.

**Accepted implementation checkpoint — 2026-10-28:** [TASK-00542](tasks/00542-TASK.md#land-acceptance-checkpoint--2026-10-28)
has independent Review4 ACCEPT and QA8 PASS for candidate `90f6bdd83acb51c22793477398332f702c078116` under 1A/2A.
Primary publication and safe eligible cleanup are pending at this frozen checkpoint; actual delivery results and
remaining actions are in canonical ignored `.runs/tasks/TASK-00542/runs/primary-land10-handoff.md`. Acceptance
is not all-resource retirement, human merge or integrated-fleet PASS. No persistent merge queue or post-merge
Board chore is added. TASK-00543/00544 are unstarted; the Board's generated eligibility remains authoritative.

**Planning update — 2026-10-28 (historical checkpoint):** John approved standalone [TASK-00545](tasks/00545-TASK.md) for retained-checkout
report protection; implementation is unstarted and existing priority/dependency/active ownership is unchanged.
TASK-00542 retains its separate active session and standalone Docker case. PR #179 is verified merged at
`1ccba352f7bbf0bee09139719050d915cec7f2ba` and locally integrated; its base-integration prerequisite is satisfied
here. See [TASK-00540's reconciliation](tasks/00540-TASK.md#delivery-reconciliation--pr-179). The October 27
paragraph below is preserved as its planning checkpoint, not a current merge request or implementation-state
observation. This planning change does not repair or accept either bug.

The following original priority checkpoint preserves its then-current implementation/acceptance observations;
the sequence remains approved, while today's TASK-00542 acceptance is recorded above.

**Current approved priority — 2026-10-27:** John selected [TASK-00542](tasks/00542-TASK.md) ->
[TASK-00543](tasks/00543-TASK.md) -> [TASK-00544](tasks/00544-TASK.md), `order: 1`:
current active-repository disposable-storage cleanup qualification, exact local worktree testing runtimes,
then continuous installed workspace child/log following. Explicit blockers enforce the cleanup-first sequence;
TASK-00542 retains completed TASK-00540 as predecessor, with PR #179 integration required before implementation
starts on base. PR #179 is integrated; TASK-00542 Engineer work started October 28 in its selected isolated
checkout. Its updated scope records the eight-project inventory, authorized isolated secondary fixes/PRs
without extra TASKs, and complete removal of delivery-marker bookkeeping. Acceptance/integration remain
pending and later TASKs are unstarted.
TASK-00540 stays done with its existing reports/delivery provenance unchanged. TASK-00539 moves to `order: 2`;
all other orders/active ownership remain unchanged. Use the [Board](tasks/BOARD.md#current-human-priority--2026-10-27)
for actual eligibility. Secondary fixes must follow each owning project's instructions and verified default;
John retains every merge. Local skills do not deliver
managed TASK-00140 or browser execution authority.

**Previous priority checkpoint — 2026-10-26 (historical):** [TASK-00539](tasks/00539-TASK.md), `order: 1`, is the maintainer-approved
bounded invitation API throttle-clock repair. Its plan is included in TASK-00537's PR #171; implementation has
not started. TASK-00537 records accepted implementation as done, not merge or deployment; its current delivery
and cleanup result remains in the [Land checkpoint](tasks/00537-TASK.md#current-land-closeout--2026-10-26) and
durable receipt. Preserve other TASK priorities/dependencies and use the Board for actual active/ready routing.

[EPIC-00021](epics/00021-EPIC.md#approved-epic-and-remaining-planning-handoff) is approved/written and its
[existing-owner requirements are reconciled](wayfinder/research/private-production-owner-reconciliation.md).
Its [five-TICKET split](epics/00021-EPIC.md#approved-ticket-decomposition-and-current-handoff) is accepted/written as
TICKET-00121–00125. TICKET-00089's lifecycle/settings delta is accepted/written as TASK-00520/00521, and
TICKET-00121's artifact/CI split as TASK-00522/00523, and TICKET-00122's staging/operations/production split as
TASK-00524–00526. TICKET-00123's mail split is accepted as TASK-00527/00528, reusing TASK-00284's shared Resend
transport with its qualification holds retained. TICKET-00124's recovery split is accepted as TASK-00529/00530.
TICKET-00125's release split is accepted as TASK-00532/00533, completing this map's selected planning phases.
TASK-00531 is unrelated work allocated elsewhere. The lifecycle remains its existing owner's responsibility, not a
qualified supplier or deployment feature.
Generated child/frontier structure is not proof of semantic coverage, implementation acceptance or runtime
qualification. Use the accepted coverage/handoffs in the owning records:

| Scope | Current planning handoff |
|---|---|
| Land cleanup and delivery handoffs | [TASK-00534](tasks/00534-TASK.md) recorded accepted implementation candidate A, applicable QA PASS, a mechanical advancing-base bridge and fresh local verification in its [current Land closeout](tasks/00534-TASK.md#current-land-closeout--2026-10-25). Delivery, human review/merge and deployment remain separate; current delivery/cleanup results are in its durable TASK receipt. [EPIC-00020's completed decomposition](epics/00020-EPIC.md#completed-planning-and-approved-top-priority) remains accepted as TASK-00515–00519; its remaining TASK-00517–00519 follow at `order: 2`, with dependencies preserved. This supersedes the original EPIC top-priority selection. Read the Board for current eligibility. Planning completion is not implementation or rollout evidence. |
| Private first-production rollout, EPIC-00021 | [Approved map handoff](wayfinder/private-production-rollout-map.md#approved-first-production-planning-handoff): all eleven decisions are Closed and the sole new deployment EPIC is written. Existing provider/Planning requirements are reconciled and the five-TICKET split is accepted/written as TICKET-00121–00125. TICKET-00089's lifecycle/settings additions are accepted as TASK-00520/00521, with TASK-00344 consuming TASK-00520. TICKET-00121's CalVer production artifact/CI split is accepted as TASK-00522/00523. TICKET-00122's environment split is accepted as TASK-00524–00526. TICKET-00123's mail split is accepted as TASK-00527/00528; TASK-00524 now consumes TASK-00284's shared real transport. TICKET-00124's staging/managed-production recovery split is accepted as TASK-00529/00530. TICKET-00125's release split is accepted as TASK-00532/00533. All selected map planning phases are complete. [PR #161](https://github.com/johnnickell/fight-agent-os/pull/161) is the planning publication, pending human review/merge; see the [current publication handoff](wayfinder/private-production-rollout-map.md#current-planning-publication) for verification limits. `/skill:next` remains read-only Board-based execution selection. No implementation/spending authority or Board reprioritization; all actual qualification holds remain. Optional expansion and legacy retirement remain excluded. |
| Original vision, EPIC-00005–00010 | [Vision map frontier and coverage](wayfinder/complete-agent-os-vision-map.md#frontier): original planning phases complete; accepted requirements and unresolved supplier contracts remain with their records. |
| Local Pi workspace, TICKET-00110 | [Approved native Pi + Herdr decomposition](tickets/00110-TICKET.md#approved-herdr-native-workspace-and-task-decomposition--2026-10-23): TASK-00510 and TASK-00511 are done. [TASK-00512](tasks/00512-TASK.md#current-land-closeout--2026-10-25) adds verified worktree logs and final RPC-entry migration; its complete implementation has independent Review acceptance and current QA PASS. Its delivery is published in [PR #162](https://github.com/johnnickell/fight-agent-os/pull/162); final delivery evidence and human review/merge remain separate. Existing Board priorities, non-blocking TASK-00507, explicit runtime bindings and separate provisioning/full observability remain unchanged. |
| Operational observability, EPIC-00019 | [Completed map planning](epics/00019-EPIC.md#approved-installation-task-decomposition-and-completed-map-planning): seven TICKETs and thirty-seven TASKs, TASK-00451–00487; early suppliers are mapped, not runtime-qualified. |
| Visual Workflow policy editor | [TASK-00493](tasks/00493-TASK.md): bounded plan under TICKET-00097; exact supported stage graph and policy-management contracts still need resolution. No new Wayfinder map or priority change. |
| Other accepted destinations | [Wayfinder inventory](wayfinder/README.md) and each EPIC's own coverage/handoff retain their authority. Map closure and historical selected-slice approvals are not substitutes for current implementation evidence. |

For execution, read the Board's **Active Work** first, then its **Ready Frontier** under
[lightweight routing](CONVENTIONS.md#lightweight-work-routing). Do not copy an active TASK or a supposed next
winner into this Roadmap. TASK-00138's completed boundary qualification is not implemented orchestration;
[TASK-00139](tasks/00139-TASK.md), [TASK-00140](tasks/00140-TASK.md), [TASK-00141](tasks/00141-TASK.md) and
[TASK-00142](tasks/00142-TASK.md) retain their actual shared-foundation dependencies, not a promise of four
consecutive executable steps. Historical worktree paths and pre-merge branch snapshots are not current routes.

Next: `/skill:next` from the current checkout for read-only Board-based selection. It does not invoke implementation,
change priorities, clear a hold or grant publication, enrollment, provider, Git, scheduler or cutover authority.

## Planning publication checkpoint

For this planning-only update, John authorized pushing for GitHub CI after the local `./bin/build` stopped
before application checks because the isolated worktree had no running Compose web service. Local planning
validation passed with 623 records / 517 active; link, metadata/generated-view preservation and whitespace
checks passed. The PR workflow runs the canonical gate; its result and human review remain pending at this
checkpoint. This scoped publication exception does not waive implementation gates, claim a local build pass,
clear any TASK hold or grant merge/runtime authority.

## Historical strategy and retained contracts

John approved terminal-first sequencing on 2026-09-26; the
[recorded execution amendment](epics/00007-EPIC.md#approved-terminal-first-sequence--2026-09-26) remains historical
approval. The blended strategy above supersedes its global interface ordering. Its protected operator authority,
revision-bound Markdown inputs, shared Workflow engine and explicit cutover requirements remain intact.

The October 6 [tool-mediated strategy](../docs/engineering/TOOL_MEDIATED_EXECUTION.md) replaced the first
Engineer-root VM profile: scoped files/notes and distinct role sessions, then fixed permission-gated checks in a
qualified restricted environment. The file-only prototype is not production readiness or a completed Workflow.
The [terminal execution contract](../docs/engineering/TERMINAL_EXECUTION.md) retains those implementation boundaries.

The October 7 [incremental Harness milestone](epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07)
retains deterministic controls, Jev screening and required file relevance screening at the initial 0.70 positive-read
threshold. [Jev access](tickets/00037-TICKET.md), [tool guard](tickets/00038-TICKET.md) and
[file screening](tickets/00039-TICKET.md) own that bounded local delivery. The accepted follow-on
[checkpoint/resume](tickets/00040-TICKET.md), [compaction timing](tickets/00041-TICKET.md),
[general judgments](tickets/00042-TICKET.md), [authorized analysis](tickets/00043-TICKET.md),
[change quality](tickets/00044-TICKET.md) and [PHPUnit usefulness](tickets/00045-TICKET.md) decompositions retain
their selected Pi targets, qualifications and completion evidence; this update does not retarget their runtimes.

Detailed decomposition approvals, supplier mappings and publication checkpoints remain in their owning
EPICs/TICKETs and Wayfinder maps rather than being duplicated here. Historical terminal-first priority statements
in those checkpoints describe the approval at that time; current global sequencing is governed above. Accepted
scope exclusions, supplier direction and independent qualification gates are not superseded by that change.
The [external architecture study](research/atomic-lessons.md) remains evidence, not an imported runtime.

## EPIC status

<!-- planning:epics -->
| EPIC ID | Title | Target | Status |
|---|---|---|---|
| [EPIC-00001](epics/00001-EPIC.md) | Establish project-local planning skills | foundation | done |
| [EPIC-00002](epics/00002-EPIC.md) | Establish project-local execution and design skills | foundation | done |
| [EPIC-00003](epics/00003-EPIC.md) | Establish the web application architecture foundation | foundation | done |
| [EPIC-00004](epics/00004-EPIC.md) | Deliver the invite-only authenticated application shell | foundation | ready-for-agent |
| [EPIC-00005](epics/00005-EPIC.md) | Deliver the registered Planning workspace | registered-planning-workspace | ready-for-agent |
| [EPIC-00006](epics/00006-EPIC.md) | Deliver browser Planning Agents and the trusted Harness | browser-planning-agents-harness | ready-for-agent |
| [EPIC-00007](epics/00007-EPIC.md) | Coordinate one TASK through implementation | coordinator-builder-awaiting-review | ready-for-agent |
| [EPIC-00008](epics/00008-EPIC.md) | Complete independent review and PR publication | review-publish-pr-handoff | in-progress |
| [EPIC-00009](epics/00009-EPIC.md) | Create and register new projects | deterministic-project-creation | ready-for-agent |
| [EPIC-00010](epics/00010-EPIC.md) | Edit repository instructions safely | repository-instruction-editing | ready-for-agent |
| [EPIC-00011](epics/00011-EPIC.md) | Deliver durable scoped Agent memory | durable-agent-memory | ready-for-agent |
| [EPIC-00012](epics/00012-EPIC.md) | Introduce TASK Triage and the direct hotfix route | task-triage-hotfix-routing | ready-for-agent |
| [EPIC-00013](epics/00013-EPIC.md) | Support release TASKs and project-specific release Workflows | project-specific-release-workflows | ready-for-agent |
| [EPIC-00014](epics/00014-EPIC.md) | Deliver the working managed Agent team | working-managed-agent-team | ready-for-agent |
| [EPIC-00015](epics/00015-EPIC.md) | Deliver mailbox-confirmed human email change | human-email-address-change | ready-for-agent |
| [EPIC-00016](epics/00016-EPIC.md) | Deliver usable Feature Flags management and enforcement | feature-flags-lifecycle | ready-for-agent |
| [EPIC-00017](epics/00017-EPIC.md) | Author versioned Agent templates with human-approved AI assistance | browser-agent-template-authoring | ready-for-agent |
| [EPIC-00018](epics/00018-EPIC.md) | Publish the qualified Agent OS AccessControl reference-consumer handoff | accesscontrol-reference-consumer-handoff | ready-for-agent |
| [EPIC-00019](epics/00019-EPIC.md) | Diagnose Agent OS operations and managed runs with live and archived logs | operational-diagnosis | ready-for-agent |
| [EPIC-00020](epics/00020-EPIC.md) | Keep delivery handoffs synchronized through Land | land-owned-delivery-handoff-synchronization | done |
| [EPIC-00021](epics/00021-EPIC.md) | Deploy and recover private Fight Agent OS in staging and production | first-private-production | ready-for-agent |
<!-- /planning:epics -->

## Planning Frontier

This generated view keeps requirement decomposition visible without changing executable TASK priority on the
[TASK Board](tasks/BOARD.md). Non-terminal EPICs and TICKETs remain listed until they receive their next-level
records. [Automatic parent completion](CONVENTIONS.md#automatic-parent-completion) closes eligible parents in
the same operation that completes their children.

<!-- planning:frontier -->
### EPICs without TICKETs

| EPIC ID | Title | Status |
|---|---|---|
| None | — | — |

### TICKETs without TASKs

| TICKET ID | Title | Parent EPIC | Status |
|---|---|---|---|
| None | — | — | — |
<!-- /planning:frontier -->
