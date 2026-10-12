---
id: TICKET-00063
epic: EPIC-00014
title: Administer named managed Agents and safe lifecycle intent
status: ready-for-agent
---

# Administer named managed Agents and safe lifecycle intent

## Problem statement

A stored Agent row or semantic template cannot be used by a human. Administrators need a truthful, authorized
journey to select a reviewed versioned role, name a distinct Agent, assign its real Team and Repository scope,
preview an eligible desired Permission set, confirm provisioning and inspect lifecycle/operation status without
receiving a raw secret or treating issuance as active use.

## Solution and boundaries

Make the eight reviewed managed SDLC templates from
[TICKET-00034](00034-TICKET.md) real versioned Harness resources eligible for distinctly named Agent
creation, and incorporate the separately approved Triage template under its existing EPIC-00012 owner. This
TICKET integrates the managed catalog with Agent creation; it does **not** redefine the roles, build a second
template registry or implement [EPIC-00017](../epics/00017-EPIC.md)'s AI-assisted template editor.
From one safe Super Admin UI, show authorized Agent list/detail, current name, immutable identity, Team and
Repository scope, selected template revision, safe actual-versus-requested direct Permissions, usable/unavailable
state and package operation status. Select a registered Team and allowed Repository scope, preview the **complete**
reviewed template desired Permission set, confirm a distinct Agent provisioning intent and inspect its issued,
delivered, activated and usable outcomes separately. Offer name-only updates through the package handler and
confirmed rotation/revocation intent through [TICKET-00060](00060-TICKET.md). Never present a finished Agent on
partial delivery; support safe original-operation status/recovery rather than creating a second Agent on retry.
Only an active qualified credential and current tool authorization under [TICKET-00061](00061-TICKET.md) make
an Agent usable. Future eligible custom templates may be selectable through EPIC-00017's **separate** approved
publication flow; no arbitrary browser prompt or permission list provisions a managed Agent here.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Create a named Agent | Authorized Agent OS creation intent plus package provision with retained operation key | Reviewed Harness template revision, Team/Repository scope, eligible complete desired set and sink readiness | Package issuance and attributable creation intent only after commit | One Team-assigned Agent with its own protected holder and truthful delivery status |
| Inspect/manage Agent | Package list/detail/status, name-only `UpdateAgent`, confirmed rotate/revoke intent | Fresh Agent/operation/roster/Permission state and expected revisions | Existing package change facts and safe audit; N/A for read | Safe Views; name-only update, recoverable credential replacement or current revocation |
| Preview complete managed Permissions | No grant from reading; separate authorized complete-set reconciliation if approved | Authoritative Permission definitions/tiers and Agent actual set | Grant/revoke package facts only after a confirmed change | No implicit drift repair or `SUPER_ADMIN_ONLY` assignment to an Agent |

## Validation and permissions

Agent lifecycle and direct-Permission management are separate intent-specific managed
`SUPER_ADMIN_ONLY` **management rights** held only by current Super Admin authority, checked for every UI,
HTTP/CLI and direct-bus path with current actor, target, scope and confirmation. Agents may hold only eligible
`ADMIN_SAFE` **direct** Permissions; a Super Admin caller cannot put a protected Permission on an Agent. Template
revision requests are not grants. Managed-bound assignment follows reviewed complete desired sets, not ad hoc
checkbox overrides. Cohort/version/key/sink/readiness checks from TICKET-00059/60 and Team membership/active
session checks from TICKET-00062 still apply. Safe Views exclude credentials, raw material, claim/lease, sink
receipts, operation requests, keys and maintenance internals; respond to stale/conflicting edits without false
success or leaked target detail. Self-profile Tools belong to TICKET-00061, not the admin page.

## Acceptance and evidence

- A Super Admin creates a named Agent from each reviewed managed template with exactly one Team and correct
  eligible direct authority; initially unavailable role actions are clearly labeled rather than faked.
- List/detail, name changes, provision/rotate/revoke statuses and competing changes remain current, authorized,
  accessible and secret-free; unknown/missing destination or partially delivered key never appears usable.
- Every direct management path requires current intent-specific authority; template drift does not silently
  grant Permissions. Package-owned transition tests, owned PostgreSQL/HTTP/client checks and `./bin/build` pass.
- Real authenticated work and full role completion belong to TICKET-00065, not a seed-only acceptance claim.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as requirement area 5 of EPIC-00014's seven-TICKET split. Requires the shared TICKET-00059/60
operation/lifecycle, [TICKET-00062](00062-TICKET.md)'s Team and
[TICKET-00061](00061-TICKET.md)'s live authorization before calling an Agent usable. Preserve
[TICKET-00034](00034-TICKET.md)'s original role ownership and [EPIC-00017](../epics/00017-EPIC.md)'s later
human-edited template publication/drift actions. This planning record creates no Agent or credential.

TICKET-00061's accepted [TASK-00247](../tasks/00247-TASK.md) consumes this TICKET's actual named Team-bound Agent
creation and reviewed-template integration. Map its precise primitive TASK dependencies during decomposition;
creation consumes TASK-00242–00245's reusable controls and qualified lifecycle where required, not the final
Explorer run. Do not make an Agent's creation depend on that Agent already performing the acceptance journey.
No TASK split for this TICKET has been approved by this coordination note.

TICKET-00034's approved TASK-00248/00249 now supply reviewed definitions and exact role-profile resolution.
Named-Agent creation consumes those primitives, not TASK-00250's downstream session integration; TASK-00250
instead consumes the actual creation capability. Map that direction during this TICKET's decomposition to avoid
requiring a running role session before its Agent can be created. Triage and Hotfix consumers retain explicit
holds for actual named identities/direct authority, not just a template label.
