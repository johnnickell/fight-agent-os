---
id: TICKET-00101
epic: EPIC-00009
title: Confirm and recover one durable creation attempt
status: ready-for-agent
---

# Confirm and recover one durable creation attempt

## Problem statement

Creating a project crosses filesystem, Git, executable preparation, GitHub and database boundaries that cannot
roll back together. A browser wizard or process exit cannot establish consent, completion or safe retry; humans
need one exact preview and durable, inspectable partial outcomes.

## Solution and boundaries

Own the authenticated browser creation form, read-only preflight, complete preview, explicit confirmation and
single durable creation-attempt lifecycle. Require editable display name, Workspace slug, starter, Runner-accessible
parent directory, independent local directory name, initial branch, preparation choice and development profile.
Suggest sensible defaults without conflating identities; optional publication has independently editable owner,
repository name, private-by-default visibility and description. Show a heightened warning and require explicit
acknowledgement for public visibility.

Before preview validate pinned snapshot/manifest integrity, all transformation expectations, names/collisions,
canonical path/root containment and ownership, Runner availability/capabilities, profile revision and applicable
GitHub identity/owner/name/visibility availability. Preview every intended filesystem, Git, executable/network,
source-host, registration and context effect, including exact source/tag/commit/digest, transformations, selected
branch/initial commit intent, preparation command, publication target and selected profile revision. Preflight
reads create no project, remote, registration or preparation process and never authenticate accounts automatically.

One confirmation authorizes only that immutable plan. Recheck material input, snapshot, authority, capability,
collision and external-state changes before effect admission. An invalidated preview requires explicit revised
consent, not silent continuation. Do not prompt repeatedly between unchanged ordinary stages. Outside-root setup
remains the separate human-run installation operation; preserve the volatile form/preview where safe without
claiming it survived reload or granting a browser permission to change mounts.

Persist one attempt identity, immutable confirmed plan, initiating actor/grant, stage identities and verified
intent/result evidence. Sequence: generate; initialize Git/initial commit; optional preparation; optional GitHub
creation/push; register Repository/Checkout/Worktree; resolve first database-authoritative context. Stage adapters
from TICKET-00100/00102/00103 supply exact outcomes; this owner alone decides attempt transitions. It is not a
coding Workflow, TASK claim, Team Lead/Software Engineer assignment or Review/publication pipeline.

Commit state, required audit and delivery intent together before external effects. Use bounded delivery/claim and
currentness contracts compatible with the existing eligible Runner, not another general job platform. Duplicate
confirmation/delivery returns the original operation where applicable. Progress and receipt queries read durable
facts; queue delivery, process exit, live UI or Agent inference never establishes success. Report partial/stale/
unavailable observations distinctly and reconcile after browser disconnect, application/Runner restart or process
interruption without relaunching uncertain effects.

Explicit retry revalidates every completed effect and resumes only at the first incomplete safe stage. Prevent
duplicate directory, initial commit, remote, push, Repository/Checkout link or context revision. Changed authority,
missing capability, unexpected filesystem/Git/provider state, ambiguous ownership or unsupported manifest behavior
pauses or enters Needs Human. Preserve original uncertainty until reconciled; do not substitute a new attempt to
hide an unresolved one. Cancellation stops before the next effect and reports any already-running effect truthfully;
it never claims completed resources were rolled back.

Retain local Git after preparation/GitHub failure, both local/remote state after push failure, and prior effects
after registration/context failure. Offer explicit policy-permitted continuation, including completion without
publication, recording revised scope/consent without rewriting the original plan. Cleanup is a separate previewed,
confirmed, currently authorized and ownership-checked operation with complete impact. Recheck retained references,
process quiescence and exact resources; ambiguous or unrelated content is never deleted. Preserve history even
when a cleanup result is partial/uncertain. Never automatically delete directories, commits, remote repositories,
snapshots, registrations, profiles or context revisions as compensation.

Use the existing browser shell/authentication and component contracts. Expose real progress, retained resources,
failures and explicit retry/continue/cancel/cleanup choices with independent denied, unavailable and interrupted
states. TICKET-00103 supplies the completion receipt and Dashboard handoff; TICKET-00104 supplies optional help.
No mock success, second client state authority, automatic retry on reconnect, automatic Planning/Agent startup,
source release automation or general-purpose workflow engine is included.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Prepare a creation preview | N/A — read-only preflight | Current catalog/profile, inputs, paths, Runner and optional GitHub availability | N/A — a preview is not authorization | Complete bounded effect summary or actionable refusal |
| Confirm the exact operation | Confirm Create Project with current preview identity and public acknowledgement where needed | Current material facts and original command outcome | Committed attempt authorization/queue facts | One durable immutable plan and recoverable dispatch intent |
| Advance and observe creation | Admitted stage dispatch and expected-state outcome recording | Exact stage intent/result, current grant and safe progress | Committed confirmed stage/attempt facts | Only the next authorized stage; browser reads launch nothing |
| Recover or continue | Explicit original-attempt reconcile/retry or confirmed revised continuation | Completed effects, uncertainty, retained resources and current policy | Attributed recovery/hold/continuation facts | No duplicate effect; original plan/history preserved |
| Cancel or clean up | Cancel future effects; separate confirmed cleanup | Current execution, ownership, references and complete cleanup impact | Confirmed cancellation/cleanup facts, never inferred rollback | Retain completed effects unless exact cleanup is separately authorized |

