# Define memory ownership and scope authority

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Hierarchical agent memory](../hierarchical-agent-memory-map.md)
**Depends on:** —

## Question

What ownership and authority model lets an Agent read relevant shared memory while limiting writes and promotion
to its assigned scopes, including continuity across sessions and multiple Workflows for one TASK?

## Agreed basis — 2026-09-28

John agreed to a shared hierarchy of Workspace → Project → TASK → Workflow → Phase → Session, with Agent memory
alongside that hierarchy because one stable Agent may participate in several sessions or assignments. The hierarchy
describes applicability and ownership; it does not automatically grant access to every descendant or ancestor.

| Template | Intended reads within authorized assignments | Intended writes |
|---|---|---|
| Implementing Agent | Own working memory; relevant phase, Workflow, TASK and project memory; workspace access where permitted | Own session/Agent memory and assigned phase |
| Team Lead (Coordinator) | Relevant lower-scope working memory, Workflow, TASK, project and permitted workspace memory | Own session/Agent/phase memory and Workflow; TASK ownership is a candidate when work spans Workflows |
| Project Manager | Relevant authorized memory throughout its project, plus permitted workspace memory | Own working memory and project |
| Director / CTO | Authorized memory across the workspace | Own working memory and workspace |

These are semantic Agent profile templates, not the Access Control Role entity. Templates request capabilities;
actual direct Permissions, assignment scope and live server checks enforce access. Sharing a template does not
share an identity or memory automatically. An implementing Agent cannot publish a conclusion at workspace scope.

Read, write and promotion are separate operations. Same-level writes require ownership; one Agent cannot rewrite
another Agent's notes merely because both work in the same phase. Wider applicability does not imply greater truth.
Memory never grants execution authority, changes approved scope, supplies an approval, or substitutes for current
verification. Independent review requires an explicit context policy as well as separate identities and sessions.

The table above is the original interview basis, not a blanket right to read lower-scope private working memory.
The accepted read, write and handoff rules below refine it.

## Questions addressed

- Whether Project memory is owned by the existing registered Repository identity or needs a distinct project concept;
  preserve the accepted Workspace/Repository model rather than silently introducing a new aggregate.
- Exact read/write/curation matrix, including whether higher-scope owners can revise lower-scope entries or only
  publish their own summaries, and who can inspect private working notes and conversations.
- Phase-shared versus Agent-private memory, ownership of phase summaries, and attribution when several Agents contribute.
- Stable Agent memory across session restart, process replacement, reassignment and identity retirement; prevent
  unrelated assignments from inheriting sensitive material through an Agent's personal history.
- Whether TASK memory is always available or conditional on multiple Workflows; who owns it across successive
  Team Leads, failed attempts, resumed work and concurrent assignments.
- Scope-limited workspace reads for implementers, project-wide reads for Project Managers, and Director access;
  no job title alone implies unrestricted access.
- Human visibility and control, revocation behavior, and reviewer visibility of prior findings versus private
  implementation reasoning. Define which historical evidence is deliberately supplied to a fresh review.

## Resolution — approved 2026-10-09

### Scope and ownership

For this capability, **Project** and registered **Repository** are synonyms: Project memory belongs to the stable
`RepositoryId` within its Workspace. Do not introduce another Project identity. A stable Agent identity and its
Harness template may apply across a Workspace and receive distinct repository assignments; identity, template,
profile name and remembered prose do not grant access to every repository. Preserve WF-014's separately scoped
custom templates and direct Agent Permissions.

Keep two distinct kinds of durable memory:

- A **small Workspace-private personal bank** of generally applicable lessons for the same Agent identity, subject
  to current access and disclosure rules. The Agent may curate its own bank but cannot use it to carry confidential
  repository/TASK material into another assignment or publish Workspace-shared guidance on its own. It is private
  to that Agent for ordinary work, not an unrestricted cross-repository prompt or a peer-shared knowledge base.
- The **main scoped memory** belongs to the registered Repository, TASK and Workflow as appropriate, with private
  Agent working memory partitioned by its authorized repository and assignment. TASK memory is available from the
  first Workflow even when empty. An assigned Team Lead may publish attributed TASK-scoped summaries; a successor
  Team Lead on a later authorized Workflow may read them under current TASK authority. Failed, resumed, successive
  or otherwise distinct Workflows keep their own memory; no predecessor summary is a current grant, approval,
  check result or verdict.

