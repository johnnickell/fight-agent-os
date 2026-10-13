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
| [TASK-00288](../tasks/00288-TASK.md) | Open confirmation links without changing account state | ready-for-agent |
| [TASK-00289](../tasks/00289-TASK.md) | Confirm explicitly and return to fresh login | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of EPIC-00015's four-TICKET split. Consumes
[TICKET-00066](00066-TICKET.md)'s contract gate and [TICKET-00068](00068-TICKET.md)'s actually delivered
credential, then returns to [TICKET-00017](00017-TICKET.md)'s login or
[TICKET-00019](00019-TICKET.md)'s independent password-reset request. The
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) browser profile governs surviving
sessions and client authority. Neither the safe landing nor the product confirmation was implemented by this
planning record.

TICKET-00066's approved [TASK-00275](../tasks/00275-TASK.md) supplies atomic completion-notice capture,
including TASK-00274's original-recipient binding and TASK-00273's still-unresolved supported contract;
[TASK-00277](../tasks/00277-TASK.md) supplies qualified operational notice recovery. Map those concrete
prerequisites alongside TICKET-00068's allocated credential primitives in the approved TASK split below. Its safe GET,
explicit submit, public credential transport, session/client invalidation and login return remain owned here.
The lower-level notice primitives do not depend on this completed browser journey. Actual provider delay never
postpones committed session revocation; failed atomic notice persistence prevents the whole confirmation commit.
A post-commit exception or uncertain acknowledgement is not proof of rollback or permission for automatic replay.
That coordination mapping enabled no exposure; the subsequent approved TASK split is recorded below.

TICKET-00067's approved TASK-00278–00282 now own current self/admin authority, safe account status, self request,
exact confirmed cancellation and cross-user detail integration. Reuse their actual qualified facts where needed,
not a duplicate account/status owner. TASK-00280 now consumes TASK-00287's actual credential operation;
TASK-00281 retains its own supported generation-fencing/self-audit gate and must be exercised against this
confirmation path's real winner semantics. Its cancellation does not require successful sending or this finished
browser journey. Bearer confirmation here remains independent of a preexisting human session/admin Permission,
with one-time package policy and the separately owned notice seam; no administrator bypass is introduced.

TICKET-00068's accepted TASK-00283–00287 now map protected issuance, shared real transport, exact link delivery,
existing-runner recovery and scheduled credential operation. Consume TASK-00285's fixed configured-HTTPS link/
encoding contract and TASK-00287's actual delivery evidence. The package invocation lacks User ID/expiry; its
producer resolves necessary immutable facts through the exact delivery identity, never address-based inference.
This TICKET still owns safe nonmutating landing, volatile capture/history removal, same-secret explicit submit,
confirmation atomicity/session effects and actual login return; receipt of mail alone does not prove those paths.

TASK-00277 and TASK-00287 independently consume TASK-00284's shared provider, retaining separate notice/credential
lifecycle and schedules. Their complete journeys never become reciprocal blockers or depend on this completed
browser journey. Retain TASK-00273's supported atomic notice hold and actual provider/schedule authority limits.

### Approved TASK decomposition

The maintainer approved two independently reviewable use cases, reusing the already owned atomic notice,
credential/provider/recovery and authentication boundaries rather than adding a proof-only final TASK:

| TASK | Outcome | Blockers and holds |
|---|---|---|
| [TASK-00288](../tasks/00288-TASK.md) | Open confirmation links without changing account state | TASK-00285 and TASK-00051; ready behind unfinished producer/public-frame foundations |
| [TASK-00289](../tasks/00289-TASK.md) | Confirm explicitly and return to fresh login | TASK-00288, TASK-00275, TASK-00277, TASK-00287, TASK-00282 and TASK-00063; `needs-info` for supported atomic notice/reservation guarantees and actual operational qualification |

TASK-00288 consumes the fixed producer path/encoding and reuses TASK-00043's qualified fragment bootstrap through
the existing public frame. Capture is bounded, volatile and promptly removed from history before ordinary page
behavior. Legitimate external-mail navigation is supported without applying mutation-only Origin rules to GET.
GET/HEAD/prefetch never confirms, sends, logs in, refreshes, restores authentication or reads private `/me`. Neutral
local capture guidance deliberately omits the parent's optional validity query; no mutating probe or fabricated
valid/expired account state is allowed. Reload, teardown, history failure and restored pages cannot resurrect
abandoned secrets. Landing alone neither enables a confirmation control nor qualifies the complete journey.

