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
| [TASK-00368](../tasks/00368-TASK.md) | Enroll and inspect the installation Runner | ready-for-agent |
| [TASK-00369](../tasks/00369-TASK.md) | Admit work against shared capacity and commitments | ready-for-agent |
| [TASK-00370](../tasks/00370-TASK.md) | Deliver and reconcile original Workflow dispatch intents | ready-for-agent |
| [TASK-00371](../tasks/00371-TASK.md) | Execute bounded work through one-shot consumers | ready-for-agent |
| [TASK-00372](../tasks/00372-TASK.md) | Drain or halt Runner admission explicitly | ready-for-agent |
| [TASK-00373](../tasks/00373-TASK.md) | Reconcile Runner restart and expose safe recovery | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

### Approved TASK decomposition and current handoff

The maintainer approved six bounded implementation slices in the existing isolated planning worktree. Each owns
its application behavior, actual operational qualification and guidance; no additional certification TASK is added.

| Slice | Accepted owner | Delivered boundary |
|---|---|---|
| A | [TASK-00368](../tasks/00368-TASK.md) | Stable protected Runner enrollment/lifecycle and truthful capability inspection |
| B | [TASK-00369](../tasks/00369-TASK.md) | Atomic owning capacity, Agent-commitment and declared-resource admission participants |
| C | [TASK-00370](../tasks/00370-TASK.md) | Dedicated at-least-once Messenger dispatch and original-intent reconciliation |
| D | [TASK-00371](../tasks/00371-TASK.md) | Actual bounded one-shot consumers through the existing production execution path |
| E | [TASK-00372](../tasks/00372-TASK.md) | Distinct Runner/installation Disable and Halt operations with persistent gates |
| F | [TASK-00373](../tasks/00373-TASK.md) | Runner restart reconciliation and safe existing Workflow recovery controls |

TASK-00368 consumes TASK-00138/00321. TASK-00369 consumes TASK-00368/00139/00253. TASK-00370 consumes only
TASK-00139, allowing dispatch to wait without completed Runner or role use. TASK-00371 consumes TASK-00369/00370/
00141; TASK-00372 consumes TASK-00369/00141 independently of completed consumers; TASK-00373 consumes
TASK-00371/00372. All six are `ready-for-agent` behind unfinished dependencies with blank orders; this is not
operational readiness or execution priority.

TASK-00139–00142 retain Start, preparation, actual role execution and recovery ownership, scope and order.
This Runner split left their blockers unchanged; the subsequent TICKET-00094 mapping below adds early evidence suppliers.
The split adds no second installation, claim/commitment writer, sandbox/provisioner/broker or phase engine. Runner-only
installation work extends the existing terminal path without importing TASK-00072–00075's complete browser chain.
Consumer topology remains separate from revisioned authorized limits; defaults are three one-shot consumers and
three concurrent Workflows. Process resumptions retain the original one-plus-two allowance rather than resetting it.

TASK-00254/00261/00263 gain TASK-00369's early applicable Workflow commitment/capacity/resource contract; TASK-00264
consumes it transitively. Their `needs-info` remains for actual manual-session/Workflow participant composition,
creation/current authority, configured-base inclusion, contribution/fresh-session and host holds as applicable.
No Team selector, finished role or consumer loop reverse-blocks these primitives. Mapping is not live qualification.

TASK-00350 and TASK-00364 remain `needs-info`: stable Runner enrollment and capacity do not implement protected
session host/broker/storage enforcement or prototype execution. The missing narrow contracts remain under existing
execution owners. Do not use completed TASK-00371/00373, TASK-00141 or a fabricated manual Workflow as substitutes,
or expand this TICKET's explicit sandbox/provisioner exclusion. TASK-00087 stores evidence, not executable prototypes.
TICKET-00095 separately owns qualified screened external research. TICKET-00094 now maps TASK-00374–00380's
Artifact/contribution/subject/receipt/read/retention/redaction delivery. TASK-00373 gains TASK-00377's early receipt/
subject chain; TASK-00140 gains TASK-00374 and TASK-00141 gains TASK-00377 without changing their ownership/order.
Unknown evidence/holds prevent unsafe recovery or cleanup, not silent deletion. Full lifecycle consumers never
reverse-block early evidence capture; host/prototype gaps remain.

TICKET-00095 now maps TASK-00381–00384's screened web research without making network use a requirement for every
execution TASK or resolving protected host/prototype holds.
TICKET-00096 now maps TASK-00385–00389's safe history/live consumers; Runner/process observations remain distinct
from semantic Commands and existing recovery still works without optional telemetry. No producer gains a history blocker.
TICKET-00097 now maps TASK-00394's consuming browser controls, retaining the same semantic operations, original
outcomes and quiescence checks without adding browser prerequisites to Runner delivery.
This decomposition is complete, not runtime delivery. Continue `/skill:next` for Board-based selection after TICKET-00109's approved final split. Preserve terminal-first Board priority and Markdown Planning
authority until separately approved verified cutover. Planning authorizes no enrollment, credential operation,
queue, process, control, provider call, recovery, cleanup, implementation or publication.

