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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 3 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-013](../wayfinder/tickets/WF-013-research-mcp-skills-and-pi-support.md),
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md).

Own the first-party origin/manifest/host capability, not TICKET-00061's current principal or per-use authorization.
Map early origin contracts separately from final loader integration so TASK-00245 and this integration do not
reverse-block each other. [TICKET-00088](00088-TICKET.md) consumes verified resource-selection provenance; it must
not be a prerequisite for basic manifest validation. No connection, trust approval, cache cleanup or live MCP
invocation occurs through this planning approval. Existing Jev requirements and TASKs remain unchanged.
