# SDLC template specifications

This is the complete eight-template **authoring catalog, revision 2**, accepted by the maintainer and
independent technical Review and instruction QA under [TASK-00248](../../planning/tasks/00248-TASK.md).
It supplies reviewed inputs to [TASK-00299](../../planning/tasks/00299-TASK.md), not a runtime registry,
seed command, installed profile, Agent or grant. No loader consumes these files today.

## Catalog and provenance

The required [shared prompt](#shared-prompt--required-part-of-every-specification) and each linked **Role prompt**
are the prompt text; the surrounding sections retain the assignment and capability contracts.

| Specification and Role prompt | Historical label carried by alias mapping revision 1 |
|---|---|
| [Project Manager](project-manager.md#role-prompt) | Planner |
| [Explorer](explorer.md#role-prompt) | Explorer (unchanged) |
| [Team Lead](team-lead.md#role-prompt) | Coordinator |
| [Software Engineer](software-engineer.md#role-prompt) | Builder |
| [Senior Engineer](senior-engineer.md#role-prompt) | Reviewer |
| [QA Engineer](qa-engineer.md#role-prompt) | None; explicit addition |
| [Release Manager](release-manager.md#role-prompt) | Publisher |
| [Hotfix Engineer](hotfix-engineer.md#role-prompt) | None; explicit addition |

Authorities: [Team roles](../../docs/engineering/TEAM.md),
[WF-014](../../planning/wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md),
[WF-015](../../planning/wayfinder/tickets/WF-015-define-coordinated-task-authority-protocol.md), and their
accepted amendments in [TICKET-00034](../../planning/tickets/00034-TICKET.md) and
[EPIC-00006](../../planning/epics/00006-EPIC.md). Architect is a Senior Engineer specialization, not a
separate identity. [Triage](../../planning/tasks/00200-TASK.md) is a separately approved extension, not a
ninth member of this required catalog.

Source inspection at `0bd021867406f29a69077efa3916e17c7a86a32b` found no managed Agent-template identities
or store in `database/`, `src/` or `config/`. `harness/pi/agents/` contains local subordinate/stage launch
profiles, not managed template records; these specifications neither replace nor install them.
The eight stable UUIDs were generated once with Python's `uuid.uuid4` library for this catalog. Preserve them
and the managed keys after acceptance. They are template identities, never Permission, Agent or session IDs.
A nonempty installation must be inspected by TASK-00299 before import; collisions or older identities require
an explicit mapping, not replacement by these newly authored IDs.

Frontmatter records `template_id`, `managed_key`, `specification_revision`, `name`, `alias_mapping_revision`
and `historical_aliases`. Specification/catalog revision 2 applies the maintainer-authored concise prompts;
revision 1 remains in Git history. These are authoring revisions, not invented historical database revisions.
The exact Git commit and blobs of this README plus all eight files identify the complete review subject;
any content change needs a new review subject. Alias mapping revision 1 records the historical label provenance
from TEAM/WF-015; it does not claim an old runtime revision number or rewrite stored labels/events. Resolve aliases
in their owning installation/scope and revision; ambiguity stops resolution. A rename never creates authority.

## Shared prompt — required part of every specification

Read this section together with the selected role's **Role prompt**; neither is optional.

Follow repository instructions and existing conventions. Inspect relevant evidence before acting; distinguish facts from assumptions. Prefer the simplest solution that meets the goal. Preserve unrelated work and existing behavior unless change is required. Proceed within scope; ask only when blocked by a consequential ambiguity. Verify proportionally to risk; never claim unperformed checks. Report outcomes, evidence, remaining risks, and the next action concisely.

## Complete capability and resource intent

The concise prompts do not replace [Team roles](../../docs/engineering/TEAM.md), the assignment, selected
Skill or the supporting contracts below and in each specification. Titles, prompts and tools grant nothing;
current identity, scope, authority, resources and limits must be verified through their owners. Missing or
revoked prerequisites pause the affected action. Keep credentials private, do not reset limits, and reconcile
unknown operation outcomes before replay. Use the target's [handoff rules](../../docs/engineering/HANDOFFS.md).

Every role requests the following **common set plus its own role-specific table**, with no implied extras:

- Read only its authorized assignment, applicable Project context/policy, exact template and Harness snapshot,
  selected resource revisions, current scoped availability/authority and remaining limits.
- Discover and read its selected trusted Skill manifests/files; inspect only admitted evidence paths/records.
- Read/write its own bounded session notes, progress, questions and handoff; submit pause, stop and escalation
  requests for its own work. Read protected operation results only where assigned; never write their receipts.
- Use the explicitly assigned qualified model connection within disclosure and spend limits. This requests
  generation access, not credentials, account administration or provider enrollment.

These are semantic Permission requests, **not executable Permission names or IDs**. The current
[managed Permission definitions](../seeds/permissions.json) include `READ_PROJECTS` (`ADMIN_SAFE`), which is a
possible existing Project-inspection reference, not a grant or proof of all context access. No role requests
Project registration, reference allocation, human/Role administration, template management, log administration
or Swagger access merely because those definitions exist. The necessary fine-grained context, resource,
Planning, Workflow, file, check, report and delivery requests below are not resolved to eligible Permission
IDs/revisions here. Protected-tier administrative permissions are not substitutes for missing Agent requests.

[TASK-00249](../../planning/tasks/00249-TASK.md) must resolve the **entire** requested set, exact scope,
current eligible `ADMIN_SAFE` Permission IDs/revisions, resource identities/revisions/origins and model references
through their owners, denying missing/ineligible/ambiguous inputs rather than dropping them. Actual Agents receive
direct Permissions only through Access Control and assignment/provisioning owners. Delegated authority is capped
by the initiating user's current authority and the Agent's own direct Permissions and session constraints.

Each role's linked local Skills exist as authoring references; they are not qualified MCP manifests or immutable
managed resource IDs. Required trusted Skill identity/manifests remain with TASK-00327; prompt/persona references
with TASK-00329; theme references with TASK-00330; model catalog references with TASK-00343. TASK-00299 owns their
exact scoped seed composition. The role prompt and shared prompt here are proposed prompt content for those
owners, not duplicate runtime prompt records. No separate persona or theme is requested; presentation may use
an independently resolved user preference, never an authority dependency. Local skill availability does not
establish managed tool, isolation, provider or session readiness. TASK-00250 owns eventual immutable binding.

Model requirements below are suitability criteria, not selected model names or certification. All choices remain
unqualified for these managed profiles until supported context/tool behavior, exact requested/configured and
provider-reported identity, disclosure, limits and representative role evidence are recorded. Unavailable usage,
latency or cost stays unavailable, not zero or a guessed price; no silent fallback. Explorer's speed/cost preference
cannot override correctness or authority. No provider call is required to review these definitions.

## Publication and acceptance boundary

Applicability is Workspace-available specification intent, usable only for an explicitly authorized registered
Project/operation. It gives no installation-wide data access. TASK-00299 owns one atomic initial seed and retained
history; after initialization the database owns current selections. These files are retained reviewed input,
never a recurring reconciler, source fallback or automatic update of existing Agents, credentials or sessions.
[TICKET-00076](../../planning/tickets/00076-TICKET.md) owns later human publication and
[TICKET-00078](../../planning/tickets/00078-TICKET.md) explicit one-time Permission reconciliation.

The first managed milestone stays **Team Lead → Software Engineer → durable Awaiting review**. Only PHP-owned
validated operations establish claims, eligibility, grants, provisioning, protected check results and transitions.
Later roles below define intended behavior, not operational journeys unlocked by catalog acceptance.
Design/build contributors cannot independently review their implementation; review uses a fresh session, QA
follows technical acceptance and precedes land, and source repairs return through Engineer and renewed review.
Technical and QA repairs share the existing revision allowance; neither resets it.

**Human acceptance: recorded** for the complete revision-2 catalog at
`8a39a28734031476a9f9c4b44bdda723c2045519`, followed by independent technical Review ACCEPT and instruction
QA PASS (35 scenarios). [TASK-00248](../../planning/tasks/00248-TASK.md) records the decision and evidence.
This factual closeout changes no prompt, identity or capability contract. Implementation authorization,
walkthroughs and mechanical checks alone are not human acceptance. No seed, grant, launch, publication,
merge, release or deployment is authorized by this document.
