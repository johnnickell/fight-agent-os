---
id: TICKET-00087
epic: EPIC-00006
title: Discover and load trusted first-party Skills
status: ready-for-agent
---

# Discover and load trusted first-party Skills

## Problem statement

A remote Skill must not gain local-file trust, silently shadow instructions or expand tools merely because it
came from a familiar hostname. Agents need short usable aliases and exact origin-bound content with live access.

## Solution and boundaries

Deliver one origin-aware Harness host integration and the first-party static Skill publication contract, consuming
[TICKET-00086](00086-TICKET.md)'s database resources and [TICKET-00085](00085-TICKET.md)'s explicit connection.
Trust only the configured first-party Agent OS origin using host-assigned installation/origin identity throughout
registry, model context, cache and evidence. A hostname, `localhost`, shared Compose network or repository content
cannot register or substitute an origin. Establish trust explicitly once; new verified static revisions need no
additional per-revision prompt under the accepted first-party policy.

Discover authorized resources, hold the complete advertised manifest and exact revision, then retrieve bytes lazily.
Verify per-file size/digest and exact `SKILL.md` frontmatter. Supporting reads stay within the held manifest and
origin. Reject dynamic/unverifiable content, path escape, cross-origin reads, manifest extension via directory
results, unapproved nested activation, digest mismatch, oversize responses and silent substitution. Delegate wire
protocol/DTO concerns to qualified official SDK capabilities, not a new Fight Common policy wrapper.

Preserve useful bare aliases. Filesystem personal/project Skills win over a colliding MCP alias only with visible
collision evidence and an accessible qualified alternative or picker. Personal-versus-project collisions require
a picker or saved repository/profile choice, not discovery order. Record exact selected identities/revisions for
snapshots; explicit invocation selects instructions, not tools or grants.

Keep origin-separated on-demand cache outside Pi filesystem Skill discovery. Use host-protected immutable storage
or rehash on every access; cache is disposable, never authority. Already loaded/verified cached content may remain
usable offline only within current authority/availability rules; unavailable resources and protected operations
fail visibly. Explicit origin removal clears owned credentials/cache through their owners and retains secret-free
evidence. Never silently fall back to filesystem content or a different revision/origin.

Consume [TICKET-00061](00061-TICKET.md)'s authenticated, current permission-filtered discovery/invocation. Effective
tools are the intersection of live direct Permissions, server authorization, initiating-user/assignment delegation
and host/session constraints. `allowed-tools` only requests or narrows; it cannot create authority or widen local
Pi tools. Unknown/denied requests receive safe diagnostics and revocation applies on the next protected use.

Exclude arbitrary third-party MCP, dynamic Skills, another authentication/broker registry, a privileged Skill
identity, general MCP platform work and reimplementation of Jev judgment/evidence tools.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Establish or remove first-party trust | Explicit protected trust/removal operation | Expected installation/origin and current connection | Safe trust/removal facts or local observations at their actual owner | Origin binding or owned cache/handle cleanup, not repository-granted trust |
| Discover and choose a Skill | Record explicit selection/collision choice | Authorized catalog, aliases, origins and revisions | Selection provenance, not an authority grant | Held manifest and exact intended Skill |
| Retrieve a Skill/supporting file | N/A — resource retrieval is read-only | Exact manifest-bound authorized file | Sanitized retrieval/verification observations, not Domain mutation | Verified lazy cache population; no uncontrolled filesystem discovery |
| Determine usable tools | N/A — discovery itself executes no tool | Existing live permission-filtered discovery plus session constraints | Safe unavailable/revoked observations | Honest capabilities; invocation retains its owning command/authorization |

## Validation and permissions

Authenticate actual callers and recheck current resource access, delegated scope and origin before every protected
request or cached result disclosure. Treat Skill content as untrusted instructions that cannot change these checks.
Bound list/file sizes, total retrieval, concurrency and time; reject incomplete verification instead of reporting
successful loading. Pin protocol/runtime compatibility at implementation rather than reusing historical ecosystem
research as current support evidence. Cache keys include origin, immutable revision/manifest and content identity.

