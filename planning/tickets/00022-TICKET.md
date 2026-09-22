---
id: TICKET-00022
epic: EPIC-00004
title: Verify the integrated security and accessibility baseline
status: ready-for-agent
---

# Verify the integrated security and accessibility baseline

## Problem statement

Individually complete journeys still need production-envelope evidence across HTTPS, browser headers, secret configuration, throttling, authorization, credential redaction, accessibility, and critical browser interactions. This verification must find integration gaps without becoming a late substitute for controls owned by each journey.

## Solution and boundaries

Establish trusted local HTTPS with production-like secure-cookie behavior and mandatory HTTPS elsewhere. Keep browser/API same-origin and CORS disabled by default. Enforce compatible CSP and standard security headers; external signing, encryption, and CSRF secrets; production startup failure for missing/weak secrets; and configured limits for login, activation, reset, refresh, and invitation delivery.

Verify integrated permission denials, audit evidence, credential/grant redaction, responsive and WCAG 2.2 AA-oriented behavior, safe failure/recovery, and multi-tab races. Add only critical browser smoke coverage for contracts that cannot be proved reliably below the browser boundary.

Out of scope: postponing earlier security/accessibility work, broad/flaky end-to-end suites, formal universal WCAG certification, CORS clients, production mail enrollment, deployment, and release certification.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Start in local/production security modes | N/A — runtime configuration/startup | Inspect environment, origins, secrets, TLS and policy configuration | N/A — no domain event | Trusted local HTTPS works; insecure/missing production configuration fails before serving |
| Exercise integrated browser security | Existing journey commands only | Existing journey queries plus response/header inspection | Existing business/security events only | Cookies, CSP/headers, CSRF/origin controls, rate limits, authorization, and redaction behave consistently |
| Exercise accessibility and responsive contracts | N/A — browser interaction/checks | Inspect rendered semantics, focus, contrast, reflow, targets, states, and reduced motion | N/A — no domain event | Critical journeys remain operable across keyboard and representative viewports/states |
| Exercise cross-tab/failure recovery | Existing auth/session operations | Observe authoritative session/principal state across tabs | Existing logout/revoke/reuse outcomes plus client coordination signals | Tabs converge safely after refresh races, logout, revocation, expiry, and terminal failures |

## Validation and permissions

Production startup rejects default, absent, malformed, or weak secrets and insecure canonical origins. Local trust enrollment is explicit and reversible. CSP/headers must cover application routes while Storybook/Swagger development exceptions remain narrowly environment-scoped. Rate-limit identity keys and diagnostics must avoid account disclosure and unbounded personal-data retention.

Security checks include anonymous, ordinary user, Super Admin, stale authority, and cross-user denial cases. Accessibility evidence distinguishes automated checks from keyboard/manual observations and records untested assistive technology/browser boundaries. Logs, errors, metrics/analytics, URLs, referrers, screenshots, and audit records are inspected for credentials/grants.

## Acceptance and evidence

- Trusted local HTTPS and production HTTPS/startup requirements are documented and freshly demonstrated.
- Same-origin/CORS, secure cookies, CSP, standard headers, secret validation, and every required rate limit have passing and denial evidence.
- Permission matrix and ownership tests prove fail-closed server behavior and safe `401`/`403` responses.
- Redaction review finds no passwords, refresh credentials, raw grants, credential-bearing URLs, or secrets in normal observability surfaces.
- Automated accessibility checks plus recorded keyboard/focus/reflow/contrast/target/reduced-motion observations cover critical routes and states.
- Minimal browser smoke tests cover only integration contracts unavailable below the browser boundary, including one multi-tab continuity/invalidation journey.
- Failures are deterministic and non-flaky; warnings, exclusions, manual evidence limits, and remaining operational enrollment are explicit.
- Full `./bin/build` passes after focused security/accessibility/browser verification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00063](../tasks/00063-TASK.md) | Establish the production-like HTTPS security envelope | ready-for-agent |
| [TASK-00064](../tasks/00064-TASK.md) | Prove integrated authorization throttling and secret safety | ready-for-agent |
| [TASK-00065](../tasks/00065-TASK.md) | Prove the integrated accessibility and responsive baseline | ready-for-agent |
| [TASK-00066](../tasks/00066-TASK.md) | Prove critical browser continuity and close the foundation gate | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Closes the integrated baseline approved by [WF-005](../wayfinder/tickets/WF-005-design-authentication-and-authorization-journeys.md) after TICKET-00014 through TICKET-00021 are representative. Security and accessibility controls remain acceptance requirements of every preceding journey rather than work deferred here.
