# Roadmap

The accepted sequence is governed by EPICs and their requirements, with current implementation facts in the
[capability inventory](FOUNDATION.md). Markdown remains authoritative until the explicit verified cutover.

1. Package the approved Fight terminal identity and qualify the local execution boundary (EPIC-00006–00007).
2. Deliver the terminal path first: PHP-owned operator authorization, immutable Markdown TASK snapshots, exclusive
   claims and durable Workflow state; recoverable worktree/container/test-database preparation; then distinct
   Team Lead → Software Engineer Pi sessions to durable Awaiting review. Prove two independent TASKs can run safely.
3. Extend the same Workflow through Senior Engineer review → QA → Release Manager landing → human PR handoff
   (EPIC-00008). Proven mechanical documentation reconciliation preserves acceptance.
4. Complete authenticated browser journeys, registered database Planning and browser Planning Agents
   (EPIC-00003–00006), reusing the same application operations and explicit verified Markdown cutover.
5. Deliver project creation and safe instruction editing along their own dependencies (EPIC-00009–00010).

John approved terminal-first sequencing on 2026-09-26. Browser pages, React and database Planning cutover are no
longer blanket prerequisites for terminal execution. Required identity, permission, repository enrollment,
immutable context/Harness versions, PostgreSQL durability and credential isolation remain prerequisites of the
operations that consume them. Markdown remains authoritative before cutover; a revision-bound read adapter feeds
the same eligibility/claim operations, not a second Workflow engine. The console must not infer an operator's
business authority merely from access to a terminal. Exact enrollment and grant contracts are settled before launch.

The October 6 [tool-mediated strategy](../docs/engineering/TOOL_MEDIATED_EXECUTION.md) replaces the first
Engineer-root VM profile: scoped files/notes and separate role sessions first, then fixed permission-gated checks
in a qualified restricted environment. The file-only prototype is not production readiness or a completed TASK.

John approved the [incremental Harness protection and retrieval milestone](epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07)
on 2026-10-07 for existing human-operated Pi sessions. Prioritize deterministic controls plus Jev screening and
required file relevance screening at an initial 0.70 positive-read threshold. This bounded Harness slice can
proceed before full managed sandbox/browser/database delivery. Accepted [Jev access](tickets/00037-TICKET.md),
[tool guard](tickets/00038-TICKET.md) and [file screening](tickets/00039-TICKET.md) requirements now own it;
access is decomposed into approved [protected setup](tasks/00184-TASK.md) and dependent
[typed judgments](tasks/00159-TASK.md). The guard is decomposed into independent
[deterministic restrictions](tasks/00160-TASK.md) and [semantic screening](tasks/00161-TASK.md), which depends on
those restrictions and the shared judgments. File screening is decomposed into
[single-file screening and instructions](tasks/00162-TASK.md), dependent on shared judgments, followed by
[bounded file sets and caching](tasks/00163-TASK.md). It proceeds independently of guard implementation.
The selected three-TICKET milestone now has approved TASK decompositions; broader EPIC/map planning remains open.
The accepted Jev follow-on now has requirements for [checkpoint/resume](tickets/00040-TICKET.md),
[compaction timing and observability](tickets/00041-TICKET.md), [general judgments](tickets/00042-TICKET.md),
[authorized analysis](tickets/00043-TICKET.md), [change quality](tickets/00044-TICKET.md) and
[PHPUnit usefulness](tickets/00045-TICKET.md), consuming closed WF-029 through WF-031. Checkpoint/resume now has
approved [required checkpoint](tasks/00164-TASK.md), [optional selection](tasks/00165-TASK.md),
[selective repair](tasks/00166-TASK.md) and [retention/holds](tasks/00167-TASK.md) TASKs targeting Pi 1.1.0.
Timing/observability now has approved [deterministic safe compaction](tasks/00168-TASK.md),
[every-turn Jev evaluation](tasks/00169-TASK.md) and [history/incident inspection](tasks/00170-TASK.md) TASKs on Pi 1.1.0.
General judgments now has approved [authenticated typed MCP judgments](tasks/00171-TASK.md),
[many-file resumable stages](tasks/00172-TASK.md) and [qualified answer reuse](tasks/00173-TASK.md) TASKs on Pi 1.1.0.
Authorized evidence now has approved [MCP composition](tasks/00174-TASK.md),
[repository analysis](tasks/00175-TASK.md), [application inspection](tasks/00176-TASK.md) and
[constrained SQL](tasks/00177-TASK.md) TASKs on Pi 1.1.0; analysis routes independently consume composition.
Change quality now has approved [automatic pre-handoff assessment](tasks/00178-TASK.md),
[qualified refresh/reuse](tasks/00179-TASK.md) and [independent reviewer follow-ups](tasks/00180-TASK.md) TASKs on
Pi 1.1.0. PHPUnit usefulness now has approved [changed-case assessment](tasks/00181-TASK.md),
[scoped whole-suite assessment](tasks/00182-TASK.md) and [context-sensitive refresh](tasks/00183-TASK.md) TASKs.
All 20 follow-on TASKs target Pi 1.1.0 and remain unranked with explicit capability blockers. TASK decomposition
is complete for the selected TICKET-00040 through TICKET-00045 scope; implementation and qualification remain pending.
Older browser/Harness planning is not implicitly complete. Broader routing retains its separate triage-map decisions. TASK-00138 retains its qualification requirements, and the Board's
current executable ordering is unchanged by this planning amendment.