Never expose raw credential material, hidden private source or arbitrary provider/transport errors in diagnostics.
Preserve server-side authorization under permission changes despite immutable snapshots. Unsupported SDK/Pi routes
remain explicitly unavailable; do not materialize remote Skills as ordinary trusted filesystem Skills to bypass gaps.

## Acceptance and evidence

- Real supported Pi/MCP integration loads an authorized static Skill lazily and records exact origin/manifests;
  digest/frontmatter/path/size/origin attacks and manifest extension fail before activation.
- Actual filesystem/MCP and personal/project collisions are visible and resolvable without losing qualified access.
- Cache tampering, offline misses, origin removal and revoked access fail safely; cached bytes never confer authority.
- Live tool availability and invocation remain with TICKET-00061; no Skill text expands permissions or local tools.
- Owned trust/loader/collision tests, real protocol/runtime qualification and `./bin/build` pass. No duplicate SDK,
  package-manager, configuration or test-infrastructure tests; record exact versions and unsupported coverage.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00333](../tasks/00333-TASK.md) | Establish explicit first-party Skill trust | ready-for-agent |
| [TASK-00334](../tasks/00334-TASK.md) | Inspect effective Skill aliases and resolve collisions | ready-for-agent |
| [TASK-00335](../tasks/00335-TASK.md) | Serve authorized static Skill manifests and bytes | ready-for-agent |
| [TASK-00336](../tasks/00336-TASK.md) | Load verified Skills and supporting resources in Pi | needs-info |
| [TASK-00337](../tasks/00337-TASK.md) | Reuse verified cached content safely | ready-for-agent |
| [TASK-00338](../tasks/00338-TASK.md) | Remove first-party trust and reconcile owned cleanup | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved six delivery slices in the existing isolated planning worktree, normally one bounded PR
each. Early trust/collision facts remain independent of completed publication/loading; actual qualification,
documentation and owned tests stay inside their delivery outcomes rather than separate proof-only work.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00333](../tasks/00333-TASK.md) | Explicit first-party Skill trust bound to existing exact connection/origin authority |
| B | [TASK-00334](../tasks/00334-TASK.md) | Bounded effective-alias observations, visible collision resolution and precise candidate-bound choices |
| C | [TASK-00335](../tasks/00335-TASK.md) | Authorized static Skill catalog/manifests/bytes over the existing protected first-party MCP composition |
| D | [TASK-00336](../tasks/00336-TASK.md) | Actual Pi activation, lazy verified supporting reads, origin-tagged context and current-use/host integration |
| E | [TASK-00337](../tasks/00337-TASK.md) | Origin-separated verified cache, restart/offline limits and safe exact cleanup participation |
| F | [TASK-00338](../tasks/00338-TASK.md) | Explicit origin removal with current-use denial, owned cache cleanup and existing credential lifecycle recovery |

TASK-00333 consumes TASK-00322; TASK-00334 consumes TASK-00333/00327. TASK-00335 consumes TASK-00333/00327/00245,
TASK-00336 consumes TASK-00334/00335 and TASK-00337 consumes TASK-00336. TASK-00338 consumes TASK-00337 plus
TASK-00237/00240/00243 for actual revocation, eligible cleanup and enrollment/use holds. No dependency on completed
interactive onboarding is introduced upstream. All orders remain unranked and existing Board priority is unchanged.

TASK-00245 gains TASK-00333 and retains `needs-info` for lower-level current-use/session/host prerequisites.
TASK-00328 gains TASK-00334, resolving its sole mapping hold; it is now `ready-for-agent` behind unfinished
suppliers, not a delivered custom publisher. TASK-00332 gains TASK-00336/00337 and retains `needs-info` through the
loader's unresolved lower-level contracts. TASK-00324 gains TASK-00338 rather than implementing another cleanup
path; the latter uses lower-level credential owners, never completed onboarding as a reverse dependency.

