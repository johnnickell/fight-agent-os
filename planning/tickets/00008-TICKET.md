---
id: TICKET-00008
epic: EPIC-00003
title: Establish application ownership and orchestration boundaries
status: ready-for-agent
---

# Establish application ownership and orchestration boundaries

## Problem statement

Fight Access Control owns identity and authorization behavior while Agent OS owns framework integration and application-specific orchestration. Without a durable and enforceable boundary, feature work may duplicate package types, place policy in Actions or adapters, or create dependencies that undermine DDD and CQRS.

## Solution and boundaries

Record an accepted package-ownership/application-boundary ADR before structural production scaffolding. Define direct use of package Domain/Application commands, queries, entities, policies, views, and capability interfaces; the narrow conditions that justify `App\Application` orchestration; Domain/Application/Adapter dependency direction; capability-oriented adapter organization; CQRS dispatch/composition; and one-Action/one-interaction Action–Domain–Responder delivery.

Prove the conventions with a small representative composition path and enforceable architecture checks. Do not create wrappers merely to rename package types or speculate about contexts and abstractions without a use case.

Out of scope: feature-specific authentication/invitation endpoints, persistence schemas, HTTP representations, React behavior, event sourcing, and broad product implementation.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record ownership and dependency decisions | N/A — ADR creation is architecture work | Inspect accepted EPIC/WF decisions and package public contracts | N/A — no application domain event is produced | An accepted ADR states ownership, orchestration, dependency, CQRS, and HTTP interaction rules |
| Compose package-owned behavior | Reuse the package command or an explicit cross-boundary orchestrator; no renaming wrapper | Reuse package queries/views or an explicit application-owned query where justified | Preserve package events and application-owned events at their actual ownership boundary | Representative composition resolves injected capabilities without reversing dependencies |
| Enforce structural boundaries | N/A — architecture checks are build operations | Inspect namespaces, imports, composition, Actions, Responders, and adapter placement | N/A — checks report violations rather than emit domain events | Invalid layer/package dependencies fail deterministically |

## Validation and permissions

The ADR and proof must reject business policy in HTTP Actions, persistence/framework imports in Domain/Application, direct external effects inside business transactions, generic wrapper layers, service-location from business code, and role-name authorization bypasses. Actions map transport and dispatch one command, query, or explicit orchestrator; endpoint-specific Responders own successful representations.

No user-facing permissions apply because this is an architecture foundation. Repository changes require approved scope. The boundary must preserve server-side authorization ownership and avoid exposing package entities or secrets through adapters.

## Acceptance and evidence

- A concise accepted ADR records package ownership, allowed Agent OS orchestration, dependency direction, CQRS dispatch, composition, and Action–Domain–Responder rules.
- Owned code organization is documented by context/capability rather than speculative technical wrappers.
- A representative composition path demonstrates direct package use and injected adapters without duplicating package types.
- Architecture checks include passing valid examples and failing fixtures or equivalent evidence for prohibited dependency directions.
- Focused architecture/composition checks and `./bin/build` pass with fresh results and disclosed warnings.
- Evidence explains any boundary that cannot yet be automated and where later TICKETs must close it.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00009](../tasks/00009-TASK.md) | Accept the application ownership and orchestration ADR | ready-for-agent |
| [TASK-00010](../tasks/00010-TASK.md) | Prove and enforce application ownership boundaries | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the package and application boundary approved by [WF-004](../wayfinder/tickets/WF-004-define-application-foundation-architecture.md). It follows the stable dependency baseline in [TICKET-00007](00007-TICKET.md) and gates structural work in the persistence, HTTP, delivery, and client requirements.
