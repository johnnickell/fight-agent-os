# Planning Conventions

## Lightweight work routing

For “What's next?”, `next`, or an unqualified `/ask-matt`, use the routing entry in root AGENTS.md.
This read-only path precedes general orientation; full implementation, review, QA and delivery requirements
still apply when the selected workflow is invoked.

1. Run the documented read-only planning validator once when available. Failure stops selection with the
   actual error and planning-owner handoff; do not refresh views. Without a validator, disclose that limitation
   and check selected record metadata against the authored Board; do not claim generated freshness.
2. Read only the Board's current human decision, active work, and (if no active work) first ready entry.
   Use local section names and authoritative order. Return all active TASKs, not a new winner. Inspect selected
   TASK metadata and its current handoff, plus blocker metadata only as needed. An unresolved contradiction
   is a named input gap, not permission to audit every record or infer acceptance from `done`.
3. Read the Wayfinder index's map-status/frontier inventory. Closed or decision-complete maps need no historical
   reread. For active/open maps, read only current frontier and decision/dependency status or explicit external
   prerequisite evidence needed for availability. Unknown state or an absent frontier needs its owner's input.
   Multiple available maps remain independent choices; no global priority is implied.
4. Use the Board/Roadmap's current planning handoff for unfinished decomposition, including closed maps.
   Verify the named phase in the owning record's current progress/approval section. Map closure, parent status
   and child counts do not prove complete decomposition. If the handoff is missing or contradictory, report
   the missing phase/owner; do not reconstruct it by reading every closed map and descendant.
5. Before repeating a relevant delivery action run `./bin/planning-check --check-delivery` with its marked
   TASK IDs. Verified MERGED/integrated `done` handoffs resolve read-only; continue routing without a routine
   cleanup request. Exceptional/uncertain states hold their affected route; never write or dispatch Land.
6. Read canonical review/QA identity and disposition only when they change the selected route. Unresolved
   identity is an evidence handoff, never acceptance. Confirm a suggested skill from supplied capability
   metadata/bindings; load its full instructions and references when invoked, not merely to recommend it.
