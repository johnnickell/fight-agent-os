---
id: TICKET-00105
epic: EPIC-00010
title: Inspect effective repository instructions
status: ready-for-agent
---

# Inspect effective repository instructions

## Problem statement

A human needs to know which repository instructions Pi actually loads before changing them. A generic file browser,
Git tracking status or guessed precedence cannot establish the effective instruction set or safe checkout access.

## Solution and boundaries

Provide bounded inspection and its focused Dashboard presentation for exactly five names at the designated checkout
root: `AGENTS.override.md`, `AGENTS.md`, `AGENTS.MD`, `CLAUDE.md` and `CLAUDE.MD`. Use the installed supported Pi
discovery contract to explain loaded versus shadowed files, including same-directory override behavior. Qualify
actual runtime/version semantics during implementation rather than treating WF-022's historical Pi version as fresh
evidence. Unsupported or ambiguous discovery is an explicit limitation, not an invented precedence rule.

List recognized files only and open one bounded text/Markdown file at a time. Show plain-language effective labels,
missing/unavailable state and concise tracked, untracked, ignored, modified, branch and synchronization facts.
Untracked or ignored content may still be loaded by Pi; explain its publication consequence without calling it
ineffective or generated. Supply exact content/digest and safe evidence to TICKET-00106's form and TICKET-00107's
separately authorized assistance. Reading a file never authorizes either use or provider disclosure automatically.

Use the Repository's single designated local Worktree from TICKET-00024. Resolve its actual Repository/Host/Checkout/
Worktree identity, Git common directory/marker, registered path, approved-root containment, Runner eligibility and
current ownership. Do not ask for a clone on each read, silently choose a substitute or access a Workflow-owned
worktree. Explain moved, unavailable, unsafe, ambiguous or conflicting-operation state and its permitted next action.
Existing registration/context owners remain authoritative; this product does not relink or repair a checkout.

Keep the Runner mechanical: exact allowlisted enumeration, bounded reads, digest and filesystem/Git observation.
Application policy interprets qualified effective-file semantics and authorizes access. Preserve actual observed
branch/commit, encoding/size, digest, context revision, Runner and provenance behind readable details. Do not expose
unnecessary internal identifiers, absolute personal paths, storage locators or credentials. Observations are bounded
facts at a point in time, not a reservation, current grant or guarantee that a later write will succeed.

Reject symlinks, non-regular/special files, binary content, unsupported encodings, excessive size, path traversal,
escaped roots and case ambiguity. No recursive scan, nested instructions, parent/user content, arbitrary Markdown/
source files, `.pi/SYSTEM.md`, `.pi/APPEND_SYSTEM.md`, Skills, settings, prompts or Harness resource editing. Bounded
provenance may explain composition without exposing other instruction bodies. Never parse prose into enforceable
application policy or claim an unrecognized convention affects Pi.

Reuse the authenticated repository Dashboard, context projection and visual components. Include truthful empty,
denied, stale, failed and unavailable states, accessible keyboard navigation and readable light/dark/narrow layouts.
Do not create a repository tree, terminal, IDE, second context resolver or standalone inspection demonstration.
Ordinary authorized reads use security/request logging, not a fabricated Domain event per view.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| List recognized root instructions | N/A — read-only | Authorized designated-checkout availability and bounded recognized-file metadata | N/A — inspection is not a business mutation | Safe effective-file list or explicit refusal; no recursive scan |
| Open one file | N/A — read-only | Exact allowlisted bounded content, digest, Git state and provenance | N/A — ordinary read logging only | Current authorized content without broader filesystem access |
| Explain loaded/overridden state | N/A — deterministic read projection | Qualified runtime discovery facts and existing context evidence | N/A — derived explanation creates no context revision | Accurate labels independent of tracked/ignored status |
| Inspect a blocked checkout | N/A — read-only | Safe identity, containment, Runner/ownership and availability reason | N/A — observations confer no authority | Actionable next step without relink, mutation or alternate checkout |

## Validation and permissions

