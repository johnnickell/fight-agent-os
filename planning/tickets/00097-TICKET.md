---
id: TICKET-00097
epic: EPIC-00007
title: Inspect and control execution through the sessions Dashboard
status: ready-for-agent
---

# Inspect and control execution through the sessions Dashboard

## Problem statement

Humans need to find running or historical work, understand its exact outcome and evidence, and intervene through
real authorized operations. A transcript feed, phase-heavy card grid or locally invented status cannot provide that
control safely or at repository scale.

## Solution and boundaries

Deliver the accepted compact repository-scoped Workflow table using the existing authenticated application shell
and theme owner. Prioritize TASK/reference and Workflow identity, authoritative status, latest meaningful outcome
and next action, branch/actual PR reference, start/freshness, known active duration/recorded usage and visibility
action. No embedded phase lists, events, mini-timelines, transcript excerpts or per-row history polling. Narrow
layouts preserve the same meaning with accessible semantic records rather than document-level overflow.

Use TICKET-00096's whole-result server-side safe metadata search and stable snapshot cursor, with the accepted
25-record default page and Newer/Older navigation. Explicit refresh/search changes begin a new result. One labelled
Archived toggle replaces the entire Current result including heading/count/state/cursor with its independently
paginated archive; no combined All view. Explain projected count freshness and stale/partial/unavailable states,
never truncate older accessible history silently.

Own explicit revision-checked Archive/Restore visibility operations and their per-row controls. They change no
execution, claim, grant, approval or Planning lifecycle. Confirm active archive and keep that work visible through an
independent repository Running now summary with a detail link. If that safeguard cannot be guaranteed, reject
active archive. Restore changes visibility only. Above the table show permission-filtered Running now, Needs Human,
recent meaningful outcomes and recorded repository usage. Display qualified provider/account/key quota separately
with its broader scope through TICKET-00098, never as a repository balance or fake installed capability.

Provide four separately loaded detail sections:

- Summary prioritizes current status, meaningful outcome, next human action, exact immutable Git subject, freshness,
  role/phase/attempt/recovery metadata and known usage/time. Review is not yet executed in this EPIC: show Awaiting
  review/unavailable honestly and leave future verdict/publication facts to TICKET-00083.
- Changes exposes every file in the exact bounded syntax-highlighted base…head subject, lazily loads bodies and
  directs oversized/binary content to authorized Artifacts. Mark mutable worktree observations separately.
- Activity places dense phase/Agent timelines and bounded searchable event windows below the primary summary.
  Distinguish authoritative transitions from optional observations, allow selected-phase drill-down and expose
  only sanitized tool metadata/evidence links. No prompts, thinking, transcript or message excerpts.
- Evidence presents actual check/Git/Artifact references, provenance, limitations, sensitivity, holds and tombstones.
  Later Review/QA criteria/verdicts extend this same surface; do not invent them to fill an empty section.

Each section has independent loading, empty, denied, stale, partial, interrupted, cancelled, failed, purged and
unavailable states so a failed query cannot erase the dossier. Reconnect reloads durable state before live updates.
Planning conversations remain linked under Planning, not presented as execution history or exposed through this UI.

Expose authorized pause/cancel/resume/takeover and Runner drain/halt/recovery actions by calling existing semantic
operations from TICKET-00032/00093. Show expected revisions, consequences, remaining limits and actual outcomes;
resolve original-command uncertainty before another effect. Keep claim release dependent on confirmed quiescence.
Viewing, archive, disconnect and page refresh never start/cancel work. Reuse TASK-00205's accepted Ready-card/keyboard
start and TASK-00265's Pending start interaction, including their one-intent consent semantics; no duplicate Start
form, extra normal-route approval or generic status setter is introduced here.

Exclude a new shell/theme, client eligibility/queue algorithm, Workflow engine, browser transcript viewer, coding
command executor, review/QA execution, publication/merge or demonstration rows. Final visual treatment and actual
accessibility qualification belong with production delivery, not inherited prototype screenshots.

## Visual Workflow policy editor — approved plan 2026-10-18

