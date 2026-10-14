---
id: TICKET-00104
epic: EPIC-00009
title: Explain creation failures with bounded support assistance
status: ready-for-agent
---

# Explain creation failures with bounded support assistance

## Problem statement

A partially created project can leave useful local and remote state that a human must understand before retry or
cleanup. Optional AI help must explain this evidence without receiving credentials, inventing certainty or gaining
authority to repair the project itself.

## Solution and boundaries

Provide creation-specific versioned troubleshooting playbooks within the existing task-based operator guide,
linked from actual attempt/stage failures. Identify guide/playbook revision, applicable known conditions, expected
results, safe commands or UI actions, recovery/rollback limits and primary sources. Reuse TICKET-00023/WF-020's
single guidance source and qualification posture; do not create a competing generic onboarding product or uncited
AI authority. Playbook viewing and copyable secret-safe diagnostic export remain available without any model.

Expose a bounded authorized structured attempt-evidence projection: immutable selected plan identity, relevant
safe source/manifest and capability facts, confirmed stage outcomes, uncertainty, warnings, retained resources and
allowed next actions. Minimize private identifiers/paths and sanitize provider/process errors before presentation
or persistence. Exclude credentials, key material, credential-store contents, raw environment/log dumps, arbitrary
repository files, prompts/hidden reasoning and unrelated user data. Export is explicit; nothing uploads automatically.

An optional browser support Agent consumes only expressly authorized structured evidence and the applicable exact
versioned playbook. It identifies observed facts versus inference, cites the playbook revision, explains partial
side effects and may recommend precise revised input, a command, retry, cleanup or next action. Unsupported cases
remain explicit unknowns with safe evidence rather than speculative autonomous repair. Generated recommendations
are untrusted advisory content, not accepted commands or execution evidence.

Use TICKET-00089/00090's protected provider and durable conversation boundaries, with OpenRouter through an
installation-held server-side key and an explicit allowed model catalog plus bounded per-turn/conversation budgets.
Do not assume a personal Codex subscription authenticates a hosted installation. Reuse qualified provider identity,
usage/cost provenance, request correlation, uncertain-outcome and cancellation semantics; no silent fallback,
repeated paid generation or reset of consumed budgets. Browser/support Agent, Runner and generated project never
receive the provider key. Actual Agent identity and authority remain with existing Agent owners, not a new role
with implicit administrator privilege.

Record authorized assistance requests, evidence/playbook/Harness/provider/model revisions and protected durable
results under the owning session/attempt contracts. Browser disconnect must not turn an answer into an effect or
silently start another provider call. Current visibility and provider-disclosure permission apply independently;
permission to read an attempt or request help does not authorize sending all its private contents externally.
Treat source/evidence text as data, never instructions to expand access or reveal secrets.

The Agent cannot mutate files/settings, change roots/mounts, authenticate GitHub, delete resources, retry stages,
change the attempt, confirm a preview or apply its own recommendation. Humans review and use TICKET-00101's normal
explicit deterministic controls, which revalidate current state and authority. Advice may become stale; no button
may bypass those controls by treating a recommendation as a pre-authorized effect. Human-run setup commands remain
explicit actions outside the Agent, subject to the same operator guidance boundaries.

OpenRouter outage, missing configuration, denied disclosure, budget exhaustion or failed assistance affects only
optional help. Deterministic creation, attempt inspection, playbook viewing, diagnostics and retry/cleanup controls
remain usable according to their actual capabilities. Do not label model unavailability as failed project creation.

Exclude another provider/session engine, new privileged support role, arbitrary tool/file/network access,
autonomous repair, source-host authentication/enrollment, generic coding chat, extra generative providers,
automatic evidence upload, recurring certification and general-purpose helpdesk infrastructure.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| View applicable troubleshooting | N/A — read-only | Current authorized attempt facts and applicable versioned playbook | N/A — reading guide content changes no project | Useful recovery guidance without a provider dependency |
| Export safe diagnostics | N/A — explicit read/export, not automatic upload | Bounded redacted attempt/capability/evidence projection | N/A — ordinary read logging, not a fabricated business event | User-controlled secret-safe copy/download |
| Request support explanation | Explicit bounded assistance request through existing session/provider owner | Current visibility, disclosure, allowed model/budget and evidence revision | Owned admitted request/result/usage facts | One protected provider request and durable attributed advisory result |
| Inspect or recover a response | Existing original-request reconciliation/cancellation; reads are queries | Known result, actual request correlation and consumed budget | Existing confirmed session/provider outcomes | Honest unavailable/partial/unknown response without blind regeneration |
| Act on a recommendation | N/A here — human uses TICKET-00101 or explicit operator actions | Fresh original state, current authority and complete effect preview | Only the actual operation owner's committed facts | No effect from advice itself; exact human-authorized deterministic action only |

## Validation and permissions

Require current attempt/playbook viewing authority and independent AI-use/provider-disclosure authorization for the
actual human/Agent, Workspace and environment. Validate evidence ownership, bounded fields, exact versions, model
allowlist and budgets on every request, not just tool/UI discovery. A recommendation never carries a business grant.
Fail closed on missing actual Agent/provider capability, unsafe evidence or stale context; retain deterministic help.

Sanitize before export/provider disclosure and keep hidden reasoning/private provider payloads out of ordinary
history. Use protected session retention rather than another transcript store. Apply ADR 0002 durable admission and
post-commit provider effects plus ADR 0003/0004 no-store, volatile client authority and late-response fences. Denial,
revocation, malformed output and uncertain billing cannot trigger fallback or automatic retry of either help or work.

## Acceptance and evidence

- Real creation failures show the applicable versioned playbook and safe export without AI, including partial local/
  remote effects and supported next actions. Documentation/commands are reviewed and exercised directly.
- A separately authorized actual OpenRouter support request uses bounded structured evidence and protected provider
  access, distinguishes observations/inference and records provenance/usage without credentials or hidden reasoning.
- Attempts to make assistance apply fixes, widen disclosure, retry, delete, authenticate or alter mounts are denied;
  humans still pass through current-state deterministic controls. Stale advice cannot bypass a new preview.
- Missing provider/configuration, outage, budget exhaustion and interrupted conversation leave deterministic
  creation/recovery/guide/export usable and do not issue duplicate paid requests or claim unknown costs are zero.
- Owned assistance/disclosure/authorization/client behavior tests, real qualified provider/browser evidence and
  `./bin/build` pass. Doubles do not qualify live support; no product tests of prose, prompts, guide links, provider
  SDKs, configuration, wrappers or intentionally seeded checker failures.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 6 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume safe [TICKET-00101](00101-TICKET.md)/[TICKET-00103](00103-TICKET.md) evidence and existing guide, Harness,
Agent and protected provider/session primitives. [TICKET-00089](00089-TICKET.md)/[TICKET-00090](00090-TICKET.md)
retain exact provider/runtime ownership; this consumer must not reverse-block them or require finished browser
Planning. Optional assistance is not a prerequisite for deterministic creation or the terminal execution milestone.
Exact TASKs, playbook content/model/budget configuration and qualified runtime remain future work. This approval
creates no TASK, Agent session, provider request, diagnostic upload, credential access or operational authority.
