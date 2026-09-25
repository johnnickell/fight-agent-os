# Select the end-to-end proof and EPIC handoff

**Labels:** `wayfinder:task`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-011](WF-011-prove-markdown-migration-and-authority-switch.md), [WF-014](WF-014-define-skill-trust-and-harness-distribution.md), [WF-015](WF-015-define-coordinated-task-authority-protocol.md), [WF-016](WF-016-define-runner-dispatch-and-recovery.md), [WF-017](WF-017-define-execution-history-and-event-authority.md), [WF-018](WF-018-prototype-sessions-first-browser-experience.md), [WF-020](WF-020-define-developer-onboarding-and-operator-guidance.md), [WF-021](WF-021-define-new-project-creation-and-registration.md), [WF-022](WF-022-define-browser-instruction-inspection-and-assisted-editing.md), [WF-023](WF-023-define-local-runtime-and-shared-ingress-topology.md)

## Question

What is the smallest useful end-to-end proof, and what dependency-ordered EPIC grill sequence should carry the accepted umbrella decisions forward without competing with EPIC-00002 through EPIC-00004?

## Must decide

- A thin proof that crosses real boundaries—registered repository, resolved context, authoritative application operation, durable job/session, authorized local runner, one bounded agent phase, stored evidence/events, and browser observation—without prematurely automating the full lifecycle.
- Whether the proof uses existing Markdown authority or requires a disposable database planning slice; it must not disguise a projection as database authority or trigger cutover.
- Explicit proof exclusions, disposable versus production components, success/failure evidence, security controls, cleanup ownership, and human stop points.
- Which existing accepted EPIC/TICKET/TASK dependencies must land first and which can proceed independently.
- Proposed future EPIC boundaries, ordering, child-map handoffs, and grill briefs for repository/planning authority, harness/skills, coordination/runners, browser observability, developer onboarding, new-project creation, and browser-assisted instruction editing.
- A first future decision or grill frontier after this map closes; no implementation TASK may be selected from this handoff itself.

## Resolution boundary

This decision produces a sequencing and EPIC-planning brief only. It may recommend one or more grill sessions after human approval. It must not write an EPIC, decompose TICKETs/TASKs, create branches, implement the proof, start agents, migrate planning, or publish work.

## Preferences required

John must choose the proof's user-visible value, acceptable disposable infrastructure, and which existing foundation work may be treated as a prerequisite rather than included. The recommendation should prove one repository and one bounded workflow path before multi-repository scheduling or complete automated work/review/land orchestration.

## Resolution

### Build and use the first production slice

Do not create a disposable browser, Runner, repository-analysis, or Workflow proof. Build the production capability
and use it on a real Fight Agent OS TASK. Its first end-to-end evidence is the ordinary durable evidence from that
real use, not a separate proof endpoint, Workflow type, report, architecture, fixture suite, or certification
package.

The first coordinated slice begins only after database-authoritative Planning is available:

1. an authorized human selects an eligible TASK and invokes **Start coordinated work**;
2. the application atomically revalidates eligibility, claims the TASK, and creates its durable Workflow;
3. Coordinator validates the grant, context and Harness snapshots, checkout, limits, and current authority;
4. Coordinator dispatches one bounded Builder phase in a Workflow-owned isolated worktree;
5. Builder implements the TASK, runs its required checks, and creates an implementation commit;
6. Builder submits the commit-specific durable handoff accepted by WF-015; and
7. the Dashboard presents current state, activity, changes, evidence, checks, usage, interruptions, recovery, and
   the resulting **Awaiting review** state.

This is the beginning of the accepted production state machine, not a recurring step before coordination. Planner
owns authorized Wayfinder and EPIC → TICKET → TASK Planning. Coordinator owns the transition from an approved TASK
to its execution Workflow. Builder owns the detailed implementation approach and source changes within approved
scope. Explorer remains available for genuine bounded research or design uncertainty but is not a mandatory
preflight and does not create an execution plan for Coordinator.

The first slice stops durably at **Awaiting review**. Until automated review lands, an authorized human may inspect,
pause, cancel, resume, or take over according to the existing Workflow and cleanup boundaries while preserving
its branch, worktree, claim, sessions, and evidence. The next coordinated EPIC extends this same Workflow through
Reviewer and Publisher; it does not replace the first slice.

### Existing prerequisites and implementation order

Do not duplicate or bypass the accepted foundation:

- reconcile and formally close EPIC-00002 and its completed child records without recreating its project-local
  Skill work;
- complete EPIC-00003 so PostgreSQL, durable effects, versioned APIs, React, authorization projection, and the
  owned-code gate are production foundations; and
- complete EPIC-00004 so a real authenticated human and server-enforced Permissions control the application
  shell and authority-bearing browser operations under its integrated security boundary.

Future EPIC planning may proceed while these records are implemented, but implementation must follow the Board,
preserve accepted dependencies, and avoid competing changes to the same foundation. Do not introduce a temporary
unauthenticated Workflow page or alternate persistence, API, or client stack.

Keep verification practical. Add focused automated tests for important owned invariants and failure boundaries,
including authorization, exclusive claims, state transitions, idempotency, stale handoffs, and recovery. Exercise
the integrated path manually on real work and retain its ordinary operational evidence. Do not add ceremony tests
for browsers, frameworks, documentation, setup wrappers, configuration, package-manager behavior, queues, or the
mere existence of the feature; do not reproduce one manual journey across many automated layers. Report tested,
manually observed, and unverified behavior separately and honestly.

