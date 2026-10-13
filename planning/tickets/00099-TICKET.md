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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume early Workspace identity from [TICKET-00024](00024-TICKET.md) and exact existing Harness template/profile
contracts, not completed Agent journeys or browser editors. Supply [TICKET-00100](00100-TICKET.md) and
[TICKET-00101](00101-TICKET.md) without depending on completed creation. Track the actual external starter release
prerequisite in later TASKs; planning neither creates nor qualifies it. No TASK, provider access, snapshot download,
credential inspection, catalog mutation or source publication is authorized by this approval.
