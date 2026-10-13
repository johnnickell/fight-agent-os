---
id: TICKET-00093
epic: EPIC-00007
title: Operate the enrolled Runner and bounded dispatch
status: ready-for-agent
---

# Operate the enrolled Runner and bounded dispatch

## Problem statement

An accepted TASK must wait for genuinely available execution capacity and survive transport or Runner interruption
without duplicate work. Operators need deliberate admission, drain, halt and recovery controls rather than treating
a container, heartbeat or queue message as Workflow authority.

## Solution and boundaries

Extend the existing installation with one explicitly enrolled installation-owned `agent-runner` PHP Compose service.
Bootstrap retains a durable Runner identity and protected credential boundary across container recreation; process
and boot instances receive separate generated identities without repeated pairing. Report bounded actual versions,
approved roots, qualified sandbox/tool/network/Artifact capabilities, capacity and health. An incompatible, disabled,
revoked or unavailable Runner leaves work visibly queued or held, never apparently executing.

Operate the dedicated PostgreSQL Doctrine Messenger Agent transport and failed queue using Fight Common's qualified
asynchronous-command contracts. Consume [TICKET-00032](00032-TICKET.md)'s committed Workflow-ID dispatch intents;
reconcile uncertain delivery against the same original intent. Delivery is at least once. Queue placement, delivery
acknowledgement and transport keepalive never replace the application lease, durable TASK claim or semantic Commands.
Cancelled-before-delivery work is an idempotent no-op when later delivered; failed-queue replay cannot bypass recovery.

Run three supervised one-shot consumers by default, each handling at most one Workflow command before exit. Admit
at most three concurrent Workflows by default under explicit bounded installation settings. Keep consumer topology
configuration separate from revisioned authorized Workflow limits. Independent TASKs may proceed when actual Agent
commitments, branch/worktree, temporary/session/Artifact namespaces and declared shared resources do not conflict.
Use short checkout-administration locks and narrow policy resource claims, not a repository-wide execution lock.
Blocked queued work must not prevent another safe eligible Workflow from using capacity.

Use the existing execution owner's lease, custody and recovery operations; do not introduce another scheduler claim
or parallel commitment writer. The base Start, grant, exclusive TASK claim, semantic event stream and dispatch intent
remain TASK-00139's responsibility. Environment preparation, process supervision, phase transitions, immutable
handoff and quiescence remain TASK-00140/00141/00142. Identify actual low-level capacity/commitment participants and
shared fences during TASK decomposition; admission previews and post-commit events are not reservations.

Provide distinct authorized Disable Runner, Halt Runner, Disable All and Halt All operations. Disable stops new
admission while current work drains; Halt also requests safe pauses. Installation-wide gates apply to any later
Runner enrollment. Credential rotation/revocation and de-registration remain explicit security/lifecycle operations
with history, not synonyms for Halt. Expose current owner, health/freshness, queue/hold reason, attempts, consumed
limits and a concrete recovery action through safe console/application queries and existing operator guidance.

Delegate pause, cancel, resume, takeover, terminal failure, limit extension and cleanup to the same authoritative
Workflow operations. Pause retains claim/resources. Cancellation releases a claim only after complete child work
is stopped or proved absent. Takeover resumes the same Workflow and consumed limits; terminal failure/cancellation
cannot reopen it. Retain one initial process launch plus two safe resumptions per phase by default, snapshotted at
queueing. No blind retries for denied authority, unsafe state, exhausted limits, missing evidence or uncertain effects.

On graceful/abrupt restart, reconcile Workflow/claim/lease, transport, process groups, resources, Git, session,
checkpoint and external-effect evidence before dispatch. Unknown liveness holds; no duplicate launch or lost claim.
Retain failed evidence and human checkouts. The Runner has no Docker socket, broad host home or Agent-facing inbound
port; the constrained provisioner and broker remain existing owners. No remote Runner fleet, second installation
stack, sandbox implementation, new Start engine, browser start flow or post-publication cleanup is included.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Enroll and inspect the Runner | Explicit protected bootstrap/enrollment or lifecycle operation | Actual identity, capabilities, health and current availability | Committed Runner lifecycle facts; heartbeats remain observations | Stable enrolled identity and truthful ready/unavailable status |
| Dispatch admitted work | Reconcile/send original dispatch intent; invoke existing admission/lease operation | Current intent, gates, capacity, resources and original outcome | Existing committed admission/dispatch facts, not queue-derived transitions | At-least-once delivery to one authorized current consumer |
| Drain or halt capacity | Disable/Halt Runner or installation-wide gates | Current active work, gate revisions and quiescence | Committed gate changes and actual pause outcomes | Stop new admission; drain or request pause without implicit cancellation |
| Recover interrupted work | Existing Workflow reconciliation/resume/takeover commands | Original attempts, live process/resource/effect state and remaining limits | Owning confirmed recovery/hold facts | Same Workflow resumes safely or stays Needs Human |
| Diagnose or follow guidance | N/A — read-only | Safe current owner/status, versions, reasons and guide revision | N/A — inspection cannot repair or replay work | Actionable diagnostics without mutation |

## Validation and permissions

Require current intent-specific operator/system authority, authenticated Runner identity, explicit installation and
Repository scope, expected revisions and current Workflow grant/lease for every effect. Apply shared admission,
Agent-commitment and resource fences across participants; a per-TASK claim alone cannot prevent Agent double booking.
Unknown capacity, stale capability reports, revoke/regrant or configuration drift must not admit stale work.

Persist required state/audit/intent on the canonical PostgreSQL connection under ADR 0002. Network/process/provisioner
effects occur after commit with verified outcomes. Bound polling, reconciliation, consumer resources and retries.
No installation/provider/Agent credentials reach Pi children, ordinary output or evidence; report only safe manifests.
Setup changes remain explicit operator actions and are qualified directly on supported hosts.

## Acceptance and evidence

- Actual enrolled service survives restart with its identity; three one-shot consumers and bounded default capacity
  operate without processing a second Workflow in the same disposable consumer or widening host authority.
- Duplicate/lost dispatch, failed-queue replay, queued cancellation and capability loss preserve original Workflow
  results; blocked work does not monopolize safe capacity. Real concurrency proves no conflicting admission.
- Disable/drain, halt/pause and installation-wide gates remain distinct through races and restart. Recovery never
  treats a heartbeat gap as proof of quiescence or releases a claim while child work may remain active.
- Use the existing real TASK execution path and its ordinary evidence, not another proof endpoint or Runner. Owned
  application/PostgreSQL behavior tests, direct transport/supervision/host qualification and `./bin/build` pass.
  Do not add product tests of Compose, Supervisor, wrappers or configuration text.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

Approved as area 1 of EPIC-00007's remaining six-TICKET split. Applies
[WF-016](../wayfinder/tickets/WF-016-define-runner-dispatch-and-recovery.md),
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md),
[WF-023](../wayfinder/tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md)/
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Extend TICKET-00023's installation without inheriting its whole browser/Planning-cutover prerequisite chain for the
terminal milestone. TICKET-00032 keeps its accepted execution TASKs; TICKET-00064 keeps Team selection/admission
and Pending start. TICKET-00061 keeps Agent per-use authentication; TICKET-00090 supplies protected Harness sessions.
Decompose exact early operational participants without reverse-blocking them on completed execution, final Team
integration, browser views or forecasts. Existing supplier-TASK readiness holds, Board order and TASK scope remain
unchanged. No Runner enrollment, queue, gate, process, credential or operational authority is created by planning.
