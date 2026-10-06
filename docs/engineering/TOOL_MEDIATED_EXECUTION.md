# Tool-mediated execution boundary

## Approved strategy amendment — 2026-10-06

John approved replacing the first Engineer-root/private-Docker VM strategy with a narrower, tool-mediated
Engineer, then explicitly authorized updating TASK-00138 and its parent contracts. This is the current boundary
for [EPIC-00007](../../planning/epics/00007-EPIC.md), [TICKET-00032](../../planning/tickets/00032-TICKET.md) and
[TASK-00138](../../planning/tasks/00138-TASK.md). It supersedes their September 27 VM-specific requirements for
this slice, not the historical observations or independent review verdicts.

The Engineer reads and proposes source changes; the application owns filesystem permissions, Git administration,
check execution and authoritative completion. Docker containers are a candidate boundary for executing untrusted
candidate code, not a replacement name for a fully qualified sandbox. Worktrees organize changes, not security.
No Engineer shell, sudo, private engine, host Docker socket or arbitrary process/network capability is required
or permitted by this profile. A future unrestricted Engineer profile needs a separate approved qualification.

## Role and file contract

| Actor | Read | Write / request | Denied |
|---|---|---|---|
| Team Lead | Assigned context, candidate source, Engineer handoffs, current TASK Review/QA | Bounded assignments, own state/notes, typed check and handoff requests | Implementation edits, self-granted permissions, formal review acceptance, arbitrary host commands |
| Software Engineer | Assigned source/context, assignment, own notes, canonical Review/QA for its TASK | Scoped source edits and own local/shared scratch; submit questions and handoff | Shell/exec, Docker/Git administration, unrelated files, Lead state, reports/receipts, tool/config installation |
| Trusted application | Authorized source/role/resource state | Resolve roots and grants, manage Git, fence writes, freeze snapshots, run approved checks, record receipts and transitions | Treating model messages, filenames or claimed successes as authorization |

Engineer write grants have two explicit locations: assigned source scope (and assigned local scratch) in the
isolated worktree, and only its allocated notes subtree in the main worktree. The main worktree is not a general
write root. Bind roots to registered repository, TASK, Workflow/run, role, attempt and current generation; the
model supplies neither arbitrary host roots nor its own identity/permission context.

Proposed task scratch convention:

```text
<main>/.runs/tasks/<TASK-ID>/runs/<run-id>/
  lead/                         # Lead-authored plan/state/result
  assignments/<spoke-id>/        # Lead-authored, Engineer-readable
  spokes/<spoke-id>/             # Engineer-owned shared notes/handoff
  receipts/                     # Controller-owned evidence
<main>/.runs/reviews/<TASK-ID>/   # Engineer read-only, independent review owner writes
<main>/.runs/qa/<TASK-ID>/        # Engineer read-only, independent QA owner writes
<isolated>/.runs/tasks/<TASK-ID>/runs/<run-id>/<spoke-id>/
```

A spoke is a delegated SUBTASK, not a new TICKET. Do not expose other spokes' raw notes by default; the Lead
carries forward a compact state brief. Missing Review/QA means unavailable evidence, not acceptance. A path or
symlink inside a readable report cannot widen the grant. Existing evidence locations are not moved by this
amendment; the separately requested cleanup chore owns archival and reference repair.

Enforce read/write restrictions in tools, not prompts: contained canonical roots, safe file types, link/race
handling, size/count budgets, expected revisions and current attempt fencing. Protect Git metadata, runtime-loaded
agent configuration, extensions, credentials, controller code, authoritative state and receipts. Content such as
AGENTS.md or tool configuration may be proposed as inert changes only; writable repository content must not load
executable behavior or expand the session's tool set. Explicit resource discovery/tool allowlists are required.

## Controller and use-case ownership

