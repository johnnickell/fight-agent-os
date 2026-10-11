# Define project-linked Teams and available Agent selection

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Open
**Map:** [Complete AccessControl consumer adoption](../accesscontrol-consumer-adoption-map.md)
**Depends on:** [WF-033 — Define live Agent operation adoption and safe surfaces](WF-033-define-agent-operation-adoption-boundary.md)

## Question

How should a product-level Team group instantiated Agents, attach to one or more registered projects
(Repositories), and select an available qualified Agent for a role when an authorized TASK Workflow starts?
How does this later product extension coexist with the accepted first execution slice and triage routes?

## Proposed basis from maintainer

The operator wants a nearby but separate Agent-creation journey: choose an approved template, supply a friendly
name (e.g., Leeroy Jenkins), create a distinct Agent and assign it to a Team. A Team belongs to one project or a
set of projects. For a project TASK's Workflow Session, dispatch uses the next available suitable Team member,
such as a Hotfix Engineer. Agents/templates can be created and manually dogfooded through the Harness before the
complete coordinated Workflows exist; project-Team scheduling is **not** a prerequisite to the first working
Explorer. This is product intent, not a claim of current database, Runner or Harness implementation.

## Must decide

- Team identity, ownership and scope: single vs multi-Repository memberships, shared Teams, cross-Repository
  access, ambiguity if more than one Team serves a Repository, membership changes and who may administer them.
  **Recommend:** explicit versioned assignments; no implicit scope from a display name or Role; membership alone
  never grants direct Permissions or project authority.
- Instance creation and availability: who can create, activate, rename, pause, reassign, retire or rotate an
  Agent; what a healthy, authenticated, qualified and unoccupied Agent means for selection; whether an identity
  can belong to more than one Team and what happens to in-flight sessions on change. **Recommend:** one actual
  holder-bound credential per Agent and exact recorded template revision; never repurpose a running session or
  shared key to satisfy a missing role.
- Role matching and fair selection: when there are zero, one or many qualified members of the right template,
  concurrent starts, deterministic priority or rotation and operator override. **Recommend:** resolve at the
  authoritative start/dispatch boundary with current direct Permission, grant, project scope, runner capacity and
  independence checks; preserve chosen identity in the Workflow, fail/queue visibly if none and never guess an
  Agent from prompt text. A Skill may ask for a role but cannot choose or impersonate an Agent.
- Relationship to [EPIC-00007](../../epics/00007-EPIC.md)'s approved Team Lead → Software Engineer first slice,
  [EPIC-00008](../../epics/00008-EPIC.md)'s review independence and
  [WF-028 — Define triage dispatch and hotfix role boundaries](WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md)'s
  versioned route/confirmation rules. **Recommend:** separate linked *later* Team/selection destination, not a
  silent rewrite of the first execution slice or a second TASK start/claim; hotfix still needs its accepted human
  emergency confirmation and an independent non-contributing reviewer. Select timing/ownership with the maintainer.

## Resolution boundary

Define the user journey, Team/Repository/Agent scope and start-time selection rules, exclusions and EPIC handoff.
Do not create Teams, Agents, database records, Workflow assignments, Skills, routes or TASKs. This ticket does not
reopen the closed vision or triage maps; any change to their accepted EPIC/TICKET destinations must be explicitly
approved and linked rather than inferred from this map.

## Resolution

Pending after WF-033.