The intended durable memory capability is **database-backed**, including private Agent memory, rather than
uncommitted Markdown in worktrees. It is distinct from Pi's protected resumable conversations/checkpoints: do not
bulk-copy raw Pi transcripts, hidden reasoning or session files into memory or treat memory as model-context or
Workflow-state authority. Exact storage, partitioning, quotas and retrieval mechanics remain downstream choices.

### Workflow, phase and authorship

A user may call one coordinated `Workflow` a "workflow session"; that does not introduce another aggregate or
make its Agents' separate Pi sessions shared. The Team Lead manages Workflow-scoped shared memory. Each Agent
keeps private phase/working memory and its own session continuity; no role resumes another role's context. Each
phase produces a durable, attributed handoff and outcome visible to the Team Lead under the existing Workflow
contract. These are authoritative or verified handoff/evidence records under their owning contracts, **not**
substitutable memory notes. The Team Lead explicitly assembles information for the next Engineer, Senior Engineer
or QA Engineer, but cannot omit the exact subject, criteria, checks, prior findings where required or other
mandatory evidence to defeat independent assessment. A receiver does not inherit a predecessor's private memory.

Shared memory preserves append-only attributed history: higher-scope owners publish their own linked summaries or
corrections instead of rewriting lower-scope authors' entries. An Agent alone may ordinarily change its **own
private working memory in place** so a corrected belief, not obsolete text, is returned as current context;
change attribution and oversight metadata remain available. An old private value is not automatically reloaded.
Existing shared handoffs and outcomes are never retroactively edited by a private correction. WF-025 decides
retention of prior private content, shared revision/supersession, promotion, retraction and exceptional safety
redaction; this decision does not require old unsafe private text to remain retrievable.

### Reads, oversight and live authority

An assigned Engineer reads its own authorized private memory and relevant shared Phase/Workflow/TASK/Repository
entries plus permitted Workspace-shared guidance. The Team Lead reads the shared Workflow and phase handoffs it
manages, relevant TASK/Repository and permitted Workspace entries; a Project Manager reads authorized shared
memory across its repository; a Workspace steward reads shared Workspace memory. Higher position in that list
never grants another Agent's private working notes. Exact identity, current assignment, target scope, direct
Permission and applicable live authority are checked on every read and write; template/job title and previous
access cannot grant a new scope. Other authorized humans see shared memory only through their current scope and
Permissions.

Private memory is retained for authorized **audit and system-improvement** use outside a Workflow, not for
ordinary peer handoff. Use the exact managed Permission `READ_PRIVATE_AGENT_MEMORIES` for private-content access.
It is **not** `SUPER_ADMIN_ONLY` (under the current two-tier contract it would be `ADMIN_SAFE`): managed
`ROLE_SUPER_ADMIN` is an initial recipient, while specifically selected Agent identities may receive direct
grants and future authorized roles, possibly `ROLE_WORKSPACE_ADMIN`, are not ruled out. Role management itself
retains its separately protected authorization boundary; the tier alone is not access or target authorization. A Super Admin checks current effective Permission
and target access and generates safe attributed access audit, without a separate purpose statement or audit-TASK
requirement. An audit Agent additionally needs an explicit audit TASK that binds purpose and target scope; its
own direct Permission is necessary but insufficient outside that assignment. An authorized improvement process
uses an identified, separately permissioned and scoped principal, not a silent bulk feed or automatic permission
to publish its findings. Actual role/Agent grants follow the qualified Access Control and application actor/target
rules; this decision neither creates the Permission nor forbids future authorized delegation. Prohibited content,
credentials and provider disclosure remain guarded regardless of who reads.

Revocation, retirement, changed assignment or missing authority denies subsequent reads/writes immediately,
including through cached references and stale session snapshots. It does not by itself erase database records,
append-only shared history or durable handoffs; retained material remains available under separately authorized
audit and lifecycle rules. No memory or oversight read conveys execution authority, Planning approval, a review
verdict or permission to disclose a different repository's content.

## Decision closeout and boundary

John approved this ownership and access resolution on 2026-10-09. WF-025 now owns memory entry lifecycle,
promotion, revision/retention and safety removal; WF-026 owns bounded retrieval, context budgets and sandbox/MCP
delivery. The map frontier advances to WF-025. No Agent, Permission, database schema, memory record, runtime
access, EPIC, TICKET or TASK is created or granted by closing this decision; existing execution contracts are
preserved.