Retain ADR 0001: Domain owns grants, claims, role/phase eligibility and resource ownership; Application coordinates
approved operations and durable intents; Adapter owns file/Git/container/Pi mechanisms. Use Fight Access Control
public identity/authorization contracts directly. MCP is a transport exposing capabilities, not permission policy.
No second Python/TypeScript production Runner or new authorization model is adopted from disposable prototypes.

Useful operations are scoped file reads/edits, bounded delegation/handoff, fixed check requests and constrained
commit preparation. They accept source identity and expected revisions, not shell commands, arbitrary container
flags, URLs or repository-authored host execution policy. Reuse cohesive capabilities where they express these
use cases; do not introduce empty interfaces or package-renaming wrappers.

Team Lead may inspect code and ask for correction during implementation. This is coordination, not the independent
Senior Engineer review/QA phase, security certification or permission to execute code on the host. Agents retain
separate identities, direct Permissions, conversations and grants. Credentials belong to trusted infrastructure
outside Agent-readable/writable roots and candidate execution; role separation is not supplied by a prompt.

## Staged readiness and automatic checks

1. **Disposable handoff experiment:** controlled file access, separate conversations, notes and frozen snapshots;
   no candidate execution. Explicitly authorized limited evidence, not a production Workflow or completed TASK.
2. **Managed edit/handoff readiness:** real operator/Agent authorization, immutable source/context, protected tool
   runtime, grants, fencing, durable intent, budgets, cancellation and recovery. Can report preparation or a
   checkpoint with checks unavailable; it cannot claim implementation acceptance or Awaiting review.
3. **Check readiness:** qualify and implement the fixed check capability below. The Team Lead requests it within
   the preapproved grant. Deterministic application authorization admits routine runs without a human click each
   time; scope/permission expansion, uncertainty, publication and deployment remain separately controlled.
4. **Awaiting review:** required checks actually completed on the exact candidate, protected results reconciled,
   constrained implementation commit prepared and current authorized handoff accepted by PHP. Prototype notes,
   missing checks and model-generated "passed" claims cannot advance this transition.

The complete MCP suite is not a prerequisite for file/handoff qualification or one fixed check capability. Tests
execute arbitrary candidate code even when no shell tool is exposed. Never let the Lead's inspection substitute
for execution isolation. Keep the controller/provider credentials and canonical Git administration outside check
containers. Pass only a frozen candidate snapshot, with private disposable services/data, and bound CPU, memory,
PIDs, writable storage, output, time, egress, retained artifacts and aggregate admission. Protect required-check
selection and receipt collection from candidate scripts/configuration; an exit zero from a rewritten test runner
is not independently authoritative proof. No host Docker socket, privileged container or host-home mount.

A Docker boundary shares its container host's kernel. This selected strategy does not promise hostile multi-tenant
isolation or immunity to same-user host/controller compromise. It still requires measured supported controls and
honest limits for the actual checker/profile; unqualified execution stays unavailable. macOS VM-control-plane
hard caps investigated for the abandoned root-VM strategy are not prerequisites for a read/write-only experiment.
Do not carry those old blockers forward unchanged or silently claim container execution is qualified by this move.

## Git, messages and recovery

Trusted application operations own branch/worktree lifecycle and commits. Linked worktrees may share canonical
Git administration provided Agents cannot access it through tools and candidate execution never mounts it. No
host checkout, build wrapper, hook, filter, submodule or package script runs because an Agent edited a file.
Private-VM Git export/bundle quarantine is no longer a required path for this profile; any future untrusted import
still needs its own bounded validation.

Bind an assignment/handoff to TASK/context, run/Workflow, role, attempt, generation and exact source snapshot.
An uncommitted worktree needs an immutable manifest, not just its base OID. Fence/freeze writes while capturing and
checking it; later edits invalidate earlier evidence. Lead state, Engineer notes and controller receipts have
separate owners. A file called result.md existing does not complete a phase. Reject stale/cross-task messages;
unknown delivery/effect outcomes require reconciliation, not duplicate launches or fabricated success.

