# Integrated accessibility baseline

TASK-00065 implements this WCAG 2.2 AA-oriented **verification baseline**, not formal conformance,
a screen-reader assessment, release approval or independent Review/QA acceptance. Production pages,
components, layouts and styles are exercised; no prototype implementation is imported.

## Durable route/state matrix

Story IDs are `<prefix>--<state>` in the built Storybook `index.json`. The table enumerates the named
production-backed states; it is not a Cartesian product of every possible state and permission.
Every catalog entry renders at **320×900** and **1280×900 CSS pixels**, DPR 1. The 19 entries whose
IDs contain `ready-light`, `long-email`, `long-unicode` or `confirmation` also render at **640×900**.
Thus the initial integrated inventory has **223 stories / 465 captures** (including existing reusable
component/authority examples). Explicit dark stories retain dark mode; other stories use their declared
mode. Captures use light OS preference and reduced motion. System tracking is exercised separately.

| Route / owner | Story prefix | Named states and permission variants |
|---|---|---|
| `/login`, TASK-00046 | `authentication-login` | ready-light, ready-dark, remember-keyboard, loading, unavailable, validation, generic-failure, throttled, outage, signing-in, signed-in |
| `/activate`, TASK-00043 | `authentication-activation` | ready-light, ready-dark, loading, metadata-unavailable, unusable-invitation (invalid/used/expired recovery), policy-validation, common-password, throttled, activated |
| `/reset-password`, TASK-00054 | `authentication-resetrequest` | ready-light, ready-dark, loading, unavailable, validation, pending, throttled, outage, accepted |
| `/reset-password/complete`, TASK-00055 | `authentication-resetcompletion` | ready-light, ready-dark, loading, metadata-unavailable, invalid-used-expired, validation, common-password, throttled, system-failure, pending, completed |
| Root restoration/session expiry, TASK-00048/00051 | `authentication-continuity` | restoring, ready, network, expired-light, expired-dark, explicit-reauthentication |
| `/` and protected frame, TASK-00051 | `application-frame` | wide-light, wide-dark, narrow-dark, long-email, forbidden (authenticated without destination permission), restoring-authority, malformed-authority, session-network-failure, session-expired, background-check-unavailable, dashboard-route-return, account-seam, invitation-seam, account-menu-keyboard, account-menu-long-email, account-menu-narrow-dark, drawer-keyboard |
| Dashboard `/`, TASK-00052 | `application-dashboard` | wide-light, wide-dark, system, narrow-dark, long-email, account-only, independent-recovery, forbidden, pending-authority, malformed-authority, sign-out-retires-dashboard |
| Account/Security `/account`, TASK-00051 | `application-frame` | account-seam, dashboard-route-return; truthful account landing, not an invented account editor |
| `/account/password`, TASK-00056 | `authentication-passwordchange` | ready-light, ready-dark, loading, metadata-unavailable, validation, wrong-current, common-password, throttled, system-failure, pending, completed |
| `/account/sessions`, TASK-00057/00058 | `security-ownedsessions`, `security-ownedsessionrevocation` | current-and-remembered, dark, loading, empty, failure, unknown-device, pagination, session-end; ready, confirmation, dark-confirmation, pending, success, conflict, failure (owned other-session confirmation/revocation) |
| Current-session logout, TASK-00047 | `authentication-current-session` | ready-light, ready-dark, pending, ended, uncertain |
| `/admin/users`, TASK-00059/00118 | `administration-users`, `administration-user-detail` | directory, dark, narrow, long-email, loading, empty, system-failure, server-forbidden, role-name-without-read, read-only, pagination-ready, pagination, retirement; safe-detail, dark, no-roles, loading, forbidden, missing, unavailable |
| `/admin/invite`, TASK-00061 | `administration-invite-user` | ready, dark, validation, pending, conflict, throttled, unconfirmed, system-failure, success, success-dark, invite-only-success (no READ_USERS continuation), long-unicode-input |
| `/admin/invite-super-admin`, TASK-00062 | `administration-invite-super-admin` | ready, dark, confirmation, confirmation-dark, cancel-and-return, validation, pending, conflict, throttled, unconfirmed, system-failure, forbidden (INVITE_USERS without elevation permission), success, manage-without-read, long-unicode-confirmation |
| `/admin/invitations/{safe-user-id}`, TASK-00060 | `administration-pending-invitation` | retryable-failure, dark, read-only (READ_USERS without management), loading, system-failure, activated, expired, provider-accepted, permanent-failure, long-unicode-email, resend-confirmation, correction-confirmation, pending-recovery, conflict, corrected |
| `/admin/invitations` registered landing | `application-frame` | invitation-seam; no fabricated invitation list |
| Unknown route / shared boot failure, TASK-00026 | `application-public-shell` | system-appearance, without-theme, narrow-dark, appearance-keyboard, not-found, not-found-dark, loading, system-error, system-error-dark |
| Implemented private Swagger route | `application-swagger` | collapsed-light, collapsed-dark, narrow-dark, explicit-read, create-role-input, renewed-authority, disabled, unavailable; supplementary inventory, not certification of arbitrary future API forms |

