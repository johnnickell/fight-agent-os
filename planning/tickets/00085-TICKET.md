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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of the remaining eight-TICKET EPIC-00006 decomposition. Consumes
[WF-014](../wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md) and
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md), with
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Resource references use [TICKET-00086](00086-TICKET.md), provider choices use [TICKET-00089](00089-TICKET.md), and
session application uses TICKET-00088. Decompose exact independent capabilities rather than creating whole-TICKET
cycles: package/connection/profile identity supplies snapshot resolution; completed session switching does not
reverse-block those primitives. Browser Planning and database Planning cutover are not installation prerequisites.
No TASK, package publication, global configuration change, enrollment or credential access is authorized here.