John requested a draggable visual representation and editor for the Workflows, Policies and associated Agents
already planned. [TASK-00493](../tasks/00493-TASK.md) adds this bounded configuration surface under the existing
Dashboard owner. Preserve the compact runtime table and detail sections; open policy configuration separately.
No full Wayfinder map, new executor or duplicate policy/Agent/tool registry is needed.

The subsequent approved v1 scope uses a **fixed stage sequence** with configurable, versioned per-stage
instructions, eligible Agent/role assignments, bounded policy and requested MCP tool capabilities. Dragging changes
layout only, not execution order or topology. Provide keyboard/list-form equivalents and distinguish requested,
eligible and effective tool access. Instructions cannot grant Permissions or weaken required gates/independence.
Creating Workflows from scratch with custom stages/transitions is the v2 destination, not part of this TASK.

Reuse TICKET-00025/00052 for versioned Repository configuration/routes, TICKET-00032/00083 for owned execution
stages and authority, TICKET-00061/00064 for current tool and Agent eligibility, TICKET-00075/00076 for published
templates, TICKET-00088 for immutable snapshots, and TICKET-00096 for run facts. The application validates and
publishes an attributable policy revision; existing runs retain their selected revision. Standing policy permits
routine future runs without repeated human confirmation. Unsupported stages/connections or missing management
contracts are visible gaps, not browser-invented runtime capabilities.

John also approved bounded transient retries and shared Review/QA repair loops with visible holds on exhaustion
or uncertainty; installation constraints/defaults with permitted Repository overrides and run-only narrowing;
and immutable run policy/instructions with current revocation/stop checks. New revisions apply to future runs;
resume keeps its pinned revision, and adopting another revision requires a new eligible run. Team-level policy
inheritance and in-place policy migration are deferred to v2. These are planning decisions, not implemented behavior.