Existing `foundation-*` examples add Button ready/disabled/busy/keyboard, TextField association/validation,
Notice urgency, ContentState loading/empty/error/success, theme composition and fail-closed authority states.
No route or state is added to the production router for verification.

## Automated evidence

`./bin/client storybook-test` exercises production-backed story interactions and the existing Storybook a11y
addon. `storybook-capture` scans the **whole body**, including body-portaled confirmation dialogs, with axe
4.13.0 WCAG 2 A/AA, 2.1 AA and 2.2 AA tags. There are no rule or element suppressions. This explicit WCAG
selection is not all axe best-practice recommendations; the existing addon retains its own default checks.
For example, a whole-document best-practice scan reports `region` on the intentionally out-of-landmark skip
link. That recommendation is not used to move the skip link or claim missing production landmarks.
Existing owning component/route tests and recorded DOM inventory separately cover semantic contracts.

The capture waits for Storybook's installed render/play lifecycle to finish, waits for ready frame outlets
and fonts, then records axe violations **and incomplete checks**, headings/landmarks/live regions,
autocomplete/field associations, native controls and label bounds, focus, motion, resource requests and
horizontal geometry. Violations, overflow, render errors and unexpected external requests fail the gate.
This is direct product inspection, not a product-suite test of the inspection tool.

The integrated keyboard observer runs **11 paths at each of two widths (22 cases)** with native browser
Tab/Shift+Tab/Enter/Space/Escape/arrow input, not locator focus/click/fill:

- Login, activation, reset request/completion and password change: empty submission, first-invalid-field
  focus, forward/reverse navigation, Unicode/spaces insertion and keyboard submission; Login also toggles
  remembered state with Space. Fixture-owned generic/policy/throttled/success outcomes remain explicit.
- Ordinary invitation completion; elevated canonical long-Unicode confirmation, Cancel-first focus,
  bidirectional containment, Escape and return to the Review trigger.
- Owned-session confirmation, cancellation, trigger restoration and explicit successful revocation.
- Invitation delivery retry and email correction through Cancel-first confirmation to authoritative result.
- Users table/card pagination, heading focus and disabled terminal Next page.
- Account menu arrows/Escape, System/Light/Dark radios, narrow drawer return, Account route main focus,
  and logout retirement of authenticated controls.

Targeted Tab destinations must have visible, unobscured, in-viewport focus after layout settles (one CSS
pixel rounding tolerance). Steps record actual focus bounds/outline; this is not a claim that every focused
element in every possible scroll position was manually assessed. Existing stories additionally cover busy
submission suppression, destructive confirmations, recovery and route/session invalidation.

## Measured rendering and observations

The browser computes **84 semantic palette pairs** across light/dark backgrounds, surfaces, rails and raised
surfaces: text/muted/action/status at 4.5:1; border/focus at 3:1; on-action and error-background pairs at 4.5:1.
Observed minima were **5.63:1 text** and **3.08:1 boundary/focus**. Axe separately measures rendered text.
Measurements describe the default palette, not arbitrary themes, transparency, disabled-state policy or
colors that future components might introduce. Status meaning also uses visible text, not color alone.

Native inputs can be smaller than their activation region: the Appearance radio inputs are visually hidden
1px inputs with 44px labels; the Login checkbox has its associated label/spacing. The receipt retains both
input and label geometry, and WCAG 2.2 target-size/spacing rules run without exclusions. Inline text links
retain the inline exception; this is not a blanket assertion that all links are 44px targets.

