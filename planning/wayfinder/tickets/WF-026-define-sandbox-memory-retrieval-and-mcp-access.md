# Define sandbox memory retrieval and MCP access

**Labels:** `wayfinder:grill`
**Mode:** HITL
**Status:** Closed
**Map:** [Hierarchical agent memory](../hierarchical-agent-memory-map.md)
**Depends on:** [WF-024](WF-024-define-memory-ownership-and-scope-authority.md), [WF-025](WF-025-define-memory-lifecycle-and-promotion.md)

## Question

How can a sandboxed Agent retrieve useful authorized memory and manage its own entries through the trusted
first-party MCP boundary while durable authority stays outside the sandbox?

## Initial direction (superseded by the resolution below)

MCP Resources and preloaded memory summaries were considered, but neither is required. The accepted direction
uses on-demand MCP tools and the normal application/database boundary. Exact Pi integration remains to qualify.

## Research — startup memory versus on-demand retrieval — 2026-10-09

[Agent memory context loading research](../research/WF-026-agent-memory-context-loading-research.md) compares
primary context-engineering guidance with Claude Code, Copilot Memory, MCP and a long-context retrieval study.
The evidence does not require memory content at startup. John accepted a concise memory-use rule in the
Agent's instructions plus its authoritative task handoff, followed by on-demand retrieval. A small starting
index/selection may be tested later but is not required. This does not qualify a Pi/MCP implementation.

## Related approved Harness direction — 2026-10-07