### Planning verification

Prepared in `.runs/worktrees/ticket-00083-to-tasks` on `feature/ticket-00083-to-tasks`, preserving prior approved
uncommitted planning work. Before allocation the live TASK ceiling was TASK-00367 and the archive contained no
TASK records. TASK-00368–00373 metadata and dependencies match the approved six-slice proposal; orders/pr are blank.

`./bin/planning-check --write` and `./bin/planning-check` passed with 500 records, 408 active and current generated
views, reporting no invalid links or dependency cycles. Tracked `git diff --check` and all 65 new/untracked TASK
whitespace checks passed, including these six. Explicit checks confirmed independent consumer and host/prototype
`needs-info` holds and unchanged TASK-00138–00142 records against this worktree's HEAD. Board priority and active
TASK-00138 remained unchanged; that historical handoff advanced to TICKET-00094.

These are planning receipts, not Runner, provider, sandbox, admission, recovery or browser qualification. Full
`./bin/build` was not run and remains required before commit/publication. Changes remain uncommitted and independent
review/publication are separate. No implementation, enrollment, credential operation, service/queue/process,
provider call, recovery, cleanup, cutover, commit, push, PR or merge occurred. Unrelated main-checkout work remains
untouched, not claimed clean. Earlier receipts are historical, not fresh application evidence for these capabilities.

### Original requirement approval and supplier handoff

The earlier allocation requests below are superseded only where precisely mapped above; operational holds persist.

Approved as area 1 of EPIC-00007's remaining six-TICKET split. Applies
[WF-016](../wayfinder/tickets/WF-016-define-runner-dispatch-and-recovery.md),
[WF-020](../wayfinder/tickets/WF-020-define-developer-onboarding-and-operator-guidance.md),
[WF-023](../wayfinder/tickets/WF-023-define-local-runtime-and-shared-ingress-topology.md) and
[ADR 0001](../adr/0001-application-ownership-and-orchestration.md)/
[ADR 0002](../adr/0002-postgresql-consistency-and-durable-effects.md).

Extend TICKET-00023's installation without inheriting its whole browser/Planning-cutover prerequisite chain for the
terminal milestone. TICKET-00032 keeps its accepted execution TASKs; TICKET-00064 keeps Team selection/admission
and Pending start. TICKET-00061 keeps Agent per-use authentication; TICKET-00090 supplies protected Harness sessions.
TICKET-00090 now maps TASK-00350's protected runtime/storage/acting-window adapter but retains `needs-info` for
precise reusable operational host/broker participants under existing TICKET-00032 and relevant Runner owners.
TASK-00138's qualification alone is not implementation. Map the narrow supported contract independently of
completed TASK-00141, whole Runner delivery or a fabricated Workflow for manual use; do not relocate sandbox or
provisioner ownership into conversations. TASK-00351 consumes this adapter and supplies later provider integration.
TICKET-00092 now maps TASK-00364's browser AFK preparation consumer, with a separate `needs-info` hold for exact
protected prototype-execution participants. Identify the supported narrow contract under existing execution/host
owners; TASK-00087 evidence storage does not run prototypes. This handoff does not expand Runner scope, invent a
manual TASK Workflow or authorize an unsandboxed fallback. Unsupported participants stay held, not assumed supplied.
TICKET-00109 now maps TASK-00441–00444. Protected fetch/custody, conditional Git/physical-exclusion and actual
scheduled actor/delegation/runtime mappings remain distinct holds. Enrollment/resource facts do not qualify them;
no second registry, implicit administrator, reverse terminal dependency or existing metadata change follows.

TICKET-00108 now maps TASK-00436–00440's human instruction-publication flow. Admission/shared-custody/protected
identity, local Git physical operations and authenticated Git/PR invocation remain distinct mapping holds under
existing execution/source-host owners. Enrollment and Workflow resource contracts alone do not qualify them.
Safe return releases only proved releasable custody; no second registry, fabricated Workflow, reverse dependency
or existing TASK metadata/priority change follows.

TICKET-00105 now maps TASK-00430/00431's bounded instruction queries and viewer. TASK-00430 retains the exact
protected human-authorized read/current-checkout-custody supplier gap under existing TICKET-00032/00093 owners.
TASK-00368 enrollment and TASK-00369 Workflow resource contracts do not alone qualify that route. No supplier ID,
fabricated Workflow/Agent, extra ownership registry, scope expansion or reverse terminal blocker is added here.
TICKET-00106 now maps TASK-00432/00433's exact Save/Delete/reconciliation and form. TASK-00432 retains a separate
human-authorized protected mutation/custody participant gap and supported physical conditional-write/delete
qualification under these execution/Runner owners. Enrollment, human reads and Workflow resource metadata do not
supply those guarantees. No fabricated Workflow/Agent, second custody registry or reverse blocker is introduced.
Decompose exact early operational participants without reverse-blocking them on completed execution, final Team
integration, browser views or forecasts. Existing supplier-TASK readiness holds, Board order and TASK scope remain
unchanged. No Runner enrollment, queue, gate, process, credential or operational authority is created by planning.
