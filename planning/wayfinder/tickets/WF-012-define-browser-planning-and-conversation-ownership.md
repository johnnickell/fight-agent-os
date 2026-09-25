# Define browser planning and conversation ownership

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Complete Fight Agent OS vision](../complete-agent-os-vision-map.md)
**Depends on:** [WF-010](WF-010-define-authoritative-planning-domain-and-lifecycle.md)

## Question

How should persistent browser conversations perform Wayfinder, grill, to-tickets, and to-tasks work through the same authoritative application operations as direct artifact editing?

## Must decide

- Ownership and lifecycle of a planning conversation, its selected repository, participants, resumable Pi session, active planning artifact, and immutable context/policy revision.
- How proposed artifact changes are previewed, edited, validated, accepted, rejected, retried, and committed as planning revisions.
- Recovery from browser disconnects, agent interruption, stale revisions, and simultaneous direct edits without losing conversation history or silently overwriting artifacts.
- Browser journeys for exploration, decision questions, requirement and acceptance-criteria editing, hierarchy browsing, dependencies/blockers, progress, and archive/restore.
- Permission boundaries for viewing, proposing, accepting, archiving, and restoring records.
- Use of the canonical next-executable-TASK query from WF-010, including human-readable eligibility explanations; browser and the `next` skill must not invent separate rules.
- Separation of conversation persistence, planning revisions, execution workflow sessions, review acceptance, publication, and merge state.

## Resolution boundary

This decision owns product and application-operation behavior for planning conversations. It does not choose exact React components, Pi process topology, database mappings, or session-dashboard presentation. It must preserve grill → EPIC, to-tickets, and to-tasks as separate operations.

## Preferences required

John must choose where explicit confirmation is required before an agent-authored planning revision becomes authoritative and how much conversation history is visible by default. The recommendation should save proposals separately from accepted revisions and make acceptance a revision-checked application command.

## Resolution

### Repository-scoped planning journey

The browser presents one seamless, artifact-centered Planning experience per registered repository. Internal Pi sessions, model contexts, and linked conversation operations may rotate without fragmenting the user journey. Repository selection, conversation sharing, artifact references, authorization, and context never cross repository boundaries implicitly.

Keep **What do you want to build?** at the top of the repository Planning section. Submitting it immediately creates a durable repository-scoped planning intake, not an authoritative Wayfinder map. The planning agent inspects existing maps, EPICs, TICKETs, and TASKs and recommends continuing a meaningful match when one exists. The user decides whether to extend existing planning or create something new; the agent never attaches, reparents, or duplicates work automatically.

For new work, proceed directly into Wayfinder questions. Once the agent can state a useful destination and done condition, preview an editable recommended title, mutable repository-unique slug, destination, done condition, initial WF tickets, dependencies, and frontier. **Create Wayfinder Map** creates that approved proposal and immediately advances to the first eligible WF ticket. A HITL decision opens on its first question. AFK research or prototype work begins its bounded operation and displays truthful stages such as Queued, Inspecting, Running prototype, Preparing findings, Ready for review, interrupted, or failed. If no decision is eligible, explain the dependency or unavailable capability instead of appearing idle.

Opening a map resumes at its current decision. Interviews within Wayfinder resolve WF tickets and do not themselves create EPICs. When an approved map handoff invokes the distinct grill operation, completing that grill produces one EPIC proposal; after creation, an eligible EPIC exposes **Make Tickets**, and an eligible TICKET exposes **Make Tasks**. Preserve Wayfinder, grill, to-tickets, and to-tasks as distinct authoritative operations even though navigation feels continuous. Initial decomposition actions are prominent. After children exist, a secondary **Continue planning** action may deliberately expand that artifact. It must inspect current children and propose additions or explicit revisions rather than duplicates. Terminal or archived parents require the accepted reopen or restore operation before decomposition.

### Conversation ownership, visibility, and lifecycle

The application owns durable Planning conversations independently of browser tabs, authentication sessions, HTTP connections, and individual Pi processes. A conversation records its repository, initiating user, delegated agent when present, operation and target, immutable context/policy snapshot, transcript, accepted answers, proposal references, progress, and linked internal session lineage. Only one bounded agent turn acts for a conversation at a time; replacement or context-management sessions remain an implementation detail.

