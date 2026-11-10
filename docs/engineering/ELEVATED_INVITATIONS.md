# Confirmed elevated browser invitations

`/admin/invite-super-admin` is a separate pending-invitation journey. Its complete route metadata,
sole-cache action checks and shared issuance service require **both exact INVITE_USERS and
ASSIGN_SUPER_ADMIN**. The existing `POST /api/v1/invitations` independently resolves current server
principal authority. Role names, HTML/JWT/client claims and one permission alone grant nothing.

## Confirmation and transport

The email-only form reuses ordinary invitation canonicalization (trim, lowercase, bounded syntax and
254-character usability feedback). Server/package semantics remain authoritative, including Unicode
rejection, canonical conflicts and managed-policy drift. No identity-availability read precedes issuance.
Review opens a named, Cancel-focused modal showing the canonical target, managed ROLE_SUPER_ADMIN,
installation-wide consequences, invite-only seven-day activation, actor accountability and independent
commits. Nothing is preselected or submitted by opening it. Cancel/Escape returns focus to Review;
Tab stays within the dialog. Editing the target or losing authority retires that confirmation.

Only a deliberate Confirm sends exactly `{email, mode: "super_admin", confirm_super_admin: true}`.
The typed service checks confirmation against the current canonical target before dispatch and checks
fresh authority again. Missing/false/wrong-type/mismatched confirmation is not dispatched. The server
requires literal boolean true; duplicate JSON keys are rejected. Pending duplicates are suppressed.
There is no arbitrary actor, target ID, role/permission selection, password, provider, lifetime or
bootstrap input. Ordinary `submit()` still sends only fixed ordinary mode; hidden fields cannot select
elevation. `InvitationIssuanceService` owns the shared bounded transport, strict existing response
codec and invalidation for both explicit operations; the former ordinary-only service is removed.

## Outcomes and investigation

A strict 201 needs confirmed assignment, confirmed audit and a valid delivery observation. Only the
safe target UUID survives projection, not email, membership/audit snapshots, delivery internals or
credentials. Success confirms observed steps, not provider delivery, inbox receipt or activation.
Controlled validation/conflict/throttle/authority results permit deliberate correction and a **new**
confirmation; no operation is replayed automatically.

Partial 503, network/system/protocol/unavailable outcomes and the eight-second deadline are terminal
**unconfirmed** browser results: clear form/confirmation, stop issuance, invalidate stale directory/detail
observations and known-target status. Late completions invalidate again but cannot replace retired UI.
Routine same-authentication-context freshness/foreground verification clears sensitive form and
confirmation values and forbids fresh dispatch, but cannot resolve an elevated operation. The safe
unconfirmed investigation disposition survives verification; a pending submission whose scope retires
becomes unconfirmed immediately. Its late result cannot restore issuance or claim success. Safe status
links require freshly verified READ_USERS again. Logout, authentication/identity replacement and verified
loss of an elevated permission clear the old context's disposition; no state is persisted or replayed.
A local deadline/unmount cannot cancel a server commit. There is no reinvite, compensation or browser
assignment-repair control. Trusted operators must investigate identity, membership, required audit and
package delivery using the [invitation runbook](INVITATION_API.md#independent-issuance-commits).
READ_USERS permits deliberate safe user/status inspection only; it cannot establish audit completion.
Retry/resend does not resume or repair elevated assignment. Those delivery operations retain their
separate MANAGE_USER_INVITATIONS contract and must not be used as elevation recovery.

Status links and Users navigation require exact current READ_USERS, including after success or partial
outcome with a safe known ID. Management alone grants no reading. Without read authority only safe
success/investigation guidance and a permitted Dashboard or authenticated Account destination appear.
Form values, target authority and permissions are neither persisted nor broadcast. History contains no
form data; only classified safe status paths may contain the known UUID.

## Consumed backend and limits

No endpoint, orchestrator, transaction wrapper or package upgrade is introduced. The unchanged lock
uses Access Control v0.8.0 (`319738255e682d587642b67856039f882b9588d6`) and Common v1.4.1
(`a482c220d312d2ef7762c3f0d85d9fb76a7021fa`). TASK-00041's current Action/issuance composition
resolves only the stable managed Role and uses configured package buses. Origin (identity, seven-day
grant, recoverable encrypted delivery and user.invited audit), assignment and confirmation audit commit
independently. Delivery can start before assignment. Later failure can retain invitation, sent mail or
membership without complete audit. Exception does not mean rollback.

The current redundant Application admission/coordination is TASK-00577's explicit source-correction
boundary, not the normative architecture or a new pattern added here. This UI does not implement that
cutover. Admission-time authority suffices under the current shared standard; no new revocation/commit
locking is added. This is creation of a newly invited pending identity, **not** standalone active-user
promotion, arbitrary role administration, demotion or bootstrap bypass.

Owning tests cover exact permission combinations, stale/misleading authority, confirmation, injection,
managed assignment, safe audit, PostgreSQL partial commits and competing canonical requests. Vitest/RTL
covers the shared fixed transport, deadline/late results, sole-graph invalidation, explicit dialog,
keyboard/focus, permission loss, unmount, READ_USERS combinations and marker redaction. Fifteen
production-backed stories cover warning, light/dark confirmation, cancel/return, validation, pending,
conflict, throttle, uncertainty, system, forbidden, success, manage-without-read and long Unicode target.
Catalog Chromium interactions, axe and narrow/wide reduced-motion captures are bounded evidence, not
real inbox delivery, all browser engines, physical assistive technology or universal WCAG certification.
TASK-00062 and its Work handoff own exact runs, warnings, captures and remaining independent acceptance.
