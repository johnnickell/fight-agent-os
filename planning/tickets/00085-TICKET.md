---
id: TICKET-00085
epic: EPIC-00006
title: Install and configure the versioned Harness
status: ready-for-agent
---

# Install and configure the versioned Harness

## Problem statement

Users need one reproducible Fight Harness across registered repositories without copying divergent resources,
leaking credentials or confusing development and hosted installations. The installed terminal presentation alone
is not a managed connection, exact release or usable Agent.

## Solution and boundaries

Distribute one independently versioned Pi npm package from the canonical monorepo's `harness/` source. Routine
personal installation selects an exact release; record source/version/digest and compatibility. Checkout loading
is an explicit visibly mutable development mode, not an implicit production fallback. Package updates and rollback
to retained exact releases are deliberate operations. Reuse [TICKET-00035](00035-TICKET.md)'s accepted identity
rather than redesigning its welcome/header.

Keep machine-local connection profiles separate from revisioned database-backed user Harness profiles. Connections
select the endpoint, expected host-assigned installation/origin identity, protected credential-store handles,
environment and release/development mode. Repository content cannot redirect or establish a trusted connection.
User profiles contain non-secret resource selections, theme, aliases, prompts/persona, model defaults and update
preferences, with ownership, immutable revisions and expected-state protection. Neither profile grants authority.

Show persistent textual connection/profile and environment identity. Warn when enabled connections use indistinct
themes or light/dark pairs; permit an explicit accessibility/preference override. Color is never the sole identity
signal. Keep status and errors legible, preserve quiet/non-interactive behavior and record profile switches as
explicit linked continuations through [TICKET-00088](00088-TICKET.md), never active-session mutation.

Guide setup, capability checks, explicit update/rollback and disconnection using actual supported Linux/macOS
capabilities and safe diagnostics. Extend the existing versioned operator guide and its Dashboard help links,
record primary citations and last-verified platform/tool evidence; untested platform paths remain labelled as such.
Checks are read-only and setup mutations remain explicit human actions. Consume the fixed protected-sink,
per-holder credential lifecycle in
[TICKET-00060](00060-TICKET.md) and current activation/use in [TICKET-00061](00061-TICKET.md). The old raw-key-return
wording is superseded: no copy/paste key flow, browser key or Pi-child secret. Handle-only selection and protected
OS-store integration must respect those owners. Interactive storage uses macOS Keychain or a compatible Linux
Secret Service with no plaintext fallback; missing supported storage keeps managed use unavailable.

Exclude a second credential issuer/queue, new package release automation platform, Agent creation, provider
qualification, new terminal branding and automatic changes to personal configuration. Installation documentation
and owning-tool qualification belong with this outcome, not a separate documentation/testing product.

### Approved browser Planning default reuse — 2026-10-24

WF-049's Set as default action uses this existing user-profile/default writer (TASK-00323), consuming safe allowed
model references from TASK-00343. TASK-00367 supplies the narrow browser model-selection control, not a second
profile registry or general profile editor. Require current ownership/authorized scope and expected revision;
ordinary users cannot change another user's or installation-wide default. Catalog refresh does not update a
profile. Default changes affect future selection only; active conversations retain their chosen model until the
existing explicit linked continuation. Connection secrets/lifecycle belong to TICKET-00089, not these profiles.
No Pi version gate, host/runtime qualification or existing credential boundary is changed. See the
[reconciliation receipt](../wayfinder/research/private-production-owner-reconciliation.md).

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Install, update or roll back | Explicit exact-package install/update/rollback | Version, digest, compatibility and mode | Safe operation observations; no fabricated Domain event for package-manager work | Deliberate personal installation change; active snapshots remain unchanged |
| Configure/select a connection | Authorized local connection create/revise/select/remove | Expected installation, environment and protected readiness | Attributed selection/change observations without secrets | Local connection revision or linked continuation; no implicit trust from hostname |
| Manage own Harness preferences | Create/revise/select user profile with expected revision | Owned profile history and authorized resource/model choices | Committed owned profile revision/selection facts | Database preference revision, never an Agent Permission change |
| Inspect setup readiness | N/A — read-only inspection | Supported package, connection, credential-store and capability status | N/A — inspection creates no business mutation | Safe actionable ready/unavailable reasons; no automatic enrollment |

## Validation and permissions

Authorize profile reads/writes against the current user and installation/Workspace/Repository scope. Bound fields
and reference collections; reject stale revisions, unsupported releases, missing references and substituted origins.
Validate endpoint/environment configuration without returning arbitrary private configuration. Recheck actual
credential readiness through its owner; a saved handle is not proof of delivery, activation or permitted use.

Keep raw keys, provider authentication, machine paths, repository context and personal preferences out of releases.
Keep secrets out of database profiles, browser output, model context, session files, `.env`, logs and observations.
Use ADR 0001 ownership and ADR 0002 atomic state/audit rules for owned persistence; external package/store effects
occur outside business transactions. Uncertain changes require exact-state inspection, not silent replacement.

## Acceptance and evidence

- Exact released and visibly mutable development installations are distinguishable; explicit update/rollback
  preserves active-session history and never falls back silently. Qualify with the owning package tools.
- Connection/profile revision, ownership, wrong-installation denial, stale edits and unavailable protected storage
  are demonstrated; switching cannot reuse another installation's authority or change an active snapshot.
- Actual terminal evidence shows readable textual identity and theme-collision warnings, including narrow/light/dark
  presentation. Reuse historical branding evidence only as history, not fresh connection behavior.
