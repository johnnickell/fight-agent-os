# Ordinary browser invitations

`/app/admin/invite` presents TASK-00061's ordinary invitation journey. Both the complete protected route
metadata and the existing server Action require exact current `INVITE_USERS`. A displayed Role name grants
nothing. The browser uses the sole session/AuthorityCache graph and rechecks dispatch authority; server
admission remains authoritative. Unknown/stale authority cannot submit. No user-availability query precedes
creation.

## Request and outcome

The form has one email field, with autocomplete, paste, keyboard submission, associated instructions/errors,
visible focus and pending duplicate suppression. It trims surrounding whitespace and lowercases on submission,
checks a 254-character usability bound and basic syntax, and leaves semantic email/conflict policy to the
server/package. Unsupported Unicode addresses receive controlled rejection, never an invented identity.
Input remains volatile; it never enters history, storage, diagnostics or analytics.

`OrdinaryInvitationService` sends exactly `POST /api/v1/invitations` with JSON `email` and fixed
`mode: ordinary`, using current memory-only Bearer transport. There is no role, actor, elevation, permission,
password, provider, lifetime, template, confirmation or redirect input. The server chooses stable managed
`ROLE_USER`; UI copy is explanatory, not assignment authority. Elevated issuance remains a separate unavailable
route owned by TASK-00062.

The strict existing issuance codec checks exact keys, UUIDs, assignment/audit categories and nullable package
delivery fields. A 201 is accepted only with known safe target ID, confirmed assignment/audit and a valid
delivery observation. The feature then retains **only the safe target ID**, discarding all step/delivery
metadata. Structured server validation prose is not displayed. No grant, digest, ciphertext, credential URL,
provider payload or audit internals enter feature state.

- **Accepted:** authoritative invitation/ordinary assignment/audit observations were confirmed. Delivery work
  may still be pending or failed; no provider delivery, recipient receipt or activation is claimed.
- **Rejected:** controlled validation, conflict/policy drift, throttle or authority feedback permits deliberate
  correction/retry. No partial success is claimed. No mutation is replayed automatically.
- **Unconfirmed:** partial 503, network/system/protocol/unavailable response or the eight-second local deadline
  may follow committed work. The input is cleared and resubmission is not offered in that result. Inspect
  current users/status with an authorized reader/operator before another attempt; leaving cannot undo work.
- **Retired:** authority-context changes, unmount and request generations suppress obsolete successes and
  failures. A submitted mutation continues independently; the local deadline is not server cancellation.
  A late transport completion still invalidates affected reads without changing the displayed outcome.

Every completed network attempt invalidates user-directory/detail generations; a known target also retires
outstanding/adopted invitation status through the existing status service. The deadline invalidates directory
reads once and late completion invalidates again. No mutation payload is adopted as a directory/status View.
Status continuation is offered only with exact current `READ_USERS` and a classified safe UUID. Management
alone grants no reading. Otherwise the permitted Users, Dashboard or authenticated Account destination is
shown. Recovery remains separately governed by `MANAGE_USER_INVITATIONS` and TASK-00060.

## Qualified existing backend, not a new composition

The unchanged Composer lock supplies Access Control **v0.8.0** at
`319738255e682d587642b67856039f882b9588d6` and Common **v1.4.1** at
`a482c220d312d2ef7762c3f0d85d9fb76a7021fa`. The consumer uses the existing versioned HTTP Action/Responder,
not guessed new package APIs or an upgrade. Existing PostgreSQL/HTTP tests qualify canonical conflicts,
managed role selection, pending identity, seven-day grant, durable package delivery, audit, races, limits,
partial failure and redaction. See [the API contract](INVITATION_API.md).

Current issuance commits invitation, assignment and confirmation audit independently. Early delivery and
partial/uncertain outcomes remain possible. Its redundant Application admission/coordination is a known
TASK-00577 correction target, not the normative architecture and not repaired or reproduced in this UI TASK.
The currently protected invitation/read/manage permissions belong to the exact managed Super Admin role;
removing managed memberships causes policy drift. HTTP permission combinations therefore return 403 for
missing INVITE_USERS, or controlled 409 for drift after admission, not newly supported custom delegation.
Frontend fixtures independently exercise invite-only, reader, manager and misleading-role presentation.

## Verification boundaries

The production form, fixed transport, strict result projection, sole-graph invalidation, permission changes,
duplicate/deadline/retired results, volatile fields and marker redaction have owning Vitest/RTL tests.
Twelve production-backed Storybook states cover ready/light/dark, validation, pending, conflict, throttling,
uncertainty, system failure, success, invite-only continuation and long Unicode input. Catalog interaction,
axe, 320px/1280px captures and production build checks qualify their own boundaries, not real inbox delivery,
all browser engines, physical assistive technology or universal WCAG compliance. Independent Review and
behavioral QA remain separate. Exact current gate results, warnings, captures and browser prerequisites
belong in TASK-00061 and its canonical Work handoff.