Production components remain production components. Scratch captures or diagnostics used during development may
remain disposable under `.runs/`, but the accepted vertical slice must not depend on a throwaway implementation.
Normal Workflow interruption, cancellation, ownership-proven cleanup, secret handling, sandbox, credential,
evidence, and human-stop controls remain those settled by WF-014 through WF-017 and WF-023.

### Dynamic completion forecast

V1 coordinated Workflows show an updating estimated remaining-time range rather than a precise countdown. Display
its confidence, last update, and a concise explanation when a material event changes it. The forecast may use the
current phase and queue state, attempts and revision allowance, observed activity, check failures and retries,
comparable historical phase durations, and broad configured defaults while real history is sparse.

The range may increase as facts change. Show **Waiting for human**, **Paused**, or **Unable to estimate** when a
completion forecast would be dishonest. Distinguish estimated active work from queue and human-wait time, and
never treat an Agent's self-reported guess as measured fact. Retain observed outcomes needed to calibrate later
forecasts and make actual versus predicted duration inspectable without turning the estimate into Workflow
authority.

The Coordinator → Builder slice estimates only through **Awaiting review**. Once Reviewer and Publisher exist, the
same projection estimates through PR handoff. Exact forecasting method, cohorts, defaults, confidence thresholds,
and copy remain downstream requirements and implementation choices; they do not require another Wayfinder map.

### Dependency-ordered EPIC handoffs

Each later grill consumes the closed WF decisions, proposes one assembled EPIC, and asks only about genuinely new
product choices. It must not repeat the Wayfinder interviews. The default handoff sequence is as follows.

#### 1. Operate a registered Planning workspace

This is the first EPIC-producing grill frontier after this map closes. An authenticated user can follow guided
local installation, register an existing repository and designated checkout, resolve its context, import and
explicitly cut over its Markdown records to PostgreSQL-authoritative Planning, and use a minimal browser workspace
to inspect and operate the hierarchy, executable queue, and current context.

Package WF-008 through WF-012 with the applicable WF-020 and WF-023 foundation. Exclude coding Agents, coordinated
execution, new-project creation, and instruction editing. The Fight Agent OS repository becomes the first real
consumer through the approved migration rather than a disposable database slice.

#### 2. Deliver provider-configurable browser Planning Agents and trusted Harness

Deliver the versioned Harness package and snapshots, managed Agent identities and profiles, trusted and custom
Workspace Skills, and browser Planner conversations for Wayfinder and EPIC → TICKET → TASK proposal and
acceptance. Include the reusable instruction-writing Skill adapted with attribution from Matt Pocock's
MIT-licensed `writing-for-agents`. Do not include coding execution yet.

Initially verify two provider paths for browser Planning Agents:

- OpenRouter through an installation-held API key and explicit allowed-model catalog; and
- a local Codex subscription through Pi's authenticated `openai-codex` provider.

Neither provider is mandatory when the other is configured. The browser never receives credentials. Installation
administrators configure available connections and allowed models; profiles select defaults, and authorized users
may select another allowed option at conversation start. Snapshot provider, model, profile, and Harness revision,
and record available usage and cost facts without inventing missing pricing.

Do not silently fail over or replace the provider inside a conversation. An explicit provider change creates a
linked continuation so changed behavior, credentials, cost, and context remain visible. Keep local Codex in the
WF-020 Pi/credential-store boundary and OpenRouter in protected application configuration. Claude and other
providers may later use the same provider capability without changing Planning authority or conversation
ownership.

#### 3. Coordinate one TASK through implementation

Deliver Runner enrollment and approved roots, Workflow/event/evidence/Artifact persistence, Coordinator claim and
Builder dispatch, isolated implementation, commit-specific handoff, sessions-first Dashboard observation, and
the dynamic completion forecast. Use it on one real TASK and stop durably at **Awaiting review**.

#### 4. Complete independent review and PR publication

Extend the same Workflow with independent Reviewer, bounded Builder revisions, commit-specific acceptance,
Publisher, push and PR creation, human merge handoff, and safe post-merge reconciliation.

#### 5. Create and register new projects

Deliver managed and Workspace custom starter catalogs, deterministic local Git project generation, optional
GitHub publication, truthful recovery, repository/checkout registration, and selected development profiles under
WF-021.

#### 6. Edit repository instructions

Deliver the focused Pi-native root instruction editor, browser assistance through the versioned writing Skill,
human-authorized expected-digest creation/update/deletion, separate PR publication, and safe checkout
synchronization under WF-022.

The core dependency order is:

```text
Registered Planning workspace
  → browser Planning Agents and Harness
  → Coordinator and Builder
  → Reviewer and Publisher
```

Project creation depends on registration and onboarding but not coordinated execution. Instruction editing depends
on registration and Harness writing assistance but not automated Reviewer/Publisher orchestration. Either may move
earlier only when it does not compete with the core path for the same application, Runner, Harness, or UI
foundation. Onboarding guidance evolves with each installed capability rather than becoming a disconnected
documentation EPIC.

### Final boundary

Do not open a focused child map now. Open a later Wayfinder decision only when implementation exposes genuinely
new product uncertainty, not to restate an accepted decision or choose an implementation detail. Exact schemas,
APIs, events, UI components, permission identifiers, model lists, commands, forecasting math, thresholds, and
implementation slices belong to the downstream EPIC → TICKET → TASK process.

This resolution is a sequencing and EPIC-planning brief. It selects no executable TASK and creates no EPIC,
TICKET, TASK, branch, Workflow, claim, Agent, process, provider credential, migration, database record, or
production implementation. Its first separately approved grill handoff is
[EPIC-00005 — Deliver the registered Planning workspace](../../epics/00005-EPIC.md).
