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
- Accepted direction: pin or qualify against Fight Common `1.2` and Fight Access Control `0.*`, expecting both to iterate as this project exposes integration needs.
- Accepted direction: create agent skills before asking agents to build authentication and UI features.
- Source inspiration: previous private Factory work at <https://github.com/johnnickell/fight-software-factory>, especially conventions for ADR, directory structure, namespaces, and testing style. Use as reference, not wholesale import.
- Source inspiration: Matt Pocock's public skills at <https://github.com/mattpocock/skills>, already noticed in `docs/legal/THIRD_PARTY_NOTICES.md`.
- Design ambition: plan for beautiful application design, not just mechanically correct engineering. Research OpenDesign and other open design-skill sources before writing durable UI/design skills.

## Decisions so far

1. **[Recover source conventions](tickets/WF-001-recover-source-conventions.md) is closed.** Adopt a reviewed local subset of the Fight-specific standards at Factory commit `75a59ffd`: DDD/CQRS, Action–Domain–Responder, Fight naming/PHP, behavior tests, independent review, and delivery ownership. Keep Factory workflows and runtime machinery reference-only.
2. **[Shape the agent skill suite](tickets/WF-002-shape-agent-skill-suite.md) is open.** Decide the first implementation, review, land, and UI/design skills.
3. **[Qualify dependency baseline](tickets/WF-003-qualify-dependency-baseline.md) is open.** Decide the dependency pins and cleanup scope before feature planning.
4. **[Define application foundation architecture](tickets/WF-004-define-application-foundation-architecture.md) is open.** Decide the HTTP, Domain/Application/Adapter, CQRS, persistence, and test conventions for the web app.
5. **[Design authentication and authorization journeys](tickets/WF-005-design-authentication-and-authorization-journeys.md) is open.** Decide registration, login, sessions, roles, permissions, and dashboard boundaries.
6. **[Research UI design skill sources](tickets/WF-006-research-ui-design-skill-sources.md) is open.** Find open design sources, including OpenDesign feasibility, to inform UI skill creation.
7. **[Prepare the implementation handoff](tickets/WF-007-prepare-implementation-handoff.md) is open.** Convert closed decisions into the EPIC/TICKET/TASK planning path.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-001](tickets/WF-001-recover-source-conventions.md) | Recover source conventions | wayfinder:research | AFK | Closed | — |
| [WF-002](tickets/WF-002-shape-agent-skill-suite.md) | Shape the agent skill suite | wayfinder:grill | HITL | Open | [WF-001](tickets/WF-001-recover-source-conventions.md), [WF-006](tickets/WF-006-research-ui-design-skill-sources.md) |
| [WF-003](tickets/WF-003-qualify-dependency-baseline.md) | Qualify dependency baseline | wayfinder:research | AFK | Open | — |
| [WF-004](tickets/WF-004-define-application-foundation-architecture.md) | Define application foundation architecture | wayfinder:grill | HITL | Open | [WF-001](tickets/WF-001-recover-source-conventions.md), [WF-002](tickets/WF-002-shape-agent-skill-suite.md), [WF-003](tickets/WF-003-qualify-dependency-baseline.md) |
| [WF-005](tickets/WF-005-design-authentication-and-authorization-journeys.md) | Design authentication and authorization journeys | wayfinder:grill | HITL | Open | [WF-004](tickets/WF-004-define-application-foundation-architecture.md), [WF-006](tickets/WF-006-research-ui-design-skill-sources.md) |
| [WF-006](tickets/WF-006-research-ui-design-skill-sources.md) | Research UI design skill sources | wayfinder:research | AFK | Open | — |
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

[Qualify dependency baseline](tickets/WF-003-qualify-dependency-baseline.md) and [Research UI design skill sources](tickets/WF-006-research-ui-design-skill-sources.md) are the current frontier. They can proceed in parallel because they gather facts before human synthesis.

## Not yet specified (fog)

- Exact skill names and invocation semantics for implementation, review, land, and UI design.
- Whether worktree orchestration belongs in the first implementation skill or a later coordination skill.
- Which tests from the inherited scaffold should be removed immediately versus replaced by first application tests.
- The concrete registration, login, session, role, permission, and dashboard use cases.
- Whether OpenDesign can be installed and used locally in a repeatable way for this project.
- Whether the first implementation handoff should be one EPIC or multiple EPICs sequenced by dependencies.

## Out of scope

- Browser-backed database planning, observatory, and multi-agent runtime automation beyond the skills needed to build the first usable web foundation.
- Importing or preserving old Factory workflows such as `fight-build` as authoritative processes.
- Shipping production-grade visual design before the UI design skill and design system decisions are settled.
