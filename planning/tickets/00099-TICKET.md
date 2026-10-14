---
id: TICKET-00099
epic: EPIC-00009
title: Manage trusted immutable starter releases
status: ready-for-agent
---

# Manage trusted immutable starter releases

## Problem statement

A user needs a known, reproducible starter rather than a mutable working tree or an arbitrary repository whose
identity substitutions and executable preparation are unknown. Catalog updates must preserve ongoing attempts
without silently changing generated projects.

## Solution and boundaries

Register the five accepted managed GitHub sources: `johnnickell/project-symfony`, `project-slim`, `project-laravel`,
`project-yii` and `project-codeigniter`. Permit an authorized Workspace administrator to register an accessible
public or private GitHub custom source under the same rules. Private source access uses the Workspace's protected
server-side GitHub connection; it does not expose source credentials to archive bytes, generated projects or Agents.
Custom starters may use any language, framework or architecture compatible with the bounded generation/Runner contract.

Offer only the newest stable SemVer tag at or above `1.0.0`, excluding prereleases and historical-version selection.
Resolve exact provider/source identity and commit, obtain the tracked source archive, verify its digest and retain
an immutable installed snapshot. The tag is the maintainer's support declaration, not Agent OS certification.
Record provenance and declared compatibility without claiming framework, architecture or quality approval.

Require a small versioned generation manifest declaring exact supported identity substitutions and at most one
optional preparation command. Own the shared manifest schema, validation and immutable identity consumed by
TICKET-00100's generation and TICKET-00101's preview. Reject missing, invalid, ambiguous, changed or unmatched
contracts rather than guessing. Snapshot installation must establish that declared targets and expected matches
are valid; actual generation rechecks them against the pinned bytes and selected values. A manifest is not permission
to execute anything during catalog inspection or installation. Exact schema fields remain implementation detail.

Fail refresh closed if a previously known tag resolves to another commit or archive digest. Never silently replace
trusted bytes; require a newer stable tag or explicit operator resolution with attributable evidence. New releases
apply only to future attempts. Archived, removed or withdrawn entries are unavailable to new attempts; preserve
snapshots required by active or failed attempts and never modify generated projects. Local generation may use an
installed eligible snapshot during a GitHub outage; catalog installation/refresh reports connectivity needs and
never substitutes another revision. Do not claim other network-dependent stages work offline.

Allow authorized starter-to-recommended-profile associations using existing Workspace-owned managed/custom Harness
templates. Expose the exact selected revision to preview/receipts. Profiles and Skills remain independently versioned;
repository content cannot add or activate them, and selection grants no Permission or Agent authority. Consume
TICKET-00075/00076 template authority and TICKET-00034 profile resolution, not a second template registry.

Exclude starter source development, release automation, source-repository edits, certification, automatic project
upgrades, arbitrary unmanifested repositories, non-GitHub source support, multi-workspace isolation and a new general
package registry. The first eligible managed release is a separate maintainer action, not a catalog side effect.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Register or revise a starter entry | Authorized managed/custom catalog operation with expected state | Accessible exact source, current entry and conflicts | Committed owned catalog facts | Stable scoped entry; no project or source release |
| Install or refresh a stable snapshot | Install/reconcile the original snapshot operation | Release eligibility, immutable identity, manifest and prior outcome | Verified snapshot installation or conflict facts | Bounded protected source retrieval and retained verified bytes |
| Withdraw or resolve a conflict | Explicit withdrawal or operator resolution | Current references, held snapshots and changed-tag evidence | Committed lifecycle/resolution facts | Future availability changes; historical bytes/projects stay intact |
| Associate a recommended profile | Expected-state association change | Current authorized template/revision and scope | Committed association fact | Recommendation only, no Agent or Permission grant |
| Select an available starter | N/A — read-only selection queries | Authorized catalog, provenance, manifest, revision and unavailable reason | N/A — browsing is not installation or execution | Truthful preview inputs with no automatic fetch/substitution |

## Validation and permissions

Enforce current operation-specific catalog/association administration and source/read access at the server; an
administrator role name or source URL is not authority. Validate canonical provider identity, bounded tags, strict
SemVer ordering, schema/version, archive size/content, safe relative targets, uniqueness, scope and expected state.
Reject traversal, unsafe archive entries and secret-bearing source URLs; never extract into a trusted project root
or run source commands while validating. Do not broaden protected GitHub access into an arbitrary network proxy.

