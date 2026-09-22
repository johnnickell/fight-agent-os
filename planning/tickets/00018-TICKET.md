---
id: TICKET-00018
epic: EPIC-00004
title: Deliver the authoritative application shell and dashboard
status: ready-for-agent
---

# Deliver the authoritative application shell and dashboard

## Problem statement

Authenticated users need one trustworthy authority projection and an honest destination before planning and observability features exist. Duplicated permission logic or stale token/HTML snapshots would produce misleading UI and unsafe route assumptions.

## Solution and boundaries

Expose `/api/v1/me` from request-scoped `CurrentPrincipalProvider`, returning only safe identity, role names, and permission names. Cache it for the active client session, fail closed while unknown, and invalidate/refetch after refresh or authority-changing operations. Use one client authorization service for complete-route metadata and fine-grained navigation/component visibility; server checks remain authoritative.

Implement public/protected root routing, safe intended-route restoration, responsive shell/navigation/user controls, account/session links, authorized admin shortcuts, explicit `401` restoration and `403` forbidden handling, and an honest empty dashboard. Implement accepted semantic light/dark modes and accessible `system`/`light`/`dark` preference persistence.

Out of scope: permission snapshots in JWT/base HTML, role-name bypasses, fake charts/metrics, planning/observability/agent data, and workspace switching.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Resolve current authority | N/A — read-only endpoint | `CurrentPrincipalProvider::getCurrentPrincipal()` plus safe identity projection | N/A — no domain event | `/api/v1/me` returns current authoritative safe roles/permissions |
| Enter the application | N/A — navigation | Read authentication restoration and current-principal cache | Client navigation signal only | Anonymous user reaches Login; authenticated user reaches intended route or Dashboard |
| Authorize client presentation | N/A — visibility/guard decision | Query shared current-principal authorization service | N/A — client checks are not security events | Unknown authority fails closed; routes/links/components use consistent permission names |
| Select theme | N/A — presentation preference | Read OS media preference and explicit local override | Browser preference signal only | `system`, `light`, or `dark` applies before/through rendering without storing business/credential state |

## Validation and permissions

`/api/v1/me` requires valid Bearer authentication and authoritative active user/session/version resolution. Views exclude hashes, grants, credentials, internal audit data, and unnecessary personal/session fields. `VIEW_DASHBOARD` controls dashboard access; admin shortcuts use their exact permissions. A `403` does not leak protected data or redirect as anonymous.

Theme controls need accessible names, keyboard operation, visible current state, OS-change handling in system mode, reduced-motion compatibility, and no avoidable theme flash. Routes preserve only validated same-application destinations.

## Acceptance and evidence

- `/api/v1/me` reflects current repository authority and changes without waiting for token expiry.
- Unknown/stale principal state fails closed and invalidates correctly across refresh/logout/authority changes.
- Server permission denials and client route/component behavior use the same catalog without treating client checks as enforcement.
- Root, intended-route, `401`, `403`, not-found, loading, expiry, and system-error states are responsive and accessible.
- Dashboard contains only honest shell, account/security links, and authorized shortcuts.
- Three-state light/dark preference follows OS by default and persists only explicit presentation choice.
- Focused authorization, HTTP, component/Storybook, accessibility and `./bin/build` evidence passes.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00049](../tasks/00049-TASK.md) | Deliver the authoritative current-principal projection | ready-for-agent |
| [TASK-00050](../tasks/00050-TASK.md) | Deliver the accessible production theme preference | ready-for-agent |
| [TASK-00051](../tasks/00051-TASK.md) | Deliver the responsive authoritative application frame | ready-for-agent |
| [TASK-00052](../tasks/00052-TASK.md) | Deliver the honest permission-aware dashboard | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Depends on authentication [TICKET-00017](00017-TICKET.md), client/API foundations [TICKET-00012](00012-TICKET.md) and [TICKET-00010](00010-TICKET.md), and accepted design [TICKET-00014](00014-TICKET.md).
