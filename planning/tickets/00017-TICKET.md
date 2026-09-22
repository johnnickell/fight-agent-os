---
id: TICKET-00017
epic: EPIC-00004
title: Deliver secure browser authentication and session continuity
status: ready-for-agent
---

# Deliver secure browser authentication and session continuity

## Problem statement

Active users need secure password login and bounded long-lived continuity across reloads and tabs. Rotating refresh credentials, request races, and ambient cookies require coordinated server/client controls rather than token persistence in browser storage.

## Solution and boundaries

Deliver canonical email/password login, remember-me, refresh rotation, current-session logout, and cross-tab coordination through `AuthenticationService`. Access JWTs last 15 minutes, remain memory-only, and are sent as Bearer authorization. Opaque refresh credentials remain only in narrowly scoped Secure/HttpOnly/SameSite cookies.

Ordinary sessions use one-day idle/two-day absolute limits and browser-session cookies. Remembered sessions use 30-day idle/one-year absolute limits and persistent cookies. Implement the accepted JWT claim validation, externally configured secrets, production throttle, CSRF/Origin/Fetch Metadata checks, refresh conflict/reuse handling, and bounded multi-tab leader/broadcast behavior.

Out of scope: roles/permissions in tokens, credential `localStorage`, infinite retries, logout-everywhere, MFA, passkeys, magic links, SSO, usernames, and social login.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Log in | `AuthenticationService::login()` | Resolve canonical active identity, password hash, policy, and throttle state | `UserLoggedIn` | Refresh session and credential are created; access token is returned; cookie is set safely |
| Refresh authority | `AuthenticationService::refresh()` | Resolve refresh credential/session and authoritative user version/state | Package refresh/session lifecycle outcomes | Credential rotates, idle deadline advances within absolute limit, cookie and access token are replaced |
| Log out current session | `AuthenticationService::logout()` | Resolve current refresh credential/session | `CurrentSessionLoggedOut` and session revocation outcome | Server session is revoked, cookie expires, in-memory authority clears across tabs |
| Coordinate tabs | N/A — Web Locks/BroadcastChannel client coordination | Read in-memory token expiry and shared refresh/logout signals | Client-only coordination signals, not trusted Domain events | One leader refreshes; siblings receive memory-only result/invalidation; bounded fallback handles unsupported APIs |

## Validation and permissions

Login returns generic failures for nonexistent, pending, disabled, deleted, or wrong-password identities and applies rate limits. Access-token authentication validates signature, expiry, token type, subject, session ID, authentication version, and accepted issuer/audience constraints before authoritative principal resolution. Refresh/logout enforce exact origin, CSRF, Fetch Metadata where supported, JSON expectations, and cookie scope.

Login is public but throttled; refresh/logout require the ambient credential plus request protections. Bearer endpoints require server-resolved current authority. Refresh conflicts get one bounded recovery; suspected reuse or terminal failure revokes/fails closed without diagnostic leakage.

## Acceptance and evidence

- Ordinary and remembered login produce exact configured idle/absolute lifetimes and cookie persistence.
- Access JWTs are memory-only; refresh credentials are HttpOnly and absent from JSON, logs, storage, and analytics.
- Invalid claims, revoked/expired sessions, changed authentication versions, conflicts, and reuse fail safely.
- CSRF/origin/fetch-metadata tests cover permitted and denied requests.
- Multi-tab tests/demonstration prove leader election, broadcast, crash/timeout recovery, logout propagation, and bounded fallback without persisted credentials.
- Safe intended-route restoration excludes secret form contents; `401` and terminal expiry behavior is accessible.
- Focused security, PostgreSQL race, HTTP, client tests and `./bin/build` pass.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00044](../tasks/00044-TASK.md) | Enforce complete access-token authentication | ready-for-agent |
| [TASK-00045](../tasks/00045-TASK.md) | Enforce browser authentication request controls | ready-for-agent |
| [TASK-00046](../tasks/00046-TASK.md) | Deliver the canonical email and password login journey | ready-for-agent |
| [TASK-00047](../tasks/00047-TASK.md) | Deliver rotating refresh and current-session logout | ready-for-agent |
| [TASK-00048](../tasks/00048-TASK.md) | Coordinate bounded multi-tab session continuity | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the session profile from [WF-005](../wayfinder/tickets/WF-005-design-authentication-and-authorization-journeys.md). It depends on active managed identities from [TICKET-00015](00015-TICKET.md), the browser-security ADR/API foundation in [TICKET-00010](00010-TICKET.md), the client foundation in [TICKET-00012](00012-TICKET.md), and accepted design [TICKET-00014](00014-TICKET.md).