Use PostgreSQL for owned identity, current selection, reference/retention and audit consistency under ADR 0002.
Commit required intent before external retrieval; reconcile uncertain outcomes and verify bytes before declaring
installation. Keep secrets and raw provider errors out of views, events and ordinary logs. Snapshot storage may
reuse qualified private storage capabilities, but catalog eligibility and release retention remain this owner.

## Acceptance and evidence

- Managed and custom public/private entries follow identical eligibility, manifest and immutable-tag rules;
  malformed contracts, changed tags/digests and unavailable sources fail truthfully without substitute versions.
- Refresh, withdrawal and competing selection/reference changes preserve attempts and existing generated projects.
  Installed eligible bytes support local generation without a fresh GitHub fetch.
- Profile associations retain exact revisions without source-driven activation or grants. Private source credentials
  never appear in snapshots or results.
- At least one real managed stable release `>=1.0.0`, preferably `project-slim`, is installed with its valid manifest
  for TICKET-00103's actual create/register journey. Historical inspection or an empty catalog is not fresh evidence.
- Owned catalog/manifest/PostgreSQL behavior tests, direct source/storage qualification and `./bin/build` pass.
  Do not test starter quality, package-manager internals, release tooling, configuration or generated file layout.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00401](../tasks/00401-TASK.md) | Administer the scoped starter catalog | ready-for-agent |
| [TASK-00402](../tasks/00402-TASK.md) | Install verified immutable starter snapshots | needs-info |
| [TASK-00403](../tasks/00403-TASK.md) | Install private starters through protected GitHub access | needs-info |
| [TASK-00404](../tasks/00404-TASK.md) | Withdraw starters and resolve conflicts without losing attempts | ready-for-agent |
| [TASK-00405](../tasks/00405-TASK.md) | Associate exact recommended development profiles | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved five bounded slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked feature records with blank PRs. A/D/E are now `ready-for-agent` behind
unfinished suppliers; B/C retain distinct explicit prerequisites. D's sole allocation hold resolves through TASK-00411. Planning is not implementation or live readiness.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00401](../tasks/00401-TASK.md) | TASK-00078, TASK-00117 | Scoped protected catalog administration and truthful unverified/unavailable inputs |
| B | [TASK-00402](../tasks/00402-TASK.md) | TASK-00401, TASK-00374 | Public immutable snapshot installation/refresh, shared validated manifest and early pin/reference fences |
| C | [TASK-00403](../tasks/00403-TASK.md) | TASK-00402; protected credential/access supplier unallocated | Actual private GitHub source integration through the same verified pipeline |
| D | [TASK-00404](../tasks/00404-TASK.md) | TASK-00402, TASK-00411 | Withdrawal and explicit conflict resolution with actual attempt retention |
| E | [TASK-00405](../tasks/00405-TASK.md) | TASK-00401, TASK-00249 | Exact existing managed/custom template recommendations and protected preview inputs |

TASK-00401 registers the five managed sources and custom entries without claiming eligibility from registration.
TASK-00402 owns newest-stable selection, exact source/tag/commit/digest, safe bounded archive handling and the shared
versioned manifest. Preparation declarations remain inert; TICKET-00100 independently rechecks pinned bytes/targets/
values before generation. Source commands never execute during catalog inspection or installation. Known-tag drift
fails closed, with no automatic older-version fallback or trusted-byte replacement.

TASK-00402 reuses TASK-00374's narrow qualified private-storage capability, not Workflow-specific Artifact admission
as catalog authority. Snapshot scope, eligibility, reference/retention policy and actual Workspace integration stay
here. No new Workflow, Repository, Agent or grant is fabricated to store source bytes; no second storage stack or
full browser installation is required. Public installation remains independent of TASK-00403 private access.

**TASK-00402 remains `needs-info`** until fresh availability/provenance of a real managed stable release `>=1.0.0`
with a valid manifest is established, preferably `project-slim`. Historical inspection proves neither current absence
nor availability. Its actual installation belongs inside this slice; source publication requires a separate maintainer
action, not Agent OS release automation or a proof-only TASK. TICKET-00103 still owns the real create/register proof.

**TASK-00403 remains `needs-info`** until the exact protected Workspace GitHub credential/access supplier is mapped.
Neither TASK-00315's Release Manager publisher nor eventual TICKET-00102 project publication establishes this read
boundary or enrollment. Actual source/credential qualification remains required after mapping. Ordinary queries
never contact GitHub, authenticate, repair or invoke a provider; private failure does not block public/local paths.

