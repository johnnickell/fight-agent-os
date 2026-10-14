---
id: TICKET-00032
epic: EPIC-00007
title: Start and recover TASK execution in an isolated local workspace
status: ready-for-agent
---

# Start and recover TASK execution in an isolated local workspace

## Problem statement

John needs useful autonomous delegation and code editing without giving the Engineer general host execution
powers or requiring a human click for each permitted operation. Worktrees and separate conversations alone do
not restrict files or establish safe execution of candidate code.

## Solution and boundaries

The [October 6 tool-mediated strategy](../../docs/engineering/TOOL_MEDIATED_EXECUTION.md) supersedes the earlier
Engineer-root/private-engine VM profile for this slice. Deterministic PHP owns authorization, filesystem/Git
capabilities, effect recovery and check execution. Team Lead delegates; Engineer reads/edits only assigned code
and scratch, exchanges bounded notes, and reads its TASK's canonical Review/QA reports without write permission.
Neither Agent receives bash, sudo, Docker administration, arbitrary network/command tools or mutable tool loading.

1. Authorize the TASK revision, grant, dependencies and required capabilities; atomically claim and persist dispatch.
2. Resolve an exact base, create an owned branch/worktree, register contained role-specific code/notes roots and
   protect shared Git administration. Do not repurpose a human checkout or expose sibling metadata.
3. Prepare pinned trusted Pi/tool infrastructure with separate role identities, conversations, file grants,
   provider bindings and budgets. Models do not select host paths, credentials, container policy or new tools.
4. Establish file/handoff readiness independently of check readiness. Missing test execution permits only honest
   checkpoints, never a claim of green checks, TASK completion or the production Awaiting review handoff.
5. For an authorized check request, freeze the exact source snapshot and use the installation-owned restricted
   container profile/private services. Keep candidate code, credentials, controller state and canonical Git apart.
   Collect protected results, prepare the constrained implementation commit and validate the current handoff.
6. Reconcile interrupted effects and attempts. Pause preserves state; cancellation proves quiescence; retirement
   touches only owned resources under explicit retention policy. Never silently delete failed evidence.

The complete MCP suite is not a gate for this sequence. Narrow capabilities can be integrated directly first;
MCP later exposes the same application authorization. The Team Lead may automatically request a fixed test
operation within an approved grant, but cannot run arbitrary commands or declare code safe to execute because it
read the diff. Formal independent review/QA/publication remain separate from coordination and correction.

Use explicitly approved roots for isolated source/local scratch and the Engineer's per-spoke shared notes in the
main worktree. Main source, Lead state, assignments, controller receipts, other TASKs, credential files and Git
administration are not Engineer-writable. Only the assigned TASK's canonical Review/QA directories are additionally
readable. Links in notes or reports do not expand access. Deny traversal, escaping links, unsafe file types,
excess content, stale revisions/generations and runtime-loading changes through the actual capability boundary.

Check containers execute untrusted candidate code. Qualify fixed images/entrypoints, independently selected checks,
CPU/memory/PID/time/storage/output/egress bounds, private databases/caches, retained-resource accounting and
ownership-checked stop/recovery. No host socket, privileged mode, home/credential mounts or canonical Git metadata.
Containers share their host kernel; this is not hostile multi-tenant isolation or protection from trusted-controller
compromise. Unsupported check profiles fail closed; no host-shell fallback or Agent-editable check authority.

Provider and Agent HMAC credentials stay in trusted infrastructure outside Agent file grants and candidate execution.
Bind every tool/provider operation to current Workflow, role, attempt and permissions, with bounded usage and
cancellation. Preserve package-owned enrollment/recovery, durable intent, source-publication coordination and
independent receipt authority. A prompt, copied role name, note or exit code alone grants nothing.

## Pi Durable integration direction — 2026-10-13

John requested this bounded refinement of existing Harness and execution work after reviewing Pi Durable:
prefer `@earendil-works/pi-durable` for durable conversation execution where qualification satisfies our contracts.
Evaluate it before implementing equivalent checkpoint, queued-input and reconnect machinery. Record the exact
package/source revision, supported runtime, storage choice and observed limitations in the implementing TASK;
if it cannot meet a required contract, record the specific gap before selecting an alternative. This is an
integration preference, not package adoption or proof that existing Pi extensions work unchanged.

The product goal is one installation-owned durable Agent conversation reachable from authorized terminal and
browser clients. Closing either client detaches it; it does not cancel accepted work. Reattachment resolves the
same Agent/session and committed state. The owning machine must be running to execute work; durable recovery
does not imply execution while powered off, automatic cross-machine migration or a new always-online hub.
Keep the terminal-first milestone; browser interaction arrives through its existing Planning/session owners.

