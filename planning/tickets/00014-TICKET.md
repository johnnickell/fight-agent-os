---
id: TICKET-00014
epic: EPIC-00004
title: Accept the authentication and dashboard design language
status: ready-for-agent
---

# Accept the authentication and dashboard design language

## Problem statement

Production authentication and dashboard UI needs a reviewed visual and interaction specification. Bootstrap defaults, unreviewed taste, or prototype code promoted directly into React would not establish a coherent accessible foundation.

## Solution and boundaries

Use the approved `design` and `design-review` workflows to explore and independently accept a responsive design language for login, activation, recovery, account security, active sessions, Super Admin operations, dashboard shell, navigation, and user controls. Produce a disposable Bootswatch-like specimen, semantic light/dark tokens, Bootstrap mappings, complete journey/state evidence, and a production handoff.

The preference supports `system`, `light`, and `dark`; system is the default and tracks OS changes, while explicit overrides may persist in `localStorage`. Prototype artifacts remain under `.runs/prototypes/` and are recreated—not promoted—in production.

Out of scope: production React implementation, fake dashboard data, RTL, localization, additional themes, and universal WCAG certification.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Explore the design language | N/A — disposable design workflow | Read approved journeys, architecture, assets, references, and licenses | N/A — no application domain event | Brief, alternatives, specimen, responsive/state captures, and token recommendations are produced under `.runs/` |
| Review the exploration independently | N/A — review workflow | Inspect/render brief, alternatives, states, accessibility evidence, and handoff | N/A — no application domain event | `accept`, `revise`, or `reject` verdict separates blocking findings from subjective critique |
| Hand accepted decisions to production | N/A — design documentation effect | Read accepted verdict and selected alternative | N/A — no application domain event | Production requirements identify tokens, mappings, layouts, interactions, copy, states, and known limitations |

## Validation and permissions

Evidence must cover narrow/mobile and wide/desktop layouts; loading, empty, validation, authentication failure, forbidden, system error, success, expired-link, and expired-session states; keyboard and visible/unobscured focus; semantics; contrast; target sizes; reflow; accessible authentication; and reduced motion. Automated checks and screenshots must not be presented as complete accessibility proof.

Use fake/sanitized data only and record source provenance/licenses. No runtime permission model applies; protected and Super Admin variants must nevertheless show permission-aware visibility without implying client checks provide security.

## Acceptance and evidence

- A sourced brief and meaningful alternatives cover every initial public, authenticated, and Super Admin route.
- The specimen covers typography, color, spacing, controls, focus/form states, navigation, cards, tables, alerts, logos/assets, and semantic token/Bootstrap mappings.
- Light/dark modes and the accessible three-state selector are specified without flashes or credential storage.
- Deterministic responsive/state captures and honest accessibility/interaction observations are recorded.
- Independent design review accepts the production handoff after blocking findings are resolved.
- No prototype code or scratch artifact enters production paths.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00034](../tasks/00034-TASK.md) | Explore the authentication and dashboard design language | ready-for-agent |
| [TASK-00035](../tasks/00035-TASK.md) | Independently accept the authentication and dashboard production handoff | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

Implements the visual destination from [WF-005](../wayfinder/tickets/WF-005-design-authentication-and-authorization-journeys.md). It depends on [TICKET-00005](00005-TICKET.md) and [TICKET-00006](00006-TICKET.md), may run alongside backend bootstrap work, and gates production journey UI.
