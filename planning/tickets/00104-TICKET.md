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

Use TICKET-00089/00090's protected provider and durable conversation boundaries, with OpenRouter through the
initiating user's own server-protected environment connection and exact generation, an allowed compatible model
catalog and bounded per-turn/conversation plus applicable user/connection/environment budgets. No shared
administrator/family key, another user's connection or inherited local Codex credential is a fallback.
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
| [TASK-00427](../tasks/00427-TASK.md) | Explain failures with versioned playbooks and safe diagnostics | ready-for-agent |
| [TASK-00428](../tasks/00428-TASK.md) | Request and recover a bounded support explanation | needs-info |
| [TASK-00429](../tasks/00429-TASK.md) | Ask for support safely from the creation browser | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved private-production reconciliation — 2026-10-24

The per-user browser-provider amendment supersedes installation-held shared-key wording. TASK-00428 binds the
initiating user and selected environment connection/generation through TASK-00344/00345/00346 and the existing
conversation owner. Replacement/disconnect cannot erase original usage or silently rebind a request. TICKET-00089
owns connection self-service; no second setup/credential UI is introduced here. Pre-registration actual Agent/
snapshot/assignment and host holds remain, and deterministic help/recovery stays independent. This optional
consumer is not a first browser Wayfinder prerequisite. See the
[reconciliation receipt](../wayfinder/research/private-production-owner-reconciliation.md).

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
The requirement approval left TASKs, playbook content/model/budget configuration and qualified runtime for later
work. The approved split below now allocates TASKs only; it creates no Agent session, provider request, diagnostic
upload, credential access or operational authority.

### Approved TASK decomposition and current handoff

The maintainer approved three slices in `.runs/worktrees/ticket-00083-to-tasks` on
`feature/ticket-00083-to-tasks`. All are unranked features with blank PRs, normally one independently reviewable PR.

| Slice | Accepted owner | Exact known blockers | Delivered boundary |
|---|---|---|---|
| A | [TASK-00427](../tasks/00427-TASK.md) | TASK-00075, TASK-00416 | Versioned playbooks, bounded safe evidence and explicit export in the existing failure view, without AI |
| B | [TASK-00428](../tasks/00428-TASK.md) | TASK-00427, TASK-00250, TASK-00346, TASK-00354; pre-registration scope supplier unallocated | Protected actual OpenRouter support request, attributed advisory result and original-request recovery |
| C | [TASK-00429](../tasks/00429-TASK.md) | TASK-00428 | Explicit browser disclosure/submission, same-conversation recovery and safe navigation to normal deterministic controls |

TASK-00428 starts `needs-info`. TASK-00250/00339/00348 and their Agent/Team/current-authority owners require real
Repository/checkout/context bindings, while creation failures can precede registration. Map an exact attempt/
Workspace-scoped actual Agent, Harness snapshot and assignment supplier under those existing owners before exposure.
No guessed supplier ID, fabricated Repository/context, borrowed human authority, unteamed/privileged support Agent
or second session engine resolves the gap. Selecting a development profile grants nothing. Existing Team/creation/
current-use and protected host/broker/storage/provider holds remain. TASK-00427/00429 are ready behind unfinished/
held dependencies, not runtime-qualified; model-free help does not depend on TASK-00428.

TASK-00427 extends TASK-00075's single guide and TASK-00416's attempt surface using retained actual stage facts.
No successful final TASK-00426 receipt is required to explain an earlier failure. Playbooks identify exact revision,
known applicability, primary sources, commands/actions, expected results and recovery limits. Projection/export
minimizes private facts, sanitizes before persistence/presentation and never inspects live paths/providers or uploads
automatically. Export/view rights do not confer AI disclosure. Unknown conditions stay unknown; command/prose
qualification is direct, not product tests of documentation or diagnostic tooling.