Preserve PostgreSQL authority, shared transaction/dispatch-intent ordering, lease fencing, source-publication
coordination, pause/cancel/quiescence and ownership-checked cleanup. Provider requests retain explicit role/model/
usage limits, no global fallback and bounded cancellation. These requirements do not disappear with the shell.

## Qualification evidence and limits

The approved October 6 prototype used Pi SDK 1.0.2 and two in-memory `openai-codex/gpt-6-astra` sessions, low
reasoning, with only custom read/write tools. Forty-one direct file-capability outcomes and a zero-model SDK
preflight passed before the live five-phase assignment/edit/correction/handoff loop (19 model requests).
Engineer read the actual TASK review, reported missing QA and wrote only assigned source/local/shared notes.
Controller snapshots matched final source; sessions were disposed and writes frozen. No candidate code/tests ran.
The two sessions shared a trusted host process; this is tool/context separation, not OS-level role isolation.
Existing Pi login was used by the controller, not production broker/enrollment qualification.

Local evidence (ignored, not shipped with a clone):

- In the authorized qualification worktree: `.runs/prototypes/TASK-00138/lead-engineer-handoff/REPORT.md` and
  run `1fe64746-1290-465f-88d3-1e4ef0041b43/` with scripts, direct checks, SDK/live receipts and digests.
- In the main checkout: `.runs/prototypes/TASK-00138/lead-engineer-handoff/result.md` and
  `.runs/tasks/TASK-00138/runs/1fe64746-1290-465f-88d3-1e4ef0041b43/` with shared notes and controller manifests.
- Earlier VM, external-quarantine and host-primitive evidence remains in the historical qualification documents;
  none is a fresh check-runtime or production recovery receipt.

## TASK ownership and review trace

| Owner | Exit responsibility |
|---|---|
| TASK-00138 | Settle revised authority/file/message/check contracts; qualify minimum file/handoff primitives and select a supportable restricted-check mechanism with direct scoped evidence; record limits and independent review disposition. Not a production implementation. |
| TASK-00139 | Implement authorized start/inspect, immutable eligibility, durable grants/claims/intents and staged readiness; unsupported capabilities remain disabled. |
| TASK-00140 | Implement owned worktree/scratch/file capabilities, restricted check runtime/services, budgets, protected results, Git preparation and effect recovery; qualify the actual composed checker before enabling it. |
| TASK-00141 | Implement separate Pi roles, bounded messages/correction, automatic permitted check requests and exact-commit handoff through the production path; one real TASK reaches Awaiting review only with required evidence. |
| TASK-00142 | Qualify parallel production Workflows, cross-task/role denial, stale messages/snapshots, interruption, cancellation and retained-resource recovery. |

Canonical review remains `revise` for af1fdbc; this amendment does not edit or independently close its findings:

- **R1 retained:** package-backed operator/Agent recovery, permission catalog, protected credentials and source
  authority remain required. Recheck released capabilities at adoption; historical package findings are not a
  statement about today's upstream source. Installed dependencies are unchanged by this amendment.
- **R2 remapped, open:** replace root-VM bootstrap/aggregate-host proof with protected file/tool runtime, bounded
  candidate-check profile/accounting and provider authority. Full root-VM host-helper/I/O qualification is no
  longer this profile's target; the actual container/control-plane exposure still needs measured limits.
- **R3 remapped, open:** replace guest-root escape/host-MCP/bundle-export proof with actual file grants, runtime
  loading denial, immutable message/snapshot binding, canonical Git exclusion and independently collected checks.
  The prototype is partial evidence, not production proof or an independent verdict.

Independent review must evaluate the new criteria and explicit risk tradeoff. Do not transfer old checked boxes
or reviewer acceptance to newly defined requirements. Required checks, production authorization and recovery may
not be waived merely to call the prototype a finished implementation.
