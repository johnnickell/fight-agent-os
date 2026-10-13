---
id: TICKET-00061
epic: EPIC-00014
title: Authenticate and authorize current Agent tool use
status: ready-for-agent
---

# Authenticate and authorize current Agent tool use

## Problem statement

A provisioned, delivered key is not permission to run tools or perform work. No Agent-authenticated request
path exists today. A static template, signed request or old snapshot could otherwise outlive direct Permission,
Team, Repository or credential revocation.

## Solution and boundaries

Integrate the actual package HMAC verifier and `AuthenticatedAgentPrincipal` for the holder-bound credential;
qualify protected broker/first-party MCP transport, replay/nonce controls, destination binding and current
activation before tool discovery **and each invocation**. Intersect the Agent's currently assigned eligible
direct Permissions with current initiating-human/Workflow delegation where relevant, Team/Repository/checkout
scope, session profile/context revision and supported host/sandbox/provider controls. Give an authenticated
Agent narrowly authorized safe self-profile read/update Tools, targeting only its own identity; no client-supplied
Agent ID, stale profile or skill-requested tool widens authority. Expose unavailable tools and revocations
truthfully to the Harness. Support a manually initiated bounded Explorer run with the qualified repository
context without making browser Planning cutover or a TASK Workflow a prerequisite.

Do not implement the EPIC-00006 Harness package/provider/conversation engine, EPIC-00007 Runner sandbox, or a
second MCP trust registry here; integrate their owners at this Agent authentication/authorization boundary.
No self-service key retrieval, direct Permission management, arbitrary external MCP origin, role-name grant or
browser Agent credential is authorized.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Authenticate a holder | Verify signed request and current active credential/enrollment | Current Agent and operation, destination, holder and replay evidence | Safe authentication/denial observation, not credential disclosure | One request-scoped authenticated Agent principal or fail-closed denial |
| Discover/invoke a tool | Package availability and owning MCP/Tool use-case command when permitted | Fresh direct Permissions, human/Workflow grant and Team/Repository/host scope | Only the tool's existing authorized event, if any | The actual bounded operation, never a power inferred from a template |
| Read/update own safe profile | Package-owned self-profile Tool operations | Principal-bound target and safe profile View | Package profile fact only when changed | Limited own profile update without privilege/credential mutation |

## Validation and permissions

HMAC, timestamp/nonce, holder and active credential are necessary, never sufficient. Authorize each requested
capability with its exact Permission and current trusted context on every direct, CLI, broker and MCP entry path.
Permission/credential/session/Team/delegation revocation must deny subsequent use even when Harness snapshots
are immutable. Missing qualified sandbox/host/provider/origin keeps work unavailable; do not silently launch
outside approved limits. Never send raw HMAC keys, refresh/provider credentials, internal sink claims or arbitrary
Tool input to models, child environments, UI or audit.

## Acceptance and evidence

- A real distinct Agent performs permitted source-scoped use via authenticated tools; invalid holder, replay,
  wrong destination, lost enrollment, disabled/revoked Agent, stale grant and changed Team/Repository deny.
- Direct Permission changes become effective on the next use without template/session authority shortcuts;
  self-profile Tools are identity-bound and cannot update another Agent or expand grants.
- Independently qualify actual broker/Harness/MCP origin, allowed tools and host protection with real consumer
  participants and owned authorization tests plus `./bin/build`; package fixtures and key issuance alone do
  not establish live use.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00242](../tasks/00242-TASK.md) | Authenticate signed Agent requests with atomic replay protection | ready-for-agent |
| [TASK-00243](../tasks/00243-TASK.md) | Activate only the exact currently authorized delivered credential | ready-for-agent |
| [TASK-00244](../tasks/00244-TASK.md) | Authorize and broker each bounded Agent use | needs-info |
| [TASK-00245](../tasks/00245-TASK.md) | Enforce current authority throughout first-party MCP discovery and invocation | needs-info |
| [TASK-00246](../tasks/00246-TASK.md) | Deliver independently authorized self-profile read and rename Tools | ready-for-agent |
| [TASK-00247](../tasks/00247-TASK.md) | Connect manual Explorer sessions to authenticated scoped tools | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 3 of EPIC-00014's seven-TICKET split. Requires
[TICKET-00059](00059-TICKET.md) and [TICKET-00060](00060-TICKET.md); consumes EPIC-00006's
[trusted Harness](../epics/00006-EPIC.md), EPIC-00005's registered context and qualified execution
isolation where used. [TICKET-00065](00065-TICKET.md) owns real role-use integration; this TICKET owns reusable
current Agent authentication/authorization, not another Workflow.