**TASK-00404 is now ready behind TASK-00402/00411** after TICKET-00101 mapped its early actual admission/reference
participant. Only that allocation hold resolves; real composition and supplier prerequisites remain. TASK-00402 provides caller-transaction pin/reference/eligibility
fences first; early admission must not depend on completed lifecycle, browser or integrated creation. Withdrawal
closes new admission atomically while preserving active/failed references. Explicit conflict resolution retains
old bytes and attributed evidence; no automatic purge, compensation or project upgrades are introduced.

TASK-00405 consumes TASK-00249/00299 rather than a second template registry or user-owned Harness preferences.
TASK-00300 remains custom-template publication, not a mandatory completed editor for association primitives.
Qualify actual Workspace-only read/reference composition before Repository creation; do not fabricate context or
clear existing scoped-read/Triage holds. Recommendations and selected revisions remain distinct, independently
versioned and non-authoritative: no Agent creation, Skill activation, Permission grant or source-driven profile.

Protected API/console outcomes are independently useful; TICKET-00101 retains the browser form/preview and
TICKET-00103 the final context/receipt. Meaningful owned behavior tests, actual source/storage/participant
qualification, guidance and `./bin/build` stay within delivery. Fixtures do not qualify live providers, actual
attempts or source releases. No terminal producer or existing identity/storage/profile owner gains a reverse blocker.

TICKET-00100 now maps TASK-00406–00410: local feasibility, owned generation, fresh initial Git, optional preparation
and separate local cleanup. They consume TASK-00402's shared pinned inputs without clearing its real-release hold.
TICKET-00101 now maps TASK-00411/00412/00413 early attempt/stage/cleanup participants, with TASK-00414–00417 actual
execution/browser/recovery/cleanup consumers. TASK-00404 gains TASK-00411's retained-reference integration. Protected local host/process/egress gaps remain; no completed consumer reverse-blocks this catalog.

This completes this TICKET's split only. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138, terminal-first priorities and all independent source/host/
profile/authority holds. Markdown remains Planning authority until separately approved verified cutover. Checkout,
finite source/credential/storage effects, full build, independent review and publication remain separate.

### Planning verification

This historical catalog-split receipt predates TICKET-00100's local-stage split; latest planning verification is
in TICKET-00109.

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving all prior approved
uncommitted planning. Before allocation the highest live TASK was TASK-00400 and the archive contained no TASK
records. TASK-00401–00405 match the approved metadata, known blockers and all template sections. All are unranked
features with blank PRs; A/E are ready behind unfinished suppliers and B/C/D retain the accepted release, protected
source-access and early attempt/reference prerequisites. Unknown future supplier edges are not claimed verified.

`./bin/planning-check --write` and `./bin/planning-check` passed with 532 records, 440 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 97 new/untracked TASK
whitespace checks passed, including these five. Explicit checks preserved existing identity/storage/profile and
terminal dependency metadata/priority, TASK-00249/00299's scoped-read/Triage holds, independent host/prototype/
provider/current-authority holds and TASK-00399's quota gap. Previously approved TASK-00387/00320 measurement/
forecast mappings remain unchanged. No existing supplier gains a reverse creation dependency.

TASK-00138/00139/00142/00159/00184/00226 remain byte-for-byte unchanged against this worktree's HEAD. TASK-00138
remains the only active Board item, order 2. That split advanced handoffs to TICKET-00100, with TICKET-00101–00109 still
to decompose at that point; no stale current TICKET-00099 command handoff remained. Incoming catalog/manifest/reference/profile
coordination is recorded in TICKET-00100/00101/00103 without allocating their TASKs or completing those splits.
Earlier requirement-phase and measurement receipts remain historical, not fresh source/runtime evidence.

These are planning receipts, not actual starter-release availability, source/credential access, snapshot/storage,
manifest execution, PostgreSQL catalog/attempt/profile integration or browser qualification. Full `./bin/build`
was not run and remains required before commit/publication. All changes remain uncommitted; independent review,
publication, source release and live enrollment remain separate. No implementation, source/private-content/
credential read, provider request, snapshot download, catalog mutation, project generation, cleanup, live Planning
mutation, cutover, commit, push, PR or merge occurred. Unrelated main-checkout work was untouched, not claimed clean.

### Original requirement approval and supplier handoff

Approved as area 1 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume early Workspace identity from [TICKET-00024](00024-TICKET.md) and exact existing Harness template/profile
contracts, not completed Agent journeys or browser editors. Supply [TICKET-00100](00100-TICKET.md) and
[TICKET-00101](00101-TICKET.md) without depending on completed creation. Track the actual external starter release
prerequisite in later TASKs; planning neither creates nor qualifies it. No TASK, provider access, snapshot download,
credential inspection, catalog mutation or source publication is authorized by this approval.