Deliver inspection first, then typed draft edits, validation/diff and authorized publication/restore through these
same owners. [TASK-00493's decision record](../tasks/00493-TASK.md#approved-v1-policy-decisions) owns the details;
its [supplier contract trace](../tasks/00493-TASK.md#supplier-contract-trace-and-remaining-holds) distinguishes
existing planned boundaries from missing amendments. John accepted the [policy ownership boundary](../tasks/00493-TASK.md#approved-policy-ownership-boundary):
Execution owns policy semantics and the installation-policy lifecycle; Repository configuration owns scoped
overrides; the editor consumes their shared validation/publication contracts. The separately approved
[TASK-00496](../tasks/00496-TASK.md) now owns the installation-policy lifecycle under TICKET-00032; TASK-00139 and
TASK-00493 gain its explicit prerequisite edge. Its [approved management boundary](../tasks/00496-TASK.md#approved-management-authority)
restricts installation changes to protected Super Admin authority and Repository overrides to separate scoped
management; neither grants execution rights. Its [engineering Permission/participant map](../tasks/00496-TASK.md#engineering-investigation-permission-mapping)
records the accepted five-right mapping and traces current writer paths, without implementation or runtime qualification.
The [Repository access model](../tasks/00496-TASK.md#approved-repository-access-model) permits explicit human/Agent
access, all repositories for Super Admin and the initial Workspace's repositories through `ROLE_WORKSPACE_ADMIN`.
Use existing Role assignment/removal; separate Workspace assignment is deferred until multi-workspace functionality,
not a new administrative-membership type or current blocker. Operation Permissions remain separate. Humans and Agents may read/revise/restore Repository configuration when granted the
required rights and access; likely read-only Agent assignments are not a capability or assignment restriction.
No additional principal-type gate is introduced. The Repository access-grant writer, Workspace Admin Role integration,
authenticated console admission, compatible lock order, fixed stage schema, instruction composition and actual
shared publication/admission integration remain held. The
[ownership map](../tasks/00496-TASK.md#engineering-investigation-repository-access-and-administrator-role-owners)
traces existing Role/Permission and Team writers and identifies Repository access grant/revoke as the remaining
unallocated writer. Workspace Admin reuses the Role path; Workspace assignment waits for multi-workspace work.
Neither identity nor configuration silently owns access grants; no new TASK or prerequisite edge is allocated here. Reconcile existing start-override
and drift contracts with the approved narrowing/pinning rules before readiness. Preserve `needs-info`, authored
priorities, other dependencies and all host/runtime/authority qualification holds. No new Wayfinder map, v2 TASKs,
implementation or operational grant follows from this allocation.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Find current or archived work | N/A — navigation/search is read-only | Whole-result authorized search, stable cursor and summary | N/A — browsing changes no Workflow state | Safe useful overview with honest freshness |
| Inspect exact work and evidence | N/A — separately bounded section reads | Summary/Changes/Activity/Evidence and authorized Artifact access | N/A — viewing cannot approve or execute | Exact subject and known/unknown evidence, never transcript disclosure |
| Archive or restore visibility | Explicit expected-state Archive/Restore | Current visibility, authority and independent running safeguard | Committed visibility facts, not execution transitions | Result-set membership changes; running work continues |
| Intervene in execution | Existing intent-specific lifecycle/Runner commands | Fresh grant/claim/attempt, limits, consequence and original result | Existing confirmed authoritative outcomes only | Bounded pause/cancel/resume/control effect or truthful hold |
| Follow an existing start/pending intent | Existing TASK-00205/00265 operations only | Authoritative original intent/Workflow status | Existing owner facts, no client-generated status | Same accepted start or withdrawable pending intent, no second claim |

## Validation and permissions

Use current server intent-specific viewing/evidence/visibility/control authority and Repository scope, not Role names
or UI visibility. Apply ADR 0003/0004 authentication, volatile state, no-store, strict decoding, invalidation and
late-response fences. Cancel/retire requests and clear private views on authority/context change; a live message or
stale button never authorizes a command. Guard loaders/actions as well as rendering, revalidate expected state on
server mutations and never automatically replay an uncertain business request after refresh.

Validate bounded filters/cursors, escape all untrusted metadata/diffs, preserve private Artifact access, and keep
raw keys, sensitive payloads and hidden reasoning out of browser/logging. Keyboard actions must match pointer
semantics with accessible status/focus and reduced-motion behavior. Unknown availability disables unsupported
controls truthfully rather than adding fixtures to production.

## Acceptance and evidence

- Actual production Workflow results populate compact searched/paginated Current and Archived views, with useful
  responsive navigation and independent detail sections. No per-row history fetch or silent older-history cap.
- Archive/Restore cannot change execution; active archived work stays independently visible or active archive is
  rejected. Permission changes and stale/racing commands never leak data or produce false local success.
- Exact immutable changes/evidence remain distinguishable from mutable observations; oversized/binary/purged data
  is explicit. Phase detail and all search routes exclude transcripts, excerpts and unsafe payloads.
- Real operational commands preserve original request identity, current authority, quiescence and pending outcomes.
  Browser start reuses existing owners; detach/reload is not cancellation or duplicate execution.
- Owned API/client behavior tests, actual browser keyboard/focus/responsive/light-dark evidence and `./bin/build`
  pass on the real TASK path. Prototype/historical receipts are not fresh accessibility or runtime acceptance;
  do not duplicate browser/library behavior or create a proof-only Dashboard.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00390](../tasks/00390-TASK.md) | Control Workflow visibility without hiding running work | ready-for-agent |
| [TASK-00391](../tasks/00391-TASK.md) | Find and follow Workflows in the sessions Dashboard | ready-for-agent |
| [TASK-00392](../tasks/00392-TASK.md) | Inspect immutable changes and retained evidence | ready-for-agent |
| [TASK-00393](../tasks/00393-TASK.md) | Drill into sanitized phase activity | ready-for-agent |
| [TASK-00394](../tasks/00394-TASK.md) | Intervene through existing Workflow and Runner controls | ready-for-agent |
| [TASK-00395](../tasks/00395-TASK.md) | Follow existing Start and Pending start intents | ready-for-agent |
| [TASK-00493](../tasks/00493-TASK.md) | Visually configure Workflow stages, Agent assignments and tool policies | needs-info |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved six bounded slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are feature records, `ready-for-agent` behind unfinished suppliers, with blank
order/pr. This is implementation planning, not operational readiness or browser acceptance.

| Slice | Accepted owner | Exact blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00390](../tasks/00390-TASK.md) | TASK-00385 | Revision-checked visibility API/console operations and early independent-running safeguard |
| B | [TASK-00391](../tasks/00391-TASK.md) | TASK-00390, TASK-00052, TASK-00389 | Compact searched Current/Archived table, summary metrics, archive controls, Summary detail and real live following |
| C | [TASK-00392](../tasks/00392-TASK.md) | TASK-00391 | Independently loaded immutable Changes and retained Evidence |
| D | [TASK-00393](../tasks/00393-TASK.md) | TASK-00391 | Independent sanitized Activity, bounded search and selected-phase drill-down |
| E | [TASK-00394](../tasks/00394-TASK.md) | TASK-00391, TASK-00373, TASK-00142 | Existing Workflow/Runner controls with current authority and original-outcome reconciliation |
| F | [TASK-00395](../tasks/00395-TASK.md) | TASK-00391, TASK-00265 | Existing Ready-card/keyboard and Pending start navigation/status continuity |

TASK-00390 is the exact early visibility/current-membership and independent-running supplier requested by
TICKET-00096. TASK-00387 now also gains TASK-00396/00397's early measured usage/time and projection integration from
TICKET-00098; its allocation hold is resolved, not operationally qualified. TASK-00390 consumes TASK-00385/source
authority, never
completed TASK-00387, Dashboard or forecasts. Its current-state/safeguard participants precede final projected
summaries; unknown or unqualified active-running visibility rejects active archive instead of assuming a route/count
is sufficient. Inactive visibility operations and early queries do not require the complete browser journey.

TASK-00391 consumes the existing honest Dashboard/frame/theme and sole production `/me` owner, plus TASK-00389's
actual committed live transport and history-copy protections. Stable 25-record cursor results, whole-result safe
metadata search, independent Running now and Summary are one useful browse/follow path. Changes/Evidence and
Activity remain independently loaded extensions with honest unavailable states until delivered; no demonstration
rows, duplicate projection engine, full transcript or per-row history polling is introduced.

TASK-00392 uses the existing TASK-00378 evidence/Artifact queries transitively; every file stays discoverable, bodies
load lazily and retained bytes are never replaced with mutable worktree observations. TASK-00393 consumes actual
TASK-00386/00387 safe phase facts, not raw Pi context. TASK-00394 calls existing semantic operations and consumes
actual TASK-00142/00373 recovery qualification; claim release still requires confirmed quiescence. TASK-00395 reuses
TASK-00205/00265, not another Start form, normal-work confirmation, eligibility rule or pending engine. Its completion
is not a prerequisite of independently useful lifecycle controls.

TICKET-00098 now maps TASK-00398's independent usage/timing breakdowns, TASK-00399's separate allowance and
TASK-00400's explainable forecasts/comparisons into these surfaces. All consume TASK-00391, with early TASK-00396/
00397 facts transitively. TASK-00399 retains the unallocated qualified source/protected-refresh hold; shared quota
is never a Repository balance and its absence blocks neither useful history nor forecasts. Early measured facts
never depend on final Dashboard, TASK-00387 or live consumers. TASK-00320 consumes TASK-00400 for later PR-handoff
forecasting; Review/QA/verdict/publication remains TICKET-00083 and Awaiting review is not acceptance or cleanup.

Actual browser keyboard/focus/announcements, responsive light/dark, reduced motion, failure/recovery states and
current-authority/copy races belong in each delivering slice, alongside guidance, owned tests and `./bin/build`.
Historical prototype screenshots and inherited runtime receipts are not fresh UI qualification. No proof-only TASK
or alternate runtime is created. Existing suppliers gain no reverse browser blockers; terminal priority, active
TASK-00138, independent host/prototype/source/authority holds and Markdown Planning authority remain unchanged.

This decomposition is complete, not runtime delivery. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Checkout, finite runtime/read/control/credential authority,
full build, independent review and publication remain separate; planning performs none of those effects.

### Historical planning verification for the sessions split

The receipt below predates TASK-00396/00397's measurement allocation; latest planning verification is in TICKET-00109.

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
uncommitted planning. Before allocation, the highest live TASK was TASK-00389 and the archive contained no TASK
records. TASK-00390–00395 match the approved metadata/dependencies and all template sections; all six are unranked
features, ready behind unfinished suppliers with blank PRs. TASK-00387 gains only TASK-00390 and stays `needs-info`
for the exact early usage/time allocation. No unknown future dependency is claimed verified.

`./bin/planning-check --write` and `./bin/planning-check` passed with 522 records, 430 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 87 new/untracked TASK
whitespace checks passed, including these six. Explicit checks preserved independent consumer/host/prototype
`needs-info` holds and unchanged TASK-00138/00139/00142/00159/00184/00226 records against this worktree's HEAD.
Existing supplier/entry priority, status and dependency metadata remain intact, including TASK-00052/00205/00265
and previously approved TASK-00373/00378/00389 blockers. No existing producer gains a browser dependency.
Active TASK-00138 remained the only active Board item, order 2. That split advanced handoffs to TICKET-00098, with
TICKET-00099–00109 still to decompose at that point; no stale current TICKET-00097 command handoff remained.

These are planning receipts, not fresh PostgreSQL visibility/running-safeguard, browser accessibility/visual,
live transport, evidence disclosure, Start/control, provider or runtime qualification. Full `./bin/build` was not
run and remains required before commit/publication. All changes remain uncommitted; independent review/publication
are separate. No implementation, private read, visibility mutation, Start/withdrawal/lifecycle/Runner operation,
credential/provider/service effect, cleanup, live Planning mutation, cutover, commit, push, PR or merge occurred.
Unrelated main-checkout work was untouched, not claimed clean. Earlier verification receipts remain historical.

### Earlier supplier request and original requirement approval

The earlier visibility/measurement requests below now map to TASK-00390/00396/00397; independent operational
qualification holds remain. These paragraphs preserve the earlier requirement handoff, not current allocation status.

TICKET-00096's approved TASK-00385/00386 supply semantic history and actual sanitized activity; TASK-00387 owns
whole-result search/phase queries and scope-labelled summaries, TASK-00388 history-copy lifecycle participation,
and TASK-00389 real committed live delivery with reload-first bounded client transport. This TICKET retains browser
components, archive/restore operations, controls and actual keyboard/focus/responsive/light-dark evidence.

TASK-00387 remains `needs-info` pending this TICKET's precise early visibility/current-membership and independent-
running safeguard supplier, plus the separate TICKET-00098 early usage/time allocation. During decomposition, map
that supplier before final Dashboard consumers: it may use TASK-00385/00386 or underlying source/current-state
contracts, but cannot depend on completed TASK-00387 or final presentation. Active archive needs an actual atomic/
consistency participant guaranteeing independent running visibility, not a stale projected query or fabricated
archive flag. Add the exact blocker only after approval; this handoff allocates no further TASK or implementation.
Preserve safe disclosure, existing host/authority holds and terminal-first priority. No whole-browser prerequisite
is added to core history; TASK-00389's narrow Mercure addition reuses the existing installation boundary.

Approved as area 5 of EPIC-00007's remaining six-TICKET split. Applies
[WF-018](../wayfinder/tickets/WF-018-prototype-sessions-first-browser-experience.md),
[WF-017](../wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md), EPIC-00007's search/metrics/
phase-detail amendments, [ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume real shell/authentication, TICKET-00032/00093 commands, TICKET-00094 evidence, TICKET-00096 queries and
TICKET-00098 usage/forecast facts. Decompose the visibility operation early where projections need its contract,
without reversing producers onto completed UI. [TICKET-00052](00052-TICKET.md)/[TICKET-00064](00064-TICKET.md)
retain start/pending ownership; TICKET-00083 extends later managed phases. Browser and forecast delivery are not
new blockers of the terminal-first milestone. No TASK, runtime UI, visibility change or operational effect is
created by this approval, and existing supplier readiness holds and Board priority remain unchanged.
