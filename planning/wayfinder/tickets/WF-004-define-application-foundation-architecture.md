# Define application foundation architecture

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
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

Write this only when the decision is closed. Link any ADR or implementation handoff that records the settled architecture.
