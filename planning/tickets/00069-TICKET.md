---
id: TICKET-00069
epic: EPIC-00015
title: Confirm email change explicitly and return to login
status: ready-for-agent
---

# Confirm email change explicitly and return to login

## Problem statement

Mail scanners and browser prefetchers can visit a confirmation URL without the user's consent. A link visit
must not change the account, while a deliberate one-time confirmation must promote the new address, invalidate
old authentication authority and offer a clear way back to login without treating the link as a session.

## Solution and boundaries

Deliver a public, accessible **two-request** confirmation journey using the *same* purpose-bound new-mailbox
secret: the link opens a safe non-mutating landing page, and an explicit user action submits that captured secret
again to `AuthenticationService::confirmEmail()`. The landing may offer safe validity guidance only through a
qualified read-only path; inability to validate on GET must not trigger a mutating probe. Capture and promptly
remove token-bearing URL material from visible history, keep it in volatile memory only as long as necessary for
the explicit submission or bounded retry, and prevent reappearance in markup, logs, referrers, analytics,
telemetry, audit or screenshots. Do not auto-submit on load or use a state-changing GET. On successful package
confirmation, consume the credential, promote the reserved new address, revoke active sessions and advance
authentication authority under package policy, and atomically establish the **old-address completion notice
intent** qualified by [TICKET-00066](00066-TICKET.md). Discard all local client authority, including stale
multi-tab results. Show a login link: the user signs in using the *new email and existing password*, or starts
the **separate** password-reset request if forgotten. The confirmation credential never logs in the user.

No password requirement for confirmation, new-email form, old-mailbox approval, second verification secret,
login-token issuance, reusable magic link, password-reset completion or direct email update belongs here.
Invitation activation and reset remain owned by their existing TICKETs; the new confirmation page may reuse
safe shared client/transport controls without conflating token purposes.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Open link | N/A — GET is non-mutating | Optional qualified read-only, generic validity check; no private account read | N/A — no business event on landing | Safe page/expiry guidance; no grant consumption, promotion, email, session or notice |
| Confirm deliberately | Package `AuthenticationService::confirmEmail()` with exact user/credential pair on explicit protected submit | Package resolves latest grant, active User and reservation; caller receives only safe result | Package `EmailChangeConfirmed` after commit; durable Agent OS old-mail completion intent under TICKET-00066 | One confirmed new canonical email and consumed grant; all previous sessions invalidated; no new session |
| Continue after result | N/A — navigation, or a *separate* existing reset request if chosen | Existing safe login/reset destinations | N/A for navigation | Local authority discarded; link to login and generic expired/used/conflict recovery |

## Validation and permissions

Possession of the *new-mailbox* confirmation secret (bound by package to user, purpose, latest grant,
reservation and one-hour expiry) is confirmation proof; no prior session or password is needed. Protect the
public landing and submit with exact trusted HTTPS origin, no-store, restrictive referrer behavior, bounded
throttling/retry and safe body/URL handling; reject malformed, repeated, replaced, cancelled, expired or wrong-
purpose credentials generically without identity enumeration. Confirm uses package fencing against concurrent
cancel, expiry, duplicate submit, changed reservation and session changes; no client or role authority overrides
that decision. After success, old Bearer/refresh state and tabs are non-authoritative even if notification
provider dispatch is delayed. Audit is durable and redacted, not a raw credential or link log. Confirmation does
not authorize other commands, including reset or fresh login.

## Acceptance and evidence

- Link GET/prefetch never mutates, consumes, sends an old-mail completion notice or logs in; only an explicit
  second submission of the **same** credential confirms once. Accessible loading/confirm/success/expired/error
  states work without token disclosure in history, markup or telemetry.
- Real PostgreSQL and browser/security checks show one winner under duplicate/cancel/expiry/reservation races,
  correct new canonical email, session/version revocation and local multi-tab authority clearing. An old token
  cannot be reused for API access or login; password reset remains a separate journey.
- Actual new-mailbox delivery, secret-free completion notice to the captured *old* recipient and crash/restart
  notice recovery are verified with TICKET-00068 and TICKET-00066; no success claim when the notice intent
  cannot be made durable atomically.
- Owned package-consumer/HTTP/client behavior tests, direct provider/security observations and
  `./bin/build` pass against the selected tagged/source/locked package; warnings and unverified deployment
  enrollment remain explicit.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of EPIC-00015's four-TICKET split. Consumes
[TICKET-00066](00066-TICKET.md)'s contract gate and [TICKET-00068](00068-TICKET.md)'s actually delivered
credential, then returns to [TICKET-00017](00017-TICKET.md)'s login or
[TICKET-00019](00019-TICKET.md)'s independent password-reset request. The
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) browser profile governs surviving
sessions and client authority. Neither the safe landing nor the product confirmation was implemented by this
planning record.
