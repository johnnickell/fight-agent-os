# Define application foundation architecture

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Usable web application foundation](../usable-web-application-foundation-map.md)
**Depends on:** [WF-001](WF-001-recover-source-conventions.md), [WF-002](WF-002-shape-agent-skill-suite.md), [WF-003](WF-003-qualify-dependency-baseline.md)

## Question

What architecture and delivery conventions should govern the first usable web application foundation?

## Must decide

- HTTP Action and Responder shape for single-use-case endpoints.
- Domain, Application, and Adapter responsibilities for registration, login, session, role, and permission behavior.
- CQRS command/query naming, handler placement, and validation boundaries.
- Persistence and migration direction for users, roles, permissions, sessions, and dashboard state.
- Test seams and fixture style for meaningful behavior coverage.
- Which inherited application tests should be removed once replacement behavior tests exist.

## Resolution boundary

This ticket may settle architecture conventions and identify ADRs or planning records to write. It must not decompose implementation TASKs before the authentication and UI journeys are decided.

## Resolution

[EPIC-00003](../../epics/00003-EPIC.md) establishes the web application architecture foundation. Fight Access Control owns identity and authorization Domain/Application behavior; Agent OS owns its capability-first adapters, composition, and necessary cross-boundary orchestration. The HTTP boundary uses single-interaction Actions, happy-path Responders, pre-Action attribute validation, centralized safe errors, versioned JSend APIs, explicit Views, and OpenAPI.

PostgreSQL is the durable contract. Doctrine migrations own schema evolution, Doctrine's transactional Unit of Work owns transaction boundaries, and repositories select ORM, locking, conditional updates, or targeted DBAL according to their behavioral contracts. Access Control is not event-sourced; later agent-session use cases may justify event sourcing separately. Durable delivery intent commits before external effects.

The React client separates Route, Layout, Page, Component, feature API, and shared transport responsibilities; uses an authoritative current-principal projection for route and component permission checks; and keeps state with its narrowest owner. Storybook provides the production component/theme catalog, while frontend browser automation remains intentionally limited.

Owned Domain/Application behavior requires meaningful 100% line coverage. PostgreSQL integration tests prove persistence and concurrency, deterministic fakes isolate outbound effects, and a small functional suite covers critical HTTP boundaries. Exact authentication journeys, tenant scope, session transport, permission policy, and dashboard contents remain for WF-005.
