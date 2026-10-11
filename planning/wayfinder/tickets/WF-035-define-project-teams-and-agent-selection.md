# Define project-linked Teams and available Agent selection

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete AccessControl consumer adoption](../accesscontrol-consumer-adoption-map.md)
**Depends on:** [WF-033 — Define live Agent operation adoption and safe surfaces](WF-033-define-agent-operation-adoption-boundary.md)

## Question

How should a product-level Team group instantiated Agents, attach to one or more registered projects
(Repositories), and select an available qualified Agent for a role when an authorized TASK Workflow starts?
How does this MVP Team/assignment destination supply the accepted first execution slice and triage routes?

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
  versioned route/confirmation rules. **Recommend:** a separate linked *MVP* Team/selection destination supplying
  EPIC-00007's first execution slice, not a silent rewrite or a second TASK start/claim; hotfix still needs its
  accepted human emergency confirmation and an independent non-contributing reviewer. Exclude cross-Team peer
  delegation within one TASK.

## Resolution boundary

Define the user journey, Team/Repository/Agent scope and start-time selection rules, exclusions and EPIC handoff.
Do not create Teams, Agents, database records, Workflow assignments, Skills, routes or TASKs. This ticket does not
reopen the closed vision or triage maps; any change to their accepted EPIC/TICKET destinations must be explicitly
approved and linked rather than inferred from this map.

## Resolution

Approved in the WF-035 interview:

- **Team identity and administration:** a Team may serve one or several registered Repositories; a Repository
  may have one or several Teams and one authorized default Team. One Agent belongs to exactly one Team at a time;
  a versioned template may instantiate many distinct named Agents across Teams. An idle Agent may transfer under
  authorized administration with history preserved; do not silently move an active session or reassign its
  identity. Team association alone does not grant Repository scope or direct Permissions. Initially the
  `ROLE_SUPER_ADMIN` holder, checked through **current distinct managed Permissions** (not the role name alone),
  may create/manage Teams, link them to Repositories and assign/transfer Agents. Agent creation/management and
  Agent-template management likewise require their **own intent-specific management Permissions** initially
  granted to the Super Admin. Team Leads coordinate permitted work for their Team under a bounded Workflow grant;
  they cannot administer roster, elevate permissions or recruit Agents from another Team. Future
  `WORKSPACE_ADMIN` and `REPOSITORY_ADMIN`/`PROJECT_ADMIN` are possible scoped grantees **only after** a separately
  approved Workspace/Repository authority model, actor/target rules and grant policy; they do not exist or gain
  authority through this decision. Choose exact permission names/tiers and scoped rollout in later EPIC/TICKET
  planning, without silently changing TICKET-00015's current exact managed catalog or granting an `ADMIN_SAFE`
  permission to custom Roles/Agents just because the tier permits it.
- **MVP human selection and truthful board:** a human chooses a TASK from the priority-ordered Ready Kanban lane
  and drags it toward In Progress. Triage classifies/routes that *selected* TASK and proposes an idle Team Lead
  or Hotfix Engineer, not the next TASK in the queue; this preserves
  [WF-027](WF-027-define-task-triage-readiness-and-classification.md)'s existing human selection. The gesture
  is an intent, not a claim or evidence of Agent progress. Show Pending start, queued after claim, actually
  working, blocked/waiting and confirmed outcomes distinctly, with text/state as well as optional motion/color.
  **Future v2 only:** autonomous Triage selection from the canonical queue after prior handoff/merge or safe
  overlap needs its own later explicit approval; no MVP automatic TASK selection or merge follows from this map.
- **MVP whole-Team handoff and strict Team boundary:** a Team may have one or many Team Leads. Triage tries an
  eligible idle lead on the Repository's default Team first; if busy, it may recommend handing the **whole new
  TASK** to an idle lead on another Team *explicitly associated with that Repository* for safe independent work.
  A repeatable fair choice settles equivalent candidates; hold with a reason on real dependency, shared-resource,
  role-capacity or independence uncertainty. Do not invoke semantic judgment for a deterministic fallback. This
  is alternate-Team assignment **at intake**, not cross-Team peer delegation: once a Team receives a TASK, its
  Team Lead may delegate phases only to qualified members of **that same Team**, including an independent
  same-Team reviewer when required. If the Team has no eligible member, hold visibly rather than borrowing one
  from another Team. Neither Skill text nor an Agent can grant cross-Team control.
- **Idle, prerequisites and pending intent:** a Team Lead is idle without active Agent work or a conflicting
  commitment; waiting for human review/PR merge does not alone occupy that Agent. Independent TASKs can run in
  parallel on separate safe resources/branches. A TASK dependent on an unmerged prerequisite PR stays ineligible
  until its required change is verified on its configured base under [EPIC-00008](../../epics/00008-EPIC.md),
  even if the blocker TASK is Done. When no suitable idle Team/Agent is available or a prerequisite remains
  unmerged, keep a visible, withdrawable, human-selected **Pending start** intent; it is neither a TASK claim,
  Workflow start nor actual progress. Retry only within the valid bounded human intent and recheck current
  TASK/parent/context/base, authority, Team roster and exclusive claim before the **single** atomic Workflow
  start. Changed scope, expired authority or uncertain outcomes stop for renewed human decision/reconciliation;
  cancellation withdraws only its owned pending intent. A queued Runner after a confirmed claim remains a
  different state. Exact intent persistence and trigger belong to later planning, not an implicit edit to
  EPIC-00007's existing atomic start contract.
- **Authority and MVP ownership:** Triage recommends a role-matching Team/Agent or hold, but cannot claim work,
  grant permissions or approve emergency treatment. The application rechecks current credentials, direct
  Permissions, Repository/Team scope, eligibility, availability, grant, roster and independence and performs
  [WF-028](WF-028-define-triage-dispatch-and-hotfix-role-boundaries.md)'s one authoritative start/claim;
  hotfix still needs its accepted human confirmation. The Team Lead coordinates project work only after start.
  Team membership and alternate-Team *intake* are an **MVP destination**, supplied by one separately linked
  Team/assignment EPIC for the first full [EPIC-00007](../../epics/00007-EPIC.md) coordinated Workflow. This
  explicitly adds a prerequisite/capability supplier without rewriting EPIC-00007/00008, their existing TASKs
  or WF-028's accepted routes. It does **not** gate the first working Explorer/Project Manager's manual Harness
  use. No cross-Team peer dispatch within one TASK belongs to this plan.

This decision creates no EPIC/TICKET/TASK, Team, Agent, Workflow, grant, new admin Role or product UI.
