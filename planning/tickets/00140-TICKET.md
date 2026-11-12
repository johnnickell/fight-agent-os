---
id: TICKET-00140
epic: EPIC-00026
title: Resolve comparison projects and permitted local settings
status: ready-for-agent
---

# Resolve comparison projects and permitted local settings

## Problem statement

Maintainers need to compare the intended projects without hard-coding one developer's checkout layout into a
reusable skill. Shared defaults must remain useful while another developer can replace the entire comparison
set. Configuration and path resolution must not execute source text or expose unrelated environment secrets.

## Solution and boundaries

Deliver the scope and setting-resolution outcome approved in [EPIC-00026](../epics/00026-EPIC.md):

- Resolve configuration from the invoking project, not the installed skill provider. Use root-level tracked
  `compare.yaml`, replaced in full by ignored `compare.local.yaml` when present. Never merge them or silently
  fall back when the local file is invalid. Explicit project selection overrides configured selection for the run.
- Specify and document one small YAML schema for project names, repository identities and checkout paths.
  Keep membership and paths in the active file, without a separate paths file or registry. Include examples
  for shared defaults, complete local replacement and explicit selection. Define supported invocation syntax
  before implementing callers; do not invent a parser, runner or option framework solely for this skill.
- Supply defaults for `project-slim`, `project-symfony`, `project-laravel`, `project-codeigniter` and `project-yii`.
  Their expected repositories are the corresponding `johnnickell` starters. Use paths such as
  `${PROJECT_BASE}/project-slim`; membership is intent, not proof of checkout availability or alignment.
- Support `$VARIABLE` and `${VARIABLE}` path substitution without shell execution, command substitution or
  implicit tilde expansion. Resolve relative paths against the active configuration file's directory. Define
  malformed placeholders and unsupported configuration forms as actionable errors, not executable syntax.
- Expose `./bin/env PROJECT_BASE` on the host. Permit only explicitly supported setting names; initially this
  comparison requirement needs only `PROJECT_BASE`. Read the process environment first, then the invoking
  checkout's `.env`. Require a nonempty value; never dump the environment or return an unrelated setting.
- Specify a deliberately bounded dotenv format before implementation, covering comments, whitespace, quoting,
  duplicate keys and malformed assignments. Parse values as data, never source the file or execute/interpolate
  shell expressions. Errors must identify the setting/problem without echoing secret-bearing file content.
  Do not make comparison depend on application boot, a database or a running container.
- Document an absolute-path `PROJECT_BASE` example in `.env.example` without a private developer path. Do not
  populate a user's existing `.env` automatically or forward host checkout paths into container environments.
- Keep the consumer contract a request for a named, permitted setting. Ordinary exported variables work across
  projects; the current project-provided host resolver supplies missing permitted values. A future authorized
  MCP tool may replace this transport without changing configuration. No MCP implementation, provider registry
  or generic settings framework is included.
- Report unset variables, absent capabilities, denied access and missing/unreadable checkouts explicitly. Never
  bypass denial by reading `.env` directly, switching providers or falling back to Bash. If no explicit scope
  or applicable configuration exists, ask for scope rather than scan arbitrary sibling directories.
- Inspect each selected location's repository/source evidence and revision, preserving discrepancies and dirty
  working-tree observations. Configuration does not grant access or prove managed Project identity. Follow
  [ADR 0005](../adr/0005-repository-identity-and-link-consistency.md)'s distinction between paths/source evidence
  and identity without registering, linking or creating managed identities. Do not silently substitute a fork,
  another checkout or a similarly named directory.

Excluded: analysis of drift, report generation, repository cloning, compared-source changes, container changes,
MCP implementation, managed registration/linking, global installation and publication. The sibling
[TICKET-00141](00141-TICKET.md) consumes this scope/settings contract and owns comparison and report delivery.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Resolve shared or local comparison scope | No product command; invoked by the local `compare` skill | Read the active project configuration and explicit selection | N/A: local read-only resolution produces no application event | A bounded project selection, path/source observations and explicit gaps; no checkout changes |
| Resolve a permitted host setting | Host tooling `./bin/env PROJECT_BASE`, not an application CommandBus operation | Read only the supported process setting or parsed local `.env` fallback | N/A: no business state changes | Return the named value or a safe diagnostic; no environment dump |
| Use another developer's project selection | Local configuration authored by its owner; no product command | Read the complete local replacement instead of tracked defaults | N/A: no application event | Selected projects reflect the local file without retaining shared entries |
| Encounter unavailable or contradictory evidence | No mutation or automatic recovery command | Inspect only authorized setting/path/source evidence | N/A: gaps are observations, not workflow transitions | Identify affected projects and owner action; do not claim complete scope |

## Validation and permissions

Use native authorized reads for the invoking project and selected targets. Configuration does not confer access
to sibling repositories, `.env` secrets or Desktop destinations. Resolver permission failures stop dependent
resolution; independent usable scope may be handed to comparison with the omissions explicit. Reject an invalid
active configuration as such rather than guess intent. Process-environment and local-file precedence must be
observable and documented, including empty values and malformed local input.

Validate YAML structure, duplicate/invalid project entries, placeholder handling and dotenv edge cases directly
with the owning tooling. Demonstrate that shell expressions cannot run, unrelated keys cannot be requested,
and diagnostics do not print secret values. Do not add product-suite tests of wrappers or configuration text.
There are no application commands, queries, transactions, audit events or new managed Permissions in this local
tooling requirement. Future permission-controlled transport remains a separate implementation.

## Acceptance and evidence

- No-argument scope resolves the five configured starters when available; explicit selection and a complete
  local replacement produce their intended scope without leftover shared members.
- The documented schema and invocation syntax cover portable paths, source identity evidence and local overrides.
- Relative paths, both variable spellings, process-over-file precedence and quoted dotenv paths resolve as
  documented without requiring a container. Unset/empty/unsupported settings and malformed input fail clearly.
- No configuration, dotenv value or repository content can execute commands through this resolution path.
  Requesting an unrelated secret is refused without disclosing it or dumping surrounding `.env` text.
- Missing checkouts, source mismatches, ambiguous identities, dirty state and denied capability are visible;
  no implicit clone, registration, source substitution or permission bypass occurs.
- Evidence records actual inputs exercised and outcomes, without retaining credentials. Owning tooling checks
  replace product-suite tests of infrastructure; later implementation retains normal build/Review/QA duties.

## Sequencing and TASK readiness

Establish the exact configuration, invocation and setting-request/result contracts before implementing their
consumers in TICKET-00141. Keep tooling attached to this real comparison journey, not a standalone settings
infrastructure demonstration. Report presentation design can proceed once those contracts are available.

Implementation must use the already selected isolated worktree on a `feature/*` branch from `develop`; do not
repeat the checkout-choice question. No implementation worktree or TASK is created by this TICKET.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00593](../tasks/00593-TASK.md) | Resolve configured comparison scope safely | in-progress |
<!-- /planning:children -->

## Decisions and progress

The user approved the two-TICKET decomposition of EPIC-00026 in this session. This record owns project selection
and permitted settings; TICKET-00141 owns comparison and the complete report outcome. Requirements are accepted;
implementation and verification have not begun. The user approved the single-TASK decomposition, recorded as
[TASK-00593](../tasks/00593-TASK.md), with no blockers and the isolated-worktree choice preserved. This TICKET's
TASK decomposition is complete. Next planning phase: decompose TICKET-00141 before selecting implementation.