The initiating user sees the full transcript, rejected ideas, unfinished answers, and proposals by default. Other authorized repository planners see accepted artifacts, accepted decisions, and progress, but not private drafts or the full transcript unless the owner explicitly shares or hands off read or continue access. V1 does not provide simultaneous free-form co-editing. A delegated browser agent preserves both initiating `UserId` and acting `AgentId` and never exceeds current delegated authority.

Resume directly at the last unresolved question with current progress, accepted decisions, recent conversation, and any pending proposal, conflict, or retry. Earlier transcript remains available through progressive loading without exposing internal Pi-session boundaries. Closing a tab, losing connectivity, walking away, or logging out never closes the conversation or cancels its bounded active turn. Reopening reconnects to running work or fetches its durable result. **Stop** requests cancellation; **Abandon planning** is a separate confirmed operation that preserves history and removes the conversation from the active queue.

Successful application of the final proposal completes that planning operation. Completed and abandoned operations remain available through artifact activity/history. A later revision may use a linked internal continuation to preserve the original operation and context, while the UI still presents one chronological artifact history. Normal users cannot permanently delete conversation history; narrowly authorized redaction and retention remain separate concerns.

User input is durable before delegated work starts. The agent may perform factual inspection and deterministic planning until it reaches a genuine product, priority, boundary, or risk choice or an authoritative proposal requiring acceptance. It may prepare the next decision and progress while the user is away, but it cannot choose human preferences, accept its own proposal, invoke a guarded lifecycle transition, or start a distinct decomposition/execution operation without the required user command. Idempotent command identities prevent a reconnect or retry from applying the same mutation twice; an uncertain command outcome is resolved before another apply is offered.

A changed configuration revision or effective instruction digest preserves the conversation but stops new authority-bearing agent operations. Show a concise change summary and require **Continue with updated context**. That action links an internal continuation using the new immutable snapshot; pending proposals survive but must be revalidated. Unchanged reconnects need no ceremony, and live authorization may always narrow or revoke access immediately.

### Progress

Show two honest progress levels. Map progress is closed Wayfinder tickets divided by all currently known tickets. The active WF ticket shows settled decision points divided by all currently known required decisions. Display counts and current/remaining work beside progress bars. If a response exposes legitimate new decisions or tickets, increase the denominator and explain what was added rather than hiding the change. Decision units, not turns, tokens, elapsed time, or internal agent steps, determine progress. A grill reaches completion only after its final EPIC proposal is accepted and created; proposal preparation and review are explicit stages.

AFK work uses observable stages, last activity, interruption, and retry information rather than fabricated percentages. Browser disconnect does not stop it. Agent-authored research, prototype findings, and artifact changes still enter the normal proposal and acceptance boundary.

### Proposals, direct edits, and conflicts

Save agent-authored proposals separately from accepted Planning revisions. Clicking or submitting a human answer records that answer as a revisioned decision point; later correction appends another semantic revision. Before creating or revising an EPIC, TICKET, TASK, Wayfinder resolution, research note, prototype finding, or similar agent-authored artifact, show one editable structured preview and require explicit **Create** or **Apply changes**. Multi-record **Make Tickets** and **Make Tasks** proposals apply atomically. Guarded lifecycle operations retain their WF-010 confirmations.

Direct artifact editing uses small command-oriented forms for concepts such as a title, use case, criterion, dependency, priority, or narrative section. Do not offer free-form whole-document replacement. V1 does not persist direct-edit drafts or autosave each keystroke: unsaved form state may be lost on refresh, and the browser warns before leaving a dirty form. Submission validates and invokes one semantic command against the expected aggregate version. Agent proposals remain durable through the conversation, but unsaved manual changes to a preview may also be lost in v1. Persistent direct-edit drafts and autosave are deferred.

Every authoritative mutation supplies expected aggregate or queue revisions. The API rejects stale commands without discarding transcript or proposal state. Offer an AI-assisted semantic reconciliation using the proposal's base revision, current authoritative revision, and rejected proposed revision. Clearly distinguish clean combinations from conflicts in typed fields, relationships, and narrative sections. The result is a new proposal against current state and requires user review; AI never bypasses optimistic concurrency, silently merges, or writes with last-write-wins behavior. Any stale member rejects an atomic multi-record proposal rather than creating a partial hierarchy.