TASK-00289 supplies the real deliberate button, typed client and guarded public POST using the same captured
credential. Reuse the existing request/rate controls and explicit public Action classification; grant possession
is proof, not an ambient session or administration Permission. CSRF proof remains non-authoritative transport
protection, not a second mailbox secret. No password/new-email form, actor/recipient override, automatic submit,
login/reset/reissue or new validity/history API is added. Exact input and secrets stay out of ordinary diagnostics,
markup, persistence, screenshots, audit and shared client messages.

**Observed qualification hold:** the inspected `AuthenticationService::confirmEmail()` checks latest usable grant,
credential and destination equality but does not explicitly compare the bound reservation revision or call
`matchesReservation()`. Exact generation/reservation fencing across supported writers and same-address successor/
ABA cases must be qualified through TASK-00273/00275. This is not a demonstrated exploit or authority to patch
upstream. Missing guarantees require separate owner/tagged-contract resolution, never copied package policy,
nontransactional prechecks, hidden repository side effects or an outer nested transaction.

The service also returns `void` and publishes its event after commit. TASK-00275 remains owner of atomic grant/
User/authentication/session/audit/completion-intent coordination and exact original-recipient binding. Required
notice failure rolls back the whole change; later provider delay does not undo promotion or postpone session
revocation. An exception/lost response may follow success. Observe original outcome only through a qualified
appropriately protected seam, otherwise retain truthful uncertainty; absent pending state, latest email or a
rejected replay never proves the original result. No automatic mutation replay follows recovery or refresh.

TASK-00289 connects TASK-00048/00049's shared auth/principal/private-cache generation barriers and existing exact
cookie lifecycle. Clear old local authority and navigation intent on success/uncertainty, fence delayed results
against newer explicit contexts and keep target-server revocation distinct from conservative local clearing of
another browser identity. Signals remain bounded best-effort; missing APIs/suspended tabs do not weaken server
checks or justify persistent coordination. No new session is issued. Existing TASK-00046 login and TASK-00054
reset-request routes remain their owners: new email plus unchanged password for login, separate reset if forgotten.

TASK-00282 supplies actual self/admin initiation and exact cancellation through TASK-00280/00281. The complete
confirmation uses actual authorized new-mail receipt and secret-free completion to the original old mailbox,
including post-commit notice recovery. TASK-00277/00287 keep independent worker/provider ownership; valid
attributable receipts may be reused with affected integration refreshed. No repeated credential ceremony,
provider, queue, private status owner or final proof registry is introduced. Final browser acceptance never
reverse-blocks these primitives. Initial exposure needs qualified operations; an already-held usable credential
can still confirm during provider/delivery-key outage when the required atomic persistence is available.

Each slice owns observable behavior, applicable real PostgreSQL/HTTP/browser/security evidence and `./bin/build`.
Configuration, infrastructure, generated artifacts and migrations are validated directly through their tools,
not product tests of wiring/planning. Actual effects require bounded target/mailbox/process authority. A discovered
bug needs a failing regression before authorized repair; local or historical receipts are not live qualification.

All new orders remain unranked; active TASK-00138/TASK-00226 and existing priorities are unchanged. All four
EPIC-00015 TICKETs now have approved written TASK splits, not completed implementation. With TICKET-00070–00082,
**all 24 map TICKETs are decomposed**. Older Harness/managed review planning remains incomplete.
Next: `/skill:next` for read-only Board routing.
No route, account change, session revocation, mailbox/provider/schedule enrollment or live effect follows from
these records. Implementation/worktree choice, independent review, publication and operational effects remain separate.

Decomposition verification: `./bin/planning-check --write` and `./bin/planning-check` passed with 389 records,
302 active; `git diff --check` passed. `./bin/build` passed with 459 tests, 3499 assertions, 78 captures and zero
violations, with repetitive deprecation warnings. Evidence: `.runs/build/20261012T223226Z-57425`; summary log:
`.runs/build/ticket-00069-to-tasks.log`. These validate planning and the existing repository, not future link
capture, exact reservation/notice qualification, confirmation/session/browser behavior or actual mail/login
journeys. Independent review and publication remain unperformed.