TASK-00428 composes existing protected provider/session/retention owners, not a duplicate ledger, transcript store,
Agent role or queue. Independent current viewing/AI/disclosure rights bind exact evidence/playbook/Harness/model
revisions. Finite request/conversation budgets, atomic original intent/reservation/audit and post-commit effects
preserve original correlation and uncertain liability. No fallback, paid regeneration or budget reset hides an
interruption. A separately authorized actual OpenRouter request must qualify this support scope; Planning-provider
receipts, local Jev access, fixtures and enrollment metadata are not that evidence.

TASK-00429 extends the same browser failure view with explicit disclosure review and safe advisory output. Advice
is inert untrusted content, distinguishes observed facts/inference and carries no action grant. It cannot retry,
delete, authenticate, change mounts/settings/files, confirm a preview or apply itself. Humans use TASK-00416/00417's
normal current-state controls and fresh effect consent. Provider/configuration failure, denial, exhaustion and
interrupted help affect only assistance; permitted deterministic creation/recovery, playbooks and diagnostics remain
usable. Reconnect reads the original result rather than issuing another request. Sole `/me`, no-store volatile state,
late-response fences and fresh accessible responsive light/dark/reduced-motion browser evidence remain mandatory.

Each slice includes owned behavior tests, applicable actual participant/provider/browser evidence, guidance and
`./bin/build`. Qualify infrastructure/tools directly; no standalone proof TASK or product tests of prompts, prose,
SDKs, configuration, wrappers or deliberately seeded checker failures. No existing creation, guide, Agent, provider,
session or terminal supplier gains a reverse support dependency or has a hold/priority cleared.

This completes TICKET-00104 and EPIC-00009's accepted TASK-decomposition coverage, not implementation or acceptance.
Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve active TASK-00138,
terminal-first Board order, independent holds and Markdown Planning authority. Implementation checkout, live
source/credential/provider/host authority, full build, independent review and publication remain separate.

### Planning verification

This historical support-split receipt predates TICKET-00105's instruction-inspection split; latest planning
verification is in TICKET-00109. Counts and handoff state below describe the earlier point, not runtime evidence.

Recorded the approved split in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving
prior uncommitted planning. Highest live ID before allocation was TASK-00426; no archived TASK records existed.
TASK-00427–00429 match the approved statuses, exact known blockers and template sections, all unranked features with
blank PRs. TASK-00428 retains its explicit pre-registration Agent/snapshot/assignment supplier gap and inherited
operational holds. The unknown supplier edge is not invented or claimed cycle-checked; ready metadata for A/C is
not runtime qualification.

`./bin/planning-check --write` and `./bin/planning-check` passed with 556 records, 464 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 121 new/untracked TASK
whitespace checks passed. Explicit checks confirmed no existing TASK gained an assistance blocker and model-free
TASK-00427 is independent of AI. Guide, role, Harness-resolution, assignment, OpenRouter, retention and creation-
recovery coordination adds no metadata/priority change or new scope grant. Earlier creation/reference/publication,
measurement/forecast mappings and independent holds remain preserved.

TASK-00138/00139/00142/00159/00184/00226 remain byte-identical to this worktree's HEAD. Existing root, guide, identity,
configuration, context, migration/authority, frame and Dashboard supplier frontmatter was checked unchanged.
TASK-00138 remained the sole active Board item, order 2. That split advanced handoffs to TICKET-00105, then
TICKET-00106–00109; no stale current TICKET-00104 command remained. EPIC-00009's six splits are recorded; remaining
instruction splits, supplier resolution and implementation are not claimed complete.

Full `./bin/build` was not run and remains required before commit/publication. These checks are planning evidence,
not actual Agent-scope, PostgreSQL application-participant, provider, browser, guide-command or real creation
qualification. No live source/private/account or credential inspection, diagnostic upload, Agent/session/request,
provider call, project/recovery/cleanup effect, Planning import or authority cutover occurred. All changes remain
uncommitted; independent review and publication remain outstanding. No commit, push, PR or merge occurred.
Unrelated main-checkout work was untouched and is not claimed clean; earlier receipts remain historical.
