---
id: TICKET-00038
epic: EPIC-00006
title: Guard Pi tool execution with deterministic restrictions and Jev
status: ready-for-agent
---

# Guard Pi tool execution with deterministic restrictions and Jev

## Problem statement

Existing human-operated Pi sessions need practical protection against unsafe tool effects before the complete
managed sandbox is available. Prompts alone cannot enforce restrictions, and a probabilistic judgment must not
replace permissions or imply that arbitrary shell execution is contained.

## Solution and boundaries

Deliver the first-milestone tool guard from the [Harness amendment](../epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07),
using [TICKET-00037](00037-TICKET.md) for Jev access. Enforce configured restrictions first, then screen remaining
semantic risks. Keep the initial target human-operated Pi sessions with explicit operator-selected, versioned
policy, allowed roots/tools, protected paths and effect restrictions. Protect guard policy/resources from supported
Agent write routes. Record residual risks when arbitrary shell execution can reach beyond intercepted arguments.

Define supported read/write/edit/bash interception and any nested tool execution. Bind decisions to the actual
operation, arguments, working directory and relevant policy/source revisions. Revalidate before execution when
those inputs change. A positive Jev result never expands permission. The file relevance threshold of 0.70 does
not select safety thresholds; guard rubrics and permit/deny/uncertain treatment need their own reviewed policy.

Block deterministic and semantic denials. Required evaluation failure, timeout, malformed response or unresolved
uncertainty prevents that operation from executing. Surface the operator action needed without teaching the Agent
to work around the block. An explicit narrowly scoped operator decision is not a reusable permission for unrelated
operations, and cannot override independent runtime restrictions. Stop reporting protection as active if required
hooks or configuration are unavailable; report the limits of detecting disablement from within an extension.

Exclude sandbox qualification, managed Workflow grants/claims, autonomous launch, deployment and publication.
[TASK-00138](../tasks/00138-TASK.md) retains those execution-boundary requirements. Semantic credential detection
and post-result prompt-injection screening are still optional later candidates, not first-milestone requirements.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Select protected operation policy | Activate/revise an operator-owned guard profile | Inspect effective version, supported tools and restrictions | Profile activation/revision fact; no Workflow grant | Guard configuration and visible status |
| Request a tool effect | Submit the actual tool operation through interception | Check deterministic restrictions and Jev risk judgment | Operation permitted/blocked/unresolved with bound evidence | Only permitted effects execute; others do not start |
| Resolve uncertainty | Submit an explicit scoped operator decision or cancel | Inspect proposed effect and exact reasons | Resolution recorded for that operation | Resume only the bound permitted effect, or retain the block |
| Recover from evaluator/runtime failure | Retry evaluation of a still-current operation when permitted | Inspect readiness, hook coverage and stale results | Failure/recovery observations | No automatic execution or duplicate effect from an uncertain result |

These are integration operations and observations, not new authoritative Workflow lifecycle events.

## Validation and permissions

- Enforce tool/path/effect restrictions in trusted code for declared supported routes. Account for canonical paths,
  symlinks, writes to guard resources, alternate encodings/tools and nested execution; do not claim a lexical path
  prefix or working-directory setting establishes process isolation.
- Treat command text and repository data as untrusted evaluation inputs. Jev output is evidence under a versioned
  rubric, not a permission or instruction. Keep runtime permissions authoritative.
- Any nested execution added by a helper/general judgment tool must use the same effect boundary. If that route is
  unsupported, deny it or explicitly exclude the containing tool from the protected profile; never imply coverage.
- Bound requests and redact evidence through TICKET-00037. A deterministic denial should not require an upstream
  call. An old allow result cannot authorize changed arguments, policy, sources or a replacement session.
- Prevent duplicate effects across pending evaluations, operator responses, cancellation and reload. Qualify actual
  Pi behavior; do not claim hooks constrain child processes or an adversarial worker unless directly demonstrated.

## Acceptance and evidence

- Directly demonstrate supported calls being intercepted before effects occur, including allowed work, protected-path
  denial, semantic risk denial, uncertainty, provider failure and stale evaluation. Retain exact runtime/policy evidence.
- Verify supported alternate/nested routes do not bypass the guard. Document uncovered routes and their handling;
  show that the supported profile does not present them as protected.
- A late evaluator reply or operator response after cancellation/reload cannot execute an obsolete operation.
- An operator can see active profile, scope, reason for a block and next action without credentials or sensitive
  content leaking into logs. Installing/using the existing presentation resources does not falsely activate guards.
- Test owned policy and effect-admission behavior; qualify extension/runtime integration and restrictions directly.
  Include the repository full gate for application changes without adding product tests of configuration text or wrappers.

## Sequencing and TASK readiness

The semantic guard consumes the qualified access capability from TICKET-00037; its deterministic foundation
can be implemented independently. No dependency on full sandbox completion, browser
Planning or the entire managed Harness is introduced. Before marking guard TASKs executable, pin the initial Pi
integration/coverage, operator-owned policy source, protected-root behavior, semantic rubric/thresholds and exact
uncertainty resolution. Unsupported guarantees require a declared narrower supported profile or a needs-info TASK,
not a promise of universal interception. The complete initial Jev-enabled profile also includes TICKET-00039.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00160](../tasks/00160-TASK.md) | Enforce an operator-selected Pi tool policy | ready-for-agent |
| [TASK-00161](../tasks/00161-TASK.md) | Screen permitted tool effects with Jev and resolve uncertainty | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement split on 2026-10-07 for incremental protection of human-operated sessions. This
TICKET is accepted for TASK decomposition; no guard is active or managed execution qualified by this record.

### Approved TASK decomposition — 2026-10-07

John approved [TASK-00160](../tasks/00160-TASK.md) for an operator-selected deterministic Pi tool policy and
[TASK-00161](../tasks/00161-TASK.md) for semantic screening with operation-scoped uncertainty resolution.
TASK-00160 has no TASK blocker; TASK-00161 depends on TASK-00160 and TASK-00159. Both remain unranked and preserve
existing Board priority. The deterministic-only delivery must be visibly distinguished from Jev-enabled protection.

The initial policy uses operator-controlled machine-local settings, qualified built-in read/write/edit/bash
interception, approved command forms or versioned scripts with constrained arguments, and exclusion of unsupported
execution routes. Every bash and mutating tool call that passes deterministic checks receives semantic screening.
Automatic admission requires `allow` with classification confidence at least 0.95; valid `deny` blocks and other
valid outcomes need explicit one-operation human resolution or cancellation. Required evaluator failures demand
recovery and reevaluation, never an approval bypass. Confidence is not measured safety probability. The TASKs own
exact coverage, invalidation, resource limits and evidence; TASK-00138 retains full execution-boundary qualification.

No runtime protection, live provider use or implementation was activated by this planning approval. At this guard-split approval,
TICKET-00039 still needed TASK decomposition; its later approved split is recorded in that TICKET. The remaining
EPIC/Wayfinder scope remains open.