The [incremental Harness amendment](../../epics/00006-EPIC.md#approved-incremental-harness-protection-and-retrieval--2026-10-07)
owns the first `jev_read_file` tool, required screening convention and initial 0.70 positive-read policy, including
uncertainty, required-evidence exceptions and provider/data boundaries. Deliver that repository-file capability
without waiting for this map's full memory design. This decision later determines how it applies to authorized
memory entries; it does not authorize reading or sending private memory as part of a repository glob.

Session compaction checkpoints do not promote memory or erase provenance. The memory contract below supersedes
this section's earlier retrieval and caching proposals; the file tool remains a separate Harness capability.

## Earlier Jev proposal — 2026-10-07

The earlier proposal considered candidate ranking, per-entry judgments, indexed discovery and cache reuse.
The resolution below replaces that speculative shape with Jev typed questions about individual memories or an
authorized scope and a Level 9-style entry narrowing route. No memory-specific cache is accepted.

Session compaction timing and checkpoint-content selection belong to
[WF-029](WF-029-define-compaction-timing-and-notifications.md) and
[WF-030](WF-030-define-context-retention-and-resume-contract.md); the general judgment tool belongs to
[WF-031](WF-031-define-general-purpose-bounded-judgments.md), all Harness follow-ons in
[EPIC-00006](../../epics/00006-EPIC.md#follow-on-jev-decision-coverage--2026-10-07). This decision owns retrieval of
permitted memory, not compaction orchestration or a general-purpose judgment tool. Closing this decision does not
qualify a provider, embedding store, MCP transport or runtime.

## Questions addressed

- Service ownership and application boundaries; durable memory outside the sandbox, with local scratch/cache
  explicitly disposable and no shared writable host-memory mount or direct database access.
- Discovery, search, resource reads and writes scoped to the authenticated Agent and current assignment; enforce
  permissions before exposing snippets, titles, counts or other metadata, and recheck authority on reads/writes.
- Initial context selection, explicit broader search, byte/token/result budgets, source visibility and exact entry
  revision recording. Distinguish later retrieval from silently changing pinned Harness or repository snapshots.
- Agent-requested remember/revise/retract operations and uncertain-write recovery under WF-025; actual server checks
  enforce WF-024 even when the model submits another scope or guessed resource URI.
- Cache isolation, retention and revocation; revocation prevents future access but cannot erase content already
  observed by a model. Restricted data must not be reintroduced through caches or derived summaries.
- Stale/conflicting memories, unavailable service, interruption and resume, independent review context, and clear
  behavior when a resource or operation is unsupported rather than silently widening access or falling back.
- First-party memory versus future external knowledge sources; any later federation preserves source permissions,
  provenance and trust instead of importing broad external access into every sandbox.
- Focused qualification evidence for useful retrieval, denied cross-scope access, promotion denial, revisions and
  recovery; reuse existing Harness/broker contracts without claiming current sandbox qualification is complete.

## Resolution boundary

Set the access and retrieval contract and identify any necessary bounded research. Preserve accepted first-party
MCP trust, live revocation and external authority boundaries. Do not implement transport, enroll credentials,
ingest host memory, or select an embedding provider merely to close this decision.

## Resolution — approved 2026-10-10

### Startup and application authority

Start an Agent with concise instructions for when to consult memory and the authoritative task handoff. Do not
require an up-front memory fetch or preload entry contents. The Agent retrieves its own private and currently
permitted shared memory on demand; a small starting index is only a later measured option. Instructions and
memory do not grant permission or replace TASK criteria, Workflow evidence, Planning decisions or source code.

Durable memory belongs to the application database outside the sandbox. An Agent uses its existing HMAC
identity to call first-party MCP memory tools, not direct database access or a shared writable memory mount.
Reads and writes carry applicable scope IDs; the application checks the authenticated Agent's current
permission, assignment, ownership and target-scope lifecycle as part of each operation. A guessed ID or claimed
scope does not create access. No extra second authorization pass is required within one already authorized
read/lookup request; a later, separate operation uses its own current authority. Jev/OpenRouter is a judgment
backend, not a separately provisioned Agent with ordinary memory Permissions or independent database access.

Normal execution access follows lifecycle, without deleting historical records. Once a Workflow completes, an
Engineer cannot ordinarily read that Workflow's memory through a later assignment. An assigned Team Lead may
read shared memory from completed Workflows under its still-active TASK to prepare a later attempt or TASK
summary; its normal Workflow/TASK access ends when the TASK completes. Later Workflows receive the separately
attributed TASK summaries and required handoff, not direct inheritance of predecessors' private notes or
Workflow memory. A Project Manager with current repository authority may continue reading completed Workflow
memory for estimation. Authorized audit Agents and humans can inspect retained history under their own current
scope and Permissions; WF-024's additional audit-TASK requirement still applies to private Agent memory. Normal
read loss is not data removal, a blanket denial to project/audit readers, or a new scope grant.

An independent reviewer starts with its own session and mandatory TASK/review evidence. It may consult shared
memory permitted by its own current authority; ordinary review cannot read an implementer's private working
memory, searches, session or reasoning. Separately authorized audit access is not granted by being a reviewer.

### MCP operations and Jev judgments

Expose simple memory MCP operations rather than requiring MCP Resources. Explicit writes name their scope and
intent: remember in one's own private bank, append at an authorized shared scope, correct or retract according
to WF-025, or propose promotion. The application enforces ownership and whether the target scope still accepts
that operation; uncertain retries of the same write resolve to the original entry rather than appending it
again. Nothing bulk-copies a conversation or automatically promotes a note.

Provide an ordinary authorized read for an exact memory entry. Separately, the Agent can ask typed yes/no or
predeclared-choice questions about one memory or memories in a specific currently authorized scope without
loading their text into its own model context. Jev can judge entries in that scope, in the manner of
[Level 9 files at scale](https://github.com/disler/ten-levels-of-jev#level-9-files-at-scale), to return
matching memory IDs for selective exact reads. Jev returns judgments, not generative answers or summaries; the
Agent reads original entries through the ordinary operation when source text is needed. The service may use
available application commands and queries on behalf of the invoking Agent to gather evidence; do not freeze
future SQL, repository-file or broader Jev routes through this memory decision. A numeric threshold or the
Level 9 example's file cap is not inherited without memory-specific qualification.

Jev/OpenRouter may process eligible memory content for those judgments without Jev holding an Agent credential
or ordinary Agent read Permissions. Protect secrets and other prohibited material from provider disclosure;
the precise detection and exclusion mechanism requires qualification. Existing repository-file and database
analysis plans own their own paths and use cases. Neither a typed judgment nor a guessed record ID expands
access or creates memory authority. No memory-specific judgment cache is used. Normal helpful-memory lookup
excludes retracted entries and rejected promotion proposals; an explicit authorized historical or self-learning
path may inspect retained history without turning it into current instructions. Completed work remains available
where the caller's current role and scope permit it.

If Jev fails, question-based lookup reports the actual timeout, provider error, input limit or other known
failure, never a fabricated negative or silent alternative search. An ordinary known-ID read through the
healthy application is a separate operation. If the authoritative database/application is unavailable, its
operations stop; there is no offline memory authority. Actual request ceilings, question definitions, cost,
recall, missed entries, scope isolation, source revisions and Pi/MCP delivery need qualification when planning
and implementing the consumer. Later external knowledge sources require their own decisions, not an implicit
memory permission.

## Decision closeout and map handoff

John approved this MCP/tool, lifecycle access and Jev judgment boundary on 2026-10-10. WF-024 through WF-026
are now closed. The map remains Active until the human approves a bounded EPIC destination or amendment to
existing planning and resolves or delegates its remaining fog. No runtime access, credential, provider call,
Agent, database record, EPIC, requirement TICKET or implementation TASK was created by this decision.