The maintainer approved six dependency-ordered TASK slices:
[TASK-00242](../tasks/00242-TASK.md) authenticates signed requests with atomic replay protection;
[TASK-00243](../tasks/00243-TASK.md) activates only the exact currently authorized delivered credential;
[TASK-00244](../tasks/00244-TASK.md) authorizes/brokers each bounded use;
[TASK-00245](../tasks/00245-TASK.md) enforces current first-party MCP discovery/invocation authority;
[TASK-00246](../tasks/00246-TASK.md) delivers separately authorized self-profile read/rename; and
[TASK-00247](../tasks/00247-TASK.md) connects real manual Explorer sessions to authenticated scoped tools.

TASK-00242–00245 are now linked into TASK-00231–00233 and TASK-00241, directly or through explicit dependency
chains. Those four existing TASKs become `ready-for-agent` only behind their recorded unfinished blockers; this
resolves their direct TICKET-00061 mapping, not implementation or runtime qualification. TASK-00244/00245 retain
`needs-info` for remaining Harness/host and first-party origin prerequisites. TICKET-00062's accepted
TASK-00253 now supplies the exact Team authority to TASK-00244/00247/00250, with its creation/membership seam
and participant holds intact; TASK-00254 feeds writer qualification through TASK-00231. TICKET-00034's
TASK-00248–00250 supply the profile dependency chain, retaining actual resource/session prerequisites.
TICKET-00063's subsequent TASK-00258 now supplies actual named creation to TASK-00247/00250; supported
creation composition and actual manual-session/provider participants remain held. TASK-00259 supplies human
management/creation writers through TASK-00231 without depending on final readiness or browser work. No
missing owner is represented by a fabricated ID or fixture. These holds still block integrated qualification.

The dependency direction is persistence/credential primitives → authentication/activation/current-use components
→ integrated writer/switch/restore qualification → TASK-00241 operational readiness → TASK-00247 manual Explorer
integration. Neither Team/Harness primitives nor TASK-00242–00245 may depend on final Explorer work or whole-parent
completion. TICKET-00062/00063 retain Team and named-Agent creation; TICKET-00065 consumes the real Explorer
outcome/evidence without another implementation or ceremonial run. TASK-00171 now explicitly consumes TASK-00245
and retains judgment endpoint/Tool and Pi caller integration, with no second MCP server or origin registry.

Package principal snapshots remain request-scoped: every later page/invocation/request resolves fresh authority,
without claiming retroactive cancellation of already-admitted work. Self-profile Permission definitions are an
explicit complete-policy extension with stable generated identities, not implicit grants or a silent replacement
of TASK-00036's original catalog. Browser JWT and Agent HMAC stay separate. Planning changes no runtime behavior,
creates no Agent/credential/session/grant and leaves all new TASK orders unranked and active Board work unchanged.

TICKET-00064's accepted TASK-00261–00264 now consume current-use and roster primitives for actual selection/
admission; they do not resolve lower-level Harness/commitment holds or become reverse blockers of TASK-00244
or first manual Explorer use. Newly enabled participants still need current qualification, not inherited receipts.

TICKET-00065's accepted TASK-00266 now consumes TASK-00247's actual Explorer result alongside the separate
Project Manager browser journey; TASK-00267–00272 integrate other owned roles without becoming prerequisites
of manual use or clearing its remaining host/session holds.

Next planning operation: `/skill:to-tasks TICKET-00080`, mapping consumer coverage and qualification accounting;
then continue the remaining map TICKET decompositions. Worktree selection, implementation, real
qualification, independent review, publication and operational enrollment remain separately authorized.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 347 records,
260 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations. Repetitive deprecation warnings remain. Evidence: `.runs/build/20261012T165711Z-38536`; summary log:
`.runs/build/ticket-00061-to-tasks.log`. These are planning/repository checks, not fresh Agent authentication,
activation, broker/MCP, restoration or real Explorer evidence. Independent review and publication remain unperformed.