## Validation and permissions

Require current server intent-specific creation/view/recovery/cleanup authority and real Workspace/Host/Runner
scope. Independently check stage-specific filesystem/preparation/source-host/registration authority; profile
selection and earlier confirmation cannot override revocation. Use expected revisions and shared resource fences,
strict bounded input and secret-safe errors. External effects stay outside business transactions under ADR 0002.

Apply ADR 0003/0004: protected reads/actions, volatile drafts, no-store safe responses, late-response/context fences
and no automatic replay after authentication refresh. Viewing a receipt or support suggestion grants no effect.
No credentials, source-host tokens, raw environment or hidden reasoning enter previews, events, logs or exports.

## Acceptance and evidence

- Complete real previews show all effects and public acknowledgement; material drift rejects stale confirmation.
  Duplicate confirmation creates no second attempt and unchanged ordinary stages need no repeated prompts.
- Controlled competing requests, restart/redelivery and uncertain process/provider outcomes preserve original
  identity and truthful partial states; no repeated directory/commit/remote/link/context effects.
- Cancellation/continuation and separately authorized cleanup preserve unrelated work and completed history.
  Browser disconnect, permission loss and stale responses cannot claim success, repeat work or leak private data.
- Production form/progress/recovery behavior has real browser keyboard/focus/responsive evidence and supports
  TICKET-00103's real integrated journey. Owned application/PostgreSQL/client tests and `./bin/build` pass;
  no tests of queue libraries, wrappers, configuration or a proof-only wizard.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00411](../tasks/00411-TASK.md) | Preview and admit one immutable creation attempt | needs-info |
| [TASK-00412](../tasks/00412-TASK.md) | Fence original stage admission delivery and outcomes | ready-for-agent |
| [TASK-00413](../tasks/00413-TASK.md) | Bind and fence separately confirmed cleanup authority | ready-for-agent |
| [TASK-00414](../tasks/00414-TASK.md) | Advance and inspect the actual creation stages | needs-info |
| [TASK-00415](../tasks/00415-TASK.md) | Create from the complete browser preview | ready-for-agent |
| [TASK-00416](../tasks/00416-TASK.md) | Inspect and recover the original attempt in the browser | ready-for-agent |
| [TASK-00417](../tasks/00417-TASK.md) | Preview execute and reconcile explicit cleanup | needs-info |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved seven slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked features with blank PRs. Early admission, stage and cleanup
participants precede their execution/browser consumers; these are concrete delivery blockers, not a new job platform.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00411](../tasks/00411-TASK.md) | TASK-00406, TASK-00405, TASK-00418, TASK-00422; actual initial-mode/first-link qualification held | Complete server preview, one immutable confirmation/attempt and atomic retained inputs |
| B | [TASK-00412](../tasks/00412-TASK.md) | TASK-00411 | Original stage admission, bounded delivery/claims and verified expected-state outcomes |
| C | [TASK-00413](../tasks/00413-TASK.md) | TASK-00412, TASK-00423; actual shared reference composition required | Separate exact cleanup consent and shared durable resource/reference exclusion |
| D | [TASK-00414](../tasks/00414-TASK.md) | TASK-00412, TASK-00409, TASK-00420, TASK-00425; initial-mode/actual stage composition held | Actual selected-stage advancement, reconciliation and safe durable progress |
| E | [TASK-00415](../tasks/00415-TASK.md) | TASK-00411, TASK-00051 | Production independent inputs, complete preview and exact browser confirmation |
| F | [TASK-00416](../tasks/00416-TASK.md) | TASK-00414, TASK-00415 | Durable browser progress and explicit original-attempt retry/continuation/cancellation |
| G | [TASK-00417](../tasks/00417-TASK.md) | TASK-00413, TASK-00416, TASK-00410, TASK-00421; actual reference composition and inherited host/process/provider holds | Complete impact preview, separately admitted cleanup and truthful partial recovery |

