---
id: TICKET-00100
epic: EPIC-00009
title: Generate and prepare a fresh local project
status: ready-for-agent
---

# Generate and prepare a fresh local project

## Problem statement

A confirmed project must become a predictable new local Git repository, not a copy of the starter's history,
ignored dependencies or credentials. Unsafe paths, mismatched transformations and failed preparation must preserve
truthful partial state instead of triggering broad replacement or AI repair.

## Solution and boundaries

Supply deterministic local stages to TICKET-00101's single durable creation attempt. Use only TICKET-00099's pinned
tracked archive and generation manifest. Materialize reusable source, instructions, tests, architecture and tooling
according to that contract, excluding starter `.git`, remotes, ignored dependencies, caches, local state, secrets
and generated credentials. Apply exact declared substitutions; validate every expected target/match and observed
result. Changed, absent, duplicate, unsafe or out-of-bounds targets stop before later effects. Never broad-search/
replace or ask a model to repair generation. Remove or neutralize source package identity only as declared; do not
ask for Composer vendor/package identity merely because Composer is present.

Keep human name, Workspace slug, parent path, local directory name, selected branch and remote repository name
independent. Operate only beneath configured Runner-accessible approved roots, including safe nested parents.
Canonicalize destination and ownership, prevent symlink escape and collisions, and refuse pre-existing or unrelated
content. Revalidate immediately before mutation; a prior successful preview is not a permanent path reservation.
An outside-root location needs TICKET-00023's explicit installation/mount operation, never an implicit browser fix.

Initialize a fresh Git repository without inherited commits or remotes. Suggest `develop` but create only the
selected initial branch, such as `main`, and exactly one initial commit over deterministic generated state.
Record actual commit/tree/branch evidence and distinguish deterministic content from incidental commit metadata;
recovery must reconcile the original commit rather than create another one. Local-only is a complete Git result
with no GitHub remote, not a no-Git fallback.

After the initial commit, optionally run only the exact manifest preparation command, selected by default and
previewed as potentially networked executable work. Use qualified Runner containment, bounded time/resources,
credential isolation, egress, process supervision, safe observations and redaction. The manifest cannot grant
network or secret access beyond confirmed and current policy. Missing capability holds instead of executing on
an unrestricted host. Preserve declared tracked preparation changes as observable dirty state; do not silently
commit, discard or include them in the initial-commit publication. Report the exact initial subject separately.

Preparation failure retains the valid generated Git repository for explicit reconciled retry or deliberate
completion where policy permits. Return exact stage receipts, current filesystem/Git/process observations, known
retained resources and safe failure reasons to the attempt owner. Uncertain process liveness/effects block another
launch; no success is inferred from exit alone or a missing process. Cancellation and cleanup are separately
controlled by TICKET-00101; this capability executes only its exact authorized, ownership-checked local effects.
Never automatically delete a directory or hide unrelated changes to make recovery appear successful.

Exclude catalog policy, another attempt state machine, GitHub publication, Repository registration, arbitrary
commands, AI-chosen paths/transformations, existing-content adoption, source releases and a coding Workflow/TASK
claim. Reuse qualified deterministic Runner primitives without requiring completed Team/Agent execution.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect generation feasibility | N/A — read-only validation | Exact snapshot/manifest, substitutions, canonical paths, collisions and capabilities | N/A — inspection changes no project | Safe facts for the complete preview |
| Generate tracked source | Execute the exact admitted generation stage | Original intent/result and current destination ownership | Verified stage facts recorded by the attempt owner | New owned files with exact manifest transformations |
| Initialize fresh Git | Execute the original Git-initialization stage | Current Git shape, branch, tree and prior commit evidence | Confirmed local Git stage facts | One selected branch and one initial commit, no inherited history/remotes |
| Prepare dependencies | Execute or explicitly retry the admitted manifest preparation stage | Current authority, command, capability, process and prior outcome | Confirmed preparation/partial-result facts | Bounded executable/network effects with visible tracked changes |
| Reconcile or clean local effects | Reconcile original operation; separately authorized precise cleanup | Actual files/Git/process identity, ownership and retained resources | Confirmed outcome/cleanup facts only | Resume safely or hold; delete only separately authorized owned resources |

## Validation and permissions

Require current initiating-human grant, exact immutable plan/stage identity, authenticated eligible Runner and
operation-specific local filesystem/Git/preparation authority. A queue delivery, profile or manifest is not consent.
Use shared root/resource fences where needed so another operation cannot replace a validated destination or run
conflicting preparation. Reject malformed names/branch inputs, command injection, unsafe archive paths/file types,
ambiguous ownership, stale plan or unexpected Git state. Bound all extraction, transformation, process and evidence
operations; secret-safe errors must not expose provider credentials or unrelated host contents.

Commit required stage intent before filesystem/process/Git effects and record verified outcomes separately under
ADR 0002. Do not keep database transactions open across these effects or claim exactly-once external execution.
On uncertain retry, inspect original effect ownership before deciding anything remains safe to repeat.

## Acceptance and evidence

- A real eligible installed starter generates exact declared content under an approved root, with independent
  names, no copied secret/history/remotes, one selected branch and one original initial commit.
- Traversal, symlink/root escape, pre-existing content, changed targets and concurrent destination collisions are
  refused without damaging unrelated work; incomplete owned stages remain attributable and recoverable.
- Actual bounded preparation reports success, failed/unknown process outcomes and tracked changes honestly.
  Retry never hides effects or duplicates commits; skipping preparation remains an explicit recorded choice.
- Owned generation/authorization/reconciliation tests and real Git/Runner integration support TICKET-00103's
  create/register acceptance. Run `./bin/build`; qualify infrastructure directly rather than test Git, package
  managers, frameworks, Compose, generated files or sandbox tooling as product behavior.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 2 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Consume [TICKET-00099](00099-TICKET.md)'s manifest/snapshot and precise approved-root/Runner capabilities from
TICKET-00023/00032/00093. Those owners do not gain a new dependency on project creation or lose existing holds.
[TICKET-00101](00101-TICKET.md) owns authorization/stage sequencing/recovery; define its admission/result contract
early without whole-TICKET cycles. [TICKET-00102](00102-TICKET.md) consumes the exact initial commit and
[TICKET-00103](00103-TICKET.md) consumes the identified local checkout. No TASK or live directory, Git commit,
preparation command, cleanup or Runner activation is authorized through this planning approval.
