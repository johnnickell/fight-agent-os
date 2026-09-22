# Design authentication and authorization journeys

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** [WF-004](WF-004-define-application-foundation-architecture.md), [WF-006](WF-006-research-ui-design-skill-sources.md)

## Question

What should the first usable registration, login, session management, roles, permissions, and empty dashboard experience include?

## Must decide

- Whether self-registration is open, invite-only, or admin-created for the first foundation.
- Login method, session lifetime, logout behavior, and remember-me boundaries.
- Initial roles and permissions, including the bootstrap administrator path.
- Dashboard purpose when no observability or planning data exists yet.
- UI layout, accessibility, error states, empty states, and design-system expectations.
- Security exclusions that are acceptable for the first local foundation and what must be present immediately.

## Resolution boundary

This ticket may settle product and UX requirements for the first foundation EPIC/TICKETs. It must not implement endpoints, forms, or database tables.

## Resolution

[EPIC-00004](../../epics/00004-EPIC.md) defines the invite-only authenticated application shell for one private installation/workspace. It selects guarded Super Admin bootstrap and ordinary invitation/activation, `ROLE_USER` and `ROLE_SUPER_ADMIN`, five verb-first managed permissions, canonical email/password login, memory-only access JWTs, secure rotating refresh cookies, long-lived bounded remember-me, multi-tab refresh coordination, self-service session management, password recovery/change, and authoritative `/api/v1/me` permission projection.

The first UI includes public authentication/recovery routes, authenticated dashboard/account/security routes, and Super Admin user/invitation operations. It requires HTTPS-preferred local development, immediate production security controls, an offline common-password denylist, WCAG 2.2 AA behavior, light/dark/system themes, complete interaction states, and an honest dashboard without invented data. Multi-tenancy, full lifecycle/role administration, MFA/passkeys, alternative login methods, cross-user session administration, and planning/observability data remain excluded.