Long/Unicode directory, identity, ordinary invitation and elevated confirmation examples wrap at 320px.
The long dialog can scroll; keyboard movement to its final action checks visibility rather than declaring
its full content must fit simultaneously. Intermediate 640px samples represent the effective layout of a
1280px viewport at 200%; 320px represents 400%. Those catalog samples are **not native zoom evidence**.

Separate native browser observations used **host Google Chrome 149.0.7827.156**, macOS arm64, a disposable
profile, default TLS trust and the actual task HTTPS origin. Chrome's native default zoom was set only in
that disposable profile through its settings API and read back at 100%, 200% and 400% (floating-point tolerance).
Five live public routes (`/login`, `/activate`, `/reset-password`, `/reset-password/complete`, unknown route)
passed **15 native zoom cases**, with effective CSS widths 1280/640/320, zero WCAG-tagged axe violations,
no horizontal overflow and no page errors. Missing grants deliberately exercise generic recovery, not real
activation/reset. Authenticated layouts use the deterministic production catalog, not real accounts.
Native viewport screenshots use CDP without resizing the zoomed page; they are viewport observations,
not full-page visual-regression assertions.

Live System mode followed emulated OS light → dark → light. Explicit Dark survived OS Light and reload;
explicit Light applied; returning to System removed the preference. Existing prepaint/runtime tests cover
the shared mapping. This is not a filmstrip measurement guaranteeing no flash on every device. Catalog
normal-motion/System and reduced-motion observations retain text meaning when spinners stop. Forced-colors
is Chromium media emulation only, not an observed Windows High Contrast installation.

## Defect chronology

**A65-01 — TASK-00026 shared recovery links / TASK-00050 default theme.** Newly catalogued actual NotFoundPage
and SystemErrorPage failed before repair: Bootstrap outline-primary blue `#0d6efd` against `#f6f3ef` measured
**4.06:1**, below 4.5:1. The owning browser regression reported **2 failed / 217 passed** stories. The before
capture receipt and test log are retained in TASK-owned evidence. Both native recovery links now use a scoped
semantic palette class with wrapping, 44px targets and supported Bootstrap hover/active variables. They remain
navigation links; no button/Space semantics or journey redesign was introduced. The light/dark recovery
stories and affected browser scans pass after repair. No blocking defect was waived.

## Evidence classes, review limits and retention

- **Automated:** axe, existing Vitest/RTL and Storybook assertions, native keyboard scripts, overflow/resource
  checks and artifact inspection. Automated output is a floor, not certification.
- **Computed:** contrast pairs, field/target/landmark/live-region inventory, focus bounds, fonts/motion and
  native zoom geometry. Live-region markup is not a recording of screen-reader speech.
- **Screenshots:** deterministic fake-data catalog and blank live public forms/recovery only. Sample images
  were visually inspected (400% native Login header, 320px long-Unicode confirmation and dark error recovery).
  Not every image receives subjective visual approval; no screenshot is a golden image or design acceptance.
- **Human/platform limits:** no screen reader, real password manager, OS clipboard, Safari, Firefox, mobile
  device/browser, touch/voice control, Windows High Contrast or exhaustive browser/AT matrix was tested.
  Unicode insertion and declared autocomplete/paste support do not establish real password-manager integration.

Axe incomplete results are retained, not counted as passes or suppressed. In the settled focused capture,
33 color-contrast records concern occlusion/overlap (including text behind open overlays); palette measurements
and corresponding unobscured states provide complementary evidence, not invented overlap ratios. Seven
`aria-prohibited-attr` records concern React-Bootstrap's redundant `aria-labelledby` on its generic account
container: the visible User Menu trigger and individual links/radios retain their own names; no required
control name depends on that optional generic-container label. Independent assessment can challenge these
observations; no screen-reader interoperability conclusion is claimed.

Raw evidence is ignored, task-owned and finite: canonical `.runs/tasks/TASK-00065/runs/` contains the Work
handoff, regression chronology and host-browser runner; the single runtime's canonical
`.runs/compose/fight-wt-0dc6c632abe3/build/reports/client/` contains catalog index, capture receipt/images and
native host-browser receipt/images while assessors consume them. Application runtime never depends on these
reports. TASK completion notes record the exact gate receipt, counts, warnings and independent review state.