PHP/PostgreSQL retain Workflow grants, claims, fencing, provisioning and phase transitions. Pi Durable may own
transcripts and internal execution checkpoints behind the Harness adapter; its internal tasks are not Planning
TASKs. Map stable conversation/submission/attempt references across that boundary. Reconcile lost acknowledgements
against the original PHP operation before retrying; a durable submission does not make external effects exactly-once.
Only demonstrably replay-safe work may rerun. Unknown effects, expired authority and uncertain liveness hold.
Stored approval answers retain their original scope and validity; they never bypass current authorization.

Qualify a single active storage owner, protected storage outside disposable worktrees, brokered tools/providers,
and explicit stop/pause/cancel behavior including owned background work. Pin executable extension versions as well
as names across restart; mutable upstream configuration must not silently replace our immutable Harness snapshots.
Clients use the same authorized operations with original request identities and current-state resynchronization;
concurrent input must preserve one bounded active turn and expose conflicts rather than duplicate execution.
Transcript access remains owner-first, and Workflow observation retains its exclusion of private Pi transcripts.

References inspected on 2026-10-13: [Earendil's introduction](https://earendil.com/posts/pi-durable/), the
[experimental package contract](https://github.com/earendil-works/pi/blob/main/packages/durable/README.md), and
[IndyDevDan's playground](https://github.com/disler/durable-pi). The playground supplies browser/terminal design
examples, not our authentication, sandbox or Workflow policy. The linked
[video](https://www.youtube.com/watch?v=DNDYYHsypZg) motivated this request; its contents were not independently
retrieved. These upstream references are mutable; implementation must qualify pinned code.

TASK-00141 owns the execution integration and TASK-00142 its recovery proof. TASK-00250 consumes the protected
session primitives; TASK-00266 consumes the browser Planning owners. Their existing unmapped prerequisites remain
explicit. TASK-00164's separately approved Pi 1.1.0 local compaction scope is preserved. No new TASK, dependency,
priority, Wayfinder session, runtime launch or database Planning cutover follows from this amendment.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Start approved work | Start coordinated work; prepare scoped workspace | Eligibility, grant, file/check capabilities | Preparation requested/verified or failed | Claimed TASK, owned worktree/notes and scoped role processes |
| Delegate and revise | Assign bounded SUBTASK; submit handoff | Current assignment, exact source snapshot, role notes | Authorized phase facts after validation | Scoped source/notes changes, fenced handoff and controlled correction |
| Run permitted checks | Request approved project checks | Check readiness, subject and protected results | Check requested/completed or failed | Restricted disposable execution, bounded logs/services and receipts |
| Resume interrupted preparation | Reconcile owned execution | Persisted intents, resource/process ownership, checkpoints | Reconciliation/continuation recorded | Reuse verified resources or pause uncertainty |
| Pause/cancel/retire | Explicit lifecycle operations | Quiescence, ownership, retention | Paused/cancelled/retired facts | Stop owned processes; preserve or remove only authorized resources |

Names describe semantic operations; exact PHP classes and schemas belong to implementation, not speculative scaffolding.

## Validation and permissions

Domain owns grants, claims, transitions and resource ownership. Application coordinates typed capabilities;
Adapter implements filesystem, Git, processes, check containers and Pi integration. All protected entry paths
share application policy regardless of MCP exposure. Record redacted evidence; source snapshots and permission
checks must survive correction, retries and takeover without stale writes or stale check-result acceptance.

This TICKET does not authorize a production launch or claim that the prototype implements managed identities.
The explicitly approved disposable experiment is limited evidence, not a second production Runner.

## Acceptance and evidence

- Scoped file tools permit assigned source/scratch/shared notes and TASK-only Review/QA reads; deny protected,
  unrelated and cross-role access, unsafe paths/links and runtime configuration/tool-loading bypasses.
- Separate role sessions exchange bounded assignments, questions and handoffs; Lead state and controller receipts
  are not writable by Engineer. Exact source snapshots, generations and command identities reject stale delivery.
- Two concurrent TASKs cannot change each other's source, notes, database, cache, temporary files or artifacts;
  qualify actual file grants and restricted execution boundaries, not merely directory names or prompts.
- Fixed check operations enforce current grants, source identity, resource/egress limits and protected collection.
  Routine preauthorized testing is automatic; code review cannot substitute for containment or missing evidence.
- Application tests prove authorization, idempotency, stale-owner fencing, state transitions and recovery. Owning
  tools directly qualify file/process/container mechanisms; no product-suite tests of configuration or wrappers.
- Interrupt after claim, worktree/notes creation, check-container creation, readiness, role spawn and handoff;
  reconcile without duplicate effects, lost claims or fabricated success. Cancellation proves owned-workload stop.
- Unsupported capabilities stay disabled; candidate checks unavailable means no complete implementation handoff.
  Evidence binds exact source/base/head, role/attempt, runtime/check profile, required results and artifacts.
- One real TASK reaches Awaiting review through the production path with all required checks; disposable notes,
  scripted transport or a prototype's passing outcomes cannot satisfy this production proof.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00138](../tasks/00138-TASK.md) | Define and qualify tool-mediated execution boundaries | done |
| [TASK-00139](../tasks/00139-TASK.md) | Start and inspect an authorized TASK from the terminal | ready-for-agent |
| [TASK-00140](../tasks/00140-TASK.md) | Prepare and recover owned worktrees containers and test databases | ready-for-agent |
| [TASK-00141](../tasks/00141-TASK.md) | Delegate one TASK through Team Lead and Software Engineer in Pi | ready-for-agent |
| [TASK-00142](../tasks/00142-TASK.md) | Prove parallel TASK isolation and interrupted execution recovery | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John authorized terminal-first decomposition on September 26 and Engineer-root/private-engine VM qualification
on September 27. Those historical decisions and [VM findings](../../docs/engineering/DOCKER_SANDBOX_QUALIFICATION.md)
remain intact. October 6 replaces that first-profile choice after unresolved host-enforcement limitations; it does
not claim the old profile passed or erase missing authority/independent-evidence proof.

The approved file-only prototype demonstrated two live role conversations, notes, revision and frozen source
identity without executing candidate code. Its evidence, limitations, R1–R3 remapping and exact ownership across
TASK-00138–00142 are in the [current contract](../../docs/engineering/TOOL_MEDIATED_EXECUTION.md).
The full suite of MCP tools is not required before the first safe capability, and the terminal path still does
not depend on browser delivery or database Planning cutover. TASK-00138 remains incomplete; production start,
recovery, check execution and delegation are not implemented or accepted by this amendment.

TICKET-00064's accepted TASK-00261–00264 now supply Team/Agent eligibility, fair whole-TASK intake, atomic
Team admission and same-Team phase constraints. TASK-00139 retains the base Start and immutable Markdown
eligibility capability; TASK-00263 extends that same owner, never creates another claim engine or reverse
prerequisite. TASK-00141 explicitly consumes TASK-00264 for its real Team Lead/Software Engineer handoff.
Lower-level commitment/resource/base/independence and phase/session mappings remain `needs-info`, not supplied
by these consumers or by a completed role journey. No Planning database cutover is required by core selection.
TASK-00265 later extends owned pending-intent/withdrawal and final terminal/browser integration, without changing
the first Awaiting review milestone, authored order or active work. No live launch was enabled by planning.

### Remaining EPIC-00007 requirement ownership

John approved six additional TICKETs on the existing isolated planning branch without changing TASK-00138–00142.
This TICKET remains the core Start, grant/claim/dispatch-intent, sandbox, preparation, Workflow/phase/process custody,
Team Lead/Software Engineer handoff and recovery owner through Awaiting review. Apply the full accepted EPIC
contracts, including event-store/Command authority, exact idempotency, current grant/lease fences, immutable subject
and safe quiescence; this note creates no second implementation slice or new status/blocker.

[TICKET-00093](00093-TICKET.md) owns enrolled Runner operation, transport/capacity and drain/halt integration over
those existing contracts. [TICKET-00094](00094-TICKET.md) supplies exact evidence/private Artifacts and their safe
retention; this TICKET still validates the complete handoff and transitions to Awaiting review.
[TICKET-00095](00095-TICKET.md) provides the separately authorized web research seam.
[TICKET-00096](00096-TICKET.md), [TICKET-00097](00097-TICKET.md) and [TICKET-00098](00098-TICKET.md) own safe
history/projections, sessions browser presentation and honest usage/forecasting respectively. None replaces
execution authority or adds a browser/forecast/Planning-cutover blocker to the first terminal milestone.

Map exact early suppliers during later TASK decomposition rather than depending on completed TICKETs or final
Team/role journeys. Existing Team/Agent, Harness and shared-fence readiness holds remain; these requirement records
are not runtime evidence. The EPIC records approval/verification and the next map-wide planning handoff.
