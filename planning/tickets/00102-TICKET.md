---
id: TICKET-00102
epic: EPIC-00009
title: Publish the confirmed initial project to GitHub
status: ready-for-agent
---

# Publish the confirmed initial project to GitHub

## Problem statement

A human may want the new project's initial commit published to GitHub, but that choice must not authorize arbitrary
pushes or expose source-host credentials to generated code or Agents. Creation, push and default-branch effects
may succeed separately and need truthful recovery.

## Solution and boundaries

Supply the optional GitHub stage of TICKET-00101's exact confirmed creation plan. Use the existing authenticated
`gh` identity without collecting or displaying its token; verify current identity and authority for the independently
selected personal or organization owner. Suggest the personal owner and project slug as repository name, but permit
other authorized owners/names. Default private; public requires the owning complete preview's heightened warning
and explicit acknowledgement. Bind description, owner, name, visibility, selected initial branch and exact initial
commit to that confirmation. No extra ordinary-stage prompt or inferred consent from a previous attempt.

Expose bounded read-only preflight for identity, owner authority, repository-name availability, visibility/policy
and connectivity. Respect organization policy, SAML, scopes, Permissions, naming collisions and source-host outages.
Unknown availability is not proof that a name is free. No automatic login, key creation, organization enrollment or
credential repair occurs; direct the human to existing versioned operator guidance.

Create only the exact confirmed repository, establish its attributable provider-native identity, configure the
new local repository's intended remote, push the generated initial commit and establish/verify the selected remote
default branch. Prefer configured SSH Git transport; an explicitly configured supported transport must preserve the
same credential and target boundaries. Verify actual local/remote identity, visibility, branch/ref and commit
outcomes, not just CLI exit. Do not stage or commit preparation changes or unrelated files; publication of the
initial subject remains distinct from observed post-preparation worktree state. Never force-push or overwrite
unrelated local/remote state to satisfy an attempt.

Keep source-host authority in its protected operation owner, not the preparation process, browser/support Agent,
Pi coding child or generated project. Human-authorized project publication is distinct from EPIC-00008 Release
Manager authority and grants no standing implementation/push/PR access to any Agent. Reuse qualified Git/provider
adapters when their contracts fit, without depending on completed managed Review or release workflows.

Record original intent/result evidence for remote creation, local remote setup, push and default-branch setting
within the same creation attempt. Reconcile provider uncertainty and exact operation-owned resources before retry.
A matching name alone does not establish ownership or authorize adopting an existing repository. Create failure
retains local Git; push failure retains local and remote state; interrupted/unknown effects remain visible until
reconciled. No duplicate remote, commit, push effect or inferred success from disappeared processes. When current
policy permits, TICKET-00101 owns explicit continuation without publication, preserving remote state already created.

Cancellation stops future effects, not completed publication. No automatic remote/directory deletion or cleanup.
If TICKET-00101 admits separately confirmed cleanup, execute only the exact currently authorized ownership-checked
provider effect, verify its outcome and preserve safe history. Refuse uncertain, referenced or unrelated resources;
cleanup authority never comes from the original create confirmation alone.

Local-only creation does not invoke this stage and needs no GitHub publication identity or connectivity when its
installed source is already available. Exclude existing-repository adoption, non-GitHub hosting, credential enrollment,
arbitrary source-host administration, unrelated push, PR creation/approval/merge, releases/deployment and source
starter publication. This TICKET does not own catalog retrieval or private-source connection management.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Inspect publication feasibility | N/A — read-only preflight | Safe authenticated identity, owner policy, name availability and transport capability | N/A — inspection publishes nothing | Exact preview facts or policy/connectivity failure |
| Create the confirmed repository | Execute the original admitted remote-creation stage | Current grant/target, original operation and provider outcome | Confirmed source-host creation facts at the attempt owner | One exact repository with the selected visibility/description |
| Publish the initial subject | Execute admitted remote setup/push/default-branch operations | Local identity, exact initial commit and actual remote refs/default | Verified publication-stage facts | Intended remote/ref/default branch only; no new local commit |
| Reconcile partial publication | Explicit original-operation reconciliation or safe retry | Provider-native identity, prior results, current authority and actual local/remote state | Confirmed recovery/hold facts | Preserve local/remote evidence and avoid duplicate or unrelated effects |
| Clean an owned remote when separately authorized | Exact confirmed provider cleanup operation | Current ownership, references, target and actual provider outcome | Verified cleanup or uncertainty fact | Only the separately approved cleanup; no automatic compensation |

## Validation and permissions

Check current human creation/publication authority, precise owner/target scope and actual source-host authority at
each admitted effect. Revalidate plan/currentness and ownership; public acknowledgement is specific to the exact
preview, not a reusable permission. Validate bounded names, description, branch/ref, remote URL/provider identity
and command arguments; reject credential-bearing URLs, injection, changed targets and unsupported transitions.

Use protected credentials only within the qualified adapter's invocation lifetime; never expose tokens, SSH keys,
passphrases, raw provider errors or credential-store contents through browser, logs, events or receipts. Required
state/audit/intent commits under ADR 0002 precede external Git/HTTP/CLI effects. Reconciliation uses actual provider
facilities; unavailable evidence remains unknown rather than an exactly-once promise or blind replay.

## Acceptance and evidence

- Real authorized GitHub creation is private by default; selected organization/name/branch and explicitly
  acknowledged public choice are enforced with independent names and safe source-host authority checks.
- Verify the exact initial commit, actual remote identity/visibility/default branch and secret-free receipt.
  Preparation changes and unrelated content are neither committed nor published implicitly.
- Collisions, SAML/scope/policy failures, revocation, outage, lost acknowledgement and partial push/default-branch
  results preserve local/remote resources and permit only reconciled safe retry or explicit continuation.
- Local-only creation succeeds without this stage. Agents and generated code receive no source-host credentials;
  cleanup never follows automatically from failure or cancellation.
- Owned adapter/authority/recovery tests, separately authorized real GitHub/Git evidence and `./bin/build` pass.
  Doubles prove fault behavior, not live support; do not test GitHub, `gh`, SSH or Git internals or publish fixtures
  to unrelated repositories. TICKET-00103 consumes the actual integrated creation receipt.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 4 of EPIC-00009's six-TICKET split. Applies
[WF-021](../wayfinder/tickets/WF-021-define-new-project-creation-and-registration.md),
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md),
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md) and
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

[TICKET-00101](00101-TICKET.md) owns plan/attempt/cleanup authorization; [TICKET-00100](00100-TICKET.md) supplies
the exact new local Git subject. Supply verified source identity to [TICKET-00103](00103-TICKET.md)'s registration
consumer without duplicating TICKET-00024 identity rules. Reuse early Runner/Git/provider capabilities, not final
managed role journeys, as dependencies. Existing source-access/operator owners remain; this planning approval
creates no TASK, credential access, authentication, remote, push, default-branch change or deletion authority.
