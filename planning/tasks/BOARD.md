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
| 14 | [TASK-00014](00014-TASK.md) | Persist activation grants and replacement races | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | — | — |
| 15 | [TASK-00015](00015-TASK.md) | Persist password-reset grants and terminal succession | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | — | — |
| 17 | [TASK-00017](00017-TASK.md) | Accept the browser-authentication security-profile ADR | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | — | — |
| 18 | [TASK-00018](00018-TASK.md) | Establish the versioned JSend API interaction boundary | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | — | — |
| 30 | [TASK-00030](00030-TASK.md) | Complete PHP style and static analysis | [TICKET-00013 — Complete the owned-code quality gate](../tickets/00013-TICKET.md) | ready-for-agent | — | — |
| 74 | [TASK-00077](00077-TASK.md) | Accept the repository identity and link-consistency ADR | [TICKET-00024 — Register repositories and designated checkouts](../tickets/00024-TICKET.md) | ready-for-agent | — | — |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 16 | [TASK-00016](00016-TASK.md) | Establish atomic audit evidence and package delivery persistence | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | ready-for-agent | [TASK-00014](00014-TASK.md), [TASK-00015](00015-TASK.md) | — |
| 19 | [TASK-00019](00019-TASK.md) | Reject invalid API input before dispatch | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00018](00018-TASK.md) | — |
| 20 | [TASK-00020](00020-TASK.md) | Centralize correlated and sanitized API failures | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00019](00019-TASK.md) | — |
| 21 | [TASK-00021](00021-TASK.md) | Publish and restrict the representative OpenAPI contract | [TICKET-00010 — Establish safe versioned HTTP API delivery](../tickets/00010-TICKET.md) | ready-for-agent | [TASK-00017](00017-TASK.md), [TASK-00020](00020-TASK.md) | — |
| 22 | [TASK-00022](00022-TASK.md) | Establish secret-safe credential provider adapters | [TICKET-00011 — Establish recoverable external-effect delivery](../tickets/00011-TICKET.md) | ready-for-agent | [TASK-00016](00016-TASK.md) | — |
| 23 | [TASK-00023](00023-TASK.md) | Compose direct package credential delivery | [TICKET-00011 — Establish recoverable external-effect delivery](../tickets/00011-TICKET.md) | ready-for-agent | [TASK-00022](00022-TASK.md) | — |
| 24 | [TASK-00024](00024-TASK.md) | Recover and observe package credential delivery | [TICKET-00011 — Establish recoverable external-effect delivery](../tickets/00011-TICKET.md) | ready-for-agent | [TASK-00023](00023-TASK.md) | — |
| 25 | [TASK-00025](00025-TASK.md) | Accept the client-authority and runtime-state ADR | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00017](00017-TASK.md) | — |
| 26 | [TASK-00026](00026-TASK.md) | Establish the React and TypeScript application shell | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00025](00025-TASK.md) | — |
| 27 | [TASK-00027](00027-TASK.md) | Establish the typed shared API client boundary | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00021](00021-TASK.md), [TASK-00026](00026-TASK.md) | — |
| 28 | [TASK-00028](00028-TASK.md) | Establish fail-closed client authority and guarded routing | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00027](00027-TASK.md) | — |
| 29 | [TASK-00029](00029-TASK.md) | Establish the production component catalog and state evidence | [TICKET-00012 — Establish the React and component-catalog foundation](../tickets/00012-TICKET.md) | ready-for-agent | [TASK-00026](00026-TASK.md) | — |
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
| 69 | [TASK-00072](00072-TASK.md) | Establish the installation-owned Planning runtime | [TICKET-00023 — Operate the local Planning installation](../tickets/00023-TICKET.md) | ready-for-agent | [TASK-00066](00066-TASK.md) | — |
| 70 | [TASK-00073](00073-TASK.md) | Establish contained approved-root access | [TICKET-00023 — Operate the local Planning installation](../tickets/00023-TICKET.md) | ready-for-agent | [TASK-00072](00072-TASK.md) | — |
| 71 | [TASK-00074](00074-TASK.md) | Expose authorized installation capabilities and diagnostics | [TICKET-00023 — Operate the local Planning installation](../tickets/00023-TICKET.md) | ready-for-agent | [TASK-00073](00073-TASK.md) | — |
| 72 | [TASK-00075](00075-TASK.md) | Deliver the guided Planning installation journey | [TICKET-00023 — Operate the local Planning installation](../tickets/00023-TICKET.md) | ready-for-agent | [TASK-00074](00074-TASK.md) | — |
| 75 | [TASK-00078](00078-TASK.md) | Register and inspect stable repositories | [TICKET-00024 — Register repositories and designated checkouts](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00077](00077-TASK.md) | — |
| 76 | [TASK-00079](00079-TASK.md) | Confirm and designate checkout and worktree links | [TICKET-00024 — Register repositories and designated checkouts](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00073](00073-TASK.md), [TASK-00078](00078-TASK.md) | — |
| 77 | [TASK-00080](00080-TASK.md) | Complete repository reconciliation and lifecycle operations | [TICKET-00024 — Register repositories and designated checkouts](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00079](00079-TASK.md) | — |
| 78 | [TASK-00081](00081-TASK.md) | Version repository configuration and rollback | [TICKET-00025 — Resolve versioned repository context](../tickets/00025-TICKET.md) | ready-for-agent | [TASK-00080](00080-TASK.md) | — |
| 79 | [TASK-00082](00082-TASK.md) | Resolve deterministic repository context snapshots | [TICKET-00025 — Resolve versioned repository context](../tickets/00025-TICKET.md) | ready-for-agent | [TASK-00081](00081-TASK.md) | — |
| 80 | [TASK-00083](00083-TASK.md) | Record narrowing context choices | [TICKET-00025 — Resolve versioned repository context](../tickets/00025-TICKET.md) | ready-for-agent | [TASK-00082](00082-TASK.md) | — |
| 81 | [TASK-00084](00084-TASK.md) | Enforce snapshot drift and unavailable-authority boundaries | [TICKET-00025 — Resolve versioned repository context](../tickets/00025-TICKET.md) | ready-for-agent | [TASK-00083](00083-TASK.md) | — |
| 82 | [TASK-00085](00085-TASK.md) | Establish event-sourced Wayfinder planning authority | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00080](00080-TASK.md) | — |
| 83 | [TASK-00086](00086-TASK.md) | Complete Wayfinder decisions and handoff readiness | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00085](00085-TASK.md) | — |
| 84 | [TASK-00087](00087-TASK.md) | Version research notes and prototype evidence | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00085](00085-TASK.md) | — |
| 85 | [TASK-00088](00088-TASK.md) | Create and revise EPIC and TICKET artifacts | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00085](00085-TASK.md) | — |
| 86 | [TASK-00089](00089-TASK.md) | Create and revise implementation TASK artifacts | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00088](00088-TASK.md) | — |
| 87 | [TASK-00090](00090-TASK.md) | Enforce Planning hierarchy and dependency revisions | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00086](00086-TASK.md), [TASK-00089](00089-TASK.md) | — |
| 88 | [TASK-00091](00091-TASK.md) | Record stable Planning criteria and evidence | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00087](00087-TASK.md), [TASK-00089](00089-TASK.md) | — |
| 89 | [TASK-00092](00092-TASK.md) | Complete Planning lifecycle and archive operations | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00090](00090-TASK.md), [TASK-00091](00091-TASK.md) | — |
| 90 | [TASK-00093](00093-TASK.md) | Rebuild, redact and render Planning authority | [TICKET-00026 — Establish authoritative Planning artifacts and lifecycles](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00092](00092-TASK.md) | — |
| 91 | [TASK-00094](00094-TASK.md) | Version authoritative Roadmap ordering | [TICKET-00027 — Deliver the canonical Planning queue and Roadmap](../tickets/00027-TICKET.md) | ready-for-agent | [TASK-00093](00093-TASK.md) | — |
| 92 | [TASK-00095](00095-TASK.md) | Version TASK sequencing and queue overrides | [TICKET-00027 — Deliver the canonical Planning queue and Roadmap](../tickets/00027-TICKET.md) | ready-for-agent | [TASK-00094](00094-TASK.md) | — |
| 93 | [TASK-00096](00096-TASK.md) | Resolve canonical executable TASK eligibility | [TICKET-00027 — Deliver the canonical Planning queue and Roadmap](../tickets/00027-TICKET.md) | ready-for-agent | [TASK-00084](00084-TASK.md), [TASK-00095](00095-TASK.md) | — |
| 94 | [TASK-00097](00097-TASK.md) | Expose canonical Roadmap and work-queue views | [TICKET-00027 — Deliver the canonical Planning queue and Roadmap](../tickets/00027-TICKET.md) | ready-for-agent | [TASK-00096](00096-TASK.md) | — |
| 95 | [TASK-00098](00098-TASK.md) | Inventory immutable Markdown Planning source | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-agent | [TASK-00097](00097-TASK.md) | — |
| 96 | [TASK-00099](00099-TASK.md) | Map complete Markdown baseline events | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-agent | [TASK-00098](00098-TASK.md) | — |
| 97 | [TASK-00100](00100-TASK.md) | Rehearse and compare complete PostgreSQL import | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-agent | [TASK-00099](00099-TASK.md) | — |
| 98 | [TASK-00101](00101-TASK.md) | Approve an exact Planning migration rehearsal | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-agent | [TASK-00100](00100-TASK.md) | — |
| 99 | [TASK-00102](00102-TASK.md) | Cut over Planning authority atomically | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-agent | [TASK-00101](00101-TASK.md) | — |
| 100 | [TASK-00103](00103-TASK.md) | Enforce database-only Planning consumers | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-agent | [TASK-00102](00102-TASK.md) | — |
| 102 | [TASK-00105](00105-TASK.md) | Explore the registered Planning workspace design | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00103](00103-TASK.md) | — |
| 103 | [TASK-00106](00106-TASK.md) | Independently accept the Planning workspace handoff | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00105](00105-TASK.md) | — |
| 104 | [TASK-00107](00107-TASK.md) | Deliver repository registration, selection, and context workspace | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00106](00106-TASK.md) | — |
| 105 | [TASK-00108](00108-TASK.md) | Browse complete Planning hierarchy and history | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00107](00107-TASK.md) | — |
| 106 | [TASK-00109](00109-TASK.md) | Edit Planning through semantic forms | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00108](00108-TASK.md) | — |
| 107 | [TASK-00110](00110-TASK.md) | Operate guarded lifecycle and relationship work | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00109](00109-TASK.md) | — |
| 108 | [TASK-00111](00111-TASK.md) | Operate the canonical Roadmap and work queue | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00108](00108-TASK.md) | — |
| 109 | [TASK-00112](00112-TASK.md) | Operate migration and Planning authority | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00107](00107-TASK.md) | — |
| 110 | [TASK-00113](00113-TASK.md) | Complete integrated Planning Dashboard states and accessibility | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-agent | [TASK-00110](00110-TASK.md), [TASK-00111](00111-TASK.md), [TASK-00112](00112-TASK.md) | — |