6. Before evaluating or repeating a relevant current delivery/merge handoff, require the
   [bounded live check](#live-delivery-verification). Select only subjects relevant to that recommendation;
   do not audit the delivery table or historical PRs. Report verified ended state and the exact Land handback,
   or unverified evidence and recovery, separately from unrelated TASK/Wayfinder/planning routes. Do not edit,
   refresh, invoke Land or let a delivery observation reorder the Board. Historical references alone trigger no
   query; after Land retires an ended current claim, preserved evidence does not keep blocking selection.

Prefer heading searches and bounded section reads. Aim to retrieve no more than about 24,000 characters
of routing evidence; stop with a precise unresolved input before exceeding that scope unless the user requests
a deeper audit. Do not read append-only task histories, other worktrees' entire tasks, general architecture,
or all receiving skills to produce a recommendation. Return TASK, WAYFINDER, and relevant PLANNING/HUMAN INPUT;
stop without invoking a workflow or changing files. Missing indexes are reported, not fabricated.

During authorized planning updates, keep current Board/Roadmap handoffs source-linked and concise: target,
phase, pending decision/owner. Keep Wayfinder inventory/frontier pointers current. These are navigation aids,
not another backlog or proof of delivery. Preserve generated sections and all historical decisions.

Routing sources: [TASK Board](tasks/BOARD.md), [Wayfinder inventory](wayfinder/README.md), [Roadmap](ROADMAP.md).

Individual Markdown records own requirements, status, dependencies, and execution priority. Boards, indexes,
and Roadmap status tables are generated views. Authored strategy and decision narratives remain editable.

## Hierarchy and paths

| Level | Responsibility | Path and displayed ID |
|---|---|---|
| EPIC | Destination, business outcome, and boundaries | `planning/epics/00001-EPIC.md` · `EPIC-00001` |
| TICKET | Related use cases and requirements | `planning/tickets/00001-TICKET.md` · `TICKET-00001` |
| TASK | Bounded implementation, normally one PR | `planning/tasks/00001-TASK.md` · `TASK-00001` |
| SUBTASK | Dependency-ordered implementation assignment | Ignored `.runs/` material belonging to a TASK |

Each level has its own five-digit sequence. Preserve numbers and gaps; never reuse archived identities. Inspect
both live and archived records before allocating the next ID. This is a fresh planning surface; no source-project records or identifiers are imported. WF decision IDs have their own sequence.

Every artifact directory keeps a copy-ready `_…_TEMPLATE.md`. Templates are not records and receive no ID. ADRs
remain in `adr/` with `NNNN-description.md` names; focused agent instructions remain in `agents/`.

Grill writes the EPIC only. Decompose it into TICKETs, then TASKs before implementation. Record use cases,
commands, queries, events, expected side effects, validation, permissions, and observable acceptance evidence.
Explain concerns that are not applicable rather than silently omitting them.

A TASK normally delivers a complete use case. Layer-based SUBTASKs coordinate Domain, migrations, Application,
adapters, and UI according to dependencies. A substantial SUBTASK may have a separately planned PR; the parent
TASK owns complete acceptance and records the PR dependencies. Small bugs and chores may be standalone TASKs
with `kind: bug` or `kind: chore` and an empty `ticket` field. Do not reopen an archived parent for unrelated work.

## Delivery value before decomposition

Every proposed TASK must name the accepted product outcome it delivers or the concrete delivery blocker it
removes, with an existing owning EPIC/TICKET and observable user/team benefit where applicable. State that
connection in the proposal and record's Outcome/Coordination; do not add a parallel scorecard or planning queue.
Package API coverage, hypothetical reuse, demonstration screens and proving that infrastructure exists are not
sufficient reasons for new work. Keep necessary safety, reliability and verification within the real delivery
path; this rule does not waive them. Reuse existing owners and defer optional surface area without a current use.

Follow the [approved blended delivery strategy](ROADMAP.md#approved-blended-delivery-strategy): terminal,
browser and shared-foundation work may interleave through their real dependencies. The Board's TASK metadata
still owns execution priority; strategy prose does not reorder it. Preserve the first managed implementation
proof: one approved TASK through Team Lead and Software Engineer to durable Awaiting review. Later product
uses do not automatically become prerequisites to earlier outcomes, and early suppliers must not depend on
completed downstream journeys. Scope, priority or dependency changes need recorded human approval; do not
silently remove requirements, clear qualification holds or mass-close planned work. No TASK should be created
merely to demonstrate this rule.

## Upstream capability qualification

While Fight Access Control is pre-1.0, future consumer plans and UI dependencies name required capabilities,
invariants and public contracts, not a prospective package version. At implementation, inspect available tagged
releases, qualify the chosen release against those requirements and existing consumer integrations, and record
its exact version, source commit and Composer lock reference. Commit the deliberate manifest/lock update so
installation remains reproducible; capability-based planning does not mean a floating runtime dependency.
A missing guarantee blocks dependent behavior, not permission to infer it from a version number. Preserve
historical versioned evidence and current-lock facts as such; neither selects the next release to adopt.

## Metadata and lifecycle

```yaml
---
id: TASK-00103
ticket:
kind: chore
title: Adopt the EPIC, TICKET, and TASK planning surface
status: in-progress
order: 1
blocked_by:
pr:
---
```

TICKETs require an `epic: EPIC-NNNNN` parent. TASKs use `ticket: TICKET-NNNNN`, except standalone bugs/chores.
`blocked_by` holds comma-separated TASK IDs. Preserve edges after completion; unfinished blockers are derived.
`order` is an optional positive priority number, lower first. Unranked rows come last, with IDs breaking ties
only for deterministic display. `pr` is an optional full PR URL, not an assertion about live GitHub merge state.

| Status | Meaning |
|---|---|
| `needs-triage` | Scope or ownership is unclassified |
| `needs-info` | A decision or required evidence is missing |
| `ready-for-agent` | Decision-complete and executable when dependencies permit |
| `ready-for-human` | Human judgment or an external action is next |
| `in-progress` | Implementation or revision is underway |
| `done` | Implementation acceptance and required acceptance verification are complete; delivery is separate |
| `wontfix` | Intentionally closed without implementation |

Blocking is derived, not a stored status. `done` does not assert merge or deployment: record those effects and
evidence explicitly. Mark a TASK done once implementation acceptance and all required acceptance verification are
complete. The canonical local gate is mandatory; hosted checks are optional unless an accepted repository or TASK
requirement explicitly makes them an acceptance gate. A workflow file or repository visibility alone does not
create that requirement. When required hosted acceptance proof needs a PR, publish an authorized draft under
[landing standards](../docs/engineering/LANDING.md#draft-publication-to-obtain-hosted-evidence) while the TASK
remains incomplete. For subsequent completion metadata, follow the
[acceptance-candidate and administrative-closeout contract](../docs/engineering/LANDING.md#acceptance-candidate-and-administrative-closeout):
`done` records the already-accepted implementation candidate; final-head delivery checks can remain pending only
when the accepted policy permits that split. A literal-final-head acceptance prerequisite needs an authorized
owner amendment, not a silent exemption. Record independent review, delivery, merge, release, and deployment
separately; done does not grant approval for those actions.

## Current delivery handoffs

A live (non-archived) TASK may carry this optional frontmatter:

```yaml
delivery: awaiting-merge
pr: https://github.com/johnnickell/fight-agent-os/pull/162
```

This is a representation example, not a declaration about PR #162. The subject is the record's `id`, bound
by its canonical filename and unique identifier. The exact canonical GitHub `pr` URL binds repository and
positive decimal PR number (no leading zero, query, fragment or trailing slash). For marked records only,
its repository must match local `origin`, supported as `git@github.com:owner/repository.git` or
`https://github.com/owner/repository.git` (optional `.git`). Comparison is exact/case-sensitive; aliases,
credential-bearing URLs and other hosts/transports need local identity resolution, not a guessed repository.
Two marked subjects cannot claim the same PR. Empty/absent `delivery` means no current delivery claim;
other values, marked parents/archives and missing/mismatched PR identity are errors.

Land owns setting/correcting markers; authorized Work/Land/substantive planning intake owns routine retirement
under the [subject synchronization contract](../docs/engineering/LANDING.md#synchronize-current-subject-handoffs).
Set it only from exact publication evidence at the authorized human review/merge handoff. It records a
publication snapshot, **not** an assertion that the PR remains open, readiness/CI success, independent acceptance,
merge or deployment. Status remains independent: `done`, ordinary `pr` links and historical prose never create
markers. Do not backfill markers by scanning old completion notes or promote an example into current authority.
Keep applicable authored current delivery handoffs pointed at the subject and generated delivery section;
resolve contradictions through Land rather than maintaining a competing live instruction.

The Board and Roadmap `planning:delivery` tables derive only from these markers, sorted by subject ID. An empty
table means no **recorded** current delivery handoffs, not proof that no PRs exist. These tables do not select
work, reorder the Board or replace the distinct planning/decomposition handoff and frontier. README points to
the Board's delivery section; historical PR/acceptance/checkpoint bytes remain evidence, not live authority.

| Supported command | Effect and failure/recovery |
|---|---|
| `./bin/planning-check` | Offline, read-only identity and view validation. Malformed/conflicting identity fails; stale views fail with the explicit refresh command. Success and failure write nothing. |
| `./bin/planning-check --write` | Offline, explicit parent rollup and generated-view refresh after validation. Identity or structural errors prevent planned writes. Reports actual updated paths; it never sets/retires markers or repairs history. Inspect disk state after an I/O failure before retry; this is not a filesystem transaction. |

Neither path invokes hosting tools, fetches, needs hosting credentials or claims live PR freshness. Local origin
inspection is a bounded five-second Git configuration read only and is skipped when no markers exist. A missing
origin holds marked identity validation; restore the intended repository configuration through its owner.
Land resolves malformed identity before an authorized refresh; read-only routing never performs that refresh.
The explicit live modes below are separate from offline checks and writes. At authorized Work/Land/substantive
planning intake run `./bin/planning-check --reconcile-delivery [TASK-ID …]` before record edits. It verifies exact
merged/integrated `done` snapshots, retires only resolved markers with exact PR/base/head/merge/time/local-HEAD
evidence, rolls up eligible parents and refreshes views. Inspect affected authored Board/Roadmap/README claims
separately; preserve historical checkpoints and canonical reports. No separate routine correction prompt/PR is
required. Repeat intake is a no-op and retired history triggers no queries. A later merge resolves read-only until
the next authorized mutation; no watcher or post-exit update is implied. Validation/live errors prevent planned
writes; inspect disk after I/O failure before retry. Review/QA/routing never use the writing mode.

### Live delivery verification

Before Land or read-only routing evaluates a relevant delivery/merge handoff, run:

```sh
./bin/planning-check --check-delivery TASK-00517
```

This is a real subject example; use the actual relevant marked TASK IDs. `--check-delivery` with no IDs checks
all explicit current markers, not historical PR links. Prefer explicit IDs for routing. The tool first requires
successful offline validation and fresh generated views; on failure, no hosting lookup occurs. Routing never
runs `--write` to recover freshness. Unknown, duplicate or unmarked requested IDs fail without hosting calls;
resolve the current subject with Land, rather than promoting historical evidence into a marker.

Bounds: maximum **16 selected subjects**, one sequential `gh pr view` invocation per exact PR, **15 seconds**
per invocation (at most 240 seconds of hosting-command waits), no checker retries/polling or auth preflight.
The CLI may make multiple underlying HTTP requests; 16 is a CLI lookup bound, not a wire-request guarantee.
Local origin lookup has its existing five-second timeout. Responses above **16,384 UTF-8 bytes** are rejected
(after capture). Existing operator `gh` access is used noninteractively, with explicit `github.com` host/repository;
no credentials are printed or grants changed. No marker means zero hosting commands, including without `gh`.

The canonical URL/repository/number, exact TASK ID and title, `develop` base, subject-owned
`feature/task-NNNNN-<slug>` head and full 40-hex head OID must match. All requested state/merge fields must be
present. MERGED additionally requires a valid UTC merge time and full merge OID; OPEN/CLOSED require null
merge time/commit. Mismatched or contradictory evidence is never an observation of a trusted PR state.
This verifies the recorded subject and advertised branch, **not** an accepted-candidate/remote-head provenance
bridge: Land still compares exact recorded base/head/OIDs, fork identity, independent reports and delivery holds
under [landing standards](../docs/engineering/LANDING.md#synchronize-current-subject-handoffs).

| Result | Meaning and next action |
|---|---|
| OPEN | Verified observation only. Evaluate actual acceptance, QA, provenance and delivery holds before recommending the supported human/Land action; open alone proves no readiness or CI success. |
| MERGED | Resolve read-only only for an already-`done` subject with exact published-head → merge → local-HEAD ancestry, using five-second local Git calls without replacement objects/lazy fetching. Report exact merge/time/integration and continue; no routine cleanup request. Missing integration/nonterminal scope holds for the Git/requirement owner. Merge is not acceptance, QA or deployment. |
| CLOSED WITHOUT MERGE | Report non-delivery through that PR and the same explicit Land correction handback. Preserve acceptance; maintainer owns any future-delivery decision. |
| UNVERIFIED | Hold only the affected delivery recommendation. Restore missing CLI, existing authentication/access/network or complete exact PR metadata; Land resolves identity contradictions. Rerun the same subject after recovery, never assume open/merged or silently use offline success as freshness. |

Exit **0** means selected observations are OPEN or verified merged/integrated `done`, or no markers exist
(not proof that no PRs exist). Exit **1** means a local/live evidence, scope, integration or ended-state hold.
Exit **2** means invalid command-line arguments or selection/bounds rejected before hosting requests. Inspect per-subject output: no aggregate success overrides an unverified subject. Raw hosting
stdout/stderr is never replayed on failure. The command writes no records/views, invokes no Land, scans no
history, fetches/publishes/merges nothing, and checks no CI. `--write`, `--check-delivery` and `--reconcile-delivery` are mutually exclusive.
Ordinary check/write and `./bin/build` planning remain network-independent. A correctly reconciled unmarked
historical PR is not queried again merely because its evidence remains recorded.

### Qualified reference

Read-only inspection of Fight Common at source commit `2c4d1d529f6057f0a4ef2d7483b92d0327a58403`
qualified `scripts/planning_portfolio.py` and its current-delivery conventions: explicit live-TASK marker plus
PR, generated Board delivery block, ordinary offline check/write, and local origin matching.

TASK-00518 separately inspected Common at `59ba43b40cfa6fee681f6dde2bfe075aeb1f2aa3`: its
`--check-delivery` validates at most 16 markers, uses one 15-second `gh pr view` per PR, checks URL/number,
exact title, develop base, subject branch, full head OID and complete consistent merge metadata, and rejects
responses over 16,384 characters. Common additionally checks local ancestry with five-second local Git calls,
disabled replacement objects/lazy fetching and no fetch; merged/integrated `done` subjects can resolve read-only.
Its `--reconcile-delivery` writes marker retirement and views; its closed-unmerged/incomplete output names a
planning owner. These are qualified **source observations**, not fresh Common execution or fleet acceptance.

TASK-00519 adapts Common's integrated-merge resolution and explicit retirement while retaining Agent OS's
canonical marked-PR/conflicting-subject validation, targeted selection, byte-counted response bounds, separate
Board/Roadmap delivery tables and existing selection/parent/frontier authority. The earlier TASK-00518 receipts
remain historical evidence of their original scope, not acceptance of this prospective extension. Common's
source observations alone are not fleet acceptance; TASK-00519 owns all nine active project outcomes.

## Automatic parent completion

Closing a TASK includes closing its eligible ancestors in the same operation. When a TICKET has at least one
TASK and every child is `done` or `wontfix`, close the TICKET; apply the same rule from TICKETs to their EPIC.
Use `done` when any child is `done`, and `wontfix` when every child is `wontfix`. Preserve the children's recorded
outcomes and exclusions; a mixed result does not claim that declined work was implemented. Include live and
archived children, using their `ticket`/`epic` metadata rather than a stale generated table. Empty parents and
parents with unfinished children remain open. Already-terminal parents and archived records are not rewritten.

This rollup is part of TASK closure, not a separate review, approval, QA, skill invocation or user handoff.
`./bin/planning-check --write` performs it before refreshing views, including eligible parents left open by older
workflows. Include those parent status changes and generated views in the same change as the TASK closure.
The read-only check reports unsynchronized parent status with that same repair command; it never asks for a
closeout review. Child evidence supplies the parent completion record; no duplicate evidence ceremony is needed.
Requirements that need implementation or validation belong in TASKs before the final child closes, not in a
second parent-acceptance gate.

This rule supersedes the separate parent-closeout procedure in WF-010. It does not alter TASK acceptance or
Won't Fix decisions, and does not archive, merge, release or deploy anything. Wayfinder decision/map closure
retains its own rules.

## Board and generated views

`tasks/BOARD.md` shows Active Work, Ready Frontier, Waiting, Needs Info, Human Action, Needs Triage, and Recently
Closed. Every section shows Order, TASK ID, Title, Parent TICKET, Status, Blocked by, and PR. Parent cells show ID
and title. Artifact and blocker IDs are visible links. Archives have separate indexes.

For "What's next?" or `/ask-matt`, return the current human decision/question and active TASK; otherwise return
the first executable TASK in Ready Frontier. Do not start a second TASK merely because its ID is lower. If there
is no executable work, say so. Execution priority is authored in record metadata rather than generated rows.

`ROADMAP.md` retains strategy and milestone narrative; its EPIC status table is generated. Standalone chores
appear on the Board without inventing an EPIC. Live EPICs and TICKETs have generated child tables. Archived
progress prose remains historical completion evidence, not a live status source.

`ROADMAP.md` also generates a Planning Frontier for every non-terminal EPIC without TICKETs and every
non-terminal TICKET without TASKs. Completed children close their parents automatically; there is no separate
parent-closeout queue.

Generated sections use `<!-- planning:NAME -->` and `<!-- /planning:NAME -->`. After editing source records:

```bash
./bin/planning-check --write
./bin/planning-check
```

The first command validates records and links, closes eligible parents, then refreshes marked sections. The second is read-only and fails
on stale views, invalid identifiers/parents, missing links, and dependency cycles. `./bin/build` already runs
the read-only check and must not rewrite planning as a side effect. Verify documentation and tooling directly;
do not add tests of Markdown, wrappers, configuration text, or planning tooling to the product suite.

## Wayfinder

Maps describe uncertain planning destinations. Decision tickets remain `WF-NNN-description.md` under
`wayfinder/tickets/`; they are distinct from requirements TICKETs and implementation TASKs. Research remains
under `wayfinder/research/`.

Use `_MAP_TEMPLATE.md`: Active/Closed status, destination and done condition, notes, linked decision summaries,
decision table, blocking relationships, one Frontier, remaining fog, and exclusions. Decisions use
`_WAYFINDER_TICKET_TEMPLATE.md` with Map, Labels, Mode, Status, and Depends on links.

The generated map table includes every decision owned by that map, including closed decisions, with visible
WF IDs, title, type, mode, current status, and dependency IDs. Data comes from decision records. Authored Frontier
and decision summaries retain their meaning. A Closed map has no decision frontier and links to its implementation
handoff. Wayfinder indexes derive state from maps. Do not invent a frontier when all maps are closed.

## Map-wide planning order

For a selected Wayfinder map, prefer finishing each planning phase across the whole accepted map scope before
starting the next: resolve its decisions/grilling and write all resulting approved EPICs, then decompose all
those EPICs into accepted TICKETs, then decompose all those TICKETs into TASKs. Do not descend from the first EPIC
to TASKs while the same map still has unsettled destinations or unwritten EPICs. Respect decision dependencies,
the authored frontier and any explicit human choice to work a narrower slice or different sequence; this is the
default planning preference, not permission to bypass a blocker or invent a new dependency between records.

Use the map's decision summaries and linked handoff to identify its approved destinations and resulting records.
Distinguish decisions resolved, EPIC written/approved, TICKET decomposition accepted and TASK decomposition
accepted; parent `done` is implementation closeout, not a planning-phase marker. An existing child table alone
does not prove the full intended decomposition was accepted. Trace the agreed scope, exclusions and accepted
records; reuse live/archived artifacts instead of allocating duplicates. If mapping or completion is uncertain,
name that decision rather than claiming the phase finished. Record authorized planning outcomes in the existing
map/handoff and owning records, without introducing a parallel queue or rewriting historical approvals.

A Wayfinder decision can resolve to zero, one or several future EPIC destinations; closing a decision does not
itself authorize creating an EPIC. A Wayfinder interview stays inside that decision's resolution boundary;
a grill-mode label alone does not invoke the EPIC-producing grill skill.
An EPIC-producing grill creates one approved EPIC per invocation, then recommends the next destination in the
same map. Complete the map's remaining decisions and EPIC handoffs before recommending TICKET decomposition,
unless the human explicitly selects an earlier slice. A Closed map may still have an unfinished linked EPIC
handoff; it needs no new decision frontier or reopened interviews merely to finish that handoff.

When all EPICs in that scope have accepted TICKET decompositions, recommend `to-tasks` for the next accepted
TICKET. Continue across the map's remaining TICKETs before recommending implementation selection through `next`.
Stop for each required human approval unless already provided; recommending the next phase never runs it.
The TASK Board still owns execution order, including existing active work unrelated to this map. Standalone
EPICs/TICKETs need not wait for unrelated maps, and multiple maps have no implicit global priority.

Use the [skill handoff rules](../docs/engineering/HANDOFFS.md) to return an explicit, copyable `Next:` command for
the selected target or the precise human/prerequisite action when no command is ready.

## Archive operation

Archive only on an explicit request, never as a completion side effect. Use the owning tool rather than moving
records by hand; inspect its dry run before applying it:

| Operation | Command shape | Destination |
|---|---|---|
| TASKs | `./bin/archive-planning tasks TASK-00001 … [--apply]` | `tasks/archive/` |
| TICKETs | `./bin/archive-planning tickets TICKET-00001 … [--apply]` | `tickets/archive/` |
| EPICs | `./bin/archive-planning epics EPIC-00001 … [--apply]` | `epics/archive/` |
| Wayfinder map | `./bin/archive-planning wayfinder map-name [--apply]` | Existing Wayfinder archive directories |

A selected TASK must be terminal. TICKETs additionally require all children terminal; EPICs require all TICKETs
terminal. A Wayfinder map must be Closed with all decisions Closed, no frontier, and a linked implementation
handoff. Archive moves preserve records, repair local Markdown references, and refresh generated views.

## Branches and completion

For a new TASK branch, use `feature/task-NNNNN-<slug>` from `develop` (five-digit TASK ID and concise lowercase
hyphenated slug); never commit directly to `develop` or `main`. Preserve established branches rather than renaming
or rewriting them to comply retroactively. TASK PR titles use `TASK-NNNNN — <TASK title>`; the repository's
`.github/pull_request_template.md` supplies an editable body, not the title. Choose the main checkout or an
isolated worktree with the user. Ignored `.runs/worktrees/`, `.runs/notes/`, `.runs/handoffs/`, and
`.runs/archive/` hold local execution material; use the [ignored scratch layout](../docs/engineering/SCRATCH.md) for new runs and retention exceptions. Durable requirements and outcomes belong in planning records.

Before a commit or PR, record verified acceptance and outstanding review honestly, update the PR link if known
and refresh views. Use focused owning checks during iteration and one complete final `./bin/build` for changed
implementation. Apply [gate evidence reuse](../docs/engineering/EXECUTION.md#gate-evidence-reuse) across roles,
commits and administrative-only planning/PR updates; validate their exact diff, planning and applicable live
provenance rather than repeating the full suite. Required hosted/literal-final-head contracts still bind.
Surface warnings, notices and deprecations. Release certification, deployment, and runtime enrollment remain separate operations.