Require current View repository instructions authority and actual Repository/checkout scope on every metadata and
content query. Use TICKET-00024/00025 identity/context and TICKET-00023 root contracts, not caller-selected absolute
paths or a role-name shortcut. Validate strict bounded names, text shape and observation provenance; recheck unsafe
or changed path/identity before reading. Source content is untrusted data and must be rendered inert.

Apply ADR 0003/0004 no-store, current-context response fences, guarded loaders and private-cache invalidation.
Revocation, context change and late responses must not expose retained content. Queries may observe the filesystem
through qualified Runner capabilities but cannot repair, fetch/synchronize, write files or trigger Agent/provider
work. Keep raw exceptions, credentials and unrelated content out of logs, telemetry and browser error details.

## Acceptance and evidence

- Actual designated-checkout inspection shows only the five recognized root names and correctly explains effective
  state under the qualified Pi runtime, including ignored/untracked and override cases without guessed precedence.
- Unsafe paths/types/encoding/size, case ambiguity, moved/conflicting checkouts and denied/revoked authority refuse
  access without reading unrelated files or choosing another worktree.
- Exact authorized content/digest/provenance feeds editing and assistance consumers; stale observations never grant
  subsequent effects. Browser loading/empty/denied/unavailable and responsive/keyboard states use real results.
- Owned inspection/classification/authorization/client behavior tests, direct actual Pi/Git/Runner qualification and
  `./bin/build` pass. Do not test Pi, Git, Markdown prose, library internals, configuration or a generated file list.
  Integrated evidence continues through TICKET-00109's real instruction-change journey.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00430](../tasks/00430-TASK.md) | Inspect effective instructions through bounded designated-checkout queries | needs-info |
| [TASK-00431](../tasks/00431-TASK.md) | Browse effective instructions in the repository Dashboard | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume existing registration/context/Dashboard and exact qualified deterministic Runner contracts, not completed
coding or managed Review journeys. Supply [TICKET-00106](00106-TICKET.md) and [TICKET-00107](00107-TICKET.md)
without depending on final editing/assistance. [TICKET-00109](00109-TICKET.md) owns synchronization facts/operations;
absence of that integration remains explicit, not a reason to invent freshness or reverse-block bounded reads.
Existing supplier holds and Board priority remain unchanged. The requirement approval authorized no TASK, file read
outside planning, runtime discovery, checkout change or live Agent operation. The subsequent approval below now
allocates TASK records only, not operational authority.

### Approved TASK decomposition and current handoff

The maintainer approved two slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. Both are unranked features with blank PRs, normally one independently reviewable PR.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00430](../tasks/00430-TASK.md) | TASK-00082, TASK-00368; protected-read/custody supplier unallocated | Authorized designated-checkout enumeration/single-file query API, qualified effective labels and coherent content/digest/Git/provenance |
| B | [TASK-00431](../tasks/00431-TASK.md) | TASK-00430, TASK-00107 | Recognized-file list and one-file read-only viewer in the existing repository Dashboard, with accessible truthful states |

TASK-00430 starts `needs-info` for the precise protected human-authorized instruction-read route and current
checkout-custody participants under existing TICKET-00032/00093 execution/Runner owners. TASK-00082's discovery and
TASK-00368's enrollment facts do not implement that route. TASK-00369's Workflow resource contracts are relevant
but do not alone qualify this human read scope. No missing supplier ID, Workflow, TASK claim, Agent, alternate
checkout or second ownership registry is invented. Early suppliers must remain independent of completed coding,
Review, editing, assistance, publication or synchronization journeys. Existing runtime holds remain unchanged.
TASK-00431 is ready behind unfinished/held suppliers, not runtime-qualified.

TASK-00430 reuses TASK-00082's sole context/discovery owner and its transitive roots/identity/lifecycle/configuration
contracts. It qualifies actual supported Pi semantics rather than assuming historical precedence; unknown or
unsupported behavior cannot receive a guessed Loaded label. The mechanical Runner returns bounded observations;
Application interprets them under current View instructions authority. All five exact accepted names remain the
allowlist. Digest and returned bytes describe the same bounded observation; physical containment must survive
replacement races, not just lexical/stat checks. Reject unsafe paths/types/encoding/size/case and stale or conflicting
identity/custody without reading unrelated files, relinking or selecting another checkout.