TASK-00411/00414 stay `needs-info` for initial-mode and actual first-link/stage composition after TASK-00422/00425
resolve allocations. TASK-00413 gains TASK-00423 and becomes ready behind suppliers: only reference allocation
resolves. TASK-00417 remains held for actual references and inherited host/process/provider support.
TASK-00412/00415/00416 remain ready behind suppliers, not runtime-qualified. TASK-00422/00425 retain an unallocated
new-Repository authority capability; TASK-00418/00420 retain distinct protected-operation mapping holds.

TASK-00404 now gains TASK-00411 and becomes ready behind suppliers: only its early attempt/reference allocation
hold resolves. Actual catalog/admission/reference composition and TASK-00402's real managed-release prerequisite
remain. TASK-00407 gains TASK-00412, with TASK-00411 transitively, while retaining `needs-info` for the protected
local-operation boundary. TASK-00410 gains TASK-00413 but retains `needs-info` for actual reference composition and
inherited host/process holds. TASK-00409's containment/process/egress/credential supplier gap remains unchanged.
No early catalog, root, Runner, profile or terminal owner gains a reverse creation dependency.

TICKET-00102 now maps TASK-00418 early publication feasibility independently of this admission consumer,
TASK-00419/00420 actual publication and TASK-00421 remote impact/cleanup. TASK-00411/00414/00417 gain their precise
suppliers; only allocation gaps resolve. Remote cleanup depends on early creation, not successful publication.
TICKET-00103 now maps TASK-00422 early registration/context/first-link feasibility, TASK-00423 actual references,
TASK-00424 registration/link and TASK-00425 context stages. TASK-00426 owns receipt and integrated acceptance.
Its early read contract cannot wait for completed admission; stage/reference contracts consume TASK-00412 but
never final TASK-00414/00416/00417/00426. Existing-checkout confirmation alone does not establish consent
for not-yet-generated Git; qualify the exact intent-to-verified-evidence contract without fabricated identities.

TASK-00413 owns early impact binding/consent/reference exclusion, not live impact inspection or deletion.
TASK-00417 obtains fresh complete impact through TASK-00410 and the remote owner, then composes that authorization.
The early participant must reject absent/untrusted ownership, reference or quiescence evidence; fixtures do not
qualify a live cleanup route. Its completion must not depend on completed effect adapters or final browser recovery.
Each ordinary stage retains its own original-effect reconciliation; cleanup never becomes their prerequisite.

All paths enforce current intent-specific rights, complete expected state and real shared PostgreSQL participants.
Atomic state/audit/original outcome/intent precedes external effects; neither delivery nor process disappearance
proves completion or safe repetition. Revised continuation preserves original plans and effects, cancellation
closes future admission without rollback claims, and cleanup always requires separate exact consent.

Explicit preparation skip needs no preparation-network capability. Public installed/local-only creation needs no
private-source or publication credentials, but still requires actual Git, registration and database context.
TASK-00408's initial subject remains distinct from preparation changes. Profiles grant no authority; no fabricated
Workflow, Agent, Repository, second resolver or new scheduler fills a missing participant. Optional support never
gates deterministic controls. TICKET-00103 retains completion receipt/Dashboard handoff and real integrated proof.

Owned behavior tests, actual PostgreSQL/Runner/Git/provider integration, guidance and full gate remain within
delivery; tooling/configuration/generated artifacts are qualified directly, not product-tested. Browser slices
include fresh keyboard/focus/announcement, responsive light/dark, accessibility, applicable reduced-motion and
honest denied/stale/unavailable/interrupted/recovery evidence. Volatile drafts, sole `/me` authority and late-result
fences apply; no automatic business-command replay after refresh or reconnection.

This completes this TICKET's split only. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138, terminal-first priority, unrelated holds and Markdown Planning authority.
Implementation checkout and finite read/credential/provider/host/destructive authority remain separate.

### Planning verification

This historical attempt-split receipt predates TICKET-00102's publication mapping; latest planning verification is
in TICKET-00109. Counts and then-unresolved allocations below describe the earlier point.