The next execution TASKs are [tool-mediated boundaries](tasks/00138-TASK.md), [authorized console start](tasks/00139-TASK.md),
[recoverable preparation](tasks/00140-TASK.md), [Pi delegation](tasks/00141-TASK.md), and
[parallel/recovery qualification](tasks/00142-TASK.md). They are incremental delivery slices, not a promise that
existing foundation dependencies disappear or that a container alone is ready for autonomous work.

Current amendments define [Team roles](../docs/engineering/TEAM.md), [tool-mediated startup](tickets/00032-TICKET.md),
[post-review QA](tickets/00033-TICKET.md), and [team templates](tickets/00034-TICKET.md). Those TICKETs cover selected
cross-cutting requirements, not full decomposition of their parent EPICs; remaining EPIC requirements still need
TICKET/TASK coverage before execution. Prioritize Graphify and Writing for Agents locally, then security audit and
the existing skill backlog. Signed releases, application deployment and operational hotfix automation require
separate accepted implementation scope; self-learning awaits trustworthy metrics. Personal daily workflows are excluded.

The [external architecture study](research/atomic-lessons.md) informs contracts without importing Atomic's runtime.

## Current planning handoff

The separately approved [operational observability map](wayfinder/operational-observability-map.md#approved-epic-handoff)
is decision-complete, and its sole destination is written/approved as [EPIC-00019](epics/00019-EPIC.md).
Its [seven-TICKET requirement split](epics/00019-EPIC.md#approved-ticket-decomposition-and-current-handoff) is now
written/accepted as TICKET-00111–00117. EPIC/TICKET phases are complete. TICKET-00111's
[four-TASK split](tickets/00111-TICKET.md#approved-task-decomposition-and-current-handoff) is accepted/written as
TASK-00451–00454. TICKET-00112's [six-TASK split](tickets/00112-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00455–00460. TICKET-00113's [six-TASK split](tickets/00113-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00461–00466. TICKET-00114's [seven-TASK split](tickets/00114-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00467–00473. TICKET-00115's [four-TASK split](tickets/00115-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00474–00477. TICKET-00116's [three-TASK split](tickets/00116-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00478–00480. TICKET-00117's [seven-TASK split and completed handoff](tickets/00117-TICKET.md#approved-task-decomposition-and-current-handoff)
is accepted/written as TASK-00481–00487. **All map planning phases are complete**: one EPIC, seven TICKETs and
thirty-seven unranked TASKs. Exact early installation suppliers are mapped without clearing qualification gates;
TASK-00487 contains the composed operator/workload acceptance. Implementation/runtime qualification remains outstanding.
Next: `/skill:next` in `.runs/worktrees/observability-planning/` for read-only Board-based work selection, not execution.
This feature does not alter terminal-first/Board priority or block the independent local terminal log pane below.
At the latest allocation, fetched `develop` was `58b087b`, while this worktree remained `ac41c28`; no rebase was
performed. Live/archive/concurrent IDs were rechecked across 17 refs/four worktrees; recheck before future allocation.

The approved [local Pi terminal workspace split](tickets/00110-TICKET.md#approved-decomposition--2026-10-15)
is complete: TASK-00447 has no external blockers; TASK-00448 and TASK-00449 depend only on it. The log pane
reads the existing shared main-checkout runtime; isolated containers and full observability remain separate.
These unranked additions preserve existing Board priorities and other maps' planning handoffs.

The closed vision map's original EPIC-00005–00010 destinations now have accepted **TICKET coverage**; see the
[requirement-phase coverage and next phase](wayfinder/complete-agent-os-vision-map.md#original-map-requirement-phase-coverage-and-next-phase)
and [TICKET-00109's approved final split](tickets/00109-TICKET.md#approved-task-decomposition-and-current-handoff).
The selected original map's **planning phases are complete**, not implementation or runtime qualification.
Next: `/skill:next` for Board-based selection under [map-wide phase order](CONVENTIONS.md#map-wide-planning-order).
Publication reconciliation preserves upstream TASK-00138 completion; the refreshed Board has no active TASK and
TASK-00139 is first ready. Terminal-first priorities remain; unrelated maps retain their own planning handoffs.
The approved identity reconciliation preserves upstream local screening TASK-00309 and renumbers only this split's
managed-Review owner to TASK-00445. Earlier receipts/active-work statements describe the pre-reconciliation baseline;
[TICKET-00109's publication checkpoint](tickets/00109-TICKET.md#authorized-planning-publication-checkpoint) owns current evidence.
The [TICKET-00083 split](tickets/00083-TICKET.md#approved-task-decomposition-and-current-handoff) is approved as
TASK-00445 and TASK-00310–00320 and the [TICKET-00085 split](tickets/00085-TICKET.md#approved-task-decomposition-and-current-handoff)
as TASK-00321–00326. The [TICKET-00086 split](tickets/00086-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00327–00332 and [TICKET-00087](tickets/00087-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00333–00338. [TICKET-00088](tickets/00088-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00339–00342. [TICKET-00089](tickets/00089-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00343–00347. [TICKET-00090](tickets/00090-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00348–00354. [TICKET-00091](tickets/00091-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00355–00360. [TICKET-00092](tickets/00092-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00361–00367. [TICKET-00093](tickets/00093-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00368–00373. [TICKET-00094](tickets/00094-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00374–00380. [TICKET-00095](tickets/00095-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00381–00384. [TICKET-00096](tickets/00096-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00385–00389. [TICKET-00097](tickets/00097-TICKET.md#approved-task-decomposition-and-current-handoff)
adds TASK-00390–00395. [TICKET-00098](tickets/00098-TICKET.md#approved-task-decomposition-and-current-handoff)
now adds TASK-00396–00400. TASK-00387 gains early usage/time suppliers TASK-00396/00397 alongside visibility
TASK-00390; TASK-00320 gains base forecast TASK-00400. Only these allocation holds are resolved, not runtime
qualification. TASK-00399 retains its safe quota-source/protected-refresh mapping hold without blocking history
or forecasts. Completed Dashboard/forecast cannot reverse-block early primitives. No terminal producer gains a
browser blocker; independent holds and unfinished runtime persist.
[TICKET-00099](tickets/00099-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00401–00405 for
catalog administration, immutable snapshots/manifest, private source, retained lifecycle and exact profile
associations. TASK-00402 retains the real managed-release prerequisite, TASK-00403 its protected credential/access
mapping gap; TASK-00404 now gains TASK-00411, resolving only its early attempt/reference allocation hold. Existing storage/identity/profile
owners gain no reverse blocker and no operational hold is cleared. All new orders remain unranked.
[TICKET-00100](tickets/00100-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00406–00410 for
local feasibility, generation, fresh Git, optional preparation and separate local cleanup. TASK-00407 now gains
TASK-00412 but retains protected local-operation gaps; TASK-00409 retains actual process/egress support and
TASK-00410 gains TASK-00413 while retaining reference composition and inherited host/process holds.
[TICKET-00101](tickets/00101-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00411–00417 for
early immutable admission/stage/cleanup contracts, real stage advancement and browser confirmation/recovery/cleanup.
TASK-00411/00414 retain initial-mode/actual first-link/stage holds after the registration allocation below.
[TICKET-00102](tickets/00102-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00418–00421 for
early publication preflight, exact remote creation, verified initial publication and separate remote cleanup.
TASK-00411/00414/00417 gain TASK-00418/00420/00421 respectively, resolving allocation only. TASK-00418/00420 retain
protected `gh`/Git invocation mapping holds; actual reference/local-host/process/provider holds remain, including
TASK-00417. Early suppliers cannot wait for final browser/receipt acceptance. No runtime qualification hold or
terminal priority is cleared; all new orders remain unranked.
[TICKET-00103](tickets/00103-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00422–00426 for
early finalization preflight, actual reference protection, registration/link, initial context and durable receipt/
Dashboard handoff with real integrated acceptance. TASK-00411/00413/00414 gain TASK-00422/00423/00425 respectively;
only allocation gaps resolve. TASK-00413 becomes ready behind suppliers; TASK-00410/00417 retain actual reference/
host/process/provider holds. TASK-00422/00425 remain `needs-info` for a distinct new-Repository database-authority
supplier under TICKET-00028; existing migration/post-cutover TASK-00102/00103 do not provide it. No fake cutover,
configuration bypass or live Planning authority change follows. Final receipt never reverse-blocks early owners.
[TICKET-00104](tickets/00104-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00427–00429 for
model-free playbooks/safe diagnostics, protected OpenRouter support and its browser journey. TASK-00428 remains
`needs-info` for exact pre-registration attempt/Workspace Agent/snapshot/assignment scope under existing owners;
Repository-bound contracts cannot fabricate context or silently widen authority. No deterministic creation or early
supplier gains a reverse assistance blocker. EPIC-00009's six TICKET splits are recorded, not runtime-qualified.
[TICKET-00105](tickets/00105-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00430/00431 for
bounded effective-instruction queries and the existing-Dashboard viewer. TASK-00430 retains an exact protected
human read/current-custody supplier gap under existing execution/Runner owners; no fabricated Workflow or second
resolver. Inspection grants no edit/disclosure/sync authority and adds no reverse blocker to early suppliers.
[TICKET-00106](tickets/00106-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00432/00433 for
exact Save/Delete/reconciliation and the existing-Dashboard form. TASK-00432 retains its distinct protected mutation/
shared-custody/physical-guarantee hold; reads and database fences alone do not qualify conditional filesystem effects.
Reuse context/Harness drift owners; no provider, completed publication/sync or reverse terminal dependency is added.
[TICKET-00107](tickets/00107-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00434/00435 for
bounded durable instruction suggestions and explicit adoption into the existing form. Existing read/Skill/Agent/
protected-runtime holds remain; direct editing gains no AI dependency.
[TICKET-00108](tickets/00108-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00436–00440 for
exact publication preview/admission, selected-file commit, non-force push/PR, safe default return and Dashboard
recovery/handoff. TASK-00436–00438 retain distinct protected admission/identity, local Git and authenticated Git/PR
mapping holds; TASK-00439/00440 are ready behind them, not qualified. No creation/managed-publication journey or
optional AI becomes a prerequisite.
[TICKET-00109](tickets/00109-TICKET.md#approved-task-decomposition-and-current-handoff) adds TASK-00441–00444 for
retained status/explicit checks, exact fast-forward/recovery, currently authorized scheduling and the existing
Dashboard/final real instruction-change journey. TASK-00441–00443 retain protected fetch/custody, conditional Git/
physical-exclusion and scheduled actor/delegation/runtime holds; TASK-00444 is ready behind suppliers, not qualified.
No earlier supplier gains a reverse dependency. Latest planning checks are in
[TICKET-00109](tickets/00109-TICKET.md#authorized-planning-publication-checkpoint); publication-stage full build passed,
with final documented-tree verification and publication still pending at that checkpoint.
Planning completion grants no implementation, live source/credential/Git/scheduler effects, merge or cutover authority.

[TASK-00249](tasks/00249-TASK.md) inherits exact resource and model suppliers through TASK-00299, retaining
scoped-read and Triage holds. TASK-00323/00339 gain TASK-00343 and become ready behind unfinished dependencies,
not live qualification. TASK-00342 gains TASK-00345; TASK-00302/00304 gain TASK-00344/00345. TASK-00340/00342 and
provider suppliers now consume TASK-00348/00349 identity/attempts and TASK-00351/00352/00353 invocation/attachment/
recovery. TASK-00340/00344/00345 become ready behind unfinished dependencies after their sole mappings resolve.
TASK-00244/00336 consume TASK-00350's protected session adapter, retaining its precise operational host/broker gap
under existing execution owners and actual authority composition. TASK-00302 now gains TASK-00355's early proposal
contract independently of Planning cutover; its Feature/publication and host/authority holds persist. TASK-00266
gains TASK-00360's proposal/answer/Apply/reconciliation chain and now TASK-00362–00367's browser operations, with
TASK-00361 transitively. Its browser allocation gap resolves, but actual role/host/authority holds remain, alongside
TASK-00364's exact protected prototype-execution participant gap. TASK-00087 evidence storage does not execute
prototypes; external web research separately requires TICKET-00095's qualified screened tools. TASK-00254/00261/
00263 now gain TASK-00369's early applicable Workflow commitment/capacity/resource participants, with TASK-00264
consuming them transitively; independent composition/base/contribution/authority holds remain. Runner enrollment
or completed consumers do not resolve TASK-00350/00364's host/prototype gaps. TASK-00140/00087 gain TASK-00374
private storage; TASK-00250/00261/00264 gain TASK-00375 contribution facts, preserving independent holds.
TASK-00141/00373 gain TASK-00377's exact receipt/subject chain, and TASK-00445 gains TASK-00378 protected reads.
Execution ownership/order remains unchanged; those precise early supplier edges add no browser/cutover or final
retention prerequisite. TASK-00381 consumes TASK-00171's protected shared Jev integration; independent TASK-00382
search and TASK-00383 retrieval use that gate, while TASK-00384 consumes existing Artifact/read/recovery owners for
retained/cached/resumed delivery. TASK-00364 names these conditional web capabilities without a blanket local-only
blocker or clearing its prototype hold. Local Jev readiness is not managed qualification. Primitives never reverse-
depend on completed web research, runtime continuation, Planning Apply or final browser/role consumers. Use the Board for current execution priority. Refresh this handoff
when the accepted phase changes; historical selected-slice handoffs do not supersede it or prove full decomposition.

## EPIC status

<!-- planning:epics -->
| EPIC ID | Title | Target | Status |
|---|---|---|---|
| [EPIC-00001](epics/00001-EPIC.md) | Establish project-local planning skills | foundation | done |
| [EPIC-00002](epics/00002-EPIC.md) | Establish project-local execution and design skills | foundation | done |
| [EPIC-00003](epics/00003-EPIC.md) | Establish the web application architecture foundation | foundation | ready-for-agent |
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