- Owned profile behavior tests, direct supported-host setup checks and the full `./bin/build` pass. No product tests
  of package-manager internals, configuration text, wrappers or installation documentation.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00321](../tasks/00321-TASK.md) | Install and maintain exact Harness releases | ready-for-agent |
| [TASK-00322](../tasks/00322-TASK.md) | Configure trusted machine-local connections | ready-for-agent |
| [TASK-00323](../tasks/00323-TASK.md) | Manage revisioned user Harness profiles | ready-for-agent |
| [TASK-00324](../tasks/00324-TASK.md) | Connect through qualified protected credential storage | ready-for-agent |
| [TASK-00325](../tasks/00325-TASK.md) | Show unmistakable identity and switch through linked continuations | needs-info |
| [TASK-00326](../tasks/00326-TASK.md) | Deliver guided Harness setup and diagnostics | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved six delivery slices in the existing isolated planning worktree after TICKET-00083's
approved split. TASK-00321–00326 retain this TICKET's accepted boundaries, normally one independently reviewable
PR per TASK, with documentation and direct platform/tool qualification inside their owning product outcomes.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00321](../tasks/00321-TASK.md) | Exact independently versioned package install/update/retained-release rollback and visibly mutable development mode; reuse approved branding |
| B | [TASK-00322](../tasks/00322-TASK.md) | Explicit machine-local connection revisions, expected host-assigned installation/origin, private handles and safe identity verification |
| C | [TASK-00323](../tasks/00323-TASK.md) | User-owned non-secret database Harness preferences, immutable history, exact authorized resource/model references and expected-state writes |
| D | [TASK-00324](../tasks/00324-TASK.md) | Interactive Keychain/Secret Service integration through existing protected lifecycle/current-use owners, truthful readiness, recovery and disconnection |
| E | [TASK-00325](../tasks/00325-TASK.md) | Persistent textual connection/profile/environment identity, theme-collision warning/override and explicit linked-session switching |
| F | [TASK-00326](../tasks/00326-TASK.md) | Existing operator-guide/Dashboard help extension, bounded read-only diagnostics and actual supported setup/recovery journey |

TICKET-00086's subsequent approved split maps TASK-00323's exact resource/reference suppliers to
TASK-00327/00329/00330. TICKET-00089 now maps TASK-00343's model catalog to TASK-00323, resolving its sole remaining
mapping hold; status is `ready-for-agent` behind unfinished dependencies, not actual consumer/live-provider
qualification. TASK-00325 consumes TICKET-00088's TASK-00342 snapshot/linked-continuation integration, now including
TASK-00345 original outcomes and TASK-00351/00352/00353 invocation/attachment/recovery. It retains `needs-info` for
TASK-00350's precise operational host/broker gap and actual authority/host/provider composition. Other new TASKs are `ready-for-agent` behind their explicit dependencies,
not claims of usable runtime. TASK-00324 consumes TASK-00241's actual integrated credential/current-use readiness
and now TASK-00338's exact origin/cache removal composition from TICKET-00087. That supplier uses lower-level
credential owners, not completed onboarding; unresolved authority/session/host mappings remain transitive. All new orders are unranked and existing
Board active work/terminal-first priority is unchanged.

Keep dependencies non-circular: TASK-00321–00323 supply package/connection/profile facts to snapshot and broker
consumers. Resource/model, holder/sink/current-use and snapshot/session primitives must not depend on completed
switching, final onboarding, Explorer or browser Planning. TASK-00324 integrates interactive protected access but
does not create another sink, issuer, queue or signer, nor reverse-block shared credential readiness. Interactive
OS-store support is qualified through existing TASK-00234/00236/00243/00244 owners; missing support denies rather
than adds a plaintext fallback. User profiles do not replace TASK-00249's role-template resolver or clear its holds.

This TICKET's TASK split is complete, not implementation or runtime qualification. Browser Planning and verified
Markdown-to-database Planning cutover are not installation prerequisites. TICKET-00109's approved final split completes original-map planning phases.
Next: `/skill:next` for Board-based selection, preserving all prior approved splits and unresolved suppliers.
No package publication, global configuration change, enrollment, credential access or implementation follows from
this planning approval.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving the preceding
TICKET-00083 changes. Live and archived TASK identities were inspected before allocating TASK-00321–00326;
the archive contained no TASK records. `./bin/planning-check --write` and `./bin/planning-check` passed with
453 records, 361 active and current generated views. Tracked `git diff --check` and all six new-record whitespace
checks passed. Validation found no dependency cycles or invalid planning links; existing Board priority and active
TASK-00138 are unchanged. TICKET-00088 now names package/connection/profile suppliers without a reverse switching edge.

These are planning receipts, not application/runtime or platform qualification. The full `./bin/build` was not
run and remains required before commit or publication. No implementation, package/configuration mutation,
credential access, enrollment, session launch, commit, push, PR or merge was performed. Changes remain uncommitted
in the isolated worktree; formal independent review and delivery are separate.

### Original requirement approval

Approved as area 1 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md) and
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md), with
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Resource references use [TICKET-00086](00086-TICKET.md), provider choices use [TICKET-00089](00089-TICKET.md), and
session application uses TICKET-00088. Decompose exact independent capabilities rather than creating whole-TICKET
cycles: package/connection/profile identity supplies snapshot resolution; completed session switching does not
reverse-block those primitives. Browser Planning and database Planning cutover are not installation prerequisites.
That requirement approval created no TASK and authorized no package publication, global configuration change,
enrollment or credential access. The separately approved TASK split above now supplies implementation ownership,
not authority to perform those effects.
