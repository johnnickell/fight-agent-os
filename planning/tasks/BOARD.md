# TASK Board

Use the generated sections below to choose the next executable TASK. See the [foundation planning brief](../FOUNDATION.md) for starting context.

<!-- planning:board -->
## Active Work

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Ready Frontier

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 6 | [TASK-00006](00006-TASK.md) | Establish and prove independent design review | [TICKET-00006 — Establish independent design review](../tickets/00006-TICKET.md) | ready-for-agent | — | — |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 7 | [TASK-00007](00007-TASK.md) | Adopt and prove the stable dependency graph | [TICKET-00007 — Stabilize application dependencies and smoke baseline](../tickets/00007-TICKET.md) | ready-for-agent | [TASK-00006](00006-TASK.md) | — |
| 8 | [TASK-00008](00008-TASK.md) | Replace inherited receipts with the Agent OS smoke baseline | [TICKET-00007 — Stabilize application dependencies and smoke baseline](../tickets/00007-TICKET.md) | ready-for-agent | [TASK-00007](00007-TASK.md) | — |
| 9 | [TASK-00009](00009-TASK.md) | Accept the application ownership and orchestration ADR | [TICKET-00008 — Establish application ownership and orchestration boundaries](../tickets/00008-TICKET.md) | ready-for-agent | [TASK-00008](00008-TASK.md) | — |
| 10 | [TASK-00010](00010-TASK.md) | Prove and enforce application ownership boundaries | [TICKET-00008 — Establish application ownership and orchestration boundaries](../tickets/00008-TICKET.md) | ready-for-agent | [TASK-00009](00009-TASK.md) | — |
| 11 | [TASK-00011](00011-TASK.md) | Accept the PostgreSQL consistency and durable-effects ADR | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00010](00010-TASK.md) | — |
| 12 | [TASK-00012](00012-TASK.md) | Establish guarded PostgreSQL persistence through managed authority | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00011](00011-TASK.md) | — |
| 13 | [TASK-00013](00013-TASK.md) | Persist identities and refresh sessions atomically | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00012](00012-TASK.md) | — |
| 14 | [TASK-00014](00014-TASK.md) | Persist activation grants and replacement races | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00013](00013-TASK.md) | — |
| 15 | [TASK-00015](00015-TASK.md) | Persist password-reset grants and terminal succession | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00013](00013-TASK.md) | — |
| 16 | [TASK-00016](00016-TASK.md) | Establish atomic audit evidence and durable delivery intent | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00012](00012-TASK.md) | — |
| 17 | [TASK-00017](00017-TASK.md) | Accept the browser-authentication security-profile ADR | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00010](00010-TASK.md) | — |
| 18 | [TASK-00018](00018-TASK.md) | Establish the versioned JSend API interaction boundary | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00010](00010-TASK.md) | — |
| 19 | [TASK-00019](00019-TASK.md) | Reject invalid API input before dispatch | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00018](00018-TASK.md) | — |
| 20 | [TASK-00020](00020-TASK.md) | Centralize correlated and sanitized API failures | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00019](00019-TASK.md) | — |
| 21 | [TASK-00021](00021-TASK.md) | Publish and restrict the representative OpenAPI contract | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00017](00017-TASK.md), [TASK-00020](00020-TASK.md) | — |
| 22 | [TASK-00022](00022-TASK.md) | Establish secret-safe outbound effect capabilities | [TICKET-00011 — Establish recoverable external-effect delivery](../tickets/00011-TICKET.md) | ready-for-agent | [TASK-00016](00016-TASK.md) | — |
| 23 | [TASK-00023](00023-TASK.md) | Process registered durable intents after commit | [TICKET-00011 — Establish recoverable external-effect delivery](../tickets/00011-TICKET.md) | ready-for-agent | [TASK-00022](00022-TASK.md) | — |
| 24 | [TASK-00024](00024-TASK.md) | Recover retry and observe durable effect delivery | [TICKET-00011 — Establish recoverable external-effect delivery](../tickets/00011-TICKET.md) | ready-for-agent | [TASK-00023](00023-TASK.md) | — |
| 25 | [TASK-00025](00025-TASK.md) | Accept the client-authority and runtime-state ADR | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00017](00017-TASK.md) | — |
| 26 | [TASK-00026](00026-TASK.md) | Establish the React and TypeScript application shell | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00025](00025-TASK.md) | — |
| 27 | [TASK-00027](00027-TASK.md) | Establish the typed shared API client boundary | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00021](00021-TASK.md), [TASK-00026](00026-TASK.md) | — |
| 28 | [TASK-00028](00028-TASK.md) | Establish fail-closed client authority and guarded routing | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00027](00027-TASK.md) | — |
| 29 | [TASK-00029](00029-TASK.md) | Establish the production component catalog and state evidence | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00026](00026-TASK.md) | — |
| 30 | [TASK-00030](00030-TASK.md) | Complete PHP style static-analysis and dependency enforcement | [TICKET-00013 — Complete the owned-code quality gate](../tickets/00013-TICKET.md) | ready-for-agent | [TASK-00010](00010-TASK.md) | — |
| 31 | [TASK-00031](00031-TASK.md) | Enforce backend behavior coverage and PostgreSQL verification | [TICKET-00013 — Complete the owned-code quality gate](../tickets/00013-TICKET.md) | ready-for-agent | [TASK-00014](00014-TASK.md), [TASK-00015](00015-TASK.md), [TASK-00021](00021-TASK.md), [TASK-00024](00024-TASK.md), [TASK-00030](00030-TASK.md) | — |
| 32 | [TASK-00032](00032-TASK.md) | Enforce deterministic frontend quality checks | [TICKET-00013 — Complete the owned-code quality gate](../tickets/00013-TICKET.md) | ready-for-agent | [TASK-00028](00028-TASK.md), [TASK-00029](00029-TASK.md) | — |
| 33 | [TASK-00033](00033-TASK.md) | Make bin build the complete read-only application gate | [TICKET-00013 — Complete the owned-code quality gate](../tickets/00013-TICKET.md) | ready-for-agent | [TASK-00031](00031-TASK.md), [TASK-00032](00032-TASK.md) | — |
| 34 | [TASK-00034](00034-TASK.md) | Explore the authentication and dashboard design language | [TICKET-00014 — Accept the authentication and dashboard design language](../tickets/00014-TICKET.md) | ready-for-agent | [TASK-00033](00033-TASK.md) | — |
| 35 | [TASK-00035](00035-TASK.md) | Independently accept the authentication and dashboard production handoff | [TICKET-00014 — Accept the authentication and dashboard design language](../tickets/00014-TICKET.md) | ready-for-agent | [TASK-00034](00034-TASK.md) | — |
| 36 | [TASK-00036](00036-TASK.md) | Reconcile the exact managed authority policy | [TICKET-00015 — Establish managed authority and guarded bootstrap](../tickets/00015-TICKET.md) | ready-for-agent | [TASK-00033](00033-TASK.md) | — |
| 37 | [TASK-00037](00037-TASK.md) | Deliver trusted ordinary-user console invitations | [TICKET-00015 — Establish managed authority and guarded bootstrap](../tickets/00015-TICKET.md) | ready-for-agent | [TASK-00036](00036-TASK.md) | — |
| 38 | [TASK-00038](00038-TASK.md) | Guard the one-time Super Admin bootstrap | [TICKET-00015 — Establish managed authority and guarded bootstrap](../tickets/00015-TICKET.md) | ready-for-agent | [TASK-00037](00037-TASK.md) | — |
| 39 | [TASK-00039](00039-TASK.md) | Enforce shared password acceptance and Argon2id hashing | [TICKET-00016 — Deliver the invitation and activation lifecycle](../tickets/00016-TICKET.md) | ready-for-agent | [TASK-00033](00033-TASK.md) | — |
| 40 | [TASK-00040](00040-TASK.md) | Deliver recoverable secret-safe invitation email | [TICKET-00016 — Deliver the invitation and activation lifecycle](../tickets/00016-TICKET.md) | ready-for-agent | [TASK-00038](00038-TASK.md) | — |
| 41 | [TASK-00041](00041-TASK.md) | Expose authorized invitation issuance and safe status | [TICKET-00016 — Deliver the invitation and activation lifecycle](../tickets/00016-TICKET.md) | ready-for-agent | [TASK-00040](00040-TASK.md) | — |
| 42 | [TASK-00042](00042-TASK.md) | Expose permission-controlled invitation recovery | [TICKET-00016 — Deliver the invitation and activation lifecycle](../tickets/00016-TICKET.md) | ready-for-agent | [TASK-00041](00041-TASK.md) | — |
| 43 | [TASK-00043](00043-TASK.md) | Deliver the public invitation activation journey | [TICKET-00016 — Deliver the invitation and activation lifecycle](../tickets/00016-TICKET.md) | ready-for-agent | [TASK-00035](00035-TASK.md), [TASK-00039](00039-TASK.md), [TASK-00042](00042-TASK.md) | — |
| 44 | [TASK-00044](00044-TASK.md) | Enforce complete access-token authentication | [TICKET-00017 — Deliver secure browser authentication and session continuity](../tickets/00017-TICKET.md) | ready-for-agent | [TASK-00043](00043-TASK.md) | — |
| 45 | [TASK-00045](00045-TASK.md) | Enforce browser authentication request controls | [TICKET-00017 — Deliver secure browser authentication and session continuity](../tickets/00017-TICKET.md) | ready-for-agent | [TASK-00043](00043-TASK.md) | — |
| 46 | [TASK-00046](00046-TASK.md) | Deliver the canonical email and password login journey | [TICKET-00017 — Deliver secure browser authentication and session continuity](../tickets/00017-TICKET.md) | ready-for-agent | [TASK-00044](00044-TASK.md), [TASK-00045](00045-TASK.md) | — |
| 47 | [TASK-00047](00047-TASK.md) | Deliver rotating refresh and current-session logout | [TICKET-00017 — Deliver secure browser authentication and session continuity](../tickets/00017-TICKET.md) | ready-for-agent | [TASK-00046](00046-TASK.md) | — |
| 48 | [TASK-00048](00048-TASK.md) | Coordinate bounded multi-tab session continuity | [TICKET-00017 — Deliver secure browser authentication and session continuity](../tickets/00017-TICKET.md) | ready-for-agent | [TASK-00047](00047-TASK.md) | — |
| 49 | [TASK-00049](00049-TASK.md) | Deliver the authoritative current-principal projection | [TICKET-00018 — Deliver the authoritative application shell and dashboard](../tickets/00018-TICKET.md) | ready-for-agent | [TASK-00048](00048-TASK.md) | — |
| 50 | [TASK-00050](00050-TASK.md) | Deliver the accessible production theme preference | [TICKET-00018 — Deliver the authoritative application shell and dashboard](../tickets/00018-TICKET.md) | ready-for-agent | [TASK-00035](00035-TASK.md) | — |
| 51 | [TASK-00051](00051-TASK.md) | Deliver the responsive authoritative application frame | [TICKET-00018 — Deliver the authoritative application shell and dashboard](../tickets/00018-TICKET.md) | ready-for-agent | [TASK-00049](00049-TASK.md), [TASK-00050](00050-TASK.md) | — |
| 52 | [TASK-00052](00052-TASK.md) | Deliver the honest permission-aware dashboard | [TICKET-00018 — Deliver the authoritative application shell and dashboard](../tickets/00018-TICKET.md) | ready-for-agent | [TASK-00051](00051-TASK.md) | — |
| 53 | [TASK-00053](00053-TASK.md) | Deliver recoverable secret-safe password-reset email | [TICKET-00019 — Deliver password recovery and authenticated password change](../tickets/00019-TICKET.md) | ready-for-agent | [TASK-00040](00040-TASK.md) | — |
| 54 | [TASK-00054](00054-TASK.md) | Deliver the generic password-reset request journey | [TICKET-00019 — Deliver password recovery and authenticated password change](../tickets/00019-TICKET.md) | ready-for-agent | [TASK-00051](00051-TASK.md), [TASK-00053](00053-TASK.md) | — |
| 55 | [TASK-00055](00055-TASK.md) | Deliver one-time password-reset completion | [TICKET-00019 — Deliver password recovery and authenticated password change](../tickets/00019-TICKET.md) | ready-for-agent | [TASK-00054](00054-TASK.md) | — |
| 56 | [TASK-00056](00056-TASK.md) | Deliver authenticated password change and terminal logout | [TICKET-00019 — Deliver password recovery and authenticated password change](../tickets/00019-TICKET.md) | ready-for-agent | [TASK-00051](00051-TASK.md) | — |
| 57 | [TASK-00057](00057-TASK.md) | Deliver authoritative owned-session visibility | [TICKET-00020 — Deliver self-service active-session management](../tickets/00020-TICKET.md) | ready-for-agent | [TASK-00051](00051-TASK.md) | — |
| 58 | [TASK-00058](00058-TASK.md) | Deliver confirmed revocation of another owned session | [TICKET-00020 — Deliver self-service active-session management](../tickets/00020-TICKET.md) | ready-for-agent | [TASK-00057](00057-TASK.md) | — |
| 59 | [TASK-00059](00059-TASK.md) | Deliver the permission-controlled user directory | [TICKET-00021 — Deliver Super Admin user and invitation operations](../tickets/00021-TICKET.md) | ready-for-agent | [TASK-00051](00051-TASK.md) | — |
| 60 | [TASK-00060](00060-TASK.md) | Deliver pending-invitation status and recovery | [TICKET-00021 — Deliver Super Admin user and invitation operations](../tickets/00021-TICKET.md) | ready-for-agent | [TASK-00059](00059-TASK.md) | — |
| 61 | [TASK-00061](00061-TASK.md) | Deliver the ordinary web invitation journey | [TICKET-00021 — Deliver Super Admin user and invitation operations](../tickets/00021-TICKET.md) | ready-for-agent | [TASK-00060](00060-TASK.md) | — |
| 62 | [TASK-00062](00062-TASK.md) | Deliver the confirmed elevated invitation journey | [TICKET-00021 — Deliver Super Admin user and invitation operations](../tickets/00021-TICKET.md) | ready-for-agent | [TASK-00061](00061-TASK.md) | — |
| 63 | [TASK-00063](00063-TASK.md) | Establish the production-like HTTPS security envelope | [TICKET-00022 — Verify the integrated security and accessibility baseline](../tickets/00022-TICKET.md) | ready-for-agent | [TASK-00052](00052-TASK.md), [TASK-00055](00055-TASK.md), [TASK-00056](00056-TASK.md), [TASK-00058](00058-TASK.md), [TASK-00062](00062-TASK.md) | — |
| 64 | [TASK-00064](00064-TASK.md) | Prove integrated authorization throttling and secret safety | [TICKET-00022 — Verify the integrated security and accessibility baseline](../tickets/00022-TICKET.md) | ready-for-agent | [TASK-00063](00063-TASK.md) | — |
| 65 | [TASK-00065](00065-TASK.md) | Prove the integrated accessibility and responsive baseline | [TICKET-00022 — Verify the integrated security and accessibility baseline](../tickets/00022-TICKET.md) | ready-for-agent | [TASK-00063](00063-TASK.md) | — |
| 66 | [TASK-00066](00066-TASK.md) | Prove critical browser continuity and close the foundation gate | [TICKET-00022 — Verify the integrated security and accessibility baseline](../tickets/00022-TICKET.md) | ready-for-agent | [TASK-00064](00064-TASK.md), [TASK-00065](00065-TASK.md) | — |