TICKET-00088's subsequent approved split maps TASK-00336's early snapshot binding/currentness to TASK-00340/00341.
TICKET-00089 maps early model facts to TASK-00343; TICKET-00090 now supplies TASK-00350's protected session/host/
acting-window adapter with early TASK-00348/00349 facts. The loader remains `needs-info` for TASK-00350's exact
operational host/broker gap and actual supported scope; provider qualification is separate. TASK-00342 consumes the completed loader,
never the reverse. Early resource/origin/choice facts support snapshot/admission
bootstrap; final remote transport/loading must not reverse-block its prerequisites. Naming trust/loader owners
does not qualify live authentication, session isolation, original-operation recovery or any missing host contract.
Snapshot facts are not grants; current authority still applies to all protected reads, cached disclosure and use.

### Current implementation evidence boundary

The installed Pi 1.2.0 `docs/mcp.md`, `docs/skills.md` and `docs/security.md` were read during proposal preparation.
They document built-in MCP tools/resources, project-configured overrides and first-discovered local Skill collision
behavior. This supersedes the historical Pi 0.87.1 absence-of-generic-MCP observation as a current implementation
starting point, not the accepted product requirements or historical WF-013 receipt. No runtime was exercised and
these docs do not qualify the accepted Skills extension, protected Agent credentials, exact origin/manifest handling,
acting-window controls or safe cache behavior. Revalidate selected official protocol/SDK/Pi capabilities at
implementation, pin exact source/version/lock and qualify every reachable direct/resource/codemode path. Reuse
conforming capabilities without building a second MCP platform or treating generic Resources support as Skills.

This TICKET's decomposition is complete, not runtime delivery. Following TICKET-00088–00099's approved snapshot/
provider/session/proposal/browser/Runner/evidence/web-research/history/sessions/measurement/catalog splits, continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Browser Planning and database Planning cutover are
not prerequisites for these early capabilities. Preserve Markdown authority and all existing credential/session
owners. This planning authorizes no connection, trust grant, provider call, cache deletion or credential mutation.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving the preceding
approved planning changes. Live and archived TASK identities were inspected before allocating TASK-00333–00338;
the archive contained no TASK records. `./bin/planning-check --write` and `./bin/planning-check` passed with
465 records, 373 active and current generated views. Tracked `git diff --check` and all six new-record whitespace
checks passed. Validation found no dependency cycles or invalid planning links. New orders remain unranked;
existing priority and active TASK-00138 are unchanged. TASK-00328's documented status change resolves only its
supplier-mapping hold and retains its unfinished dependencies.

These are planning receipts, not application, host, protocol or credential qualification. The full `./bin/build`
was not run and remains required before commit/publication. No implementation, connection, trust enrollment,
Skill activation, provider call, cache/credential mutation, commit, push, PR or merge was performed. Changes remain
uncommitted in the isolated worktree; independent review and delivery remain separate.

### Original requirement approval

Approved as area 3 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-013](../wayfinder/tickets/WF-013-research-mcp-skills-and-pi-support.md),
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md).

Own the first-party origin/manifest/host capability, not TICKET-00061's current principal or per-use authorization.
Map early origin contracts separately from final loader integration so TASK-00245 and this integration do not
reverse-block each other. TICKET-00086 maps managed Skill storage/exact manifests to TASK-00327. Its TASK-00328
and TASK-00332 requested the early collision and qualified-loader mappings now supplied by the approved split above.
Consume TASK-00322's connection facts without waiting for final onboarding or switching; TASK-00327 supplies database
facts, not origin trust or live access. [TICKET-00088](00088-TICKET.md) consumes verified resource-selection provenance; it must
not be a prerequisite for basic manifest validation. No connection, trust approval, cache cleanup or live MCP
invocation occurs through this planning approval. Existing Jev requirements and TASKs remain unchanged.
