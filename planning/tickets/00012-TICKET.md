---
id: TICKET-00012
epic: EPIC-00003
title: Establish the React and component-catalog foundation
status: ready-for-agent
---

# Establish the React and component-catalog foundation

## Problem statement

The browser application needs a production-backed React structure, typed API boundary, deterministic test surface, and durable component catalog before authentication and dashboard journeys are implemented. Without explicit state and authority ownership, credentials or authorization snapshots may leak into unsafe storage or inconsistent UI checks.

## Solution and boundaries

Record the client-authority/runtime-state ADR, then establish the React/TypeScript application entry point and responsibility boundaries for Routes, Layouts, Pages, Components/forms, feature API services, and one shared API client. Define typed snake_case transport mapping, cancellation, normalized failures, memory-only credential and refresh seams, authoritative principal-cache behavior, and fail-closed authorization seams.

Assign URL, transient local, API-cache, and application-context state to their narrowest owner. Establish Storybook from production components and semantic tokens plus Vitest/React Testing Library for behavior. Provide representative responsive and loading/empty/error/interaction states without selecting the final EPIC-00004 visual language or implementing product journeys.

Out of scope: login, activation, invitation, account, administration, and dashboard journeys; final visual design; credentials or business workflow state in browser storage; JWT/HTML authorization snapshots; and broad end-to-end automation.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Start and route the browser application | N/A — client navigation is not a Domain command | Read route metadata and current client authority state | Browser navigation/state changes are UI effects, not Domain events | Routes render through shared Layout/Page boundaries with deterministic unknown/loading/error handling |
| Call a typed API operation | The feature service maps an interaction to the documented API command endpoint | The feature service maps reads to documented API query endpoints | Server events are not recreated as client events | Shared client applies transport, cancellation, mapping, and normalized failure behavior |
| Resolve client authority | N/A — authentication journeys are deferred | Read the authoritative current-principal API-cache projection | Authority invalidation is a client coordination signal, not a trusted Domain event | Unknown/stale authority fails closed and route/component checks use one service |
| Develop and verify components | N/A — Storybook/test operations are tooling | Inspect component props, states, semantic tokens, and rendered behavior | N/A — no application domain event is produced | Production components and accepted states are rendered/tested without prototype promotion |

## Validation and permissions

Transport mapping must reject malformed or unexpected responses safely. Access credentials remain memory-only; refresh credentials remain inaccessible to JavaScript; no role/permission snapshot is trusted from base HTML or a JWT. Unknown permission state fails closed, and client visibility/guards never replace server authorization.

Only explicit non-sensitive presentation preferences may use browser persistence when a requirement allows it. Runtime configuration must be typed and contain no secrets. Components must support semantic markup, keyboard/focus behavior, deterministic loading/error/empty states, and narrow/wide rendering evidence appropriate to the foundation.

## Acceptance and evidence

- An accepted ADR records `/api/v1/me` authority, fail-closed checks, API-cache ownership, memory-only credentials, refresh-coordination seams, runtime configuration, and theme-preference bootstrap boundaries.
- React/TypeScript builds through the project toolchain with clear Routes/Layout/Page/Component/service/API-client responsibilities.
- Typed request/response mapping handles snake_case transport separately from camelCase client models and normalizes common failures/cancellation.
- Representative route, API-client, authorization-unknown, form/state, and error behavior has Vitest/React Testing Library coverage.
- Storybook renders production components and semantic-token examples rather than copied prototype code.
- Responsive/state evidence covers representative narrow/wide, loading, empty, failure, disabled, and focus behavior without claiming final product design.
- Focused frontend build/test/Storybook checks and `./bin/build` pass with fresh counts, artifacts, and warnings.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00025](../tasks/00025-TASK.md) | Accept the client-authority and runtime-state ADR | ready-for-agent |
| [TASK-00026](../tasks/00026-TASK.md) | Establish the React and TypeScript application shell | ready-for-agent |
| [TASK-00027](../tasks/00027-TASK.md) | Establish the typed shared API client boundary | ready-for-agent |
| [TASK-00028](../tasks/00028-TASK.md) | Establish fail-closed client authority and guarded routing | ready-for-agent |
| [TASK-00029](../tasks/00029-TASK.md) | Establish the production component catalog and state evidence | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the client direction approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md) and the client-authority gate selected by [WF-007](../wayfinder/tickets/WF-007-prepare-implementation-handoff.md). It follows [TICKET-00008](00008-TICKET.md) and integrates against [TICKET-00010](00010-TICKET.md)'s API contracts. Production visual work additionally depends on EPIC-00002 design capabilities.