### Planning home, hierarchy, Roadmap, and Kanban

After the always-visible intake, show active conversations requiring answers, proposal review, reconciliation, or retry; then provide hierarchy, Kanban, Roadmap, next-work, and archive views. Artifact views expose navigable parent/child relationships, criteria, accepted progress, direct and transitive dependency effects, evidence, available semantic actions, and human-readable reasons for unavailable actions. React calls application commands and queries and never recreates Planning invariants.

Roadmap order supplies inherited priority for EPICs and their TICKETs. Eligible descendant TASKs inherit that relative position, while TASK order controls sequencing within it. Authorized Kanban reordering creates visible TASK-level queue overrides for independent work, including placing feature work above a newly created standalone bug or chore. An override persists until cleared or superseded; blocked work retains its priority for later eligibility. Repository authority reorders work inside one repository, while cross-repository workspace order requires workspace authority. Standalone bugs and chores receive direct queue placement. Roadmap and queue mutations are authoritative semantic commands, so stale revisions fail visibly.

Use the same canonical workspace work-queue capability behind the Kanban and the `next` skill. Kanban shows several TASKs, ordered eligibility, and stable human-readable inclusion or exclusion reasons. It may use derived lanes such as Ready, In Progress, Blocked, Human Action, In Review, and Complete, but it never treats Blocked as a stored status or permits a manual drop into it in v1. If implementation itself lacks information, use the existing reasoned Needs Info transition. If only deployment is unavailable, keep TASK completion separate from deployment. A future orthogonal Hold operation may deliberately suppress eligibility but is not part of this contract.

Dragging a Ready TASK toward In Progress is a semantic **Start coordinated work** request. Let the visual move complete into a clearly provisional position, then show a concise confirmation with TASK, repository, revalidated eligibility, intended workflow or agent profile, and later revision allowance when WF-015 defines them. The card moves authoritatively only after the server successfully rechecks and claims it; cancellation or rejection returns it smoothly with an explanation. Derived lanes cannot be manual targets, and other supported drags map to intent-specific commands rather than a generic status setter.

Kanban is an end-to-end work projection, not a replacement status model. Planning lifecycle and eligibility remain distinct from linked execution, review, delivery, and merge evidence. A TASK may be Done after accepted local implementation and landing while its card appears **In Review** with Review Accepted, PR Open, and Merge Pending facts. Move the card projection to **Complete** only after a trusted refresh verifies the provider's merged result on the configured base branch, supporting merge-commit, squash, and rebase strategies. Do not append another TASK-completion event or infer Planning status from GitHub. Human Action may surface exhausted revision allowance or another intervention without inventing a TASK status. WF-015 and WF-016 define the coordinator, claim, run, and revision behavior.

Archive remains an explicit secondary view and operation. Active views hide archived records by default. Preview archive eligibility and retained links, enforce WF-010 terminal and child rules, and never cascade. Restore from the archive view, preserve previous status and history, and keep reopening separate. If a parent must be restored first, explain the prerequisite rather than silently restoring multiple records. Conversations and evidence remain discoverable through archived artifacts subject to their own permissions and retention.

### Authorization and boundaries

Authorize every command and query through Fight Access Control permissions, never role-name checks. Default to a permission aligned with each protected use case. Deliberately group operations beneath a broader managed permission only when they genuinely share authority and risk. Managed roles may consistently bundle managed permissions. Viewing Planning, starting/proposing through conversations, accepting revisions, reordering a Roadmap or queue, archive/restore, reparenting, waiver, conversation sharing/handoff, and other guarded operations receive independently justified permissions or groupings during downstream planning. Client visibility reflects the same server-authoritative permission but never enforces it.

Comments, mentions, teammate notifications, simultaneous collaborative editing, persistent direct-edit drafts, automatic autosave, manual Blocked transitions, and manual Hold are deferred. This decision does not choose React components, exact routes or permission identifiers, Pi process or model topology, streaming transport, database mappings, coordinator internals, runner recovery, or session-dashboard presentation. It creates no EPIC, TICKET, TASK, schema, migration, database record, running agent, or authority change. WF-015 through WF-018 own coordinated execution, runners, Execution history, and the sessions-first prototype; WF-019 retains the EPIC handoff for this umbrella map.
