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
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 3 of EPIC-00014's seven-TICKET split. Requires
[TICKET-00059](00059-TICKET.md) and [TICKET-00060](00060-TICKET.md); consumes EPIC-00006's
[trusted Harness](../epics/00006-EPIC.md), EPIC-00005's registered context and qualified execution
isolation where used. [TICKET-00065](00065-TICKET.md) owns real role-use integration; this TICKET owns reusable
current Agent authentication/authorization, not another Workflow.

Planning handoff from the accepted TICKET-00059/00060 decompositions: allocate this TICKET's actual activation,
current-use and relevant human/Workflow delegation/target-policy primitives before final Explorer integration.
Map their precise TASK IDs into [TASK-00231](../tasks/00231-TASK.md), [TASK-00232](../tasks/00232-TASK.md),
[TASK-00233](../tasks/00233-TASK.md) and [TASK-00241](../tasks/00241-TASK.md) as applicable; those records remain
`needs-info` for this mapping. Primitive implementations consume TASK-00228–00230 and TASK-00234–00240's real
participants, not the entire parents or TASK-00241's final integration. Writer/switch/restore acceptance and final
operational readiness then consume these controls, avoiding a cycle. No TASK for this TICKET has yet been proposed,
approved or allocated by that coordination handoff.