TASK-00431 extends TASK-00107's existing workspace and shared `/me`/frame/theme/transport owners. Render content
inertly with no-store volatile state and late-success/error fences. Show effective versus ignored/untracked status
honestly, disclose safe technical provenance and keep denied/stale/failed/unavailable states useful. Fresh keyboard,
focus/announcement, responsive light/dark, accessibility/reflow and applicable reduced-motion evidence are required.
No viewer creates a draft, grants effect permission or sends content to a provider.

TICKET-00106/00107 consume the exact bounded observations and existing viewer; direct editing and optional assistance
remain separate. TICKET-00108 retains publication; TICKET-00109 retains synchronization facts/operations and the real
integrated instruction-change journey. Unknown remote freshness stays unknown and reads never fetch. Observations
are not reservations, current grants, guaranteed future writes or proof that an existing Agent session reloaded.
No `.pi`, nested/parent/user content, recursive scan, arbitrary file, mutation, provider operation or new resolver.

Each slice includes owned behavior tests, actual qualified Pi/Git/Runner or browser evidence as applicable, guidance
and `./bin/build`. Qualify tools/infrastructure/prose directly, not product tests of Pi/Git, generated lists,
Markdown, configuration or wrappers. No proof-only TASK is introduced and no existing supplier gains a reverse
inspection blocker or has a hold cleared.

TICKET-00106 now maps TASK-00432/00433's exact Save/Delete/reconciliation and Dashboard form, retaining its distinct
protected mutation/shared-custody/physical-guarantee hold. Neither this read contract nor its allocation authorizes
a write; no reverse inspection dependency follows.

This completes this TICKET's split, not implementation or the remaining map. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138, terminal-first Board priority,
independent holds and Markdown Planning authority. Implementation checkout, finite private fixture/read/runtime
authority, full build, independent review and publication remain separate.

### Planning verification

This historical inspection-split receipt predates TICKET-00106's Save/Delete split; latest planning verification
is in TICKET-00109. Counts and handoff state below describe the earlier point, not runtime evidence.

Recorded the approved split in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving
prior uncommitted planning. Highest live ID before allocation was TASK-00429; no archived TASK records existed.
TASK-00430/00431 match the approved exact known blockers, statuses and template sections, both unranked features with
blank PRs. The protected-read/current-custody supplier edge remains explicitly unallocated, not guessed or claimed
cycle-checked; TASK-00430 stays `needs-info`. TASK-00431's ready metadata is not runtime qualification.

`./bin/planning-check --write` and `./bin/planning-check` passed with 558 records, 466 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 123 new/untracked TASK
whitespace checks passed. A before/after hash comparison of every pre-existing TASK's frontmatter confirmed no
status, priority, dependency or PR metadata changed during this recording pass. No existing owner gained an
inspection reverse blocker. Prior mappings and independent creation/support/host/provider/authority holds remain.

TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD. TASK-00138 remains the sole
active Board item, order 2. Context, Runner enrollment/resource and Dashboard coordination records the consumer gap
without changing existing ownership/policy. TICKET-00106/00107 record incoming observations/viewer suppliers, not
approved decompositions or effect authority at that checkpoint. That split advanced handoffs to TICKET-00106,
then TICKET-00107–00109; all four remained on the Planning Frontier and no stale TICKET-00105 command remained.

Full `./bin/build` was not run and remains required before commit/publication. These are planning checks, not
fresh Pi-discovery, Git/Runner/physical containment, PostgreSQL application-participant or browser acceptance.
No live private instruction read, runtime discovery, credential/host/service change, checkout operation, provider
call, Agent session, filesystem effect, Planning import or cutover occurred. All changes remain uncommitted;
implementation, independent review and publication remain outstanding. No commit, push, PR or merge occurred.
Unrelated main-checkout work was untouched and is not claimed clean; earlier receipts remain historical.
