---
id: TICKET-00142
epic: EPIC-00027
title: Share white-label React and Bootstrap account journeys
status: done
---

# Share white-label React and Bootstrap account journeys

## Problem statement

The application suite needs maintained account behavior and a consistent visual foundation without copying
Agent OS source or inheriting its product-specific navigation and screens.

## Solution and boundaries

Deliver one npm library under `packages/react/`, working name `@fight/react`, independently versioned from
Agent OS and Access Control. Supply focused public controls/account/style exports, compiled JavaScript and
TypeScript declarations. Agent OS consumes the actual package boundary; remove replaced local implementations.
Keep existing React-Bootstrap components directly where they suffice. Retain owned components for actual
meaning or repeated behavior, not forwarding wrappers or a general plugin/registry system.

React/TypeScript, Bootstrap and React-Bootstrap are the suite convention. No Tailwind. Use host React peer
dependencies and a documented compatible range; avoid bundling a second React or requiring the host's exact
build tool/router. Host application composition supplies routes/navigation, page destinations, API integration
and brand settings. Share the sole session/principal machinery without exporting product-specific state.
Transport customization must preserve qualified same-origin/session/CSRF/security contracts, not arbitrary
credential-bearing URLs or caller overrides that weaken admission.

White-label public/error/account/admin shells through product name, local logo with accessible text, titles,
semantic palette, typography, spacing and radius overrides. Export default CSS plus a documented later override
surface for light/dark modes; no required Sass recompilation in consumers. Preserve CSP, local font/asset
provenance, keyboard/focus, contrast, responsive layouts and reduced motion. No dynamic theme-upload service,
inline unsafe styles, database theme editor or remote asset provider is included.

TASK-00596 extracts and brands the existing journeys. TASK-00123/00128 add their new administration screens
directly to the library afterward; TASK-00597 adds the shared table. Package extraction does not reopen accepted
behavior or imply the new administration already exists. Keep the package consumable without Agent OS
Planning, Swagger, Workflow or managed Agent modules. Later Agent provisioning has no first-release stub.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Use shared human account screens | Existing protected backend operations | Existing safe identity/session/user/catalog reads | Existing server events and local invalidation | Current authority and recovery behavior preserved |
| Brand an application | N/A — trusted application configuration | Public brand/theme inputs | Local appearance changes only | Branded public/account/admin UI without modifying package source |
| Add application screens | N/A — host-owned composition | Host-owned data | Host-owned events | New business UI uses public components and conventions |

## Validation and permissions

The backend remains the authority. Preserve existing permission/target admission, memory-only credentials,
safe error mapping, terminal session retirement and explicit mutation confirmation. Package API options cannot
grant permissions or expose secrets. Treat brand strings as text and asset locations as validated local inputs.
Test composition and extension through public exports rather than allowing deep imports of internal files.

## Acceptance and evidence

- Agent OS installs/consumes the library and all extracted real journeys retain their accepted behavior.
- A deliberately different brand exercises public, error, account and admin layouts in both themes without forks.
- Consumers can use supported exports without Agent OS's router, build scripts, private configuration or Tailwind.
- Owned unit-testable behavior has 100% Unit line coverage; browser/accessibility checks cover interactions and
  meaningful integration contracts. Existing tests move with behavior rather than being duplicated.
- Packaging is inspected with its owning tools; no product-suite tests of manifests, build tools or generated files.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00596](../tasks/00596-TASK.md) | Run white-label account journeys from the shared React package | done |
<!-- /planning:children -->

## Decisions and progress

John approved this shared-library/white-label direction on 2026-11-17. TASK-00596 is the bounded package
extraction and branding outcome. The existing administration TASKs retain feature ownership and consume it.
Registry publication and real receiving skeletons remain TICKET-00144. Planning supplies no release evidence.