Recorded the approved seven-slice split in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`, preserving prior uncommitted planning. Before allocation the highest live TASK was
TASK-00410 and there were no archived TASK records. TASK-00411–00417 match approved statuses, blockers and template
sections; all are unranked features with blank PRs. A/C/D/G retain precise missing supplier mappings; B/E/F are ready
behind unfinished dependencies. Unallocated future preflight/reference/stage/provider edges are not claimed verified.

`./bin/planning-check --write` and `./bin/planning-check` passed with 544 records, 452 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 109 new/untracked TASK
whitespace checks passed. Explicit checks verified TASK-00404 → TASK-00411, TASK-00407 → TASK-00412 and TASK-00410 →
TASK-00413, resolving only the approved allocation gaps. TASK-00404 is ready behind suppliers; TASK-00407/00410 stay
held. Real release/private-source/profile/current-authority/host/process/prototype/provider/quota holds remain,
alongside previous TASK-00387/00320 measurement/forecast mappings.

Existing terminal/root/identity/frame/Runner/catalog/profile dependencies and priority were checked. TASK-00138/
00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD; TASK-00138 remains the sole active Board
item, order 2. No early supplier gained a reverse creation blocker. That split advanced handoffs to TICKET-00102, then
TICKET-00103–00109, and no stale current TICKET-00101 command remains. TICKET-00102/00103 record exact incoming
preflight/reference/stage/cleanup requirements without allocating their TASKs or claiming their splits complete.

Full `./bin/build` was not run and remains required before commit/publication. These are planning checks, not fresh
Runner, PostgreSQL application-participant, browser, source-release, provider or cleanup qualification. All changes
remain uncommitted. No live source/account/private-path inspection, confirmation, attempt/stage execution, cleanup,
Planning mutation, cutover, commit, push, PR or merge occurred. Implementation, independent review and publication
remain outstanding; finite operational authority and implementation checkout stay separate. Unrelated main-checkout
work was untouched and is not claimed clean. Prior receipts remain labeled historical.

### Historical incoming local-stage handoff

The incoming allocation requests below predate the approved split above. TASK-00411/00412/00413 now resolve those
specific allocations; they do not resolve actual runtime/preflight/reference capabilities or inherited holds.

TICKET-00100 now maps TASK-00406 read-only exact local feasibility, TASK-00407 materialization, TASK-00408 fresh Git,
TASK-00409 optional contained preparation and TASK-00410 separately admitted local cleanup. Each normal stage owns
its original-effect reconciliation; this TICKET alone owns immutable confirmed plans, attempt transitions, explicit
retry/continuation/cancellation and cleanup consent. No completed browser or effect adapter may reverse-block the
early admission/stage-intent/result participant needed by TASK-00407, or the early cleanup-authorization/reference
participant needed by TASK-00410. Allocate these independently deliverable real contracts during this TICKET's split,
not guessed IDs now; they can consume TASK-00406/00402's read/pin primitives before final orchestration.

TASK-00407 retains its protected local-operation mapping hold and TASK-00409 its actual process/egress/credential
supplier hold under existing execution owners. Enrollment, root configuration or TASK-00138 qualification do not
implement them. No fabricated Workflow/Agent or completed Team journey supplies deterministic creation authority.
Use exact initial-subject receipts from TASK-00408 separately from preparation's dirty-state observations. Explicit
skip must not require preparation network capability; normal local-only completion never requires cleanup execution.
Early cleanup authority must coordinate actual references/admission exclusion without depending on final cleanup
UI or TASK-00410's completed adapter. Mapping alone is not qualification and no existing hold is cleared here.

### Historical incoming catalog attempt/reference allocation

TICKET-00099 now maps catalog/manifest suppliers TASK-00401/00402 and exact profile associations TASK-00405.
TASK-00402 supplies real early caller-transaction eligibility/pinning/reference/hold fences; this TICKET must map
its exact independently deliverable admission/reference participant before final browser or integrated acceptance.
TASK-00404 remains `needs-info` for that precise supplier and will consume it for actual withdrawal/refresh versus
admission retention races. Do not make early admission depend on completed TASK-00404 or create a whole-journey
cycle. Add only a real approved TASK blocker during the later split, not a guessed ID now.

TASK-00402 retains the real managed-release availability prerequisite. TASK-00403 retains the protected private
GitHub credential/access mapping hold and is conditional on that selected route, not a public/local prerequisite.
TASK-00405 reuses TASK-00249/00299 with their existing scoped-read/Triage holds; no fabricated Repository enables
pre-registration profile reads. Browser preview and selected-revision confirmation stay here; no catalog read
fetches source or grants execution. This handoff creates no attempt, runtime authority or TASK for this TICKET.

### Original requirement approval

Approved as area 3 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

[TICKET-00099](00099-TICKET.md) supplies immutable catalog inputs; [TICKET-00100](00100-TICKET.md),
[TICKET-00102](00102-TICKET.md) and [TICKET-00103](00103-TICKET.md) supply owned stage contracts. Define early
admission/effect/result primitives before final browser integration, not mutually blocked whole-TICKET journeys.
Reuse installation/Runner/authentication owners; creation is not a new prerequisite of terminal TASK execution.
Optional [TICKET-00104](00104-TICKET.md) assistance never gates this path. Exact TASKs remain separate; this approval
creates no attempt, consent grant, queue message, filesystem effect, cleanup, runtime or changed Board priority.
