---
id: TICKET-00058
epic: EPIC-00013
title: Deploy an application release
status: ready-for-agent
---

# Deploy an application release

## Problem statement

An application release must deliver a specific certified candidate to a named production environment and
establish its observed health. Publishing a TASK PR or a library tag does neither; a generic deployment
command would bypass project-owned preparation, secrets, rollout policy and recovery.

## Solution and boundaries

Continue the **same** release TASK and Team Lead-coordinated Workflow from [TICKET-00056](00056-TICKET.md)
using a qualified project-owned application runbook. Bind the deployable artifact/commit, production
environment, health criteria, rollback/forward plan and authorized effects to one exact certified candidate.
Only a separately permissioned Release Manager executes supported deployment operations inside the bounded
human approval. Team Lead prepares, monitors and reconciles everything else that requires no new human
decision. Verify actual deployment identity, target and health before recording completion; a started or
acknowledged rollout is not a successful deployment.

When configured branch strategy requires merge-back or forward-port after verified release, perform it within
the same Workflow under applicable bounded authority and branch protection, with no additional TASK. Unknown
provider state, failed health, conflicting changes or needed rollback stop at a visible recovery decision.
Rollback or an expanded target is **not** automatic or implied by the original grant unless the approved
runbook and exact effect approval explicitly cover it. This TICKET excludes a universal deployment executor,
package tagging/GitHub releases, independent production credentials in Agent sessions and PR merge authority.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Validate deployable candidate | Prepare/confirm exact stage under existing Workflow | Certified artifact/commit, runbook, environment, current grants and health/rollback policy | Attributed readiness/blocked facts | No production effect until candidate and named effect approval are current |
| Deploy and verify | Durable authorized deployment operation followed by verified result/health recording | Provider rollout state, target version/digest, health observations, current policy | Deployment intent, observed outcome and verified health/failure separately | Only the approved project-specific production effect; never infer success from acceptance of a request |
| Resolve failed or uncertain rollout | Reconcile same effect identity; request renewed grant for unapproved rollback | Provider/environment state, prior intent, checks and available rollback/forward path | Recovery, Needs Human or separately authorized rollback fact | Avoid duplicate deployment; continue same TASK/Workflow safely |
| Finish project follow-through | Scoped branch operation if strategy requires | Verified production candidate, merge inclusion and current branch authority | Verified completion/blocked follow-through facts | Same-Workflow forward-port/merge-back when permitted, not another TASK |

These are semantic use cases, not a predetermined deployment platform, protocol or lifecycle schema.

## Validation and permissions

Separate Release Manager deployment grants from Team Lead preparation and ordinary PR landing. Bind all
authority-bearing operations to live Permissions, candidate/artifact digest, environment, runbook/configuration
revision, exact effect approval, time/cost limits and expected state. Keep deployment credentials out of
Agent-visible environments, logs and prompts; project adapters reconcile at-least-once external effects
using durable intent and provider observations. Enforce health and required checks before `done`; a failed
health check or unknown target remains incomplete. New environment, expanded scope, check exception or
unapproved rollback needs a specific human grant, not a classifier decision. Respect branch protection and
never merge unpublished/unrelated development into production.

## Acceptance and evidence

- A configured application project can identify one exact candidate and production environment, qualify its
  own runbook, and obtain one bounded approval for named effects; an unsupported project cannot deploy.
- Deployment records actual target version/artifact identity and independently verified health. A queued,
  unknown, failed or partially rolled out effect is not `done` and exposes a concrete next action.
- Duplicate delivery, timeout, changed configuration or revoked authority cannot trigger repeated production
  effects or claim success without provider evidence; a rollback is separately scoped unless already approved.
- Required configured branch follow-through remains in the same release Workflow and honors protected-branch
  rules; trunk-based projects do not inherit a GitFlow `develop` step.
- Exercise owned scoped authority, stale-candidate rejection, health/verification, uncertain-effect recovery
  and denied rollback with focused behavior and integration evidence. Qualify the actual deployment provider
  and project runbook using owning tools; no product tests of runbook/configuration text or test harnesses.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00222](../tasks/00222-TASK.md) | Qualify and preflight an exact application deployment target | needs-info |
| [TASK-00223](../tasks/00223-TASK.md) | Execute the approved application rollout and reconcile provider state | ready-for-agent |
| [TASK-00224](../tasks/00224-TASK.md) | Verify production health and coordinate bounded recovery | ready-for-agent |
| [TASK-00225](../tasks/00225-TASK.md) | Finish application branch follow-through and release closeout | needs-info |
<!-- /planning:children -->

## Decisions and progress

Approved requirement split for [EPIC-00013](../epics/00013-EPIC.md), consuming
[TICKET-00055](00055-TICKET.md)'s project policy and [TICKET-00056](00056-TICKET.md)'s bounded release grant.
This is independent of [TICKET-00057](00057-TICKET.md)'s library publication path; it does not turn ordinary
PR landing into deployment. No production enrollment, credential, rollout or live release TASK was created
by requirement planning.

### Approved TASK decomposition — 2026-10-11

John approved [TASK-00222 — Qualify and preflight an exact application deployment target](../tasks/00222-TASK.md),
[TASK-00223 — Execute the approved application rollout and reconcile provider state](../tasks/00223-TASK.md),
[TASK-00224 — Verify production health and coordinate bounded recovery](../tasks/00224-TASK.md), and
[TASK-00225 — Finish application branch follow-through and release closeout](../tasks/00225-TASK.md). They
are dependency-ordered implementation slices of the **same** release TASK and Workflow, not per-stage
live release TASKs or new effect approvals. TASK-00222 consumes conditional human signing and the exact
approved subject from TICKET-00056; the application path does not depend on TICKET-00057's library path.
All are unranked.

TASK-00222 is `needs-info` pending a concrete enrolled application/environment and qualified
project-owned provider/runbook. TASK-00225 is `needs-info` pending actual trusted managed merge
observation for applicable branch follow-through, not the library TASK's grant. These are real unresolved
capabilities, not invented TASK blocker IDs. No application was enrolled and no live release TASK, Agent,
Workflow, deployment, rollback, branch effect or health/closeout proof was created by planning. This is
the final accepted TICKET in the selected map's TASK-decomposition phase; Board-based selection follows
once generated views are current.
