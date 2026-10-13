---
id: TICKET-00105
epic: EPIC-00010
title: Inspect effective repository instructions
status: ready-for-agent
---

# Inspect effective repository instructions

## Problem statement

A human needs to know which repository instructions Pi actually loads before changing them. A generic file browser,
Git tracking status or guessed precedence cannot establish the effective instruction set or safe checkout access.

## Solution and boundaries

Provide bounded inspection and its focused Dashboard presentation for exactly five names at the designated checkout
root: `AGENTS.override.md`, `AGENTS.md`, `AGENTS.MD`, `CLAUDE.md` and `CLAUDE.MD`. Use the installed supported Pi
discovery contract to explain loaded versus shadowed files, including same-directory override behavior. Qualify
actual runtime/version semantics during implementation rather than treating WF-022's historical Pi version as fresh
evidence. Unsupported or ambiguous discovery is an explicit limitation, not an invented precedence rule.

List recognized files only and open one bounded text/Markdown file at a time. Show plain-language effective labels,
missing/unavailable state and concise tracked, untracked, ignored, modified, branch and synchronization facts.
Untracked or ignored content may still be loaded by Pi; explain its publication consequence without calling it
ineffective or generated. Supply exact content/digest and safe evidence to TICKET-00106's form and TICKET-00107's
separately authorized assistance. Reading a file never authorizes either use or provider disclosure automatically.

Use the Repository's single designated local Worktree from TICKET-00024. Resolve its actual Repository/Host/Checkout/
Worktree identity, Git common directory/marker, registered path, approved-root containment, Runner eligibility and
current ownership. Do not ask for a clone on each read, silently choose a substitute or access a Workflow-owned
worktree. Explain moved, unavailable, unsafe, ambiguous or conflicting-operation state and its permitted next action.
Existing registration/context owners remain authoritative; this product does not relink or repair a checkout.

Keep the Runner mechanical: exact allowlisted enumeration, bounded reads, digest and filesystem/Git observation.
Application policy interprets qualified effective-file semantics and authorizes access. Preserve actual observed
branch/commit, encoding/size, digest, context revision, Runner and provenance behind readable details. Do not expose
unnecessary internal identifiers, absolute personal paths, storage locators or credentials. Observations are bounded
facts at a point in time, not a reservation, current grant or guarantee that a later write will succeed.

Reject symlinks, non-regular/special files, binary content, unsupported encodings, excessive size, path traversal,
escaped roots and case ambiguity. No recursive scan, nested instructions, parent/user content, arbitrary Markdown/
source files, `.pi/SYSTEM.md`, `.pi/APPEND_SYSTEM.md`, Skills, settings, prompts or Harness resource editing. Bounded
provenance may explain composition without exposing other instruction bodies. Never parse prose into enforceable
application policy or claim an unrecognized convention affects Pi.

Reuse the authenticated repository Dashboard, context projection and visual components. Include truthful empty,
denied, stale, failed and unavailable states, accessible keyboard navigation and readable light/dark/narrow layouts.
Do not create a repository tree, terminal, IDE, second context resolver or standalone inspection demonstration.
Ordinary authorized reads use security/request logging, not a fabricated Domain event per view.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| List recognized root instructions | N/A — read-only | Authorized designated-checkout availability and bounded recognized-file metadata | N/A — inspection is not a business mutation | Safe effective-file list or explicit refusal; no recursive scan |
| Open one file | N/A — read-only | Exact allowlisted bounded content, digest, Git state and provenance | N/A — ordinary read logging only | Current authorized content without broader filesystem access |
| Explain loaded/overridden state | N/A — deterministic read projection | Qualified runtime discovery facts and existing context evidence | N/A — derived explanation creates no context revision | Accurate labels independent of tracked/ignored status |
| Inspect a blocked checkout | N/A — read-only | Safe identity, containment, Runner/ownership and availability reason | N/A — observations confer no authority | Actionable next step without relink, mutation or alternate checkout |

## Validation and permissions

Require current View repository instructions authority and actual Repository/checkout scope on every metadata and
content query. Use TICKET-00024/00025 identity/context and TICKET-00023 root contracts, not caller-selected absolute
paths or a role-name shortcut. Validate strict bounded names, text shape and observation provenance; recheck unsafe
or changed path/identity before reading. Source content is untrusted data and must be rendered inert.

Apply ADR 0003/0004 no-store, current-context response fences, guarded loaders and private-cache invalidation.
Revocation, context change and late responses must not expose retained content. Queries may observe the filesystem
through qualified Runner capabilities but cannot repair, fetch/synchronize, write files or trigger Agent/provider
work. Keep raw exceptions, credentials and unrelated content out of logs, telemetry and browser error details.

## Acceptance and evidence

- Actual designated-checkout inspection shows only the five recognized root names and correctly explains effective
  state under the qualified Pi runtime, including ignored/untracked and override cases without guessed precedence.
- Unsafe paths/types/encoding/size, case ambiguity, moved/conflicting checkouts and denied/revoked authority refuse
  access without reading unrelated files or choosing another worktree.
- Exact authorized content/digest/provenance feeds editing and assistance consumers; stale observations never grant
  subsequent effects. Browser loading/empty/denied/unavailable and responsive/keyboard states use real results.
- Owned inspection/classification/authorization/client behavior tests, direct actual Pi/Git/Runner qualification and
  `./bin/build` pass. Do not test Pi, Git, Markdown prose, library internals, configuration or a generated file list.
  Integrated evidence continues through TICKET-00109's real instruction-change journey.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00010's five-TICKET split. Applies
[WF-022](../wayfinder/tickets/WF-022-define-browser-instruction-inspection-and-assisted-editing.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md),
[ADR 0003](../adr/0003-browser-authentication-security-profile.md) and
[ADR 0004](../adr/0004-client-authority-and-runtime-state.md).

Consume existing registration/context/Dashboard and exact qualified deterministic Runner contracts, not completed
coding or managed Review journeys. Supply [TICKET-00106](00106-TICKET.md) and [TICKET-00107](00107-TICKET.md)
without depending on final editing/assistance. [TICKET-00109](00109-TICKET.md) owns synchronization facts/operations;
absence of that integration remains explicit, not a reason to invent freshness or reverse-block bounded reads.
Existing supplier holds and Board priority remain unchanged. No TASK, file read outside planning, runtime discovery,
checkout change or live Agent operation is authorized by this requirement approval.