## Needs Info

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Human Action

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Needs Triage

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Recently Closed

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00001](00001-TASK.md) | Add the initial project-local planning skills | [TICKET-00001 — Seed the planning skill foundation](../tickets/00001-TICKET.md) | done | — | — |
| 2 | [TASK-00002](00002-TASK.md) | Establish and prove safe TASK execution | [TICKET-00002 — Establish safe TASK execution](../tickets/00002-TICKET.md) | done | — | [PR #2](https://github.com/johnnickell/fight-agent-os/pull/2) |
| 3 | [TASK-00003](00003-TASK.md) | Establish and prove independent implementation review | [TICKET-00003 — Establish independent implementation review](../tickets/00003-TICKET.md) | done | — | [PR #2](https://github.com/johnnickell/fight-agent-os/pull/2) |
| 4 | [TASK-00004](00004-TASK.md) | Establish and prove controlled landing | [TICKET-00004 — Establish controlled landing and human handoff](../tickets/00004-TICKET.md) | done | — | [PR #4](https://github.com/johnnickell/fight-agent-os/pull/4) |
| 5 | [TASK-00005](00005-TASK.md) | Establish and prove disposable product-design exploration | [TICKET-00005 — Establish disposable product-design exploration](../tickets/00005-TICKET.md) | done | — | — |
| 67 | [TASK-00067](00067-TASK.md) | Add the read-only next-work router skill | — (standalone chore) | done | — | [PR #5](https://github.com/johnnickell/fight-agent-os/pull/5) |
| 68 | [TASK-00068](00068-TASK.md) | Publish and clean isolated work during landing | — (standalone bug) | done | — | [PR #6](https://github.com/johnnickell/fight-agent-os/pull/6) |
<!-- /planning:board -->
