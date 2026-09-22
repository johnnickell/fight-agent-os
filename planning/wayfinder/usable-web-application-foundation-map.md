# Wayfinder Map: Usable web application foundation

**Label:** `wayfinder:map`
**Status:** Active

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map only summarizes the linked resolutions and shows the next decision frontier.

## Destination

Find the route from the current Slim scaffold to the first usable Fight Agent OS web application foundation: project-local engineering skills first, then registration, login, session management, roles, permissions, and an early empty dashboard UI that future planning and observability features can build on.

**Done** = every linked decision ticket is closed, the remaining fog is resolved or excluded, and the map links to the resulting EPIC plus any TICKET/TASK handoff needed to implement the foundation.

## Notes

- Accepted direction: stay DDD and CQRS oriented, with business logic in Domain, orchestration in Application, and framework/provider concerns in Adapter.
- Accepted direction: preserve Action-Domain-Responder style for HTTP delivery unless a decision ticket rules out or refines that convention.
- Resolved dependency direction: use stable Fight Common `~1.2.0` and Fight Access Control `^0.2.0` lines with a committed lock; upstream iterations return through deliberate tagged releases.
- Accepted direction: create agent skills before asking agents to build authentication and UI features.
- Source inspiration: previous private Factory work at <https://github.com/johnnickell/fight-software-factory>, especially conventions for ADR, directory structure, namespaces, and testing style. Use as reference, not wholesale import.
- Source inspiration: Matt Pocock's public skills at <https://github.com/mattpocock/skills>, already noticed in `docs/legal/THIRD_PARTY_NOTICES.md`.
- Resolved design-source direction: create small project-owned design/exploration and independent design-review capabilities from reviewed open sources; use standards and rendered evidence as authorities, keep artifacts disposable, and do not install OpenDesign or hosted design agents wholesale.
- Resolved architecture direction: use Fight Access Control as the authoritative access-control Domain/Application model, PostgreSQL with contract-first Doctrine persistence, versioned JSend APIs, a responsibility-separated React client, Storybook for production component states, and behavior-focused tests.
- Resolved journey direction: deliver one private invite-only installation with guarded Super Admin bootstrap, explicit managed permissions, canonical email/password login, bounded long-lived sessions, self-service account security, operable invitations, authoritative client permission projection, and an accessible light/dark dashboard shell.

## Decisions so far

1. **[Recover source conventions](tickets/WF-001-recover-source-conventions.md) is closed.** Adopt a reviewed local subset of the Fight-specific standards at Factory commit `75a59ffd`: DDD/CQRS, Action–Domain–Responder, Fight naming/PHP, behavior tests, independent review, and delivery ownership. Keep Factory workflows and runtime machinery reference-only.
2. **[Shape the agent skill suite](tickets/WF-002-shape-agent-skill-suite.md) is closed.** Create project-local `work`, `review`, `land`, `design`, and `design-review` skills through EPIC-00002, with planning authority kept separate and human merge control preserved.
3. **[Qualify dependency baseline](tickets/WF-003-qualify-dependency-baseline.md) is closed.** Use the stable `~1.2.0`/`^0.2.0` lines, retire inherited support-receipt journeys, preserve a minimal application smoke boundary, and evolve `./bin/build` into the project-owned quality gate.
4. **[Define application foundation architecture](tickets/WF-004-define-application-foundation-architecture.md) is closed.** EPIC-00003 settles the package/application boundary, HTTP/CQRS conventions, PostgreSQL and Doctrine direction, delivery guarantees, React responsibilities, authorization projection, Storybook surface, and test seams.
5. **[Design authentication and authorization journeys](tickets/WF-005-design-authentication-and-authorization-journeys.md) is closed.** EPIC-00004 defines invite-only activation, guarded bootstrap, roles/permissions, login and refresh transport, account/session security, invitation operations, client authority projection, immediate security controls, and the accessible empty dashboard.
6. **[Research UI design skill sources](tickets/WF-006-research-ui-design-skill-sources.md) is closed.** Adapt OpenDesign's context-first exploration and handoff ideas without installing it; require sourced references, disposable prototypes, responsive/state screenshots, accessibility evidence, and independent visual critique.
7. **[Prepare the implementation handoff](tickets/WF-007-prepare-implementation-handoff.md) is open.** Convert closed decisions into the EPIC/TICKET/TASK planning path.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-001](tickets/WF-001-recover-source-conventions.md) | Recover source conventions | wayfinder:research | AFK | Closed | — |
| [WF-002](tickets/WF-002-shape-agent-skill-suite.md) | Shape the agent skill suite | wayfinder:grill | HITL | Closed | [WF-001](tickets/WF-001-recover-source-conventions.md), [WF-006](tickets/WF-006-research-ui-design-skill-sources.md) |
| [WF-003](tickets/WF-003-qualify-dependency-baseline.md) | Qualify dependency baseline | wayfinder:research | AFK | Closed | — |
| [WF-004](tickets/WF-004-define-application-foundation-architecture.md) | Define application foundation architecture | wayfinder:grill | HITL | Closed | [WF-001](tickets/WF-001-recover-source-conventions.md), [WF-002](tickets/WF-002-shape-agent-skill-suite.md), [WF-003](tickets/WF-003-qualify-dependency-baseline.md) |
| [WF-005](tickets/WF-005-design-authentication-and-authorization-journeys.md) | Design authentication and authorization journeys | wayfinder:grill | HITL | Closed | [WF-004](tickets/WF-004-define-application-foundation-architecture.md), [WF-006](tickets/WF-006-research-ui-design-skill-sources.md) |
| [WF-006](tickets/WF-006-research-ui-design-skill-sources.md) | Research UI design skill sources | wayfinder:research | AFK | Closed | — |
| [WF-007](tickets/WF-007-prepare-implementation-handoff.md) | Prepare the implementation handoff | wayfinder:task | HITL | Open | [WF-002](tickets/WF-002-shape-agent-skill-suite.md), [WF-005](tickets/WF-005-design-authentication-and-authorization-journeys.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
Recover source conventions ──→ Shape the agent skill suite ──→ Define application foundation architecture ──→ Prepare the implementation handoff
Qualify dependency baseline ──────────────────────────────────→ Define application foundation architecture
Define application foundation architecture ───────────────────→ Design authentication and authorization journeys ──→ Prepare the implementation handoff
Research UI design skill sources ─────────────────────────────→ Shape the agent skill suite
Research UI design skill sources ─────────────────────────────→ Design authentication and authorization journeys
```

## Frontier

[Prepare the implementation handoff](tickets/WF-007-prepare-implementation-handoff.md) is the current frontier. The skill-suite, architecture, design-source, and authentication/application-shell decisions are closed and ready to sequence into requirement planning.

## Not yet specified (fog)

- Exact requirement sequencing and dependency boundaries across EPIC-00002, EPIC-00003, and EPIC-00004.

## Out of scope

- Browser-backed database planning, observatory, and multi-agent runtime automation beyond the skills needed to build the first usable web foundation.
- Importing or preserving old Factory workflows such as `fight-build` as authoritative processes.
- Shipping production-grade visual design before the UI design skill and design system decisions are settled.
