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

The merged planning records have no EPIC without TICKETs or TICKET without TASKs in the generated frontier below.
That structural result is not proof of semantic coverage, implementation acceptance or runtime qualification.
Use the accepted coverage/handoffs in the owning records:

| Scope | Current planning handoff |
|---|---|
| Original vision, EPIC-00005–00010 | [Vision map frontier and coverage](wayfinder/complete-agent-os-vision-map.md#frontier): original planning phases complete; accepted requirements and unresolved supplier contracts remain with their records. |
| Local Pi workspace, TICKET-00110 | [Approved follow-up](tickets/00110-TICKET.md#approved-session-and-worktree-following--2026-10-20): TASK-00502 adds session-child and worktree test-log following after TASK-00448/00449; TASK-00503 independently delivers the global command after completed TASK-00447. Container provisioning and full observability remain separate. |
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