## Needs Info

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Human Action

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 73 | [TASK-00076](00076-TASK.md) | Qualify supported local Planning operation | [TICKET-00023 — Operate the local Planning installation](../tickets/00023-TICKET.md) | ready-for-human | [TASK-00075](00075-TASK.md) | — |
| 101 | [TASK-00104](00104-TASK.md) | Migrate Fight Agent OS Planning authority | [TICKET-00028 — Migrate and cut over Markdown Planning authority](../tickets/00028-TICKET.md) | ready-for-human | [TASK-00103](00103-TASK.md) | — |
| 111 | [TASK-00114](00114-TASK.md) | Qualify the migrated Planning Dashboard | [TICKET-00029 — Deliver the registered Planning Dashboard](../tickets/00029-TICKET.md) | ready-for-human | [TASK-00104](00104-TASK.md), [TASK-00113](00113-TASK.md) | — |

## Needs Triage

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Recently Closed

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00001](00001-TASK.md) | Add the initial project-local planning skills | [TICKET-00001 — Seed the planning skill foundation](../tickets/00001-TICKET.md) | done | — | — |
| 1 | [TASK-00071](00071-TASK.md) | Prepare develop documentation for public visibility | — (standalone chore) | done | — | [PR #18](https://github.com/johnnickell/fight-agent-os/pull/18) |
| 2 | [TASK-00002](00002-TASK.md) | Establish and prove safe TASK execution | [TICKET-00002 — Establish safe TASK execution](../tickets/00002-TICKET.md) | done | — | [PR #2](https://github.com/johnnickell/fight-agent-os/pull/2) |
| 3 | [TASK-00003](00003-TASK.md) | Establish and prove independent implementation review | [TICKET-00003 — Establish independent implementation review](../tickets/00003-TICKET.md) | done | — | [PR #2](https://github.com/johnnickell/fight-agent-os/pull/2) |
| 4 | [TASK-00004](00004-TASK.md) | Establish and prove controlled landing | [TICKET-00004 — Establish controlled landing and human handoff](../tickets/00004-TICKET.md) | done | — | [PR #4](https://github.com/johnnickell/fight-agent-os/pull/4) |
| 4 | [TASK-00069](00069-TASK.md) | Persist and discover every implementation review handoff | — (standalone bug) | done | — | [PR #8](https://github.com/johnnickell/fight-agent-os/pull/8) |
| 5 | [TASK-00005](00005-TASK.md) | Establish and prove disposable product-design exploration | [TICKET-00005 — Establish disposable product-design exploration](../tickets/00005-TICKET.md) | done | — | [PR #7](https://github.com/johnnickell/fight-agent-os/pull/7) |
| 6 | [TASK-00006](00006-TASK.md) | Establish and prove independent design review | [TICKET-00006 — Establish independent design review](../tickets/00006-TICKET.md) | done | — | [PR #10](https://github.com/johnnickell/fight-agent-os/pull/10) |
| 7 | [TASK-00007](00007-TASK.md) | Adopt and prove the stable dependency graph | [TICKET-00007 — Stabilize application dependencies and smoke baseline](../tickets/00007-TICKET.md) | done | — | — |
| 8 | [TASK-00008](00008-TASK.md) | Replace inherited receipts with the Agent OS smoke baseline | [TICKET-00007 — Stabilize application dependencies and smoke baseline](../tickets/00007-TICKET.md) | done | — | [PR #12](https://github.com/johnnickell/fight-agent-os/pull/12) |
| 9 | [TASK-00009](00009-TASK.md) | Accept the application ownership and orchestration ADR | [TICKET-00008 — Establish application ownership and orchestration boundaries](../tickets/00008-TICKET.md) | done | — | [PR #13](https://github.com/johnnickell/fight-agent-os/pull/13) |
| 10 | [TASK-00010](00010-TASK.md) | Prove and enforce application ownership boundaries | [TICKET-00008 — Establish application ownership and orchestration boundaries](../tickets/00008-TICKET.md) | done | — | [PR #14](https://github.com/johnnickell/fight-agent-os/pull/14) |
| 11 | [TASK-00011](00011-TASK.md) | Accept the PostgreSQL consistency and durable-effects ADR | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | done | — | [PR #15](https://github.com/johnnickell/fight-agent-os/pull/15) |
| 12 | [TASK-00012](00012-TASK.md) | Establish guarded PostgreSQL persistence through managed authority | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | done | — | [PR #17](https://github.com/johnnickell/fight-agent-os/pull/17) |
| 12 | [TASK-00070](00070-TASK.md) | Remove build and test self-verification bloat | — (standalone chore) | done | — | [PR #16](https://github.com/johnnickell/fight-agent-os/pull/16) |
| 13 | [TASK-00013](00013-TASK.md) | Persist identities and refresh sessions atomically | [TICKET-00009 — Establish authoritative PostgreSQL persistence](../tickets/00009-TICKET.md) | done | — | [PR #20](https://github.com/johnnickell/fight-agent-os/pull/20) |
| 67 | [TASK-00067](00067-TASK.md) | Add the read-only next-work router skill | — (standalone chore) | done | — | [PR #5](https://github.com/johnnickell/fight-agent-os/pull/5) |
| 68 | [TASK-00068](00068-TASK.md) | Publish and clean isolated work during landing | — (standalone bug) | done | — | [PR #6](https://github.com/johnnickell/fight-agent-os/pull/6) |
| — | [TASK-00115](00115-TASK.md) | Remove redundant root index and placeholder and align path indentation | — (standalone chore) | done | — | [PR #19](https://github.com/johnnickell/fight-agent-os/pull/19) |
<!-- /planning:board -->
